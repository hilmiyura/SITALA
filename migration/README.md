# Migrasi Skema Database

Repo ini **tidak punya migration runner**. Tidak ada Phinx, Doctrine, maupun
`composer.json` di root — perubahan skema selama ini dilakukan langsung di server.

Folder ini dibuat supaya perubahan skema setidaknya **tercatat dan ter-review** di
repo, meski eksekusinya tetap manual.

## Menjalankan

```bash
mysql -h <host> -u <user> -p <database> < migration/<nama-file>.sql
```

Jalankan berurutan menurut tanggal pada nama file. Tidak ada tabel pencatat versi,
jadi **catat sendiri** migrasi mana yang sudah diterapkan pada tiap environment
(lokal, staging, produksi).

## Konvensi penamaan

```
YYYY-MM-DD_deskripsi_singkat.sql
```

Contoh: `2026-09-01_add_ocr_result_to_pelaporan_iku.sql`

## Isi tiap file

Sertakan minimal:

- **Konteks** — kenapa perubahan ini diperlukan
- **Perintah DDL**-nya
- **Query verifikasi** (dikomentari) untuk memastikan hasilnya
- **Rollback** (dikomentari)

## Catatan `.gitignore`

`.gitignore` di root memuat pola `*.sql` — untuk mengecualikan dump database yang
berukuran besar. Karena itu ada aturan negasi khusus agar file di folder ini tetap
ikut ter-commit:

```
!/migration/*.sql
```

Jangan pindahkan file migrasi ke luar folder ini, atau git akan mengabaikannya.

## Hati-hati pada database bersama

Environment pengembangan memakai database yang dipakai bersama. Sebelum menjalankan
DDL apa pun, pastikan sudah dikonfirmasi ke pengelola database.

## Masalah skema yang sudah diketahui

**Tidak ada `AUTO_INCREMENT` di seluruh tabel.** Dari 105 tabel ber-primary-key di
`sitala_iklh`, tidak satu pun kolom PK-nya `auto_increment` — hilang saat restore dari
dump. Akibatnya `INSERT` baru menyimpan `0` pada kolom PK, dan simpan kedua gagal
karena duplicate key.

Perbaikannya belum dibuatkan file migrasi karena menyangkut banyak tabel dan
memerlukan pembersihan baris ber-id `0` lebih dulu. Contoh untuk satu tabel:

```sql
DELETE FROM pelaporan_iku WHERE uid_pelaporan_iku = 0;
ALTER TABLE pelaporan_iku MODIFY uid_pelaporan_iku INT NOT NULL AUTO_INCREMENT;
ALTER TABLE pelaporan_iku AUTO_INCREMENT = 36431;
```
