<?php
/**
 * created at 	: 01/10/2020
 * created by 	: dasendria team
 * desc		  	: controller INDEKS KUALITAS AIR LAUT IKLHK
 *
 */
class simulasiHitungIKALController extends Front
{
    public function init()
    {
      // ini_set("display_errors",1);
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

        $this -> view -> assign("primaryKey", "uid_pelaporan_ikal");
        $this -> viewName = "v_pelaporan_ikal";
        $this -> primaryKey = "uid_pelaporan_ikal";
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

    private function cekLocation($where = "", $vals = array(), $post = array())
    {
        $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
        $cekData = $this -> tables -> fetch($where);
        if ($cekData["total"]) {
            return $cekData['data'][0]['uid_lokasi_pemantauan'];
        } else {
          return 0;
            // $push_location['form']['cruser'] = $post['cruser'];
            // $push_location['form']['kode_lokasi'] = $vals[2];
            // $push_location['form']['periode_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[3]);
            // $push_location['form']['alamat_detail'] = $vals[3];
            // if ($this -> me['role_user'] == 2) {
            //     $post['uid_provinsi'] = $this -> me['uid_provinsi'];
            // }
            // $push_location['form']['uid_provinsi'] = $post['uid_provinsi'];
            // $push_location['form']['uid_kabkota'] = $post['uid_kabkota'];
            // $push_location['form']['latitude'] = str_replace(",", ".", $vals[4]);
            // $push_location['form']['longitude'] = str_replace(",", ".", $vals[5]);
            // $push_location['form']['uid_rf_component'] = 5;
            // $push_location['submit'] = true;
            // if ($this -> tables -> post($push_location)) {
            //     return $this -> tables -> lastInsertID();
            // }
        }
    }

    private function cekPeruntukan($nama)
    {
        $cek = $this -> tables -> query("SELECT * FROM rf_peruntukan WHERE deleted=0 AND peruntukan = 2 AND nama='" . $nama . "'");
        if ($cek['total']) {
            return $cek['data'][0]['uid_rf_peruntukan'];
        } else {
            return 99;
            // $postPeruntukan['form']['nama'] = $nama;
            // $postPeruntukan['form']['peruntukan'] = 2;
            // $postPeruntukan['submit'] = true;
            // $this -> tables -> set("rf_peruntukan", "uid_rf_peruntukan");
            // if ($this -> tables -> post($postPeruntukan)) {
            //     return $this -> tables -> lastInsertID();
            // }
        }
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

        $this -> tables -> set("rf_peruntukan", "uid_rf_peruntukan");
        $rf = $this -> tables -> fetch("deleted = 0 AND peruntukan = 2");
        $this -> view -> assign("peruntukan", $rf['data']);

        $this -> tables -> set("v_lokasi_pemantauan", "uid_lokasi_pemantauan");
        $rf = $this -> tables -> fetch("deleted = 0 AND uid_rf_component = 5" . $wLokasi);
        $this -> view -> assign("lokasi", $rf['data']);
        // $this->debug->show($rf);

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

    public function hitungNasional()
    {
      $uid_indeks = $this->params('x');
            $dataIndeks = $this -> tables -> query("SELECT a.* FROM indeks_ikal a WHERE a.uid_indeks_ikal=" . $uid_indeks);
            $tahun[1] = $dataIndeks['data'][0]['tahun'];
            if ($dataIndeks['total']) {
                $dataProvinsi = $this -> tables -> query("SELECT SUM(a.jumlah_penduduk) AS total_penduduk, SUM(a.luas_wilayah) AS total_luas_wilayah FROM rf_provinsi a");
                $sqlNasional = "SELECT a.* ,b.nama_propinsi, b.jumlah_penduduk, b.luas_wilayah,
                          (b.jumlah_penduduk/" . $dataProvinsi['data'][0]['total_penduduk'] . ") AS rasio_jumlah_penduduk,
                          (b.luas_wilayah/" . $dataProvinsi['data'][0]['total_luas_wilayah'] . ") AS rasio_luas_wilayah,
                          ( (b.jumlah_penduduk/" . $dataProvinsi['data'][0]['total_penduduk'] . ") + (b.luas_wilayah/" . $dataProvinsi['data'][0]['total_luas_wilayah'] . ") )/2  AS bobot_provinsi
                          FROM indeks_ikal a
                          LEFT JOIN rf_provinsi b ON a.uid_provinsi = b.kd_propinsi
                          WHERE a.tahun=" . $dataIndeks['data'][0]['tahun'] . " AND a.jenis_indeks = 1";
                // $this->debug->show($sqlNasional);
                $dataIndeksProv = $this -> tables -> query($sqlNasional);
                $nilai_indeks = 0;
                if ($dataIndeksProv['total']) {
                    foreach ($dataIndeksProv['data'] as $key => $value) {
                        $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_provinsi'];
                    }
                    $nilai_indeks = array_sum($nilai_indeks_tmp);
                }
                $this -> tables -> set("indeks_ikal", "uid_indeks_ikal");
                $postIdx['form']['uid_indeks_ikal'] = $post['form']['uid_indeks_ikal'];
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

    public function hitungProv($jenis_indeks)
    {//function for counting data pelaporan
      $uid_indeks = $this->params('x');
        $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi FROM indeks_ikal a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE a.uid_indeks_ikal=" . $uid_indeks);
        if ($dataIndeks['total']) {
            $cnLokasi = $this -> _countIndeksLokasi($dataIndeks['data'][0]['uid_provinsi'], $dataIndeks['data'][0]['tahun']);
            if ($cnLokasi) {
                $cnProvinsi = $this -> _countIndeksProvinsi($dataIndeks['data'][0]['uid_provinsi'], $dataIndeks['data'][0]['tahun']);
                // return "Provinsi " . $dataIndeks['data'][0]['nama_propinsi'] . " tahun " . $dataIndeks['data'][0]['tahun'];
                echo json_encode(array(
                  "statusCode"=>200,
                  "message"=>"success",
                  "data"=>$cnProvinsi
                ));
            } else {
                $cnProvinsi = $this -> _countIndeksProvinsi($dataIndeks['data'][0]['uid_provinsi'], $dataIndeks['data'][0]['tahun']);
                // return "Provinsi " . $dataIndeks['data'][0]['nama_propinsi'] . " tahun " . $dataIndeks['data'][0]['tahun'] . " <br><b>Note:</b>silahkan hitung ulang karena ada kegagalan perhitungan per lokasi";
                echo json_encode(array(
                  "statusCode"=>200,
                  "message"=>"success",
                  "data"=>$cnProvinsi
                ));
            }
        }
    }

    private function _countIndeksLokasi($uid_provinsi, $tahun)
    {
        /*$sqlAvg = "SELECT YEAR(tanggal) AS tahun, uid_lokasi_pemantauan, AVG(tss) AS tss, AVG(do_p) AS do_p, AVG(minyak_dan_lemak) AS minyak_dan_lemak, AVG(amonia_total) AS amonia_total, AVG(orto_fosfat) AS orto_fosfat FROM v_pelaporan_ikal WHERE YEAR(tanggal) ='" . $tahun . "' AND uid_provinsi=" . $uid_provinsi . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat = 1) GROUP BY uid_lokasi_pemantauan";**/
        // $sqlAvg = "SELECT YEAR(tanggal) AS tahun, uid_lokasi_pemantauan, AVG(tss) AS tss, AVG(do_p) AS do_p, AVG(minyak_dan_lemak) AS minyak_dan_lemak, AVG(amonia_total) AS amonia_total, AVG(orto_fosfat) AS orto_fosfat FROM v_pelaporan_ikal WHERE deleted= 0 AND deleted_lokasi = 0 AND YEAR(tanggal) ='" . $tahun . "' AND uid_provinsi=" . $uid_provinsi . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat = 1) GROUP BY uid_lokasi_pemantauan";
        // $sqlAvg = "SELECT YEAR(tanggal) AS tahun, uid_lokasi_pemantauan, AVG(tss) AS tss, AVG(do_p) AS do_p, AVG(minyak_dan_lemak) AS minyak_dan_lemak, AVG(amonia_total) AS amonia_total, AVG(orto_fosfat) AS orto_fosfat FROM v_pelaporan_ikal WHERE deleted= 0 AND deleted_lokasi = 0 AND YEAR(tanggal) ='" . $tahun . "' AND uid_provinsi=" . $uid_provinsi . " AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) GROUP BY uid_lokasi_pemantauan";
        $sqlAvg = "SELECT YEAR(tanggal) AS tahun, uid_lokasi_pemantauan, AVG(tss) AS tss, AVG(do_p) AS do_p, AVG(minyak_dan_lemak) AS minyak_dan_lemak, AVG(amonia_total) AS amonia_total, AVG(orto_fosfat) AS orto_fosfat FROM v_pelaporan_ikal WHERE deleted= 0 AND deleted_lokasi = 0 AND YEAR(tanggal) ='" . $tahun . "' AND uid_provinsi=" . $uid_provinsi . " GROUP BY uid_lokasi_pemantauan";
        $avgData = $this -> tables -> query($sqlAvg);
        $cnPost = 0;

        // foreach ($avgData['data'] as $key => $value) {
        // 	$idIn[]=$value['uid_lokasi_pemantauan'];
        // }
        // $this->debug->show(implode(",",$idIn));
        // $this->debug->show($avgData);

        foreach ($avgData['data'] as $key => $value) {
            $cnLocation['form'] = $value;
            $cnIndeks = $this -> _countByLocation($cnLocation);

            $cekDataLocation = $this -> tables -> query("SELECT uid_indeks_ikal FROM indeks_ikal  WHERE deleted = 0 AND uid_lokasi=" . $value['uid_lokasi_pemantauan']." AND tahun=".$tahun);
            if ($cekDataLocation['total'] > 1) {
                // $this->debug->show($cekDataLocation);
                $tmpUppdate = null;
                foreach ($cekDataLocation['data'] as $ki => $vi) {
                    if ($ki > 0) {
                        $tmpUppdate[] = $vi['uid_indeks_ikal'];
                    }
                }
                if ($tmpUppdate) {
                    $this->tables->query("UPDATE `indeks_ikal` SET `deleted` = '1' WHERE `indeks_ikal`.`uid_indeks_ikal` IN(".implode(",", $tmpUppdate).")");
                }
            }
            $postIndeks['form']['uid_indeks_ikal'] = "";
            if ($cekDataLocation['total']) {
                $postIndeks['form']['uid_indeks_ikal'] = $cekDataLocation['data'][0]['uid_indeks_ikal'];
            } else {
                $postIndeks['form']['uid_provinsi'] = $uid_provinsi;
                $postIndeks['form']['uid_lokasi'] = $value['uid_lokasi_pemantauan'];
            }

            $postIndeks['form']['json_data'] = $cnIndeks['json_data'];
            $postIndeks['form']['nilai_indeks'] = $cnIndeks['wqi'];
            $postIndeks['form']['rating_indeks'] = $cnIndeks['wqr'];
            $postIndeks['form']['tahun'] = $tahun;
            $postIndeks['form']['status_hitung'] = 1;
            $postIndeks['submit'] = true;
            $this -> tables -> set("indeks_ikal", "uid_indeks_ikal");
            if ($this -> tables -> post($postIndeks)) {
                $cnPost++;
            }
        }
        if ($cnPost == $avgData['total']) {
            return 1;
        } else {
            return 0;
        }
    }

    private function _countByLocation($post)
    {
        $json_data['wf']['tss'] = '0.223837849269234';
        $json_data['wf']['do_p'] = '0.196387027260743';
        $json_data['wf']['minyak_dan_lemak'] = '0.205162776063457';
        $json_data['wf']['amonia_total'] = '0.192041900850097';
        $json_data['wf']['orto_fosfat'] = '0.182570446556469';

        // counting TSS q_value
        $params['tss'] = ($post['form']['tss'] ? $post['form']['tss'] : null);
        if ($params['tss'] == null) {
            $params['tss'] = $params['tss'];
        } elseif ($params['tss'] <= 20) {
            $params['tss'] = (-0.035 * (pow($params['tss'], 2))) + (0.55 * $params['tss']) + 93;
        } elseif ($params['tss'] <= 100) {
            $params['tss'] = (0.0008 * (pow($params['tss'], 2))) - (1.0217 * $params['tss']) + 107.83;
        } else {
            $params['tss'] = 10;
        }

        //counting DO q_value
        $params['do_p'] = ($post['form']['do_p'] ? str_replace(",", ".", $post['form']['do_p']) : null);
        // $params['do_p']    = 1;
        if ($params['do_p'] == null) {
            // IF(C18="";"NM";
            $params['do_p'] = $params['do_p'];
        } elseif ($params['do_p'] <= 3) {
            // IF(C6<=3;1,6336*C6^3-5,3439*C6^2+12,996*C6-4*10^-12;
            $params['do_p'] = (1.6336 * (pow($params['do_p'], 3))) - (5.3439 * (pow($params['do_p'], 2))) + (12.996 * $params['do_p']) - (4 * (pow(10, -12)));
        } elseif ($params['do_p'] <= 7) {
            // IF(C6<=7; -0,0028*C6^4 + 0,0611*C6^3 - 2,5294*C6^2 + 37,097*C6-54,951;
            $params['do_p'] = (-0.0028 * (pow($params['do_p'], 4))) + (0.0611 * (pow($params['do_p'], 3))) - (2.5294 * (pow($params['do_p'], 2))) + (37.097 * $params['do_p']) - 54.951;
        } elseif ($params['do_p'] <= 10) {
            // IF(C6<=10;-1,5596*C6^3+38,895*C6^2-331,35*C6+1043,6;
            $params['do_p'] = (-1.5596 * (pow($params['do_p'], 3))) + (38.895 * pow($params['do_p'], 2)) - (331.35 * $params['do_p']) + 1043.6;
        } elseif ($params['do_p'] <= 11) {
            // IF(C6<=11;-20*C6+260;
            $params['do_p'] = (-20 * $params['do_p']) + 260;
        } elseif ($params['do_p'] <= 15) {
            // IF(C6<=15;40
            $params['do_p'] = 40;
        } else {
            $params['do_p'] = 0;
        }

        // count MInyak dan Lemak
        $params['minyak_dan_lemak'] = ($post['form']['minyak_dan_lemak'] ? str_replace(",", ".", $post['form']['minyak_dan_lemak']) : null);
        if ($params['minyak_dan_lemak'] == null) {
            // =IF(C21="";"NM";
            $params['minyak_dan_lemak'] = $params['minyak_dan_lemak'];
        } elseif ($params['minyak_dan_lemak'] <= 2) {
            // IF(C9<=2;3,5*C9^2-47,5*C9+100;
            $params['minyak_dan_lemak'] = (3.5 * (pow($params['minyak_dan_lemak'], 2))) - (47.5 * $params['minyak_dan_lemak']) + 100;
        } elseif ($params['minyak_dan_lemak'] <= 4) {
            // IF(C9<=4;2,5*C9^2-19,5*C9+48;
            $params['minyak_dan_lemak'] = (2.5 * pow($params['minyak_dan_lemak'], 2)) - (19.5 * $params['minyak_dan_lemak']) + 48;
        } elseif ($params['minyak_dan_lemak'] <= 8) {
            // IF(C9<=8;10;
            $params['minyak_dan_lemak'] = 10;
        } elseif ($params['minyak_dan_lemak'] <= 14) {
            // IF(C9<=14;-0,0333*C9^3+0,9*C9^2-9,0667*C9+42
            $params['minyak_dan_lemak'] = (-0.0333 * pow($params['minyak_dan_lemak'], 3)) + (0.9 * pow($params['minyak_dan_lemak'], 2)) - (9.0667 * $params['minyak_dan_lemak']) + 42;
        } else {
            $params['minyak_dan_lemak'] = 0;
        }

        // count AMONIA q_value
        $params['amonia_total'] = ($post['form']['amonia_total'] ? str_replace(",", ".", $post['form']['amonia_total']) : null);
        if ($params['amonia_total'] == null) {
            // =IF(C19="";"NM";
            $params['amonia_total'] = $params['amonia_total'];
        } elseif ($params['amonia_total'] <= 0.4) {
            // IF(C7<=0,4;-2619*C7^4+238,1*C7^3+611,9*C7^2-200,95*C7+100;
            $params['amonia_total'] = (-2619 * (pow($params['amonia_total'], 4))) + (238.1 * (pow($params['amonia_total'], 3))) + (611.9 * (pow($params['amonia_total'], 2))) - (200.95 * $params['amonia_total']) + 100;
        } elseif ($params['amonia_total'] <= 1) {
            // IF(C7<=1;4488,3*C7^5 -17735*C7^4 +27529*C7^3 -20734*C7^2 +7373,7*C7-920,17
            $params['amonia_total'] = (4488.3 * (pow($params['amonia_total'], 5))) - (17735 * (pow($params['amonia_total'], 4))) + (27529 * (pow($params['amonia_total'], 3))) - (20734 * (pow($params['amonia_total'], 2))) + (7373.7 * $params['amonia_total']) - 920.17;
        } else {
            $params['amonia_total'] = 1;
        }

        // counting orto_fosfat q_value
        $params['orto_fosfat'] = ($post['form']['orto_fosfat'] ? str_replace(",", ".", $post['form']['orto_fosfat']) : null);
        if ($params['orto_fosfat'] == null) {
            //=IF(C20="";"NM";
            $params['orto_fosfat'] = null;
        } elseif ($params['orto_fosfat'] <= 0.001) {
            // IF(C8<=0,001;-10000*C8+100;
            $params['orto_fosfat'] = (-10000 * $params['orto_fosfat']) + 100;
        } elseif ($params['orto_fosfat'] <= 0.015) {
            // IF(C8<=0,015;-598,36*C8+89,923;
            $params['orto_fosfat'] = (-598.36 * $params['orto_fosfat']) + 89.923;
        } elseif ($params['orto_fosfat'] <= 0.05) {
            // IF(C8<=0,05;-1329,9*C8+99,995;
            $params['orto_fosfat'] = (-1329.9 * $params['orto_fosfat']) + 99.995;
        } elseif ($params['orto_fosfat'] <= 0.07) {
            // IF(C8<=0,07;-330,36*C8+51,726;
            $params['orto_fosfat'] = (-330.36 * $params['orto_fosfat']) + 51.726;
        } elseif ($params['orto_fosfat'] <= 0.1) {
            // IF(C8<=0,1;-2678,6*C8^2+89,286*C8+35,714;
            $params['orto_fosfat'] = (-2678.6 * pow($params['orto_fosfat'], 2)) + (89.286 * $params['orto_fosfat']) + 35.714;
        } elseif ($params['orto_fosfat'] <= 1) {
            // IF(C8<=1;2,7778*C8^2-14,167*C8+16,389;
            $params['orto_fosfat'] = (2.7778 * pow($params['orto_fosfat'], 2)) - (14.167 * $params['orto_fosfat']) + 16.389;
        } else {
            $params['orto_fosfat'] = 2;
        }

        $json_data['q_value'] = $params;

        $total_wf = 0;
        $total_rwf = 0;
        $total_sub = 0;
        foreach ($params as $k => $v) {
            $json_data['rwf'][$k] = (is_numeric($v) ? $json_data['wf'][$k] : null);
            $json_data['subtotal'][$k] = (is_numeric($json_data['rwf'][$k]) ? $v * $json_data['wf'][$k] : null);

            $total_wf += $json_data['wf'][$k];
            $total_rwf += $json_data['rwf'][$k];
            $total_sub += $json_data['subtotal'][$k];
        }

        $json_data['total_wf'] = $total_wf;
        $json_data['total_rwf'] = $total_rwf;
        $json_data['total_sub'] = $total_sub;

        if ($json_data['total_rwf']) {
            $json_data['wqi'] = $json_data['total_sub'] / $json_data['total_rwf'];
        } else {
            $json_data['wqi'] = $json_data['total_sub'];
        }

        // =IF(H24="NM";"NM";
        // IF(H24<25;"Sangat Kurang";
        // IF(H24<50;"Kurang";
        // IF(H24<70;"Sedang";
        // IF(H24<90;"Baik";
        // IF(H24<100;"Sangat Baik"))))))

        if ($json_data['wqi'] == null) {
            $json_data['wqr'] = null;
        } elseif ($json_data['wqi'] < 25) {
            $json_data['wqr'] = "Sangat Kurang";
        } elseif ($json_data['wqi'] < 50) {
            $json_data['wqr'] = "Kurang";
        } elseif ($json_data['wqi'] < 70) {
            $json_data['wqr'] = "Sedang";
        } elseif ($json_data['wqi'] < 90) {
            $json_data['wqr'] = "Baik";
        } elseif ($json_data['wqi'] < 100) {
            $json_data['wqr'] = "Sangat Baik";
        }
        $returnData['json_data'] = json_encode($json_data);
        $returnData['wqi'] = $json_data['wqi'];
        $returnData['wqr'] = $json_data['wqr'];
        return $returnData;
    }

    private function _countIndeksProvinsi($provinsi, $tahun)
    {
        $tahun = $tahun;
        if ($provinsi) {
            $sql = "SELECT
                  ABS(SUM(a.nilai_indeks)/count(a.uid_indeks_ikal)) AS average
									FROM indeks_ikal a
									LEFT JOIN lokasi_pemantauan b ON a.uid_lokasi = b.uid_lokasi_pemantauan
                WHERE a.deleted= 0 AND b.deleted = 0 AND a.uid_provinsi =" . $provinsi . " AND a.uid_lokasi > 0 AND a.tahun =".$tahun. " AND a.jenis_indeks = 0";
            // $this->debug->show($sql);
            $coutIndeksLocation = $this -> tables -> query($sql);
            $postIndeks['form']['nilai_indeks'] = $coutIndeksLocation['data'][0]['average'];
            $postIndeks['form']['tahun'] = $tahun;
            $postIndeks['form']['uid_provinsi'] = $provinsi;
            $postIndeks['form']['uid_lokasi'] = 0;
            if ($postIndeks['form']['nilai_indeks'] == null) {
                $postIndeks['form']['rating_indeks'] = "";
            } elseif ($postIndeks['form']['nilai_indeks'] < 25) {
                $postIndeks['form']['rating_indeks'] = "Sangat Kurang";
            } elseif ($postIndeks['form']['nilai_indeks'] < 50) {
                $postIndeks['form']['rating_indeks'] = "Kurang";
            } elseif ($postIndeks['form']['nilai_indeks'] < 70) {
                $postIndeks['form']['rating_indeks'] = "Sedang";
            } elseif ($postIndeks['form']['nilai_indeks'] < 90) {
                $postIndeks['form']['rating_indeks'] = "Baik";
            } elseif ($postIndeks['form']['nilai_indeks'] < 100) {
                $postIndeks['form']['rating_indeks'] = "Sangat Baik";
            }

            $cekData = $this -> tables -> query("SELECT * FROM indeks_ikal WHERE deleted = 0 AND uid_lokasi = 0 AND uid_provinsi=" . $provinsi . " AND tahun=" . $tahun);
            if ($cekData) {
                $postIndeks['form']['uid_indeks_ikal'] = $cekData['data'][0]['uid_indeks_ikal'];
            } else {
                $postIndeks['form']['uid_indeks_ikal'] = "";
            }

            return $postIndeks['form'];
        }
    }

    public function getDataIndeks()
    {
      $tahunShow = $this->params('x');
        $properties = $this -> _getProperties("indeks_ikal");
        $urlVar = BASEURL . $this -> url . '/';
        // $w = $this -> where . "  AND uid_lokasi = 0";
        $w = "a.deleted = 0 ";
        $w .= "  AND uid_lokasi = 0";
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
        if ($this->params('debug')==1) {
          $post['search'] = true;
          // $post['form']['tahun'] = 2020;
        }
        if (isset($post['search'])) {
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
            if ($post['form']['src_peruntukan']) {
                $w .= " AND uid_rf_peruntukan =" . $post['form']['src_peruntukan'];
            }
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
        $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi FROM indeks_ikal a
							        LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
							       WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $data = $this -> tables -> query($sql);
        $All = $this -> db -> query('SELECT count(a.uid_indeks_ikal) as x FROM indeks_ikal a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);
        $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);

        //get Nasional
        $getDataNasional = $this -> tables -> query("SELECT * FROM indeks_ikal WHERE jenis_indeks = 2 AND tahun=" . $post['form']['tahun']);
        // NILI INTERVENSI AOH
        if($post['form']['tahun']==2022){
            $getDataNasional['data'][0]['nilai_indeks'] = 84.41;
            // $this->debug->show($getDataNasional);
        }
        $this -> view -> assign("indeksNasional", $getDataNasional['data'][0]);
        //end

        $this -> view -> assign("urlVar", $urlVar);
        $this -> view -> assign("totalRow", $totalRow);
        $this -> view -> assign("limit", $limit);
        $this -> view -> assign("page", $offset);
        $this -> view -> assign("view", $data['data']);


        if ($this->params("ex") == "provinsi") {
            $provinsi = $this -> tables -> query('SELECT a.*, b.nama_propinsi AS nama_provinsi FROM indeks_ikal a
							        LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
							       WHERE jenis_indeks=1 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
            foreach ($provinsi['data'] as $key => $value) {
                $provinsi['detail'][$key] = $this -> tables -> query("SELECT a.*, b.alamat, b.alamat_detail, b.uid_kabkota, c.nama_kabkot FROM indeks_ikal a
	                  LEFT JOIN lokasi_pemantauan b ON b.uid_lokasi_pemantauan = a.uid_lokasi
	                  LEFT JOIN rf_kabkota c ON c.kd_kota = b.uid_kabkota
	                  WHERE b.deleted = 0 AND a.deleted = 0 AND a.uid_lokasi > 0 AND a.uid_provinsi = ".$value['uid_provinsi']." AND tahun=".$value['tahun']."
	                  ORDER BY b.uid_lokasi_pemantauan ASC
	                  ");
            }
        }
        if ($this->params("ex") == "kabkota") {
            $this->expExcel($kabkota, null);
        } elseif ($this->params("ex") == "provinsi") {
            $this->expExcel(null, $provinsi);
        }

        $html = $this->view->fetch('parts/contents/ikal/indeks/simulasi.html');
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
