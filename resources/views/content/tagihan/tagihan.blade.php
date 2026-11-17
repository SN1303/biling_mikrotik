@extends('layouts/layoutMaster')

@section('title', 'Tagihan Aktif (Belum Lunas)')

@section('vendor-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
@endsection

@section('page-style')
<style>
  /* Styling KPI Cards Compact & Responsif */
  .metric-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 10px !important;
  }
  .metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.07) !important;
  }
  .metric-card .card-body {
    padding: 0.75rem 0.9rem !important;
  }
  .metric-icon-box {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .metric-icon-box i {
    font-size: 1.25rem;
  }
  .metric-value {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.2;
  }

  /* Metode Bayar Visual Radio Tiles */
  .metode-card {
    cursor: pointer;
    border: 2px solid #e2e8f0 !important;
    background: #ffffff;
    transition: all 0.2s ease;
  }
  .metode-card:hover {
    border-color: #cbd5e1 !important;
    background: #f8fafc;
  }
  .btn-check:checked + .metode-card,
  .metode-card.active-metode {
    border-color: #2563eb !important;
    background: rgba(37, 99, 235, 0.08) !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2) !important;
  }

  /* Table styling */
  #table thead th {
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-weight: 700;
    white-space: nowrap;
    background-color: #f8fafc !important;
  }
  #table tbody tr:hover {
    background-color: rgba(37, 99, 235, 0.02) !important;
  }
  .btn-xs {
    padding: 2px 7px;
    font-size: 0.72rem;
    border-radius: 4px;
  }

  /* Quick filter scrollable di mobile */
  .quick-filter-scroll-wrapper {
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 2px;
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
      display: block !important;
      border: none !important;
      padding: 10px 12px !important;
    }
    .card-datatable {
      padding: 6px !important;
      background: #f8fafc !important;
    }

    /* Search Input Full Width di HP */
    .dataTables_filter {
      width: 100% !important;
      text-align: left !important;
      margin-top: 4px !important;
    }
    .dataTables_filter label {
      width: 100% !important;
      margin-bottom: 0 !important;
    }
    .dataTables_filter input {
      width: 100% !important;
      margin-left: 0 !important;
      font-size: 0.85rem !important;
      border-radius: 8px !important;
      height: 38px !important;
    }
    .dataTables_length {
      display: none !important;
    }
    .dataTables_info,
    .dataTables_paginate {
      text-align: center !important;
      justify-content: center !important;
      font-size: 0.8rem !important;
    }
  }
</style>
@endsection

