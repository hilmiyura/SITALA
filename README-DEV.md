# SITALA — Panduan Developer

Dokumen orientasi repo SITALA: struktur, techstack, dan cara menjalankan di local.

SITALA (IKLH — Indeks Kualitas Lingkungan Hidup) adalah sistem untuk mengukur dan
mengevaluasi kualitas lingkungan hidup, terdiri dari beberapa indeks: kualitas air (IKA),
udara (IKU), air laut (IKAL), dan tutupan lahan (IKTL).

> Catatan: `README.md` berisi catatan setup awal yang lebih ringkas dan sebagian sudah
> tidak berlaku (konfigurasi DB kini lewat `.env`, bukan edit `config/globalsetting.php`).
> Dokumen ini yang jadi acuan.

---

## Daftar Isi

- [Arsitektur singkat](#arsitektur-singkat)
- [Struktur repo](#struktur-repo)
- [Techstack frontend](#techstack-frontend)
- [Techstack backend](#techstack-backend)
  - [Alur request](#alur-request)
  - [Base class controller](#base-class-controller)
  - [Data layer](#data-layer)
  - [View layer](#view-layer)
  - [Integrasi eksternal](#integrasi-eksternal)
  - [API keluar](#api-keluar)
  - [Hal yang perlu diwaspadai](#hal-yang-perlu-diwaspadai)
- [Menjalankan dengan Docker](#menjalankan-dengan-docker)
- [Menjalankan di local (tanpa Docker)](#menjalankan-di-local-tanpa-docker)
- [Service lain](#service-lain)
- [Deploy production](#deploy-production)

---

## Arsitektur singkat

SITALA **bukan** aplikasi dengan repo frontend dan backend terpisah. Ini **satu monolit
PHP** dengan server-side rendering — HTML digenerate di server oleh Smarty lalu dikirim
ke browser. Tidak ada SPA, tidak ada REST API yang dikonsumsi frontend sendiri, tidak ada
`npm install` / `composer install` untuk menjalankannya (dependency composer sudah ikut
di-commit di `libs/vendor/`).

Istilah **"fe"** dan **"be"** di repo ini merujuk ke dua **folder view**, bukan dua aplikasi:

| Folder | Arti | Status |
|---|---|---|
| `application/views/be/` | tampilan admin / backoffice | ada di repo, ini seluruh isinya |
| `application/views/fe/` | tampilan publik | **tidak ada di repo** — harus dibuat sebagai folder kosong |

Folder `fe` yang kosong tetap wajib ada: class `View` (`kick/view/View.php:16`) selalu
men-set folder default `"fe"` saat instansiasi, sebelum controller sempat override ke
`"be"`. Tanpa folder itu, semua request mati dengan pesan `Folder fe not found`.

Konsekuensinya: **FE dan BE dijalankan oleh satu proses yang sama.** Tidak ada dua
perintah run yang terpisah.

---

## Struktur repo

```
sitala/
├── index.php              ← satu-satunya entry point, semua request masuk sini
├── router.php             ← emulasi rewrite .htaccess untuk `php -S`
├── .htaccess              ← rewrite rule Apache (produksi)
├── .env.example           ← template konfigurasi (DB, BASE_URL, OpenRouter)
├── Dockerfile             ← image PHP 7.4 + Apache
├── docker-compose.yml     ← service app (+ db opsional lewat profil localdb)
├── .dockerignore          ← menjaga .env & apikeys.php tidak masuk image
├── docker/
│   ├── apache/sitala.conf ← vhost: AllowOverride All, alias /assets/, proteksi dir
│   ├── php/php.ini        ← batas upload/memori/eksekusi
│   ├── entrypoint.sh      ← bikin stub wajib saat container start
│   └── initdb/            ← dump .sql untuk service db opsional
├── config/
│   ├── globalsetting.php  ← konstanta app (DB, BASEURL, path, ACTIVE_YEAR)
│   ├── dotenv.php         ← loader .env buatan sendiri (bukan vlucas/phpdotenv)
│   └── apikeys.example.php← template kredensial OpenRouter
├── kick/                  ← FRAMEWORK (jangan disentuh untuk fitur baru)
│   ├── Kick.php           ← bootstrap + dispatcher
│   ├── front/Front.php    ← base class semua controller
│   ├── uri/Uri.php        ← parser URL → controller/action/params
│   ├── db/                ← Database.php, Adodb.php, adodb5/
│   ├── view/              ← View.php + Smarty 3.1.30
│   └── auth|session|upload|pdf|image|email|cache|format|filter/
├── libs/                  ← library pihak ketiga
│   ├── vendor/            ← PhpSpreadsheet 1.29, HTMLPurifier 4.19, zipstream 3.1
│   ├── PHPExcel/          ← legacy, masih dipakai sebagian controller
│   ├── mpdf_new/          ← mPDF 5.7
│   └── functions.php      ← helper global
└── application/           ← KODE APLIKASI (di sini kerjaannya)
    ├── controllers/       ← 68 file *Controller.php
    ├── models/            ← 10 file
    ├── prompts/           ← ika.md, iku.md, ikal.md (system prompt OCR)
    └── views/be/
        ├── index.html, login-nw-2025.html, ...
        ├── parts/block/    ← layout: head, nav, menu, modal, pagination, search
        ├── parts/contents/ ← 40 folder, satu per modul (ika/, iku/, iktl/, ikal/, ...)
        └── assets/         ← app-assets/ (tema) + assets/ (custom)
```

---

## Techstack frontend

Semua di-serve statis dari `application/views/be/assets/`, **tanpa build step**.

| Bagian | Teknologi |
|---|---|
| Template engine | Smarty 3.1.30 — file berekstensi `.html`, bukan `.tpl` |
| CSS framework | Bootstrap 4.3.1, tema admin "Chameleon" |
| JS core | jQuery 3.3.1, jQuery UI |
| Widget | select2, sweetalert2, toastr, pickadate, daterangepicker, `jquery.repeater`, inputmask |
| Peta | Leaflet + leaflet.draw + Control.Geocoder |
| Chart | amCharts 5 (5.2.33) |
| Tabel | DataTables |

`jquery.repeater` dipakai berat di form pelaporan (baris lokasi/parameter yang bisa
ditambah-kurang secara dinamis) — perhatikan ini kalau menyentuh form IKA/IKU/IKAL.

Layout dipecah jadi:
- `application/views/be/parts/block/` — potongan layout global (`block_head.html`,
  `block_nav.html`, `block_menu.html`, `block_modal.html`, `block_pagination.html`,
  `block_script.html`, dan varian `block_search_*.html` per modul)
- `application/views/be/parts/contents/{modul}/` — konten per modul, masing-masing
  umumnya berisi `index/`, `indeks/`, `verifikasi/`, dan `script.html`

**Catatan:** sebagian asset masih di-load dari CDN eksternal (unpkg, cdnjs,
momentjs.com, Google Fonts), jadi butuh koneksi internet saat runtime.

---

## Techstack backend

**Framework:** Kick Framework 3.0 — custom MVC buatan Dasendria Digital Media (2016).
Tidak ada dokumentasi publik yang bisa diandalkan; sumber kebenarannya adalah folder
`kick/` itu sendiri.

**PHP 7.4 — wajib.** Smarty versi yang di-bundle memakai fungsi `each()` yang sudah
dihapus di PHP 8, jadi fatal error di PHP 8.x.

**Database:** MySQL via ADOdb 5.11 (rilis 2010), driver `mysqli`.

### Alur request

```
Request → .htaccess / router.php → index.php
       → Kick::run() → Uri parse → new {ctrl}Controller
       → $module->init()      (selalu dipanggil kalau method-nya ada)
       → $module->{action}()
```

Routing **berbasis konvensi murni**, tidak ada route file:

```
/iku/verifikasi/uid/123/tahun/2025
  └ controller : application/controllers/ikuController.php
    └ action   : public function verifikasi()
      └ params : $this->params('uid') = 123, $this->params('tahun') = 2025
```

Params adalah pasangan key/value bergantian di segmen URL — lihat
`kick/uri/Uri.php:26-54`. Default controller `dashboard`, default action `index`.

> **Menambah endpoint baru = menambah `public function` di controller.**
> Tidak ada registrasi di mana pun. Kalau method tidak ada, Kick akan
> `die("Action ... not found")`.

Perhatikan bahwa parsing URL **bercabang berdasarkan nilai `BASEURL`**
(`_baseUrlIsRoot()` vs `_baseUrlNotIsRoot()`). Salah set `BASE_URL` bikin semua routing
meleset satu segmen.

### Base class controller

Semua controller `extends Front` (`kick/front/Front.php`). Constructor-nya
menginstansiasi service ke property: `$this->db`, `$this->session`, `$this->view`,
`$this->upload`, `$this->pdf`, `$this->format`, `$this->filter`, `$this->cache`,
`$this->encrypt`, `$this->auth`, `$this->image`, `$this->email`, `$this->message`,
`$this->debug`, `$this->uri`.

Pola `init()` yang konsisten di semua controller:

```php
public function init() {
    ($this->session->get('memberIKLH') ?: $this->redirect("login")); // auth guard
    $this->view->setFolder('be');                                    // pilih folder view
    $this->loadModel("tables");                                      // load model
    $this->models(['dbcache']);                                      // varian array
    $this->me = $this->session->get('memberIKLH');                   // user aktif
    $this->view->assign("baseUrl", BASEURL);                         // variabel ke template
}
```

**Auth:** session PHP biasa dengan key `memberIKLH`. Tidak ada middleware — guard
ditulis manual di setiap `init()`, jadi jangan lupa menyalinnya saat bikin controller baru.

`$this->me['role_user']` adalah level akses:

| Nilai | Level | Filter data |
|---|---|---|
| 1 | Pusat | tanpa filter |
| 2 | Provinsi | `uid_provinsi` |
| 3 | Kabupaten/Kota | `uid_kabkota` |

Contoh penerapannya ada di `application/controllers/ocrController.php:364-369`.

### Data layer

Tiga lapis: `Database` (`kick/db/Database.php`) → `Adodb` (`kick/db/Adodb.php`) →
ADOdb 5.11 → `mysqli`.

Model `extends Database` dan mewarisi: `fetch($where, $order, $limit)`, `query($sql)`,
`insert()`, `update()`, `updateBy()`, `delete()`, `deleteBy()`, `lastInsertID()`.

Model generik `application/models/tables.php` adalah workhorse-nya — CRUD tabel apa saja
tanpa perlu bikin model baru:

```php
$this->tables->set("lokasi_pemantauan", "uid_lokasi_pemantauan");
$rows = $this->tables->fetch("deleted = 0 AND uid_provinsi = 11")['data'];
$this->tables->post($_POST);              // insert kalau PK kosong, update kalau ada
$this->tables->softDelete($uid, $userUid);
```

**Konvensi tabel** yang harus diikuti kalau bikin tabel baru:

| Kolom | Keterangan |
|---|---|
| `uid_*` | primary key |
| `crdate` | unix timestamp integer, diisi otomatis oleh `tables->post()` saat insert |
| `chdate` | unix timestamp integer, diisi otomatis saat update |
| `chuser` | uid user yang mengubah |
| `deleted` | flag 0/1 — hampir semua query menyaring `deleted = 0` |

**Model lainnya:**

| File | Isi |
|---|---|
| `ika.php`, `iku.php`, `iktl.php` | rumus perhitungan indeks per tahun (`cnIndeks2024()`, `cnIndeks2025()`) — logika bisnis inti IKLH |
| `users.php` | auth, enkripsi/dekripsi |
| `dbcache.php` | cache hasil query |
| `ref.php` | tabel referensi, hak akses admin |
| `external.php` | captcha |
| `openrouter.php` | OCR via OpenRouter |
| `actions.php` | daftar action per kategori member |

### View layer

```php
$this->view->assign("title", 'Pelaporan IKU');   // kirim variabel ke template
$this->view->display("index.html");              // render, relatif ke application/views/be/
```

Smarty dikonfigurasi `force_compile = TRUE`, `caching = FALSE`
(`kick/view/View.php:10-14`) — template dikompilasi ulang setiap request, jadi hasil edit
langsung terlihat tanpa perlu clear cache.

Ada helper pagination: `$this->view->pagination($view, $totalRow, $offset, $limit, $urlVar, $id)`
via SmartyPaginate.

### Integrasi eksternal

**OpenRouter → Gemini 2.5 Flash** untuk OCR dokumen SHU/LHP/LHU lab:

- Model: `application/models/openrouter.php`
- Controller: `application/controllers/ocrController.php`
- System prompt: `application/prompts/{iku,ika,ikal}.md` — sengaja disimpan sebagai
  markdown terpisah supaya bisa direview/diedit tanpa menyentuh kode PHP

Alurnya: upload PDF/JPG/PNG (maks 10 MB) → base64 → OpenRouter → JSON → fuzzy-match
hasil OCR ke uid referensi di DB (`similar_text` + regex per nama parameter) → auto-fill
form. Untuk PDF, dipakai plugin `file-parser` dengan engine `native`.

Integrasi lain:
- `captcha.just4dev.id` — captcha di halaman login (`application/models/external.php`)
- Export Excel: PhpSpreadsheet 1.29 + PHPExcel legacy
- Export PDF: mPDF 5.7

### API keluar

Repo ini juga **menyediakan** REST API untuk sistem lain (bukan untuk frontend sendiri):

| Controller | Keterangan |
|---|---|
| `apiController.php` | auth via header `X-API-KEY` |
| `apiIbexController.php` | endpoint indeks IKLH untuk IBEX |
| `apiDashboardController.php` | data dashboard |
| `ajaxController.php` | request AJAX dari template sendiri |

### Hal yang perlu diwaspadai

Tiga hal yang sebaiknya diketahui sebelum mulai development backend:

**1. Query dirakit lewat konkatenasi string.** WHERE clause diterima mentah sebagai
string; tidak ada prepared statement / parameter binding di layer ini. Escaping dilakukan
ad-hoc (mis. `addslashes()` di `ocrController.php:492`). Kalau menambah fitur yang
memasukkan input user ke WHERE, ini surface SQL injection yang aktif — minimal lakukan
cast `(int)` untuk nilai numerik, seperti yang sudah dilakukan kode di sekitarnya.

**2. Banyak file duplikat bertanggal.** Contoh: `ikaController_23042025.php`,
`indeksResponController_bak.php`, `nilaiDkkController_10062025.php`. Ini snapshot versi
lama yang ditinggal di folder yang sama, bukan kode aktif — tapi karena routing berbasis
nama file, **semuanya tetap bisa diakses lewat URL**. Pastikan mengedit file yang benar
(yang tanpa suffix).

**3. Error tampil sebagai halaman putih.** `index.php:15` menyetel
`display_errors = FALSE`. Saat development, ubah ke `TRUE` atau pakai pola yang sudah ada
di beberapa controller: `ini_set("display_errors", true)` di dalam `init()`.

---

## Menjalankan dengan Docker

Cara tercepat, dan tidak menuntut PHP 7.4 terpasang di mesin kamu.

```powershell
docker compose up -d --build
```

Buka `http://localhost:8000/login`.

| Perintah | Kegunaan |
|---|---|
| `docker compose logs -f app` | log Apache + PHP secara langsung |
| `docker compose exec app bash` | shell di dalam container |
| `docker compose restart app` | restart setelah mengubah `.env` |
| `docker compose down` | hentikan |
| `docker compose up -d --build` | rebuild setelah mengubah `Dockerfile`/`docker/` |

### Isi stack

| File | Peran |
|---|---|
| `Dockerfile` | `php:7.4.33-apache-bullseye` + `mysqli`, `gd`, `zip` + `mod_rewrite` |
| `docker-compose.yml` | service `app` (port 8000) dan `db` opsional |
| `docker/apache/sitala.conf` | vhost: `AllowOverride All`, alias `/assets/`, proteksi direktori |
| `docker/php/php.ini` | batas upload/memori/eksekusi |
| `docker/entrypoint.sh` | membuat stub wajib saat start, idempoten |
| `.dockerignore` | menjaga `.env` dan `config/apikeys.php` tidak terpanggang ke image |

Semua prasyarat manual yang dijelaskan di bagian berikutnya (`temp/tpl_compile/`,
`uploads/`, `application/views/fe/`, `temp/allowupload.php`, `config/apikeys.php`)
dibuat otomatis oleh `docker/entrypoint.sh` setiap container start, jadi **tidak perlu
disiapkan sendiri**.

### Tiga hal yang perlu dipahami

**1. Masalah symlink `assets` hilang total.** Vhost memakai
`Alias /assets/ → /var/www/html/application/views/be/assets/`, jadi container tidak
bergantung pada symlink maupun junction. Ini menghapus seluruh keruwetan Windows yang
dibahas di bagian [Setup — Windows](#setup--windows).

**2. `.env` mengalahkan `environment:` di compose.** Ada dua pihak yang membaca `.env`:
Docker Compose (untuk mengisi `${...}`) dan aplikasi sendiri lewat `config/dotenv.php`,
yang memanggil `putenv()` sehingga **menimpa** environment container. Selama `.env` ikut
ter-bind-mount, isinya yang menang. Untuk pindah database, ubah `DB_SERVER` di `.env` —
bukan di `docker-compose.yml`.

Tanpa `.env` (misalnya saat image dijalankan mandiri untuk produksi), `load_env()` keluar
lebih awal dan `getenv()` jatuh ke environment container — jalur ini sudah diverifikasi
bekerja.

**3. Direktori sensitif ditolak Apache.** `config/`, `kick/`, `libs/`, `temp/`, dan
`application/{controllers,models,prompts}` dikembalikan **403**. Ini bukan hardening
teoretis: rewrite di `.htaccess` hanya meneruskan ke `index.php` kalau file-nya *tidak*
ada, sehingga file `.php` yang benar-benar ada di path itu — termasuk template hasil
kompilasi Smarty di `temp/` — akan dieksekusi Apache langsung di luar alur controller.

### Port, dan kenapa tidak ada nginx

`docker-compose.yml` mempublikasikan `${APP_PORT:-80}:80` — **default 80** supaya cocok
untuk produksi. Saat development set `APP_PORT=8000` di `.env`, karena port 80 di mesin
lokal sering sudah terpakai dan menuntut hak administrator.

**Tidak ada nginx di stack ini, dan memang tidak dibutuhkan.** Container-nya menjalankan
Apache — web server penuh yang sudah menyajikan aset statis, mengeksekusi rewrite
`.htaccess`, dan menolak direktori sensitif. Ini berbeda dari pola Node.js yang lazim
(`node` di port 5000 + nginx di depannya): di sana nginx dibutuhkan karena application
server-nya memang bukan web server. Padanan proses Node di sini adalah mod_php **di dalam**
Apache, bukan Apache itu sendiri. Menambahkan nginx berarti menumpuk dua web server untuk
pekerjaan yang sama.

### Catatan untuk deploy ke Elastic Beanstalk

Platform Docker EB (AL2/AL2023) membaca `docker-compose.yml` di root source bundle, jadi
stack ini bisa dipakai apa adanya. Lima hal yang perlu disiapkan:

**1. Nginx sudah disediakan platform.** Rantainya
`Domain → ALB → nginx milik EB di host → container Apache`. Dokumentasi AWS menyebut
Elastic Beanstalk memakai nilai *ContainerPort* untuk menyambungkan container ke *reverse
proxy running on the host*. Load balancer tidak menunjuk langsung ke container. Jangan
tambahkan nginx sendiri.

**2. Naikkan batas upload di nginx EB.** `php.ini` sudah mengizinkan 20 MB, tapi nginx
milik platform akan memotongnya lebih dulu di 1 MB (default `client_max_body_size`) dan
fitur OCR akan gagal dengan **413**. Perbaikannya lewat file di source bundle, bukan lewat
compose:

```
.platform/nginx/conf.d/upload.conf
    client_max_body_size 25M;
```

**3. Konfigurasi lewat environment properties, bukan `.env`.** `.env` sengaja tidak ikut
ke image (lihat `.dockerignore`), jadi isi `DB_*`, `BASE_URL`, dan `OPENROUTER_*` sebagai
environment properties di EB. Jalur ini sudah diverifikasi bekerja: image yang dijalankan
tanpa bind mount dan tanpa `.env` tetap melayani `/login` dengan HTTP 200.

**4. Session PHP tersimpan di disk container.** Aplikasi memakai `session_start()` dengan
handler file bawaan. Begitu environment diskalakan ke lebih dari satu instance di belakang
ALB, request user bisa mendarat di instance berbeda dan sesinya hilang — gejalanya user
ter-logout acak. Aktifkan **sticky sessions** di ALB, atau pindahkan session ke penyimpanan
bersama.

**5. `uploads/` bersifat ephemeral.** Isinya ada di filesystem container dan **hilang setiap
deploy maupun scale-in**. Untuk produksi, arahkan ke EFS yang di-mount, atau pindahkan ke
S3. Ini bukan masalah di development karena bind mount menyimpannya di host.

### Database lokal (opsional)

Secara default hanya service `app` yang jalan, memakai database yang disebut `.env`.
Untuk memakai MySQL dalam container:

```powershell
docker compose --profile localdb up -d
```

Lalu ubah `DB_SERVER` di `.env` menjadi `db` dan `docker compose restart app`. Taruh dump
`.sql` di `docker/initdb/` — MySQL menjalankannya otomatis saat volume pertama dibuat.
Port host-nya `3307` supaya tidak bentrok dengan MySQL yang mungkin sudah ada.

### Hasil verifikasi

Stack ini sudah diuji, bukan sekadar ditulis:

| Cek | Hasil |
|---|---|
| `GET /login` | HTTP 200, `<title>IKLH</title>`, tanpa error PHP |
| Asset lewat Alias | `bootstrap.css` 266 KB · `style.css` 6,4 KB — keduanya 200 |
| Direktori sensitif | `/config/globalsetting.php`, `/kick/Kick.php`, `/application/controllers/*` → 403 |
| Extension | `mysqli`, `gd`, `zip`, `curl`, `mbstring`, `openssl`, `fileinfo`, `session` |
| php.ini di SAPI Apache | `max_execution_time=300`, `upload_max_filesize=20M`, `memory_limit=512M`, TZ `Asia/Jakarta` |
| Koneksi database | OK (234 ms), MySQL 8.4.9 |
| Image mandiri tanpa bind mount & tanpa `.env` | `GET /login` HTTP 200 — konfigurasi via env vars terbukti jalan |
| Healthcheck compose | `healthy` |

---

## Menjalankan di local (tanpa Docker)

Pakai bagian ini kalau tidak memakai Docker.

FE dan BE dijalankan oleh **satu proses yang sama** — tidak ada perintah run terpisah.

### Prasyarat

| Kebutuhan | Keterangan |
|---|---|
| **PHP 7.4** | wajib, bukan PHP 8 (lihat alasan di atas) |
| **Database MySQL** | bisa **remote** (mis. RDS dev/staging) — tinggal isi `DB_SERVER` di `.env`, tidak perlu install MySQL sama sekali. Kalau mau **local**, baru perlu install MySQL + import dump |
| Dump database | hanya kalau pakai DB local: `sitalakx_iklh.sql` (~1 GB, **tidak** disertakan di repo — file terpisah) |

> **Kalau `.env` menunjuk ke database bersama (dev/staging remote), ingat bahwa app local
> kamu menulis ke data bersama itu** — hati-hati saat menguji fitur yang insert/update/delete.

Beberapa file/folder sengaja tidak di-commit (ada di `.gitignore`) tapi **dibutuhkan agar
app tidak fatal error**, jadi harus dibuat manual:

| Yang perlu dibuat | Kenapa |
|---|---|
| `.env` | konfigurasi DB, BASE_URL, API key |
| `config/apikeys.php` | di-`require_once` **tanpa syarat** di `kick/Kick.php:5` — tanpa file ini app fatal error sebelum apa pun jalan. Salin dari `config/apikeys.example.php`; isinya membaca `.env`, jadi tidak perlu diedit |
| `temp/allowupload.php` | di-`require_once` tanpa syarat di `index.php:10` |
| `temp/tpl_compile/` | target compile Smarty, harus writable |
| `uploads/` | target upload file (`UPLOADFOLDER`) |
| `application/views/fe/` | folder kosong — lihat [Arsitektur singkat](#arsitektur-singkat) |
| `assets` | symlink/junction ke `application/views/be/assets` |

### Setup — Windows

**1. Install PHP 7.4 — pakai XAMPP 7.4.33** (cara termudah, sudah terverifikasi).

XAMPP 7.4.33 membawa PHP 7.4.33 dengan `mysqli`, `curl`, `mbstring`, `gd`, `openssl`,
`zip`, `fileinfo` **sudah aktif secara default**, jadi tidak perlu mengedit `php.ini`
sama sekali.

Download dari repositori resmi Apache Friends di SourceForge — versi 7.4 tidak lagi
dipajang di halaman depan apachefriends.org karena PHP 7.4 sudah EOL, tapi tombol
download di situs resmi itu memang mengarah ke folder SourceForge yang sama:

```
https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/7.4.33/
→ xampp-windows-x64-7.4.33-0-VC15-installer.exe   (147.828.408 byte)
```

Installer resminya ditandatangani oleh `Open Source Developer, Beltran Rueda`
(`beltran@apachefriends.org`) — bisa dicek dengan `Get-AuthenticodeSignature`.

Saat instalasi:

- Dialog pembuka soal **UAC itu peringatan biasa, bukan error** — klik OK dan lanjut.
  Peringatan itu hanya relevan kalau install ke folder yang dilindungi UAC.
- Folder install: biarkan **`C:\xampp`**. Jangan ke `C:\Program Files`.
- Di *Select Components*, cukup **Apache + PHP** (keduanya wajib) dan opsional
  phpMyAdmin. Uncheck **MySQL** (kalau pakai DB remote — sekalian menghindari bentrok
  port 3306), FileZilla, Mercury, Tomcat, Perl, Webalizer, Fake Sendmail.
- Uncheck "Learn more about Bitnami for XAMPP" dan "Do you want to start the Control
  Panel now?".
- Kalau muncul prompt firewall untuk Apache, boleh **Cancel** — `php -S` hanya
  mendengarkan `localhost` dan tidak butuh izin firewall.

> Jangan ambil XAMPP 8.x dari halaman depan apachefriends.org — PHP 8 bikin Smarty
> fatal error di repo ini.

Verifikasi setelah install:

```powershell
C:\xampp\php\php.exe -v      # harus PHP 7.4.33
C:\xampp\php\php.exe -m      # cek mysqli, curl, mbstring, gd, openssl, zip, fileinfo
```

**Alternatif tanpa XAMPP:** download PHP 7.4 NTS x64 dari
`windows.php.net/downloads/releases/archives`, extract ke `C:\php74`, salin
`php.ini-development` jadi `php.ini`, lalu aktifkan sendiri extension di atas. Perhatikan
di PHP 7.4 nama extension GD adalah `gd2`, bukan `gd`.

**2. Buat folder & file stub** (PowerShell, dari root repo):

```powershell
New-Item -ItemType Directory -Force temp, temp\tpl_compile, uploads, application\views\fe
Set-Content temp\allowupload.php '<?php class allowupload { public function __construct() {} }' -Encoding utf8
Copy-Item .env.example .env
Copy-Item config\apikeys.example.php config\apikeys.php
```

**3. Perbaiki `assets`.**
Di repo ini `assets` adalah symlink Unix; di Windows ter-checkout sebagai file teks 27
byte berisi path, sehingga semua request `/assets/...` gagal dan seluruh CSS/JS mati.

Solusi paling mulus adalah **directory junction** — tidak butuh hak Administrator maupun
Developer Mode (yang butuh itu `mklink /D`, bukan `/J`):

```powershell
Remove-Item assets -Force
cmd /c mklink /J assets "application\views\be\assets"
```

Junction ini menimbulkan **dua** keriuhan di git yang perlu diredam:

**(a) `assets` terbaca sebagai "deleted"** — yang tercatat di index adalah symlink,
sedangkan di disk sekarang berupa direktori:

```powershell
git update-index --skip-worktree assets
```

Untuk membatalkannya nanti: `git update-index --no-skip-worktree assets`.

**(b) ~4.300 file untracked di bawah `assets/`** — isi folder tema sudah tracked di path
aslinya (`application/views/be/assets/...`), tapi junction membuatnya bisa dicapai lewat
path kedua (`assets/...`). Git membandingkan path secara literal, bukan inode, jadi path
kedua dianggap untracked. Tidak ada duplikasi byte di disk, hanya duplikasi nama path.

Gejalanya menyesatkan: `git status` biasa terlihat bersih (default-nya
`--untracked-files=normal`, dan path `assets` sudah dilewati karena `skip-worktree`),
tapi panel Source Control VSCode memakai `--untracked-files=all` yang menelusuri ke dalam
direktori sehingga semuanya muncul.

Redam lewat `.git/info/exclude` — ignore lokal per-clone, **tidak** ikut ter-commit,
sehingga developer macOS/Linux (yang symlink-nya normal dan tidak butuh aturan ini) tidak
terpengaruh:

```powershell
Add-Content .git\info\exclude "assets/"
```

Verifikasi dengan perintah yang sama seperti yang dipakai VSCode:

```powershell
git status --porcelain --untracked-files=all
```

> Jangan menaruh aturan ini di `.gitignore` — file itu ter-commit dan aturannya akan
> ikut ke semua developer, padahal masalahnya khas Windows.

Alternatif kalau Developer Mode kamu aktif: `git config core.symlinks true` lalu
`Remove-Item assets -Force; git checkout -- assets` akan memulihkan symlink asli, dan
`git status` bersih tanpa perlu `skip-worktree`.

**4. Isi `.env`:**

```
DB_DRIVER=mysqli
DB_SERVER=localhost
DB_USER=root
DB_PASS=
DB_NAME=sitala_iklh

BASE_URL=/

OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_OCR_MODEL=google/gemini-2.5-flash
OPENROUTER_API_URL=https://openrouter.ai/api/v1/chat/completions
```

`BASE_URL=/` supaya app bisa diserve langsung dari root tanpa subfolder. Tanpa
`OPENROUTER_API_KEY` fitur lain tetap jalan normal, hanya OCR yang mati.

**5. Import database:**

```powershell
mysql -u root -e "CREATE DATABASE sitala_iklh"
cmd /c "mysql -u root sitala_iklh < sitalakx_iklh.sql"
```

Lanjutkan dengan [fix DEFINER](#known-issue-coloums-of-table-v_-not-found) di bawah —
**wajib**, kalau tidak halaman pelaporan akan error.

**6. Jalankan** (dari root repo):

```powershell
C:\xampp\php\php.exe -S localhost:8000 router.php
```

Buka `http://localhost:8000/login`. Hentikan server dengan `Ctrl+C`.

Working directory **harus** root repo: `DOCROOT` di `config/globalsetting.php:27`
diturunkan dari `$_SERVER["DOCUMENT_ROOT"]`, yang di-set `php -S` dari cwd.

Tidak perlu membuka XAMPP Control Panel, dan tidak perlu men-start Apache maupun MySQL —
perintah di atas memakai PHP CLI bawaan XAMPP secara langsung.

**Log yang normal muncul dan boleh diabaikan:** sederet `PHP Deprecated` dari ADOdb dan
SmartyPaginate (constructor gaya PHP 4), `PHP Deprecated: The each() function is
deprecated` dari Smarty — ini persis yang jadi fatal error kalau dijalankan di PHP 8 —
serta beberapa `PHP Notice: Undefined index` dari template. Semuanya tidak menghalangi
halaman render, dan tidak terlihat di browser karena `display_errors` dimatikan.

### Setup — macOS / Linux

```bash
brew install php@7.4
mkdir -p temp/tpl_compile uploads application/views/fe
echo '<?php class allowupload { public function __construct() {} }' > temp/allowupload.php
ln -s application/views/be/assets assets
cp .env.example .env                                  # lalu edit sesuai kredensial local
cp config/apikeys.example.php config/apikeys.php      # wajib, lihat tabel di atas

mysql -u root -e "CREATE DATABASE sitala_iklh"
mysql -u root sitala_iklh < sitalakx_iklh.sql
# lalu jalankan fix DEFINER di bawah

$(brew --prefix php@7.4)/bin/php -S localhost:8000 router.php
```

### Menjalankan lewat Apache / XAMPP

`.htaccess` sudah menangani rewrite. Arahkan DocumentRoot ke folder repo, aktifkan
`mod_rewrite` dan `AllowOverride All`. `router.php` **hanya** untuk PHP built-in server
(`php -S`) — Apache tidak memakainya.

### Known issue: "Coloums of table `v_...` not found"

> **Hanya berlaku untuk DB local hasil import dump.** Pada database dev/staging remote,
> objek `v_*` sudah berbentuk *base table*, bukan VIEW (terverifikasi: `table_type='VIEW'`
> menghasilkan 0 baris, dan `SHOW COLUMNS FROM v_pelaporan_ika` mengembalikan 110 kolom),
> sehingga masalah DEFINER di bawah tidak muncul sama sekali.

Setelah import dump, membuka halaman yang memakai view tertentu (pelaporan
IKA/IKU/IKTL/IKAL) bisa memunculkan error:

```
Coloums of table v_pelaporan_ika not found
```

atau kalau dicek langsung di MySQL:

```
ERROR 1356 (HY000): View 'sitala_iklh.v_pelaporan_ika' references invalid table(s) or
column(s) or function(s) or definer/invoker of view lack rights to use them
```

**Penyebab:** semua VIEW di dump ini (ada 31, prefix `v_`) didefinisikan dengan
``DEFINER=`sitalakxrg4s`@`localhost` `` — user MySQL dari server produksi asal. User itu
hanya ikut ter-dump sebagai *referensi nama* di definisi view, bukan sebagai akun
sungguhan. Di MySQL local user tersebut tidak ada, sehingga semua view gagal diakses,
termasuk lewat `SHOW COLUMNS` yang dipakai `_getProperties()` di banyak controller
(`ikaController`, `ikuController`, `iktlController`, `ikalController`, dkk).

**Fix — langkah 1**, buat user-nya:

```sql
CREATE USER IF NOT EXISTS 'sitalakxrg4s'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON sitala_iklh.* TO 'sitalakxrg4s'@'localhost';
FLUSH PRIVILEGES;
```

**Fix — langkah 2 (wajib, langkah 1 saja tidak cukup).** View yang sudah terlanjur dibuat
sebelum user-nya ada tetap gagal di `SHOW COLUMNS`/introspeksi metadata — meski `SELECT`
data biasa sudah normal. MySQL menyimpan status "invalid" di metadata internal view sejak
pertama dibuat, dan itu tidak otomatis pulih hanya karena user-nya belakangan dibuat.
Semua view perlu di-`CREATE OR REPLACE` ulang dengan definisi persis sama (tidak ada
perubahan logika) supaya metadata-nya bersih:

```bash
grep -E '^CREATE ALGORITHM=UNDEFINED DEFINER=`[^`]*`@`[^`]*` SQL SECURITY DEFINER VIEW `' sitalakx_iklh.sql \
  | sed 's/^CREATE ALGORITHM/CREATE OR REPLACE ALGORITHM/' \
  | mysql -u root sitala_iklh
```

Di Windows, jalankan perintah ini lewat **Git Bash** (`grep`/`sed` tidak tersedia di
PowerShell maupun cmd).

---

## Service lain

**Tidak ada.** Yang berjalan hanya **satu proses PHP + MySQL**. Tidak ada Redis, queue
worker, cron job, container, atau microservice yang perlu dijalankan terpisah.

Yang ada adalah **dependensi eksternal** — bukan service yang perlu kamu jalankan:

| Dependensi | Sifat | Dampak kalau mati |
|---|---|---|
| MySQL | wajib | app tidak jalan |
| `captcha.just4dev.id` | outbound HTTPS | login bisa terganggu |
| OpenRouter API | outbound HTTPS | hanya fitur OCR yang mati |
| CDN (unpkg, cdnjs, momentjs.com, Google Fonts) | outbound | sebagian asset FE tidak load |

Selain itu ada dua sistem terkait yang **bukan** bagian dari repo ini:

- **IRLH** — konstanta `APP_IRLH` di `config/globalsetting.php:34` menunjuk ke `/irlh/`,
  aplikasi sibling di server yang sama. Hanya di-link, tidak dipanggil sebagai API.
- **IBEX** — sistem yang *mengonsumsi* API SITALA (`apiIbexController`), jadi konsumen,
  bukan dependensi.

---

## Deploy production

Konfigurasi sudah dipindah ke `.env` (tidak lagi hardcode di `globalsetting.php`), jadi
yang perlu disesuaikan saat deploy hanya isi `.env`:

- `DB_USER`, `DB_PASS`, `DB_NAME` → kredensial server produksi
- `BASE_URL` → sesuaikan dengan lokasi deploy. Produksi berjalan di subfolder,
  sehingga nilainya `/iklh/`, bukan `/`.

Selain itu pastikan di server:

- `temp/`, `temp/tpl_compile/`, dan `uploads/` ada dan writable oleh user web server
- `application/views/fe/` ada (boleh kosong)
- `assets` ter-resolve ke `application/views/be/assets`
- `mod_rewrite` aktif dan `.htaccess` terbaca (`AllowOverride All`)
- `config/apikeys.php` terisi, atau API key disuplai lewat `.env`
