<?php

namespace Tests\Feature;

use App\Services\MikrotikPpp;
use App\Services\NotifikasiPpp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pencocokan tiap 10 menit dengan /ppp/active dan /ppp/secret, termasuk MikroTik tak terjangkau.
 */
class PppCocokkanTest extends TestCase
{
  use RefreshDatabase;

  /** @var list<string> */
  public static array $aktif = [];
  public static array $secret = [];
  /** berapa panggilan pertama yang dibuat gagal */
  public static int $gagal = 0;

  private int $mt;
  private int $rizal;
  private int $bagos;
  private int $baru;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '99', 'services.ppp_notif.ulang_detik' => 0]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    self::$aktif = [];
    self::$secret = [];
    self::$gagal = 0;
    app()->instance(MikrotikPpp::class, new class extends MikrotikPpp {
      public function aktif(int $idMikrotik): array
      {
        if (PppCocokkanTest::$gagal-- > 0) throw new \RuntimeException('MikroTik tidak terjangkau');
        return PppCocokkanTest::$aktif;
      }
      public function secretNonaktif(int $idMikrotik): array
      {
        return PppCocokkanTest::$secret;
      }
    });

    $this->mt = PppEventTest::buatMikrotik();
    $this->rizal = PppEventTest::buatPelanggan('RIZAL', 'rizal@tj-1', $this->mt, null, ['ppp_online' => 1]);
    $this->bagos = PppEventTest::buatPelanggan('BAGOS', 'bagos@tj-6', $this->mt, null, ['ppp_online' => 0, 'ppp_offline_sejak' => now()->subHour()]);
    $this->baru = PppEventTest::buatPelanggan('BARU', 'baru@tj-1', $this->mt, null);
  }

  private function b(int $id): object
  {
    return DB::table('lamtim_user_mikrotik_details')->find($id);
  }

  private function cocokkan(): void
  {
    app(NotifikasiPpp::class)->cocokkan($this->mt);
  }

  public function test_cocokkan_membetulkan_status(): void
  {
    $this->freezeSecond();
    self::$aktif = ['bagos@tj-6', 'baru@tj-1'];
    $this->cocokkan();
    $this->assertSame(0, (int) $this->b($this->rizal)->ppp_online);
    $this->assertSame(now()->toDateTimeString(), $this->b($this->rizal)->ppp_offline_sejak);
    $this->assertSame(1, (int) $this->b($this->bagos)->ppp_online);
    $this->assertNull($this->b($this->bagos)->ppp_offline_sejak);
    $this->assertSame(1, (int) $this->b($this->baru)->ppp_online);
    Http::assertNothingSent();
  }

  public function test_offline_sejak_tidak_digeser(): void
  {
    $awal = $this->b($this->bagos)->ppp_offline_sejak;
    $this->cocokkan(); // bagos tetap tidak aktif
    $this->assertSame($awal, $this->b($this->bagos)->ppp_offline_sejak);
  }

  public function test_online_yang_sudah_dikabari_tetap_simpan_jam(): void
  {
    DB::table('lamtim_user_mikrotik_details')->where('id', $this->bagos)->update(['ppp_dikabari' => 1]);
    self::$aktif = ['bagos@tj-6'];
    $this->cocokkan();
    $this->assertSame(1, (int) $this->b($this->bagos)->ppp_online);
    $this->assertNotNull($this->b($this->bagos)->ppp_offline_sejak); // untuk pesan "online lagi"
  }

  public function test_secret_disabled_ditandai(): void
  {
    self::$secret = ['rizal@tj-1' => true, 'bagos@tj-6' => false];
    DB::table('lamtim_user_mikrotik_details')->where('id', $this->bagos)->update(['ppp_secret_nonaktif' => 1]);
    $this->cocokkan();
    $this->assertSame(1, (int) $this->b($this->rizal)->ppp_secret_nonaktif);
    $this->assertSame(0, (int) $this->b($this->bagos)->ppp_secret_nonaktif);
  }

  public function test_mikrotik_tak_terjangkau_satu_pesan_dan_status_tetap(): void
  {
    self::$gagal = 99;
    $this->cocokkan();
    Http::assertSentCount(1);
    Http::assertSent(fn($r) => str_contains($r['text'], 'tidak terjangkau'));
    $this->assertSame(1, (int) $this->b($this->rizal)->ppp_online);
    $this->cocokkan();
    Http::assertSentCount(1);
  }

  public function test_mikrotik_pulih_satu_pesan(): void
  {
    self::$gagal = 2;
    $this->cocokkan(); // gagal dua kali → tidak terjangkau
    $this->cocokkan(); // pulih
    Http::assertSentCount(2);
    Http::assertSent(fn($r) => str_contains($r['text'], 'terjangkau lagi'));
    $this->cocokkan();
    Http::assertSentCount(2);
  }

  public function test_gagal_sekali_lalu_berhasil_tanpa_pesan(): void
  {
    self::$gagal = 1;
    self::$aktif = ['rizal@tj-1'];
    $this->cocokkan();
    Http::assertNothingSent();
    $this->assertSame(1, (int) $this->b($this->rizal)->ppp_online);
  }

  public function test_pelanggan_mikrotik_lain_tidak_disentuh(): void
  {
    $lain = PppEventTest::buatMikrotik('MT2');
    $diLain = PppEventTest::buatPelanggan('LAIN', 'lain@x', $lain, null, ['ppp_online' => 1]);
    $tanpa = PppEventTest::buatPelanggan('TANPA', 'tanpa@x', null, null, ['ppp_online' => 1]);
    $this->cocokkan();
    $this->assertSame(1, (int) $this->b($diLain)->ppp_online);
    // Dua MikroTik aktif: pelanggan tanpa idMikrotik tidak bisa dipastikan milik yang mana
    $this->assertSame(1, (int) $this->b($tanpa)->ppp_online);
  }

  public function test_command_semua_mikrotik_aktif(): void
  {
    self::$aktif = ['rizal@tj-1'];
    $this->artisan('ppp:cocokkan')->assertSuccessful();
    $this->assertSame(0, (int) $this->b($this->baru)->ppp_online);
  }
}
