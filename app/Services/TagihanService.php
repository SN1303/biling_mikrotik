<?php

namespace App\Services;

use App\Models\Lamtim_paket;
use App\Models\Lamtim_tagihans;
use App\Models\Lamtim_transaksi;
use App\Models\User;
use App\Repositories\TagihanRepository;
use App\Traits\ApiResponseTrait;
use App\Traits\DatabaseErrorHandlerTrait;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;
use App\Utils\Logger;

class TagihanService
{
  use ApiResponseTrait, DatabaseErrorHandlerTrait;

  protected TagihanRepository $repository;

  public function __construct(TagihanRepository $repository)
  {
    $this->repository = $repository;
  }

  public function getAll()
  {
    try {
      $data = $this->repository->getAll();
      return $this->successResponse($data, 'Data tagihan berhasil diambil');
    } catch (\Exception $e) {
      return $this->errorResponse('Gagal mengambil data tagihan', [$e->getMessage()]);
    }
  }

  public function deleteByEncryptedId($encryptedId)
  {
    try {
      $id = decrypt($encryptedId);

      $tagihan = $this->repository->find($id);
      if (!$tagihan) {
        return $this->errorResponse('Tagihan tidak ditemukan atau sudah dihapus');
      }

      // Lindungi riwayat keuangan: tagihan yang sudah ada uang masuk tidak boleh dihapus
      // (transaksi ikut terhapus karena FK cascade). Batalkan pembayarannya dulu.
      $adaUangMasuk = (float)($tagihan->terbayar ?? 0) > 0
        || ($tagihan->statusBayar == 1 && (float)$tagihan->total > 0)
        || Lamtim_transaksi::where('idTagihan', $id)->where('total', '>', 0)->exists();
      if ($adaUangMasuk) {
        return $this->errorResponse('Tagihan ini sudah memiliki pembayaran. Batalkan pembayarannya terlebih dahulu sebelum menghapus.');
      }

      $deleted = $this->repository->deleteById($id);

      if ($deleted) {
        return $this->successResponse(null, 'Tagihan berhasil dihapus');
      } else {
        return $this->errorResponse('Tagihan tidak ditemukan atau sudah dihapus');
      }
    } catch (\Exception $e) {
      Log::error('Gagal menghapus tagihan: ' . $e->getMessage());
      return $this->errorResponse('Gagal menghapus tagihan');
    }
  }

