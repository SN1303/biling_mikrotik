@extends('layouts/layoutMaster')

@section('title', 'Edit Pelanggan')

@section("vendor-style")
@endsection


@section("vendor-script")

@endsection

@section('page-script')
<script>
  $(document).ready(function() {
    $('#area-select').select2({
      placeholder: "Pilih Area",
      allowClear: true,
      dropdownParent: $('#area-select').closest('.form-floating') // agar tidak overlap label
    });

    $('#form-add-user').on('submit', function (e) {
        e.preventDefault(); // Mencegah form untuk submit secara normal

        const form = this;
        const formData = new FormData(form); // Ambil data dari form
        const $btn = $('#btn-simpan');
        const labelAwal = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menyimpan...');

        $.ajax({
            url: "{{ route('user.update', ['id' => $id]) }}", // Endpoint untuk update data
            method: 'POST', // Gunakan PUT request
            data: formData, // Kirimkan data form
            processData: false, // Biarkan data tetap sebagai FormData
            contentType: false, // Agar data dikirim dalam format multipart/form-data
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') // CSRF token untuk keamanan
            },
            success: function (response) {
                //form.reset(); // Reset form setelah berhasil
                toastr.success(response.message || 'Data berhasil disimpan.'); // Tampilkan pesan sukses

                // Optional: reload tabel atau redirect
                // $('#table-user').DataTable().ajax.reload();
                // window.location.href = '/user'; // Jika ingin redirect
            },
            error: function (xhr) {
                let message = 'Terjadi kesalahan.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message); // Tampilkan pesan error
            },
            complete: function () {
                $btn.prop('disabled', false).html(labelAwal);
            }
        });
    });


  });
</script>
@endsection

