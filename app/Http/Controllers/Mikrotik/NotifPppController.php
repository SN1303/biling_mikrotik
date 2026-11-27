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

  /** Pengaturan yang salah menghasilkan skrip yang tidak pernah sampai ke billing; tolak dulu. */
  private function belumDiatur(): ?JsonResponse
  {
    $secret = (string) config('services.ppp_notif.secret');
    // Huruf/angka saja: http-header-field RouterOS dipisah koma, dan secret pendek mudah ditebak
    if (strlen($secret) < 32 || !ctype_alnum($secret)) {
      return response()->json(['message' => 'Isi PPP_NOTIF_SECRET di file .env: huruf/angka acak, minimal 32 karakter.'], 422);
    }
    $host = parse_url((string) config('services.ppp_notif.url'), PHP_URL_HOST);
    if (!$host || in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
      return response()->json(['message' => 'Isi PPP_NOTIF_URL di file .env dengan alamat billing yang bisa dijangkau MikroTik (mis. http://172.1.10.20).'], 422);
    }
    return null;
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
