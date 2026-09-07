-- =============================================================================
-- Menambahkan kolom catatan_ocr pada pelaporan_iku, pelaporan_ika, pelaporan_ikal
--
-- Konteks
--   Kolom `ocr_result` (migrasi 2026-09-01 dan 2026-09-02) menyimpan apa yang
--   DIBACA model dari dokumen SHU. Kolom `catatan_ocr` ini menyimpan catatan
--   JSON yang menyertai pembacaan itu — hal-hal yang perlu diketahui tentang
--   hasil OCR sebuah baris, terpisah dari payload bacaannya sendiri.
--
--   Dipisah dari `ocr_result` dan tidak dilebur ke dalamnya karena keduanya
--   berbeda asal-usul dan berbeda umur: `ocr_result` adalah keluaran model yang
--   sekali tulis lalu tidak berubah, sedangkan catatan bisa ditambah atau
--   dikoreksi setelahnya. Menaruhnya di dalam payload yang sama berarti setiap
--   perubahan catatan menulis ulang jejak audit yang justru harus tetap utuh.
--
--   Dibedakan pula dari keluarga `catatan_verifikator` / `catatan_provinsi` /
--   `catatan_regional` yang sudah ada: yang itu catatan bebas dari manusia dalam
--   alur verifikasi berjenjang, sedangkan yang ini terstruktur (JSON) dan terikat
--   pada proses OCR.
--
-- Cara menjalankan (manual — tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-08_add_catatan_ocr_to_pelaporan.sql
--
--   Ketiga ALTER disatukan dalam satu berkas karena merupakan satu perubahan
--   yang sama pada tiga tabel sejajar. Menjalankan sebagian saja tidak merusak
--   apa pun — kode PHP menoleransi kolom yang belum ada (lihat catatan di bawah).
--
-- Catatan tipe kolom
--   TEXT menampung maksimal 65.535 byte, sama seperti `ocr_result`. Perlu
--   diketahui: sql_mode server ini TIDAK memuat STRICT_TRANS_TABLES, sehingga
--   nilai yang melebihi kapasitas akan dipotong DIAM-DIAM tanpa error. Bila
--   nanti isinya membesar, ganti ke MEDIUMTEXT (16 MB).
--
-- Cakupan sisi PHP saat ini
--   Hanya modul IKU yang sudah membaca/menulis kolom ini (index, editData,
--   getData). IKA dan IKAL mendapat kolomnya sekarang supaya ketiganya tetap
--   sejajar dan migrasi tidak perlu diulang belakangan, tapi controller-nya
--   belum menyentuh kolom itu — nilainya akan tetap NULL sampai dikerjakan.
-- =============================================================================

ALTER TABLE `pelaporan_iku`
  ADD COLUMN `catatan_ocr` TEXT NULL DEFAULT NULL
  COMMENT 'Catatan JSON yang menyertai hasil OCR baris ini'
  AFTER `ocr_result`;

ALTER TABLE `pelaporan_ika`
  ADD COLUMN `catatan_ocr` TEXT NULL DEFAULT NULL
  COMMENT 'Catatan JSON yang menyertai hasil OCR baris ini'
  AFTER `ocr_result`;

ALTER TABLE `pelaporan_ikal`
  ADD COLUMN `catatan_ocr` TEXT NULL DEFAULT NULL
  COMMENT 'Catatan JSON yang menyertai hasil OCR baris ini'
  AFTER `ocr_result`;


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- SELECT table_name, column_name, column_type, is_nullable, column_comment
--   FROM information_schema.columns
--  WHERE table_schema = DATABASE()
--    AND table_name IN ('pelaporan_iku', 'pelaporan_ika', 'pelaporan_ikal')
--    AND column_name = 'catatan_ocr';
--
-- Harus mengembalikan TEPAT 3 baris, semuanya text / YES.


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- ALTER TABLE `pelaporan_iku`  DROP COLUMN `catatan_ocr`;
-- ALTER TABLE `pelaporan_ika`  DROP COLUMN `catatan_ocr`;
-- ALTER TABLE `pelaporan_ikal` DROP COLUMN `catatan_ocr`;
--
-- Bila 2026-09-08_add_catatan_ocr_to_v_pelaporan_iku.sql sudah dijalankan,
-- kembalikan DULU view-nya ke definisi tanpa catatan_ocr. Menghapus kolom yang
-- masih dirujuk view tidak ditolak MySQL — view-nya yang kemudian rusak, dan
-- baru ketahuan saat di-SELECT dengan "View references invalid table(s)".
