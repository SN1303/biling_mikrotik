<?php

namespace Tests\Feature;

use App\Exceptions\GenieAcsTidakTerjangkau;
use App\Models\Lamtim_user_mikrotik_details;
use App\Models\User;
use App\Services\GenieAcsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GenieAcsService bicara ke NBI GenieACS. Semua respons di sini tiruan (Http::fake).
 */
class GenieAcsServiceTest extends TestCase
{
  use RefreshDatabase;

  public const ID = '00259E-F609-ZTEGC1234567';

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    config(['services.genieacs.url' => 'http://acs.test:7557', 'services.genieacs.online_menit' => 10]);
  }

  /** Dokumen device seperti balasan NBI. */
  public static function device(array $vp = [], string $sn = 'ZTEGC1234567', int $menitLalu = 2): array
  {
    $nilai = $vp + [
      'pppoeUsername' => 'rizal@tji-1', 'RXPower' => '-19.8', 'getSerialNumber' => $sn, 'WlanPassword' => '',
      'wifiSsid' => 'RIZAL-NET', 'wifiPassword' => 'rahasia123', 'wifiSsid5' => '', 'wifiPassword5' => '',
    ];
    return [
      '_id' => '00259E-F609-' . $sn,
      '_deviceId' => ['_SerialNumber' => $sn, '_ProductClass' => 'F609', '_Manufacturer' => 'ZTE'],
      '_lastInform' => now()->subMinutes($menitLalu)->toIso8601String(),
      'VirtualParameters' => array_map(fn($v) => ['_value' => $v], $nilai),
    ];
  }

  private function client(?string $pppoe = 'rizal@tji-1', ?string $sn = null): Lamtim_user_mikrotik_details
  {
    $user = User::create(['name' => 'Rizal', 'email' => 'rizal' . uniqid() . '@test', 'wa' => '2', 'password' => 'x', 'idRole' => 5, 'isActive' => 1]);
    $kat = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'Rumahan', 'description' => '-']);
    $paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kat, 'kode' => 'P' . uniqid(), 'nama' => '10 Mbps', 'price' => '100000']);
    return Lamtim_user_mikrotik_details::create([
      'idUser' => $user->id, 'idPaket' => $paket, 'statusIsolir' => 0, 'namaMikrotikUser' => $pppoe, 'ont_sn' => $sn,
    ]);
  }

  private function service(): GenieAcsService
  {
    return app(GenieAcsService::class);
  }

  public function test_aktif_false_bila_url_kosong(): void
  {
    config(['services.genieacs.url' => '']);
    Http::fake();
    $this->assertFalse($this->service()->aktif());
    $this->assertSame(['status' => 'belum'], $this->service()->ontPelanggan($this->client()));
    Http::assertNothingSent();
  }

  public function test_cari_lewat_sn_bila_ont_sn_terisi(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::sequence()->push([])->push([self::device()])]);

    $hasil = $this->service()->ontPelanggan($this->client(null, 'ZTEGC1234567'));

    $kueri = [];
    Http::assertSent(function (Request $r) use (&$kueri) {
      $kueri[] = json_decode($r['query'], true);
      return true;
    });
    $this->assertSame([['VirtualParameters.getSerialNumber' => 'ZTEGC1234567'], ['_deviceId._SerialNumber' => 'ZTEGC1234567']], $kueri);
    $this->assertSame('terhubung', $hasil['status']);
    $this->assertSame(-19.8, $hasil['ont']['rxPower']);
    $this->assertTrue($hasil['ont']['online']);
    $this->assertSame('RIZAL-NET', $hasil['ont']['wifi']['ssid']);
    $this->assertNull($hasil['ont']['wifi']['ssid5']);
  }

  public function test_cari_lewat_pppoe_lalu_simpan_sn(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response([self::device()])]);
    $client = $this->client('rizal@tji-1');

    $hasil = $this->service()->ontPelanggan($client);

    Http::assertSent(fn(Request $r) => json_decode($r['query'], true) === ['VirtualParameters.pppoeUsername' => 'rizal@tji-1']);
    $this->assertSame('terhubung', $hasil['status']);
    $this->assertSame('ZTEGC1234567', $client->fresh()->ont_sn);
  }

  public function test_pppoe_cocok_dua_ont_jadi_kandidat(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response([self::device(), self::device([], 'ZTEGC7654321')])]);
    $client = $this->client('rizal@tji-1');

    $hasil = $this->service()->ontPelanggan($client);

    $this->assertSame('kandidat', $hasil['status']);
    $this->assertCount(2, $hasil['kandidat']);
    $this->assertNull($client->fresh()->ont_sn);
  }

  public function test_password_kosong_pakai_wlanpassword(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response([self::device(['wifiPassword' => '', 'WlanPassword' => 'cadangan123'])])]);
    $hasil = $this->service()->ontPelanggan($this->client());
    $this->assertSame('cadangan123', $hasil['ont']['wifi']['password']);
  }

  public function test_tanpa_pppoe_dan_sn_tidak_query(): void
  {
    Http::fake();
    $this->assertSame(['status' => 'belum'], $this->service()->ontPelanggan($this->client(null, null)));
    Http::assertNothingSent();
  }

  public function test_offline_bila_lastinform_lama(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response([self::device([], 'ZTEGC1234567', 30)])]);
    $hasil = $this->service()->ontPelanggan($this->client());
    $this->assertFalse($hasil['ont']['online']);
  }

  public function test_koneksi_gagal_lempar_exception(): void
  {
    Http::fake(fn() => throw new ConnectionException('timeout'));
    $this->expectException(GenieAcsTidakTerjangkau::class);
    $this->service()->ontPelanggan($this->client());
  }

  public function test_reboot_200_selesai(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response('', 200)]);
    $this->assertSame('selesai', $this->service()->reboot(self::ID));
    Http::assertSent(fn(Request $r) => $r->method() === 'POST'
      && str_contains($r->url(), '/devices/' . self::ID . '/tasks?timeout=3000&connection_request')
      && json_decode($r->body(), true) === ['name' => 'reboot']);
  }

  public function test_reboot_202_antri(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response('', 202)]);
    $this->assertSame('antri', $this->service()->reboot(self::ID));
  }

  public function test_device_id_di_url_encode(): void
  {
    Http::fake(['acs.test:7557/*' => Http::response('', 200)]);
    $this->service()->reboot('A B/1');
    Http::assertSent(fn(Request $r) => str_contains($r->url(), '/devices/A%20B%2F1/tasks'));
  }

  public function test_set_wifi_kirim_satu_task(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response('', 200)]);
    $this->assertSame('selesai', $this->service()->setWifi(self::ID, ['ssid' => 'Rumah Budi', 'ssid5' => 'Rumah Budi-5G', 'password' => '12345678']));

    Http::assertSentCount(1);
    Http::assertSent(function (Request $r) {
      $body = json_decode($r->body(), true);
      $this->assertSame('setParameterValues', $body['name']);
      $this->assertEqualsCanonicalizing([
        ['VirtualParameters.wifiSsid', 'Rumah Budi', 'xsd:string'],
        ['VirtualParameters.wifiSsid5', 'Rumah Budi-5G', 'xsd:string'],
        ['VirtualParameters.wifiPassword', '12345678', 'xsd:string'],
      ], $body['parameterValues']);
      return true;
    });
  }

  public function test_refresh_minta_virtual_parameter(): void
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response('', 200)]);
    $this->service()->refresh(self::ID);
    Http::assertSent(function (Request $r) {
      $body = json_decode($r->body(), true);
      $this->assertSame('getParameterValues', $body['name']);
      foreach (['pppoeUsername', 'RXPower', 'getSerialNumber', 'WlanPassword', 'wifiSsid', 'wifiPassword', 'wifiSsid5', 'wifiPassword5'] as $vp) {
        $this->assertContains("VirtualParameters.$vp", $body['parameterNames']);
      }
      return true;
    });
  }

  public function test_respons_500_lempar_exception(): void
  {
    Http::fake(['acs.test:7557/*' => Http::response('error', 500)]);
    $this->expectException(GenieAcsTidakTerjangkau::class);
    $this->service()->ontPelanggan($this->client());
  }
}
