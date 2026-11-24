<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membersihkan sisa integrasi FreeRADIUS yang sudah tidak dipakai.
 *
 * Dihapus:
 *  - Tabel: radusergroup, radgroupreply, radreply, radcheck, nas, lamtim_radius_configs
 *  - Kolom: lamtim_user_mikrotik_details.radius_synced_at
 *  - Index idx_radius_username diganti nama menjadi idx_nama_mikrotik_user
 *    (index-nya tetap berguna untuk pencarian berdasarkan username PPPoE).
 *
 * TIDAK dihapus: kolom paket (speed_limit, ip_pool, address_list, burst_*, priority)
 * karena juga dipakai untuk sinkronisasi PPP Profile MikroTik.
 *
 * PENGAMAN: jika tabel `radacct` ada, berarti server FreeRADIUS sungguhan memakai
 * database ini untuk autentikasi/akuntansi. Migrasi dibatalkan agar koneksi
 * pelanggan tidak putus.
 */
return new class extends Migration
{
    private array $radiusTables = [
        'radusergroup',
        'radgroupreply',
        'radreply',
        'radcheck',
        'nas',
        'lamtim_radius_configs',
    ];

    public function up(): void
    {
        if (Schema::hasTable('radacct')) {
            throw new RuntimeException(
                'Tabel radacct ditemukan: server FreeRADIUS tampaknya masih memakai database ini. ' .
                'Pembersihan RADIUS dibatalkan. Pastikan MikroTik tidak lagi memakai RADIUS, ' .
                'lalu hapus/pindahkan tabel radacct secara manual sebelum menjalankan migrasi ini.'
            );
        }

        foreach ($this->radiusTables as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('lamtim_user_mikrotik_details')) {
            if (Schema::hasColumn('lamtim_user_mikrotik_details', 'radius_synced_at')) {
                Schema::table('lamtim_user_mikrotik_details', function (Blueprint $table) {
                    $table->dropColumn('radius_synced_at');
                });
            }

            if ($this->hasIndex('lamtim_user_mikrotik_details', 'idx_radius_username')) {
                Schema::table('lamtim_user_mikrotik_details', function (Blueprint $table) {
                    $table->renameIndex('idx_radius_username', 'idx_nama_mikrotik_user');
                });
            } elseif (!$this->hasIndex('lamtim_user_mikrotik_details', 'idx_nama_mikrotik_user')) {
                Schema::table('lamtim_user_mikrotik_details', function (Blueprint $table) {
                    $table->index('namaMikrotikUser', 'idx_nama_mikrotik_user');
                });
            }
        }

        // Hapus catatan migrasi RADIUS lama yang file-nya sudah dihapus dari repo
        DB::table('migrations')->whereIn('migration', [
            '2026_04_10_013156_create_lamtim_radius_configs_table',
            '2026_04_14_105945_create_freeradius_tables',
            '2026_05_06_041811_add_radius_tracking_to_user_mikrotik_details',
            '2026_05_06_042200_add_indexes_to_radius_tables',
        ])->delete();
    }

    /**
     * Tabel RADIUS tidak dibuat ulang (integrasi sudah dihapus dari kode).
     * Hanya mengembalikan nama index.
     */
    public function down(): void
    {
        if ($this->hasIndex('lamtim_user_mikrotik_details', 'idx_nama_mikrotik_user')) {
            Schema::table('lamtim_user_mikrotik_details', function (Blueprint $table) {
                $table->renameIndex('idx_nama_mikrotik_user', 'idx_radius_username');
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
