-- =============================================================================
-- Membuat tabel ai_token_usage
--
-- Konteks
--   Panggilan OCR ke OpenRouter ditagih per token, tapi sampai sekarang tidak ada
--   catatan apa pun di sisi aplikasi: sekali respons selesai dikirim ke browser,
--   angka pemakaiannya hilang. Akibatnya biaya hanya bisa dilihat sebagai satu
--   angka gabungan di dasbor OpenRouter -- tidak bisa dipecah per modul, per
--   pengguna, atau per bulan.
--
--   Tabel ini mencatat SATU BARIS PER PANGGILAN ke model, dengan rincian token
--   dan biaya yang dilaporkan OpenRouter apa adanya.
--
--   Kolomnya mengikuti bentuk `usage` yang dikembalikan
--   openrouter::normalizeUsage(), ditambah `service` untuk menandai asal
--   panggilan.
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-08_create_ai_token_usage.sql
--
-- Catatan tipe `cost`
--   DECIMAL, BUKAN DOUBLE. Kolom ini akan sering dijumlahkan (SUM per bulan, per
--   service), dan penjumlahan ribuan nilai DOUBLE mengakumulasi galat pembulatan
--   biner yang membuat total tidak pernah persis cocok dengan tagihan.
--   DECIMAL(16,10) menampung sampai ~999.999 USD dengan 10 angka di belakang
--   koma; biaya satu dokumen berada di orde 0,0000001 sampai 0,01 USD, jadi
--   presisi itu perlu supaya panggilan murah tidak membulat jadi nol.
--
-- Catatan kolom token
--   Semuanya NULL-able, dan NULL BERBEDA dari 0. NULL berarti provider tidak
--   melaporkan angka itu; 0 berarti dilaporkan dan memang nol. Perbedaan ini
--   penting saat menjumlahkan: menganggap NULL sebagai 0 akan diam-diam
--   mengecilkan total, sedangkan SUM() bawaan MySQL memang melewatkan NULL.
--
-- Catatan `service`
--   VARCHAR, bukan ENUM. Nilai awal yang dipakai: 'iku-ocr', 'ikuaqms-ocr',
--   'ika-ocr', 'ikal-ocr'. Layanan baru akan menyusul, dan menambah nilai ENUM
--   memerlukan ALTER TABLE pada tabel yang isinya terus bertambah -- sedangkan
--   VARCHAR cukup ditulis dari kode.
--
-- Catatan `crdate` / `cruser`
--   Mengikuti konvensi tabel lain di basis data ini: crdate berupa UNIX timestamp
--   (INT), bukan DATETIME. cruser merujuk users.uid_users, boleh NULL untuk
--   panggilan yang tidak berasal dari sesi login.
--
--   Kolom `deleted` / `hidden` SENGAJA tidak disertakan. Tabel ini catatan
--   keuangan yang hanya ditulis sekali; menghapus sebagian barisnya secara logis
--   justru membuat total tidak lagi bisa dipercaya.
--
-- Sifat
--   Aman dijalankan berulang: CREATE TABLE IF NOT EXISTS.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `ai_token_usage` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service`           VARCHAR(64)     NOT NULL              COMMENT 'Asal panggilan: iku-ocr, ikuaqms-ocr, ika-ocr, ikal-ocr, dst',
  `prompt_tokens`     INT UNSIGNED    NULL DEFAULT NULL     COMMENT 'Token masukan. NULL = tidak dilaporkan provider',
  `completion_tokens` INT UNSIGNED    NULL DEFAULT NULL     COMMENT 'Token keluaran. NULL = tidak dilaporkan provider',
  `total_tokens`      INT UNSIGNED    NULL DEFAULT NULL     COMMENT 'Jumlah keduanya menurut provider, tidak dihitung ulang di sini',
  `cached_tokens`     INT UNSIGNED    NULL DEFAULT NULL     COMMENT 'Bagian prompt_tokens yang dilayani dari cache, ditagih lebih murah',
  `reasoning_tokens`  INT UNSIGNED    NULL DEFAULT NULL     COMMENT 'Token penalaran. Selalu 0 untuk model non-reasoning',
  `cost`              DECIMAL(16,10)  NULL DEFAULT NULL     COMMENT 'Biaya dalam USD menurut OpenRouter. NULL = tidak dilaporkan',
  `model`             VARCHAR(128)    NULL DEFAULT NULL     COMMENT 'Model yang BENAR-BENAR melayani; bisa beda dari yang diminta bila OpenRouter merutekan ulang',
  `generation_id`     VARCHAR(128)    NULL DEFAULT NULL     COMMENT 'Id generasi OpenRouter, untuk menelusuri satu panggilan di dasbor mereka',
  `crdate`            INT             NULL DEFAULT NULL     COMMENT 'UNIX timestamp saat panggilan dicatat',
  `cruser`            INT             NULL DEFAULT NULL     COMMENT 'users.uid_users pemicu panggilan, NULL bila tanpa sesi',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- SELECT column_name, column_type, is_nullable
--   FROM information_schema.columns
--  WHERE table_schema = DATABASE() AND table_name = 'ai_token_usage'
--  ORDER BY ordinal_position;
--
-- Harus mengembalikan 12 baris.


-- -----------------------------------------------------------------------------
-- Contoh rekap
-- -----------------------------------------------------------------------------
-- Biaya dan token per layanan pada bulan berjalan:
--
-- SELECT `service`,
--        COUNT(*)                AS panggilan,
--        SUM(`total_tokens`)     AS token,
--        ROUND(SUM(`cost`), 6)   AS biaya_usd
--   FROM `ai_token_usage`
--  WHERE `crdate` >= UNIX_TIMESTAMP(DATE_FORMAT(NOW(), '%Y-%m-01'))
--  GROUP BY `service`
--  ORDER BY biaya_usd DESC;


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- DROP TABLE `ai_token_usage`;
