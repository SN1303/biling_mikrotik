<?php

namespace App\Services;

use App\Exceptions\GenieAcsTidakTerjangkau;
use App\Models\Lamtim_user_mikrotik_details;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Klien tipis untuk NBI GenieACS. Data tidak disalin ke database lokal: setiap
 * pemanggilan langsung bertanya ke GenieACS. Redaman, SN, dan PPPoE dibaca dari virtual
 * parameter yang sudah ada di GenieACS; WiFi dibaca dan ditulis langsung ke jalur TR-069.
 */
class GenieAcsService
{
  private const WLAN = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration';
  private const PROYEKSI = '_id,_deviceId,_lastInform,VirtualParameters,' . self::WLAN . ',Device.WiFi';

  /**
   * Calon jalur WiFi per pita: TR-098 (indeks 1 = 2,4 GHz, 5 = 5 GHz) lalu TR-181.
   * Merek yang memakai indeks lain cukup ditambahkan di sini.
   */
  private const JALUR_WIFI = [
    'ssid' => [self::WLAN . '.1.SSID', 'Device.WiFi.SSID.1.SSID'],
    'password' => [self::WLAN . '.1.KeyPassphrase', self::WLAN . '.1.PreSharedKey.1.KeyPassphrase', 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase'],
    'ssid5' => [self::WLAN . '.5.SSID', 'Device.WiFi.SSID.2.SSID'],
    'password5' => [self::WLAN . '.5.KeyPassphrase', self::WLAN . '.5.PreSharedKey.1.KeyPassphrase', 'Device.WiFi.AccessPoint.2.Security.KeyPassphrase'],
  ];

  public function aktif(): bool
  {
    return (string) config('services.genieacs.url') !== '';
  }

  /**
   * Cari ONT milik pelanggan: lewat SN tersimpan, atau lewat username PPPoE.
   * Hasil PPPoE yang tepat satu langsung disimpan SN-nya ke ont_sn.
   *
   * @return array{status:'terhubung',ont:array}|array{status:'kandidat',kandidat:list<array>}|array{status:'belum'}
   */
  public function ontPelanggan(Lamtim_user_mikrotik_details $client): array
  {
    if (!$this->aktif()) {
      return ['status' => 'belum'];
    }

    if (!empty($client->ont_sn)) {
      $ont = $this->devices(['VirtualParameters.getSerialNumber' => $client->ont_sn])
        ?: $this->devices(['_deviceId._SerialNumber' => $client->ont_sn]);
      if ($ont) {
        return ['status' => 'terhubung', 'ont' => $ont[0]];
      }
    }

    if (empty($client->namaMikrotikUser)) {
      return ['status' => 'belum'];
    }

    $ont = $this->devices(['VirtualParameters.pppoeUsername' => $client->namaMikrotikUser]);
    // Lebih dari satu ONT, atau SN-nya sudah milik pelanggan lain: jangan hubungkan otomatis.
    // Pilihan lewat "Hubungkan" meminta konfirmasi sebelum memindahkan SN.
    $milikLain = count($ont) === 1 && Lamtim_user_mikrotik_details::where('ont_sn', $ont[0]['sn'])
      ->where('id', '!=', $client->id)->exists();
    if (count($ont) > 1 || $milikLain) {
      return ['status' => 'kandidat', 'kandidat' => $ont];
    }
    if (count($ont) === 1) {
      $client->update(['ont_sn' => $ont[0]['sn']]);
      return ['status' => 'terhubung', 'ont' => $ont[0]];
    }
    return ['status' => 'belum'];
  }

  /** @return list<array> device NBI dalam bentuk `ont` */
  public function devices(array $query): array
  {
    $respons = $this->kirim(fn(PendingRequest $http) => $http->get('/devices/', [
      'query' => json_encode((object) $query),
      'projection' => self::PROYEKSI,
    ]));

    // 4xx (mis. login proxy salah) atau balasan yang bukan daftar jangan dianggap "tidak ada ONT"
    $daftar = $respons->json();
    if (!$respons->successful() || !is_array($daftar) || !array_is_list($daftar)) {
      throw new GenieAcsTidakTerjangkau('GenieACS membalas tidak wajar (HTTP ' . $respons->status() . '). Periksa GENIEACS_URL/USER/PASS.');
    }
    return array_map(fn(array $d) => $this->bentukOnt($d), $daftar);
  }

  /** Minta ONT melapor ulang virtual parameter dan WiFi-nya. @return 'selesai'|'antri'|'terkirim' */
  public function refresh(array $ont): string
  {
    $nama = array_map(fn($vp) => "VirtualParameters.$vp", ['pppoeUsername', 'RXPower', 'getSerialNumber', 'WlanPassword']);
    return $this->task($ont['id'], ['name' => 'getParameterValues', 'parameterNames' => [...$nama, ...array_merge(...array_values($ont['jalurWifi']))]]);
  }

  /** @return 'selesai'|'antri'|'terkirim' */
  public function reboot(string $deviceId): string
  {
    return $this->task($deviceId, ['name' => 'reboot']);
  }

  /**
   * Tulis WiFi langsung ke jalur yang ada di ONT itu. Password yang sama untuk SEMUA pita ONT
   * dikirim lewat VirtualParameters.WlanPassword (sudah ada di GenieACS), supaya salinan
   * cadangannya ikut diperbarui untuk ONT yang menyembunyikan password.
   *
   * @param array<'ssid'|'password'|'ssid5'|'password5', string> $nilai hanya kunci yang diubah
   * @return 'selesai'|'antri'|'terkirim'
   * @throws \DomainException bila ONT tidak punya jalur untuk salah satu kunci
   */
  public function setWifi(array $ont, array $nilai): string
  {
    $nilai = array_intersect_key($nilai, array_flip(['ssid', 'password', 'ssid5', 'password5']));
    $semuaPita = $ont['dualBand'] ? ['password', 'password5'] : ['password'];
    $password = array_intersect_key($nilai, array_flip($semuaPita));

    $parameter = [];
    if (count($password) === count($semuaPita) && count(array_unique($password)) === 1) {
      $parameter[] = ['VirtualParameters.WlanPassword', reset($password), 'xsd:string'];
      $nilai = array_diff_key($nilai, $password);
    }
    foreach ($nilai as $kunci => $isi) {
      if (!$ont['jalurWifi'][$kunci]) {
        throw new \DomainException('WiFi ONT ini belum bisa dibaca, jadi belum bisa diganti dari billing.');
      }
      foreach ($ont['jalurWifi'][$kunci] as $jalur) {
        $parameter[] = [$jalur, $isi, 'xsd:string'];
      }
    }
    return $this->task($ont['id'], ['name' => 'setParameterValues', 'parameterValues' => $parameter]);
  }

  /**
   * 200 = sudah dikerjakan ONT; 202 "Task queued…" = antri karena ONT tidak menjawab;
   * 202 "Task faulted" = ONT menolak. NBI menunggu sesi ONT selesai (sampai 120 detik), jadi
   * bila belum ada jawaban dalam 25 detik (di bawah batas 30 detik PHP) hasilnya 'terkirim':
   * perintah sudah di GenieACS, tapi hasilnya belum diketahui.
   *
   * @return 'selesai'|'antri'|'terkirim'
   */
  private function task(string $deviceId, array $body): string
  {
    try {
      $respons = $this->kirim(fn(PendingRequest $http) => $http->timeout(25)->connectTimeout(3)->asJson()
        ->post('/devices/' . rawurlencode($deviceId) . '/tasks?timeout=3000&connection_request', $body));
    } catch (GenieAcsTidakTerjangkau $e) {
      if (str_contains((string) $e->getPrevious()?->getMessage(), 'Operation timed out')) {
        return 'terkirim';
      }
      throw $e;
    }

    return match (true) {
      $respons->status() === 200 => 'selesai',
      $respons->status() === 202 && str_contains(strtolower($respons->reason()), 'fault')
        => throw new GenieAcsTidakTerjangkau('ONT menolak perintah. Periksa nilai yang dikirim, atau lihat Faults di GenieACS.'),
      $respons->status() === 202 => 'antri',
      default => throw new GenieAcsTidakTerjangkau('GenieACS menolak perintah (HTTP ' . $respons->status() . ')'),
    };
  }

  private function bentukOnt(array $d): array
  {
    $vp = fn(string $nama) => ($v = $d['VirtualParameters'][$nama]['_value'] ?? null) === '' ? null : $v;
    $lastInform = isset($d['_lastInform']) ? Carbon::parse($d['_lastInform']) : null;
    $rx = $vp('RXPower');
    $cadangan = $vp('WlanPassword');

    // Jalur WiFi yang benar-benar ada di dokumen ONT ini (nilainya boleh kosong, mis. ZTE menyembunyikan password)
    $jalur = array_map(fn(array $calon) => array_values(array_filter($calon,
      fn(string $p) => is_array($n = data_get($d, $p)) && array_key_exists('_value', $n))), self::JALUR_WIFI);
    $baca = function (string $kunci) use ($d, $jalur) {
      foreach ($jalur[$kunci] as $p) {
        if (($v = data_get($d, "$p._value")) !== null && $v !== '') {
          return (string) $v;
        }
      }
      return null;
    };

    return [
      'id' => $d['_id'],
      'sn' => $vp('getSerialNumber') ?? ($d['_deviceId']['_SerialNumber'] ?? ''),
      'model' => trim(($d['_deviceId']['_Manufacturer'] ?? '') . ' ' . ($d['_deviceId']['_ProductClass'] ?? '')),
      'lastInform' => $lastInform,
      'online' => $lastInform !== null && $lastInform->gt(now()->subMinutes((int) config('services.genieacs.online_menit', 10))),
      'rxPower' => is_numeric($rx) ? (float) $rx : null,
      'pppoe' => $vp('pppoeUsername'),
      'dualBand' => (bool) $jalur['ssid5'],
      'jalurWifi' => $jalur,
      'wifi' => [
        'ssid' => $baca('ssid'),
        'password' => $baca('password') ?? $cadangan,
        'ssid5' => $baca('ssid5'),
        'password5' => $jalur['ssid5'] ? ($baca('password5') ?? $cadangan) : null,
      ],
    ];
  }

  /** Jalankan request; koneksi gagal atau 5xx jadi GenieAcsTidakTerjangkau. */
  private function kirim(callable $permintaan): Response
  {
    $http = Http::baseUrl(rtrim((string) config('services.genieacs.url'), '/'))->timeout(5);
    if (config('services.genieacs.user')) {
      $http = $http->withBasicAuth(config('services.genieacs.user'), (string) config('services.genieacs.pass'));
    }

    try {
      $respons = $permintaan($http);
    } catch (ConnectionException $e) {
      throw new GenieAcsTidakTerjangkau('GenieACS tidak terjangkau', 0, $e);
    }
    if ($respons->serverError()) {
      throw new GenieAcsTidakTerjangkau('GenieACS tidak terjangkau (HTTP ' . $respons->status() . ')');
    }
    return $respons;
  }
}
