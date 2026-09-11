-- =============================================================================
-- Menambahkan parameter matching lokasi berbasis koordinat ke config_parameters
--
-- Konteks
--   Tiga parameter dipakai ocrController::nearestLokasiByCoordinate() (di
--   application/controllers/ocrController.php) untuk matchLokasi(), lewat
--   utils::configInt(). Sebelumnya semua hardcoded langsung di kode.
--
--   - KOORDINAT_MATCH_RADIUS_M: radius maksimal (meter) agar sebuah titik
--     lokasi_pemantauan dianggap representasi koordinat GPS hasil OCR.
--     Sebelumnya 2000m hardcoded, sekarang diturunkan jadi 500m.
--   - KOORDINAT_MATCH_CANDIDATE_LIMIT: jumlah kandidat terdekat (dalam radius)
--     yang diambil sebelum fuzzy tie-break berbasis teks.
--   - KOORDINAT_MATCH_FUZZY_THRESHOLD: skor minimum similar_text() (persen)
--     agar kandidat fuzzy terbaik menang atas kandidat yang jaraknya paling
--     dekat. Di bawah ambang ini, kandidat terdekat yang dipakai.
--
-- Prasyarat
--   2026-09-07_create_config_parameters.sql sudah dijalankan.
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-11_add_koordinat_match_radius_param.sql
--
-- Sifat
--   Aman dijalankan berulang: ON DUPLICATE KEY UPDATE `key` = `key` membuat
--   perintah ini tidak melakukan apa-apa bila barisnya sudah ada, sehingga nilai
--   yang sudah disetel operator tidak tertimpa kembali ke bawaan.
-- =============================================================================

INSERT INTO `config_parameters` (`key`, `value_int`, `value_text`, `description`) VALUES
  ('KOORDINAT_MATCH_RADIUS_M', 500, NULL,
   'Radius maksimal, dalam meter, agar titik lokasi_pemantauan dianggap kandidat cocok dengan koordinat GPS hasil OCR. Dipakai ocrController::nearestLokasiByCoordinate() sebagai batas pencarian kandidat terdekat sebelum fuzzy tie-break.'),
  ('KOORDINAT_MATCH_CANDIDATE_LIMIT', 10, NULL,
   'Jumlah kandidat lokasi terdekat (dalam KOORDINAT_MATCH_RADIUS_M) yang diambil sebelum fuzzy tie-break berbasis teks di ocrController::nearestLokasiByCoordinate().'),
  ('KOORDINAT_MATCH_FUZZY_THRESHOLD', 80, NULL,
   'Skor minimum similar_text() (persen) agar kandidat dengan teks paling mirip menang atas kandidat yang jaraknya paling dekat. Di bawah ambang ini, kandidat terdekat (bukan yang teksnya paling mirip) yang dipakai. Dipakai ocrController::nearestLokasiByCoordinate().')
ON DUPLICATE KEY UPDATE `key` = `key`;


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- Harus mengembalikan 3 baris:
--
-- SELECT `key`, `value_int` FROM `config_parameters`
--  WHERE `key` LIKE 'KOORDINAT\_MATCH\_%' ORDER BY `key`;


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- DELETE FROM `config_parameters`
--  WHERE `key` IN ('KOORDINAT_MATCH_RADIUS_M', 'KOORDINAT_MATCH_CANDIDATE_LIMIT', 'KOORDINAT_MATCH_FUZZY_THRESHOLD');
