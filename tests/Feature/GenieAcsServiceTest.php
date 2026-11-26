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

  public const WLAN = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration';

  /**
   * Dokumen device seperti balasan NBI. $wlan = isi WLANConfiguration per indeks
   * (1 = 2,4 GHz, 5 = 5 GHz); bawaan: ONT single-band.
   */
  public static function device(array $vp = [], string $sn = 'ZTEGC1234567', int $menitLalu = 2, ?array $wlan = null): array
  {
    $nilai = $vp + ['pppoeUsername' => 'rizal@tji-1', 'RXPower' => '-19.8', 'getSerialNumber' => $sn, 'WlanPassword' => ''];
    $wlan ??= ['1' => ['SSID' => 'RIZAL-NET', 'KeyPassphrase' => 'rahasia123']];
    return [
      '_id' => '00259E-F609-' . $sn,
      '_deviceId' => ['_SerialNumber' => $sn, '_ProductClass' => 'F609', '_Manufacturer' => 'ZTE'],
      '_lastInform' => now()->subMinutes($menitLalu)->toIso8601String(),
      'VirtualParameters' => array_map(fn($v) => ['_value' => $v], $nilai),
      'InternetGatewayDevice' => ['LANDevice' => ['1' => ['WLANConfiguration' => self::bungkus($wlan)]]],
    ];
  }

  /** Nilai daun jadi {"_value": v}, seperti dokumen GenieACS. */
  private static function bungkus(array $isi): array
  {
    return array_map(fn($v) => is_array($v) ? self::bungkus($v) : ['_value' => $v], $isi);
  }

  /** ONT dalam bentuk `ont`, lewat devices() dengan NBI tiruan; task membalas $task (kode status atau callback). */
  private function ont(?array $wlan = null, int|\Closure $task = 200): array
  {
    Http::fake([
      'acs.test:7557/devices/?*' => Http::response([self::device([], 'ZTEGC1234567', 2, $wlan)]),
      'acs.test:7557/devices/*/tasks*' => is_int($task) ? Http::response('', $task) : $task,
    ]);
    return $this->service()->devices([])[0];
  }

  /** parameterValues dari task setParameterValues yang terkirim. */
  private function nilaiTerkirim(): array
  {
    $nilai = null;
    Http::assertSent(function (Request $r) use (&$nilai) {
      if (str_contains($r->url(), '/tasks')) {
        $nilai = json_decode($r->body(), true)['parameterValues'] ?? null;
      }
      return true;
    });
    return $nilai;
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
    // ZTE: jalur password ada, tapi nilainya disembunyikan (kosong)
    Http::fake(['acs.test:7557/devices/*' => Http::response([self::device(['WlanPassword' => 'cadangan123'], 'ZTEGC1234567', 2,
      ['1' => ['SSID' => 'RIZAL-NET', 'KeyPassphrase' => '']])])]);
    $hasil = $this->service()->ontPelanggan($this->client());
    $this->assertSame('cadangan123', $hasil['ont']['wifi']['password']);
  }

  public function test_wifi_dibaca_dari_jalur_asli_dua_pita(): void
  {
    $ont = $this->ont(['1' => ['SSID' => 'RIZAL-NET', 'KeyPassphrase' => 'satu2345'], '5' => ['SSID' => 'RIZAL-5G', 'PreSharedKey' => ['1' => ['KeyPassphrase' => 'lima2345']]]]);
    $this->assertSame(['ssid' => 'RIZAL-NET', 'password' => 'satu2345', 'ssid5' => 'RIZAL-5G', 'password5' => 'lima2345'], $ont['wifi']);
    $this->assertTrue($ont['dualBand']);
  }

  public function test_single_band_tanpa_5ghz(): void
  {
    $ont = $this->ont();
    $this->assertFalse($ont['dualBand']);
    $this->assertNull($ont['wifi']['ssid5']);
    $this->assertNull($ont['wifi']['password5']);
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

  private const DUA_PITA = ['1' => ['SSID' => 'A', 'KeyPassphrase' => 'satu2345'], '5' => ['SSID' => 'A-5G', 'KeyPassphrase' => 'lima2345']];

  public function test_nama_dan_password_satu_pita_ke_jalur_asli(): void
  {
    $ont = $this->ont(self::DUA_PITA);
    $this->assertSame('selesai', $this->service()->setWifi($ont, ['ssid' => 'Rumah Budi', 'ssid5' => 'Rumah Budi-5G', 'password' => '12345678']));
    $this->assertEqualsCanonicalizing([
      [self::WLAN . '.1.SSID', 'Rumah Budi', 'xsd:string'],
      [self::WLAN . '.5.SSID', 'Rumah Budi-5G', 'xsd:string'],
      [self::WLAN . '.1.KeyPassphrase', '12345678', 'xsd:string'],
    ], $this->nilaiTerkirim());
  }

  public function test_password_5ghz_ke_jalur_presharedkey(): void
  {
    $ont = $this->ont(['1' => ['SSID' => 'A', 'KeyPassphrase' => 'x'], '5' => ['SSID' => 'A-5G', 'PreSharedKey' => ['1' => ['KeyPassphrase' => '']]]]);
    $this->service()->setWifi($ont, ['password5' => 'abcdefgh']);
    $this->assertSame([[self::WLAN . '.5.PreSharedKey.1.KeyPassphrase', 'abcdefgh', 'xsd:string']], $this->nilaiTerkirim());
  }

  public function test_password_semua_pita_lewat_wlanpassword(): void
  {
    // ONT single-band: password 2,4 GHz = semua WiFi → pakai WlanPassword supaya cadangannya ikut diperbarui
    $this->service()->setWifi($this->ont(), ['password' => '12345678']);
    $this->assertSame([['VirtualParameters.WlanPassword', '12345678', 'xsd:string']], $this->nilaiTerkirim());
  }

  public function test_password_keduanya_sama_lewat_wlanpassword(): void
  {
    $this->service()->setWifi($this->ont(self::DUA_PITA), ['ssid' => 'B', 'password' => '12345678', 'password5' => '12345678']);
    $this->assertEqualsCanonicalizing([
      [self::WLAN . '.1.SSID', 'B', 'xsd:string'],
      ['VirtualParameters.WlanPassword', '12345678', 'xsd:string'],
    ], $this->nilaiTerkirim());
  }

  public function test_jalur_tidak_ada_ditolak_tanpa_task(): void
  {
    $ont = $this->ont(); // single-band: tidak ada WLANConfiguration.5
    try {
      $this->service()->setWifi($ont, ['ssid5' => 'X-5G']);
      $this->fail('Pita yang tidak ada harus ditolak');
    } catch (\DomainException) {
    }
    Http::assertNotSent(fn(Request $r) => str_contains($r->url(), '/tasks'));
  }

  public function test_refresh_minta_vp_dan_jalur_wifi(): void
  {
    $ont = $this->ont(self::DUA_PITA);
    $this->service()->refresh($ont);
    Http::assertSent(function (Request $r) {
      if (!str_contains($r->url(), '/tasks')) return false;
      $body = json_decode($r->body(), true);
      $this->assertSame('getParameterValues', $body['name']);
      foreach (['VirtualParameters.pppoeUsername', 'VirtualParameters.RXPower', 'VirtualParameters.getSerialNumber', 'VirtualParameters.WlanPassword',
        self::WLAN . '.1.SSID', self::WLAN . '.1.KeyPassphrase', self::WLAN . '.5.SSID', self::WLAN . '.5.KeyPassphrase'] as $nama) {
        $this->assertContains($nama, $body['parameterNames']);
      }
      return true;
    });
  }

  public function test_task_lambat_jadi_terkirim(): void
  {
    // NBI menunggu sesi ONT selesai (bisa >25 detik); perintah tetap jalan di GenieACS
    Http::fake(fn() => throw new ConnectionException('cURL error 28: Operation timed out after 25001 milliseconds with 0 bytes received'));
    $this->assertSame('terkirim', $this->service()->reboot(self::ID));
  }

  public function test_task_gagal_konek_tetap_exception(): void
  {
    Http::fake(fn() => throw new ConnectionException('cURL error 7: Failed to connect to acs.test port 7557'));
    $this->expectException(GenieAcsTidakTerjangkau::class);
    $this->service()->reboot(self::ID);
  }

  public function test_task_faulted_bukan_antri(): void
  {
    $ont = $this->ont(null, fn() => new \GuzzleHttp\Promise\FulfilledPromise(new \GuzzleHttp\Psr7\Response(202, [], '{}', '1.1', 'Task faulted')));
    $this->expectException(GenieAcsTidakTerjangkau::class);
    $this->expectExceptionMessage('ONT menolak perintah');
    $this->service()->setWifi($ont, ['ssid' => 'Rumah']);
  }

  public function test_task_202_antri_pakai_reason_asli(): void
  {
    Http::fake(fn() => new \GuzzleHttp\Promise\FulfilledPromise(new \GuzzleHttp\Psr7\Response(202, [], '{}', '1.1', 'Task queued but not processed')));
    $this->assertSame('antri', $this->service()->reboot(self::ID));
  }

  public function test_pppoe_cocok_ont_milik_pelanggan_lain_tidak_dihubungkan(): void
  {
    // X sudah memegang SN ini; ONT kemudian dial dengan PPPoE milik Y
    $this->client('lama@tji-1', 'ZTEGC1234567');
    $y = $this->client('rizal@tji-1');
    Http::fake(['acs.test:7557/devices/*' => Http::response([self::device()])]);

    $hasil = $this->service()->ontPelanggan($y);

    $this->assertSame('kandidat', $hasil['status']);
    $this->assertNull($y->fresh()->ont_sn);
  }

  public function test_respons_401_atau_bukan_daftar_lempar_exception(): void
  {
    Http::fake(['acs.test:7557/*' => Http::response('<html>Unauthorized</html>', 401)]);
    try {
      $this->service()->devices([]);
      $this->fail('401 harus dilempar');
    } catch (GenieAcsTidakTerjangkau) {
    }
    Http::fake(['acs.test:7557/*' => Http::response(['bukan' => 'daftar'])]);
    $this->expectException(GenieAcsTidakTerjangkau::class);
    $this->service()->devices([]);
  }

  public function test_respons_500_lempar_exception(): void
  {
    Http::fake(['acs.test:7557/*' => Http::response('error', 500)]);
    $this->expectException(GenieAcsTidakTerjangkau::class);
    $this->service()->ontPelanggan($this->client());
  }
}
