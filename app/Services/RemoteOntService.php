<?php

namespace App\Services;

use App\Exceptions\GenieAcsTidakTerjangkau;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Sesi remote web ONT. Data sesi (staf, device, IP) disimpan di cache server; browser
 * hanya membawa ID-nya, lewat tiket sekali pakai lalu cookie di host remote.
 * IP selalu berasal dari GenieACS, tidak pernah dari isian pengguna.
 */
class RemoteOntService
{
  private const MASA_SESI = 7200;   // 2 jam
  private const MASA_TIKET = 60;    // detik
  private const CEK_IP = 300;       // cek ulang IP ke GenieACS tiap 5 menit
  private const ROLE = [1, 2, 4, 7];

  public function __construct(private GenieAcsService $acs)
  {
  }

  public function aktif(): bool
  {
    return (string) config('services.genieacs.remote_host') !== '' && $this->acs->aktif();
  }

  /** @throws \DomainException bila ONT tidak punya IP di GenieACS */
  public function buatTiket(User $staf, string $sessionIdBilling, array $ont): string
  {
    if (empty($ont['ip'])) {
      throw new \DomainException('IP ONT belum terbaca di GenieACS (IPTR069 / pppoeIP kosong).');
    }
    $id = Str::random(40);
    Cache::put("ont-remote:$id", [
      'user' => $staf->id,
      'sesi' => $sessionIdBilling,
      'device' => $ont['id'],
      'ip' => $ont['ip'],
      'cek' => now()->timestamp,
    ], self::MASA_SESI);

    return Crypt::encryptString(json_encode(['id' => $id, 'exp' => now()->timestamp + self::MASA_TIKET]));
  }

  /** ID sesi bila tiket sah, belum kedaluwarsa, dan belum pernah dipakai. */
  public function tukarTiket(string $tiket): ?string
  {
    try {
      $isi = json_decode(Crypt::decryptString($tiket), true);
    } catch (\Throwable) {
      return null;
    }
    if (!is_array($isi) || empty($isi['id']) || ($isi['exp'] ?? 0) < now()->timestamp) {
      return null;
    }
    if (!Cache::add("ont-remote-tiket:{$isi['id']}", 1, self::MASA_TIKET * 2)) {
      return null; // sudah dipakai
    }
    return Cache::has("ont-remote:{$isi['id']}") ? $isi['id'] : null;
  }

  /** @return array{ip:string,user:int}|null */
  public function sesi(?string $id): ?array
  {
    $data = $id ? Cache::get("ont-remote:$id") : null;
    if (!$data) {
      return null;
    }

    $staf = User::find($data['user']);
    $sesiBillingHidup = Session::getHandler()->read($data['sesi']) !== '';
    if (!$staf || !$staf->isActive || !in_array((int) $staf->idRole, self::ROLE, true) || !$sesiBillingHidup) {
      Cache::forget("ont-remote:$id");
      return null;
    }

    // PPPoE bisa tersambung ulang dengan IP lain: ikuti IP baru dari device yang sama
    if (now()->timestamp - $data['cek'] >= self::CEK_IP) {
      try {
        $ont = $this->acs->devices(['_id' => $data['device']])[0] ?? null;
        if (!$ont || empty($ont['ip'])) {
          Cache::forget("ont-remote:$id");
          return null;
        }
        $data['ip'] = $ont['ip'];
      } catch (GenieAcsTidakTerjangkau) {
        // GenieACS sedang tidak terjangkau: tetap pakai IP terakhir yang diketahui
      }
      $data['cek'] = now()->timestamp;
      Cache::put("ont-remote:$id", $data, self::MASA_SESI);
    }

    return ['ip' => $data['ip'], 'user' => $staf->id];
  }
}
