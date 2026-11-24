/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_address_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_address_lists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lamtim_address_lists_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_areas` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `idProv` varchar(50) DEFAULT NULL,
  `idKab` varchar(50) DEFAULT NULL,
  `idKec` varchar(50) DEFAULT NULL,
  `idKel` varchar(50) DEFAULT NULL,
  `komentar` text DEFAULT NULL,
  `latitude` text DEFAULT NULL,
  `longitude` text DEFAULT NULL,
  `radius` int(11) DEFAULT NULL,
  `public` int(11) DEFAULT NULL,
  `code_area` varchar(20) DEFAULT NULL,
  `coordinates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`coordinates`)),
  `color` varchar(7) NOT NULL DEFAULT '#696cff',
  `create_by` int(11) DEFAULT NULL,
  `mitra` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_diskons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_diskons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `idUser` bigint(20) unsigned DEFAULT NULL,
  `bulan` int(2) DEFAULT NULL,
  `tahun` int(11) DEFAULT NULL,
  `nominal` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `createBy` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idUser` (`idUser`),
  KEY `lamtim_diskons_user_periode_idx` (`idUser`,`bulan`,`tahun`),
  CONSTRAINT `lamtim_diskons_ibfk_1` FOREIGN KEY (`idUser`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_fotos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_fotos` (
  `id` bigint(11) NOT NULL AUTO_INCREMENT,
  `idPelanggan` bigint(11) NOT NULL,
  `idJenis` tinyint(1) DEFAULT NULL COMMENT '1 KTP 2 FOTO RUMAH',
  `foto` varchar(255) DEFAULT NULL,
  `extensi` varchar(5) DEFAULT NULL,
  `ukuran` varchar(100) DEFAULT NULL,
  `ukuran2` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_ip_pools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_ip_pools` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `ranges` varchar(255) DEFAULT NULL,
  `next_pool` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lamtim_ip_pools_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_kategoris`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_kategoris` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(125) NOT NULL,
  `description` text NOT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `create_by` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `idUser` bigint(20) unsigned NOT NULL,
  `nama` varchar(255) NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `keterangan` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idUser` (`idUser`),
  CONSTRAINT `lamtim_logs_ibfk_1` FOREIGN KEY (`idUser`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_mikrotiks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_mikrotiks` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `kode` varchar(100) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` text DEFAULT NULL,
  `ip` varchar(100) NOT NULL,
  `port` int(11) NOT NULL,
  `date_reset` int(11) DEFAULT 0,
  `isActive` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `createBy` bigint(20) DEFAULT NULL COMMENT 'id user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_odcs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_odcs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `idOlt` bigint(20) NOT NULL,
  `idOdc` bigint(20) unsigned DEFAULT NULL,
  `kode` varchar(100) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `port` int(5) DEFAULT NULL COMMENT 'jumlah port yg ada',
  `portSisa` int(5) DEFAULT NULL COMMENT 'jml port sisa setelah terpakai',
  `portOlt` int(5) DEFAULT NULL COMMENT 'di lokasi mana port olt nya',
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  `route_waypoints` longtext DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unlocked',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idOlt` (`idOlt`),
  KEY `lamtim_odcs_idodc_index` (`idOdc`),
  CONSTRAINT `lamtim_odcs_ibfk_1` FOREIGN KEY (`idOlt`) REFERENCES `lamtim_olts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_odps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_odps` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `idOdc` bigint(20) DEFAULT NULL,
  `idOdp` bigint(20) DEFAULT NULL,
  `idOlt` bigint(20) NOT NULL,
  `kode` varchar(100) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `port` int(5) DEFAULT NULL COMMENT 'jumlah port yg ada',
  `portSisa` int(5) DEFAULT NULL COMMENT 'jml port sisa setelah terpakai',
  `portOdc` int(11) DEFAULT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  `route_waypoints` longtext DEFAULT NULL,
  `tipe` varchar(50) DEFAULT NULL,
  `fo_a` int(11) DEFAULT 0,
  `fo_b` int(11) DEFAULT 0,
  `kabel` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unlocked',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_olts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_olts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `kode` varchar(100) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `ip` varchar(100) DEFAULT NULL COMMENT 'ip olt',
  `port` int(5) DEFAULT NULL COMMENT 'port akses',
  `sfp` int(5) DEFAULT NULL COMMENT 'jml port sft',
  `isActive` tinyint(1) DEFAULT 1 COMMENT '1 on 0 off',
  `latitude` text DEFAULT NULL,
  `longitude` text DEFAULT NULL,
  `teknologi` varchar(50) DEFAULT NULL,
  `port_pon` int(11) DEFAULT NULL,
  `port_uplink` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unlocked',
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_pakets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_pakets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idKategori` int(11) NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `price` varchar(255) NOT NULL,
  `speed_limit` varchar(255) DEFAULT NULL COMMENT 'Contoh: 10M/10M',
  `is_burst` tinyint(1) NOT NULL DEFAULT 0,
  `burst_rate` varchar(255) DEFAULT NULL,
  `burst_threshold` varchar(255) DEFAULT NULL,
  `burst_time` varchar(255) DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 8,
  `ip_pool` varchar(255) DEFAULT NULL COMMENT 'Framed-Pool',
  `address_list` varchar(255) DEFAULT NULL COMMENT 'Mikrotik-Address-List',
  `picture` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `create_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`idKategori`),
  KEY `create_by` (`create_by`),
  CONSTRAINT `lamtim_pakets_ibfk_1` FOREIGN KEY (`idKategori`) REFERENCES `lamtim_kategoris` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_payment_metodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_payment_metodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `noUrut` int(11) DEFAULT NULL,
  `kode` varchar(100) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_tagihans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_tagihans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `idUser` bigint(20) unsigned NOT NULL,
  `noTagihan` varchar(255) NOT NULL COMMENT 'uuid',
  `idPaket` int(11) NOT NULL COMMENT 'id paket',
  `bulan` int(5) NOT NULL,
  `tahun` int(5) NOT NULL,
  `statusBayar` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 paid',
  `harga` int(10) NOT NULL,
  `ppn` int(10) NOT NULL,
  `diskon` int(10) NOT NULL,
  `total` int(10) NOT NULL,
  `terbayar` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tglJatuhTempo` date NOT NULL COMMENT 'Tgl jatuh tempo tagihanya',
  `tglBayar` datetime DEFAULT NULL COMMENT 'tanggalBayar',
  `prosesBy` bigint(20) unsigned DEFAULT NULL COMMENT 'id admin yang bayar',
  `prosesNama` varchar(255) DEFAULT NULL COMMENT 'nama admin yang bayar',
  `metode` varchar(50) DEFAULT NULL COMMENT 'metode bayar',
  `aksi` varchar(50) DEFAULT 'created' COMMENT 'created, pembayaranm pembatalan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `create_by` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `noTagihan` (`noTagihan`),
  UNIQUE KEY `lamtim_tagihans_user_periode_unique` (`idUser`,`bulan`,`tahun`),
  KEY `lamtim_tagihans_ibfk_1` (`idUser`),
  KEY `lamtim_tagihans_ibfk_2` (`idPaket`),
  KEY `prosesBy` (`prosesBy`),
  CONSTRAINT `lamtim_tagihans_ibfk_1` FOREIGN KEY (`idUser`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `lamtim_tagihans_ibfk_2` FOREIGN KEY (`idPaket`) REFERENCES `lamtim_pakets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `lamtim_tagihans_ibfk_3` FOREIGN KEY (`prosesBy`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_transaksis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_transaksis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `idUser` bigint(20) unsigned NOT NULL,
  `idTagihan` bigint(20) unsigned NOT NULL COMMENT 'Id user+tahun+bln+tgl',
  `metodeBayar` varchar(50) DEFAULT NULL COMMENT 'kode payment',
  `namaPaket` varchar(100) DEFAULT NULL COMMENT 'nama paket di bayar',
  `tglBayar` datetime DEFAULT NULL COMMENT 'tanggalBayar',
  `prosesBy` bigint(20) unsigned DEFAULT NULL COMMENT 'id admin yang bayar',
  `prosesNama` varchar(255) DEFAULT NULL COMMENT 'nama admin yang bayar',
  `harga` int(11) DEFAULT NULL,
  `ppn` int(11) DEFAULT NULL,
  `diskon` int(11) DEFAULT NULL,
  `total` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `batalOleh` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lamtim_transaksis_ibfk_1` (`idUser`),
  KEY `prosesBy` (`prosesBy`),
  KEY `lamtim_transaksis_ibfk_2` (`idTagihan`),
  CONSTRAINT `lamtim_transaksis_ibfk_1` FOREIGN KEY (`idUser`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `lamtim_transaksis_ibfk_2` FOREIGN KEY (`idTagihan`) REFERENCES `lamtim_tagihans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lamtim_transaksis_ibfk_3` FOREIGN KEY (`prosesBy`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_user_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_user_details` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `idUser` bigint(20) unsigned NOT NULL,
  `idArea` bigint(11) DEFAULT NULL,
  `tglDafatar` date DEFAULT NULL,
  `tglJatuhTempo` date DEFAULT NULL COMMENT 'Tanggal jatuh tempo',
  `mulaiTagih` date DEFAULT NULL,
  `statusPpn` tinyint(1) DEFAULT NULL COMMENT '1 aktif atau 0 tidak',
  `statusTagihan` tinyint(1) DEFAULT NULL COMMENT '1 aktif 0 tidak kirim tagihan ke wa',
  `jenisBayar` tinyint(1) DEFAULT NULL COMMENT '1.pascabayar 2.Prabayar',
  `googleMap` varchar(255) DEFAULT NULL COMMENT 'Url google map, lokasinya',
  `js` enum('L','P') DEFAULT NULL,
  `telegram` varchar(100) DEFAULT NULL,
  `fotoProfile` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `qrcode` varchar(255) DEFAULT NULL,
  `identitas` varchar(10) DEFAULT NULL,
  `noIdentitas` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idArea` (`idArea`),
  KEY `idUser` (`idUser`),
  CONSTRAINT `lamtim_user_details_ibfk_1` FOREIGN KEY (`idArea`) REFERENCES `lamtim_areas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `lamtim_user_details_ibfk_2` FOREIGN KEY (`idUser`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lamtim_user_mikrotik_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lamtim_user_mikrotik_details` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `idUser` bigint(20) unsigned NOT NULL,
  `idKategori` int(11) DEFAULT NULL,
  `idPaket` int(11) DEFAULT NULL,
  `idMikrotik` bigint(20) DEFAULT NULL COMMENT 'id server mikrotik, di mikrotik mana pelanggan',
  `idMikrotikUser` varchar(255) DEFAULT NULL COMMENT 'id user di mikrotik',
  `namaMikrotikUser` varchar(255) DEFAULT NULL COMMENT 'nama user di mikrotik',
  `serviceMikrotikUser` varchar(255) DEFAULT NULL COMMENT 'service user di mikrotik',
  `profileMikrotikUser` varchar(255) DEFAULT NULL COMMENT 'proifle user di mikrotik',
  `statusIsolir` tinyint(1) DEFAULT 0 COMMENT '1 isolir 0 tidak',
  `password` varchar(255) DEFAULT NULL COMMENT 'password',
  `localAdress` varchar(100) DEFAULT NULL COMMENT 'ip local di mikrotik',
  `remoteAdress` varchar(100) DEFAULT NULL COMMENT 'ip remote di mikrotik',
  `idOlt` bigint(20) DEFAULT NULL,
  `idOdc` bigint(20) DEFAULT NULL,
  `idOdp` bigint(20) DEFAULT NULL,
  `portOdp` int(1) DEFAULT NULL COMMENT 'lokasi port odp',
  `modeIp` varchar(100) DEFAULT NULL COMMENT 'DHCP / STATIC / PPOE / HOTSPOT',
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  `route_waypoints` longtext DEFAULT NULL,
  `tglDiIsolir` datetime DEFAULT NULL COMMENT 'catat tanggal di isolir',
  `tglDiBuka` datetime DEFAULT NULL COMMENT 'catata tanggal di buka isolir',
  `tglDiOff` datetime DEFAULT NULL COMMENT 'catat tanggal di off akun',
  `diskon` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Nama paket pada mikrotik',
  `updated_at` timestamp NULL DEFAULT NULL,
  `upload_speed` varchar(50) DEFAULT NULL,
  `download_speed` varchar(50) DEFAULT NULL,
  `wifi_name` varchar(100) DEFAULT NULL,
  `ont_sn` varchar(100) DEFAULT NULL,
  `kabel` varchar(100) DEFAULT NULL,
  `keterangan` time DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'online',
  PRIMARY KEY (`id`),
  KEY `idOdc` (`idOdc`),
  KEY `idOdp` (`idOdp`),
  KEY `idUser` (`idUser`),
  KEY `idKategori` (`idKategori`),
  KEY `idPaket` (`idPaket`),
  KEY `lamtim_user_mikrotik_details_ont_sn_index` (`ont_sn`),
  KEY `idx_nama_mikrotik_user` (`namaMikrotikUser`),
  CONSTRAINT `lamtim_user_mikrotik_details_ibfk_1` FOREIGN KEY (`idOdc`) REFERENCES `lamtim_odcs` (`id`),
  CONSTRAINT `lamtim_user_mikrotik_details_ibfk_2` FOREIGN KEY (`idOdp`) REFERENCES `lamtim_odps` (`id`),
  CONSTRAINT `lamtim_user_mikrotik_details_ibfk_3` FOREIGN KEY (`idUser`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `lamtim_user_mikrotik_details_ibfk_4` FOREIGN KEY (`idKategori`) REFERENCES `lamtim_kategoris` (`id`),
  CONSTRAINT `lamtim_user_mikrotik_details_ibfk_5` FOREIGN KEY (`idPaket`) REFERENCES `lamtim_pakets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `idRole` tinyint(1) NOT NULL DEFAULT 5 COMMENT 'role id',
  `name` varchar(255) NOT NULL,
  `julukan` varchar(255) DEFAULT NULL COMMENT 'julukan pelanggan',
  `email` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `wa` varchar(25) NOT NULL COMMENT 'no wa bisa untuk login',
  `isActive` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 aktif 0 off',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*M!999999\- enable the sandbox mode */ 
set autocommit=0;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_02_04_120139_add_id_odp_to_lamtim_odps_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_02_04_122647_make_id_odc_nullable_in_lamtim_odps_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_02_10_100000_add_polygon_columns_to_lamtim_areas_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_02_10_100001_change_code_area_to_string_on_lamtim_areas_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_02_10_200000_add_idOdc_to_lamtim_odcs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_05_05_123343_add_speed_limit_to_lamtim_pakets',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_05_05_123927_add_more_radius_attributes_to_lamtim_pakets',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_05_05_131352_add_burst_fields_to_lamtim_pakets',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_05_05_133238_create_lamtim_ip_pools_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_05_05_134905_create_lamtim_address_lists_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_05_06_044631_increase_kode_length_in_lamtim_pakets',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_11_17_002946_add_ont_sn_to_lamtim_user_mikrotik_details_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_11_17_070000_add_terbayar_to_lamtim_tagihans_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_11_17_071000_change_id_tagihan_unique_to_index_on_lamtim_transaksis_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_11_20_090000_add_tahun_to_lamtim_diskons_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_11_20_090100_add_unique_periode_to_lamtim_tagihans_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_11_20_100000_drop_freeradius_integration',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_11_24_100000_encrypt_password_on_lamtim_mikrotiks_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_11_24_110000_add_mulai_tagih_to_lamtim_user_details_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_11_24_120000_add_soft_delete_to_lamtim_transaksis_table',8);
commit;
