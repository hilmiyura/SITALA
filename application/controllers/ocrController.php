<?php
/**
 * desc : controller OCR auto-fill (Gemini Flash via OpenRouter) for pelaporan forms
 */
class ocrController extends Front
{
    public function init()
    {
        ($this -> session -> get('memberIKLH') ?: $this -> redirect("login"));

        //LOAD MODELS
        $this -> loadModel("tables");
        $this -> loadModel("openrouter");
        //utils dipakai untuk jarak antar-koordinat (distanceMeters) dan pembacaan
        //ambang dari config_parameters (configInt) — lihat mergeSameLocationEntries()
        $this -> loadModel("utils");

        //GLOBAL VAR
        $this -> me = $this -> session -> get('memberIKLH');
    }

    //OCR auto-fill for "Tambah Data IKU" form
    public function ikuExtract()
    {
        header("Content-Type: application/json; charset=UTF-8");

        $file = $this -> readUploadedFile();
        if (isset($file['error'])) {
            echo json_encode($file);
            return;
        }

        $result = $this -> openrouter -> extractIku($file['tmp_name'], $file['mime']);

        //Prompt iku.md sengaja meminta model TIDAK menggabungkan sertifikat dari lab
        //berbeda untuk lokasi yang sama — pekerjaan itu diserahkan ke sini, di mana
        //aturannya bisa diuji dan dijamin konsisten. Dijalankan pada lokasi_list MENTAH,
        //sebelum pencocokan ke master, supaya penggabungan bekerja pada koordinat dan
        //nilai apa adanya dari dokumen.
        if ($result['success'] && isset($result['data']['lokasi_list']) && is_array($result['data']['lokasi_list'])) {
            $result['data']['lokasi_list'] = $this -> mergeSameLocationEntries($result['data']['lokasi_list']);
        }

        $this -> respondExtract($result, "matchFieldsIku", "iku-ocr");
    }

    //OCR auto-fill for "Tambah Data IKA" form (uses application/prompts/ika.md — see matchFieldsIka())
    public function ikaExtract()
    {
        header("Content-Type: application/json; charset=UTF-8");

        $file = $this -> readUploadedFile();
        if (isset($file['error'])) {
            echo json_encode($file);
            return;
        }

        $result = $this -> openrouter -> extractIka($file['tmp_name'], $file['mime']);
        $this -> respondExtract($result, "matchFieldsIka", "ika-ocr");
    }

    //OCR auto-fill for "Tambah Data IKAL" form
    //NOTE: reuses the IKU prompt for now (see openrouter::extractIkal()) — see ikaExtract() note.
    public function ikalExtract()
    {
        header("Content-Type: application/json; charset=UTF-8");

        $file = $this -> readUploadedFile();
        if (isset($file['error'])) {
            echo json_encode($file);
            return;
        }

        $result = $this -> openrouter -> extractIkal($file['tmp_name'], $file['mime']);
        $this -> respondExtract($result, "matchFieldsIkal", "ikal-ocr");
    }

    //Satu hari AQMS terdiri dari 48 pembacaan, satu per interval 30 menit.
    //Dipakai sebagai pengali saat menghitung berapa data yang SEHARUSNYA ada.
    const AQMS_SLOTS_PER_DAY = 48;

    //Pelaporan IKU hanya memakai bulan 1-11. Halaman Desember, kalau ada di dokumen,
    //tidak diminta dan tidak ikut jadi penyebut persentase.
    const AQMS_MONTH_FIRST = 1;
    const AQMS_MONTH_LAST  = 11;

    //Sebuah hari dihitung VALID bila kelengkapan datanya minimal 75% -- yaitu 36 dari 48
    //pembacaan. Disimpan sebagai rasio, bukan angka 36, supaya hubungannya dengan jumlah
    //slot tetap terlihat kalau kelak alatnya berubah interval.
    const AQMS_DAY_VALID_RATIO = 0.75;

    //Anggaran waktu seluruh aksi, dalam detik. Diukur: satu panggilan halaman bulanan
    //memakan 50-60 detik, dan satu dokumen bisa berisi 22-33 halaman yang dijalankan
    //belasan sekaligus.
    const AQMS_TIME_LIMIT = 900;

    //Sisa waktu setelah panggilan terakhir: merakit respons, mencocokkan lokasi ke
    //master, dan mencatat pemakaian token.
    const AQMS_TIME_RESERVE = 30;

    /**
     * OCR auto-fill jalur laporan bulanan AQMS (bukan SHU).
     *
     * Berjalan dua langkah:
     *
     *   1. satu panggilan "rangka" (prompts/iku_aqms.md) membaca kop, footer tiap halaman
     *      bulanan, dan halaman rekap tahunan. Murah dan cepat (~21 detik, ~$0,005), dan
     *      dari sinilah diketahui bulan mana saja yang benar-benar ada -- sehingga dokumen
     *      berisi enam bulan tidak ditagih 33 panggilan;
     *   2. satu panggilan per parameter-bulan (prompts/iku_aqms_harian.md) mengambil baris
     *      ringkasan harian, dijalankan berbarengan.
     *
     * Dipecah per bulan karena batas KETELITIAN: satu panggilan berisi 11 bulan sekaligus
     * terbukti bergeser ~2 hari mulai hari ke-11 dan kehilangan min/mean/max pada hari
     * 23-30, dengan struktur JSON yang tetap tampak sehat.
     *
     * Angka resmi (hari valid, data valid, rata-rata, persentase) dihitung dari baris
     * harian. Angka footer TIDAK dipakai sebagai sumber, hanya sebagai pembanding --
     * lihat catatan akurasi di aqmsHitung().
     */
    public function ikuAqmsExtract()
    {
        header("Content-Type: application/json; charset=UTF-8");

        $mulai = microtime(TRUE);

        //Server pengembangan (`php -S`) berjalan di SAPI CLI, yang max_execution_time
        //bawaannya 0 alias TANPA BATAS. Jadi baris ini bukan menaikkan batas melainkan
        //MEMBUAT batas -- disengaja, supaya proses berhenti pada waktu yang kita tentukan
        //dan sempat melaporkan diri, bukan menggantung tanpa ujung. Diawali @ karena
        //set_time_limit ditolak di sebagian konfigurasi FastCGI.
        @set_time_limit(self::AQMS_TIME_LIMIT);

        $file = $this -> readUploadedFile();
        if (isset($file['error'])) {
            echo json_encode($file);
            return;
        }

        //Pemakaian dicatat PER TAHAP, bukan sekali di akhir. Satu dokumen bisa berarti
        //puluhan panggilan berbayar; kalau pencatatannya menunggu akhir, proses yang mati
        //di tengah jalan menghapus jejak biaya yang sudah terlanjur keluar.
        $usages = array();

        $rangka = $this -> openrouter -> extractIkuAqms($file['tmp_name'], $file['mime']);
        $usages[] = isset($rangka['usage']) ? $rangka['usage'] : null;
        $this -> logTokenUsage("ikuaqms-ocr", isset($rangka['usage']) ? $rangka['usage'] : null);

        if (!$rangka['success']) {
            echo $this -> aqmsJson(array(
                "statusCode" => 500,
                "message" => "OCR gagal dibaca: " . $rangka['error'],
                "data" => null,
                "usage" => $this -> sumUsage($usages),
            ));
            return;
        }

        $jobs = $this -> aqmsJobs($rangka['data']);
        if (!count($jobs)) {
            echo $this -> aqmsJson(array(
                "statusCode" => 500,
                "message" => "Tidak ada halaman bulanan AQMS yang terbaca dari dokumen",
                "data" => null,
                "usage" => $this -> sumUsage($usages),
            ));
            return;
        }

        //Batas MEMULAI panggilan baru. Disisakan satu TIMEOUT_HARIAN penuh supaya
        //panggilan yang berangkat paling akhir masih sempat selesai, ditambah cadangan
        //untuk merakit respons dan mencatat ke database.
        $deadline = $mulai + self::AQMS_TIME_LIMIT - openrouter::TIMEOUT_HARIAN - self::AQMS_TIME_RESERVE;

        $harian = $this -> openrouter -> extractIkuAqmsHarian(
            $file['tmp_name'], $file['mime'], $jobs, $deadline
        );

        foreach ($harian as $r) {
            $u = isset($r['usage']) ? $r['usage'] : null;
            $usages[] = $u;
            $this -> logTokenUsage("ikuaqms-ocr", $u);
        }

        $data = $this -> matchFieldsIkuAqms($rangka['data'], $harian);
        $data['durasi_detik'] = round(microtime(TRUE) - $mulai, 1);

        echo $this -> aqmsJson(array(
            "statusCode" => 200,
            "message" => "Dokumen AQMS selesai diproses",
            "data" => $data,
            "usage" => $this -> sumUsage($usages),
        ));
    }

    //Menyusun daftar panggilan harian dari hasil langkah rangka: satu pekerjaan per
    //parameter per bulan yang benar-benar ADA di dokumen.
    //
    //Kuncinya "<parameter>-<bulan>" sekaligus membuang duplikat bila model menyebut satu
    //bulan dua kali.
    private function aqmsJobs($ocr)
    {
        $jobs = array();

        foreach (array('no2', 'so2', 'pm25') as $param) {
            $p = isset($ocr[$param]) && is_array($ocr[$param]) ? $ocr[$param] : null;
            if (!$p || !isset($p['bulanan']) || !is_array($p['bulanan'])) {
                continue;
            }

            foreach ($p['bulanan'] as $b) {
                if (!is_array($b) || !isset($b['bulan'])) {
                    continue;
                }
                $bulan = (int) $b['bulan'];
                if ($bulan < self::AQMS_MONTH_FIRST || $bulan > self::AQMS_MONTH_LAST) {
                    continue;
                }
                $jobs[$param . '-' . $bulan] = array('parameter' => $param, 'bulan' => $bulan);
            }
        }

        return $jobs;
    }

