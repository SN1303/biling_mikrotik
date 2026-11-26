<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GenieAcsService;
use App\Services\RemoteOntService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Sesi remote web ONT: tiket sekali pakai, sesi di cache, dan cek ulang IP ke GenieACS.
 */
class RemoteOntServiceTest extends TestCase
{
  use RefreshDatabase;

  public const SID = 'sesi-billing-uji-0000000000000000000000';

  private User $staf;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    config(['services.genieacs.url' => 'http://acs.test:7557', 'services.genieacs.remote_host' => 'ont.test', 'cache.default' => 'array']);
    $this->staf = User::create(['name' => 'Teknisi', 'email' => 'tek@test', 'wa' => '1', 'password' => 'x', 'idRole' => 4, 'isActive' => 1]);
    Session::getHandler()->write(self::SID, 'x');
  }

  public static function deviceDenganIp(string $ip = '10.1.1.5', array $vp = []): array
  {
    return GenieAcsServiceTest::device($vp + ['IPTR069' => $ip, 'superAdmin' => 'telecomadmin', 'superPassword' => 'adm1n']);
  }

  private function service(): RemoteOntService
  {
    return app(RemoteOntService::class);
  }

  /** Bentuk `ont` dari device tiruan. */
  private function ont(array $device): array
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response([$device])]);
    return app(GenieAcsService::class)->devices([])[0];
  }

  /**
   * Sesi remote siap pakai (ONT di 10.1.1.5); mengembalikan id sesi. $nbiBerikut menentukan
   * balasan NBI untuk cek ulang IP: 'mati' = koneksi gagal, array = daftar device.
   */
  private function sesiBaru(string|array|null $nbiBerikut = null): string
  {
    $urutan = Http::sequence()->push([self::deviceDenganIp()]);
    if ($nbiBerikut === 'mati') {
      $urutan->pushFailedConnection();
    } elseif (is_array($nbiBerikut)) {
      $urutan->push($nbiBerikut);
    }
    Http::fake(['acs.test:7557/devices/*' => $urutan]);
    $ont = app(GenieAcsService::class)->devices([])[0];
    return $this->service()->tukarTiket($this->service()->buatTiket($this->staf, self::SID, $ont));
  }

  public function test_ont_punya_ip_dan_login(): void
  {
    $ont = $this->ont(self::deviceDenganIp());
    $this->assertSame('10.1.1.5', $ont['ip']);
    $this->assertSame(['user' => 'telecomadmin', 'pass' => 'adm1n'], $ont['login']['admin']);
    $this->assertSame(['user' => null, 'pass' => null], $ont['login']['user']);
  }

  public function test_ip_cadangan_pppoe(): void
  {
    $ont = $this->ont(GenieAcsServiceTest::device(['IPTR069' => '', 'pppoeIP' => '100.66.1.2']));
    $this->assertSame('100.66.1.2', $ont['ip']);
  }

  public function test_tiket_ditukar_sekali(): void
  {
    $tiket = $this->service()->buatTiket($this->staf, self::SID, $this->ont(self::deviceDenganIp()));
    $this->assertNotNull($this->service()->tukarTiket($tiket));
    $this->assertNull($this->service()->tukarTiket($tiket));
  }

  public function test_tiket_kedaluwarsa_atau_palsu_ditolak(): void
  {
    $tiket = $this->service()->buatTiket($this->staf, self::SID, $this->ont(self::deviceDenganIp()));
    $this->travel(61)->seconds();
    $this->assertNull($this->service()->tukarTiket($tiket));
    $this->assertNull($this->service()->tukarTiket('bukan-tiket'));
  }

  public function test_sesi_sah_berisi_ip(): void
  {
    $this->assertSame(['ip' => '10.1.1.5', 'user' => $this->staf->id], $this->service()->sesi($this->sesiBaru()));
    $this->assertNull($this->service()->sesi(null));
    $this->assertNull($this->service()->sesi('tidak-ada'));
  }

  public function test_sesi_berakhir_bila_user_nonaktif(): void
  {
    $id = $this->sesiBaru();
    $this->staf->update(['isActive' => 0]);
    $this->assertNull($this->service()->sesi($id));
  }

  public function test_sesi_berakhir_bila_role_jadi_kolektor(): void
  {
    $id = $this->sesiBaru();
    $this->staf->update(['idRole' => 6]);
    $this->assertNull($this->service()->sesi($id));
  }

  public function test_sesi_berakhir_setelah_logout_billing(): void
  {
    $id = $this->sesiBaru();
    Session::getHandler()->destroy(self::SID);
    $this->assertNull($this->service()->sesi($id));
  }

  public function test_sesi_berakhir_setelah_2_jam(): void
  {
    $id = $this->sesiBaru();
    $this->travel(121)->minutes();
    $this->assertNull($this->service()->sesi($id));
  }

  public function test_ip_dicek_ulang_dan_ikut_berubah(): void
  {
    $id = $this->sesiBaru([self::deviceDenganIp('10.1.1.9')]);
    $this->travel(6)->minutes();
    $this->assertSame('10.1.1.9', $this->service()->sesi($id)['ip']);
    Http::assertSentCount(2);
  }

  public function test_ip_tidak_dicek_sebelum_5_menit(): void
  {
    $id = $this->sesiBaru([self::deviceDenganIp('10.1.1.9')]);
    $this->travel(4)->minutes();
    $this->assertSame('10.1.1.5', $this->service()->sesi($id)['ip']);
    Http::assertSentCount(1); // hanya pencarian awal
  }

  public function test_device_hilang_sesi_berakhir(): void
  {
    $id = $this->sesiBaru([]);
    $this->travel(6)->minutes();
    $this->assertNull($this->service()->sesi($id));
  }

  public function test_genieacs_mati_pakai_ip_lama(): void
  {
    $id = $this->sesiBaru('mati');
    $this->travel(6)->minutes();
    $this->assertSame('10.1.1.5', $this->service()->sesi($id)['ip']);
    Http::assertSentCount(2); // cek ulang benar-benar dicoba
  }

  public function test_tanpa_ip_tidak_bisa_buat_tiket(): void
  {
    $this->expectException(\DomainException::class);
    $this->service()->buatTiket($this->staf, self::SID, $this->ont(GenieAcsServiceTest::device()));
  }

  public static function ipBerbahaya(): array
  {
    return ['loopback' => ['127.0.0.1'], 'dengan port' => ['127.0.0.1:7557'], 'metadata' => ['169.254.169.254'],
      'nama host' => ['acs.test'], 'nol' => ['0.0.0.0'], 'ipv6 loopback' => ['::1']];
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('ipBerbahaya')]
  public function test_ip_dari_device_divalidasi(string $ip): void
  {
    // IP dilaporkan device (bisa dikendalikan pelanggan): jangan sampai billing mem-proxy ke layanan lokalnya sendiri
    $this->expectException(\DomainException::class);
    $this->service()->buatTiket($this->staf, self::SID, $this->ont(self::deviceDenganIp($ip)));
  }

  public function test_ip_lan_dan_cgnat_diizinkan(): void
  {
    foreach (['10.116.202.236', '100.66.30.96', '192.168.1.1', '172.16.5.5'] as $ip) {
      $this->assertNotEmpty($this->service()->buatTiket($this->staf, self::SID, ['id' => 'D', 'ip' => $ip]));
    }
  }

  public function test_ip_berubah_jadi_berbahaya_sesi_berakhir(): void
  {
    $id = $this->sesiBaru([self::deviceDenganIp('127.0.0.1')]);
    $this->travel(6)->minutes();
    $this->assertNull($this->service()->sesi($id));
  }

  public function test_aktif_butuh_remote_host_dan_genieacs(): void
  {
    $this->assertTrue($this->service()->aktif());
    config(['services.genieacs.remote_host' => '']);
    $this->assertFalse($this->service()->aktif());
    config(['services.genieacs.remote_host' => 'ont.test', 'services.genieacs.url' => '']);
    $this->assertFalse($this->service()->aktif());
  }
}
