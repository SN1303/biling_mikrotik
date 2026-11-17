<?php

namespace App\Services;

use App\Models\Lamtim_odp;
use App\Models\Lamtim_user_mikrotik_details;
use Illuminate\Support\Str;

class PppoeService
{
    /**
     * Generate username dan password PPPoE berdasarkan nama pelanggan dan ODP (ID atau Kode)
     * Aturan:
     * - Username: <nama_slug>@<kode_odp>
     * - Password: Sama persis dengan username (mempermudah teknisi lapangan)
     * - Anti-duplikasi otomatis: <nama_slug>2@<kode_odp>, dst.
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

        $odpCode = 'odp';
        if (!empty($odpParam)) {
            if (is_numeric($odpParam)) {
                $odpRecord = Lamtim_odp::find($odpParam);
                if ($odpRecord) {
                    $odpCode = !empty($odpRecord->kode) ? $odpRecord->kode : $odpRecord->nama;
                }
            } else {
                // Cek kemungkinan ID terenkripsi atau kode langsung
                try {
                    $decrypted = decrypt($odpParam);
                    $odpRecord = Lamtim_odp::find($decrypted);
                    if ($odpRecord) {
                        $odpCode = !empty($odpRecord->kode) ? $odpRecord->kode : $odpRecord->nama;
                    } else {
                        $odpCode = (string) $odpParam;
                    }
                } catch (\Exception $e) {
                    $odpCode = (string) $odpParam;
                }
            }
        }

        // Sanitasi kode ODP: huruf kecil, hanya alfanumerik atau strip
        $cleanOdpCode = Str::slug($odpCode, '');
        if (empty($cleanOdpCode)) {
            $cleanOdpCode = 'odp';
        }

        $baseUsername = "{$slugName}@{$cleanOdpCode}";
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

            $candidate = "{$slugName}{$counter}@{$cleanOdpCode}";
            $counter++;
        }

        return [
            'username' => $candidate,
            'password' => $candidate,
        ];
    }
}
