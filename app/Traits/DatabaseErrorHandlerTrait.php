<?php


namespace App\Traits;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Dipakai bersama ApiResponseTrait. Detail error (SQL) hanya ke log, tidak ke browser.
 */
trait DatabaseErrorHandlerTrait
{
  public function handleDatabaseException(QueryException $e, $defaultMessage = 'Database error occurred')
  {
    Log::error('Database Error: ' . $e->getMessage(), [
      'code' => $e->getCode(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
      'sql' => $e->getSql() ?? 'N/A'
    ]);

    return $this->errorResponse($this->getDatabaseErrorMessage($e->getCode()));
  }

  /**
   * Pesan yang ramah pengguna berdasarkan kode error database (SQLSTATE)
   */
  private function getDatabaseErrorMessage($errorCode)
  {
    $errorMessages = [
      // Integrity constraint violations
      '23000' => 'Data duplikat atau melanggar aturan database',
      '23502' => 'Field yang wajib diisi tidak boleh kosong',
      '23503' => 'Data tidak dapat dihapus karena masih digunakan oleh data lain',
      '23505' => 'Data dengan nilai yang sama sudah ada',
      '23514' => 'Data tidak memenuhi kriteria yang ditetapkan',

      // Transaction errors
      '40001' => 'Deadlock terdeteksi, silakan coba lagi',
      '40002' => 'Serialization failure, silakan coba lagi',
      '40P01' => 'Deadlock terdeteksi saat menunggu resource',

      // Syntax and schema errors
      '42000' => 'Sintaks SQL tidak valid',
      '42S02' => 'Tabel database tidak ditemukan',
      '42S22' => 'Kolom database tidak ditemukan',
      '42S21' => 'Kolom sudah ada',
      '42S01' => 'Tabel sudah ada',

      // Connection and general errors
      'HY000' => 'Error umum database',
      'HY001' => 'Alokasi memori database gagal',
      'HY003' => 'Parameter tidak valid',
      'HY004' => 'Tipe data SQL tidak valid',
      'HY007' => 'Statement tidak siap untuk eksekusi',
      'HY010' => 'Sequence error dalam fungsi',
      'HY013' => 'Manajemen memori database error',

      // MySQL specific errors
      '08S01' => 'Koneksi database terputus',
      '22001' => 'Data terlalu panjang untuk kolom',
      '22003' => 'Nilai numerik di luar rentang',
      '22007' => 'Format datetime tidak valid',
      '22012' => 'Pembagian dengan nol',

      // PostgreSQL specific errors
      '08001' => 'Tidak dapat terhubung ke database',
      '08006' => 'Koneksi database gagal',
      '53300' => 'Terlalu banyak koneksi database',

      // Access and permission errors
      '28000' => 'Akses ditolak untuk user database',
      '42501' => 'Tidak memiliki hak akses yang cukup',
    ];

    return $errorMessages[$errorCode] ?? 'Terjadi kesalahan pada database';
  }
}