  /**
   * Bayar tagihan
   * Menandai tagihan sebagai lunas.
   * Memperbarui status tagihan di database.
   * Mengembalikan respons sukses atau gagal.
   * @param  string  $id
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function bayarTagihan($id, $request)
  {
    // Idempotency: setiap pembukaan kasir membawa token unik. Jika request yang
    // sama terkirim dua kali (double-klik / retry jaringan), request kedua ditolak
    // sehingga uang tidak teralokasi dua kali.
    $token = (string) $request->input('request_token', '');
    $tokenKey = $token !== '' ? 'pay_token_' . sha1($token) : null;

    if ($tokenKey && !Cache::add($tokenKey, true, now()->addMinutes(30))) {
      return $this->errorResponse('Pembayaran ini sudah diproses sebelumnya (permintaan ganda dicegah). Silakan muat ulang data.');
    }

    $result = $this->prosesBayarTagihan($id, $request);

    // Jika gagal, lepaskan token agar kasir bisa mencoba lagi
    if ($tokenKey && empty($result['success'])) {
      Cache::forget($tokenKey);
    }

    return $result;
  }

  protected function prosesBayarTagihan($id, $request)
  {
    try {
      $id = decrypt($id);
    } catch (\Exception $e) {
      return $this->errorResponse('ID tagihan tidak valid');
    }
    // Validasi input
    if (empty($request->metode_bayar)) {
      return $this->errorResponse('Metode bayar harus diisi', [], 400);
    }

    $lockKey = "payment_process_{$id}";

    try {
      Cache::put($lockKey, true, 60);
      DB::beginTransaction();

      $tagihan = $this->repository->findByIdWithLock($id);

      if (!$tagihan) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Tagihan tidak ditemukan', [], 404);
      }

      $rawId = $tagihan->getRawOriginal('id');
      $idUser = $tagihan->idUser;

      if (!Auth::check()) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('User tidak terautentikasi', [], 401);
      }

      $metodeBayar = $request->metode_bayar;
      $keteranganUser = $request->keterangan ?? null;

      // =========================================================================
      // MODE A: BAYAR SEMUA TUNGGAKAN PELANGGAN (AKUMULASI FIFO)
      // =========================================================================
      if ($request->boolean('bayar_semua') === true) {
        $allUnpaid = Lamtim_tagihans::where('idUser', $idUser)
          ->where('statusBayar', 0)
          ->orderBy('tahun', 'asc')
          ->orderBy('bulan', 'asc')
          ->orderBy('id', 'asc')
          ->lockForUpdate()
          ->get();

        if ($allUnpaid->isEmpty()) {
          DB::rollBack();
          Cache::forget($lockKey);
          return $this->errorResponse('Tidak ada tagihan tertunggak untuk pelanggan ini', [], 400);
        }

        $totalSemuaSisa = $allUnpaid->sum(function ($b) {
          return max(0, (float)$b->total - (float)($b->terbayar ?? 0));
        });

        $nominalDiterima = !empty($request->nominal_bayar)
          ? (float)$request->nominal_bayar
          : (!empty($request->uang_diterima) ? (float)$request->uang_diterima : $totalSemuaSisa);

        if ($nominalDiterima <= 0) {
          DB::rollBack();
          Cache::forget($lockKey);
          return $this->errorResponse('Nominal pembayaran harus lebih besar dari 0', [], 400);
        }

        $sisaDana = $nominalDiterima;
        $processed = [];
        $totalAlokasi = 0;

        foreach ($allUnpaid as $bill) {
          if ($sisaDana <= 0) break;

          $bRawId = $bill->getRawOriginal('id');
          $bTotal = (float)$bill->total;
          $bTerbayar = (float)($bill->terbayar ?? 0);
          $bSisa = max(0, $bTotal - $bTerbayar);

          if ($bSisa <= 0) continue;

          $alokasi = min($sisaDana, $bSisa);
          $newTerbayar = $bTerbayar + $alokasi;
          $newSisa = max(0, $bTotal - $newTerbayar);
          $isBillLunas = ($newSisa <= 0);

          Lamtim_transaksi::create([
            'idUser' => $idUser,
            'idTagihan' => $bRawId,
            'metodeBayar' => $metodeBayar,
            'namaPaket' => optional($bill->paket)->nama ?? 'Paket Internet',
            'tglBayar' => Carbon::now(),
            'prosesBy' => Auth::id(),
            'prosesNama' => Auth::user()->name,
            'harga' => $bill->harga,
            'ppn' => $bill->ppn ?? 0,
            'diskon' => $bill->diskon ?? 0,
            'total' => $alokasi,
            'keterangan' => $keteranganUser ?? ($isBillLunas ? "Pelunasan Periode {$bill->bulan}/{$bill->tahun}" : "Cicilan Periode {$bill->bulan}/{$bill->tahun} (Sisa Rp " . number_format($newSisa, 0, ',', '.') . ")"),
          ]);

          $bill->update([
            'terbayar' => $newTerbayar,
            'statusBayar' => $isBillLunas ? 1 : 0,
            'tglBayar' => Carbon::now(),
            'prosesBy' => Auth::id(),
            'prosesNama' => Auth::user()->name,
            'metode' => $metodeBayar,
            'aksi' => $isBillLunas ? 'Pembayaran' : 'Cicilan',
            'updated_at' => Carbon::now(),
          ]);

          $processed[] = [
            'id' => encrypt($bRawId),
            'noTagihan' => $bill->noTagihan,
            'bulan' => $bill->bulan,
            'tahun' => $bill->tahun,
            'paket_nama' => optional($bill->paket)->nama,
            'alokasi' => $alokasi,
            'sisa' => $newSisa,
            'is_lunas' => $isBillLunas
          ];

          $totalAlokasi += $alokasi;
          $sisaDana -= $alokasi;
        }

        DB::commit();
        Cache::forget($lockKey);

        $namaPelanggan = optional($tagihan->user)->name ?? 'Pelanggan';
        Logger::payment(
          'Pembayaran Akumulasi Berhasil',
          "{$namaPelanggan} - Total Dibayar: Rp " . number_format($totalAlokasi) . ", Jumlah Tagihan: " . count($processed)
        );

        $kembalian = max(0, $nominalDiterima - $totalAlokasi);
        $sisaTunggakanAkhir = max(0, $totalSemuaSisa - $totalAlokasi);

        return $this->successResponse([
          'mode' => 'bayar_semua',
          'is_lunas' => ($sisaTunggakanAkhir <= 0),
          'total_dibayar' => $totalAlokasi,
          'uang_diterima' => $nominalDiterima,
          'kembalian' => $kembalian,
          'sisa_tunggakan' => $sisaTunggakanAkhir,
          'tagihan_lunas_count' => collect($processed)->where('is_lunas', true)->count(),
          'items' => $processed,
          'tagihan' => $tagihan->fresh(),
          'message' => 'Pembayaran akumulasi berhasil diproses!'
        ], 'Pembayaran akumulasi berhasil diproses');
      }

      // =========================================================================
      // MODE B: BAYAR 1 TAGIHAN SPESIFIK (LUNAS PENUH ATAU CICILAN SEBAGIAN)
      // =========================================================================
      if ($tagihan->statusBayar == 1) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Tagihan sudah lunas sebelumnya', [], 400);
      }

      $totalAwal = (float)$tagihan->total;
      $terbayarAwal = (float)($tagihan->terbayar ?? 0);
      $sisaSaatIni = max(0, $totalAwal - $terbayarAwal);

      if ($sisaSaatIni <= 0) {
        $tagihan->update(['statusBayar' => 1]);
        DB::commit();
        Cache::forget($lockKey);
        return $this->errorResponse('Tagihan sudah lunas', [], 400);
      }

      // Tentukan nominal yang disetor untuk tagihan ini
      $uangDiterima = !empty($request->nominal_bayar)
        ? (float)$request->nominal_bayar
        : (!empty($request->uang_diterima) ? (float)$request->uang_diterima : $sisaSaatIni);

      if ($uangDiterima <= 0) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Nominal pembayaran harus lebih besar dari 0', [], 400);
      }

      // Alokasi bayar tidak boleh melebihi sisa
      $alokasiBayar = min($uangDiterima, $sisaSaatIni);
      $newTerbayar = $terbayarAwal + $alokasiBayar;
      $newSisa = max(0, $totalAwal - $newTerbayar);
      $isLunas = ($newSisa <= 0);

      // Simpan transaksi kasir
      $arrayTransaksi = [
        'idUser' => $idUser,
        'idTagihan' => $rawId,
        'metodeBayar' => $metodeBayar,
        'namaPaket' => optional($tagihan->paket)->nama ?? 'Paket Internet',
        'tglBayar' => Carbon::now(),
        'prosesBy' => Auth::id(),
        'prosesNama' => Auth::user()->name,
        'harga' => $tagihan->harga,
        'ppn' => $tagihan->ppn ?? 0,
        'diskon' => $tagihan->diskon ?? 0,
        'total' => $alokasiBayar,
        'keterangan' => $keteranganUser ?? ($isLunas ? 'Pelunasan Tagihan' : "Cicilan (Sisa Rp " . number_format($newSisa, 0, ',', '.') . ")"),
      ];
      $transaksi = Lamtim_transaksi::create($arrayTransaksi);

      if (!$transaksi) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Gagal membuat transaksi', [], 500);
      }

      // Update status tagihan
      $tagihan->update([
        'terbayar' => $newTerbayar,
        'statusBayar' => $isLunas ? 1 : 0,
        'tglBayar' => Carbon::now(),
        'prosesBy' => Auth::id(),
        'prosesNama' => Auth::user()->name,
        'metode' => $metodeBayar,
        'aksi' => $isLunas ? 'Pembayaran' : 'Cicilan',
        'updated_at' => Carbon::now(),
      ]);

      DB::commit();
      Cache::forget($lockKey);

      $namaPelanggan = optional($tagihan->user)->name ?? 'Nama tidak diketahui';
      Logger::payment(
        $isLunas ? 'Pembayaran Berhasil' : 'Cicilan Berhasil',
        "{$namaPelanggan} - No: {$tagihan->noTagihan}, Metode: {$metodeBayar}, Bayar: Rp " . number_format($alokasiBayar) . ($isLunas ? ' (LUNAS)' : " (Sisa Rp " . number_format($newSisa) . ")")
      );

      $kembalian = max(0, $uangDiterima - $alokasiBayar);

      return $this->successResponse([
        'mode' => $isLunas ? 'lunas' : 'cicilan',
        'is_lunas' => $isLunas,
        'alokasi_bayar' => $alokasiBayar,
        'uang_diterima' => $uangDiterima,
        'kembalian' => $kembalian,
        'sisa' => $newSisa,
        'total_awal' => $totalAwal,
        'total_terbayar' => $newTerbayar,
        'tagihan' => $tagihan->fresh(),
        'transaksi' => $transaksi,
        'message' => $isLunas ? 'Tagihan berhasil dilunasi!' : 'Pembayaran cicilan berhasil dicatat!'
      ], $isLunas ? 'Tagihan berhasil dilunasi!' : 'Pembayaran cicilan berhasil dicatat!');
    } catch (QueryException $e) {
      DB::rollBack();
      Cache::forget($lockKey);

      return $this->handleDatabaseException($e, 'Gagal memproses pembayaran');
    } catch (\Exception $e) {
      DB::rollBack();
      Cache::forget($lockKey);

      Log::error('Gagal membayar tagihan: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
      return $this->errorResponse('Gagal membayar tagihan. Silakan coba lagi atau hubungi admin.');
    }
  }


  /**
   * Batal pembayaran tagihan
   * Mengembalikan status tagihan menjadi belum lunas dan menandai transaksinya dibatalkan (soft delete)
   *
   * @param string $encryptedId
   * @return \Illuminate\Http\JsonResponse
   */
  public function batalPembayaran($encryptedId)
  {
    try {
      $id = decrypt($encryptedId);
    } catch (\Exception $e) {
      return $this->errorResponse('ID tagihan tidak valid');
    }
    // Cek apakah ada proses pembatalan yang sedang berlangsung
    $lockKey = "cancel_payment_process_{$id}";
    if (Cache::has($lockKey)) {
      return $this->errorResponse('Pembatalan pembayaran sedang diproses, silakan tunggu', [], 423);
    }

    try {
      // Set lock untuk mencegah double process (2 menit timeout)
      Cache::put($lockKey, true, 120);

      // Mulai database transaction
      DB::beginTransaction();

      // Ambil tagihan dengan locking
      $tagihan = $this->repository->findByIdWithLock($id);

      if (!$tagihan) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Tagihan tidak ditemukan', [], 404);
      }

      // Tagihan gratis (total 0) tidak punya pembayaran untuk dibatalkan
      $isGratis = strtolower((string)$tagihan->metode) === 'gratis' || (float)$tagihan->total <= 0;
      if ($isGratis) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Tagihan gratis tidak memiliki pembayaran yang bisa dibatalkan');
      }

