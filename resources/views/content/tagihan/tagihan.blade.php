@extends('layouts/layoutMaster')

@section('title', 'Tagihan Aktif (Belum Lunas)')

@section('vendor-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
@endsection

@section('page-style')
@include('content.tagihan._style')
<style>
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

  /* Modal Kasir POS Custom Styling */
  .btn-quick-cash {
    font-size: 0.72rem !important;
    font-weight: 600 !important;
    padding: 2px 8px !important;
    border-radius: 6px !important;
    border: 1px solid #cbd5e1 !important;
    background-color: #ffffff !important;
    color: #334155 !important;
    transition: all 0.15s ease;
  }
  .btn-quick-cash:hover {
    background-color: #f1f5f9 !important;
    border-color: #94a3b8 !important;
    color: #0f172a !important;
  }
  #cash-calculator-box {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
  }
</style>
@endsection
@section('page-script')
<script>
  let table;
  let activeQuickFilter = '';
  let currentRawTotal = 0;
  let currentBillData = null;
  let currentPaymentMode = 'single'; // 'single' atau 'all'
  let currentTargetTotal = 0;
  let currentMultiTotal = 0;

  const namaBulan = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];

  // Escape teks dari database sebelum disisipkan ke HTML (cegah XSS dari nama/WA/paket)
  function esc(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Token unik per sesi kasir untuk mencegah pembayaran ganda
  let currentPaymentToken = null;
  function generatePaymentToken() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'pt-' + Date.now() + '-' + Math.random().toString(36).slice(2);
  }

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
          className: 'align-middle p-2 p-md-3 col-mobile-card',
          render: function(data, type, row) {
            const wa = row.user_wa ? esc(row.user_wa) : row.user_wa;
            data = esc(data);
            const noTagihanSafe = esc(row.noTagihan);
            const paketSafe = esc(row.paket_nama);
            const waButton = wa && wa !== '-'
              ? `<a href="javascript:void(0)" class="btn-wa-direct badge bg-success-subtle text-success border border-success-subtle text-decoration-none" data-id="${row.id}" title="Kirim Pesan WhatsApp"><i class="mdi mdi-whatsapp me-1"></i>${wa}</a>`
              : `<span class="badge bg-light text-muted border">No WA (-)</span>`;

            const sisaNominal = (row.sisa !== undefined && row.sisa !== null) ? row.sisa : row.total;
            const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(sisaNominal || 0);
            const cicilBadgeMobile = row.terbayar > 0
              ? `<span class="badge bg-warning-subtle text-warning border border-warning-subtle d-block mt-1" style="font-size: 0.65rem;"><i class="mdi mdi-clock-check-outline me-1"></i>Cicil Rp ${new Intl.NumberFormat('id-ID').format(row.terbayar)}</span>`
              : '';
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
                  <span class="badge bg-label-secondary font-monospace" style="font-size: 0.7rem;">${noTagihanSafe}</span>
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
                      <i class="mdi mdi-receipt-text-outline me-1"></i>${noTagihanSafe || '-'}
                    </small>
                  </div>
                  <div class="text-end flex-shrink-0">
                    <div class="fs-6 fw-bolder text-primary mb-0">${totalFmt}</div>
                    ${cicilBadgeMobile}
                    <div class="mt-1">${dueBadgeMobile}</div>
                  </div>
                </div>

                <!-- Baris 2: Paket, Periode & WA Pelanggan -->
                <div class="d-flex align-items-center justify-content-between gap-1 py-1 px-2 my-2 bg-light rounded-2 border" style="font-size: 0.72rem;">
                  <div class="text-truncate">
                    <i class="mdi mdi-speedometer me-1 text-primary"></i>
                    <span class="fw-semibold text-dark">${paketSafe || 'Paket Internet'}</span>
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

                  ${parseFloat(row.terbayar || 0) > 0 ? `
                  <!-- Tombol Lihat Struk Bukti Cicilan -->
                  <button type="button" class="btn btn-outline-primary btn-lihat-struk d-flex align-items-center justify-content-center p-2 shadow-xs" data-id="${row.id}" title="Lihat Struk / Bukti Cicilan" style="border-radius: 8px; width: 42px; height: 38px;">
                    <i class="mdi mdi-printer fs-5"></i>
                  </button>
                  ` : ''}

                  <button type="button" class="btn btn-outline-success btn-wa-direct d-flex align-items-center justify-content-center p-2 shadow-xs" data-id="${row.id}" title="Kirim Pengingat WhatsApp" style="border-radius: 8px; width: 42px; height: 38px;">
                    <i class="mdi mdi-whatsapp fs-5"></i>
                  </button>

                  ${parseFloat(row.terbayar || 0) > 0 ? '' : deleteBtnMobile}
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
                  <i class="mdi mdi-speedometer me-1"></i>${esc(data) || 'Paket Internet'}
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
            const sisaNominal = (row.sisa !== undefined && row.sisa !== null) ? row.sisa : data;
            const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(sisaNominal);
            let detailSub = '';
            if (row.terbayar > 0) {
              detailSub = `<small class="text-warning fw-semibold d-block" style="font-size: 0.72rem;"><i class="mdi mdi-clock-check-outline me-1"></i>Dicicil Rp ${new Intl.NumberFormat('id-ID').format(row.terbayar)}</small>`;
            } else if (row.diskon > 0) {
              detailSub = `<small class="text-success d-block" style="font-size: 0.72rem;"><i class="mdi mdi-tag-outline me-1"></i>Hemat Rp ${new Intl.NumberFormat('id-ID').format(row.diskon)}</small>`;
            } else if (row.ppn > 0) {
              detailSub = `<small class="text-muted d-block" style="font-size: 0.72rem;">Termasuk PPN</small>`;
            }
            return `
              <div>
                <div class="fw-bold text-danger fs-6">${sisaFmt}</div>
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

            const hasCicilan = parseFloat(row.terbayar || 0) > 0;
            const strukBtnDesktop = hasCicilan ? `
              <!-- Tombol Preview Struk Cicilan -->
              <button type="button" class="btn btn-sm btn-outline-primary btn-lihat-struk d-inline-flex align-items-center p-1" data-id="${data}" title="Lihat Struk / Bukti Cicilan">
                <i class="mdi mdi-printer fs-6"></i>
              </button>
            ` : '';

            return `
              <div class="d-inline-flex align-items-center gap-1">
                <!-- Tombol Bayar Langsung -->
                <button type="button" class="btn btn-sm btn-success btn-bayar d-inline-flex align-items-center gap-1 shadow-xs px-2 py-1" data-id="${data}" title="Bayar Sekarang">
                  <i class="mdi mdi-cash-check"></i>
                  <span class="fw-semibold">Bayar</span>
                </button>

                ${strukBtnDesktop}

                <!-- Tombol WhatsApp -->
                <button type="button" class="btn btn-sm btn-outline-success btn-wa-direct d-inline-flex align-items-center p-1" data-id="${data}" title="Kirim Pengingat WhatsApp">
                  <i class="mdi mdi-whatsapp fs-6"></i>
                </button>

                <!-- Dropdown Opsi -->
                <div class="dropdown">
                  <button type="button" class="btn btn-sm btn-icon btn-light rounded-circle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="mdi mdi-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow border py-1" style="min-width: 175px; border-radius: 8px;">
                    <li>
                      <a class="dropdown-item btn-bayar text-success py-1" href="javascript:void(0);" data-id="${data}">
                        <i class="mdi mdi-credit-card-outline me-2"></i> Kasir Pembayaran
                      </a>
                    </li>
                    ${hasCicilan ? `
                    <li>
                      <a class="dropdown-item btn-lihat-struk py-1 text-primary" href="javascript:void(0);" data-id="${data}">
                        <i class="mdi mdi-printer-outline me-2"></i> Bukti Cicilan / Struk
                      </a>
                    </li>
                    ` : ''}
                    <li>
                      <a class="dropdown-item btn-wa-direct py-1" href="javascript:void(0);" data-id="${data}">
                        <i class="mdi mdi-whatsapp me-2 text-success"></i> Ingatkan Pelanggan
                      </a>
                    </li>
                    ${hasCicilan ? '' : deleteBtn}
                  </ul>
                </div>
              </div>
            `;
          }
        }
      ],
      order: [[4, 'asc']], // Urutkan berdasarkan jatuh tempo terdekat
      dom:
        't' +
        '<"card-body py-2 px-3 border-top bg-light-subtle"<"row align-items-center g-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"p>>>',
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

    // Custom Search with Debounce
    let searchTimeout;
    $('#custom-search').on('keyup input', function() {
      clearTimeout(searchTimeout);
      const val = this.value;
      searchTimeout = setTimeout(() => {
        table.search(val).draw();
      }, 250);
    });

    // Custom Length Change
    $('#custom-length').on('change', function() {
      table.page.len(parseInt(this.value)).draw();
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
      $('#potential-income').text('Rp ' + new Intl.NumberFormat('id-ID').format(summary.cicilan_masuk || 0));

      // Pills badge
      $('#pill-all').text(totalCount);
      $('#pill-overdue').text(overdueCount);
      $('#pill-today').text(todayCount);
    }

    // Filter Quick Pills Event
    $('#quick-filter-group').on('click', '.btn-filter-pill', function() {
      $('#quick-filter-group .btn-filter-pill').removeClass('active');
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

      if (window.GlobalLoading) GlobalLoading.show('Memuat data kasir...');
      $.ajax({
        url: `{{ url('tagihan') }}/${id}`,
        type: 'GET',
        success: function(res) {
          if (window.GlobalLoading) GlobalLoading.hide();
          const billData = res.data || res;
          bukaModalKasir(id, billData);
        },
        error: function() {
          if (window.GlobalLoading) GlobalLoading.hide();
          Swal.fire('Error', 'Gagal memuat rincian kasir tagihan.', 'error');
        }
      });
    });

    function bukaModalKasir(id, billData) {
      currentBillData = billData;
      currentPaymentMode = 'single';
      currentPaymentToken = generatePaymentToken(); // token baru setiap kasir dibuka

      const sisaTagihan = parseFloat(billData.sisa !== undefined ? billData.sisa : billData.total) || 0;
      const totalAwal = parseFloat(billData.total) || 0;
      const terbayarAwal = parseFloat(billData.terbayar) || 0;
      currentTargetTotal = sisaTagihan;
      currentMultiTotal = parseFloat(billData.total_tunggakan) || sisaTagihan;

      const bulanNama = namaBulan[billData.bulan] || billData.bulan;
      const periodeStr = `${bulanNama} ${billData.tahun}`;

      // Populate Modal Info
      $('#modal-noTagihan').text(billData.noTagihan || '-');
      $('#modal-namaPelanggan').text(billData.user_nama || 'Pelanggan');
      $('#modal-paket-badge').text(billData.paket_nama || 'Paket Internet');
      $('#modal-periode-display').text(periodeStr);

      // Info cicilan awal jika sudah pernah dicicil
      if (terbayarAwal > 0) {
        $('#modal-cicilan-info').show();
        $('#modal-cicilan-terbayar').text('Rp ' + new Intl.NumberFormat('id-ID').format(terbayarAwal));
        $('#modal-total-label').text('Sisa Tagihan Ini');
      } else {
        $('#modal-cicilan-info').hide();
        $('#modal-total-label').text('Total Tagihan');
      }

      // Handle Multi-Tunggakan Alert Box
      if (billData.unpaid_count && billData.unpaid_count > 1) {
        $('#box-multi-tunggakan').show();
        $('#multi-count').text(billData.unpaid_count);
        $('#multi-total-tunggakan').text('Rp ' + new Intl.NumberFormat('id-ID').format(currentMultiTotal));
        $('#txt-sisa-ini').text('Rp ' + new Intl.NumberFormat('id-ID').format(currentTargetTotal));
        $('#txt-total-semua').text('Rp ' + new Intl.NumberFormat('id-ID').format(currentMultiTotal));
        setPaymentMode('single');
      } else {
        $('#box-multi-tunggakan').hide();
        setPaymentMode('single');
      }

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

    // Fungsi Ganti Mode Pembayaran Kasir (Tagihan Ini vs Lunasi Semua)
    function setPaymentMode(mode) {
      currentPaymentMode = mode;
      const targetAmount = (mode === 'all') ? currentMultiTotal : currentTargetTotal;

      if (mode === 'all') {
        $('#btn-mode-bayar-semua').removeClass('btn-outline-warning').addClass('btn-warning shadow-xs active');
        $('#btn-mode-tagihan-ini').removeClass('btn-warning shadow-xs active').addClass('btn-outline-warning');
        $('#modal-total-label').text('Total Semua Tunggakan (' + (currentBillData ? currentBillData.unpaid_count : 0) + ' Periode)');
        $('#modal-total-display').text('Rp ' + new Intl.NumberFormat('id-ID').format(currentMultiTotal));
      } else {
        $('#btn-mode-tagihan-ini').removeClass('btn-outline-warning').addClass('btn-warning shadow-xs active');
        $('#btn-mode-bayar-semua').removeClass('btn-warning shadow-xs active').addClass('btn-outline-warning');
        $('#modal-total-label').text((currentBillData && currentBillData.terbayar > 0) ? 'Sisa Tagihan Ini' : 'Total Tagihan Ini');
        $('#modal-total-display').text('Rp ' + new Intl.NumberFormat('id-ID').format(currentTargetTotal));
      }

      $('#cash-received').val(targetAmount);
      hitungKembalian();
    }

    $(document).on('click', '#btn-mode-tagihan-ini', function() {
      setPaymentMode('single');
    });

    $(document).on('click', '#btn-mode-bayar-semua', function() {
      setPaymentMode('all');
    });

    // Fungsi Ganti Metode Pembayaran Kasir
    function pilihMetodeBayar(selected) {
      $('#metode_bayar').val(selected);
      $('input[name="metode_bayar_radio"]').prop('checked', false);
      $(`#metode_${selected}`).prop('checked', true);

      // Highlight visual card aktif
      $('.metode-card').removeClass('active-metode');
      $(`label[for="metode_${selected}"]`).addClass('active-metode');

      // Update placeholder catatan berdasarkan metode
      if (selected === 'transfer') {
        $('#keterangan_bayar').attr('placeholder', 'No. Ref transfer / An. Rekening pengirim (opsional)');
      } else if (selected === 'qris') {
        $('#keterangan_bayar').attr('placeholder', 'NMID / Ref transaksi QRIS (opsional)');
      } else if (selected === 'ewallet') {
        $('#keterangan_bayar').attr('placeholder', 'Nama E-Wallet & No. HP pengirim (opsional)');
      } else {
        $('#keterangan_bayar').attr('placeholder', 'Contoh: Titip ke tetangga / bayar tunai di lokasi');
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

    // Otomatis Blok / Seleksi Teks saat Kolom Uang Diterima Diklik / Difokuskan
    $(document).on('focus click', '#cash-received', function() {
      const input = this;
      setTimeout(() => {
        input.select();
      }, 50);
    });

    // Kalkulator Kembalian & Mode Cicilan Realtime
    $(document).on('input', '#cash-received', function() {
      hitungKembalian();
    });

    function hitungKembalian() {
      const received = parseFloat($('#cash-received').val()) || 0;
      const targetAmount = (currentPaymentMode === 'all') ? currentMultiTotal : currentTargetTotal;
      const diff = received - targetAmount;
      const btnProses = $('#btn-proses-bayar');

      if (received === 0) {
        $('#cash-change-display').text('Rp 0').removeClass('text-danger text-warning').addClass('text-success');
        $('#cash-change-status').html('<span class="text-muted small">Nominal belum diisi</span>');
        $('#cash-cicilan-alert').slideUp(150);
        btnProses.removeClass('btn-warning').addClass('btn-success')
          .html('<i class="mdi mdi-check-circle fs-5"></i><span>Konfirmasi Pelunasan</span>');
      } else if (diff < 0) {
        // Kurang -> Pembayaran Cicilan
        const kurang = Math.abs(diff);
        const kurangFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(kurang);
        const bayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(received);

        $('#cash-change-display').text('- ' + kurangFmt).removeClass('text-success').addClass('text-danger');
        $('#cash-change-status').html('<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0.5"><i class="mdi mdi-clock-outline me-1"></i>Cicilan Bertahap</span>');

        $('#cash-cicilan-sisa-badge').text('Sisa: ' + kurangFmt);
        $('#cash-cicilan-alert').slideDown(150);

        btnProses.removeClass('btn-success').addClass('btn-warning')
          .html(`<i class="mdi mdi-cash-plus fs-5"></i><span>Terima Cicilan (${bayarFmt})</span>`);
      } else if (diff === 0) {
        // Uang Pas
        $('#cash-change-display').text('Rp 0').removeClass('text-danger text-warning').addClass('text-success');
        $('#cash-change-status').html('<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5"><i class="mdi mdi-check me-1"></i>Uang Pas</span>');
        $('#cash-cicilan-alert').slideUp(150);
        btnProses.removeClass('btn-warning').addClass('btn-success')
          .html('<i class="mdi mdi-check-circle fs-5"></i><span>Konfirmasi Pelunasan</span>');
      } else {
        // Lebih -> Ada Kembalian
        const kembalianFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(diff);
        $('#cash-change-display').text(kembalianFmt).removeClass('text-danger text-warning').addClass('text-success');
        $('#cash-change-status').html('<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5"><i class="mdi mdi-hand-coin me-1"></i>Kembalikan ke Pelanggan</span>');
        $('#cash-cicilan-alert').slideUp(150);
        btnProses.removeClass('btn-warning').addClass('btn-success')
          .html('<i class="mdi mdi-check-circle fs-5"></i><span>Konfirmasi Pelunasan</span>');
      }
    }

    // Tombol Uang Cepat (Quick Cash Buttons)
    $(document).on('click', '.btn-quick-cash', function() {
      const mode = $(this).data('mode');
      const targetAmount = (currentPaymentMode === 'all') ? currentMultiTotal : currentTargetTotal;
      if (mode === 'exact') {
        $('#cash-received').val(targetAmount);
      } else {
        const amount = parseFloat($(this).data('amount')) || 0;
        $('#cash-received').val(amount);
      }
      hitungKembalian();
    });

    // Form Submit Pembayaran
    let isSubmittingPayment = false;
    $('#form-bayar').on('submit', function(e) {
      e.preventDefault();

      // Cegah klik/enter ganda saat request masih berjalan
      if (isSubmittingPayment) return;

      const tagihanId = $('#tagihan_id').val() || $(this).data('tagihan-id') || $(this).attr('data-tagihan-id');
      const metodeBayar = $('#metode_bayar').val();
      const keterangan = $('#keterangan_bayar').val();
      const received = parseFloat($('#cash-received').val()) || 0;

      if (!metodeBayar) {
        Swal.fire({
          title: 'Metode Pembayaran',
          text: 'Silakan pilih metode pembayaran terlebih dahulu!',
          icon: 'warning'
        });
        return;
      }

      if (received <= 0) {
        Swal.fire({
          title: 'Nominal Tidak Valid',
          text: 'Nominal pembayaran yang diterima harus lebih besar dari Rp 0!',
          icon: 'warning'
        });
        return;
      }

      isSubmittingPayment = true;
      $('#btn-proses-bayar').prop('disabled', true);

      const modalEl = document.getElementById('modalBayar');
      if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      } else {
        $('#modalBayar').modal('hide');
      }

      const paymentSteps = [
        { progress: 20, text: 'Memvalidasi data transaksi kasir...', delay: 250 },
        { progress: 55, text: 'Mencatat transaksi & audit trail...', delay: 350 },
        { progress: 90, text: 'Mengupdate status tagihan...', delay: 300 },
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
            keterangan: keterangan,
            nominal_bayar: received,
            uang_diterima: received,
            bayar_semua: (currentPaymentMode === 'all' ? 1 : 0),
            request_token: currentPaymentToken
          },
          complete: function() {
            isSubmittingPayment = false;
            $('#btn-proses-bayar').prop('disabled', false);
          },
          success: function(response) {
            if (window.GlobalLoading) {
              GlobalLoading.hide();
            }

            if (response.success) {
              const resData = response.data || {};
              const isCicilan = (resData.mode === 'cicilan');
              const isBayarSemua = (resData.mode === 'bayar_semua');

              let titleHtml = 'Pembayaran Berhasil!';
              let badgeHtml = `<span class="badge bg-success">Status: LUNAS (${metodeBayar.toUpperCase()})</span>`;
              let amountHtml = $('#modal-total-display').text();

              if (isCicilan) {
                titleHtml = 'Pembayaran Cicilan Diterima!';
                const nominalBayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(resData.alokasi_bayar || received);
                const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(resData.sisa || 0);
                amountHtml = `<span class="text-warning">${nominalBayarFmt}</span>`;
                badgeHtml = `
                  <span class="badge bg-warning text-dark mb-1">Status: CICILAN (${metodeBayar.toUpperCase()})</span>
                  <div class="small text-danger fw-bold mt-1">Sisa Tagihan: ${sisaFmt}</div>
                `;
              } else if (isBayarSemua) {
                titleHtml = 'Pelunasan Multi-Tunggakan Berhasil!';
                const totalBayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(resData.total_dibayar || received);
                amountHtml = `<span class="text-success">${totalBayarFmt}</span>`;
                const stText = resData.is_lunas ? 'LUNAS SEMUA' : 'SEBAGIAN LUNAS';
                badgeHtml = `
                  <span class="badge bg-success mb-1">Status: ${stText} (${metodeBayar.toUpperCase()})</span>
                  <div class="small text-muted mt-1">${resData.tagihan_lunas_count || 0} Periode Tagihan Dilunasi</div>
                `;
              }

              const kembalianVal = parseFloat(resData.kembalian || 0);
              const kembalianHtml = kembalianVal > 0
                ? `<div class="alert alert-primary py-2 px-3 mt-3 mb-0 small fw-semibold"><i class="mdi mdi-hand-coin me-1"></i>Kembalian ke pelanggan: Rp ${new Intl.NumberFormat('id-ID').format(kembalianVal)}</div>`
                : '';

              Swal.fire({
                title: titleHtml,
                html: `
                  <div class="text-center p-2">
                    <div class="avatar avatar-lg ${isCicilan ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success'} mx-auto mb-3" style="width: 54px; height: 54px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                      <i class="mdi ${isCicilan ? 'mdi-clock-check-outline' : 'mdi-check-decagram'} fs-2"></i>
                    </div>
                    <h5 class="fw-bold mb-1">${esc($('#modal-namaPelanggan').text())}</h5>
                    <p class="text-muted small mb-2">Invoice: ${esc($('#modal-noTagihan').text())}</p>
                    <div class="fs-4 fw-bold mb-2">${amountHtml}</div>
                    ${badgeHtml}
                    ${kembalianHtml}
                  </div>
                `,
                icon: null,
                showCancelButton: true,
                confirmButtonText: '<i class="mdi mdi-whatsapp me-1"></i> Kirim Kuitansi WA',
                cancelButtonText: '<i class="mdi mdi-check me-1"></i> Selesai',
                buttonsStyling: false,
                customClass: {
                  confirmButton: "btn btn-success px-3 me-2",
                  cancelButton: "btn btn-outline-secondary px-3"
                }
              }).then((result) => {
                if (result.isConfirmed) {
                  kirimKuitansiSmartWA(currentBillData, resData, metodeBayar);
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
      }, 300);
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
    const terbayar = parseFloat(row.terbayar) || 0;
    const sisa = (row.sisa !== undefined && row.sisa !== null) ? parseFloat(row.sisa) : Math.max(0, parseFloat(row.total || 0) - terbayar);
    const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(sisa);
    const terbayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(terbayar);

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

    let nominalBlock = `💰 *Total Tagihan:* *${totalFmt}*\n`;
    if (terbayar > 0) {
      nominalBlock = `💵 *Total Tagihan:* ${totalFmt}\n` +
        `💸 *Sudah Dicicil:* ${terbayarFmt}\n` +
        `⏳ *Sisa Tagihan:* *${sisaFmt}*\n`;
    }

    const message = `Halo Kak *${row.user_nama}*,\n\n` +
      `Berikut adalah rincian tagihan internet *MRYES NET* Anda:\n\n` +
      `📄 *No. Invoice:* ${row.noTagihan}\n` +
      `📦 *Paket:* ${row.paket_nama || 'Internet Reguler'}\n` +
      `📅 *Periode:* ${periodeStr}\n` +
      nominalBlock +
      `⏰ *Jatuh Tempo:* ${row.tglJatuhTempo || '-'}\n\n` +
      `${statusNote}\n\n` +
      `Pembayaran dapat dititipkan langsung ke Petugas/Kolektor Lapangan resmi MRYES, atau via transfer bank/QRIS.\n` +
      `_Abaikan pesan ini apabila Anda telah melakukan pembayaran. Terima kasih atas kepercayaan Anda menggunakan layanan MRYES NET!_ 🙏`;

    window.open(`https://wa.me/${cleanWa}?text=${encodeURIComponent(message)}`, '_blank');
  }

  // Fungsi Kirim Kuitansi WhatsApp Pintar (Lunas / Cicilan / Multi-Tunggakan)
  function kirimKuitansiSmartWA(bill, paymentRes, metode) {
    if (!bill) return;
    const rawWa = bill.user_wa;
    if (!rawWa || rawWa === '-' || rawWa.trim() === '') {
      Swal.fire({
        icon: 'info',
        title: 'Nomor WhatsApp Tidak Ditemukan',
        text: `Pelanggan "${bill.user_nama}" belum memiliki nomor WhatsApp di sistem.`,
        confirmButtonText: 'OK',
        customClass: { confirmButton: 'btn btn-primary' }
      });
      return;
    }

    let cleanWa = String(rawWa).replace(/[^0-9]/g, '');
    if (cleanWa.startsWith('0')) cleanWa = '62' + cleanWa.slice(1);

    const bulanNama = namaBulan[bill.bulan] || bill.bulan;
    const periodeStr = `${bulanNama} ${bill.tahun}`;
    const metodeStr = (metode || 'Tunai').toUpperCase();
    const waktuStr = (typeof moment !== 'undefined') ? moment().format('DD-MM-YYYY HH:mm') : new Date().toLocaleString('id-ID');

    let message = '';

    if (paymentRes && paymentRes.mode === 'cicilan') {
      const alokasiFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(paymentRes.alokasi_bayar || 0);
      const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(paymentRes.sisa || 0);
      const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(bill.total || 0);

      message = `Halo Kak *${bill.user_nama}*,\n\n` +
        `Terima kasih atas pembayaran cicilan tagihan internet *MRYES NET*. Berikut rincian tanda terima pembayaran Anda:\n\n` +
        `📄 *No. Invoice:* ${bill.noTagihan}\n` +
        `📦 *Paket:* ${bill.paket_nama || 'Internet'}\n` +
        `📅 *Periode:* ${periodeStr}\n` +
        `💵 *Total Tagihan:* ${totalFmt}\n` +
        (paymentRes.kumulatif
          ? `💰 *Total Sudah Dibayar:* *${alokasiFmt}*\n`
          : `💰 *Nominal Cicilan Masuk:* *${alokasiFmt}*\n`) +
        `⏳ *Sisa Tagihan:* *${sisaFmt}*\n` +
        `💳 *Metode Pembayaran:* ${metodeStr}\n` +
        `🗓️ *Waktu Bayar:* ${waktuStr}\n` +
        `⚠️ *Status:* *BELUM LUNAS (TERCATAT SEBAGAI CICILAN)*\n\n` +
        `Mohon sisa tagihan dapat diselesaikan sebelum tanggal jatuh tempo. Terima kasih! 🙏`;
    } else if (paymentRes && paymentRes.mode === 'bayar_semua') {
      const totalBayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(paymentRes.total_dibayar || 0);
      const sisaTunggakanFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(paymentRes.sisa_tunggakan || 0);

      let itemsRincian = '';
      if (paymentRes.items && paymentRes.items.length > 0) {
        itemsRincian = paymentRes.items.map(item => {
          const bNama = namaBulan[item.bulan] || item.bulan;
          const alokFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(item.alokasi);
          const stLabel = item.is_lunas ? 'LUNAS' : `CICILAN (Sisa Rp ${new Intl.NumberFormat('id-ID').format(item.sisa)})`;
          return `  • Periode ${bNama} ${item.tahun}: ${alokFmt} (${stLabel})`;
        }).join('\n');
      }

      message = `Halo Kak *${bill.user_nama}*,\n\n` +
        `Terima kasih atas pembayaran akumulasi tagihan internet *MRYES NET*. Berikut rincian tanda terima pelunasan Anda:\n\n` +
        `👤 *Nama Pelanggan:* ${bill.user_nama}\n` +
        `📋 *Rincian Tagihan:*\n${itemsRincian}\n\n` +
        `💰 *Total Pembayaran Masuk:* *${totalBayarFmt}*\n` +
        `⏳ *Sisa Tunggakan:* *${sisaTunggakanFmt}*\n` +
        `💳 *Metode Pembayaran:* ${metodeStr}\n` +
        `🗓️ *Waktu Bayar:* ${waktuStr}\n` +
        `✅ *Status:* *${paymentRes.is_lunas ? 'LUNAS SEMUA' : 'SEBAGIAN TUNGGAKAN LUNAS'}*\n\n` +
        `Terima kasih telah setia menggunakan layanan internet cepat *MRYES NET*. 🙏`;
    } else {
      const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(bill.total || 0);

      message = `Halo Kak *${bill.user_nama}*,\n\n` +
        `Terima kasih atas pembayaran tagihan internet *MRYES NET*. Berikut adalah bukti kuitansi pelunasan resmi:\n\n` +
        `📄 *No. Invoice:* ${bill.noTagihan}\n` +
        `📦 *Paket:* ${bill.paket_nama || 'Internet'}\n` +
        `📅 *Periode:* ${periodeStr}\n` +
        `💰 *Total Lunas:* *${totalFmt}*\n` +
        `💳 *Metode Pembayaran:* ${metodeStr}\n` +
        `🗓️ *Waktu Bayar:* ${waktuStr}\n` +
        `✅ *Status:* *LUNAS (TERVERIFIKASI)*\n\n` +
        `Terima kasih telah setia menggunakan layanan internet cepat *MRYES NET*. Jika ada kendala koneksi atau pertanyaan, silakan hubungi customer service kami. 🙏`;
    }

    window.open(`https://wa.me/${cleanWa}?text=${encodeURIComponent(message)}`, '_blank');
  }

  // Helper untuk mengambil Row Data dari DataTables
  function getRowDataById(id) {
    const allRows = table.rows().data().toArray();
    if (allRows && allRows.length > 0) {
      const found = allRows.find(item => item.id == id);
      if (found) return found;
    }
    return null;
  }

  // Event Handler: Buka Modal Struk / Cetak Kuitansi dari Tagihan Aktif
  $(document).on('click', '.btn-lihat-struk', function(e) {
    e.preventDefault();
    const id = $(this).attr('data-id');
    const row = getRowDataById(id);
    if (!row) {
      Swal.fire('Perhatian', 'Data tagihan tidak ditemukan.', 'warning');
      return;
    }

    currentBillData = row;
    const bulanNama = namaBulan[row.bulan] || row.bulan;
    const periodeStr = `${bulanNama} ${row.tahun}`;
    const hargaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.harga || 0);
    const ppnFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.ppn || 0);
    const diskonFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.diskon || 0);
    const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.total || 0);
    const terbayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.terbayar || 0);
    const sisaVal = (row.sisa !== undefined && row.sisa !== null) ? row.sisa : Math.max(0, (row.total || 0) - (row.terbayar || 0));
    const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(sisaVal);

    const isCicilan = (parseFloat(row.terbayar || 0) > 0);

    let tglBayarStr = row.tglBayar || '-';
    if (row.tglBayar && moment(row.tglBayar).isValid()) {
      tglBayarStr = moment(row.tglBayar).format('DD/MM/YYYY HH:mm');
    }

    $('#struk-noTagihan').text(row.noTagihan || '-');
    $('#struk-namaPelanggan').text(row.user_nama || '-');
    $('#struk-paket').text(row.paket_nama || 'Paket Internet');
    $('#struk-periode').text(periodeStr);
    $('#struk-tglBayar').text(tglBayarStr);
    $('#struk-metode').text((row.metode || 'Tunai').toUpperCase());
    $('#struk-kasir').text(row.prosesNama || '-');

    const $stamp = $('#struk-stamp');
    $stamp.removeClass('receipt-stamp-paid receipt-stamp-cicilan receipt-stamp-gratis');

    if (isCicilan) {
      $stamp.addClass('receipt-stamp-cicilan').text('CICILAN');
      $('#struk-modal-title').text('Tanda Terima Cicilan');
      $('#struk-rincian-lunas').hide();
      $('#struk-rincian-cicilan').show();
      $('#struk-label-total').text('TOTAL DITERIMA:');
      $('#struk-total').removeClass('text-success text-info').addClass('text-warning').text(terbayarFmt);

      $('#struk-cicil-total').text(totalFmt);
      $('#struk-cicil-terbayar').text(terbayarFmt);
      $('#struk-cicil-sisa').text(sisaFmt);
    } else {
      $stamp.addClass('receipt-stamp-paid').text('LUNAS');
      $('#struk-modal-title').text('Kuitansi Pembayaran');
      $('#struk-rincian-lunas').show();
      $('#struk-rincian-cicilan').hide();
      $('#struk-harga').text(hargaFmt);
      $('#struk-ppn').text(ppnFmt);
      $('#struk-diskon').text(diskonFmt);
      $('#struk-label-total').text('TOTAL DIBAYAR:');
      $('#struk-total').removeClass('text-warning text-info').addClass('text-success').text(totalFmt);
    }

    // Riwayat Transaksi Bertahap
    let trxList = [];
    if (typeof row.transaksi_list === 'string') {
      try { trxList = JSON.parse(row.transaksi_list); } catch (e) { trxList = []; }
    } else if (Array.isArray(row.transaksi_list)) {
      trxList = row.transaksi_list;
    }

    if (trxList && trxList.length > 0) {
      $('#struk-riwayat-wrapper').show();
      $('#struk-riwayat-count').text(`${trxList.length} transaksi`);
      let listHtml = '';
      trxList.forEach((t, idx) => {
        let tglStr = t.tglBayar || '-';
        if (t.tglBayar && moment(t.tglBayar).isValid()) {
          tglStr = moment(t.tglBayar).format('DD/MM/YY HH:mm');
        }
        const nomFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(t.total || 0);
        listHtml += `
          <div class="d-flex align-items-center justify-content-between py-1 ${idx > 0 ? 'border-top border-light' : ''}">
            <div>
              <span class="badge bg-secondary-subtle text-dark me-1 fw-bold" style="font-size: 0.65rem;">#${idx + 1}</span>
              <span class="text-muted">${tglStr}</span>
              <span class="badge bg-white text-secondary border ms-1" style="font-size: 0.65rem;">${(t.metode || 'Tunai').toUpperCase()}</span>
              ${t.prosesNama ? `<span class="text-muted ms-1" style="font-size: 0.68rem;">(${esc(t.prosesNama)})</span>` : ''}
            </div>
            <div class="fw-bold text-warning">${nomFmt}</div>
          </div>
        `;
      });
      $('#struk-riwayat-list').html(listHtml);
    } else {
      $('#struk-riwayat-wrapper').hide();
    }

    const modalElement = document.getElementById('modalStruk');
    if (modalElement) {
      const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
      modalInstance.show();
    }
  });

  // Tombol Cetak Struk di Dalam Modal
  $(document).on('click', '#btn-cetak-struk-action', function() {
    window.print();
  });

  // Tombol Kirim WA dari dalam Modal Struk
  $(document).on('click', '#btn-wa-struk-action', function() {
    if (currentBillData) {
      kirimKuitansiSmartWA(currentBillData, {
        mode: 'cicilan',
        kumulatif: true, // nominal = total semua cicilan yang sudah masuk, bukan 1 transaksi
        alokasi_bayar: currentBillData.terbayar,
        sisa: currentBillData.sisa
      }, currentBillData.metode);
    }
  });
