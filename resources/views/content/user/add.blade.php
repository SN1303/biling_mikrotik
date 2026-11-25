@extends('layouts/layoutMaster')

@section('title', 'Tambah Pelanggan Baru & Provisioning PPPoE')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/leaflet/leaflet.css') }}" />
<style>
  .step-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    font-size: 13px;
    font-weight: 700;
  }
  .pppoe-highlight-box {
    background: linear-gradient(135deg, rgba(105, 108, 255, 0.08) 0%, rgba(105, 108, 255, 0.02) 100%);
    border: 1px solid rgba(105, 108, 255, 0.25);
    border-radius: 0.75rem;
  }
  .coord-status-badge {
    transition: all 0.3s ease;
  }
  #mini-map-customer {
    height: 300px;
    width: 100%;
    border-radius: 0.5rem;
    z-index: 1;
  }
  .input-icon-btn {
    cursor: pointer;
    background: transparent;
    border: none;
  }
</style>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/leaflet/leaflet.js') }}"></script>
@endsection

@section('page-script')
<script>
  // Data paket terkelompok per kategori dari backend
  const paketGrouped = @json(Helper::getPaketGrouped());

  $(document).ready(function() {
    // 1. Inisialisasi Select2 pada dropdown
    $('.select2-init').each(function() {
      $(this).select2({
        width: '100%',
        dropdownParent: $(this).closest('.form-floating, .card-body')
      });
    });

    // 2. Leaflet Mini Map Setup
    let initialLat = -5.2600;
    let initialLng = 105.7000;
    let map = L.map('mini-map-customer', { maxZoom: 21 }).setView([initialLat, initialLng], 11);

    // Definisi Layer Peta (Google Satellite, Google Hybrid, Google Roads, OpenStreetMap)
    const googleHybrid = L.tileLayer('https://{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
      maxZoom: 21,
      maxNativeZoom: 20,
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: '© Google Maps'
    });

    const googleSat = L.tileLayer('https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
      maxZoom: 21,
      maxNativeZoom: 20,
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: '© Google Maps'
    });

    const googleStreets = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
      maxZoom: 21,
      maxNativeZoom: 20,
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: '© Google Maps'
    });

    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 21,
      maxNativeZoom: 19,
      attribution: '© OpenStreetMap'
    });

    // Default aktif: Google Satelit Hybrid (paling disukai teknisi untuk melihat atap rumah & jalan)
    googleHybrid.addTo(map);

    const baseMaps = {
      "Satelit Hybrid": googleHybrid,
      "Satelit Murni": googleSat,
      "Google Jalan": googleStreets,
      "OpenStreetMap": osm
    };

    // Kontrol layer Leaflet bawaan (pojok kanan atas)
    L.control.layers(baseMaps, null, { position: 'topright' }).addTo(map);

    // Event handler tombol switcher visual di atas peta
    $('.btn-map-type').on('click', function() {
      const selected = $(this).data('layer');
      [googleHybrid, googleSat, googleStreets, osm].forEach(l => {
        if (map.hasLayer(l)) map.removeLayer(l);
      });

      if (selected === 'googleHybrid') googleHybrid.addTo(map);
      else if (selected === 'googleSat') googleSat.addTo(map);
      else if (selected === 'googleStreets') googleStreets.addTo(map);
      else if (selected === 'osm') osm.addTo(map);

      $('.btn-map-type').removeClass('btn-primary active').addClass('btn-outline-secondary');
      $(this).removeClass('btn-outline-secondary').addClass('btn-primary active');
    });

    // Sinkronisasi tombol ketika layer diganti via Leaflet control
    map.on('baselayerchange', function(e) {
      $('.btn-map-type').removeClass('btn-primary active').addClass('btn-outline-secondary');
      if (e.layer === googleHybrid) $('[data-layer="googleHybrid"]').removeClass('btn-outline-secondary').addClass('btn-primary active');
      else if (e.layer === googleSat) $('[data-layer="googleSat"]').removeClass('btn-outline-secondary').addClass('btn-primary active');
      else if (e.layer === googleStreets) $('[data-layer="googleStreets"]').removeClass('btn-outline-secondary').addClass('btn-primary active');
      else if (e.layer === osm) $('[data-layer="osm"]').removeClass('btn-outline-secondary').addClass('btn-primary active');
    });

    let customerMarker = null;

    function updateMarker(lat, lng, zoom = 16) {
      if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
        const latLng = [parseFloat(lat), parseFloat(lng)];
        if (!customerMarker) {
          customerMarker = L.marker(latLng, { draggable: true }).addTo(map);
          customerMarker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            $('#latitude').val(pos.lat.toFixed(6));
            $('#longitude').val(pos.lng.toFixed(6));
            $('#coord-badge').removeClass('d-none').html('<i class="mdi mdi-check-circle text-success me-1"></i> Koordinat diset dari pin peta');
          });
        } else {
          customerMarker.setLatLng(latLng);
        }
        map.setView(latLng, zoom);
        $('#coord-badge').removeClass('d-none').html('<i class="mdi mdi-map-marker-check text-success me-1"></i> Titik terpasang: ' + parseFloat(lat).toFixed(5) + ', ' + parseFloat(lng).toFixed(5));
      }
    }

    // Klik pada peta untuk set koordinat
    map.on('click', function(e) {
      const lat = e.latlng.lat.toFixed(6);
      const lng = e.latlng.lng.toFixed(6);
      $('#latitude').val(lat);
      $('#longitude').val(lng);
      updateMarker(lat, lng);
    });

    // Perubahan manual pada input lat/lng
    $('#latitude, #longitude').on('input', function() {
      const lat = parseFloat($('#latitude').val());
      const lng = parseFloat($('#longitude').val());
      if (!isNaN(lat) && !isNaN(lng)) {
        updateMarker(lat, lng);
      }
    });

    // 3. Logika Ekstrak Koordinat dari Google Maps URL / Teks Koordinat
    function extractCoordinatesFromUrl(url) {
      if (!url || !url.trim()) return;

      let rawUrl = url.trim();

      // Ekstrak URL jika ada teks pengantar (misal dari pesan WA: "Lokasi: https://...")
      const urlRegex = /(https?:\/\/[^\s]+)/i;
      const urlMatch = rawUrl.match(urlRegex);
      if (urlMatch) {
        rawUrl = urlMatch[1].replace(/[.,;:()\[\]"'<>]+$/, '');
      } else {
        const domainRegex = /((?:maps\.app\.goo\.gl|goo\.gl\/maps|maps\.google\.com|google\.com\/maps)[^\s]*)/i;
        const domainMatch = rawUrl.match(domainRegex);
        if (domainMatch) {
          rawUrl = 'https://' + domainMatch[1].replace(/[.,;:()\[\]"'<>]+$/, '');
        }
      }

      // Decode URL entities dan %2C
      let decodedUrl = rawUrl;
      try {
        decodedUrl = decodeURIComponent(rawUrl);
      } catch (e) {
        decodedUrl = rawUrl;
      }

      function applyCoord(lat, lng, sourceLabel) {
        lat = parseFloat(lat);
        lng = parseFloat(lng);
        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180 && (lat !== 0 || lng !== 0)) {
          $('#latitude').val(lat.toFixed(6));
          $('#longitude').val(lng.toFixed(6));
          updateMarker(lat, lng);
          toastr.success('Koordinat berhasil diekstrak (' + sourceLabel + ')!');
          return true;
        }
        return false;
      }

      // Pola 1: @lat,lng dalam URL (misal: @-5.382123,105.258123,17z)
      const atRegex = /@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/;
      const atMatch = decodedUrl.match(atRegex);
      if (atMatch && applyCoord(atMatch[1], atMatch[2], 'link Google Maps')) return;

      // Pola 2: URL params: ?q=, &ll=, query=, daddr=, saddr=, center=, destination=
      const qRegex = /(?:[?&](?:q|query|ll|daddr|saddr|center|destination)=(?:loc:)?)(-?\d{1,2}\.\d+)(?:,|\+|\s)+(-?\d{1,3}\.\d+)/i;
      const qMatch = decodedUrl.match(qRegex);
      if (qMatch && applyCoord(qMatch[1], qMatch[2], 'parameter link')) return;

      // Pola 3: Path /search/lat,lng atau /place/lat,lng atau /dir/.../lat,lng
      const searchRegex = /(?:\/search|\/place|\/dir(?:\/[^\/]*)*)\/(-?\d{1,2}\.\d+)(?:,|\+|\s)+\+?(-?\d{1,3}\.\d+)/i;
      const searchMatch = decodedUrl.match(searchRegex);
      if (searchMatch && applyCoord(searchMatch[1], searchMatch[2], 'link navigasi/pencarian')) return;

      // Pola 4: Internal data=!3dlat!4dlng
      const dataRegex = /!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/;
      const dataMatch = decodedUrl.match(dataRegex);
      if (dataMatch && applyCoord(dataMatch[1], dataMatch[2], 'data lokasi Google')) return;

      // Pola 5: Staticmap center=lat,lng
      const centerRegex = /center=(-?\d{1,2}\.\d+)(?:%2C|,)(-?\d{1,3}\.\d+)/i;
      const centerMatch = decodedUrl.match(centerRegex);
      if (centerMatch && applyCoord(centerMatch[1], centerMatch[2], 'embed peta')) return;

      // Pola 6: Format standar lat, lng angka langsung (-5.123456, 105.123456 atau -5.123456 105.123456)
      const directRegex = /(-?\d{1,2}\.\d+)[\s,;]+\+?(-?\d{1,3}\.\d+)/;
      const directMatch = decodedUrl.match(directRegex);
      if (directMatch && applyCoord(directMatch[1], directMatch[2], 'input koordinat langsung')) return;

      // Pola 7: Format Derajat Menit Detik (DMS): 5°22'55.6"S 105°46'52.4"E
      const dmsRegex = /(\d+)[°\s]+(\d+)[\'\s]+(\d+(?:\.\d+)?)["\s]*([NS])[,;\s]+(\d+)[°\s]+(\d+)[\'\s]+(\d+(?:\.\d+)?)["\s]*([EW])/i;
      const dmsMatch = decodedUrl.match(dmsRegex);
      if (dmsMatch) {
        let lat = parseFloat(dmsMatch[1]) + (parseFloat(dmsMatch[2]) / 60.0) + (parseFloat(dmsMatch[3]) / 3600.0);
        if (dmsMatch[4].toUpperCase() === 'S') lat = -lat;
        let lng = parseFloat(dmsMatch[5]) + (parseFloat(dmsMatch[6]) / 60.0) + (parseFloat(dmsMatch[7]) / 3600.0);
        if (dmsMatch[8].toUpperCase() === 'W') lng = -lng;
        if (applyCoord(lat, lng, 'format derajat DMS')) return;
      }

      // Pola 8: Indikator arah: 5.123456S, 105.123456E
      const cardRegex = /([NS])?\s*([+-]?\d{1,2}\.\d+)\s*([NS])?[\s,;]+([EW])?\s*([+-]?\d{1,3}\.\d+)\s*([EW])?/i;
      const cardMatch = decodedUrl.match(cardRegex);
      if (cardMatch) {
        const dirLat = (cardMatch[1] || cardMatch[3] || '').toUpperCase();
        let lat = parseFloat(cardMatch[2]);
        if (dirLat === 'S') lat = -Math.abs(lat);
        const dirLng = (cardMatch[4] || cardMatch[6] || '').toUpperCase();
        let lng = parseFloat(cardMatch[5]);
        if (dirLng === 'W') lng = -Math.abs(lng);
        if (applyCoord(lat, lng, 'format mata angin')) return;
      }

      // Jika belum terdeteksi secara instan (misal shortlink maps.app.goo.gl), kirim ke endpoint server
      $('#btn-extract-maps').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Mengurai...');
      $.ajax({
        url: "{{ route('user.extract-maps') }}",
        method: "GET",
        data: {
          url: rawUrl
        },
        success: function(res) {
          $('#btn-extract-maps').prop('disabled', false).html('<i class="mdi mdi-crosshairs-gps me-1"></i> Ekstrak');
          if (res.success && res.latitude && res.longitude) {
            applyCoord(res.latitude, res.longitude, 'shortlink server');
          } else {
            toastr.warning('Tidak dapat mengekstrak koordinat dari link tersebut. Silakan klik langsung titik lokasi pada peta.');
          }
        },
        error: function() {
          $('#btn-extract-maps').prop('disabled', false).html('<i class="mdi mdi-crosshairs-gps me-1"></i> Ekstrak');
          toastr.error('Gagal menghubungi server untuk parsing URL.');
        }
      });
    }

    $('#btn-extract-maps').on('click', function() {
      extractCoordinatesFromUrl($('#google_map_url').val());
    });

    let mapsInputTimer = null;
    $('#google_map_url').on('paste change blur', function() {
      setTimeout(function() {
        extractCoordinatesFromUrl($('#google_map_url').val());
      }, 150);
    }).on('input', function() {
      clearTimeout(mapsInputTimer);
      mapsInputTimer = setTimeout(function() {
        extractCoordinatesFromUrl($('#google_map_url').val());
      }, 800);
    });

    // 4. Logika Otomatisasi Generate Kredensial PPPoE
    let debounceTimer = null;
    let isManuallyEdited = false;

    function triggerGenerateCredentials() {
      const name = $('#nama').val().trim();
      const odpId = $('#odp-select').val();

      if (!name) {
        return;
      }

      $('#btn-regen-pppoe').html('<span class="spinner-border spinner-border-sm text-primary"></span>');

      $.ajax({
        url: "{{ route('user.generate-credentials') }}",
        method: "GET",
        data: {
          name: name,
          odp: odpId || ''
        },
        success: function(res) {
          $('#btn-regen-pppoe').html('<i class="mdi mdi-refresh"></i>');
          if (res.username && res.password) {
            $('#pppoe_username').val(res.username);
            $('#pppoe_password').val(res.password);
            $('#pppoe-badge-info').removeClass('d-none').html('<i class="mdi mdi-check-decagram text-primary me-1"></i> Kredensial siap: <code>' + res.username + '</code>');
          }
        },
        error: function() {
          $('#btn-regen-pppoe').html('<i class="mdi mdi-refresh"></i>');
        }
      });
    }

    // Input nama memicu auto-generate dengan debounce
    $('#nama').on('input', function() {
      if (!isManuallyEdited) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(triggerGenerateCredentials, 400);
      }
    });

    // ODP change memicu auto-generate
    $('#odp-select').on('change', function() {
      const selectedOpt = $(this).find('option:selected');
      const portTotal = selectedOpt.data('port');
      const portSisa = selectedOpt.data('sisa');

      if (portTotal) {
        $('#odp-port-info').removeClass('d-none').html('Kapasitas: <strong>' + portTotal + ' Port</strong> | Tersisa: <span class="badge bg-label-success">' + portSisa + ' Port</span>');
        $('#port_odp').attr('max', portTotal);
      } else {
        $('#odp-port-info').addClass('d-none');
      }

      if (!isManuallyEdited) {
        triggerGenerateCredentials();
      }
    });

    // Tombol refresh generate kredensial paksa
    $('#btn-regen-pppoe').on('click', function() {
      isManuallyEdited = false;
      triggerGenerateCredentials();
    });

    // Jika user mengedit kredensial secara manual, beri tanda
    $('#pppoe_username, #pppoe_password').on('input', function() {
      isManuallyEdited = true;
      $('#pppoe-badge-info').html('<i class="mdi mdi-pencil text-warning me-1"></i> Diedit manual oleh admin/teknisi');
    });

    // Peek/Show password toggles
    $('#toggle-web-pass').on('click', function() {
      const input = $('#web_password');
      const type = input.attr('type') === 'password' ? 'text' : 'password';
      input.attr('type', type);
      $(this).find('i').toggleClass('mdi-eye-outline mdi-eye-off-outline');
    });

    $('#toggle-pppoe-pass').on('click', function() {
      const input = $('#pppoe_password');
      const type = input.attr('type') === 'password' ? 'text' : 'password';
      input.attr('type', type);
      $(this).find('i').toggleClass('mdi-eye-outline mdi-eye-off-outline');
    });

    // 5. Dinamisasi Paket Berdasarkan Kategori
    $('#kategori-select').on('change', function() {
      const katId = $(this).val();
      const $paket = $('#paket-select');
      $paket.empty().append('<option value="" disabled selected>Pilih Paket Internet</option>');
      $('#paket-price-card').addClass('d-none');

      if (katId && paketGrouped[katId]) {
        paketGrouped[katId].forEach(function(item) {
          const formattedPrice = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(item.price);
          $paket.append('<option value="' + item.id + '" data-kode="' + item.kode + '" data-price="' + formattedPrice + '">' + item.nama + ' (' + formattedPrice + ')</option>');
        });
        $paket.prop('disabled', false);
      } else {
        $paket.prop('disabled', true);
      }
    });

    $('#paket-select').on('change', function() {
      const selected = $(this).find('option:selected');
      const price = selected.data('price');
      const kode = selected.data('kode');
      if (price) {
        $('#paket-price-card').removeClass('d-none');
        $('#paket-preview-text').html('Profil MikroTik: <code>' + kode + '</code> | Biaya: <strong>' + price + '/bln</strong>');
      } else {
        $('#paket-price-card').addClass('d-none');
      }
    });

    // 6. Submit Form AJAX
    $('#form-add-user').on('submit', function(e) {
      e.preventDefault();

      const $btn = $('#btn-submit-form');
      const originalText = $btn.html();
      $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan & Provisioning PPPoE...');

      const formData = new FormData(this);

      $.ajax({
        url: "{{ route('user.store') }}",
        method: "POST",
        data: formData,
        processData: false,
        contentType: false,
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
          $btn.prop('disabled', false).html(originalText);

          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: response.message || 'Pelanggan berhasil didaftarkan dan secret PPPoE siap digunakan.',
            showCancelButton: true,
            confirmButtonText: '<i class="mdi mdi-format-list-bulleted me-1"></i> Ke Daftar Pelanggan',
            cancelButtonText: '<i class="mdi mdi-account-plus me-1"></i> Tambah Lagi',
            customClass: {
              confirmButton: 'btn btn-primary me-2',
              cancelButton: 'btn btn-outline-secondary'
            },
            buttonsStyling: false
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = "{{ route('user.index') }}";
            } else {
              $('#form-add-user')[0].reset();
              $('.select2-init').val('').trigger('change');
              $('#pppoe-badge-info').addClass('d-none');
              $('#coord-badge').addClass('d-none');
              $('#paket-price-card').addClass('d-none');
              if (customerMarker) {
                map.removeLayer(customerMarker);
                customerMarker = null;
              }
              isManuallyEdited = false;
            }
          });
        },
        error: function(xhr) {
          $btn.prop('disabled', false).html(originalText);

          let errorMsg = 'Terjadi kesalahan saat memproses data.';
          if (xhr.responseJSON) {
            if (xhr.responseJSON.errors) {
              const errList = Object.values(xhr.responseJSON.errors).flat();
              errorMsg = errList.join('<br>');
            } else if (xhr.responseJSON.message) {
              errorMsg = xhr.responseJSON.message;
            }
          }

          Swal.fire({
            icon: 'error',
            title: 'Gagal Menyimpan',
            html: errorMsg,
            customClass: {
              confirmButton: 'btn btn-danger'
            },
            buttonsStyling: false
          });
        }
      });
    });
  });
