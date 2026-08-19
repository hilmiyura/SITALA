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

        $file = isset($_FILES['file']) ? $_FILES['file'] : null;
        if (!$file || !$file['name']) {
            echo json_encode(array("statusCode" => 400, "message" => "File tidak ditemukan"));
            return;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array("statusCode" => 400, "message" => "Upload file gagal"));
            return;
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(array("statusCode" => 400, "message" => "Ukuran file maksimal 10 Mb"));
            return;
        }

        $ext = strtolower(strrchr($file['name'], "."));
        $mimeMap = array(
            ".pdf"  => "application/pdf",
            ".jpg"  => "image/jpeg",
            ".jpeg" => "image/jpeg",
            ".png"  => "image/png",
        );
        if (!isset($mimeMap[$ext])) {
            echo json_encode(array("statusCode" => 400, "message" => "Format file harus PDF, JPG, atau PNG"));
            return;
        }

        $result = $this -> openrouter -> extractIku($file['tmp_name'], $mimeMap[$ext]);

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
            $options[] = $this -> matchFields($shared, $entry);
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

    private function matchFields($shared, $entry)
    {
        $out = array();
        $out['tanggal'] = $shared['tanggal'];
        $out['periode_pemantauan'] = $shared['periode_pemantauan'];
        $out['lokasi'] = $this -> matchLokasi(isset($entry['lokasi_text']) ? $entry['lokasi_text'] : null);
        $out['peruntukan'] = $this -> matchPeruntukan(isset($entry['peruntukan_text']) ? $entry['peruntukan_text'] : null);
        $out['lab'] = $this -> matchLab($shared['laboratorium_text']);
        $out['latitude'] = isset($entry['latitude']) ? $entry['latitude'] : null;
        $out['longitude'] = isset($entry['longitude']) ? $entry['longitude'] : null;
        $out['label'] = trim(
            (isset($entry['peruntukan_text']) ? $entry['peruntukan_text'] : '')
            . (isset($entry['lokasi_text']) ? ' - ' . $entry['lokasi_text'] : '')
        , ' -');

        foreach (array('no2', 'so2', 'pm25') as $param) {
            $p = isset($entry[$param]) && is_array($entry[$param]) ? $entry[$param] : array();
            $out[$param] = array(
                'nilai' => isset($p['nilai']) ? $p['nilai'] : null,
                'durasi_pemantauan' => isset($p['durasi_pemantauan']) ? $p['durasi_pemantauan'] : null,
                'metode' => $this -> matchMetode(isset($p['metode_text']) ? $p['metode_text'] : null, $shared['matrik_sampel_text']),
            );
        }

        return $out;
    }

    private function matchLokasi($text)
    {
        $result = array('uid' => null, 'text' => $text);
        if (!$text) {
            return $result;
        }

        $w = "deleted = 0 AND uid_rf_component = 1";
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

    private function matchPeruntukan($text)
    {
        $result = array('uid' => null, 'text' => $text);
        if (!$text) {
            return $result;
        }

        $this -> tables -> set("rf_peruntukan", "uid_rf_peruntukan");
        $rows = $this -> tables -> fetch("deleted = 0 AND nama LIKE '%" . $this -> esc($text) . "%'")['data'];
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
