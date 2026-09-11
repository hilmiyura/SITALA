CREATE TABLE sp_rhl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jenis_rhl VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL,
    kd_kota INT NULL,
    geom POLYGON NOT NULL SRID 4326,
    SPATIAL INDEX(geom),
    INDEX idx_sp_rhl_kd_kota (kd_kota),
    CONSTRAINT fk_sp_rhl_kd_kota FOREIGN KEY (kd_kota) REFERENCES rf_kabkota (kd_kota)
) ENGINE=InnoDB;