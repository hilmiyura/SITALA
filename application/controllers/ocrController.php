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

        $this -> respondExtract($result, "matchFieldsIku");
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
        $this -> respondExtract($result, "matchFieldsIka");
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
        $this -> respondExtract($result, "matchFieldsIkal");
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
    private function respondExtract($result, $matchMethod)
    {
        if (!$result['success']) {
            echo json_encode(array("statusCode" => 500, "message" => "OCR gagal dibaca: " . $result['error']));
            return;
        }

        $ocr = $result['data'];
        $lokasiList = isset($ocr['lokasi_list']) && is_array($ocr['lokasi_list']) ? $ocr['lokasi_list'] : array();

        if (!count($lokasiList)) {
            echo json_encode(array("statusCode" => 500, "message" => "Tidak ada lokasi pemantauan yang terbaca dari dokumen"));
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

        if (count($options) == 1) {
            echo json_encode(array("statusCode" => 200, "data" => $options[0]));
            return;
        }

        echo json_encode(array("statusCode" => 200, "data" => array(
            "multi" => true,
            "options" => $options,
        )));
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
        $out['lokasi'] = $this -> matchLokasi(isset($entry['lokasi_text']) ? $entry['lokasi_text'] : null, 2);
        $out['lab'] = $this -> matchLab($shared['laboratorium_text']);
        $out['latitude'] = isset($entry['latitude']) ? $entry['latitude'] : null;
        $out['longitude'] = isset($entry['longitude']) ? $entry['longitude'] : null;
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

    private function matchLokasi($text, $component)
    {
        $result = array('uid' => null, 'text' => $text);
        if (!$text) {
            return $result;
        }

        $w = "deleted = 0 AND uid_rf_component = " . (int) $component;
        if ($this -> me['role_user'] == 3) {
            $w .= " AND uid_kabkota = " . (int) $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND uid_provinsi = " . (int) $this -> me['uid_provinsi'];
        }

        $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
        $rows = $this -> tables -> fetch($w)['data'];

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

        //Method codes like "SNI 7119.xx:2023" don't state sampler type themselves;
        //fall back to the document-level "Matrik Sampel"/"Sample Matrix" text when present.
        $hasKeyword = false;
        foreach ($keywordGroups as $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($needle, $kw) !== false) {
                    $hasKeyword = true;
                    break 2;
                }
            }
        }
        $matchNeedle = (!$hasKeyword && $matrikSampelText) ? strtolower($matrikSampelText) : $needle;

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
