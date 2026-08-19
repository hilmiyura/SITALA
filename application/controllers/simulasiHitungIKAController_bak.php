<?php
/**
 * created at 	: 01/10/2020
 * created by 	: dasendria team
 * desc		  	: controller INDEKS KUALITAS AIR IKLHK
 *
 */
class simulasiHitungIKAController extends Front
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

        $this -> view -> assign("primaryKey", "uid_pelaporan_ika");
        $this -> viewName = "v_pelaporan_ika";
        $this -> primaryKey = "uid_pelaporan_ika";
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
    }

    private function periode($periode = "")
    {
        if ($periode) {
            return $periode;
        } else {
          return 1;
        }
    }

    private function kategori($kategori = "")
    {
        if ($kategori) {
            $kategoriLapor = array("AIR SUNGAI" => 1, "BADAN AIR GAMBUT" => 2, "DANAU/SITU" => 3);
            return $kategoriLapor[$kategori];
        } else {
            return 1;
        }
    }

    public function hitungNasional()
    {
      $uid_indeks = $this->params('x');
        date_default_timezone_set("Asia/Jakarta");

            $dataIndeks = $this -> tables -> query("SELECT a.* FROM indeks_ika a WHERE a.uid_indeks_ika=" . $uid_indeks);
            $tahun[1] = $dataIndeks['data'][0]['tahun'];
            if ($dataIndeks['total']) {
                $dataProvinsi = $this -> tables -> query("SELECT SUM(a.jumlah_penduduk) AS total_penduduk, SUM(a.luas_wilayah) AS total_luas_wilayah FROM rf_provinsi a");
                $sqlNasional = "SELECT a.* ,b.nama_propinsi, b.jumlah_penduduk, b.luas_wilayah,
                          (b.jumlah_penduduk/" . $dataProvinsi['data'][0]['total_penduduk'] . ") AS rasio_jumlah_penduduk,
                          (b.luas_wilayah/" . $dataProvinsi['data'][0]['total_luas_wilayah'] . ") AS rasio_luas_wilayah,
                          ( (b.jumlah_penduduk/" . $dataProvinsi['data'][0]['total_penduduk'] . ") + (b.luas_wilayah/" . $dataProvinsi['data'][0]['total_luas_wilayah'] . ") )/2  AS bobot_provinsi
                          FROM indeks_ika a
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
                $this -> tables -> set("indeks_ika", "uid_indeks_ika");
                $postIdx['form']['uid_indeks_ika'] = $post['form']['uid_indeks_ika'];
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
        $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi, c.nama_kabkot FROM indeks_ika a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.uid_indeks_ika=" . $uid_indeks);
        if ($dataIndeks['total']) {
            $dataIndeks = $dataIndeks['data'][0];
            if ($jenis_indeks == 1) {
                $w = " deleted = 0 AND uid_kabkota =" . $dataIndeks['uid_kabkota'];
                $dataReturn = $dataIndeks['nama_kabkot'] . ", Provinsi " . $dataIndeks['nama_propinsi'];
            } elseif ($jenis_indeks == 2) {
                $w = " deleted = 0 AND uid_provinsi =" . $dataIndeks['uid_provinsi'];
                $dataReturn = "Provinsi " . $dataIndeks['nama_propinsi'];
            }
            // $w .= " AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31' AND v_pusat = 1 ";
            // $w .= " AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31' AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat = 1) ";
            // $w .= " AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31' AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) ";
            $w .= " AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31'";

            // if((ABS(a.ph-(b.ph_max+b.ph_min)/2)) / (b.ph_max-(b.ph_max+b.ph_min)/2) > 1, (1+(5* LOG(ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2))) AS ph_l,
            // foreach ($sumData['data'] as $ki => $vi) {
            //   $sumDataLog[]['ph'] = ($vi['ph'] >1 ? (1+(5*log($vi['ph']))) : $vi['ph']);
            //   $sumDataLog[]['bod'] = ($vi['bod'] >1 ? (1+(5*log($vi['bod']))) : $vi['bod']);
            //   $sumDataLog[]['cod'] = ($vi['cod'] >1 ? (1+(5*log($vi['cod']))) : $vi['cod']);
            //   $sumDataLog[]['tss'] = ($vi['tss'] >1 ? (1+(5*log($vi['tss']))) : $vi['tss']);
            //   $sumDataLog[]['do_max_p'] = ($vi['do_max_p'] >1 ? (1+(5*log($vi['do_max_p']))) : $vi['do_max_p']);
            //   $sumDataLog[]['no3_n'] = ($vi['no3_n'] >1 ? (1+(5*log($vi['no3_n']))) : $vi['no3_n']);
            //   $sumDataLog[]['total_phosphat'] = ($vi['total_phosphat'] >1 ? (1+(5*log($vi['total_phosphat']))) : $vi['total_phosphat']);
            //   $sumDataLog[]['fecal_coliform'] = ($vi['fecal_coliform'] >1 ? (1+(5*log($vi['fecal_coliform']))) : $vi['fecal_coliform']);
            // }

            $sql = "SELECT
          a.kategori,
          if(a.kategori = 2, ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2), ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)) AS ph,

          ABS(a.bod/b.bod) AS bod,
          ABS(a.cod/b.cod) AS cod,
          ABS(a.tss/b.tss) AS tss,
          ABS(a.do_p/b.do) AS do,
          ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do) AS do_max_p,
          ABS(a.no3_n/b.no3_n) AS no3_n,
          ABS(a.total_phosphat/b.total_phosphat) AS total_phosphat,
          ABS(a.fecal_coliform/b.fecal_coliform) AS fecal_coliform,
          ABS(a.kecerahan/b.kecerahan) AS kecerahan,
          ABS(a.klorofil_a/b.klorofil_a) AS klorofil_a,
          ABS(a.total_nitrogen/b.total_nitrogen) AS total_nitrogen,

          if(a.kategori = 2, if((ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2)) > 1, (1+(5* LOG(10,ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2))), if((ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)) > 1, (1+(5* LOG(10,ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)))) AS ph_l,

          if((ABS(a.bod/b.bod)) > 1, (1+(5* LOG(10,ABS(a.bod/b.bod))))  ,(ABS(a.bod/b.bod))) AS bod_l,
          if((ABS(a.cod/b.cod)) > 1, (1+(5* LOG(10,ABS(a.cod/b.cod))))  ,(ABS(a.cod/b.cod))) AS cod_l,
          if((ABS(a.tss/b.tss)) > 1, (1+(5* LOG(10,ABS(a.tss/b.tss))))  ,(ABS(a.tss/b.tss))) AS tss_l,
          if((ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do)) > 1, (1+(5* LOG(10,ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do))))  ,(ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do))) AS do_l,
          if((ABS(a.no3_n/b.no3_n)) > 1, (1+(5* LOG(10,ABS(a.no3_n/b.no3_n))))  ,ABS(a.no3_n/b.no3_n)) AS no3_n_l,
          if((ABS(a.total_phosphat/b.total_phosphat)) > 1, (1+(5* LOG(10,ABS(a.total_phosphat/b.total_phosphat))))  ,(ABS(a.total_phosphat/b.total_phosphat))) AS total_phosphat_l,
          if((ABS(a.fecal_coliform/b.fecal_coliform)) > 1, (1+(5* LOG(10,ABS(a.fecal_coliform/b.fecal_coliform))))  ,(ABS(a.fecal_coliform/b.fecal_coliform))) AS fecal_coliform_l,
          if((ABS(a.kecerahan/b.kecerahan)) > 1, (1+(5* LOG(10,ABS(a.kecerahan/b.kecerahan))))  ,(ABS(a.kecerahan/b.kecerahan))) AS kecerahan_l,
          if((ABS(a.klorofil_a/b.klorofil_a)) > 1, (1+(5* LOG(10,ABS(a.klorofil_a/b.klorofil_a))))  ,(ABS(a.klorofil_a/b.klorofil_a))) AS klorofil_a_l,
          if((ABS(a.total_nitrogen/b.total_nitrogen)) > 1, (1+(5* LOG(10,ABS(a.total_nitrogen/b.total_nitrogen))))  ,(ABS(a.total_nitrogen/b.total_nitrogen))) AS total_nitrogen_l

        FROM v_pelaporan_ika a
        INNER JOIN rf_bma b ON b.uid_rf_bma = a.uid_rf_bma
        WHERE " . $w;
        // $this->debug->show($sql);
            $cnIndeks = $this -> tables -> query($sql);
            $cnIndeks['jumlahTitik']['berat'] = 0;
            $cnIndeks['jumlahTitik']['sedang'] = 0;
            $cnIndeks['jumlahTitik']['ringan'] = 0;
            $cnIndeks['jumlahTitik']['memenuhi'] = 0;
            foreach ($cnIndeks['data'] as $key => $value) {
                $tmpCn['countParams'][$key]['ph_l'] = $value['ph_l'];
                $tmpCn['countParams'][$key]['bod_l'] = $value['bod_l'];
                $tmpCn['countParams'][$key]['cod_l'] = $value['cod_l'];
                $tmpCn['countParams'][$key]['tss_l'] = $value['tss_l'];
                $tmpCn['countParams'][$key]['do_l'] = $value['do_l'];
                $tmpCn['countParams'][$key]['no3_n_l'] = $value['no3_n_l'];
                $tmpCn['countParams'][$key]['total_phosphat_l'] = $value['total_phosphat_l'];
                $tmpCn['countParams'][$key]['fecal_coliform_l'] = $value['fecal_coliform_l'];
                $tmpCn['countParams'][$key]['kecerahan_l'] = $value['kecerahan_l'];
                $tmpCn['countParams'][$key]['klorofil_a_l'] = $value['klorofil_a_l'];
                $tmpCn['countParams'][$key]['total_nitrogen_l'] = $value['total_nitrogen_l'];

                if ($value['kategori'] == 3) {
                    unset($tmpCn['countParams'][$key]['no3_n_l']);
                } else {
                    unset($tmpCn['countParams'][$key]['kecerahan_l']);
                    unset($tmpCn['countParams'][$key]['klorofil_a_l']);
                    unset($tmpCn['countParams'][$key]['total_nitrogen_l']);
                }

                //nilai
                $cnIndeks['data'][$key]['nilai_rataan'] = array_sum($tmpCn['countParams'][$key]) / count($tmpCn['countParams'][$key]);
                $cnIndeks['data'][$key]['nilai_maksimum'] = max($tmpCn['countParams'][$key]);
                $cnIndeks['data'][$key]['nilai_pij'] = sqrt((pow($cnIndeks['data'][$key]['nilai_rataan'], 2) + pow($cnIndeks['data'][$key]['nilai_maksimum'], 2)) / 2);

                if ($cnIndeks['data'][$key]['nilai_pij'] > 10) {
                    $cnIndeks['jumlahTitik']['berat']++;
                    $cnIndeks['data'][$key]['status_mutu'] = "CEMAR BERAT";
                } elseif ($cnIndeks['data'][$key]['nilai_pij'] > 5 && $cnIndeks['data'][$key]['nilai_pij'] <= 10) {
                    $cnIndeks['jumlahTitik']['sedang']++;
                    $cnIndeks['data'][$key]['status_mutu'] = "CEMAR SEDANG";
                } elseif ($cnIndeks['data'][$key]['nilai_pij'] > 1 && $cnIndeks['data'][$key]['nilai_pij'] <= 5) {
                    $cnIndeks['jumlahTitik']['ringan']++;
                    $cnIndeks['data'][$key]['status_mutu'] = "CEMAR RINGAN";
                } elseif ($cnIndeks['data'][$key]['nilai_pij'] >= 0 && $cnIndeks['data'][$key]['nilai_pij'] <= 1) {
                    $cnIndeks['jumlahTitik']['memenuhi']++;
                    $cnIndeks['data'][$key]['status_mutu'] = "MEMENUHI";
                }

                $cnIndeks['nilaiIndeksPerMutu']['memenuhi'] = ($cnIndeks['jumlahTitik']['memenuhi'] / array_sum($cnIndeks['jumlahTitik'])) * 70;
                $cnIndeks['nilaiIndeksPerMutu']['ringan'] = ($cnIndeks['jumlahTitik']['ringan'] / array_sum($cnIndeks['jumlahTitik'])) * 50;
                $cnIndeks['nilaiIndeksPerMutu']['sedang'] = ($cnIndeks['jumlahTitik']['sedang'] / array_sum($cnIndeks['jumlahTitik'])) * 30;
                $cnIndeks['nilaiIndeksPerMutu']['berat'] = ($cnIndeks['jumlahTitik']['berat'] / array_sum($cnIndeks['jumlahTitik'])) * 10;
                //status
            }
            if($_SERVER['REMOTE_ADDR']=='103.144.175.182'){
              // $this->debug->show($cnIndeks);
            }
            $indeksIka = array_sum($cnIndeks['nilaiIndeksPerMutu']);
            $postIdx['form']['uid_indeks_ika'] = $uid_indeks;
            $postIdx['form']['jumlah_titik_memenuhi'] = $cnIndeks['jumlahTitik']['memenuhi'];
            $postIdx['form']['jumlah_titik_ringan'] = $cnIndeks['jumlahTitik']['ringan'];
            $postIdx['form']['jumlah_titik_sedang'] = $cnIndeks['jumlahTitik']['sedang'];
            $postIdx['form']['jumlah_titik_berat'] = $cnIndeks['jumlahTitik']['berat'];
            $postIdx['form']['nilai_mutu_memenuhi'] = $cnIndeks['nilaiIndeksPerMutu']['memenuhi'];
            $postIdx['form']['nilai_mutu_ringan'] = $cnIndeks['nilaiIndeksPerMutu']['ringan'];
            $postIdx['form']['nilai_mutu_sedang'] = $cnIndeks['nilaiIndeksPerMutu']['sedang'];
            $postIdx['form']['nilai_mutu_berat'] = $cnIndeks['nilaiIndeksPerMutu']['berat'];
            $postIdx['form']['nilai_indeks'] = $indeksIka;
            $postIdx['form']['json_data'] = json_encode($cnIndeks['data']);
            $postIdx['form']['status_hitung'] = 1;
            echo json_encode(array(
                    "statusCode"=>200,
                    "message"=>"success",
                    "data"=>$postIdx['form']
                ));
        } else {
            return 0;
        }
    }

    public function _countIndekStatusMutu($idIndeks)
    {
        $this->loadModel('tables');
        // $whereIndeksParams = "deleted= 0 AND uid_pelaporan_ika=".$idIndeks." AND a.ph > 0 AND a.temperatur_air > 0 AND a.tds > 0 AND a.do_p > 0 AND a.tss > 0 AND a.bod > 0 AND a.cod > 0 AND a.nitrit > 0 AND a.no3_n > 0 AND a.amoniak > 0 AND a.total_phosphat > 0 AND a.klorin_bebas > 0 AND a.fenol > 0 AND a.minyak_lemak > 0 AND a.detergen_total > 0 AND a.fecal_coliform > 0 AND a.total_coliform > 0 AND a.sianida > 0 AND a.sulfat > 0 AND a.pb > 0 AND a.cd > 0";
        $whereIndeksParams = "deleted= 0 AND uid_pelaporan_ika=" . $idIndeks;
        $cekData = $this -> tables -> query("SELECT a.uid_pelaporan_ika, YEAR(a.tanggal) AS tahun, a.uid_lokasi_pemantauan FROM pelaporan_ika a WHERE " . $whereIndeksParams);
        if ($cekData['total']) {
            $dataKelas = null;
            $sqlKelas = null;
            for ($i = 1; $i <= 4; $i++) {
                $sql = "SELECT
            a.kategori,
            if(a.kategori = 2, ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2), ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)) AS ph,

            a.temperatur_air AS temperatur_air,
            a.temperatur_udara AS temperatur_udara,
            ABS(a.temperatur_air/a.temperatur_udara) AS temperatur,
            ABS(a.tds/b.tds) AS tds,
            ABS(a.do_p/b.do) AS do,
            ABS(a.tss/b.tss) AS tss,
            ABS(a.bod/b.bod) AS bod,
            ABS(a.cod/b.cod) AS cod,
            ABS(a.nitrit/b.nitrit) AS nitrit,
            ABS(a.no3_n/b.no3_n) AS no3_n,
            ABS(a.amoniak/b.amoniak) AS amoniak,
            ABS(a.total_phosphat/b.total_phosphat) AS total_phosphat,
            ABS(a.klorin_bebas/b.klorin_bebas) AS klorin_bebas,
            ABS(a.fenol/b.fenol) AS fenol,
            ABS(a.minyak_lemak/b.minyak_lemak) AS minyak_lemak,
            ABS(a.detergen_total/b.detergen_total) AS detergen_total,
            ABS(a.fecal_coliform/b.fecal_coliform) AS fecal_coliform,
            ABS(a.total_coliform/b.total_coliform) AS total_coliform,
            ABS(a.sianida/b.sianida) AS sianida,
            ABS(a.sulfat/b.sulfat) AS sulfat,
            ABS(a.pb/b.pb) AS pb,
            ABS(a.cd/b.cd) AS cd,

            if(a.kategori = 2, if((ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2)) > 1, (1+(5* LOG(10,ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min_gambut)/2) / (b.ph_max-(b.ph_max+b.ph_min_gambut)/2))), if((ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)) > 1, (1+(5* LOG(10,ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2))))  ,(ABS(a.ph-(b.ph_max+b.ph_min)/2) / (b.ph_max-(b.ph_max+b.ph_min)/2)))) AS ph_l,

            if((ABS(a.temperatur_air/a.temperatur_udara)) > 1, (1+(5* LOG(10,ABS(a.temperatur_air/a.temperatur_udara))))  ,(ABS(a.temperatur_air/a.temperatur_udara))) AS temperatur_l,

            if((ABS(a.tds/b.tds)) > 1, (1+(5* LOG(10,ABS(a.tds/b.tds))))  ,(ABS(a.tds/b.tds))) AS tds_l,

            if((ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do)) > 1, (1+(5* LOG(10,ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do))))  ,(ABS(((a.do_max_p-a.do_p)/(a.do_max_p-b.do))/b.do))) AS do_l,
            if((ABS(a.tss/b.tss)) > 1, (1+(5* LOG(10,ABS(a.tss/b.tss))))  ,(ABS(a.tss/b.tss))) AS tss_l,
            if((ABS(a.bod/b.bod)) > 1, (1+(5* LOG(10,ABS(a.bod/b.bod))))  ,(ABS(a.bod/b.bod))) AS bod_l,
            if((ABS(a.cod/b.cod)) > 1, (1+(5* LOG(10,ABS(a.cod/b.cod))))  ,(ABS(a.cod/b.cod))) AS cod_l,
            if((ABS(a.nitrit/b.nitrit)) > 1, (1+(5* LOG(10,ABS(a.nitrit/b.nitrit))))  ,ABS(a.nitrit/b.nitrit)) AS nitrit_l,
            if((ABS(a.no3_n/b.no3_n)) > 1, (1+(5* LOG(10,ABS(a.no3_n/b.no3_n))))  ,ABS(a.no3_n/b.no3_n)) AS no3_n_l,
            if((ABS(a.amoniak/b.amoniak)) > 1, (1+(5* LOG(10,ABS(a.amoniak/b.amoniak))))  ,ABS(a.amoniak/b.amoniak)) AS amoniak_l,
            if((ABS(a.total_phosphat/b.total_phosphat)) > 1, (1+(5* LOG(10,ABS(a.total_phosphat/b.total_phosphat))))  ,(ABS(a.total_phosphat/b.total_phosphat))) AS total_phosphat_l,
            if((ABS(a.klorin_bebas/b.klorin_bebas)) > 1, (1+(5* LOG(10,ABS(a.klorin_bebas/b.klorin_bebas))))  ,(ABS(a.klorin_bebas/b.klorin_bebas))) AS klorin_bebas_l,
            if((ABS(a.fenol/b.fenol)) > 1, (1+(5* LOG(10,ABS(a.fenol/b.fenol))))  ,(ABS(a.fenol/b.fenol))) AS fenol_l,
            if((ABS(a.minyak_lemak/b.minyak_lemak)) > 1, (1+(5* LOG(10,ABS(a.minyak_lemak/b.minyak_lemak))))  ,(ABS(a.minyak_lemak/b.minyak_lemak))) AS minyak_lemak_l,
            if((ABS(a.detergen_total/b.detergen_total)) > 1, (1+(5* LOG(10,ABS(a.detergen_total/b.detergen_total))))  ,(ABS(a.detergen_total/b.detergen_total))) AS detergen_total_l,
            if((ABS(a.fecal_coliform/b.fecal_coliform)) > 1, (1+(5* LOG(10,ABS(a.fecal_coliform/b.fecal_coliform))))  ,(ABS(a.fecal_coliform/b.fecal_coliform))) AS fecal_coliform_l,
            if((ABS(a.total_coliform/b.total_coliform)) > 1, (1+(5* LOG(10,ABS(a.total_coliform/b.total_coliform))))  ,(ABS(a.total_coliform/b.total_coliform))) AS total_coliform_l,
            if((ABS(a.sianida/b.sianida)) > 1, (1+(5* LOG(10,ABS(a.sianida/b.sianida))))  ,(ABS(a.sianida/b.sianida))) AS sianida_l,
            if((ABS(a.sulfat/b.sulfat)) > 1, (1+(5* LOG(10,ABS(a.sulfat/b.sulfat))))  ,(ABS(a.sulfat/b.sulfat))) AS sulfat_l,
            if((ABS(a.pb/b.pb)) > 1, (1+(5* LOG(10,ABS(a.pb/b.pb))))  ,(ABS(a.pb/b.pb))) AS pb_l,
            if((ABS(a.cd/b.cd)) > 1, (1+(5* LOG(10,ABS(a.cd/b.cd))))  ,(ABS(a.cd/b.cd))) AS cd_l

          FROM pelaporan_ika a
          INNER JOIN rf_bma b ON b.uid_rf_bma = a.bma_" . $i . " WHERE deleted = 0 AND uid_pelaporan_ika=" . $idIndeks;
                $cnIndeks = $this -> tables -> query($sql);
                $cnIndeks['data'][0]['kelas'] = $i;
                $dataKelas[] = $cnIndeks['data'][0];
                $sqlKelas[] = $sql;
            }
            foreach ($dataKelas as $key => $value) {
                $tmpCn['countParams'][$key]['ph_l'] = $value['ph_l'];

                if ($value['temperatur_air'] && $value['temperatur_udara']) {
                    $tmpCn['countParams'][$key]['temperatur_l'] = $value['temperatur_l'];
                }
                $tmpCn['countParams'][$key]['tds_l'] = $value['tds_l'];
                $tmpCn['countParams'][$key]['do_p_l'] = $value['do_l'];
                $tmpCn['countParams'][$key]['tss_l'] = $value['tss_l'];
                $tmpCn['countParams'][$key]['bod_l'] = $value['bod_l'];
                $tmpCn['countParams'][$key]['cod_l'] = $value['cod_l'];
                $tmpCn['countParams'][$key]['nitrit_l'] = $value['nitrit_l'];
                $tmpCn['countParams'][$key]['no3_n_l'] = $value['no3_n_l'];
                $tmpCn['countParams'][$key]['amoniak_l'] = $value['amoniak_l'];
                $tmpCn['countParams'][$key]['total_phosphat_l'] = $value['total_phosphat_l'];
                $tmpCn['countParams'][$key]['klorin_bebas_l'] = $value['klorin_bebas_l'];
                $tmpCn['countParams'][$key]['fenol_l'] = $value['fenol_l'];
                $tmpCn['countParams'][$key]['minyak_lemak_l'] = $value['minyak_lemak_l'];
                $tmpCn['countParams'][$key]['detergen_total_l'] = $value['detergen_total_l'];
                $tmpCn['countParams'][$key]['fecal_coliform_l'] = $value['fecal_coliform_l'];
                $tmpCn['countParams'][$key]['total_coliform_l'] = $value['total_coliform_l'];
                $tmpCn['countParams'][$key]['sianida_l'] = $value['sianida_l'];
                $tmpCn['countParams'][$key]['sulfat_l'] = $value['sulfat_l'];
                $tmpCn['countParams'][$key]['pb_l'] = $value['pb_l'];
                $tmpCn['countParams'][$key]['cd_l'] = $value['cd_l'];

                if ($value['kelas'] == 4) {
                    unset($tmpCn['countParams'][$key]['nitrit_l']);
                    unset($tmpCn['countParams'][$key]['amoniak_l']);
                    unset($tmpCn['countParams'][$key]['total_phosphat_l']);
                    unset($tmpCn['countParams'][$key]['klorin_bebas_l']);
                    unset($tmpCn['countParams'][$key]['detergen_total_l']);
                    unset($tmpCn['countParams'][$key]['sianida_l']);
                }

                //nilai
                $dataKelasSet[$key]['kelas'] = $value['kelas'];
                $dataKelasSet[$key]['nilai_rataan'] = array_sum($tmpCn['countParams'][$key]) / count($tmpCn['countParams'][$key]);
                $dataKelasSet[$key]['nilai_maksimum'] = max($tmpCn['countParams'][$key]);
                $dataKelasSet[$key]['nilai_pij'] = sqrt((pow($dataKelasSet[$key]['nilai_rataan'], 2) + pow($dataKelasSet[$key]['nilai_maksimum'], 2)) / 2);

                //status
                if ($dataKelasSet[$key]['nilai_pij'] > 10) {
                    $dataKelasSet[$key]['status_mutu'] = "CEMAR BERAT";
                } elseif ($dataKelasSet[$key]['nilai_pij'] > 5 && $dataKelasSet[$key]['nilai_pij'] <= 10) {
                    $dataKelasSet[$key]['status_mutu'] = "CEMAR SEDANG";
                } elseif ($dataKelasSet[$key]['nilai_pij'] > 1 && $dataKelasSet[$key]['nilai_pij'] <= 5) {
                    $dataKelasSet[$key]['status_mutu'] = "CEMAR RINGAN";
                } elseif ($dataKelasSet[$key]['nilai_pij'] >= 0 && $dataKelasSet[$key]['nilai_pij'] <= 1) {
                    $dataKelasSet[$key]['status_mutu'] = "MEMENUHI";
                }

            }

            $update['status_mutu_1'] = $dataKelasSet[0]['status_mutu'];
            $update['status_mutu_2'] = $dataKelasSet[1]['status_mutu'];
            $update['status_mutu_3'] = $dataKelasSet[2]['status_mutu'];
            $update['status_mutu_4'] = $dataKelasSet[3]['status_mutu'];
            $update['status_mutu_detail'] = json_encode($dataKelasSet);
        } else {
            $update['status_mutu_1'] = null;
            $update['status_mutu_2'] = null;
            $update['status_mutu_3'] = null;
            $update['status_mutu_4'] = null;
            $update['status_mutu_detail'] = null;
        }
        if($_SERVER['REMOTE_ADDR']=="103.144.175.182"){
          // $this->debug->show($update);
        }
        return $update;
    }

    public function _countIndekStatusMutuGroup($idTitik, $tahun, $uid_rf_bma, $kategori, $alamat)
    {
        $stepCounting = array("pertitik", "persungai");
        if ($idTitik && $tahun && $alamat) {
            foreach ($stepCounting as $ks => $vs) {
                if ($vs == "pertitik") {
                    // $whereIndeksParams = "deleted= 0 AND v_pusat = 1 AND uid_lokasi_pemantauan=".$idTitik." AND YEAR(tanggal) ='".$tahun."' AND a.ph > 0 AND a.temperatur_air > 0 AND a.tds > 0 AND a.do_p > 0 AND a.tss > 0 AND a.bod > 0 AND a.cod > 0 AND a.nitrit > 0 AND a.no3_n > 0 AND a.amoniak > 0 AND a.total_phosphat > 0 AND a.klorin_bebas > 0 AND a.fenol > 0 AND a.minyak_lemak > 0 AND a.detergen_total > 0 AND a.fecal_coliform > 0 AND a.total_coliform > 0 AND a.sianida > 0 AND a.sulfat > 0 AND a.pb > 0 AND a.cd > 0";
                    // $whereIndeksParams = "deleted= 0 AND v_pusat = 1 AND uid_lokasi_pemantauan=" . $idTitik . " AND YEAR(tanggal) ='" . $tahun . "'";
                    // $whereIndeksParams = "deleted= 0 AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat = 1) AND uid_lokasi_pemantauan=" . $idTitik . " AND YEAR(tanggal) ='" . $tahun . "'";
                    $whereIndeksParams = "deleted= 0 AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) AND uid_lokasi_pemantauan=" . $idTitik . " AND YEAR(tanggal) ='" . $tahun . "'";
                    $cekData = $this -> tables -> query("SELECT a.* FROM v_pelaporan_ika a WHERE " . $whereIndeksParams);
                    if ($cekData['total']) {
                        $totalData = $cekData['total'];
                        $sumKelas = null;
                        $dataKelasSet = null;
                        foreach ($cekData['data'] as $ki => $vi) {
                            $cekData['data'][$ki]['status_mutu_detail'] = json_decode($cekData['data'][$ki]['status_mutu_detail'], true);
                            for ($i = 1; $i <= 4; $i++) {
                                $dataKelasSet[$i]['kelas'] = $i;

                                $idx = array_search($i, array_column($cekData['data'][$ki]['status_mutu_detail'], 'kelas'));
                                $sumKelas[$i][] = (is_numeric($idx) ? $cekData['data'][$ki]['status_mutu_detail'][$idx]['nilai_pij'] : 0);

                                $cnPij = array_sum($sumKelas[$i]) / $cekData['total'];
                                $dataKelasSet[$i]['nilai_pij'] = $cnPij;

                                if ($cnPij > 10) {
                                    $dataKelasSet[$i]['status_mutu'] = "CEMAR BERAT";
                                } elseif ($cnPij > 5 && $cnPij <= 10) {
                                    $dataKelasSet[$i]['status_mutu'] = "CEMAR SEDANG";
                                } elseif ($cnPij > 1 && $cnPij <= 5) {
                                    $dataKelasSet[$i]['status_mutu'] = "CEMAR RINGAN";
                                } elseif ($cnPij >= 0 && $cnPij <= 1) {
                                    $dataKelasSet[$i]['status_mutu'] = "MEMENUHI";
                                }
                            }
                        }

                        $cekIndeks = $this -> tables -> query("SELECT * FROM indeks_ika_sungai WHERE deleted = 0 AND tahun=" . $tahun . " AND uid_lokasi_pemantauan=" . $idTitik);
                        $update['form']['uid_indeks_ika_sungai'] = ($cekIndeks['total'] ? $cekIndeks['data'][0]['uid_indeks_ika_sungai'] : "");
                        $update['form']['uid_lokasi_pemantauan'] = $idTitik;
                        $update['form']['uid_rf_bma'] = $uid_rf_bma;
                        $update['form']['kategori'] = $kategori;
                        $update['form']['tahun'] = $tahun;
                        $update['form']['status_mutu_1'] = ($dataKelasSet[1]['status_mutu'] ? $dataKelasSet[1]['status_mutu'] : null);
                        $update['form']['status_mutu_2'] = ($dataKelasSet[2]['status_mutu'] ? $dataKelasSet[2]['status_mutu'] : null);
                        $update['form']['status_mutu_3'] = ($dataKelasSet[3]['status_mutu'] ? $dataKelasSet[3]['status_mutu'] : null);
                        $update['form']['status_mutu_4'] = ($dataKelasSet[4]['status_mutu'] ? $dataKelasSet[4]['status_mutu'] : null);
                        $update['form']['status_mutu_detail'] = json_encode(array_values($dataKelasSet));
                        $update['submit'] = true;
                        $this -> tables -> set("indeks_ika_sungai", "uid_indeks_ika_sungai");
                        $this -> tables -> post($update);
                    } else {
                        $cekIndeks = $this -> tables -> query("SELECT * FROM indeks_ika_sungai WHERE deleted = 0 AND tahun=" . $tahun . " AND uid_lokasi_pemantauan=" . $idTitik);
                        if ($cekIndeks['total']) {
                            $update['form']['uid_indeks_ika_sungai'] = $cekIndeks['data'][0]['uid_indeks_ika_sungai'];
                            $update['form']['status_mutu_1'] = null;
                            $update['form']['status_mutu_2'] = null;
                            $update['form']['status_mutu_3'] = null;
                            $update['form']['status_mutu_4'] = null;
                            $update['form']['status_mutu_detail'] = null;
                            $update['submit'] = true;
                            $this -> tables -> set("indeks_ika_sungai", "uid_indeks_ika_sungai");
                            $this -> tables -> post($update);
                        }
                    }
                } elseif ($vs == "persungai") {
                    $sqlCekLokasiName = "SELECT alamat , GROUP_CONCAT(uid_lokasi_pemantauan) AS alamat FROM lokasi_pemantauan WHERE deleted = 0 AND uid_rf_component = 2 AND alamat ='" . $alamat . "'";
                    $idLokasi = $this -> tables -> query($sqlCekLokasiName);
                    // $whereIndeksParams = "a.deleted= 0 AND a.v_pusat = 1 AND a.uid_lokasi_pemantauan IN(".$idLokasi['data'][0]['alamat'].") AND YEAR(tanggal) ='".$tahun."' AND a.ph > 0 AND a.temperatur_air > 0 AND a.tds > 0 AND a.do_p > 0 AND a.tss > 0 AND a.bod > 0 AND a.cod > 0 AND a.nitrit > 0 AND a.no3_n > 0 AND a.amoniak > 0 AND a.total_phosphat > 0 AND a.klorin_bebas > 0 AND a.fenol > 0 AND a.minyak_lemak > 0 AND a.detergen_total > 0 AND a.fecal_coliform > 0 AND a.total_coliform > 0 AND a.sianida > 0 AND a.sulfat > 0 AND a.pb > 0 AND a.cd > 0";
                    // $whereIndeksParams = "a.deleted= 0 AND a.v_pusat = 1 AND a.uid_lokasi_pemantauan IN(" . $idLokasi['data'][0]['alamat'] . ") AND YEAR(tanggal) ='" . $tahun . "'";
                    // $whereIndeksParams = "a.deleted= 0 AND (a.v_provinsi = 1 OR a.v_regional = 1 OR a.v_pusat = 1) AND a.uid_lokasi_pemantauan IN(" . $idLokasi['data'][0]['alamat'] . ") AND YEAR(tanggal) ='" . $tahun . "'";
                    $whereIndeksParams = "a.deleted= 0 AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) AND a.uid_lokasi_pemantauan IN(" . $idLokasi['data'][0]['alamat'] . ") AND YEAR(tanggal) ='" . $tahun . "'";
                    $cekData = $this -> tables -> query("SELECT a.* FROM v_pelaporan_ika a WHERE " . $whereIndeksParams);
                    if ($cekData['total']) {
                        $totalData = $cekData['total'];
                        $sumKelas = null;
                        $dataKelasSet = null;
                        foreach ($cekData['data'] as $ki => $vi) {
                            $cekData['data'][$ki]['status_mutu_detail'] = json_decode($cekData['data'][$ki]['status_mutu_detail'], true);
                            for ($i = 1; $i <= 4; $i++) {
                                $dataKelasSet[$i]['kelas'] = $i;

                                $idx = array_search($i, array_column($cekData['data'][$ki]['status_mutu_detail'], 'kelas'));
                                $sumKelas[$i][] = (is_numeric($idx) ? $cekData['data'][$ki]['status_mutu_detail'][$idx]['nilai_pij'] : 0);

                                $cnPij = array_sum($sumKelas[$i]) / $cekData['total'];
                                $dataKelasSet[$i]['nilai_pij'] = $cnPij;

                                if ($cnPij > 10) {
                                    $dataKelasSet[$i]['status_mutu'] = "CEMAR BERAT";
                                } elseif ($cnPij > 5 && $cnPij <= 10) {
                                    $dataKelasSet[$i]['status_mutu'] = "CEMAR SEDANG";
                                } elseif ($cnPij > 1 && $cnPij <= 5) {
                                    $dataKelasSet[$i]['status_mutu'] = "CEMAR RINGAN";
                                } elseif ($cnPij >= 0 && $cnPij <= 1) {
                                    $dataKelasSet[$i]['status_mutu'] = "MEMENUHI";
                                }
                            }
                        }

                        $cekIndeks = $this -> tables -> query("SELECT * FROM indeks_ika_sungai WHERE deleted = 0 AND tahun=" . $tahun . " AND nama_sungai='" . $alamat . "'");
                        $update['form']['uid_indeks_ika_sungai'] = ($cekIndeks['total'] ? $cekIndeks['data'][0]['uid_indeks_ika_sungai'] : "");
                        $update['form']['nama_sungai'] = $alamat;
                        $update['form']['uid_lokasi_pemantauan'] = 0;
                        $update['form']['uid_rf_bma'] = 0;
                        $update['form']['kategori'] = 0;
                        $update['form']['tahun'] = $tahun;
                        $update['form']['status_mutu_1'] = ($dataKelasSet[1]['status_mutu'] ? $dataKelasSet[1]['status_mutu'] : null);
                        $update['form']['status_mutu_2'] = ($dataKelasSet[2]['status_mutu'] ? $dataKelasSet[2]['status_mutu'] : null);
                        $update['form']['status_mutu_3'] = ($dataKelasSet[3]['status_mutu'] ? $dataKelasSet[3]['status_mutu'] : null);
                        $update['form']['status_mutu_4'] = ($dataKelasSet[4]['status_mutu'] ? $dataKelasSet[4]['status_mutu'] : null);
                        $update['form']['status_mutu_detail'] = json_encode(array_values($dataKelasSet));
                        $update['submit'] = true;
                        $this -> tables -> set("indeks_ika_sungai", "uid_indeks_ika_sungai");
                        $this -> tables -> post($update);
                    } else {
                        $cekIndeks = $this -> tables -> query("SELECT * FROM indeks_ika_sungai WHERE deleted = 0 AND tahun=" . $tahun . " AND nama_sungai='" . $alamat . "'");
                        if ($cekIndeks['total']) {
                            $update['form']['uid_indeks_ika_sungai'] = $cekIndeks['data'][0]['uid_indeks_ika_sungai'];
                            $update['form']['status_mutu_1'] = null;
                            $update['form']['status_mutu_2'] = null;
                            $update['form']['status_mutu_3'] = null;
                            $update['form']['status_mutu_4'] = null;
                            $update['form']['status_mutu_detail'] = null;
                            $update['submit'] = true;
                            $this -> tables -> set("indeks_ika_sungai", "uid_indeks_ika_sungai");
                            $this -> tables -> post($update);
                        }
                    }
                }
            }
        }
    }

    public function getDataIndeks()
    {
      $tahunShow = $this->params('x');
        // $properties	= $this->_getProperties("indeks_ika");
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
                $w .= " AND tahun =" . $post['form']['tahun'];
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
        $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_ika a
							LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
							LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota
							WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $data = $this -> tables -> query($sql);
        $All = $this -> db -> query('SELECT count(a.uid_indeks_ika) as x FROM indeks_ika a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);
        $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);

        //get Nasional
        $getDataNasional = $this -> tables -> query("SELECT * FROM indeks_ika WHERE jenis_indeks = 2 AND tahun=" . $post['form']['tahun']);
        // NILI INTERVENSI AOH
        if($post['form']['tahun']==2022){
            $getDataNasional['data'][0]['nilai_indeks'] = 53.88;
            // $this->debug->show($getDataNasional);
        }
        $this -> view -> assign("indeksNasional", $getDataNasional['data'][0]);
        //end
        if ($this->params('debug')==1) {
          $this->debug->show($data);
        }
        $this -> view -> assign("view", $data['data']);

        $kabkota = $this -> tables -> query('SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_ika a
							LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
							LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota
							WHERE a.jenis_indeks=0 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $provinsi = $this -> tables -> query('SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_ika a
							LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
							LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota
							WHERE a.jenis_indeks=1 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);


        if ($this->params("ex") == "kabkota") {
            $this->expExcel($kabkota, null);
        } elseif ($this->params("ex") == "provinsi") {
            $this->expExcel(null, $provinsi);
        }

        $html = $this->view->fetch('parts/contents/ika/indeks/simulasi.html');
        echo json_encode(array(
                "statusCode"=>200,
                "message"=>"success",
                "html"=>$html
            ));
    }

    private function getDataIndeksMutu($tahunShow)
    {
        $this -> tables -> set($this -> viewName, $this -> primaryKey);
        $properties = $this -> _getProperties($this -> viewName);
        $urlVar = BASEURL . $this -> url . '/';
        // $w = $this -> where . " AND v_pusat =1 AND status_mutu_1 IS NOT NULL AND status_mutu_2 IS NOT NULL AND status_mutu_3 IS NOT NULL AND status_mutu_4 IS NOT NULL";
        // $w = $this -> where . " AND (v_provinsi=1 OR v_regional=1 OR v_pusat =1) AND status_mutu_1 IS NOT NULL AND status_mutu_2 IS NOT NULL AND status_mutu_3 IS NOT NULL AND status_mutu_4 IS NOT NULL";
        $w = $this -> where . " AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) AND status_mutu_1 IS NOT NULL AND status_mutu_2 IS NOT NULL AND status_mutu_3 IS NOT NULL AND status_mutu_4 IS NOT NULL";
        if ($this -> me['role_user'] == 3) {
            $w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND kd_regional =" . $this -> me['uid_regional'];
        }
        // $this->debug->show($w);
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
            if ($post['form']['tampil_data'] == 2 || $post['form']['tampil_data'] == 3) {
                // $w = str_replace("v_pusat =1 AND", "", $w);
                // $w = str_replace("(v_provinsi=1 OR v_regional=1 OR v_pusat =1) AND", "", $w);
                $w = str_replace("v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) AND", "", $w);
                $this -> viewName = "v_indeks_ika_sungai";
                $this -> primaryKey = "uid_indeks_ika_sungai";
                $this -> tables -> set($this -> viewName, $this -> primaryKey);
                $properties = $this -> _getProperties($this -> viewName);
                if ($post['form']['tampil_data'] == 2) {
                    $w .= " AND uid_lokasi_pemantauan > 0 ";
                } else {
                    $w .= " AND uid_lokasi_pemantauan = 0 ";
                }
                if ($post['form']['tahun']) {
                    $w .= " AND tahun =" . $post['form']['tahun'];
                }
                if ($post['form']['src_kabkota2']) {
                    $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
                }
                if ($post['form']['src_prop']) {
                    $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
                }
                if ($post['form']['src_reg']) {
                    $w .= " AND kd_regional = " . $post['form']['src_reg'];
                }
            } else {
                if ($post['form']['tahun']) {
                    $w .= " AND YEAR(tanggal) ='" . $post['form']['tahun'] . "'";
                }
                if ($post['form']['src_periode']) {
                  $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
                }
                if ($post['form']['src_level']) {
                    $w .= " AND role_user = " . $post['form']['src_level'];
                }
                if ($post['form']['src_kabkota2']) {
                    $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
                }
                if ($post['form']['src_prop']) {
                    $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
                }
                if ($post['form']['src_reg']) {
                    $w .= " AND kd_regional = " . $post['form']['src_reg'];
                }
            }
            if ($post['form']['keyword']) {
                if ($properties['total']) {
                    $w .= " AND ";
                    $w .= "(";
                    for ($i = 5; $i < $properties['total']; $i++) {
                        $w .= $properties['data'][$i] . " LIKE '%" . $post['form']['keyword'] . "%' OR ";
                    }
                    $w .= $properties['data'][$properties['total'] - 1] . " LIKE '%" . $post['form']['keyword'] . "%' ";
                    $w .= ")";
                }
            }
            if ($post['form']['sungai_show']) {
                $showM = 1;
            }
            $urlVar .= 'xp/showM/search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        } else {
            $w .= " AND YEAR(tanggal) ='" . ACTIVE_YEAR . "'";
            $post['form']['tahun'] = ACTIVE_YEAR;
            $post['form']['tampil_data'] = 1;
            // $this->debug->show($this->viewName);
            $urlVar .= 'xp/showM/search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        }
        $this->yearActive = $post['form']['tahun'];
        $o = ($this -> url == "ika/verifikasi" ? "v_provinsi,v_regional,v_pusat ASC" : $this -> primaryKey . " DESC");
        $search_json = urlencode(json_encode($post['form']));
        $this->view->assign("search_json", $search_json);

        //PAGING
        $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
        $limit = LIMIT;
        $data = $this -> tables -> query('SELECT * FROM ' . $this -> viewName . ' WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $All = $this -> db -> query('SELECT count(' . $this -> primaryKey . ') as x FROM ' . $this -> viewName . ' WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);

        // $this->debug->show($data);

        foreach ($data['data'] as $key => $value) {
            $data['data'][$key]['status_mutu_detail'] = json_decode($data['data'][$key]['status_mutu_detail'], true);
            $idx1 = array_search(1, array_column($data['data'][$key]['status_mutu_detail'], 'kelas'));
            $idx2 = array_search(2, array_column($data['data'][$key]['status_mutu_detail'], 'kelas'));
            $idx3 = array_search(3, array_column($data['data'][$key]['status_mutu_detail'], 'kelas'));
            $idx4 = array_search(4, array_column($data['data'][$key]['status_mutu_detail'], 'kelas'));

            $data['data'][$key]['pij1'] = $data['data'][$key]['status_mutu_detail'][$idx1]['nilai_pij'];
            $data['data'][$key]['pij2'] = $data['data'][$key]['status_mutu_detail'][$idx2]['nilai_pij'];
            $data['data'][$key]['pij3'] = $data['data'][$key]['status_mutu_detail'][$idx3]['nilai_pij'];
            $data['data'][$key]['pij4'] = $data['data'][$key]['status_mutu_detail'][$idx4]['nilai_pij'];
        }

        $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
        $listExport = $this->_getListExport($totalRow);
        $this->view->assign("listExport", $listExport);
        $this -> view -> assign("urlVar", $urlVar);
        $this -> view -> assign("totalRow", $totalRow);
        $this -> view -> assign("limit", $limit);
        $this -> view -> assign("page", $offset);
        if ($this->params('debug')==1) {
          $this->debug->show($data);
        }
        if ($showM) {
            $this -> view -> assign("showM", 1);
            $this -> view -> assign("vSungai", "");
            $this -> view -> assign("showN", "");
            $this -> view -> assign("showProv", "");
        } else {
            $this -> view -> assign("showM", ($this -> params("xp") ? 1 : ""));
        }
        $this -> view -> assign("viewMutu", $data['data']);
    }

    private function rfData()
    {
        if ($this -> me['role_user'] == 2) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
            $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
            $wLokasi = " AND uid_provinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch("deleted=0 AND kd_provinsi=" . $this -> me['uid_provinsi']);
            $this -> view -> assign("kabkota", $rf['data']);
        } elseif ($this -> me['role_user'] == 3) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
            $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
            $wLokasi = " AND uid_kabkota=" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $wProvinsi = "kd_regional =" . $this -> me['uid_regional'];
            $wLokasi = " AND kd_regional =" . $this -> me['uid_regional'];
        } else {
            $wProvinsi = "";
            $wLokasi = "";
        }
        $this -> tables -> set("rf_regional", "kd_regional");
        $rf = $regSelect = $this -> tables -> fetch($wRegional);
        $this -> view -> assign("regional", $rf['data']);

        $this -> tables -> set("rf_provinsi", "kd_propinsi");
        $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
        $this -> view -> assign("provinsi", $rf['data']);

        $this->tables->set("rf_pelaksana","uid_rf_pelaksana");
  			$rf = $this->tables->fetch("deleted = 0");
  			$this->view->assign("pelaksana",$rf['data']);

        $this -> tables -> set("v_lokasi_pemantauan", "uid_lokasi_pemantauan");
        $rf = $this -> tables -> fetch("deleted = 0 AND uid_rf_component = 2" . $wLokasi);
        $this -> view -> assign("lokasi", $rf['data']);

        $this -> tables -> set("rf_kabkota", "kd_kota");
        $rf = $this -> tables -> fetch('deleted = 0');
        $this -> view -> assign("kabkotaSelect2", $rf['data']);

        if ($this->me['role_user']==2) {
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch('deleted=0 AND kd_provinsi='.$this -> me['uid_provinsi']);
            $this -> view -> assign("kabkotaSelect", $rf['data']);
            // $this->debug->show($rf);
        }
        if ($this->me['role_user']==4 || $this -> me['role_user'] == 5) {
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $this -> tables -> fetch('kd_regional='.$this -> me['uid_regional']);
            $this -> view -> assign("propSelect", $rf['data']);
            // $this->debug->show($rf);
        }
        if ($this->me['role_user'] < 2) {
          $this -> view -> assign("regSelect", $regSelect['data']);
            $this -> view -> assign("propSelect", $propSelect['data']);
        }
    }

    public function rekapStatusMutu()
    {
        $tahun = ($this -> params("t") ? $this -> params("t") : ACTIVE_YEAR);
        $tampilData = ($this -> params("td") ? $this -> params("td") : 1);
        if ($tampilData == 1) {
            $tampilDataTxt = "PER PEMANTAUAN";
        } elseif ($tampilData == 2) {
            $tampilDataTxt = "PER TITIK";
        } elseif ($tampilData == 3) {
            $tampilDataTxt = "PER SUNGAI";
        }

        $rekap = null;
        $statusData = 0;

        $wStatusMutu = '';
        if ($this -> me['role_user'] == 2) {
            $wStatusMutu = ' AND uid_provinsi='.$this -> me['uid_provinsi'];
        }
        if ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $wStatusMutu = ' AND kd_regional='.$this -> me['uid_regional'];
        }
        for ($i = 1; $i <= 4; $i++) {
            if ($tampilData == 1) {
                // $sql = "SELECT COUNT(uid_pelaporan_ika) AS jumlah, status_mutu_" . $i . " FROM v_pelaporan_ika WHERE deleted = 0 AND v_pusat = 1 AND YEAR(tanggal)=" . $tahun . $wStatusMutu . " GROUP BY status_mutu_" . $i;
                // $sql = "SELECT COUNT(uid_pelaporan_ika) AS jumlah, status_mutu_" . $i . " FROM v_pelaporan_ika WHERE deleted = 0 AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat = 1) AND YEAR(tanggal)=" . $tahun . $wStatusMutu . " GROUP BY status_mutu_" . $i;
                $sql = "SELECT COUNT(uid_pelaporan_ika) AS jumlah, status_mutu_" . $i . " FROM v_pelaporan_ika WHERE deleted = 0 AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) AND YEAR(tanggal)=" . $tahun . $wStatusMutu . " GROUP BY status_mutu_" . $i;
            } elseif ($tampilData == 2) {
                $sql = "SELECT COUNT(uid_indeks_ika_sungai) AS jumlah, status_mutu_" . $i . " FROM v_indeks_ika_sungai WHERE deleted = 0 AND uid_lokasi_pemantauan > 0 AND status_mutu_" . $i . " IS NOT NULL  AND tahun=" . $tahun . $wStatusMutu . " GROUP BY status_mutu_" . $i;
            } elseif ($tampilData == 3) {
                $sql = "SELECT COUNT(uid_indeks_ika_sungai) AS jumlah, status_mutu_" . $i . " FROM indeks_ika_sungai WHERE deleted = 0 AND uid_lokasi_pemantauan = 0 AND status_mutu_" . $i . " IS NOT NULL  AND tahun=" . $tahun . " GROUP BY status_mutu_" . $i;
            }
            $data = $this -> tables -> query($sql);
            if ($data['total']) {
                $statusData++;
            }
            $idxBerat = array_search('CEMAR BERAT', array_column($data['data'], 'status_mutu_' . $i));
            $idxSedang = array_search('CEMAR SEDANG', array_column($data['data'], 'status_mutu_' . $i));
            $idxRingan = array_search('CEMAR RINGAN', array_column($data['data'], 'status_mutu_' . $i));
            $idxMemenuhi = array_search('MEMENUHI', array_column($data['data'], 'status_mutu_' . $i));
            $rekap['kelas' . $i]['kelas'] = "KELAS-" . $i;
            $rekap['kelas' . $i]['berat'] = (is_numeric($idxBerat) ? $data['data'][$idxBerat]['jumlah'] : 0);
            $rekap['kelas' . $i]['sedang'] = (is_numeric($idxSedang) ? $data['data'][$idxSedang]['jumlah'] : 0);
            $rekap['kelas' . $i]['ringan'] = (is_numeric($idxRingan) ? $data['data'][$idxRingan]['jumlah'] : 0);
            $rekap['kelas' . $i]['memenuhi'] = (is_numeric($idxMemenuhi) ? $data['data'][$idxMemenuhi]['jumlah'] : 0);
        }
        if ($statusData) {
            echo json_encode(array('code' => 1, 'tahun' => $tahun, 'tampil_data' => $tampilDataTxt, 'data' => array_values($rekap)));
        } else {
            echo json_encode(array('code' => 0, 'tahun' => $tahun, 'tampil_data' => $tampilDataTxt, 'data' => array_values($rekap)));
        }
    }

    public function verifikasi()
    {//index verification menu
        $this -> getData();
        $this -> rfData();
        $this -> cekLockSystem(2, $this -> me['uid_users']);
        $this -> view -> assign("verifikasiActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("icons", '<i class="la la-tint"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS AIR');
        $this -> view -> display("index.html");
    }

    public function getCatatanVerifikasi()
    {
        $uid = $this->params("x");
        if (is_numeric($uid)) {
            $data = $this->tables->query("SELECT catatan_verifikator AS catatan FROM ".$this->viewName." WHERE ".$this->primaryKey." = ".$uid);
            echo json_encode(array("statusCode"=>200, "catatan"=>$data['data'][0]['catatan']));
        } else {
            echo json_encode(array("statusCode"=>401));
        }
    }
    public function catatanVerifikasi()
    {
        $dataRequest = file_get_contents("php://input");
        $dataRequest = json_decode($dataRequest, true);

        if (is_numeric($dataRequest['uid'])) {
            $dataUpdate['form']['uid_pelaporan_ika'] = $dataRequest['uid'];
            $dataUpdate['form']['catatan_verifikator'] = $dataRequest['catatan'];
            $dataUpdate['submit'] = true;
            $this->tables->set('pelaporan_ika', 'uid_pelaporan_ika');
            if ($this->tables->post($dataUpdate)) {
                echo 1;
            } else {
                echo 2;
            }
        } else {
            echo 2;
        }
    }

    public function verifikasiAct()
    {// function verification laporan
        $uid = $this -> params("x");
        $field = $this -> params("f");
        $act = $this -> params("act");
        if ($field == "v_provinsi" && ($this -> me['role_user'] != 0 && $this -> me['role_user'] != 2)) {
            die(2);
        }
        if ($field == "v_regional" && ($this -> me['role_user'] != 0 && $this -> me['role_user'] != 4)) {
            die(2);
        }
        if ($field == "v_pusat" && ($this -> me['role_user'] != 0 && $this -> me['role_user'] != 1)) {
            die(2);
        }
        if ($uid && $field && $act) {
            $updateMutu = $this -> _countIndekStatusMutu($uid);
            $this -> tables -> set("pelaporan_ika", "uid_pelaporan_ika");
            $post['form']['uid_pelaporan_ika'] = $uid;
            $post['form'][$field] = $act == 'un' ? 0 : $act;
            $post['form'][$field.'_date'] = date("Y-m-d H:i:s");
            // if($field == 'v_pusat'){
            $post['form']['do_max_p'] = 8.5;
            // }
            $post['form']['status_mutu_1'] = $updateMutu['status_mutu_1'];
            $post['form']['status_mutu_2'] = $updateMutu['status_mutu_2'];
            $post['form']['status_mutu_3'] = $updateMutu['status_mutu_3'];
            $post['form']['status_mutu_4'] = $updateMutu['status_mutu_4'];
            $post['form']['status_mutu_detail'] = $updateMutu['status_mutu_detail'];
            $post['submit'] = true;
            if ($this -> tables -> post($post)) {
                $this -> tables -> set("v_pelaporan_ika", "uid_pelaporan_ika");
                $dataLokasi = $this -> tables -> fetch("uid_pelaporan_ika=" . $uid);
                $this -> generatefieldInIndeks(date("Y", strtotime($dataLokasi['data'][0]['tanggal'])), $dataLokasi['data'][0]['uid_provinsi'], $dataLokasi['data'][0]['uid_kabkota']);
                $this -> _countIndekStatusMutuGroup($dataLokasi['data'][0]['uid_lokasi_pemantauan'], date("Y", strtotime($dataLokasi['data'][0]['tanggal'])), $dataLokasi['data'][0]['uid_rf_bma'], $dataLokasi['data'][0]['kategori'], $dataLokasi['data'][0]['alamat']);
                echo 1;
            } else {
                echo 2;
            }
        } else {
            echo 2;
        }
    }

    public function generatefieldInIndeks($tahun, $uid_provinsi, $uid_kabota)
    {// function for generate field indeks
        $cekDataKabkota = $this -> tables -> query("SELECT uid_indeks_ika FROM indeks_ika WHERE deleted = 0 AND jenis_indeks =0  AND uid_kabkota=" . $uid_kabota . " AND tahun=" . $tahun);
        if (!$cekDataKabkota['total']) {
            $this -> tables -> set("indeks_ika", "uid_indeks_ika");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = $uid_provinsi;
            $postIdx['form']['uid_kabkota'] = $uid_kabota;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
        $cekDataProvinsi = $this -> tables -> query("SELECT uid_indeks_ika FROM indeks_ika WHERE deleted = 0 AND jenis_indeks =1 AND uid_provinsi=" . $uid_provinsi . " AND tahun=" . $tahun);
        if (!$cekDataProvinsi['total']) {
            $this -> tables -> set("indeks_ika", "uid_indeks_ika");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = $uid_provinsi;
            $postIdx['form']['uid_kabkota'] = 0;
            $postIdx['form']['jenis_indeks'] = 1;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
        $cekDataNasional = $this -> tables -> query("SELECT uid_indeks_ika FROM indeks_ika WHERE deleted = 0 AND jenis_indeks =2 AND tahun=" . $tahun);
        if (!$cekDataNasional['total']) {
            $this -> tables -> set("indeks_ika", "uid_indeks_ika");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = 0;
            $postIdx['form']['uid_kabkota'] = 0;
            $postIdx['form']['jenis_indeks'] = 2;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
    }

    private function cekLockSystem($menu, $users)
    {
        $messageLock = null;
        $lockAction = 0;
        $data = $this -> tables -> query("SELECT * FROM rf_lock_system WHERE deleted = 0 AND aktif = 1");
        if ($data['total']) {
            $data['data'][0]['menu'] = explode(",", $data['data'][0]['menu']);
            $data['data'][0]['kabkota'] = explode(",", $data['data'][0]['kabkota']);
            $data['data'][0]['provinsi'] = explode(",", $data['data'][0]['provinsi']);
            $data['data'][0]['p3e'] = explode(",", $data['data'][0]['p3e']);
            if (is_numeric(array_search($menu, $data['data'][0]['menu']))) {
                // $messageLock .= " abaikan pesan, halaman sedang dalam pengembangan";
                if (strtotime($data['data'][0]['tanggal_mulai']) <= strtotime(date('Y-m-d')) && strtotime($data['data'][0]['tanggal_selesai']) >= strtotime(date('Y-m-d'))) {
                  if (is_numeric(array_search($users, $data['data'][0]['kabkota'])) || is_numeric(array_search($users, $data['data'][0]['provinsi'])) || is_numeric(array_search($users, $data['data'][0]['p3e']))) {
                    $lockAction = 0;
                  }else {
                    $messageLock .= "<br>";
                    $messageLock .= "Halaman ini dikunci untuk sementara waktu dari tanggal " . $this -> format -> dateFormat($data['data'][0]['tanggal_mulai']) . " <b>s/d</b> " . $this -> format -> dateFormat($data['data'][0]['tanggal_selesai']);
                    $messageLock .= ($data['data'][0]['deskripsi'] ? '<br>' . $data['data'][0]['deskripsi'] : '');
                    $lockAction = 1;
                  }
                } elseif (strtotime("-3 week", strtotime($data['data'][0]['tanggal_mulai'])) <= strtotime(date('Y-m-d')) && strtotime($data['data'][0]['tanggal_selesai']) >= strtotime(date('Y-m-d'))) {
                    $messageLock .= "<br>";
                    $messageLock .= "Halaman ini akan dikunci untuk sementara waktu dari tanggal " . $this -> format -> dateFormat($data['data'][0]['tanggal_mulai']) . " <b>s/d</b> " . $this -> format -> dateFormat($data['data'][0]['tanggal_selesai']);
                    $messageLock .= ($data['data'][0]['deskripsi'] ? '<br>' . $data['data'][0]['deskripsi'] : '');
                    $lockAction = 0;
                }
            }

            $data['data'][0]['menu_tahunan'] = explode(",", $data['data'][0]['menu_tahunan']);
            $data['data'][0]['tahun'] = explode(",", $data['data'][0]['tahun']);
            if (is_numeric(array_search($menu, $data['data'][0]['menu_tahunan'])) && is_numeric(array_search($this->yearActive, $data['data'][0]['tahun']))) {
                $lockActionYear = $this->yearActive;
            } else {
                $lockActionYear = 0;
            }
        }

        // if ($_SERVER['REMOTE_ADDR'] == '103.144.175.182') {
        //   $lockAction = 0;
        //   $lockActionYear = 0;
        //   $messageLock = null;
        // }
        $this -> view -> assign("messageLock", $messageLock);
        $this -> view -> assign("lockAction", $lockAction);
        $this -> view -> assign("lockActionYear", $lockActionYear);
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
