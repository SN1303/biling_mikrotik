@extends('layouts/layoutMaster')

@section('title', 'Rekap Tagihan')

@section('page-style')
<style>
  .bulan-header {
    font-size: 1rem;
    font-weight: 700;
  }
  .stat-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .5rem;
    padding: 4px 0;
    font-size: 0.82rem;
  }
  .stat-row .label { color: var(--mryes-text-secondary); min-width: 0; }
  .stat-row .label small { display: block; color: var(--mryes-text-muted); font-size: .72rem; }
  .stat-row .value { font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .divider-thin { border-top: 1px dashed var(--mryes-border); margin: 8px 0; }
  .grand-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.88rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
  }
  .empty-month { opacity: .6; }
  .badge-count {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
  }
  .rekap-ringkas .nilai { white-space: nowrap; font-variant-numeric: tabular-nums; }
</style>
@endsection

@section('page-script')
<script>
  document.getElementById('filter-tahun').addEventListener('change', function() {
    const tahun = this.value;
    const url = new URL(window.location.href);
    url.searchParams.set('tahun', tahun);
    window.location.href = url.toString();
  });
</script>
@endsection

@section('content')
@php
  $rupiah = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

{{-- Header & Filter --}}
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-1">Rekap Tagihan {{ $tahunDipilih }}</h4>
    <p class="text-muted mb-0" style="font-size:.875rem;">Ringkasan tagihan per bulan — belum bayar (sisa), sudah bayar (termasuk cicilan masuk), dan total keseluruhan.</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <label for="filter-tahun" class="form-label mb-0 text-muted small fw-medium">Tahun:</label>
    <select id="filter-tahun" class="form-select form-select-sm" style="width: auto; min-width: 90px;">
      @foreach($listTahun as $t)
        <option value="{{ $t }}" {{ $t == $tahunDipilih ? 'selected' : '' }}>{{ $t }}</option>
      @endforeach
    </select>
  </div>
</div>

{{-- Ringkasan setahun --}}
@php
  $totalSemua       = collect($rekapBulan)->sum('grand_total');
  $totalBelumBayar  = collect($rekapBulan)->sum('belum_bayar_total');
  $totalSudahBayar  = collect($rekapBulan)->sum('sudah_bayar_total');
  $totalTagihan     = collect($rekapBulan)->sum('total_tagihan');
  $ringkasan = [
    ['label' => 'Jumlah Tagihan',    'nilai' => number_format($totalTagihan, 0, ',', '.'), 'ikon' => 'mdi-file-document-multiple-outline', 'warna' => 'primary', 'teks' => ''],
    ['label' => 'Belum Bayar',       'nilai' => $rupiah($totalBelumBayar), 'ikon' => 'mdi-cash-clock',              'warna' => 'danger',  'teks' => 'text-danger'],
    ['label' => 'Sudah Bayar',       'nilai' => $rupiah($totalSudahBayar), 'ikon' => 'mdi-check-circle-outline',    'warna' => 'success', 'teks' => 'text-success'],
    ['label' => 'Total Keseluruhan', 'nilai' => $rupiah($totalSemua),      'ikon' => 'mdi-cash-multiple',           'warna' => 'info',    'teks' => ''],
  ];
@endphp
<div class="row g-2 g-sm-3 mb-4 rekap-ringkas">
  @foreach($ringkasan as $r)
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body p-3 d-flex align-items-center gap-3">
        <div class="d-none d-sm-flex align-items-center justify-content-center bg-label-{{ $r['warna'] }} rounded-2 flex-shrink-0" style="width:42px;height:42px;">
          <i class="mdi {{ $r['ikon'] }} fs-5"></i>
        </div>
        <div class="min-w-0">
          <div class="text-muted small">{{ $r['label'] }}</div>
          <div class="fw-bold fs-6 nilai {{ $r['teks'] }}">{{ $r['nilai'] }}</div>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>

{{-- Grid 12 Bulan --}}
<div class="row g-2 g-sm-3">
  @foreach($rekapBulan as $bulan)
  @php $isEmpty = $bulan['total_tagihan'] == 0; @endphp
  <div class="col-12 col-sm-6 col-md-4 col-xl-3">
    <div class="card border-0 shadow-sm {{ $isEmpty ? 'empty-month' : 'h-100' }}">
      <div class="card-body p-3">

        {{-- Header Bulan --}}
        <div class="d-flex align-items-center justify-content-between">
          <span class="bulan-header text-dark">{{ $bulan['nama'] }}</span>
          @if(!$isEmpty)
            <span class="badge bg-secondary badge-count">{{ $bulan['total_tagihan'] }} tagihan</span>
          @else
            <small class="text-muted">Belum ada tagihan</small>
          @endif
        </div>

        @unless($isEmpty)
        <div class="divider-thin"></div>

        {{-- Belum Bayar --}}
        <div class="stat-row">
          <span class="label">
            <i class="mdi mdi-circle text-danger me-1" style="font-size:.55rem;"></i>Belum Bayar
            <small>{{ $bulan['belum_bayar_count'] }} tagihan</small>
          </span>
          <span class="value text-danger">{{ $rupiah($bulan['belum_bayar_total']) }}</span>
        </div>

        {{-- Sudah Bayar --}}
        <div class="stat-row">
          <span class="label">
            <i class="mdi mdi-circle text-success me-1" style="font-size:.55rem;"></i>Sudah Bayar
            <small>{{ $bulan['sudah_bayar_count'] }} tagihan</small>
          </span>
          <span class="value text-success">{{ $rupiah($bulan['sudah_bayar_total']) }}</span>
        </div>

        <div class="divider-thin"></div>

        {{-- Total Bulan --}}
        <div class="grand-total-row">
          <span class="text-muted">Total</span>
          <span class="text-dark">{{ $rupiah($bulan['grand_total']) }}</span>
        </div>
        @endunless

      </div>
    </div>
  </div>
  @endforeach
</div>

@endsection
