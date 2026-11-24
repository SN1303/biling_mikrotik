<?php

use App\Http\Controllers\Select\SelectController;

// Berisi password PPPoE pelanggan: hanya role yang mengurus mapping jaringan (Admin, Teknisi, Teknisi & Kolektor)
Route::prefix('select')->as('select.')->controller(SelectController::class)->middleware('role:1,2,4,7')->group(function () {
  Route::get('/secret-api/{id}', 'SelectSecretApiMikrotik')->name('secret.api');
});
