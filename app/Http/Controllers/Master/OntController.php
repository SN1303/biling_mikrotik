<?php

namespace App\Http\Controllers\Master;

use App\Exceptions\GenieAcsTidakTerjangkau;
use App\Http\Controllers\Controller;
use App\Models\Lamtim_user_mikrotik_details;
use App\Models\User;
use App\Services\GenieAcsService;
use App\Services\RemoteOntService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * ONT pelanggan lewat GenieACS: lihat status, refresh, reboot, dan ganti WiFi.
 * Akses: role 1, 2, 4, 7 (diatur di routes/web.php).
 */
class OntController extends Controller
{
  private const PESAN_BELUM_DIATUR = 'GenieACS belum diatur. Isi GENIEACS_URL di file .env.';
  private const PESAN_ANTRI = 'ONT tidak merespons; perintah akan dijalankan saat ONT online lagi.';

  public function __construct(private GenieAcsService $acs, private RemoteOntService $remote)
  {
  }

  /** Buat tiket remote web ONT; balas {url} untuk dimuat di iframe. */
  public function remote(Request $request, string $id): JsonResponse
  {
    if (!$this->remote->aktif()) {
      return response()->json(['message' => 'Remote ONT belum diatur. Isi GENIEACS_REMOTE_HOST di file .env.'], 422);
    }
    $pelanggan = $this->pelangganDari($id);
    try {
      $hasil = $pelanggan->user_mikrotik ? $this->acs->ontPelanggan($pelanggan->user_mikrotik) : ['status' => 'belum'];
      if ($hasil['status'] !== 'terhubung') {
        return response()->json(['message' => 'ONT pelanggan ini belum terhubung.'], 422);
      }
      $tiket = $this->remote->buatTiket($request->user(), $request->session()->getId(), $hasil['ont']);
    } catch (GenieAcsTidakTerjangkau $e) {
      return response()->json(['message' => $e->getMessage()], 503);
    } catch (\DomainException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }

    Log::info('ONT remote', [
      'oleh' => $request->user()->id, 'pelanggan' => $pelanggan->id,
      'sn' => $hasil['ont']['sn'], 'ip' => $hasil['ont']['ip'],
    ]);

    // Skema dan port mengikuti alamat billing saat ini (https lewat Cloudflare, http:8099 saat uji lokal)
    $port = in_array($request->getPort(), [80, 443], true) ? '' : ':' . $request->getPort();
    $url = $request->getScheme() . '://' . config('services.genieacs.remote_host') . $port . '/_remote/mulai?tiket=' . urlencode($tiket);
    return response()->json(['url' => $url]);
  }

  public function pelanggan(string $id)
  {
    $pelanggan = $this->pelangganDari($id);
    $hasil = null;
    $galat = null;

    if ($this->acs->aktif() && $pelanggan->user_mikrotik) {
      try {
        $hasil = $this->acs->ontPelanggan($pelanggan->user_mikrotik);
      } catch (GenieAcsTidakTerjangkau $e) {
        $galat = 'GenieACS tidak terjangkau. Coba lagi beberapa saat, atau periksa server GenieACS.';
      }
    }

    return view('content.ont.pelanggan', [
      'id' => $id,
      'pelanggan' => $pelanggan,
      'aktif' => $this->acs->aktif(),
      'pesanBelumDiatur' => self::PESAN_BELUM_DIATUR,
      'hasil' => $hasil,
      'galat' => $galat,
      'remoteAktif' => $this->remote->aktif(),
    ]);
  }

  public function refresh(string $id): JsonResponse
  {
    return $this->jalankan($id, 'refresh', fn(array $ont) => $this->acs->refresh($ont), 'Data ONT diperbarui.');
  }

  public function reboot(string $id): JsonResponse
  {
    return $this->jalankan($id, 'reboot', fn(array $ont) => $this->acs->reboot($ont['id']), 'ONT sedang reboot. Tunggu 1–2 menit.');
  }

  public function wifi(Request $request, string $id): JsonResponse
  {
    $data = $request->validate([
      'band' => 'required|in:24,5,keduanya',
      'ssid' => 'nullable|string|max:32',
      'ssid5' => 'nullable|string|max:32',
      'password' => 'nullable|string|min:8|max:63',
      'password5' => 'nullable|string|min:8|max:63',
    ]);

    $kunci = match ($data['band']) {
      '24' => ['ssid', 'password'],
      '5' => ['ssid5', 'password5'],
      'keduanya' => ['ssid', 'password', 'ssid5', 'password5'],
    };
    if ($data['band'] === 'keduanya' && empty($data['password5'])) {
      $data['password5'] = $data['password'] ?? null;
    }
    $nilai = array_filter(array_intersect_key($data, array_flip($kunci)), fn($v) => $v !== null && $v !== '');
    if (!$nilai) {
      throw ValidationException::withMessages(['ssid' => 'Isi nama WiFi atau password yang mau diganti.']);
    }

    return $this->jalankan($id, 'ganti WiFi', function (array $ont, User $pelanggan) use ($nilai) {
      try {
        $status = $this->acs->setWifi($ont, $nilai);
      } catch (\DomainException $e) { // jalur WiFi pita itu tidak dikenal di ONT ini
        throw ValidationException::withMessages(['band' => $e->getMessage()]);
      }
      if ($status === 'selesai' && isset($nilai['ssid'])) {
        $pelanggan->user_mikrotik->update(['wifi_name' => $nilai['ssid']]);
      }
      return $status;
    }, 'WiFi berhasil diganti.', ['diubah' => array_keys($nilai)]);
  }

