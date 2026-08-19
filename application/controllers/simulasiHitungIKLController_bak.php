<?php
/**
 * created at 	: 01/10/2020
 * created by 	: dasendria team
 * desc		  	: controller INDEKS KUALITAS LAHAN IKLHK
 *
 */
class simulasiHitungIKLController extends Front
{
    public function init()
    {
      // if($_SERVER['REMOTE_ADDR']!='180.252.94.178') die('sedang development');
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

        $this -> view -> assign("primaryKey", "uid_pelaporan_iktl");
        $this -> viewName = "v_pelaporan_iktl";
        $this -> primaryKey = "uid_pelaporan_iktl";
        $this -> where = "deleted = 0";

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

        $this->dev = "1";

        if($_SERVER['REMOTE_ADDR'] == '103.144.175.184' || $_SERVER['REMOTE_ADDR'] == '180.252.162.145'){
          $this->dev = 1;
        }
        $this->view->assign("dev", $this->dev);
    }

    public function hitungNasional()
    {
      $uid_indeks = $this->params('x');
            $dataIndeks = $this -> tables -> query("SELECT a.* FROM indeks_iktl a WHERE a.uid_indeks_iktl=" . $uid_indeks);
            $tahun[1] = $dataIndeks['data'][0]['tahun'];
            if ($dataIndeks['total']) {
                $dataProvinsi = $this -> tables -> query("SELECT SUM(a.jumlah_penduduk) AS total_penduduk, SUM(a.luas_wilayah) AS total_luas_wilayah FROM rf_provinsi a");
                $sqlNasional = "SELECT a.* ,b.nama_propinsi, b.jumlah_penduduk, b.luas_wilayah,
                          (b.jumlah_penduduk/" . $dataProvinsi['data'][0]['total_penduduk'] . ") AS rasio_jumlah_penduduk,
                          (b.luas_wilayah/" . $dataProvinsi['data'][0]['total_luas_wilayah'] . ") AS rasio_luas_wilayah,
                          ( (b.jumlah_penduduk/" . $dataProvinsi['data'][0]['total_penduduk'] . ") + (b.luas_wilayah/" . $dataProvinsi['data'][0]['total_luas_wilayah'] . ") )/2  AS bobot_provinsi
                          FROM indeks_iktl a
                          LEFT JOIN rf_provinsi b ON a.uid_provinsi = b.kd_propinsi
                          WHERE a.tahun=" . $dataIndeks['data'][0]['tahun'] . " AND a.jenis_indeks = 1";
                $dataIndeksProv = $this -> tables -> query($sqlNasional);
                // $this->debug->show($dataProvinsi);
                $nilai_indeks = 0;
                if ($dataIndeksProv['total']) {
                    foreach ($dataIndeksProv['data'] as $key => $value) {
                        $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_provinsi'];
                        $nilai_indeks_tmp_origin[] = $value['nilai_indeks'];
                    }
                    $nilai_indeks = array_sum($nilai_indeks_tmp);
                    // $nilai_indeks_tmp_origin = array_sum($nilai_indeks_tmp_origin)/;
                }
                // $this->debug->show($nilai_indeks_tmp_origin);
                $this -> tables -> set("indeks_iktl", "uid_indeks_iktl");
                $postIdx['form']['uid_indeks_iktl'] = $post['form']['uid_indeks_iktl'];
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

    public function hitung()
    {//function for counting data pelaporan
      $uid_indeks = $this->params('x');
      $jenis_indeks = $this->params('y');
        $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi, c.nama_kabkot FROM indeks_iktl a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.uid_indeks_iktl=" . $uid_indeks);
        if ($dataIndeks['total']) {
            $dataIndeks = $dataIndeks['data'][0];
            if ($jenis_indeks == 1) {
                $w = " deleted = 0 AND uid_kabkota =" . $dataIndeks['uid_kabkota'];
                $dataReturn = $dataIndeks['nama_kabkot'] . ", Provinsi " . $dataIndeks['nama_propinsi'];
            } elseif ($jenis_indeks == 2) {
                $w = " deleted = 0 AND uid_provinsi =" . $dataIndeks['uid_provinsi'];
                $dataReturn = "Provinsi " . $dataIndeks['nama_propinsi'];
            }
            /*$w .= " AND v_pusat = 1 AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31'";**/
            // $w .= " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat = 1) AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31'";
            // $w .= " AND v_pusat =1 AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31'";
            $w .= " AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31'";
            if ($jenis_indeks == 1) {

                $dataPelaporan = $this -> tables -> query("SELECT * FROM pelaporan_iktl WHERE " . $w);

                $dataUpdate['form']['uid_pelaporan_iktl'] = $dataPelaporan['data'][0]['uid_pelaporan_iktl'];
                $dataUpdate['form']['tl'] = ($dataPelaporan['data'][0]['luas_hutan'] + (($dataPelaporan['data'][0]['luas_belukar_dalam_kawasan'] + $dataPelaporan['data'][0]['luas_belukar_pada_fungsi_lindung'] + $dataPelaporan['data'][0]['kebun_raya_data_lipi'] + $dataPelaporan['data'][0]['rth'] + $dataPelaporan['data'][0]['taman_kehati'] + $dataPelaporan['data'][0]['rhl'] + $dataPelaporan['data'][0]['tutupan_vegetasi']) * 0.6)) / $dataPelaporan['data'][0]['luas_wilayah'];
                $dataUpdate['form']['iktl'] = 100 - ((84.3 - ($dataUpdate['form']['tl'] * 100)) * 50 / 54.3);
                $dataUpdate['form']['tl_dkk'] = $dataUpdate['form']['tl'] - $dataPelaporan['data'][0]['dkk'];
                $dataUpdate['form']['ikl'] = 100 - ((84.3 - ($dataUpdate['form']['tl_dkk'] * 100)) * 50 / 54.3);
                $dataUpdate['submit'] = TRUE;
                $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
                $this->tables->post($dataUpdate);

                $dataPelaporan = $this -> tables -> query("SELECT * FROM pelaporan_iktl WHERE " . $w);
                $nilai_indeks = ($dataPelaporan['total'] ? $dataPelaporan['data'][0]['ikl'] : 0);
                $json_data['luas_wilayah'] = ($dataPelaporan['data'][0]['luas_wilayah'] ? $dataPelaporan['data'][0]['luas_wilayah'] : 0);
                $json_data['luas_hutan'] = ($dataPelaporan['data'][0]['luas_hutan'] ? $dataPelaporan['data'][0]['luas_hutan'] : 0);
                $json_data['luas_belukar_dalam_kawasan'] = ($dataPelaporan['data'][0]['luas_belukar_dalam_kawasan'] ? $dataPelaporan['data'][0]['luas_belukar_dalam_kawasan'] : 0);
                $json_data['luas_belukar_pada_fungsi_lindung'] = ($dataPelaporan['data'][0]['luas_belukar_pada_fungsi_lindung'] ? $dataPelaporan['data'][0]['luas_belukar_pada_fungsi_lindung'] : 0);
                $json_data['kebun_raya_data_lipi'] = ($dataPelaporan['data'][0]['kebun_raya_data_lipi'] ? $dataPelaporan['data'][0]['kebun_raya_data_lipi'] : 0);
                $json_data['rth'] = ($dataPelaporan['data'][0]['rth'] ? $dataPelaporan['data'][0]['rth'] : 0);
                $json_data['taman_kehati'] = ($dataPelaporan['data'][0]['taman_kehati'] ? $dataPelaporan['data'][0]['taman_kehati'] : 0);
                $json_data['rhl'] = ($dataPelaporan['data'][0]['rhl'] ? $dataPelaporan['data'][0]['rhl'] : 0);
                $json_data['tutupan_vegetasi'] = ($dataPelaporan['data'][0]['tutupan_vegetasi'] ? $dataPelaporan['data'][0]['tutupan_vegetasi'] : 0);
                $json_data['dkk'] = ($dataPelaporan['data'][0]['dkk'] ? $dataPelaporan['data'][0]['dkk'] : 0);
                $json_data['tl'] = ($dataPelaporan['data'][0]['tl'] ? $dataPelaporan['data'][0]['tl'] : 0);
                $json_data['iktl'] = ($dataPelaporan['data'][0]['iktl'] ? $dataPelaporan['data'][0]['iktl'] : 0);
                $json_data['tl_dkk'] = ($dataPelaporan['data'][0]['tl_dkk'] ? $dataPelaporan['data'][0]['tl_dkk'] : 0);

            } elseif ($jenis_indeks == 2) {
                $sql = "SELECT
                    SUM(luas_wilayah) AS luas_wilayah,
                    SUM(luas_hutan) AS luas_hutan,
                    SUM(luas_belukar_dalam_kawasan) AS luas_belukar_dalam_kawasan,
                    SUM(luas_belukar_pada_fungsi_lindung) AS luas_belukar_pada_fungsi_lindung,
                    SUM(kebun_raya_data_lipi) AS kebun_raya_data_lipi,
                    SUM(rth) AS rth,
                    SUM(taman_kehati) AS taman_kehati,
                    SUM(rhl) AS rhl,
                    SUM(tutupan_vegetasi) AS tutupan_vegetasi,
                    SUM(dkk) AS dkk
                  FROM pelaporan_iktl
                  WHERE
                  " . $w;
                $dataPelaporan = $this -> tables -> query($sql);
                $json_data['luas_wilayah'] = ($dataPelaporan['data'][0]['luas_wilayah'] ? $dataPelaporan['data'][0]['luas_wilayah'] : 0);
                $json_data['luas_hutan'] = ($dataPelaporan['data'][0]['luas_hutan'] ? $dataPelaporan['data'][0]['luas_hutan'] : 0);
                $json_data['luas_belukar_dalam_kawasan'] = ($dataPelaporan['data'][0]['luas_belukar_dalam_kawasan'] ? $dataPelaporan['data'][0]['luas_belukar_dalam_kawasan'] : 0);
                $json_data['luas_belukar_pada_fungsi_lindung'] = ($dataPelaporan['data'][0]['luas_belukar_pada_fungsi_lindung'] ? $dataPelaporan['data'][0]['luas_belukar_pada_fungsi_lindung'] : 0);
                $json_data['kebun_raya_data_lipi'] = ($dataPelaporan['data'][0]['kebun_raya_data_lipi'] ? $dataPelaporan['data'][0]['kebun_raya_data_lipi'] : 0);
                $json_data['rth'] = ($dataPelaporan['data'][0]['rth'] ? $dataPelaporan['data'][0]['rth'] : 0);
                $json_data['taman_kehati'] = ($dataPelaporan['data'][0]['taman_kehati'] ? $dataPelaporan['data'][0]['taman_kehati'] : 0);
                $json_data['rhl'] = ($dataPelaporan['data'][0]['rhl'] ? $dataPelaporan['data'][0]['rhl'] : 0);
                $json_data['tutupan_vegetasi'] = ($dataPelaporan['data'][0]['tutupan_vegetasi'] ? $dataPelaporan['data'][0]['tutupan_vegetasi'] : 0);
                // $json_data['dkk'] = ($dataPelaporan['data'][0]['dkk'] ? $dataPelaporan['data'][0]['dkk'] : 0);

                $getDkkProvinsi = $this->tables->query("SELECT * FROM nilai_dkk WHERE deleted = 0 AND uid_provinsi=".$dataIndeks['uid_provinsi']." AND tahun=".$dataIndeks['tahun']);
                $json_data['dkk'] = ($getDkkProvinsi['data'][0]['nilai'] ? $getDkkProvinsi['data'][0]['nilai'] : 0);

                $json_data['tl'] = ($json_data['luas_hutan'] + (($json_data['luas_belukar_dalam_kawasan'] + $json_data['luas_belukar_pada_fungsi_lindung'] + $json_data['kebun_raya_data_lipi'] + $json_data['rth'] + $json_data['taman_kehati'] + $json_data['rhl'] + $json_data['tutupan_vegetasi']) * 0.6)) / $json_data['luas_wilayah'];
                if ($json_data['tl']) {
                    $json_data['iktl'] = 100 - ((84.3 - ($json_data['tl'] * 100)) * 50 / 54.3);
                    $json_data['tl_dkk'] = $json_data['tl'] - $json_data['dkk'];
                    $ikl = 100 - ((84.3 - ($json_data['tl_dkk'] * 100)) * 50 / 54.3);
                    $nilai_indeks = ($ikl ? $ikl : 0);
                } else {
                    $json_data['iktl'] = 0;
                    $json_data['tl_dkk'] = 0;
                    $ikl = 0;
                    $nilai_indeks = 0;
                }
            }
            $json_data['nilai_indeks'] = ($nilai_indeks > 100 ? 100 : $nilai_indeks);

            $this -> tables -> set("indeks_iktl", "uid_indeks_iktl");
            $postIdx['form']['uid_indeks_iktl'] = $uid_indeks;
            $postIdx['form']['nilai_indeks'] = ($nilai_indeks > 100 ? 100 : $nilai_indeks);
            $postIdx['form']['json_data'] = json_encode($json_data);
            $postIdx['form']['status_hitung'] = 1;
            echo json_encode(array(
                    "statusCode"=>200,
                    "message"=>"success",
                    "data"=>$json_data
                ));
        }
    }

    public function getDataIndeks()
    {// function get data indeks pelaporan
      $tahunShow = $this->params('x');
        $properties = $this -> _getProperties("indeks_iktl");
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
                $w .= " AND tahun ='" . $post['form']['tahun'] . "'";
            }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        } else {
            $w .= " AND tahun ='" . ACTIVE_YEAR . "'";
            $post['form']['tahun'] = ACTIVE_YEAR;
            $this -> view -> assign("search", $post['form']);
        }
        $this->yearActive = $post['form']['tahun'];
        //PAGING
        $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
        $limit = LIMIT_INDEKS;
        $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iktl a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $data = $this -> tables -> query($sql);
        $All = $this -> db -> query('SELECT count(a.uid_indeks_iktl) as x FROM indeks_iktl a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);
        $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);

        //get Nasional
        $getDataNasional = $this -> tables -> query("SELECT * FROM indeks_iktl WHERE jenis_indeks = 2 AND tahun=" . $post['form']['tahun']);
        // NILI INTERVENSI AOH
        if($post['form']['tahun']==2022){
            $getDataNasional['data'][0]['nilai_indeks'] = 60.72;
            // $this->debug->show($getDataNasional);
        }
        $this -> view -> assign("indeksNasional", $getDataNasional['data'][0]);
        //end

        // $this->debug->show($data);
        $this -> view -> assign("urlVar", $urlVar);
        $this -> view -> assign("totalRow", $totalRow);
        $this -> view -> assign("limit", $limit);
        $this -> view -> assign("page", $offset);
        $this -> view -> assign("view", $data['data']);

        $kabkota = $this -> tables -> query('SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iktl a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.jenis_indeks=0 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $provinsi = $this -> tables -> query('SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iktl a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.jenis_indeks=1 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        //$this->debug->show($w);

        if ($this->params("ex") == "kabkota") {
            $this->expExcel($kabkota, null);
        } elseif ($this->params("ex") == "provinsi") {
            $this->expExcel(null, $provinsi);
        }

        $html = $this->view->fetch('parts/contents/iktl/indeks/simulasi.html');
        echo json_encode(array(
                "statusCode"=>200,
                "message"=>"success",
                "html"=>$html
            ));
    }

    private function _getProperties($model)
    {
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
