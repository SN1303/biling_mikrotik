@extends('layouts/layoutMaster')

@section('title', 'Riwayat Tagihan Lunas')

@section('vendor-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
@endsection

@section('page-style')
@include('content.tagihan._style')
@endsection
@section('page-script')
<script>
  let table;
  let activeBillData = null;
  let activeFilterLunas = ''; // '' = semua, 'lunas', 'cicilan'

  // Batal pembayaran hanya untuk Super Admin, Admin, Bendahara (sesuai route)
  const CAN_CANCEL_PAYMENT = @json(auth()->user()->hasRole(1, 2, 3));

  // Escape teks dari database sebelum disisipkan ke HTML (cegah XSS)
  function esc(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  const namaBulan = [
    '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];

  $(document).ready(function() {
    // Enable GlobalLoading jika tersedia
    if (window.GlobalLoading) {
      GlobalLoading.enableAutoLoading();
      GlobalLoading.show('Memuat riwayat transaksi lunas & cicilan...');
    }

    table = $('#table').DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: "{{ route('tagihan.json') }}",
        data: function(d) {
          d.status_bayar = 1; // Khusus data lunas & cicilan masuk
          const tahun = $('#filter-tahun').val();
          const bulan = $('#filter-bulan').val();
          if (tahun) d.tahun = tahun;
          if (bulan) d.bulan = bulan;
          if (activeFilterLunas) d.filter_lunas = activeFilterLunas;
        },
        dataSrc: function(json) {
          // Update summary cards dari response
          if (json.summary) {
            updateSummaryCards(json.summary);
          }
          if (json.unpaid_summary) {
            updateUnpaidSummary(json.unpaid_summary);
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
            const kasirSafe = esc(row.prosesNama);
            const waBadge = wa && wa !== '-'
              ? `<a href="https://wa.me/${String(wa).replace(/[^0-9]/g, '').replace(/^0/, '62')}" target="_blank" class="badge bg-success-subtle text-success border border-success-subtle text-decoration-none" title="Chat WhatsApp"><i class="mdi mdi-whatsapp me-1"></i>${wa}</a>`
              : `<span class="badge bg-light text-muted border">No WA (-)</span>`;

            const isCicilan = !!row.is_cicilan;
            const isGratis = (row.metode && String(row.metode).toLowerCase().includes('gratis')) || (parseFloat(row.total || 0) <= 0);

            const totalAwalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.total || 0);
            const terbayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.terbayar || 0);
            const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.sisa || 0);

            const namaBulanStr = namaBulan[row.bulan] || row.bulan;
            const periodeText = `${namaBulanStr} ${row.tahun}`;

            // Format tanggal bayar ringkas
            let tglBayarFmt = row.tglBayar || '-';
            if (row.tglBayar && moment(row.tglBayar).isValid()) {
              tglBayarFmt = moment(row.tglBayar).format('DD/MM/YYYY HH:mm');
            }

            // Metode Bayar Badge
            const metodeLower = String(row.metode || '').toLowerCase();
            let metodeBadge = '';
            if (isGratis) {
              metodeBadge = `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="mdi mdi-gift-outline me-1"></i>Gratis</span>`;
            } else if (metodeLower.includes('tunai') || metodeLower.includes('cash') || metodeLower.includes('cod')) {
              metodeBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="mdi mdi-cash me-1"></i>Tunai</span>`;
            } else if (metodeLower.includes('transfer') || metodeLower.includes('bank')) {
              metodeBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="mdi mdi-bank-transfer me-1"></i>Transfer</span>`;
            } else if (metodeLower.includes('qris')) {
              metodeBadge = `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="mdi mdi-qrcode me-1"></i>QRIS</span>`;
            } else if (metodeLower.includes('wallet')) {
              metodeBadge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="mdi mdi-wallet-outline me-1"></i>E-Wallet</span>`;
            } else {
              metodeBadge = `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">${row.metode || 'Lunas'}</span>`;
            }

            let statusBadgeDesktop = '';
            if (isCicilan) {
              statusBadgeDesktop = `<span class="badge bg-warning text-dark border border-warning"><i class="mdi mdi-clock-outline me-1"></i>Cicilan</span>`;
            } else if (isGratis) {
              statusBadgeDesktop = `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="mdi mdi-gift-outline me-1"></i>Gratis</span>`;
            } else {
              statusBadgeDesktop = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="mdi mdi-check-circle me-1"></i>Lunas</span>`;
            }

            let deleteBtnMobile = '';
            @if(auth()->user()->hasRole(1, 2))
            deleteBtnMobile = `
              <button type="button" class="btn btn-sm btn-outline-danger btn-delete p-2 shadow-xs" data-id="${row.id}" title="Hapus Tagihan" style="border-radius: 8px; width: 38px; height: 38px;">
                <i class="mdi mdi-trash-can-outline fs-6"></i>
              </button>
            `;
            @endif

            // Mobile amount display
            let mobileAmountBlock = '';
            if (isCicilan) {
              mobileAmountBlock = `
                <div class="fs-6 fw-bolder text-warning mb-0">${terbayarFmt}</div>
                <div class="mt-1">
                  <span class="badge bg-warning text-dark border border-warning" style="font-size: 0.68rem;">
                    <i class="mdi mdi-clock-outline me-1"></i>CICILAN
                  </span>
                </div>
                <div class="text-muted mt-1" style="font-size: 0.65rem;">
                  Tagihan: ${totalAwalFmt} | <span class="text-danger fw-bold">Sisa: ${sisaFmt}</span>
                </div>
              `;
            } else if (isGratis) {
              mobileAmountBlock = `
                <div class="fs-6 fw-bolder text-info mb-0">Rp 0</div>
                <div class="mt-1">
                  <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size: 0.68rem;">
                    <i class="mdi mdi-gift-outline me-1"></i>BEBAS BIAYA
                  </span>
                </div>
              `;
            } else {
              mobileAmountBlock = `
                <div class="fs-6 fw-bolder text-success mb-0">${totalAwalFmt}</div>
                <div class="mt-1">
                  <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">
                    <i class="mdi mdi-check-circle me-1"></i>LUNAS
                  </span>
                </div>
              `;
            }

            let mobileActionPrimary = '';
            if (isCicilan) {
              mobileActionPrimary = `
                <a href="{{ route('tagihan.index') }}" class="btn btn-warning flex-grow-1 d-flex align-items-center justify-content-center gap-1 py-2 shadow-xs fw-bold text-dark text-decoration-none" style="font-size: 0.85rem; border-radius: 8px;">
                  <i class="mdi mdi-cash-register fs-5"></i>
                  <span>LUNASI DI KASIR</span>
                </a>
              `;
            } else {
              mobileActionPrimary = `
                <button type="button" class="btn btn-success btn-kuitansi-wa flex-grow-1 d-flex align-items-center justify-content-center gap-1 py-2 shadow-xs fw-bold" data-id="${row.id}" style="font-size: 0.85rem; border-radius: 8px;">
                  <i class="mdi mdi-whatsapp fs-5"></i>
                  <span>KUITANSI WA</span>
                </button>
              `;
            }

            return `
              <!-- DESKTOP VIEW (>= 768px) -->
              <div class="d-none d-md-block">
                <div class="fw-bold text-dark fs-6 mb-1">${data || 'Tanpa Nama'}</div>
                <div class="d-flex align-items-center flex-wrap gap-1">
                  <span class="badge bg-label-secondary font-monospace" style="font-size: 0.7rem;">${noTagihanSafe}</span>
                  ${waBadge}
                  ${statusBadgeDesktop}
                </div>
              </div>

              <!-- MOBILE CARD VIEW (< 768px) - RESPONSIF SMARTPHONE UNTUK KOLEKTOR -->
              <div class="d-md-none mobile-paid-card">
                <!-- Baris 1: Nama Pelanggan & Total -->
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
                    ${mobileAmountBlock}
                  </div>
                </div>

                <!-- Baris 2: Paket, Periode & Metode Pembayaran -->
                <div class="d-flex align-items-center justify-content-between gap-1 py-1 px-2 my-2 bg-light rounded-2 border" style="font-size: 0.72rem;">
                  <div class="text-truncate">
                    <i class="mdi mdi-speedometer me-1 text-primary"></i>
                    <span class="fw-semibold text-dark">${paketSafe || 'Paket Internet'}</span>
                    <span class="text-muted">(${periodeText})</span>
                  </div>
                  <div>
                    ${metodeBadge}
                  </div>
                </div>

                <!-- Baris 3: Info Waktu Bayar & Kasir Pemroses -->
                <div class="d-flex align-items-center justify-content-between gap-1 mb-2 text-muted" style="font-size: 0.72rem;">
                  <div class="text-truncate">
                    <i class="mdi mdi-clock-check-outline me-1 ${isCicilan ? 'text-warning' : 'text-success'}"></i>
                    <span>${tglBayarFmt}</span>
                  </div>
                  <div class="text-truncate">
                    <i class="mdi mdi-account-check-outline me-1 text-primary"></i>
                    <span>Kasir: ${kasirSafe || '-'}</span>
                  </div>
                </div>

                <!-- Baris 4: ACTION BUTTONS MOBILE (Jempol Friendly) -->
                <div class="d-flex align-items-center gap-2 mt-2">
                  ${mobileActionPrimary}

                  ${isCicilan ? `
                  <button type="button" class="btn btn-outline-success btn-kuitansi-wa d-flex align-items-center justify-content-center p-2 shadow-xs" data-id="${row.id}" title="Kirim Kuitansi Cicilan WA" style="border-radius: 8px; width: 42px; height: 38px;">
                    <i class="mdi mdi-whatsapp fs-5"></i>
                  </button>
                  ` : ''}

                  <!-- Tombol Lihat Struk Cetak -->
                  <button type="button" class="btn btn-outline-primary btn-lihat-struk d-flex align-items-center justify-content-center p-2 shadow-xs" data-id="${row.id}" title="Lihat Struk / Cetak" style="border-radius: 8px; width: 42px; height: 38px;">
                    <i class="mdi mdi-printer fs-5"></i>
                  </button>

                  ${(CAN_CANCEL_PAYMENT && !isGratis) ? `
                  <!-- Tombol Batal Pembayaran -->
                  <button type="button" class="btn btn-outline-warning btn-cancel-payment d-flex align-items-center justify-content-center p-2 shadow-xs" data-id="${row.id}" title="Batal Pembayaran" style="border-radius: 8px; width: 42px; height: 38px;">
                    <i class="mdi mdi-undo fs-5"></i>
                  </button>
                  ` : ''}

                  ${isGratis ? deleteBtnMobile : ''}
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
            const isCicilan = !!row.is_cicilan;
            const isGratis = (row.metode && String(row.metode).toLowerCase().includes('gratis')) || (parseFloat(data || 0) <= 0);

            if (isCicilan) {
              const terbayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.terbayar || 0);
              const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(data || 0);
              const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.sisa || 0);
              return `
                <div>
                  <div class="fw-bold text-warning fs-6">
                    ${terbayarFmt}
                    <span class="badge bg-warning text-dark border border-warning small fw-normal ms-1" style="font-size: 0.68rem;">Cicil</span>
                  </div>
                  <small class="text-muted d-block" style="font-size: 0.72rem;">
                    Tagihan: ${totalFmt} | Sisa: <strong class="text-danger">${sisaFmt}</strong>
                  </small>
                </div>
              `;
            }

            if (isGratis) {
              return `
                <div>
                  <div class="fw-bold text-info fs-6">Rp 0</div>
                  <small class="text-info d-block" style="font-size: 0.72rem;"><i class="mdi mdi-gift-outline me-1"></i>Bebas Biaya / Gratis</small>
                </div>
              `;
            }

            const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(data);
            let detailSub = '';
            if (row.diskon > 0) {
              detailSub = `<small class="text-success d-block" style="font-size: 0.72rem;"><i class="mdi mdi-tag-outline me-1"></i>Diskon Rp ${new Intl.NumberFormat('id-ID').format(row.diskon)}</small>`;
            } else if (row.ppn > 0) {
              detailSub = `<small class="text-muted d-block" style="font-size: 0.72rem;">Termasuk PPN</small>`;
            }
            return `
              <div>
                <div class="fw-bold text-success fs-6">${totalFmt}</div>
                ${detailSub}
              </div>
            `;
          }
        },
        {
          data: 'tglBayar',
          name: 'tglBayar',
          className: 'align-middle d-none d-md-table-cell',
          render: function(data, type, row) {
            let tglFmt = data || '-';
            if (data && moment(data).isValid()) {
              tglFmt = moment(data).format('DD/MM/YYYY HH:mm');
            }

            const isCicilan = !!row.is_cicilan;
            const isGratis = (row.metode && String(row.metode).toLowerCase().includes('gratis')) || (parseFloat(row.total || 0) <= 0);

            const metodeLower = String(row.metode || '').toLowerCase();
            let metodeBadge = '';
            if (isGratis) {
              metodeBadge = `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="mdi mdi-gift-outline me-1"></i>Gratis</span>`;
            } else if (metodeLower.includes('tunai') || metodeLower.includes('cash') || metodeLower.includes('cod')) {
              metodeBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="mdi mdi-cash me-1"></i>Tunai</span>`;
            } else if (metodeLower.includes('transfer') || metodeLower.includes('bank')) {
              metodeBadge = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="mdi mdi-bank-transfer me-1"></i>Transfer</span>`;
            } else if (metodeLower.includes('qris')) {
              metodeBadge = `<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="mdi mdi-qrcode me-1"></i>QRIS</span>`;
            } else if (metodeLower.includes('wallet')) {
              metodeBadge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="mdi mdi-wallet-outline me-1"></i>E-Wallet</span>`;
            } else {
              metodeBadge = `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">${row.metode || 'Lunas'}</span>`;
            }

            let badgeTambahan = isCicilan
              ? `<span class="badge bg-warning text-dark border border-warning" style="font-size: 0.68rem;"><i class="mdi mdi-clock-outline me-1"></i>Cicilan Berjalan</span>`
              : '';

            return `
              <div>
                <div class="small fw-semibold text-dark">${tglFmt}</div>
                <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
                  ${metodeBadge}
                  ${badgeTambahan}
                </div>
                <div class="small text-muted mt-1" style="font-size: 0.72rem;">
                  <i class="mdi mdi-account-check-outline me-1 text-primary"></i>${esc(row.prosesNama) || '-'}
                </div>
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
            // Tagihan gratis tidak punya pembayaran untuk dibatalkan; tagihan berbayar
            // tidak boleh dihapus (riwayat keuangan) — batalkan pembayarannya dulu.
            const isGratisRow = (row.metode && String(row.metode).toLowerCase().includes('gratis')) || (parseFloat(row.total || 0) <= 0);
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

            let cicilanDropdownOption = '';
            if (row.is_cicilan) {
              cicilanDropdownOption = `
                <li>
                  <a class="dropdown-item py-1 text-warning fw-semibold" href="{{ route('tagihan.index') }}">
                    <i class="mdi mdi-cash-register me-2"></i> Lanjutkan di Kasir
                  </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
              `;
            }

            return `
              <div class="d-inline-flex align-items-center gap-1">
                <!-- Tombol Kuitansi WA -->
                <button type="button" class="btn btn-sm btn-success btn-kuitansi-wa d-inline-flex align-items-center gap-1 shadow-xs px-2 py-1" data-id="${data}" title="Kirim Kuitansi WhatsApp">
                  <i class="mdi mdi-whatsapp"></i>
                  <span class="fw-semibold">Kuitansi</span>
                </button>

                <!-- Tombol Preview Struk -->
                <button type="button" class="btn btn-sm btn-outline-primary btn-lihat-struk d-inline-flex align-items-center p-1" data-id="${data}" title="Lihat Struk / Cetak">
                  <i class="mdi mdi-printer fs-6"></i>
                </button>

                <!-- Dropdown Opsi -->
                <div class="dropdown">
                  <button aria-label="Menu aksi" type="button" class="btn btn-sm btn-icon btn-light rounded-circle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="mdi mdi-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow border py-1" style="min-width: 185px; border-radius: 8px;">
                    <li>
                      <a class="dropdown-item btn-kuitansi-wa text-success py-1" href="javascript:void(0);" data-id="${data}">
                        <i class="mdi mdi-whatsapp me-2"></i> Kirim Kuitansi WA
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item btn-lihat-struk py-1 text-primary" href="javascript:void(0);" data-id="${data}">
                        <i class="mdi mdi-printer-outline me-2"></i> Cetak / Struk
                      </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    ${cicilanDropdownOption}
                    ${(CAN_CANCEL_PAYMENT && !isGratisRow) ? `
                    <li>
                      <a class="dropdown-item btn-cancel-payment text-warning fw-semibold py-1" href="javascript:void(0);" data-id="${data}">
                        <i class="mdi mdi-undo me-2"></i> ${row.is_cicilan ? 'Batalkan Cicilan' : 'Batal Pembayaran'}
                      </a>
                    </li>
                    ` : ''}
                    ${isGratisRow ? deleteBtn : ''}
                  </ul>
                </div>
              </div>
            `;
          }
        }
      ],
      order: [[4, 'desc']], // Urutkan berdasarkan waktu bayar terbaru
      dom:
        't' +
        '<"card-body py-2 px-3 border-top bg-light-subtle"<"row align-items-center g-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"p>>>',
      displayLength: 20,
      lengthMenu: [10, 20, 35, 50, 100],
      language: {
        search: "",
        searchPlaceholder: "Cari pelanggan, invoice, paket...",
        lengthMenu: "Tampilkan _MENU_ data",
        info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ riwayat transaksi",
        infoEmpty: "Tidak ada riwayat transaksi",
        infoFiltered: "(disaring dari _MAX_ total data)",
        emptyTable: "Belum ada transaksi pembayaran lunas atau cicilan untuk periode ini",
        paginate: {
          next: '<i class="mdi mdi-chevron-right"></i>',
          previous: '<i class="mdi mdi-chevron-left"></i>'
        }
      },
      initComplete: function() {
        if (window.GlobalLoading) {
          GlobalLoading.hide();
        }
      },
      drawCallback: function() {
        if (window.GlobalLoading && window.GlobalLoading.isLoading) {
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

    // Update Summary Cards Tagihan Lunas
    function updateSummaryCards(summary) {
      $('#total-count').text(new Intl.NumberFormat('id-ID').format(summary.total_count || 0));
      $('#total-paid').text('Rp ' + new Intl.NumberFormat('id-ID').format(summary.total_unpaid || 0));
      $('#total-diskon-lunas').text('Rp ' + new Intl.NumberFormat('id-ID').format(summary.total_diskon || 0));

      // Tab count badges
      $('#tab-count-all').text(new Intl.NumberFormat('id-ID').format(summary.total_count || 0));
      $('#tab-count-lunas').text(new Intl.NumberFormat('id-ID').format(summary.lunas_count || 0));
      $('#tab-count-cicilan').text(new Intl.NumberFormat('id-ID').format(summary.cicilan_count || 0));
    }

    // Update Summary Tagihan Belum Bayar
    function updateUnpaidSummary(unpaidSummary) {
      if (unpaidSummary && unpaidSummary.unpaid_total !== undefined) {
        $('#total-unpaid').text('Rp ' + new Intl.NumberFormat('id-ID').format(unpaidSummary.unpaid_total || 0));
      } else {
        $('#total-unpaid').text('Rp 0');
      }
    }

    // Tab Filter Lunas Penuh vs Cicilan Berjalan
    $('#filter-lunas-group').on('click', '.btn-filter-pill', function() {
      $('#filter-lunas-group .btn-filter-pill').removeClass('active');
      $(this).addClass('active');

      activeFilterLunas = $(this).data('filter') || '';

      if (window.GlobalLoading) {
        GlobalLoading.show('Menyaring status transaksi...');
      }

      table.ajax.reload(() => {
        if (window.GlobalLoading) {
          GlobalLoading.hide();
        }
      });
    });

    // Filter Periode (Bulan & Tahun)
    $('#filter-bulan, #filter-tahun').on('change', function() {
      if (window.GlobalLoading) {
        GlobalLoading.show('Memfilter data riwayat...');
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

    // Helper untuk mengambil Row Data dari DataTables
    function getRowDataById(id) {
      const allRows = table.rows().data().toArray();
      if (allRows && allRows.length > 0) {
        const found = allRows.find(item => item.id == id);
        if (found) return found;
      }
      return null;
    }

    // Event Handler: Kirim Kuitansi WhatsApp
    $(document).on('click', '.btn-kuitansi-wa', function(e) {
      e.preventDefault();
      const id = $(this).attr('data-id');
      const row = getRowDataById(id);
      if (!row) {
        Swal.fire('Perhatian', 'Data tagihan tidak ditemukan di tabel.', 'warning');
        return;
      }
      kirimKuitansiWhatsApp(row);
    });

    // Function: Kirim Kuitansi WhatsApp (Pintar Lunas / Cicilan / Gratis)
    function kirimKuitansiWhatsApp(row) {
      const rawWa = row.user_wa;
      if (!rawWa || rawWa === '-' || rawWa.trim() === '') {
        Swal.fire({
          icon: 'info',
          title: 'Nomor WhatsApp Tidak Ditemukan',
          text: `Pelanggan "${row.user_nama}" belum memiliki nomor WhatsApp di sistem. Silakan update data pelanggan.`,
          confirmButtonText: 'OK',
          customClass: { confirmButton: 'btn btn-primary' }
        });
        return;
      }

      let cleanWa = String(rawWa).replace(/[^0-9]/g, '');
      if (cleanWa.startsWith('0')) cleanWa = '62' + cleanWa.slice(1);

      const bulanNama = namaBulan[row.bulan] || row.bulan;
      const periodeStr = `${bulanNama} ${row.tahun}`;
      const isCicilan = !!row.is_cicilan;
      const isGratis = (row.metode && String(row.metode).toLowerCase().includes('gratis')) || (parseFloat(row.total || 0) <= 0);

      let tglBayarStr = row.tglBayar || '-';
      if (row.tglBayar && moment(row.tglBayar).isValid()) {
        tglBayarStr = moment(row.tglBayar).format('DD-MM-YYYY HH:mm');
      }

      let message = '';

      if (isCicilan) {
        const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.total || 0);
        const terbayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.terbayar || 0);
        const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.sisa || 0);
        const metodeStr = (row.metode || 'Tunai').toUpperCase();

        message = `Halo Kak *${row.user_nama}*,\n\n` +
          `Terima kasih atas pembayaran tagihan internet *MRYES NET*. Berikut rincian tanda terima pembayaran cicilan Anda:\n\n` +
          `📄 *No. Invoice:* ${row.noTagihan}\n` +
          `📦 *Paket:* ${row.paket_nama || 'Internet'}\n` +
          `📅 *Periode:* ${periodeStr}\n` +
          `💵 *Total Tagihan:* ${totalFmt}\n` +
          `💰 *Total Uang Masuk (Terbayar):* *${terbayarFmt}*\n` +
          `⏳ *Sisa Tagihan Belum Lunas:* *${sisaFmt}*\n` +
          `💳 *Metode Pembayaran:* ${metodeStr}\n` +
          `🗓️ *Waktu Proses:* ${tglBayarStr}\n` +
          `👤 *Petugas/Admin:* ${row.prosesNama || '-'}\n` +
          `⚠️ *Status:* *BELUM LUNAS (TERCATAT SEBAGAI CICILAN)*\n\n` +
          `Mohon sisa tagihan dapat diselesaikan sebelum tanggal jatuh tempo. Terima kasih telah mempercayakan layanan internet Anda kepada MRYES NET! 🙏`;
      } else if (isGratis) {
        message = `Halo Kak *${row.user_nama}*,\n\n` +
          `Berikut adalah bukti konfirmasi layanan internet *MRYES NET* periode ${periodeStr}:\n\n` +
          `📄 *No. Kuitansi/Invoice:* ${row.noTagihan}\n` +
          `📦 *Paket:* ${row.paket_nama || 'Internet Reguler'}\n` +
          `📅 *Periode:* ${periodeStr}\n` +
          `💰 *Total Biaya:* *Rp 0 (BEBAS BIAYA / GRATIS)*\n` +
          `💳 *Keterangan:* PROMO / PAKET GRATIS\n` +
          `🗓️ *Waktu Proses:* ${tglBayarStr}\n` +
          `👤 *Petugas/Admin:* ${row.prosesNama || '-'}\n` +
          `✅ *Status:* *LUNAS (PAKET GRATIS / BEBAS BIAYA)*\n\n` +
          `Terima kasih telah setia menggunakan layanan internet cepat *MRYES NET*. 🙏`;
      } else {
        const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.total || 0);
        const metodeStr = (row.metode || 'Tunai').toUpperCase();

        message = `Halo Kak *${row.user_nama}*,\n\n` +
          `Terima kasih atas pembayaran tagihan internet *MRYES NET*. Berikut adalah bukti kuitansi pelunasan resmi:\n\n` +
          `📄 *No. Kuitansi/Invoice:* ${row.noTagihan}\n` +
          `📦 *Paket:* ${row.paket_nama || 'Internet Reguler'}\n` +
          `📅 *Periode:* ${periodeStr}\n` +
          `💰 *Total:* *${totalFmt}*\n` +
          `💳 *Metode Pembayaran:* ${metodeStr}\n` +
          `🗓️ *Waktu Proses:* ${tglBayarStr}\n` +
          `👤 *Petugas/Admin:* ${row.prosesNama || '-'}\n` +
          `✅ *Status:* *LUNAS (TERVERIFIKASI)*\n\n` +
          `Terima kasih telah setia menggunakan layanan internet cepat *MRYES NET*. Jika ada kendala koneksi atau pertanyaan, silakan hubungi customer service kami. 🙏`;
      }

      window.open(`https://wa.me/${cleanWa}?text=${encodeURIComponent(message)}`, '_blank');
    }

    // Event Handler: Buka Modal Struk / Cetak Kuitansi
    $(document).on('click', '.btn-lihat-struk', function(e) {
      e.preventDefault();
      const id = $(this).attr('data-id');
      const row = getRowDataById(id);
      if (!row) {
        Swal.fire('Perhatian', 'Data transaksi tidak ditemukan.', 'warning');
        return;
      }

      activeBillData = row;
      const bulanNama = namaBulan[row.bulan] || row.bulan;
      const periodeStr = `${bulanNama} ${row.tahun}`;
      const hargaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.harga || 0);
      const ppnFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.ppn || 0);
      const diskonFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.diskon || 0);
      const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.total || 0);
      const terbayarFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.terbayar || 0);
      const sisaFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(row.sisa || 0);

      const isCicilan = !!row.is_cicilan;
      const isGratis = (row.metode && String(row.metode).toLowerCase().includes('gratis')) || (parseFloat(row.total || 0) <= 0);

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

      // Stempel & Tampilan Rincian Biaya Cerdas
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
      } else if (isGratis) {
        $stamp.addClass('receipt-stamp-gratis').text('GRATIS');
        $('#struk-modal-title').text('Konfirmasi Bebas Biaya');
        $('#struk-rincian-lunas').show();
        $('#struk-rincian-cicilan').hide();
        $('#struk-harga').text('Rp 0');
        $('#struk-ppn').text('Rp 0');
        $('#struk-diskon').text(diskonFmt);
        $('#struk-label-total').text('TOTAL TAGIHAN:');
        $('#struk-total').removeClass('text-success text-warning').addClass('text-info').text('Rp 0 (BEBAS BIAYA)');
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
              <div class="fw-bold ${isCicilan ? 'text-warning' : 'text-success'}">${nomFmt}</div>
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
    $('#btn-cetak-struk-action').on('click', function() {
      window.print();
    });

    // Tombol Kirim WA dari dalam Modal Struk
    $('#btn-wa-struk-action').on('click', function() {
      if (activeBillData) {
        kirimKuitansiWhatsApp(activeBillData);
      }
    });

    // Event Handler: Batal Pembayaran
    $('#table').on('click', '.btn-cancel-payment', function() {
      const id = $(this).data('id');

      Swal.fire({
        title: "Yakin ingin membatalkan pembayaran?",
        html: `
          <div class="text-start">
            <p class="mb-2"><strong>Peringatan Sistem:</strong></p>
            <ul class="text-muted small">
              <li>Status tagihan akan kembali menjadi <strong>BELUM LUNAS</strong></li>
              <li>Data riwayat transaksi kasir akan dihapus</li>
              <li>Tanggal bayar dan metode pembayaran akan direset</li>
            </ul>
            <p class="text-danger small mt-2 mb-0"><i class="mdi mdi-alert-circle me-1"></i> Aksi ini akan memindahkan tagihan kembali ke halaman Tagihan Aktif.</p>
          </div>
        `,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "YA, BATALKAN PEMBAYARAN",
        cancelButtonText: "TIDAK, TETAPKAN",
        buttonsStyling: false,
        customClass: {
          confirmButton: "btn btn-warning me-2",
          cancelButton: "btn btn-outline-secondary"
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const cancelSteps = [
            { progress: 20, text: 'Memverifikasi transaksi lunas...', delay: 250 },
            { progress: 50, text: 'Membatalkan pembayaran...', delay: 350 },
            { progress: 80, text: 'Mengembalikan ke tagihan aktif...', delay: 300 },
            { progress: 100, text: 'Pembayaran berhasil dibatalkan!', delay: 200 }
          ];

          if (window.GlobalLoading) {
            GlobalLoading.showWithSteps(cancelSteps);
          }

          setTimeout(() => {
            $.ajax({
              url: `{{ url('tagihan/batal-pembayaran') }}/${id}`,
              type: 'POST',
              data: {
                _token: '{{ csrf_token() }}'
              },
              success: function(response) {
                if (window.GlobalLoading) {
                  GlobalLoading.hide();
                }

                if (response.success) {
                  Swal.fire({
                    title: 'Pembayaran Dibatalkan!',
                    html: `
                      <div class="text-start">
                        <p class="text-success mt-1 mb-1"><i class="mdi mdi-check-circle me-1"></i> Status tagihan telah kembali menjadi <strong>BELUM LUNAS</strong>.</p>
                        <p class="text-muted small">Tagihan telah dipindahkan kembali ke menu <strong>Tagihan Aktif</strong>.</p>
                      </div>
                    `,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    customClass: { confirmButton: 'btn btn-success' }
                  });

                  if (window.GlobalLoading) {
                    GlobalLoading.show('Memuat ulang data...');
                  }

                  table.ajax.reload(() => {
                    if (window.GlobalLoading) {
                      GlobalLoading.hide();
                    }
                  });
                } else {
                  Swal.fire({
                    title: 'Gagal Membatalkan Pembayaran',
                    text: response.message || 'Terjadi kesalahan sistem.',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    customClass: { confirmButton: 'btn btn-danger' }
                  });
                }
              },
              error: function(xhr) {
                if (window.GlobalLoading) {
                  GlobalLoading.hide();
                }

                let message = 'Terjadi kesalahan pada server.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                  message = xhr.responseJSON.message;
                }

                Swal.fire({
                  title: 'Error Sistem',
                  text: message,
                  icon: 'error',
                  confirmButtonText: 'OK',
                  customClass: { confirmButton: 'btn btn-danger' }
                });
              }
            });
          }, 800);
        }
      });
    });

    // Event Handler: Hapus Tagihan (Khusus Admin role 1 & 2)
    $('#table').on('click', '.btn-delete', function() {
      const id = $(this).data('id');

      Swal.fire({
        title: "Yakin ingin menghapus tagihan?",
        text: "Data tagihan lunas ini akan dihapus permanen dari sistem!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "YA, HAPUS PERMANEN!",
        cancelButtonText: "BATAL",
        buttonsStyling: false,
        customClass: {
          confirmButton: "btn btn-danger me-2",
          cancelButton: "btn btn-outline-secondary"
        }
      }).then((result) => {
        if (result.isConfirmed) {
          if (window.GlobalLoading) {
            GlobalLoading.show('Menghapus data tagihan...');
          }

          $.ajax({
            url: `{{ url('tagihan/destroy') }}/${id}`,
            type: 'DELETE',
            data: {
              _token: '{{ csrf_token() }}'
            },
            success: function(response) {
              if (window.GlobalLoading) {
                GlobalLoading.hide();
              }

              if (response.success) {
                Swal.fire({
                  title: 'Data Dihapus!',
                  text: 'Data tagihan berhasil dihapus dari sistem.',
                  icon: 'success',
                  confirmButtonText: 'OK',
                  customClass: { confirmButton: 'btn btn-success' }
                });

                table.ajax.reload();
              } else {
                Swal.fire('Gagal', response.message || 'Gagal menghapus tagihan.', 'error');
              }
            },
            error: function(xhr) {
              if (window.GlobalLoading) {
                GlobalLoading.hide();
              }
              Swal.fire('Error', 'Terjadi kesalahan pada server.', 'error');
            }
          });
        }
      });
    });
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
            <i class="mdi mdi-check-decagram-outline text-success me-1"></i>Riwayat Tagihan Lunas
          </h5>
          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill" style="font-size: 0.7rem;">Terbayar</span>
        </div>
        <p class="text-muted small mb-0 mt-0.5" style="font-size: 0.75rem;">Daftar arsip transaksi dan riwayat pembayaran tagihan pelanggan yang telah lunas</p>
      </div>

      <!-- Right: Filters & Action -->
      <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="d-flex align-items-center gap-1 bg-light rounded-3 p-1 border">
          <span class="text-muted small px-1 fw-semibold" style="font-size: 0.72rem;"><i class="mdi mdi-filter-variant me-sm-1 text-primary" aria-hidden="true"></i><span class="d-none d-sm-inline">Periode:</span></span>
          <select id="filter-bulan" class="form-select form-select-sm border-0 bg-white shadow-xs fw-semibold" style="width: 140px; font-size: 0.78rem;">
            <option value="" selected>Semua Bulan</option>
            @php
              $namaBulanList = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
              ];
            @endphp
            @foreach($namaBulanList as $key => $nama)
              <option value="{{ $key }}">{{ $nama }}</option>
            @endforeach
          </select>
          <select id="filter-tahun" class="form-select form-select-sm border-0 bg-white shadow-xs fw-semibold" style="width: 115px; font-size: 0.78rem;">
            <option value="" selected>Semua Thn</option>
            @if(isset($filterData['tahun']) && !empty($filterData['tahun']))
              @foreach($filterData['tahun'] as $tahun)
                <option value="{{ $tahun }}">{{ $tahun }}</option>
              @endforeach
            @else
              <option value="{{ $currentYear ?? date('Y') }}">{{ $currentYear ?? date('Y') }}</option>
            @endif
          </select>
          <button type="button" class="btn btn-sm btn-icon btn-primary rounded-2 shadow-xs" id="btn-refresh-table" title="Refresh Data" style="width: 30px; height: 30px;">
            <i class="mdi mdi-refresh"></i>
          </button>
        </div>

        <a href="{{ route('tagihan.index') }}" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 shadow-xs py-1 px-2.5" style="font-size: 0.8rem;">
          <i class="mdi mdi-receipt-text-outline"></i>
          <span>Tagihan Aktif</span>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- 4 Executive KPI Summary Cards (Compact & Rapi) -->
