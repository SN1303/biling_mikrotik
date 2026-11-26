@extends('layouts/layoutMaster')

@section('title', 'ONT · ' . $pelanggan->name)

@php
  $ont = ($hasil['status'] ?? null) === 'terhubung' ? $hasil['ont'] : null;
  $redaman = null;
  if ($ont && $ont['rxPower'] !== null) {
    $rx = $ont['rxPower'];
    $redaman = $rx >= -25 ? ['baik', 'success'] : ($rx >= -27 ? ['waspada', 'warning'] : ['buruk', 'danger']);
  }
  $dualBand = $ont && $ont['dualBand'];
  $bisaHubungkan = Route::has('ont.hubungkan');
@endphp

@section('page-script')
<script>
  $(function () {
    const csrf = $('meta[name="csrf-token"]').attr('content');
    toastr.options.escapeHtml = true; // pesan memuat nama pelanggan / SSID

    function kirim(url, data, $btn, setelahSelesai) {
      const labelAwal = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memproses...');
      $.ajax({ url, method: 'POST', data, headers: { 'X-CSRF-TOKEN': csrf } })
        .done(function (res) {
          if (res.status === 'selesai') { toastr.success(res.message); if (setelahSelesai) setelahSelesai(); }
          else if (res.status === 'antri' || res.status === 'terkirim') toastr.warning(res.message);
          else toastr.error(res.message);
        })
        .fail(function (xhr) {
          const r = xhr.responseJSON || {};
          if (xhr.status === 409) { // SN milik pelanggan lain: minta konfirmasi pindah
            Swal.fire({
              title: 'Pindahkan ONT?', text: r.message, icon: 'warning', showCancelButton: true,
              confirmButtonText: 'Ya, pindahkan', cancelButtonText: 'Batal',
              customClass: { confirmButton: 'btn btn-warning me-2', cancelButton: 'btn btn-outline-secondary' }, buttonsStyling: false,
            }).then(x => { if (x.isConfirmed) kirim(url, Object.assign({}, data, { pindahkan: 1 }), $btn, setelahSelesai); });
            return;
          }
          const galat = r.errors ? Object.values(r.errors)[0][0] : null;
          toastr.error(galat || r.message || 'Terjadi kesalahan.');
        })
        .always(function () { $btn.prop('disabled', false).html(labelAwal); });
    }

    $('#btn-refresh').on('click', function () {
      kirim(@json(route('ont.refresh', $id)), {}, $(this), () => setTimeout(() => location.reload(), 800));
    });

    $('#btn-reboot').on('click', function () {
      const $btn = $(this);
      Swal.fire({
        title: 'Reboot ONT?',
        text: 'Internet pelanggan akan putus sekitar 1–2 menit.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, reboot',
        cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-outline-secondary' },
        buttonsStyling: false,
      }).then(r => { if (r.isConfirmed) kirim(@json(route('ont.reboot', $id)), {}, $btn); });
    });

    // Lihat / sembunyikan password WiFi
    $('.btn-lihat').on('click', function () {
      const $nilai = $('#' + $(this).data('target'));
      const terlihat = $nilai.text() !== '••••••••';
      $nilai.text(terlihat ? '••••••••' : $nilai.attr('data-nilai')); // attr: jangan diurai sebagai JSON
      $(this).text(terlihat ? 'lihat' : 'sembunyikan');
    });

    // Form ganti WiFi: kolom 5 GHz hanya untuk pita 5 GHz / keduanya
    const $form = $('#form-wifi');
    let ssid5Diubah = false;
    function aturPita() {
      const pita = $form.find('[name="band"]:checked').val();
      $form.find('.kolom-24').toggleClass('d-none', pita === '5');
      $form.find('.kolom-5').toggleClass('d-none', pita === '24');
      $form.find('.kolom-password5').toggleClass('d-none', pita !== '5');
      $('#bantuan-keduanya').toggleClass('d-none', pita !== 'keduanya');
    }
    $form.find('[name="band"]').on('change', aturPita);
    $('#ssid5').on('input', () => { ssid5Diubah = true; });
    $('#ssid').on('input', function () {
      if ($form.find('[name="band"]:checked').val() === 'keduanya' && !ssid5Diubah) {
        $('#ssid5').val(this.value ? this.value + '-5G' : '');
      }
    });
    aturPita();

    $form.on('submit', function (e) {
      e.preventDefault();
      const pita = $form.find('[name="band"]:checked').val();
      const data = { band: pita };
      if (pita !== '5') { data.ssid = $('#ssid').val(); data.password = $('#password').val(); }
      if (pita !== '24') { data.ssid5 = $('#ssid5').val(); }
      if (pita === '5') { data.password5 = $('#password5').val(); }
      const $btn = $('#btn-simpan-wifi');
      Swal.fire({
        title: 'Ganti WiFi?',
        text: 'Perangkat yang tersambung ke WiFi ini akan terputus dan harus menyambung ulang.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, ganti',
        cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-outline-secondary' },
        buttonsStyling: false,
      }).then(r => { if (r.isConfirmed) kirim(@json(route('ont.wifi', $id)), data, $btn, () => setTimeout(() => location.reload(), 800)); });
    });

    $('.btn-pilih-ont').on('click', function () {
      kirim(@json($bisaHubungkan ? route('ont.hubungkan') : ''), { sn: $(this).data('sn'), pelanggan: @json($id) }, $(this), () => location.reload());
    });

    // Remote web ONT: minta tiket baru setiap kali dibuka / dimuat ulang
    function bukaRemote() {
      const $btn = $('#btn-remote');
      const labelAwal = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Membuka...');
      $.ajax({ url: @json($ont ? route('ont.remote', $id) : ''), method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } })
        .done(function (res) {
          // sandbox tanpa izin navigasi atas: web ONT tidak bisa membelokkan halaman billing
          $('#remote-bingkai').html('').append($('<iframe>', { src: res.url, title: 'Web ONT', class: 'remote-iframe',
            sandbox: 'allow-scripts allow-forms allow-same-origin allow-popups allow-modals allow-downloads' }));
          $('#remote-area').removeClass('d-none');
        })
        .fail(function (xhr) { toastr.error((xhr.responseJSON || {}).message || 'Gagal membuka remote ONT.'); })
        .always(function () { $btn.prop('disabled', false).html(labelAwal); });
    }
    $('#btn-remote, #btn-remote-muat').on('click', bukaRemote);
    $('#btn-remote-tutup').on('click', function () { $('#remote-bingkai').html(''); $('#remote-area').addClass('d-none'); });
  });
