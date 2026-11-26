# Remote web ONT dari halaman ONT pelanggan

Tanggal: 26 November 2026 · Status: menunggu persetujuan pemilik

## Maksud

Teknisi ingin membuka **web admin ONT pelanggan** dari halaman ONT di billing, tampil di dalam halaman itu (bukan tab baru), dari mana saja termasuk lewat data seluler. Billing akan dionlinekan lewat **Cloudflare Tunnel**. ONT pelanggan hanya bisa dijangkau dari jaringan lokal (IP 10.x/100.x), dan server billing bisa menjangkau semuanya. Jadi yang mengakses ONT adalah **server billing**, lalu tampilannya diteruskan ke teknisi.

Fitur ini melengkapi halaman ONT pelanggan (`/pelanggan/{id}/ont`), yang sudah bisa membaca status, me-reboot, dan mengganti WiFi lewat GenieACS (lihat `2026-11-26-genieacs-design.md`).

## Tujuan

1. Tombol **Remote ONT** di halaman ONT pelanggan membuka web ONT di kotak (iframe) di bawah halaman itu.
2. Web ONT bisa dipakai seperti biasa (login, menu, simpan pengaturan) untuk merek campur (ZTE, Huawei, FiberHome, dll.).
3. **Login admin ONT** ditampilkan di atas kotak dari virtual parameter yang sudah ada: `superAdmin`/`superPassword` dan `userAdmin`/`userPassword`. Password tersembunyi dengan tombol "lihat". Keputusan pemilik.
4. Hak akses: role 1, 2, 4, 7, sama dengan fitur ONT lain.

## Bukan tujuan

- Web ONT lewat HTTPS (port 443) atau port selain 80; telnet/SSH ke ONT.
- Menulis ulang JavaScript web ONT.
- Remote lebih dari satu ONT sekaligus dalam satu browser.
- Membuka ONT ke internet secara langsung.

## Anti-tujuan

- **Proxy terbuka:** jalur ini tidak boleh bisa dipakai untuk membuka alamat lain di jaringan lokal. IP tujuan selalu berasal dari GenieACS untuk pelanggan yang dibuka, tidak pernah dari isian pengguna.
- Remote tidak boleh bisa dibuka tanpa login billing, atau oleh role 3/6, atau setelah akun staf dinonaktifkan.
- Login admin ONT tidak boleh tampil untuk role selain 1, 2, 4, 7, dan tidak boleh tercatat di log.

## Pendekatan

| | Cara | Untung | Rugi |
|---|---|---|---|
| A | Proxy di bawah alamat billing (`billing/…/ont/remote/…`) dan isi halaman ONT ditulis ulang | Satu alamat publik | Web ONT banyak memakai alamat akar (`/login.cgi`, `/getpage.gch`); penulisan ulang di JavaScript sering meleset, sehingga menu/tombol rusak per merek |
| **B (dipilih)** | Proxy di **subdomain sendiri** (mis. `ont.domainmu.com`), dilayani aplikasi billing yang sama; web ONT diteruskan apa adanya di akar | Alamat ONT tetap benar tanpa penulisan ulang; cocok untuk merek campur | Satu Public Hostname tambahan di Cloudflare; satu ONT per browser pada satu waktu |
| C | iframe langsung ke `http://<IP ONT>` | Paling sederhana | HP teknisi di luar jaringan tidak bisa menjangkau ONT; diblokir HTTPS; banyak ONT menolak di-embed |

Pemilik memilih jalur lewat server billing. B dipilih karena merek ONT campur dan Cloudflare Tunnel membuat penambahan subdomain murah.

## Desain

### Konfigurasi

```
GENIEACS_REMOTE_HOST=ont.domainmu.com   # kosong = fitur remote mati
```

Di Cloudflare: Public Hostname `ont.domainmu.com` diarahkan ke layanan yang sama dengan billing. Host remote **harus satu domain induk dengan billing** (`billing.domainmu.com` dan `ont.domainmu.com`), supaya cookie di dalam iframe tidak dianggap pihak ketiga, yang diblokir Safari dan sebagian Chrome di HP. Di aaPanel: domain itu ditambahkan ke situs billing, supaya nginx menerimanya.

`SESSION_DOMAIN` billing tetap kosong, supaya cookie login billing tidak dikirim ke host remote, tempat kode web ONT berjalan.

Untuk uji lokal: billing dibuka lewat `billing.mryes.test:8099`, dengan `GENIEACS_REMOTE_HOST=ont.mryes.test`. Keduanya diarahkan ke `127.0.0.1` lewat file hosts, dan dianggap satu situs, sama seperti produksi. (Koreksi saat implementasi: `localhost` dan `ont.localhost` ternyata dianggap **beda situs** oleh Chrome, sehingga cookie remote di iframe ditolak.)

### Alur

1. Di halaman ONT pelanggan, teknisi menekan **Remote ONT**.
2. Billing (route ber-role 1,2,4,7) mencari ONT pelanggan lewat `GenieAcsService::ontPelanggan()`, lalu membuat **sesi remote** di sisi server:
   - ID acak disimpan di cache sebagai `{user, session billing, device id GenieACS, ip, berlaku 2 jam}`.
   - IP diambil dari `VirtualParameters.IPTR069`; bila kosong, dari `pppoeIP`.
   - ID itu dibungkus menjadi **tiket** terenkripsi yang berlaku 60 detik dan **sekali pakai**.
   - Iframe diarahkan ke `https://<REMOTE_HOST>/_remote/mulai?tiket=…`.
