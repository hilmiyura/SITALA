<?php

  class apiDashboardController extends Front
  {
    public function init()
    {
      $this->loadModel('tables');
      $this->loadModel('users');
      $this->models(['dbcache']);
      $this->cacheAge = 172800; // 48 hour
    }

    public function index(){
      // $this->debug->show($this->users->EncDec("encode", "4p1IKLH2024@#!"));
    }

    private function auth(){
      $X_API_KEY = $this->users->EncDec("decode", $_SERVER['HTTP_X_API_KEY']);
      if($X_API_KEY == "4p1IKLH2024@#!"){

      }else{
        $this->returnJson(401, "Unauthorized");
      }
    }
    private function returnJson($code, $message = "", $rows = null)
    {
        // http_response_code($code);
        // header("Content-type: application/json; charset=utf-8");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, timeout, X-Api-Key");
        header('Access-Control-Allow-Methods: GET, POST, PUT');
        header("Content-Type: application/json; charset=UTF-8");
        if($code != 200){
          echo json_encode(['status' => $code, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }else{
          echo json_encode(['status' => $code, 'message' => $message, 'total'=>$rows['total'], 'rows' => $rows['data']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        exit();
    }

    public function lokasiPemantauan(){
      $this->auth();

      $tahun    = $this->params("tahun");
      $tahun    = ($tahun ? $tahun : date("Y"));
      $type     = $this->params("type");
      $type     = ($type ? $type : 1);
      $provinsi = $this->params("provinsi");
      $kabkota  = $this->params("kabkota");
      $pelaksana  = $this->params("pelaksana");
      $pelaksana     = ($pelaksana ? $pelaksana : 1);
      $w = "a.deleted = 0 AND a.uid_rf_component = 2";
      if($type == 1){
        $cacheName = 'getValues.lokasiPemantauanType1';
        $validCheck = $this->cache->read($cacheName, $this->cacheAge);

        $query = "SELECT a.kode_lokasi,a.alamat,a.alamat_detail,a.uid_provinsi, a.tahun, a.uid_rf_pelaksana, d.kd_regional AS uid_regional, d.ur_regional AS nama_regional, b.nama_propinsi AS nama_provinsi,  a.uid_kabkota, c.nama_kabkot AS nama_kabkota, a.latitude, a.longitude
                  FROM lokasi_pemantauan a
                  LEFT JOIN rf_provinsi b ON a.uid_provinsi = b.kd_propinsi
                  LEFT JOIN rf_kabkota c ON a.uid_kabkota = c.kd_kota
                  LEFT JOIN rf_regional d ON b.kd_regional = d.kd_regional
                  WHERE ".$w ;
        $validFind = $this->dbcache->setAge($this->cacheAge)->setName($cacheName)->setQuery($query);
        $data['data'] = $validFind;

        if($provinsi){
          $data['data'] = array_filter($data['data'], function ($field) use ($provinsi) {
              return $field['uid_provinsi'] == $provinsi;
          });
        }
        if($kabkota){
          $data['data'] = array_filter($data['data'], function ($field) use ($kabkota) {
              return $field['uid_kabkota'] == $kabkota;
          });
        }
        if($tahun){
          $data['data'] = array_filter($data['data'], function ($field) use ($tahun) {
            return in_array($tahun, explode(",",$field['tahun']));

          });
        }
        if($pelaksana){
          $data['data'] = array_filter($data['data'], function ($field) use ($pelaksana) {
            return in_array($pelaksana, explode(",",$field['uid_rf_pelaksana']));

          });
        }
        $data['data'] = array_values($data['data']);
        $data['total'] = count($data['data']);
      }elseif ($type == 2) {

        $cacheName = 'getValues.lokasiPemantauanType2';
        $validCheck = $this->cache->read($cacheName, $this->cacheAge);

        $query = "SELECT COUNT(a.uid_lokasi_pemantauan) AS total, a.uid_kabkota, a.tahun, c.nama_kabkot AS nama_kabkota, c.latitude, c.longitude
                  a.kode_lokasi,a.alamat,a.alamat_detail
                  FROM lokasi_pemantauan a
                  LEFT JOIN rf_provinsi b ON a.uid_provinsi = b.kd_propinsi
                  LEFT JOIN rf_kabkota c ON a.uid_kabkota = c.kd_kota
                  WHERE ".$w." GROUP BY a.uid_kabkota" ;

        $validFind = $this->dbcache->setAge($this->cacheAge)->setName($cacheName)->setQuery($query);
        $data['data'] = $validFind;

        if($provinsi){
          $data['data'] = array_filter($data['data'], function ($field) use ($provinsi) {
              return $field['uid_provinsi'] == $provinsi;
          });
        }
        if($kabkota){
          $data['data'] = array_filter($data['data'], function ($field) use ($kabkota) {
              return $field['uid_kabkota'] == $kabkota;
          });
        }
        if($tahun){
          $data['data'] = array_filter($data['data'], function ($field) use ($tahun) {
            return in_array($tahun, explode(",",$field['tahun']));

          });
        }
        $data['data'] = array_values($data['data']);
        $data['total'] = count($data['data']);
      }elseif ($type == 3) {
        $cacheName = 'getValues.lokasiPemantauanType3';
        $validCheck = $this->cache->read($cacheName, $this->cacheAge);

        $query = "SELECT COUNT(a.uid_lokasi_pemantauan) AS total, a.uid_provinsi, b.nama_propinsi AS nama_provinsi, a.tahun, b.latitude, b.longitude
                  a.kode_lokasi,a.alamat,a.alamat_detail
                  FROM lokasi_pemantauan a
                  LEFT JOIN rf_provinsi b ON a.uid_provinsi = b.kd_propinsi
                  WHERE ".$w." GROUP BY a.uid_provinsi" ;
        $validFind = $this->dbcache->setAge($this->cacheAge)->setName($cacheName)->setQuery($query);
        $data['data'] = $validFind;

        if($provinsi){
          $data['data'] = array_filter($data['data'], function ($field) use ($provinsi) {
              return $field['uid_provinsi'] == $provinsi;
          });
        }
        if($tahun){
          $data['data'] = array_filter($data['data'], function ($field) use ($tahun) {
            return in_array($tahun, explode(",",$field['tahun']));

          });
        }
        $data['data'] = array_values($data['data']);
        $data['total'] = count($data['data']);
      }

      $this->returnJson(200,"Success", $data);
    }
    public function ratingIndeks(){
      $this->auth();

      $cacheName = 'getValues.ratingIndeks';
      $validCheck = $this->cache->read($cacheName, $this->cacheAge);
      $query = "SELECT * FROM rf_rating_iklh WHERE deleted = 0";
      $validFind = $this->dbcache->setAge($this->cacheAge)->setName($cacheName)->setQuery($query);
      $data['data'] = $validFind;
      $data['data'] = array_values($data['data']);
      $data['total'] = count($data['data']);
      $this->returnJson(200,"Success", $data);
    }
    public function indeks(){
      $this->auth();

      $tahun = $this->params("tahun");
      $tahun    = ($tahun ? $tahun : date("Y"));
      $type = $this->params("type");
      $provinsi = $this->params("provinsi");
      $kabkota = $this->params("kabkota");
      $w = "a.deleted = 0";

      $cacheName = 'getValues.lokasiIndeksIka';
      $validCheck = $this->cache->read($cacheName, $this->cacheAge);

      $query = "SELECT
                a.nilai_indeks, round(a.nilai_indeks,2) AS nilai_indeks_format,
                a.jumlah_titik_memenuhi, a.jumlah_titik_ringan, a.jumlah_titik_sedang, a.jumlah_titik_berat,
                a.nilai_mutu_memenuhi, a.nilai_mutu_ringan, a.nilai_mutu_sedang, a.nilai_mutu_berat,
                if(a.nilai_indeks < 25, 'sangat kurang', if(a.nilai_indeks < 50 , 'kurang' , if(a.nilai_indeks < 70 , 'sedang' , if(a.nilai_indeks < 90 , 'baik' , if(a.nilai_indeks <= 100 , 'sangat baik', '-'))))) AS rating,
                if(a.nilai_indeks < 25, '#464855', if(a.nilai_indeks < 50 , '#fa626b' , if(a.nilai_indeks < 70 , '#fdb901' , if(a.nilai_indeks < 90 , '#28afd0' , if(a.nilai_indeks <= 100 , '#5ed84f', '#5ed84f'))))) AS rating_color,
                a.jenis_indeks,a.tahun, a.uid_provinsi, b.nama_propinsi AS nama_provinsi, a.uid_kabkota, c.nama_kabkot AS nama_kabkota, b.latitude AS latitude_provinsi, b.longitude AS longitude_provinsi , c.latitude AS latitude_kabkota, c.longitude AS longitude_kabkota
                FROM indeks_ika a
                LEFT JOIN rf_provinsi b ON a.uid_provinsi = b.kd_propinsi
                LEFT JOIN rf_kabkota c ON a.uid_kabkota = c.kd_kota
                WHERE ".$w;

      // $data = $this->db->fetch($query);
      // $this->debug->show($data);
      $validFind = $this->dbcache->setAge($this->cacheAge)->setName($cacheName)->setQuery($query);
      $data['data'] = $validFind;

      if($type){
        $type = ($type == 1 ? 0 : 1);
        $data['data'] = array_filter($data['data'], function ($field) use ($type) {
            return $field['jenis_indeks'] == $type;
        });
      }
      if($provinsi){
        $data['data'] = array_filter($data['data'], function ($field) use ($provinsi) {
            return $field['uid_provinsi'] == $provinsi;
        });
      }
      if($kabkota){
        $data['data'] = array_filter($data['data'], function ($field) use ($kabkota) {
            return $field['uid_kabkota'] == $kabkota;
        });
      }
      if($tahun){
        $data['data'] = array_filter($data['data'], function ($field) use ($tahun) {
          return in_array($tahun, explode(",",$field['tahun']));

        });
      }

      $data['data'] = array_values($data['data']);
      $data['total'] = count($data['data']);

      $this->returnJson(200,"Success", $data);

    }
    public function  sumary(){
      $this->auth();

      $tahun    = $this->params("tahun");
      $tahun    = ($tahun ? $tahun : date("Y"));

      $cacheName = 'getValues.sumary';
      $validCheck = $this->cache->read($cacheName, $this->cacheAge);
      if($validCheck){
        $data['data'] = $validCheck;
      }else{
        $w = "deleted = 0";
        //1. TOTAL DATA
        $query 			= 'SELECT COUNT(uid_pelaporan_ika) AS total, YEAR(tanggal) AS tahun FROM v_pelaporan_ika WHERE '.$w." AND YEAR(tanggal) >= 2015 GROUP BY YEAR(tanggal)";
        $validFind = $this->db->fetch($query);
        $total_data = $validFind;

        // 2. VERIFIED
        $query 			= 'SELECT COUNT(uid_pelaporan_ika) AS total, YEAR(tanggal) AS tahun  FROM v_pelaporan_ika WHERE '.$w.' AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) AND YEAR(tanggal) >= 2015 GROUP BY YEAR(tanggal)';
        $validFind = $this->db->fetch($query);
        $verifed_data = $validFind;

        // 3. REJECTED
        $query 		= 'SELECT COUNT(uid_pelaporan_ika) AS total, YEAR(tanggal) AS tahun FROM v_pelaporan_ika WHERE '.$w." AND v_reject_status = 1 AND YEAR(tanggal) >= 2015 GROUP BY YEAR(tanggal)";
        $validFind = $this->db->fetch($query);
        $rejected_data = $validFind;

        foreach ($total_data['data'] as $key => $value) {
          $idxVerifed = array_search($value['tahun'], array_column($verifed_data['data'], 'tahun'));
          $idxRejected = array_search($value['tahun'], array_column($rejected_data['data'], 'tahun'));

          $total_data['data'][$key]['verifed'] = (is_numeric($idxVerifed) ? $verifed_data['data'][$idxVerifed]['total'] : 0);
          $total_data['data'][$key]['rejected'] = (is_numeric($idxRejected) ? $rejected_data['data'][$idxRejected]['total'] : 0);

          $total_data['data'][$key]['tahun'] = (int)$total_data['data'][$key]['tahun'];
          $total_data['data'][$key]['total'] = (int)$total_data['data'][$key]['total'];
          $total_data['data'][$key]['verifed'] = (int)$total_data['data'][$key]['verifed'];
          $total_data['data'][$key]['rejected'] = (int)$total_data['data'][$key]['rejected'];
          $data['data'] = $this->cache->write($cacheName, $total_data['data']);
        }
      }

      if($tahun){
        $data['data'] = array_filter($data['data'], function ($field) use ($tahun) {
          return $field['tahun'] == $tahun;
        });
      }

      $data['data'] = array_values($data['data']);
      $data['total'] = count($data['data']);

      $this->returnJson(200,"Success", $data);
    }

    public function statusMutu(){
      $this->auth();

      $tahun    = $this->params("tahun");
      $tahun    = ($tahun ? $tahun : date("Y"));
      $type     = $this->params("type"); //1 pemantauan | 2 titik | 3 sungai
      $type     = ($type ? $type : 1);
      $lv     = $this->params("lv"); //1 pusat | 2 prov | 3 kabkota
      $lv     = ($lv ? $lv : 1);
      $regional = $this->params("regional");
      $provinsi = $this->params("provinsi");
      $kabkota  = $this->params("kabkota");
      $pelaksana  = $this->params("pelaksana");
      // $pelaksana     = ($pelaksana ? $pelaksana : 1);

      if($type == 1){
        $cacheName = 'getValues.statusMutuPemantauan';
        $validCheck = $this->cache->read($cacheName, $this->cacheAge);

        $w = "deleted = 0  AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) AND status_mutu_1 IS NOT NULL AND status_mutu_2 IS NOT NULL AND status_mutu_3 IS NOT NULL AND status_mutu_4 IS NOT NULL";
        $query = "SELECT uid_pelaporan_ika,role_user,tanggal,periode_pemantauan,uid_lokasi_pemantauan,
                  v_provinsi,v_regional,v_pusat,v_reject_status,v_provinsi_date,v_regional_date,v_pusat_date,
                  status_mutu_1,status_mutu_2,status_mutu_3,status_mutu_4,
                  alamat,kode_lokasi,alamat_detail,uid_rf_pelaksana,uid_provinsi,uid_kabkota,kd_regional,nama_provinsi,nama_kabkota,latitude,longitude,
                  tanggal, status_mutu_detail FROM v_pelaporan_ika WHERE ".$w."";

        $validFind = $this->dbcache->setAge($this->cacheAge)->setName($cacheName)->setQuery($query,['status_mutu_detail']);
        $data['data'] = $validFind;

      }elseif ($type == 2) {
        if($lv==1){
          $cacheName = 'getValues.statusMutuTitik';
          $groupby   = "" ;
        }else if($lv==2){
          $cacheName = 'getValues.statusMutuTitik2';
          $groupby   = " GROUP BY uid_provinsi" ;
        }elseif($lv==3){
          $cacheName = 'getValues.statusMutuTitik3';
          $groupby   = " GROUP BY uid_kabkota" ;
        }
        $cacheName = $cacheName;
        $validCheck = $this->cache->read($cacheName, $this->cacheAge);

        $w = "deleted = 0 AND status_mutu_1 IS NOT NULL AND status_mutu_2 IS NOT NULL AND status_mutu_3 IS NOT NULL AND status_mutu_4 IS NOT NULL";
        $query = "SELECT * FROM v_indeks_ika_sungai WHERE ".$w." AND uid_lokasi_pemantauan > 0 ".$groupby;

        $validFind = $this->dbcache->setAge($this->cacheAge)->setName($cacheName)->setQuery($query,['status_mutu_detail']);
        $data['data'] = $validFind;
      }elseif ($type == 3){
        $cacheName = 'getValues.statusMutuSungai';
        $validCheck = $this->cache->read($cacheName, $this->cacheAge);

        $w = "deleted = 0 AND status_mutu_1 IS NOT NULL AND status_mutu_2 IS NOT NULL AND status_mutu_3 IS NOT NULL AND status_mutu_4 IS NOT NULL";
        $query = "SELECT * FROM v_indeks_ika_sungai WHERE ".$w." AND uid_lokasi_pemantauan = 0";

        $validFind = $this->dbcache->setAge($this->cacheAge)->setName($cacheName)->setQuery($query,['status_mutu_detail']);
        $data['data'] = $validFind;

      }

      if($regional){
        $data['data'] = array_filter($data['data'], function ($field) use ($regional) {
            return $field['kd_regional'] == $regional;
        });
      }

      if($provinsi){
        $data['data'] = array_filter($data['data'], function ($field) use ($provinsi) {
            return $field['uid_provinsi'] == $provinsi;
        });
      }
      if($kabkota){
        $data['data'] = array_filter($data['data'], function ($field) use ($kabkota) {
            return $field['uid_kabkota'] == $kabkota;
        });
      }
      if($pelaksana){
        $data['data'] = array_filter($data['data'], function ($field) use ($pelaksana) {
            return $field['uid_rf_pelaksana'] == $pelaksana;
        });
      }
      if($tahun){
        if($type == 1){
          $data['data'] = array_filter($data['data'], function ($field) use ($tahun) {
            return (int)date("Y",strtotime($field['tanggal'])) == $tahun;
          });
        }else{
          $data['data'] = array_filter($data['data'], function ($field) use ($tahun) {
            return $field['tahun'] == $tahun;
          });
        }
      }

      $data['data'] = array_values($data['data']);
      foreach ($data['data'] as $key => $value) {
        $idx1 = array_search(1, array_column($data['data'][$key]['status_mutu_detail'], 'kelas'));
        $idx2 = array_search(2, array_column($data['data'][$key]['status_mutu_detail'], 'kelas'));
        $idx3 = array_search(3, array_column($data['data'][$key]['status_mutu_detail'], 'kelas'));
        $idx4 = array_search(4, array_column($data['data'][$key]['status_mutu_detail'], 'kelas'));

        $data['data'][$key]['pij1'] = $data['data'][$key]['status_mutu_detail'][$idx1]['nilai_pij'];
        $data['data'][$key]['pij2'] = $data['data'][$key]['status_mutu_detail'][$idx2]['nilai_pij'];
        $data['data'][$key]['pij3'] = $data['data'][$key]['status_mutu_detail'][$idx3]['nilai_pij'];
        $data['data'][$key]['pij4'] = $data['data'][$key]['status_mutu_detail'][$idx4]['nilai_pij'];
        unset($data['data'][$key]['status_mutu_detail']);
      }
      $data['total'] = count($data['data']);

      $this->returnJson(200,"Success", $data);

    }
  }
?>
