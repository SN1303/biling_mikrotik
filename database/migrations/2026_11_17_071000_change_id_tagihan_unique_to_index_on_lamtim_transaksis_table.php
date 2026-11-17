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
        // Ubah unique index idTagihan menjadi index biasa agar 1 tagihan bisa dicicil berkali-kali
        Schema::table('lamtim_transaksis', function (Blueprint $table) {
            $table->dropForeign('lamtim_transaksis_ibfk_2');
            $table->dropUnique('idTagihan');
            $table->foreign('idTagihan', 'lamtim_transaksis_ibfk_2')
                ->references('id')
                ->on('lamtim_tagihans')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lamtim_transaksis', function (Blueprint $table) {
            $table->dropForeign('lamtim_transaksis_ibfk_2');
            $table->unique('idTagihan');
            $table->foreign('idTagihan', 'lamtim_transaksis_ibfk_2')
                ->references('id')
                ->on('lamtim_tagihans')
                ->onDelete('cascade');
        });
    }
};
