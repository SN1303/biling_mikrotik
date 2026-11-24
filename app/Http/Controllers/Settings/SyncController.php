<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Lamtim_kategori;
use App\Models\Lamtim_paket;
use App\Models\Lamtim_mikrotik;
use App\Models\Lamtim_user_mikrotik_details;
use App\Services\MikrotikMulti;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    protected $mikrotikService;

    public function __construct(MikrotikService $mikrotikService)
    {
        $this->mikrotikService = $mikrotikService;
    }

    /**
     * Uji login ke MikroTik dulu agar error asli (password salah, router mati, dll)
     * bisa ditampilkan ke user, bukan sekadar "gagal" / "berhasil 0 data".
     * Return null jika sukses, atau pesan error jika gagal.
     */
    private function cekKoneksiMikrotik($idMikrotik): ?string
    {
        $mikrotik = Lamtim_mikrotik::find($idMikrotik);
        if (!$mikrotik) {
            return 'Server MikroTik tidak ditemukan.';
        }

        try {
            MikrotikMulti::connectionMikrotik($idMikrotik);
            return null;
        } catch (\Throwable $e) {
            $pesan = $e->getMessage();
            if (stripos($pesan, 'user name or password') !== false) {
                return "Login ke MikroTik \"{$mikrotik->nama}\" ({$mikrotik->ip}) ditolak: username/password salah. " .
                    'Perbarui di menu Jaringan → MikroTik → Server MikroTik.';
            }
            return "Tidak bisa terhubung ke MikroTik \"{$mikrotik->nama}\" ({$mikrotik->ip}:{$mikrotik->port}): {$pesan}";
        }
    }

    public function index()
    {
        $mikrotiks = Lamtim_mikrotik::where('isActive', 1)->get();
        $kategoris = Lamtim_kategori::where('isActive', 1)->get();
        return view('content.settings.sync', compact('mikrotiks', 'kategoris'));
    }

    public function syncKategori(Request $request)
    {
        $request->validate(['id_mikrotik' => 'required|integer']);
        
        try {
            // Pastikan minimal ada satu kategori jika kosong
            $exists = Lamtim_kategori::count();
            if ($exists === 0) {
                Lamtim_kategori::create([
                    'nama' => 'UMUM',
                    'description' => 'Kategori default sistem',
                    'isActive' => 1
                ]);
            }

            $kategoris = Lamtim_kategori::where('isActive', 1)->get();

            return response()->json([
                'status' => true, 
                'message' => "Berhasil memuat data kategori.",
                'data' => $kategoris
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function syncPaket(Request $request)
    {
        $request->validate([
            'id_mikrotik' => 'required|integer',
            'id_kategori' => 'required|integer'
        ]);

        set_time_limit(300); // 5 menit
        
        try {
            if ($err = $this->cekKoneksiMikrotik($request->id_mikrotik)) {
                return response()->json(['status' => false, 'message' => $err]);
            }

            $profiles = MikrotikMulti::commandApi2($request->id_mikrotik, '/ppp/profile/print');

            if ($profiles === null) {
                return response()->json(['status' => false, 'message' => 'Gagal mengambil data PPP Profile dari MikroTik. Cek storage/logs/laravel.log.']);
            }
            if (count($profiles) === 0) {
                return response()->json(['status' => false, 'message' => 'Tidak ada PPP Profile di MikroTik ini.']);
            }

            $idKategoriDefault = (int) $request->id_kategori;
            $kategoriGratis = Lamtim_kategori::where('isActive', 1)
                ->where('nama', 'like', '%gratis%')
                ->first();

            $baru = [];
            $diperbarui = 0;

            foreach ($profiles as $p) {
                $name = $p['name'] ?? null;
                if (!$name || $name == 'default' || $name == 'default-encryption') continue;

                $paket = Lamtim_paket::where('nama', $name)->first();

                if ($paket) {
                    // Paket sudah ada: HANYA perbarui data teknis dari MikroTik.
                    // Harga, kategori, kode & status aktif yang diatur admin TIDAK ditimpa
                    // (dulu harga di-reset ke 0 → tagihan pelanggan jadi Rp 0 / "gratis").
                    $paket->update([
                        'speed_limit' => $p['rate-limit'] ?? $paket->speed_limit,
                        'ip_pool' => $p['remote-address'] ?? $paket->ip_pool,
                    ]);
                    $diperbarui++;
                    continue;
                }

                // Paket baru: profil bernama "gratis" otomatis ke kategori Gratis,
                // selain itu ke kategori yang dipilih.
                $idKategori = ($kategoriGratis && stripos($name, 'gratis') !== false)
                    ? $kategoriGratis->id
                    : $idKategoriDefault;

                Lamtim_paket::create([
                    'idKategori' => $idKategori,
                    'kode' => strtoupper(str_replace(' ', '_', $name)),
                    'nama' => $name,
                    'price' => 0,
                    'description' => "Profil MikroTik: $name",
                    'speed_limit' => $p['rate-limit'] ?? null,
                    'ip_pool' => $p['remote-address'] ?? null,
                    'isActive' => 1,
                ]);
                $baru[] = $name;
            }

            $pesan = "Sinkron selesai: " . count($baru) . " paket baru, {$diperbarui} paket lama diperbarui (harga & kategori tidak diubah).";
            if (count($baru) > 0) {
                $pesan .= " PERHATIAN: paket baru (" . implode(', ', $baru) . ") masih Rp 0 — atur harganya di menu Paket Internet sebelum generate tagihan.";
            }

            return response()->json(['status' => true, 'message' => $pesan]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Sync Jaringan (IP Pool & Address List)
     */
    public function syncJaringan(Request $request)
    {
        $request->validate(['id_mikrotik' => 'required|integer']);
        
        try {
            $idMikrotik = $request->id_mikrotik;
            $countPool = 0;
            $countAddr = 0;

            // Jangan laporkan "berhasil 0 data" kalau sebenarnya login ke router gagal
            if ($err = $this->cekKoneksiMikrotik($idMikrotik)) {
                return response()->json(['status' => false, 'message' => $err]);
            }

            // 1. Sync IP Pool
            $pools = MikrotikMulti::commandApi2($idMikrotik, '/ip/pool/print');
            if (is_array($pools)) {
                foreach ($pools as $p) {
                    $name = $p['name'] ?? null;
                    if (!$name) continue;

                    \App\Models\Lamtim_ip_pool::updateOrCreate(
                        ['name' => $name],
                        [
                            'ranges' => $p['ranges'] ?? '',
                            'next_pool' => $p['next-pool'] ?? null
                        ]
                    );
                    $countPool++;
                }
            }

            // 2. Sync Address List (Get unique list names)
            $addressLists = MikrotikMulti::commandApi2($idMikrotik, '/ip/firewall/address-list/print');
            if (is_array($addressLists)) {
                $uniqueLists = collect($addressLists)->pluck('list')->unique()->filter();
                foreach ($uniqueLists as $listName) {
                    \App\Models\Lamtim_address_list::updateOrCreate(
                        ['name' => $listName],
                        ['description' => "Sinkronisasi dari MikroTik ID: $idMikrotik"]
                    );
                    $countAddr++;
                }
            }

            return response()->json([
                'status' => true, 
                'message' => "Berhasil sinkronisasi $countPool IP Pool dan $countAddr Address List."
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Ambil daftar pelanggan dari MikroTik (tanpa eksekusi sync)
     */
    public function fetchPelangganList(Request $request)
    {
        $request->validate(['id_mikrotik' => 'required|integer']);
        
        // Naikkan batas waktu untuk pengambilan data dari MikroTik (10 Menit)
        set_time_limit(600); 

        try {
            $fetch = $this->mikrotikService->fetchSecretsForSync($request->id_mikrotik);
            if (!$fetch['success']) return response()->json(['status' => false, 'message' => $fetch['message']]);

            return response()->json([
                'status' => true,
                'data' => $fetch['data'],
                'total' => count($fetch['data'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Eksekusi sinkronisasi untuk satu batch pelanggan
     */
    public function syncPelangganBatch(Request $request)
    {
        $request->validate([
            'id_mikrotik' => 'required|integer',
            'secrets' => 'required|array'
        ]);

        // Beri waktu ekstra untuk setiap batch yang diproses
        set_time_limit(120);
        
        try {
            $idMikrotik = $request->id_mikrotik;
            $secrets = $request->secrets;

            // 1. Eksekusi Sync ke Billing
            $syncBilling = $this->mikrotikService->executeSyncCustomers($idMikrotik, $secrets);

            return response()->json([
                'status' => true,
                'processed' => count($secrets),
                'details' => $syncBilling
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }
}
