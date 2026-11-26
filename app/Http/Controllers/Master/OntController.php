<?php

namespace App\Http\Controllers\Master;

use App\Exceptions\GenieAcsTidakTerjangkau;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GenieAcsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

  public function __construct(private GenieAcsService $acs)
  {
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
    ]);
  }

  public function refresh(string $id): JsonResponse
  {
    return $this->jalankan($id, 'refresh', fn(array $ont) => $this->acs->refresh($ont['id']), 'Data ONT diperbarui.');
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
      $status = $this->acs->setWifi($ont['id'], $nilai);
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
        default => $pesanGagal ?? 'GenieACS tidak terjangkau.',
      },
    ], $status === 'gagal' ? 503 : 200);
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
