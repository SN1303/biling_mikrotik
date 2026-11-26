<?php

use App\Http\Controllers\Master\OntController;

// ONT pelanggan lewat GenieACS. Dimuat di grup role:1,2,4,7 (Admin, Teknisi, Teknisi-Kolektor).
Route::controller(OntController::class)->as('ont.')->group(function () {
  Route::get('/pelanggan/{id}/ont', 'pelanggan')->name('pelanggan');
  Route::post('/pelanggan/{id}/ont/refresh', 'refresh')->name('refresh');
  Route::post('/pelanggan/{id}/ont/reboot', 'reboot')->name('reboot');
  Route::post('/pelanggan/{id}/ont/wifi', 'wifi')->name('wifi');
  Route::get('/ont/belum-terhubung', 'belum')->name('belum');
  Route::post('/ont/hubungkan', 'hubungkan')->name('hubungkan');
});
