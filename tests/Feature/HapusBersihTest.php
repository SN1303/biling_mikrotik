<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HapusBersihTest extends TestCase
{
  use RefreshDatabase;

  private function superAdmin(): User
  {
    return User::create(['name' => 'Owner', 'email' => 'owner@test', 'wa' => '1', 'password' => bcrypt('rahasia123'), 'idRole' => 1, 'isActive' => 1]);
  }

  public function test_password_salah_tidak_menghapus_apa_pun(): void
  {
    DB::table('lamtim_kategoris')->insert(['nama' => 'Rumahan', 'description' => '-']);

    $this->actingAs($this->superAdmin())
      ->postJson(route('setting.hapus-bersih'), ['groups' => ['layanan'], 'password' => 'salah'])
      ->assertStatus(422)
      ->assertJsonValidationErrors('password');

    $this->assertSame(1, DB::table('lamtim_kategoris')->count());
  }

  public function test_password_benar_menghapus_kelompok_terpilih(): void
  {
    DB::table('lamtim_kategoris')->insert(['nama' => 'Rumahan', 'description' => '-']);

    $this->actingAs($this->superAdmin())
      ->postJson(route('setting.hapus-bersih'), ['groups' => ['layanan'], 'password' => 'rahasia123'])
      ->assertOk();

    $this->assertSame(0, DB::table('lamtim_kategoris')->count());
  }
}
