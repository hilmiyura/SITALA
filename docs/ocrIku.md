# OCR IKU — Aturan Pencocokan ke Master Data

Dokumentasi bagaimana teks hasil pembacaan dokumen oleh model dipetakan menjadi `uid`
master data pada alur OCR pelaporan IKU.

| Field | Fungsi | Master data | Status dokumen |
|---|---|---|---|
| Lokasi Pemantauan | `matchLokasi()` :357 | `lokasi_pemantauan` | ✅ |
| Laboratorium | `matchLab()` :416 | `rf_lab` | ✅ |
| Peruntukan | `matchPeruntukan()` :401 | `rf_peruntukan` | ✅ |
| Parameter NO₂ / SO₂ / PM2.5 | `matchFieldsIku()` :155 | — tanpa master | ✅ |
| Metode per parameter | `matchMetode()` :432 | `rf_metode_pemantauan` | ✅ |
| Tanggal, koordinat, periode | `matchFieldsBase()` :139 | — diteruskan apa adanya | belum |

Semua nomor baris merujuk `application/controllers/ocrController.php`.

---

# Lokasi Pemantauan

Bagaimana teks lokasi dipetakan menjadi `uid_lokasi_pemantauan`.

**File terkait**

| Peran | Lokasi |
|---|---|
| Endpoint | `application/controllers/ocrController.php` → `ikuExtract()` :20 |
| Pencocokan lokasi | `application/controllers/ocrController.php` → `matchLokasi()` :357 |
| Pemanggil | `matchFieldsBase()` :139, dipanggil dari `matchFieldsIku()` :155 |
| Normalisasi | `normalize()` :487 |
| Instruksi ekstraksi | `application/prompts/iku.md` :16 |
| Master data | tabel `lokasi_pemantauan` |

## Ringkasan

Lokasi berasal dari **dua sumber yang dipertemukan**:

1. **Teks bebas dari model** — Gemini membaca dokumen dan mengembalikan `lokasi_text`
   apa adanya. Belum ada kaitan dengan database.
2. **Master data** — `matchLokasi()` mencari baris `lokasi_pemantauan` yang paling mirip
   dengan teks tersebut.

Keduanya dikembalikan ke frontend:

```json
"lokasi": { "uid": 1523, "text": "Jl. Ahmad Yani No. 45" }
```

`uid` dipakai untuk memilih otomatis dropdown Lokasi Pemantauan. Kalau `uid` bernilai
`null`, frontend menampilkan `text`-nya di daftar *unmatched* dan user mengisi manual.

## Sumber teks: instruksi prompt

`application/prompts/iku.md` meminta model mengisi, per lokasi:

> **lokasi_text**: nama/kode lokasi dan alamat/keterangan tempat sampling seperti tertulis
> (mis. nama jalan, kelurahan, kantor, dsb), gabungkan bagian yang relevan jika terpisah
> jadi beberapa baris.

Satu dokumen dapat memuat banyak lokasi sekaligus (`lokasi_list`), sehingga
`matchLokasi()` dipanggil sekali untuk setiap elemen.

## Alur

```
respondExtract()
  └─ per elemen lokasi_list → matchFieldsIku()
       └─ matchFieldsBase($shared, $entry, component = 1)
            └─ matchLokasi($entry['lokasi_text'], 1)
                 │
                 ├─ [0] teks kosong → langsung kembali {uid:null, text:null}
                 │
                 ├─ [1] Ambil kandidat dari lokasi_pemantauan
                 │        WHERE deleted = 0 AND uid_rf_component = 1
                 │        role_user 3 → AND uid_kabkota  = <milik user>
                 │        role_user 2 → AND uid_provinsi = <milik user>
                 │        role_user 1 → tanpa filter wilayah (seluruh Indonesia)
                 │
                 ├─ [2] TAHAP 1 — substring kode_lokasi
                 │        kode_lokasi ada di dalam teks OCR?
                 │        → skor 100, menang, loop BERHENTI
                 │
                 ├─ [3] TAHAP 2 — fuzzy similar_text()
                 │        teks OCR  vs  kode_lokasi + " " + alamat + " " + alamat_detail
                 │        simpan persentase tertinggi
                 │
                 └─ [4] Ambang: skor >= 40 → pakai uid kandidat terbaik
                                skor <  40 → uid = null
```

**Kode komponen** (`uid_rf_component`) yang menyaring kandidat:

| Nilai | Komponen |
|---|---|
| 1 | Udara ambien — **IKU** |
| 2 | Air permukaan — IKA |
| 5 | Air laut — IKAL |

Sebelum dibandingkan, kedua sisi dilewatkan `normalize()`: dijadikan huruf kecil dan
spasi berlebih dirapatkan.

## Tahap 1 — pencocokan kode lokasi

```php
if ($kode && strpos($needle, $kode) !== false) {
    $best = $row;
    $bestScore = 100;
    break;
}
```

