<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Laporan PPPoE up/down dari skrip MikroTik (dilindungi header X-Notif-Token)
// Tanpa throttle api (60/menit per IP): semua laporan datang dari satu IP MikroTik, dan listrik berkedip
// bisa menghasilkan puluhan laporan sekaligus. Endpoint sudah dilindungi token.
Route::post('/mikrotik/ppp-event', \App\Http\Controllers\Api\PppEventController::class)->name('ppp-event')
  ->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class . ':api');
