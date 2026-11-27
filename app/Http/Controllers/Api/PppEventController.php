<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lamtim_user_mikrotik_details;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Laporan dari skrip on-up/on-down PPP Profile MikroTik (lihat SkripNotifPpp).
 * Hanya mencatat; pengiriman Telegram dilakukan command ppp:notifikasi setelah jeda.
 */
class PppEventController extends Controller
{
  public function __invoke(Request $request): JsonResponse
  {
    $secret = (string) config('services.ppp_notif.secret');
    if ($secret === '' || !hash_equals($secret, (string) $request->header('X-Notif-Token'))) {
      return response()->json(['ok' => false], 403);
    }

    // MikroTik tidak meminta JSON, jadi validasi gagal dibalas 422 langsung (bukan redirect)
    $validasi = Validator::make($request->all(), [
      'm' => 'required|integer',
      'e' => 'required|in:up,down',
      'u' => 'required|string|max:100',
    ]);
    if ($validasi->fails()) {
      return response()->json(['ok' => false, 'errors' => $validasi->errors()], 422);
    }
    $data = $validasi->validated();

    $pelanggan = Lamtim_user_mikrotik_details::where('namaMikrotikUser', $data['u'])
      ->where(fn($q) => $q->where('idMikrotik', $data['m'])->orWhereNull('idMikrotik'))
      ->get();

    foreach ($pelanggan as $p) {
      if ($data['e'] === 'down') {
        $p->update(['ppp_online' => 0, 'ppp_offline_sejak' => $p->ppp_offline_sejak ?? now()]);
      } else {
        // Yang sudah dikabari offline menyimpan jam offline-nya untuk pesan "online lagi"
        $p->update(['ppp_online' => 1] + ($p->ppp_dikabari ? [] : ['ppp_offline_sejak' => null]));
      }
    }

    return response()->json(['ok' => true]);
  }
}
