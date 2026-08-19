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
- Database MySQL local kosong (`sitala_iklh`) — **tidak ada dump/schema SQL di repo ini**, jadi tabel belum ada dan login/data tidak akan berfungsi sampai schema+data di-import.
- Cara jalankan:
  ```
  $(brew --prefix php@7.4)/bin/php -S localhost:8000 router.php
  ```
  lalu buka `http://localhost:8000/login`.
