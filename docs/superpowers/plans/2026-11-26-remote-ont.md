# Remote Web ONT Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Teknisi membuka web admin ONT pelanggan di dalam halaman ONT billing, dengan server billing sebagai perantara, lewat subdomain remote.

**Architecture:**
- `RemoteOntService` membuat dan memeriksa sesi remote. Datanya disimpan di cache server, dan kuncinya dibawa oleh tiket atau cookie terenkripsi.
- Middleware global `OntRemoteProxy` menangkap setiap request ber-Host `GENIEACS_REMOTE_HOST`, lalu meneruskannya ke `http://<ip ONT>:80` dengan `Http`. Request ke host lain lewat tanpa diubah.
- Halaman ONT pelanggan mendapat kartu Remote ONT, yang meminta tiket ke route baru, lalu memasang iframe.

**Tech Stack:** Laravel 13 (Kernel lama `App\Http\Kernel`), PHP 8.5, Blade + jQuery, PHPUnit 12, cache bawaan (`file`; `array` saat tes).

**Spec:** `docs/superpowers/specs/2026-11-26-remote-ont-design.md`

## Global Constraints

- `.env`: `GENIEACS_REMOTE_HOST` (kosong = fitur remote mati), dibaca sebagai `config('services.genieacs.remote_host')`.
- Hak akses: role 1, 2, 4, 7. Role 3 dan 6 ditolak.
- IP tujuan hanya dari GenieACS: `VirtualParameters.IPTR069`, cadangannya `VirtualParameters.pppoeIP`. Tidak pernah dari isian pengguna. Port selalu 80, skema selalu http.
- Masa berlaku:
  - tiket 60 detik, sekali pakai;
  - sesi remote 2 jam;
  - IP dicek ulang ke GenieACS bila pengecekan terakhir lebih dari 5 menit lalu.
- Sesi remote berakhir bila user tidak aktif, role tidak lagi 1/2/4/7, sesi billing yang membuatnya sudah tidak ada (logout atau kedaluwarsa), atau device tidak ditemukan di GenieACS.
- Cookie remote:
  - nama `ont_remote`;
  - isinya hanya ID sesi, dienkripsi `Crypt`;
  - atribut HttpOnly, SameSite=Lax, Secure bila request HTTPS;
  - path `/`;
  - tidak pernah diteruskan ke ONT.
- Balasan ONT:
  - buang `X-Frame-Options`, `Content-Security-Policy`, `Transfer-Encoding`, `Connection`, `Content-Encoding` dan `Content-Length`;
  - `Location` yang diawali `http://<ip>` dijadikan relatif;
  - buang atribut `Domain=` dari `Set-Cookie`.
- Timeout request ke ONT 15 detik. Redirect tidak diikuti.
- Login admin ONT dari `superAdmin`/`superPassword` dan `userAdmin`/`userPassword`. Tidak pernah ditulis ke log.
- Log `Log::info('ONT remote', [...])` saat sesi dibuat: oleh, pelanggan, sn, ip.
- Teks UI berbahasa Indonesia. Tes memakai `#[DataProvider]` attribute.

## Review Focus

1. **ONT tidak menjawab (mati atau IP tak terjangkau):** host remote menampilkan halaman "ONT tidak bisa dihubungi" dengan status 502, bukan error 500. Diuji di Task 2.
2. **Billing dibuka dari host remote** (misalnya `ont.domainmu.com/user`): tidak boleh menampilkan halaman billing. Semua path di host remote adalah wilayah ONT. Diuji di Task 2.
3. **Halaman ONT berukuran besar atau berupa file biner** (gambar, JS): diteruskan utuh dengan `Content-Type` aslinya. Diuji di Task 2.
4. **GenieACS mati saat IP dicek ulang:** sesi tetap memakai IP terakhir yang diketahui. Sesi hanya diakhiri bila device benar-benar tidak ditemukan. Diuji di Task 1.
5. **Dua pelanggan dibuka bergantian di satu browser:** cookie diganti, dan ONT pertama tidak lagi bisa dibuka lewat cookie lama. Diuji di Task 2.

