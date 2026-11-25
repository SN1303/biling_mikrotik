<?php

/**
 * Tempat khusus untuk menyimpan dan mengelola query database.
 * Mengakses database (Model, Query Builder, dll)
 * Menyediakan data mentah ke Service
 * Menyembunyikan kompleksitas query dari controller
 */

namespace App\Repositories;

use App\Models\Lamtim_diskon;
use App\Models\Lamtim_tagihans;
use App\Models\Lamtim_transaksi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class TagihanRepository
{
  protected $model;

  public function __construct()
  {
    $this->model = new Lamtim_tagihans();
  }

  public function getAll()
  {
    return $this->model::all(); // atau with() kalau butuh relasi
  }

  public function create(array $data)
  {
    return $this->model::create($data);
  }
  public function update(array $data, string $id)
  {
    $mikrotik = $this->model::findOrFail($id);
    $mikrotik->update($data);
    return $mikrotik;
  }
  public function deleteById(int $id): bool
  {
    $tagihan = $this->model::find($id);

    if (!$tagihan) {
      return false;
    }

    return $tagihan->delete();
  }

  public function find($id)
  {
    return $this->model::find($id);
  }

  public function findByIdWithLock($id)
  {
    return $this->model->with('paket:id,nama', 'user:id,name')->where('id', $id)->lockForUpdate()->first();
  }


  /**
   * Query (belum dieksekusi) untuk tabel tagihan. Paging, cari & urut dikerjakan database.
   * $adaUangMasuk = true: lunas + cicilan berjalan (halaman Lunas); false: hanya pakai $where.
   */
  public function queryTagihan(array $where = [], bool $adaUangMasuk = false)
  {
    $query = $this->model::query()
      ->select('lamtim_tagihans.*')
      ->with([
        'paket:id,nama,price',
        'user:id,name,julukan,wa,email',
        'transaksis:id,idTagihan,idUser,total,metodeBayar,tglBayar,prosesNama,keterangan'
      ]);

    if ($adaUangMasuk) {
      $query->where(function ($q) {
        $q->where('statusBayar', 1)
          ->orWhere(fn ($sub) => $sub->where('statusBayar', 0)->where('terbayar', '>', 0));
      });
    }

    if (!empty($where)) {
      $query->where($where);
    }

    return $query;
  }

  /**
   * Prabayar: tagihan periode X jatuh tempo di bulan X juga, pada tanggal milik pelanggan
   * (hanya tanggalnya yang dipakai). Tanggal 29–31 dibatasi ke hari terakhir bulan pendek.
   */
  public static function jatuhTempo($tglPelanggan, Carbon $periode): Carbon
  {
    $hari = $tglPelanggan
      ? Carbon::parse($tglPelanggan)->day
      : config('billing.jatuh_tempo_default', 10);

    return $periode->copy()->startOfMonth()->day(min($hari, $periode->daysInMonth));
  }

  /**
   * PPN dihitung dari harga setelah diskon (DPP). Hasil: [ppn, total], total tidak pernah negatif.
   */
  public static function hitungTotal(float $harga, float $diskon, float $persenPpn): array
  {
    $dpp = max(0, $harga - $diskon);
    $ppn = round($dpp * $persenPpn / 100);

    return [$ppn, $dpp + $ppn];
  }

  public function generateTagihanBulananRepo()
  {
    $result = [
      'inserted' => 0,
      'gratis' => 0,
      'skipped' => 0,
      'errors' => [],
      //'processed_users' => [],
    ];

    $users = User::where('idRole', 5)
      ->where('isActive', 1)
      ->whereHas('user_mikrotik', function ($q) {
        $q->where('statusIsolir', 0);
      })
      ->whereHas('user_detail', function ($q) {
        $q->where('statusTagihan', 1)
          // pelanggan baru dilewati sampai bulan "mulai ditagih" (masa PSB)
          ->where(fn ($m) => $m->whereNull('mulaiTagih')->orWhere('mulaiTagih', '<=', Carbon::now()->startOfMonth()->toDateString()));
      })
      ->with(['user_mikrotik:id,idUser,idPaket,diskon'])
      ->with(['user_mikrotik.paket:id,idKategori,nama,price'])
      ->with(['user_mikrotik.paket.kategori:id,nama'])
      ->with(['user_detail:id,idUser,statusPpn,statusTagihan,tglJatuhTempo'])
      ->get(['id', 'name', 'isActive']);

    foreach ($users as $user) {

      try {
        $existingTagihan = $this->model::where('idUser', $user->id)
          ->where('bulan', Carbon::now()->month)
          ->where('tahun', Carbon::now()->year)
          ->first();
        // Diskon khusus bulan ini — harus cocok bulan DAN tahun, dan belum dihapus
        $getDiskonBulanIni = Lamtim_diskon::where('idUser', $user->id)
          ->where('bulan', Carbon::now()->month)
          ->where('tahun', Carbon::now()->year)
          ->whereNull('deleted_at')
          ->first();

        // Tentukan nilai diskon yang akan digunakan
        $nilaiDiskon = 0;
        if ($getDiskonBulanIni) {
          // Jika ada diskon bulan ini, gunakan nominal dari tabel diskon
          $nilaiDiskon = $getDiskonBulanIni->nominal;
        } else if (isset($user->user_mikrotik->diskon)) {
          // Jika tidak ada diskon bulan ini tapi ada diskon di user_mikrotik
          $nilaiDiskon = $user->user_mikrotik->diskon;
        } else {
          $nilaiDiskon = 0;
        }

        if ($existingTagihan) {
          $result['skipped']++;
          continue;
        }

        $now = Carbon::now();

        $hargaPaket = (float) ($user->user_mikrotik->paket->price ?? 0);

        // Pengaman: paket berbayar yang harganya 0 (mis. ter-reset oleh sinkron MikroTik
        // atau belum diatur) jangan sampai jadi tagihan "gratis/lunas" otomatis.
        $paketUser = $user->user_mikrotik->paket ?? null;
        $isKategoriGratis = $paketUser && (
          stripos((string) optional($paketUser->kategori)->nama, 'gratis') !== false
          || stripos((string) $paketUser->nama, 'gratis') !== false
        );
        if ($hargaPaket <= 0 && !$isKategoriGratis) {
          $result['errors'][] = [
            'user_id' => $user->id,
            'message' => 'Dilewati: harga paket "' . ($paketUser->nama ?? '-') . '" masih Rp 0. Atur harganya di menu Paket Internet.',
          ];
          continue;
        }

        // Opsi B: PPN Fleksibel per Pelanggan
        // PPN hanya dikenakan jika statusPpn pada profil pelanggan bernilai 1 (Aktif)
        $isPpnActive = (int) optional($user->user_detail)->statusPpn === 1;
        [$ppn, $finalTotal] = self::hitungTotal($hargaPaket, (float) $nilaiDiskon, $isPpnActive ? config('billing.ppn', 11) : 0);

        // Opsi B User Gratisan: Jika total <= 0, otomatis langsung berstatus LUNAS (Gratis)
        $isGratis = ($finalTotal <= 0);

        DB::beginTransaction();

        $tagihan = $this->model::create([
          'idUser' => $user->id,
          'idPaket' => $user->user_mikrotik->idPaket,
          'noTagihan' => strtoupper($user->id . '-' . $now->month . $now->year . '-' . uniqid()),
          'bulan' => $now->month,
          'tahun' => $now->year,
          'tglJatuhTempo' => self::jatuhTempo(optional($user->user_detail)->tglJatuhTempo, $now),
          'create_by' => Auth::id() ?? 1,
          'statusBayar' => $isGratis ? 1 : 0,
          'terbayar' => 0,
          'tglBayar' => $isGratis ? Carbon::now() : null,
          'prosesBy' => $isGratis ? (Auth::id() ?? 1) : null,
          'prosesNama' => $isGratis ? (Auth::check() ? Auth::user()->name : 'Sistem') : null,
          'metode' => $isGratis ? 'gratis' : null,
          'aksi' => $isGratis ? 'Gratis' : 'Penagihan',
          'harga' => $hargaPaket,
          'ppn' => $ppn,
          'diskon' => $nilaiDiskon,
          'total' => $finalTotal,
        ]);

        if ($isGratis) {
          $rawTagihanId = $tagihan->getRawOriginal('id');
          Lamtim_transaksi::create([
            'idUser' => $user->id,
            'idTagihan' => $rawTagihanId,
            'metodeBayar' => 'gratis',
            'namaPaket' => optional($user->user_mikrotik->paket)->nama ?? 'Paket Gratis',
            'tglBayar' => Carbon::now(),
            'prosesBy' => Auth::id() ?? 1,
            'prosesNama' => Auth::check() ? Auth::user()->name : 'Sistem',
            'harga' => $hargaPaket,
            'ppn' => $ppn,
            'diskon' => $nilaiDiskon,
            'total' => 0,
            'keterangan' => 'Paket Gratis / Bebas Biaya',
          ]);
          $result['gratis'] = ($result['gratis'] ?? 0) + 1;
        }

        DB::commit();
        $result['inserted']++;
        //$result['processed_users'][] = $user->id;
      } catch (QueryException $e) {
        DB::rollBack();
        // 23000 = pelanggaran unique (idUser, bulan, tahun): tagihan sudah dibuat
        // oleh proses generate lain yang berjalan bersamaan → anggap dilewati.
        if ($e->getCode() === '23000') {
          $result['skipped']++;
          continue;
        }
        $result['errors'][] = [
          'user_id' => $user->id,
          'message' => 'Gagal menyimpan tagihan',
        ];
        Log::error("Generate tagihan user {$user->id} gagal: " . $e->getMessage());
      } catch (\Exception $e) {
        if (DB::transactionLevel() > 0) DB::rollBack();
        $result['errors'][] = [
          'user_id' => $user->id,
          'message' => $e->getMessage(),
        ];
      }
    }

    return $result;
  }
}
