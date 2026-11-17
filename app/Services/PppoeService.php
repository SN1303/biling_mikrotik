<?php

namespace App\Services;

use App\Models\Lamtim_odp;
use App\Models\Lamtim_user_mikrotik_details;
use Illuminate\Support\Str;

class PppoeService
{
    /**
     * Generate username dan password PPPoE berdasarkan nama pelanggan dan ODP (ID atau Nama ODP)
     * Aturan:
     * - Username: <nama_slug>@<nama_odp> (mengambil NAMA ODP, mempertahankan tanda strip -)
     * - Password: Sama persis dengan username (mempermudah teknisi lapangan)
     * - Anti-duplikasi otomatis: <nama_slug>2@<nama_odp>, dst.
     *
     * @param string $name
     * @param string|int|null $odpParam
     * @param int|null $excludeUserId
     * @return array{username: string, password: string}
     */
    public static function generateCredentials(string $name, $odpParam = null, ?int $excludeUserId = null): array
    {
        // Bersihkan nama menjadi slug tanpa spasi/simbol
        $slugName = Str::slug($name, '');
        if (empty($slugName)) {
            $slugName = 'user';
        }

        $odpName = 'odp';
        if (!empty($odpParam)) {
            if (is_numeric($odpParam)) {
                $odpRecord = Lamtim_odp::find($odpParam);
                if ($odpRecord) {
                    // Prioritaskan NAMA ODP (bukan kode)
                    $odpName = !empty($odpRecord->nama) ? $odpRecord->nama : $odpRecord->kode;
                }
            } else {
                // Cek kemungkinan ID terenkripsi atau teks nama langsung
                try {
                    $decrypted = decrypt($odpParam);
                    $odpRecord = Lamtim_odp::find($decrypted);
                    if ($odpRecord) {
                        $odpName = !empty($odpRecord->nama) ? $odpRecord->nama : $odpRecord->kode;
                    } else {
                        $odpName = (string) $odpParam;
                    }
                } catch (\Exception $e) {
                    // Cari berdasarkan nama atau kode ODP di database
                    $odpRecord = Lamtim_odp::where('nama', $odpParam)->orWhere('kode', $odpParam)->first();
                    if ($odpRecord) {
                        $odpName = !empty($odpRecord->nama) ? $odpRecord->nama : $odpRecord->kode;
                    } else {
                        $odpName = (string) $odpParam;
                    }
                }
            }
        }

        // Sanitasi NAMA ODP: huruf kecil, pertahankan pemisah strip (-)
        $cleanOdpName = Str::slug($odpName, '-');
        if (empty($cleanOdpName)) {
            $cleanOdpName = 'odp';
        }

        $baseUsername = "{$slugName}@{$cleanOdpName}";
        $candidate = $baseUsername;
        $counter = 2;

        // Cek duplikasi di database tabel lamtim_user_mikrotik_details
        while (true) {
            $query = Lamtim_user_mikrotik_details::where('namaMikrotikUser', $candidate);
            if ($excludeUserId) {
                $query->where('idUser', '!=', $excludeUserId);
            }

            if (!$query->exists()) {
                break;
            }

            $candidate = "{$slugName}{$counter}@{$cleanOdpName}";
            $counter++;
        }

        return [
            'username' => $candidate,
            'password' => $candidate,
        ];
    }
}