Jalur paling andal dan langsung berhenti begitu ketemu. Namun **jarang terpicu**, karena
sertifikat laboratorium umumnya memakai penomoran sampel milik lab sendiri, bukan
`kode_lokasi` internal SITALA.

## Tahap 2 — fuzzy matching

```php
$hay = $this->normalize($row['kode_lokasi'] . " " . $row['alamat'] . " " . $row['alamat_detail']);
similar_text($needle, $hay, $pct);
```

Teks OCR dibandingkan dengan **gabungan tiga kolom**, lalu diambil skor tertinggi di
antara semua kandidat.

### Ambang 40% sering mustahil tercapai

Ini kelemahan paling penting dan paling sering disalahartikan sebagai "OCR-nya jelek".

PHP menghitung persentase `similar_text` sebagai:

```
persen = (karakter_mirip × 2) / (panjang_teks_1 + panjang_teks_2) × 100
```

Penyebutnya **menjumlahkan kedua panjang**. Karena haystack adalah gabungan tiga kolom,
ia hampir selalu jauh lebih panjang daripada teks OCR — dan itu menekan skor secara
struktural, terlepas dari seberapa tepat pembacaannya.

Batas atas skor yang mungkin dicapai:

```
plafon = panjang_teks_OCR × 2 / (panjang_teks_OCR + panjang_haystack) × 100
```

Artinya bila **haystack lebih panjang dari ±4× teks OCR, skor 40% tidak akan pernah
tercapai** sekalipun teks OCR cocok sempurna sebagai substring.

**Hasil pengukuran nyata.** Teks OCR `"jl. ahmad yani no. 45"` (21 karakter), cocok
sempurna sebagai substring di ketiga baris berikut — yang berbeda hanya panjang
`alamat_detail`:

| Isi `alamat_detail` | Panjang haystack | Skor | Plafon | Hasil |
|---|---|---|---|---|
| `Kel. Sukajadi, Kec. Batununggal, Kota Bandung` | 79 | 42,0 % | 42,0 % | diterima |
| … `, Provinsi Jawa Barat` | 100 | 34,7 % | 34,7 % | **ditolak** |
| … `, Provinsi Jawa Barat (depan kantor kecamatan)` | 125 | 28,8 % | 28,8 % | **ditolak** |

Perhatikan skor selalu **sama persis dengan plafonnya** — pembacaannya memang sempurna.
Menambahkan "Provinsi Jawa Barat" saja sudah cukup menjatuhkan pencocokan yang benar.

Konsekuensi praktis: lokasi dengan alamat panjang praktis hanya bisa terpetakan lewat
Tahap 1. Bila banyak lokasi masuk *unmatched* padahal datanya jelas ada di master,
inilah tersangka utamanya — bukan kualitas pembacaan dokumen.

## Contoh respons per kasus

Skor pada tabel di atas adalah hasil hitung sungguhan; nilai `uid` dan isi baris master
di bawah bersifat ilustratif.

### Kasus 1 — model tidak menemukan lokasi

`lokasi_text` bernilai `null`; fungsi kembali sebelum menyentuh database.

```json
"lokasi": { "uid": null, "text": null }
```

### Kasus 2 — Tahap 1, kode lokasi tercetak di dokumen

Teks OCR `"Titik IKU-3273-01, Jl. Ahmad Yani"` terhadap baris dengan
`kode_lokasi = "IKU-3273-01"` → skor 100.

```json
"lokasi": { "uid": 1523, "text": "Titik IKU-3273-01, Jl. Ahmad Yani" }
```

### Kasus 3 — Tahap 2 lolos

Teks OCR `"Jl. Sudirman"` (12 karakter) terhadap haystack pendek 19 karakter
(`kode_lokasi="IKU-02"`, `alamat="Jl. Sudirman"`, `alamat_detail` kosong) → **77,4 %**.

```json
"lokasi": { "uid": 884, "text": "Jl. Sudirman" }
```

### Kasus 4 — Tahap 2 ditolak ambang, padahal cocok

Teks OCR `"Jl. Ahmad Yani No. 45"` terhadap haystack 100 karakter → **34,7 %**.

```json
"lokasi": { "uid": null, "text": "Jl. Ahmad Yani No. 45" }
```

### Kasus 5 — tidak ada kandidat sama sekali

Query tidak mengembalikan baris — misalnya user role 3 mengunggah dokumen untuk
kabupaten/kota lain, atau komponennya keliru.

```json
"lokasi": { "uid": null, "text": "Jl. Ahmad Yani No. 45" }
```

### Ringkasan kasus

| Kasus | `uid` | `text` | Penyebab |
|---|---|---|---|
| 1 | `null` | `null` | model tidak membaca lokasi |
| 2 | terisi | terisi | kode lokasi cocok — skor 100 |
| 3 | terisi | terisi | fuzzy ≥ 40 % |
| 4 | `null` | terisi | fuzzy < 40 % meski pembacaan benar |
| 5 | `null` | terisi | tidak ada kandidat dalam lingkup user |

