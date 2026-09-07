-- =============================================================================
-- Menambahkan kolom catatan_ocr pada view v_pelaporan_iku
--
-- Konteks
--   Migrasi 2026-09-08_add_catatan_ocr_to_pelaporan.sql sudah menambahkan kolom
--   `catatan_ocr` ke TABEL `pelaporan_iku`. Namun view `v_pelaporan_iku`
--   mengenumerasi kolomnya satu per satu (bukan SELECT *), dan MySQL MEMBEKUKAN
--   daftar kolom sebuah view pada saat view itu dibuat. Akibatnya kolom baru
--   tidak ikut muncul di view sampai view-nya dibuat ulang.
--
--   catatan_ocr dibutuhkan oleh dua jalur yang membaca view ini:
--     - ikuController::getData()  -> tabel daftar pelaporan
--     - ikuController::editData() -> detail satu laporan
--   Karena itu kolomnya ditaruh di view, bukan diambil lewat query terpisah di
--   masing-masing jalur â€” sama seperti yang dilakukan untuk ocr_result.
--
--   Migrasi ini membuat ulang view dengan definisi yang PERSIS SAMA seperti yang
--   sedang berjalan, hanya ditambah satu kolom `a`.`catatan_ocr` yang diletakkan
--   setelah `ocr_result` agar urutannya mengikuti tabel dasarnya. Definisi di
--   bawah di-generate langsung dari SHOW CREATE VIEW, bukan disalin manual.
--
--   Jumlah kolom view: 58 -> 59.
--
--   Menyusul di sisi PHP: _getProperties() mengecualikan catatan_ocr agar filter
--   keyword tidak ikut mem-LIKE isi JSON-nya. Lihat komentar di fungsi tersebut.
--
-- Prasyarat
--   Jalankan 2026-09-08_add_catatan_ocr_to_pelaporan.sql LEBIH DULU.
--   Tanpa itu, CREATE OR REPLACE di bawah gagal dengan "Unknown column".
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-08_add_catatan_ocr_to_v_pelaporan_iku.sql
--
-- PERHATIAN soal DEFINER
--   View ini ber-DEFINER `admin`@`%` dengan SQL SECURITY DEFINER, sedangkan
--   kredensial aplikasi di .env adalah `sitala_admin`@`%`. Menetapkan DEFINER ke
--   akun LAIN memerlukan privilege SUPER / SET_USER_ID. Bila dijalankan sebagai
--   sitala_admin, perintah di bawah kemungkinan ditolak:
--
--       ERROR 1227 (42000): Access denied; you need the SUPER privilege
--
--   Dua pilihan bila itu terjadi:
--     a. Jalankan sebagai `admin` (master user RDS) -- definer tetap utuh.
--     b. Hapus bagian "DEFINER=`admin`@`%`" dari perintah di bawah. View lalu
--        ber-definer sitala_admin. Aplikasi tetap jalan karena memang konek
--        sebagai akun itu, tapi konsumen lain yang memakai akun berbeda ikut
--        terpengaruh, dan view rusak bila akun itu kelak dihapus.
--   Konfirmasikan ke pengelola database sebelum memilih (b).
--
-- Catatan
--   CREATE OR REPLACE VIEW hanya mengganti definisi; tidak ada data yang
--   tersentuh dan tabel dasarnya tidak dikunci lama.
-- =============================================================================

CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`admin`@`%` SQL SECURITY DEFINER VIEW `v_pelaporan_iku` AS 
  select `a`.`uid_pelaporan_iku` AS `uid_pelaporan_iku`,
         `a`.`crdate` AS `crdate`,
         `a`.`chdate` AS `chdate`,
         `a`.`deleted` AS `deleted`,
         `a`.`hidden` AS `hidden`,
         `a`.`cruser` AS `cruser`,
         `usr`.`role_user` AS `role_user`,
         `a`.`tanggal` AS `tanggal`,
         `a`.`periode_pemantauan` AS `periode_pemantauan`,
         `a`.`durasi_pemantauan` AS `durasi_pemantauan`,
         `a`.`uid_lokasi_pemantauan` AS `uid_lokasi_pemantauan`,
         `a`.`uid_rf_peruntukan` AS `uid_rf_peruntukan`,
         `e`.`nama` AS `peruntukan`,
         `a`.`uid_metode_pemantauan` AS `uid_metode_pemantauan`,
         `f`.`metode` AS `metode`,
         `a`.`no2` AS `no2`,
         `a`.`no2_uid_metode_pemantauan` AS `no2_uid_metode_pemantauan`,
         `a`.`no2_durasi_pemantauan` AS `no2_durasi_pemantauan`,
         `a`.`no2_durasi_pemantauan_pasif` AS `no2_durasi_pemantauan_pasif`,
         `a`.`no2_faktor_koreksi` AS `no2_faktor_koreksi`,
         `a`.`so2` AS `so2`,
         `a`.`so2_uid_metode_pemantauan` AS `so2_uid_metode_pemantauan`,
         `a`.`so2_durasi_pemantauan` AS `so2_durasi_pemantauan`,
         `a`.`so2_durasi_pemantauan_pasif` AS `so2_durasi_pemantauan_pasif`,
         `a`.`so2_faktor_koreksi` AS `so2_faktor_koreksi`,
         `a`.`pm25` AS `pm25`,
         `np`.`iku_pm25_satelit` AS `pm25_np_satelit`,
         `a`.`pm25_uid_metode_pemantauan` AS `pm25_uid_metode_pemantauan`,
         `a`.`pm25_durasi_pemantauan` AS `pm25_durasi_pemantauan`,
         `a`.`pm25_durasi_pemantauan_pasif` AS `pm25_durasi_pemantauan_pasif`,
         `a`.`pm25_faktor_koreksi` AS `pm25_faktor_koreksi`,
         `a`.`shu` AS `shu`,
         `a`.`ocr_result` AS `ocr_result`,
         `a`.`catatan_ocr` AS `catatan_ocr`,
         `a`.`uid_lab` AS `uid_lab`,
         `a`.`catatan_verifikator` AS `catatan_verifikator`,
         `a`.`catatan_provinsi` AS `catatan_provinsi`,
         `a`.`catatan_verifikator_select` AS `catatan_verifikator_select`,
         `a`.`catatan_provinsi_select` AS `catatan_provinsi_select`,
         `a`.`catatan_regional` AS `catatan_regional`,
         `a`.`catatan_regional_select` AS `catatan_regional_select`,
         `a`.`v_provinsi` AS `v_provinsi`,
         `a`.`v_regional` AS `v_regional`,
         `a`.`v_pusat` AS `v_pusat`,
         `a`.`v_reject_status` AS `v_reject_status`,
         `a`.`v_provinsi_date` AS `v_provinsi_date`,
         `a`.`v_regional_date` AS `v_regional_date`,
         `a`.`v_pusat_date` AS `v_pusat_date`,
         `b`.`alamat` AS `alamat`,
         `b`.`kode_lokasi` AS `kode_lokasi`,
         `b`.`alamat_detail` AS `alamat_detail`,
         `b`.`uid_rf_pelaksana` AS `uid_rf_pelaksana`,
         `b`.`uid_provinsi` AS `uid_provinsi`,
         `b`.`uid_kabkota` AS `uid_kabkota`,
         `c`.`kd_regional` AS `kd_regional`,
         `c`.`nama_propinsi` AS `nama_provinsi`,
         `d`.`nama_kabkot` AS `nama_kabkota`,
         `b`.`latitude` AS `latitude`,
         `b`.`longitude` AS `longitude`
  from (((((((`pelaporan_iku` `a` left join `lokasi_pemantauan` `b` on((`b`.`uid_lokasi_pemantauan` = `a`.`uid_lokasi_pemantauan`))) left join `rf_provinsi` `c` on((`c`.`kd_propinsi` = `b`.`uid_provinsi`))) left join `rf_kabkota` `d` on((`d`.`kd_kota` = `b`.`uid_kabkota`))) left join `rf_peruntukan` `e` on((`e`.`uid_rf_peruntukan` = `a`.`uid_rf_peruntukan`))) left join `rf_metode_pemantauan` `f` on((`f`.`uid_metode_pemantauan` = `a`.`uid_metode_pemantauan`))) left join `rf_nilai_pelengkap_iklh` `np` on(((`np`.`uid_provinsi` = `b`.`uid_provinsi`) and (`np`.`uid_kabkota` = `b`.`uid_kabkota`) and (`np`.`tahun` = year(`a`.`tanggal`)) and (`np`.`deleted` = 0)))) left join `users` `usr` on((`a`.`cruser` = `usr`.`uid_users`)));

-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- SELECT ordinal_position, column_name
--   FROM information_schema.columns
--  WHERE table_schema = DATABASE()
--    AND table_name   = 'v_pelaporan_iku'
--    AND column_name IN ('shu', 'ocr_result', 'catatan_ocr')
--  ORDER BY ordinal_position;
--
-- Harus mengembalikan 3 baris berurutan: shu, ocr_result, catatan_ocr.


-- -----------------------------------------------------------------------------
-- Rollback
-- -----------------------------------------------------------------------------
-- Jalankan ulang 2026-09-02_add_ocr_result_to_v_pelaporan_iku.sql, yang memuat
-- definisi view tanpa catatan_ocr.
