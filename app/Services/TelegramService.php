<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Kirim pesan teks ke satu chat Telegram (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID). */
class TelegramService
{
  // Batas Telegram 4096 dihitung dalam satuan UTF-16 (emoji = 2); 4000 karakter memberi ruang
  private const BATAS = 4000;

  public function aktif(): bool
  {
    return (string) config('services.telegram.token') !== '' && (string) config('services.telegram.chat_id') !== '';
  }

  /**
   * True bila bagian pertama terkirim: bagian berikut yang gagal hanya dicatat di log, supaya
   * pemanggil tidak mengirim ulang bagian yang sudah sampai tiap menit. Tidak pernah melempar exception.
   */
  public function kirim(string $teks): bool
  {
    if (!$this->aktif()) {
      return false;
    }
    $token = (string) config('services.telegram.token');
    $url = "https://api.telegram.org/bot$token/sendMessage";

    foreach ($this->pecah($teks) as $ke => $bagian) {
      try {
        $res = Http::asForm()->timeout(10)->post($url, ['chat_id' => (string) config('services.telegram.chat_id'), 'text' => $bagian]);
        $gagal = !$res->successful() || !$res->json('ok') ? 'Telegram menolak pesan: ' . $res->json('description') : null;
      } catch (\Throwable $e) {
        // Pesan exception memuat URL berisi token bot: jangan sampai masuk log
        $gagal = 'Telegram tidak terjangkau: ' . str_replace($token, '***', $e->getMessage());
      }
      if ($gagal) {
        Log::warning($gagal, ['bagian' => $ke + 1]);
        return $ke > 0;
      }
    }
    return true;
  }

  /** Pecah per baris supaya tiap bagian ≤ 4096 karakter. */
  private function pecah(string $teks): array
  {
    $bagian = [];
    $kini = '';
    foreach (explode("\n", $teks) as $baris) {
      $calon = $kini === '' ? $baris : "$kini\n$baris";
      if (mb_strlen($calon) > self::BATAS && $kini !== '') {
        $bagian[] = $kini;
        $calon = $baris;
      }
      $kini = mb_substr($calon, 0, self::BATAS);
    }
    if ($kini !== '') {
      $bagian[] = $kini;
    }
    return $bagian;
  }
}
