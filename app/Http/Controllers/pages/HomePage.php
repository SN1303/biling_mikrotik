<?php


namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Lamtim_mikrotik;
use App\Models\Lamtim_tagihans;
use App\Models\Lamtim_paket;
use App\Models\Lamtim_transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HomePage extends Controller
{
  public function index()
  {
    // Get current month and year
    $currentMonth = Carbon::now()->month;
    $currentYear = Carbon::now()->year;

    // Angka uang (pendapatan, tunggakan, transaksi) hanya untuk Admin & Bendahara
    $lihatUang = auth()->user()->hasRole(1, 2, 3);
    if (!$lihatUang) {
      $stats = [
        'total_users' => User::where('isActive', 1)->where('idRole', 5)->count(),
        'active_mikrotik' => Lamtim_mikrotik::where('isActive', 1)->count(),
        'total_mikrotik' => Lamtim_mikrotik::count(),
      ];
      return view('content.pages.home', ['stats' => $stats, 'lihatUang' => false, 'recent_transactions' => collect(), 'monthlyRevenue' => []]);
    }

    // Statistics for dashboard
    $stats = [
      // Total Users
      'total_users' => User::where('isActive', 1)->where('idRole', 5)->count(),

      // Active Mikrotik Servers
      'active_mikrotik' => Lamtim_mikrotik::where('isActive', 1)->count(),
      'total_mikrotik' => Lamtim_mikrotik::count(),

      // Monthly Bills Statistics
      'monthly_bills' => Lamtim_tagihans::where('bulan', $currentMonth)
        ->where('tahun', $currentYear)
        ->count(),

      'paid_bills' => Lamtim_tagihans::where('bulan', $currentMonth)
        ->where('tahun', $currentYear)
        ->where('statusBayar', 1)
        ->count(),

      'unpaid_bills' => Lamtim_tagihans::where('bulan', $currentMonth)
        ->where('tahun', $currentYear)
        ->where('statusBayar', 0)
        ->count(),

      // Pendapatan = uang masuk bulan ini (tanggal bayar transaksi, termasuk cicilan &
      // tunggakan lama). Transaksi yang dibatalkan otomatis tidak terhitung (soft delete).
      'monthly_revenue' => self::uangMasuk(Carbon::now())->sum('total'),
      'monthly_transactions' => self::uangMasuk(Carbon::now())->count(),

      // Tagihan tertunda = semua sisa yang belum lunas, termasuk tunggakan bulan-bulan lalu
      'pending_revenue' => (float) Lamtim_tagihans::where('statusBayar', 0)
        ->sum(DB::raw('GREATEST(total - COALESCE(terbayar, 0), 0)')),
      'pending_bills' => Lamtim_tagihans::where('statusBayar', 0)->count(),

      // Total Packages
      'total_packages' => Lamtim_paket::count(),
    ];

    // Recent transactions (last 5) — dari tabel transaksi agar cicilan ikut tampil
    $recent_transactions = Lamtim_transaksi::with('user')
      ->leftJoin('lamtim_tagihans', 'lamtim_tagihans.id', '=', 'lamtim_transaksis.idTagihan')
      ->where('lamtim_transaksis.total', '>', 0)
      ->orderBy('lamtim_transaksis.tglBayar', 'desc')
      ->orderBy('lamtim_transaksis.id', 'desc')
      ->limit(5)
      ->get(['lamtim_transaksis.*', 'lamtim_tagihans.noTagihan', 'lamtim_tagihans.statusBayar as tagihanLunas']);

    // Monthly revenue chart data (last 6 months)
    $monthlyRevenue = [];
    for ($i = 5; $i >= 0; $i--) {
      $date = Carbon::now()->startOfMonth()->subMonths($i);
      $monthlyRevenue[] = [
        'month' => $date->locale('id')->translatedFormat('M Y'),
        'revenue' => (float) self::uangMasuk($date)->sum('total'),
      ];
    }

    return view('content.pages.home', compact('stats', 'recent_transactions', 'monthlyRevenue') + ['lihatUang' => true]);
  }

  /** Transaksi berbayar yang tanggal bayarnya jatuh di bulan $bulan. */
  private static function uangMasuk(Carbon $bulan)
  {
    return Lamtim_transaksi::where('total', '>', 0)
      ->whereBetween('tglBayar', [$bulan->copy()->startOfMonth(), $bulan->copy()->endOfMonth()]);
  }
}