---

### Task 1: Sesi remote dan data ONT tambahan

**Files:**
- Modify: `config/services.php` (tambah `'remote_host' => env('GENIEACS_REMOTE_HOST', '')` di blok `genieacs`), `.env.example`
- Modify: `app/Services/GenieAcsService.php`:
  - `PROYEKSI` ditambah `VirtualParameters` (sudah ada);
  - `bentukOnt()` ditambah kunci `ip` (`IPTR069`, cadangan `pppoeIP`, null bila keduanya kosong) dan `login`:
    ```
    ['admin' => ['user' => ?string, 'pass' => ?string], 'user' => ['user' => ?string, 'pass' => ?string]]
    ```
- Create: `app/Services/RemoteOntService.php`
- Test: `tests/Feature/RemoteOntServiceTest.php`

**Interfaces:**
- Consumes: `GenieAcsService::devices(array $query): array` (bentuk `ont`, sekarang dengan `ip`, `login`); `GenieAcsTidakTerjangkau`.
- Produces:
  - `RemoteOntService::aktif(): bool`. True bila `remote_host` terisi **dan** `GenieAcsService::aktif()`.
  - `RemoteOntService::buatTiket(User $staf, string $sessionIdBilling, array $ont): string`. Menyimpan sesi `{user, sesi, device, ip, cek}` di cache dengan kunci `ont-remote:<id acak 40>` selama 2 jam. Mengembalikan tiket `Crypt::encryptString(json {id, exp: now+60})`. Melempar `\DomainException` bila `$ont['ip']` null.
  - `RemoteOntService::tukarTiket(string $tiket): ?string`. Mengembalikan ID sesi bila tiket sah, belum kedaluwarsa, dan belum dipakai. Penanda sekali pakai: `Cache::add("ont-remote-tiket:<id>", 1, 120)`.
  - `RemoteOntService::sesi(?string $id): ?array`. Mengembalikan `['ip' => string, 'user' => int]` bila sesi sah, atau null. Fungsi ini juga mengecek ulang IP lewat `devices(['_id' => device])` bila `cek` lebih dari 5 menit lalu, lalu memperbarui cache.

- [ ] **Step 1: Write the failing tests** (cache `array`, `Http::fake` untuk NBI `acs.test:7557`, user role 4 `isActive=1`, sesi billing tiruan dengan `Session::getHandler()->write($sid, 'x')`)
  - `test_ont_punya_ip_dan_login`: device dengan `IPTR069='10.1.1.5'`, `superAdmin='telecomadmin'`, `superPassword='adm1n'` → `ip==='10.1.1.5'`, `login.admin.user==='telecomadmin'`. Bila `IPTR069` kosong dan `pppoeIP='100.66.1.2'` → `ip==='100.66.1.2'`.
  - `test_tiket_ditukar_sekali`: `tukarTiket($t)` mengembalikan id, panggilan kedua null.
  - `test_tiket_kedaluwarsa_atau_palsu_ditolak`: `travel(61)->seconds()` → null; string acak → null.
  - `test_sesi_berakhir_bila_user_nonaktif_atau_role_6`: ubah `isActive=0` → `sesi($id)===null`; ubah `idRole=6` → null.
  - `test_sesi_berakhir_setelah_logout_billing`: `Session::getHandler()->destroy($sid)` → null.
  - `test_ip_dicek_ulang_dan_ikut_berubah`: `travel(6)->minutes()`, NBI mengembalikan device sama dengan `IPTR069='10.1.1.9'` → `sesi($id)['ip']==='10.1.1.9'`.
  - `test_device_hilang_sesi_berakhir`: NBI membalas `[]` → null.
  - `test_genieacs_mati_pakai_ip_lama` (Review Focus 4): NBI melempar `ConnectionException` → `ip==='10.1.1.5'`.
  - `test_tanpa_ip_tidak_bisa_buat_tiket`: `expectException(\DomainException::class)`.
