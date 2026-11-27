<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status koneksi PPPoE dari laporan MikroTik (on-up/on-down) untuk notifikasi Telegram.
 * Kolom `status` lama (isian manual peta: online/offline/isolir/active) sengaja tidak dipakai.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::table('lamtim_user_mikrotik_details', function (Blueprint $table) {
      $table->boolean('ppp_online')->nullable()->after('status');
      $table->timestamp('ppp_offline_sejak')->nullable()->after('ppp_online');
      $table->boolean('ppp_dikabari')->default(0)->after('ppp_offline_sejak');
      $table->boolean('ppp_secret_nonaktif')->default(0)->after('ppp_dikabari');
    });
  }

  public function down(): void
  {
    Schema::table('lamtim_user_mikrotik_details', function (Blueprint $table) {
      $table->dropColumn(['ppp_online', 'ppp_offline_sejak', 'ppp_dikabari', 'ppp_secret_nonaktif']);
    });
  }
};
