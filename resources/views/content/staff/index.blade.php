@extends('layouts/layoutMaster')

@section('title', 'Manajemen Staf & Pengguna Sistem')

@section('page-style')
<style>
  .badge-role-1 { background-color: rgba(235, 87, 87, 0.12); color: #eb5757; border: 1px solid rgba(235, 87, 87, 0.25); }
  .badge-role-2 { background-color: rgba(105, 108, 255, 0.12); color: #696cff; border: 1px solid rgba(105, 108, 255, 0.25); }
  .badge-role-3 { background-color: rgba(113, 221, 55, 0.12); color: #71dd37; border: 1px solid rgba(113, 221, 55, 0.25); }
  .badge-role-4 { background-color: rgba(255, 171, 0, 0.12); color: #ffab00; border: 1px solid rgba(255, 171, 0, 0.25); }
  .badge-role-6 { background-color: rgba(3, 195, 236, 0.12); color: #03c3ec; border: 1px solid rgba(3, 195, 236, 0.25); }
  .badge-role-7 { background-color: rgba(113, 88, 226, 0.12); color: #7158e2; border: 1px solid rgba(113, 88, 226, 0.25); }
  .staff-card-header {
    background: linear-gradient(135deg, rgba(105, 108, 255, 0.04) 0%, rgba(105, 108, 255, 0.01) 100%);
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  }
  .table-hover tbody tr:hover {
    background-color: rgba(105, 108, 255, 0.02);
  }
</style>
@endsection

@section('page-script')
<script>
  let tableStaff;

  $(document).ready(function() {
    // Inisialisasi DataTable Staf
    tableStaff = $('#table-staff').DataTable({
      processing: true,
      serverSide: true,
      ajax: "{{ route('staff.json') }}",
      language: {
        search: "Cari Staf:",
        lengthMenu: "Tampilkan _MENU_ data",
        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ staf",
        infoEmpty: "Tidak ada data staf",
        zeroRecords: "Tidak ada data yang cocok",
        paginate: {
          previous: "<i class='mdi mdi-chevron-left'></i>",
          next: "<i class='mdi mdi-chevron-right'></i>"
        }
      },
      columns: [
        {
          data: 'DT_RowIndex',
          name: 'DT_RowIndex',
          orderable: false,
          searchable: false,
          className: "text-center fw-semibold text-muted",
          width: "5%"
        },
        {
          data: 'action',
          name: 'action',
          orderable: false,
          searchable: false,
          className: "text-center",
          width: "8%"
        },
        {
          data: 'name',
          name: 'name',
          render: function(data, type, row) {
            return `
              <div class="d-flex align-items-center">
                <div class="avatar avatar-sm me-2">
                  <span class="avatar-initial rounded-circle bg-label-primary fw-bold">
                    ${data.charAt(0).toUpperCase()}
                  </span>
                </div>
                <div>
                  <span class="fw-bold text-heading d-block">${data}</span>
                  <small class="text-muted">ID: #${row.id}</small>
                </div>
              </div>
            `;
          }
        },
        {
          data: 'email',
          name: 'email',
          render: function(data) {
            return `
              <span class="d-flex align-items-center">
                <i class="mdi mdi-email-outline me-1 text-muted"></i>
                <a href="mailto:${data}" class="text-body fw-medium">${data}</a>
              </span>
            `;
          }
        },
        {
          data: 'wa',
          name: 'wa',
          render: function(data) {
            if (!data || data === '-') return '<span class="text-muted">-</span>';
            const cleanWa = data.replace(/[^0-9]/g, '');
            const waUrl = cleanWa.startsWith('0') ? '62' + cleanWa.substring(1) : cleanWa;
            return `
              <span class="d-flex align-items-center">
                <i class="mdi mdi-whatsapp me-1 text-success"></i>
                <a href="https://wa.me/${waUrl}" target="_blank" class="text-body fw-medium">${data}</a>
              </span>
            `;
          }
        },
        {
          data: 'role_badge',
          name: 'idRole',
          className: "text-center"
        },
        {
          data: 'status_badge',
          name: 'isActive',
          className: "text-center"
        },
        {
          data: 'created_at',
          name: 'created_at',
          className: "text-center text-muted",
          render: function(data) {
            if (!data) return '-';
            const d = new Date(data);
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
          }
        }
      ]
    });

    // Toggle Password Visibility
    $('.toggle-password-btn').on('click', function() {
      const targetId = $(this).data('target');
      const input = $('#' + targetId);
      const icon = $(this).find('i');
      if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('mdi-eye-outline').addClass('mdi-eye-off-outline');
      } else {
        input.attr('type', 'password');
        icon.removeClass('mdi-eye-off-outline').addClass('mdi-eye-outline');
      }
    });

    // Form Tambah Staf Submit
    $('#form-add-staff').on('submit', function(e) {
      e.preventDefault();
      const btn = $('#btn-save-add');
      const originalText = btn.html();

      btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan...');

      $.ajax({
        url: "{{ route('staff.store') }}",
        method: 'POST',
        data: $(this).serialize(),
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        success: function(res) {
          btn.prop('disabled', false).html(originalText);
          if (res.success) {
            $('#modal-add-staff').modal('hide');
            $('#form-add-staff')[0].reset();
            tableStaff.ajax.reload(null, false);
            if (typeof toastr !== 'undefined') {
              toastr.success(res.message);
            } else if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 2000, showConfirmButton: false });
            } else {
              alert(res.message);
            }
          }
        },
        error: function(xhr) {
          btn.prop('disabled', false).html(originalText);
          let msg = 'Terjadi kesalahan saat menyimpan data.';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          }
          if (typeof toastr !== 'undefined') {
            toastr.error(msg);
          } else if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
          } else {
            alert(msg);
          }
        }
      });
    });

    // Form Edit Staf Submit
    $('#form-edit-staff').on('submit', function(e) {
      e.preventDefault();
      const staffId = $('#edit_id').val();
      const btn = $('#btn-save-edit');
      const originalText = btn.html();

      btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Memperbarui...');

      $.ajax({
        url: `{{ url('/staff') }}/${staffId}/update`,
        method: 'POST',
        data: $(this).serialize(),
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        success: function(res) {
          btn.prop('disabled', false).html(originalText);
          if (res.success) {
            $('#modal-edit-staff').modal('hide');
            tableStaff.ajax.reload(null, false);
            if (typeof toastr !== 'undefined') {
              toastr.success(res.message);
            } else if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 2000, showConfirmButton: false });
            } else {
              alert(res.message);
            }
          }
        },
        error: function(xhr) {
          btn.prop('disabled', false).html(originalText);
          let msg = 'Gagal memperbarui data staf.';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          }
          if (typeof toastr !== 'undefined') {
            toastr.error(msg);
          } else if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
          } else {
            alert(msg);
          }
        }
      });
    });

    // Form Reset Password Submit
    $('#form-reset-password').on('submit', function(e) {
      e.preventDefault();
      const staffId = $('#reset_id').val();
      const btn = $('#btn-save-reset');
      const originalText = btn.html();

      btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Mereset...');

      $.ajax({
        url: `{{ url('/staff') }}/${staffId}/reset-password`,
        method: 'POST',
        data: $(this).serialize(),
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        success: function(res) {
          btn.prop('disabled', false).html(originalText);
          if (res.success) {
            $('#modal-reset-password').modal('hide');
            $('#form-reset-password')[0].reset();
            if (typeof toastr !== 'undefined') {
              toastr.success(res.message);
            } else if (typeof Swal !== 'undefined') {
              Swal.fire({ icon: 'success', title: 'Berhasil', text: res.message, timer: 2000, showConfirmButton: false });
            } else {
              alert(res.message);
            }
          }
        },
        error: function(xhr) {
          btn.prop('disabled', false).html(originalText);
          let msg = 'Gagal mereset password.';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          }
          if (typeof toastr !== 'undefined') {
            toastr.error(msg);
          } else {
            alert(msg);
          }
        }
      });
    });
  });

  // Buka Modal Edit
  function openEditModal(id) {
    $.ajax({
      url: `{{ url('/staff') }}/${id}`,
      method: 'GET',
      success: function(res) {
        if (res.success) {
          const d = res.data;
          $('#edit_id').val(d.id);
          $('#edit_name').val(d.name);
          $('#edit_email').val(d.email);
          $('#edit_wa').val(d.wa);
          $('#edit_idRole').val(d.idRole);
          $('#edit_isActive').val(d.isActive);
          $('#edit_password').val('');

          // Jika ini akun yang sedang login, kunci select role dan status
          if (d.isCurrentUser) {
            $('#edit_idRole').prop('disabled', true);
            $('#edit_isActive').prop('disabled', true);
            $('#edit_self_alert').removeClass('d-none');
          } else {
            $('#edit_idRole').prop('disabled', false);
            $('#edit_isActive').prop('disabled', false);
            $('#edit_self_alert').addClass('d-none');
          }

          $('#modal-edit-staff').modal('show');
        }
      },
      error: function(xhr) {
        const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat data staf.';
        if (typeof toastr !== 'undefined') toastr.error(msg);
        else alert(msg);
      }
    });
  }

  // Buka Modal Reset Password
  function openResetPasswordModal(id, name) {
    $('#reset_id').val(id);
    $('#reset_staff_name').text(name);
    $('#reset_password').val('');
    $('#modal-reset-password').modal('show');
  }

  // Toggle Status Aktif
  function toggleStaffStatus(id) {
    $.ajax({
      url: `{{ url('/staff') }}/${id}/toggle-status`,
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      success: function(res) {
        tableStaff.ajax.reload(null, false);
        if (typeof toastr !== 'undefined') toastr.success(res.message);
        else alert(res.message);
      },
      error: function(xhr) {
        const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal mengubah status.';
        if (typeof toastr !== 'undefined') toastr.error(msg);
        else alert(msg);
      }
    });
  }

  // Hapus Akun Staf
  function deleteStaff(id, name) {
    const doDelete = function() {
      $.ajax({
        url: `{{ url('/staff') }}/${id}/delete`,
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        success: function(res) {
          tableStaff.ajax.reload(null, false);
          if (typeof toastr !== 'undefined') toastr.success(res.message);
          else alert(res.message);
        },
        error: function(xhr) {
          const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus akun.';
          if (typeof toastr !== 'undefined') toastr.error(msg);
          else alert(msg);
        }
      });
    };

    if (typeof Swal !== 'undefined') {
      Swal.fire({
        title: 'Hapus Akun Staf?',
        html: `Apakah Anda yakin ingin menghapus akun <b>${name}</b>?<br><small class="text-danger">Aksi ini tidak dapat dibatalkan.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#eb5757',
        cancelButtonColor: '#a1acb8',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
      }).then((result) => {
        if (result.isConfirmed) doDelete();
      });
    } else {
      if (confirm(`Apakah Anda yakin ingin menghapus akun staf "${name}"?`)) {
        doDelete();
      }
    }
  }
</script>
@endsection

@section('content')
<!-- Header Card -->
<div class="card mb-4 shadow-sm border-0">
  <div class="card-header staff-card-header d-flex flex-wrap justify-content-between align-items-center py-3">
    <div class="d-flex align-items-center mb-2 mb-md-0">
      <div class="avatar avatar-md me-3">
        <span class="avatar-initial rounded bg-label-primary shadow-xs">
          <i class="mdi mdi-shield-account fs-3"></i>
        </span>
      </div>
      <div>
        <h5 class="mb-0 fw-bold text-heading">Manajemen Staf & Hak Akses</h5>
        <small class="text-muted">Kelola akun internal sistem (Super Admin, Admin, Bendahara, Teknisi)</small>
      </div>
    </div>
    <div>
      <button type="button" class="btn btn-primary shadow-xs" data-bs-toggle="modal" data-bs-target="#modal-add-staff">
        <i class="mdi mdi-account-plus me-1"></i> Tambah Staf Baru
      </button>
    </div>
  </div>

  <!-- Role Legend Banner -->
  <div class="card-body bg-light-subtle border-bottom py-2 px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <span class="text-muted small fw-semibold">Role Tersedia:</span>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge badge-role-1 small"><i class="mdi mdi-shield-crown me-1"></i>Super Admin (1)</span>
        <span class="badge badge-role-2 small"><i class="mdi mdi-account-cog me-1"></i>Admin (2)</span>
        <span class="badge badge-role-3 small"><i class="mdi mdi-cash-register me-1"></i>Bendahara (3)</span>
        <span class="badge badge-role-4 small"><i class="mdi mdi-wrench me-1"></i>Teknisi (4)</span>
        <span class="badge badge-role-6 small"><i class="mdi mdi-cash-multiple me-1"></i>Kolektor (6)</span>
        <span class="badge badge-role-7 small"><i class="mdi mdi-wrench-clock me-1"></i>Teknisi & Kolektor (7)</span>
      </div>
    </div>
  </div>

  <!-- Table Card -->
  <div class="card-datatable table-responsive p-3">
    <table id="table-staff" class="table table-hover table-bordered w-100">
      <thead class="table-light">
        <tr>
          <th class="text-center">NO</th>
          <th class="text-center">AKSI</th>
          <th>NAMA STAF</th>
          <th>EMAIL LOGIN</th>
          <th>NO. WHATSAPP</th>
          <th class="text-center">ROLE / JABATAN</th>
          <th class="text-center">STATUS</th>
          <th class="text-center">TERDAFTAR</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- ==================== MODAL TAMBAH STAF ==================== -->
<div class="modal fade" id="modal-add-staff" tabindex="-1" aria-labelledby="modalAddStaffTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-primary text-white py-3">
        <h5 class="modal-title text-white d-flex align-items-center" id="modalAddStaffTitle">
          <i class="mdi mdi-account-plus-outline me-2"></i> Tambah Staf / Pengguna Baru
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="form-add-staff">
        <div class="modal-body p-4">
          <!-- Nama Lengkap -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="add_name">Nama Lengkap <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="mdi mdi-account-outline"></i></span>
              <input type="text" class="form-control" id="add_name" name="name" placeholder="contoh: Ahmad Teknisi" required>
            </div>
          </div>

          <!-- Email -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="add_email">Email Login <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="mdi mdi-email-outline"></i></span>
              <input type="email" class="form-control" id="add_email" name="email" placeholder="nama@domain.id" required>
            </div>
            <small class="text-muted">Email digunakan sebagai username saat login.</small>
          </div>

          <!-- WhatsApp -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="add_wa">Nomor WhatsApp <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="mdi mdi-whatsapp"></i></span>
              <input type="text" class="form-control" id="add_wa" name="wa" placeholder="contoh: 081234567890" required>
            </div>
          </div>

          <!-- Role -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="add_idRole">Role / Jabatan <span class="text-danger">*</span></label>
            <select class="form-select" id="add_idRole" name="idRole" required>
              <option value="" disabled selected>-- Pilih Role Staf --</option>
              <option value="1">🔴 Super Admin (Akses Penuh Seluruh Sistem)</option>
              <option value="2">🔵 Admin (Akses Server, Pelanggan, & Jaringan)</option>
              <option value="3">🟢 Bendahara (Akses Tagihan, Billing, & Pelanggan Read-Only)</option>
              <option value="4">🟠 Teknisi (Akses Jaringan, ODC, ODP, & Pelanggan)</option>
              <option value="6">🔵 Kolektor (Akses Tagihan & Pembayaran Lapangan)</option>
              <option value="7">🟣 Teknisi & Kolektor (Akses Jaringan, ODC, ODP, Pelanggan, & Tagihan)</option>
            </select>
          </div>

          <!-- Password -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="add_password">Password Awal <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="mdi mdi-lock-outline"></i></span>
              <input type="password" class="form-control" id="add_password" name="password" placeholder="Minimal 8 karakter" minlength="8" required>
              <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="add_password">
                <i class="mdi mdi-eye-outline"></i>
              </button>
            </div>
          </div>

          <!-- Status Aktif -->
          <div class="mb-2">
            <label class="form-label fw-semibold" for="add_isActive">Status Akun</label>
            <select class="form-select" id="add_isActive" name="isActive">
              <option value="1" selected>✅ Aktif (Bisa langsung login)</option>
              <option value="0">⛔ Nonaktif (Akses ditangguhkan)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer bg-light px-4 py-3">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" id="btn-save-add">
            <i class="mdi mdi-content-save-outline me-1"></i> Simpan Staf
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==================== MODAL EDIT STAF ==================== -->
<div class="modal fade" id="modal-edit-staff" tabindex="-1" aria-labelledby="modalEditStaffTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-label-primary py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center" id="modalEditStaffTitle">
          <i class="mdi mdi-account-edit-outline me-2 text-primary"></i> Edit Data Staf
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="form-edit-staff">
        <input type="hidden" id="edit_id" name="id">
        <div class="modal-body p-4">
          <!-- Alert jika edit akun sendiri -->
          <div id="edit_self_alert" class="alert alert-info d-none py-2 px-3 small mb-3">
            <i class="mdi mdi-information-outline me-1"></i> Ini adalah akun Anda sendiri. Role dan status aktif tidak dapat diubah di sini.
          </div>

          <!-- Nama Lengkap -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="edit_name">Nama Lengkap <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="mdi mdi-account-outline"></i></span>
              <input type="text" class="form-control" id="edit_name" name="name" required>
            </div>
          </div>

          <!-- Email -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="edit_email">Email Login <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="mdi mdi-email-outline"></i></span>
              <input type="email" class="form-control" id="edit_email" name="email" required>
            </div>
          </div>

          <!-- WhatsApp -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="edit_wa">Nomor WhatsApp <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="mdi mdi-whatsapp"></i></span>
              <input type="text" class="form-control" id="edit_wa" name="wa" required>
            </div>
          </div>

          <!-- Role -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="edit_idRole">Role / Jabatan <span class="text-danger">*</span></label>
            <select class="form-select" id="edit_idRole" name="idRole" required>
              <option value="1">🔴 Super Admin</option>
              <option value="2">🔵 Admin</option>
              <option value="3">🟢 Bendahara</option>
              <option value="4">🟠 Teknisi</option>
              <option value="6">🔵 Kolektor</option>
              <option value="7">🟣 Teknisi & Kolektor</option>
            </select>
          </div>

          <!-- Status Aktif -->
          <div class="mb-3">
            <label class="form-label fw-semibold" for="edit_isActive">Status Akun <span class="text-danger">*</span></label>
            <select class="form-select" id="edit_isActive" name="isActive" required>
              <option value="1">✅ Aktif</option>
              <option value="0">⛔ Nonaktif</option>
            </select>
          </div>

          <!-- Password Baru (Opsional) -->
          <div class="mb-2">
            <label class="form-label fw-semibold" for="edit_password">Password Baru <small class="text-muted">(Kosongkan jika tidak diganti)</small></label>
            <div class="input-group">
              <span class="input-group-text"><i class="mdi mdi-lock-reset"></i></span>
              <input type="password" class="form-control" id="edit_password" name="password" placeholder="Minimal 8 karakter" minlength="8">
              <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="edit_password">
                <i class="mdi mdi-eye-outline"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light px-4 py-3">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary" id="btn-save-edit">
            <i class="mdi mdi-check me-1"></i> Perbarui Data
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ==================== MODAL RESET PASSWORD ==================== -->
<div class="modal fade" id="modal-reset-password" tabindex="-1" aria-labelledby="modalResetPasswordTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-label-info py-3">
        <h5 class="modal-title fw-bold d-flex align-items-center" id="modalResetPasswordTitle">
          <i class="mdi mdi-key-outline me-2 text-info"></i> Reset Password
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="form-reset-password">
        <input type="hidden" id="reset_id" name="id">
        <div class="modal-body p-3">
          <p class="small text-muted mb-2">Reset password untuk: <strong id="reset_staff_name" class="text-heading"></strong></p>
          <div class="mb-2">
            <label class="form-label fw-semibold" for="reset_password">Password Baru</label>
            <div class="input-group">
              <input type="password" class="form-control" id="reset_password" name="password" placeholder="Min. 8 karakter" minlength="8" required>
              <button class="btn btn-outline-secondary toggle-password-btn" type="button" data-target="reset_password">
                <i class="mdi mdi-eye-outline"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light px-3 py-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-info" id="btn-save-reset">Reset Sekarang</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

