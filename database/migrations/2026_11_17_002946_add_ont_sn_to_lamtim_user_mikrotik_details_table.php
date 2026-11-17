<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lamtim_user_mikrotik_details', function (Blueprint $table) {
            $table->string('ont_sn', 100)->nullable()->after('wifi_name')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lamtim_user_mikrotik_details', function (Blueprint $table) {
            $table->dropColumn('ont_sn');
        });
    }
};
