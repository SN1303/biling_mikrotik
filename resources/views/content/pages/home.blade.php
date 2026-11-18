@php
$configData = Helper::appClasses();
$currentUser = auth()->user();

// Dynamic Greeting based on Indonesian time
$hour = (int) Carbon\Carbon::now()->format('H');
if ($hour >= 5 && $hour < 11) {
    $greeting = 'Selamat Pagi';
} elseif ($hour >= 11 && $hour < 15) {
    $greeting = 'Selamat Siang';
} elseif ($hour >= 15 && $hour < 18) {
    $greeting = 'Selamat Sore';
} else {
    $greeting = 'Selamat Malam';
}
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Dashboard - MRYES NET Billing')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart with Modern NOC styling
    const revenueChartEl = document.querySelector('#revenueChart');
    if (revenueChartEl) {
      const revenueData = @json($monthlyRevenue);
      const months = revenueData.map(item => item.month);
      const revenues = revenueData.map(item => item.revenue);

      const revenueChart = new ApexCharts(revenueChartEl, {
        chart: {
          height: 310,
          type: 'area',
          toolbar: { show: false },
          fontFamily: "'Plus Jakarta Sans', sans-serif",
          sparkline: { enabled: false }
        },
        series: [{
          name: 'Pendapatan',
          data: revenues
        }],
        colors: ['#2563eb'],
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.45,
            opacityTo: 0.05,
            stops: [0, 95, 100]
          }
        },
        stroke: {
          curve: 'smooth',
          width: 3
        },
        xaxis: {
          categories: months,
          axisBorder: { show: false },
          axisTicks: { show: false },
          labels: {
            style: {
              colors: '#64748b',
              fontSize: '12px',
              fontWeight: 500
            }
          }
        },
        yaxis: {
          labels: {
            style: {
              colors: '#64748b',
              fontSize: '12px'
            },
            formatter: function (val) {
              if (val >= 1000000) {
                return 'Rp ' + (val / 1000000).toFixed(1) + 'M';
              } else if (val >= 1000) {
                return 'Rp ' + (val / 1000).toFixed(0) + 'k';
              }
              return 'Rp ' + val.toLocaleString('id-ID');
            }
          }
        },
        tooltip: {
          theme: 'light',
          y: {
            formatter: function (val) {
              return 'Rp ' + val.toLocaleString('id-ID');
            }
          }
        },
        grid: {
          borderColor: '#f1f5f9',
          strokeDashArray: 4,
          padding: { top: 10, right: 10, bottom: 0, left: 10 }
        },
        markers: {
          size: 4,
          colors: ['#2563eb'],
          strokeColors: '#ffffff',
          strokeWidth: 2,
          hover: { size: 6 }
        }
      });
      revenueChart.render();
    }

    // Bills Status Donut Chart
    const billsChartEl = document.querySelector('#billsChart');
    if (billsChartEl) {
      const paidBills = {{ (int) $stats['paid_bills'] }};
      const unpaidBills = {{ (int) $stats['unpaid_bills'] }};
      const hasData = (paidBills + unpaidBills) > 0;

      const billsChart = new ApexCharts(billsChartEl, {
        chart: {
          height: 230,
          type: 'donut',
          fontFamily: "'Plus Jakarta Sans', sans-serif"
        },
        series: hasData ? [paidBills, unpaidBills] : [1],
        labels: hasData ? ['Terbayar', 'Belum Bayar'] : ['Belum Ada Tagihan'],
        colors: hasData ? ['#10b981', '#ef4444'] : ['#e2e8f0'],
        legend: {
          position: 'bottom',
          fontSize: '13px',
          fontWeight: 600,
          markers: { radius: 12 }
        },
        stroke: { width: 0 },
        plotOptions: {
          pie: {
            donut: {
              size: '75%',
              labels: {
                show: true,
                name: {
                  show: true,
                  fontSize: '13px',
                  fontWeight: 600,
                  color: '#64748b'
                },
                value: {
                  show: true,
                  fontSize: '20px',
                  fontWeight: 700,
                  color: '#0f172a',
                  formatter: function(val) {
                    return hasData ? val : 0;
                  }
                },
                total: {
                  show: true,
                  label: 'Total Tagihan',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: '#64748b',
                  formatter: function() {
                    return {{ (int) $stats['monthly_bills'] }};
                  }
                }
              }
            }
          }
        },
        dataLabels: { enabled: false }
      });
      billsChart.render();
    }
  });
</script>
@endsection

@section('content')

