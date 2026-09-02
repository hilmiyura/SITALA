-- =============================================================================
-- Menambahkan kolom ocr_result pada tabel pelaporan_ikal
--
-- Konteks
--   Alur OCR (/ocr/ikalExtract) saat ini tidak meninggalkan jejak apa pun:
--   dokumen sumber dibaca dari direktori temp PHP lalu dibuang tanpa disimpan,
--   dan hasil ekstraksinya hanya dipakai untuk mengisi form di browser.
--   Akibatnya, ketika suatu nilai dipertanyakan, tidak ada cara menelusuri
--   kembali apa yang sebenarnya dibaca model.
--
--   Kolom ini menyimpan payload JSON hasil ekstraksi (respons ikalExtract,
--   setelah pencocokan ke uid master) supaya tiap baris pelaporan bisa
--   ditelusuri ulang. Menyamakan pelaporan_ikal dengan pelaporan_iku yang sudah
--   lebih dulu diberi kolom serupa pada migrasi 2026-09-01.
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-02_add_ocr_result_to_pelaporan_ikal.sql
--
-- Urutan
--   Jalankan berkas ini LEBIH DULU, baru
--   2026-09-02_add_ocr_result_to_v_pelaporan_ikal.sql. View tidak bisa dibuat
--   ulang selama kolomnya belum ada di tabel dasar.
--
-- Catatan tipe kolom
--   TEXT menampung maksimal 65.535 byte. IKAL hanya punya 5 parameter, jadi
--   payloadnya paling kecil di antara ketiga modul.
--   Perlu diketahui: sql_mode server ini TIDAK memuat STRICT_TRANS_TABLES,
--   sehingga nilai yang melebihi kapasitas akan dipotong DIAM-DIAM tanpa error.
--   Karena itu controller memadatkan JSON-nya sebelum menyimpan (json_encode
--   ulang tanpa indentasi).
--
--   Catatan: tabel ini sudah punya kolom `json_data` yang juga menyimpan JSON
--   dalam TEXT. Keduanya berbeda peruntukan -- json_data adalah hasil
--   perhitungan aplikasi, ocr_result adalah bacaan mentah model.
-- =============================================================================

ALTER TABLE `pelaporan_ikal`
  ADD COLUMN `ocr_result` TEXT NULL DEFAULT NULL
  COMMENT 'Payload JSON hasil OCR dari ocrController::ikalExtract'
  AFTER `shu`;


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- SELECT column_name, column_type, is_nullable, column_comment
--   FROM information_schema.columns
--  WHERE table_schema = DATABASE()
--    AND table_name   = 'pelaporan_ikal'
--    AND column_name  = 'ocr_result';


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- ALTER TABLE `pelaporan_ikal` DROP COLUMN `ocr_result`;
