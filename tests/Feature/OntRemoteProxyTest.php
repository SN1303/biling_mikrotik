<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GenieAcsService;
use App\Services\RemoteOntService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

/**
 * Host remote (ont.test) meneruskan semua request ke web ONT lewat server billing.
 */
class OntRemoteProxyTest extends TestCase
{
  use RefreshDatabase;

  private User $staf;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    config(['services.genieacs.url' => 'http://acs.test:7557', 'services.genieacs.remote_host' => 'ont.test', 'cache.default' => 'array']);
    $this->staf = User::create(['name' => 'Teknisi', 'email' => 'tek@test', 'wa' => '1', 'password' => 'x', 'idRole' => 4, 'isActive' => 1]);
    Session::getHandler()->write(RemoteOntServiceTest::SID, 'x');
  }

  /** Tiket untuk ONT di $ip; ONT tiruan membalas sesuai $ont (pola => balasan). */
  private function tiket(string $ip = '10.1.1.5', array $ont = []): string
  {
    Http::fake(['acs.test:7557/devices/*' => Http::response([RemoteOntServiceTest::deviceDenganIp($ip)])] + $ont);
    $data = app(GenieAcsService::class)->devices([])[0];
    return app(RemoteOntService::class)->buatTiket($this->staf, RemoteOntServiceTest::SID, $data);
  }

  /** Cookie remote yang sah untuk ONT di $ip. */
  private function cookie(string $ip = '10.1.1.5', array $ont = []): string
  {
    return Crypt::encryptString(app(RemoteOntService::class)->tukarTiket($this->tiket($ip, $ont)));
  }

  private function keOnt(array $ont): array
  {
    return ['http://10.1.1.5/*' => $ont[0] ?? Http::response('<html>ONT</html>')] + $ont;
  }

  public function test_mulai_set_cookie_lalu_redirect(): void
  {
    $res = $this->get('http://ont.test/_remote/mulai?tiket=' . urlencode($this->tiket()));
    $res->assertRedirect('/');
    $cookie = collect($res->headers->getCookies())->firstWhere(fn($c) => $c->getName() === 'ont_remote');
    $this->assertNotNull($cookie);
    $this->assertTrue($cookie->isHttpOnly());
    $this->assertSame('strict', $cookie->getSameSite());
  }

  public function test_tiket_tidak_sah_halaman_berakhir(): void
  {
    $this->get('http://ont.test/_remote/mulai?tiket=palsu')->assertForbidden()->assertSee('Sesi remote berakhir');
  }

  public function test_tanpa_cookie_halaman_berakhir(): void
  {
    Http::fake();
    $this->get('http://ont.test/')->assertForbidden()->assertSee('Sesi remote berakhir');
    Http::assertNothingSent();
  }

  public function test_cookie_rusak_halaman_berakhir(): void
  {
    $this->withUnencryptedCookie('ont_remote', 'rusak')->get('http://ont.test/')->assertForbidden();
  }

  public function test_get_diteruskan_ke_ip_sesi(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('<html>Halaman ZTE</html>', 200, ['Content-Type' => 'text/html'])]);
    $this->withUnencryptedCookie('ont_remote', $cookie)->get('http://ont.test/getpage.gch?pid=1002&nextpage=x')
      ->assertOk()->assertSee('Halaman ZTE');
    Http::assertSent(fn(Request $r) => $r->url() === 'http://10.1.1.5/getpage.gch?pid=1002&nextpage=x' && $r->method() === 'GET');
  }

  public function test_header_frame_dibuang_dan_location_relatif(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('', 302, [
      'X-Frame-Options' => 'SAMEORIGIN',
      'Content-Security-Policy' => "frame-ancestors 'self'",
      'Location' => 'http://10.1.1.5/login.asp',
      'Set-Cookie' => 'SID=abc; Domain=10.1.1.5; Path=/',
    ])]);
    $res = $this->withUnencryptedCookie('ont_remote', $cookie)->get('http://ont.test/');
    $res->assertStatus(302)->assertHeaderMissing('X-Frame-Options')->assertHeaderMissing('Content-Security-Policy');
    $this->assertSame('/login.asp', $res->headers->get('Location'));
    $sid = collect($res->headers->getCookies())->firstWhere(fn($c) => $c->getName() === 'SID');
    $this->assertSame('abc', $sid->getValue());
    $this->assertNull($sid->getDomain());
  }

  public function test_post_form_diteruskan_dengan_body_dan_cookie_ont(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('ok')]);
    $this->call('POST', 'http://ont.test/login.cgi', [], ['ont_remote' => $cookie, 'SID' => 'abc'], [],
      ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_COOKIE' => "ont_remote=$cookie; SID=abc"], 'Username=admin&Password=x')->assertOk();
    Http::assertSent(fn(Request $r) => $r->method() === 'POST' && $r->url() === 'http://10.1.1.5/login.cgi'
      && $r->body() === 'Username=admin&Password=x'
      && str_contains($r->header('Cookie')[0] ?? '', 'SID=abc')
      && !str_contains($r->header('Cookie')[0] ?? '', 'ont_remote'));
  }

  public function test_path_billing_di_host_remote_tetap_ke_ont(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('bukan billing')]);
    $this->withUnencryptedCookie('ont_remote', $cookie)->get('http://ont.test/user')->assertOk()->assertSee('bukan billing');
    Http::assertSent(fn(Request $r) => $r->url() === 'http://10.1.1.5/user');
  }

  public function test_ont_tidak_menjawab_502(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => fn() => throw new ConnectionException('timeout')]);
    $this->withUnencryptedCookie('ont_remote', $cookie)->get('http://ont.test/')->assertStatus(502)->assertSee('ONT tidak bisa dihubungi');
  }

  public function test_biner_diteruskan_utuh(): void
  {
    $png = "\x89PN";
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);
    $res = $this->withUnencryptedCookie('ont_remote', $cookie)->get('http://ont.test/img/logo.png');
    $this->assertSame($png, $res->getContent());
    $this->assertSame('image/png', $res->headers->get('Content-Type'));
  }

  public function test_tiap_cookie_ke_ont_sesinya_sendiri(): void
  {
    Http::fake([
      'acs.test:7557/devices/*' => Http::sequence()->push([RemoteOntServiceTest::deviceDenganIp('10.1.1.5')])->push([RemoteOntServiceTest::deviceDenganIp('10.1.1.6')]),
      'http://10.1.1.*' => Http::response('ok'),
    ]);
    $acs = app(GenieAcsService::class);
    $remote = app(RemoteOntService::class);
    $c1 = Crypt::encryptString($remote->tukarTiket($remote->buatTiket($this->staf, RemoteOntServiceTest::SID, $acs->devices([])[0])));
    $c2 = Crypt::encryptString($remote->tukarTiket($remote->buatTiket($this->staf, RemoteOntServiceTest::SID, $acs->devices([])[0])));

    $this->withUnencryptedCookie('ont_remote', $c2)->get('http://ont.test/a')->assertOk();
    $this->withUnencryptedCookie('ont_remote', $c1)->get('http://ont.test/b')->assertOk();
    Http::assertSent(fn(Request $r) => $r->url() === 'http://10.1.1.6/a');
    Http::assertSent(fn(Request $r) => $r->url() === 'http://10.1.1.5/b');
  }

  public function test_cookie_ont_diteruskan_mentah(): void
  {
    // PHP meng-url-decode $_COOKIE; ONT harus menerima nilai persis seperti yang ia set
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('ok')]);
    $this->call('GET', 'http://ont.test/status.asp', [], ['ont_remote' => $cookie], [],
      ['HTTP_COOKIE' => "SID=ab+c%3D; ont_remote=$cookie; lang=id"])->assertOk();
    Http::assertSent(fn(Request $r) => str_contains($r->url(), '/status.asp')
      && ($r->header('Cookie')[0] ?? '') === 'SID=ab+c%3D; lang=id');
  }

  public function test_authorization_diteruskan(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('ok')]);
    $this->withUnencryptedCookie('ont_remote', $cookie)->withHeader('Authorization', 'Basic YWRtaW46eA==')->get('http://ont.test/')->assertOk();
    Http::assertSent(fn(Request $r) => str_contains($r->url(), '10.1.1.5') && $r->hasHeader('Authorization', 'Basic YWRtaW46eA=='));
  }

  public function test_set_cookie_rusak_atau_ont_remote_dilewati(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('ok', 200, [
      'SET-COOKIE' => ['=tanpanama', 'a b=spasi', 'ont_remote=dibajak', 'SID=baik; Path=/'],
    ])]);
    $res = $this->withUnencryptedCookie('ont_remote', $cookie)->get('http://ont.test/')->assertOk();
    $nama = collect($res->headers->getCookies())->map->getName()->all();
    $this->assertSame(['SID'], $nama);
  }

  public function test_path_dengan_titik_dua_tetap_utuh(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('ok')]);
    $this->withUnencryptedCookie('ont_remote', $cookie)->get('http://ont.test/a:80/b?x=1')->assertOk();
    Http::assertSent(fn(Request $r) => $r->url() === 'http://10.1.1.5/a:80/b?x=1');
  }

  public function test_location_ip_lain_tidak_terpotong(): void
  {
    $cookie = $this->cookie('10.1.1.5', ['http://10.1.1.5/*' => Http::response('', 302, ['Location' => 'http://10.1.1.55/x'])]);
    $res = $this->withUnencryptedCookie('ont_remote', $cookie)->get('http://ont.test/');
    $this->assertSame('http://10.1.1.55/x', $res->headers->get('Location'));
  }

  public function test_tiket_array_bukan_500(): void
  {
    $this->get('http://ont.test/_remote/mulai?tiket[]=x')->assertForbidden();
  }

  public function test_cookie_strict_dan_secure_di_belakang_https(): void
  {
    $res = $this->withHeader('X-Forwarded-Proto', 'https')->get('http://ont.test/_remote/mulai?tiket=' . urlencode($this->tiket()));
    $c = collect($res->headers->getCookies())->firstWhere(fn($c) => $c->getName() === 'ont_remote');
    $this->assertSame('strict', $c->getSameSite());
    $this->assertTrue($c->isSecure());
  }

  public function test_host_lain_tidak_terpengaruh(): void
  {
    Http::fake();
    $this->get('http://localhost/auth/login')->assertOk();
    Http::assertNothingSent();
  }

  public function test_remote_mati_bila_host_kosong(): void
  {
    config(['services.genieacs.remote_host' => '']);
    Http::fake();
    $this->get('http://ont.test/auth/login')->assertOk(); // host apa pun = billing biasa
    Http::assertNothingSent();
  }
}
