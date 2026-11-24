<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // Sisa port ODP/ODC dihitung ulang dari data sebenarnya setiap kali ada
    // perubahan pelanggan / ODP / ODC (lihat App\Services\PortUsageService).
    $recalc = fn () => \App\Services\PortUsageService::scheduleRecalculate();
    foreach ([
      \App\Models\Lamtim_user_mikrotik_details::class,
      \App\Models\Lamtim_odp::class,
      \App\Models\Lamtim_odc::class,
      \App\Models\User::class, // hapus pelanggan → detail ikut terhapus via FK cascade
    ] as $model) {
      $model::saved($recalc);
      $model::deleted($recalc);
    }
  }
}