> **Kasus 4 dan 5 menghasilkan respons yang identik**, padahal penyebabnya berbeda jauh:
> satu soal ambang, satu lagi soal lingkup hak akses. Dari respons saja keduanya tidak
> bisa dibedakan, dan user hanya melihat field kosong.

## Perilaku di frontend

Semua hasil ber-`uid` `null` diperlakukan sama oleh
`application/views/be/parts/contents/iku/script.html`:

- masuk daftar *unmatched* dengan pesan `Lokasi Pemantauan ("<text>")`
- dropdown Lokasi Pemantauan dibiarkan kosong
- user memilih manual

## Catatan implementasi lain

**`similar_text()` peka urutan argumen.** `similar_text($a,$b)` dan `similar_text($b,$a)`
dapat menghasilkan nilai berbeda, sehingga perilakunya tidak sepenuhnya stabil.

**Seluruh baris ditarik setiap panggilan.** Tidak ada `LIMIT` maupun penyaringan awal.
Untuk user role 1 (pusat) yang tidak terkena filter wilayah, itu berarti seluruh lokasi
se-Indonesia untuk komponen tersebut, lalu di-loop dengan `similar_text` yang
berkompleksitas O(n²) per baris.

**Tidak ada sinyal keyakinan.** Yang dikembalikan hanya `uid` dan `text`. Skor 41 % dan
99 % diperlakukan identik, dan tidak ada perbandingan dengan kandidat terbaik kedua —
sehingga dua lokasi yang sama-sama mirip tidak terdeteksi sebagai ambigu.

**Arah perbaikan yang tidak memecahkan frontend.** Menambahkan field baru (mis. `score`,
`candidates`, atau `reason`) bersifat aditif: `script.html` hanya membaca `uid` dan
`text`, sehingga field tambahan diabaikan dengan aman. Ini memungkinkan membedakan
Kasus 4 dari Kasus 5 tanpa koordinasi perubahan di sisi frontend.

---

# Laboratorium

Bagaimana nama laboratorium penguji dipetakan menjadi `rf_lab.uid`.

**File terkait**

| Peran | Lokasi |
|---|---|
| Pencocokan lab | `application/controllers/ocrController.php` → `matchLab()` :416 |
| Pemanggil | `matchFieldsBase()` :139 |
| Escaping | `esc()` :492 — hanya `addslashes()` |
| Instruksi ekstraksi | `application/prompts/iku.md` :13 |
| Master data | tabel `rf_lab` — **primary key `uid`**, bukan `uid_rf_lab` |

## Ringkasan

Berbeda dari lokasi, laboratorium adalah **field level dokumen**, bukan per lokasi. Ia
diambil sekali dari `$shared` lalu dipakai untuk **seluruh** lokasi dalam satu sertifikat.

Prompt `application/prompts/iku.md` menyatakan:

> **laboratorium_text**: nama badan usaha/laboratorium penguji yang menerbitkan
> sertifikat (mis. dari kop surat), berlaku untuk seluruh dokumen, bukan per lokasi.

Bentuk responsnya sama seperti lokasi:

```json
"lab": { "uid": 4, "text": "PT. Mutuagung Lestari Cabang Pangkalan Bun" }
```

## Alur

Seluruh pencocokan terjadi **di dalam SQL**, tidak ada logika di PHP:

```php
$this->tables->set("rf_lab", "uid");
$safe = $this->esc($text);                       // addslashes()
$rows = $this->tables->fetch(
    "deleted = 0 AND (nama LIKE '%" . $safe . "%'"
  . " OR '" . $safe . "' LIKE CONCAT('%', kode, '%'))"
)['data'];
if (count($rows)) {
    $result['uid'] = $rows[0]['uid'];            // ← baris pertama, TANPA ORDER BY
}
```

Ada **dua arah pencocokan** yang di-OR:

| Arah | Kondisi | Arti |
|---|---|---|
| A | `nama LIKE '%teks%'` | teks OCR harus menjadi **substring dari nama lab** |
| B | `'teks' LIKE CONCAT('%', kode, '%')` | kode lab harus menjadi **substring dari teks OCR** |

Tidak ada penyaringan wilayah — laboratorium bersifat nasional, siapa pun boleh memakai
lab mana pun.

**Arah B praktis tidak pernah terpakai.** Data `rf_lab` memakai kode sistematis
(`LB00003`, `LB00004`, …) yang merupakan penomoran internal SITALA; sertifikat lab tidak
mencantumkannya. Ini persoalan yang sama seperti `kode_lokasi` pada Tahap 1 lokasi.
Efektifnya, hanya arah A yang bekerja.

## Perbedaan mendasar dari pencocokan lokasi

