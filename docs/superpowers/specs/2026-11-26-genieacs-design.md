# Integrasi GenieACS: status ONT, reboot, dan WiFi pelanggan

Tanggal: 26 November 2026 · Status: disetujui pemilik (26 Nov 2026)

## Maksud

Admin dan teknisi ingin mengurus ONT pelanggan dari aplikasi billing, tanpa membuka GenieACS. Kebutuhannya: melihat apakah ONT online dan berapa redamannya, me-reboot ONT, melihat dan mengganti nama serta password WiFi, dan menemukan ONT yang belum dikaitkan ke pelanggan. Pemakaian utamanya ada di lapangan dan saat menangani keluhan pelanggan, sering lewat HP.

## Lingkungan

- Aplikasi billing (Laravel 13) dan GenieACS berjalan di dua VM terpisah dalam satu Proxmox lokal. Billing dipasang lewat aaPanel, GenieACS langsung di VM-nya sendiri.
- Aplikasi bicara ke **GenieACS NBI** (REST API, bawaan port 7557), bukan ke UI web GenieACS (port 3000).
- Merek ONT campur dan belum diketahui pastinya.
- Saat desain ini ditulis, GenieACS belum terpasang. Pembangunan memakai respons tiruan (`Http::fake`), lalu diuji sungguhan setelah VM berjalan.

## Tujuan

1. **Status ONT per pelanggan:** online atau offline (dari waktu terakhir lapor), redaman Rx (dBm), Serial Number, model, nama WiFi, dan password WiFi bila ONT mengizinkannya dibaca.
2. **Tombol Refresh:** meminta ONT melapor ulang saat itu juga.
3. **Reboot ONT.**
4. **Ganti nama WiFi dan/atau password WiFi.**
5. **Daftar ONT belum terhubung:** ONT yang lapor ke GenieACS tapi tidak terkait ke pelanggan mana pun, dengan aksi untuk menghubungkannya ke pelanggan.
6. **Hak akses:** Super Admin, Admin, Teknisi, dan Teknisi-Kolektor (role 1, 2, 4, 7). Teknisi boleh melihat dan mengganti password WiFi; ini keputusan pemilik. Kolektor (6) dan Bendahara (3) tidak punya akses.

## Bukan tujuan (versi ini)

- Total penggunaan data. Diputuskan diambil dari MikroTik, sebagai pekerjaan terpisah.
- Menyalin data GenieACS ke database lokal atau cron sinkron (pendekatan B ditolak, lihat di bawah).
- Peta berwarna berdasarkan redaman, atau peringatan redaman buruk.
- Provisioning otomatis (mengirim konfigurasi PPPoE ke ONT baru), update firmware, dan pengaturan GenieACS lainnya.

## Anti-tujuan

- Aplikasi billing tidak boleh ikut lambat atau error saat GenieACS mati. Bagian ONT cukup menampilkan "GenieACS tidak terjangkau", dan halaman lain tetap normal.
- URL dan password GenieACS tidak boleh masuk ke repo. Semuanya diatur lewat `.env`.
- Tombol aksi tidak boleh tampil seolah berhasil kalau tugasnya hanya mengantri karena ONT offline. Pengguna harus diberi tahu bedanya.

## Pendekatan

| | Cara | Untung | Rugi |
|---|---|---|---|
| **A (dipilih)** | Setiap kali halaman ONT dibuka atau tombol ditekan, aplikasi langsung bertanya ke NBI GenieACS | Data selalu terbaru; tanpa tabel baru dan tanpa cron | Kalau GenieACS mati, bagian ONT tidak bisa dipakai |
| B | Cron menyalin semua ONT ke tabel lokal tiap 5–10 menit; aksi tetap langsung | Halaman cepat; siap untuk peta redaman dan peringatan | Tambah tabel dan cron; data bisa terlambat beberapa menit, padahal teknisi butuh data saat itu juga |
| C | GenieACS mengirim data ke aplikasi | — | GenieACS tidak punya webhook bawaan; perlu ekstensi khusus |

Pemilik memilih **A**, karena semua kebutuhannya bersifat "lihat dan kerjakan sekarang" untuk satu pelanggan. B bisa ditambahkan di atas A kelak tanpa membongkar apa pun.

## Desain

### Konfigurasi

Di `config/services.php` ada blok `genieacs`, dibaca dari `.env`:

```
GENIEACS_URL=http://10.0.0.20:7557   # kosong = fitur ONT dimatikan
GENIEACS_USER=                       # opsional: Basic Auth bila NBI dipasang di belakang proxy
GENIEACS_PASS=
```

