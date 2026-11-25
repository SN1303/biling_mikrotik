<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Pendapatan dasbor = uang yang masuk (tanggal bayar transaksi), bukan periode tagihan. */
class DashboardPendapatanTest extends TestCase
{
  use RefreshDatabase;

  public function test_pendapatan_dihitung_dari_tanggal_uang_masuk(): void
  {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $admin = User::create(['name' => 'Admin', 'email' => 'a@test', 'wa' => '1', 'password' => 'x', 'idRole' => 1, 'isActive' => 1]);
    $pelanggan = User::create(['name' => 'Budi', 'email' => 'b@test', 'wa' => '2', 'password' => 'x', 'idRole' => 5])->id;
    $kategori = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'R', 'description' => '-']);
    $paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kategori, 'kode' => 'P', 'nama' => 'P', 'price' => '100000']);

    $tagihan = fn (int $bulan) => DB::table('lamtim_tagihans')->insertGetId([
      'idUser' => $pelanggan, 'idPaket' => $paket, 'noTagihan' => uniqid(), 'bulan' => $bulan, 'tahun' => 2026,
      'statusBayar' => 1, 'harga' => 100000, 'ppn' => 0, 'diskon' => 0, 'total' => 100000, 'terbayar' => 100000,
      'tglJatuhTempo' => "2026-$bulan-10",
    ]);
    $bayar = fn (int $idTagihan, string $tgl, array $extra = []) => DB::table('lamtim_transaksis')->insert($extra + [
      'idUser' => $pelanggan, 'idTagihan' => $idTagihan, 'metodeBayar' => 'cash', 'tglBayar' => $tgl, 'total' => 100000,
    ]);

    $bayar($tagihan(10), '2026-11-05 09:00:00');   // tunggakan Oktober dibayar November -> masuk November
    $bayar($tagihan(11), '2026-10-28 09:00:00');   // tagihan November dibayar di Oktober -> masuk Oktober
    $bayar($tagihan(9), '2026-11-06 09:00:00', ['deleted_at' => '2026-11-07 00:00:00']); // dibatalkan -> tidak dihitung

    $res = $this->actingAs($admin)->get('/');

    $res->assertOk();
    $this->assertEquals(100000, $res->viewData('stats')['monthly_revenue']);
    $this->assertSame(1, $res->viewData('stats')['monthly_transactions']);
    $grafik = collect($res->viewData('monthlyRevenue'))->pluck('revenue', 'month');
    $this->assertEquals(100000, $grafik['Okt 2026']);
    $this->assertEquals(100000, $grafik['Nov 2026']);
  }

  public function test_tagihan_tertunda_mencakup_tunggakan_bulan_lalu(): void
  {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $admin = User::create(['name' => 'Admin', 'email' => 'a@test', 'wa' => '1', 'password' => 'x', 'idRole' => 1, 'isActive' => 1]);
    $pelanggan = User::create(['name' => 'Budi', 'email' => 'b@test', 'wa' => '2', 'password' => 'x', 'idRole' => 5])->id;
    $kategori = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'R', 'description' => '-']);
    $paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kategori, 'kode' => 'P', 'nama' => 'P', 'price' => '100000']);
    $tagihan = fn (int $bulan, int $terbayar) => DB::table('lamtim_tagihans')->insert([
      'idUser' => $pelanggan, 'idPaket' => $paket, 'noTagihan' => uniqid(), 'bulan' => $bulan, 'tahun' => 2026,
      'statusBayar' => 0, 'harga' => 100000, 'ppn' => 0, 'diskon' => 0, 'total' => 100000, 'terbayar' => $terbayar,
      'tglJatuhTempo' => "2026-$bulan-10",
    ]);
    $tagihan(9, 0);       // tunggakan September
    $tagihan(11, 40000);  // November, sudah dicicil 40.000

    $stats = $this->actingAs($admin)->get('/')->viewData('stats');

    $this->assertEquals(160000, $stats['pending_revenue']);
    $this->assertSame(2, $stats['pending_bills']);
    $this->assertSame(1, $stats['unpaid_bills'], 'donut realisasi tetap khusus bulan ini');
  }
}