| Aspek | `matchLokasi()` | `matchLab()` |
|---|---|---|
| Tempat pencocokan | PHP | **SQL** |
| Metode | fuzzy `similar_text` | `LIKE` — substring persis |
| Toleransi salah ketik | ada | **tidak ada** |
| Ambang | 40 % | tidak ada |
| Kandidat terpilih | skor tertinggi | `$rows[0]` — **tanpa `ORDER BY`** |
| Normalisasi | `normalize()` eksplisit | mengandalkan collation DB |
| Penyaringan wilayah | ya | tidak |
| Bila gagal | `uid` `null` (terlihat) | `null` **atau uid yang salah** (senyap) |

Baris terakhir itu yang paling penting: lokasi gagal secara terbuka, laboratorium bisa
gagal secara diam-diam dengan jawaban yang terlihat meyakinkan.

## Hasil pengujian nyata

Dijalankan terhadap data produksi — **338 laboratorium aktif**. Query yang dipakai persis
seperti di kode.

| Teks OCR | Jumlah cocok | `uid` terpilih | Catatan |
|---|---|---|---|
| `PT. Mutuagung Lestari Cabang Pangkalan Bun` | 1 | 4 | ✅ tepat |
| `PT. Mutuagung Lestari` | **9** | 4 | ⚠ sembarang di antara 9 cabang |
| `PT Mutuagung Lestari` *(tanpa titik)* | 1 | **244** | ❌ **cabang Batam — lab yang salah** |
| `Mutuagung` | **10** | 4 | ⚠ sembarang |
| `PT Unilab Perdana` *(tanpa titik)* | **0** | `null` | ❌ gagal, master menulis `PT. Unilab Perdana` |
| `LB00004` | 1 | 4 | arah B, tapi tidak realistis |
| `Laboratorium Lingkungan` | **96** | 3 | ⚠ sembarang di antara 96 |

Tiga temuan dari tabel ini:

**1. Satu tanda titik menentukan segalanya.** `PT Unilab Perdana` gagal total hanya karena
master menuliskannya `PT. Unilab Perdana`. Tidak ada normalisasi tanda baca di kedua sisi.

**2. Perbedaan tanda baca bisa memilih lab yang salah, bukan sekadar gagal.**
`PT Mutuagung Lestari` tanpa titik menghasilkan tepat satu kecocokan — tetapi ke
**cabang Batam** (`uid` 244), bukan cabang yang menerbitkan dokumen. Karena hanya ada satu
hasil, tidak ada tanda bahaya apa pun: field terisi, user melihatnya wajar, dan data
tersimpan dengan laboratorium yang keliru.

**3. Teks generik menghasilkan puluhan kandidat.** `Laboratorium Lingkungan` cocok dengan
**96** baris. Yang dipakai adalah `$rows[0]` tanpa `ORDER BY`, sehingga urutannya
ditentukan MySQL dan tidak dijamin stabil — kueri yang sama bisa memberi hasil berbeda
setelah data berubah atau rencana eksekusi berganti.

## Contoh respons per kasus

Angka pada tabel di atas adalah hasil pengujian sungguhan terhadap data produksi.

### Kasus 1 — model tidak membaca nama lab

```json
"lab": { "uid": null, "text": null }
```

### Kasus 2 — cocok tepat, satu kandidat

Teks OCR `"PT. Mutuagung Lestari Cabang Pangkalan Bun"` → 1 kecocokan.

```json
"lab": { "uid": 4, "text": "PT. Mutuagung Lestari Cabang Pangkalan Bun" }
```

### Kasus 3 — banyak kandidat, dipilih sembarang

Teks OCR `"PT. Mutuagung Lestari"` → 9 kecocokan, diambil yang pertama.

```json
"lab": { "uid": 4, "text": "PT. Mutuagung Lestari" }
```

Bisa kebetulan benar, bisa juga cabang lain. Tidak ada penanda bahwa hasilnya ambigu.

### Kasus 4 — tidak ada yang cocok

Teks OCR `"PT Unilab Perdana"` → 0 kecocokan karena beda tanda baca.

```json
"lab": { "uid": null, "text": "PT Unilab Perdana" }
```

Masuk daftar *unmatched*, user mengisi manual. Ini **kegagalan yang aman** karena terlihat.

### Kasus 5 — cocok ke lab yang salah

Teks OCR `"PT Mutuagung Lestari"` → 1 kecocokan, tetapi ke cabang Batam.

```json
"lab": { "uid": 244, "text": "PT Mutuagung Lestari" }
```

**Kasus paling berbahaya di seluruh modul.** Field terisi, tidak masuk *unmatched*, tidak
ada peringatan — tetapi laboratoriumnya salah.

### Ringkasan kasus

| Kasus | `uid` | Terlihat user? | Risiko |
|---|---|---|---|
| 1 | `null` | ya, *unmatched* | aman |
| 2 | benar | — | aman |
| 3 | sembarang dari N kandidat | tidak | **sedang** |
| 4 | `null` | ya, *unmatched* | aman |
| 5 | salah | tidak | **tinggi** |

