<?php

use App\Http\Controllers\Master\UserController;
use App\Http\Controllers\Master\FotoController;

// Lihat data pelanggan: semua staf. Ubah data pelanggan (tambah/edit/hapus/mapping/foto/ekspor): hanya Admin (role 1, 2).
// Urutan route dipertahankan: route statis harus di atas wildcard /{id}.
Route::prefix('user')->as('user.')->controller(UserController::class)->group(function () {
  Route::get('/data/json', 'json')->name('json');
  Route::get('/list/mapping', 'mapping')->name('mapping')->middleware('role:1,2,4,6,7');
  Route::get('/data/mapping/json', 'jsonMapping')->name('mapping.json');
  Route::get('/edit/mapping/{id}', 'editMapping')->name('edit.mapping')->middleware('role:1,2');
  Route::post('/store/mapping', 'storeMapping')->name('mapping.store')->middleware('role:1,2');
  Route::get('/show/map', 'showMapping')->name('show.mapping');

  Route::get('/export/excel', 'export')->name('export')->middleware('role:1,2');

  Route::get('/', 'index')->name('index');
  Route::get('/create', 'create')->name('create')->middleware('role:1,2');
  Route::get('/generate-credentials', 'generateCredentials')->name('generate-credentials')->middleware('role:1,2');
  Route::match(['get', 'post'], '/extract-maps', 'extractMaps')->name('extract-maps')->middleware('role:1,2');
  Route::post('/', 'store')->name('store')->middleware('role:1,2');
  Route::get('/{id}/foto', 'showFoto')->name('foto');
  Route::get('/{id}/edit', 'edit')->name('edit')->middleware('role:1,2');
  Route::get('/{id}', 'show')->name('show');
  Route::post('/{id}', 'update')->name('update')->middleware('role:1,2');
  Route::delete('/{id}', 'destroy')->name('destroy')->middleware('role:1,2');
});

// Foto routes
Route::prefix('foto')->as('foto.')->controller(FotoController::class)->group(function () {
  Route::post('/upload', 'store')->name('store')->middleware('role:1,2');
  Route::get('/user/{id}', 'getByUser')->name('user');
  Route::delete('/{id}', 'destroy')->name('destroy')->middleware('role:1,2');
});
