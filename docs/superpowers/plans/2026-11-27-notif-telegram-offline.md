# Notifikasi Telegram Pelanggan Offline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** MikroTik melapor PPPoE up/down ke billing; billing mengirim ringkasan pelanggan offline (>10 menit, per ODP) dan online-lagi ke Telegram, ditambah pencocokan 10 menit.

**Architecture:**
- `MikrotikPpp`: pembungkus kecil di atas `MikrotikMulti::commandApi`, supaya bisa diganti tiruan di tes.
- `PppEventController`: endpoint `api` yang mencatat kejadian ke kolom `ppp_*`.
- Command `ppp:notifikasi` (tiap menit) dan `ppp:cocokkan` (tiap 10 menit): memakai `NotifikasiPpp` untuk menyusun pesan dan `TelegramService` untuk mengirim.
- Halaman MikroTik mendapat pasang/cabut skrip dan skrip manual.

**Tech Stack:** Laravel 13 (Kernel lama), PHP 8.5, PHPUnit 12, `Http` facade (Telegram Bot API `sendMessage`), RouterOS API lewat `MikrotikMulti`.

**Spec:** `docs/superpowers/specs/2026-11-27-notif-telegram-offline-design.md`

## Global Constraints

- **`.env`:**
  - `TELEGRAM_BOT_TOKEN` (kosong = notifikasi mati) dan `TELEGRAM_CHAT_ID`;
  - `PPP_NOTIF_SECRET` dan `PPP_NOTIF_URL` (kosong = `APP_URL`);
  - `PPP_NOTIF_JEDA_MENIT=10`, `PPP_NOTIF_AMBANG_MASSAL=3`.
  - Semuanya dibaca lewat `config('services.telegram.*')` dan `config('services.ppp_notif.*')`.
- **Endpoint:** `POST /api/mikrotik/ppp-event`.
  - Header `X-Notif-Token`, dibandingkan dengan `hash_equals`; token salah atau secret kosong → 403.
  - Field `m` (id mikrotik), `e` (`up`|`down`), `u` (username).
  - Pelanggan tidak dikenal → 200 `{ok:true}`.
- **Kolom baru di `lamtim_user_mikrotik_details`:**
  - `ppp_online` (boolean null);
  - `ppp_offline_sejak` (timestamp null);
  - `ppp_dikabari` (boolean default 0);
  - `ppp_secret_nonaktif` (boolean default 0).
  - Kolom `status` **tidak** diubah oleh fitur ini.
- **Syarat dikabari:** `users.isActive=1`, `statusIsolir=0`, `ppp_secret_nonaktif=0`. Pencocokan pelanggan memakai `namaMikrotikUser` + (`idMikrotik` = m atau `idMikrotik` null).
- **Pesan:**
  - satu pesan per jalan;
  - dipecah per ≤ 4096 karakter;
  - grup per nama ODP, tanpa ODP → "Tanpa ODP";
  - judul ⚠️ bila offline yang memenuhi syarat di ODP itu (termasuk yang sudah dikabari) ≥ ambang. Formatnya `n dari m`, dengan m = semua pelanggan ODP itu yang memenuhi syarat;
  - 🟢 hanya untuk yang `ppp_dikabari=1`.
- Penanda `ppp_dikabari` hanya diubah **setelah** Telegram membalas `ok:true`.
- **Pencocokan:**
  - gagal → coba lagi setelah `config('services.ppp_notif.ulang_detik', 30)` detik;
  - tetap gagal → pesan ⚠️ sekali (status di cache `ppp-mikrotik-terjangkau:<id>`), pelanggan tidak diubah;
  - pulih → ✅ sekali.