## Catatan implementasi lain

**`addslashes()` tidak menetralkan wildcard `LIKE`.** `esc()` hanya meng-escape kutip dan
backslash; karakter `%` dan `_` diteruskan apa adanya ke klausa `LIKE`, sehingga menjadi
wildcard. Teks OCR yang mengandung `%` akan memperluas pencocokan secara tak terduga.
Teks itu berasal dari dokumen yang diunggah user, jadi jalur ini dapat dipengaruhi dari
luar. Untuk MySQL, escaping yang benar adalah `mysqli_real_escape_string`, ditambah
meng-escape `%` dan `_` bila memang dipakai dalam `LIKE`.

**Risiko laten: `kode` bernilai string kosong.** Bila ada baris dengan `kode = ''`, maka
`CONCAT('%', '', '%')` menghasilkan `'%%'`, dan `'apa pun' LIKE '%%'` selalu `TRUE` —
sehingga baris tersebut cocok dengan **setiap** dokumen. Digabung dengan `$rows[0]` tanpa
`ORDER BY`, ia berpeluang selalu terpilih. Diperiksa pada data produksi: **0 baris** ber-
`kode` kosong maupun `NULL`, jadi risikonya belum aktif — tetapi tidak ada `constraint`
yang mencegahnya muncul. `kode` `NULL` aman, karena `CONCAT` menghasilkan `NULL` dan
perbandingannya tidak pernah bernilai benar.

**Case-insensitivity bergantung pada collation.** Tidak seperti `matchLokasi()` yang
memanggil `normalize()` secara eksplisit, `matchLab()` mengandalkan collation kolom di
MySQL. Perilakunya akan berubah bila collation tabel diubah.

**Arah perbaikan.** Sama seperti lokasi, penambahan field bersifat aditif dan aman
terhadap frontend. Yang paling mendesak justru murah: menambahkan `ORDER BY` yang
deterministik agar hasilnya stabil, dan menandai kondisi ambigu ketika jumlah kandidat
lebih dari satu — sehingga Kasus 3 dan 5 berhenti gagal secara diam-diam. Normalisasi
tanda baca di kedua sisi sebelum dibandingkan akan menutup penyebab paling sering dari
Kasus 4 dan 5.

---

# Peruntukan

Bagaimana jenis peruntukan lokasi dipetakan menjadi `rf_peruntukan.uid_rf_peruntukan`.

**File terkait**

| Peran | Lokasi |
|---|---|
| Pencocokan | `application/controllers/ocrController.php` → `matchPeruntukan()` :401 |
| Pemanggil | `matchFieldsIku()` :156 — dengan discriminator `1` |
| Instruksi ekstraksi | `application/prompts/iku.md` :15 |
| Master data | tabel `rf_peruntukan` — PK `uid_rf_peruntukan` |

## Ringkasan

Peruntukan adalah field **per lokasi** (berbeda dari laboratorium yang level dokumen).
Prompt memintanya sebagai:

> **per lokasi → peruntukan_text**: jenis peruntukan lokasi seperti tertulis
> (Transportasi/Industri/Pemukiman/Perkantoran/dst).

Kolom `peruntukan` pada tabel berfungsi sebagai **discriminator antar indeks**:

| Nilai | Dipakai oleh |
|---|---|
| 1 | IKU — udara ambien |
| 2 | IKAL — air laut |
| — | IKA tidak memiliki field Peruntukan |

## Alur

```php
$rows = $this->tables->fetch(
    "deleted = 0 AND peruntukan = " . (int) $discriminator
  . " AND nama LIKE '%" . $this->esc($text) . "%'"
)['data'];
if (count($rows)) {
    $result['uid'] = $rows[0]['uid_rf_peruntukan'];   // baris pertama, TANPA ORDER BY
}
```

Polanya sama seperti `matchLab()` — pencocokan di SQL, ambil `$rows[0]`, tanpa `ORDER BY`
— dengan satu perbedaan: **hanya ada satu arah pencocokan**. Tidak ada arah `kode` seperti
pada laboratorium.

Arahnya `nama LIKE '%teks%'`, artinya **teks OCR harus menjadi substring dari nama master**.

## Master data

Isi `rf_peruntukan` untuk `peruntukan = 1` pada data produksi:

| uid | nama |
|---|---|
| 14 | `TRANSPORTASI` |
| 15 | `INDUSTRI` |
| 16 | `PERKANTORAN` |
| 17 | `PEMUKIMAN` |
| 98 | `-` |

Perhatikan semuanya **kata tunggal**, dan ada satu baris placeholder `-`.

## Kelemahan struktural: nama master terlalu pendek

Karena arahnya `nama LIKE '%teks%'`, teks OCR tidak boleh lebih panjang dari nama master.
Padahal nama master di sini hanya satu kata — sehingga **setiap kata tambahan pada
dokumen langsung menggagalkan pencocokan**.