</script>
@endsection

@section('content')
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="min-w-0">
      <h5 class="mb-0">ONT &amp; WiFi</h5>
      <small class="text-muted text-truncate d-block">{{ $pelanggan->name }}</small>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('user.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="mdi mdi-arrow-left me-1"></i> Daftar Pelanggan
      </a>
      @if ($ont)
      <button type="button" id="btn-refresh" class="btn btn-sm btn-outline-primary">
        <i class="mdi mdi-refresh me-1"></i> Refresh
      </button>
      @endif
    </div>
  </div>

  <div class="card-body">
    @if (!$aktif)
      <div class="alert alert-secondary mb-0">{{ $pesanBelumDiatur }}</div>
    @elseif ($galat)
      <div class="alert alert-warning mb-0"><i class="mdi mdi-lan-disconnect me-1"></i> {{ $galat }}</div>
    @elseif (($hasil['status'] ?? 'belum') === 'kandidat')
      <p class="mb-3">Username PPPoE <strong>{{ $pelanggan->user_mikrotik->namaMikrotikUser }}</strong> cocok dengan lebih dari satu ONT. Pilih ONT milik pelanggan ini:</p>
      <div class="list-group">
        @foreach ($hasil['kandidat'] as $k)
        <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="min-w-0">
            <div class="fw-medium text-break">{{ $k['sn'] }}</div>
            <small class="text-muted">{{ $k['model'] }} · {{ $k['online'] ? 'online' : 'offline' }}@if ($k['lastInform']) · lapor {{ $k['lastInform']->locale('id')->diffForHumans() }}@endif</small>
          </div>
          @if ($bisaHubungkan)
          <button type="button" class="btn btn-sm btn-primary btn-pilih-ont" data-sn="{{ $k['sn'] }}">Pilih</button>
          @endif
        </div>
        @endforeach
      </div>
    @elseif (!$ont)
      <div class="alert alert-secondary mb-0">
        ONT pelanggan ini belum terhubung. Pastikan username PPPoE di ONT sama dengan di billing
        (<strong>{{ $pelanggan->user_mikrotik->namaMikrotikUser ?? '-' }}</strong>)@if (Route::has('ont.belum')), atau hubungkan dari
        <a href="{{ route('ont.belum') }}">daftar ONT belum terhubung</a>@endif.
      </div>
    @else
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge rounded-pill bg-label-{{ $ont['online'] ? 'success' : 'secondary' }}">
          <i class="mdi mdi-circle mdi-10px me-1"></i>{{ $ont['online'] ? 'Online' : 'Offline' }}
        </span>
        @if ($ont['lastInform'])
        <small class="text-muted">lapor {{ $ont['lastInform']->locale('id')->diffForHumans() }}</small>
        @endif
      </div>

      <dl class="row mb-0 ont-detail">
        <dt class="col-sm-3 col-xl-2">Redaman</dt>
        <dd class="col-sm-9 col-xl-10">
          @if ($redaman)
            <span class="fw-medium text-{{ $redaman[1] }}">{{ number_format($ont['rxPower'], 1, ',', '.') }} dBm</span>
            <span class="badge bg-label-{{ $redaman[1] }} ms-1">{{ $redaman[0] }}</span>
          @else
            <span class="text-muted">tidak terbaca</span>
          @endif
        </dd>

        <dt class="col-sm-3 col-xl-2">Serial Number</dt>
        <dd class="col-sm-9 col-xl-10 text-break">{{ $ont['sn'] }} <span class="text-muted">· {{ $ont['model'] }}</span></dd>

        <dt class="col-sm-3 col-xl-2">PPPoE</dt>
        <dd class="col-sm-9 col-xl-10 text-break">{{ $ont['pppoe'] ?? '-' }}</dd>

        @foreach (array_filter(['24' => ['WiFi 2,4 GHz', 'ssid', 'password'], '5' => $dualBand ? ['WiFi 5 GHz', 'ssid5', 'password5'] : null]) as $pita => [$judul, $kSsid, $kPass])
        <dt class="col-sm-3 col-xl-2">{{ $judul }}</dt>
        <dd class="col-sm-9 col-xl-10">
          <span class="fw-medium text-break">{{ $ont['wifi'][$kSsid] ?? 'nama tidak terbaca' }}</span>
          <span class="d-inline-flex align-items-center gap-1 ms-sm-2">
            @if ($ont['wifi'][$kPass] !== null)
              <code id="pass-{{ $pita }}" data-nilai="{{ $ont['wifi'][$kPass] }}">••••••••</code>
              <button type="button" class="btn btn-link btn-sm p-0 btn-lihat" data-target="pass-{{ $pita }}">lihat</button>
            @else
              <small class="text-muted">password tidak bisa dibaca (dilindungi ONT)</small>
            @endif
          </span>
        </dd>
        @endforeach
      </dl>
    @endif
  </div>
</div>

@if ($ont)
<div class="card mb-4">
  <div class="card-header"><h6 class="mb-0">Ganti WiFi</h6></div>
  @if (!$ont['jalurWifi']['ssid'])
  {{-- Jalur WiFi ONT ini tidak dikenal (lihat GenieAcsService::JALUR_WIFI); menulis tidak akan sampai ke ONT --}}
  <div class="card-body">
    <p class="text-muted">Nama WiFi ONT ini belum bisa dibaca, jadi belum bisa diganti dari billing. Tekan Refresh; bila tetap begini, merek ONT ini memakai jalur WiFi yang belum dikenal.</p>
    <button type="button" id="btn-reboot" class="btn btn-outline-danger">
      <i class="mdi mdi-restart me-1"></i> Reboot ONT
    </button>
  </div>
  @else
  <form id="form-wifi" class="card-body" autocomplete="off">
    @if ($dualBand)
    <div class="d-flex flex-wrap gap-3 mb-3" role="radiogroup" aria-label="Pita WiFi">
      <div class="form-check"><input class="form-check-input" type="radio" name="band" id="band-24" value="24" checked><label class="form-check-label" for="band-24">2,4 GHz</label></div>
      <div class="form-check"><input class="form-check-input" type="radio" name="band" id="band-5" value="5"><label class="form-check-label" for="band-5">5 GHz</label></div>
      <div class="form-check"><input class="form-check-input" type="radio" name="band" id="band-keduanya" value="keduanya"><label class="form-check-label" for="band-keduanya">Keduanya</label></div>
    </div>
    @else
    {{-- ONT single-band: pita selalu 2,4 GHz --}}
    <input type="radio" name="band" value="24" checked class="d-none" aria-hidden="true" tabindex="-1">
    @endif

    <div class="row g-4">
      <div class="col-md-6 kolom-24">
        <div class="form-floating form-floating-outline">
          <input type="text" id="ssid" class="form-control" maxlength="32" placeholder="Nama WiFi" value="{{ $ont['wifi']['ssid'] }}">
          <label for="ssid">Nama WiFi{{ $dualBand ? ' 2,4 GHz' : '' }}</label>
        </div>
      </div>
      <div class="col-md-6 kolom-24">
        <div class="form-floating form-floating-outline">
          <input type="password" id="password" class="form-control" minlength="8" maxlength="63" placeholder="Password baru" autocomplete="new-password">
          <label for="password">Password baru</label>
        </div>
        <div class="form-text">8–63 karakter. Kosongkan jika tidak diganti.</div>
      </div>
      @if ($dualBand)
      <div class="col-md-6 kolom-5">
        <div class="form-floating form-floating-outline">
          <input type="text" id="ssid5" class="form-control" maxlength="32" placeholder="Nama WiFi 5 GHz" value="{{ $ont['wifi']['ssid5'] }}">
          <label for="ssid5">Nama WiFi 5 GHz</label>
        </div>
        <div class="form-text d-none" id="bantuan-keduanya">Password 5 GHz ikut sama dengan 2,4 GHz.</div>
      </div>
      <div class="col-md-6 kolom-5 kolom-password5">
        <div class="form-floating form-floating-outline">
          <input type="password" id="password5" class="form-control" minlength="8" maxlength="63" placeholder="Password baru 5 GHz" autocomplete="new-password">
          <label for="password5">Password baru 5 GHz</label>
        </div>
        <div class="form-text">8–63 karakter. Kosongkan jika tidak diganti.</div>
      </div>
      @endif
    </div>

    <div class="pt-4 d-flex flex-wrap justify-content-between gap-2">
      <button type="submit" id="btn-simpan-wifi" class="btn btn-primary">
        <i class="mdi mdi-wifi-cog me-1"></i> Simpan WiFi
      </button>
      <button type="button" id="btn-reboot" class="btn btn-outline-danger">
        <i class="mdi mdi-restart me-1"></i> Reboot ONT
      </button>
    </div>
  </form>
  @endif
</div>

@if ($remoteAktif)
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h6 class="mb-0">Remote ONT</h6>
    <button type="button" id="btn-remote" class="btn btn-sm btn-primary">
      <i class="mdi mdi-monitor-share me-1"></i> Buka remote
    </button>
  </div>
  <div class="card-body">
    @php $loginOnt = array_filter(['Login admin' => $ont['login']['admin'], 'Login user' => $ont['login']['user']], fn($l) => $l['user'] !== null); @endphp
    @if ($loginOnt)
    <dl class="row mb-3">
      @foreach ($loginOnt as $judul => $l)
      <dt class="col-sm-3 col-xl-2">{{ $judul }}</dt>
      <dd class="col-sm-9 col-xl-10">
        <span class="fw-medium text-break">{{ $l['user'] }}</span>
        @if ($l['pass'] !== null)
        <span class="d-inline-flex align-items-center gap-1 ms-sm-2">
          <code id="login-{{ $loop->index }}" data-nilai="{{ $l['pass'] }}">••••••••</code>
          <button type="button" class="btn btn-link btn-sm p-0 btn-lihat" data-target="login-{{ $loop->index }}">lihat</button>
        </span>
        @endif
      </dd>
      @endforeach
    </dl>
    @endif
    <p class="text-muted small mb-0" id="remote-keterangan">Web ONT dibuka lewat server billing{{ $ont['ip'] ? ' (' . $ont['ip'] . ')' : '' }}. Sesi remote berakhir sendiri setelah 2 jam atau saat kamu logout.</p>

    <div id="remote-area" class="d-none mt-3">
      <div id="remote-bingkai" class="remote-bingkai"></div>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <button type="button" id="btn-remote-muat" class="btn btn-sm btn-outline-primary"><i class="mdi mdi-refresh me-1"></i> Muat ulang</button>
        <button type="button" id="btn-remote-tutup" class="btn btn-sm btn-outline-secondary"><i class="mdi mdi-close me-1"></i> Tutup</button>
      </div>
    </div>
  </div>
</div>
@endif
@endif
@endsection

@section('page-style')
<style>
  .remote-bingkai { border: 1px solid var(--mryes-border, #e5e7eb); border-radius: .5rem; overflow: hidden; background: #fff; }
  .remote-iframe { display: block; width: 100%; height: 70vh; min-height: 420px; border: 0; }
</style>
@endsection
