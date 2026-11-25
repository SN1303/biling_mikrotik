{{-- Gaya bersama halaman Tagihan Aktif & Riwayat Lunas: kartu KPI, filter, tabel, tampilan HP, struk, cetak --}}
<style>
  /* Styling KPI Cards Compact & Sleek */
  .metric-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 10px !important;
  }
  .metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06) !important;
  }
  .metric-card .card-body {
    padding: 0.6rem 0.85rem !important;
  }
  .metric-icon-box {
    width: 34px !important;
    height: 34px !important;
    border-radius: 8px !important;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .metric-icon-box i {
    font-size: 1.15rem !important;
  }
  .metric-title {
    font-size: 0.72rem !important;
    font-weight: 600 !important;
    color: #64748b !important;
    margin-bottom: 2px !important;
  }
  .metric-value {
    font-size: 1.15rem !important;
    font-weight: 800 !important;
    line-height: 1.15 !important;
  }
  .metric-sub {
    font-size: 0.68rem !important;
  }

  /* Segmented Filter Pills (Linear / iOS Style - Sleek & Compact) */
  .filter-segmented-control {
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 2px;
  }
  .btn-filter-pill {
    padding: 0.28rem 0.65rem !important;
    font-size: 0.78rem !important;
    font-weight: 600 !important;
    border: none !important;
    border-radius: 6px !important;
    background: transparent;
    color: #64748b;
    transition: all 0.15s ease;
    white-space: nowrap;
    line-height: 1.3 !important;
  }
  .btn-filter-pill:hover {
    background: rgba(255, 255, 255, 0.6);
    color: #0f172a;
  }
  .btn-filter-pill.active {
    background: #ffffff !important;
    color: #0f172a !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1) !important;
  }
  .btn-filter-pill.active.text-danger {
    color: #ef4444 !important;
  }
  .btn-filter-pill.active.text-warning {
    color: #d97706 !important;
  }
  .btn-filter-pill.active.text-success {
    color: #10b981 !important;
  }

  /* Custom Search Box & Length */
  .search-input-group {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  .search-input-group:focus-within {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12) !important;
  }
  .search-input-group .input-group-text,
  .search-input-group .form-control {
    border: none !important;
  }

  /* Table styling */
  #table thead th {
    font-size: 0.72rem !important;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-weight: 700;
    white-space: nowrap;
    background-color: #f8fafc !important;
    padding: 0.65rem 0.85rem !important;
    color: #475569 !important;
  }
  #table tbody td {
    padding: 0.65rem 0.85rem !important;
    font-size: 0.82rem !important;
  }
  #table tbody tr:hover {
    background-color: rgba(37, 99, 235, 0.02) !important;
  }
  .btn-xs {
    padding: 2px 7px;
    font-size: 0.72rem;
    border-radius: 4px;
  }

  /* Override Datatables wrapper padding */
  .card-datatable .dataTables_wrapper .dataTables_length,
  .card-datatable .dataTables_wrapper .dataTables_filter {
    padding: 0 !important;
    margin: 0 !important;
  }
  .card-datatable .dataTables_wrapper .dataTables_info,
  .card-datatable .dataTables_wrapper .dataTables_paginate {
    padding: 0.5rem 1rem !important;
  }

  /* Quick filter scrollable di mobile */
  .quick-filter-scroll-wrapper {
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }
  .quick-filter-scroll-wrapper::-webkit-scrollbar {
    display: none;
  }

  /* Responsif Smartphone Khusus HP (< 768px) */
  @media (max-width: 767.98px) {
    /* Perkecil Kotak KPI Summary */
    .metric-card .card-body {
      padding: 0.45rem 0.65rem !important;
    }
    .metric-icon-box {
      width: 28px !important;
      height: 28px !important;
      border-radius: 6px !important;
    }
    .metric-icon-box i {
      font-size: 0.95rem !important;
    }
    .metric-title {
      font-size: 0.68rem !important;
      margin-bottom: 0px !important;
    }
    .metric-value {
      font-size: 0.92rem !important;
      margin-bottom: 0 !important;
    }
    .metric-sub {
      display: none !important;
    }

    /* Transformasi DataTables ke Mobile List Cards */
    #table thead {
      display: none !important;
    }
    #table {
      border: none !important;
    }
    #table tbody tr {
      display: block !important;
      margin-bottom: 8px !important;
      border: 1px solid #e2e8f0 !important;
      border-radius: 10px !important;
      background: #ffffff !important;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
      overflow: hidden !important;
    }
    #table tbody td {
      display: none !important;
      border: none !important;
    }
    #table tbody td.col-mobile-card {
      display: block !important;
      padding: 10px 12px !important;
    }
    .card-datatable {
      padding: 6px !important;
      background: #f8fafc !important;
    }

    /* Search Input Full Width di HP */
    .search-input-group {
      width: 100% !important;
      margin-top: 4px;
    }
    .dataTables_info,
    .dataTables_paginate {
      text-align: center !important;
      justify-content: center !important;
      font-size: 0.8rem !important;
    }
  }

  /* Struk Kuitansi Container */
  .receipt-box {
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    padding: 20px;
    font-family: 'Courier New', Courier, monospace, system-ui;
    color: #1e293b;
  }
  .receipt-header {
    text-align: center;
    border-bottom: 2px dashed #94a3b8;
    padding-bottom: 12px;
    margin-bottom: 14px;
  }
  .receipt-stamp-paid {
    border: 2px solid #10b981;
    color: #10b981;
    font-weight: 900;
    padding: 2px 10px;
    border-radius: 6px;
    display: inline-block;
    letter-spacing: 2px;
    transform: rotate(-3deg);
  }
  .receipt-stamp-cicilan {
    border: 2px solid #f59e0b;
    color: #f59e0b;
    font-weight: 900;
    padding: 2px 10px;
    border-radius: 6px;
    display: inline-block;
    letter-spacing: 2px;
    transform: rotate(-3deg);
  }
  .receipt-stamp-gratis {
    border: 2px solid #06b6d4;
    color: #06b6d4;
    font-weight: 900;
    padding: 2px 10px;
    border-radius: 6px;
    display: inline-block;
    letter-spacing: 2px;
    transform: rotate(-3deg);
  }
  .receipt-item-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
    font-size: 0.88rem;
  }
  .receipt-divider {
    border-top: 1px dashed #cbd5e1;
    margin: 10px 0;
  }

  /* Print Struk CSS Styling */
  @media print {
    body * {
      visibility: hidden;
    }
    #struk-print-wrapper, #struk-print-wrapper * {
      visibility: visible;
    }
    #struk-print-wrapper {
      position: absolute;
      left: 0;
      top: 0;
      width: 100%;
      margin: 0;
      padding: 10px;
    }
    .modal-footer, .btn-close {
      display: none !important;
    }
  }
</style>
