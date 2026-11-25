<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Form Edit Pelanggan (POST /user/{id}). Pelanggan hasil sinkron MikroTik belum punya area
 * maupun nomor identitas; form tetap harus bisa disimpan tanpa mengisi keduanya.
 */
class EditPelangganTest extends TestCase
{
  use RefreshDatabase;

  private int $pelanggan;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@test', 'wa' => '1', 'password' => 'x', 'idRole' => 1, 'isActive' => 1]));

    $this->pelanggan = User::create(['name' => 'Rizal', 'email' => 'rizal@test', 'wa' => '2', 'password' => 'lama-sekali', 'idRole' => 5, 'isActive' => 1])->id;
    DB::table('lamtim_user_details')->insert(['idUser' => $this->pelanggan, 'tglDafatar' => '2025-04-10', 'tglJatuhTempo' => '2026-02-10']);
  }

  private function isianForm(array $ubah = []): array
  {
    return $ubah + [
      'nama' => 'Rizal', 'wa' => '0812', 'status_user' => 1, 'status_isolir' => 0,
      'tgl_daftar' => '2025-04-10', 'jatuh_tempo' => '2026-02-10', 'status_ppn' => 0, 'status_tagihan' => 1,
      'jenis_bayar' => 1, 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP',
      // Kolom opsional tetap terkirim (kosong) seperti dari form sungguhan
      'julukan' => '', 'password' => '', 'area' => '', 'mulai_tagih' => '', 'google_map_url' => '',
      'no_identitas' => '', 'alamat' => '', 'keterangan' => '',
    ];
  }

  public function test_bisa_disimpan_tanpa_area_dan_nomor_identitas(): void
  {
    $this->post('/user/' . encrypt($this->pelanggan), $this->isianForm())->assertOk();
    $this->assertDatabaseHas('lamtim_user_details', ['idUser' => $this->pelanggan, 'idArea' => null, 'noIdentitas' => null]);
    $this->assertDatabaseHas('users', ['id' => $this->pelanggan, 'wa' => '0812']);
  }

  public function test_area_yang_dipilih_tersimpan(): void
  {
    $area = DB::table('lamtim_areas')->insertGetId(['name' => 'Blok A', 'address' => '-', 'code_area' => 'BA']);
    $this->post('/user/' . encrypt($this->pelanggan), $this->isianForm(['area' => encrypt($area)]))->assertOk();
    $this->assertDatabaseHas('lamtim_user_details', ['idUser' => $this->pelanggan, 'idArea' => $area]);
  }

  public function test_password_baru_bisa_dipakai_login(): void
  {
    $this->post('/user/' . encrypt($this->pelanggan), $this->isianForm(['password' => 'rahasia-baru-123']))->assertOk();
    $this->assertTrue(Hash::check('rahasia-baru-123', User::find($this->pelanggan)->password));
  }

  public function test_password_kosong_tidak_mengubah_password_lama(): void
  {
    $this->post('/user/' . encrypt($this->pelanggan), $this->isianForm(['password' => '']))->assertOk();
    $this->assertTrue(Hash::check('lama-sekali', User::find($this->pelanggan)->password));
  }
}
