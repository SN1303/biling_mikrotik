<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Lamtim_user_details extends Model
{
  protected $guarded = [];

  /** Pasang sampai tanggal ini: mulai ditagih bulan depan. Sesudahnya (20+): 2 bulan lagi. */
  public const BATAS_PASANG_BULAN_DEPAN = 19;

  /**
   * Bulan pertama pelanggan baru ditagih (tanggal 1 bulan itu). Pembayaran PSB
   * menutup masa sampai bulan tersebut. Tgl 16–19 default bulan depan, admin bisa ubah.
   */
  public static function mulaiTagihDefault($tglPasang): string
  {
    $pasang = Carbon::parse($tglPasang)->startOfMonth();
    $tambah = Carbon::parse($tglPasang)->day <= self::BATAS_PASANG_BULAN_DEPAN ? 1 : 2;

    return $pasang->addMonthsNoOverflow($tambah)->toDateString();
  }
}
