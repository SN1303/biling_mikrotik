<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu pelanggan hanya boleh punya satu tagihan per bulan/tahun.
     * Mencegah tagihan dobel ketika tombol generate diklik bersamaan.
     */
    public function up(): void
    {
        $duplikat = DB::table('lamtim_tagihans')
            ->select('idUser', 'bulan', 'tahun', DB::raw('COUNT(*) as jml'))
            ->groupBy('idUser', 'bulan', 'tahun')
            ->having('jml', '>', 1)
            ->get();

        if ($duplikat->isNotEmpty()) {
            $daftar = $duplikat->map(fn($d) => "user {$d->idUser} periode {$d->bulan}/{$d->tahun} ({$d->jml}x)")->implode('; ');
            throw new RuntimeException(
                "Tidak bisa menambah unique index: masih ada tagihan ganda. Bereskan dulu secara manual: {$daftar}"
            );
        }

        Schema::table('lamtim_tagihans', function (Blueprint $table) {
            $table->unique(['idUser', 'bulan', 'tahun'], 'lamtim_tagihans_user_periode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lamtim_tagihans', function (Blueprint $table) {
            $table->dropUnique('lamtim_tagihans_user_periode_unique');
        });
    }
};
