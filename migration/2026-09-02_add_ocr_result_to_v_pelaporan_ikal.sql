-- =============================================================================
-- Menambahkan kolom ocr_result pada view v_pelaporan_ikal
--
-- Konteks
--   Migrasi 2026-09-02_add_ocr_result_to_pelaporan_ikal.sql sudah menambahkan kolom
--   `ocr_result` ke TABEL `pelaporan_ikal`. Namun view `v_pelaporan_ikal`
--   mengenumerasi kolomnya satu per satu (bukan SELECT *), dan MySQL MEMBEKUKAN
--   daftar kolom sebuah view pada saat view itu dibuat. Akibatnya kolom baru
--   tidak ikut muncul di view sampai view-nya dibuat ulang.
--
--   ocr_result dibutuhkan oleh dua jalur yang membaca view ini:
--     - ikalController::getData()   -> tabel daftar pelaporan
--     - ikalController::dataExcel() -> ekspor Excel
--   Endpoint detail ikalController::editData() TIDAK termasuk: ia membaca
--   langsung dari tabel dasar, jadi sudah beres begitu migrasi tabel dijalankan.
--
--   Migrasi ini membuat ulang view dengan definisi yang PERSIS SAMA, hanya
--   ditambah satu kolom `ocr_result` yang diletakkan setelah `shu` agar
--   urutannya mengikuti tabel dasarnya.
--
--   Menyusul di sisi PHP: _getProperties() mengecualikan ocr_result agar filter
--   keyword tidak ikut mem-LIKE isi JSON-nya. Lihat komentar di fungsi tersebut.
--
-- Prasyarat
--   Jalankan 2026-09-02_add_ocr_result_to_pelaporan_ikal.sql LEBIH DULU.
--   Tanpa itu, CREATE OR REPLACE di bawah gagal dengan "Unknown column".
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-02_add_ocr_result_to_v_pelaporan_ikal.sql
--
-- PERHATIAN soal DEFINER
--   View ini saat ini ber-DEFINER `admin`@`%` dengan SQL SECURITY DEFINER,
--   sedangkan kredensial aplikasi di .env adalah `sitala_admin`@`%`.
--   Menetapkan DEFINER ke akun LAIN memerlukan privilege SUPER / SET_USER_ID.
--   Bila dijalankan sebagai sitala_admin, perintah di bawah kemungkinan ditolak:
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

CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`admin`@`%` SQL SECURITY DEFINER VIEW `v_pelaporan_ikal` AS
  select `a`.`uid_pelaporan_ikal` AS `uid_pelaporan_ikal`,
         `a`.`crdate` AS `crdate`,
         `a`.`chdate` AS `chdate`,
         `a`.`deleted` AS `deleted`,
         `a`.`hidden` AS `hidden`,
         `a`.`cruser` AS `cruser`,
         `usr`.`role_user` AS `role_user`,
         `a`.`tanggal` AS `tanggal`,
         `a`.`periode_pemantauan` AS `periode_pemantauan`,
         `a`.`uid_lokasi_pemantauan` AS `uid_lokasi_pemantauan`,
         `a`.`uid_rf_peruntukan` AS `uid_rf_peruntukan`,
         `a`.`tss` AS `tss`,
         `a`.`do_p` AS `do_p`,
         `a`.`minyak_dan_lemak` AS `minyak_dan_lemak`,
         `a`.`amonia_total` AS `amonia_total`,
         `a`.`orto_fosfat` AS `orto_fosfat`,
         `a`.`shu` AS `shu`,
         `a`.`ocr_result` AS `ocr_result`,
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
         `e`.`nama` AS `peruntukan`,
         `b`.`deleted` AS `deleted_lokasi`,
         `b`.`alamat` AS `alamat`,
         `b`.`kode_lokasi` AS `kode_lokasi`,
         `b`.`alamat_detail` AS `alamat_detail`,
         `b`.`uid_rf_pelaksana` AS `uid_rf_pelaksana`,
         `b`.`latitude` AS `latitude`,
         `b`.`longitude` AS `longitude`,
         `b`.`uid_provinsi` AS `uid_provinsi`,
         `b`.`uid_kabkota` AS `uid_kabkota`,
         `c`.`kd_regional` AS `kd_regional`,
         `c`.`nama_propinsi` AS `nama_provinsi`,
         `d`.`nama_kabkot` AS `nama_kabkota`
  from (((((`pelaporan_ikal` `a`
       left join `lokasi_pemantauan` `b` on((`b`.`uid_lokasi_pemantauan` = `a`.`uid_lokasi_pemantauan`)))
       left join `rf_provinsi` `c` on((`c`.`kd_propinsi` = `b`.`uid_provinsi`)))
       left join `rf_kabkota` `d` on((`d`.`kd_kota` = `b`.`uid_kabkota`)))
       left join `rf_peruntukan` `e` on((`e`.`uid_rf_peruntukan` = `a`.`uid_rf_peruntukan`)))
       left join `users` `usr` on((`a`.`cruser` = `usr`.`uid_users`)));


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- Harus mengembalikan 1 baris:
--
-- SELECT column_name, ordinal_position
--   FROM information_schema.columns
--  WHERE table_schema = DATABASE()
--    AND table_name   = 'v_pelaporan_ikal'
--    AND column_name  = 'ocr_result';
--
-- Jumlah kolom view harus menjadi 45 (sebelumnya 44):
--
-- SELECT COUNT(*) FROM information_schema.columns
--  WHERE table_schema = DATABASE() AND table_name = 'v_pelaporan_ikal';


