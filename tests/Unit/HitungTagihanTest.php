<?php

namespace Tests\Unit;

use App\Repositories\TagihanRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HitungTagihanTest extends TestCase
{
  public static function kasus(): array
  {
    // [harga, diskon, persen PPN, harap PPN, harap total]
    return [
      'tanpa PPN tanpa diskon' => [100000, 0, 0, 0, 100000],
      'PPN 11% tanpa diskon' => [100000, 0, 11, 11000, 111000],
      'PPN dihitung setelah diskon' => [100000, 20000, 11, 8800, 88800],
      'diskon tanpa PPN' => [100000, 20000, 0, 0, 80000],
      'pembulatan PPN' => [150000, 10000, 11, 15400, 155400],
      'diskon melebihi harga jadi gratis' => [100000, 150000, 11, 0, 0],
    ];
  }

  #[DataProvider('kasus')]
  public function test_hitung_ppn_dan_total(float $harga, float $diskon, float $persen, float $harapPpn, float $harapTotal): void
  {
    [$ppn, $total] = TagihanRepository::hitungTotal($harga, $diskon, $persen);
    $this->assertEquals($harapPpn, $ppn);
    $this->assertEquals($harapTotal, $total);
  }
}
