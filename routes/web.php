<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\pages\HomePage;
use App\Http\Controllers\authentications\LoginBasic;


// Main Page Route
// Bisa diakses tanpa login (public)
Route::get('/auth/login', [LoginBasic::class, 'index'])->name('login');

// Proses login
Route::post('/auth/proses-login', [LoginBasic::class, 'login'])->name('auth.login');
// Logout
Route::post('/auth/logout', [LoginBasic::class, 'logout'])->name('logout');

// Hanya staf yang sudah login (pelanggan role 5 tidak termasuk)
Route::middleware(['auth', 'role:1,2,3,4,6,7'])->group(function () {
  Route::get('/', [HomePage::class, 'index'])->name('pages-home');

  // Super Admin only (role 1) — manajemen akun staf & pengguna internal
  Route::middleware('role:1')->group(function () {
    require __DIR__ . '/modules/staff.php';
  });

  // Super Admin + Admin only (role 1,2) — server management, internet packages
    Route::middleware('role:1,2')->group(function () {
      require __DIR__ . '/modules/mikrotik.php';
      require __DIR__ . '/modules/olt.php';
      require __DIR__ . '/modules/kategori.php';
      require __DIR__ . '/modules/paket.php';
      require __DIR__ . '/modules/ip-pool.php';
      require __DIR__ . '/modules/address-list.php';
      require __DIR__ . '/modules/settings.php';
    });

  // Admin + Teknisi + Teknisi-Kolektor (role 1,2,4,7) — network devices, mapping, pelanggan CRUD
  Route::middleware('role:1,2,4,7')->group(function () {
    require __DIR__ . '/modules/odc.php';
    require __DIR__ . '/modules/odp.php';
    require __DIR__ . '/modules/google-map.php';
    require __DIR__ . '/modules/mapping.php';
    require __DIR__ . '/modules/area.php';
    require __DIR__ . '/modules/ont.php';
  });

  // Admin + Teknisi + Bendahara + Kolektor + Teknisi-Kolektor (role 1,2,3,4,6,7) — pelanggan
  Route::middleware('role:1,2,3,4,6,7')->group(function () {
    require __DIR__ . '/modules/user.php';
    require __DIR__ . '/modules/select.php';
  });

  // Admin + Bendahara + Kolektor + Teknisi-Kolektor (role 1,2,3,6,7) — billing/tagihan
  Route::middleware('role:1,2,3,6,7')->group(function () {
    require __DIR__ . '/modules/tagihan.php';
  });
});