- [ ] **Step 2: Run, verify fail.** `vendor/bin/phpunit tests/Feature/RemoteOntServiceTest.php` → class not found.
- [ ] **Step 3: Implement** config, tambahan `bentukOnt`, dan `RemoteOntService`. Sesi billing dianggap hidup bila `Session::getHandler()->read($sid) !== ''`.
- [ ] **Step 4: Run, verify pass.** Lalu jalankan seluruh tes GenieACS/ONT; tidak ada yang rusak.
- [ ] **Step 5: Commit** `feat(ont-remote): sesi remote ONT dengan tiket sekali pakai`

---

### Task 2: Middleware proxy di host remote

**Files:**
- Create: `app/Http/Middleware/OntRemoteProxy.php`
- Modify: `app/Http/Kernel.php` (taruh paling depan di `$middleware`)
- Create: `resources/views/content/ont/remote-berakhir.blade.php` (halaman polos tanpa layout; menerima `$pesan`)
- Test: `tests/Feature/OntRemoteProxyTest.php`

**Interfaces:**
- Consumes: `RemoteOntService::tukarTiket()`, `sesi()`.
- Produces: perilaku HTTP pada host `remote_host`:
  - `GET /_remote/mulai?tiket=` → 302 ke `/` dengan cookie `ont_remote`; tiket tidak sah → 403 halaman berakhir.
  - Path lain → diteruskan ke ONT; tanpa sesi → 403 halaman berakhir ("Sesi remote berakhir, buka lagi dari halaman ONT di billing").

- [ ] **Step 1: Write the failing tests.** Request ke `http://ont.test/...` dengan `config(['services.genieacs.remote_host' => 'ont.test'])`. Sesi dibuat lewat `RemoteOntService::buatTiket` (NBI dan sesi billing tiruan seperti Task 1). ONT tiruan: `Http::fake(['10.1.1.5/*' => ...])`.
  - `test_mulai_set_cookie_lalu_redirect`: 302, `Location` `/`, cookie `ont_remote` ada.
  - `test_tanpa_cookie_halaman_berakhir`: GET `/` → 403, `assertSee('Sesi remote berakhir')`, dan `Http::assertNothingSent()`.
  - `test_get_diteruskan_ke_ip_sesi`: GET `/getpage.gch?pid=1` dengan cookie → `Http::assertSent` URL `http://10.1.1.5/getpage.gch?pid=1`; balasan berisi body ONT.
  - `test_header_frame_dibuang_dan_location_relatif`: ONT membalas 302 dengan `X-Frame-Options: SAMEORIGIN`, `Location: http://10.1.1.5/login.asp`, `Set-Cookie: SID=abc; Domain=10.1.1.5; Path=/` → `assertHeaderMissing('X-Frame-Options')`, `Location === '/login.asp'`, `Set-Cookie` tanpa `Domain`.
  - `test_post_form_diteruskan_dengan_body_dan_cookie_ont`: POST `/login.cgi` dengan `Username=admin&Password=x` dan cookie `SID=abc` → request terkirim dengan method POST, body sama, header Cookie berisi `SID=abc` dan **tidak** berisi `ont_remote`.
  - `test_path_billing_di_host_remote_tetap_ke_ont` (Review Focus 2): GET `/user` → diteruskan ke `http://10.1.1.5/user`, bukan halaman billing.
  - `test_ont_tidak_menjawab_502` (Review Focus 1): ONT melempar `ConnectionException` → 502, `assertSee('ONT tidak bisa dihubungi')`.
  - `test_biner_diteruskan_utuh` (Review Focus 3): ONT membalas PNG 3 byte dengan `Content-Type: image/png` → body dan Content-Type sama.
  - `test_cookie_lama_tidak_berlaku_setelah_ganti_pelanggan` (Review Focus 5): dua tiket untuk dua sesi; setelah `mulai` dengan tiket kedua, cookie pertama tetap hanya berlaku untuk sesinya sendiri. Yang diuji: masing-masing cookie mengarah ke IP-nya sendiri, dan cookie palsu atau rusak → 403.
  - `test_host_lain_tidak_terpengaruh`: GET `http://localhost/auth/login` → 200 halaman login billing.
