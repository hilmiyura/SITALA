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
        //multi seperti jalur SHU. Label/koordinat pertama yang terisi dipakai untuk
        //pencocokan -- kebanyakan laporan AQMS tidak mencantumkan koordinat sama sekali
        //(lihat prompts/iku_aqms.md), jadi primaryLat/primaryLng sering tetap null dan
        //matchLokasi() akan mengembalikan uid null (tidak ada fallback fuzzy tanpa
        //koordinat, sama seperti jalur IKU/IKA/IKAL lainnya).
        $primaryLabel = null;
        $primaryLat = null;
        $primaryLng = null;
        foreach (array('no2', 'so2', 'pm25') as $param) {
            $p = isset($ocr[$param]) && is_array($ocr[$param]) ? $ocr[$param] : null;
            if (!empty($labels[$param]) && $primaryLabel === null) {
                $primaryLabel = $labels[$param];
            }
            if ($p && isset($p['latitude'], $p['longitude']) && is_numeric($p['latitude']) && is_numeric($p['longitude']) && $primaryLat === null) {
                $primaryLat = $p['latitude'];
                $primaryLng = $p['longitude'];
            }
        }

        $out['lokasi'] = $this -> matchLokasi($primaryLabel, 1, $primaryLat, $primaryLng);
        $out['latitude'] = $primaryLat;
        $out['longitude'] = $primaryLng;
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
        $out['latitude'] = isset($entry['latitude']) ? $entry['latitude'] : null;
        $out['longitude'] = isset($entry['longitude']) ? $entry['longitude'] : null;
        $out['lokasi'] = $this -> matchLokasi(isset($entry['lokasi_text']) ? $entry['lokasi_text'] : null, $component, $out['latitude'], $out['longitude']);
        $out['lab'] = $this -> matchLab($shared['laboratorium_text']);
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

        //Konversi satuan dan validasi dikerjakan di sini, BUKAN oleh model: aritmetikanya
        //tidak butuh kecerdasan, dan hasilnya harus bisa diuji ulang tanpa memanggil model
        //lagi. Model hanya melaporkan nilai, satuan, dan baku mutu apa adanya seperti tercetak
        //(lihat prompts/ika.md) -- sebelum ini satuan_text dibuang begitu saja, sehingga
        //"12 L/detik" masuk ke kolom berlabel m3/s tanpa ada yang tahu.
        $params = isset($entry['parameters']) && is_array($entry['parameters']) ? $entry['parameters'] : array();
        $hasil = $this -> buildIkaParameters($params);

        $out['parameters'] = $hasil['parameters'];
        $out['parameters_validasi'] = $hasil['parameters_validasi'];
        $out['unmatched_parameters'] = $hasil['unmatched_parameters'];
        $out['validasi_ringkas'] = $hasil['validasi_ringkas'];

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
            //"TP" dan "total pospat" adalah penulisan yang dipakai sebagian SHU untuk Total
            //Fosfat (lihat rulesika.txt). "Fosfat" POLOS sengaja tidak ada di sini -- bisa
            //berarti total fosfat atau organofosfat, jadi ditolak ikaAmbiguousPatterns().
            'total_phosphat'         => array('/total\s*fosfat/i', '/total\s*phosphat/i', '/total\s*pospat/i', '/fosfat\s*total/i', '/\btp\b/i'),
            'fecal_coliform'         => array('/fecal coliform/i', '/faecal coliform/i', '/coliform tinja/i', '/koliform tinja/i', '/coli\s*tinja/i', '/bakteri\s*koli\s*tinja/i'),
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
            //HANYA klorin BEBAS. "Residu klorin"/"residual klorin"/"sisa klor" tanpa kata
            //"bebas" adalah parameter yang BERBEDA (rulesika.txt) -- dulu keduanya ikut
            //dipetakan ke sini sehingga nilai parameter lain masuk ke kolom klorin bebas
            //tanpa jejak. Sekarang ditolak lewat ikaAmbiguousPatterns().
            'klorin_bebas'           => array('/klorin bebas/i', '/free chlorine/i', '/sisa\s*klor\s*bebas/i'),
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

    //Nama parameter yang BENTUKNYA mirip parameter yang kita kenal, tapi artinya belum tentu
    //sama -- dan salah memetakannya berarti menaruh hasil uji parameter lain ke kolom yang
    //keliru, tanpa jejak apa pun bahwa itu terjadi. Lebih baik dilempar ke
    //unmatched_parameters supaya operator memutuskan sendiri (lihat rulesika.txt).
    //
    //Diperiksa SEBELUM ikaParameterPatterns(), jadi tiap pola di sini harus dipastikan tidak
    //ikut menangkap bentuk yang sah -- itulah guna lookahead negatifnya:
    //  "Sisa Klor Bebas" -> lolos ke klorin_bebas, "Sisa Klor" saja -> ditolak
    //  "Temperatur Air"  -> lolos ke temperatur_air, "Temperatur" saja -> ditolak
    //  "Total Fosfat"    -> lolos ke total_phosphat, "Fosfat" saja -> ditolak
    private function ikaAmbiguousPatterns()
    {
        return array(
            '/residu(al)?\s*klor(?!.*bebas)/i'            => 'ambigu: residu/residual klorin bukan klorin bebas',
            '/sisa\s*k?hlor(?!.*bebas)/i'                 => 'ambigu: sisa klor bukan klorin bebas',
            '/residual\s*chlorine(?!.*free)/i'            => 'ambigu: residual chlorine bukan klorin bebas',
            '/\b(temperatur|suhu)\b(?!.*\b(air|udara)\b)/i' => 'ambigu: temperatur tanpa keterangan air atau udara',
            '/^(?!.*\btotal\b)(?!.*\borto\b)(?!.*\bortho\b).*\b(fosfat|pospat|phosphat)\b/i' => 'ambigu: fosfat bisa total fosfat atau organofosfat',
        );
    }

    //Mengembalikan alasan penolakan bila nama parameter tergolong ambigu, atau null bila aman
    //untuk dicocokkan seperti biasa.
    private function matchIkaAmbiguousReason($name)
    {
        foreach ($this -> ikaAmbiguousPatterns() as $pattern => $alasan) {
            if (preg_match($pattern, $name)) {
                return $alasan;
            }
        }
        return null;
    }

    //Satuan yang DIHARAPKAN sistem untuk tiap field IKA, disalin dari label input di
    //views/be/parts/contents/ika/index/form.html. Sumbernya sengaja disebut: satuan ini tidak
    //tersimpan di tabel referensi mana pun, jadi kalau label di form berubah, tabel ini harus
    //ikut diubah -- kalau tidak, konversi akan menghasilkan angka yang salah secara diam-diam.
    //
    //Field tanpa satuan (ph, sampah) sengaja TIDAK didaftarkan: nilainya dipakai apa adanya
    //dan tidak pernah dikonversi.
    private function ikaParameterUnits()
    {
        $mgL = array(
            'bod', 'cod', 'tss', 'do_p', 'do_max_p', 'no3_n', 'total_phosphat', 'total_nitrogen',
            'minyak_lemak', 'detergen_total', 'fenol', 'tds', 'sulfat', 'klorida', 'nitrit',
            'amoniak', 'florida', 'belerang_sbg_h2s', 'sianida', 'klorin_bebas',
            'ba', 'bo', 'hg', 'as_', 'se', 'fe', 'cd', 'co', 'mn', 'ni', 'zn', 'cu', 'pb', 'cr_6',
        );
        $ugL = array(
            'aldrin', 'bhc', 'chlordane', 'ddt', 'endrin', 'heptachlor', 'lindane',
            'methoxychlor', 'toxapan',
        );

        $units = array(
            'debit'                  => 'm3/s',
            'kecerahan'              => 'm',
            'klorofil_a'             => 'mg/m3',
            'fecal_coliform'         => 'MPN/100 mL',
            'total_coliform'         => 'MPN/100 mL',
            'e_coli'                 => 'MPN/100 mL',
            'temperatur_air'         => '°C',
            'temperatur_udara'       => '°C',
            'warna'                  => 'Pt-Co Unit',
            'radioaktivitas_gross_a' => 'Bq/L',
            'radioaktivitas_gross_b' => 'Bq/L',
        );
        foreach ($mgL as $id) {
            $units[$id] = 'mg/L';
        }
        foreach ($ugL as $id) {
            $units[$id] = 'µg/L';
        }

        return $units;
    }

    //Menyederhanakan satuan tertulis jadi satu token baku supaya "MPN/100 mL", "MPN/100mL",
    //dan "mpn / 100 ml" dikenali sebagai satuan yang sama.
    //
    //Mengembalikan null untuk dua keadaan yang BERBEDA artinya bagi pemanggil, jadi
    //convertIkaValue() memeriksa teks kosongnya lebih dulu: teks kosong berarti dokumen tidak
    //mencantumkan satuan, sedangkan teks terisi yang tidak dikenali berarti satuannya asing
    //dan nilainya tidak boleh dipercaya.
    private function normalizeUnitToken($text)
    {
        $t = trim((string) $text);
        if ($t === '' || $t === '-') {
            return null;
        }

        //Karakter non-ASCII diganti SEBELUM strtolower(), dan urutan itu wajib. strtolower()
        //bekerja per BYTE dan mengikuti locale: pada locale Latin-1 byte 0xC2 -- yang justru
        //merupakan byte pertama UTF-8 untuk µ dan ° -- ikut "dihurufkecilkan" jadi 0xE2,
        //sehingga urutan byte-nya rusak dan str_replace() di bawah tidak lagi mengenalinya.
        //Akibatnya µg/L dan °C jatuh ke cabang "satuan tidak dikenali" dan seluruh hasil uji
        //logam serta temperatur ditolak tanpa sebab yang terlihat. Setelah baris-baris ini
        //string sudah murni ASCII, jadi strtolower() aman dipanggil.
        //
        //Mikro bisa ditulis µ (U+00B5), μ/Μ (huruf mu Yunani), atau sekadar "u" bila dokumen
        //tidak memuat karakter khusus. Semuanya sama.
        $t = str_replace(array('µ', 'μ', 'Μ'), 'u', $t);
        $t = str_replace(array('³', '^3'), '3', $t);
        $t = str_replace(array('°', 'º'), '', $t);
        $t = strtolower($t);
        //Sebagian SHU menulis derajat dengan superscript nol, yang terbaca sebagai "0c"
        $t = preg_replace('/(^|[^0-9])0c$/', '$1c', $t);
        $t = str_replace(array('detik', 'dtk', 'det', 'second', 'sec'), 's', $t);
        //Spasi, titik, dan strip dibuang seluruhnya: "Pt-Co Unit" dan "ptco" satuan yang sama
        $t = preg_replace('/[\s.\-]+/', '', $t);

        $alias = array(
            'mg/l' => 'mg/L', 'ppm' => 'mg/L', 'miligram/l' => 'mg/L',
            'ug/l' => 'µg/L', 'ppb' => 'µg/L', 'mikrogram/l' => 'µg/L',
            'mg/m3' => 'mg/m3', 'ug/m3' => 'µg/m3',
            'm3/s' => 'm3/s', 'm3/dt' => 'm3/s',
            'l/s' => 'L/s', 'l/dt' => 'L/s',
            'm' => 'm', 'meter' => 'm', 'cm' => 'cm',
            'mpn/100ml' => 'MPN/100 mL', 'mpn/100cc' => 'MPN/100 mL',
            'cfu/100ml' => 'CFU/100 mL', 'koloni/100ml' => 'CFU/100 mL', 'jml/100ml' => 'Jml/100 mL',
            'ptco' => 'Pt-Co Unit', 'ptcounit' => 'Pt-Co Unit', 'unitptco' => 'Pt-Co Unit', 'skalaptco' => 'Pt-Co Unit',
            'tcu' => 'TCU',
            'c' => '°C', 'derajatc' => '°C', 'degc' => '°C',
            'bq/l' => 'Bq/L',
            'ntu' => 'NTU',
        );

        return isset($alias[$t]) ? $alias[$t] : null;
    }

    //Faktor pengali untuk mengubah satuan asal jadi satuan target, dikunci "asal|target".
    //Hanya pasangan yang tercantum di sini yang boleh dikonversi -- pasangan lain sengaja
    //ditolak, karena "kelihatan mirip" bukan alasan yang cukup untuk mengalikan angka
    //pelaporan. Contohnya CFU/100 mL dan MPN/100 mL: keduanya cacah bakteri per 100 mL, tapi
    //hasil metode yang berbeda dan TIDAK saling dikonversi (rulesika.txt).
    private function ikaUnitFactors()
    {
        return array(
            'µg/L|mg/L'  => 0.001,
            'mg/L|µg/L'  => 1000,
            'L/s|m3/s'   => 0.001,
            'cm|m'       => 0.01,
            'm|cm'       => 100,
            //Klorofil-a hampir selalu dilaporkan lab dalam µg/L, sedangkan form memakai
            //mg/m3. Keduanya SETARA persis: 1 µg/L = 1e-6 g / 1e-3 m3 = 1 mg/m3. Tanpa baris
            //ini setiap laporan danau akan menolak klorofil-a padahal satuannya benar.
            'µg/L|mg/m3' => 1,
            'mg/L|mg/m3' => 1000,
            //Setara, bukan dikonversi: skala TCU dibaca sama dengan Pt-Co (rulesika.txt)
            'TCU|Pt-Co Unit' => 1,
        );
    }

    //Satuan yang DIHARAPKAN sistem untuk tiap field IKAL, disalin dari label input di
    //views/be/parts/contents/ikal/index/form.html. IKAL hanya punya 5 parameter dan
    //kelimanya berlabel mg/L -- tidak seragam seperti IKA yang mencampur mg/L, µg/L, dan
    //satuan lain, tapi tetap disalin lewat fungsi terpisah (bukan konstanta inline) supaya
    //perubahan pada form tetap tercermin di satu tempat.
    private function ikalParameterUnits()
    {
        return array(
            'tss' => 'mg/L',
            'do_p' => 'mg/L',
            'minyak_dan_lemak' => 'mg/L',
            'amonia_total' => 'mg/L',
            'orto_fosfat' => 'mg/L',
        );
    }

    //Wrapper tipis di atas convertParameterValue() untuk parameter IKA -- lihat
    //convertIkalValue() untuk pasangannya di modul IKAL. Dipisah per modul (bukan satu
    //fungsi generik yang dipanggil langsung dengan ikaParameterUnits()/ikalParameterUnits())
    //supaya titik panggil di buildIkaParameters()/buildIkalParameters() tetap menyebut nama
    //modulnya secara eksplisit dan gampang ditelusuri.
    private function convertIkaValue($fieldId, $nilai, $satuanText)
    {
        return $this -> convertParameterValue($fieldId, $nilai, $satuanText, $this -> ikaParameterUnits());
    }

    private function convertIkalValue($fieldId, $nilai, $satuanText)
    {
        return $this -> convertParameterValue($fieldId, $nilai, $satuanText, $this -> ikalParameterUnits());
    }

    //Menyesuaikan satu nilai ke satuan yang diharapkan sistem. $units adalah map
    //fieldId => satuan_target milik modul pemanggil (ikaParameterUnits() atau
    //ikalParameterUnits()) -- konversi & faktor pengalinya (ikaUnitFactors()) dipakai
    //bersama karena aritmetikanya sama, hanya daftar field dan target yang berbeda per modul.
    //
    //@return array(satuan_target, faktor, nilai, status, alasan[])
    //        status: verified (sudah benar) | converted (dikalikan faktor) |
    //                unknown (satuan tidak tercantum) | reject (satuan asing/tak terkonversi)
    private function convertParameterValue($fieldId, $nilai, $satuanText, $units)
    {
        $target = isset($units[$fieldId]) ? $units[$fieldId] : null;

        $out = array(
            'satuan_target' => $target,
            'faktor' => null,
            'nilai' => $nilai,
            'status' => 'verified',
            'alasan' => array(),
        );

        //Parameter tanpa satuan baku (ph, sampah) atau baris tanpa angka tidak ada yang bisa
        //dikonversi -- dan "tidak diuji" bukan temuan, jadi tidak diberi alasan apa pun.
        if ($target === null || $nilai === null) {
            return $out;
        }

        $satuanBersih = trim((string) $satuanText);
        if ($satuanBersih === '' || $satuanBersih === '-') {
            $out['status'] = 'unknown';
            $out['alasan'][] = 'Satuan tidak tercantum di dokumen, nilai dianggap sudah dalam ' . $target;
            return $out;
        }

        $asal = $this -> normalizeUnitToken($satuanBersih);
        if ($asal === null) {
            $out['status'] = 'reject';
            $out['alasan'][] = 'Satuan "' . $satuanBersih . '" tidak dikenali (seharusnya ' . $target . ')';
            return $out;
        }

        if ($asal === $target) {
            return $out;
        }

        $factors = $this -> ikaUnitFactors();
        $kunci = $asal . '|' . $target;
        if (!isset($factors[$kunci])) {
            $out['status'] = 'reject';
            $out['alasan'][] = 'Satuan "' . $satuanBersih . '" tidak dapat dikonversi ke ' . $target;
            return $out;
        }

        $faktor = $factors[$kunci];
        $out['faktor'] = $faktor;
        $out['nilai'] = $nilai * $faktor;

        if ($faktor == 1) {
            $out['alasan'][] = 'Satuan ' . $asal . ' diperlakukan setara ' . $target;
            return $out;
        }

        $out['status'] = 'converted';
        $out['alasan'][] = 'Dikonversi ' . $nilai . ' ' . $asal . ' menjadi ' . $out['nilai'] . ' ' . $target;

        return $out;
    }

    //Pemeriksaan yang berlaku untuk SEMUA parameter, dijalankan pada angka APA ADANYA seperti
    //tercetak di dokumen (bukan hasil konversi): ketiganya membandingkan nilai terhadap dirinya
    //sendiri atau terhadap baku mutu yang tercetak di baris yang sama, jadi keduanya harus
    //berada di satuan yang sama -- yaitu satuan dokumen.
    //
    //@return array daftar alasan penolakan, kosong bila lolos
    private function validateIkaValueBasic($nilai, $bakumutu)
    {
        $alasan = array();

        //null berarti parameternya tidak diuji, dan itu bukan pelanggaran. Dibedakan dari 0
        //dengan perbandingan identitas, BUKAN empty() -- empty(0) bernilai TRUE dan akan
        //meloloskan justru nilai yang harus ditolak (rulesika.txt).
        if ($nilai === null) {
            return $alasan;
        }

        if ($nilai == 0) {
            $alasan[] = 'Nilai 0 tidak dianggap hasil pengukuran yang sah';
        }
        if ($nilai < 0) {
            $alasan[] = 'Nilai negatif tidak sah';
        }
        //Toleransi relatif, bukan perbandingan == : nilai dan baku mutu sama-sama pecahan
        //hasil parsing teks, dan 0.1 hasil hitung tidak selalu identik bit-per-bit dengan 0.1
        //yang ditulis langsung.
        if ($bakumutu !== null && abs($nilai - $bakumutu) <= 1e-9 * max(1.0, abs($bakumutu))) {
            $alasan[] = 'Nilai sama persis dengan baku mutu (' . $bakumutu . ')';
        }

        return $alasan;
    }

    //Ambang per parameter yang berdiri sendiri (tidak bergantung parameter lain). Dijalankan
    //pada nilai yang SUDAH dikonversi, karena ambangnya dinyatakan dalam satuan sistem.
    private function validateIkaAmbang($fieldId, $nilai)
    {
        $alasan = array();
        if ($nilai === null) {
            return $alasan;
        }

        if ($fieldId === 'ph' && $nilai > 14) {
            $alasan[] = 'pH di atas 14 tidak mungkin';
        }
        if ($fieldId === 'bod' && $nilai < 1) {
            $alasan[] = 'BOD di bawah 1 mg/L dianggap tidak wajar';
        }
        if ($fieldId === 'fecal_coliform' && $nilai < self::IKA_FECAL_MIN) {
            $alasan[] = 'Fecal coliform di bawah ' . self::IKA_FECAL_MIN . ' MPN/100 mL tidak dapat dilaporkan';
        }

        return $alasan;
    }

    //Ambang IKAL yang berdiri sendiri, sama pola dengan validateIkaAmbang() tapi konstantanya
    //TIDAK bergantung parameter lain: batas DO air laut ini tetap 8.5 mg/L berapa pun suhunya
    //-- beda dari IKA di mana batas DO memang berubah mengikuti temperatur_air (lihat
    //validateIkaCrossParams()). Dijalankan pada nilai yang sudah dikonversi.
    private function validateIkalAmbang($fieldId, $nilai)
    {
        $alasan = array();
        if ($nilai === null) {
            return $alasan;
        }

        if ($fieldId === 'do_p' && $nilai > self::IKAL_DO_MAKS) {
            $alasan[] = 'DO di atas ' . self::IKAL_DO_MAKS . ' mg/L tidak wajar untuk air laut';
        }

        return $alasan;
    }

    const IKAL_DO_MAKS = 8.5;

    //Batas bawah pelaporan fecal coliform. 1,8 bukan angka sembarang melainkan nilai terkecil
    //yang bisa DIHASILKAN metode MPN seri tiga tabung -- di bawah itu hasilnya hanya bisa
    //dinyatakan "<1,8", bukan sebuah angka. Nilai yang lebih kecil karena itu menandakan salah
    //baca atau salah satuan, bukan air yang sangat bersih (rulesika.txt).
    //
    //Perbandingannya "kurang dari": tepat 1,8 masih sah.
    const IKA_FECAL_MIN = 1.8;

    //Batas atas DO menurut suhu air. Angkanya adalah kelarutan oksigen jenuh: makin hangat
    //air, makin sedikit oksigen yang bisa larut, jadi DO di atas ambang ini secara fisika
    //tidak mungkin dan menandakan salah baca (rulesika.txt).
    const IKA_DO_MAKS_HANGAT = 8.24;  //temperatur_air >= 25 °C
    const IKA_DO_MAKS_SEDANG = 9.08;  //20 <= temperatur_air < 25 °C
    const IKA_DO_MAKS_DINGIN = 10.07; //temperatur_air < 20 °C

    //Delapan parameter yang wajib ada dalam pelaporan IKA (rulesika.txt).
    private function ikaParameterWajib()
    {
        return array('ph', 'bod', 'cod', 'tss', 'do_p', 'fecal_coliform', 'no3_n', 'total_phosphat');
    }

    //Aturan yang baru bisa dinilai setelah SELURUH parameter satu lokasi terkumpul, karena
    //membandingkan satu parameter terhadap parameter lain. Bekerja pada nilai yang sudah
    //dikonversi.
    //
    //Bila pembandingnya tidak ada di dokumen, hasilnya BUKAN pelanggaran -- tidak ada yang
    //bisa disimpulkan dari perbandingan yang tidak bisa dilakukan. Ini mengikuti pola
    //'cocok' => null di aqmsIntegritas().
    private function validateIkaCrossParams($rows)
    {
        $bod = $this -> ikaRowValue($rows, 'bod');
        $cod = $this -> ikaRowValue($rows, 'cod');
        //Aturan tertulis di rulesika.txt sebagai "bod > cod", tapi itu terbalik secara kimia:
        //COD mengoksidasi semua bahan yang dioksidasi BOD DITAMBAH bahan lain, sehingga COD
        //selalu >= BOD. BOD melebihi COD hampir pasti berarti kedua kolom tertukar saat
        //dibaca. Yang ditegakkan di sini karena itu COD > BOD.
        if ($bod !== null && $cod !== null && $cod <= $bod) {
            $this -> tandaiRejectIka($rows['bod'], 'BOD (' . $bod . ') tidak boleh >= COD (' . $cod . ')');
            $this -> tandaiRejectIka($rows['cod'], 'COD (' . $cod . ') harus lebih besar dari BOD (' . $bod . ')');
        }

        $fecal = $this -> ikaRowValue($rows, 'fecal_coliform');
        $total = $this -> ikaRowValue($rows, 'total_coliform');
        if ($fecal !== null && $total !== null && $fecal >= $total) {
            $this -> tandaiRejectIka($rows['fecal_coliform'], 'Fecal coliform (' . $fecal . ') harus lebih kecil dari total coliform (' . $total . ')');
        }

        $do = $this -> ikaRowValue($rows, 'do_p');
        if ($do !== null) {
            $suhu = $this -> ikaRowValue($rows, 'temperatur_air');
            if ($suhu === null) {
                $maks = self::IKA_DO_MAKS_HANGAT;
                $dasar = 'temperatur air tidak tersedia';
            } elseif ($suhu >= 25) {
                $maks = self::IKA_DO_MAKS_HANGAT;
                $dasar = 'temperatur air ' . $suhu . ' °C';
            } elseif ($suhu >= 20) {
                $maks = self::IKA_DO_MAKS_SEDANG;
                $dasar = 'temperatur air ' . $suhu . ' °C';
            } else {
                $maks = self::IKA_DO_MAKS_DINGIN;
                $dasar = 'temperatur air ' . $suhu . ' °C';
            }

            if ($do > $maks) {
                $this -> tandaiRejectIka($rows['do_p'], 'DO ' . $do . ' mg/L melebihi batas kelarutan ' . $maks . ' mg/L (' . $dasar . ')');
            }
        }

        return $rows;
    }

    private function ikaRowValue($rows, $fieldId)
    {
        return isset($rows[$fieldId]['nilai']) ? $rows[$fieldId]['nilai'] : null;
    }

    //Status reject "menang" atas status apa pun sebelumnya: sebuah nilai yang berhasil
    //dikonversi tapi kemudian melanggar ambang tetap tidak boleh dipakai.
    private function tandaiRejectIka(&$row, $alasan)
    {
        $row['status'] = 'reject';
        $row['alasan'][] = $alasan;
    }

    //Membaca angka dari keluaran model dengan hati-hati.
    //
    //is_numeric(), bukan empty(): 0 adalah hasil pengukuran yang harus dinilai (dan menurut
    //rulesika.txt justru ditolak), sedangkan null berarti parameternya tidak diuji. empty()
    //menyamakan keduanya.
    //
    //Koma desimal ikut ditangani meski prompt sudah meminta titik. Model terbukti sesekali
    //mengembalikan "24,5" (lihat docs/ocrIku.md), dan tanpa penanganan ini nilai tersebut
    //bukan cuma salah -- ia hilang sama sekali menjadi null. Hanya koma tunggal tanpa titik
    //yang diperlakukan sebagai pemisah desimal; penulisan ribuan gaya Indonesia memakai titik,
    //sehingga tidak ada bentuk sah yang tertukar di sini.
    private function angkaAtauNull($nilai)
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }
        if (is_numeric($nilai)) {
            return (float) $nilai;
        }

        $teks = trim((string) $nilai);
        if (substr_count($teks, ',') === 1 && strpos($teks, '.') === false) {
            $teks = str_replace(',', '.', $teks);
            if (is_numeric($teks)) {
                return (float) $teks;
            }
        }

        return null;
    }

    //Merakit parameters/parameters_validasi/unmatched_parameters untuk SATU lokasi.
    //
    //Urutannya penting: konversi satuan dijalankan lebih dulu supaya seluruh ambang di
    //belakangnya membandingkan angka pada satuan yang sama. Aturan lintas parameter menyusul
    //paling akhir, saat semua baris sudah terkumpul.
    private function buildIkaParameters($params)
    {
        $rows = array();
        $unmatched = array();

        foreach ($params as $p) {
            if (!is_array($p)) {
                continue;
            }
            $name = isset($p['nama_text']) ? trim((string) $p['nama_text']) : '';
            if ($name === '') {
                continue;
            }

            $nilai = $this -> angkaAtauNull(isset($p['nilai']) ? $p['nilai'] : null);
            $bakumutu = $this -> angkaAtauNull(isset($p['bakumutu']) ? $p['bakumutu'] : null);
            $satuan = isset($p['satuan_text']) ? $p['satuan_text'] : null;
            $jejak = $name . ($nilai !== null ? ' = ' . $nilai : '');

            $ambigu = $this -> matchIkaAmbiguousReason($name);
            if ($ambigu !== null) {
                $unmatched[] = $jejak . ' (' . $ambigu . ')';
                continue;
            }

            $fieldId = $this -> matchIkaParameterField($name);
            if (!$fieldId) {
                $unmatched[] = $jejak;
                continue;
            }

            $konversi = $this -> convertIkaValue($fieldId, $nilai, $satuan);

            $row = array(
                'nama_text' => $name,
                'nilai_asal' => $nilai,
                'satuan_text' => $satuan,
                'satuan_target' => $konversi['satuan_target'],
                'faktor' => $konversi['faktor'],
                'nilai' => $konversi['nilai'],
                'bakumutu' => $bakumutu,
                'status' => $konversi['status'],
                'alasan' => $konversi['alasan'],
            );

            //Dibandingkan terhadap angka dokumen (nilai_asal), sedangkan ambang memakai nilai
            //terkonversi -- lihat alasannya di masing-masing fungsi.
            foreach ($this -> validateIkaValueBasic($nilai, $bakumutu) as $alasanNilai) {
                $this -> tandaiRejectIka($row, $alasanNilai);
            }
            foreach ($this -> validateIkaAmbang($fieldId, $row['nilai']) as $alasanAmbang) {
                $this -> tandaiRejectIka($row, $alasanAmbang);
            }

            $rows[$fieldId] = $row;
        }

        $rows = $this -> validateIkaCrossParams($rows);

        //Nilai yang ditolak TETAP dimasukkan ke parameters supaya operator melihat apa yang
        //terbaca dari dokumen dan bisa mengoreksinya; penandaannya dikerjakan frontend lewat
        //parameters_validasi. Sengaja tidak memblokir -- konsisten dengan validasi OCR lain
        //di controller ini (lihat AQMS_HARI_VALID_MIN).
        $parameters = array();
        $jumlahReject = 0;
        foreach ($rows as $fieldId => $row) {
            $parameters[$fieldId] = $row['nilai'];
            if ($row['status'] === 'reject') {
                $jumlahReject++;
            }
        }

        $wajibHilang = array();
        foreach ($this -> ikaParameterWajib() as $fieldId) {
            if (!isset($rows[$fieldId]) || $rows[$fieldId]['nilai'] === null) {
                $wajibHilang[] = $fieldId;
            }
        }

        return array(
            'parameters' => $parameters,
            'parameters_validasi' => $rows,
            'unmatched_parameters' => $unmatched,
            'validasi_ringkas' => array(
                'wajib_hilang' => $wajibHilang,
                'jumlah_reject' => $jumlahReject,
                'jumlah_terbaca' => count($rows),
            ),
        );
    }

    //Sama pola dengan buildIkaParameters(), disederhanakan sesuai bentuk IKAL: hanya 5
    //parameter, semuanya bertarget mg/L (ikalParameterUnits()), tidak ada field bakumutu di
    //skema prompts/ikal.md, dan tidak ada nama parameter ambigu maupun aturan lintas-parameter
    //yang perlu ditegakkan (bandingkan dengan IKA yang punya keduanya).
    private function buildIkalParameters($params)
    {
        $rows = array();
        $unmatched = array();

        foreach ($params as $p) {
            if (!is_array($p)) {
                continue;
            }
            $name = isset($p['nama_text']) ? trim((string) $p['nama_text']) : '';
            if ($name === '') {
                continue;
            }

            $nilai = $this -> angkaAtauNull(isset($p['nilai']) ? $p['nilai'] : null);
            $satuan = isset($p['satuan_text']) ? $p['satuan_text'] : null;
            $jejak = $name . ($nilai !== null ? ' = ' . $nilai : '');

            $fieldId = $this -> matchIkalParameterField($name);
            if (!$fieldId) {
                $unmatched[] = $jejak;
                continue;
            }

            $konversi = $this -> convertIkalValue($fieldId, $nilai, $satuan);

            $row = array(
                'nama_text' => $name,
                'nilai_asal' => $nilai,
                'satuan_text' => $satuan,
                'satuan_target' => $konversi['satuan_target'],
                'faktor' => $konversi['faktor'],
                'nilai' => $konversi['nilai'],
                'status' => $konversi['status'],
                'alasan' => $konversi['alasan'],
            );

            //bakumutu selalu null di sini -- prompts/ikal.md tidak mengekstraknya, beda dari
            //IKA. validateIkaValueBasic() tetap dipakai apa adanya karena pemeriksaan 0/negatif
            //tidak bergantung modul.
            foreach ($this -> validateIkaValueBasic($nilai, null) as $alasanNilai) {
                $this -> tandaiRejectIka($row, $alasanNilai);
            }
            foreach ($this -> validateIkalAmbang($fieldId, $row['nilai']) as $alasanAmbang) {
                $this -> tandaiRejectIka($row, $alasanAmbang);
            }

            $rows[$fieldId] = $row;
        }

        $parameters = array();
        $jumlahReject = 0;
        foreach ($rows as $fieldId => $row) {
            $parameters[$fieldId] = $row['nilai'];
            if ($row['status'] === 'reject') {
                $jumlahReject++;
            }
        }

        return array(
            'parameters' => $parameters,
            'parameters_validasi' => $rows,
            'unmatched_parameters' => $unmatched,
            'validasi_ringkas' => array(
                'jumlah_reject' => $jumlahReject,
                'jumlah_terbaca' => count($rows),
            ),
        );
    }

    //IKAL form has a Peruntukan field (rf_peruntukan discriminator peruntukan=2); its water-quality
    //values come back as a free-form parameters[] array (application/prompts/ikal.md) that needs
    //name-matching against the form's 5 known parameter field ids, same approach as matchFieldsIka.
    private function matchFieldsIkal($shared, $entry)
    {
        $out = $this -> matchFieldsBase($shared, $entry, 5);
        $out['peruntukan'] = $this -> matchPeruntukan(isset($entry['peruntukan_text']) ? $entry['peruntukan_text'] : null, 2);

        //IKAL, seperti IKA, tidak punya langkah merge backend seperti IKU -- prompts/ikal.md
        //meminta model MENGGABUNGKAN sendiri hasil >1 lab untuk lokasi yang sama ke satu
        //elemen lokasi_list, dengan laboratorium_text digabung "; " (lihat aturan
        //laboratorium_text di ikal.md). "lab" karena itu jadi ARRAY, menimpa hasil
        //matchFieldsBase() yang objek tunggal.
        $out['lab'] = $this -> matchLabMulti($this -> splitLabTexts($shared['laboratorium_text']));

        $params = isset($entry['parameters']) && is_array($entry['parameters']) ? $entry['parameters'] : array();
        $hasil = $this -> buildIkalParameters($params);

        $out['parameters'] = $hasil['parameters'];
        $out['parameters_validasi'] = $hasil['parameters_validasi'];
        $out['unmatched_parameters'] = $hasil['unmatched_parameters'];
        $out['validasi_ringkas'] = $hasil['validasi_ringkas'];

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

    //Cadangan bila baris KOORDINAT_MATCH_RADIUS_M / _CANDIDATE_LIMIT / _FUZZY_THRESHOLD
    //belum ada di config_parameters (lihat migration
    //2026-09-11_add_koordinat_match_radius_param.sql).
    const KOORDINAT_MATCH_RADIUS_FALLBACK_M = 500;
    const KOORDINAT_MATCH_CANDIDATE_LIMIT_FALLBACK = 10;
    const KOORDINAT_MATCH_FUZZY_THRESHOLD_FALLBACK = 80;

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

        $result['uid'] = $this -> nearestLokasiByCoordinate($w, $lat, $lng, $text);
        return $result;
    }

    //Orkestrasi:
    //  1+2. Kalau koordinat ada, matchLokasiWithinRadius() cari beberapa titik terdekat
    //       dalam radius lewat SQL, lalu similar_text() PHP dipakai sebagai tie-break di
    //       antara kandidat yang sudah di-fetch itu (bukan seluruh tabel -- cuma sampai
    //       candidateLimit baris, jadi loop PHP-nya murah).
    //  3.   STEP 3 (fallback) dipicu di DUA kondisi: koordinat sama sekali tidak ada, ATAU
    //       step 1 tidak menemukan titik apa pun dalam radius. Keduanya jatuh ke
    //       matchLokasiByTextSql() -- fuzzy match via SQL LIKE ke seluruh tabel (masih
    //       dibatasi component + region yang sama), tanpa syarat jarak. Dipakai SQL (bukan
    //       similar_text()) di sini karena kandidatnya bisa ribuan baris -- menariknya ke
    //       PHP dulu untuk similar_text() akan mahal.
    private function nearestLokasiByCoordinate($where, $lat, $lng, $text = null)
    {
        $radius = $this -> utils -> configInt('KOORDINAT_MATCH_RADIUS_M', self::KOORDINAT_MATCH_RADIUS_FALLBACK_M);
        $candidateLimit = $this -> utils -> configInt('KOORDINAT_MATCH_CANDIDATE_LIMIT', self::KOORDINAT_MATCH_CANDIDATE_LIMIT_FALLBACK);
        $fuzzyThreshold = $this -> utils -> configInt('KOORDINAT_MATCH_FUZZY_THRESHOLD', self::KOORDINAT_MATCH_FUZZY_THRESHOLD_FALLBACK);

        if (is_numeric($lat) && is_numeric($lng)) {
            $uid = $this -> matchLokasiWithinRadius($where, (float) $lat, (float) $lng, $text, $radius, $candidateLimit, $fuzzyThreshold);
            if ($uid !== null) {
                return $uid;
            }
        }

        return $this -> matchLokasiByTextSql($where, $text, $fuzzyThreshold);
    }

    //STEP 1 + STEP 2: ambil sampai $candidateLimit lokasi terdekat dalam radius $radius
    //langsung lewat SQL (haversine formula di SELECT/HAVING, dibatasi bounding box dulu
    //supaya index idx_lokasi_geo_lookup di kolom latitude/longitude kepakai). Baris yang
    //kembali (maksimal $candidateLimit) dipakai similar_text() PHP sebagai tie-break:
    //kandidat dengan skor >= $fuzzyThreshold menang berdasar skor tertinggi (bisa jadi
    //bukan yang paling dekat jaraknya); kalau tidak ada yang cukup mirip, kandidat
    //paling dekat (baris pertama, karena sudah ORDER BY jarak) yang menang. Return null
    //hanya kalau TIDAK ADA titik sama sekali dalam radius (memicu step 3 fallback).
    private function matchLokasiWithinRadius($where, $lat, $lng, $text, $radius, $candidateLimit, $fuzzyThreshold)
    {
        $r = 6371000; //radius bumi, meter
        //Padding bounding box dalam derajat, dilebihkan dikit dari radius supaya tidak
        //memotong kandidat yang sebenarnya masih dalam radius (arc vs chord approx).
        $latDelta = $radius / 110574;
        $lngDelta = $radius / (111320 * max(cos(deg2rad($lat)), 0.000001));

        $latSql = sprintf('%F', $lat);
        $lngSql = sprintf('%F', $lng);

        $sql = "SELECT uid_lokasi_pemantauan, kode_lokasi, alamat, alamat_detail,
                    (" . $r . " * ACOS(LEAST(1, GREATEST(-1,
                        COS(RADIANS(" . $latSql . ")) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(" . $lngSql . "))
                        + SIN(RADIANS(" . $latSql . ")) * SIN(RADIANS(latitude))
                    )))) AS jarak
                FROM lokasi_pemantauan
                WHERE " . $where . "
                    AND latitude IS NOT NULL AND longitude IS NOT NULL
                    AND latitude BETWEEN " . sprintf('%F', $lat - $latDelta) . " AND " . sprintf('%F', $lat + $latDelta) . "
                    AND longitude BETWEEN " . sprintf('%F', $lng - $lngDelta) . " AND " . sprintf('%F', $lng + $lngDelta) . "
                HAVING jarak <= " . $radius . "
                ORDER BY jarak ASC
                LIMIT " . (int) $candidateLimit;

        $rows = $this -> tables -> query($sql)['data'];
        if (!count($rows)) {
            return null;
        }

        $nearestUid = $rows[0]['uid_lokasi_pemantauan'];
        if (!$text) {
            return $nearestUid;
        }

        $needle = $this -> normalize($text);
        $best = null;
        $bestScore = 0;
        foreach ($rows as $row) {
            $hay = $this -> normalize($row['kode_lokasi'] . " " . $row['alamat'] . " " . $row['alamat_detail']);
            similar_text($needle, $hay, $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best = $row;
            }
        }

        if ($best && $bestScore >= $fuzzyThreshold) {
            return $best['uid_lokasi_pemantauan'];
        }
        return $nearestUid;
    }

    //STEP 3 (fallback): dipanggil hanya kalau matchLokasiWithinRadius() tidak menemukan
    //titik sama sekali dalam radius. Fuzzy match via SQL ke SELURUH tabel yang lolos
    //$where (component + region yang sama seperti step 1), tanpa syarat jarak sama
    //sekali. uid dikembalikan hanya kalau skor terbaiknya >= $fuzzyThreshold -- kalau
    //tidak ada yang cukup mirip, atau $text kosong, hasilnya null.
    private function matchLokasiByTextSql($where, $text, $fuzzyThreshold)
    {
        $skorSql = $this -> likeScoreSql($text, array('kode_lokasi', 'alamat', 'alamat_detail'));
        if ($skorSql === '0') {
            return null;
        }

        $sql = "SELECT uid_lokasi_pemantauan, " . $skorSql . " AS skor
                FROM lokasi_pemantauan
                WHERE " . $where . "
                HAVING skor >= " . (int) $fuzzyThreshold . "
                ORDER BY skor DESC
                LIMIT 1";

        $rows = $this -> tables -> query($sql)['data'];
        return count($rows) ? $rows[0]['uid_lokasi_pemantauan'] : null;
    }

    //Ekspresi SQL yang menghitung skor kemiripan (0-100) sebuah baris terhadap $text:
    //persentase kata (token) dari $text yang ditemukan sebagai substring di gabungan
    //kolom-kolom di $cols (via LIKE '%token%'). Dipakai sebagai pengganti similar_text()
    //PHP supaya tidak perlu loop PHP atas baris kandidat -- MySQL tidak punya fungsi
    //kemiripan teks native yang setara similar_text(), jadi ini pendekatan praktis:
    //bukan algoritma longest-common-substring seperti similar_text(), tapi rasio
    //kata-yang-cocok. Dipakai baik untuk lokasi (matchLokasiByTextSql()) maupun lab
    //(matchLab()) -- $cols menentukan kolom tabel mana yang dibandingkan.
    private function likeScoreSql($text, $cols)
    {
        $tokens = $this -> likeTokens($text);
        if (!count($tokens)) {
            return '0';
        }

        $hay = "LOWER(CONCAT_WS(' ', " . implode(', ', $cols) . "))";
        $hits = array();
        foreach ($tokens as $tok) {
            $hits[] = "(" . $hay . " LIKE '%" . $this -> likeEscape($tok) . "%')";
        }
        return "((" . implode(' + ', $hits) . ") / " . count($tokens) . " * 100)";
    }

    //Pecah $text ternormalisasi jadi kata-kata unik (>=2 karakter, maks 10 kata) untuk
    //dipakai likeScoreSql(). Kata sangat pendek (1 huruf) dibuang karena nyaris pasti
    //muncul di semua baris dan tidak berguna sebagai sinyal kemiripan.
    private function likeTokens($text)
    {
        $needle = $this -> normalize($text);
        if ($needle === '') {
            return array();
        }
        $tokens = array_filter(explode(' ', $needle), function ($t) {
            return mb_strlen($t) >= 2;
        });
        return array_slice(array_values(array_unique($tokens)), 0, 10);
    }

    //Escape token untuk dipakai di dalam pola LIKE '%...%': wildcard LIKE (% dan _)
    //serta backslash (escape char default LIKE) di-escape dulu, baru quote SQL biasa.
    private function likeEscape($text)
    {
        $text = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $text);
        return addslashes($text);
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

    //Cadangan bila baris LAB_MATCH_FUZZY_THRESHOLD belum ada di config_parameters.
    const LAB_MATCH_FUZZY_THRESHOLD_FALLBACK = 70;

    //1. Exact: kode rf_lab (mis. "MTG", "SWT") sebagai substring teks OCR -- murah dan
    //   nyaris tidak mungkin salah kalau memang ketemu, jadi dicoba duluan.
    //2. Fuzzy: skor kemiripan kata (likeScoreSql(), sama seperti dipakai lokasi) terhadap
    //   nama + kode, supaya variasi penulisan ("PT Mutuagung Lestari" vs "Mutu
    //   International" vs "Mutuagung Lestari, PT") masih bisa ketemu selama kata-kata
    //   intinya cocok -- beda dari LIKE polos sebelumnya yang butuh substring persis.
    //   ORDER BY skor DESC membuat hasil deterministik (dulu $rows[0] tanpa ORDER BY).
    private function matchLab($text)
    {
        $result = array('uid' => null, 'text' => $text);
        if (!$text) {
            return $result;
        }

        $needle = $this -> esc($this -> normalize($text));
        $exact = $this -> tables -> query(
            "SELECT uid FROM rf_lab
             WHERE deleted = 0 AND kode IS NOT NULL AND kode <> ''
                 AND '" . $needle . "' LIKE CONCAT('%', LOWER(kode), '%')
             LIMIT 1"
        )['data'];
        if (count($exact)) {
            $result['uid'] = $exact[0]['uid'];
            return $result;
        }

        $skorSql = $this -> likeScoreSql($text, array('nama', 'kode'));
        if ($skorSql === '0') {
            return $result;
        }

        $threshold = $this -> utils -> configInt('LAB_MATCH_FUZZY_THRESHOLD', self::LAB_MATCH_FUZZY_THRESHOLD_FALLBACK);
        $rows = $this -> tables -> query(
            "SELECT uid, " . $skorSql . " AS skor
             FROM rf_lab
             WHERE deleted = 0
             HAVING skor >= " . (int) $threshold . "
             ORDER BY skor DESC
             LIMIT 1"
        )['data'];
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
