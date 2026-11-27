<?php

namespace App\Console\Commands;

use App\Services\NotifikasiPpp;
use Illuminate\Console\Command;

class PppNotifikasi extends Command
{
  protected $signature = 'ppp:notifikasi';
  protected $description = 'Kirim ringkasan Telegram pelanggan PPPoE offline (lebih dari jeda) dan yang online lagi';

  public function handle(NotifikasiPpp $notifikasi): int
  {
    $teks = $notifikasi->jalankan();
    $this->info($teks === null ? 'Tidak ada yang dikirim.' : 'Terkirim.');
    return self::SUCCESS;
  }
}