    //Menjumlahkan pemakaian seluruh panggilan jadi satu angka untuk respons.
    //
    //Sisi pemanggil butuh "berapa biaya dokumen ini", bukan daftar puluhan baris.
    //generation_id sengaja TIDAK ikut: tidak ada satu id yang mewakili gabungan, dan
    //menyertakan salah satunya saja akan menyesatkan saat ditelusuri.
    private function sumUsage($usages)
    {
        $total = array(
            'panggilan' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0,
            'total_tokens' => 0, 'cached_tokens' => 0, 'reasoning_tokens' => 0,
            'cost' => 0.0, 'model' => NULL,
        );
        $ada = FALSE;

        foreach ($usages as $usage) {
            if (!is_array($usage)) {
                continue;
            }
            $ada = TRUE;
            $total['panggilan']++;

            foreach (array('prompt_tokens', 'completion_tokens', 'total_tokens', 'cached_tokens', 'reasoning_tokens') as $k) {
                if (isset($usage[$k]) && $usage[$k] !== null) {
                    $total[$k] += (int) $usage[$k];
                }
            }
            if (isset($usage['cost']) && $usage['cost'] !== null) {
                $total['cost'] += (float) $usage['cost'];
            }
            if (!$total['model'] && !empty($usage['model'])) {
                $total['model'] = $usage['model'];
            }
        }

        return $ada ? $total : NULL;
    }