Kalau `GENIEACS_URL` kosong, menu dan tombol ONT tidak tampil, dan route-nya mengembalikan pesan "GenieACS belum diatur".

### Virtual parameter di GenieACS (satu kali pemasangan)

Karena merek ONT campur, letak data per merek berbeda. Perbedaan ini ditangani di GenieACS lewat **virtual parameter**, sehingga aplikasi selalu membaca nama yang sama:

| Virtual parameter | Isi | Tulis? |
|---|---|---|
| `VirtualParameters.pppoeUsername` | Username PPPoE dari WAN ONT (TR-098 `InternetGatewayDevice...WANPPPConnection.*.Username` atau TR-181 `Device.PPP.Interface.*.Username`) | tidak |
| `VirtualParameters.rxPower` | Redaman Rx dalam dBm, sudah dinormalkan dari jalur khusus vendor (ZTE `X_ZTE-COM_WANPONInterfaceConfig`, Huawei `X_GponInterafceConfig`, FiberHome `X_FH_GponInterfaceConfig`, TR-181 `Device.Optical.Interface.*`) | tidak |
| `VirtualParameters.wifiSsid` | SSID 2,4 GHz (TR-098 `WLANConfiguration.1`, TR-181 `Device.WiFi.SSID.1`) | ya |
| `VirtualParameters.wifiPassword` | Password 2,4 GHz (jalur `PreSharedKey.1.KeyPassphrase` atau `KeyPassphrase`, sesuai vendor) | ya |
| `VirtualParameters.wifiSsid5` | SSID 5 GHz (TR-098 umumnya `WLANConfiguration.5`, TR-181 `Device.WiFi.SSID.2`); kosong bila ONT hanya single-band | ya |
| `VirtualParameters.wifiPassword5` | Password 5 GHz | ya |

Skrip keenamnya disimpan di repo, di `docs/genieacs/virtual-parameters/*.js`, beserta panduan singkat cara memasangnya di GenieACS (Admin → Virtual Parameters). Satuan redaman berbeda antarmodel, jadi skrip `rxPower` **wajib dikalibrasi** dengan data ONT sungguhan saat GenieACS sudah berjalan. Ini langkah yang sudah direncanakan, bukan risiko tersembunyi.

### Mencari ONT milik pelanggan

1. Jika `lamtim_user_mikrotik_details.ont_sn` terisi, cari dengan `{"_deviceId._SerialNumber": ont_sn}`.
2. Jika tidak, cari dengan `{"VirtualParameters.pppoeUsername": namaMikrotikUser}`. Jika ketemu tepat satu, simpan Serial Number-nya ke `ont_sn` (terhubung otomatis).
3. Jika tidak ketemu: tampilkan "ONT belum terhubung" dengan tautan ke daftar ONT belum terhubung.

Saat ini kolom `ont_sn` kosong untuk 37 dari 37 pelanggan, dan semuanya punya username PPPoE. Jadi langkah 2 diharapkan menghubungkan sebagian besar ONT secara otomatis, selama ONT tersebut dalam mode router (dial PPPoE sendiri).

### Daftar ONT belum terhubung

Ambil semua device dari NBI dengan proyeksi seperlunya (id, SN, model, waktu terakhir lapor, `pppoeUsername`, `rxPower`). Buang yang SN-nya sudah ada di `ont_sn` atau yang `pppoeUsername`-nya cocok dengan pelanggan. Sisanya ditampilkan dalam tabel, dengan aksi **Hubungkan** (pilih pelanggan, lalu simpan SN ke `ont_sn` pelanggan itu). Pelanggan yang sudah punya ONT lain akan ditimpa setelah konfirmasi.

### Aksi ke ONT

Semua aksi memakai `POST /devices/{id}/tasks?timeout=3000&connection_request`:

- **Refresh:** task `getParameterValues` untuk keenam virtual parameter dan `_lastInform`.
- **Reboot:** task `reboot`.
- **Ganti WiFi:** pengguna memilih pita: **2,4 GHz**, **5 GHz**, atau **keduanya**. Pilihan 5 GHz dan "keduanya" hanya muncul bila `wifiSsid5` ONT itu tidak kosong (ONT dual-band). Nama dan password boleh diisi salah satu atau dua-duanya; kolom yang kosong berarti tidak diubah. Untuk "keduanya", nama 5 GHz diisi otomatis dengan akhiran `-5G` (masih bisa diedit), dan passwordnya sama. Semua nilai dikirim dalam satu task `setParameterValues` ke virtual parameter yang sesuai. Validasinya: SSID 1–32 karakter; password WPA 8–63 karakter.

