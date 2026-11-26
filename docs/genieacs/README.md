# Menghubungkan billing ke GenieACS

Halaman **ONT & WiFi** di billing membaca dan mengatur ONT pelanggan lewat NBI GenieACS (REST API, port 7557). Panduan ini dikerjakan satu kali.

## 1. Isi `.env` billing

```
GENIEACS_URL=http://172.1.10.86:7557   # alamat NBI; kosong = fitur ONT mati
GENIEACS_USER=                         # opsional, hanya bila NBI di belakang proxy ber-Basic Auth
GENIEACS_PASS=
GENIEACS_ONLINE_MENIT=10               # ONT dianggap online bila lapor dalam 10 menit terakhir
```

Lalu jalankan `php artisan optimize`. Menu **Perangkat → ONT Belum Terhubung** dan aksi **ONT & WiFi** di Daftar Pelanggan langsung muncul.

## 2. Buka NBI ke jaringan, khusus untuk billing

NBI tidak memakai login, jadi port 7557 **wajib** dibatasi hanya untuk IP yang boleh. Di VM GenieACS:

1. Di `/opt/genieacs/genieacs.env`, ubah `GENIEACS_NBI_INTERFACE=127.0.0.1` menjadi `GENIEACS_NBI_INTERFACE=0.0.0.0`, lalu `systemctl restart genieacs-nbi`.
2. Di `/etc/nftables.conf`, batasi port 7557:

   ```
   table inet filter {
     set nbi_boleh {
       type ipv4_addr
       elements = { 127.0.0.1, <IP VM billing> }
     }
     chain input {
       type filter hook input priority filter;
       tcp dport 7557 ip saddr != @nbi_boleh drop
       tcp dport 7557 meta nfproto ipv6 iifname != "lo" drop
     }
     ...
   }
   ```

   Cek dengan `nft -c -f /etc/nftables.conf`, lalu `systemctl reload nftables`.
3. Uji dari VM billing: `curl "http://<IP GenieACS>:7557/devices/?projection=_id"` harus membalas daftar device (JSON). Dari IP lain, perintah yang sama harus gagal.

Di VM GenieACS milik pemilik (26 Nov 2026) langkah ini sudah dikerjakan. Yang diizinkan baru `127.0.0.1`, `172.1.10.20` dan `172.1.10.85` (PC pengembangan). **IP VM billing perlu ditambahkan** ke `nbi_boleh` setelah VM itu jadi.

## 3. Yang dibaca billing dari GenieACS

Tidak ada yang perlu dipasang di GenieACS. Billing memakai yang sudah ada apa adanya:

- **Virtual parameter lama:** `pppoeUsername`, `RXPower`, `getSerialNumber` dan `WlanPassword`. Keempatnya jangan diubah.
- **Jalur WiFi TR-069 yang sudah diperbarui tiap jam** oleh provision `default`:
  - `WLANConfiguration.*.SSID`
  - `KeyPassphrase`
  - `PreSharedKey.1.KeyPassphrase`

| Standar | 2,4 GHz | 5 GHz |
|---|---|---|
| TR-098 (ZTE, Huawei, FiberHome, dan lain-lain) | `WLANConfiguration.1` | `WLANConfiguration.5` |
| TR-181 | `SSID.1` / `AccessPoint.1` | `SSID.2` / `AccessPoint.2` |

Cara billing mengganti WiFi:

- **Nama WiFi, atau password satu pita saja:** ditulis langsung ke jalur di atas, hanya ke jalur yang memang ada di ONT itu.
- **Password yang sama untuk semua pita** (ONT single-band, atau pilihan "Keduanya"): dikirim lewat `WlanPassword`. Dengan begitu salinan cadangannya (`X_CT-COM_CardKey`) ikut diperbarui, dan password tetap tampil di ONT yang menyembunyikannya, misalnya ZTE.

