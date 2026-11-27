<?php

namespace App\Services;

use App\Models\Lamtim_mikrotik;
use App\Models\Lamtim_user_mikrotik_details;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

/**
 * Ringkasan Telegram pelanggan offline (lebih dari jeda, per ODP) dan online lagi.
 * Status koneksi dicatat oleh laporan MikroTik (PppEventController) dan pencocokan.
 */
class NotifikasiPpp
{
  public function __construct(private TelegramService $telegram, private MikrotikPpp $ppp)
  {
  }

  /** Kirim satu ringkasan bila ada isinya. @return string|null teks yang terkirim */
  public function jalankan(): ?string
  {
    if (!$this->telegram->aktif()) {
      return null;
    }

    $baru = $this->layak()->with(['user:id,name', 'odp:id,nama'])
      ->where('ppp_online', 0)->where('ppp_dikabari', 0)
      ->where('ppp_offline_sejak', '<=', now()->subMinutes((int) config('services.ppp_notif.jeda_menit', 10)))
      ->get();
    $baru = $this->buangSecretNonaktif($baru);
    $pulih = Lamtim_user_mikrotik_details::with('user:id,name')->where('ppp_online', 1)->where('ppp_dikabari', 1)->get();

    if ($baru->isEmpty() && $pulih->isEmpty()) {
      return null;
    }

    $teks = trim($this->blokOffline($baru) . "\n\n" . $this->blokPulih($pulih));
    if (!$this->telegram->kirim($teks)) {
      return null; // dicoba lagi menit berikutnya; penanda belum diubah
    }

    Lamtim_user_mikrotik_details::whereIn('id', $baru->pluck('id'))->update(['ppp_dikabari' => 1]);
    Lamtim_user_mikrotik_details::whereIn('id', $pulih->pluck('id'))->update(['ppp_dikabari' => 0, 'ppp_offline_sejak' => null]);
    return $teks;
  }

  /**
   * Cocokkan status dengan /ppp/active dan /ppp/secret: menutup laporan on-up/on-down yang
   * hilang, dan memberi tahu bila MikroTik tidak terjangkau (sekali) atau pulih (sekali).
   */
  public function cocokkan(int $idMikrotik): void
  {
    $kunci = "ppp-mikrotik-terjangkau:$idMikrotik";
    $nama = Lamtim_mikrotik::whereKey($idMikrotik)->value('nama') ?? "#$idMikrotik";

    try {
      [$aktif, $secret] = $this->ambil($idMikrotik);
    } catch (\RuntimeException $e) {
      // Pelanggan tidak diubah: putus ke MikroTik bukan berarti pelanggan offline
      if (Cache::get($kunci, true) && $this->telegram->kirim("⚠️ MikroTik $nama tidak terjangkau dari billing. Status pelanggan tidak bisa dipantau sampai MikroTik terjangkau lagi.")) {
        Cache::forever($kunci, false);
      }
      return;
    }
    if (!Cache::get($kunci, true) && $this->telegram->kirim("✅ MikroTik $nama terjangkau lagi. Pemantauan pelanggan berjalan normal.")) {
      Cache::forever($kunci, true);
    }

    $aktif = array_flip($aktif);
    // Pelanggan tanpa idMikrotik hanya bisa dipastikan bila MikroTik aktif cuma satu
    $satuMikrotik = Lamtim_mikrotik::where('isActive', 1)->count() <= 1;
    $pelanggan = Lamtim_user_mikrotik_details::whereNotNull('namaMikrotikUser')
      ->where(fn($q) => $q->where('idMikrotik', $idMikrotik)->when($satuMikrotik, fn($w) => $w->orWhereNull('idMikrotik')))
      ->get();

    foreach ($pelanggan as $p) {
      $ubah = [];
      if (isset($aktif[$p->namaMikrotikUser])) {
        if ($p->ppp_online !== 1 && $p->ppp_online !== true) {
          $ubah = ['ppp_online' => 1] + ($p->ppp_dikabari ? [] : ['ppp_offline_sejak' => null]);
        }
      } elseif ($p->ppp_online === null || $p->ppp_online) {
        $ubah = ['ppp_online' => 0, 'ppp_offline_sejak' => $p->ppp_offline_sejak ?? now()];
      }
      if (array_key_exists($p->namaMikrotikUser, $secret) && (bool) $p->ppp_secret_nonaktif !== $secret[$p->namaMikrotikUser]) {
        $ubah['ppp_secret_nonaktif'] = $secret[$p->namaMikrotikUser] ? 1 : 0;
      }
      if ($ubah) {
        $p->update($ubah);
      }
    }
  }