<!-- Modern Hero Banner: Telecom & ISP Command Center -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card mryes-hero-banner p-3 p-md-4">
      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge hero-noc-badge">
              <i class="mdi mdi-access-point-network me-1"></i> MRYES NOC CONTROL
            </span>
            <span class="hero-date small">&bull;</span>
            <span class="hero-date small">{{ Carbon\Carbon::now()->isoFormat('dddd, D MMMM Y') }}</span>
          </div>
          <h3 class="hero-title fw-bold mb-1" style="letter-spacing: -0.02em;">
            {{ $greeting }}, {{ $currentUser->name ?? 'Administrator' }}! 👋
          </h3>
          <p class="hero-subtitle mb-0" style="font-size: 0.9rem; max-width: 620px;">
            Sistem operasional terintegrasi: Monitoring MikroTik RouterOS, Manajemen PPPoE/Isolir, Penagihan Otomatis, dan Pemetaan ODP.
          </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
          @if($currentUser && $currentUser->hasRole(1, 2, 4, 7))
          <a href="{{ route('user.create') }}" class="btn btn-primary fw-bold d-flex align-items-center gap-1 shadow-sm px-3">
            <i class="mdi mdi-account-plus"></i>
            <span>Pelanggan Baru</span>
          </a>
          @endif

          @if($currentUser && $currentUser->hasRole(1, 2))
          <a href="{{ route('mikrotik.index') }}" class="btn btn-outline-primary fw-semibold d-flex align-items-center gap-1 shadow-xs px-3" id="hero-btn-mikrotik">
            <i class="mdi mdi-router-wireless"></i>
            <span>Router MikroTik</span>
          </a>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 4 Key Metric Cards -->
<div class="row g-2 g-sm-3 mb-4">
  <!-- Total Pelanggan Aktif -->
  <div class="col-6 col-xl-3">
    <div class="card metric-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between p-2 p-sm-3">
        <div>
          <span class="metric-label d-block mb-1">Pelanggan Aktif</span>
          <div class="metric-value mb-1">{{ number_format($stats['total_users']) }}</div>
          <div class="d-flex align-items-center gap-1" style="font-size: 0.75rem; color: #64748b;">
            <span class="text-success fw-semibold"><i class="mdi mdi-check-circle-outline"></i> Siap Layanan</span>
          </div>
        </div>
        <div class="metric-icon-box" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;">
          <i class="mdi mdi-account-group-outline"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Router Server Aktif -->
  <div class="col-6 col-xl-3">
    <div class="card metric-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between p-2 p-sm-3">
        <div>
          <span class="metric-label d-block mb-1">Router MikroTik</span>
          <div class="metric-value mb-1">{{ $stats['active_mikrotik'] }} <span class="text-muted fw-normal" style="font-size: 0.95rem;">/ {{ $stats['total_mikrotik'] }}</span></div>
          <div class="d-flex align-items-center gap-1" style="font-size: 0.75rem;">
            @if($stats['active_mikrotik'] > 0)
              <span class="text-success fw-semibold"><i class="mdi mdi-lan-connect"></i> Terhubung API</span>
            @else
              <span class="text-danger fw-semibold"><i class="mdi mdi-lan-disconnect"></i> Belum Aktif</span>
            @endif
          </div>
        </div>
        <div class="metric-icon-box" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;">
          <i class="mdi mdi-router-wireless"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Pendapatan Bulan Ini -->
  <div class="col-6 col-xl-3">
    <div class="card metric-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between p-2 p-sm-3">
        <div>
          <span class="metric-label d-block mb-1">Pendapatan Bulan Ini</span>
          <div class="metric-value mb-1" style="font-size: 1.25rem; color: #047857;">Rp {{ number_format($stats['monthly_revenue'], 0, ',', '.') }}</div>
          <div class="d-flex align-items-center gap-1" style="font-size: 0.75rem; color: #64748b;">
            <span>{{ $stats['paid_bills'] }} transaksi terbayar</span>
          </div>
        </div>
        <div class="metric-icon-box" style="background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;">
          <i class="mdi mdi-cash-check"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Tagihan Tertunda -->
  <div class="col-6 col-xl-3">
    <div class="card metric-card h-100">
      <div class="card-body d-flex align-items-center justify-content-between p-2 p-sm-3">
        <div>
          <span class="metric-label d-block mb-1">Tagihan Tertunda</span>
          <div class="metric-value mb-1" style="font-size: 1.25rem; color: #b91c1c;">Rp {{ number_format($stats['pending_revenue'], 0, ',', '.') }}</div>
          <div class="d-flex align-items-center gap-1" style="font-size: 0.75rem; color: #64748b;">
            <span class="text-danger fw-semibold">{{ $stats['unpaid_bills'] }} invoice</span> belum lunas
          </div>
        </div>
        <div class="metric-icon-box" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
          <i class="mdi mdi-alert-circle-outline"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Main Operations Hub: Charts & Fast Status -->