</script>
@endsection

@section('content')
@php
  $configData = Helper::appClasses();
@endphp

<!-- Header Card & Periode Filter (Compact & Rapi) -->
<div class="card border-0 shadow-sm mb-2">
  <div class="card-body py-2 px-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <div class="d-flex align-items-center gap-2">
          <h5 class="fw-bold mb-0 text-dark">
            <i class="mdi mdi-receipt-text-outline text-primary me-1"></i>Tagihan Aktif
          </h5>
          <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded-pill" style="font-size: 0.7rem;">Belum Lunas</span>
        </div>
        <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.75rem;">Monitoring dan kasir penagihan tagihan pelanggan yang belum terbayar</p>
      </div>

      <!-- Right: Filters & Action -->
      <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex align-items-center gap-1 bg-light rounded-3 p-1 border">
          <span class="text-muted small px-1 fw-semibold" style="font-size: 0.72rem;"><i class="mdi mdi-filter-variant me-1 text-primary"></i>Periode:</span>
          <select id="filter-bulan" class="form-select form-select-sm border-0 bg-white shadow-xs fw-semibold" style="width: 140px; font-size: 0.78rem;">
            <option value="">Semua Bulan</option>
            @php
              $namaBulanList = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
              ];
            @endphp
            @foreach($namaBulanList as $key => $nama)
              {{-- Default "Semua Bulan": tunggakan bulan-bulan sebelumnya harus tetap terlihat oleh kolektor --}}
              <option value="{{ $key }}">{{ $nama }}</option>
            @endforeach
          </select>
          <select id="filter-tahun" class="form-select form-select-sm border-0 bg-white shadow-xs fw-semibold" style="width: 115px; font-size: 0.78rem;">
            <option value="">Semua Thn</option>
            @if(isset($filterData['tahun']) && !empty($filterData['tahun']))
              @foreach($filterData['tahun'] as $tahun)
                <option value="{{ $tahun }}">{{ $tahun }}</option>
              @endforeach
            @else
              <option value="{{ $currentYear }}">{{ $currentYear }}</option>
            @endif
          </select>
          <button type="button" class="btn btn-sm btn-icon btn-primary rounded-2 shadow-xs" id="btn-refresh-table" title="Refresh Data" style="width: 30px; height: 30px;">
            <i class="mdi mdi-refresh"></i>
          </button>
          <button type="button" class="btn btn-sm btn-icon btn-outline-secondary rounded-2 shadow-xs" onclick="setAppTheme('{{ (($configData['style'] ?? 'light') === 'dark') ? 'light' : 'dark' }}')" title="Ganti Mode Tema (Light/Dark)" style="width: 30px; height: 30px;">
            <i class="mdi {{ (($configData['style'] ?? 'light') === 'dark') ? 'mdi-weather-night text-info' : 'mdi-weather-sunny text-warning' }}"></i>
          </button>
        </div>

        <a href="{{ route('tagihan.lunas') }}" class="btn btn-sm btn-outline-success d-flex align-items-center gap-1 shadow-xs py-1 px-2.5" style="font-size: 0.8rem;">
          <i class="mdi mdi-check-decagram-outline"></i>
          <span>Riwayat Lunas</span>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- 4 Executive KPI Summary Cards (Compact & Rapi) -->