Ini kebalikan persis dari laboratorium: nama lab panjang sehingga substring sering
berhasil; nama peruntukan pendek sehingga hampir tidak ada ruang toleransi.

**Hasil pengujian nyata** terhadap data produksi:

| Teks OCR | Jumlah cocok | `uid` | Hasil |
|---|---|---|---|
| `Transportasi` | 1 | 14 | ✅ |
| `TRANSPORTASI` | 1 | 14 | ✅ — collation case-insensitive |
| `Roadside/Transportasi` | **0** | `null` | ❌ ada kata tambahan |
| `Pemukiman` | 1 | 17 | ✅ |
| `Permukiman` | **0** | `null` | ❌ **ejaan baku KBBI justru gagal** |
| `Industri` | 1 | 15 | ✅ |
| `Kawasan Industri` | **0** | `null` | ❌ ada kata tambahan |
| `Perkantoran` | 1 | 16 | ✅ |
| `Area Perkantoran` | **0** | `null` | ❌ ada kata tambahan |
| `-` | 1 | 98 | placeholder ikut cocok |

Dua hal yang menonjol:

**Ejaan baku justru ditolak.** Master menulis `PEMUKIMAN`, sementara ejaan baku KBBI
adalah "permukiman". Dokumen yang menulis dengan benar akan gagal dipetakan.

**Kata tambahan mematikan pencocokan.** Dokumen jarang menulis "Industri" telanjang;
lebih lazim "Kawasan Industri" atau "Area Industri". Semua varian itu menghasilkan `null`.

## Contoh respons per kasus

| Kasus | Kondisi | Respons |
|---|---|---|
| 1 | `peruntukan_text` `null` | `{"uid": null, "text": null}` |
| 2 | persis satu kata master | `{"uid": 15, "text": "Industri"}` |
| 3 | ada kata tambahan | `{"uid": null, "text": "Kawasan Industri"}` |
| 4 | ejaan berbeda | `{"uid": null, "text": "Permukiman"}` |

Berbeda dari laboratorium, **tidak ada kasus "cocok ke nilai yang salah"** di sini —
karena nama master saling lepas, tidak ada yang menjadi substring nama lain. Kegagalannya
selalu berupa `null`, yang berarti selalu terlihat user sebagai *unmatched*. Lebih aman,
meski lebih sering gagal.

## Arah perbaikan

Membalik arah pencocokan menjadi `'teks' LIKE CONCAT('%', nama, '%')` — yakni nama master
harus menjadi substring teks OCR — akan menyelesaikan tiga dari empat kegagalan di atas
(`Kawasan Industri`, `Area Perkantoran`, `Roadside/Transportasi`). Kasus `Permukiman`
tetap gagal karena ejaannya memang berbeda; itu butuh fuzzy matching atau perbaikan data
master.

> ⚠ **Jangan membalik arah tanpa menangani baris `-` (uid 98).** Dengan arah terbalik,
> `'teks' LIKE '%-%'` akan bernilai benar untuk **setiap** teks yang mengandung tanda
> hubung — dan alamat sering memuatnya. Digabung `$rows[0]` tanpa `ORDER BY`, baris
> placeholder itu berpeluang selalu terpilih. Kecualikan `nama = '-'` lebih dulu.

---

# Parameter NO₂, SO₂, dan PM2.5

**File terkait**

| Peran | Lokasi |
|---|---|
| Perakitan | `application/controllers/ocrController.php` → `matchFieldsIku()` :155 |
| Instruksi ekstraksi | `application/prompts/iku.md` :18 |
| Master data | **tidak ada** |

## Tidak ada pencocokan sama sekali

Berbeda dari seluruh field lain di dokumen ini, nilai parameter **tidak dicocokkan ke
apa pun**. Nama parameternya di-hardcode dan nilainya diteruskan apa adanya:

```php
$out['parameters'] = array();
foreach (array('no2', 'so2', 'pm25') as $param) {
    $p = isset($entry[$param]) && is_array($entry[$param]) ? $entry[$param] : array();
    $out['parameters'][$param] = array(
        'nilai'             => isset($p['nilai']) ? $p['nilai'] : null,
        'durasi_pemantauan' => isset($p['durasi_pemantauan']) ? $p['durasi_pemantauan'] : null,
        'metode'            => $this->matchMetode(
                                   isset($p['metode_text']) ? $p['metode_text'] : null,
                                   $shared['matrik_sampel_text']
                               ),
    );
}
```

`nilai` dan `durasi_pemantauan` berpindah langsung dari keluaran model ke respons —
**tanpa cast numerik, tanpa validasi rentang, tanpa pemeriksaan satuan**. Hanya `metode`
yang melewati proses pencocokan.

Artinya kualitas kedua field itu **sepenuhnya bergantung pada prompt**, bukan pada kode.

## Aturan yang ditegakkan lewat prompt

