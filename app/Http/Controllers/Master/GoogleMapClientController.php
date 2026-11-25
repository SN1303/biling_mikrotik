<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Lamtim_odp;
use App\Models\Lamtim_user_details;
use App\Models\Lamtim_user_mikrotik_details;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pelanggan di halaman peta: daftar, detail, tambah/lengkapi dari secret MikroTik, ubah, hapus.
 * Perangkat jaringan (OLT/ODC/ODP), area, dan import ada di GoogleMapController.
 */
class GoogleMapClientController extends Controller
{
    public function getClients()
    {
        $clients = Lamtim_user_mikrotik_details::with('user:id,name')
            ->select('id', 'idUser', 'idOdp', 'localAdress', 'latitude', 'longitude', 'status')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'nama' => $c->user->name ?? 'Unknown',
                'latitude' => $c->latitude,
                'longitude' => $c->longitude,
            ]);

        return response()->json(['success' => true, 'data' => $clients]);
    }

    public function getClient($id)
    {
        $client = Lamtim_user_mikrotik_details::with(['user:id,name,wa', 'user.user_detail:id,idUser,idArea,tglDafatar,tglJatuhTempo', 'odp:id,nama'])->find($id);
        if (!$client) return response()->json(['success' => false, 'message' => 'Client tidak ditemukan'], 404);

        $userDetail = $client->user->user_detail ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $client->id,
                'nama' => $client->user->name ?? 'Unknown',
                'wa' => $client->user->wa ?? '-',
                'idOdp' => $client->idOdp,
                'odp_nama' => $client->odp->nama ?? null,
                'ip' => $client->localAdress,
                'lat' => $client->latitude,
                'lng' => $client->longitude,
                'status' => $client->status,
                'idKategori' => $client->idKategori,
                'idPaket' => $client->idPaket,
                'idMikrotik' => $client->idMikrotik,
                'portOdp' => $client->portOdp,
                'namaMikrotikUser' => $client->namaMikrotikUser,
                'idMikrotikUser' => $client->idMikrotikUser,
                'serviceMikrotikUser' => $client->serviceMikrotikUser,
                'profileMikrotikUser' => $client->profileMikrotikUser,
                'password' => $client->password,
                'keterangan' => $client->keterangan,
                'idArea' => $userDetail->idArea ?? null,
                'tglDaftar' => $userDetail->tglDafatar ?? null,
                'tglJatuhTempo' => $userDetail->tglJatuhTempo ?? null,
            ]
        ]);
    }

    public function createClient(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'wa' => 'nullable|string|max:20',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'idOdp' => 'nullable|integer|exists:lamtim_odps,id',
            'portOdp' => 'nullable|integer|min:0',
            'idKategori' => 'nullable|integer',
            'idPaket' => 'nullable|integer',
            'idMikrotik' => 'nullable|integer',
            'secretName' => 'nullable|string|max:255',
            'secretId' => 'nullable|string|max:255',
            'secretService' => 'nullable|string|max:255',
            'secretProfile' => 'nullable|string|max:255',
            'secretPassword' => 'nullable|string|max:255',
            'ip' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
            'status' => 'nullable|string|in:online,offline,isolir',
            'idArea' => 'nullable|integer',
            'tglDaftar' => 'nullable|date',
            'tglJatuhTempo' => 'nullable|date',
        ]);

        // Isi IP dari sesi PPPoE aktif bila form tidak membawa IP (pelanggan IP dinamis)
        if (empty($validated['ip']) && !empty($validated['secretName']) && !empty($validated['idMikrotik'])) {
            $validated['ip'] = $this->cariIpAktif((int) $validated['idMikrotik'], $validated['secretName']);
        }

        // Username PPPoE sudah ada (mis. hasil sinkron MikroTik)? Lengkapi data pelanggan
        // yang sudah ada, JANGAN buat pelanggan baru (dulu ini membuat pelanggan dobel).
        if (!empty($validated['secretName'])) {
            $existing = $this->cariPelangganByUsername($validated['secretName'], $validated['idMikrotik'] ?? null);
            if ($existing) {
                return $this->lengkapiPelangganDariPeta($existing, $validated);
            }
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $validated['nama'],
                'email' => 'client_' . time() . '_' . rand(100, 999) . '@local.net',
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                'wa' => $validated['wa'] ?? '-',
                'idRole' => 5,
            ]);

            // Create user_details with dates from form
            Lamtim_user_details::create([
                'idUser' => $user->id,
                'idArea' => $validated['idArea'] ?? null,
                'tglDafatar' => $validated['tglDaftar'] ?? now()->toDateString(),
                'tglJatuhTempo' => $validated['tglJatuhTempo'] ?? now()->addMonth()->toDateString(),
                'mulaiTagih' => Lamtim_user_details::mulaiTagihDefault($validated['tglDaftar'] ?? now()),
                'statusPpn' => $validated['status_ppn'] ?? 0,
                'statusTagihan' => 1,
                'jenisBayar' => 1,
                'js' => 'L',
                'identitas' => 'KTP',
            ]);

            // Resolve OLT/ODC from ODP
            $idOlt = null;
            $idOdc = null;
            if (!empty($validated['idOdp'])) {
                $odp = Lamtim_odp::find($validated['idOdp']);
                if ($odp) {
                    $idOlt = $odp->idOlt;
                    $idOdc = $odp->idOdc;
                }
            }

            $client = Lamtim_user_mikrotik_details::create([
                'idUser' => $user->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'idOlt' => $idOlt,
                'idOdc' => $idOdc,
                'idOdp' => $validated['idOdp'] ?? null,
                'portOdp' => $validated['portOdp'] ?? null,
                'idKategori' => $validated['idKategori'] ?? null,
                'idPaket' => $validated['idPaket'] ?? null,
                'idMikrotik' => $validated['idMikrotik'] ?? null,
                'namaMikrotikUser' => $validated['secretName'] ?? null,
                'idMikrotikUser' => $validated['secretId'] ?? null,
                'serviceMikrotikUser' => $validated['secretService'] ?? null,
                'profileMikrotikUser' => $validated['secretProfile'] ?? null,
                'password' => $validated['secretPassword'] ?? null,
                'localAdress' => $validated['ip'] ?? null,
                'statusIsolir' => ($validated['status'] ?? 'online') === 'isolir' ? 1 : 0,
                'status' => $validated['status'] ?? 'online',
                'keterangan' => $validated['keterangan'] ?? null,
            ]);

            // Decrement parent ODP port
            if (!empty($validated['idOdp']) && isset($odp) && $odp->portSisa > 0) {
                $odp->decrement('portSisa');
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Client berhasil ditambahkan', 'data' => $client]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    public function updateClient(Request $request, $id)
    {
        $client = Lamtim_user_mikrotik_details::with(['user.user_detail'])->findOrFail($id);
        $validated = $request->validate([
            'nama' => 'nullable|string|max:255',
            'wa' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'idOdp' => 'nullable|integer',
            'portOdp' => 'nullable|integer|min:0',
            'idKategori' => 'nullable|integer',
            'idPaket' => 'nullable|integer',
            'idMikrotik' => 'nullable|integer',
            'secretName' => 'nullable|string|max:255',
            'secretId' => 'nullable|string|max:255',
            'secretService' => 'nullable|string|max:255',
            'secretProfile' => 'nullable|string|max:255',
            'secretPassword' => 'nullable|string|max:255',
            'ip' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
            'status' => 'nullable|string|in:online,offline,isolir',
            'idArea' => 'nullable|integer',
            'tglDaftar' => 'nullable|date',
            'tglJatuhTempo' => 'nullable|date',
        ]);

        // Cegah 2 pelanggan memakai username PPPoE yang sama
        if (!empty($validated['secretName']) && $validated['secretName'] !== $client->namaMikrotikUser) {
            $lain = $this->cariPelangganByUsername($validated['secretName'], $validated['idMikrotik'] ?? $client->idMikrotik);
            if ($lain && $lain->id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => "Username PPPoE \"{$validated['secretName']}\" sudah dipakai pelanggan " . (optional($lain->user)->name ?? '#' . $lain->id) . '.',
                ], 422);
            }
        }

        if (empty($validated['ip']) && empty($client->localAdress) && !empty($validated['secretName'] ?? $client->namaMikrotikUser)) {
            $idMk = (int) ($validated['idMikrotik'] ?? $client->idMikrotik);
            if ($idMk) {
                $validated['ip'] = $this->cariIpAktif($idMk, $validated['secretName'] ?? $client->namaMikrotikUser);
            }
        }

        if (isset($validated['nama']) && $client->user) {
            $userUpdate = ['name' => $validated['nama']];
            if (isset($validated['wa'])) $userUpdate['wa'] = $validated['wa'];
            $client->user->update($userUpdate);
        }

        // Resolve OLT/ODC from ODP if changed
        $idOlt = $client->idOlt;
        $idOdc = $client->idOdc;
        if (isset($validated['idOdp']) && $validated['idOdp'] != $client->idOdp) {
            if ($validated['idOdp']) {
                $odp = Lamtim_odp::find($validated['idOdp']);
                if ($odp) { $idOlt = $odp->idOlt; $idOdc = $odp->idOdc; }
            } else {
                $idOlt = null; $idOdc = null;
            }
        }

        $client->update([
            'latitude' => $validated['latitude'] ?? $client->latitude,
            'longitude' => $validated['longitude'] ?? $client->longitude,
            'idOlt' => $idOlt,
            'idOdc' => $idOdc,
            'idOdp' => $validated['idOdp'] ?? $client->idOdp,
            'portOdp' => $validated['portOdp'] ?? $client->portOdp,
            'idKategori' => $validated['idKategori'] ?? $client->idKategori,
            'idPaket' => $validated['idPaket'] ?? $client->idPaket,
            'idMikrotik' => $validated['idMikrotik'] ?? $client->idMikrotik,
            'namaMikrotikUser' => $validated['secretName'] ?? $client->namaMikrotikUser,
            'idMikrotikUser' => $validated['secretId'] ?? $client->idMikrotikUser,
            'serviceMikrotikUser' => $validated['secretService'] ?? $client->serviceMikrotikUser,
            'profileMikrotikUser' => $validated['secretProfile'] ?? $client->profileMikrotikUser,
            'password' => $validated['secretPassword'] ?? $client->password,
            'localAdress' => $validated['ip'] ?? $client->localAdress,
            'keterangan' => $validated['keterangan'] ?? $client->keterangan,
            'status' => $validated['status'] ?? $client->status,
            'statusIsolir' => isset($validated['status']) ? ($validated['status'] === 'isolir' ? 1 : 0) : $client->statusIsolir,
        ]);

        // Update user_details (area, tglDaftar, tglJatuhTempo)
        $userDetail = $client->user->user_detail ?? null;
        if ($userDetail) {
            $detailUpdate = [];
            if (array_key_exists('idArea', $validated)) $detailUpdate['idArea'] = $validated['idArea'];
            if (isset($validated['tglDaftar'])) $detailUpdate['tglDafatar'] = $validated['tglDaftar'];
            if (isset($validated['tglJatuhTempo'])) $detailUpdate['tglJatuhTempo'] = $validated['tglJatuhTempo'];
            if (!empty($detailUpdate)) $userDetail->update($detailUpdate);
        }

        return response()->json(['success' => true, 'message' => 'Client berhasil diupdate', 'data' => $client]);
    }

    /**
     * Cari pelanggan berdasarkan username PPPoE (dan server MikroTik bila diketahui).
     */
    private function cariPelangganByUsername(string $username, $idMikrotik = null): ?Lamtim_user_mikrotik_details
    {
        return Lamtim_user_mikrotik_details::with('user.user_detail')
            ->where('namaMikrotikUser', $username)
            ->when($idMikrotik, fn ($q) => $q->where(function ($w) use ($idMikrotik) {
                $w->where('idMikrotik', $idMikrotik)->orWhereNull('idMikrotik');
            }))
            ->orderBy('id')
            ->first();
    }

    /**
     * Ambil IP dari sesi PPPoE yang sedang aktif. Null bila offline / router tak terjangkau.
     */
    private function cariIpAktif(int $idMikrotik, string $username): ?string
    {
        try {
            $actives = \App\Services\MikrotikMulti::commandApi2($idMikrotik, '/ppp/active/print');
            foreach ((array) $actives as $a) {
                if (($a['name'] ?? null) === $username && !empty($a['address'])) {
                    return $a['address'];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Gagal ambil IP aktif {$username}: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Pelanggan dengan username PPPoE ini sudah ada → lengkapi datanya dari form peta
     * (nama, lokasi, ODP, paket, dll) tanpa menimpa data teknis yang sudah terisi (IP, secret).
     */
    private function lengkapiPelangganDariPeta(Lamtim_user_mikrotik_details $client, array $v)
    {
        try {
            DB::beginTransaction();

            if ($client->user) {
                $client->user->update(array_filter([
                    'name' => $v['nama'] ?? null,
                    'wa' => $v['wa'] ?? null,
                ], fn ($x) => $x !== null && $x !== ''));
            }

            $idOlt = $client->idOlt;
            $idOdc = $client->idOdc;
            if (!empty($v['idOdp'])) {
                $odp = Lamtim_odp::find($v['idOdp']);
                if ($odp) { $idOlt = $odp->idOlt; $idOdc = $odp->idOdc; }
            }

            $client->update([
                'latitude' => $v['latitude'],
                'longitude' => $v['longitude'],
                'idOlt' => $idOlt,
                'idOdc' => $idOdc,
                'idOdp' => $v['idOdp'] ?? $client->idOdp,
                'portOdp' => $v['portOdp'] ?? $client->portOdp,
                'idKategori' => $v['idKategori'] ?? $client->idKategori,
                'idPaket' => $v['idPaket'] ?? $client->idPaket,
                'idMikrotik' => $client->idMikrotik ?? ($v['idMikrotik'] ?? null),
                'idMikrotikUser' => $client->idMikrotikUser ?: ($v['secretId'] ?? null),
                'serviceMikrotikUser' => $client->serviceMikrotikUser ?: ($v['secretService'] ?? null),
                'profileMikrotikUser' => $v['secretProfile'] ?? $client->profileMikrotikUser,
                'password' => $client->password ?: ($v['secretPassword'] ?? null),
                'localAdress' => $client->localAdress ?: ($v['ip'] ?? null),
                'keterangan' => $v['keterangan'] ?? $client->keterangan,
            ]);

            $userDetail = optional($client->user)->user_detail;
            if ($userDetail) {
                $userDetail->update(array_filter([
                    'idArea' => $v['idArea'] ?? null,
                    'tglDafatar' => $v['tglDaftar'] ?? null,
                    'tglJatuhTempo' => $v['tglJatuhTempo'] ?? null,
                ], fn ($x) => $x !== null));
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Pelanggan dengan username \"{$client->namaMikrotikUser}\" sudah ada (hasil sinkron) — datanya dilengkapi & ditempatkan di peta, tidak dibuat dobel.",
                'data' => $client->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    public function deleteClient($id)
    {
        $client = Lamtim_user_mikrotik_details::findOrFail($id);

        DB::beginTransaction();
        // Restore parent ODP port
        if ($client->idOdp) {
            Lamtim_odp::where('id', $client->idOdp)->increment('portSisa');
        }
        $client->delete();
        DB::commit();

        return response()->json(['success' => true, 'message' => 'Client berhasil dihapus']);
    }

}
