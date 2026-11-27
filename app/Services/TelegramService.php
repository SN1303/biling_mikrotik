<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Kirim pesan teks ke satu chat Telegram (TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID). */
class TelegramService
{
  private const BATAS = 4096; // batas panjang satu pesan Telegram

  public function aktif(): bool
  {
    return (string) config('services.telegram.token') !== '' && (string) config('services.telegram.chat_id') !== '';
  }

  /** True hanya bila semua bagian terkirim. Tidak pernah melempar exception. */
  public function kirim(string $teks): bool
  {
    if (!$this->aktif()) {
      return false;
    }
    $url = 'https://api.telegram.org/bot' . config('services.telegram.token') . '/sendMessage';

    foreach ($this->pecah($teks) as $bagian) {
      try {
        $res = Http::asForm()->timeout(10)->post($url, ['chat_id' => (string) config('services.telegram.chat_id'), 'text' => $bagian]);
        if (!$res->successful() || !$res->json('ok')) {
          Log::warning('Telegram menolak pesan', ['status' => $res->status(), 'keterangan' => $res->json('description')]);
          return false;
        }
      } catch (\Throwable $e) {
        Log::warning('Telegram tidak terjangkau: ' . $e->getMessage());
        return false;
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
