<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class StaffController extends Controller
{
  /**
   * Tampilkan halaman utama manajemen staf.
   */
  public function index()
  {
    return view('content.staff.index');
  }

  /**
   * Data JSON untuk DataTables staf (Role 1 s/d 4).
   */
  public function json(Request $request): JsonResponse
  {
    $query = User::query()
      ->whereIn('idRole', [1, 2, 3, 4, 6])
      ->select(['id', 'name', 'julukan', 'email', 'wa', 'idRole', 'isActive', 'created_at']);

    return DataTables::of($query)
      ->addIndexColumn()
      ->addColumn('role_badge', function ($user) {
        return match ((int) $user->idRole) {
          1 => '<span class="badge bg-label-danger"><i class="mdi mdi-shield-crown me-1"></i>Super Admin</span>',
          2 => '<span class="badge bg-label-primary"><i class="mdi mdi-account-cog me-1"></i>Admin</span>',
          3 => '<span class="badge bg-label-success"><i class="mdi mdi-cash-register me-1"></i>Bendahara</span>',
          4 => '<span class="badge bg-label-warning"><i class="mdi mdi-wrench me-1"></i>Teknisi</span>',
          6 => '<span class="badge bg-label-info"><i class="mdi mdi-cash-multiple me-1"></i>Kolektor</span>',
          default => '<span class="badge bg-label-secondary">Lainnya</span>',
        };
      })
      ->addColumn('status_badge', function ($user) {
        if ($user->isActive) {
          return '<span class="badge bg-label-success"><i class="mdi mdi-check-circle-outline me-1"></i>Aktif</span>';
        }
        return '<span class="badge bg-label-secondary"><i class="mdi mdi-close-circle-outline me-1"></i>Nonaktif</span>';
      })
      ->addColumn('action', function ($user) {
        $isCurrentUser = $user->id === Auth::id();
        $currentUserBadge = $isCurrentUser ? ' <span class="badge bg-label-info ms-1" style="font-size:10px;">Anda</span>' : '';

        $toggleLabel = $user->isActive ? 'Nonaktifkan' : 'Aktifkan';
        $toggleIcon = $user->isActive ? 'mdi-account-off-outline text-warning' : 'mdi-account-check-outline text-success';

        $disableAttr = $isCurrentUser ? 'disabled title="Akun Anda sendiri"' : '';

        return '
          <div class="dropdown text-center">
            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="mdi mdi-dots-vertical fs-5"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-sm border-0">
              <a class="dropdown-item fw-semibold" href="javascript:void(0);" onclick="openEditModal(' . $user->id . ')">
                <i class="mdi mdi-pencil-outline me-2 text-primary"></i> Edit Data
              </a>
              <a class="dropdown-item fw-semibold" href="javascript:void(0);" onclick="openResetPasswordModal(' . $user->id . ', \'' . e($user->name) . '\')">
                <i class="mdi mdi-key-outline me-2 text-info"></i> Reset Password
              </a>' .
              (!$isCurrentUser ? '
              <a class="dropdown-item fw-semibold" href="javascript:void(0);" onclick="toggleStaffStatus(' . $user->id . ')">
                <i class="mdi ' . $toggleIcon . ' me-2"></i> ' . $toggleLabel . '
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item text-danger fw-semibold" href="javascript:void(0);" onclick="deleteStaff(' . $user->id . ', \'' . e($user->name) . '\')">
                <i class="mdi mdi-trash-can-outline me-2"></i> Hapus Akun
              </a>' : '') . '
            </div>
          </div>
        ';
      })
      ->rawColumns(['role_badge', 'status_badge', 'action'])
      ->make(true);
  }

  /**
   * Simpan staf baru.
   */
  public function store(Request $request): JsonResponse
  {
    $validator = Validator::make($request->all(), [
      'name' => ['required', 'string', 'max:100'],
      'email' => ['required', 'email', 'max:100', 'unique:users,email'],
      'wa' => ['required', 'string', 'max:25'],
      'idRole' => ['required', 'in:1,2,3,4,6'],
      'password' => ['required', 'string', 'min:8'],
      'isActive' => ['nullable', 'in:0,1'],
    ], [
      'name.required' => 'Nama lengkap wajib diisi.',
      'email.required' => 'Email wajib diisi.',
      'email.email' => 'Format email tidak valid.',
      'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
      'wa.required' => 'Nomor WhatsApp wajib diisi.',
      'idRole.required' => 'Role/Jabatan wajib dipilih.',
      'idRole.in' => 'Pilihan role tidak valid.',
      'password.required' => 'Password wajib diisi.',
      'password.min' => 'Password minimal 8 karakter.',
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => $validator->errors()->first(),
        'errors' => $validator->errors(),
      ], 422);
    }

    try {
      $user = User::create([
        'name' => trim($request->name),
        'email' => strtolower(trim($request->email)),
        'wa' => trim($request->wa),
        'idRole' => (int) $request->idRole,
        'password' => Hash::make($request->password),
        'isActive' => $request->has('isActive') ? (int) $request->isActive : 1,
      ]);

      return response()->json([
        'success' => true,
        'message' => "Staf '{$user->name}' berhasil ditambahkan.",
        'data' => $user,
      ], 201);
    } catch (\Throwable $e) {
      return response()->json([
        'success' => false,
        'message' => 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Ambil detail staf untuk form edit.
   */
  public function show($id): JsonResponse
  {
    $user = User::whereIn('idRole', [1, 2, 3, 4, 6])->find($id);

    if (!$user) {
      return response()->json([
        'success' => false,
        'message' => 'Data staf tidak ditemukan.',
      ], 404);
    }

    return response()->json([
      'success' => true,
      'data' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'wa' => $user->wa,
        'idRole' => $user->idRole,
        'isActive' => $user->isActive,
        'isCurrentUser' => $user->id === Auth::id(),
      ],
    ]);
  }

  /**
   * Update data staf.
   */
  public function update(Request $request, $id): JsonResponse
  {
    $user = User::whereIn('idRole', [1, 2, 3, 4, 6])->find($id);

    if (!$user) {
      return response()->json([
        'success' => false,
        'message' => 'Data staf tidak ditemukan.',
      ], 404);
    }

    $isCurrentUser = $user->id === Auth::id();

    $validator = Validator::make($request->all(), [
      'name' => ['required', 'string', 'max:100'],
      'email' => ['required', 'email', 'max:100', 'unique:users,email,' . $user->id],
      'wa' => ['required', 'string', 'max:25'],
      'idRole' => ['required', 'in:1,2,3,4,6'],
      'isActive' => ['required', 'in:0,1'],
      'password' => ['nullable', 'string', 'min:8'],
    ], [
      'name.required' => 'Nama lengkap wajib diisi.',
      'email.required' => 'Email wajib diisi.',
      'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
      'wa.required' => 'Nomor WhatsApp wajib diisi.',
      'idRole.required' => 'Role wajib dipilih.',
      'password.min' => 'Password baru minimal 8 karakter.',
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => $validator->errors()->first(),
        'errors' => $validator->errors(),
      ], 422);
    }

    // Proteksi akun sendiri
    if ($isCurrentUser) {
      if ((int) $request->isActive === 0) {
        return response()->json([
          'success' => false,
          'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang aktif.',
        ], 422);
      }
      if ((int) $request->idRole !== 1) {
        return response()->json([
          'success' => false,
          'message' => 'Anda tidak dapat mengubah role akun Anda sendiri dari Super Admin.',
        ], 422);
      }
    }

    try {
      $user->name = trim($request->name);
      $user->email = strtolower(trim($request->email));
      $user->wa = trim($request->wa);
      $user->idRole = (int) $request->idRole;
      $user->isActive = (int) $request->isActive;

      if (!empty($request->password)) {
        $user->password = Hash::make($request->password);
      }

      $user->save();

      return response()->json([
        'success' => true,
        'message' => "Data staf '{$user->name}' berhasil diperbarui.",
        'data' => $user,
      ]);
    } catch (\Throwable $e) {
      return response()->json([
        'success' => false,
        'message' => 'Gagal memperbarui data: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Toggle status aktif / nonaktif staf.
   */
  public function toggleStatus($id): JsonResponse
  {
    $user = User::whereIn('idRole', [1, 2, 3, 4, 6])->find($id);

    if (!$user) {
      return response()->json([
        'success' => false,
        'message' => 'Data staf tidak ditemukan.',
      ], 404);
    }

    if ($user->id === Auth::id()) {
      return response()->json([
        'success' => false,
        'message' => 'Anda tidak dapat mengubah status akun Anda sendiri.',
      ], 422);
    }

    try {
      $user->isActive = $user->isActive ? 0 : 1;
      $user->save();

      $statusStr = $user->isActive ? 'diaktifkan' : 'dinonaktifkan';

      return response()->json([
        'success' => true,
        'message' => "Akun '{$user->name}' berhasil {$statusStr}.",
        'isActive' => $user->isActive,
      ]);
    } catch (\Throwable $e) {
      return response()->json([
        'success' => false,
        'message' => 'Gagal mengubah status: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Reset password staf secara langsung.
   */
  public function resetPassword(Request $request, $id): JsonResponse
  {
    $user = User::whereIn('idRole', [1, 2, 3, 4, 6])->find($id);

    if (!$user) {
      return response()->json([
        'success' => false,
        'message' => 'Data staf tidak ditemukan.',
      ], 404);
    }

    $validator = Validator::make($request->all(), [
      'password' => ['required', 'string', 'min:8'],
    ], [
      'password.required' => 'Password baru wajib diisi.',
      'password.min' => 'Password minimal 8 karakter.',
    ]);

    if ($validator->fails()) {
      return response()->json([
        'success' => false,
        'message' => $validator->errors()->first(),
      ], 422);
    }

    try {
      $user->password = Hash::make($request->password);
      $user->save();

      return response()->json([
        'success' => true,
        'message' => "Password untuk '{$user->name}' berhasil direset.",
      ]);
    } catch (\Throwable $e) {
      return response()->json([
        'success' => false,
        'message' => 'Gagal mereset password: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Hapus akun staf.
   */
  public function destroy($id): JsonResponse
  {
    $user = User::whereIn('idRole', [1, 2, 3, 4, 6])->find($id);

    if (!$user) {
      return response()->json([
        'success' => false,
        'message' => 'Data staf tidak ditemukan.',
      ], 404);
    }

    if ($user->id === Auth::id()) {
      return response()->json([
        'success' => false,
        'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
      ], 422);
    }

    try {
      $name = $user->name;
      $user->delete();

      return response()->json([
        'success' => true,
        'message' => "Akun staf '{$name}' berhasil dihapus.",
      ]);
    } catch (\Throwable $e) {
      return response()->json([
        'success' => false,
        'message' => 'Gagal menghapus data: ' . $e->getMessage(),
      ], 500);
    }
  }
}