  /** Ambil daftar aktif dan secret; coba sekali lagi setelah jeda (mis. MikroTik sedang reboot). */
  private function ambil(int $idMikrotik): array
  {
    try {
      return [$this->ppp->aktif($idMikrotik), $this->ppp->secretNonaktif($idMikrotik)];
    } catch (\RuntimeException) {
      if (($detik = (int) config('services.ppp_notif.ulang_detik', 30)) > 0) {
        sleep($detik);
      }
      return [$this->ppp->aktif($idMikrotik), $this->ppp->secretNonaktif($idMikrotik)];
    }
  }

  /** Pelanggan yang boleh dikabari: akun aktif, tidak diisolir, secret aktif. */
  private function layak(): Builder
  {
    return Lamtim_user_mikrotik_details::query()
      ->whereHas('user', fn($q) => $q->where('isActive', 1))
      ->where(fn($q) => $q->where('statusIsolir', '!=', 1)->orWhereNull('statusIsolir'))
      ->where('ppp_secret_nonaktif', 0);
  }

  /**
   * Isolir pemilik dilakukan dengan menonaktifkan secret dari Winbox, yang tidak dilaporkan
   * ke billing. Cek dulu ke MikroTik; bila tidak terjangkau, lewati pengecekan.
   */
  private function buangSecretNonaktif(Collection $baru): Collection
  {
    foreach ($baru->whereNotNull('idMikrotik')->groupBy('idMikrotik') as $idMikrotik => $grup) {
      try {
        $nonaktif = $this->ppp->secretNonaktif((int) $idMikrotik);
      } catch (\RuntimeException) {
        continue;
      }
      $buang = $grup->filter(fn($p) => $nonaktif[$p->namaMikrotikUser] ?? false);
      Lamtim_user_mikrotik_details::whereIn('id', $buang->pluck('id'))->update(['ppp_secret_nonaktif' => 1]);
      $baru = $baru->reject(fn($p) => $buang->contains('id', $p->id));
    }
    return $baru->values();
  }

  private function blokOffline(Collection $baru): string
  {
    if ($baru->isEmpty()) {
      return '';
    }
    $ambang = (int) config('services.ppp_notif.ambang_massal', 3);
    $baris = ["🔴 Pelanggan offline ({$baru->count()})"];

    $perOdp = $baru->groupBy(fn($p) => $p->odp->nama ?? '')->sortKeys();
    if ($perOdp->has('')) { // "Tanpa ODP" paling akhir
      $perOdp = $perOdp->except('')->put('', $perOdp->get(''));
    }
    foreach ($perOdp as $namaOdp => $grup) {
      $baris[] = '';
      $idOdp = $grup->first()->idOdp;
      // Hitungan massal memakai semua yang sedang offline di ODP itu, termasuk yang sudah dikabari
      $offline = $idOdp ? $this->layak()->where('idOdp', $idOdp)->where('ppp_online', 0)->count() : 0;
      $total = $idOdp ? $this->layak()->where('idOdp', $idOdp)->count() : 0;
      $judul = $namaOdp === '' ? 'Tanpa ODP' : "ODP $namaOdp";
      $baris[] = $idOdp && $offline >= $ambang
        ? "⚠️ $judul: kemungkinan gangguan ($offline dari $total pelanggan offline)"
        : $judul;
      foreach ($grup->sortBy(fn($p) => $p->user->name ?? '') as $p) {
        $baris[] = '• ' . ($p->user->name ?? '-') . " ({$p->namaMikrotikUser}), " . $this->lama($p->ppp_offline_sejak);
      }
    }
    return implode("\n", $baris);
  }

  private function blokPulih(Collection $pulih): string
  {
    if ($pulih->isEmpty()) {
      return '';
    }
    $baris = ["🟢 Online lagi ({$pulih->count()})"];
    foreach ($pulih->sortBy(fn($p) => $p->user->name ?? '') as $p) {
      $baris[] = '• ' . ($p->user->name ?? '-') . " ({$p->namaMikrotikUser}), offline " . $this->lama($p->ppp_offline_sejak);
    }
    return implode("\n", $baris);
  }

  private function lama($sejak): string
  {
    $menit = $sejak ? (int) Carbon::parse($sejak)->diffInMinutes(now()) : 0;
    return $menit < 60 ? "$menit menit" : intdiv($menit, 60) . ' jam ' . ($menit % 60) . ' menit';
  }
}
