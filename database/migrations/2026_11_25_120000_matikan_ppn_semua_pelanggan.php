<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PPN dimatikan untuk semua pelanggan dan jadi bawaan "tidak". Pelanggan yang memang kena PPN
 * diaktifkan lagi satu per satu lewat Edit Pelanggan. Tagihan yang sudah dibuat tidak diubah.
 */
return new class extends Migration
{
  public function up(): void
  {
    DB::table('lamtim_user_details')->update(['statusPpn' => 0]);

    Schema::table('lamtim_user_details', function (Blueprint $table) {
      $table->boolean('statusPpn')->nullable()->default(0)->change();
    });
  }

  // Status PPN lama per pelanggan tidak disimpan, jadi tidak bisa dikembalikan otomatis
  public function down(): void {}
};
