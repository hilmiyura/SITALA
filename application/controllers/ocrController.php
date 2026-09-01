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
