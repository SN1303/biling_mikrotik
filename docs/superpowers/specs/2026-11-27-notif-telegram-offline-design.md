# Notifikasi Telegram pelanggan offline

Tanggal: 27 November 2026 · Status: menunggu persetujuan pemilik

## Maksud

Pemilik ingin dikabari lewat Telegram saat pelanggan (PPPoE) putus, supaya gangguan cepat ketahuan dan bisa ditindak, tanpa harus memantau MikroTik. Pesan dikirim ke akun Telegram pemilik sendiri, **24 jam**. Bot sudah ada dan sudah diuji: token dan chat id disimpan di `.env`.

## Keadaan sekarang

- Ada 1 MikroTik (API, 192.168.0.1), 15 ODP, dan 37 pelanggan yang semuanya punya ODP.
- Kolom `lamtim_user_mikrotik_details.status` (`online`/`offline`) hanya diisi saat sinkron manual. Belum ada pemantauan rutin.
- Penjadwal sudah ada di `app/Console/Kernel.php`, untuk tagihan bulanan.

## Tujuan

1. **Ringkasan offline:** pelanggan yang offline lebih dari **10 menit** dikumpulkan dalam **satu pesan**, dikelompokkan per ODP, berisi nama, username PPPoE, dan lama offline.
2. **Gangguan massal:** bila **3 pelanggan atau lebih di satu ODP** offline, ODP itu diberi judul menonjol: ⚠️ *Kemungkinan gangguan ODP X (n dari m pelanggan offline)*.
3. **Online lagi:** pelanggan yang sebelumnya sudah dikabari offline dikabari 🟢 saat tersambung lagi, beserta lama offline-nya. Pesan ini juga digabung.
4. **Pelanggan yang wajar offline tidak dikabari:** secret PPPoE dinonaktifkan, akun pelanggan nonaktif, atau sedang diisolir.
5. **MikroTik tidak terjangkau:** satu pesan ⚠️ saat billing gagal menghubungi MikroTik, dan satu pesan saat terjangkau lagi. Selama MikroTik tidak terjangkau, pelanggan **tidak** dianggap offline.
6. **Status di billing ikut hidup:** status koneksi pelanggan (`ppp_online`) selalu mutakhir, sehingga warna titik di peta ikut benar.

## Bukan tujuan

- Pemakaian data. Ini proyek terpisah sesudah ini, memakai log accounting PPP dan/atau penghitung byte interface.
- Bot yang bisa diajak bicara (perintah `/status` dan sejenisnya).
- Banyak penerima atau pengaturan per teknisi. Cukup satu `TELEGRAM_CHAT_ID`, yang juga boleh berupa id grup.
- Jam diam malam. Pemilik memilih pesan tetap dikirim 24 jam.

## Anti-tujuan

- Spam: putus sebentar (router restart, listrik berkedip, MikroTik reboot) tidak boleh memicu pesan.
- Pelanggan dianggap offline massal hanya karena billing tidak bisa menghubungi MikroTik.
- Alamat laporan dari MikroTik bisa dipalsukan pihak lain.
- Skrip on-up/on-down yang sudah ada di profile MikroTik tertimpa.

## Pendekatan

| | Cara | Untung | Rugi |
|---|---|---|---|
| A | Billing mengecek `/ppp/active` di MikroTik tiap menit | MikroTik tidak diubah; tidak ada kejadian yang hilang | Telat ≤ 1 menit; ada panggilan API tiap menit |
| **B (dipilih pemilik)** | MikroTik melapor ke billing lewat skrip **on-up/on-down** di PPP Profile | Langsung saat kejadian | Profile MikroTik harus diubah; MikroTik harus bisa menghubungi billing; laporan hilang bila billing mati |

**Keputusan Claude:** B dilengkapi **pencocokan tiap 10 menit** dengan `/ppp/active` dan `/ppp/secret`. Ini menutup laporan yang hilang dan mendeteksi MikroTik yang tidak terjangkau. Penjadwal tiap menit tetap diperlukan untuk jeda 10 menit.

## Desain

### Konfigurasi (`.env`)

