<?php

namespace Tests\Feature;

use App\Models\Lamtim_transaksi;
use App\Models\User;
use App\Services\TagihanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Alur uang: bayar penuh, cicilan, bayar semua (FIFO), cegah bayar ganda, batal pembayaran.
 * Memakai database lamtim_biling_test (lihat phpunit.xml), dibangun dari database/schema.
 */
class PembayaranTest extends TestCase
{
  use RefreshDatabase;

  private int $pelanggan;
  private int $paket;

  protected function setUp(): void
  {
    parent::setUp();
    $this->assertSame('lamtim_biling_test', DB::connection()->getDatabaseName(), 'Tes harus memakai database tes');

    $kasir = User::create(['name' => 'Kasir', 'email' => 'kasir@test', 'wa' => '1', 'password' => 'x', 'idRole' => 2]);
    Auth::login($kasir);

    $this->pelanggan = User::create(['name' => 'Budi', 'email' => 'budi@test', 'wa' => '2', 'password' => 'x', 'idRole' => 5])->id;
    $kategori = DB::table('lamtim_kategoris')->insertGetId(['nama' => 'Rumahan', 'description' => '-']);
    $this->paket = DB::table('lamtim_pakets')->insertGetId(['idKategori' => $kategori, 'kode' => 'P10', 'nama' => '10 Mbps', 'price' => '100000']);
  }

  private function tagihan(int $bulan, int $total = 100000): int
  {
    return DB::table('lamtim_tagihans')->insertGetId([
      'idUser' => $this->pelanggan, 'idPaket' => $this->paket, 'noTagihan' => uniqid(),
      'bulan' => $bulan, 'tahun' => 2026, 'statusBayar' => 0, 'harga' => $total, 'ppn' => 0,
      'diskon' => 0, 'total' => $total, 'terbayar' => 0, 'tglJatuhTempo' => "2026-$bulan-10",
    ]);
  }

  private function bayar(int $idTagihan, array $input): array
  {
    return app(TagihanService::class)->bayarTagihan(encrypt($idTagihan), Request::create('/', 'POST', $input + ['metode_bayar' => 'cash']));
  }

  private function tagihanDb(int $id): object
  {
    return DB::table('lamtim_tagihans')->find($id);
  }

  public function test_bayar_penuh_melunasi_tagihan(): void
  {
    $id = $this->tagihan(11);
    $this->assertTrue($this->bayar($id, [])['success']);

    $t = $this->tagihanDb($id);
    $this->assertSame(1, (int) $t->statusBayar);
    $this->assertEquals(100000, $t->terbayar);
    $this->assertSame(1, Lamtim_transaksi::where('idTagihan', $id)->count());
  }

  public function test_cicilan_baru_lunas_setelah_sisa_dibayar(): void
  {
    $id = $this->tagihan(11);

    $this->bayar($id, ['nominal_bayar' => 40000]);
    $this->assertSame(0, (int) $this->tagihanDb($id)->statusBayar);
    $this->assertEquals(40000, $this->tagihanDb($id)->terbayar);

    // bayar lebih dari sisa: yang dicatat hanya sisanya (60.000)
    $this->bayar($id, ['nominal_bayar' => 80000]);
    $this->assertSame(1, (int) $this->tagihanDb($id)->statusBayar);
    $this->assertEquals(100000, Lamtim_transaksi::where('idTagihan', $id)->sum('total'));
  }

  public function test_bayar_semua_melunasi_tagihan_terlama_dulu(): void
  {
    $okt = $this->tagihan(10);
    $nov = $this->tagihan(11);

    $hasil = $this->bayar($nov, ['bayar_semua' => 1, 'nominal_bayar' => 150000]);

    $this->assertTrue($hasil['success']);
    $this->assertSame(1, (int) $this->tagihanDb($okt)->statusBayar);
    $this->assertSame(0, (int) $this->tagihanDb($nov)->statusBayar);
    $this->assertEquals(50000, $this->tagihanDb($nov)->terbayar);
    $this->assertEquals(50000, $hasil['data']['sisa_tunggakan']);
  }

  public function test_permintaan_ganda_dengan_token_sama_ditolak(): void
  {
    $id = $this->tagihan(11);

    $this->assertTrue($this->bayar($id, ['nominal_bayar' => 30000, 'request_token' => 'abc'])['success']);
    $this->assertFalse($this->bayar($id, ['nominal_bayar' => 30000, 'request_token' => 'abc'])['success']);

    $this->assertEquals(30000, $this->tagihanDb($id)->terbayar);
    $this->assertSame(1, Lamtim_transaksi::where('idTagihan', $id)->count());
  }

  public function test_batal_pembayaran_menyimpan_jejak_transaksi(): void
  {
    $id = $this->tagihan(11);
    $this->bayar($id, []);

    $this->assertTrue(app(TagihanService::class)->batalPembayaran(encrypt($id))['success']);

    $t = $this->tagihanDb($id);
    $this->assertSame(0, (int) $t->statusBayar);
    $this->assertEquals(0, $t->terbayar);
    // transaksi tidak tampil lagi, tapi masih tersimpan dengan nama yang membatalkan
    $this->assertSame(0, Lamtim_transaksi::where('idTagihan', $id)->count());
    $this->assertSame('Kasir', Lamtim_transaksi::onlyTrashed()->where('idTagihan', $id)->value('batalOleh'));
  }
}
