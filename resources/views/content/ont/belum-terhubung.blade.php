@extends('layouts/layoutMaster')

@section('title', 'ONT Belum Terhubung')

@section('page-script')
<script>
  $(function () {
    const csrf = $('meta[name="csrf-token"]').attr('content');
    toastr.options.escapeHtml = true; // pesan memuat nama pelanggan
    const $modal = $('#modal-hubungkan');
    const $pilih = $('#pilih-pelanggan');

    $pilih.select2({ placeholder: 'Cari nama atau PPPoE', dropdownParent: $modal, width: '100%' });

    $('.btn-hubungkan').on('click', function () {
      $('#hubungkan-sn').text($(this).data('sn'));
      $modal.data('sn', $(this).data('sn'));
      $pilih.val(null).trigger('change');
      bootstrap.Modal.getOrCreateInstance($modal[0]).show();
    });

    function kirim(pindahkan) {
      const $btn = $('#btn-simpan-hubungkan');
      const labelAwal = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menyimpan...');
      $.ajax({
        url: @json(route('ont.hubungkan')), method: 'POST', headers: { 'X-CSRF-TOKEN': csrf },
        data: { sn: $modal.data('sn'), pelanggan: $pilih.val(), pindahkan: pindahkan ? 1 : 0 },
      }).done(function (res) {
        toastr.success(res.message);
        setTimeout(() => location.reload(), 700);
      }).fail(function (xhr) {
        const r = xhr.responseJSON || {};
        if (xhr.status === 409) {
          Swal.fire({
            title: 'Pindahkan ONT?', text: r.message, icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Ya, pindahkan', cancelButtonText: 'Batal',
            customClass: { confirmButton: 'btn btn-warning me-2', cancelButton: 'btn btn-outline-secondary' }, buttonsStyling: false,
          }).then(x => { if (x.isConfirmed) kirim(true); });
          return;
        }
        toastr.error(r.errors ? Object.values(r.errors)[0][0] : (r.message || 'Terjadi kesalahan.'));
      }).always(() => $btn.prop('disabled', false).html(labelAwal));
    }

    $('#form-hubungkan').on('submit', function (e) {
      e.preventDefault();
      if (!$pilih.val()) { toastr.warning('Pilih pelanggan dulu.'); return; }
      kirim(false);
    });
  });
</script>
@endsection

@section('content')
<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">ONT Belum Terhubung</h5>
    <small class="text-muted">ONT yang lapor ke GenieACS tapi belum dikaitkan ke pelanggan.</small>
  </div>

  @if (!$aktif)
    <div class="card-body"><div class="alert alert-secondary mb-0">{{ $pesanBelumDiatur }}</div></div>
  @elseif ($galat)
    <div class="card-body"><div class="alert alert-warning mb-0"><i class="mdi mdi-lan-disconnect me-1"></i> {{ $galat }}</div></div>
  @elseif (!$ont)
    <div class="card-body"><p class="text-muted mb-0">Semua ONT sudah terhubung ke pelanggan.</p></div>
  @else
    <div class="list-group list-group-flush">
      @foreach ($ont as $o)
      <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 px-4">
        <div class="min-w-0">
          <div class="fw-medium text-break">{{ $o['sn'] }}</div>
          <small class="text-muted d-block">
            {{ $o['model'] }} · PPPoE {{ $o['pppoe'] ?? '-' }}
            @if ($o['rxPower'] !== null) · {{ number_format($o['rxPower'], 1, ',', '.') }} dBm @endif
          </small>
          <small class="text-{{ $o['online'] ? 'success' : 'muted' }}">
            {{ $o['online'] ? 'online' : 'offline' }}@if ($o['lastInform']) · lapor {{ $o['lastInform']->locale('id')->diffForHumans() }}@endif
          </small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary btn-hubungkan" data-sn="{{ $o['sn'] }}">
          <i class="mdi mdi-link-variant me-1"></i> Hubungkan
        </button>
      </div>
      @endforeach
    </div>
  @endif
</div>

<div class="modal fade" id="modal-hubungkan" tabindex="-1" aria-labelledby="judul-hubungkan" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="form-hubungkan">
      <div class="modal-header">
        <h5 class="modal-title" id="judul-hubungkan">Hubungkan <span id="hubungkan-sn" class="text-break"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <label for="pilih-pelanggan" class="form-label">Pelanggan</label>
        <select id="pilih-pelanggan" class="form-select">
          <option></option>
          @foreach ($pelanggan as $p)
          <option value="{{ encrypt($p->id) }}">{{ $p->name }} — {{ $p->user_mikrotik->namaMikrotikUser ?? '-' }}{{ $p->user_mikrotik->ont_sn ? ' (sudah ada ONT)' : '' }}</option>
          @endforeach
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="btn-simpan-hubungkan">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection
