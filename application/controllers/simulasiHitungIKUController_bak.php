<?php
/**
 * created at 	: 01/10/2020
 * created by 	: dasendria team
 * desc		  	: controller INDEKS KUALITAS UDARA IKLHK
 *
 */
class simulasiHitungIKUController extends Front
{
    public function init()
    {
        ($this -> session -> get('memberIKLH') ?: $this -> redirect("login"));
        date_default_timezone_set("Asia/Jakarta");

        //SET CUSTOM VIEWS FOLDER
        $this -> view -> setFolder('be');

        //LOAD MODELS
        $this -> loadModel("tables");
        $this -> loadModel("ref");
        $this -> loadModel("users");

        // LOAD FUNCTION
        require_once "functions.php";
        $this -> functions = new functions();
        $this -> view -> assign("functions", $this -> functions);
        require_once "excelReader.php";

        //GLOBAL VAR
        $this -> me = $this -> session -> get('memberIKLH');
        $this -> ctrl = $this -> uri -> getController();
        $this -> act = $this -> uri -> getAction();
        $this -> url = $this -> ctrl . '/' . $this -> act;

        //ASSIGN VAR
        $this -> view -> assign("now", $this -> now = date('Y-m-d'));
        $this -> view -> assign("me", $this -> me);
        $this -> view -> assign("baseUrl", BASEURL);
        $this -> view -> assign("ctrl", $this -> ctrl);
        $this -> view -> assign("act", $this -> act);
        $this -> view -> assign("format", $this -> format);
        $this -> view -> assign("time", time());
        $this -> view -> assign("thisYear", date('Y'));
        $this -> view -> assign("assets", ASSETS);

        $this -> view -> assign("primaryKey", "uid_pelaporan_iku");
        $this -> viewName = "v_pelaporan_iku";
        $this -> primaryKey = "uid_pelaporan_iku";
        $this -> where = "deleted = 0 AND hidden = 0";

        $this->yearActive = ACTIVE_YEAR;

        if(is_numeric($this->me['role_user']) && $this->me['role_user'] <= 1){
    			//admin
    			$this->view->assign("raportShow", 1);
    		}elseif ($this -> me['role_user'] == 3) {
    			//kabkota
    		} elseif ($this -> me['role_user'] == 2) {
    			//provinsi
    		} elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
    			//regional
    			$this->view->assign("raportShow", 1);
    		}

        $this->dev = "";
    		if($_SERVER['REMOTE_ADDR'] == '103.144.175.182'){
    			$this->dev = 1;
    		}
    }

    public function hitungNasional()
    {//menu index perhitungan iku
      $uid_indeks = $this->params('x');
            $dataIndeks = $this -> tables -> query("SELECT a.* FROM indeks_iku a WHERE a.uid_indeks_iku=" . $uid_indeks);
            $tahun[1] = $dataIndeks['data'][0]['tahun'];
            if ($dataIndeks['total']) {
                $dataProvinsi = $this -> tables -> query("SELECT SUM(a.jumlah_penduduk) AS total_penduduk, SUM(a.luas_wilayah) AS total_luas_wilayah FROM rf_provinsi a");
                $sqlNasional = "SELECT a.* ,b.nama_propinsi, b.jumlah_penduduk, b.luas_wilayah,
                          (b.jumlah_penduduk/" . $dataProvinsi['data'][0]['total_penduduk'] . ") AS rasio_jumlah_penduduk,
                          (b.luas_wilayah/" . $dataProvinsi['data'][0]['total_luas_wilayah'] . ") AS rasio_luas_wilayah,
                          ( (b.jumlah_penduduk/" . $dataProvinsi['data'][0]['total_penduduk'] . ") + (b.luas_wilayah/" . $dataProvinsi['data'][0]['total_luas_wilayah'] . ") )/2  AS bobot_provinsi
                          FROM indeks_iku a
                          LEFT JOIN rf_provinsi b ON a.uid_provinsi = b.kd_propinsi
                          WHERE a.tahun=" . $dataIndeks['data'][0]['tahun'] . " AND a.jenis_indeks = 1";
                $dataIndeksProv = $this -> tables -> query($sqlNasional);
                $nilai_indeks = 0;
                if ($dataIndeksProv['total']) {
                    foreach ($dataIndeksProv['data'] as $key => $value) {
                        $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_provinsi'];
                    }
                    $nilai_indeks = array_sum($nilai_indeks_tmp);
                }
                $this -> tables -> set("indeks_iku", "uid_indeks_iku");
                $postIdx['form']['uid_indeks_iku'] = $post['form']['uid_indeks_iku'];
                $postIdx['form']['nilai_indeks'] = $nilai_indeks;
                echo json_encode(array(
                        "statusCode"=>200,
                        "message"=>"success",
                        "data"=>$nilai_indeks
                    ));
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
    }

    public function hitungKabkota()
    {//function for counting data pelaporan
      $jenis_indeks = 1;
      $uid_indeks = $this->params('x');
      $year_indeks = $this->params('y');
      // $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi, c.nama_kabkot FROM v_pelaporan_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.uid_kabkota = " .$uid_indeks. " AND a.tanggal LIKE '%" .$year_indeks. "%'");
            if ($jenis_indeks == 1) {
                $w = " deleted = 0 AND uid_kabkota =" . $uid_indeks;
                $dataReturn = $dataIndeks['nama_kabkot'] . ", Provinsi " . $dataIndeks['nama_propinsi'];

                // new
                $w .= " AND tanggal BETWEEN '" . $year_indeks . "-01-01' AND '" . $year_indeks . "-12-31'";
                // $avgData = $this -> tables -> query("SELECT AVG(CASE WHEN no2 > 0 THEN no2 END) AS avg_no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS avg_so2, peruntukan FROM `v_pelaporan_iku` WHERE " . $w . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat=1) GROUP BY uid_rf_peruntukan");
                // $avgData = $this -> tables -> query("SELECT AVG(CASE WHEN no2 > 0 THEN no2 END) AS avg_no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS avg_so2, peruntukan FROM `v_pelaporan_iku` WHERE " . $w . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat=1) GROUP BY uid_rf_peruntukan");
                // $avgData = $this -> tables -> query("SELECT AVG(CASE WHEN no2 > 0 THEN no2 END) AS avg_no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS avg_so2, peruntukan FROM `v_pelaporan_iku` WHERE " . $w . " AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) GROUP BY uid_rf_peruntukan");
                $avgData = $this -> tables -> query("SELECT AVG(CASE WHEN no2 > 0 THEN no2 END) AS avg_no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS avg_so2, peruntukan FROM `v_pelaporan_iku` WHERE " . $w . " GROUP BY uid_rf_peruntukan");
                // end

                // $this->debug->show($avgData);

                $nilaiParam = null;
                if ($avgData['total']) {
                    foreach ($avgData['data'] as $key => $value) {
                      if($value['avg_no2']){
                        $nilaiParam['no2'][] = $value['avg_no2'];
                      }
                      if($value['avg_so2']){
                        $nilaiParam['so2'][] = $value['avg_so2'];
                      }
                    }
                    $cnIndeks['avgPeruntukanDetail'] = $avgData['data'];
                    $cnIndeks['avgPeruntukan'] = $nilaiParam;
                    $cnIndeks['rpp']['no2'] = array_sum($nilaiParam['no2']) / count($nilaiParam['no2']);
                    $cnIndeks['rpp']['so2'] = array_sum($nilaiParam['so2']) / count($nilaiParam['so2']);
                    $cnIndeks['idbm']['no2'] = $cnIndeks['rpp']['no2'] / 40;
                    $cnIndeks['idbm']['so2'] = $cnIndeks['rpp']['so2'] / 20;
                    $cnIndeks['rataanIndeks'] = array_sum($cnIndeks['idbm']) / count($cnIndeks['idbm']);
                    // $cnIndeks['rataanIndeks'] = round($cnIndeks['rataanIndeks'],2);
                    $cnIndeks['rataanIndeks'] = $cnIndeks['rataanIndeks'];
                    $cnIndeks['indeksIku'] = 100 - (50 / 0.9 * ($cnIndeks['rataanIndeks'] - 0.1));

                    // $this->debug->show($cnIndeks);
                    echo json_encode(array(
                            "statusCode"=>200,
                            "message"=>"success",
                            "data"=>$cnIndeks
                        ));
                }
            }
    }

    public function hitungProv()
    {
      $jenis_indeks = 2;
      $uid_indeks = $this->params('x');
        $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi, c.nama_kabkot FROM indeks_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.uid_indeks_iku=" . $uid_indeks);
        if ($dataIndeks['total']) {
            $dataIndeks = $dataIndeks['data'][0];
            $dataReturn = "Provinsi " . $dataIndeks['nama_propinsi'];

            $dataIndeksKabkota = $this -> tables -> query("SELECT * FROM indeks_iku WHERE deleted=0 AND jenis_indeks = 0 AND uid_provinsi=" . $dataIndeks['uid_provinsi'] . " AND tahun=" . $dataIndeks['tahun']);

            //count rpp
            $nilaiParam['no2'] = null;
            $nilaiParam['so2'] = null;

            if ($dataIndeksKabkota['total']) {
                foreach ($dataIndeksKabkota['data'] as $key => $value) {
                    $dataIndeksKabkota['data'][$key]['json_data'] = json_decode($dataIndeksKabkota['data'][$key]['json_data'], true);
                    $nilaiParam['no2'][] = $dataIndeksKabkota['data'][$key]['json_data']['rpp']['no2'];
                    $nilaiParam['so2'][] = $dataIndeksKabkota['data'][$key]['json_data']['rpp']['so2'];
                }
                $cnIndeks['rpp']['no2'] = array_sum($nilaiParam['no2']) / count($nilaiParam['no2']);
                $cnIndeks['rpp']['so2'] = array_sum($nilaiParam['so2']) / count($nilaiParam['so2']);
                $cnIndeks['idbm']['no2'] = $cnIndeks['rpp']['no2'] / 40;
                $cnIndeks['idbm']['so2'] = $cnIndeks['rpp']['so2'] / 20;
                $cnIndeks['rataanIndeks'] = array_sum($cnIndeks['idbm']) / count($cnIndeks['idbm']);
                $cnIndeks['rataanIndeks'] = $cnIndeks['rataanIndeks'];
                $cnIndeks['indeksIku'] = 100 - (50 / 0.9 * ($cnIndeks['rataanIndeks'] - 0.1));

                echo json_encode(array(
                        "statusCode"=>200,
                        "message"=>"success",
                        "data"=>$cnIndeks
                    ));
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    public function getDataIndeks()
    {// function get data indeks pelaporan
      $tahunShow = $this->params('x');
        $properties = $this -> _getProperties("indeks_iku");
        $urlVar = BASEURL . $this -> url . '/';
        // $w = $this -> where;
        $w = "a.deleted = 0 ";
        $o = "uid_provinsi ASC";

        if ($this -> me['role_user'] == 3) {
            $w .= " AND a.uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND a.uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND b.kd_regional =" . $this -> me['uid_regional'];
        }

        $post = $this -> post();
        if ($this -> params('search')) {
            $post['search'] = true;
            $post['form'] = json_decode(urldecode($this -> params('search')), 1);
        }
        if ($tahunShow) {
            $post['search'] = true;
            $post['form']['tahun'] = $tahunShow;
        }
        if (isset($post['search'])) {
            if ($post['form']['tahun']) {
                $w .= " AND tanggal LIKE '%" . $post['form']['tahun'] . "%'";
            }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        } else {
            $w .= " AND tahun ='" . ACTIVE_YEAR . "'";
            $post['form']['tahun'] = ACTIVE_YEAR;
            $this -> view -> assign("search", $post['form']);
        }
        $this -> yearActive = $post['form']['tahun'];
        //PAGING
        $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
        $limit = LIMIT_INDEKS;
        $sqlKabkota = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM v_pelaporan_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE ' . $w . ' GROUP BY a.uid_kabkota ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $dataKabkota = $this -> tables -> query($sqlKabkota);
        $sqlProv = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM v_pelaporan_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE ' . $w . ' GROUP BY a.uid_provinsi ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $dataProv = $this -> tables -> query($sqlProv);

        //get Nasional
        $getDataNasional = $this -> tables -> query("SELECT * FROM indeks_iku WHERE jenis_indeks = 2 AND tahun=" . $post['form']['tahun']);
        // NILI INTERVENSI AOH
        if($post['form']['tahun']==2022){
            $getDataNasional['data'][0]['nilai_indeks'] = 88.06;
            // $this->debug->show($getDataNasional);
        }
        $this -> view -> assign("indeksNasional", $getDataNasional['data'][0]);
        //end

        // $this->debug->show($dataKabkota);
        $this -> view -> assign("year_indeks", $post['form']['tahun']);
        $this -> view -> assign("viewKabkota", $dataKabkota['data']);
        $this -> view -> assign("viewProv", $dataProv['data']);

        $html = $this->view->fetch('parts/contents/iku/indeks/simulasi.html');
        echo json_encode(array(
                "statusCode"=>200,
                "message"=>"success",
                "html"=>$html
            ));
    }

    private function _getProperties($model)
    {// function get Coloums in table
        $sql = "SHOW COLUMNS FROM " . $model;
        $result = $this -> db -> fetch($sql);
        //$this->debug->show($result);
        if ($result['total']) {
            $data = array();
            foreach ($result['data'] as $key => $val) {
                $data[$key] = $val['Field'];
            }
            $result['data'] = $data;
            return $result;
        } else {
            die('Coloums of table ' . $model . ' not found');
        }
    }
}