</script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <!-- Page Header -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
        <span class="step-badge bg-primary text-white"><i class="mdi mdi-account-plus-outline"></i></span>
        <span>Registrasi Pelanggan Baru & Provisioning PPPoE</span>
      </h4>
      <p class="text-muted mb-0">Alur terpadu all-in-one: identitas, pemetaan ODP, formulasi kredensial PPPoE otomatis, dan paket layanan.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('user.index') }}" class="btn btn-outline-secondary">
        <i class="mdi mdi-arrow-left me-1"></i> Kembali ke Daftar
      </a>
    </div>
  </div>

  <form id="form-add-user" enctype="multipart/form-data">
    @csrf

    <div class="row g-4">
      <!-- Kolom Kiri: Data Pelanggan & Jaringan ODP -->
      <div class="col-xl-8 col-lg-7">

        <!-- KARTU 1: DATA PERSONAL -->
        <div class="card mb-4 shadow-sm border">
          <div class="card-header bg-transparent pb-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2 fs-6">
              <span class="step-badge bg-label-primary text-primary">1</span>
              <span>Identitas & Kontak Pelanggan</span>
            </h5>
            <span class="badge bg-label-info">Data Utama</span>
          </div>

          <div class="card-body pt-4">
            <div class="row g-3">
              <!-- Nama Pelanggan -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" id="nama" name="nama" class="form-control" autocomplete="off" placeholder="Nama Lengkap" required />
                  <label for="nama"><i class="mdi mdi-account-outline me-1"></i> Nama Lengkap <span class="text-danger">*</span></label>
                </div>
                <small class="text-muted">Nama akan langsung menjadi dasar format username PPPoE</small>
              </div>

              <!-- Nomor WA -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" id="wa" name="wa" class="form-control" autocomplete="off" placeholder="Contoh: 08123456789" required />
                  <label for="wa"><i class="mdi mdi-whatsapp me-1"></i> Nomor WhatsApp Pelanggan <span class="text-danger">*</span></label>
                </div>
                <small class="text-muted">Untuk notifikasi tagihan billing & info jaringan</small>
              </div>

              <!-- Jenis Kelamin & Password Portal -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select name="jenis_kelamin" id="jenis_kelamin" class="form-select" required>
                    <option value="L" selected>Laki-laki</option>
                    <option value="P">Perempuan</option>
                  </select>
                  <label for="jenis_kelamin"><i class="mdi mdi-gender-male-female me-1"></i> Jenis Kelamin</label>
                </div>
              </div>

              <div class="col-md-6">
                <div class="input-group input-group-merge">
                  <div class="form-floating form-floating-outline flex-grow-1">
                    <input type="password" id="web_password" name="password" class="form-control" autocomplete="new-password" placeholder="Password Portal" />
                    <label for="web_password"><i class="mdi mdi-lock-outline me-1"></i> Password Login Dashboard</label>
                  </div>
                  <span class="input-group-text cursor-pointer" id="toggle-web-pass">
                    <i class="mdi mdi-eye-off-outline"></i>
                  </span>
                </div>
                <small class="text-muted">Default jika kosong: <code>123456789abc</code></small>
              </div>

              <!-- Jenis & Nomor Identitas -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select name="jenis_identitas" id="jenis_identitas" class="form-select" required>
                    <option value="KTP" selected>KTP (Kartu Tanda Penduduk)</option>
                    <option value="KK">Kartu Keluarga (KK)</option>
                    <option value="SIM">SIM</option>
                    <option value="PASPOR">Paspor</option>
                    <option value="KARTU_PELAJAR">Kartu Pelajar / Mahasiswa</option>
                    <option value="NPWP">NPWP</option>
                    <option value="BPJS">BPJS</option>
                    <option value="LAINNYA">Lainnya</option>
                  </select>
                  <label for="jenis_identitas"><i class="mdi mdi-card-account-details-outline me-1"></i> Jenis Dokumen</label>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" id="no_identitas" name="no_identitas" class="form-control" placeholder="Nomor Identitas KTP/KK" value="-" required />
                  <label for="no_identitas"><i class="mdi mdi-numeric me-1"></i> Nomor Dokumen Identitas <span class="text-danger">*</span></label>
                </div>
              </div>

              <!-- Alamat Lengkap -->
              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <textarea name="alamat" id="alamat" class="form-control" style="height: 75px;" placeholder="Dusun / RT / RW / Desa"></textarea>
                  <label for="alamat"><i class="mdi mdi-home-outline me-1"></i> Alamat Pemasangan Rumah Pelanggan</label>
                </div>
              </div>

              <!-- Keterangan -->
              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <input type="text" name="keterangan" id="keterangan" class="form-control" placeholder="Misal: Dekat masjid, rumah pagar hitam" />
                  <label for="keterangan"><i class="mdi mdi-information-outline me-1"></i> Catatan Khusus Teknisi Lapangan</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- KARTU 2: DISTRIBUSI ODP & JARINGAN -->
        <div class="card mb-4 shadow-sm border">
          <div class="card-header bg-transparent pb-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2 fs-6">
              <span class="step-badge bg-label-info text-info">2</span>
              <span>Jaringan Distribusi & Titik ODP</span>
            </h5>
            <span class="badge bg-label-primary">Infrastruktur</span>
          </div>

          <div class="card-body pt-4">
            <div class="row g-3">
              <!-- Router MikroTik Target -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select name="id_mikrotik" id="mikrotik-select" class="form-select select2-init" required>
                    <option value="" disabled selected>Pilih Router MikroTik</option>
                    @foreach (Helper::getMikrotik() as $val)
                    <option value="{{ $val->id }}" {{ $loop->first ? 'selected' : '' }}>
                      {{ $val->nama }} ({{ $val->ip }}:{{ $val->port }})
                    </option>
                    @endforeach
                  </select>
                  <label for="mikrotik-select"><i class="mdi mdi-router-wireless me-1"></i> Target Router MikroTik <span class="text-danger">*</span></label>
                </div>
                <small class="text-muted">Router tempat akun PPP Secret akan diprovisioning</small>
              </div>

              <!-- Area Wilayah -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select name="area" id="area-select" class="form-select select2-init" required>
                    <option value="" disabled selected>Pilih Wilayah / Area</option>
                    @foreach (Helper::getArea() as $val)
                    <option value="{{ $val->id }}">{{ $val->name }}</option>
                    @endforeach
                  </select>
                  <label for="area-select"><i class="mdi mdi-map-marker-radius me-1"></i> Wilayah / Area <span class="text-danger">*</span></label>
                </div>
              </div>

              <!-- ODP Selection -->
              <div class="col-md-8">
                <div class="form-floating form-floating-outline">
                  <select name="id_odp" id="odp-select" class="form-select select2-init" required>
                    <option value="" disabled selected>Pilih Kotak ODP (Tiang Distribusi)</option>
                    @foreach (Helper::getOdp() as $val)
                    <option value="{{ $val->id }}" data-nama="{{ $val->nama }}" data-kode="{{ $val->kode }}" data-port="{{ $val->port }}" data-sisa="{{ $val->portSisa }}">
                      {{ $val->nama }} [{{ $val->kode ?? 'ODP' }}] — Sisa: {{ $val->portSisa }} port
                    </option>
                    @endforeach
                  </select>
                  <label for="odp-select"><i class="mdi mdi-lan-connect me-1"></i> Titik Kotak ODP <span class="text-danger">*</span></label>
                </div>
                <div id="odp-port-info" class="text-muted mt-1 small d-none"></div>
              </div>

              <!-- Port ODP Number -->
              <div class="col-md-4">
                <div class="form-floating form-floating-outline">
                  <input type="number" id="port_odp" name="port_odp" class="form-control" min="1" max="64" placeholder="1" value="1" />
                  <label for="port_odp"><i class="mdi mdi-numeric me-1"></i> Nomor Port ODP</label>
                </div>
                <small class="text-muted">Port dropcore di ODP</small>
              </div>
            </div>
          </div>
        </div>

        <!-- KARTU 3: LOKASI & GOOGLE MAPS -->
        <div class="card mb-4 shadow-sm border">
          <div class="card-header bg-transparent pb-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2 fs-6">
              <span class="step-badge bg-label-warning text-warning">3</span>
              <span>Pemetaan Lokasi & Koordinat Presisi</span>
            </h5>
            <span class="badge bg-label-success">Auto-Extract</span>
          </div>

          <div class="card-body pt-4">
            <div class="row g-3">
              <!-- URL Google Map dengan Tombol Ekstrak -->
              <div class="col-12">
                <div class="input-group input-group-merge">
                  <div class="form-floating form-floating-outline flex-grow-1">
                    <input type="text" id="google_map_url" name="google_map_url" class="form-control"
                      placeholder="Paste link Google Maps (misal https://maps.app.goo.gl/... atau @-5.123,105.123)" />
                    <label for="google_map_url"><i class="mdi mdi-google-maps me-1"></i> Tautan / Link Google Maps</label>
                  </div>
                  <button type="button" class="btn btn-outline-primary" id="btn-extract-maps">
                    <i class="mdi mdi-crosshairs-gps me-1"></i> Ekstrak
                  </button>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-1">
                  <small class="text-muted">Paste tautan share Google Maps dari WhatsApp/teknisi</small>
                  <div id="coord-badge" class="badge bg-label-success d-none"></div>
                </div>
              </div>

              <!-- Lat & Long Inputs -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" id="latitude" name="latitude" class="form-control font-monospace" placeholder="-5.123456" />
                  <label for="latitude"><i class="mdi mdi-latitude me-1"></i> Latitude</label>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="text" id="longitude" name="longitude" class="form-control font-monospace" placeholder="105.123456" />
                  <label for="longitude"><i class="mdi mdi-longitude me-1"></i> Longitude</label>
                </div>
              </div>

              <!-- Mini Leaflet Map -->
              <div class="col-12">
                <div class="p-2 border rounded bg-lighter">
                  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 px-1 gap-2">
                    <small class="fw-semibold text-muted">
                      <i class="mdi mdi-map-marker-radius me-1"></i> Pratinjau Peta (Klik/seret marker untuk ubah lokasi):
                    </small>
                    <div class="btn-group btn-group-sm shadow-xs" role="group" id="map-layer-switcher">
                      <button type="button" class="btn btn-primary active btn-map-type" data-layer="googleHybrid" title="Peta Satelit dengan Nama Jalan">
                        <i class="mdi mdi-satellite-variant me-1"></i> Satelit Hybrid
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-map-type" data-layer="googleSat" title="Citra Satelit Asli">
                        <i class="mdi mdi-earth me-1"></i> Satelit Murni
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-map-type" data-layer="googleStreets" title="Peta Jalan Google Maps">
                        <i class="mdi mdi-google-maps me-1"></i> Google Jalan
                      </button>
                      <button type="button" class="btn btn-outline-secondary btn-map-type" data-layer="osm" title="Peta OpenStreetMap">
                        <i class="mdi mdi-map-outline me-1"></i> OSM
                      </button>
                    </div>
                  </div>
                  <div id="mini-map-customer"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Kolom Kanan: PPPoE Credentials & Paket Billing -->
      <div class="col-xl-4 col-lg-5">

        <!-- KARTU 4: KREDENSIAL PPPOE (AUTO-GENERATE CERDAS) -->
        <div class="card mb-4 shadow-sm border pppoe-highlight-box">
          <div class="card-header bg-transparent pb-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2 fs-6 text-primary">
              <span class="step-badge bg-primary text-white"><i class="mdi mdi-key-variant"></i></span>
              <span>Akun PPPoE MikroTik</span>
            </h5>
            <button type="button" id="btn-regen-pppoe" class="btn btn-sm btn-icon btn-outline-primary" title="Generate Ulang Kredensial">
              <i class="mdi mdi-refresh"></i>
            </button>
          </div>

          <div class="card-body pt-4">
            <div class="alert alert-primary d-flex align-items-center p-2 mb-3" role="alert">
              <i class="mdi mdi-shield-check-outline fs-4 me-2"></i>
              <div class="small">
                Format: <code>nama@nama_odp</code> (contoh: <code>budi@tji-1</code>). Password disamakan persis dengan username untuk memudahkan teknisi di lapangan.
              </div>
            </div>

            <div class="mb-3">
              <label for="pppoe_username" class="form-label fw-bold small text-uppercase">Username PPPoE <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="mdi mdi-account-key-outline"></i></span>
                <input type="text" id="pppoe_username" name="pppoe_username" class="form-control font-monospace fw-bold" placeholder="otomatis terisi..." required />
              </div>
              <div id="pppoe-badge-info" class="mt-1 small d-none"></div>
            </div>

            <div class="mb-3">
              <label for="pppoe_password" class="form-label fw-bold small text-uppercase">Password PPPoE <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text"><i class="mdi mdi-lock-check-outline"></i></span>
                <input type="password" id="pppoe_password" name="pppoe_password" class="form-control font-monospace fw-bold" placeholder="otomatis terisi..." required />
                <button aria-label="Tampilkan atau sembunyikan password" type="button" class="btn btn-outline-secondary" id="toggle-pppoe-pass">
                  <i class="mdi mdi-eye-off-outline"></i>
                </button>
              </div>
              <small class="text-success"><i class="mdi mdi-check me-1"></i> Otomatis sinkron dengan username</small>
            </div>
          </div>
        </div>

        <!-- KARTU 5: PAKET LAYANAN & PENAGIHAN -->
        <div class="card mb-4 shadow-sm border">
          <div class="card-header bg-transparent pb-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2 fs-6">
              <span class="step-badge bg-label-success text-success">4</span>
              <span>Paket & Billing</span>
            </h5>
            <span class="badge bg-label-info">Berlangganan</span>
          </div>

          <div class="card-body pt-4">
            <div class="row g-3">
              <!-- Kategori Paket -->
              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <select name="id_kategori" id="kategori-select" class="form-select select2-init" required>
                    <option value="" disabled selected>Pilih Kategori Layanan</option>
                    @foreach (Helper::getKategori() as $kat)
                    <option value="{{ $kat->id }}">{{ $kat->nama }}</option>
                    @endforeach
                  </select>
                  <label for="kategori-select"><i class="mdi mdi-shape-outline me-1"></i> Kategori Paket <span class="text-danger">*</span></label>
                </div>
              </div>

              <!-- Paket Internet -->
              <div class="col-12">
                <div class="form-floating form-floating-outline">
                  <select name="id_paket" id="paket-select" class="form-select select2-init" disabled required>
                    <option value="" disabled selected>Pilih Kategori Dahulu</option>
                  </select>
                  <label for="paket-select"><i class="mdi mdi-speedometer me-1"></i> Paket Internet <span class="text-danger">*</span></label>
                </div>
                <div id="paket-price-card" class="mt-2 p-2 rounded bg-label-primary text-primary small d-none">
                  <i class="mdi mdi-information-outline me-1"></i> <span id="paket-preview-text"></span>
                </div>
              </div>

              <!-- Tanggal Pasang & Jatuh Tempo -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="date" name="tgl_daftar" id="tgl_daftar" class="form-control" value="{{ now()->format('Y-m-d') }}" required />
                  <label for="tgl_daftar">Tgl Registrasi</label>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="date" name="jatuh_tempo" id="jatuh_tempo" class="form-control" value="{{ now()->day(10)->format('Y-m-d') }}" required />
                  <label for="jatuh_tempo">Jatuh Tempo (yang dipakai hanya tanggalnya)</label>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <input type="month" name="mulai_tagih" id="mulai_tagih" class="form-control"
                    value="{{ substr(\App\Models\Lamtim_user_details::mulaiTagihDefault(now()), 0, 7) }}" />
                  <label for="mulai_tagih">Mulai Ditagih</label>
                </div>
                <div class="form-text">Pasang tgl 1–19: bulan depan, tgl 20+: 2 bulan lagi. Boleh diubah.</div>
              </div>
              <script>
                // Isi otomatis "Mulai Ditagih" saat tanggal pasang diubah (aturan sama dengan server)
                document.getElementById('tgl_daftar').addEventListener('change', function () {
                  if (!this.value) return;
                  const [y, m, d] = this.value.split('-').map(Number);
                  const t = new Date(y, m - 1 + (d <= {{ \App\Models\Lamtim_user_details::BATAS_PASANG_BULAN_DEPAN }} ? 1 : 2), 1);
                  document.getElementById('mulai_tagih').value = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0');
                });
              </script>

              <!-- Jenis Bayar & PPN -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select name="jenis_bayar" id="jenis_bayar" class="form-select" required>
                    <option value="1" selected>Pascabayar</option>
                    <option value="2">Prabayar</option>
                  </select>
                  <label for="jenis_bayar">Sistem Bayar</label>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select name="status_ppn" id="status_ppn" class="form-select">
                    <option value="0" selected>Tidak Ada PPN</option>
                    <option value="1">PPN 11%</option>
                  </select>
                  <label for="status_ppn">Status PPN</label>
                </div>
              </div>

              <!-- Status Tagihan & Isolir -->
              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select name="status_tagihan" id="status_tagihan" class="form-select">
                    <option value="1" selected>Aktif</option>
                    <option value="0">Nonaktif</option>
                  </select>
                  <label for="status_tagihan">Generate Tagihan</label>
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-floating form-floating-outline">
                  <select name="status_isolir" id="status_isolir" class="form-select">
                    <option value="0" selected>Normal (Aktif)</option>
                    <option value="1">Terisolir</option>
                  </select>
                  <label for="status_isolir">Status Isolir</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- KARTU 6: PERANGKAT ONT / MODEM (OPSIONAL) -->
        <div class="card mb-4 shadow-sm border">
          <div class="card-header bg-transparent pb-3 border-bottom d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2 fs-6">
              <span class="step-badge bg-label-dark text-body">5</span>
              <span>Perangkat ONT / Modem</span>
            </h5>
            <span class="badge bg-label-secondary">Opsional</span>
          </div>

          <div class="card-body pt-4">
            <div class="form-floating form-floating-outline mb-2">
              <input type="text" id="ont_sn" name="ont_sn" class="form-control font-monospace" placeholder="ZTEGC1234567 / HWTC..." />
              <label for="ont_sn"><i class="mdi mdi-barcode-scan me-1"></i> Serial Number / MAC ONT</label>
            </div>
            <small class="text-muted">Untuk integrasi monitoring redaman optik via GenieACS</small>
          </div>
        </div>

        <!-- TOMBOL AKSI SUBMIT -->
        <div class="card shadow-sm border">
          <div class="card-body d-grid gap-2">
            <button type="submit" id="btn-submit-form" class="btn btn-primary btn-lg shadow-primary">
              <i class="mdi mdi-check-circle-outline me-1 fs-5"></i> Daftarkan & Provisioning PPPoE
            </button>
            <a href="{{ route('user.index') }}" class="btn btn-outline-secondary">
              Batal
            </a>
          </div>
        </div>

      </div>
    </div>
  </form>
</div>
@endsection