<div class="row g-3 mb-4">
  <!-- Trend Pendapatan Chart -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <h5 class="card-title mb-0">Tren Arus Kas Pembayaran</h5>
          <small class="text-muted">Riwayat realisasi pendapatan 6 bulan terakhir</small>
        </div>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
          <i class="mdi mdi-trending-up me-1"></i> Arus Kas
        </span>
      </div>
      <div class="card-body">
        <div id="revenueChart"></div>
      </div>
    </div>
  </div>

  <!-- Status Tagihan & Quick Stats -->
  <div class="col-lg-4 d-flex flex-column gap-3">
    <!-- Donut Chart -->
    <div class="card">
      <div class="card-header pb-2">
        <h5 class="card-title mb-0">Realisasi Tagihan</h5>
        <small class="text-muted">{{ Carbon\Carbon::now()->isoFormat('MMMM Y') }}</small>
      </div>
      <div class="card-body pt-1">
        <div id="billsChart"></div>
        <div class="row g-2 text-center mt-2 pt-2 border-top">
          <div class="col-6">
            <span class="text-muted d-block small">Terbayar</span>
            <span class="text-success fw-bold fs-6">{{ $stats['paid_bills'] }}</span>
          </div>
          <div class="col-6">
            <span class="text-muted d-block small">Belum Bayar</span>
            <span class="text-danger fw-bold fs-6">{{ $stats['unpaid_bills'] }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Network Summary -->
    <div class="card flex-grow-1">
      <div class="card-header pb-2">
        <h5 class="card-title mb-0">Rangkuman Operasional</h5>
      </div>
      <div class="card-body pt-2 d-flex flex-column justify-content-around gap-2">
        <div class="d-flex align-items-center justify-content-between p-2 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-2 p-1 text-primary bg-primary-subtle"><i class="mdi mdi-speedometer"></i></div>
            <span class="small fw-semibold text-secondary">Total Paket Internet</span>
          </div>
          <span class="badge bg-light text-dark border">{{ $stats['total_packages'] }} Paket</span>
        </div>

        <div class="d-flex align-items-center justify-content-between p-2 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-2 p-1 text-success bg-success-subtle"><i class="mdi mdi-percent"></i></div>
            <span class="small fw-semibold text-secondary">Rasio Pembayaran</span>
          </div>
          <span class="badge bg-success-subtle text-success border border-success-subtle">
            {{ $stats['monthly_bills'] > 0 ? number_format(($stats['paid_bills'] / $stats['monthly_bills']) * 100, 1) : 0 }}%
          </span>
        </div>

        <div class="d-flex align-items-center justify-content-between p-2 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-2 p-1 text-info bg-info-subtle"><i class="mdi mdi-receipt-outline"></i></div>
            <span class="small fw-semibold text-secondary">Tagihan Diterbitkan</span>
          </div>
          <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $stats['monthly_bills'] }} Invoice</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Transactions Table -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-0">
            <i class="mdi mdi-history text-primary me-1"></i> 5 Pembayaran Terakhir
          </h5>
          <small class="text-muted">Transaksi pelunasan tagihan realtime yang berhasil dicatat sistem</small>
        </div>
        @if($currentUser && $currentUser->hasRole(1, 2, 3, 6, 7))
        <a href="{{ route('tagihan.lunas') }}" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
          <i class="mdi mdi-format-list-bulleted"></i>
          <span>Lihat Semua Transaksi</span>
        </a>
        @endif
      </div>
      <div class="card-body p-0">
        @if($recent_transactions->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>No. Invoice</th>
                <th>Pelanggan</th>
                <th>Paket Layanan</th>
                <th>Nominal</th>
                <th>Waktu Pembayaran</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recent_transactions as $transaction)
              <tr>
                <td>
                  <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.8rem;">
                    #{{ $transaction->noTagihan }}
                  </span>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 32px; height: 32px; background: linear-gradient(135deg, #3b82f6, #0ea5e9); font-size: 0.8rem;">
                      {{ strtoupper(substr($transaction->user->name ?? 'P', 0, 1)) }}
                    </div>
                    <div>
                      <div class="fw-semibold text-dark">{{ $transaction->user->name ?? 'Pelanggan N/A' }}</div>
                      <small class="text-muted">ID: {{ $transaction->user->username ?? '-' }}</small>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                    {{ $transaction->paket->nama ?? 'Paket N/A' }}
                  </span>
                </td>
                <td>
                  <span class="fw-bold text-success" style="font-size: 0.95rem;">
                    Rp {{ number_format($transaction->total, 0, ',', '.') }}
                  </span>
                </td>
                <td>
                  <span class="text-secondary small">
                    <i class="mdi mdi-clock-outline me-1"></i>{{ Carbon\Carbon::parse($transaction->tglBayar)->isoFormat('D MMM Y, HH:mm') }} WIB
                  </span>
                </td>
                <td class="text-center">
                  <span class="badge badge-status-active">
                    <i class="mdi mdi-check-circle-outline me-1"></i>LUNAS
                  </span>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div class="text-center py-5">
          <div class="mb-2 text-muted" style="font-size: 2.5rem;">
            <i class="mdi mdi-receipt-text-outline"></i>
          </div>
          <h6 class="text-dark fw-bold mb-1">Belum Ada Transaksi Bulan Ini</h6>
          <p class="text-muted small mb-0">Pembayaran tagihan pelanggan yang terkonfirmasi lunas akan langsung muncul di sini.</p>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>

<!-- Operational Quick Navigation Grid -->
<div class="row g-2 g-sm-3">
  @if($currentUser && $currentUser->hasRole(1, 2, 4, 7))
  <div class="col-6 col-md-3">
    <a href="{{ route('user.create') }}" class="card text-decoration-none h-100 metric-card p-2 p-sm-3 border">
      <div class="d-flex align-items-center gap-2 gap-sm-3">
        <div class="rounded-3 p-2 p-sm-3 bg-primary-subtle text-primary">
          <i class="mdi mdi-account-plus fs-4"></i>
        </div>
        <div class="overflow-hidden">
          <h6 class="text-dark fw-bold mb-0 text-truncate" style="font-size: 0.875rem;">Tambah Pelanggan</h6>
          <small class="text-muted text-truncate d-none d-sm-block">Registrasi baru</small>
        </div>
      </div>
    </a>
  </div>
  @endif

  @if($currentUser && $currentUser->hasRole(1, 2, 3))
  <div class="col-6 col-md-3">
    <a href="{{ route('tagihan.generate') }}" class="card text-decoration-none h-100 metric-card p-2 p-sm-3 border">
      <div class="d-flex align-items-center gap-2 gap-sm-3">
        <div class="rounded-3 p-2 p-sm-3 bg-success-subtle text-success">
          <i class="mdi mdi-lightning-bolt-circle fs-4"></i>
        </div>
        <div class="overflow-hidden">
          <h6 class="text-dark fw-bold mb-0 text-truncate" style="font-size: 0.875rem;">Generate Tagihan</h6>
          <small class="text-muted text-truncate d-none d-sm-block">Terbitkan invoice</small>
        </div>
      </div>
    </a>
  </div>
  @endif

  @if($currentUser && $currentUser->hasRole(1, 2))
  <div class="col-6 col-md-3">
    <a href="{{ route('mikrotik.index') }}" class="card text-decoration-none h-100 metric-card p-2 p-sm-3 border">
      <div class="d-flex align-items-center gap-2 gap-sm-3">
        <div class="rounded-3 p-2 p-sm-3 bg-info-subtle text-info">
          <i class="mdi mdi-router-wireless fs-4"></i>
        </div>
        <div class="overflow-hidden">
          <h6 class="text-dark fw-bold mb-0 text-truncate" style="font-size: 0.875rem;">Server MikroTik</h6>
          <small class="text-muted text-truncate d-none d-sm-block">Status koneksi</small>
        </div>
      </div>
    </a>
  </div>
  @endif

  @if($currentUser && $currentUser->hasRole(1, 2, 3))
  <div class="col-6 col-md-3">
    <a href="{{ route('tagihan.rekap') }}" class="card text-decoration-none h-100 metric-card p-2 p-sm-3 border">
      <div class="d-flex align-items-center gap-2 gap-sm-3">
        <div class="rounded-3 p-2 p-sm-3 bg-warning-subtle text-warning">
          <i class="mdi mdi-file-chart-outline fs-4"></i>
        </div>
        <div class="overflow-hidden">
          <h6 class="text-dark fw-bold mb-0 text-truncate" style="font-size: 0.875rem;">Rekapitulasi Kas</h6>
          <small class="text-muted text-truncate d-none d-sm-block">Laporan kasir</small>
        </div>
      </div>
    </a>
  </div>
  @endif
</div>

@endsection
