<?php

namespace Tests\Unit;

use App\Models\Lamtim_user_details;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MulaiTagihTest extends TestCase
{
  public static function kasus(): array
  {
    return [
      'pasang tgl 1 -> bulan depan' => ['2026-11-01', '2026-12-01'],
      'pasang tgl 15 -> bulan depan' => ['2026-11-15', '2026-12-01'],
      'zona 16-19 default bulan depan' => ['2026-11-19', '2026-12-01'],
      'pasang tgl 20 -> 2 bulan lagi' => ['2026-11-20', '2027-01-01'],
      'pasang tgl 31 Jan -> Maret' => ['2027-01-31', '2027-03-01'],
      'pasang Desember -> Januari tahun depan' => ['2026-12-10', '2027-01-01'],
    ];
  }

  #[DataProvider('kasus')]
  public function test_mulai_tagih_dari_tanggal_pasang(string $tglPasang, string $harap): void
  {
    $this->assertSame($harap, Lamtim_user_details::mulaiTagihDefault($tglPasang));
  }
}