  /**
   * Cari ONT pelanggan, jalankan aksi, catat ke log, lalu balas JSON {status, message}.
   * Device id selalu diambil dari GenieACS, bukan dari isian pengguna.
   */
  private function jalankan(string $id, string $aksi, callable $kerja, string $pesanSelesai, array $catatan = []): JsonResponse
  {
    if (!$this->acs->aktif()) {
      return response()->json(['status' => 'gagal', 'message' => self::PESAN_BELUM_DIATUR], 422);
    }
    $pelanggan = $this->pelangganDari($id);

    try {
      $hasil = $pelanggan->user_mikrotik ? $this->acs->ontPelanggan($pelanggan->user_mikrotik) : ['status' => 'belum'];
      if ($hasil['status'] !== 'terhubung') {
        return response()->json(['status' => 'gagal', 'message' => 'ONT pelanggan ini belum terhubung.'], 422);
      }
      $status = $kerja($hasil['ont'], $pelanggan);
    } catch (GenieAcsTidakTerjangkau $e) {
      $status = 'gagal';
      $pesanGagal = $e->getMessage();
    }

    Log::info("ONT $aksi", $catatan + [
      'oleh' => auth()->id(),
      'pelanggan' => $pelanggan->id,
      'sn' => $hasil['ont']['sn'] ?? null,
      'hasil' => $status,
    ]);

    return response()->json([
      'status' => $status,
      'message' => match ($status) {
        'selesai' => $pesanSelesai,
        'antri' => self::PESAN_ANTRI,
        'terkirim' => 'Perintah sudah dikirim, tapi ONT belum memberi jawaban. Tekan Refresh sebentar lagi untuk melihat hasilnya.',
        default => $pesanGagal ?? 'GenieACS tidak terjangkau.',
      },
    ], $status === 'gagal' ? 503 : 200);
  }

  /** ONT yang lapor ke GenieACS tapi SN dan username PPPoE-nya tidak cocok dengan pelanggan mana pun. */
  public function belum()
  {
    $ont = [];
    $galat = null;
    if ($this->acs->aktif()) {
      try {
        $snTerpakai = array_flip(Lamtim_user_mikrotik_details::whereNotNull('ont_sn')->pluck('ont_sn')->all());
        $pppoe = array_flip(Lamtim_user_mikrotik_details::whereNotNull('namaMikrotikUser')->pluck('namaMikrotikUser')->all());
        $ont = array_values(array_filter($this->acs->devices([]),
          fn(array $o) => !isset($snTerpakai[$o['sn']]) && !($o['pppoe'] !== null && isset($pppoe[$o['pppoe']]))));
      } catch (GenieAcsTidakTerjangkau) {
        $galat = 'GenieACS tidak terjangkau. Coba lagi beberapa saat, atau periksa server GenieACS.';
      }
    }

    $pelanggan = User::where('idRole', 5)->whereHas('user_mikrotik')->with('user_mikrotik:id,idUser,namaMikrotikUser,ont_sn')
      ->orderBy('name')->get(['id', 'name']);

    return view('content.ont.belum-terhubung', [
      'aktif' => $this->acs->aktif(),
      'pesanBelumDiatur' => self::PESAN_BELUM_DIATUR,
      'ont' => $ont,
      'galat' => $galat,
      'pelanggan' => $pelanggan,
    ]);
  }

  /** Simpan SN ke pelanggan. SN milik pelanggan lain hanya dipindah bila `pindahkan` dikirim. */
  public function hubungkan(Request $request): JsonResponse
  {
    $data = $request->validate([
      'sn' => 'required|string|max:100',
      'pelanggan' => 'required|string',
      'pindahkan' => 'nullable|boolean',
    ]);
    $tujuan = $this->pelangganDari($data['pelanggan']);
    if (!$tujuan->user_mikrotik) {
      return response()->json(['status' => 'gagal', 'message' => 'Pelanggan ini belum punya data PPPoE.'], 422);
    }

    $pemilik = Lamtim_user_mikrotik_details::with('user:id,name')
      ->where('ont_sn', $data['sn'])->where('idUser', '!=', $tujuan->id)->first();
    if ($pemilik && empty($data['pindahkan'])) {
      return response()->json([
        'status' => 'konfirmasi',
        'message' => "ONT {$data['sn']} sudah terhubung ke " . ($pemilik->user->name ?? 'pelanggan lain') . '. Pindahkan ke ' . $tujuan->name . '?',
      ], 409);
    }

    DB::transaction(function () use ($data, $tujuan) {
      Lamtim_user_mikrotik_details::where('ont_sn', $data['sn'])->update(['ont_sn' => null]);
      $tujuan->user_mikrotik->update(['ont_sn' => $data['sn']]);
    });

    Log::info('ONT hubungkan', [
      'oleh' => auth()->id(), 'pelanggan' => $tujuan->id, 'sn' => $data['sn'],
      'dari' => $pemilik?->idUser, 'hasil' => 'selesai',
    ]);
    return response()->json(['status' => 'selesai', 'message' => "ONT terhubung ke {$tujuan->name}."]);
  }

  private function pelangganDari(string $id): User
  {
    try {
      $idUser = decrypt($id);
    } catch (\Throwable) {
      abort(404);
    }
    return User::with('user_mikrotik')->findOrFail($idUser);
  }
}