    /**
     * Menserialkan respons AQMS dengan aman.
     *
     * json_encode() mengembalikan FALSE, TANPA warning apa pun, bila datanya memuat byte
     * yang bukan UTF-8 sah -- dan `echo FALSE` mencetak string kosong. Satu karakter rusak
     * dari hasil bacaan model cukup untuk membuat seluruh respons hilang tanpa jejak, yang
     * nyaris mustahil didiagnosis dari sisi pemanggil (gejalanya: "no content", tanpa
     * error di log mana pun).
     *
     * Nama stasiun hasil OCR adalah sumber paling mungkin: PDF laporan kerap memakai
     * pengodean Latin-1 untuk simbol derajat dan sejenisnya. INF/NAN memicu kegagalan yang
     * sama.
     *
     * @return string selalu string, tidak pernah FALSE
     */
    private function aqmsJson($payload)
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== FALSE) {
            return $json;
        }

        $alasan = json_last_error_msg();

        //Percobaan kedua: byte rusak diganti U+FFFD daripada seluruh respons hilang.
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if ($json !== FALSE) {
            return $json;
        }

        return '{"statusCode":500,"message":' . json_encode("Respons gagal diserialkan ke JSON: " . $alasan)
             . ',"data":null,"usage":null}';
    }

    //Merakit respons akhir: hasil perhitungan AQMS di bawah kunci "aqms", lalu field-field
    //berbentuk SAMA dengan OCR IKU biasa supaya applyOcrResult() di frontend bisa
    //memakainya tanpa perubahan.
    private function matchFieldsIkuAqms($ocr, $harian)
    {
        //Hasil panggilan harian dikelompokkan per parameter-bulan, dan yang gagal/salah
        //halaman dicatat terpisah supaya sisi pemanggil tahu hasilnya tidak utuh.
        $harianByParam = array();
        $gagal = array();
        $dilewati = array();
        $salahHalaman = array();

        foreach ($harian as $r) {
            $job = $r['job'];

            if (!$r['success']) {
                //"dilewati" berarti permintaannya tidak pernah dikirim karena anggaran
                //waktu habis: tidak ada biaya yang keluar dan mengulangnya masuk akal.
                //"gagal" berarti sudah dikirim dan sudah ditagih.
                $catatan = array(
                    'parameter' => $job['parameter'],
                    'bulan' => $job['bulan'],
                    'alasan' => $r['error'],
                );
                if (!empty($r['dilewati'])) {
                    $dilewati[] = $catatan;
                } else {
                    $gagal[] = $catatan;
                }
                continue;
            }

            //Model diminta melaporkan kop halaman yang BENAR-BENAR ia baca (kop_terbaca),
            //terpisah dari parameter/bulan yang diminta. Dokumen bisa puluhan halaman,
            //dan model terbukti kadang salah menemukan halaman yang dimaksud -- gejalanya
            //tidak terlihat dari bentuk JSON-nya (tetap sah), hanya dari ISINYA (rentang
            //nilai yang tidak masuk akal untuk parameter itu). kop_terbaca adalah satu-
            //satunya sinyal yang bisa diperiksa tanpa tahu nilai yang "benar" lebih dulu.
            $cocok = $this -> aqmsKopCocok($job, isset($r['data']['kop_terbaca']) ? $r['data']['kop_terbaca'] : null);

            if ($cocok === FALSE) {
                //TIDAK ikut dihitung. Halaman yang salah terbaca mengotori jumlah tahunan
                //tanpa jejak apa pun bila tetap diikutkan -- lebih baik bulan itu hilang
                //dari total (dan terlihat sebagai kelengkapan berkurang) daripada mengotori
                //bulan lain yang sebenarnya benar.
                $salahHalaman[] = array(
                    'parameter' => $job['parameter'],
                    'bulan' => $job['bulan'],
                    'kop_terbaca' => isset($r['data']['kop_terbaca']) ? $r['data']['kop_terbaca'] : null,
                );
                continue;
            }

            $harianByParam[$job['parameter']][$job['bulan']] = $r['data'];
        }

        $out = array('aqms' => array(), 'parameters' => array());
        $labels = array();

        foreach (array('no2', 'so2', 'pm25') as $param) {
            $p = isset($ocr[$param]) && is_array($ocr[$param]) ? $ocr[$param] : null;
            if (!$p) {
                $out['aqms'][$param] = null;
                $out['parameters'][$param] = null;
                continue;
            }

            $tahun  = (isset($p['tahun']) && $p['tahun']) ? (int) $p['tahun'] : null;
            $rekap  = (isset($p['rekap_tahunan']) && is_array($p['rekap_tahunan'])) ? $p['rekap_tahunan'] : null;
            $hitung = $this -> aqmsHitung(
                isset($harianByParam[$param]) ? $harianByParam[$param] : array(),
                isset($p['bulanan']) && is_array($p['bulanan']) ? $p['bulanan'] : array(),
                $rekap,
                $tahun
            );

            $out['aqms'][$param] = array(
                'lokasi_text' => isset($p['lokasi_text']) ? $p['lokasi_text'] : null,
                'tahun' => $tahun,
                'bulanan' => $hitung['bulanan'],
                'ringkasan' => $hitung['ringkasan'],
                'ringkasan_sumber' => $hitung['ringkasan_sumber'],
                //Angka yang TERCETAK di halaman rekap, apa adanya. Disertakan sebagai
                //pembanding, bukan sebagai sumber.
                'rekap_dokumen' => $rekap,
                'integritas' => $hitung['integritas'],
                'dasar' => $hitung['dasar'],
            );

            $out['parameters'][$param] = array(
                'nilai' => $hitung['ringkasan']['rata_rata'],
                //AQMS mengukur kontinu sepanjang tahun; tidak ada "durasi pemantauan"
                //dalam pengertian SHU (lama satu kali pengambilan sampel).
                'durasi_pemantauan' => null,
                //Dicari lewat matchMetode(), bukan uid 3 yang ditulis langsung, supaya
                //tetap benar bila isi rf_metode_pemantauan berubah.
                'metode' => $this -> matchMetode('otomatis'),
            );

            if (!empty($p['lokasi_text'])) {
                $labels[$param] = $p['lokasi_text'];
            }
        }

        //Satu dokumen AQMS = satu stasiun, jadi tidak ada lokasi_list dan tidak ada mode
        //multi seperti jalur SHU. Label pertama yang terisi dipakai untuk pencocokan.
        $primaryLabel = null;
        foreach (array('no2', 'so2', 'pm25') as $param) {
            if (!empty($labels[$param])) {
                $primaryLabel = $labels[$param];
                break;
            }
        }

        $out['lokasi'] = $this -> matchLokasi($primaryLabel, 1);
        $out['labels'] = $labels;

        //Field sisanya dikirim kosong supaya bentuk respons tetap sama dengan OCR IKU
        //biasa. Dokumen AQMS memang tidak memuat laboratorium maupun peruntukan, dan
        //tanggalnya sengaja TIDAK ditebak: laporan ini mencakup satu tahun sedangkan form
        //meminta satu tanggal pemantauan. Membiarkannya kosong memaksa operator mengisi
        //secara sadar, alih-alih menerima tebakan yang tampak meyakinkan.
        $out['peruntukan'] = array('uid' => null, 'text' => null);
        $out['lab'] = array();
        $out['tanggal'] = null;
        $out['periode_pemantauan'] = null;
        $out['label'] = $primaryLabel;

        //Tiga alasan berbeda hasilnya tidak utuh, dilaporkan terpisah karena tindak
        //lanjutnya berbeda: gagal/dilewati layak diulang, salah_halaman butuh perbaikan
        //cara memilih halaman (di luar cakupan sekarang -- lihat catatan di
        //aqmsKopCocok()) sebelum diulang dengan cara yang sama.
        $out['gagal'] = $gagal;
        $out['dilewati'] = $dilewati;
        $out['salah_halaman'] = $salahHalaman;
        $out['utuh'] = (count($gagal) === 0 && count($dilewati) === 0 && count($salahHalaman) === 0);

        return $out;
    }

    /**
     * Memeriksa apakah kop halaman yang BENAR-BENAR dibaca model (kop_terbaca) cocok
     * dengan parameter-bulan yang diminta.
     *
     * Ada karena diagnosis nyata: pemanggilan satu-halaman-per-permintaan (perlu, sebab
     * satu panggilan berisi 11 bulan sekaligus terbukti bergeser dan kehilangan data --
     * lihat aqmsHitung()) TERNYATA membuat model kadang salah menemukan halaman yang
     * dimaksud di antara puluhan halaman dokumen. Gejalanya tidak terlihat dari BENTUK
     * JSON-nya -- tetap valid, tetap 31 hari, tetap ada mean/min/max -- hanya dari ISINYA:
     * pada dokumen uji, NO2 bulan Maret mengembalikan rentang nilai ~20 (mestinya ~4-8),
     * persis rentang SO2. Memeriksa NILAI memerlukan tahu jawaban yang benar lebih dulu;
     * memeriksa KOP yang dilaporkan model tidak.
     *
     * Ini pemeriksaan STRUKTURAL, bukan perbaikan akurasi pembacaan halaman itu sendiri --
     * itu di luar cakupan saat ini. Yang dikerjakan di sini hanya mencegah halaman yang
     * terbukti salah ikut mengotori total tahunan tanpa jejak.
     *
     * @return bool|null TRUE cocok, FALSE tidak cocok, NULL tidak bisa diperiksa (model
     *                    tidak melaporkan kop_terbaca -- respons lama/tidak lengkap)
     */
    private function aqmsKopCocok($job, $kopTerbaca)
    {
        if (!is_array($kopTerbaca) || empty($kopTerbaca['parameter_teks'])) {
            return NULL;
        }

        //Sinonim yang sama dipakai matchMetode()-adjacent di modul lain: parameter bisa
        //ditulis dengan subscript unicode, koma sebagai pemisah desimal, atau embel-embel
        //"Dioksida"/"Dioxide". Dinormalisasi ke bentuk polos sebelum dibandingkan.
        $sinonim = array(
            'no2' => array('no2', 'no₂', 'nitrogendioksida', 'nitrogendioxide'),
            'so2' => array('so2', 'so₂', 'sulfurdioksida', 'sulfurdioxide'),
            'pm25' => array('pm25', 'pm2.5', 'pm2,5', 'pm₂.₅', 'pm₂,₅'),
        );

        $bersih = strtolower(preg_replace('/[^a-z0-9,.₂₅]/i', '', (string) $kopTerbaca['parameter_teks']));
        $bersih = str_replace(array(',', '.'), '', $bersih);

        $diminta = isset($sinonim[$job['parameter']]) ? $sinonim[$job['parameter']] : array($job['parameter']);
        foreach ($diminta as $s) {
            if (str_replace(array(',', '.'), '', $s) === $bersih) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Menghitung ringkasan tahunan satu parameter DARI BARIS HARIAN.
     *
     *   hari valid  -> hari dengan kelengkapan >= 75% (>= 36 dari 48 pembacaan)
     *   data valid  -> jumlah seluruh pembacaan sah
     *   rata-rata   -> SUM(mean hari x cacah pembacaan hari) / SUM(cacah) -- rata-rata
     *                  tertimbang, yang secara matematis sama dengan "jumlah semua data
     *                  valid dibagi banyaknya data valid"
     *   persentase  -> terhadap KALENDER bulan 1-11, bukan terhadap bulan yang kebetulan
     *                  ada di dokumen
     *
     * Penyebut kalender disengaja: dokumen yang kehilangan halaman Maret harus terlihat
     * sebagai kelengkapan yang berkurang, bukan tetap 100% karena Maret ikut hilang dari
     * pembaginya.
     *
     * CATATAN AKURASI YANG PENTING
     * Angka footer ("Hari Valid"/"Data Valid" di tiap halaman) TIDAK dipakai sebagai
     * sumber, hanya sebagai pembanding di 'integritas'. Pengukuran pada dokumen uji
     * menunjukkan keduanya bisa berbeda: pada Juni, turunan harian memberi 27 hari valid
     * sedangkan footer mencetak 22 -- sementara jumlah seluruh footer terbukti cocok
     * sampai digit terakhir dengan rekap tahunan dokumen. Jadi bila 'integritas' menandai
     * selisih, yang lebih mungkin keliru adalah pembacaan barisan hariannya. Selisih itu
     * sengaja DITAMPILKAN, bukan disembunyikan atau dikoreksi diam-diam.
     *
     * @param  array $harianByMonth dipetakan bulan => hasil panggilan harian bulan itu
     * @param  array $footer daftar {bulan, hari_valid, data_valid} dari langkah rangka
     * @param  array|null $rekap angka rekap tahunan yang tercetak
     * @param  int|null $tahun dibutuhkan untuk menentukan panjang Februari
     * @return array
     */
    private function aqmsHitung($harianByMonth, $footer, $rekap, $tahun)
    {
        //Footer diindeks per bulan supaya bisa dipasangkan sebagai pembanding.
        $footerByMonth = array();
        foreach ($footer as $b) {
            if (is_array($b) && isset($b['bulan'])) {
                $footerByMonth[(int) $b['bulan']] = $b;
            }
        }

        $ambang = (int) ceil(self::AQMS_SLOTS_PER_DAY * self::AQMS_DAY_VALID_RATIO);

        $bulanan        = array();
        $totalHariValid = 0;
        $totalDataValid = 0;
        $totalNilai     = 0.0;
        $totalBobot     = 0;

        for ($bulan = self::AQMS_MONTH_FIRST; $bulan <= self::AQMS_MONTH_LAST; $bulan++) {
            if (!isset($harianByMonth[$bulan])) {
                continue;
            }

            $isi    = $harianByMonth[$bulan];
            $harian = (isset($isi['harian']) && is_array($isi['harian'])) ? $isi['harian'] : array();
            if (!count($harian)) {
                continue;
            }

            $hariDalamBulan = $this -> aqmsJumlahHari($tahun, $bulan);
            $persen = $this -> aqmsSatuanPersen($isi, $harian);

            $hariValid = 0;
            $dataValid = 0;
            $sudah     = array();

            foreach ($harian as $h) {
                if (!is_array($h) || !isset($h['hari'])) {
                    continue;
                }
                $hari = (int) $h['hari'];

                //KALENDER yang menentukan hari mana yang dihitung, bukan jumlah kolom di
                //tabel. Dokumen bisa menggambar 31 kolom untuk semua bulan (tanggal yang
                //tidak ada dikosongkan dengan Jumlah Data 0), bisa juga sepanjang bulan
                //saja. Satu aturan ini benar untuk keduanya tanpa perlu mendeteksi
                //bentuknya, dan tetap membuang nilai bukan-nol yang salah tercetak di
                //kolom yang seharusnya tidak ada.
                if ($hari < 1 || $hari > $hariDalamBulan || isset($sudah[$hari])) {
                    continue;
                }
                $sudah[$hari] = TRUE;

                //jumlah_data 0 adalah nilai SAH yang berarti tidak ada data hari itu --
                //bukan penanda rusak. Hari seperti itu bercacah 0, tidak valid, dan tidak
                //menyumbang bobot apa pun ke rata-rata.
                $jd = (isset($h['jumlah_data']) && is_numeric($h['jumlah_data'])) ? (float) $h['jumlah_data'] : 0;
                $n  = $persen
                    ? (int) round($jd / 100 * self::AQMS_SLOTS_PER_DAY)
                    : (int) round($jd);
                $n = max(0, min(self::AQMS_SLOTS_PER_DAY, $n));

                $dataValid += $n;
                if ($n >= $ambang) {
                    $hariValid++;
                }
                if ($n > 0 && isset($h['mean']) && is_numeric($h['mean'])) {
                    $totalNilai += (float) $h['mean'] * $n;
                    $totalBobot += $n;
                }
            }

            $f = isset($footerByMonth[$bulan]) ? $footerByMonth[$bulan] : null;

            $bulanan[] = array(
                'bulan' => $bulan,
                'hari_valid' => $hariValid,
                'data_valid' => $dataValid,
                'hari_dalam_bulan' => $hariDalamBulan,
                'hari_terbaca' => count($sudah),
                'jumlah_data_satuan' => $persen ? 'persen' : 'cacah',
                //Angka footer halaman itu, apa adanya, untuk dibandingkan.
                'footer_dokumen' => $f ? array(
                    'hari_valid' => (isset($f['hari_valid']) && is_numeric($f['hari_valid'])) ? (int) $f['hari_valid'] : NULL,
                    'data_valid' => (isset($f['data_valid']) && is_numeric($f['data_valid'])) ? (int) $f['data_valid'] : NULL,
                ) : NULL,
                'selisih_footer' => $f ? array(
                    'hari_valid' => (isset($f['hari_valid']) && is_numeric($f['hari_valid'])) ? $hariValid - (int) $f['hari_valid'] : NULL,
                    'data_valid' => (isset($f['data_valid']) && is_numeric($f['data_valid'])) ? $dataValid - (int) $f['data_valid'] : NULL,
                ) : NULL,
                'harian' => $harian,
            );

            $totalHariValid += $hariValid;
            $totalDataValid += $dataValid;
        }

        $hariSeharusnya = $this -> aqmsHariSeharusnya($tahun);
        $dataSeharusnya = $hariSeharusnya * self::AQMS_SLOTS_PER_DAY;

        return array(
            'bulanan' => $bulanan,
            'ringkasan' => array(
                'jumlah_hari_valid' => $totalHariValid,
                'jumlah_data_valid' => $totalDataValid,
                //null, bukan 0, bila tidak ada satu pun data: "tidak ada bacaan" berbeda
                //dari "rata-ratanya nol".
                'rata_rata' => $totalBobot ? round($totalNilai / $totalBobot, 2) : NULL,
                'persentase_data' => $dataSeharusnya ? round($totalDataValid / $dataSeharusnya * 100, 2) : NULL,
                'persentase_hari' => $hariSeharusnya ? round($totalHariValid / $hariSeharusnya * 100, 2) : NULL,
            ),
            'ringkasan_sumber' => array(
                'jumlah_hari_valid' => 'dihitung dari baris harian (kelengkapan >= 75%)',
                'jumlah_data_valid' => 'dihitung dari baris harian',
                'rata_rata' => 'dihitung dari baris harian (rata-rata tertimbang)',
                'persentase_data' => 'dihitung terhadap kalender bulan 1-11',
                'persentase_hari' => 'dihitung terhadap kalender bulan 1-11',
            ),
            //Uji integritas: hasil hitung dibandingkan terhadap jumlah footer bulanan DAN
            //terhadap rekap tahunan tercetak. Ketiganya dicetak/diturunkan dari tempat
            //berbeda, jadi kecocokannya bukti kuat dan ketidakcocokannya menandai halaman
            //yang tertukar, terlewat, atau salah baca.
            'integritas' => $this -> aqmsIntegritas(
                array('jumlah_hari_valid' => $totalHariValid, 'jumlah_data_valid' => $totalDataValid),
                $footerByMonth,
                $rekap
            ),
            //Penyebut yang dipakai, disertakan supaya persentase di atas bisa ditelusuri
            //tanpa harus tahu aturannya lebih dulu.
            'dasar' => array(
                'bulan_terbaca' => count($bulanan),
                'hari_seharusnya' => $hariSeharusnya,
                'data_seharusnya' => $dataSeharusnya,
                'slot_per_hari' => self::AQMS_SLOTS_PER_DAY,
                'ambang_hari_valid' => $ambang,
            ),
        );
    }

    /**
     * Menentukan apakah kolom "Jumlah Data" berisi persentase (0-100) atau cacah (0-48).
     *
     * Pernyataan model dipakai lebih dulu bila ada. Bila tidak, disimpulkan dari data:
     * nilai di atas 48 hanya mungkin bila satuannya persen.
     *
     * Bila seluruh nilainya <= 48 dan model tidak menyatakan apa-apa, dianggap CACAH.
     * Itu tafsir yang lebih aman: menganggapnya persen akan mengubah, misalnya, 40 (dari
     * 48 pembacaan, sudah valid) menjadi 40% (19 pembacaan, tidak valid) -- membuang hari
     * yang sebenarnya sah.
     */
    private function aqmsSatuanPersen($isi, $harian)
    {
        if (isset($isi['jumlah_data_satuan'])) {
            $s = strtolower(trim((string) $isi['jumlah_data_satuan']));
            if ($s === 'persen') { return TRUE; }
            if ($s === 'cacah')  { return FALSE; }
        }

        foreach ($harian as $h) {
            if (is_array($h) && isset($h['jumlah_data']) && is_numeric($h['jumlah_data'])
                && (float) $h['jumlah_data'] > self::AQMS_SLOTS_PER_DAY) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Membandingkan hasil hitung terhadap DUA sumber tercetak yang berbeda:
     * jumlah footer bulanan, dan rekap tahunan.
     *
     * Ketiganya berasal dari tempat berbeda di dokumen yang sama. Bila hitungan kita
     * cocok dengan keduanya, hampir pasti benar. Bila hitungan kita menyimpang sementara
     * kedua sumber tercetak itu saling cocok, yang keliru kemungkinan besar pembacaan
     * baris hariannya -- itulah pola yang teramati pada dokumen uji.
     *
     * 'cocok' bernilai null bila sisi pembandingnya tidak ada. "Tidak bisa dibandingkan"
     * sengaja tidak disamarkan jadi "cocok".
     */
    private function aqmsIntegritas($jumlah, $footerByMonth, $rekap)
    {
        //Jumlahkan footer bulanan sebagai sumber pembanding pertama.
        $footerTotal = array('jumlah_hari_valid' => 0, 'jumlah_data_valid' => 0);
        $adaFooter = FALSE;
        foreach ($footerByMonth as $bulan => $f) {
            if ($bulan < self::AQMS_MONTH_FIRST || $bulan > self::AQMS_MONTH_LAST) {
                continue;
            }
            if (isset($f['hari_valid']) && is_numeric($f['hari_valid'])) {
                $footerTotal['jumlah_hari_valid'] += (int) $f['hari_valid'];
                $adaFooter = TRUE;
            }
            if (isset($f['data_valid']) && is_numeric($f['data_valid'])) {
                $footerTotal['jumlah_data_valid'] += (int) $f['data_valid'];
                $adaFooter = TRUE;
            }
        }

        $out = array('cocok' => NULL);
        $semua = array();

        foreach (array('jumlah_hari_valid', 'jumlah_data_valid') as $k) {
            $a  = $jumlah[$k];
            $bf = $adaFooter ? (float) $footerTotal[$k] : NULL;
            $br = (isset($rekap[$k]) && is_numeric($rekap[$k])) ? (float) $rekap[$k] : NULL;

            $cocokFooter = ($bf === NULL) ? NULL : (abs($a - $bf) < 0.5);
            $cocokRekap  = ($br === NULL) ? NULL : (abs($a - $br) < 0.5);

            $out[$k] = array(
                'hitung' => $a,
                'jumlah_footer' => $bf,
                'rekap_dokumen' => $br,
                'selisih_footer' => ($bf === NULL) ? NULL : round($a - $bf, 2),
                'selisih_rekap' => ($br === NULL) ? NULL : round($a - $br, 2),
                'cocok_footer' => $cocokFooter,
                'cocok_rekap' => $cocokRekap,
                //Apakah kedua sumber TERCETAK itu saling cocok. Bila ya sedangkan hasil
                //hitung menyimpang, dokumennya konsisten dan pembacaan hariannya yang
                //patut dicurigai.
                'sumber_cetak_konsisten' => ($bf === NULL || $br === NULL) ? NULL : (abs($bf - $br) < 0.5),
            );
            $semua[] = $cocokFooter;
            $semua[] = $cocokRekap;
        }

        $terbanding = array_filter($semua, function ($v) { return $v !== NULL; });
        if (count($terbanding)) {
            $out['cocok'] = !in_array(FALSE, $terbanding, TRUE);
        }

        return $out;
    }

    //Jumlah hari satu bulan, sudah memperhitungkan kabisat.
    //
    //date('t') dipakai, bukan cal_days_in_month(), karena extension calendar TIDAK
    //dipasang di image ini (lihat daftar extension di Dockerfile) -- memanggilnya akan
    //fatal error. date() juga menerapkan aturan abad dengan benar: 2000 kabisat, 1900
    //tidak.
    //
    //Bila tahunnya tidak terbaca dari dokumen, dipakai tahun non-kabisat supaya Februari
    //dihitung 28 hari. Itu pilihan konservatif: penyebutnya jadi sedikit lebih kecil,
    //sehingga persentase tidak terlihat lebih buruk dari kenyataannya.
    private function aqmsJumlahHari($tahun, $bulan)
    {
        $tahun = $tahun ? (int) $tahun : 2001;

        return (int) date('t', mktime(0, 0, 0, (int) $bulan, 1, $tahun));
    }

    //Total hari bulan 1-11 menurut kalender: 334 hari, atau 335 pada tahun kabisat.
    private function aqmsHariSeharusnya($tahun)
    {
        $total = 0;
        for ($bulan = self::AQMS_MONTH_FIRST; $bulan <= self::AQMS_MONTH_LAST; $bulan++) {
            $total += $this -> aqmsJumlahHari($tahun, $bulan);
        }

        return $total;
    }

    //Validates $_FILES['file'], returns ["tmp_name"=>..., "mime"=>...] on success,
    //or ["error"=>true, "statusCode"=>..., "message"=>...] (json_encode-ready as-is) on failure
    private function readUploadedFile()
    {
        $file = isset($_FILES['file']) ? $_FILES['file'] : null;
        if (!$file || !$file['name']) {
            return array("error" => true, "statusCode" => 400, "message" => "File tidak ditemukan");
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array("error" => true, "statusCode" => 400, "message" => "Upload file gagal");
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return array("error" => true, "statusCode" => 400, "message" => "Ukuran file maksimal 10 Mb");
        }

        $ext = strtolower(strrchr($file['name'], "."));
        $mimeMap = array(
            ".pdf"  => "application/pdf",
            ".jpg"  => "image/jpeg",
            ".jpeg" => "image/jpeg",
            ".png"  => "image/png",
        );
        if (!isset($mimeMap[$ext])) {
            return array("error" => true, "statusCode" => 400, "message" => "Format file harus PDF, JPG, atau PNG");
        }

        return array("tmp_name" => $file['tmp_name'], "mime" => $mimeMap[$ext]);
    }

    //Shared response builder for the three *Extract() actions: turns an openrouter
    //extractX() result into the statusCode/data JSON payload the frontend expects,
    //using $matchMethod (one of matchFieldsIku/matchFieldsIka/matchFieldsIkal) to
    //resolve each detected location's free text into DB uids.
    //$service labels the call in ai_token_usage (iku-ocr, ika-ocr, ikal-ocr, ...).
    private function respondExtract($result, $matchMethod, $service)
    {
        //Pemakaian token disertakan di SETIAP cabang, termasuk yang gagal. Sekali
        //dokumen dikirim ke model, tokennya sudah ditagih -- terbaca atau tidak.
        //Justru cabang gagal yang paling perlu dilaporkan: dokumen yang sulit dibaca
        //biasanya diulang beberapa kali, dan tanpa angka di sini biaya percobaan
        //ulang itu tidak muncul di mana pun.
        //
        //null berarti responsnya memang tidak memuat usage -- praktisnya hanya terjadi
        //bila permintaannya gagal sebelum sampai ke model (mis. koneksi putus), yang
        //berarti tidak ada yang ditagih.
        $usage = isset($result['usage']) ? $result['usage'] : null;
        $this -> logTokenUsage($service, $usage);

        if (!$result['success']) {
            echo json_encode(array("statusCode" => 500, "message" => "OCR gagal dibaca: " . $result['error'], "usage" => $usage));
            return;
        }

        $ocr = $result['data'];
        $lokasiList = isset($ocr['lokasi_list']) && is_array($ocr['lokasi_list']) ? $ocr['lokasi_list'] : array();

        if (!count($lokasiList)) {
            echo json_encode(array("statusCode" => 500, "message" => "Tidak ada lokasi pemantauan yang terbaca dari dokumen", "usage" => $usage));
            return;
        }

        $shared = array(
            'tanggal' => isset($ocr['tanggal']) ? $ocr['tanggal'] : null,
            'periode_pemantauan' => isset($ocr['periode_pemantauan']) ? $ocr['periode_pemantauan'] : null,
            'laboratorium_text' => isset($ocr['laboratorium_text']) ? $ocr['laboratorium_text'] : null,
            'matrik_sampel_text' => isset($ocr['matrik_sampel_text']) ? $ocr['matrik_sampel_text'] : null,
        );

        $options = array();
        foreach ($lokasiList as $entry) {
            $options[] = $this -> $matchMethod($shared, $entry);
        }

        //usage ditaruh SEJAJAR dengan data, bukan di dalamnya. Bentuk `data` berbeda
        //antara hasil tunggal dan multi-lokasi, sedangkan biaya adalah milik satu
        //panggilan OCR secara keseluruhan -- satu dokumen, satu tagihan, berapa pun
        //lokasi yang terbaca di dalamnya. Dengan begini sisi frontend membacanya di
        //tempat yang sama untuk kedua bentuk.
        if (count($options) == 1) {
            echo json_encode(array("statusCode" => 200, "data" => $options[0], "usage" => $usage));
            return;
        }

        echo json_encode(array("statusCode" => 200, "data" => array(
            "multi" => true,
            "options" => $options,
        ), "usage" => $usage));
    }

    //Mencatat satu baris pemakaian token ke ai_token_usage.
    //
    //Dipanggil untuk SETIAP panggilan ke model, berhasil maupun gagal — tokennya
    //sudah ditagih begitu dokumen dikirim, jadi mencatat yang sukses saja akan
    //melaporkan biaya lebih kecil dari yang sebenarnya.
    //
    //Kegagalan mencatat SENGAJA didiamkan. Ini pekerjaan sampingan: bila tabelnya
    //belum dimigrasi atau database sedang bermasalah, hasil OCR yang sudah terlanjur
    //dibayar tetap harus sampai ke user.
    private function logTokenUsage($service, $usage)
    {
        //null berarti panggilannya tidak pernah sampai ke model (mis. koneksi putus),
        //sehingga tidak ada yang ditagih dan tidak ada yang perlu dicatat.
        if (!is_array($usage)) {
            return;
        }

        $cols = array("service");
        $vals = array("'" . $this -> sqlToken($service, 64) . "'");

        //NULL diteruskan sebagai NULL, bukan 0: kolomnya dirancang membedakan
        //"tidak dilaporkan provider" dari "dilaporkan dan memang nol", supaya SUM()
        //tidak diam-diam mengecilkan total.
        foreach (array("prompt_tokens", "completion_tokens", "total_tokens", "cached_tokens", "reasoning_tokens") as $f) {
            $cols[] = $f;
            $vals[] = (isset($usage[$f]) && $usage[$f] !== null) ? (int) $usage[$f] : "NULL";
        }

        //number_format, bukan sekadar dirangkai sebagai float. PHP menuliskan angka
        //sekecil 7.2e-5 dalam notasi eksponen, dan MySQL membaca literal semacam itu
        //sebagai DOUBLE lalu membulatkannya saat masuk kolom DECIMAL — persis galat
        //presisi yang ingin dihindari kolom DECIMAL itu.
        $cols[] = "cost";
        $vals[] = (isset($usage['cost']) && $usage['cost'] !== null)
            ? number_format((float) $usage['cost'], 10, ".", "")
            : "NULL";

        foreach (array("model" => 128, "generation_id" => 128) as $f => $len) {
            $cols[] = $f;
            $vals[] = (isset($usage[$f]) && $usage[$f] !== null)
                ? "'" . $this -> sqlToken($usage[$f], $len) . "'"
                : "NULL";
        }

        $cols[] = "crdate";
        $vals[] = time();

        $cols[] = "cruser";
        $vals[] = isset($this -> me['uid_users']) ? (int) $this -> me['uid_users'] : "NULL";

        $sql = "INSERT INTO ai_token_usage (" . implode(", ", $cols) . ")"
             . " VALUES (" . implode(", ", $vals) . ")";

        //Dijalankan lewat db->fetch(), BUKAN tables->post(). Adodb::insert() memanggil
        //die() bila query gagal, yang berarti tabel yang belum dimigrasi akan memutus
        //respons OCR di tengah jalan dan meninggalkan JSON rusak di browser.
        //Adodb::fetch() menjalankan query lewat Execute() yang sama tapi hanya
        //mengembalikan array kosong bila gagal — satu-satunya jalur non-fatal yang
        //tersedia di lapisan ini.
        $this -> db -> fetch($sql);
    }

    //Membersihkan nilai yang dirangkai ke dalam literal SQL di logTokenUsage().
    //
    //service, model, dan generation_id semuanya PENGENAL, bukan teks bebas:
    //"iku-ocr", "google/gemini-2.5-flash", "gen-1788773797-qDSiswMRhsoXcUv6SyP1".
    //Karena itu karakter di luar alfabet pengenal DIBUANG, bukan di-escape — lebih
    //mudah dipastikan benar daripada memilih fungsi escape yang tepat, dan tidak ada
    //yang hilang sebab nilai yang sah memang tidak pernah memuat karakter lain.
    //Yang penting: kutip tunggal termasuk yang dibuang, sehingga nilai dari OpenRouter
    //tidak bisa keluar dari literalnya.
    //
    //Panjangnya dipotong agar muat kolomnya. sql_mode server ini tanpa
    //STRICT_TRANS_TABLES, jadi kelebihan panjang akan dipotong DIAM-DIAM di sisi
    //MySQL — lebih baik dipotong di sini di tempat yang terlihat.
    private function sqlToken($value, $maxLength)
    {
        $value = preg_replace('/[^A-Za-z0-9._:\/@-]/', '', (string) $value);
        return substr($value, 0, $maxLength);
    }

    //Fields common to every module: date/period, lokasi, lab, coordinates, a display label.
    //$component is the lokasi_pemantauan.uid_rf_component to scope matching to (1=IKU air,
    //2=IKA water, 5=IKAL marine water).
    private function matchFieldsBase($shared, $entry, $component)
    {
        $out = array();
        $out['tanggal'] = $shared['tanggal'];
        $out['periode_pemantauan'] = $shared['periode_pemantauan'];
        $out['lokasi'] = $this -> matchLokasi(isset($entry['lokasi_text']) ? $entry['lokasi_text'] : null, $component);
        $out['lab'] = $this -> matchLab($shared['laboratorium_text']);
        $out['latitude'] = isset($entry['latitude']) ? $entry['latitude'] : null;
        $out['longitude'] = isset($entry['longitude']) ? $entry['longitude'] : null;
        $out['label'] = trim(
            (isset($entry['peruntukan_text']) ? $entry['peruntukan_text'] : '')
            . (isset($entry['lokasi_text']) ? ' - ' . $entry['lokasi_text'] : '')
        , ' -');
        return $out;
    }

    private function matchFieldsIku($shared, $entry)
    {
        $out = $this -> matchFieldsBase($shared, $entry, 1);
        $out['peruntukan'] = $this -> matchPeruntukan(isset($entry['peruntukan_text']) ? $entry['peruntukan_text'] : null, 1);

        //"lab" untuk IKU berupa ARRAY, berbeda dari matchFieldsBase() yang mengembalikan
        //satu objek dan tetap dipakai apa adanya oleh IKA/IKAL. Sebabnya satu lokasi bisa
        //dilayani lebih dari satu lab (lihat mergeSameLocationEntries()), dan kolom
        //pelaporan_iku.uid_lab memang menyimpannya sebagai CSV — bentuk array di sini
        //memungkinkan FE meng-preselect multi-select #uid_lab dengan beberapa lab sekaligus.
        $labTexts = array();
        $primaryLabText = !empty($entry['laboratorium_text']) ? $entry['laboratorium_text'] : $shared['laboratorium_text'];
        if (!empty($primaryLabText)) {
            $labTexts[] = $primaryLabText;
        }
        //_lab_texts hanya ada pada entri hasil penggabungan — field internal, bukan
        //bagian skema prompt (lihat mergeEntryGroup())
        if (!empty($entry['_lab_texts']) && is_array($entry['_lab_texts'])) {
            $labTexts = array_merge($labTexts, $entry['_lab_texts']);
        }
        $out['lab'] = $this -> matchLabMulti($labTexts);

        //Parameter dikelompokkan di bawah key "parameters" supaya sebentuk dengan respons
        //IKA dan IKAL. Perhatikan isinya TIDAK sama: di sini tiap parameter berupa objek
        //{nilai, durasi_pemantauan, metode}, sedangkan IKA/IKAL hanya menyimpan nilainya
        //(skalar) karena prompt kedua modul itu tidak mengekstrak metode per parameter.
        $out['parameters'] = array();
        foreach (array('no2', 'so2', 'pm25') as $param) {
            $p = isset($entry[$param]) && is_array($entry[$param]) ? $entry[$param] : array();
            $out['parameters'][$param] = array(
                'nilai' => isset($p['nilai']) ? $p['nilai'] : null,
                'durasi_pemantauan' => isset($p['durasi_pemantauan']) ? $p['durasi_pemantauan'] : null,
                'metode' => $this -> matchMetode(isset($p['metode_text']) ? $p['metode_text'] : null, $shared['matrik_sampel_text']),
            );
        }

        return $out;
    }

    //IKA has its own prompt (application/prompts/ika.md) with a richer per-location schema:
    //date is per-location (not document-level), "jenis_contoh_text" maps to the Kategori field,
    //and lab results come back as a free-form parameters[] array that needs name-matching against
    //the form's 55 known parameter field ids. IKA has no Peruntukan field.
    private function matchFieldsIka($shared, $entry)
    {
        $out = array();
        $out['tanggal'] = isset($entry['tanggal']) ? $entry['tanggal'] : $shared['tanggal'];
        $out['periode_pemantauan'] = $shared['periode_pemantauan'];
        $out['latitude'] = isset($entry['latitude']) ? $entry['latitude'] : null;
        $out['longitude'] = isset($entry['longitude']) ? $entry['longitude'] : null;
        $out['lokasi'] = $this -> matchLokasi(isset($entry['lokasi_text']) ? $entry['lokasi_text'] : null, 2, $out['latitude'], $out['longitude']);

        //IKA tidak punya langkah merge backend seperti IKU (lihat mergeSameLocationEntries()) --
        //prompt ika.md justru meminta model MENGGABUNGKAN sendiri hasil >1 lab untuk lokasi yang
        //sama ke satu elemen lokasi_list, dengan laboratorium_text digabung "; " (lihat aturan
        //laboratorium_text di ika.md). "lab" karena itu jadi ARRAY seperti IKU: teksnya dipecah
        //dulu baru dicocokkan satu-satu ke rf_lab, supaya gabungan "Lab A; Lab B" tidak dilempar
        //sebagai satu string utuh yang hampir pasti tidak match record manapun.
        $out['lab'] = $this -> matchLabMulti($this -> splitLabTexts($shared['laboratorium_text']));

        $out['label'] = trim(isset($entry['lokasi_text']) ? $entry['lokasi_text'] : '');
        $out['kategori'] = $this -> matchKategoriIka(isset($entry['jenis_contoh_text']) ? $entry['jenis_contoh_text'] : null);

        $params = isset($entry['parameters']) && is_array($entry['parameters']) ? $entry['parameters'] : array();
        $matched = array();
        $unmatched = array();
        foreach ($params as $p) {
            $name = isset($p['nama_text']) ? $p['nama_text'] : null;
            $nilai = isset($p['nilai']) ? $p['nilai'] : null;
            if (!$name) {
                continue;
            }
            $fieldId = $this -> matchIkaParameterField($name);
            if ($fieldId) {
                $matched[$fieldId] = $nilai;
            } else {
                $unmatched[] = $name . ($nilai !== null ? (" = " . $nilai) : '');
            }
        }
        $out['parameters'] = $matched;
        $out['unmatched_parameters'] = $unmatched;

        return $out;
    }

    private function matchKategoriIka($text)
    {
        $result = array('value' => null, 'text' => $text);
        if (!$text) {
            return $result;
        }
        $needle = strtolower($text);
        if (strpos($needle, 'gambut') !== false) {
            $result['value'] = 2;
        } elseif (strpos($needle, 'danau') !== false || strpos($needle, 'situ') !== false) {
            $result['value'] = 3;
        } else {
            $result['value'] = 1; //sungai / air permukaan (default)
        }
        return $result;
    }

    //Ordered field_id => [regex patterns] map for IKA's 55 water-quality parameter inputs.
    //Order matters: more specific patterns (e.g. "fecal coliform") are listed before generic
    //ones that could otherwise swallow them (e.g. bare "coliform"), first match wins.
    private function ikaParameterPatterns()
    {
        return array(
            'debit'                  => array('/debit/i'),
            'bod'                    => array('/\bbod\b/i', '/biological oxygen demand/i', '/biochemical oxygen demand/i'),
            'cod'                    => array('/\bcod\b/i', '/chemical oxygen demand/i'),
            'tss'                    => array('/\btss\b/i', '/total suspended solid/i', '/zat padat tersuspensi/i'),
            'tds'                    => array('/\btds\b/i', '/total dissolved solid/i', '/zat padat terlarut/i'),
            'do_p'                   => array('/\bdo\b/i', '/dissolved oxygen/i', '/oksigen terlarut/i'),
            'ph'                     => array('/\bph\b/i'),
            'no3_n'                  => array('/nitrat/i', '/\bno3\b/i', '/no₃/i'),
            'nitrit'                 => array('/nitrit/i', '/\bno2\b/i', '/no₂/i'),
            'total_phosphat'         => array('/total\s*fosfat/i', '/total\s*phosphat/i', '/fosfat\s*total/i'),
            'fecal_coliform'         => array('/fecal coliform/i', '/faecal coliform/i', '/coliform tinja/i', '/koliform tinja/i'),
            'total_coliform'         => array('/total coliform/i', '/coliform total/i', '/koliform total/i'),
            'e_coli'                 => array('/e\.?\s*coli/i', '/escherichia coli/i'),
            'kecerahan'              => array('/kecerahan/i', '/transparansi/i', '/secchi/i'),
            'klorofil_a'             => array('/klorofil/i', '/chlorophyll/i'),
            'total_nitrogen'         => array('/total nitrogen/i', '/nitrogen total/i'),
            'temperatur_air'         => array('/temperatur\s*air/i', '/suhu\s*air/i', '/temperature\s*air/i'),
            'temperatur_udara'       => array('/temperatur\s*udara/i', '/suhu\s*udara/i', '/temperature\s*udara/i'),
            'minyak_lemak'           => array('/minyak.{0,8}lemak/i', '/oil.{0,4}grease/i'),
            'detergen_total'         => array('/detergen/i', '/surfactant/i', '/\bmba\b/i'),
            'fenol'                  => array('/fenol/i', '/phenol/i'),
            'sulfat'                 => array('/sulfat/i', '/\bso4\b/i', '/so₄/i'),
            'klorida'                => array('/klorida/i', '/chloride/i'),
            'amoniak'                => array('/amoniak/i', '/amonia/i', '/ammonia/i', '/\bnh3\b/i', '/nh₃/i'),
            'florida'                => array('/flourida/i', '/fluorida/i', '/fluoride/i'),
            'belerang_sbg_h2s'       => array('/h2s/i', '/h₂s/i', '/belerang/i', '/sulfida/i', '/hydrogen sulfide/i'),
            'sianida'                => array('/sianida/i', '/cyanide/i', '/\bcn\b/i'),
            'klorin_bebas'           => array('/klorin bebas/i', '/free chlorine/i', '/sisa khlor/i', '/residual chlorine/i'),
            'warna'                  => array('/warna/i', '/\bcolour\b/i', '/\bcolor\b/i'),
            'sampah'                 => array('/sampah/i'),
            'ba'                     => array('/barium/i', '/\bba\b/i'),
            'bo'                     => array('/boron/i'),
            'hg'                     => array('/merkuri/i', '/mercury/i', '/\bhg\b/i'),
            'as_'                    => array('/arsenik/i', '/arsenic/i', '/\bas\b/i'),
            'se'                     => array('/selenium/i', '/\bse\b/i'),
            'fe'                     => array('/\bferrum\b/i', '/\bbesi\b/i', '/\bfe\b/i'),
            'cd'                     => array('/kadmium/i', '/cadmium/i', '/\bcd\b/i'),
            'co'                     => array('/kobalt/i', '/cobalt/i', '/\bco\b/i'),
            'mn'                     => array('/mangan/i', '/manganese/i', '/\bmn\b/i'),
            'ni'                     => array('/nikel/i', '/nickel/i', '/\bni\b/i'),
            'zn'                     => array('/\bseng\b/i', '/\bzinc\b/i', '/\bzn\b/i'),
            'cu'                     => array('/tembaga/i', '/copper/i', '/cuprum/i', '/\bcu\b/i'),
            'pb'                     => array('/timbal/i', '/timah hitam/i', '/\blead\b/i', '/plumbum/i', '/\bpb\b/i'),
            'cr_6'                   => array('/kromium.{0,4}6/i', '/kromium heksavalen/i', '/chromium.{0,4}vi\b/i', '/cr.{0,3}6/i', '/cr\s*\(vi\)/i'),
            'aldrin'                 => array('/aldrin/i', '/dieldrin/i'),
            'bhc'                    => array('/\bbhc\b/i', '/hexachlorocyclohexane/i'),
            'chlordane'              => array('/chlordane/i', '/klordan/i'),
            'ddt'                    => array('/\bddt\b/i'),
            'endrin'                 => array('/endrin/i'),
            'heptachlor'             => array('/heptachlor/i', '/heptaklor/i'),
            'lindane'                => array('/lindane/i', '/lindan/i'),
            'methoxychlor'           => array('/methoxychlor/i', '/metoksiklor/i'),
            'toxapan'                => array('/toxaphene/i', '/toksafen/i', '/toxapan/i'),
            'radioaktivitas_gross_a' => array('/gross.{0,4}alpha/i', '/gross.{0,4}a\b/i', '/radioaktivitas.{0,6}alfa/i'),
            'radioaktivitas_gross_b' => array('/gross.{0,4}beta/i', '/gross.{0,4}b\b/i', '/radioaktivitas.{0,6}beta/i'),
        );
    }

    private function matchIkaParameterField($name)
    {
        foreach ($this -> ikaParameterPatterns() as $fieldId => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    return $fieldId;
                }
            }
        }
        return null;
    }

    //IKAL form has a Peruntukan field (rf_peruntukan discriminator peruntukan=2); its water-quality
    //values come back as a free-form parameters[] array (application/prompts/ikal.md) that needs
    //name-matching against the form's 5 known parameter field ids, same approach as matchFieldsIka.
    private function matchFieldsIkal($shared, $entry)
    {
        $out = $this -> matchFieldsBase($shared, $entry, 5);
        $out['peruntukan'] = $this -> matchPeruntukan(isset($entry['peruntukan_text']) ? $entry['peruntukan_text'] : null, 2);

        $params = isset($entry['parameters']) && is_array($entry['parameters']) ? $entry['parameters'] : array();
        $matched = array();
        $unmatched = array();
        foreach ($params as $p) {
            $name = isset($p['nama_text']) ? $p['nama_text'] : null;
            $nilai = isset($p['nilai']) ? $p['nilai'] : null;
            if (!$name) {
                continue;
            }
            $fieldId = $this -> matchIkalParameterField($name);
            if ($fieldId) {
                $matched[$fieldId] = $nilai;
            } else {
                $unmatched[] = $name . ($nilai !== null ? (" = " . $nilai) : '');
            }
        }
        $out['parameters'] = $matched;
        $out['unmatched_parameters'] = $unmatched;

        return $out;
    }

    //Ordered field_id => [regex patterns] map for IKAL's 5 water-quality parameter inputs.
    private function ikalParameterPatterns()
    {
        return array(
            'tss'              => array('/\btss\b/i', '/total suspended solid/i', '/zat padat tersuspensi/i', '/padatan tersuspensi/i'),
            'do_p'             => array('/\bdo\b/i', '/dissolved oxygen/i', '/oksigen terlarut/i'),
            'minyak_dan_lemak' => array('/minyak.{0,8}lemak/i', '/oil.{0,4}grease/i'),
            'amonia_total'     => array('/amoniak/i', '/amonia/i', '/ammonia/i', '/\bnh3\b/i', '/nh₃/i'),
            'orto_fosfat'      => array('/orto.{0,4}fosfat/i', '/orto.{0,4}phosphat/i', '/\bpo4\b/i', '/po₄/i'),
        );
    }

    private function matchIkalParameterField($name)
    {
        foreach ($this -> ikalParameterPatterns() as $fieldId => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    return $fieldId;
                }
            }
        }
        return null;
    }

    //Radius (meter) untuk pencocokan lokasi berbasis koordinat — koordinat GPS OCR jauh lebih
    //bisa diandalkan daripada kemiripan teks (nama/alamat lokasi sering generik dan gampang
    //salah cocok), jadi kalau tersedia dicoba dulu sebelum fallback ke kemiripan teks.
    const KOORDINAT_MATCH_RADIUS_M = 2000;

    private function matchLokasi($text, $component, $lat = null, $lng = null)
    {
        $result = array('uid' => null, 'text' => $text);

        $w = "deleted = 0 AND uid_rf_component = " . (int) $component;
        //is_array() diperiksa lebih dulu karena $this->me TIDAK selalu array: bila sesi
        //tidak ada, init() menyimpan apa pun kembalian session->get(). Tanpa penjagaan
        //ini, log dibanjiri "Trying to access array offset on value of type int" yang
        //menenggelamkan galat sesungguhnya. Tanpa sesi, pencarian memang tidak
        //disempitkan ke wilayah mana pun.
        $me = is_array($this -> me) ? $this -> me : array();
        $role = isset($me['role_user']) ? $me['role_user'] : null;
        if ($role == 3) {
            $w .= " AND uid_kabkota = " . (int) $me['uid_kabkota'];
        } elseif ($role == 2) {
            $w .= " AND uid_provinsi = " . (int) $me['uid_provinsi'];
        }

        $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
        $rows = $this -> tables -> fetch($w)['data'];

        if (is_numeric($lat) && is_numeric($lng)) {
            $nearest = null;
            $nearestDist = null;
            foreach ($rows as $row) {
                if (!is_numeric($row['latitude']) || !is_numeric($row['longitude'])) {
                    continue;
                }
                $dist = $this -> haversineMeters($lat, $lng, $row['latitude'], $row['longitude']);
                if ($nearestDist === null || $dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearest = $row;
                }
            }
            if ($nearest && $nearestDist <= self::KOORDINAT_MATCH_RADIUS_M) {
                $result['uid'] = $nearest['uid_lokasi_pemantauan'];
                return $result;
            }
        }

        if (!$text) {
            return $result;
        }

        $needle = $this -> normalize($text);
        $best = null;
        $bestScore = 0;
        foreach ($rows as $row) {
            $kode = $this -> normalize($row['kode_lokasi']);
            $hay = $this -> normalize($row['kode_lokasi'] . " " . $row['alamat'] . " " . $row['alamat_detail']);

            if ($kode && strpos($needle, $kode) !== false) {
                $best = $row;
                $bestScore = 100;
                break;
            }

            similar_text($needle, $hay, $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best = $row;
            }
        }

        if ($best && $bestScore >= 40) {
            $result['uid'] = $best['uid_lokasi_pemantauan'];
        }
        return $result;
    }

    private function haversineMeters($lat1, $lon1, $lat2, $lon2)
    {
        $r = 6371000; //radius bumi, meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $r * $c;
    }

    //$discriminator is rf_peruntukan.peruntukan (1=IKU, 2=IKAL — IKA has no Peruntukan field)
    private function matchPeruntukan($text, $discriminator)
    {
        $result = array('uid' => null, 'text' => $text);
        if (!$text) {
            return $result;
        }

        $this -> tables -> set("rf_peruntukan", "uid_rf_peruntukan");
        $rows = $this -> tables -> fetch("deleted = 0 AND peruntukan = " . (int) $discriminator . " AND nama LIKE '%" . $this -> esc($text) . "%'")['data'];
        if (count($rows)) {
            $result['uid'] = $rows[0]['uid_rf_peruntukan'];
        }
        return $result;
    }

    private function matchLab($text)
    {
        $result = array('uid' => null, 'text' => $text);
        if (!$text) {
            return $result;
        }

        $this -> tables -> set("rf_lab", "uid");
        $safe = $this -> esc($text);
        $rows = $this -> tables -> fetch("deleted = 0 AND (nama LIKE '%" . $safe . "%' OR '" . $safe . "' LIKE CONCAT('%', kode, '%'))")['data'];
        if (count($rows)) {
            $result['uid'] = $rows[0]['uid'];
        }
        return $result;
    }

    //$texts: daftar nama lab, bisa lebih dari satu bila entri lokasi ini hasil penggabungan
    //beberapa sertifikat (lihat mergeSameLocationEntries()). Selalu mengembalikan ARRAY,
    //boleh kosong; tiap elemen {uid, text} hasil matchLab() apa adanya — entri dengan uid
    //null tetap disertakan supaya FE bisa menampilkan nama lab yang tidak ketemu di rf_lab,
    //konsisten dengan pola unmatched di field lain.
    //
    //Duplikat dibuang di dua tingkat: teks yang sama setelah dinormalkan, dan dua teks
    //berbeda yang ternyata menunjuk uid rf_lab yang sama (mis. "PT Mutuagung Lestari" dan
    //"Mutu International" di dua sertifikat) — kalau lolos, uid_lab CSV akan memuat angka
    //kembar dan multi-select di FE ikut janggal.
    private function matchLabMulti($texts)
    {
        $results = array();
        $seenUid = array();
        $seenText = array();

        foreach ($texts as $text) {
            if (!$text) {
                continue;
            }

            $norm = $this -> normalize($text);
            if (isset($seenText[$norm])) {
                continue;
            }
            $seenText[$norm] = true;

            $result = $this -> matchLab($text);
            if ($result['uid'] !== null) {
                if (isset($seenUid[$result['uid']])) {
                    continue;
                }
                $seenUid[$result['uid']] = true;
            }

            $results[] = $result;
        }

        return $results;
    }

    //Memecah laboratorium_text yang sudah digabung MODEL dengan pemisah "; " (lihat aturan
    //laboratorium_text di prompts/ika.md) jadi daftar nama lab tersendiri, siap dilempar ke
    //matchLabMulti() — supaya tiap lab dicocokkan sendiri-sendiri ke rf_lab, bukan sebagai satu
    //string gabungan yang tidak akan match record manapun.
    private function splitLabTexts($text)
    {
        if (!$text) {
            return array();
        }

        $parts = preg_split('/\s*;\s*/', trim($text));
        $parts = array_filter(array_map('trim', $parts), function ($t) {
            return $t !== '';
        });

        return array_values($parts);
    }

    //Cadangan bila baris LOCATION_MERGE_RADIUS_M belum ada di config_parameters.
    //Nilai yang sebenarnya berlaku ada di tabel itu.
    const MERGE_RADIUS_FALLBACK_M = 500;

    //Menggabungkan entri lokasi_list MENTAH yang sebenarnya menunjuk satu titik fisik,
    //untuk kasus satu berkas berisi sertifikat dari beberapa lab (lab A mengukur NO2+SO2,
    //lab B mengukur PM2.5) yang oleh model ditulis sebagai entri terpisah.
    //
    //Dua entri disatukan HANYA bila kedua syarat terpenuhi:
    //  (a) jaraknya <= LOCATION_MERGE_RADIUS_M, dan
    //  (b) cakupan parameternya saling melengkapi — lihat entriesComplementary()
    //
    //Union-find dipakai, bukan sekadar membandingkan berpasangan, karena satu lokasi bisa
    //muncul di lebih dari dua sertifikat sehingga penggabungannya berantai.
    //
    //Entri tanpa koordinat TIDAK PERNAH digabung. Itu disengaja: tanpa koordinat, satu-satunya
    //petunjuk tersisa adalah kemiripan teks lokasi, yang tidak cukup kuat untuk mempertaruhkan
    //penggabungan dua titik berbeda menjadi satu baris pelaporan.
    private function mergeSameLocationEntries($lokasiList)
    {
        $n = count($lokasiList);
        if ($n < 2) {
            return $lokasiList;
        }

        $radius = $this -> utils -> configInt('LOCATION_MERGE_RADIUS_M', self::MERGE_RADIUS_FALLBACK_M);

        $parent = range(0, $n - 1);
        $find = function ($x) use (&$parent, &$find) {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]]; //path halving
                $x = $parent[$x];
            }
            return $x;
        };

        for ($i = 0; $i < $n; $i++) {
            if (!$this -> entryHasCoordinates($lokasiList[$i])) {
                continue;
            }
            for ($j = $i + 1; $j < $n; $j++) {
                if (!$this -> entryHasCoordinates($lokasiList[$j])) {
                    continue;
                }

                $dist = $this -> utils -> distanceMeters(
                    $lokasiList[$i]['latitude'], $lokasiList[$i]['longitude'],
                    $lokasiList[$j]['latitude'], $lokasiList[$j]['longitude']
                );
                if ($dist > $radius) {
                    continue;
                }
                if (!$this -> entriesComplementary($lokasiList[$i], $lokasiList[$j])) {
                    continue;
                }

                $ri = $find($i);
                $rj = $find($j);
                if ($ri !== $rj) {
                    $parent[$rj] = $ri;
                }
            }
        }

        $groups = array();
        for ($i = 0; $i < $n; $i++) {
            $groups[$find($i)][] = $i;
        }

        $merged = array();
        foreach ($groups as $indexes) {
            if (count($indexes) === 1) {
                $merged[] = $lokasiList[$indexes[0]];
                continue;
            }
            $entries = array();
            foreach ($indexes as $idx) {
                $entries[] = $lokasiList[$idx];
            }
            $merged[] = $this -> mergeEntryGroup($entries);
        }

        return $merged;
    }

    private function entryHasCoordinates($entry)
    {
        return isset($entry['latitude']) && isset($entry['longitude'])
            && is_numeric($entry['latitude']) && is_numeric($entry['longitude']);
    }

    //Dua entri disebut saling melengkapi bila TIDAK ADA satu pun parameter yang sama-sama
    //terisi. Ini penjaga terpenting dalam penggabungan: kalau keduanya sama-sama punya nilai
    //NO2, itu pertanda dua lokasi fisik berbeda yang kebetulan berdekatan — bukan satu lokasi
    //dua lab. Lebih baik gagal menggabungkan (user menggabungkan sendiri lewat form) daripada
    //mencampur hasil uji dua titik jadi satu baris pelaporan yang tidak bisa ditelusuri lagi.
    private function entriesComplementary($a, $b)
    {
        foreach (array('no2', 'so2', 'pm25') as $param) {
            $av = isset($a[$param]['nilai']) ? $a[$param]['nilai'] : null;
            $bv = isset($b[$param]['nilai']) ? $b[$param]['nilai'] : null;
            if ($av !== null && $bv !== null) {
                return false;
            }
        }
        return true;
    }

    //Menyatukan satu kelompok entri jadi satu. Entri pertama jadi dasar; tiap parameter yang
    //masih kosong diisi dari entri lain yang punya nilai — aman karena entriesComplementary()
    //sudah memastikan tidak ada yang tumpang tindih. Objek parameternya disalin UTUH (nilai,
    //metode_text, durasi_pemantauan) supaya metode dan durasi tetap milik lab yang benar-benar
    //mengukurnya, bukan milik lab pada entri dasar.
    private function mergeEntryGroup($entries)
    {
        $out = $entries[0];
        $labTexts = array();

        foreach ($entries as $e) {
            if (!empty($e['laboratorium_text'])) {
                $labTexts[] = $e['laboratorium_text'];
            }

            foreach (array('no2', 'so2', 'pm25') as $param) {
                $v = isset($e[$param]['nilai']) ? $e[$param]['nilai'] : null;
                $curr = isset($out[$param]['nilai']) ? $out[$param]['nilai'] : null;
                if ($curr === null && $v !== null) {
                    $out[$param] = $e[$param];
                }
            }

            if (empty($out['peruntukan_text']) && !empty($e['peruntukan_text'])) {
                $out['peruntukan_text'] = $e['peruntukan_text'];
            }
            if (empty($out['lokasi_text']) && !empty($e['lokasi_text'])) {
                $out['lokasi_text'] = $e['lokasi_text'];
            }
        }

        //Field internal, dibaca matchFieldsIku() lalu tidak ikut ke respons
        $out['_lab_texts'] = array_values(array_unique($labTexts));

        return $out;
    }

    private function matchMetode($text, $matrikSampelText = null)
    {
        $result = array('uid' => null, 'text' => $text);
        if (!$text) {
            return $result;
        }

        $needle = strtolower($text);
        $keywordGroups = array(
            array('aktif', 'active'),
            array('pasif', 'passive', 'passif'),
            array('otomatis', 'aqms', 'automatic'),
        );

        //Kode metode seperti "SNI 7119.2:2017" tidak menyebut jenis sampler sama sekali,
        //jadi urutan penentuannya:
        //  (1) kata kunci eksplisit di teks metode itu sendiri;
        //  (2) kode SNI 7119 yang dikenal — deterministik per parameter, lihat sniMethodKeyword();
        //  (3) fallback ke "Matrik Sampel"/"Sample Matrix" di level dokumen.
        //
        //Tingkat (2) sengaja MENGALAHKAN (3). Alasannya: matrik_sampel_text hanya SATU nilai
        //untuk seluruh dokumen, sedangkan satu dokumen lazim mencampur metode antar-parameter
        //— gas diukur pasif, partikulat wajib ditarik pompa. Di data produksi, NO2 dan PM2.5
        //berbeda metode pada 5.857 baris. Tanpa tingkat (2), dokumen ber-Matrik "Passive
        //Sampler" akan menstempel PM2.5 sebagai Manual Passive, padahal metode pasif untuk
        //partikulat memang tidak ada — salah diam-diam, lebih buruk daripada kolom kosong.
        $hasKeyword = false;
        foreach ($keywordGroups as $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($needle, $kw) !== false) {
                    $hasKeyword = true;
                    break 2;
                }
            }
        }

        if ($hasKeyword) {
            $matchNeedle = $needle;
        } elseif ($sniKeyword = $this -> sniMethodKeyword($needle)) {
            $matchNeedle = $sniKeyword;
        } elseif ($matrikSampelText) {
            $matchNeedle = strtolower($matrikSampelText);
        } else {
            $matchNeedle = $needle;
        }

        $this -> tables -> set("rf_metode_pemantauan", "uid_metode_pemantauan");
        $rows = $this -> tables -> fetch("deleted = 0")['data'];

        foreach ($keywordGroups as $keywords) {
            $inText = false;
            foreach ($keywords as $kw) {
                if (strpos($matchNeedle, $kw) !== false) {
                    $inText = true;
                    break;
                }
            }
            if (!$inText) {
                continue;
            }

            foreach ($rows as $row) {
                $rowLower = strtolower($row['metode']);
                foreach ($keywords as $kw) {
                    if (strpos($rowLower, $kw) !== false) {
                        $result['uid'] = $row['uid_metode_pemantauan'];
                        return $result;
                    }
                }
            }
        }
        return $result;
    }

    //Kode SNI keluarga 7119 (udara ambien) sudah menentukan jenis sampler secara pasti, walau
    //teksnya tidak menyebut "pasif/aktif". Nomor bagian setelah "7119" menandakan parameter
    //SEKALIGUS jenis samplernya, jadi fungsi ini tidak perlu tahu sedang dipanggil untuk
    //parameter apa:
    //
    //  Sulfur Dioksida    aktif: SNI 7119.7:2017    pasif: SNI 7119-16:2023
    //  Nitrogen Dioksida  aktif: SNI 7119.2:2017    pasif: SNI 7119-17:2023
    //  Partikel Debu      aktif: SNI 7119.14:2017   pasif: TIDAK ADA
    //
    //Tahun tidak ikut diperiksa — hanya nomor bagiannya — supaya revisi standar tidak
    //membuat pencocokan ini berhenti bekerja.
    //
    //Pemisah titik dan strip diperlakukan sama ("7119.17" == "7119-17"), dan format lama
    //seperti "SNI 19-7119.7-2005" ikut tertangkap. Nomor bagian di luar daftar ini
    //mengembalikan null, sehingga penentuan jatuh ke fallback matrik_sampel_text seperti
    //sebelumnya — degradasi yang aman bila kelak ada bagian baru.
    private function sniMethodKeyword($needle)
    {
        if (strpos($needle, '7119') === false) {
            return null;
        }
        if (!preg_match('/7119[\s._-]*(\d{1,2})/', $needle, $m)) {
            return null;
        }

        $part = (int) $m[1];
        $pasif = array(16, 17);
        $aktif = array(2, 7, 14);

        if (in_array($part, $pasif, true)) {
            return 'pasif';
        }
        if (in_array($part, $aktif, true)) {
            return 'aktif';
        }
        return null;
    }

    private function normalize($text)
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $text)));
    }

    private function esc($text)
    {
        return addslashes($text);
    }
}
?>
