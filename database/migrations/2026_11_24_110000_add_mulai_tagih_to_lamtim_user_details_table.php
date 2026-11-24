<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  // Bulan pertama pelanggan ditagih (selalu tanggal 1). NULL = pelanggan lama, sudah ditagih.
  public function up(): void
  {
    Schema::table('lamtim_user_details', function (Blueprint $table) {
      $table->date('mulaiTagih')->nullable()->after('tglJatuhTempo');
    });
  }

  public function down(): void
  {
    Schema::table('lamtim_user_details', function (Blueprint $table) {
      $table->dropColumn('mulaiTagih');
    });
  }
};