      // Boleh dibatalkan jika sudah lunas ATAU sudah ada cicilan masuk
      $adaPembayaran = $tagihan->statusBayar == 1 || (float)($tagihan->terbayar ?? 0) > 0;
      if (!$adaPembayaran) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Tagihan ini belum memiliki pembayaran');
      }

      // Validasi user yang login
      if (!Auth::check()) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('User tidak terautentikasi', [], 401);
      }

      $rawId = $tagihan->getRawOriginal('id');
      // Tandai transaksi sebagai dibatalkan (soft delete) agar jejaknya tetap tersimpan
      Lamtim_transaksi::where('idTagihan', $rawId)->update([
        'batalOleh' => Auth::user()->name,
        'deleted_at' => Carbon::now(),
      ]);

      // Update status tagihan kembali ke belum lunas dan reset terbayar = 0
      $updateResult = $tagihan->update([
        'tglBayar' => null,
        'terbayar' => 0,
        'statusBayar' => 0,
        'prosesBy' => Auth::user()->id,
        'prosesNama' => Auth::user()->name,
        'metode' => null,
        'aksi' => 'Pembatalan',
        'updated_at' => Carbon::now()
      ]);

      if (!$updateResult) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Gagal mengupdate status tagihan', [], 500);
      }
      $namaPelanggan = optional($tagihan->user)->name ?? 'Nama tidak diketahui';

      // LOG Payment dengan ID PELANGGAN
      Logger::payment(
        'Batal Pembayaran',
        "{$namaPelanggan} - No: {$tagihan->noTagihan}, Total: Rp " . number_format($tagihan->total),
      );

      // Commit transaction jika semua berhasil
      DB::commit();
      Cache::forget($lockKey);

      // Refresh data tagihan setelah update
      $tagihan->refresh();


      return $this->successResponse([
        'tagihan' => $tagihan,
      ], 'Pembayaran berhasil dibatalkan. Status tagihan dikembalikan ke belum lunas.');
    } catch (\Illuminate\Database\QueryException $e) {
      DB::rollBack();
      Cache::forget($lockKey);

      return $this->handleDatabaseException($e, 'Gagal membatalkan pembayaran');
    } catch (\Exception $e) {
      DB::rollBack();
      Cache::forget($lockKey);

      Log::error('Gagal membatalkan pembayaran: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
      return $this->errorResponse('Gagal membatalkan pembayaran. Silakan coba lagi atau hubungi admin.');
    }
  }

  /**
   * Generate Tagihan Bulanan
   * Menghasilkan tagihan bulanan untuk semua pengguna berdasarkan paket yang mereka gunakan.
   * Memastikan bahwa tagihan tidak digandakan untuk bulan yang sama.
   * Menghitung total tagihan termasuk pajak dan diskon.
   * Menyimpan tagihan ke database.
   */
  public function generateTagihanBulanan()
  {
    try {
      $result = $this->repository->generateTagihanBulananRepo();
      // Bulan/tahun baru harus langsung muncul di dropdown filter
      Cache::forget('tagihan_filter_data');
      return $this->successResponse(
        $result,
        'Generate tagihan bulanan selesai'
      );
    } catch (\Exception $e) {
      Log::error('Generate tagihan bulanan gagal: ' . $e->getMessage());
      return $this->errorResponse('Terjadi error saat generate tagihan bulanan');
    }
  }

  /**
   * Query untuk datatables
   * Memproses request dari datatables dan mengembalikan data dalam format yang sesuai.
   * Mendukung filter, sorting, dan pagination.
   * Menggunakan Yajra DataTables untuk mempermudah proses ini.
   * Menyediakan data yang sudah diformat untuk ditampilkan di frontend.
   *
   */
  /** Angka ringkasan tabel tagihan dalam satu query agregat (tanpa memuat baris). */
  private function ringkasanTagihan($query, string $today): object
  {
    $sisa = 'GREATEST(total - COALESCE(terbayar, 0), 0)';

    return $query->toBase()->cloneWithout(['columns', 'orders'])->selectRaw("
      COUNT(*) AS total_count,
      COALESCE(SUM(harga), 0) AS total_harga,
      COALESCE(SUM(ppn), 0) AS total_ppn,
      COALESCE(SUM(diskon), 0) AS total_diskon,
      COALESCE(SUM(CASE WHEN statusBayar = 1 THEN total ELSE COALESCE(terbayar, 0) END), 0) AS uang_masuk,
      COALESCE(SUM($sisa), 0) AS sisa,
      COALESCE(SUM(DATE(tglJatuhTempo) < ?), 0) AS overdue_count,
      COALESCE(SUM(CASE WHEN DATE(tglJatuhTempo) < ? THEN $sisa ELSE 0 END), 0) AS overdue_total,
      COALESCE(SUM(DATE(tglJatuhTempo) = ?), 0) AS today_due_count,
      COALESCE(SUM(statusBayar = 1), 0) AS lunas_count,
      COALESCE(SUM(statusBayar = 0), 0) AS cicilan_count,
      COALESCE(SUM(CASE WHEN statusBayar = 0 THEN COALESCE(terbayar, 0) ELSE 0 END), 0) AS cicilan_masuk
    ", [$today, $today, $today])->first();
  }

  public function getDatatablesJson($request)
  {
    try {
      $where = [];
      // filter tahun + bulan
      if (!empty($request->tahun)) {
        $where['tahun'] = $request->tahun;
      }
      if (!empty($request->bulan)) {
        $where['bulan'] = $request->bulan;
      }

      $isLunas = $request->status_bayar == 1;
      if (!$isLunas) {
        // Tagihan Aktif (Belum Lunas)
        $where['statusBayar'] = 0;
      }
      // Lunas: tagihan lunas & cicilan berjalan (ada uang masuk)
      $query = $this->repository->queryTagihan($where, $isLunas);

      $today = date('Y-m-d');

      // Ringkasan dihitung SEBELUM filter cepat, langsung di database (satu query)
      $s = $this->ringkasanTagihan($query, $today);
      $summaryData = [
        'total_count' => (int) $s->total_count,
        'total_unpaid' => (float) ($isLunas ? $s->uang_masuk : $s->sisa),
        'total_harga' => (float) $s->total_harga,
        'total_ppn' => (float) $s->total_ppn,
        'total_diskon' => (float) $s->total_diskon,
        'overdue_count' => (int) $s->overdue_count,
        'overdue_total' => (float) $s->overdue_total,
        'today_due_count' => (int) $s->today_due_count,
        'lunas_count' => (int) $s->lunas_count,
        'cicilan_count' => (int) $s->cicilan_count,
      ];
      $summaryData['potential_income'] = $summaryData['total_unpaid'];
      // Total uang cicilan yang sudah masuk dari tagihan yang belum lunas
      $summaryData['cicilan_masuk'] = (float) $s->cicilan_masuk;
      $summaryData['average_amount'] = $summaryData['total_count'] > 0 ?
        $summaryData['total_unpaid'] / $summaryData['total_count'] : 0;

      // Filter cepat status jatuh tempo (opsional dari tab)
      $operatorJatuhTempo = ['overdue' => '<', 'today' => '=', 'upcoming' => '>'][$request->quick_filter ?? ''] ?? null;
      if ($operatorJatuhTempo) {
        $query->whereDate('tglJatuhTempo', $operatorJatuhTempo, $today);
      }

      // Filter khusus tab halaman lunas: Semua vs Lunas Penuh vs Cicilan Berjalan
      if ($isLunas && $request->filter_lunas === 'lunas') {
        $query->where('statusBayar', 1);
      } elseif ($isLunas && $request->filter_lunas === 'cicilan') {
        $query->where('statusBayar', 0)->where('terbayar', '>', 0);
      }

      // Halaman lunas juga menampilkan ringkasan tagihan yang belum bayar
      $unpaidSummary = [];
      if ($isLunas) {
        $u = $this->ringkasanTagihan($this->repository->queryTagihan(array_merge(
          array_intersect_key($where, array_flip(['tahun', 'bulan'])),
          ['statusBayar' => 0]
        )), $today);
        $unpaidSummary = [
          'unpaid_count' => (int) $u->total_count,
          'unpaid_total' => (float) $u->sisa,
          'unpaid_harga' => (float) $u->total_harga,
          'unpaid_ppn' => (float) $u->total_ppn,
          'unpaid_diskon' => (float) $u->total_diskon,
        ];
      }

      // Kolom turunan (nama pelanggan / paket) dicari & diurutkan lewat relasi
      $namaUser = User::select('name')->whereColumn('users.id', 'lamtim_tagihans.idUser');
      $namaPaket = fn ($kolom) => Lamtim_paket::selectRaw($kolom)->whereColumn('lamtim_pakets.id', 'lamtim_tagihans.idPaket');

      $dataTable = DataTables::of($query)
        ->filterColumn('user_nama', function ($q, $keyword) {
          $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$keyword}%")->orWhere('julukan', 'like', "%{$keyword}%"));
        })
        ->filterColumn('paket_nama', function ($q, $keyword) {
          $q->whereHas('paket', fn ($p) => $p->where('nama', 'like', "%{$keyword}%"));
        })
        ->orderColumn('user_nama', fn ($q, $order) => $q->orderBy($namaUser, $order))
        // panjang dulu agar "PAKET-5M" < "PAKET-10M" (urutan natural, sama seperti sebelumnya)
        ->orderColumn('paket_nama', fn ($q, $order) => $q->orderBy($namaPaket('LENGTH(nama)'), $order)->orderBy($namaPaket('nama'), $order))
        ->addColumn('paket_nama', function ($row) {
          return optional($row->paket)->nama;
        })
        ->addColumn('terbayar', function ($row) {
          return (float) ($row->terbayar ?? 0);
        })
        ->addColumn('sisa', function ($row) {
          return max(0, (float)$row->total - (float)($row->terbayar ?? 0));
        })
        ->addColumn('user_nama', function ($row) {
          $user = optional($row->user);
          $nama = $user->name;
          $julukan = $user->julukan;

          return $julukan
            ? "{$nama} ({$julukan})"
            : $nama;
        })
        ->addColumn('user_wa', function ($row) {
          return optional($row->user)->wa;
        })
        ->addColumn('due_diff_days', function ($row) use ($today) {
          if (empty($row->tglJatuhTempo)) return null;
          $due = Carbon::parse($row->tglJatuhTempo)->startOfDay();
          $now = Carbon::parse($today)->startOfDay();
          return (int) $now->diffInDays($due, false); // < 0 jika overdue
        })
        ->addColumn('paket_harga', function ($row) {
          return optional($row->paket)->price;
        })
        ->addColumn('transaksi_list', function ($row) {
          return $row->transaksis ? $row->transaksis->map(function ($t) {
            return [
              'id' => $t->id,
              'total' => (float)$t->total,
              'metode' => $t->metodeBayar,
              'tglBayar' => $t->tglBayar ? (is_string($t->tglBayar) ? $t->tglBayar : $t->tglBayar->format('Y-m-d H:i:s')) : null,
              'prosesNama' => $t->prosesNama,
              'keterangan' => $t->keterangan
            ];
          })->values() : [];
        })
        ->addColumn('is_cicilan', function ($row) {
          return ($row->statusBayar == 0 && (float)($row->terbayar ?? 0) > 0);
        })
        ->addColumn('aksi', function ($row) {
          return $row->aksi ?? ($row->statusBayar == 1 ? 'Pembayaran' : 'Penagihan');
        })
        ->editColumn('created_at', function ($row) {
          return $row->created_at
            ? Carbon::parse($row->created_at)->format('d-m-Y H:i:s')
            : '-';
        })
        ->editColumn('status_bayar', function ($row) {
          return $row->statusBayar == 1 ? 'paid' : 'unpaid';
        })
        ->rawColumns(['paket_nama', 'paket_harga'])
        ->only([
          'id',
          'noTagihan',
          'bulan',
          'tahun',
          'status_bayar',
          'harga',
          'ppn',
          'diskon',
          'total',
          'terbayar',
          'sisa',
          'tglJatuhTempo',
          'tglBayar',
          'prosesNama',
          'metode',
          'aksi',
          'transaksi_list',
          'is_cicilan',
          'create_by',
          'updated_at',
          'created_at',
          'paket_nama',
          'paket_harga',
          'user_nama',
          'user_wa',
          'due_diff_days'
        ])
        // ✅ Tambahkan summary data + unpaid summary ke response DataTables
        ->with([
          'summary' => $summaryData,
          'unpaid_summary' => $unpaidSummary, // Data tagihan belum bayar
          'filter_info' => [
            'bulan' => $request->bulan,
            'tahun' => $request->tahun,
            'status_bayar' => $request->status_bayar ?? 0,
            'has_unpaid_data' => !empty($unpaidSummary) // Flag untuk frontend
          ]
        ])
        ->make(true);

      return $dataTable;
    } catch (\Exception $e) {
      Log::error('Gagal mengambil data datatables tagihan: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
      return response()->json([
        'draw' => intval($request->input('draw', 0)),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        // ✅ Summary kosong untuk error case
        'summary' => [
          'total_count' => 0,
          'total_unpaid' => 0,
          'potential_income' => 0,
          'total_harga' => 0,
          'total_ppn' => 0,
          'total_diskon' => 0,
          'average_amount' => 0
        ],
        'unpaid_summary' => [
          'unpaid_count' => 0,
          'unpaid_total' => 0,
          'unpaid_harga' => 0,
          'unpaid_ppn' => 0,
          'unpaid_diskon' => 0,
        ],
        'filter_info' => [
          'bulan' => $request->bulan ?? null,
          'tahun' => date('Y'),
          'status_bayar' => $request->status_bayar ?? 0,
          'has_unpaid_data' => false
        ],
        'success' => false,
        'message' => 'Gagal mengambil data datatables',
      ], 500);
    }
  }
}
