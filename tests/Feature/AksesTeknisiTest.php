<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Teknisi (role 4) dan Teknisi-Kolektor (role 7) mengurus jaringan & lokasi, bukan uang, OLT,
 * atau akun pelanggan. Halaman peta tidak boleh jadi jalan belakang ke hal-hal itu.
 */
class AksesTeknisiTest extends TestCase
{
  use RefreshDatabase;

  private int $olt;
  private int $odp;
  private int $client;
  private int $pelanggan;
  private int $paket;
  private int $paketLain;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    $this->withoutMiddleware(VerifyCsrfToken::class);

    $this->olt = DB::table('lamtim_olts')->insertGetId(['kode' => 'OLT1', 'nama' => 'OLT Pusat', 'latitude' => -5.1, 'longitude' => 105.6]);
    $this->odp = DB::table('lamtim_odps')->insertGetId(['idOlt' => $this->olt, 'kode' => 'ODP1', 'nama' => 'ODP Satu']);
    $kat = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'Rumahan', 'description' => '-']);
    $this->paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kat, 'kode' => 'P10', 'nama' => '10 Mbps', 'price' => '100000']);
    $this->paketLain = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kat, 'kode' => 'P20', 'nama' => '20 Mbps', 'price' => '200000']);
    $this->pelanggan = User::create(['name' => 'Budi', 'email' => 'budi@test', 'wa' => '2', 'password' => 'x', 'idRole' => 5, 'isActive' => 1])->id;
    DB::table('lamtim_user_details')->insert(['idUser' => $this->pelanggan]);
    $this->client = DB::table('lamtim_user_mikrotik_details')->insertGetId([
      'idUser' => $this->pelanggan, 'idPaket' => $this->paket, 'status' => 'online', 'statusIsolir' => 0, 'latitude' => -5.0, 'longitude' => 105.0,
    ]);
  }

  private function masukSebagai(int $role): User
  {
    $u = User::create(['name' => "Staf $role", 'email' => "staf$role@test", 'wa' => "9$role", 'password' => 'x', 'idRole' => $role, 'isActive' => 1]);
    $this->actingAs($u);
    return $u;
  }

  public static function peranLapangan(): array
  {
    return ['teknisi' => [4], 'teknisi-kolektor' => [7]];
  }

  #[DataProvider('peranLapangan')]
  public function test_dashboard_tanpa_angka_uang(int $role): void
  {
    $this->masukSebagai($role);
    $this->get('/')->assertOk()
      ->assertDontSee('Pendapatan Bulan Ini')
      ->assertDontSee('Tagihan Tertunda')
      ->assertDontSee('Tren Arus Kas')
      ->assertDontSee('Pembayaran Terakhir');
  }

  public function test_kolektor_melihat_daftar_belum_dan_sudah_bayar(): void
  {
    $bulan = now()->month;
    $tahun = now()->year;
    $lunas = User::create(['name' => 'Sari Lunas', 'email' => 'sari@test', 'wa' => '3', 'password' => 'x', 'idRole' => 5, 'isActive' => 1])->id;
    $tagihan = ['idPaket' => $this->paket, 'bulan' => $bulan, 'tahun' => $tahun, 'harga' => 100000, 'ppn' => 0, 'diskon' => 0, 'total' => 100000, 'tglJatuhTempo' => now()->format('Y-m-10')];
    DB::table('lamtim_tagihans')->insert([
      $tagihan + ['idUser' => $this->pelanggan, 'noTagihan' => 'T1', 'statusBayar' => 0, 'terbayar' => 40000, 'tglBayar' => null],
      $tagihan + ['idUser' => $lunas, 'noTagihan' => 'T2', 'statusBayar' => 1, 'terbayar' => 100000, 'tglBayar' => now()],
    ]);

    $this->masukSebagai(7);
    $this->get('/')->assertOk()
      ->assertSee('Belum Bayar')->assertSee('Budi')->assertSee('60.000') // sisa yang harus ditagih
      ->assertSee('Sudah Bayar')->assertSee('Sari Lunas')
      ->assertDontSee('Pendapatan Bulan Ini');

    $this->masukSebagai(4); // teknisi murni tidak menagih
    $this->get('/')->assertOk()->assertDontSee('Belum Bayar')->assertDontSee('Budi');
  }

  public function test_admin_dan_bendahara_tetap_melihat_angka_uang(): void
  {
    foreach ([2, 3] as $role) {
      $this->masukSebagai($role);
      $this->get('/')->assertOk()->assertSee('Pendapatan Bulan Ini')->assertSee('Tren Arus Kas');
    }
  }

  #[DataProvider('peranLapangan')]
  public function test_peta_menolak_olt_dan_tambah_hapus_pelanggan(int $role): void
  {
    $this->masukSebagai($role);
    $this->postJson('/google-map/olt', ['kode' => 'X', 'nama' => 'X'])->assertForbidden();
    $this->putJson("/google-map/olt/{$this->olt}", ['nama' => 'Ganti'])->assertForbidden();
    $this->deleteJson("/google-map/olt/{$this->olt}")->assertForbidden();
    $this->putJson("/google-map/position/olt/{$this->olt}", ['latitude' => 1, 'longitude' => 1])->assertForbidden();
    $this->postJson('/google-map/set-coordinates', ['type' => 'olt', 'id' => $this->olt, 'latitude' => 1, 'longitude' => 1])->assertForbidden();
    $this->postJson('/google-map/client', ['nama' => 'Baru', 'latitude' => 1, 'longitude' => 1])->assertForbidden();
    $this->deleteJson("/google-map/client/{$this->client}")->assertForbidden();

    $this->assertDatabaseHas('lamtim_olts', ['id' => $this->olt, 'nama' => 'OLT Pusat', 'latitude' => -5.1]);
    $this->assertDatabaseHas('lamtim_user_mikrotik_details', ['id' => $this->client]);
  }

  #[DataProvider('peranLapangan')]
  public function test_edit_pelanggan_dari_peta_hanya_menyimpan_lokasi(int $role): void
  {
    $this->masukSebagai($role);
    $this->putJson("/google-map/client/{$this->client}", [
      'latitude' => -5.5, 'longitude' => 105.5, 'idOdp' => $this->odp, 'portOdp' => 3,
      'idPaket' => $this->paketLain, 'status' => 'isolir', 'nama' => 'Diganti',
    ])->assertOk();

    $this->assertDatabaseHas('lamtim_user_mikrotik_details', [
      'id' => $this->client, 'latitude' => -5.5, 'longitude' => 105.5, 'idOdp' => $this->odp, 'portOdp' => 3,
      'idPaket' => $this->paket, 'status' => 'online', 'statusIsolir' => 0,
    ]);
    $this->assertDatabaseHas('users', ['id' => $this->pelanggan, 'name' => 'Budi']);
  }

  #[DataProvider('peranLapangan')]
  public function test_boleh_geser_odp_dan_pelanggan(int $role): void
  {
    $this->masukSebagai($role);
    $this->putJson("/google-map/position/odp/{$this->odp}", ['latitude' => -5.2, 'longitude' => 105.2])->assertOk();
    $this->putJson("/google-map/position/client/{$this->client}", ['latitude' => -5.3, 'longitude' => 105.3])->assertOk();
  }

  #[DataProvider('peranLapangan')]
  public function test_boleh_upload_foto_pelanggan(int $role): void
  {
    Storage::fake('public');
    $this->masukSebagai($role);
    $this->post('/foto/upload', ['idUser' => encrypt($this->pelanggan), 'fotos' => [UploadedFile::fake()->image('rumah.jpg')]])
      ->assertOk();
    $this->assertDatabaseCount('lamtim_fotos', 1);
  }

  public function test_admin_tetap_bisa_mengubah_paket_dari_peta(): void
  {
    $this->masukSebagai(2);
    $this->putJson("/google-map/client/{$this->client}", ['idPaket' => $this->paketLain])->assertOk();
    $this->assertDatabaseHas('lamtim_user_mikrotik_details', ['id' => $this->client, 'idPaket' => $this->paketLain]);
  }
}