```
TELEGRAM_BOT_TOKEN=            # kosong = notifikasi mati
TELEGRAM_CHAT_ID=
PPP_NOTIF_SECRET=              # kode rahasia laporan MikroTik (acak, ≥ 32 karakter)
PPP_NOTIF_URL=                 # alamat billing yang bisa dijangkau MikroTik; kosong = APP_URL
PPP_NOTIF_JEDA_MENIT=10
PPP_NOTIF_AMBANG_MASSAL=3
```

### Laporan dari MikroTik

- **Endpoint:** `POST /api/mikrotik/ppp-event`, tanpa sesi atau CSRF.
  - Header: `X-Notif-Token: <PPP_NOTIF_SECRET>`, dicek dengan `hash_equals`.
  - Isi: `m=<id mikrotik>&e=up|down&u=<username PPPoE>`.
  - Balasan: token salah → 403; pelanggan tidak dikenal → 200, diabaikan.
- **`down`:** `ppp_online=0`, dan `ppp_offline_sejak=now()` bila belum terisi.
- **`up`:** `ppp_online=1`. Bila pelanggan sudah dikabari offline, ia ditandai untuk pesan "online lagi" (lama offline dihitung dari `ppp_offline_sejak`). Kalau belum dikabari, `ppp_offline_sejak` cukup dikosongkan.
- **Blok skrip RouterOS** (ditandai supaya bisa dipasang ulang atau dicabut tanpa menyentuh isi lain):

  ```
  # mryes-notif:mulai
  :do { /tool fetch url="<PPP_NOTIF_URL>/api/mikrotik/ppp-event" http-method=post http-header-field="X-Notif-Token: <secret>" http-data=("m=<id>&e=down&u=" . $user) output=none } on-error={}
  # mryes-notif:selesai
  ```

  `e=up` untuk on-up. `on-error={}` menjaga agar login PPPoE tidak terganggu bila billing mati.

### Data

**Kolom `status` yang sudah ada tidak disentuh.** Isinya bukan hanya online/offline: `isolir` diisi manual lewat form peta, dan `active` diisi saat pelanggan dibuat. Status koneksi asli disimpan di kolom baru, supaya isian manual tidak bentrok dengan laporan otomatis. Kolom baru di `lamtim_user_mikrotik_details`:

| Kolom | Tipe | Isi |
|---|---|---|
| `ppp_online` | boolean, null | Status koneksi PPPoE terakhir menurut MikroTik. `null` = belum pernah dilaporkan |
| `ppp_offline_sejak` | timestamp, null | Sejak kapan offline |
| `ppp_dikabari` | boolean, default 0 | Offline-nya sudah dikirim ke Telegram |
| `ppp_secret_nonaktif` | boolean, default 0 | Secret PPPoE dinonaktifkan |

`ppp_secret_nonaktif` diisi dari tiga sumber: aksi nonaktif/aktif secret milik billing sendiri (`MikrotikService::disablePppoeUser`/`enablePppoeUser`), aksi isolir, dan pencocokan 10 menit. Dengan begitu, pelanggan yang baru saja dinonaktifkan staf langsung tidak dikabari, tanpa menunggu pencocokan.

**Peta:** warna titik pelanggan memakai `ppp_online` bila sudah terisi. Pelanggan dengan `statusIsolir=1` atau `status=isolir` tetap kuning (isolir). Bila `ppp_online` masih `null`, peta memakai `status` seperti sekarang.

Status MikroTik terakhir (terjangkau atau tidak) disimpan di cache.

### Penjadwal

- **`ppp:notifikasi`, tiap menit:**
  1. Kumpulkan pelanggan dengan `ppp_online=0`, `ppp_dikabari=0`, `ppp_offline_sejak ≤ now − jeda`, dan memenuhi syarat: `users.isActive=1`, `statusIsolir=0`, `ppp_secret_nonaktif=0`. Pencocokan memakai `namaMikrotikUser` + `idMikrotik`, sama dengan pola controller peta.
  2. Kumpulkan pelanggan `ppp_online=1` yang `ppp_dikabari=1`.
  3. Bila ada isinya, susun **satu pesan** (dipecah bila lebih dari 4096 karakter): blok 🔴 per ODP (ODP yang lewat ambang diberi judul ⚠️), lalu blok 🟢.
  4. Kirim lewat `sendMessage`. **Hanya bila terkirim**, tandai `ppp_dikabari` (offline → 1; online → 0, dan `ppp_offline_sejak` dikosongkan). Bila gagal, dicoba lagi menit berikutnya.