@section('page-script')
<script>
  let table;
  let activeQuickFilter = '';
  let currentRawTotal = 0;

  const namaBulan = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];

  $(document).ready(function() {
    // Enable GlobalLoading
    if (window.GlobalLoading) {
      GlobalLoading.enableAutoLoading();
      GlobalLoading.show('Memuat data tagihan...');
    }

    table = $('#table').DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: "{{ route('tagihan.json') }}",
        data: function(d) {
          d.status_bayar = 0; // Khusus belum bayar
          const tahun = $('#filter-tahun').val();
          const bulan = $('#filter-bulan').val();
          if (tahun) d.tahun = tahun;
          if (bulan) d.bulan = bulan;
          if (activeQuickFilter) d.quick_filter = activeQuickFilter;
        },
        dataSrc: function(json) {
          if (json.summary) {
            updateSummaryCards(json.summary);
          }
          return json.data;
        }
      },
      columns: [
        {
          data: null,
          orderable: false,
          searchable: false,
          className: 'text-center align-middle d-none d-md-table-cell',
          render: function(data, type, row, meta) {
            return `<span class="text-muted fw-bold small">${meta.row + meta.settings._iDisplayStart + 1}</span>`;
          }
        },
        {
          data: 'user_nama',
          name: 'user_nama',
          className: 'align-middle p-2 p-md-3',
          render: function(data, type, row) {
            const wa = row.user_wa;
            const waButton = wa && wa !== '-'
              ? `<a href="javascript:void(0)" class="btn-wa-direct badge bg-success-subtle text-success border border-success-subtle text-decoration-none" data-id="${row.id}" title="Kirim Pesan WhatsApp"><i class="mdi mdi-whatsapp me-1"></i>${wa}</a>`
              : `<span class="badge bg-light text-muted border">No WA (-)</span>`;

            const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.total || 0);
            const namaBulanStr = namaBulan[row.bulan] || row.bulan;
            const periodeText = `${namaBulanStr} ${row.tahun}`;

            // Perhitungan Jatuh Tempo
            const days = row.due_diff_days;
            let dueBadgeMobile = '';
            if (row.tglJatuhTempo) {
              if (days !== null && days !== undefined) {
                if (days < 0) {
                  dueBadgeMobile = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-semibold" style="font-size: 0.68rem;"><i class="mdi mdi-alert-circle me-1"></i>Lewat ${Math.abs(days)} hr</span>`;
                } else if (days === 0) {
                  dueBadgeMobile = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-bold" style="font-size: 0.68rem;"><i class="mdi mdi-clock-alert me-1"></i>Hari Ini!</span>`;
                } else if (days <= 3) {
                  dueBadgeMobile = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.68rem;"><i class="mdi mdi-clock-outline me-1"></i>Sisa ${days} hr</span>`;
                } else {
                  dueBadgeMobile = `<span class="badge bg-light text-secondary border" style="font-size: 0.68rem;">Sisa ${days} hr</span>`;
                }
              } else {
                dueBadgeMobile = `<span class="badge bg-light text-muted" style="font-size: 0.68rem;">${row.tglJatuhTempo}</span>`;
              }
            }

            let deleteBtnMobile = '';
            @if(auth()->user()->hasRole(1, 2))
            deleteBtnMobile = `
              <button type="button" class="btn btn-sm btn-outline-danger btn-delete px-2 py-2" data-id="${row.id}" title="Hapus Tagihan" style="border-radius: 8px;">
                <i class="mdi mdi-trash-can-outline fs-6"></i>
              </button>
            `;
            @endif

            return `
              <!-- DESKTOP VIEW (>= 768px) -->
              <div class="d-none d-md-block">
                <div class="fw-bold text-dark fs-6 mb-1">${data || 'Tanpa Nama'}</div>
                <div class="d-flex align-items-center flex-wrap gap-1">
                  <span class="badge bg-label-secondary font-monospace" style="font-size: 0.7rem;">${row.noTagihan}</span>
                  ${waButton}
                </div>
              </div>

              <!-- MOBILE CARD VIEW (< 768px) - RESPONSIF SMARTPHONE UNTUK KOLEKTOR -->
              <div class="d-md-none mobile-bill-card">
                <!-- Baris 1: Nama Pelanggan & Total Tagihan -->
                <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                  <div class="flex-grow-1" style="min-width: 0;">
                    <div class="fw-bold text-dark fs-6 text-truncate mb-0">
                      ${data || 'Tanpa Nama'}
                    </div>
                    <small class="text-muted d-block" style="font-size: 0.7rem;">
                      <i class="mdi mdi-receipt-text-outline me-1"></i>${row.noTagihan || '-'}
                    </small>
                  </div>
                  <div class="text-end flex-shrink-0">
                    <div class="fs-6 fw-bolder text-primary mb-0">${totalFmt}</div>
                    <div class="mt-1">${dueBadgeMobile}</div>
                  </div>
                </div>

                <!-- Baris 2: Paket, Periode & WA Pelanggan -->
                <div class="d-flex align-items-center justify-content-between gap-1 py-1 px-2 my-2 bg-light rounded-2 border" style="font-size: 0.72rem;">
                  <div class="text-truncate">
                    <i class="mdi mdi-speedometer me-1 text-primary"></i>
                    <span class="fw-semibold text-dark">${row.paket_nama || 'Paket Internet'}</span>
                    <span class="text-muted">(${periodeText})</span>
                  </div>
                  <div>
                    ${wa && wa !== '-' ? `<a href="https://wa.me/${String(wa).replace(/[^0-9]/g, '').replace(/^0/, '62')}" target="_blank" class="text-success fw-semibold text-decoration-none"><i class="mdi mdi-whatsapp me-1"></i>${wa}</a>` : '<span class="text-muted">No WA (-)</span>'}
                  </div>
                </div>

                <!-- Baris 3: TOMBOL BAYAR LANGSUNG DI DEPAN MATA (MUDAH DITEKAN JEMPOL) -->
                <div class="d-flex align-items-center gap-2 mt-2">
                  <button type="button" class="btn btn-success btn-bayar flex-grow-1 d-flex align-items-center justify-content-center gap-1 py-2 shadow-xs fw-bold" data-id="${row.id}" style="font-size: 0.85rem; border-radius: 8px;">
                    <i class="mdi mdi-cash-check fs-5"></i>
                    <span>BAYAR SEKARANG</span>
                  </button>

                  <button type="button" class="btn btn-outline-success btn-wa-direct d-flex align-items-center justify-content-center p-2 shadow-xs" data-id="${row.id}" title="Kirim Pengingat WhatsApp" style="border-radius: 8px; width: 42px; height: 38px;">
                    <i class="mdi mdi-whatsapp fs-5"></i>
                  </button>

                  ${deleteBtnMobile}
                </div>
              </div>
            `;
          }
        },
        {
          data: 'paket_nama',
          name: 'paket_nama',
          className: 'align-middle d-none d-md-table-cell',
          render: function(data, type, row) {
            const periodeText = `${namaBulan[row.bulan] || row.bulan} ${row.tahun}`;
            return `
              <div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold mb-1">
                  <i class="mdi mdi-speedometer me-1"></i>${data || 'Paket Internet'}
                </span>
                <div class="small text-muted d-flex align-items-center gap-1">
                  <i class="mdi mdi-calendar-month-outline text-secondary"></i>
                  <span>${periodeText}</span>
                </div>
              </div>
            `;
          }
        },
        {
          data: 'total',
          name: 'total',
          className: 'align-middle d-none d-md-table-cell',
          render: function(data, type, row) {
            const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(data);
            let detailSub = '';
            if (row.diskon > 0) {
              detailSub = `<small class="text-success d-block" style="font-size: 0.72rem;"><i class="mdi mdi-tag-outline me-1"></i>Hemat Rp ${new Intl.NumberFormat('id-ID').format(row.diskon)}</small>`;
            } else if (row.ppn > 0) {
              detailSub = `<small class="text-muted d-block" style="font-size: 0.72rem;">Termasuk PPN</small>`;
            }
            return `
              <div>
                <div class="fw-bold text-danger fs-6">${totalFmt}</div>
                ${detailSub}
              </div>
            `;
          }
        },
        {
          data: 'tglJatuhTempo',
          name: 'tglJatuhTempo',
          className: 'align-middle d-none d-md-table-cell',
          render: function(data, type, row) {
            if (!data) return '<span class="text-muted">-</span>';

            const days = row.due_diff_days;
            let badge = '';

            if (days !== null && days !== undefined) {
              if (days < 0) {
                badge = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex align-items-center gap-1 mt-1" style="font-size: 0.7rem;"><i class="mdi mdi-alert-circle"></i> Lewat ${Math.abs(days)} hari</span>`;
              } else if (days === 0) {
                badge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle d-inline-flex align-items-center gap-1 mt-1" style="font-size: 0.7rem;"><i class="mdi mdi-clock-alert"></i> Hari Ini!</span>`;
              } else if (days <= 3) {
                badge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle d-inline-flex align-items-center gap-1 mt-1" style="font-size: 0.7rem;"><i class="mdi mdi-clock-outline"></i> Sisa ${days} hari</span>`;
              } else {
                badge = `<span class="badge bg-light text-secondary border d-inline-flex align-items-center gap-1 mt-1" style="font-size: 0.7rem;"><i class="mdi mdi-calendar-check"></i> Sisa ${days} hari</span>`;
              }
            }

            return `
              <div>
                <div class="small fw-semibold text-dark">${data}</div>
                ${badge}
              </div>
            `;
          }
        },
        {
          data: 'id',
          name: 'action',
          orderable: false,
          searchable: false,
          className: 'text-center align-middle d-none d-md-table-cell',
          render: function(data, type, row) {
            let deleteBtn = '';
            @if(auth()->user()->hasRole(1, 2))
            deleteBtn = `
              <li><hr class="dropdown-divider my-1"></li>
              <li>
                <a class="dropdown-item btn-delete text-danger py-1" href="javascript:void(0);" data-id="${data}">
                  <i class="mdi mdi-trash-can-outline me-2"></i> Hapus Tagihan
                </a>
              </li>
            `;
            @endif

            return `
              <div class="d-inline-flex align-items-center gap-1">
                <!-- Tombol Bayar Langsung -->
                <button type="button" class="btn btn-sm btn-success btn-bayar d-inline-flex align-items-center gap-1 shadow-xs px-2 py-1" data-id="${data}" title="Bayar Sekarang">
                  <i class="mdi mdi-cash-check"></i>
                  <span class="fw-semibold">Bayar</span>
                </button>

                <!-- Tombol WhatsApp -->
                <button type="button" class="btn btn-sm btn-outline-success btn-wa-direct d-inline-flex align-items-center p-1" data-id="${data}" title="Kirim Pengingat WhatsApp">
                  <i class="mdi mdi-whatsapp fs-6"></i>
                </button>

                <!-- Dropdown Opsi -->
                <div class="dropdown">
                  <button type="button" class="btn btn-sm btn-icon btn-light rounded-circle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="mdi mdi-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow border py-1" style="min-width: 160px; border-radius: 8px;">
                    <li>
                      <a class="dropdown-item btn-bayar text-success py-1" href="javascript:void(0);" data-id="${data}">
                        <i class="mdi mdi-credit-card-outline me-2"></i> Kasir Pembayaran
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item btn-wa-direct py-1" href="javascript:void(0);" data-id="${data}">
                        <i class="mdi mdi-whatsapp me-2 text-success"></i> Ingatkan Pelanggan
                      </a>
                    </li>
                    ${deleteBtn}
                  </ul>
                </div>
              </div>
            `;
          }
        }
      ],
      order: [[4, 'asc']], // Urutkan berdasarkan jatuh tempo terdekat
      dom:
        '<"card-body py-2 px-3 border-bottom"<"row align-items-center g-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>>' +
        't' +
        '<"card-body py-2 px-3"<"row align-items-center g-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"p>>>',
      displayLength: 20,
      lengthMenu: [10, 20, 35, 50, 100],
      language: {
        search: "",
        searchPlaceholder: "Cari nama, invoice, paket...",
        lengthMenu: "Tampilkan _MENU_ data",
        info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ tagihan",
        infoEmpty: "Tidak ada data tagihan",
        infoFiltered: "(disaring dari _MAX_ total data)",
        emptyTable: "Tidak ada tagihan tertunda untuk periode ini",
        paginate: {
          next: '<i class="mdi mdi-chevron-right"></i>',
          previous: '<i class="mdi mdi-chevron-left"></i>'
        }
      },
      initComplete: function() {
        if (window.GlobalLoading) {
          GlobalLoading.hide();
        }
      }
    });

    // Update Summary Cards & Pills
    function updateSummaryCards(summary) {
      const totalCount = summary.total_count || 0;
      const totalUnpaid = summary.total_unpaid || 0;
      const overdueCount = summary.overdue_count || 0;
      const overdueTotal = summary.overdue_total || 0;
      const todayCount = summary.today_due_count || 0;
      const potentialIncome = summary.potential_income || 0;

      $('#total-count').text(new Intl.NumberFormat('id-ID').format(totalCount));
      $('#total-unpaid').text('Rp ' + new Intl.NumberFormat('id-ID').format(totalUnpaid));
      $('#overdue-count').text(new Intl.NumberFormat('id-ID').format(overdueCount));
      $('#overdue-total').text('Rp ' + new Intl.NumberFormat('id-ID').format(overdueTotal));
      $('#potential-income').text('Rp ' + new Intl.NumberFormat('id-ID').format(potentialIncome));

      // Pills badge
      $('#pill-all').text(totalCount);
      $('#pill-overdue').text(overdueCount);
      $('#pill-today').text(todayCount);
    }

    // Filter Quick Pills Event
    $('#quick-filter-group').on('click', 'button', function() {
      $('#quick-filter-group button').removeClass('active');
      $(this).addClass('active');

      activeQuickFilter = $(this).data('filter');

      if (window.GlobalLoading) {
        GlobalLoading.show('Menyaring status tagihan...');
      }

      table.ajax.reload(() => {
        if (window.GlobalLoading) {
          GlobalLoading.hide();
        }
      });
    });

    // Filter Tahun & Bulan Event
    $('#filter-tahun, #filter-bulan').on('change', function() {
      if (window.GlobalLoading) {
        GlobalLoading.show('Memfilter data periode...');
      }
      table.ajax.reload(() => {
        if (window.GlobalLoading) {
          GlobalLoading.hide();
        }
      });
    });

    // Tombol Refresh
    $('#btn-refresh-table').on('click', function() {
      $(this).find('i').addClass('mdi-spin');
      table.ajax.reload(() => {
        $('#btn-refresh-table').find('i').removeClass('mdi-spin');
      });
    });

    // Event Handler Buka Kasir Pembayaran (Modal Bayar)
    $(document).on('click', '.btn-bayar', function(e) {
      e.preventDefault();
      const id = $(this).attr('data-id');
      console.log('Klik bayar ID:', id);

      let rowData = null;
      const allRows = table.rows().data().toArray();
      if (allRows && allRows.length > 0) {
        rowData = allRows.find(item => item.id == id);
      }

      if (!rowData) {
        const tr = $(this).closest('tr');
        rowData = table.row(tr).data();
      }

      if (!rowData) {
        console.warn('Row data tidak ditemukan di memori, ambil via AJAX:', id);
        if (window.GlobalLoading) GlobalLoading.show('Memuat data tagihan...');
        $.ajax({
          url: `{{ url('tagihan') }}/${id}`,
          type: 'GET',
          success: function(res) {
            if (window.GlobalLoading) GlobalLoading.hide();
            bukaModalKasir(id, res);
          },
          error: function() {
            if (window.GlobalLoading) GlobalLoading.hide();
            Swal.fire('Error', 'Gagal memuat rincian tagihan.', 'error');
          }
        });
        return;
      }

      bukaModalKasir(id, rowData);
    });

    function bukaModalKasir(id, rowData) {
      currentRawTotal = parseFloat(rowData.total) || 0;
      const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(currentRawTotal);
      const bulanNama = namaBulan[rowData.bulan] || rowData.bulan;
      const periodeStr = `${bulanNama} ${rowData.tahun}`;

      // Populate Modal Info
      $('#modal-noTagihan').text(rowData.noTagihan || '-');
      $('#modal-namaPelanggan').text(rowData.user_nama || 'Pelanggan');
      $('#modal-paket-badge').text(rowData.paket_nama || 'Paket Internet');
      $('#modal-periode-display').text(periodeStr);
      $('#modal-total-display').text(totalFmt);

      // Set ID tagihan ke form
      $('#form-bayar').data('tagihan-id', id).attr('data-tagihan-id', id);
      $('#tagihan_id').val(id);

      // Default Metode: Tunai / COD
      pilihMetodeBayar('cod');

      // Reset Catatan
      $('#keterangan_bayar').val('');

      // Tampilkan Modal
      const modalEl = document.getElementById('modalBayar');
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      } else {
        $('#modalBayar').modal('show');
      }
    }

    // Fungsi Ganti Metode Pembayaran Kasir (Tunai vs Non-Tunai)
    function pilihMetodeBayar(selected) {
      $('#metode_bayar').val(selected);
      $('input[name="metode_bayar_radio"]').prop('checked', false);
      $(`#metode_${selected}`).prop('checked', true);

      // Highlight visual card aktif
      $('.metode-card').removeClass('active-metode');
      $(`label[for="metode_${selected}"]`).addClass('active-metode');

      const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(currentRawTotal);

      if (selected === 'cod') {
        // Tunai / COD: Tampilkan kalkulator kembalian uang tunai
        $('#cash-calculator-box').show();
        $('#nontunai-info-box').hide();
        $('#cash-received').val('');
        $('#cash-change-display').text('Rp 0').removeClass('text-danger').addClass('text-success');
        $('#cash-change-status').text('Masukkan nominal uang yang diterima');
        $('#keterangan_bayar').attr('placeholder', 'Contoh: Titip ke tetangga / bayar tunai di lokasi');
      } else {
        // Non-Tunai (Transfer / QRIS / E-Wallet):
        // UANG DITERIMA OTOMATIS IKUT PAS SESUAI TOTAL TAGIHAN, KEMBALIAN RP 0
        $('#cash-calculator-box').hide();
        $('#nontunai-info-box').show();
        $('#nontunai-amount').text(totalFmt);
        $('#cash-received').val(currentRawTotal); // Otomatis terisi uang pas

        if (selected === 'transfer') {
          $('#nontunai-icon').attr('class', 'mdi mdi-bank-transfer fs-5');
          $('#nontunai-title').text('Pembayaran Transfer Bank');
          $('#nontunai-desc').text('Uang diterima otomatis pas sesuai tagihan (Kembalian Rp 0)');
          $('#keterangan_bayar').attr('placeholder', 'No. Ref transfer / An. Rekening pengirim (opsional)');
        } else if (selected === 'qris') {
          $('#nontunai-icon').attr('class', 'mdi mdi-qrcode-scan fs-5');
          $('#nontunai-title').text('Pembayaran QRIS');
          $('#nontunai-desc').text('Nominal scan otomatis pas sesuai barcode (Kembalian Rp 0)');
          $('#keterangan_bayar').attr('placeholder', 'NMID / Ref transaksi QRIS (opsional)');
        } else if (selected === 'ewallet') {
          $('#nontunai-icon').attr('class', 'mdi mdi-cellphone-nfc fs-5');
          $('#nontunai-title').text('Pembayaran E-Wallet (DANA/OVO/GoPay)');
          $('#nontunai-desc').text('Nominal otomatis pas terdebet (Kembalian Rp 0)');
          $('#keterangan_bayar').attr('placeholder', 'Nama E-Wallet & No. HP pengirim (opsional)');
        }
      }
    }

    // Event Handler Ganti Metode Bayar (Delegasi Radio & Card Click)
    $(document).on('change', 'input[name="metode_bayar_radio"]', function() {
      pilihMetodeBayar($(this).val());
    });

    $(document).on('click', '.metode-card', function(e) {
      const forId = $(this).attr('for');
      if (forId) {
        const val = $('#' + forId).val();
        pilihMetodeBayar(val);
      }
    });

    // Kalkulator Kembalian Uang Tunai Realtime
    $('#cash-received').on('input', function() {
      hitungKembalian();
    });

    function hitungKembalian() {
      const received = parseFloat($('#cash-received').val()) || 0;
      const change = received - currentRawTotal;

      if (received === 0) {
        $('#cash-change-display').text('Rp 0').removeClass('text-danger').addClass('text-success');
        $('#cash-change-status').text('Masukkan nominal uang yang diterima');
      } else if (change < 0) {
        $('#cash-change-display').text('- Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(change))).removeClass('text-success').addClass('text-danger');
        $('#cash-change-status').text('Uang kurang Rp ' + new Intl.NumberFormat('id-ID').format(Math.abs(change)));
      } else if (change === 0) {
        $('#cash-change-display').text('Rp 0').removeClass('text-danger').addClass('text-success');
        $('#cash-change-status').text('Uang pas');
      } else {
        $('#cash-change-display').text('Rp ' + new Intl.NumberFormat('id-ID').format(change)).removeClass('text-danger').addClass('text-success');
        $('#cash-change-status').text('Kembalikan ke pelanggan');
      }
    }

    // Tombol Uang Cepat (Quick Cash Buttons)
    $('.btn-quick-cash').on('click', function() {
      const mode = $(this).data('mode');
      if (mode === 'exact') {
        $('#cash-received').val(currentRawTotal);
      } else {
        const amount = parseFloat($(this).data('amount')) || 0;
        $('#cash-received').val(amount);
      }
      hitungKembalian();
    });

    // Form Submit Pembayaran
    $('#form-bayar').on('submit', function(e) {
      e.preventDefault();

      const tagihanId = $('#tagihan_id').val() || $(this).data('tagihan-id') || $(this).attr('data-tagihan-id');
      const metodeBayar = $('#metode_bayar').val();
      const keterangan = $('#keterangan_bayar').val();

      if (!metodeBayar) {
        Swal.fire({
          title: 'Metode Pembayaran',
          text: 'Silakan pilih metode pembayaran terlebih dahulu!',
          icon: 'warning'
        });
        return;
      }

      const modalEl = document.getElementById('modalBayar');
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      } else {
        $('#modalBayar').modal('hide');
      }

      const paymentSteps = [
        { progress: 20, text: 'Memvalidasi data transaksi kasir...', delay: 250 },
        { progress: 55, text: 'Mencatat pelunasan & audit trail...', delay: 350 },
        { progress: 90, text: 'Mengupdate status tagihan lunas...', delay: 300 },
        { progress: 100, text: 'Pembayaran sukses diproses!', delay: 200 }
      ];

      if (window.GlobalLoading) {
        GlobalLoading.showWithSteps(paymentSteps);
      }

      setTimeout(() => {
        $.ajax({
          url: `{{ url('tagihan/bayar') }}/${tagihanId}`,
          type: 'POST',
          data: {
            _token: '{{ csrf_token() }}',
            metode_bayar: metodeBayar,
            keterangan: keterangan
          },
          success: function(response) {
            if (window.GlobalLoading) {
              GlobalLoading.hide();
            }

            if (response.success) {
              Swal.fire({
                title: 'Pembayaran Berhasil!',
                html: `
                  <div class="text-center p-2">
                    <div class="avatar avatar-lg bg-success-subtle text-success mx-auto mb-3" style="width: 54px; height: 54px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                      <i class="mdi mdi-check-decagram fs-2"></i>
                    </div>
                    <h5 class="fw-bold mb-1">${$('#modal-namaPelanggan').text()}</h5>
                    <p class="text-muted small mb-2">Invoice: ${$('#modal-noTagihan').text()}</p>
                    <div class="fs-4 fw-bold text-success mb-2">${$('#modal-total-display').text()}</div>
                    <span class="badge bg-success">Status: LUNAS (${metodeBayar.toUpperCase()})</span>
                  </div>
                `,
                icon: null,
                confirmButtonText: '<i class="mdi mdi-check me-1"></i> Selesai',
                buttonsStyling: false,
                customClass: {
                  confirmButton: "btn btn-success px-4"
                }
              });

              table.ajax.reload(null, false);
            } else {
              Swal.fire({
                title: 'Gagal Memproses Pembayaran',
                text: response.message || 'Terjadi kesalahan sistem.',
                icon: 'error'
              });
            }
          },
          error: function(xhr) {
            if (window.GlobalLoading) {
              GlobalLoading.hide();
            }

            let msg = 'Terjadi kesalahan pada server saat memproses pembayaran.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
              msg = xhr.responseJSON.message;
            }

            Swal.fire({
              title: 'Gagal',
              text: msg,
              icon: 'error'
            });
          }
        });
      }, 1200);
    });

    // Delete Tagihan Event
    $(document).on('click', '.btn-delete', function() {
      const id = $(this).attr('data-id');

      Swal.fire({
        title: "Hapus Tagihan Ini?",
        text: "Data tagihan akan dihapus permanen dari sistem!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Ya, Hapus!",
        cancelButtonText: "Batal"
      }).then((result) => {
        if (result.isConfirmed) {
          if (window.GlobalLoading) {
            GlobalLoading.show('Menghapus data tagihan...');
          }

          $.ajax({
            url: `{{ url('tagihan/destroy') }}/${id}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
              if (window.GlobalLoading) {
                GlobalLoading.hide();
              }

              if (response.success) {
                toastr.success(response.message || 'Tagihan berhasil dihapus!');
                table.ajax.reload(null, false);
              } else {
                Swal.fire("Gagal", response.message || "Gagal menghapus tagihan", "error");
              }
            },
            error: function(xhr) {
              if (window.GlobalLoading) {
                GlobalLoading.hide();
              }
              Swal.fire("Error", xhr.responseJSON?.message || "Terjadi kesalahan server", "error");
            }
          });
        }
      });
    });

    // Event Handler Tombol WhatsApp
    $(document).on('click', '.btn-wa-direct', function(e) {
      e.preventDefault();
      const id = $(this).attr('data-id');
      const allRows = table.rows().data().toArray();
      const rowData = allRows ? allRows.find(item => item.id == id) : null;
      if (rowData) {
        kirimPengingatWA(rowData);
      }
    });
  });

  // Fungsi Kirim Pesan Pengingat WhatsApp
  function kirimPengingatWA(row) {
    const rawWa = row.user_wa;
    if (!rawWa || rawWa === '-' || rawWa.trim() === '') {
      Swal.fire({
        icon: 'warning',
        title: 'No WhatsApp Belum Terdaftar',
        text: `Pelanggan ${row.user_nama} belum memiliki nomor WhatsApp di sistem.`
      });
      return;
    }

    let cleanWa = String(rawWa).replace(/[^0-9]/g, '');
    if (cleanWa.startsWith('0')) cleanWa = '62' + cleanWa.slice(1);

    const periodeStr = `${namaBulan[row.bulan] || row.bulan} ${row.tahun}`;
    const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.total);

    let statusNote = '';
    if (row.due_diff_days !== null && row.due_diff_days !== undefined) {
      if (row.due_diff_days < 0) {
        statusNote = `⚠️ *Pemberitahuan:* Tagihan ini telah *LEWAT JATUH TEMPO* selama ${Math.abs(row.due_diff_days)} hari. Mohon segera melunasi agar terhindar dari pemutusan sementara koneksi internet Anda.`;
      } else if (row.due_diff_days === 0) {
        statusNote = `⏰ *Pemberitahuan:* Hari ini adalah *TANGGAL JATUH TEMPO* pembayaran Anda. Mohon segera melakukan pembayaran.`;
      } else {
        statusNote = `Jatuh tempo pada *${row.tglJatuhTempo}* (tersisa ${row.due_diff_days} hari).`;
      }
    }

    const message = `Halo Kak *${row.user_nama}*,\n\n` +
      `Berikut adalah rincian tagihan internet *MRYES NET* Anda:\n\n` +
      `📄 *No. Invoice:* ${row.noTagihan}\n` +
      `📦 *Paket:* ${row.paket_nama || 'Internet Reguler'}\n` +
      `📅 *Periode:* ${periodeStr}\n` +
      `💰 *Total Tagihan:* *${totalFmt}*\n` +
      `⏰ *Jatuh Tempo:* ${row.tglJatuhTempo || '-'}\n\n` +
      `${statusNote}\n\n` +
      `Pembayaran dapat dititipkan langsung ke Petugas/Kolektor Lapangan resmi MRYES, atau via transfer bank/QRIS.\n` +
      `_Abaikan pesan ini apabila Anda telah melakukan pembayaran. Terima kasih atas kepercayaan Anda menggunakan layanan MRYES NET!_ 🙏`;

    window.open(`https://wa.me/${cleanWa}?text=${encodeURIComponent(message)}`, '_blank');
  }
</script>
@endsection

@section('content')

<!-- Header Card & Periode Filter -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body py-3 px-3 px-sm-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <div class="d-flex align-items-center gap-2">
          <h4 class="fw-bold mb-0 text-dark">
            <i class="mdi mdi-receipt-text-outline text-primary me-2"></i>Tagihan Aktif
          </h4>
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill small">Belum Lunas</span>
        </div>
        <p class="text-muted small mb-0 mt-1">Monitoring dan kasir penagihan tagihan pelanggan yang belum terbayar</p>
      </div>

      <!-- Right: Filters & Action -->
      <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex align-items-center gap-1 bg-light rounded-3 p-1 border">
          <span class="text-muted small px-2 fw-semibold"><i class="mdi mdi-filter-variant me-1 text-primary"></i>Periode:</span>
          <select id="filter-bulan" class="form-select form-select-sm border-0 bg-white shadow-xs fw-semibold" style="width: 130px;">
            <option value="">Semua Bulan</option>
            @php
              $namaBulanList = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
              ];
            @endphp
            @foreach($namaBulanList as $key => $nama)
              <option value="{{ $key }}" {{ $key == $currentMonth ? 'selected' : '' }}>{{ $nama }}</option>
            @endforeach
          </select>
          <select id="filter-tahun" class="form-select form-select-sm border-0 bg-white shadow-xs fw-semibold" style="width: 100px;">
            <option value="">Semua Thn</option>
            @if(isset($filterData['tahun']) && !empty($filterData['tahun']))
              @foreach($filterData['tahun'] as $tahun)
                <option value="{{ $tahun }}" {{ $tahun == $currentYear ? 'selected' : '' }}>{{ $tahun }}</option>
              @endforeach
            @else
              <option value="{{ $currentYear }}" selected>{{ $currentYear }}</option>
            @endif
          </select>
          <button type="button" class="btn btn-sm btn-icon btn-primary rounded-2 shadow-xs" id="btn-refresh-table" title="Refresh Data">
            <i class="mdi mdi-refresh"></i>
          </button>
        </div>

        <a href="{{ route('tagihan.lunas') }}" class="btn btn-sm btn-outline-success d-flex align-items-center gap-1 shadow-xs py-2">
          <i class="mdi mdi-check-decagram-outline"></i>
          <span>Riwayat Lunas</span>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- 4 Executive KPI Summary Cards (Compact & Responsif) -->
<div class="row g-2 mb-3" id="kpi-summary-row">
  <!-- Card 1: Total Tagihan Aktif -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #2563eb !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title text-muted d-block small mb-1 fw-medium">Total Tagihan</span>
          <div class="metric-value fs-5 fw-bold text-dark mb-0" id="total-count">0</div>
          <small class="metric-sub text-muted" style="font-size: 0.7rem;">Menunggu bayar</small>
        </div>
        <div class="metric-icon-box bg-primary-subtle text-primary border border-primary-subtle">
          <i class="mdi mdi-file-clock-outline"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Total Nominal Tertunda -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #ef4444 !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title text-muted d-block small mb-1 fw-medium">Belum Terbayar</span>
          <div class="metric-value fs-5 fw-bold text-danger mb-0" id="total-unpaid">Rp 0</div>
          <small class="metric-sub text-muted" style="font-size: 0.7rem;">Total piutang</small>
        </div>
        <div class="metric-icon-box bg-danger-subtle text-danger border border-danger-subtle">
          <i class="mdi mdi-cash-remove"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 3: Overdue / Lewat Jatuh Tempo -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #f59e0b !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title text-warning d-block small mb-1 fw-bold">Lewat Tempo</span>
          <div class="metric-value fs-5 fw-bold text-warning mb-0" id="overdue-count">0</div>
          <small class="metric-sub text-danger fw-semibold" id="overdue-total" style="font-size: 0.7rem;">Rp 0 macet</small>
        </div>
        <div class="metric-icon-box bg-warning-subtle text-warning border border-warning-subtle shadow-xs">
          <i class="mdi mdi-alert-circle-outline"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 4: Potensi Penerimaan Kas -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #10b981 !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title text-muted d-block small mb-1 fw-medium">Potensi Kas</span>
          <div class="metric-value fs-5 fw-bold text-success mb-0" id="potential-income">Rp 0</div>
          <small class="metric-sub text-muted" style="font-size: 0.7rem;">Estimasi masuk</small>
        </div>
        <div class="metric-icon-box bg-success-subtle text-success border border-success-subtle">
          <i class="mdi mdi-trending-up"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Main Table Card -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-bottom py-2 px-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <!-- Quick Filter Status Pills with Smooth Mobile Horizontal Scroll -->
      <div class="quick-filter-scroll-wrapper flex-grow-1">
        <div class="btn-group btn-group-sm shadow-xs" role="group" id="quick-filter-group">
          <button type="button" class="btn btn-outline-primary active" data-filter="">
            <i class="mdi mdi-view-list me-1"></i> Semua (<span id="pill-all">0</span>)
          </button>
          <button type="button" class="btn btn-outline-danger" data-filter="overdue">
            <i class="mdi mdi-alert-circle-outline me-1"></i> Lewat Tempo (<span id="pill-overdue">0</span>)
          </button>
          <button type="button" class="btn btn-outline-warning" data-filter="today">
            <i class="mdi mdi-clock-alert-outline me-1"></i> Hari Ini (<span id="pill-today">0</span>)
          </button>
          <button type="button" class="btn btn-outline-success" data-filter="upcoming">
            <i class="mdi mdi-calendar-check-outline me-1"></i> Belum Tempo
          </button>
        </div>
      </div>

      <div class="text-muted small d-none d-md-block">
        <i class="mdi mdi-information-outline me-1 text-primary"></i> Klik <span class="badge bg-success-subtle text-success border border-success-subtle py-1">Bayar</span> untuk melunasi
      </div>
    </div>
  </div>

  <div class="card-datatable table-responsive pt-0">
    <table id="table" class="datatables-basic table table-bordered table-hover align-middle mb-0 w-100">
      <thead class="table-light">
        <tr>
          <th style="width: 45px;" class="text-center d-none d-md-table-cell">NO</th>
          <th>PELANGGAN & INVOICE</th>
          <th class="d-none d-md-table-cell" style="width: 170px;">PAKET & PERIODE</th>
          <th class="d-none d-md-table-cell" style="width: 140px;">TOTAL TAGIHAN</th>
          <th class="d-none d-md-table-cell" style="width: 140px;">JATUH TEMPO</th>
          <th class="d-none d-md-table-cell text-center" style="width: 150px;">AKSI</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

<!-- Modal Bayar Modern Kasir POS (Compact & Ringan untuk HP) -->
<div class="modal fade" id="modalBayar" tabindex="-1" aria-labelledby="modalBayarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">

      <!-- Top Cashier Header -->
      <div class="p-3 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success p-1 rounded-circle shadow-xs">
              <i class="mdi mdi-cash-register fs-5 text-white"></i>
            </span>
            <div>
              <h6 class="modal-title fw-bold text-white mb-0" id="modalBayarLabel">Kasir Pembayaran</h6>
              <small class="text-white-50" style="font-size: 0.7rem;">Pencatatan Transaksi & Pelunasan</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Highlight Showcase: Total & Pelanggan Ringkas -->
        <div class="bg-white bg-opacity-10 rounded-3 p-2 p-sm-3 border border-white border-opacity-10 d-flex align-items-center justify-content-between gap-2">
          <div>
            <div class="text-uppercase text-white-50 fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.5px;">Total Tagihan</div>
            <div class="fs-3 fw-bolder text-warning lh-1 my-1" id="modal-total-display">Rp 0</div>
            <small class="text-white-50" style="font-size: 0.7rem;">
              Inv: <span class="text-white fw-semibold" id="modal-noTagihan">-</span>
            </small>
          </div>
          <div class="text-end border-start border-white border-opacity-10 ps-2 ps-sm-3">
            <div class="fw-bold text-white fs-6 text-truncate" style="max-width: 160px;" id="modal-namaPelanggan">-</div>
            <span class="badge bg-white bg-opacity-20 text-white border border-white border-opacity-25" style="font-size: 0.68rem;" id="modal-paket-badge">-</span>
            <div class="text-white-50 mt-1" style="font-size: 0.68rem;">
              Periode: <span class="text-white" id="modal-periode-display">-</span>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-body p-3 bg-light-subtle">
        <form id="form-bayar">
          <input type="hidden" id="tagihan_id" name="tagihan_id" value="">
          <!-- Step 1: Pilih Metode Bayar (Compact Row) -->
          <div class="mb-3">
            <label class="form-label fw-bold text-dark small mb-1">
              <i class="mdi mdi-wallet-outline text-primary me-1"></i>Metode Pembayaran <span class="text-danger">*</span>
            </label>
            <div class="row g-1 g-sm-2" id="metode-cards">
              <div class="col-3">
                <input type="radio" class="btn-check" name="metode_bayar_radio" id="metode_cod" value="cod" checked autocomplete="off">
                <label class="btn btn-outline-secondary w-100 p-2 d-flex flex-column align-items-center justify-content-center text-center rounded-3 metode-card" for="metode_cod" style="min-height: 56px;">
                  <i class="mdi mdi-cash-multiple fs-4 text-success"></i>
                  <span class="fw-bold d-block text-dark mt-1" style="font-size: 0.72rem;">Tunai</span>
                </label>
              </div>
              <div class="col-3">
                <input type="radio" class="btn-check" name="metode_bayar_radio" id="metode_transfer" value="transfer" autocomplete="off">
                <label class="btn btn-outline-secondary w-100 p-2 d-flex flex-column align-items-center justify-content-center text-center rounded-3 metode-card" for="metode_transfer" style="min-height: 56px;">
                  <i class="mdi mdi-bank-transfer fs-4 text-primary"></i>
                  <span class="fw-bold d-block text-dark mt-1" style="font-size: 0.72rem;">Transfer</span>
                </label>
              </div>
              <div class="col-3">
                <input type="radio" class="btn-check" name="metode_bayar_radio" id="metode_qris" value="qris" autocomplete="off">
                <label class="btn btn-outline-secondary w-100 p-2 d-flex flex-column align-items-center justify-content-center text-center rounded-3 metode-card" for="metode_qris" style="min-height: 56px;">
                  <i class="mdi mdi-qrcode-scan fs-4 text-info"></i>
                  <span class="fw-bold d-block text-dark mt-1" style="font-size: 0.72rem;">QRIS</span>
                </label>
              </div>
              <div class="col-3">
                <input type="radio" class="btn-check" name="metode_bayar_radio" id="metode_ewallet" value="ewallet" autocomplete="off">
                <label class="btn btn-outline-secondary w-100 p-2 d-flex flex-column align-items-center justify-content-center text-center rounded-3 metode-card" for="metode_ewallet" style="min-height: 56px;">
                  <i class="mdi mdi-cellphone-nfc fs-4 text-warning"></i>
                  <span class="fw-bold d-block text-dark mt-1" style="font-size: 0.72rem;">E-Wallet</span>
                </label>
              </div>
            </div>
            <input type="hidden" id="metode_bayar" name="metode_bayar" value="cod">
          </div>

          <!-- Step 2A: Kalkulator Kembalian Tunai (Muncul HANYA saat Tunai / COD) -->
          <div id="cash-calculator-box" class="card border border-success border-opacity-25 bg-success-subtle p-2 p-sm-3 rounded-3 mb-2">
            <div class="row align-items-center g-2">
              <div class="col-sm-6">
                <label for="cash-received" class="form-label fw-bold text-dark small mb-1">Uang Diterima (Rp)</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white fw-bold">Rp</span>
                  <input type="number" class="form-control form-control-sm fw-bold fs-6" id="cash-received" placeholder="0">
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-mode="exact">Uang Pas</button>
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-amount="50000">50rb</button>
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-amount="100000">100rb</button>
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-amount="200000">200rb</button>
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-amount="500000">500rb</button>
                </div>
              </div>
              <div class="col-sm-6 text-sm-end pt-1 pt-sm-0 border-sm-start border-success border-opacity-25 ps-sm-3">
                <div class="small text-muted fw-semibold" style="font-size: 0.72rem;">Uang Kembalian</div>
                <div class="fs-4 fw-bolder text-success" id="cash-change-display">Rp 0</div>
                <small class="text-muted d-block" id="cash-change-status" style="font-size: 0.7rem;">Uang pas</small>
              </div>
            </div>
          </div>

          <!-- Step 2B: Konfirmasi Non-Tunai (Transfer / QRIS / E-Wallet) - Otomatis Uang Pas & Kembalian Rp 0 -->
          <div id="nontunai-info-box" class="card border border-primary border-opacity-25 bg-primary-subtle p-2 p-sm-3 rounded-3 mb-2" style="display: none;">
            <div class="d-flex align-items-center justify-content-between gap-2">
              <div class="d-flex align-items-center gap-2">
                <div class="avatar avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                  <i class="mdi mdi-check-decagram fs-5" id="nontunai-icon"></i>
                </div>
                <div>
                  <span class="fw-bold text-dark d-block" style="font-size: 0.8rem;" id="nontunai-title">Pembayaran Transfer Bank</span>
                  <small class="text-muted d-block" style="font-size: 0.7rem;" id="nontunai-desc">Uang diterima otomatis pas sesuai tagihan (Kembalian Rp 0)</small>
                </div>
              </div>
              <div class="text-end flex-shrink-0">
                <span class="badge bg-primary fs-6 fw-bold shadow-xs" id="nontunai-amount">Rp 0</span>
              </div>
            </div>
          </div>

          <!-- Step 3: Keterangan Catatan Tambahan (Compact) -->
          <div class="mb-1">
            <label for="keterangan_bayar" class="form-label fw-semibold text-muted small mb-1" style="font-size: 0.72rem;">
              Catatan / Referensi (Opsional)
            </label>
            <input type="text" id="keterangan_bayar" name="keterangan" class="form-control form-control-sm"
              placeholder="Contoh: Titip ke tetangga / ref transfer">
          </div>
        </form>
      </div>

      <div class="modal-footer bg-white border-top p-2 px-3 d-flex align-items-center justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">
          <i class="mdi mdi-close me-1"></i>Batal
        </button>
        <button type="submit" form="form-bayar" class="btn btn-success px-3 py-2 shadow-sm fw-bold d-flex align-items-center gap-1" id="btn-proses-bayar">
          <i class="mdi mdi-check-circle fs-5"></i>
          <span>Konfirmasi Pelunasan</span>
        </button>
      </div>
    </div>
  </div>
</div>

@endsection
