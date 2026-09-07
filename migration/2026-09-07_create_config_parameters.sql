-- =============================================================================
-- Membuat tabel config_parameters + parameter ambang pergeseran lokasi
--
-- Konteks
--   Ambang pergeseran koordinat semula berupa konstanta PHP
--   (MAX_LOCATION_SHIFT_M di config/globalsetting.php). Mengubahnya berarti
--   menyunting berkas dan menerbitkan ulang aplikasi.
--
--   Tabel ini memindahkan nilai semacam itu ke database supaya bisa disetel
--   tanpa deploy, dan supaya parameter lain menyusul di tempat yang sama.
--
--   Dipakai oleh utils::validateLocationPelaporan() lewat utils::configInt().
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-07_create_config_parameters.sql
--
-- Catatan nama kolom
--   `key` adalah KATA CADANGAN di MySQL, jadi WAJIB ditulis dalam backtick di
--   setiap query. Penamaan ini mengikuti permintaan; bila kelak terasa
--   merepotkan, ganti ke config_key dan sesuaikan utils::loadConfig().
--
-- Catatan tipe
--   `id` memakai semantik SERIAL (BIGINT UNSIGNED AUTO_INCREMENT) tapi ditulis
--   eksplisit, sebab SERIAL di MySQL hanya membuat indeks UNIQUE -- bukan
--   PRIMARY KEY.
--
--   Nilai dipisah dua kolom: value_int untuk angka, value_text untuk teks.
--   Parameter ambang di bawah memakai value_int; value_text dibiarkan NULL.
--
-- Sifat
--   Berkas ini aman dijalankan berulang: CREATE TABLE IF NOT EXISTS, dan
--   INSERT ... ON DUPLICATE KEY UPDATE yang TIDAK menimpa nilai yang sudah
--   disetel operator.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `config_parameters` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`         VARCHAR(100)    NOT NULL                COMMENT 'Nama parameter, huruf kapital dengan garis bawah',
  `value_int`   INT             NULL DEFAULT NULL       COMMENT 'Nilai bila parameternya berupa angka',
  `value_text`  VARCHAR(255)    NULL DEFAULT NULL       COMMENT 'Nilai bila parameternya berupa teks',
  `description` TEXT            NULL DEFAULT NULL       COMMENT 'Keterangan untuk operator: arti nilai dan akibat bila diubah',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_config_parameters_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- Parameter awal
-- -----------------------------------------------------------------------------
-- ON DUPLICATE KEY UPDATE `key` = `key` sengaja dipakai: bila baris dengan key
-- yang sama sudah ada, perintah ini TIDAK melakukan apa-apa. Nilai yang sudah
-- disetel operator di produksi tidak akan tertimpa kembali ke bawaan.

INSERT INTO `config_parameters` (`key`, `value_int`, `value_text`, `description`) VALUES
  ('LOCATION_SHIFT_OK_M', 50, NULL,
   'Batas atas pergeseran koordinat yang dianggap wajar, dalam meter. Pergeseran sampai nilai ini berstatus "ok".'),
  ('LOCATION_SHIFT_WARN_M', 100, NULL,
   'Batas atas pergeseran yang masih ditoleransi dengan peringatan, dalam meter. Di atas LOCATION_SHIFT_OK_M sampai nilai ini berstatus "warn"; lebih dari ini berstatus "invalid". Harus lebih besar dari LOCATION_SHIFT_OK_M.')
ON DUPLICATE KEY UPDATE `key` = `key`;


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- Harus mengembalikan 2 baris, value_int 50 dan 100:
--
-- SELECT `key`, `value_int`, `value_text` FROM `config_parameters`
--  WHERE `key` IN ('LOCATION_SHIFT_OK_M', 'LOCATION_SHIFT_WARN_M')
--  ORDER BY `value_int`;


-- -----------------------------------------------------------------------------
-- Contoh penyetelan di kemudian hari
-- -----------------------------------------------------------------------------
-- UPDATE `config_parameters` SET `value_int` = 75 WHERE `key` = 'LOCATION_SHIFT_OK_M';


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- DROP TABLE `config_parameters`;
