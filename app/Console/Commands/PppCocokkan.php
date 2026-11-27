<?php

namespace App\Console\Commands;

use App\Models\Lamtim_mikrotik;
use App\Services\NotifikasiPpp;
use Illuminate\Console\Command;

class PppCocokkan extends Command
{
  protected $signature = 'ppp:cocokkan';
  protected $description = 'Cocokkan status PPPoE pelanggan dengan MikroTik (menutup laporan on-up/on-down yang hilang)';

  public function handle(NotifikasiPpp $notifikasi): int
  {
    foreach (Lamtim_mikrotik::where('isActive', 1)->pluck('id') as $id) {
      $notifikasi->cocokkan((int) $id);
    }
    return self::SUCCESS;
  }
}