-- -----------------------------------------------------------------------------
-- Rollback -- definisi view PERSIS seperti sebelum migrasi ini
-- -----------------------------------------------------------------------------
-- CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`admin`@`%` SQL SECURITY DEFINER VIEW `v_pelaporan_ikal` AS
--   select `a`.`uid_pelaporan_ikal` AS `uid_pelaporan_ikal`,
--          `a`.`crdate` AS `crdate`,
--          `a`.`chdate` AS `chdate`,
--          `a`.`deleted` AS `deleted`,
--          `a`.`hidden` AS `hidden`,
--          `a`.`cruser` AS `cruser`,
--          `usr`.`role_user` AS `role_user`,
--          `a`.`tanggal` AS `tanggal`,
--          `a`.`periode_pemantauan` AS `periode_pemantauan`,
--          `a`.`uid_lokasi_pemantauan` AS `uid_lokasi_pemantauan`,
--          `a`.`uid_rf_peruntukan` AS `uid_rf_peruntukan`,
--          `a`.`tss` AS `tss`,
--          `a`.`do_p` AS `do_p`,
--          `a`.`minyak_dan_lemak` AS `minyak_dan_lemak`,
--          `a`.`amonia_total` AS `amonia_total`,
--          `a`.`orto_fosfat` AS `orto_fosfat`,
--          `a`.`shu` AS `shu`,
--          `a`.`uid_lab` AS `uid_lab`,
--          `a`.`catatan_verifikator` AS `catatan_verifikator`,
--          `a`.`catatan_provinsi` AS `catatan_provinsi`,
--          `a`.`catatan_verifikator_select` AS `catatan_verifikator_select`,
--          `a`.`catatan_provinsi_select` AS `catatan_provinsi_select`,
--          `a`.`catatan_regional` AS `catatan_regional`,
--          `a`.`catatan_regional_select` AS `catatan_regional_select`,
--          `a`.`v_provinsi` AS `v_provinsi`,
--          `a`.`v_regional` AS `v_regional`,
--          `a`.`v_pusat` AS `v_pusat`,
--          `a`.`v_reject_status` AS `v_reject_status`,
--          `a`.`v_provinsi_date` AS `v_provinsi_date`,
--          `a`.`v_regional_date` AS `v_regional_date`,
--          `a`.`v_pusat_date` AS `v_pusat_date`,
--          `e`.`nama` AS `peruntukan`,
--          `b`.`deleted` AS `deleted_lokasi`,
--          `b`.`alamat` AS `alamat`,
--          `b`.`kode_lokasi` AS `kode_lokasi`,
--          `b`.`alamat_detail` AS `alamat_detail`,
--          `b`.`uid_rf_pelaksana` AS `uid_rf_pelaksana`,
--          `b`.`latitude` AS `latitude`,
--          `b`.`longitude` AS `longitude`,
--          `b`.`uid_provinsi` AS `uid_provinsi`,
--          `b`.`uid_kabkota` AS `uid_kabkota`,
--          `c`.`kd_regional` AS `kd_regional`,
--          `c`.`nama_propinsi` AS `nama_provinsi`,
--          `d`.`nama_kabkot` AS `nama_kabkota`
--   from (((((`pelaporan_ikal` `a`
--        left join `lokasi_pemantauan` `b` on((`b`.`uid_lokasi_pemantauan` = `a`.`uid_lokasi_pemantauan`)))
--        left join `rf_provinsi` `c` on((`c`.`kd_propinsi` = `b`.`uid_provinsi`)))
--        left join `rf_kabkota` `d` on((`d`.`kd_kota` = `b`.`uid_kabkota`)))
--        left join `rf_peruntukan` `e` on((`e`.`uid_rf_peruntukan` = `a`.`uid_rf_peruntukan`)))
--        left join `users` `usr` on((`a`.`cruser` = `usr`.`uid_users`)));
