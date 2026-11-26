# lamtim_biling_mikrotik
aplikasi biling rt rw net sederhana

## Pasang / update di server

1. `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` milik server sendiri (jangan salin dari `.env.example`).
2. Ambil kode lalu jalankan:
   ```
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   npm ci && npm run build
   php artisan optimize
   ```
   Build frontend butuh Node 18 ke atas.
3. Cron sekali saja, supaya tagihan bulanan terbit otomatis tiap tanggal 1 jam 00:10:
   `* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1`

## GenieACS (ONT & WiFi pelanggan)

Opsional. Isi di `.env`, lalu ikuti [docs/genieacs/README.md](docs/genieacs/README.md) (membuka NBI dan memasang 4 virtual parameter WiFi):

```
GENIEACS_URL=http://<ip-genieacs>:7557   # kosong = fitur ONT mati
GENIEACS_USER=
GENIEACS_PASS=
GENIEACS_ONLINE_MENIT=10
```

## Menjalankan tes

Tes memakai database terpisah `lamtim_biling_test` (lihat `phpunit.xml`), jadi data asli tidak tersentuh.
Strukturnya dibangun dari `database/schema/mysql-schema.sql`, lalu migration yang lebih baru.

1. Buat database kosong sekali saja: `CREATE DATABASE lamtim_biling_test;`
2. Pastikan client `mysql` di PATH versi baru (MariaDB 10.5+ / MySQL 8).
3. Jalankan `php artisan test`.

Setelah menambah banyak migration, perbarui schema dengan `php artisan schema:dump`.
