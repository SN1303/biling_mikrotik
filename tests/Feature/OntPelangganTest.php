<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman ONT pelanggan (/pelanggan/{id}/ont) dan aksinya. NBI GenieACS ditiru dengan Http::fake.
 */
class OntPelangganTest extends TestCase
{
  use RefreshDatabase;

  private int $pelanggan;
  private int $client;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    $this->withoutMiddleware(VerifyCsrfToken::class);
    config(['services.genieacs.url' => 'http://acs.test:7557', 'services.genieacs.online_menit' => 10]);

    $kat = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'Rumahan', 'description' => '-']);
    $paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kat, 'kode' => 'P10', 'nama' => '10 Mbps', 'price' => '100000']);
    $this->pelanggan = User::create(['name' => 'Rizal', 'email' => 'rizal@test', 'wa' => '2', 'password' => 'x', 'idRole' => 5, 'isActive' => 1])->id;
    DB::table('lamtim_user_details')->insert(['idUser' => $this->pelanggan]);
    $this->client = DB::table('lamtim_user_mikrotik_details')->insertGetId([
      'idUser' => $this->pelanggan, 'idPaket' => $paket, 'statusIsolir' => 0,
      'namaMikrotikUser' => 'rizal@tji-1', 'wifi_name' => 'NAMA-LAMA',
    ]);
  }

  private function masukSebagai(int $role): User
  {
    $u = User::create(['name' => "Staf $role", 'email' => "staf$role@test", 'wa' => "9$role", 'password' => 'x', 'idRole' => $role, 'isActive' => 1]);
    $this->actingAs($u);
    return $u;
  }

  private function url(string $aksi = ''): string
  {
    return '/pelanggan/' . encrypt($this->pelanggan) . '/ont' . ($aksi ? "/$aksi" : '');
  }

  /** Pencarian device membalas ONT Rizal; task membalas $statusTask. */
  /** $wlan = isi WLANConfiguration (lihat GenieAcsServiceTest::device); null = single-band bawaan. */
  private function fakeNbi(int $statusTask = 200, ?array $wlan = null): void
  {
    Http::fake([
      'acs.test:7557/devices/?*' => Http::response([GenieAcsServiceTest::device([], 'ZTEGC1234567', 2, $wlan)]),
      'acs.test:7557/devices/*/tasks*' => Http::response('', $statusTask),
    ]);
  }

  public function test_halaman_tampil_status_dan_redaman(): void
  {
    $this->masukSebagai(1);
    $this->fakeNbi();
    $this->get($this->url())->assertOk()->assertSee('RIZAL-NET')->assertSee('-19,8 dBm')->assertSee('baik');
  }

  public function test_genieacs_mati_halaman_tetap_200(): void
  {
    $this->masukSebagai(1);
    Http::fake(fn() => throw new ConnectionException('timeout'));
    $this->get($this->url())->assertOk()->assertSee('GenieACS tidak terjangkau');
  }

  public function test_url_kosong_tampil_belum_diatur(): void
  {
    config(['services.genieacs.url' => '']);
    $this->masukSebagai(1);
    $this->get($this->url())->assertOk()->assertSee('GenieACS belum diatur');
    $this->postJson($this->url('reboot'))->assertStatus(422);
  }

  public static function roleDitolak(): array
  {
    return ['bendahara' => [3], 'kolektor' => [6]];
  }

  #[DataProvider('roleDitolak')]
  public function test_kolektor_dan_bendahara_ditolak(int $role): void
  {
    $this->masukSebagai($role);
    $this->fakeNbi();
    $this->get($this->url())->assertForbidden();
    $this->postJson($this->url('reboot'))->assertForbidden();
  }

  public static function roleTeknisi(): array
  {
    return ['teknisi' => [4], 'teknisi-kolektor' => [7]];
  }

  #[DataProvider('roleTeknisi')]
  public function test_teknisi_boleh_reboot(int $role): void
  {
    $this->masukSebagai($role);
    $this->fakeNbi(200);
    $this->postJson($this->url('reboot'))->assertOk()->assertJson(['status' => 'selesai']);
  }

  public function test_ganti_wifi_202_tidak_ubah_wifi_name(): void
  {
    $this->masukSebagai(1);
    $this->fakeNbi(202);
    $res = $this->postJson($this->url('wifi'), ['band' => '24', 'ssid' => 'Rumah Budi'])->assertOk()->assertJson(['status' => 'antri']);
    $this->assertStringContainsString('saat ONT online', $res->json('message'));
    $this->assertDatabaseHas('lamtim_user_mikrotik_details', ['id' => $this->client, 'wifi_name' => 'NAMA-LAMA']);
  }

  public function test_ganti_wifi_200_ubah_wifi_name(): void
  {
    $this->masukSebagai(1);
    $this->fakeNbi(200);
    $this->postJson($this->url('wifi'), ['band' => '24', 'ssid' => 'Rumah Budi'])->assertOk()->assertJson(['status' => 'selesai']);
    $this->assertDatabaseHas('lamtim_user_mikrotik_details', ['id' => $this->client, 'wifi_name' => 'Rumah Budi']);
  }

  public function test_keduanya_pakai_password_sama_untuk_5ghz(): void
  {
    $this->masukSebagai(1);
    $this->fakeNbi(200, ['1' => ['SSID' => 'RIZAL-NET', 'KeyPassphrase' => 'x'], '5' => ['SSID' => 'RIZAL-NET-5G', 'KeyPassphrase' => 'y']]);
    $this->get($this->url())->assertSee('Keduanya');
    $this->postJson($this->url('wifi'), ['band' => 'keduanya', 'ssid' => 'Budi', 'ssid5' => 'Budi-5G', 'password' => '12345678'])->assertOk();
    $w = GenieAcsServiceTest::WLAN;
    Http::assertSent(function ($r) use ($w) {
      if (!str_contains($r->url(), '/tasks')) return false;
      $this->assertEqualsCanonicalizing([
        ["$w.1.SSID", 'Budi', 'xsd:string'],
        ["$w.5.SSID", 'Budi-5G', 'xsd:string'],
        ['VirtualParameters.WlanPassword', '12345678', 'xsd:string'],
      ], json_decode($r->body(), true)['parameterValues']);
      return true;
    });
  }

  public function test_validasi_wifi(): void
  {
    $this->masukSebagai(1);
    $this->fakeNbi(200);
    $this->postJson($this->url('wifi'), ['band' => '24', 'ssid' => str_repeat('a', 33)])->assertStatus(422);
    $this->postJson($this->url('wifi'), ['band' => '24', 'password' => '1234567'])->assertStatus(422);
    $this->postJson($this->url('wifi'), ['band' => '24', 'ssid' => '', 'password' => ''])->assertStatus(422);
    $this->postJson($this->url('wifi'), ['band' => 'x', 'ssid' => 'Rumah'])->assertStatus(422);
    $this->postJson($this->url('wifi'), ['band' => '24', 'ssid' => 'Rumah Budi 2'])->assertOk();
  }

  public function test_ssid_tidak_terbaca_form_wifi_tidak_tampil_dan_ditolak(): void
  {
    // ONT tanpa jalur WiFi yang dikenal (merek lain / data belum terbaca): jangan izinkan menulis
    $this->masukSebagai(1);
    $this->fakeNbi(200, []);
    $this->get($this->url())->assertOk()->assertDontSee('Simpan WiFi')->assertSee('Reboot ONT');
    $this->postJson($this->url('wifi'), ['band' => '24', 'ssid' => 'Rumah'])->assertStatus(422);
    Http::assertNotSent(fn($r) => str_contains($r->url(), '/tasks') && str_contains($r->body(), 'setParameterValues'));
  }

  public function test_task_lambat_pesan_terkirim(): void
  {
    $this->masukSebagai(1);
    Http::fake([
      'acs.test:7557/devices/?*' => Http::response([GenieAcsServiceTest::device()]),
      'acs.test:7557/devices/*/tasks*' => fn() => throw new ConnectionException('cURL error 28: Operation timed out after 25001 milliseconds'),
    ]);
    $res = $this->postJson($this->url('wifi'), ['band' => '24', 'ssid' => 'Rumah Budi'])->assertOk()->assertJson(['status' => 'terkirim']);
    $this->assertStringContainsString('Refresh', $res->json('message'));
    $this->assertDatabaseHas('lamtim_user_mikrotik_details', ['id' => $this->client, 'wifi_name' => 'NAMA-LAMA']);
  }

  public function test_toast_tidak_menafsirkan_html(): void
  {
    $this->masukSebagai(1);
    $this->fakeNbi();
    $this->get($this->url())->assertSee('toastr.options.escapeHtml = true', false);
    $this->get('/ont/belum-terhubung')->assertSee('toastr.options.escapeHtml = true', false);
  }

  public function test_menu_ont_tampil_bila_aktif(): void
  {
    $this->masukSebagai(4);
    $this->get('/user')->assertOk()->assertSee('ONT Belum Terhubung')->assertSee('ONT &amp; WiFi', false);
  }

  public function test_menu_ont_hilang_bila_url_kosong(): void
  {
    config(['services.genieacs.url' => '']);
    $this->masukSebagai(4);
    $this->get('/user')->assertOk()->assertDontSee('ONT Belum Terhubung')->assertDontSee('ONT &amp; WiFi', false);
  }

  public function test_menu_ont_tidak_untuk_kolektor(): void
  {
    $this->masukSebagai(6);
    $this->get('/user')->assertOk()->assertDontSee('ONT Belum Terhubung')->assertDontSee('ONT &amp; WiFi', false);
  }

  public function test_peta_menyertakan_ont_url(): void
  {
    DB::table('lamtim_user_mikrotik_details')->where('id', $this->client)->update(['latitude' => -5.0, 'longitude' => 105.0]);
    $this->masukSebagai(4);
    $client = app(\App\Services\NetworkMapService::class)->getClients()[0];
    $this->assertStringContainsString('/pelanggan/', $client['ont_url']);
    $this->assertStringEndsWith('/ont', $client['ont_url']);

    config(['services.genieacs.url' => '']);
    $this->assertArrayNotHasKey('ont_url', app(\App\Services\NetworkMapService::class)->getClients()[0]);
  }

  public function test_log_tidak_memuat_password(): void
  {
    $this->masukSebagai(1);
    $this->fakeNbi(200);
    Log::spy();
    $this->postJson($this->url('wifi'), ['band' => '24', 'ssid' => 'Rumah', 'password' => 'rahasia-baru-99'])->assertOk();
    Log::shouldHaveReceived('info')->withArgs(fn($pesan, $konteks = []) => str_contains($pesan, 'WiFi')
      && !str_contains(json_encode($konteks), 'rahasia-baru-99'))->once();
  }
}
