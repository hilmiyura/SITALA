-- =============================================================================
-- Menambahkan parameter LOCATION_MERGE_RADIUS_M ke config_parameters
--
-- Konteks
--   Satu berkas SHU bisa memuat gabungan sertifikat dari beberapa laboratorium
--   untuk TITIK FISIK YANG SAMA -- lazimnya lab A mengukur NO2+SO2 dan lab B
--   mengukur PM2.5, karena gas dan partikulat sering dikontrakkan terpisah.
--   Prompt OCR (application/prompts/iku.md) sengaja meminta model memecahnya
--   jadi entri terpisah per sertifikat, lalu menyerahkan penggabungannya ke
--   aplikasi.
--
--   Parameter ini adalah radius maksimal, dalam meter, agar dua entri hasil OCR
--   dianggap berada di titik yang sama dan boleh digabung. Dipakai
--   ocrController::mergeSameLocationEntries() lewat utils::configInt().
--
--   Perhatikan bedanya dengan LOCATION_SHIFT_OK_M / LOCATION_SHIFT_WARN_M:
--   dua parameter itu membandingkan koordinat yang DILAPORKAN terhadap koordinat
--   MASTER di lokasi_pemantauan. Parameter ini membandingkan dua bacaan OCR
--   terhadap SATU SAMA LAIN, yang semestinya menunjuk situs yang sama persis.
--
-- Prasyarat
--   2026-09-07_create_config_parameters.sql sudah dijalankan.
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-07_add_location_merge_radius_param.sql
--
-- Sifat
--   Aman dijalankan berulang: ON DUPLICATE KEY UPDATE `key` = `key` membuat
--   perintah ini tidak melakukan apa-apa bila barisnya sudah ada, sehingga nilai
--   yang sudah disetel operator tidak tertimpa kembali ke bawaan.
-- =============================================================================

INSERT INTO `config_parameters` (`key`, `value_int`, `value_text`, `description`) VALUES
  ('LOCATION_MERGE_RADIUS_M', 500, NULL,
   'Radius maksimal, dalam meter, agar dua entri hasil OCR pada satu dokumen dianggap berada di titik yang sama dan digabung jadi satu pelaporan. Hanya berlaku bila cakupan parameternya saling melengkapi -- dua entri yang sama-sama mengisi parameter yang sama tidak akan digabung meski berdekatan.')
ON DUPLICATE KEY UPDATE `key` = `key`;


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- Harus mengembalikan 3 baris (2 ambang pergeseran + 1 radius penggabungan):
--
-- SELECT `key`, `value_int` FROM `config_parameters`
--  WHERE `key` LIKE 'LOCATION\_%' ORDER BY `key`;


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- DELETE FROM `config_parameters` WHERE `key` = 'LOCATION_MERGE_RADIUS_M';