<div class="row g-2 mb-2" id="kpi-summary-row">
  <!-- Card 1: Total Tagihan Aktif -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #2563eb !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title d-block">Total Tagihan</span>
          <div class="metric-value text-dark mb-0" id="total-count">0</div>
          <small class="metric-sub text-muted">Menunggu bayar</small>
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
          <span class="metric-title d-block">Belum Terbayar</span>
          <div class="metric-value text-danger mb-0" id="total-unpaid">Rp 0</div>
          <small class="metric-sub text-muted">Total piutang</small>
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
          <span class="metric-title text-warning d-block">Lewat Tempo</span>
          <div class="metric-value text-warning mb-0" id="overdue-count">0</div>
          <small class="metric-sub text-danger fw-semibold" id="overdue-total">Rp 0 macet</small>
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
          <span class="metric-title d-block">Cicilan Masuk</span>
          <div class="metric-value text-success mb-0" id="potential-income">Rp 0</div>
          <small class="metric-sub text-muted">Dari tagihan belum lunas</small>
        </div>
        <div class="metric-icon-box bg-success-subtle text-success border border-success-subtle">
          <i class="mdi mdi-trending-up"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Main Table Card (Toolbar Terpadu & Rapi) -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-bottom py-2 px-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <!-- Left: Sleek Segmented Filter Tabs -->
      <div class="quick-filter-scroll-wrapper">
        <div class="filter-segmented-control shadow-xs" id="quick-filter-group">
          <button type="button" class="btn btn-filter-pill active" data-filter="">
            <i class="mdi mdi-view-list me-1"></i>Semua (<span id="pill-all">0</span>)
          </button>
          <button type="button" class="btn btn-filter-pill text-danger" data-filter="overdue">
            <i class="mdi mdi-alert-circle-outline me-1"></i>Lewat Tempo (<span id="pill-overdue">0</span>)
          </button>
          <button type="button" class="btn btn-filter-pill text-warning" data-filter="today">
            <i class="mdi mdi-clock-alert-outline me-1"></i>Hari Ini (<span id="pill-today">0</span>)
          </button>
          <button type="button" class="btn btn-filter-pill text-success" data-filter="upcoming">
            <i class="mdi mdi-calendar-check-outline me-1"></i>Belum Tempo
          </button>
        </div>
      </div>

      <!-- Right: Integrated Search Box & Length Selector -->
      <div class="d-flex align-items-center gap-2 ms-auto">
        <div class="input-group input-group-sm search-input-group shadow-xs" style="width: 230px;">
          <span class="input-group-text bg-white border-0 py-1 pe-1 text-muted">
            <i class="mdi mdi-magnify fs-6"></i>
          </span>
          <input type="text" id="custom-search" class="form-control form-control-sm border-0 ps-1" placeholder="Cari nama, invoice, paket..." style="font-size: 0.78rem;">
        </div>

        <select id="custom-length" class="form-select form-select-sm text-secondary border shadow-xs fw-semibold" style="width: 72px; font-size: 0.78rem;" title="Jumlah data per halaman">
          <option value="10">10</option>
          <option value="20" selected>20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
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
        <div class="rounded-3 p-2.5 p-sm-3 d-flex align-items-center justify-content-between gap-2 shadow-xs" style="background: rgba(255, 255, 255, 0.08) !important; border: 1px solid rgba(255, 255, 255, 0.15) !important;">
          <div>
            <div class="text-uppercase fw-semibold" style="font-size: 0.68rem; letter-spacing: 0.5px; color: rgba(255, 255, 255, 0.7) !important;" id="modal-total-label">Total Tagihan</div>
            <div class="fs-3 fw-bolder text-warning lh-1 my-1" id="modal-total-display">Rp 0</div>
            <div id="modal-cicilan-info" style="display: none;" class="mt-1">
              <span class="badge bg-warning text-dark border border-warning" style="font-size: 0.65rem;">
                <i class="mdi mdi-clock-check-outline me-1"></i>Sudah Dicicil: <span id="modal-cicilan-terbayar">Rp 0</span>
              </span>
            </div>
            <small style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.7) !important;">
              Inv: <span class="text-white fw-semibold font-monospace" id="modal-noTagihan">-</span>
            </small>
          </div>
          <div class="text-end border-start ps-2 ps-sm-3" style="border-color: rgba(255, 255, 255, 0.18) !important;">
            <div class="fw-bold text-white fs-6 text-truncate" style="max-width: 175px;" id="modal-namaPelanggan">-</div>
            <span class="badge" style="background: rgba(255, 255, 255, 0.18) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.28) !important; font-size: 0.68rem;" id="modal-paket-badge">-</span>
            <div class="mt-1" style="font-size: 0.68rem; color: rgba(255, 255, 255, 0.7) !important;">
              Periode: <span class="text-white fw-medium" id="modal-periode-display">-</span>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-body p-3 bg-light-subtle">
        <!-- Alert Box Akumulasi Multi-Tunggakan -->
        <div id="box-multi-tunggakan" class="card border border-warning border-opacity-50 bg-warning-subtle p-2 mb-3 rounded-3" style="display: none;">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex align-items-center gap-2">
              <i class="mdi mdi-alert-circle text-warning fs-4"></i>
              <div>
                <div class="fw-bold text-dark small">Perhatian: Pelanggan Memiliki Multi-Tunggakan</div>
                <small class="text-muted" style="font-size: 0.72rem;">
                  Terdapat <strong id="multi-count" class="text-danger">0</strong> tagihan aktif (Total: <strong id="multi-total-tunggakan" class="text-danger">Rp 0</strong>)
                </small>
              </div>
            </div>
          </div>
          <div class="row g-1">
            <div class="col-6">
              <button type="button" class="btn btn-sm btn-outline-warning w-100 fw-bold py-1 px-2 text-start d-flex flex-column shadow-xs" id="btn-mode-tagihan-ini">
                <span style="font-size: 0.68rem;" class="text-muted">Bayar Tagihan Ini Saja</span>
                <span class="fs-6 text-dark" id="txt-sisa-ini">Rp 0</span>
              </button>
            </div>
            <div class="col-6">
              <button type="button" class="btn btn-sm btn-warning w-100 fw-bold py-1 px-2 text-start d-flex flex-column shadow-xs" id="btn-mode-bayar-semua">
                <span style="font-size: 0.68rem;" class="text-dark">LUNASI SEMUA TUNGGAKAN</span>
                <span class="fs-6 text-dark" id="txt-total-semua">Rp 0</span>
              </button>
            </div>
          </div>
        </div>

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

          <!-- Step 2: Kalkulator Pembayaran & Kembalian (Default Langsung Uang Pas) -->
          <div id="cash-calculator-box" class="card border rounded-3 p-2.5 p-sm-3 mb-2">
            <div class="row align-items-center g-2">
              <div class="col-sm-6">
                <label for="cash-received" class="form-label fw-bold text-dark small mb-1">Uang Diterima (Rp)</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white fw-bold">Rp</span>
                  <input type="number" class="form-control form-control-sm fw-bold fs-6" id="cash-received" placeholder="0" onfocus="this.select()" onclick="this.select()">
                </div>
                <div class="d-flex flex-wrap gap-1 mt-1.5">
                  <button type="button" class="btn btn-quick-cash" data-mode="exact">Uang Pas</button>
                  <button type="button" class="btn btn-quick-cash" data-amount="50000">50rb</button>
                  <button type="button" class="btn btn-quick-cash" data-amount="100000">100rb</button>
                  <button type="button" class="btn btn-quick-cash" data-amount="200000">200rb</button>
                  <button type="button" class="btn btn-quick-cash" data-amount="500000">500rb</button>
                </div>
              </div>
              <div class="col-sm-6 text-sm-end pt-1 pt-sm-0 border-sm-start ps-sm-3" style="border-color: #e2e8f0 !important;">
                <div class="small text-muted fw-semibold" style="font-size: 0.72rem;">Status / Kembalian</div>
                <div class="fs-4 fw-bolder text-success" id="cash-change-display">Rp 0</div>
                <div id="cash-change-status" style="font-size: 0.72rem;">
                  <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5">
                    <i class="mdi mdi-check me-1"></i>Uang Pas
                  </span>
                </div>
              </div>
            </div>

            <!-- Alert Cicilan Full Width (Anti-Potong / Anti-Truncate) -->
            <div id="cash-cicilan-alert" class="mt-2 pt-2 border-top" style="display: none; border-color: #fde68a !important;">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-1 text-warning-emphasis" style="font-size: 0.73rem;">
                <span class="d-flex align-items-center gap-1">
                  <i class="mdi mdi-clock-alert-outline text-warning fs-6"></i>
                  <span>Nominal pembayaran kurang dari total tagihan</span>
                </span>
                <span class="badge bg-warning text-dark fw-bold" id="cash-cicilan-sisa-badge">Sisa: Rp 0</span>
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

