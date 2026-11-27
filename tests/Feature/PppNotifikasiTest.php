<?php

namespace Tests\Feature;

use App\Services\MikrotikPpp;
use App\Services\MikrotikService;
use App\Services\NotifikasiPpp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ringkasan Telegram: pelanggan offline > jeda (per ODP, judul massal) dan online lagi.
 */
class PppNotifikasiTest extends TestCase
{
  use RefreshDatabase;

  private int $mt;
  private array $p = []; // nama => id baris detail
  /** @var array<string,bool>|\RuntimeException balasan tiruan secretNonaktif */
  public static array|\RuntimeException $secret = [];
  /** @var list<array{0:array,1:int}> antrean balasan Telegram; kosong = selalu ok */
  public static array $telegram = [];

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '99',
      'services.ppp_notif.jeda_menit' => 10, 'services.ppp_notif.ambang_massal' => 3]);
    self::$telegram = [];
    Http::fake(['api.telegram.org/*' => function () {
      [$isi, $kode] = array_shift(self::$telegram) ?? [['ok' => true], 200];
      return Http::response($isi, $kode);
    }]);
    self::$secret = [];
    app()->instance(MikrotikPpp::class, new class extends MikrotikPpp {
      public function secretNonaktif(int $idMikrotik): array
      {
        if (PppNotifikasiTest::$secret instanceof \RuntimeException) throw PppNotifikasiTest::$secret;
        return PppNotifikasiTest::$secret;
      }
    });

    $this->mt = PppEventTest::buatMikrotik();
    $olt = DB::table('lamtim_olts')->insertGetId(['kode' => 'OLT1', 'nama' => 'OLT', 'latitude' => -5, 'longitude' => 105]);
    $tji1 = DB::table('lamtim_odps')->insertGetId(['idOlt' => $olt, 'kode' => 'TJI1', 'nama' => 'TJI-1']);
    $tji6 = DB::table('lamtim_odps')->insertGetId(['idOlt' => $olt, 'kode' => 'TJI6', 'nama' => 'TJI-6']);
    foreach (['RIZAL' => $tji1, 'DALIJO' => $tji1, 'SUMILAN' => $tji1, 'SRIEDI' => $tji1, 'BAGOS' => $tji6] as $nama => $odp) {
      $this->p[$nama] = PppEventTest::buatPelanggan($nama, strtolower($nama) . '@tj', $this->mt, $odp, ['ppp_online' => 1]);
    }
  }

  private function offline(string $nama, int $menit, array $lain = []): void
  {
    DB::table('lamtim_user_mikrotik_details')->where('id', $this->p[$nama])
      ->update($lain + ['ppp_online' => 0, 'ppp_offline_sejak' => now()->subMinutes($menit)]);
  }

  private function kol(string $nama, string $kolom)
  {
    return DB::table('lamtim_user_mikrotik_details')->where('id', $this->p[$nama])->value($kolom);
  }

  private function jalankan(): ?string
  {
    return app(NotifikasiPpp::class)->jalankan();
  }

  public function test_offline_kurang_10_menit_tidak_dikirim(): void
  {
    $this->offline('RIZAL', 9);
    $this->assertNull($this->jalankan());
    Http::assertNothingSent();
  }

  public function test_offline_10_menit_dikirim_per_odp(): void
  {
    $this->offline('RIZAL', 11);
    $this->offline('BAGOS', 11);
    $teks = $this->jalankan();
    foreach (['ODP TJI-1', 'RIZAL (rizal@tj)', 'ODP TJI-6', 'BAGOS', '11 menit'] as $isi) {
      $this->assertStringContainsString($isi, $teks);
    }
    $this->assertStringNotContainsString('⚠️', $teks);
    $this->assertSame(1, (int) $this->kol('RIZAL', 'ppp_dikabari'));
    $this->assertNull($this->jalankan());
  }

  public function test_judul_massal_3_dari_4(): void
  {
    foreach (['RIZAL', 'DALIJO', 'SUMILAN'] as $n) $this->offline($n, 12);
    $teks = $this->jalankan();
    $this->assertStringContainsString('⚠️', $teks);
    $this->assertStringContainsString('3 dari 4', $teks);
  }

  public function test_massal_menghitung_yang_sudah_dikabari(): void
  {
    $this->offline('RIZAL', 30, ['ppp_dikabari' => 1]);
    $this->offline('DALIJO', 30, ['ppp_dikabari' => 1]);
    $this->offline('SUMILAN', 11);
    $teks = $this->jalankan();
    $this->assertStringContainsString('3 dari 4', $teks);
    $this->assertStringContainsString('SUMILAN', $teks);
    $this->assertStringNotContainsString('RIZAL', $teks);
  }

  public function test_online_lagi_hanya_yang_dikabari(): void
  {
    DB::table('lamtim_user_mikrotik_details')->where('id', $this->p['RIZAL'])
      ->update(['ppp_online' => 1, 'ppp_dikabari' => 1, 'ppp_offline_sejak' => now()->subMinutes(35)]);
    $teks = $this->jalankan();
    $this->assertStringContainsString('🟢', $teks);
    $this->assertStringContainsString('RIZAL', $teks);
    $this->assertStringContainsString('35 menit', $teks);
    $this->assertStringNotContainsString('DALIJO', $teks);
    $this->assertSame(0, (int) $this->kol('RIZAL', 'ppp_dikabari'));
    $this->assertNull($this->kol('RIZAL', 'ppp_offline_sejak'));
  }

  public static function tidakLayak(): array
  {
    return ['isolir' => ['lamtim_user_mikrotik_details', 'statusIsolir', 1], 'akun nonaktif' => ['users', 'isActive', 0],
      'secret nonaktif' => ['lamtim_user_mikrotik_details', 'ppp_secret_nonaktif', 1]];
  }

  #[DataProvider('tidakLayak')]
  public function test_isolir_nonaktif_secret_tidak_dikabari(string $tabel, string $kolom, int $nilai): void
  {
    $this->offline('RIZAL', 20);
    $id = $tabel === 'users' ? DB::table('lamtim_user_mikrotik_details')->where('id', $this->p['RIZAL'])->value('idUser') : $this->p['RIZAL'];
    DB::table($tabel)->where('id', $id)->update([$kolom => $nilai]);
    $this->assertNull($this->jalankan());
  }

  public function test_secret_dinonaktifkan_di_winbox_dibuang(): void
  {
    self::$secret = ['rizal@tj' => true, 'bagos@tj' => false];
    $this->offline('RIZAL', 20);
    $this->offline('BAGOS', 20);
    $teks = $this->jalankan();
    $this->assertStringNotContainsString('RIZAL', $teks);
    $this->assertStringContainsString('BAGOS', $teks);
    $this->assertSame(1, (int) $this->kol('RIZAL', 'ppp_secret_nonaktif'));
  }

  public function test_mikrotik_mati_saat_cek_secret_tetap_kirim(): void
  {
    self::$secret = new \RuntimeException('MikroTik tidak terjangkau');
    $this->offline('RIZAL', 20);
    $this->assertStringContainsString('RIZAL', $this->jalankan());
  }

  public function test_telegram_gagal_tidak_menandai(): void
  {
    self::$telegram = [[['ok' => false], 500]];
    $this->offline('RIZAL', 20);
    $this->assertNull($this->jalankan());
    $this->assertSame(0, (int) $this->kol('RIZAL', 'ppp_dikabari'));
    $this->assertStringContainsString('RIZAL', $this->jalankan());
    $this->assertSame(1, (int) $this->kol('RIZAL', 'ppp_dikabari'));
  }

  public function test_telegram_belum_diatur_diam(): void
  {
    config(['services.telegram.token' => '']);
    $this->offline('RIZAL', 20);
    $this->assertNull($this->jalankan());
    $this->assertSame(0, (int) $this->kol('RIZAL', 'ppp_dikabari'));
  }

  public function test_down_up_berulang_tanpa_pesan(): void
  {
    config(['services.ppp_notif.secret' => 'rahasia']);
    for ($i = 0; $i < 3; $i++) {
      $this->post('/api/mikrotik/ppp-event', ['m' => $this->mt, 'e' => 'down', 'u' => 'rizal@tj'], ['X-Notif-Token' => 'rahasia']);
      $this->travel(2)->minutes();
      $this->post('/api/mikrotik/ppp-event', ['m' => $this->mt, 'e' => 'up', 'u' => 'rizal@tj'], ['X-Notif-Token' => 'rahasia']);
      $this->travel(1)->minutes();
    }
    $this->assertNull($this->jalankan());
    $this->assertNull($this->kol('RIZAL', 'ppp_offline_sejak'));
  }

  public function test_pesan_panjang_dipecah(): void
  {
    $odp = DB::table('lamtim_odps')->value('id');
    for ($i = 0; $i < 120; $i++) {
      PppEventTest::buatPelanggan("PELANGGAN NOMOR $i DENGAN NAMA PANJANG", "pelanggan$i@area-panjang", $this->mt, $odp,
        ['ppp_online' => 0, 'ppp_offline_sejak' => now()->subMinutes(15)]);
    }
    $this->assertNotNull($this->jalankan());
    $this->assertGreaterThan(1, count(Http::recorded()));
    $this->assertSame(0, DB::table('lamtim_user_mikrotik_details')->where('ppp_online', 0)->where('ppp_dikabari', 0)->count());
  }

  public function test_lama_offline_dalam_jam(): void
  {
    $this->offline('RIZAL', 135);
    $this->assertStringContainsString('2 jam 15 menit', $this->jalankan());
  }

  public function test_disable_dari_billing_tandai_secret_nonaktif(): void
  {
    $svc = app(MikrotikService::class);
    $svc->tandaiSecret('rizal@tj', true);
    $this->assertSame(1, (int) $this->kol('RIZAL', 'ppp_secret_nonaktif'));
    $svc->tandaiSecret('rizal@tj', false);
    $this->assertSame(0, (int) $this->kol('RIZAL', 'ppp_secret_nonaktif'));
  }

  public function test_command_jalan(): void
  {
    $this->offline('RIZAL', 20);
    $this->artisan('ppp:notifikasi')->assertSuccessful();
    $this->assertSame(1, (int) $this->kol('RIZAL', 'ppp_dikabari'));
  }
}