`application/prompts/iku.md` :18 mengatur:

- Nilai di bawah batas deteksi ditulis `<1,44` → **ambil angkanya saja**, yaitu `1.44`
- Pemisah desimal **selalu titik**, meski dokumen memakai koma
- `durasi_pemantauan` dalam satuan **hari**, hanya bila disebutkan
- Kebanyakan dokumen hanya memuat NO₂ dan SO₂; **PM2.5 sering tidak ada**. Bila suatu
  parameter tidak muncul, seluruh objeknya diisi `null` — dilarang mengarang angka

Semua aturan ini **tidak diverifikasi ulang di PHP**. Bila model mengembalikan `"24,5"`
dengan koma, nilai itu masuk apa adanya ke form.

## Perbedaan dengan IKA dan IKAL

| | IKU | IKA / IKAL |
|---|---|---|
| Bentuk keluaran model | objek bernama tetap: `no2`, `so2`, `pm25` | array bebas `parameters[]` |
| **Key pada respons** | **`parameters`** | `parameters` |
| **Isi tiap parameter** | **objek** `{nilai, durasi_pemantauan, metode}` | **skalar** — hanya nilainya |
| Pencocokan nama | tidak ada — hardcode | tabel regex `*ParameterPatterns()` |
| Jumlah parameter | 3 | IKA 55, IKAL 5 |
| Parameter tak dikenali | tidak mungkin | masuk `unmatched_parameters` |

> **Nama key sudah diseragamkan, isinya belum.** Ketiga modul kini sama-sama memakai key
> `parameters`, tetapi nilainya berbeda bentuk: IKU menyimpan objek per parameter (karena
> promptnya mengekstrak metode dan durasi per parameter), sedangkan IKA/IKAL menyimpan
> skalar. Kode yang mengiterasi `parameters` secara generik **tetap tidak bisa dipakai
> lintas modul**. Menyamakan sepenuhnya menuntut perubahan pada `prompts/ika.md` dan
> `prompts/ikal.md` agar ikut mengekstrak metode per parameter — bukan sekadar perubahan
> kode.

Konsekuensinya: **menambah parameter baru untuk IKU berbeda caranya** dari IKA. Untuk IKU
perlu menyentuh empat tempat sekaligus — array di `matchFieldsIku()`, struktur output di
`prompts/iku.md`, kolom tabel, dan input di form.

## Risiko yang perlu diperhatikan

**Bentuk keluaran yang meleset gagal secara diam-diam.** Penjaganya adalah
`is_array($entry[$param])`. Bila model mengembalikan angka langsung — misalnya
`"no2": 24.5` alih-alih `"no2": {"nilai": 24.5, ...}` — pemeriksaan itu gagal, `$p`
menjadi array kosong, dan **ketiga field jadi `null` tanpa peringatan apa pun**. Dari sisi
user, hasilnya tak terbedakan dari dokumen yang memang tidak memuat NO₂.

**Tidak ada pemeriksaan kewajaran nilai.** Salah baca titik desimal — misalnya `2450`
alih-alih `24.50` — akan diteruskan utuh dan tersimpan sebagai data pelaporan.

## Contoh respons

```json
"parameters": {
  "no2":  { "nilai": 24.5, "durasi_pemantauan": 14, "metode": { "uid": 2, "text": "Passive Sampler" } },
  "so2":  { "nilai": 18.2, "durasi_pemantauan": 14, "metode": { "uid": 2, "text": "Passive Sampler" } },
  "pm25": { "nilai": null, "durasi_pemantauan": null, "metode": { "uid": null, "text": null } }
}
```

`pm25` di atas adalah bentuk normal untuk dokumen yang memang tidak mengukur PM2.5.

Ketiga kunci `no2`, `so2`, dan `pm25` **selalu ada** selama respons berhasil dibentuk,
karena di-generate dari array tetap — berbeda dari IKA/IKAL yang hanya memuat parameter
yang benar-benar terbaca.

---

# Metode per parameter

Bagaimana teks metode pengujian dipetakan menjadi `rf_metode_pemantauan.uid_metode_pemantauan`.

**File terkait**

| Peran | Lokasi |
|---|---|
| Pencocokan | `application/controllers/ocrController.php` → `matchMetode()` :432 |
| Pemanggil | `matchFieldsIku()` :165 — **tiga kali per lokasi** |
| Instruksi ekstraksi | `application/prompts/iku.md` :14 dan :18 |
| Master data | tabel `rf_metode_pemantauan` — PK `uid_metode_pemantauan` |

## Master data

Seluruh isi tabel pada data produksi — hanya empat baris, dan **tidak disaring per
komponen**:

| uid | metode | Terjangkau kata kunci? |
|---|---|---|
| 1 | `Manual Aktif` | ya — grup *aktif* |
| 2 | `Manual Passive` | ya — grup *pasif* |
| 3 | `Otomatis (AQMS)` | ya — grup *otomatis* |
| 17 | `Satelit` | **tidak** — dipakai indeks lain |

