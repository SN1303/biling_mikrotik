<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Daftar ONT yang lapor ke GenieACS tapi belum terkait pelanggan, dan aksi Hubungkan.
 */
class OntBelumTerhubungTest extends TestCase
{
  use RefreshDatabase;

  private int $x;
  private int $y;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    $this->withoutMiddleware(VerifyCsrfToken::class);
    config(['services.genieacs.url' => 'http://acs.test:7557']);

    $kat = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'Rumahan', 'description' => '-']);
    $paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kat, 'kode' => 'P10', 'nama' => '10 Mbps', 'price' => '100000']);
    $this->x = $this->pelanggan('Xaverius', 'xav@tj-1', 'SN-A', $paket);
    $this->y = $this->pelanggan('Yuliana', 'yuli@tj-1', null, $paket);

    Http::fake(['acs.test:7557/devices/*' => Http::response([
      GenieAcsServiceTest::device(['pppoeUsername' => 'lain@tj-9'], 'SN-A'),
      GenieAcsServiceTest::device(['pppoeUsername' => 'yuli@tj-1'], 'SN-B'),
      GenieAcsServiceTest::device(['pppoeUsername' => 'asing@tj-9'], 'SN-C'),
    ])]);
  }

  private function pelanggan(string $nama, string $pppoe, ?string $sn, int $paket): int
  {
    $id = User::create(['name' => $nama, 'email' => "$pppoe.test", 'wa' => '2', 'password' => 'x', 'idRole' => 5, 'isActive' => 1])->id;
    DB::table('lamtim_user_mikrotik_details')->insert(['idUser' => $id, 'idPaket' => $paket, 'statusIsolir' => 0, 'namaMikrotikUser' => $pppoe, 'ont_sn' => $sn]);
    return $id;
  }

  private function masukSebagai(int $role): void
  {
    $this->actingAs(User::create(['name' => "Staf $role", 'email' => "staf$role@test", 'wa' => "9$role", 'password' => 'x', 'idRole' => $role, 'isActive' => 1]));
  }

  private function snPelanggan(int $id): ?string
  {
    return DB::table('lamtim_user_mikrotik_details')->where('idUser', $id)->value('ont_sn');
  }

  public function test_daftar_buang_ont_yang_sudah_terhubung(): void
  {
    $this->masukSebagai(1);
    $this->get('/ont/belum-terhubung')->assertOk()->assertSee('SN-C')->assertDontSee('SN-A')->assertDontSee('SN-B');
  }

  public function test_hubungkan_simpan_sn(): void
  {
    $this->masukSebagai(1);
    $this->postJson('/ont/hubungkan', ['sn' => 'SN-C', 'pelanggan' => encrypt($this->y)])->assertOk();
    $this->assertSame('SN-C', $this->snPelanggan($this->y));
  }

  public function test_sn_milik_pelanggan_lain_butuh_konfirmasi(): void
  {
    $this->masukSebagai(1);
    $res = $this->postJson('/ont/hubungkan', ['sn' => 'SN-A', 'pelanggan' => encrypt($this->y)])->assertStatus(409);
    $this->assertStringContainsString('Xaverius', $res->json('message'));
    $this->assertSame('SN-A', $this->snPelanggan($this->x));
    $this->assertNull($this->snPelanggan($this->y));

    $this->postJson('/ont/hubungkan', ['sn' => 'SN-A', 'pelanggan' => encrypt($this->y), 'pindahkan' => 1])->assertOk();
    $this->assertNull($this->snPelanggan($this->x));
    $this->assertSame('SN-A', $this->snPelanggan($this->y));
  }

  public function test_teknisi_boleh_kolektor_ditolak(): void
  {
    $this->masukSebagai(7);
    $this->get('/ont/belum-terhubung')->assertOk();
    $this->masukSebagai(6);
    $this->get('/ont/belum-terhubung')->assertForbidden();
    $this->postJson('/ont/hubungkan', ['sn' => 'SN-C', 'pelanggan' => encrypt($this->y)])->assertForbidden();
  }
}
