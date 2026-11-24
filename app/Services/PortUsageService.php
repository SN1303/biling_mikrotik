<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menghitung ulang sisa port ODP & ODC dari data koneksi yang sebenarnya.
 *
 * Kolom `portSisa` dulunya hanya counter yang ditambah/dikurangi manual di banyak
 * tempat (form ODP/ODC, peta, sinkron, hapus pelanggan, dll). Jalur yang lupa
 * memperbarui counter membuat angka "Terpakai" melenceng. Service ini menjadi
 * sumber kebenaran:
 *
 *   ODP terpakai = jumlah pelanggan dengan idOdp = ODP itu
 *                + jumlah ODP anak (lamtim_odps.idOdp = ODP itu)
 *   ODC terpakai = jumlah ODP yang terhubung langsung (lamtim_odps.idOdc = ODC itu)
 *
 *   portSisa = max(0, port - terpakai)
 */
class PortUsageService
{
    /** Penanda agar penghitungan ulang hanya dijadwalkan sekali per request */
    private static bool $scheduled = false;

    public static function recalculateAll(): void
    {
        try {
            DB::statement("
                UPDATE lamtim_odps o
                LEFT JOIN (
                    SELECT idOdp, COUNT(*) AS n
                    FROM lamtim_user_mikrotik_details
                    WHERE idOdp IS NOT NULL AND idOdp <> 0
                    GROUP BY idOdp
                ) pel ON pel.idOdp = o.id
                LEFT JOIN (
                    SELECT idOdp, COUNT(*) AS n
                    FROM lamtim_odps
                    WHERE idOdp IS NOT NULL AND idOdp <> 0
                    GROUP BY idOdp
                ) anak ON anak.idOdp = o.id
                SET o.portSisa = GREATEST(COALESCE(o.port, 0) - COALESCE(pel.n, 0) - COALESCE(anak.n, 0), 0)
            ");

            DB::statement("
                UPDATE lamtim_odcs d
                LEFT JOIN (
                    SELECT idOdc, COUNT(*) AS n
                    FROM lamtim_odps
                    WHERE idOdc IS NOT NULL AND idOdc <> 0
                    GROUP BY idOdc
                ) odp ON odp.idOdc = d.id
                SET d.portSisa = GREATEST(COALESCE(d.port, 0) - COALESCE(odp.n, 0), 0)
            ");
        } catch (\Throwable $e) {
            Log::error('Gagal menghitung ulang sisa port ODP/ODC: ' . $e->getMessage());
        }
    }

    /**
     * Jadwalkan penghitungan ulang di akhir request, setelah semua logika
     * tambah/kurang port yang lama selesai berjalan (supaya hasil akhirnya benar).
     */
    public static function scheduleRecalculate(): void
    {
        if (self::$scheduled) {
            return;
        }
        self::$scheduled = true;

        app()->terminating(function () {
            self::$scheduled = false;
            self::recalculateAll();
        });
    }
}
