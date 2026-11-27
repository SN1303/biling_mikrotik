<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Laporan PPPoE up/down dari skrip on-up/on-down MikroTik, dan pengirim Telegram.
 */
class PppEventTest extends TestCase
{
  use RefreshDatabase;

  private int $mikrotik;
  private int $rizal;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    config(['services.ppp_notif.secret' => 'rahasia']);
    $this->mikrotik = self::buatMikrotik();
    $this->rizal = self::buatPelanggan('Rizal', 'rizal@tj-1', $this->mikrotik);
  }

  public static function buatMikrotik(string $kode = 'MT1'): int
  {
    return DB::table('lamtim_mikrotiks')->insertGetId(['kode' => $kode, 'nama' => "MikroTik $kode", 'username' => 'api', 'ip' => '10.0.0.1', 'port' => 8728]);
  }

  /** Pelanggan aktif dengan detail PPPoE; mengembalikan id baris lamtim_user_mikrotik_details. */
  public static function buatPelanggan(string $nama, string $pppoe, ?int $idMikrotik, ?int $idOdp = null, array $detail = []): int
  {
    $user = User::create(['name' => $nama, 'email' => uniqid('', true) . '@test', 'wa' => '2', 'password' => 'x', 'idRole' => 5, 'isActive' => 1]);
    static $paket = null;
    if (!$paket || !DB::table('lamtim_pakets')->where('id', $paket)->exists()) {
      $kat = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'Rumahan', 'description' => '-']);
      $paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kat, 'kode' => 'P' . uniqid(), 'nama' => '10 Mbps', 'price' => '100000']);
    }
    return DB::table('lamtim_user_mikrotik_details')->insertGetId($detail + [
      'idUser' => $user->id, 'idPaket' => $paket, 'statusIsolir' => 0, 'namaMikrotikUser' => $pppoe,
      'idMikrotik' => $idMikrotik, 'idOdp' => $idOdp, 'status' => 'online',
    ]);
  }

  private function lapor(string $event, string $user = 'rizal@tj-1', ?string $token = 'rahasia', ?int $m = null)
  {
    $header = $token === null ? [] : ['X-Notif-Token' => $token];
    return $this->post('/api/mikrotik/ppp-event', ['m' => $m ?? $this->mikrotik, 'e' => $event, 'u' => $user], $header);
  }

  private function baris(int $id): object
  {
    return DB::table('lamtim_user_mikrotik_details')->find($id);
  }

  public function test_token_salah_403(): void
  {
    $this->lapor('down', token: null)->assertForbidden();
    $this->lapor('down', token: 'salah')->assertForbidden();
    config(['services.ppp_notif.secret' => '']);
    $this->lapor('down', token: '')->assertForbidden();
    $this->assertNull($this->baris($this->rizal)->ppp_online);
  }

  public function test_down_catat_offline_sejak(): void
  {
    $this->freezeSecond();
    $this->lapor('down')->assertOk();
    $b = $this->baris($this->rizal);
    $this->assertSame(0, (int) $b->ppp_online);
    $this->assertSame(now()->toDateTimeString(), $b->ppp_offline_sejak);

    $awal = $b->ppp_offline_sejak;
    $this->travel(5)->minutes();
    $this->lapor('down')->assertOk();
    $this->assertSame($awal, $this->baris($this->rizal)->ppp_offline_sejak);
  }

  public function test_up_sebelum_dikabari_hapus_offline_sejak(): void
  {
    $this->lapor('down');
    $this->lapor('up')->assertOk();
    $b = $this->baris($this->rizal);
    $this->assertSame(1, (int) $b->ppp_online);
    $this->assertNull($b->ppp_offline_sejak);
    $this->assertSame(0, (int) $b->ppp_dikabari);
  }

  public function test_up_setelah_dikabari_biarkan_untuk_pesan_online(): void
  {
    $this->lapor('down');
    DB::table('lamtim_user_mikrotik_details')->where('id', $this->rizal)->update(['ppp_dikabari' => 1]);
    $this->lapor('up');
    $b = $this->baris($this->rizal);
    $this->assertSame(1, (int) $b->ppp_online);
    $this->assertNotNull($b->ppp_offline_sejak);
  }

  public function test_user_tak_dikenal_200(): void
  {
    $this->lapor('down', 'siapa@x')->assertOk()->assertJson(['ok' => true]);
  }

  public function test_event_tidak_dikenal_422(): void
  {
    $this->lapor('hilang')->assertStatus(422);
  }

  public function test_mikrotik_lain_tidak_kena(): void
  {
    $lain = self::buatMikrotik('MT2');
    $kembarLain = self::buatPelanggan('Rizal Lain', 'rizal@tj-1', $lain);
    $tanpaMikrotik = self::buatPelanggan('Tanpa MT', 'tanpa@tj-1', null);

    $this->lapor('down')->assertOk();
    $this->assertNull($this->baris($kembarLain)->ppp_online);
    $this->assertSame(0, (int) $this->baris($this->rizal)->ppp_online);

    $this->lapor('down', 'tanpa@tj-1')->assertOk();
    $this->assertSame(0, (int) $this->baris($tanpaMikrotik)->ppp_online);
  }

  public function test_banyak_laporan_tidak_kena_rate_limit(): void
  {
    // Listrik berkedip: puluhan sesi down/up dari satu IP MikroTik dalam semenit
    for ($i = 0; $i < 70; $i++) {
      $this->lapor($i % 2 ? 'up' : 'down')->assertOk();
    }
  }

  public function test_telegram_bagian_pertama_terkirim_dianggap_terkirim(): void
  {
    config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '99']);
    Http::fake(['api.telegram.org/*' => Http::sequence()->push(['ok' => true])->push(['ok' => false], 429)]);
    $teks = implode("\n", array_fill(0, 900, str_repeat('x', 9)));
    $this->assertTrue(app(TelegramService::class)->kirim($teks)); // jangan kirim ulang bagian 1 tiap menit
  }

  public function test_telegram_bagian_tidak_lewat_4000(): void
  {
    config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '99']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    app(TelegramService::class)->kirim(implode("\n", array_fill(0, 2000, '🔴 abc')));
    Http::assertSent(fn($r) => mb_strlen($r['text']) <= 4000);
    foreach (Http::recorded() as [$r]) {
      $this->assertLessThanOrEqual(4000, mb_strlen($r['text']));
    }
  }

  public function test_token_telegram_tidak_masuk_log(): void
  {
    config(['services.telegram.token' => 'TOKENRAHASIA123', 'services.telegram.chat_id' => '99']);
    Http::fake(fn() => throw new ConnectionException('cURL error 28 for https://api.telegram.org/botTOKENRAHASIA123/sendMessage'));
    \Illuminate\Support\Facades\Log::spy();
    app(TelegramService::class)->kirim('halo');
    \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(fn($pesan, $k = []) => !str_contains($pesan . json_encode($k), 'TOKENRAHASIA123'))->once();
  }
  public function test_telegram_kirim_dipecah(): void
  {
    config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '99']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $teks = implode("\n", array_fill(0, 900, str_repeat('x', 9))); // ±9000 karakter
    $this->assertTrue(app(TelegramService::class)->kirim($teks));
    Http::assertSentCount(3);
    Http::assertSent(fn($r) => str_contains($r->url(), '/botT/sendMessage') && $r['chat_id'] === '99' && mb_strlen($r['text']) <= 4096);
  }

  public function test_telegram_gagal_false(): void
  {
    config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '99']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);
    $this->assertFalse(app(TelegramService::class)->kirim('halo'));
  }

  public function test_telegram_putus_false(): void
  {
    config(['services.telegram.token' => 'T', 'services.telegram.chat_id' => '99']);
    Http::fake(fn() => throw new ConnectionException('putus'));
    $this->assertFalse(app(TelegramService::class)->kirim('halo'));
  }

  public function test_telegram_belum_diatur(): void
  {
    config(['services.telegram.token' => '', 'services.telegram.chat_id' => '99']);
    Http::fake();
    $this->assertFalse(app(TelegramService::class)->aktif());
    $this->assertFalse(app(TelegramService::class)->kirim('halo'));
    Http::assertNothingSent();
  }
}
