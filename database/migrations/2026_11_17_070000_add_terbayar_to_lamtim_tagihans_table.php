<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lamtim_tagihans', function (Blueprint $table) {
            if (!Schema::hasColumn('lamtim_tagihans', 'terbayar')) {
                $table->decimal('terbayar', 15, 2)->default(0)->after('total');
            }
        });

        // Set terbayar = total untuk tagihan yang sudah berstatus lunas sebelumnya
        DB::table('lamtim_tagihans')
            ->where('statusBayar', 1)
            ->update(['terbayar' => DB::raw('total')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lamtim_tagihans', function (Blueprint $table) {
            if (Schema::hasColumn('lamtim_tagihans', 'terbayar')) {
                $table->dropColumn('terbayar');
            }
        });
    }
};
