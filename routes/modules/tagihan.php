<?php

use App\Http\Controllers\Master\TagihanController;

Route::prefix('tagihan')->as('tagihan.')->controller(TagihanController::class)->group(function () {
  Route::get('/', 'index')->name('index');
  Route::get('/lunas', 'lunas')->name('lunas');

  // Khusus Super Admin, Admin, Bendahara (role 1, 2, 3)
  Route::middleware('role:1,2,3')->group(function () {
    Route::get('/rekap', 'rekap')->name('rekap');
    Route::get('/generate', 'generate')->name('generate');
    Route::post('/generate-tagihan-bulanan', 'generateTagihanBulanan')->name('generate-tagihan-bulanan');

    // Pembatalan pembayaran hanya oleh Super Admin, Admin, Bendahara
    Route::post('/batal-pembayaran/{id}', 'batalPembayaran')->name('batal-pembayaran');
  });

  // Hapus tagihan hanya Super Admin & Admin (role 1, 2)
  Route::middleware('role:1,2')->group(function () {
    Route::delete('/destroy/{id}', 'destroy')->name('destroy');
  });

  Route::get('/data/json', 'json')->name('json');

  // Kasir: semua role penagihan (1, 2, 3, 6, 7)
  Route::post('/bayar/{id}', 'bayar')->name('bayar');

  Route::get('/{id}', 'show')->name('show');
});
