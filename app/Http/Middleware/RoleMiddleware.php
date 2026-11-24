<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage: ->middleware('role:1,2') artinya hanya role 1 dan 2 yang boleh akses
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Akun yang dinonaktifkan saat sesinya masih terbuka langsung dikeluarkan
        if (auth()->check() && !auth()->user()->isActive) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda telah dinonaktifkan.']);
        }

        if (!auth()->check() || !in_array((int) auth()->user()->idRole, array_map('intval', $roles))) {
            abort(403, 'Akses ditolak');
        }

        return $next($request);
    }
}