Kalau ada merek yang memakai indeks lain untuk 5 GHz, tambahkan jalurnya di `JALUR_WIFI` dalam `app/Services/GenieAcsService.php`. Selama jalurnya belum dikenal, halaman ONT menampilkan "nama WiFi belum bisa dibaca" dan form Ganti WiFi tidak muncul.

## 4. Cek redaman dan coba

1. Buka satu pelanggan di billing lewat **Daftar Pelanggan → ⋮ → ONT & WiFi**.
2. Bandingkan redaman di halaman itu dengan nilai *Rx Optical Power* di halaman web ONT. Selisih 0,5 dB masih wajar.
3. Tekan **Refresh**. Jam "lapor" harus berubah menjadi "beberapa detik yang lalu".
4. Coba **Ganti WiFi** di ONT uji, lalu pastikan perangkat bisa tersambung dengan nama dan password baru.

Jika muncul pesan *"ONT tidak merespons; perintah akan dijalankan saat ONT online lagi"*, berarti GenieACS tidak bisa menghubungi ONT secara langsung. Penyebabnya biasanya *connection request* yang terhalang NAT. Perintahnya tetap tersimpan dan dijalankan saat ONT lapor berikutnya.

## Cara billing menemukan ONT pelanggan

1. Bila pelanggan sudah punya SN tersimpan, billing mencarinya lewat SN.
2. Bila belum, billing mencari lewat username PPPoE pelanggan, yaitu `VirtualParameters.pppoeUsername`. Kalau ketemu tepat satu ONT, SN-nya langsung disimpan.
3. ONT yang tidak cocok dengan siapa pun muncul di **ONT Belum Terhubung**, dan bisa dihubungkan ke pelanggan secara manual.

## Remote web ONT (opsional)

Kartu **Remote ONT** di halaman ONT pelanggan menampilkan web admin ONT di dalam billing. Yang menghubungi ONT adalah server billing, jadi teknisi bisa memakainya dari mana saja selama billing bisa dibuka. ONT tidak perlu dibuka ke internet.

1. **Subdomain remote.** Pakai satu domain induk dengan billing, misalnya billing di `billing.domainmu.com` dan remote di `ont.domainmu.com`. Kalau domain induknya beda, login web ONT di dalam kotak bisa gagal di Safari atau iPhone.
2. **Cloudflare Tunnel.** Di tunnel yang sama, tambahkan **Public Hostname** `ont.domainmu.com` dengan service yang **sama persis** dengan billing.
3. **aaPanel.** Tambahkan `ont.domainmu.com` sebagai domain di situs billing, supaya nginx menerimanya.
4. **`.env` billing:**
   ```
   GENIEACS_REMOTE_HOST=ont.domainmu.com
   SESSION_DOMAIN=            # biarkan kosong: cookie login billing tidak boleh terkirim ke subdomain remote
   ```
   Lalu jalankan `php artisan optimize`.
5. **Server billing harus bisa menjangkau IP ONT.** Billing memakai `VirtualParameters.IPTR069`, dan kalau kosong memakai `pppoeIP`. Web ONT juga harus mengizinkan akses dari sisi WAN, port 80.

Batasan:
- Satu browser hanya bisa me-remote satu ONT pada satu waktu.
- Sesi remote berakhir setelah 2 jam, saat staf logout, atau saat akunnya dinonaktifkan.
- Halaman ONT yang di dalam JavaScript-nya melompat langsung ke `http://<IP ONT>/…` akan keluar dari kotak remote. Kalau ketemu, catat merek dan halamannya.

**Uji di lokal:** billing dan remote harus satu domain induk, seperti di produksi.
- `localhost` dan `ont.localhost` dianggap **beda situs** oleh Chrome, sehingga cookie remote ditolak.
- Tambahkan ke file `hosts` komputer:
  ```
  127.0.0.1 billing.mryes.test ont.mryes.test
  ```
- Buka billing lewat `http://billing.mryes.test:8099`, lalu isi `GENIEACS_REMOTE_HOST=ont.mryes.test`.
