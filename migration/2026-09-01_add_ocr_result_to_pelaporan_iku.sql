-- =============================================================================
-- Menambahkan kolom ocr_result pada tabel pelaporan_iku
--
-- Konteks
--   Alur OCR (/ocr/ikuExtract) saat ini tidak meninggalkan jejak apa pun:
--   dokumen sumber dibaca dari direktori temp PHP lalu dibuang tanpa disimpan,
--   dan hasil ekstraksinya hanya dipakai untuk mengisi form di browser.
--   Akibatnya, ketika suatu nilai dipertanyakan, tidak ada cara menelusuri
--   kembali apa yang sebenarnya dibaca model.
--
--   Kolom ini menyimpan payload JSON hasil ekstraksi (respons ikuExtract,
--   setelah pencocokan ke uid master) supaya tiap baris pelaporan bisa
--   ditelusuri ulang.
--
-- Cara menjalankan (manual — tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-01_add_ocr_result_to_pelaporan_iku.sql
--
-- Catatan tipe kolom
--   TEXT menampung maksimal 65.535 byte. Untuk satu dokumen dengan belasan
--   lokasi, payload biasanya beberapa kilobyte saja sehingga masih lapang.
--   Perlu diketahui: sql_mode server ini TIDAK memuat STRICT_TRANS_TABLES,
--   sehingga nilai yang melebihi kapasitas akan dipotong DIAM-DIAM tanpa error.
--   Bila nanti payload membesar, ganti ke MEDIUMTEXT (16 MB).
-- =============================================================================

ALTER TABLE `pelaporan_iku`
  ADD COLUMN `ocr_result` TEXT NULL DEFAULT NULL
  COMMENT 'Payload JSON hasil OCR dari ocrController::ikuExtract'
  AFTER `shu`;


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- SELECT column_name, column_type, is_nullable, column_comment
--   FROM information_schema.columns
--  WHERE table_schema = DATABASE()
--    AND table_name   = 'pelaporan_iku'
--    AND column_name  = 'ocr_result';


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- ALTER TABLE `pelaporan_iku` DROP COLUMN `ocr_result`;
