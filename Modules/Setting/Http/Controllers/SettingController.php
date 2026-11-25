<?php

namespace Modules\Setting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingController extends Controller
{
    public function index()
    {
        return view('setting::index');
    }

    public function hapusBersih(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
            'groups' => 'required|array',
        ], [
            'password.required' => 'Masukkan password akun Anda untuk melanjutkan.',
            'password.current_password' => 'Password salah. Data tidak dihapus.',
            'groups.required' => 'Pilih minimal satu kelompok data yang akan dihapus.'
        ]);

        $groups = $request->groups;
        $deletedTables = [];

        try {
            // Catatan: Truncate di MySQL adalah DDL dan menyebabkan implicit commit, 
            // sehingga tidak bisa dibungkus dalam transaction secara efektif.
            Schema::disableForeignKeyConstraints();

            if (in_array('pelanggan', $groups)) {
                $this->safeTruncate('lamtim_user_mikrotik_details');
                $this->safeTruncate('lamtim_user_details');
                DB::table('users')->where('idRole', 5)->delete();
                $deletedTables[] = 'Data Pelanggan';
            }

            if (in_array('tagihan', $groups)) {
                $this->safeTruncate('lamtim_tagihans');
                $this->safeTruncate('lamtim_transaksis');
                $this->safeTruncate('lamtim_diskons');
                $this->safeTruncate('lamtim_payment_metodes');
                $deletedTables[] = 'Data Tagihan & Transaksi';
            }

            if (in_array('layanan', $groups)) {
                $this->safeTruncate('lamtim_pakets');
                $this->safeTruncate('lamtim_kategoris');
                $deletedTables[] = 'Data Layanan (Paket & Kategori)';
            }

            if (in_array('infrastruktur', $groups)) {
                $this->safeTruncate('lamtim_mikrotiks');
                $this->safeTruncate('lamtim_olts');
                $this->safeTruncate('lamtim_odcs');
                $this->safeTruncate('lamtim_odps');
                $this->safeTruncate('lamtim_ip_pools');
                $this->safeTruncate('lamtim_address_lists');
                $deletedTables[] = 'Data Infrastruktur (Mikrotik, OLT, ODC, ODP, IP Pool, Address List)';
            }

            if (in_array('pemetaan', $groups)) {
                $this->safeTruncate('lamtim_areas');
                $this->safeTruncate('lamtim_fotos');
                $deletedTables[] = 'Data Pemetaan (Area, Foto)';
            }

            if (in_array('logs', $groups)) {
                $this->safeTruncate('lamtim_logs');
                $deletedTables[] = 'Data Log Sistem';
            }

            Schema::enableForeignKeyConstraints();

            return response()->json([
                'success' => true,
                'message' => 'Berhasil menghapus: ' . implode(', ', $deletedTables)
            ]);

        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function safeTruncate($table)
    {
        if (Schema::hasTable($table)) {
            DB::table($table)->truncate();
        }
    }
}