<div class="row g-2 mb-2" id="kpi-summary-row">
  <!-- Card 1: Total Lunas -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #10b981 !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title d-block">Total Lunas</span>
          <div class="metric-value text-success mb-0" id="total-count">0</div>
          <small class="metric-sub text-muted">Transaksi selesai</small>
        </div>
        <div class="metric-icon-box bg-success-subtle text-success border border-success-subtle">
          <i class="mdi mdi-check-decagram"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Total Terbayar (Nominal) -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #2563eb !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title d-block">Total Terbayar</span>
          <div class="metric-value text-primary mb-0" id="total-paid">Rp 0</div>
          <small class="metric-sub text-muted">Dana masuk</small>
        </div>
        <div class="metric-icon-box bg-primary-subtle text-primary border border-primary-subtle">
          <i class="mdi mdi-cash-check"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 3: Total Diskon -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #06b6d4 !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title d-block">Total Diskon</span>
          <div class="metric-value text-info mb-0" id="total-diskon-lunas">Rp 0</div>
          <small class="metric-sub text-muted">Potongan harga</small>
        </div>
        <div class="metric-icon-box bg-info-subtle text-info border border-info-subtle">
          <i class="mdi mdi-tag-percent-outline"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 4: Belum Bayar (Piutang Berjalan) -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100" style="border-left: 3px solid #f59e0b !important;">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <span class="metric-title d-block">Belum Bayar</span>
          <div class="metric-value text-warning mb-0" id="total-unpaid">Rp 0</div>
          <small class="metric-sub text-muted">Sisa tagihan aktif</small>
        </div>
        <div class="metric-icon-box bg-warning-subtle text-warning border border-warning-subtle">
          <i class="mdi mdi-file-clock-outline"></i>
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
        <div class="filter-segmented-control shadow-xs" id="filter-lunas-group">
          <button type="button" class="btn btn-filter-pill active" data-filter="">
            <i class="mdi mdi-view-list me-1"></i>Semua (<span id="tab-count-all">0</span>)
          </button>
          <button type="button" class="btn btn-filter-pill text-success" data-filter="lunas">
            <i class="mdi mdi-check-decagram me-1"></i>Lunas Penuh (<span id="tab-count-lunas">0</span>)
          </button>
          <button type="button" class="btn btn-filter-pill text-warning" data-filter="cicilan">
            <i class="mdi mdi-clock-outline me-1"></i>Cicilan Berjalan (<span id="tab-count-cicilan">0</span>)
          </button>
        </div>
      </div>

      <!-- Right: Integrated Search Box & Length Selector -->
      <div class="d-flex align-items-center gap-2 ms-auto">
        <div class="input-group input-group-sm search-input-group shadow-xs" style="width: 230px;">
          <span class="input-group-text bg-white border-0 py-1 pe-1 text-muted">
            <i class="mdi mdi-magnify fs-6"></i>
          </span>
          <input type="text" id="custom-search" class="form-control form-control-sm border-0 ps-1" placeholder="Cari pelanggan, invoice..." style="font-size: 0.78rem;">
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
    <table id="table" class="datatables-basic table table-hover mb-0">
      <thead>
        <tr>
          <th style="width: 45px;" class="text-center">NO</th>
          <th>PELANGGAN & INVOICE</th>
          <th>PAKET & PERIODE</th>
          <th>TOTAL DIBAYAR</th>
          <th>PEMBAYARAN</th>
          <th class="text-center" style="width: 140px;">AKSI</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- Modal Struk / Cetak Kuitansi Modern & Cerdas -->
<div class="modal fade" id="modalStruk" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header bg-light py-2 px-3 border-bottom">
        <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-1 mb-0">
          <i class="mdi mdi-receipt-text-check text-success fs-5"></i>
          <span id="struk-modal-title">Kuitansi Pembayaran</span>
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3" id="struk-print-wrapper">
        <div class="receipt-box shadow-none">
          <!-- Receipt Header -->
          <div class="receipt-header">
            <h5 class="fw-bold mb-1 text-dark">MRYES NET</h5>
            <div class="small text-muted mb-2">Layanan Internet Cepat & Stabil</div>
            <div id="struk-stamp" class="receipt-stamp-paid">LUNAS</div>
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

          <!-- Rincian Biaya: Lunas Penuh / Gratis -->
          <div id="struk-rincian-lunas">
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
          <div id="struk-rincian-cicilan" style="display: none;">
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
            <span class="text-dark" id="struk-label-total">TOTAL DIBAYAR:</span>
            <span class="text-success" id="struk-total">Rp 0</span>
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
            <div>Bukti pembayaran ini sah dan diterbitkan oleh sistem komputerisasi MRYES NET.</div>
            <div class="fw-semibold mt-1">Terima kasih atas kepercayaan Anda!</div>
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
