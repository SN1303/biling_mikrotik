<?php

namespace App\Console\Commands;

use App\Services\TagihanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Membuat tagihan bulan berjalan untuk semua pelanggan aktif.
 * Dijadwalkan tiap tanggal 1 (lihat App\Console\Kernel). Aman dijalankan ulang:
 * pelanggan yang sudah punya tagihan bulan ini dilewati.
 */
class GenerateTagihanBulanan extends Command
{
  protected $signature = 'tagihan:generate';

  protected $description = 'Generate tagihan bulanan untuk semua pelanggan aktif';

  public function handle(TagihanService $service): int
  {
    $response = $service->generateTagihanBulanan();
    $hasil = $response['data'] ?? [];

    $ringkasan = sprintf(
      'Generate tagihan %s: dibuat %d (gratis %d), dilewati %d, gagal %d',
      now()->format('m/Y'),
      $hasil['inserted'] ?? 0,
      $hasil['gratis'] ?? 0,
      $hasil['skipped'] ?? 0,
      count($hasil['errors'] ?? [])
    );

    if (empty($response['success'])) {
      Log::error('Generate tagihan otomatis gagal: ' . ($response['message'] ?? '-'));
      $this->error($response['message'] ?? 'Generate tagihan gagal');
      return self::FAILURE;
    }

    Log::info($ringkasan, ['errors' => $hasil['errors'] ?? []]);
    $this->info($ringkasan);
    foreach ($hasil['errors'] ?? [] as $err) {
      $this->warn("  user {$err['user_id']}: {$err['message']}");
    }

    return self::SUCCESS;
  }
}
