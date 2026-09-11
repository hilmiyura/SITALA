-- =============================================================================
-- Menambahkan parameter LAB_MATCH_FUZZY_THRESHOLD ke config_parameters
--
-- Konteks
--   Skor minimum (persen) dari likeScoreSql() (kombinasi LIKE per kata terhadap
--   kolom nama + kode) agar sebuah baris rf_lab dianggap cocok dengan nama lab
--   hasil OCR di ocrController::matchLab(). Sebelumnya matchLab() murni LIKE
--   substring tanpa skor/threshold sama sekali (dan tanpa ORDER BY, sehingga
--   hasil tidak deterministik kalau lebih dari satu baris cocok).
--
--   Dipakai ocrController::matchLab() lewat utils::configInt().
--
-- Prasyarat
--   2026-09-07_create_config_parameters.sql sudah dijalankan.
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-11_add_lab_match_fuzzy_threshold_param.sql
--
-- Sifat
--   Aman dijalankan berulang: ON DUPLICATE KEY UPDATE `key` = `key` membuat
--   perintah ini tidak melakukan apa-apa bila barisnya sudah ada, sehingga nilai
--   yang sudah disetel operator tidak tertimpa kembali ke bawaan.
-- =============================================================================

INSERT INTO `config_parameters` (`key`, `value_int`, `value_text`, `description`) VALUES
  ('LAB_MATCH_FUZZY_THRESHOLD', 70, NULL,
   'Skor minimum (persen) dari kemiripan kata (LIKE per kata terhadap nama + kode) agar sebuah baris rf_lab dianggap cocok dengan nama laboratorium hasil OCR. Dipakai ocrController::matchLab() sebagai fallback setelah exact match kode tidak ketemu.')
ON DUPLICATE KEY UPDATE `key` = `key`;


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- SELECT `key`, `value_int` FROM `config_parameters` WHERE `key` = 'LAB_MATCH_FUZZY_THRESHOLD';


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- DELETE FROM `config_parameters` WHERE `key` = 'LAB_MATCH_FUZZY_THRESHOLD';