## Alur

Tidak memakai `LIKE`, melainkan **pencocokan kata kunci dua arah di PHP**: teks dicocokkan
ke grup, lalu grup dicocokkan ke baris master.

```
matchMetode($metode_text, $shared['matrik_sampel_text'])
   │
   ├─ [0] metode_text kosong → langsung kembali {uid:null, text:null}
   │
   ├─ [1] Tentukan teks acuan
   │        metode_text mengandung salah satu kata kunci?  → pakai metode_text
   │        tidak, dan matrik_sampel_text ada?             → FALLBACK ke matrik_sampel_text
   │
   ├─ [2] Cari grup kata kunci yang ada di teks acuan (urut, yang pertama menang)
   │        grup 1: aktif, active
   │        grup 2: pasif, passive, passif
   │        grup 3: otomatis, aqms, automatic
   │
   └─ [3] Ambil baris master PERTAMA yang mengandung kata kunci grup itu
```

**Mengapa ada fallback.** Kode metode seperti `SNI 7119.2:2017` tidak menyebutkan jenis
sampler sama sekali. Karena itu prompt juga meminta `matrik_sampel_text` di level dokumen
(field "Matrik Sampel"/"Sample Matrix", mis. `Passive Sampler`, `Active Sampler`, `AQMS`),
yang dipakai sebagai cadangan ketika teks metode per-parameter tidak informatif.

## Hasil pengujian nyata

Logika direplikasi persis dan dijalankan terhadap keempat baris master:

| `metode_text` | `matrik_sampel_text` | `uid` | Jalur |
|---|---|---|---|
| `Passive Sampler` | — | 2 | kata kunci → `Manual Passive` |
| `Active Sampler` | — | 1 | kata kunci → `Manual Aktif` |
| `AQMS` | — | 3 | kata kunci → `Otomatis (AQMS)` |
| `Metode Pasif` | — | 2 | kata kunci → `Manual Passive` |
| `SNI 7119.2:2017` | `Passive Sampler` | 2 | **fallback** → `Manual Passive` |
| `SNI 7119.2:2017` | — | `null` | tanpa kata kunci, tanpa cadangan |
| `null` | `Passive Sampler` | **`null`** | ❌ **early return — fallback dilewati** |
| `Impinger` | `Impinger 24 jam` | `null` | fallback jalan, tapi tak ada grup cocok |

## Bug: fallback tidak pernah jalan saat `metode_text` kosong

Baris ketujuh pada tabel di atas adalah cacat nyata, bukan sekadar keterbatasan.

```php
if (!$text) {
    return $result;          // ← keluar SEBELUM logika fallback
}
```

Penjaga ini berada **di atas** seluruh logika fallback. Akibatnya, dokumen yang tidak
mencantumkan metode per parameter — tetapi menyatakan `Passive Sampler` dengan jelas di
data umum — tetap menghasilkan `uid` `null`.

Padahal justru situasi itulah yang hendak diselamatkan oleh mekanisme fallback. Fallback
hanya bekerja ketika `metode_text` **ada tetapi tidak informatif**, bukan ketika ia tidak
ada sama sekali.

Perbaikannya kecil: pindahkan penentuan teks acuan ke atas, dan barulah keluar lebih awal
bila `metode_text` maupun `matrik_sampel_text` sama-sama kosong.

## Catatan implementasi lain

**Dipanggil 3× per lokasi, dan setiap panggilan menarik ulang seluruh tabel.** `fetch("deleted = 0")`
dijalankan pada tiap pemanggilan, tanpa cache. Satu dokumen berisi 4 lokasi berarti **12
kali** query ke `rf_metode_pemantauan` untuk hasil yang selalu sama. Tabelnya kecil
sehingga dampaknya ringan, tetapi ini pemborosan yang mudah dihilangkan.

**Urutan grup menentukan pemenang.** Grup diperiksa berurutan: *aktif* lebih dulu, lalu
*pasif*, lalu *otomatis*. Bila teks memuat kata kunci dari dua grup berbeda, grup pertama
menang tanpa pertimbangan konteks.

**Pemilihan baris master juga "yang pertama ditemukan".** Sama seperti `matchLab()` dan
`matchPeruntukan()`, tidak ada `ORDER BY` — urutan bergantung pada MySQL.

**Tabel tidak disaring per komponen.** Baris `Satelit` (uid 17) milik indeks lain ikut
terbawa ke dalam kandidat. Saat ini tidak berbahaya karena tak ada kata kunci yang
menjangkaunya, tetapi penambahan baris baru untuk indeks lain bisa mengubah keadaan itu.

**Kata kunci dicocokkan sebagai substring, bukan kata utuh.** `strpos()` dipakai tanpa
batas kata, sehingga teks seperti "non-aktif" atau "tidak aktif" akan tercocokkan ke grup
*aktif*.
