-- =============================================================================
-- Menambahkan kolom ocr_result pada view v_pelaporan_ika
--
-- Konteks
--   Migrasi 2026-09-02_add_ocr_result_to_pelaporan_ika.sql sudah menambahkan kolom
--   `ocr_result` ke TABEL `pelaporan_ika`. Namun view `v_pelaporan_ika`
--   mengenumerasi kolomnya satu per satu (bukan SELECT *), dan MySQL MEMBEKUKAN
--   daftar kolom sebuah view pada saat view itu dibuat. Akibatnya kolom baru
--   tidak ikut muncul di view sampai view-nya dibuat ulang.
--
--   ocr_result dibutuhkan oleh dua jalur yang membaca view ini:
--     - ikaController::getData()   -> tabel daftar pelaporan
--     - ikaController::dataExcel() -> ekspor Excel
--   Endpoint detail ikaController::editData() TIDAK termasuk: ia membaca
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
--   Jalankan 2026-09-02_add_ocr_result_to_pelaporan_ika.sql LEBIH DULU.
--   Tanpa itu, CREATE OR REPLACE di bawah gagal dengan "Unknown column".
--
-- Cara menjalankan (manual -- tidak ada migration runner di repo ini):
--   mysql -h <host> -u <user> -p <database> < 2026-09-02_add_ocr_result_to_v_pelaporan_ika.sql
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

CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`admin`@`%` SQL SECURITY DEFINER VIEW `v_pelaporan_ika` AS
  select `a`.`uid_pelaporan_ika` AS `uid_pelaporan_ika`,
         `a`.`crdate` AS `crdate`,
         `a`.`chdate` AS `chdate`,
         `a`.`deleted` AS `deleted`,
         `a`.`hidden` AS `hidden`,
         `a`.`cruser` AS `cruser`,
         `usr`.`role_user` AS `role_user`,
         `a`.`tanggal` AS `tanggal`,
         `a`.`periode_pemantauan` AS `periode_pemantauan`,
         `a`.`uid_lokasi_pemantauan` AS `uid_lokasi_pemantauan`,
         `a`.`kategori` AS `kategori`,
         `a`.`debit` AS `debit`,
         `a`.`ph` AS `ph`,
         `a`.`bod` AS `bod`,
         `a`.`cod` AS `cod`,
         `a`.`tss` AS `tss`,
         `a`.`do_p` AS `do_p`,
         `a`.`do_max_p` AS `do_max_p`,
         `a`.`no3_n` AS `no3_n`,
         `a`.`total_phosphat` AS `total_phosphat`,
         `a`.`fecal_coliform` AS `fecal_coliform`,
         `a`.`kecerahan` AS `kecerahan`,
         `a`.`klorofil_a` AS `klorofil_a`,
         `a`.`total_nitrogen` AS `total_nitrogen`,
         `a`.`total_coliform` AS `total_coliform`,
         `a`.`temperatur_air` AS `temperatur_air`,
         `a`.`temperatur_udara` AS `temperatur_udara`,
         `a`.`minyak_lemak` AS `minyak_lemak`,
         `a`.`detergen_total` AS `detergen_total`,
         `a`.`fenol` AS `fenol`,
         `a`.`tds` AS `tds`,
         `a`.`sulfat` AS `sulfat`,
         `a`.`klorida` AS `klorida`,
         `a`.`nitrit` AS `nitrit`,
         `a`.`amoniak` AS `amoniak`,
         `a`.`florida` AS `florida`,
         `a`.`belerang_sbg_h2s` AS `belerang_sbg_h2s`,
         `a`.`sianida` AS `sianida`,
         `a`.`klorin_bebas` AS `klorin_bebas`,
         `a`.`warna` AS `warna`,
         `a`.`sampah` AS `sampah`,
         `a`.`ba` AS `ba`,
         `a`.`bo` AS `bo`,
         `a`.`hg` AS `hg`,
         `a`.`as_` AS `as_`,
         `a`.`se` AS `se`,
         `a`.`fe` AS `fe`,
         `a`.`cd` AS `cd`,
         `a`.`co` AS `co`,
         `a`.`mn` AS `mn`,
         `a`.`ni` AS `ni`,
         `a`.`zn` AS `zn`,
         `a`.`cu` AS `cu`,
         `a`.`pb` AS `pb`,
         `a`.`cr_6` AS `cr_6`,
         `a`.`aldrin` AS `aldrin`,
         `a`.`bhc` AS `bhc`,
         `a`.`chlordane` AS `chlordane`,
         `a`.`ddt` AS `ddt`,
         `a`.`endrin` AS `endrin`,
         `a`.`heptachlor` AS `heptachlor`,
         `a`.`lindane` AS `lindane`,
         `a`.`methoxychlor` AS `methoxychlor`,
         `a`.`toxapan` AS `toxapan`,
         `a`.`radioaktivitas_gross_a` AS `radioaktivitas_gross_a`,
         `a`.`radioaktivitas_gross_b` AS `radioaktivitas_gross_b`,
         `a`.`e_coli` AS `e_coli`,
         `a`.`ketinggian` AS `ketinggian`,
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
         `a`.`status_mutu_1` AS `status_mutu_1`,
         `a`.`status_mutu_2` AS `status_mutu_2`,
         `a`.`status_mutu_3` AS `status_mutu_3`,
         `a`.`status_mutu_4` AS `status_mutu_4`,
         `a`.`status_mutu_detail` AS `status_mutu_detail`,
         `a`.`status_mutu_ina` AS `status_mutu_ina`,
         `a`.`status_mutu_ina_nilai` AS `status_mutu_ina_nilai`,
         `a`.`uid_rf_bma` AS `uid_rf_bma`,
         `e`.`kelas` AS `kelas`,
         `b`.`deleted` AS `deleted_lokasi`,
         `b`.`alamat` AS `alamat`,
         `b`.`kode_lokasi` AS `kode_lokasi`,
         `b`.`alamat_detail` AS `alamat_detail`,
         `f`.`nama` AS `titik_nama_sungai`,
         `g`.`kode` AS `titik_kode_das`,
         `g`.`nama` AS `titik_nama_das`,
         `b`.`uid_rf_pelaksana` AS `uid_rf_pelaksana`,
         `h`.`name` AS `nama_pelaksana`,
         `b`.`latitude` AS `latitude`,
         `b`.`longitude` AS `longitude`,
         `b`.`uid_provinsi` AS `uid_provinsi`,
         `b`.`uid_kabkota` AS `uid_kabkota`,
         `c`.`kd_regional` AS `kd_regional`,
         `c`.`nama_propinsi` AS `nama_provinsi`,
         `d`.`nama_kabkot` AS `nama_kabkota`,
         `b`.`kelas_air_ika` AS `kelas_air_ika`,
         `b`.`segmen_ika` AS `segmen_ika`
  from ((((((((`pelaporan_ika` `a`
       left join `lokasi_pemantauan` `b` on((`a`.`uid_lokasi_pemantauan` = `b`.`uid_lokasi_pemantauan`)))
       left join `rf_provinsi` `c` on((`b`.`uid_provinsi` = `c`.`kd_propinsi`)))
       left join `rf_kabkota` `d` on((`b`.`uid_kabkota` = `d`.`kd_kota`)))
       left join `rf_bma` `e` on((`a`.`uid_rf_bma` = `e`.`uid_rf_bma`)))
       left join `users` `usr` on((`a`.`cruser` = `usr`.`uid_users`)))
       left join `rf_sungai` `f` on((`b`.`uid_sungai` = `f`.`uid`)))
       left join `rf_das` `g` on((`f`.`uid_das` = `g`.`uid`)))
       left join `rf_pelaksana` `h` on((`b`.`uid_rf_pelaksana` = `h`.`uid_rf_pelaksana`)));