- **Blok skrip RouterOS diapit `# mryes-notif:mulai` dan `# mryes-notif:selesai`.**
  - Pasang = buang blok lama lalu tambahkan di akhir. Cabut = buang blok saja.
  - Isi on-down:
    ```
    :do { /tool fetch url="<URL>/api/mikrotik/ppp-event" http-method=post http-header-field="X-Notif-Token: <SECRET>" http-data=("m=<ID>&e=down&u=" . $user) output=none } on-error={}
    ```
  - on-up sama, dengan `e=up`.
- Teks UI dan pesan berbahasa Indonesia. Tes memakai `#[DataProvider]` attribute dan DB `lamtim_biling_test`.

## Review Focus

1. **Laporan `down` lalu `up` berulang dalam 10 menit** (router restart berkali-kali): tidak ada pesan, dan `ppp_offline_sejak` tidak tertinggal. Diuji di Task 2.
2. **Username PPPoE yang sama di dua MikroTik, atau pelanggan dengan `idMikrotik` null:** laporan hanya mengenai pelanggan yang cocok. Diuji di Task 1.
3. **Telegram gagal atau tidak diatur:** command tidak error, dan tidak ada penanda yang berubah. Diuji di Task 2.
4. **Pesan sangat panjang** (puluhan pelanggan offline sekaligus): dipecah, dan semua bagian terkirim. Diuji di Task 2.
5. **Skrip on-up/on-down profile yang sudah berisi skrip lain:** isi lain utuh setelah pasang dua kali lalu cabut. Diuji di Task 4.

---

### Task 1: Kolom, konfigurasi, MikrotikPpp, TelegramService, endpoint laporan

**Files:**
- Create: `database/migrations/2026_11_27_100000_tambah_kolom_ppp_notif.php`
- Modify: `config/services.php` (blok `telegram` dan `ppp_notif`), `.env.example`
- Create: `app/Services/MikrotikPpp.php`, `app/Services/TelegramService.php`, `app/Http/Controllers/Api/PppEventController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/PppEventTest.php`

**Interfaces:**
- Produces:
  - **`MikrotikPpp`** (didaftarkan lewat container; tes memakai `app()->instance`). Kegagalan koneksi melempar `\RuntimeException`.
    - `aktif(int $idMikrotik): array<string>`: username di `/ppp/active`.
    - `secretNonaktif(int $idMikrotik): array<string,bool>`: `name => disabled` dari `/ppp/secret`.
    - `profil(int $idMikrotik): list<array{id:string,name:string,on-up:string,on-down:string}>`.
    - `setSkripProfil(int $idMikrotik, string $id, string $onUp, string $onDown): void`.
  - **`TelegramService`**
    - `aktif(): bool`: token dan chat terisi.
    - `kirim(string $teks): bool`: memecah per 4096 karakter, POST `https://api.telegram.org/bot<token>/sendMessage` dengan `chat_id` dan `text`. Bernilai true hanya bila semua bagian `ok:true`. Tidak pernah melempar exception.
  - **Endpoint `POST /api/mikrotik/ppp-event`**, dengan route name `ppp-event`.

- [ ] **Step 1: Write failing tests** (`PppEventTest`, `config(['services.ppp_notif.secret' => 'rahasia'])`, dengan pelanggan Rizal `namaMikrotikUser='rizal@tj-1'` dan `idMikrotik` dari MikroTik tes)
  - `test_token_salah_403`: tanpa header atau token salah → 403, dan DB tidak berubah. Secret kosong di config → 403 walau header kosong.
  - `test_down_catat_offline_sejak`: `e=down` → `ppp_online=0`, `ppp_offline_sejak` = sekarang. Laporan `down` kedua 5 menit kemudian tidak menggeser `ppp_offline_sejak`.
  - `test_up_sebelum_dikabari_hapus_offline_sejak`: down lalu up → `ppp_online=1`, `ppp_offline_sejak=null`, `ppp_dikabari=0`.
  - `test_up_setelah_dikabari_biarkan_untuk_pesan_online`: `ppp_dikabari=1`, lalu up → `ppp_online=1`, dan `ppp_offline_sejak` tetap terisi.
  - `test_user_tak_dikenal_200`.
  - `test_mikrotik_lain_tidak_kena` (Review Focus 2): pelanggan ber-`idMikrotik` lain dengan username sama tidak berubah. Pelanggan dengan `idMikrotik` null ikut berubah.
  - `test_telegram_kirim_dipecah` (unit `TelegramService`): teks 9000 karakter → `Http::assertSentCount(3)`, hasil true. Balasan `ok:false` → false. `ConnectionException` → false.
