<?php

use App\Http\Controllers\Master\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->as('staff.')->controller(StaffController::class)->group(function () {
  Route::get('/json', 'json')->name('json');
  Route::get('/', 'index')->name('index');
  Route::post('/', 'store')->name('store');
  Route::get('/{id}', 'show')->name('show');
  Route::post('/{id}/update', 'update')->name('update');
  Route::post('/{id}/toggle-status', 'toggleStatus')->name('toggle-status');
  Route::post('/{id}/reset-password', 'resetPassword')->name('reset-password');
  Route::delete('/{id}/delete', 'destroy')->name('destroy');
});