- [ ] **Step 2: Run, verify fail.**
- [ ] **Step 3: Implement.**
  - Bandingkan `$request->getHost()` dengan `remote_host`, tanpa membedakan huruf besar/kecil.
  - Header yang diteruskan: `Cookie` (tanpa `ont_remote`), `Content-Type`, `User-Agent`, `Accept`, `Accept-Language`, `X-Requested-With`, dan `Referer` yang host-nya diganti ke ip.
  - Body memakai `$request->getContent()`.
  - Respons memakai `response($body, $status)` dengan header terpilih. Setiap `Set-Cookie` diteruskan satu per satu.
- [ ] **Step 4: Run, verify pass**, lalu seluruh suite.
- [ ] **Step 5: Commit** `feat(ont-remote): proxy web ONT di host remote`

---

### Task 3: Kartu Remote ONT di halaman pelanggan, dan panduan

**Files:**
- Modify: `routes/modules/ont.php` (`POST /pelanggan/{id}/ont/remote` → `ont.remote`)
- Modify: `app/Http/Controllers/Master/OntController.php`. Tambah `remote(string $id): JsonResponse`, yang membalas `{url}` atau 422/503. View menerima `remoteAktif`.
- Modify: `resources/views/content/ont/pelanggan.blade.php` (kartu sesuai sketsa spec)
- Modify: `docs/genieacs/README.md` (bagian "Remote ONT": `.env`, Public Hostname Cloudflare, alias domain di aaPanel, `SESSION_DOMAIN` kosong) dan `README.md` (satu baris variabel)
- Test: `tests/Feature/OntPelangganTest.php`

**Interfaces:**
- Consumes: `RemoteOntService::aktif()`, `buatTiket()`; `GenieAcsService::ontPelanggan()`.
- Produces: `POST ont.remote` → `{url: "<skema>://<remote_host><:port bila bukan 80/443>/_remote/mulai?tiket=..."}`. Skema dan port mengikuti request saat ini.

- [ ] **Step 1: Write the failing tests**
  - `test_remote_buat_url_tiket`: role 7 → 200, `url` diawali `http://ont.test/_remote/mulai?tiket=`; `Log::info('ONT remote', ...)` tanpa password.
  - `test_remote_ditolak_kolektor`: role 6 → 403.
  - `test_kartu_remote_dan_login_tampil`: device dengan `superAdmin` → `assertSee('Remote ONT')`, `assertSee('telecomadmin')`, dan password tidak tampil polos: `assertDontSee('adm1n</', false)`.
  - `test_kartu_remote_hilang_bila_host_kosong`: `remote_host=''` → `assertDontSee('Remote ONT')`; POST → 422.
  - `test_remote_tanpa_ip_422`: device tanpa `IPTR069`/`pppoeIP` → 422 dengan pesan berisi `IP`.
- [ ] **Step 2: Run, verify fail.**
- [ ] **Step 3: Implement.**
  - Iframe dibuat lewat JS setelah tombol ditekan, dengan `sandbox` tidak dipakai (web ONT butuh skrip dan form) dan tinggi `70vh`.
  - Tombol "Muat ulang" meminta tiket baru. Tombol "Tutup" menghapus iframe.
  - Baris login memakai pola `••••••••` + "lihat" yang sudah ada (`.attr('data-nilai')`).
- [ ] **Step 4: Run seluruh suite.** Lalu cek di Chrome: billing `http://localhost:8099` (role 7), `GENIEACS_REMOTE_HOST=ont.localhost`. Kartu tampil di 1366px dan 390px, terang dan gelap, tanpa error JS. Buka remote ke dummy: iframe menampilkan "ONT tidak bisa dihubungi", karena IP dummy memang tidak ada.
- [ ] **Step 5: Commit** `feat(ont-remote): kartu remote ONT di halaman pelanggan dan panduan`
