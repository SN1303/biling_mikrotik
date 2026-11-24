<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  // Batal pembayaran tidak lagi menghapus transaksi: ditandai deleted_at + siapa yang membatalkan
  public function up(): void
  {
    Schema::table('lamtim_transaksis', function (Blueprint $table) {
      $table->string('batalOleh')->nullable()->after('keterangan');
      $table->softDeletes();
    });
  }

  public function down(): void
  {
    Schema::table('lamtim_transaksis', function (Blueprint $table) {
      $table->dropSoftDeletes();
      $table->dropColumn('batalOleh');
    });
  }
};
