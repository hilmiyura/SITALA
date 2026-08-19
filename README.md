# SITALA
IKLH (Indeks Kualitas Lingkungan Hidup) adalah suatu sistem atau instrumen untuk mengukur dan mengevaluasi kualitas lingkungan hidup, yang terdiri dari beberapa indeks seperti kualitas air, udara, Laut dan tutupan lahan,

## Menjalankan di local (catatan setup)

Repo ini tidak berada di bawah git (tidak ada tracking), jadi perubahan berikut dibuat langsung di working copy agar aplikasi bisa jalan di local. Dicatat di sini supaya tidak hilang/lupa:

**File yang diubah**
- `config/globalsetting.php` — `DBUSER`, `DBPASS`, `DBNAME` diarahkan ke MySQL local (`root` / db `sitala_iklh`), dan `BASEURL` diubah dari `/iklh/` menjadi `/` supaya app bisa diserve langsung dari root tanpa perlu subfolder `iklh`. **Untuk deploy production, kembalikan nilai-nilai ini ke aslinya.**

**File/folder baru yang dibuat** (tidak ada di repo aslinya, dan dibutuhkan agar app tidak fatal error):
- `temp/allowupload.php` — stub kosong. File ini di-require tanpa syarat oleh `index.php` tapi tidak disertakan di repo.
- `temp/`, `temp/tpl_compile/` — folder compile cache Smarty, harus writable.
- `uploads/` — folder tujuan upload file (`UPLOADFOLDER`).
- `application/views/fe/` — folder stub kosong. Class `View` selalu men-set folder default `"fe"` saat instansiasi sebelum controller sempat override ke `"be"`; tanpa folder ini semua request mati dengan pesan "Folder fe not found". Folder frontend publik (`fe`) yang sesungguhnya memang tidak disertakan di repo ini — hanya folder admin (`be`) yang ada.
- `assets` (symlink di root project) → `application/views/be/assets`, supaya path `/assets/...` yang dipakai template bisa diakses.
- `router.php` — router untuk PHP built-in server (`php -S`), meniru rewrite rule di `.htaccess` (yang hanya berlaku untuk Apache).

**Environment**
- Dijalankan dengan **PHP 7.4** (`brew install php@7.4`), bukan PHP 8 default sistem. Smarty versi lama yang di-bundle di `kick/view/smarty` memakai fungsi `each()` yang sudah dihapus di PHP 8, jadi fatal error di PHP 8.x.
- Database MySQL local (`sitala_iklh`) diisi dari dump `sitalakx_iklh.sql` (tidak disertakan di repo ini — file terpisah, ~1GB). Import seperti biasa: `mysql -u root sitala_iklh < sitalakx_iklh.sql`.
- Cara jalankan:
  ```
  $(brew --prefix php@7.4)/bin/php -S localhost:8000 router.php
  ```
  lalu buka `http://localhost:8000/login`.

**Known issue: error "Coloums of table `v_...` not found" setelah import dump**

Setelah import dump di atas, membuka halaman yang pakai view tertentu (misalnya pelaporan IKA/IKU/IKTL/IKAL) bisa memunculkan error:
```
Coloums of table v_pelaporan_ika not found
```
atau kalau dicek langsung di MySQL:
```
ERROR 1356 (HY000): View 'sitala_iklh.v_pelaporan_ika' references invalid table(s) or
column(s) or function(s) or definer/invoker of view lack rights to use them
```

Penyebab: semua VIEW di dump ini (ada 31, prefix `v_`) didefinisikan dengan
`DEFINER=`sitalakxrg4s`@`localhost`` — user MySQL dari server produksi asal. User itu
cuma ikut ter-dump sebagai *referensi nama* di definisi view, bukan sebagai akun
sungguhan — jadi di MySQL local, user tersebut tidak ada, dan semua view gagal diakses
(termasuk lewat `SHOW COLUMNS`, yang dipakai fungsi `_getProperties()` di banyak
controller: `ikaController`, `ikuController`, `iktlController`, `ikalController`, dkk).

Fix (jalankan setelah database di-import dari dump):
```sql
CREATE USER IF NOT EXISTS 'sitalakxrg4s'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON sitala_iklh.* TO 'sitalakxrg4s'@'localhost';
FLUSH PRIVILEGES;
```
Catatan: sekadar membuat user ini **tidak cukup**. View yang sudah kadung dibuat sebelum
user-nya ada tetap gagal di `SHOW COLUMNS`/introspeksi metadata (meski `SELECT` data
biasa sudah normal) — MySQL menyimpan status "invalid" di metadata internal view sejak
pertama dibuat, dan itu tidak otomatis pulih hanya karena user-nya belakangan dibuat.
Semua view yang sudah ada hasil import perlu di-`CREATE OR REPLACE` ulang (definisi
persis sama, tidak ada perubahan logika) supaya metadata-nya bersih:
```bash
grep -E '^CREATE ALGORITHM=UNDEFINED DEFINER=`[^`]*`@`[^`]*` SQL SECURITY DEFINER VIEW `' sitalakx_iklh.sql \
  | sed 's/^CREATE ALGORITHM/CREATE OR REPLACE ALGORITHM/' \
  | mysql -u root sitala_iklh
```