3. Di host remote, tiket diperiksa lalu ditukar dengan **cookie remote** yang hanya berisi ID sesi (terenkripsi, HttpOnly, Secure, SameSite=Lax). Setelah itu browser dialihkan ke `/`.
4. Setiap request ke host remote:
   - Sesi remote dicari di cache: masih berlaku, user masih ada, aktif, ber-role 1/2/4/7, dan **sesi billing-nya masih hidup**.
   - IP ONT dicek ulang ke GenieACS lewat device id paling lambat tiap 5 menit. Bila berubah (PPPoE tersambung ulang), IP baru dari device yang sama dipakai. Bila device tidak ditemukan, sesi diakhiri. Dengan begitu IP lama yang sudah berpindah ke pelanggan lain tidak pernah dibuka.
   - Request diteruskan ke `http://<ip>:80<path>?<query>`: method dan body sama, header seperlunya, cookie milik ONT diteruskan, cookie remote tidak.
   - Redirect tidak diikuti.
   - Timeout 15 detik.
5. Balasan ONT diteruskan ke browser dengan perubahan:
   - `X-Frame-Options` dan `Content-Security-Policy` dibuang.
   - `Location` yang menunjuk ke `http://<ip>` dijadikan relatif.
   - Atribut `Domain` pada `Set-Cookie` dibuang.
   - Header hop-by-hop dan `Content-Length`/`Content-Encoding` disesuaikan.
6. Tanpa sesi remote yang sah, host remote menampilkan halaman singkat "Sesi remote berakhir, buka lagi dari halaman ONT di billing". Ini berlaku bila sesi kedaluwarsa, user logout dari billing, akun dinonaktifkan, atau ONT tidak ditemukan lagi. Logout billing langsung memutus remote, karena sesi remote terikat ke sesi billing.
7. Setiap pembukaan remote dicatat ke log: siapa, pelanggan mana, SN, dan IP.

### Penempatan di kode (keputusan Claude)

- Host remote ditangani oleh **middleware global yang memeriksa Host**, bukan route di `web.php`. Alasannya, route billing tidak memakai batasan domain, jadi `/` atau `/user` di host remote akan tertangkap route billing. Selain itu, request web ONT tidak boleh melewati sesi atau CSRF billing.
- Untuk host lain, middleware langsung meneruskan request tanpa perubahan.
- Tiket dan cookie memakai enkripsi Laravel (`Crypt`). Data sesi (user, device, IP) disimpan di cache server, bukan di cookie, jadi tidak bisa dipalsukan atau diubah IP-nya.
- Sesi remote dicatat per user di cache. Saat logout billing, semuanya dihapus. Pemeriksaan "sesi billing masih hidup" memakai ID sesi billing yang dicatat saat tiket dibuat.

### Keputusan kecil (diputuskan Claude dari tinjauan, boleh dikoreksi)

- Logout billing langsung memutus remote, bukan menunggu kedaluwarsa 2 jam.
- IP ONT dicek ulang ke GenieACS tiap 5 menit, dan IP baru dari ONT yang sama dipakai otomatis.
- Tiket hanya sekali pakai. Membuka ulang remote membuat tiket baru.

### Tampilan

```
┌ ONT & WiFi · RIZAL ──────────── [Refresh] ┐
│ … status, redaman, WiFi (sudah ada) …      │
└────────────────────────────────────────────┘
┌ Remote ONT ─────────────── [Buka remote] ┐
│ Login admin: telecomadmin  •••••• [lihat] │
│ Login user : user          •••••• [lihat] │
│ ┌──────────────────────────────────────┐ │
│ │  (web ONT tampil di sini)            │ │
│ └──────────────────────────────────────┘ │
│ [Muat ulang]  [Tutup]                      │
└────────────────────────────────────────────┘
```

- Kartu Remote ONT hanya tampil bila `GENIEACS_REMOTE_HOST` terisi dan ONT terhubung.
- Kotak remote baru dimuat setelah tombol ditekan, supaya halaman tetap ringan. Tingginya kira-kira 70% layar, dan di HP lebarnya penuh.
- Baris login hanya tampil bila nilainya ada.

### Pengujian

Tes fitur memakai `Http::fake` untuk ONT, dan request ke host remote memakai header `Host`:

- tiket sah → cookie diset;
- tiket kedaluwarsa, dipalsukan, atau dipakai dua kali → ditolak;
- tanpa cookie → halaman "sesi berakhir";
- user dinonaktifkan, role 6, atau sudah logout billing → ditolak;
- IP ONT berubah di GenieACS → request diteruskan ke IP baru; device hilang → sesi berakhir;
- request diteruskan ke IP dari cookie, bukan dari isian;
- `X-Frame-Options` dibuang;
- `Location` absolut ke IP ONT dijadikan relatif;
- POST form ONT diteruskan dengan body-nya;
- host lain tidak terpengaruh;
- route tiket ditolak untuk role 3/6.

## Diserahkan ke pembangun

- Teks persis pesan dan ikon.
- Header request yang diteruskan, cukup yang diperlukan web ONT (Cookie, Content-Type, User-Agent, Accept, Referer yang disesuaikan).
- Batas ukuran balasan, bila perlu.