<!-- Modal Struk / Cetak Kuitansi Cerdas (Dukungan Bukti Cicilan & Lunas) -->
<div class="modal fade" id="modalStruk" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-light py-2 px-3 border-bottom">
        <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-1 mb-0">
          <i class="mdi mdi-receipt-text-check text-primary fs-5"></i>
          <span id="struk-modal-title">Tanda Terima Cicilan</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3" id="struk-print-wrapper">
        <div class="receipt-box shadow-none">
          <!-- Receipt Header -->
          <div class="receipt-header">
            <h5 class="fw-bold mb-1 text-dark">MRYES NET</h5>
            <div class="small text-muted mb-2">Layanan Internet Cepat & Stabil</div>
            <div id="struk-stamp" class="receipt-stamp-cicilan">CICILAN</div>
          </div>

          <!-- Receipt Details -->
          <div class="receipt-item-row">
            <span class="text-muted">No. Invoice:</span>
            <span class="fw-bold font-monospace" id="struk-noTagihan">-</span>
          </div>
          <div class="receipt-item-row">
            <span class="text-muted">Pelanggan:</span>
            <span class="fw-bold text-dark" id="struk-namaPelanggan">-</span>
          </div>
          <div class="receipt-item-row">
            <span class="text-muted">Paket:</span>
            <span class="fw-semibold text-primary" id="struk-paket">-</span>
          </div>
          <div class="receipt-item-row">
            <span class="text-muted">Periode:</span>
            <span class="fw-semibold" id="struk-periode">-</span>
          </div>
          <div class="receipt-item-row">
            <span class="text-muted">Waktu Bayar:</span>
            <span id="struk-tglBayar">-</span>
          </div>
          <div class="receipt-item-row">
            <span class="text-muted">Metode:</span>
            <span class="badge bg-label-dark" id="struk-metode">-</span>
          </div>
          <div class="receipt-item-row">
            <span class="text-muted">Petugas Kasir:</span>
            <span id="struk-kasir">-</span>
          </div>

          <div class="receipt-divider"></div>

          <!-- Rincian Biaya: Lunas Penuh / Normal -->
          <div id="struk-rincian-lunas" style="display: none;">
            <div class="receipt-item-row">
              <span class="text-muted">Harga Paket:</span>
              <span id="struk-harga">Rp 0</span>
            </div>
            <div class="receipt-item-row">
              <span class="text-muted">PPN (11%):</span>
              <span id="struk-ppn">Rp 0</span>
            </div>
            <div class="receipt-item-row">
              <span class="text-muted">Potongan Diskon:</span>
              <span class="text-success" id="struk-diskon">Rp 0</span>
            </div>
          </div>

          <!-- Rincian Biaya: Cicilan Berjalan -->
          <div id="struk-rincian-cicilan">
            <div class="receipt-item-row">
              <span class="text-muted">Total Tagihan:</span>
              <span class="fw-semibold" id="struk-cicil-total">Rp 0</span>
            </div>
            <div class="receipt-item-row">
              <span class="text-muted">Total Masuk (Terbayar):</span>
              <span class="fw-bold text-warning" id="struk-cicil-terbayar">Rp 0</span>
            </div>
            <div class="receipt-item-row">
              <span class="text-muted">Sisa Belum Lunas:</span>
              <span class="fw-bold text-danger" id="struk-cicil-sisa">Rp 0</span>
            </div>
          </div>

          <div class="receipt-divider"></div>

          <div class="receipt-item-row fs-6 fw-bold">
            <span class="text-dark" id="struk-label-total">TOTAL DITERIMA:</span>
            <span class="text-warning" id="struk-total">Rp 0</span>
          </div>

          <!-- Riwayat Pembayaran Masuk Bertahap -->
          <div id="struk-riwayat-wrapper" class="mt-3 pt-2 border-top border-dashed" style="display: none;">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="fw-bold text-dark" style="font-size: 0.75rem;">
                <i class="mdi mdi-history me-1 text-primary"></i>RIWAYAT PEMBAYARAN MASUK:
              </span>
              <span class="badge bg-light text-muted border" id="struk-riwayat-count" style="font-size: 0.65rem;">0 transaksi</span>
            </div>
            <div id="struk-riwayat-list" class="bg-light bg-opacity-75 rounded-2 p-2 border" style="font-size: 0.75rem;"></div>
          </div>

          <div class="receipt-divider"></div>

          <!-- Footer Struk -->
          <div class="text-center small text-muted mt-2" style="font-size: 0.72rem;">
            <div>Bukti tanda terima ini sah dan diterbitkan oleh sistem komputerisasi MRYES NET.</div>
            <div class="fw-semibold mt-1">Terima kasih atas pembayaran Anda!</div>
          </div>
        </div>
      </div>

      <div class="modal-footer bg-light p-2 d-flex align-items-center justify-content-between border-top">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
          Tutup
        </button>
        <div class="d-flex align-items-center gap-1">
          <button type="button" class="btn btn-sm btn-success d-flex align-items-center gap-1 fw-semibold shadow-xs" id="btn-wa-struk-action">
            <i class="mdi mdi-whatsapp"></i>
            <span>Kirim WA</span>
          </button>
          <button type="button" class="btn btn-sm btn-primary d-flex align-items-center gap-1 fw-semibold shadow-xs" id="btn-cetak-struk-action">
            <i class="mdi mdi-printer"></i>
            <span>Cetak Slip</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
