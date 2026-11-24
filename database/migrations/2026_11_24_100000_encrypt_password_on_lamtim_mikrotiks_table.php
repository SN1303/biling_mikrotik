<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Password admin router disimpan terenkripsi (APP_KEY), bukan teks biasa.
 * Model Lamtim_mikrotik memakai cast 'encrypted' untuk membaca/menulisnya.
 */
return new class extends Migration
{
  public function up(): void
  {
    // Hasil enkripsi jauh lebih panjang dari varchar(50)
    Schema::table('lamtim_mikrotiks', function (Blueprint $table) {
      $table->text('password')->nullable()->change();
    });

    foreach (DB::table('lamtim_mikrotiks')->select('id', 'password')->get() as $row) {
      if ($row->password === null || $row->password === '' || $this->isEncrypted($row->password)) {
        continue;
      }
      DB::table('lamtim_mikrotiks')->where('id', $row->id)->update([
        'password' => Crypt::encryptString($row->password),
      ]);
    }
  }

  public function down(): void
  {
    foreach (DB::table('lamtim_mikrotiks')->select('id', 'password')->get() as $row) {
      if ($row->password !== null && $this->isEncrypted($row->password)) {
        DB::table('lamtim_mikrotiks')->where('id', $row->id)->update([
          'password' => Crypt::decryptString($row->password),
        ]);
      }
    }

    Schema::table('lamtim_mikrotiks', function (Blueprint $table) {
      $table->string('password', 50)->nullable()->change();
    });
  }

  private function isEncrypted(string $value): bool
  {
    try {
      Crypt::decryptString($value);
      return true;
    } catch (DecryptException $e) {
      return false;
    }
  }
};
