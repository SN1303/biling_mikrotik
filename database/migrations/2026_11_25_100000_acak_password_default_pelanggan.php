<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
  // Pelanggan lama yang masih memakai password default yang bisa ditebak diberi password acak.
  // Password yang diset admin sendiri tidak disentuh.
  public function up(): void
  {
    DB::table('users')->where('idRole', 5)->select('id', 'password')->orderBy('id')
      ->each(function ($u) {
        if (Hash::check('123456789abc', $u->password) || Hash::check('password', $u->password)) {
          DB::table('users')->where('id', $u->id)->update(['password' => Hash::make(Str::random(32))]);
        }
      });
  }

  // Tidak bisa dikembalikan: password lama sengaja dibuang.
  public function down(): void
  {
  }
};
