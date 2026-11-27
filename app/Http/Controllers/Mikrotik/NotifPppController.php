<?php

namespace App\Http\Controllers\Mikrotik;

use App\Http\Controllers\Controller;
use App\Models\Lamtim_mikrotik;
use App\Services\SkripNotifPpp;
use Illuminate\Http\JsonResponse;

/**
 * Pasang / cabut skrip laporan PPPoE (on-up/on-down) untuk notifikasi Telegram,
 * dan tampilkan skrip manual. Akses: role 1, 2 (grup route mikrotik).
 */
class NotifPppController extends Controller
{
  public function __construct(private SkripNotifPpp $skrip)
  {
  }

  public function pasang(string $id): JsonResponse
  {
    return $this->jalankan($id, fn(int $mt) => $this->skrip->pasang($mt), 'Skrip notifikasi terpasang di :n PPP Profile.');
  }

  public function cabut(string $id): JsonResponse
  {
    return $this->jalankan($id, fn(int $mt) => $this->skrip->cabut($mt), 'Skrip notifikasi dicabut dari :n PPP Profile.');
  }

  public function skrip(string $id): JsonResponse
  {
    if ($galat = $this->belumDiatur()) {
      return $galat;
    }
    $mt = $this->idMikrotik($id);
    return response()->json(['on_up' => $this->skrip->blok($mt, 'up'), 'on_down' => $this->skrip->blok($mt, 'down')]);
  }

  private function jalankan(string $id, callable $kerja, string $pesan): JsonResponse
  {
    if ($galat = $this->belumDiatur()) {
      return $galat;
    }
    try {
      $n = $kerja($this->idMikrotik($id));
    } catch (\RuntimeException $e) {
      return response()->json(['message' => $e->getMessage()], 503);
    }
    return response()->json(['message' => str_replace(':n', (string) $n, $pesan), 'jumlah' => $n]);
  }

  private function belumDiatur(): ?JsonResponse
  {
    return (string) config('services.ppp_notif.secret') === ''
      ? response()->json(['message' => 'Isi PPP_NOTIF_SECRET di file .env dulu (kode acak minimal 32 karakter).'], 422)
      : null;
  }

  private function idMikrotik(string $id): int
  {
    try {
      $idMikrotik = (int) decrypt($id);
    } catch (\Throwable) {
      abort(404);
    }
    return Lamtim_mikrotik::findOrFail($idMikrotik)->id;
  }
}
