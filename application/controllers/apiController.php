<?php

class apiController extends Front
{
    public function init()
    {
        $this->setHeaders();
        $this->loadModel('tables');
    }

    private function setHeaders()
    {
        if (isset($_SERVER["HTTP_ORIGIN"])) {
            header("Access-Control-Allow-Origin: {$_SERVER["HTTP_ORIGIN"]}");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 86400"); // 1 day
        }

        if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
            if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"]))
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

            if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]))
                header("Access-Control-Allow-Headers: {$_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]}");

            exit();
        }

        if (!isset($_SERVER['HTTP_X_API_KEY']))
            $this->returnJson(401);

        if (password_verify('r3qv35t-wqm5-2023', $_SERVER['HTTP_X_API_KEY']))
            $this->returnJson(401);
    }

    private function returnJson($code, $rows = null)
    {
        header_remove();
        http_response_code($code);
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(['status' => $code, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }

    private function unsetDefault($rows)
    {
        unset($rows['cruser']);
        unset($rows['crdate']);
        unset($rows['chdate']);
        unset($rows['deleted']);
        unset($rows['hidden']);

        return $rows;
    }

    public function locations()
    {
        $result = [];
        $year = ACTIVE_YEAR;
        $limit = $this->params('limit') ? $this->params('limit') : LIMIT;
        $offset = $this->params('offset') ? $this->params('offset') : 0;
        $keyword = $this->params('keyword') ? urldecode($this->params('keyword')) : null;
        $where = "deleted = 0 AND hidden = 0 AND uid_rf_component = 2 AND uid_rf_pelaksana = 3 AND uid_provinsi = 16 AND tahun LIKE '%{$year}%'";
        $columns = [
            'alamat',
            'alamat_detail',
            'nama_kabkota',
            'kode_lokasi',
        ];

        if ($keyword) {
            for ($i = 1; $i < count($columns); $i++) {
                $like .= "{$columns[$i]} LIKE '%{$keyword}%'";
                if ($i < count($columns) - 1)
                    $like .= " OR ";
            }
            $where .= " AND ({$like})";
        }

        if ($id = $this->params('id')) {
            $id = implode(',', json_decode(urldecode($id), true));
            $where .= " AND uid_lokasi_pemantauan IN ({$id})";
        }

        if ($code = $this->params('code')) {
            $code = implode("','", json_decode(strtoupper(urldecode($code)), true));
            $where .= " AND kode_lokasi IN ('{$code}')";
        }
        // data list
        $query = $this->db->query("SELECT * FROM v_lokasi_pemantauan WHERE {$where} LIMIT {$limit} OFFSET {$offset}");
        foreach ($query as $value) { $result[] = $this->unsetDefault($value); }
        // data count
        $total = null;
        if ($this->params('total')) {
            $total = $this->db->query("SELECT COUNT(uid_lokasi_pemantauan) AS x FROM v_lokasi_pemantauan WHERE {$where}");
            $total = $total->fields['x'];
        }

        $this->returnJson(200, ['data' => $result, 'total' => $total]);
    }

    public function locationDistrict()
    {
        $result = [];
        $year = ACTIVE_YEAR;
        $where = "deleted = 0 AND hidden = 0 AND uid_rf_component = 2 AND uid_provinsi = 16 AND tahun LIKE '%{$year}%'";
        $query = $this->db->query("SELECT * FROM v_lokasi_pemantauan WHERE {$where} ORDER BY nama_kabkota ASC");
        foreach ($query as $value) {
            $result[$value['uid_kabkota']]['name'] = $value['nama_kabkota'];
            $result[$value['uid_kabkota']]['location'][] = $value['uid_lokasi_pemantauan'];
        }

        $this->returnJson(200, array_values($result));
    }

    public function reporting()
    {
        // error_reporting (E_ALL);
        // ini_set("display_errors", true);
        $post = $this->post();
        $post['deleted'] = 0;
        $post['cruser'] = 142;
        $post['uid_provinsi'] = 16;
        $post['uid_rf_bma'] = 2;
        $post['do_max_p'] = 8.5;
        $post['shu'] = null;
        // $post['v_provinsi'] = 1;
        // $post['v_provinsi_date'] = date('Y-m-d H:i:s');
        // $post['v_pusat'] = 1;
        // $post['v_pusat_date'] = date('Y-m-d H:i:s');
        // $post['v_regional'] = 1;
        // $post['v_regional_date'] = date('Y-m-d H:i:s');
        // if ($post['shu']) {
        //     $pathExternal = $post['shu_url'];
        //     $pathInternal = UPLOADFOLDER . 'monitoring/' . $post['shu'];
        //     if (!copy($pathExternal, $pathInternal))
        //         $this->returnJson(500);
        // }

        // validate location
        $year = ACTIVE_YEAR;
        $where = "deleted = 0 AND hidden = 0 AND uid_rf_component = 2 AND uid_provinsi = 16 AND tahun LIKE '%{$year}%'";
        $where .= " AND uid_lokasi_pemantauan = {$post['uid_lokasi_pemantauan']}";
        $location = $this->db->query("SELECT * FROM lokasi_pemantauan WHERE {$where}");
        if (!$location->_numOfRows) $this->returnJson(500);
        $location = (object) $location->fields;
        $locationId = $location->uid_lokasi_pemantauan;
        $locationAlamat = $location->alamat;
        $locationProvince = $location->uid_provinsi;
        $locationDistrict = $location->uid_kabkota;

        // update pelaporan
        $this->tables->set('pelaporan_ika', 'uid_pelaporan_ika');
        if (!$this->tables->post(['form' => $post, 'submit' => true])) $this->returnJson(500);
        $updateId = $post['uid_pelaporan_ika'] ? $post['uid_pelaporan_ika'] : $this->tables->lastInsertID;
        // $updateYear = date('Y', strtotime($post['tanggal']));
        // $updateBma = $post['uid_rf_bma'];
        // $updateCategory = $post['kategori'];

        // if ($this->params('countOnly')) {
        //     // status mutu
        //     require_once CONTROLLERS . 'ikaController.php';
        //     $control = new ikaController;
        //     $statusmutu = $control->_countIndekStatusMutu($updateId);
        //     $statusmutu['uid_pelaporan_ika'] = $updateId;
        //     $statusmutu['deleted'] = 1;

        //     // set status mutu
        //     $this->tables->set('pelaporan_ika', 'uid_pelaporan_ika');
        //     if (!$this->tables->post(['form' => $statusmutu, 'submit' => true])) $this->returnJson(500);

        //     // return with status mutu
        //     $this->returnJson(200, [
        //         'id' => $statusmutu['uid_pelaporan_ika'],
        //         'value' => json_decode($statusmutu['status_mutu_detail'], true)
        //     ]);
        // }

        $this->returnJson(200, ['id' => $updateId]);
    }
}