- [ ] **Step 2: Run, verify fail.** `vendor/bin/phpunit tests/Feature/PppEventTest.php`
- [ ] **Step 3: Implement.** Endpoint ada di grup `api` (tanpa CSRF), dengan throttle bawaan `api`.
- [ ] **Step 4: Run, verify pass**, lalu migrate DB lokal: `php artisan migrate`.
- [ ] **Step 5: Commit** `feat(notif): kolom status PPP dan endpoint laporan MikroTik`

---

### Task 2: Ringkasan offline / online-lagi (`ppp:notifikasi`)

**Files:**
- Create: `app/Services/NotifikasiPpp.php`, `app/Console/Commands/PppNotifikasi.php`
- Modify: `app/Console/Kernel.php` (`ppp:notifikasi` `everyMinute()->withoutOverlapping()`)
- Modify: `app/Services/MikrotikService.php`. `disablePppoeUser`/`enablePppoeUser` yang berhasil mengisi `ppp_secret_nonaktif` 1/0 untuk pelanggan dengan username itu.
- Test: `tests/Feature/PppNotifikasiTest.php`

**Interfaces:**
- Consumes: `TelegramService::kirim()`, `MikrotikPpp::secretNonaktif()`.
- Produces: `NotifikasiPpp::jalankan(): ?string`. Mengembalikan teks yang terkirim, atau null bila tidak ada yang dikirim. Command hanya memanggilnya.

- [ ] **Step 1: Write failing tests** (`Http::fake(['api.telegram.org/*' => Http::response(['ok'=>true])])`; MikroTik tiruan `secretNonaktif` → `[]`; 2 ODP: TJI-1 dengan 4 pelanggan dan TJI-6 dengan 1 pelanggan)
  - `test_offline_kurang_10_menit_tidak_dikirim`: offline sejak 9 menit → null, `Http::assertNothingSent()`.
  - `test_offline_10_menit_dikirim_per_odp`: Rizal (TJI-1) dan Bagos (TJI-6) offline 11 menit → teks berisi `ODP TJI-1`, `RIZAL`, `ODP TJI-6`, `BAGOS`. Keduanya `ppp_dikabari=1`. Jalan kedua → null.
  - `test_judul_massal_3_dari_4`: 3 pelanggan TJI-1 offline → teks berisi `⚠️` dan `3 dari 4`.
  - `test_massal_menghitung_yang_sudah_dikabari`: 2 pelanggan sudah dikabari dan masih offline, 1 baru → teks berisi `3 dari 4`, dan hanya pelanggan baru yang didaftar.
  - `test_online_lagi_hanya_yang_dikabari`: Rizal dikabari lalu `ppp_online=1` → teks berisi `🟢` dan `RIZAL`, lalu `ppp_dikabari=0`, `ppp_offline_sejak=null`. Pelanggan online yang tidak dikabari tidak muncul.
  - `test_isolir_nonaktif_secret_tidak_dikabari`, dengan `#[DataProvider]`: `statusIsolir=1`; `users.isActive=0`; `ppp_secret_nonaktif=1` → null.
  - `test_secret_dinonaktifkan_di_winbox_dibuang`: tiruan `secretNonaktif` → `['rizal@tj-1' => true]` → Rizal tidak di teks, dan `ppp_secret_nonaktif=1`.
  - `test_mikrotik_mati_saat_cek_secret_tetap_kirim`: tiruan melempar `RuntimeException` → tetap terkirim.
  - `test_telegram_gagal_tidak_menandai` (Review Focus 3): Telegram `ok:false` → `ppp_dikabari` tetap 0. Jalan berikutnya dengan `ok:true` → terkirim.
  - `test_telegram_belum_diatur_diam`: token kosong → null, tanpa error.
  - `test_down_up_berulang_tanpa_pesan` (Review Focus 1): lewat endpoint, down–up tiga kali dalam 8 menit → `jalankan()` null.
  - `test_pesan_panjang_dipecah` (Review Focus 4): 120 pelanggan offline → `Http::assertSentCount(>1)`, dan semuanya `ppp_dikabari=1`.
  - `test_disable_dari_billing_tandai_secret_nonaktif`: `MikrotikService::disablePppoeUser` dengan klien tiruan sukses → `ppp_secret_nonaktif=1`. Bila klien tiruan sulit dibuat, uji lewat metode kecil `tandaiSecret(string $username, bool $nonaktif)` yang dipanggil kedua metode itu.
