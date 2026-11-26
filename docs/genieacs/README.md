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

## 3. Pasang 4 virtual parameter WiFi

Billing memakai virtual parameter yang **sudah ada** di GenieACS apa adanya: `pppoeUsername`, `RXPower`, `getSerialNumber` dan `WlanPassword`. Keempatnya jangan diubah.

Yang perlu ditambahkan hanya WiFi per pita, karena `WlanPassword` selalu mengganti semua WiFi sekaligus. Di UI GenieACS, buka **Admin → Virtual Parameters → New**. Isi nama persis seperti nama file, lalu tempel isinya:

| Nama | File |
|---|---|
| `wifiSsid` | [virtual-parameters/wifiSsid.js](virtual-parameters/wifiSsid.js) |
| `wifiPassword` | [virtual-parameters/wifiPassword.js](virtual-parameters/wifiPassword.js) |
| `wifiSsid5` | [virtual-parameters/wifiSsid5.js](virtual-parameters/wifiSsid5.js) |
| `wifiPassword5` | [virtual-parameters/wifiPassword5.js](virtual-parameters/wifiPassword5.js) |

Jalur yang dicakup:

| Merek / standar | 2,4 GHz | 5 GHz |
|---|---|---|
| TR-098 (ZTE, Huawei, FiberHome, dan lain-lain) | `WLANConfiguration.1` | `WLANConfiguration.5` |
| TR-181 | `SSID.1` / `AccessPoint.1` | `SSID.2` / `AccessPoint.2` |

Bila sebuah merek memakai indeks lain untuk 5 GHz, tambahkan jalurnya di daftar `JALUR` pada skrip yang bersangkutan.

Mengganti password 2,4 GHz juga memperbarui `X_CT-COM_CardKey`. Itu salinan yang dibaca `WlanPassword`, supaya password tetap tampil di ONT yang menyembunyikannya, misalnya ZTE.

## 4. Pastikan virtual parameter ikut diperbarui saat ONT lapor

Di **Admin → Provisions**, provision yang dipakai preset inform harus memuat baris berikut. Paket R-Tech biasanya sudah punya; tambahkan bila belum ada:

```
declare("VirtualParameters.*", {value: Date.now() - 300 * 1000});
```

## 5. Cek redaman dan coba

1. Buka satu pelanggan di billing lewat **Daftar Pelanggan → ⋮ → ONT & WiFi**.
2. Bandingkan redaman di halaman itu dengan nilai *Rx Optical Power* di halaman web ONT. Selisih 0,5 dB masih wajar.
3. Tekan **Refresh**. Jam "lapor" harus berubah menjadi "beberapa detik yang lalu".
4. Coba **Ganti WiFi** di ONT uji, lalu pastikan perangkat bisa tersambung dengan nama dan password baru.

Jika muncul pesan *"ONT tidak merespons; perintah akan dijalankan saat ONT online lagi"*, berarti GenieACS tidak bisa menghubungi ONT secara langsung. Penyebabnya biasanya *connection request* yang terhalang NAT. Perintahnya tetap tersimpan dan dijalankan saat ONT lapor berikutnya.

## Cara billing menemukan ONT pelanggan

1. Bila pelanggan sudah punya SN tersimpan, billing mencarinya lewat SN.
2. Bila belum, billing mencari lewat username PPPoE pelanggan, yaitu `VirtualParameters.pppoeUsername`. Kalau ketemu tepat satu ONT, SN-nya langsung disimpan.
3. ONT yang tidak cocok dengan siapa pun muncul di **ONT Belum Terhubung**, dan bisa dihubungkan ke pelanggan secara manual.
