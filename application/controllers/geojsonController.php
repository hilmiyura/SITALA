<?php

/**
 * created at   : 11/09/2026
 * created by   : Dasendria team
 * desc         : geojson endpoints for spatial layers (sp_rth, dst)
 */
class geojsonController extends Front
{
    public function init()
    {
        $this->loadModel('tables');
    }

    private function returnJson($code, $data = null, $message = null)
    {
        header_remove();
        http_response_code($code);
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(['status' => $code, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // GET /geojson/rth/kd_kota/<kd_kota>
    public function rth()
    {
        $kdKota = $this->params('kd_kota');
        if (!$kdKota || !is_numeric($kdKota)) $this->returnJson(400, null, 'Parameter kd_kota wajib diisi dan harus berupa angka');

        $where = "a.kd_kota = " . (int) $kdKota;

        $sql = "SELECT
                    a.id,
                    a.jenis_rth,
                    a.code,
                    -- a.kd_kota,
                    -- b.nama_kabkot AS nama_kabkota,
                    ST_AsGeoJSON(a.geom) AS geometry
                FROM sp_rth a
                LEFT JOIN rf_kabkota b ON b.kd_kota = a.kd_kota
                WHERE {$where}";

        $rows = $this->db->query($sql);

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row['geometry'], true),
                'properties' => [
                    'id' => (int) $row['id'],
                    'jenis_rth' => $row['jenis_rth'],
                    'code' => $row['code'],
                    // 'kd_kota' => (int) $row['kd_kota'],
                    // 'nama_kabkota' => $row['nama_kabkota'],
                ],
            ];
        }

        $this->returnJson(200, [
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    // GET /geojson/rhl/kd_kota/<kd_kota>
    public function rhl()
    {
        $kdKota = $this->params('kd_kota');
        if (!$kdKota || !is_numeric($kdKota)) $this->returnJson(400, null, 'Parameter kd_kota wajib diisi dan harus berupa angka');

        $where = "a.kd_kota = " . (int) $kdKota;

        $sql = "SELECT
                    a.id,
                    a.jenis_rhl,
                    a.code,
                    -- a.kd_kota,
                    -- b.nama_kabkot AS nama_kabkota,
                    ST_AsGeoJSON(a.geom) AS geometry
                FROM sp_rhl a
                LEFT JOIN rf_kabkota b ON b.kd_kota = a.kd_kota
                WHERE {$where}";

        $rows = $this->db->query($sql);

        $features = [];
        foreach ($rows as $row) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row['geometry'], true),
                'properties' => [
                    'id' => (int) $row['id'],
                    'jenis_rhl' => $row['jenis_rhl'],
                    'code' => $row['code'],
                    // 'kd_kota' => (int) $row['kd_kota'],
                    // 'nama_kabkota' => $row['nama_kabkota'],
                ],
            ];
        }

        $this->returnJson(200, [
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
