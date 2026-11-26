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
 * pemanggilan langsung bertanya ke GenieACS. Perbedaan antar-merek ONT ditangani
 * oleh virtual parameter di GenieACS (lihat docs/genieacs/).
 */
class GenieAcsService
{
  private const PROYEKSI = '_id,_deviceId,_lastInform,VirtualParameters';

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
    if (count($ont) > 1) {
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

    return array_map(fn(array $d) => $this->bentukOnt($d), $respons->json() ?? []);
  }

  /** Kunci isian WiFi aplikasi → virtual parameter di GenieACS. */
  private const VP_WIFI = [
    'ssid' => 'VirtualParameters.wifiSsid',
    'password' => 'VirtualParameters.wifiPassword',
    'ssid5' => 'VirtualParameters.wifiSsid5',
    'password5' => 'VirtualParameters.wifiPassword5',
  ];

  /** Minta ONT melapor ulang nilai virtual parameter. @return 'selesai'|'antri' */
  public function refresh(string $deviceId): string
  {
    $nama = array_map(fn($vp) => "VirtualParameters.$vp", ['pppoeUsername', 'RXPower', 'getSerialNumber', 'WlanPassword']);
    return $this->task($deviceId, ['name' => 'getParameterValues', 'parameterNames' => [...$nama, ...array_values(self::VP_WIFI)]]);
  }

  /** @return 'selesai'|'antri' */
  public function reboot(string $deviceId): string
  {
    return $this->task($deviceId, ['name' => 'reboot']);
  }

  /**
   * @param array<'ssid'|'password'|'ssid5'|'password5', string> $nilai hanya kunci yang diubah
   * @return 'selesai'|'antri'
   */
  public function setWifi(string $deviceId, array $nilai): string
  {
    $parameter = [];
    foreach (array_intersect_key($nilai, self::VP_WIFI) as $kunci => $isi) {
      $parameter[] = [self::VP_WIFI[$kunci], $isi, 'xsd:string'];
    }
    return $this->task($deviceId, ['name' => 'setParameterValues', 'parameterValues' => $parameter]);
  }

  /** 200 = sudah dikerjakan ONT; 202 = masuk antrian karena ONT tidak menjawab. */
  private function task(string $deviceId, array $body): string
  {
    $respons = $this->kirim(fn(PendingRequest $http) => $http->asJson()
      ->post('/devices/' . rawurlencode($deviceId) . '/tasks?timeout=3000&connection_request', $body));

    return match ($respons->status()) {
      200 => 'selesai',
      202 => 'antri',
      default => throw new GenieAcsTidakTerjangkau('GenieACS menolak perintah (HTTP ' . $respons->status() . ')'),
    };
  }

  private function bentukOnt(array $d): array
  {
    $vp = fn(string $nama) => ($v = $d['VirtualParameters'][$nama]['_value'] ?? null) === '' ? null : $v;
    $lastInform = isset($d['_lastInform']) ? Carbon::parse($d['_lastInform']) : null;
    $rx = $vp('RXPower');
    $cadangan = $vp('WlanPassword');

    return [
      'id' => $d['_id'],
      'sn' => $vp('getSerialNumber') ?? ($d['_deviceId']['_SerialNumber'] ?? ''),
      'model' => trim(($d['_deviceId']['_Manufacturer'] ?? '') . ' ' . ($d['_deviceId']['_ProductClass'] ?? '')),
      'lastInform' => $lastInform,
      'online' => $lastInform !== null && $lastInform->gt(now()->subMinutes((int) config('services.genieacs.online_menit', 10))),
      'rxPower' => is_numeric($rx) ? (float) $rx : null,
      'pppoe' => $vp('pppoeUsername'),
      'wifi' => [
        'ssid' => $vp('wifiSsid'),
        'password' => $vp('wifiPassword') ?? $cadangan,
        'ssid5' => $vp('wifiSsid5'),
        'password5' => $vp('wifiPassword5') ?? $cadangan,
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
