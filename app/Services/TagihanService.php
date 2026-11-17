<?php

namespace App\Services;

use App\Models\Lamtim_tagihans;
use App\Models\Lamtim_transaksi;
use App\Repositories\TagihanRepository;
use App\Traits\ApiResponseTrait;
use App\Traits\DatabaseErrorHandlerTrait;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
      $deleted = $this->repository->deleteById($id);

      if ($deleted) {
        return $this->successResponse(null, 'Tagihan berhasil dihapus');
      } else {
        return $this->errorResponse('Tagihan tidak ditemukan atau sudah dihapus', [], 404);
      }
    } catch (\Exception $e) {
      return $this->errorResponse('Gagal menghapus tagihan', [$e->getMessage()]);
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
    $id = decrypt($id);
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

      return $this->errorResponse('Gagal membayar tagihan', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
      ], 500);
    }
  }


  /**
   * Batal pembayaran tagihan
   * Mengembalikan status tagihan menjadi belum lunas dan menghapus data transaksi
   *
   * @param string $encryptedId
   * @return \Illuminate\Http\JsonResponse
   */
  public function batalPembayaran($encryptedId)
  {
    $id = decrypt($encryptedId);
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

      // Cek apakah tagihan sudah dibayar
      if ($tagihan->statusBayar != 1) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('Tagihan ini belum dibayar', [], 400);
      }

      // Validasi user yang login
      if (!Auth::check()) {
        DB::rollBack();
        Cache::forget($lockKey);
        return $this->errorResponse('User tidak terautentikasi', [], 401);
      }

      $rawId = $tagihan->getRawOriginal('id');
      // Hapus data transaksi yang terkait dengan tagihan ini
      Lamtim_transaksi::where('idTagihan', $rawId)->delete();

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

      return $this->errorResponse('Gagal membatalkan pembayaran', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
      ], 500);
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
      return $this->successResponse(
        $result,
        'Generate tagihan bulanan selesai'
      );
    } catch (\Exception $e) {
      return $this->errorResponse('Terjadi error saat generate tagihan bulanan', [$e->getMessage()]);
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

      if ($request->status_bayar == 1) {
        // Tagihan Lunas & Cicilan Berjalan (Ada Uang Masuk)
        $query = $this->repository->getLunasDanCicilanJson($where);
      } else {
        // Tagihan Aktif (Belum Lunas)
        $where['statusBayar'] = 0;
        $query = $this->repository->getDatatablesJson($where);
      }

      $today = date('Y-m-d');

      // ✅ Hitung summary data SEBELUM filtering quick filter
      $summaryQuery = clone $query;
      $overdueItems = $summaryQuery->filter(function ($item) use ($today) {
        return !empty($item->tglJatuhTempo) && $item->tglJatuhTempo < $today;
      });
      $todayDueItems = $summaryQuery->filter(function ($item) use ($today) {
        return !empty($item->tglJatuhTempo) && $item->tglJatuhTempo === $today;
      });

      $summaryData = [
        'total_count' => $summaryQuery->count(),
        'total_unpaid' => $request->status_bayar == 1
          ? $summaryQuery->sum(fn($item) => $item->statusBayar == 1 ? (float)$item->total : (float)($item->terbayar ?? 0))
          : $summaryQuery->sum(fn($item) => max(0, (float)$item->total - (float)($item->terbayar ?? 0))),
        'total_harga' => $summaryQuery->sum('harga'),
        'total_ppn' => $summaryQuery->sum('ppn'),
        'total_diskon' => $summaryQuery->sum('diskon'),
        'overdue_count' => $overdueItems->count(),
        'overdue_total' => $overdueItems->sum(fn($item) => max(0, (float)$item->total - (float)($item->terbayar ?? 0))),
        'today_due_count' => $todayDueItems->count(),
        'lunas_count' => $summaryQuery->where('statusBayar', 1)->count(),
        'cicilan_count' => $summaryQuery->where('statusBayar', 0)->count(),
      ];
      $summaryData['potential_income'] = $summaryData['total_unpaid'];
      $summaryData['average_amount'] = $summaryData['total_count'] > 0 ?
        $summaryData['total_unpaid'] / $summaryData['total_count'] : 0;

      // Filter cepat status jatuh tempo (opsional dari tab)
      if (!empty($request->quick_filter)) {
        if ($request->quick_filter === 'overdue') {
          $query = $query->filter(function ($item) use ($today) {
            return !empty($item->tglJatuhTempo) && $item->tglJatuhTempo < $today;
          });
        } elseif ($request->quick_filter === 'today') {
          $query = $query->filter(function ($item) use ($today) {
            return !empty($item->tglJatuhTempo) && $item->tglJatuhTempo === $today;
          });
        } elseif ($request->quick_filter === 'upcoming') {
          $query = $query->filter(function ($item) use ($today) {
            return !empty($item->tglJatuhTempo) && $item->tglJatuhTempo > $today;
          });
        }
      }

      // Filter khusus tab halaman lunas: Semua vs Lunas Penuh vs Cicilan Berjalan
      if ($request->status_bayar == 1 && !empty($request->filter_lunas)) {
        if ($request->filter_lunas === 'lunas') {
          $query = $query->filter(fn($item) => $item->statusBayar == 1);
        } elseif ($request->filter_lunas === 'cicilan') {
          $query = $query->filter(fn($item) => $item->statusBayar == 0 && (float)($item->terbayar ?? 0) > 0);
        }
      }

      // ✅ TAMBAHAN: Untuk halaman tagihan lunas, ambil juga data tagihan belum bayar
      $unpaidSummary = [];
      if ($request->status_bayar == 1) { // Jika request untuk tagihan lunas
        $unpaidWhere = [];

        // Filter tahun yang sama jika ada
        if (!empty($request->tahun)) {
          $unpaidWhere['tahun'] = $request->tahun;
        }

        // Filter bulan yang sama jika ada
        if (!empty($request->bulan)) {
          $unpaidWhere['bulan'] = $request->bulan;
        }

        // Set status belum bayar
        $unpaidWhere['statusBayar'] = 0; // Ambil yang belum bayar

        $unpaidQuery = $this->repository->getDatatablesJson($unpaidWhere);
        $unpaidSummary = [
          'unpaid_count' => $unpaidQuery->count(),
          'unpaid_total' => $unpaidQuery->sum(fn($item) => max(0, (float)$item->total - (float)($item->terbayar ?? 0))),
          'unpaid_harga' => $unpaidQuery->sum('harga'),
          'unpaid_ppn' => $unpaidQuery->sum('ppn'),
          'unpaid_diskon' => $unpaidQuery->sum('diskon'),
        ];
      }

      $dataTable = DataTables::of($query)
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
            'tahun' => date('Y'),
            'status_bayar' => $request->status_bayar ?? 0,
            'has_unpaid_data' => !empty($unpaidSummary) // Flag untuk frontend
          ]
        ])
        ->make(true);

      return $dataTable;
    } catch (\Exception $e) {
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
        'error'   => $e->getMessage(),
      ], 500);
    }
  }
}
