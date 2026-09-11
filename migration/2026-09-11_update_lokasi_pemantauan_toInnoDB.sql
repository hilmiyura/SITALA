-- Convert lokasi_pemantauan dari MyISAM ke InnoDB.
-- SPATIAL INDEX dibatalkan (MySQL mewajibkan kolom index spasial NOT NULL,
-- sementara latitude/longitude di tabel ini nullable dan sebagian datanya
-- korup di luar range -90..90 / -180..180) -- lihat catatan di ocrController.
-- InnoDB tetap dipakai karena row-level lock & transaksi lebih baik untuk
-- flow OCR yang menulis banyak baris lokasi secara konkuren.
ALTER TABLE lokasi_pemantauan ENGINE=InnoDB;

-- Composite index untuk mempercepat bounding-box pre-filter sebelum haversine
-- exact di matchLokasi() (application/controllers/ocrController.php).
ALTER TABLE lokasi_pemantauan
  ADD INDEX idx_lokasi_geo_lookup (uid_rf_component, deleted, latitude, longitude);