Arti jawaban NBI: **200** = sudah dikerjakan ONT, sehingga muncul pesan berhasil. **202** = masuk antrian karena ONT tidak menjawab, sehingga muncul pesan kuning "ONT tidak merespons; perintah akan dijalankan saat ONT online lagi". Error atau timeout jaringan = "GenieACS tidak terjangkau".

### Kode

- `app/Services/GenieAcsService.php`: satu kelas tipis di atas `Http` Laravel (timeout 5 detik). Fungsinya: `cariOntPelanggan()`, `ontBelumTerhubung()`, `refresh()`, `reboot()`, `setWifi()`. Status online dihitung dari `_lastInform`: online jika kurang dari 2× interval inform (bawaan 5 menit, bisa diatur lewat `GENIEACS_ONLINE_MENIT`).
- `app/Http/Controllers/Master/OntController.php`: halaman dan aksi.
- Route `routes/modules/ont.php`, di dalam grup `role:1,2,4,7`:
  - `GET /pelanggan/{id}/ont`: halaman ONT satu pelanggan.
  - `POST /pelanggan/{id}/ont/refresh`, `/reboot`, `/wifi`.
  - `GET /ont/belum-terhubung` dan `POST /ont/hubungkan`.

### Tampilan

- **Halaman ONT pelanggan** (ramah HP, gaya Materialize seperti halaman lain), dengan tombol Refresh, Reboot, dan Ganti WiFi (form singkat):

  ```
  ┌ ONT · RIZAL ─────────────── [Refresh] ┐
  │ ● Online · lapor 2 menit lalu           │
  │ Redaman   -19,8 dBm  (baik)             │
  │ SN        ZTEGC1234567 · ZTE F609       │
  │ WiFi 2,4  RIZAL-NET     ••••••• [lihat] │
  │ WiFi 5    RIZAL-NET-5G  ••••••• [lihat] │
  │ [Ganti WiFi]              [Reboot ONT]  │

  Ganti WiFi:  ( ) 2,4 GHz  ( ) 5 GHz  (•) Keduanya
               Nama WiFi [RIZAL-NET      ]  5 GHz [RIZAL-NET-5G]
               Password  [••••••••       ]  (kosong = tidak diubah)
  └─────────────────────────────────────────┘
  ```

  Warna redaman: ≥ -25 dBm hijau (baik), -25 sampai -27 kuning (waspada), < -27 merah (buruk). Reboot dan ganti WiFi meminta konfirmasi dulu.
- **Pintu masuk:**
  - item **ONT & WiFi** di menu aksi Daftar Pelanggan;
  - tombol **ONT** di popup pelanggan pada peta;
  - menu **Jaringan → ONT Belum Terhubung**.

  Ketiganya hanya tampil untuk role 1, 2, 4, 7, dan hanya bila `GENIEACS_URL` terisi.

### Pengujian

- **Tes fitur dengan `Http::fake`:** pencarian lewat SN dan lewat PPPoE (termasuk penyimpanan SN otomatis); 200 vs 202 vs koneksi gagal; validasi WiFi; hak akses (kolektor dan bendahara ditolak); daftar belum terhubung tidak memuat ONT milik pelanggan; aksi Hubungkan menyimpan `ont_sn`.
- **Setelah GenieACS terpasang:** uji pada 1–2 ONT sungguhan per merek, lalu kalibrasi skrip `rxPower`.

## Keputusan kecil (diputuskan Claude, boleh dikoreksi)

- Pencarian lewat PPPoE yang menemukan **lebih dari satu** ONT tidak menghubungkan otomatis. Aplikasi menampilkan daftar kandidat untuk dipilih.
- Satu Serial Number hanya boleh terhubung ke satu pelanggan. **Hubungkan** ke pelanggan lain memindahkan SN itu dari pelanggan lama, setelah konfirmasi.
- Setelah ganti WiFi berhasil (200), kolom `wifi_name` milik pelanggan diperbarui dengan nama 2,4 GHz, supaya ekspor Excel ikut benar.
- Setiap reboot, ganti WiFi, dan Hubungkan dicatat ke log aplikasi (siapa, pelanggan mana, SN, hasil). Password WiFi tidak ikut dicatat.
- Device id GenieACS di-URL-encode saat dipakai di path NBI.

## Diserahkan ke pembangun

- Teks persis pesan, susunan tabel daftar ONT, dan ikon.
- Format tanggal "lapor X menit lalu" (pakai `diffForHumans` berlocale id).
- Ambang warna redaman boleh disesuaikan setelah melihat data nyata.
