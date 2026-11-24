<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diskon bulanan sebelumnya hanya menyimpan "bulan", sehingga diskon bulan
     * November 2025 ikut terpakai lagi di November 2026, dst. Tambahkan kolom
     * "tahun" dan isi data lama dari tahun created_at.
     */
    public function up(): void
    {
        Schema::table('lamtim_diskons', function (Blueprint $table) {
            if (!Schema::hasColumn('lamtim_diskons', 'tahun')) {
                $table->integer('tahun')->nullable()->after('bulan');
                $table->index(['idUser', 'bulan', 'tahun'], 'lamtim_diskons_user_periode_idx');
            }
        });

        DB::table('lamtim_diskons')
            ->whereNull('tahun')
            ->update(['tahun' => DB::raw('YEAR(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('lamtim_diskons', function (Blueprint $table) {
            if (Schema::hasColumn('lamtim_diskons', 'tahun')) {
                $table->dropIndex('lamtim_diskons_user_periode_idx');
                $table->dropColumn('tahun');
            }
        });
    }
};