- **`ppp:cocokkan`, tiap 10 menit**, per MikroTik aktif:
  - Ambil `/ppp/active` dan `/ppp/secret`.
  - Pelanggan aktif di MikroTik tapi tercatat offline → online. Pelanggan tidak aktif tapi tercatat online → offline sejak sekarang.
  - Secret `disabled=true` dicatat, dan pelanggan itu tidak dikabari.
  - Gagal terhubung → dicoba sekali lagi setelah 30 detik, supaya MikroTik yang sedang reboot tidak memicu peringatan. Kalau masih gagal, status MikroTik menjadi "tidak terjangkau": pesan ⚠️ dikirim sekali dan pelanggan tidak diubah. Saat berhasil lagi → pesan ✅ sekali.

### Pemasangan skrip (dua cara, keputusan pemilik)

Di halaman MikroTik, khusus role 1 dan 2:
- **Pasang otomatis:** lewat API, blok di atas ditambahkan ke `on-up`/`on-down` **semua** PPP Profile. Blok lama bertanda dibuang dulu, jadi pemasangan ulang tidak menggandakan. Isi lain dibiarkan.
- **Cabut:** membuang blok bertanda saja.
- **Manual:** potongan skrip on-up dan on-down ditampilkan siap salin, dengan id MikroTik, URL, dan secret sudah terisi. Teksnya diberi peringatan bahwa isinya rahasia.

### Hitungan gangguan massal (keputusan Claude)

Judul ⚠️ tampil untuk ODP yang **saat ini** punya pelanggan offline yang memenuhi syarat sebanyak ≥ ambang. Hitungan ini termasuk pelanggan yang sudah dikabari di pesan sebelumnya, jadi kabel putus yang menjatuhkan pelanggan berselang 1–2 menit tetap terbaca sebagai gangguan massal. `n` = pelanggan offline yang memenuhi syarat di ODP itu; `m` = semua pelanggan yang memenuhi syarat di ODP itu. Judul hanya muncul di pesan yang memuat pelanggan baru dari ODP itu, jadi tidak ada pesan tambahan hanya untuk mengulang judul.

### Format pesan (contoh)

```
🔴 Pelanggan offline (3)

⚠️ ODP TJI-1: kemungkinan gangguan (3 dari 6 pelanggan offline)
• RIZAL (rizal@tj-1), 12 menit
• DALIJO (dalijo@tj-1), 11 menit
• SUMILAN (sumilan@tj-1), 11 menit

🟢 Online lagi (1)
• BAGOS (bagos@tj-6), offline 35 menit
```

Pelanggan tanpa ODP dikelompokkan sebagai "Tanpa ODP".

## Pengujian

- Tes fitur dengan `Http::fake` untuk `api.telegram.org`, dan MikroTik tiruan lewat pembungkus yang bisa diganti di tes:
  - endpoint dengan token benar dan salah;
  - down → up sebelum 10 menit menghasilkan tanpa pesan;
  - down lebih dari 10 menit menghasilkan satu pesan dikelompokkan per ODP;
  - judul massal muncul pada 3 pelanggan offline di satu ODP;
  - pesan "online lagi" hanya untuk pelanggan yang sudah dikabari;
  - pelanggan isolir, nonaktif, atau dengan secret nonaktif tidak dikabari;
  - Telegram gagal → tidak ditandai, lalu terkirim di percobaan berikutnya;
  - pencocokan membetulkan status;
  - MikroTik tidak terjangkau → satu pesan dan status pelanggan tidak berubah;
  - pasang dan cabut skrip tidak menyentuh isi lain dan tidak menggandakan.
- Uji nyata oleh pemilik: pasang skrip, cabut kabel atau matikan satu ONT lebih dari 10 menit, lalu nyalakan lagi.

## Diserahkan ke pembangun

- Teks persis pesan dan ikon.
- Letak tombol di halaman MikroTik.
- Nama kelas dan command.
