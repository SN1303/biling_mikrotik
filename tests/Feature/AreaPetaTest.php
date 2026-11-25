<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Buat area dari peta (/google-map/area): alamat boleh kosong, nama ganda ditolak dengan pesan biasa (bukan error SQL).
 */
class AreaPetaTest extends TestCase
{
  use RefreshDatabase;

  private array $titik = [['lat' => -5.1, 'lng' => 105.6], ['lat' => -5.11, 'lng' => 105.6], ['lat' => -5.11, 'lng' => 105.61]];

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');
    $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@test', 'wa' => '1', 'password' => 'x', 'idRole' => 1, 'isActive' => 1]));
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
  }

  public function test_area_tanpa_alamat_bisa_disimpan(): void
  {
    $this->postJson('/google-map/area', ['name' => 'Blok A', 'code_area' => 'BA', 'address' => null, 'color' => '#696cff', 'coordinates' => $this->titik])
      ->assertOk()->assertJson(['success' => true]);
    $this->assertDatabaseHas('lamtim_areas', ['name' => 'Blok A', 'address' => '']);
  }

  public function test_nama_area_ganda_ditolak_dengan_pesan_validasi(): void
  {
    DB::table('lamtim_areas')->insert(['name' => 'Blok A', 'address' => '-', 'code_area' => 'BA']);
    $this->postJson('/google-map/area', ['name' => 'Blok A', 'code_area' => 'BB', 'address' => 'x', 'coordinates' => $this->titik])
      ->assertStatus(422)->assertJsonValidationErrors('name');
  }

  public function test_update_area_tanpa_alamat_bisa_disimpan(): void
  {
    $id = DB::table('lamtim_areas')->insertGetId(['name' => 'Blok A', 'address' => '-', 'code_area' => 'BA']);
    $this->putJson('/google-map/area/' . encrypt($id), ['name' => 'Blok A', 'code_area' => 'BA', 'address' => null, 'coordinates' => $this->titik])
      ->assertOk()->assertJson(['success' => true]);
    $this->assertDatabaseHas('lamtim_areas', ['id' => $id, 'address' => '']);
  }
}