-- -----------------------------------------------------------------------------
-- Verifikasi
-- -----------------------------------------------------------------------------
-- Harus mengembalikan 1 baris:
--
-- SELECT column_name, ordinal_position
--   FROM information_schema.columns
--  WHERE table_schema = DATABASE()
--    AND table_name   = 'v_pelaporan_ika'
--    AND column_name  = 'ocr_result';
--
-- Jumlah kolom view harus menjadi 111 (sebelumnya 110):
--
-- SELECT COUNT(*) FROM information_schema.columns
--  WHERE table_schema = DATABASE() AND table_name = 'v_pelaporan_ika';


-- -----------------------------------------------------------------------------
-- Rollback -- definisi view PERSIS seperti sebelum migrasi ini
-- -----------------------------------------------------------------------------
-- CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`admin`@`%` SQL SECURITY DEFINER VIEW `v_pelaporan_ika` AS
--   select `a`.`uid_pelaporan_ika` AS `uid_pelaporan_ika`,
--          `a`.`crdate` AS `crdate`,
--          `a`.`chdate` AS `chdate`,
--          `a`.`deleted` AS `deleted`,
--          `a`.`hidden` AS `hidden`,
--          `a`.`cruser` AS `cruser`,
--          `usr`.`role_user` AS `role_user`,
--          `a`.`tanggal` AS `tanggal`,
--          `a`.`periode_pemantauan` AS `periode_pemantauan`,
--          `a`.`uid_lokasi_pemantauan` AS `uid_lokasi_pemantauan`,
--          `a`.`kategori` AS `kategori`,
--          `a`.`debit` AS `debit`,
--          `a`.`ph` AS `ph`,
--          `a`.`bod` AS `bod`,
--          `a`.`cod` AS `cod`,
--          `a`.`tss` AS `tss`,
--          `a`.`do_p` AS `do_p`,
--          `a`.`do_max_p` AS `do_max_p`,
--          `a`.`no3_n` AS `no3_n`,
--          `a`.`total_phosphat` AS `total_phosphat`,
--          `a`.`fecal_coliform` AS `fecal_coliform`,
--          `a`.`kecerahan` AS `kecerahan`,
--          `a`.`klorofil_a` AS `klorofil_a`,
--          `a`.`total_nitrogen` AS `total_nitrogen`,
--          `a`.`total_coliform` AS `total_coliform`,
--          `a`.`temperatur_air` AS `temperatur_air`,
--          `a`.`temperatur_udara` AS `temperatur_udara`,
--          `a`.`minyak_lemak` AS `minyak_lemak`,
--          `a`.`detergen_total` AS `detergen_total`,
--          `a`.`fenol` AS `fenol`,
--          `a`.`tds` AS `tds`,
--          `a`.`sulfat` AS `sulfat`,
--          `a`.`klorida` AS `klorida`,
--          `a`.`nitrit` AS `nitrit`,
--          `a`.`amoniak` AS `amoniak`,
--          `a`.`florida` AS `florida`,
--          `a`.`belerang_sbg_h2s` AS `belerang_sbg_h2s`,
--          `a`.`sianida` AS `sianida`,
--          `a`.`klorin_bebas` AS `klorin_bebas`,
--          `a`.`warna` AS `warna`,
--          `a`.`sampah` AS `sampah`,
--          `a`.`ba` AS `ba`,
--          `a`.`bo` AS `bo`,
--          `a`.`hg` AS `hg`,
--          `a`.`as_` AS `as_`,
--          `a`.`se` AS `se`,
--          `a`.`fe` AS `fe`,
--          `a`.`cd` AS `cd`,
--          `a`.`co` AS `co`,
--          `a`.`mn` AS `mn`,
--          `a`.`ni` AS `ni`,
--          `a`.`zn` AS `zn`,
--          `a`.`cu` AS `cu`,
--          `a`.`pb` AS `pb`,
--          `a`.`cr_6` AS `cr_6`,
--          `a`.`aldrin` AS `aldrin`,
--          `a`.`bhc` AS `bhc`,
--          `a`.`chlordane` AS `chlordane`,
--          `a`.`ddt` AS `ddt`,
--          `a`.`endrin` AS `endrin`,
--          `a`.`heptachlor` AS `heptachlor`,
--          `a`.`lindane` AS `lindane`,
--          `a`.`methoxychlor` AS `methoxychlor`,
--          `a`.`toxapan` AS `toxapan`,
--          `a`.`radioaktivitas_gross_a` AS `radioaktivitas_gross_a`,
--          `a`.`radioaktivitas_gross_b` AS `radioaktivitas_gross_b`,
--          `a`.`e_coli` AS `e_coli`,
--          `a`.`ketinggian` AS `ketinggian`,
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
--          `a`.`status_mutu_1` AS `status_mutu_1`,
--          `a`.`status_mutu_2` AS `status_mutu_2`,
--          `a`.`status_mutu_3` AS `status_mutu_3`,
--          `a`.`status_mutu_4` AS `status_mutu_4`,
--          `a`.`status_mutu_detail` AS `status_mutu_detail`,
--          `a`.`status_mutu_ina` AS `status_mutu_ina`,
--          `a`.`status_mutu_ina_nilai` AS `status_mutu_ina_nilai`,
--          `a`.`uid_rf_bma` AS `uid_rf_bma`,
--          `e`.`kelas` AS `kelas`,
--          `b`.`deleted` AS `deleted_lokasi`,
--          `b`.`alamat` AS `alamat`,
--          `b`.`kode_lokasi` AS `kode_lokasi`,
--          `b`.`alamat_detail` AS `alamat_detail`,
--          `f`.`nama` AS `titik_nama_sungai`,
--          `g`.`kode` AS `titik_kode_das`,
--          `g`.`nama` AS `titik_nama_das`,
--          `b`.`uid_rf_pelaksana` AS `uid_rf_pelaksana`,
--          `h`.`name` AS `nama_pelaksana`,
--          `b`.`latitude` AS `latitude`,
--          `b`.`longitude` AS `longitude`,
--          `b`.`uid_provinsi` AS `uid_provinsi`,
--          `b`.`uid_kabkota` AS `uid_kabkota`,
--          `c`.`kd_regional` AS `kd_regional`,
--          `c`.`nama_propinsi` AS `nama_provinsi`,
--          `d`.`nama_kabkot` AS `nama_kabkota`,
--          `b`.`kelas_air_ika` AS `kelas_air_ika`,
--          `b`.`segmen_ika` AS `segmen_ika`
--   from ((((((((`pelaporan_ika` `a`
--        left join `lokasi_pemantauan` `b` on((`a`.`uid_lokasi_pemantauan` = `b`.`uid_lokasi_pemantauan`)))
--        left join `rf_provinsi` `c` on((`b`.`uid_provinsi` = `c`.`kd_propinsi`)))
--        left join `rf_kabkota` `d` on((`b`.`uid_kabkota` = `d`.`kd_kota`)))
--        left join `rf_bma` `e` on((`a`.`uid_rf_bma` = `e`.`uid_rf_bma`)))
--        left join `users` `usr` on((`a`.`cruser` = `usr`.`uid_users`)))
--        left join `rf_sungai` `f` on((`b`.`uid_sungai` = `f`.`uid`)))
--        left join `rf_das` `g` on((`f`.`uid_das` = `g`.`uid`)))
--        left join `rf_pelaksana` `h` on((`b`.`uid_rf_pelaksana` = `h`.`uid_rf_pelaksana`)));
