# lamtim_biling_mikrotik
aplikasi biling rt rw net sederhana

## Menjalankan tes

Tes memakai database terpisah `lamtim_biling_test` (lihat `phpunit.xml`), jadi data asli tidak tersentuh.
Strukturnya dibangun dari `database/schema/mysql-schema.sql`, lalu migration yang lebih baru.

1. Buat database kosong sekali saja: `CREATE DATABASE lamtim_biling_test;`
2. Pastikan client `mysql` di PATH versi baru (MariaDB 10.5+ / MySQL 8).
3. Jalankan `php artisan test`.

Setelah menambah banyak migration, perbarui schema dengan `php artisan schema:dump`.