- [ ] **Step 2: Run, verify fail.**
- [ ] **Step 3: Implement.** Durasi ditampilkan sebagai `N menit` (< 60) atau `J jam M menit`. Nama pelanggan dari `users.name`.
- [ ] **Step 4: Run, verify pass**, lalu seluruh suite.
- [ ] **Step 5: Commit** `feat(notif): ringkasan Telegram pelanggan offline dan online lagi`

---

### Task 3: Pencocokan 10 menit (`ppp:cocokkan`)

**Files:**
- Create: `app/Console/Commands/PppCocokkan.php` (logika di `NotifikasiPpp::cocokkan(int $idMikrotik): void`)
- Modify: `app/Console/Kernel.php` (`everyTenMinutes()->withoutOverlapping()`)
- Test: `tests/Feature/PppCocokkanTest.php`

**Interfaces:**
- Consumes: `MikrotikPpp::aktif()`, `secretNonaktif()`, `TelegramService::kirim()`.
- Produces: `NotifikasiPpp::cocokkan(int $idMikrotik): void`. Command menjalankannya untuk setiap `Lamtim_mikrotik` dengan `isActive=1`.

- [ ] **Step 1: Write failing tests** (`config(['services.ppp_notif.ulang_detik' => 0])`)
  - `test_cocokkan_membetulkan_status`:
    - Rizal tercatat online tapi tidak aktif di MikroTik → `ppp_online=0` dan `ppp_offline_sejak` = sekarang.
    - Bagos tercatat offline tapi aktif → `ppp_online=1`.
    - Pelanggan `ppp_online=null` ikut diisi.
  - `test_secret_disabled_ditandai`: `secretNonaktif` → `['rizal@tj-1'=>true,'bagos@tj-6'=>false]` → Rizal 1, Bagos 0.
  - `test_mikrotik_tak_terjangkau_satu_pesan_dan_status_tetap`: tiruan selalu melempar → satu pesan berisi `tidak terjangkau`, status pelanggan tidak berubah. Jalan kedua → tidak ada pesan baru.
  - `test_mikrotik_pulih_satu_pesan`: setelah tidak terjangkau, tiruan normal → satu pesan berisi `terjangkau lagi`.
  - `test_gagal_sekali_lalu_berhasil_tanpa_pesan`: tiruan gagal sekali lalu sukses → tidak ada pesan.
- [ ] **Step 2: Run, verify fail.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run, verify pass**, lalu seluruh suite.
- [ ] **Step 5: Commit** `feat(notif): pencocokan status PPP tiap 10 menit`

---

### Task 4: Pasang/cabut skrip, skrip manual, peta, dan panduan

