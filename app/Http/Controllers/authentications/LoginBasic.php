<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginBasic extends Controller
{
  /** Maksimal percobaan login gagal per (email + IP) sebelum dikunci sementara */
  private const MAX_ATTEMPTS = 5;
  private const DECAY_SECONDS = 60;

  public function index()
  {
    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.authentications.auth-login-basic', ['pageConfigs' => $pageConfigs]);
  }

  public function login(Request $request)
  {
    // Validasi input form (ValidationException ditangani otomatis oleh Laravel)
    $credentials = $request->validate([
      'email' => ['required', 'email'],
      'password' => ['required'],
    ]);

    $throttleKey = Str::lower($request->input('email')) . '|' . $request->ip();

    if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
      $seconds = RateLimiter::availableIn($throttleKey);
      throw ValidationException::withMessages([
        'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
      ]);
    }

    try {
      // Apakah checkbox 'remember me' dicentang?
      $remember = $request->has('remember');

      // Akun nonaktif tidak boleh login
      if (Auth::attempt([...$credentials, 'isActive' => 1], $remember)) {
        // Pelanggan (role 5) belum punya portal; jangan beri akses ke dashboard staf
        if (Auth::user()->isPelanggan()) {
          Auth::logout();
          RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
          return back()->withErrors([
            'email' => 'Portal pelanggan belum tersedia.',
          ])->withInput($request->only('email', 'remember'));
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate(); // Hindari session fixation
        return redirect()->intended('/');  // Ke halaman yang diminta sebelum login
      }

      RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

      return back()->withErrors([
        'email' => 'Email atau password salah.',
      ])->withInput($request->only('email', 'remember'));
    } catch (\Throwable $e) {
      report($e);
      return back()->withErrors([
        'email' => 'Terjadi kesalahan saat login. Silakan coba lagi nanti.',
      ])->withInput($request->only('email', 'remember'));
    }
  }

  public function logout(Request $request)
  {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
  }
}
