@extends('layouts/layoutMaster')

@section('title', 'Tagihan Aktif (Belum Lunas)')

@section('vendor-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
@endsection

@section('page-style')
<style>
  /* Styling KPI Cards */
  .metric-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border-radius: 12px !important;
  }
  .metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.07) !important;
  }
  .metric-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
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
  .btn-check:checked + .metode-card {
    border-color: #2563eb !important;
    background: rgba(37, 99, 235, 0.06) !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
  }

  /* Table styling */
  #table thead th {
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    font-weight: 700;
  }
  #table tbody tr:hover {
    background-color: rgba(37, 99, 235, 0.02) !important;
  }
  .btn-xs {
    padding: 2px 7px;
    font-size: 0.72rem;
    border-radius: 4px;
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
          defaultContent: '',
          orderable: false,
          searchable: false
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: function(data, type, row, meta) {
            return `<span class="text-muted fw-semibold">${meta.row + meta.settings._iDisplayStart + 1}</span>`;
          }
        },
        {
          data: 'user_nama',
          name: 'user_nama',
          render: function(data, type, row) {
            const wa = row.user_wa;
            const waButton = wa && wa !== '-'
              ? `<a href="javascript:void(0)" class="btn-wa-direct badge bg-success-subtle text-success border border-success-subtle text-decoration-none" title="Kirim Pesan WhatsApp"><i class="mdi mdi-whatsapp me-1"></i>${wa}</a>`
              : `<span class="badge bg-light text-muted border">No WA (-)</span>`;

            return `
              <div>
                <div class="fw-bold text-dark fs-6 mb-1">${data || 'Tanpa Nama'}</div>
                <div class="d-flex align-items-center flex-wrap gap-1">
                  <span class="badge bg-label-secondary font-monospace" style="font-size: 0.7rem;">${row.noTagihan}</span>
                  ${waButton}
                </div>
              </div>
            `;
          }
        },
        {
          data: 'paket_nama',
          name: 'paket_nama',
          render: function(data, type, row) {
            const periodeText = `${namaBulan[row.bulan] || row.bulan} ${row.tahun}`;
            return `
              <div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold mb-1">
                  <i class="mdi mdi-speedometer me-1"></i>${data || 'Paket Reguler'}
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
          className: 'text-center',
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
                <!-- Tombol Bayar Cepat -->
                <button type="button" class="btn btn-sm btn-success btn-bayar d-inline-flex align-items-center gap-1 shadow-xs px-2 py-1" data-id="${data}" title="Bayar Tagihan">
                  <i class="mdi mdi-cash-check"></i>
                  <span class="fw-semibold">Bayar</span>
                </button>

                <!-- Tombol WhatsApp Pengingat -->
                <button type="button" class="btn btn-sm btn-outline-success btn-wa-direct d-inline-flex align-items-center p-1" title="Kirim Pengingat WhatsApp">
                  <i class="mdi mdi-whatsapp fs-6"></i>
                </button>

                <!-- Opsi Tambahan -->
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
                      <a class="dropdown-item btn-wa-direct py-1" href="javascript:void(0);">
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
      columnDefs: [
        {
          className: 'control',
          targets: 0,
          searchable: false,
          orderable: false,
          render: function() { return ''; }
        }
      ],
      order: [[5, 'asc']], // Urutkan berdasarkan jatuh tempo terdekat
      dom:
        '<"row mx-2 my-2"<"col-sm-12 col-md-6 d-flex align-items-center"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>' +
        't' +
        '<"row mx-2 my-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"p>>',
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
      responsive: {
        details: {
          type: 'column',
          target: 0
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
    $('#table').on('click', '.btn-bayar', function() {
      const id = $(this).data('id');
      const rowData = table.row($(this).closest('tr')).data();

      if (!rowData) return;

      currentRawTotal = rowData.total || 0;
      const totalFmt = 'Rp ' + new Intl.NumberFormat('id-ID').format(currentRawTotal);
      const periodeStr = `${namaBulan[rowData.bulan] || rowData.bulan} ${rowData.tahun}`;

      // Populate Modal Info
      $('#modal-noTagihan').text(rowData.noTagihan);
      $('#modal-namaPelanggan').text(rowData.user_nama);
      $('#modal-paket-badge').text(rowData.paket_nama || 'Paket Internet');
      $('#modal-periode-display').text(periodeStr);
      $('#modal-total-display').text(totalFmt);

      // Set ID tagihan ke form
      $('#form-bayar').data('tagihan-id', id);

      // Default Metode: Tunai / COD
      $('#metode_cod').prop('checked', true);
      $('#metode_bayar').val('cod');
      $('#cash-calculator-box').show();

      // Reset Kalkulator
      $('#cash-received').val('');
      $('#cash-change-display').text('Rp 0');
      $('#cash-change-status').text('Masukkan nominal uang yang diterima');
      $('#keterangan_bayar').val('');

      $('#modalBayar').modal('show');
    });

    // Ganti Metode Bayar Radio Tile
    $('input[name="metode_bayar_radio"]').on('change', function() {
      const selected = $(this).val();
      $('#metode_bayar').val(selected);

      if (selected === 'cod') {
        $('#cash-calculator-box').slideDown(200);
      } else {
        $('#cash-calculator-box').slideUp(200);
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

      const tagihanId = $(this).data('tagihan-id');
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

      $('#modalBayar').modal('hide');

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
    $('#table').on('click', '.btn-delete', function() {
      const id = $(this).data('id');

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
    $('#table').on('click', '.btn-wa-direct', function(e) {
      e.preventDefault();
      const rowData = table.row($(this).closest('tr')).data();
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

<!-- Header & Periode Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
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
    <!-- Filter Box Ringkas -->
    <div class="d-flex align-items-center gap-1 bg-white border rounded-3 p-1 shadow-xs">
      <div class="d-flex align-items-center px-2 text-muted small">
        <i class="mdi mdi-calendar-range me-1 text-primary"></i> Periode:
      </div>
      <select id="filter-bulan" class="form-select form-select-sm border-0 bg-transparent fw-semibold" style="width: 125px;">
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
      <select id="filter-tahun" class="form-select form-select-sm border-0 bg-transparent fw-semibold" style="width: 100px;">
        <option value="">Semua Thn</option>
        @if(isset($filterData['tahun']) && !empty($filterData['tahun']))
          @foreach($filterData['tahun'] as $tahun)
            <option value="{{ $tahun }}" {{ $tahun == $currentYear ? 'selected' : '' }}>{{ $tahun }}</option>
          @endforeach
        @else
          <option value="{{ $currentYear }}" selected>{{ $currentYear }}</option>
        @endif
      </select>
      <button type="button" class="btn btn-sm btn-icon btn-light rounded-circle" id="btn-refresh-table" title="Refresh Data">
        <i class="mdi mdi-refresh text-primary"></i>
      </button>
    </div>

    <!-- Tombol Riwayat Lunas -->
    <a href="{{ route('tagihan.lunas') }}" class="btn btn-sm btn-outline-success d-flex align-items-center gap-1 shadow-xs">
      <i class="mdi mdi-check-decagram-outline"></i>
      <span>Riwayat Lunas</span>
    </a>
  </div>
</div>

<!-- 4 Executive KPI Summary Cards -->
<div class="row g-2 g-sm-3 mb-4">
  <!-- Card 1: Total Tagihan Aktif -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100">
      <div class="card-body p-3 d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted d-block small mb-1 fw-medium">Total Tagihan</span>
          <div class="fs-4 fw-bold text-dark mb-0" id="total-count">0</div>
          <small class="text-muted" style="font-size: 0.72rem;">Menunggu pembayaran</small>
        </div>
        <div class="metric-icon-box bg-primary-subtle text-primary border border-primary-subtle">
          <i class="mdi mdi-file-clock-outline fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 2: Total Nominal Tertunda -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100">
      <div class="card-body p-3 d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted d-block small mb-1 fw-medium">Belum Terbayar</span>
          <div class="fs-4 fw-bold text-danger mb-0" id="total-unpaid">Rp 0</div>
          <small class="text-muted" style="font-size: 0.72rem;">Total tagihan bruto</small>
        </div>
        <div class="metric-icon-box bg-danger-subtle text-danger border border-danger-subtle">
          <i class="mdi mdi-cash-remove fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 3: Overdue / Lewat Jatuh Tempo -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100 border-start border-danger border-3">
      <div class="card-body p-3 d-flex align-items-center justify-content-between">
        <div>
          <span class="text-danger d-block small mb-1 fw-bold">Lewat Jatuh Tempo</span>
          <div class="fs-4 fw-bold text-danger mb-0" id="overdue-count">0</div>
          <small class="text-danger fw-semibold" id="overdue-total" style="font-size: 0.72rem;">Rp 0 macet</small>
        </div>
        <div class="metric-icon-box bg-danger text-white shadow-xs">
          <i class="mdi mdi-alert-circle-outline fs-4"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Card 4: Potensi Penerimaan Kas -->
  <div class="col-6 col-lg-3">
    <div class="card metric-card border-0 shadow-sm h-100">
      <div class="card-body p-3 d-flex align-items-center justify-content-between">
        <div>
          <span class="text-muted d-block small mb-1 fw-medium">Potensi Kas Masuk</span>
          <div class="fs-4 fw-bold text-success mb-0" id="potential-income">Rp 0</div>
          <small class="text-muted" style="font-size: 0.72rem;">Estimasi kas masuk</small>
        </div>
        <div class="metric-icon-box bg-success-subtle text-success border border-success-subtle">
          <i class="mdi mdi-trending-up fs-4"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Main Table Card -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-bottom py-3 px-3 px-sm-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <!-- Quick Filter Status Pills -->
      <div class="btn-group btn-group-sm shadow-xs" role="group" id="quick-filter-group">
        <button type="button" class="btn btn-outline-primary active" data-filter="">
          <i class="mdi mdi-view-list me-1"></i> Semua (<span id="pill-all">0</span>)
        </button>
        <button type="button" class="btn btn-outline-danger" data-filter="overdue">
          <i class="mdi mdi-alert-circle-outline me-1"></i> Lewat Jatuh Tempo (<span id="pill-overdue">0</span>)
        </button>
        <button type="button" class="btn btn-outline-warning" data-filter="today">
          <i class="mdi mdi-clock-alert-outline me-1"></i> Hari Ini (<span id="pill-today">0</span>)
        </button>
        <button type="button" class="btn btn-outline-success" data-filter="upcoming">
          <i class="mdi mdi-calendar-check-outline me-1"></i> Belum Jatuh Tempo
        </button>
      </div>

      <div class="text-muted small d-none d-md-block">
        <i class="mdi mdi-information-outline me-1 text-primary"></i> Klik <b>Bayar</b> untuk melunasi langsung di kasir
      </div>
    </div>
  </div>

  <div class="card-datatable table-responsive pt-0">
    <table id="table" class="datatables-basic table table-hover align-middle mb-0 w-100">
      <thead class="table-light">
        <tr>
          <th></th>
          <th style="width: 40px;">NO</th>
          <th>PELANGGAN & INVOICE</th>
          <th>PAKET & PERIODE</th>
          <th>TOTAL TAGIHAN</th>
          <th>JATUH TEMPO</th>
          <th class="text-center" style="min-width: 160px;">AKSI</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

<!-- Modal Bayar Modern Kasir POS -->
<div class="modal fade" id="modalBayar" tabindex="-1" aria-labelledby="modalBayarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">

      <!-- Top Cashier Header -->
      <div class="p-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success p-2 rounded-circle shadow-xs">
              <i class="mdi mdi-cash-register fs-4 text-white"></i>
            </span>
            <div>
              <h5 class="modal-title fw-bold text-white mb-0" id="modalBayarLabel">Kasir Pembayaran Tagihan</h5>
              <small class="text-white-50">Sistem Pencatatan Transaksi Kasir & Kolektor</small>
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Big Highlight Showcase -->
        <div class="bg-white bg-opacity-10 rounded-3 p-3 border border-white border-opacity-10 d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <div class="text-uppercase small text-white-50 fw-semibold tracking-wider">Total Harus Dibayar</div>
            <div class="fs-1 fw-bolder text-warning" id="modal-total-display">Rp 0</div>
            <div class="small text-white-50 mt-1">
              No. Invoice: <span class="text-white fw-semibold" id="modal-noTagihan">-</span>
            </div>
          </div>
          <div class="text-sm-end border-sm-start border-white border-opacity-10 ps-sm-3">
            <div class="fw-bold text-white fs-6" id="modal-namaPelanggan">-</div>
            <div class="text-white-50 small" id="modal-paket-badge">-</div>
            <div class="text-white-50 small mt-1">
              Periode: <span class="text-white" id="modal-periode-display">-</span>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-body p-4 bg-light-subtle">
        <form id="form-bayar">
          <!-- Step 1: Pilih Metode Bayar -->
          <div class="mb-4">
            <label class="form-label fw-bold text-dark mb-2">
              <i class="mdi mdi-wallet-outline text-primary me-1"></i>Pilih Metode Pembayaran <span class="text-danger">*</span>
            </label>
            <div class="row g-2" id="metode-cards">
              <div class="col-6 col-sm-3">
                <input type="radio" class="btn-check" name="metode_bayar_radio" id="metode_cod" value="cod" checked autocomplete="off">
                <label class="btn btn-outline-secondary w-100 p-3 h-100 d-flex flex-column align-items-center justify-content-center text-center rounded-3 metode-card" for="metode_cod">
                  <i class="mdi mdi-cash-multiple fs-2 text-success mb-1"></i>
                  <span class="fw-bold d-block text-dark small">Tunai / COD</span>
                  <small class="text-muted" style="font-size: 0.7rem;">Bayar di tempat</small>
                </label>
              </div>
              <div class="col-6 col-sm-3">
                <input type="radio" class="btn-check" name="metode_bayar_radio" id="metode_transfer" value="transfer" autocomplete="off">
                <label class="btn btn-outline-secondary w-100 p-3 h-100 d-flex flex-column align-items-center justify-content-center text-center rounded-3 metode-card" for="metode_transfer">
                  <i class="mdi mdi-bank-transfer fs-2 text-primary mb-1"></i>
                  <span class="fw-bold d-block text-dark small">Transfer Bank</span>
                  <small class="text-muted" style="font-size: 0.7rem;">BCA, BRI, Mandiri</small>
                </label>
              </div>
              <div class="col-6 col-sm-3">
                <input type="radio" class="btn-check" name="metode_bayar_radio" id="metode_qris" value="qris" autocomplete="off">
                <label class="btn btn-outline-secondary w-100 p-3 h-100 d-flex flex-column align-items-center justify-content-center text-center rounded-3 metode-card" for="metode_qris">
                  <i class="mdi mdi-qrcode-scan fs-2 text-info mb-1"></i>
                  <span class="fw-bold d-block text-dark small">QRIS</span>
                  <small class="text-muted" style="font-size: 0.7rem;">Scan barcode</small>
                </label>
              </div>
              <div class="col-6 col-sm-3">
                <input type="radio" class="btn-check" name="metode_bayar_radio" id="metode_ewallet" value="ewallet" autocomplete="off">
                <label class="btn btn-outline-secondary w-100 p-3 h-100 d-flex flex-column align-items-center justify-content-center text-center rounded-3 metode-card" for="metode_ewallet">
                  <i class="mdi mdi-cellphone-nfc fs-2 text-warning mb-1"></i>
                  <span class="fw-bold d-block text-dark small">E-Wallet</span>
                  <small class="text-muted" style="font-size: 0.7rem;">Dana, Ovo, Gopay</small>
                </label>
              </div>
            </div>
            <input type="hidden" id="metode_bayar" name="metode_bayar" value="cod">
          </div>

          <!-- Step 2: Kalkulator Kembalian Tunai (Muncul saat COD / Tunai) -->
          <div id="cash-calculator-box" class="card border border-success border-opacity-25 bg-success-subtle p-3 rounded-3 mb-3">
            <div class="row align-items-center g-2">
              <div class="col-sm-6">
                <label for="cash-received" class="form-label fw-bold text-dark small mb-1">Uang Diterima dari Pelanggan</label>
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-white fw-bold">Rp</span>
                  <input type="number" class="form-control form-control-sm fw-bold fs-6" id="cash-received" placeholder="0">
                </div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-mode="exact">Uang Pas</button>
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-amount="50000">50rb</button>
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-amount="100000">100rb</button>
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-amount="200000">200rb</button>
                  <button type="button" class="btn btn-xs btn-outline-dark btn-quick-cash" data-amount="500000">500rb</button>
                </div>
              </div>
              <div class="col-sm-6 text-sm-end pt-2 pt-sm-0 border-sm-start border-success border-opacity-25 ps-sm-3">
                <div class="small text-muted fw-semibold">Uang Kembalian</div>
                <div class="fs-3 fw-bolder text-success" id="cash-change-display">Rp 0</div>
                <div class="small text-muted" id="cash-change-status">Uang pas / pas bayar</div>
              </div>
            </div>
          </div>

          <!-- Step 3: Keterangan Catatan Tambahan -->
          <div class="mb-2">
            <label for="keterangan_bayar" class="form-label fw-bold text-dark small mb-1">
              Catatan / Referensi Transaksi (Opsional)
            </label>
            <input type="text" id="keterangan_bayar" name="keterangan" class="form-control form-control-sm"
              placeholder="Contoh: Titip ke Bu Siti / Ref transfer BCA / Bayar via kurir">
          </div>
        </form>
      </div>

      <div class="modal-footer bg-white border-top p-3 d-flex align-items-center justify-content-between">
        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">
          <i class="mdi mdi-close me-1"></i>Batal
        </button>
        <button type="submit" form="form-bayar" class="btn btn-success btn-lg px-4 shadow-sm fw-bold d-flex align-items-center gap-2" id="btn-proses-bayar">
          <i class="mdi mdi-check-circle fs-5"></i>
          <span>Konfirmasi & Lunaskan Tagihan</span>
        </button>
      </div>
    </div>
  </div>
</div>

@endsection