**Files:**
- Create: `app/Services/SkripNotifPpp.php`
  - `blok(int $idMikrotik, string $event): string`
  - `sisipkan(string $skripLama, string $blok): string`
  - `buang(string $skrip): string`
  - `pasang(int $idMikrotik): int`, yang mengembalikan jumlah profile
  - `cabut(int $idMikrotik): int`
- Modify: `routes/modules/mikrotik.php` (role 1,2 sudah ada di grupnya):
  - `POST /mikrotik/{id}/notif/pasang` → `mikrotik.notif.pasang`
  - `POST /mikrotik/{id}/notif/cabut` → `mikrotik.notif.cabut`
  - `GET /mikrotik/{id}/notif/skrip` → `mikrotik.notif.skrip`, JSON `{on_up, on_down}`
- Modify: `app/Http/Controllers/Mikrotik/MikrotikController.php`, dan `resources/views/content/server/mikrotik.blade.php` (aksi "Notifikasi Telegram" per baris: Pasang / Cabut / Lihat skrip manual dalam modal dengan tombol salin)
- Modify: `app/Services/NetworkMapService.php::getClients()`. `status_client` menjadi `isolir` bila `statusIsolir=1` atau `status=isolir`; selain itu `ppp_online` true/false → `online`/`offline`; bila null → `status` seperti sekarang.
- Modify: `.env.example`, `README.md` (bagian singkat "Notifikasi Telegram"), `docs/genieacs/README.md` tidak disentuh.
- Test: `tests/Feature/SkripNotifPppTest.php`, plus satu tes peta di `tests/Feature/OntPelangganTest.php` (`test_peta_pakai_status_ppp`).

**Interfaces:**
- Consumes: `MikrotikPpp::profil()`, `setSkripProfil()`, config `ppp_notif.url` dan `ppp_notif.secret`.

- [ ] **Step 1: Write failing tests**
  - `test_blok_berisi_url_secret_event`: `blok(1,'down')` berisi `/api/mikrotik/ppp-event`, `X-Notif-Token: rahasia`, `m=1&e=down&u=`, `on-error={}`, dan kedua penanda.
  - `test_sisipkan_dua_kali_tidak_ganda_dan_cabut_utuh` (Review Focus 5): skrip lama `:log info "lama"`; `sisipkan` dua kali → penanda mulai muncul 1 kali dan `:log info "lama"` tetap ada; `buang` → kembali persis `:log info "lama"` (trim).
  - `test_pasang_semua_profil`: tiruan dengan 2 profil → `setSkripProfil` dipanggil 2 kali dengan on-up berisi `e=up` dan on-down berisi `e=down`; hasil 2.
  - `test_pasang_ditolak_tanpa_secret`: `ppp_notif.secret` kosong → 422, dengan pesan berisi `PPP_NOTIF_SECRET`.
  - `test_route_notif_hanya_admin`: role 4 → 403, role 1 → 200 (tiruan).
  - `test_peta_pakai_status_ppp`: `ppp_online=0` dengan `status='online'` → `status_client='offline'`; `statusIsolir=1` → `isolir`; `ppp_online=null` → `status`.
- [ ] **Step 2: Run, verify fail.**
- [ ] **Step 3: Implement.** Modal skrip manual menampilkan peringatan "berisi kode rahasia, jangan dibagikan".
- [ ] **Step 4: Run seluruh suite.**
  - Cek halaman MikroTik di Chrome 1366 dan 390 px, terang dan gelap: tidak meluber dan tidak ada error JS.
  - Uji ujung-ke-ujung lokal tanpa MikroTik: `curl` POST `down` ke endpoint dengan token, `travel` dengan mengubah `ppp_offline_sejak` di DB lokal ke 11 menit lalu, lalu `php artisan ppp:notifikasi` → pesan sungguhan masuk ke Telegram pemilik. Pelanggan uji lalu dikembalikan.
- [ ] **Step 5: Commit** `feat(notif): pasang skrip MikroTik, skrip manual, dan peta memakai status PPP`