@section('content')
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="min-w-0">
      <h5 class="mb-0">Edit Pelanggan</h5>
      <small class="text-muted text-truncate d-block">{{ $query->name }}</small>
    </div>
    <a href="{{ route('user.index') }}" class="btn btn-sm btn-outline-secondary">
      <i class="mdi mdi-arrow-left me-1"></i> Kembali ke Daftar
    </a>
  </div>
  <form id="form-add-user" class="card-body" enctype="multipart/form-data">
    @csrf
    <h6 class="mb-3">Akun Pengguna</h6>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="form-floating form-floating-outline">
          <input type="text" name="nama" id="nama" class="form-control" autocomplete="off" placeholder="Nama Lengkap"
            value="{{ $query->name }}" required />
          <label for="nama">Nama</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-floating form-floating-outline">
          <input value="{{ $query->julukan }}" type="text" name="julukan" id="julukan" class="form-control" autocomplete="off"
            placeholder="Nama panggilan" />
          <label for="julukan">Julukan</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-floating form-floating-outline">
          <input value="{{ $query->wa }}" type="tel" inputmode="tel" name="wa" id="wa" class="form-control" autocomplete="off"
            placeholder="08..." required />
          <label for="wa">Nomor WhatsApp</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input type="password" name="password" id="password" class="form-control" autocomplete="new-password"
            placeholder="Password baru" />
          <label for="password">Password Baru</label>
        </div>
        <div class="form-text">Kosongkan jika tidak ingin mengganti password.</div>
      </div>
      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <select id="status-user" name="status_user" class="form-select" required>
            <option {{ $query->isActive == 1 ? 'selected' : '' }} value="1">Aktif</option>
            <option {{ $query->isActive == 0 ? 'selected' : '' }} value="0">Nonaktif</option>
          </select>
          <label for="status-user">Status Akun</label>
        </div>
      </div>
    </div>

    <hr class="my-4 mx-n4" />
    <h6 class="mb-3">Informasi Pelanggan</h6>
    <div class="row g-4">

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <select name="area" id="area-select" class="form-select">
            {{-- Kosong harus benar-benar terpilih; kalau tidak, browser memilih area pertama diam-diam --}}
            <option value="" @selected(empty($query->user_detail?->idArea))>Belum ada area</option>
            @foreach (Helper::getArea() as $val)
            <option value="{{ $val->id }}" {{ !empty($query->user_detail?->idArea) && $query->user_detail->idArea == $val->getRawOriginal('id') ? 'selected' : '' }}>
              {{ $val->name }}
            </option>
            @endforeach
            <!-- Tambah opsi lain jika perlu -->
          </select>
          <label for="area-select">Area</label>
        </div>
      </div>


      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <select name="status_isolir" id="status_isolir" class="form-select" required>
            <option {{ $query->user_mikrotik?->statusIsolir == 1 ? 'selected' : '' }} value="1">Ya, terisolir</option>
            <option {{ $query->user_mikrotik?->statusIsolir == 0 ? 'selected' : '' }} value="0">Tidak (normal)</option>
          </select>
          <label for="status_isolir">Status Isolir</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input type="date" name="tgl_daftar" id="tgl_daftar" class="form-control" value="{{ $query->user_detail?->tglDafatar }}" />
          <label for="tgl_daftar">Tanggal Daftar</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input type="date" name="jatuh_tempo" id="jatuh_tempo" value="{{ $query->user_detail?->tglJatuhTempo }}" class="form-control"
            required />
          <label for="jatuh_tempo">Jatuh Tempo</label>
        </div>
        <div class="form-text">Yang dipakai hanya tanggalnya, tiap bulan.</div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input type="month" name="mulai_tagih" id="mulai_tagih" class="form-control"
            value="{{ $query->user_detail?->mulaiTagih ? substr($query->user_detail->mulaiTagih, 0, 7) : '' }}" />
          <label for="mulai_tagih">Mulai Ditagih</label>
        </div>
        <div class="form-text">Kosong = sudah ditagih sejak awal (pelanggan lama).</div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <select name="status_ppn" id="status_ppn" class="form-select">
            <option @selected($query->user_detail?->statusPpn != 1) value="0">Tidak</option>
            <option @selected($query->user_detail?->statusPpn == 1) value="1">Aktif</option>
          </select>
          <label for="status_ppn">Status PPN</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <select name="status_tagihan" id="status_tagihan" class="form-select">
            <option @selected($query->user_detail?->statusTagihan == 1) value="1">Aktif</option>
            <option @selected($query->user_detail?->statusTagihan == 0) value="0">Nonaktif</option>
          </select>
          <label for="status_tagihan">Generate Tagihan</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <select name="jenis_bayar" id="jenis_bayar" class="form-select" required>
            <option @selected($query->user_detail?->jenisBayar == 1) value="1">Pascabayar</option>
            <option @selected($query->user_detail?->jenisBayar == 2) value="2">Prabayar</option>
          </select>
          <label for="jenis_bayar">Sistem Bayar</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input value="{{ $query->user_detail?->googleMap }}" type="url" name="google_map_url" id="google_map_url" class="form-control"
            placeholder="https://maps.app.goo.gl/..." />
          <label for="google_map_url">Link Google Maps</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <select name="jenis_kelamin" id="jenis_kelamin" class="form-select">
            <option @selected($query->user_detail?->js == 'L') value="L">Laki-laki</option>
            <option @selected($query->user_detail?->js == 'P') value="P">Perempuan</option>
          </select>
          <label for="jenis_kelamin">Jenis Kelamin</label>
        </div>
      </div>

      <div class="col-md-6">
        @php
        $listIdentitas = [
        'KTP' => 'KTP',
        'KK' => 'Kartu Keluarga',
        'SIM' => 'SIM',
        'PASPOR' => 'Paspor',
        'KARTU_PELAJAR' => 'Kartu Pelajar',
        'KARTU_MAHASISWA' => 'Kartu Mahasiswa',
        'NPWP' => 'NPWP',
        'BPJS' => 'BPJS',
        'LAINNYA' => 'Lainnya',
        ];
        @endphp
        <div class="form-floating form-floating-outline">
          <select name="jenis_identitas" id="jenis_identitas" class="form-select">
            @foreach ($listIdentitas as $value => $label)
            <option value="{{ $value }}" @selected($query->user_detail?->identitas ==$value) >
              {{ $label }}
            </option>
            @endforeach
          </select>
          <label for="jenis_identitas">Jenis Identitas</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating form-floating-outline">
          <input value="{{ $query->user_detail?->noIdentitas }}" type="text" name="no_identitas" id="no_identitas" class="form-control"
            placeholder="Nomor Identitas" />
          <label for="no_identitas">Nomor Identitas</label>
        </div>
      </div>

      <div class="col-12">
        <div class="form-floating form-floating-outline">
          <textarea name="alamat" id="alamat" class="form-control" style="height: 100px;"
            placeholder="Alamat lengkap">{{ $query->user_detail?->alamat }}</textarea>
          <label for="alamat">Alamat Pemasangan</label>
        </div>
      </div>
      <div class="col-12">
        <div class="form-floating form-floating-outline">
          <textarea name="keterangan" id="keterangan" class="form-control" style="height: 100px;"
            placeholder="Catatan untuk teknisi">{{ $query->user_detail?->keterangan }}</textarea>
          <label for="keterangan">Catatan</label>
        </div>
      </div>
    </div>

    <div class="pt-4 d-flex flex-wrap gap-2">
      <button type="submit" id="btn-simpan" class="btn btn-primary">
        <i class="mdi mdi-content-save-outline me-1"></i> Simpan Perubahan
      </button>
      <a href="{{ route('user.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
  </form>
</div>

@endsection