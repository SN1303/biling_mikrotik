<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Tabel tagihan: paging di database, ringkasan tetap menghitung semua baris. */
class TabelTagihanTest extends TestCase
{
  use RefreshDatabase;

  public function test_paging_dan_ringkasan_tagihan(): void
  {
    $admin = User::create(['name' => 'Admin', 'email' => 'a@test', 'wa' => '1', 'password' => 'x', 'idRole' => 1, 'isActive' => 1]);
    $kategori = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'R', 'description' => '-']);
    $paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kategori, 'kode' => 'P', 'nama' => 'P', 'price' => '100000']);

    // 12 belum bayar (1 di antaranya cicilan 40rb), 3 lunas
    foreach (range(1, 15) as $i) {
      $pelanggan = User::create(['name' => "Pelanggan $i", 'email' => "p$i@test", 'wa' => "$i", 'password' => 'x', 'idRole' => 5])->id;
      DB::table('lamtim_tagihans')->insert([
        'idUser' => $pelanggan, 'idPaket' => $paket, 'noTagihan' => "T$i", 'bulan' => 11, 'tahun' => 2026,
        'statusBayar' => $i > 12 ? 1 : 0, 'harga' => 100000, 'ppn' => 0, 'diskon' => 0, 'total' => 100000,
        'terbayar' => $i > 12 ? 100000 : ($i === 1 ? 40000 : 0), 'tglJatuhTempo' => '2026-11-10',
      ]);
    }

    $kolom = [['data' => 'user_nama', 'searchable' => 'true', 'orderable' => 'true'], ['data' => 'tglJatuhTempo', 'searchable' => 'true', 'orderable' => 'true']];
    $minta = fn (array $p) => $this->actingAs($admin)->getJson(route('tagihan.json', $p + [
      'draw' => 1, 'start' => 0, 'length' => 10, 'columns' => $kolom, 'order' => [['column' => 1, 'dir' => 'asc']], 'search' => ['value' => ''],
    ]))->assertOk()->json();

    $belum = $minta(['status_bayar' => 0]);
    $this->assertSame(12, $belum['recordsFiltered']);
    $this->assertCount(10, $belum['data']);
    $this->assertEquals(12 * 100000 - 40000, $belum['summary']['total_unpaid']);
    $this->assertEquals(40000, $belum['summary']['cicilan_masuk']);

    $lunas = $minta(['status_bayar' => 1]);
    $this->assertSame(4, $lunas['recordsFiltered']); // 3 lunas + 1 cicilan
    $this->assertEquals(3 * 100000 + 40000, $lunas['summary']['total_unpaid']);
    $this->assertSame(12, $lunas['unpaid_summary']['unpaid_count']);

    $cari = $minta(['status_bayar' => 0, 'search' => ['value' => 'Pelanggan 12']]);
    $this->assertSame(1, $cari['recordsFiltered']);
  }
}
