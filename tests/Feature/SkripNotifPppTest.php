<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MikrotikPpp;
use App\Services\SkripNotifPpp;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pasang / cabut blok skrip on-up/on-down di PPP Profile MikroTik, dan skrip manual.
 */
class SkripNotifPppTest extends TestCase
{
  use RefreshDatabase;

  public static array $profil = [];
  public static array $diset = [];
  private int $mt;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    $this->withoutMiddleware(VerifyCsrfToken::class);
    config(['services.ppp_notif.secret' => str_repeat('a1', 16), 'services.ppp_notif.url' => 'http://billing.lan']);
    self::$diset = [];
    self::$profil = [
      ['id' => '*1', 'name' => 'default', 'on-up' => ':log info "lama"', 'on-down' => ''],
      ['id' => '*2', 'name' => '10M', 'on-up' => '', 'on-down' => ''],
    ];
    app()->instance(MikrotikPpp::class, new class extends MikrotikPpp {
      public function profil(int $idMikrotik): array { return SkripNotifPppTest::$profil; }
      public function setSkripProfil(int $idMikrotik, string $id, string $onUp, string $onDown): void
      {
        SkripNotifPppTest::$diset[] = compact('id', 'onUp', 'onDown');
      }
    });
    $this->mt = PppEventTest::buatMikrotik();
  }

  private function skrip(): SkripNotifPpp
  {
    return app(SkripNotifPpp::class);
  }

  private function masuk(int $role): void
  {
    $this->actingAs(User::create(['name' => "Staf $role", 'email' => "s$role@test", 'wa' => '1', 'password' => 'x', 'idRole' => $role, 'isActive' => 1]));
  }

  public function test_blok_berisi_url_secret_event(): void
  {
    $blok = $this->skrip()->blok($this->mt, 'down');
    foreach (['# mryes-notif:mulai', '# mryes-notif:selesai', 'http://billing.lan/api/mikrotik/ppp-event', 'X-Notif-Token: ' . str_repeat('a1', 16), 'Content-Type: application/x-www-form-urlencoded',
      "m={$this->mt}&e=down&u=", 'on-error={}', '$user'] as $isi) {
      $this->assertStringContainsString($isi, $blok);
    }
  }

  public function test_sisipkan_dua_kali_tidak_ganda_dan_cabut_utuh(): void
  {
    $blok = $this->skrip()->blok($this->mt, 'up');
    $sekali = $this->skrip()->sisipkan(':log info "lama"', $blok);
    $dua = $this->skrip()->sisipkan($sekali, $blok);
    $this->assertSame(1, substr_count($dua, '# mryes-notif:mulai'));
    $this->assertStringContainsString(':log info "lama"', $dua);
    $this->assertSame(':log info "lama"', trim($this->skrip()->buang($dua)));
    $this->assertSame('', trim($this->skrip()->buang($this->skrip()->sisipkan('', $blok))));
  }

  public function test_pasang_semua_profil(): void
  {
    $this->assertSame(2, $this->skrip()->pasang($this->mt));
    $this->assertCount(2, self::$diset);
    $this->assertStringContainsString('e=up', self::$diset[0]['onUp']);
    $this->assertStringContainsString(':log info "lama"', self::$diset[0]['onUp']);
    $this->assertStringContainsString('e=down', self::$diset[0]['onDown']);
  }

  public function test_cabut_semua_profil(): void
  {
    self::$profil[0]['on-up'] = $this->skrip()->sisipkan(':log info "lama"', $this->skrip()->blok($this->mt, 'up'));
    $this->assertSame(2, $this->skrip()->cabut($this->mt));
    $this->assertSame(':log info "lama"', trim(self::$diset[0]['onUp']));
  }

  public function test_pasang_ditolak_tanpa_secret(): void
  {
    config(['services.ppp_notif.secret' => '']);
    $this->masuk(1);
    $res = $this->postJson('/mikrotik/' . encrypt($this->mt) . '/notif/pasang')->assertStatus(422);
    $this->assertStringContainsString('PPP_NOTIF_SECRET', $res->json('message'));
    $this->assertCount(0, self::$diset);
  }

  public static function aturanSalah(): array
  {
    return ['secret pendek' => ['services.ppp_notif.secret', 'pendek123', 'PPP_NOTIF_SECRET'],
      'secret berkoma' => ['services.ppp_notif.secret', str_repeat('a', 31) . ',', 'PPP_NOTIF_SECRET'],
      'url kosong' => ['services.ppp_notif.url', '', 'PPP_NOTIF_URL'],
      'url localhost' => ['services.ppp_notif.url', 'http://localhost', 'PPP_NOTIF_URL']];
  }

  #[\PHPUnit\Framework\Attributes\DataProvider('aturanSalah')]
  public function test_pasang_ditolak_bila_aturan_salah(string $kunci, string $nilai, string $pesan): void
  {
    config([$kunci => $nilai]);
    $this->masuk(1);
    $res = $this->postJson('/mikrotik/' . encrypt($this->mt) . '/notif/pasang')->assertStatus(422);
    $this->assertStringContainsString($pesan, $res->json('message'));
    $this->assertCount(0, self::$diset);
  }
  public function test_route_notif_hanya_admin(): void
  {
    $this->masuk(4);
    $this->postJson('/mikrotik/' . encrypt($this->mt) . '/notif/pasang')->assertForbidden();
    $this->masuk(1);
    $this->postJson('/mikrotik/' . encrypt($this->mt) . '/notif/pasang')->assertOk()->assertJsonFragment(['jumlah' => 2]);
    $this->postJson('/mikrotik/' . encrypt($this->mt) . '/notif/cabut')->assertOk();
    $this->getJson('/mikrotik/' . encrypt($this->mt) . '/notif/skrip')->assertOk()
      ->assertJsonStructure(['on_up', 'on_down']);
  }

  public function test_mikrotik_tak_terjangkau_503(): void
  {
    app()->instance(MikrotikPpp::class, new class extends MikrotikPpp {
      public function profil(int $idMikrotik): array { throw new \RuntimeException('MikroTik tidak terjangkau'); }
    });
    $this->masuk(1);
    $this->postJson('/mikrotik/' . encrypt($this->mt) . '/notif/pasang')->assertStatus(503);
  }
}
