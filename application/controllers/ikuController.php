<?php
/**
 * created at 	: 01/10/2020
 * created by 	: dasendria team
 * desc		  	: controller INDEKS KUALITAS UDARA IKLHK
 *
 */
class ikuController extends Front
{
    public function init()
    {
        // ini_set('display_errors',1);
        ($this -> session -> get('memberIKLH') ?: $this -> redirect("login"));
        date_default_timezone_set("Asia/Jakarta");
        // ini_set("display_errors",TRUE);
        //SET CUSTOM VIEWS FOLDER
        $this -> view -> setFolder('be');

        //LOAD MODELS
        $this -> loadModel("tables");
        $this -> loadModel("ref");
        $this -> loadModel("users");
        $this -> loadModel("iku");

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

    //INDEX FUNCTION IS A DEFAULT ACTION
    public function index()
    {//menu index iku
      // die("under maintainance");
        $post = $this -> post();
        if (isset($post['submit'])) {
            //check reject update
            if(isset($post['form']['uid_pelaporan_iku'])){
              $checkData = $this -> tables -> query("SELECT v_pusat, v_regional, v_provinsi FROM pelaporan_iku WHERE uid_pelaporan_iku =".$post['form']['uid_pelaporan_iku'])['data'][0];
              $post['form']['v_reject_status'] = ($checkData['v_pusat'] == 2 ? 1 : ($checkData['v_regional'] == 2 ? 1 : ($checkData['v_provinsi'] == 2 ? 1 : 0)));
              if($post['form']['v_reject_status'] == 1){
                $post['form']['v_pusat'] = 0;
                $post['form']['v_regional'] = 0;
                $post['form']['v_provinsi'] = 0;
              }
            }
            //end

            if ($this -> me['role_user'] == 3) {
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
                $post['form']['uid_kabkota'] = $this -> me['uid_kabkota'];
            }
            if ($this -> me['role_user'] == 2) {
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
            }

            $file = $_FILES['shu'];
            if ($file['name']) {
                $fileUpload = $this -> functions -> uploadFile($_FILES['shu'], "monitoring");
                $post['form']['shu'] = $fileUpload;
            }

            //Payload OCR mentah (JSON) dari /ocr/ikuExtract, dikirim form lewat field
            //tersembunyi form[ocr_result]. Disimpan sebagai jejak audit: dokumen sumbernya
            //sendiri tidak ikut disimpan, jadi ini satu-satunya rekaman apa yang dibaca model.
            //
            //Kolomnya SENGAJA dibuang dari payload bila nilainya kosong atau bukan JSON valid.
            //Alasannya: tables::post() meneruskan seluruh $post['form'] ke AutoExecute, sehingga
            //field kosong akan menimpa nilai lama saat user menyimpan ulang baris yang sama
            //secara manual. Dengan di-unset, kolomnya tidak ikut di-UPDATE dan isinya tetap utuh.
            //Payload di-decode lalu di-encode ULANG, bukan disimpan apa adanya. Tujuannya
            //memadatkan: json_encode() tanpa JSON_PRETTY_PRINT membuang seluruh indentasi
            //dan spasi antar-token yang dikirim browser. Ini bukan sekadar kerapian —
            //kolomnya TEXT (maks 65.535 byte) dan sql_mode server tidak memuat
            //STRICT_TRANS_TABLES, jadi payload yang kepanjangan dipotong DIAM-DIAM.
            //
            //JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES dipakai agar nama lokasi
            //dan URL tidak membengkak jadi "\uXXXX" / "\/" yang memakan byte percuma.
            if (isset($post['form']['ocr_result'])) {
                $ocrResult = trim($post['form']['ocr_result']);
                $ocrDecoded = json_decode($ocrResult);
                if ($ocrResult !== '' && json_last_error() === JSON_ERROR_NONE) {
                    $post['form']['ocr_result'] = json_encode($ocrDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    unset($post['form']['ocr_result']);
                }
            }

            $post['form']['cruser'] = $this -> me['uid_users'];
            if ($post['form'][$this->primaryKey]) {
                unset($post['form']['cruser']);
                $post['form']['chuser'] = $this->me['uid_users'];
            }
            $post['form']['no2'] = str_replace(",", ".", $post['form']['no2']);
            $post['form']['so2'] = str_replace(",", ".", $post['form']['so2']);
            $post['form']['pm25'] = str_replace(",", ".", $post['form']['pm25']);

            $post['form']['no2_faktor_koreksi'] = 0;
            $post['form']['so2_faktor_koreksi'] = 0;
            $post['form']['pm25_faktor_koreksi'] = 0;

            if ($post['form']['no2_uid_metode_pemantauan'] == 1) {
              if ($post['form']['no2_durasi_pemantauan'] == 24) {
                $post['form']['no2_faktor_koreksi'] = $post['form']['no2'] * 1;
              } elseif ($post['form']['no2_durasi_pemantauan'] == 12) {
                $post['form']['no2_faktor_koreksi'] = $post['form']['no2'] * 1.48;
              } elseif ($post['form']['no2_durasi_pemantauan'] == 6) {
                $post['form']['no2_faktor_koreksi'] = $post['form']['no2'] * 1.76;
              } elseif ($post['form']['no2_durasi_pemantauan'] == 4) {
                $post['form']['no2_faktor_koreksi'] = $post['form']['no2'] * 1.92;
              } elseif ($post['form']['no2_durasi_pemantauan'] == 2) {
                $post['form']['no2_faktor_koreksi'] = $post['form']['no2'] * 2.00;
              } elseif ($post['form']['no2_durasi_pemantauan'] == 1) {
                $post['form']['no2_faktor_koreksi'] = $post['form']['no2'] * 2.48;
              }
            }

            if ($post['form']['so2_uid_metode_pemantauan'] == 1) {
              if ($post['form']['so2_durasi_pemantauan'] == 24) {
                $post['form']['so2_faktor_koreksi'] = $post['form']['so2'] * 1;
              } elseif ($post['form']['so2_durasi_pemantauan'] == 12) {
                $post['form']['so2_faktor_koreksi'] = $post['form']['so2'] * 1.48;
              } elseif ($post['form']['so2_durasi_pemantauan'] == 6) {
                $post['form']['so2_faktor_koreksi'] = $post['form']['so2'] * 1.76;
              } elseif ($post['form']['so2_durasi_pemantauan'] == 4) {
                $post['form']['so2_faktor_koreksi'] = $post['form']['so2'] * 1.92;
              } elseif ($post['form']['so2_durasi_pemantauan'] == 2) {
                $post['form']['so2_faktor_koreksi'] = $post['form']['so2'] * 2.00;
              } elseif ($post['form']['so2_durasi_pemantauan'] == 1) {
                $post['form']['so2_faktor_koreksi'] = $post['form']['so2'] * 2.48;
              }
            }

            if ($post['form']['pm25_uid_metode_pemantauan'] == 1) {
              if ($post['form']['pm25_durasi_pemantauan'] == 24) {
                $post['form']['pm25_faktor_koreksi'] = $post['form']['pm25'] * 1;
              } elseif ($post['form']['pm25_durasi_pemantauan'] == 12) {
                $post['form']['pm25_faktor_koreksi'] = $post['form']['pm25'] * 1.1144;
              } elseif ($post['form']['pm25_durasi_pemantauan'] == 6) {
                $post['form']['pm25_faktor_koreksi'] = $post['form']['pm25'] * 1.1246;
              } elseif ($post['form']['pm25_durasi_pemantauan'] == 4) {
                $post['form']['pm25_faktor_koreksi'] = $post['form']['pm25'] * 1.1282;
              } elseif ($post['form']['pm25_durasi_pemantauan'] == 2) {
                $post['form']['pm25_faktor_koreksi'] = $post['form']['pm25'] * 1.1318;
              } elseif ($post['form']['pm25_durasi_pemantauan'] == 1) {
                $post['form']['pm25_faktor_koreksi'] = $post['form']['pm25'] * 1.1337;
              }
            }

            $post['form']['uid_lab'] = implode(",",$post['form']['uid_lab']);

            if (!$post['form']['uid_lokasi_pemantauan']) {
                // $post['form']['uid_rf_component'] = 1;
                // $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
                // if ($this -> tables -> post($post)) {
                //     $post['form']['uid_lokasi_pemantauan'] = $this -> tables -> lastInsertID();
                //     $post['submit'] = true;
                //     $this -> tables -> set("pelaporan_iku", "uid_pelaporan_iku");
                //     if ($this -> tables -> post($post)) {
                //         $message = "Berhasil menyimpan data !";
                //     } else {
                //         $message = "Gagal menimpan data !";
                //     }
                // } else {
                //     $message = "Gagal menimpan data !";
                // }
                $message = 'Kode lokasi harus dipilih';
            } else {
                $this -> tables -> set("pelaporan_iku", "uid_pelaporan_iku");
                if ($this -> tables -> post($post)) {
                    $message = "Berhasil menyimpan data !";
                } else {
                    $message = "Gagal menyimpan data !";
                }
            }
        }

        if (isset($post['submit-excel'])) {
            if ($this -> me['role_user'] == 3) { //Kabkota
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
                $post['form']['uid_kabkota'] = $this -> me['uid_kabkota'];
            }
            if ($this -> me['role_user'] == 2) { //Provinsi
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
            }
            $post['form']['cruser'] = $this -> me['uid_users'];
            $val = $_FILES['file_excel'];
            $ext = strtolower(strrchr($val['name'], "."));
            if ($ext == ".xls") {

                $files = $this -> functions -> uploadFile($_FILES['file_excel']);
                // $this->debug->show($files);
                // $this->debug->show($_FILES['file_excel']);
            }
            if ($files) {
                $excelReader = new Spreadsheet_Excel_Reader(UPLOADFOLDER . "docs/" . $files, true);
                $rows = $excelReader -> rowcount(0);
                for ($c = 1; $c <= 14; $c++) {
                    for ($d = 3; $d <= $rows; $d++) {
                        if ($excelReader -> val($d, $c)) {
                            $data[$d][$c] = trim($excelReader -> val($d, $c));
                        }
                        // else {
                        //     $data[$d][$c] = "-";
                        // };
                    }
                }
                unlink(UPLOADFOLDER . "docs/" . $files);

                $tmpCn = 0;
                $tmpTgl = NULL;
                $message = '';
                foreach ($data as $key => $vals) {
                    if ($vals[1] != "-") {
                        // $latitude = str_replace(",", ".", trim($vals[6]));
                        // $longitude = str_replace(",", ".", trim($vals[7]));
                        $periode = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[3]);

                        // $where = "deleted = 0 AND uid_rf_component= 1 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND uid_kabkota=" . $post['form']['uid_kabkota'] . " AND alamat='" . $vals[2] . "' AND latitude=" . $latitude . " AND longitude=" . $longitude;
                        if ($this -> me['role_user'] == 3) { //Kabkota
                          // $where = "deleted = 0 AND uid_rf_component= 1 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND uid_kabkota=" . $post['form']['uid_kabkota'] . " AND uid_rf_pelaksana=4 AND kode_lokasi='" . $vals[2] . "' AND tahun=". date("Y", strtotime($vals[1]));
                          $where = "deleted = 0 AND uid_rf_component= 1 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND uid_kabkota=" . $post['form']['uid_kabkota'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun LIKE '%". date("Y", strtotime($vals[1]))."%'";
                        }else {
                          $where = "deleted = 0 AND uid_rf_component= 1 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun LIKE '%". date("Y", strtotime($vals[1]))."%'";
                          // if ($this -> me['role_user'] <= 1) {//pusat
                          //   $where = $where.' AND uid_rf_pelaksana=1';
                          // }elseif ($this -> me['role_user'] == 2) {//provinsi
                          //   $where = $where.' AND uid_rf_pelaksana=3';
                          // }elseif ($this -> me['role_user'] == 4) {//p3e
                          //   $where = $where.' AND uid_rf_pelaksana=2';
                          // }
                        }

                        $date = date("Y-m-d", strtotime($vals[1]));
                        if ($this -> cekLocation($where, $vals, $post['form']) && $date <= date("Y-m-d")) {
                          $postLaporan['form']['uid_pelaporan_iku'] = "";
                          $postLaporan['form']['cruser'] = $post['form']['cruser'];
                          $postLaporan['form']['uid_lokasi_pemantauan'] = $this -> cekLocation($where, $vals, $post['form']);
                          $postLaporan['form']['tanggal'] = date("Y-m-d", strtotime($vals[1]));
                          $postLaporan['form']['periode_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[3]);
                          $postLaporan['form']['uid_rf_peruntukan'] = $this -> cekPeruntukan($vals[4]);
                          // $postLaporan['form']['uid_metode_pemantauan'] = $this -> cekMetode($vals[5]);
                          $postLaporan['form']['no2'] = str_replace(",", ".", preg_replace('~[\\\\/:*?"<>|]~', "", $vals[5]));
                          $postLaporan['form']['no2_uid_metode_pemantauan'] = $this -> cekMetode($vals[6]);
                          if ($postLaporan['form']['no2_uid_metode_pemantauan'] == 1) {
                            $postLaporan['form']['no2_durasi_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[7]);
                          } else {
                            $postLaporan['form']['no2_durasi_pemantauan_pasif'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[7]);
                          }
                          $postLaporan['form']['so2'] = str_replace(",", ".", preg_replace('~[\\\\/:*?"<>|]~', "", $vals[8]));
                          $postLaporan['form']['so2_uid_metode_pemantauan'] = $this -> cekMetode($vals[9]);
                          if ($postLaporan['form']['so2_uid_metode_pemantauan'] == 1) {
                            $postLaporan['form']['so2_durasi_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[10]);
                          } else {
                            $postLaporan['form']['so2_durasi_pemantauan_pasif'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[10]);
                          }
                          $postLaporan['form']['pm25'] = str_replace(",", ".", preg_replace('~[\\\\/:*?"<>|]~', "", $vals[11]));
                          $postLaporan['form']['pm25_uid_metode_pemantauan'] = $this -> cekMetode($vals[12]);
                          if ($postLaporan['form']['pm25_uid_metode_pemantauan'] == 1) {
                            $postLaporan['form']['pm25_durasi_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[13]);
                          } else {
                            $postLaporan['form']['pm25_durasi_pemantauan_pasif'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[13]);
                          }
                          // $postLaporan['form']['durasi_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[9]);
                          $postLaporan['form']['uid_lab'] = $this->checkLab(preg_replace('~[\\\\/:*?"<>|]~', "", $vals[14]));
                          $postLaporan['submit'] = true;
                          // $this->debug->show($postLaporan);
                          $this -> tables -> set("pelaporan_iku", "uid_pelaporan_iku");
                          if($this -> tables -> post($postLaporan)){
                              $tmpCn++;
                          }else{
                            $message .= "Gagal menyimpan data kode lokasi ". $vals[2] ."<br>";
                          }
                        }else {
                          $message .= "Gagal menyimpan data kode lokasi ". $vals[2] .", kesalahan pada kode lokasi atau tanggal melebihi tanggal saat ini<br>";
                        }
                        // $this->debug->show($postLaporan);
                    }
                }
                // $this->debug->show(count($data).' - '.$tmpCn.' - '.$message);
                if (count($data) == $tmpCn && $message == '') {
                    $message = "Berhasil menyimpan data !";
                }
                // $this->debug->show($post);
            }
        }

        $this -> getData();
        $this -> rfData();
        $this -> cekLockSystem(1, 1.1,  $this -> me['uid_users']);
        $this -> view -> assign("pelaporanActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("icons", '<i class="la la-cloud"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS UDARA');
        $this -> view -> display("index.html");
    }

    private function cekPeruntukan($nama)
    {// function for cek peruntukan iku
        $cek = $this -> tables -> query("SELECT * FROM rf_peruntukan WHERE deleted=0 AND peruntukan = 1 AND nama='" . $nama . "'");
        if ($cek['total']) {
            return $cek['data'][0]['uid_rf_peruntukan'];
        } else {
          return 98;
            // $postPeruntukan['form']['nama'] = $nama;
            // $postPeruntukan['form']['peruntukan'] = 1;
            // $postPeruntukan['submit'] = true;
            // $this -> tables -> set("rf_peruntukan", "uid_rf_peruntukan");
            // if ($this -> tables -> post($postPeruntukan)) {
            //     return $this -> tables -> lastInsertID();
            // }
        }
    }

    private function cekMetode($nama)
    {// function for cek peruntukan iku
        $cek = $this -> tables -> query("SELECT * FROM rf_metode_pemantauan WHERE deleted=0 AND peruntukan = 1 AND metode='" . $nama . "'");
        if ($cek['total']) {
            return $cek['data'][0]['uid_metode_pemantauan'];
        } else {
            return 6;
            // $postMetode['form']['metode'] = $nama;
            // $postMetode['form']['peruntukan'] = 1;
            // $postMetode['submit'] = true;
            // $this -> tables -> set("rf_metode_pemantauan", "uid_metode_pemantauan");
            // if ($this -> tables -> post($postMetode)) {
            //     return $this -> tables -> lastInsertID();
            // }
        }
    }

    private function cekLocation($where = "", $vals = array(), $post = array())
    {//function for cek location pelaporan iku
        $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
        $cekData = $this -> tables -> fetch($where);
        if ($cekData["total"]) {
            return $cekData['data'][0]['uid_lokasi_pemantauan'];
        }else {
          return 0;
        }
        // else {
        //     $push_location['form']['cruser'] = $post['cruser'];
        //     $push_location['form']['kode_lokasi'] = $vals[2];
        //     $push_location['form']['periode_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[3]);
        //     $push_location['form']['alamat_detail'] = $vals[3];
        //     $postLaporan['form']['tahun'] = date("Y", strtotime($vals[1]));
        //     if ($this -> me['role_user'] == 3) {
        //         $post['uid_provinsi'] = $this -> me['uid_provinsi'];
        //         $post['uid_kabkota'] = $this -> me['uid_kabkota'];
        //     }
        //     if ($this -> me['role_user'] == 2) {
        //         $post['uid_provinsi'] = $this -> me['uid_provinsi'];
        //     }
        //
        //     $push_location['form']['uid_provinsi'] = $post['uid_provinsi'];
        //     $push_location['form']['uid_kabkota'] = $post['uid_kabkota'];
        //     $push_location['form']['latitude'] = str_replace(",", ".", trim($vals[6]));
        //     $push_location['form']['longitude'] = str_replace(",", ".", trim($vals[7]));
        //     $push_location['form']['uid_rf_component'] = 1;
        //     $push_location['submit'] = true;
        //     if ($this -> tables -> post($push_location)) {
        //         return $this -> tables -> lastInsertID();
        //     }
        // }
    }

    private function checkLab($kode_lab){
      if(!$kode_lab){
        return NULL;
      }

      $kode_lab = explode(";",$kode_lab);
      $kode_lab_str = NULL;
      foreach ($kode_lab as $key => $value) {
        $kode_lab_str[] = "'{$value}'";
      }
      $kode_lab = implode(",",$kode_lab_str);
      $data = $this->db->fetch("SELECT uid FROM rf_lab WHERE deleted = 0 AND verifikasi = 1 AND kode IN ({$kode_lab})");
      if($data['total'] > 0){
        return implode(",",array_column($data["data"],"uid"));
      }else{
        return NULL;
      }
    }

    private function getData()
    {// function get data pelaporan iku
        $this -> tables -> set($this -> viewName, $this -> primaryKey);
        $properties = $this -> _getProperties($this -> viewName);
        $urlVar = BASEURL . $this -> url . '/';
        $w = $this -> where;
        if ($this -> me['role_user'] == 3) {
            $w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND kd_regional =" . $this -> me['uid_regional'];

            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w .= " AND uid_provinsi IN ({$provinsiLainnya})";
            }
        }
        $o = $this -> primaryKey . " DESC";
        $post = $this -> post();
        if ($this -> params('search')) {
            $post['search'] = true;
            $post['form'] = json_decode(urldecode($this -> params('search')), 1);
        }
        if (isset($post['search'])) {
          $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
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
            if ($post['form']['tahun']) {
                $w .= " AND YEAR(tanggal) ='" . $post['form']['tahun'] . "'";
            }
            if ($post['form']['crdate_start'] && $post['form']['crdate_end']){
                $crdate_start = strtotime($post['form']['crdate_start']);
                $crdate_end = strtotime($post['form']['crdate_end']);
                $w .= " AND (crdate BETWEEN ".$crdate_start." AND ".$crdate_end." OR chdate BETWEEN ".$crdate_start." AND ".$crdate_end.") ";
            }
            if ($post['form']['src_level']) {
                $w .= " AND role_user = " . $post['form']['src_level'];
            }
            if ($post['form']['src_peruntukan']) {
                $w .= " AND uid_rf_peruntukan = " . $post['form']['src_peruntukan'];
            }
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_metode']) {
              $w .= " AND (no2_uid_metode_pemantauan = " . $post['form']['src_metode']." OR so2_uid_metode_pemantauan = " . $post['form']['src_metode']. " OR pm25_uid_metode_pemantauan = " . $post['form']['src_metode'].") ";
            }
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_shu']) {
                $w .= " AND shu IS " . $post['form']['src_shu'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }
            if ($post['form']['verifikasi']) {
              $post['form']['verifikasi_'] =  $post['form']['verifikasi'] == 'un' ? 0 :  $post['form']['verifikasi'];
              if ($post['form']['verifikasi_'] == 0) {
                if ($this -> me['role_user'] == 1 || $this -> me['role_user'] == 0) {
                  $w .= " AND v_pusat = ".$post['form']['verifikasi_'];
                } elseif ($this -> me['role_user'] == 2) {
                  $w .= " AND v_provinsi = ".$post['form']['verifikasi_'];
                } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
                  $w .= " AND v_regional = ".$post['form']['verifikasi_'];
                }
              }else{
                $w .= " AND (v_pusat = ".$post['form']['verifikasi_'] ." OR v_provinsi = ".$post['form']['verifikasi_']." OR v_regional =".$post['form']['verifikasi_'].")";
              }
            }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        } else {
            $w .= " AND YEAR(tanggal) ='" . ACTIVE_YEAR . "'";
            $post['form']['tahun'] = ACTIVE_YEAR;

            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        }
        $this->yearActive = $post['form']['tahun'];
        if ($this -> url == "iku/verifikasi") {
            $o = "v_provinsi, v_regional, v_pusat ASC";
        }
        $search_json = urlencode(json_encode($post['form']));
        $this->view->assign("search_json", $search_json);
        //PAGING
        $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
        $limit = LIMIT;
        $data = $this -> tables -> query('SELECT * FROM ' . $this -> viewName . ' WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $All = $this -> db -> query('SELECT count(' . $this -> primaryKey . ') as x FROM ' . $this -> viewName . ' WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);

        if ($this -> params('debug')==1) {
          $this->debug->show('SELECT count(' . $this -> primaryKey . ') as x FROM ' . $this -> viewName . ' WHERE ' . $w);
        }

        $uid_lokasi_pemantauan_list = implode(",",array_keys(array_column($data["data"],null,"uid_lokasi_pemantauan")));
        if($uid_lokasi_pemantauan_list){
          $checkJumlahLapor = $this->db->fetch("SELECT uid_lokasi_pemantauan, COUNT(uid_lokasi_pemantauan) AS total FROM pelaporan_iku WHERE deleted = 0 AND uid_lokasi_pemantauan IN({$uid_lokasi_pemantauan_list}) AND YEAR(tanggal) = ".$post['form']['tahun']." GROUP BY uid_lokasi_pemantauan HAVING total < 2")["data"];
          if($checkJumlahLapor[0]){
            $checkJumlahLapor = array_column($checkJumlahLapor,null,"uid_lokasi_pemantauan");
          }
        }

        foreach ($data['data'] as $key => $value) {
          $data['data'][$key]['catatan_verifikator'] = $value['catatan_verifikator'] .' '.($value['catatan_verifikator_select']?str_replace("|",",",$value['catatan_verifikator_select']) : '');
          $data['data'][$key]['catatan_provinsi'] = $value['catatan_provinsi'] .' '.($value['catatan_provinsi_select']?str_replace("|",",",$value['catatan_provinsi_select']) : '');
          $data['data'][$key]['catatan_regional'] = $value['catatan_regional'] .' '.($value['catatan_regional_select']?str_replace("|",",",$value['catatan_regional_select']) : '');

          $data['data'][$key]['verify_status_reject'] = ($value['v_pusat'] == 2 ? 1 : ($value['v_regional'] == 2 ? 1 : ($value['v_provinsi'] == 2 ? 1 : 0)));

          if(isset($checkJumlahLapor[$value['uid_lokasi_pemantauan']])){
            $data['data'][$key]['kurang_jumlah_lapor'] = 1;
          }else{
            $data['data'][$key]['kurang_jumlah_lapor'] = 0;
          }

          $dataMetodeNo2 = $this->db->query("SELECT * FROM rf_metode_pemantauan WHERE uid_metode_pemantauan = ".$value['no2_uid_metode_pemantauan']);
          $data['data'][$key]['no2_metode'] = $dataMetodeNo2->fields['metode'];

          $dataMetodeSo2 = $this->db->query("SELECT * FROM rf_metode_pemantauan WHERE uid_metode_pemantauan = ".$value['so2_uid_metode_pemantauan']);
          $data['data'][$key]['so2_metode'] = $dataMetodeSo2->fields['metode'];

          $dataMetodePm25 = $this->db->query("SELECT * FROM rf_metode_pemantauan WHERE uid_metode_pemantauan = ".$value['pm25_uid_metode_pemantauan']);
          $data['data'][$key]['pm25_metode'] = $dataMetodePm25->fields['metode'];
        }

        $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
        $listExport = $this->_getListExport($totalRow);
        $this -> view -> assign("listExport", $listExport);
        $this -> view -> assign("urlVar", $urlVar);
        $this -> view -> assign("totalRow", $totalRow);
        $this -> view -> assign("limit", $limit);
        $this -> view -> assign("page", $offset);
        $this -> view -> assign("view", $data['data']);
        // $this->debug->show($w);
    }

    public function editData()
    {// function get for edit data iku
        header("Content-Type: application/json; charset=UTF-8");
        if ($this -> params("x")) {
            $this -> tables -> set("v_pelaporan_iku", "uid_pelaporan_iku");
            $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_iku=" . $this -> params("x"));
            $row = $dataEdit['data'][0];

            //ocr_result diambil lewat query TERPISAH ke tabel dasar, bukan dengan
            //menambahkan kolomnya ke v_pelaporan_iku. Alasannya view itu bukan hanya
            //milik endpoint ini:
            //
            //  1. getData() membangun filter keyword dari SHOW COLUMNS view ini, lalu
            //     mem-LIKE SEMUA kolomnya. Kalau ocr_result ikut, pencarian akan
            //     menyisir isi JSON mentah — user mencari nama lab bisa mendapat baris
            //     yang lab-nya lain, hanya karena nama itu muncul di hasil OCR yang
            //     sudah dikoreksi manual. Hasil pencarian jadi keliru.
            //  2. getData() dan ekspor Excel memakai SELECT * dari view ini, sehingga
            //     tiap baris daftar ikut memikul payload JSON yang tidak dipakai.
            //
            //Payload ini bersifat khusus-detail, jadi biayanya ditaruh di sini saja.
            //
            //Dikembalikan sudah ter-decode (objek), bukan string JSON, supaya sisi view
            //bisa langsung meneruskannya ke applyOcrResult() tanpa parse lagi. null
            //berarti: belum pernah ada hasil OCR, ATAU isinya gagal di-parse — yang bisa
            //terjadi bila payload melebihi kapasitas TEXT lalu terpotong diam-diam.
            if ($row) {
                $ocrRow = $this -> db -> query("SELECT ocr_result FROM pelaporan_iku WHERE uid_pelaporan_iku = " . intval($this -> params("x")));
                $ocrResult = (isset($ocrRow -> fields['ocr_result']) ? $ocrRow -> fields['ocr_result'] : null);

                $row['ocr_result'] = null;
                if ($ocrResult) {
                    $decoded = json_decode($ocrResult);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row['ocr_result'] = $decoded;
                    }
                }
            }

            echo json_encode($row);
        }
    }

    public function deletedData()
    {// function deleted data pelaporan iku
        $post = $this -> post();
        if (isset($post['x'])) {
            $this -> tables -> set("pelaporan_iku", "uid_pelaporan_iku");
            if ($this -> tables -> softDelete($post['x'])) {
                echo json_encode(array('statusCode' => 200, 'message' => $this -> message -> delete('success')));
            } else {
                echo json_encode(array('statusCode' => 400, 'message' => $this -> message -> delete('failed')));
            }
        } else {
            echo json_encode(array('statusCode' => 403, 'message' => $this -> message -> access()));
        }
    }

    private function _getListExport($totalRow, $limitRow = LIMIT_DOWNLOAD_EXCEL)
    {
      $numList    = round($totalRow / $limitRow);
      $numListRes = $numList * $limitRow;
      $itemCount  = 1;
      $limitCount = $limitRow;
      if ($totalRow >= $numListRes) {
        $numList += 1;
      }
      for ($itemCount; $itemCount <= $numList; $itemCount++) {
        $offsetStart = 0;
        $offsetEnd = 0;
        if ($itemCount > 1) {
          $offsetStart = ($limitCount - $limitRow) + 1;
        }
        if ($limitCount >= $totalRow) {
          $offsetEnd = $totalRow;
        } else {
          $offsetEnd = $limitCount;
        }
        $listExport[$itemCount]['offset_start']  = $offsetStart;
        $listExport[$itemCount]['offset_end']  = $offsetEnd;
        $limitCount += $limitRow;
      }

        return $listExport;
    }

    public function dataExcel($w=null, $offset=null)
    {
        $offset = $this->params('offset');
        $properties = $this -> _getProperties('v_pelaporan_iku');
        $w = $this -> where;
        if ($this -> me['role_user'] == 3) {
            $w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND kd_regional =" . $this -> me['uid_regional'];
            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w .= " AND uid_provinsi IN ({$provinsiLainnya})";
            }
        }
        $o = $this -> primaryKey . " DESC";
        $post = $this -> post();
        if ($this -> params('search')) {
            $post['search'] = true;
            $post['form'] = json_decode(urldecode($this -> params('search')), 1);
        }
        if (isset($post['search'])) {
          $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
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
            if ($post['form']['tahun']) {
                $w .= " AND YEAR(tanggal) ='" . $post['form']['tahun'] . "'";
            }
            if ($post['form']['src_level']) {
                $w .= " AND role_user = " . $post['form']['src_level'];
            }
            if ($post['form']['src_peruntukan']) {
                $w .= " AND uid_rf_peruntukan = " . $post['form']['src_peruntukan'];
            }
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_metode']) {
                $w .= " AND uid_metode_pemantauan = " . $post['form']['src_metode'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_shu']) {
                $w .= " AND shu IS " . $post['form']['src_shu'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }
            if ($post['form']['verifikasi']) {
              $post['form']['verifikasi_'] =  $post['form']['verifikasi'] == 'un' ? 0 :  $post['form']['verifikasi'];
              if ($post['form']['verifikasi_'] == 0) {
                if ($this -> me['role_user'] == 1 || $this -> me['role_user'] == 0) {
                  $w .= " AND v_pusat = ".$post['form']['verifikasi_'];
                } elseif ($this -> me['role_user'] == 2) {
                  $w .= " AND v_provinsi = ".$post['form']['verifikasi_'];
                } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
                  $w .= " AND v_regional = ".$post['form']['verifikasi_'];
                }
              }else{
                $w .= " AND (v_pusat = ".$post['form']['verifikasi_'] ." OR v_provinsi = ".$post['form']['verifikasi_']." OR v_regional =".$post['form']['verifikasi_'].")";
              }
            }
            $search_json = urlencode(json_encode($post['form']));
            $urlVar .= 'search/' . $search_json . '/';
            $this->view->assign("search", $post['form']);
            $this->view->assign("search_json", $search_json);
        } else {
            $w .= " AND YEAR(tanggal) ='" . ACTIVE_YEAR . "'";
            $post['form']['tahun'] = ACTIVE_YEAR;

            $search_json = urlencode(json_encode($post['form']));
            $urlVar .= 'search/' . $search_json . '/';
            $this->view->assign("search", $post['form']);
            $this->view->assign("search_json", $search_json);
        }
          $this->tables->set("v_pelaporan_iku", "uid_pelaporan_iku");
          $offset = ($offset > 0 ? $offset - 1 : 0);
          $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
          $data	= $this->tables->fetch($w, $o, $paging);

          $this->view->assign("offset", $offset+1);
          $this->view->assign("viewExcel", $data);

          header("Content-type: application/vnd-ms-excel");
          header('Content-Disposition: attachment; filename="PELAPORAN_IKU_'.time().'.xls"');
          $html = $this->view->fetch('parts/contents/iku/index/excel.html');
          echo $html;
    }

    public function dataExcel2($w=null, $offset=null)
    {
        $offset = $this->params('offset');
        $properties = $this -> _getProperties('v_pelaporan_iku');
        $w = $this -> where;
        if ($this -> me['role_user'] == 3) {
            $w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND kd_regional =" . $this -> me['uid_regional'];

            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w .= " AND uid_provinsi IN ({$provinsiLainnya})";
            }
        }
        $o = $this -> primaryKey . " DESC";
        $post = $this -> post();
        if ($this -> params('search')) {
            $post['search'] = true;
            $post['form'] = json_decode(urldecode($this -> params('search')), 1);
        }
        if (isset($post['search'])) {
          $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
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
            if ($post['form']['tahun']) {
                $w .= " AND YEAR(tanggal) ='" . $post['form']['tahun'] . "'";
            }
            if ($post['form']['src_level']) {
                $w .= " AND role_user = " . $post['form']['src_level'];
            }
            if ($post['form']['src_peruntukan']) {
                $w .= " AND uid_rf_peruntukan = " . $post['form']['src_peruntukan'];
            }
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_metode']) {
                $w .= " AND uid_metode_pemantauan = " . $post['form']['src_metode'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_shu']) {
                $w .= " AND shu IS " . $post['form']['src_shu'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }
            if ($post['form']['verifikasi']) {
              $post['form']['verifikasi_'] =  $post['form']['verifikasi'] == 'un' ? 0 :  $post['form']['verifikasi'];
              if ($post['form']['verifikasi_'] == 0) {
                if ($this -> me['role_user'] == 1 || $this -> me['role_user'] == 0) {
                  $w .= " AND v_pusat = ".$post['form']['verifikasi_'];
                } elseif ($this -> me['role_user'] == 2) {
                  $w .= " AND v_provinsi = ".$post['form']['verifikasi_'];
                } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
                  $w .= " AND v_regional = ".$post['form']['verifikasi_'];
                }
              }else{
                $w .= " AND (v_pusat = ".$post['form']['verifikasi_'] ." OR v_provinsi = ".$post['form']['verifikasi_']." OR v_regional =".$post['form']['verifikasi_'].")";
              }
            }
            $search_json = urlencode(json_encode($post['form']));
            $urlVar .= 'search/' . $search_json . '/';
            $this->view->assign("search", $post['form']);
            $this->view->assign("search_json", $search_json);
        } else {
            $w .= " AND YEAR(tanggal) ='" . ACTIVE_YEAR . "'";
            $post['form']['tahun'] = ACTIVE_YEAR;

            $search_json = urlencode(json_encode($post['form']));
            $urlVar .= 'search/' . $search_json . '/';
            $this->view->assign("search", $post['form']);
            $this->view->assign("search_json", $search_json);
        }
            $o = "v_provinsi, v_regional, v_pusat ASC";
            $this->tables->set("v_pelaporan_iku", "uid_pelaporan_iku");
            $offset = ($offset > 0 ? $offset - 1 : 0);
            $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
            $data	= $this->tables->fetch($w, $o, $paging);

            $this->view->assign("offset", $offset+1);
            $this->view->assign("viewExcel", $data);

            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="VERIFIKASI_IKU_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/iku/verifikasi/excel.html');
            echo $html;
    }

    public function dataExcelLokasi($w = null, $offset = null)
    {
        $offset = $this->params('offset');

        $viewName   = "v_indeks_iku_lokasi";
        $primaryKey = "uid_indeks_iku_lokasi";

        $this->tables->set($viewName, $primaryKey);

        $w = $this->where;

        if ($this->me['role_user'] == 3) {
            $w .= " AND uid_kabkota =" . $this->me['uid_kabkota'];
        } elseif ($this->me['role_user'] == 2) {
            $w .= " AND uid_provinsi =" . $this->me['uid_provinsi'];
        } elseif ($this->me['role_user'] == 4 || $this->me['role_user'] == 5) {
            $w .= " AND kd_regional =" . $this->me['uid_regional'];
        }

        $post = $this->post();

        if ($this->params('search')) {
            $post['search'] = true;
            $post['form'] = json_decode(urldecode($this->params('search')), true);
        }

        if (isset($post['search'])) {

            if (!empty($post['form']['tahun'])) {
                $w .= " AND tahun = " . (int)$post['form']['tahun'];
            }

            if (!empty($post['form']['src_kabkota2'])) {
                $w .= " AND uid_kabkota = " . (int)$post['form']['src_kabkota2'];
            }

            if (!empty($post['form']['src_prop'])) {
                $w .= " AND uid_provinsi = " . (int)$post['form']['src_prop'];
            }

            if (!empty($post['form']['src_reg'])) {
                $w .= " AND kd_regional = " . (int)$post['form']['src_reg'];
            }

            if (!empty($post['form']['keyword'])) {
                $keyword = str_replace("=", "", strip_tags($post['form']['keyword']));
                $w .= " AND (
                            kode_lokasi LIKE '%{$keyword}%'
                            OR alamat LIKE '%{$keyword}%'
                        )";
            }

        } else {
            $w .= " AND tahun = " . ACTIVE_YEAR;
            $post['form']['tahun'] = ACTIVE_YEAR;
        }

        $o = $primaryKey . " DESC";

        // paging Excel
        $offset = ($offset > 0 ? $offset - 1 : 0);
        $paging = [
            "offset" => $offset,
            "limit"  => LIMIT_DOWNLOAD_EXCEL
        ];

        $data = $this->tables->fetch($w, $o, $paging);
        // $this->debug->show($data);

        // kirim ke view
        $this->view->assign("offset", $offset + 1);
        $this->view->assign("viewExcel", $data);

        // HEADER EXCEL
        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="DATA_PERHITUNGAN_LOKASI_IKU_' . time() . '.xls"');

        // TEMPLATE EXCEL
        echo $this->view->fetch('parts/contents/iku/indeks/excel_lokasi.html');
    }

    public function exportData()
    {
        $tahun = $this->params('y');
        $this->getDataIndeks($tahun);
    }

    public function expExcel($kabkota = null, $provinsi = null)
    {
        // $this->debug->show($provinsi);
        //load function
        require_once "functions.php";
        $this -> functions = new functions();
        $this -> view -> assign("functions", $this -> functions);
        if ($kabkota) {
            // $this->debug->show($kabkota['data']);
            $this -> view -> assign("viewk", $kabkota['data']);
            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="PERHITUNGAN_IKU_KABKOTA_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/iku/indeks/excel_kabkota.html');
            echo $html;
        } elseif ($provinsi) {
            $this -> view -> assign("viewp", $provinsi['data']);
            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="PERHITUNGAN_IKU_PROVINSI_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/iku/indeks/excel_provinsi.html');
            echo $html;
        }
    }

    public function indeks()
    {//menu index perhitungan iku
        $post = $this -> post();
        if (isset($post['submitAllKabkota'])){
          $post['form']['uid_indeks_kabkota_all'] = explode(",",base64_decode($post['form']['uid_indeks_kabkota_all'],TRUE));
          foreach ($post['form']['uid_indeks_kabkota_all'] as $key => $value) {
            $counting[] = $this -> _countIndeks($value, 1);
          }
          $tahun = explode("tahun", $counting[0]);
          if (count($counting) == count($post['form']['uid_indeks_kabkota_all'])) {
              $message = "Data Indeks telah diperbaharui";
          } else {
              $message = "Data Indeks gagal diperbaharui";
          }
        }
        if (isset($post['submitAllProvinsi'])){
          $post['form']['uid_indeks_provinsi_all'] = explode(",",base64_decode($post['form']['uid_indeks_provinsi_all'],TRUE));
          foreach ($post['form']['uid_indeks_provinsi_all'] as $key => $value) {
            $counting[] = $this -> _countIndeksProvinsi($value, 2);
          }
          $tahun = explode("tahun", $counting[0]);
          if (count($counting) == count($post['form']['uid_indeks_provinsi_all'])) {
              $message = "Data Indeks telah diperbaharui";
          } else {
              $message = "Data Indeks gagal diperbaharui";
          }
          $this -> view -> assign("showProv", 1);
        }
        if (isset($post['submit'])) {
            $counting = $this -> _countIndeks($post['form']['uid_indeks_iku'], 1);
            $tahun = explode("tahun", $counting);
            if ($counting) {
                $message = "Data Indeks " . $counting . " telah diperbaharui";
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
        }
        if (isset($post['submitProvinsi'])) {
            $counting = $this -> _countIndeksProvinsi($post['form']['uid_indeks_iku'], 2);
            $tahun = explode("tahun", $counting);
            if ($counting) {
                $message = "Data Indeks " . $counting . " telah diperbaharui";
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
            $this -> view -> assign("showProv", 1);
        }

        if (isset($post['submitNasional'])) {
            $dataIndeks = $this -> tables -> query("SELECT a.* FROM indeks_iku a WHERE a.uid_indeks_iku=" . $post['form']['uid_indeks_iku']);
            $tahun[1] = $dataIndeks['data'][0]['tahun'];
            $dataBobotProvinsi = $this->db->fetch("SELECT * FROM rf_provinsi_bobot WHERE deleted = 0 AND tahun=".$tahun[1]);
            if ($dataIndeks['total']) {
                $dataProvinsi = $this -> tables -> query("SELECT SUM(a.jumlah_penduduk) AS total_penduduk, SUM(a.luas_wilayah) AS total_luas_wilayah FROM rf_provinsi a");
                $sqlNasional = "SELECT a.* ,b.nama_propinsi, b.jumlah_penduduk, b.luas_wilayah, b.bobot_2023,
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
                      if($tahun[1] < 2023){
                        $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_provinsi'];
                      }elseif($tahun[1] < 2025) {
                        // $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_2023'];
                        $idexBobotProvinsi = array_search($value['uid_provinsi'], array_column($dataBobotProvinsi['data'],'uid_provinsi'));
                        $bobotProvinsi = (is_numeric($idexBobotProvinsi) ? $dataBobotProvinsi['data'][$idexBobotProvinsi]['bobot'] : 0);
                        $nilai_indeks_tmp[] = $value['nilai_indeks'] * $bobotProvinsi;
                      }else{
                        $nilai_indeks_tmp[] = $value['nilai_indeks_ina'];
                      }
                    }
                    if($tahun[1] < 2025){
                      $nilai_indeks = array_sum($nilai_indeks_tmp);
                    }else{
                      $nilai_indeks = array_sum($nilai_indeks_tmp) / count($nilai_indeks_tmp);
                    }
                }
                // $this->debug->show($nilai_indeks);
                $this -> tables -> set("indeks_iku", "uid_indeks_iku");
                $postIdx['form']['uid_indeks_iku'] = $post['form']['uid_indeks_iku'];
                $postIdx['form']['nilai_indeks'] = $nilai_indeks;
                $postIdx['submit'] = true;
                if ($this -> tables -> post($postIdx)) {
                    $statusUpdate = $this -> updateHistory($postIdx['form']['nilai_indeks'], 2, $dataIndeks['data'][0]['tahun'], 0, 0);
                    if ($statusUpdate) {
                        $message = "Data Indeks Nasional tahun " . $dataIndeks['data'][0]['tahun'] . " telah diperbaharui";
                    } else {
                        $message = "Data Indeks gagal diperbaharui";
                    }
                } else {
                    $message = "Data Indeks gagal diperbaharui";
                }
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
            $this -> view -> assign("showN", 1);
        }

        $this -> rfData();
        $this -> getDataIndeks($tahun[1]);
        $this -> getDataIndeksTitik($tahun[1]);
        $this -> cekLockSystem(3, 3.1, $this -> me['uid_users']);
        $this -> view -> assign("rf_catatan",$this->ref->getRekomendasiCatatan('iku','perhitungan')['data']);
        $this -> view -> assign("indeksActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("scrollTobtn", $post['scrollTo']);
        $this -> view -> assign("activeBtn", $post['form']['uid_indeks_iku']);
        $this -> view -> assign("icons", '<i class="la la-cloud"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS UDARA');
        $this -> view -> display("index.html");
    }

    private function _countIndeks($uid_indeks, $jenis_indeks)
    {//function for counting data pelaporan
        $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi, c.nama_kabkot FROM indeks_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.uid_indeks_iku=" . $uid_indeks);
        if ($dataIndeks['total']) {
            $dataIndeks = $dataIndeks['data'][0];
            if ($jenis_indeks == 1) {
                $w = " deleted = 0 AND uid_kabkota =" . $dataIndeks['uid_kabkota'];
                $dataReturn = $dataIndeks['nama_kabkot'] . ", Provinsi " . $dataIndeks['nama_propinsi'];

                //old
                // $w .= " AND no2 > 0 AND so2 > 0 AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31'";
                // $avgData = $this -> tables -> query("SELECT AVG(no2) AS avg_no2, AVG(so2) AS avg_so2, peruntukan FROM `v_pelaporan_iku` WHERE " . $w . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat=1) GROUP BY uid_rf_peruntukan");
                //end

                // new
                $w .= " AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31'";
                // $avgData = $this -> tables -> query("SELECT AVG(CASE WHEN no2 > 0 THEN no2 END) AS avg_no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS avg_so2, peruntukan FROM `v_pelaporan_iku` WHERE " . $w . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat=1) GROUP BY uid_rf_peruntukan");
                // $avgData = $this -> tables -> query("SELECT AVG(CASE WHEN no2 > 0 THEN no2 END) AS avg_no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS avg_so2, peruntukan FROM `v_pelaporan_iku` WHERE " . $w . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat=1) GROUP BY uid_rf_peruntukan");
                $avgData = $this -> tables -> query("SELECT AVG(CASE WHEN no2 > 0 THEN no2 END) AS avg_no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS avg_so2, AVG(CASE WHEN pm25 > 0 THEN pm25 END) AS avg_pm25, peruntukan FROM `v_pelaporan_iku` WHERE " . $w . " AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) GROUP BY uid_rf_peruntukan");
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
                      if($value['avg_pm25']){
                        $nilaiParam['pm25'][] = $value['avg_pm25'];
                      }
                    }
                    $cnIndeks['avgPeruntukanDetail'] = $avgData['data'];
                    $cnIndeks['avgPeruntukan'] = $nilaiParam;

                    $cnIndeks['rpp']['no2'] = array_sum($nilaiParam['no2']) / count($nilaiParam['no2']);
                    $cnIndeks['rpp']['so2'] = array_sum($nilaiParam['so2']) / count($nilaiParam['so2']);
                    $cnIndeks['rpp']['pm25'] = array_sum($nilaiParam['pm25']) / count($nilaiParam['pm25']);

                    $_cnIndeks = $this->_cnIndeks($cnIndeks['rpp'], $dataIndeks['tahun']);
                    $cnIndeks = array_merge($cnIndeks, $_cnIndeks);

                    $cnIndeks2025 = $this->iku->cnIndeks2025($dataIndeks['tahun'],$dataIndeks['uid_provinsi'],$dataIndeks['uid_kabkota'],false);

                    // $cnIndeks['idbm']['no2'] = $cnIndeks['rpp']['no2'] / 40;
                    // $cnIndeks['idbm']['so2'] = $cnIndeks['rpp']['so2'] / 20;
                    // $cnIndeks['rataanIndeks'] = array_sum($cnIndeks['idbm']) / count($cnIndeks['idbm']);
                    // $cnIndeks['rataanIndeks'] = $cnIndeks['rataanIndeks'];
                    // $cnIndeks['indeksIku'] = 100 - (50 / 0.9 * ($cnIndeks['rataanIndeks'] - 0.1));

                    $this -> tables -> set("indeks_iku", "uid_indeks_iku");
                    $postIdx['form']['uid_indeks_iku'] = $uid_indeks;
                    $postIdx['form']['status_hitung'] = 1;

                    $postIdx['form']['nilai_indeks'] = $cnIndeks['indeksIku'];
                    $postIdx['form']['json_data'] = json_encode($cnIndeks);

                    $postIdx['form']['json_data_ina'] = json_encode(array(
                      'titik' => $cnIndeks2025['titik'],
                      'detail' => $cnIndeks2025['provinsi']
                    ));
                    $postIdx['form']['nilai_indeks_ina'] = $cnIndeks2025['provinsi']['iku'];
                    $postIdx['form']['nilai_indeks_ina_mutu'] = $cnIndeks2025['provinsi']['iku_kategori'];
                    // $postIdx['form']['json_data'] = $cnIndeks;
                    $postIdx['submit'] = true;
                    if ($this -> tables -> post($postIdx)) {
                        $jenis_indeks = ($jenis_indeks == 2 ? 1 : 0);
                        $nilai_indeks = ($dataIndeks['tahun'] > 2024 ? $postIdx['form']['nilai_indeks_ina'] : $postIdx['form']['nilai_indeks']);
                        $statusUpdate = $this -> updateHistory($nilai_indeks, $jenis_indeks, $dataIndeks['tahun'], $dataIndeks['uid_kabkota'], $dataIndeks['uid_provinsi']);
                        if ($statusUpdate) {
                            return $dataReturn . " tahun " . $dataIndeks['tahun'];
                        } else {
                            return 0;
                        }
                    } else {
                        return 0;
                    }
                }else{
                  $this->tables->set("indeks_iku", "uid_indeks_iku");
                  $this->tables->hardDelete($dataIndeks['uid_indeks_iku']);
                  $jenis_indeks = ($jenis_indeks == 2 ? 1 : 0);
                  $statusUpdate = $this -> updateHistory(0, $jenis_indeks, $dataIndeks['tahun'], $dataIndeks['uid_kabkota'], $dataIndeks['uid_provinsi']);
                  if ($statusUpdate) {
                      return $dataReturn . " tahun " . $dataIndeks['tahun'];
                  } else {
                      return 0;
                  }
                }
            } elseif ($jenis_indeks == 2) {
                $this -> _countIndeksProvinsi($uid_indeks, $jenis_indeks);
            }
        } else {
            return 0;
        }
    }

    private function _countIndeksProvinsi($uid_indeks, $jenis_indeks)
    {
        $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi, c.nama_kabkot FROM indeks_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.uid_indeks_iku=" . $uid_indeks);
        if ($dataIndeks['total']) {
            $dataIndeks = $dataIndeks['data'][0];
            $dataReturn = "Provinsi " . $dataIndeks['nama_propinsi'];

            $dataIndeksKabkota = $this -> tables -> query("SELECT * FROM indeks_iku WHERE deleted=0 AND jenis_indeks = 0 AND uid_provinsi=" . $dataIndeks['uid_provinsi'] . " AND tahun=" . $dataIndeks['tahun']);

            //count rpp
            $nilaiParam['no2'] = null;
            $nilaiParam['so2'] = null;
            $nilaiParam['pm25'] = null;

            if ($dataIndeksKabkota['total']) {
                foreach ($dataIndeksKabkota['data'] as $key => $value) {
                    $dataIndeksKabkota['data'][$key]['json_data'] = json_decode($dataIndeksKabkota['data'][$key]['json_data'], true);
                    $nilaiParam['no2'][] = $dataIndeksKabkota['data'][$key]['json_data']['rpp']['no2'];
                    $nilaiParam['so2'][] = $dataIndeksKabkota['data'][$key]['json_data']['rpp']['so2'];
                    $nilaiParam['pm25'][] = $dataIndeksKabkota['data'][$key]['json_data']['rpp']['pm25'];
                }
                $cnIndeks['rpp']['no2'] = array_sum($nilaiParam['no2']) / count($nilaiParam['no2']);
                $cnIndeks['rpp']['so2'] = array_sum($nilaiParam['so2']) / count($nilaiParam['so2']);
                $cnIndeks['rpp']['pm25'] = array_sum($nilaiParam['pm25']) / count($nilaiParam['pm25']);

                $_cnIndeks = $this->_cnIndeks($cnIndeks['rpp'], $dataIndeks['tahun']);
                $cnIndeks = array_merge($cnIndeks, $_cnIndeks);

                $cnIndeks2025 = $this->iku->cnIndeks2025($dataIndeks['tahun'],$dataIndeks['uid_provinsi'],0,false);

                // $cnIndeks['idbm']['no2'] = $cnIndeks['rpp']['no2'] / 40;
                // $cnIndeks['idbm']['so2'] = $cnIndeks['rpp']['so2'] / 20;
                // $cnIndeks['rataanIndeks'] = array_sum($cnIndeks['idbm']) / count($cnIndeks['idbm']);
                // $cnIndeks['rataanIndeks'] = $cnIndeks['rataanIndeks'];
                // $cnIndeks['indeksIku'] = 100 - (50 / 0.9 * ($cnIndeks['rataanIndeks'] - 0.1));

                $this -> tables -> set("indeks_iku", "uid_indeks_iku");
                $postIdx['form']['uid_indeks_iku'] = $uid_indeks;
                $postIdx['form']['status_hitung'] = 1;

                $postIdx['form']['nilai_indeks'] = $cnIndeks['indeksIku'];
                $postIdx['form']['json_data'] = json_encode($cnIndeks);

                $postIdx['form']['json_data_ina'] = json_encode(array(
                  'titik' => $cnIndeks2025['titik'],
                  'detail' => $cnIndeks2025['provinsi']
                ));
                $postIdx['form']['nilai_indeks_ina'] = $cnIndeks2025['provinsi']['iku'];
                $postIdx['form']['nilai_indeks_ina_mutu'] = $cnIndeks2025['provinsi']['iku_kategori'];

                // $postIdx['form']['json_data'] = $cnIndeks;
                // $this->debug->show($postIdx);
                $postIdx['submit'] = true;
                if ($this -> tables -> post($postIdx)) {
                    $jenis_indeks = ($jenis_indeks == 2 ? 1 : 0);
                    $nilai_indeks = ($dataIndeks['tahun'] > 2024 ? $postIdx['form']['nilai_indeks_ina'] : $postIdx['form']['nilai_indeks']);
                    $statusUpdate = $this -> updateHistory($nilai_indeks, $jenis_indeks, $dataIndeks['tahun'], $dataIndeks['uid_kabkota'], $dataIndeks['uid_provinsi']);
                    if ($statusUpdate) {
                        return $dataReturn . " tahun " . $dataIndeks['tahun'];
                    } else {
                        return 0;
                    }
                } else {
                    return 0;
                }
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    private function _cnIndeks($rpp, $tahun){
      $cnIndeks["rpp"] = $rpp;

      // if($tahun >= 2025){
      //   // $cnIndeks["rpp"]["pm25"] = $cnIndeks["rpp"]["pm25"] ? $cnIndeks["rpp"]["pm25"] : 13.22;
      //
      //   $cnIndeks["idbm"]["no2"] = $cnIndeks["rpp"]["no2"]/50;
      //   $cnIndeks["idbm"]["so2"] = $cnIndeks["rpp"]["so2"]/45;
      //   $cnIndeks["idbm"]["pm25"] = $cnIndeks["rpp"]["pm25"]/15;
      //
      //   $cnIndeks["rataanIndeks"] = array_sum($cnIndeks['idbm']) / count($cnIndeks['idbm']);
      //   $cnIndeks['indeksIku'] = 100 - ((50 / 0.99) * ($cnIndeks['rataanIndeks'] - 0.01));
      // }elseif ($tahun < 2025) {
        $cnIndeks['idbm']['no2'] = $cnIndeks['rpp']['no2'] / 40;
        $cnIndeks['idbm']['so2'] = $cnIndeks['rpp']['so2'] / 20;

        $cnIndeks['rataanIndeks'] = array_sum($cnIndeks['idbm']) / count($cnIndeks['idbm']);
        $cnIndeks['indeksIku'] = 100 - (50 / 0.9 * ($cnIndeks['rataanIndeks'] - 0.1));
      // }

      return $cnIndeks;
    }

    private function updateHistory($nilai_indeks = 0, $jenis_indeks = 0, $tahun = 0, $uid_kabota = 0, $uid_provinsi = 0)
    {
        if ($jenis_indeks == 1) {
            $wHistory = 'deleted = 0 AND jenis_indeks = 1 AND tahun=' . $tahun . " AND uid_provinsi =" . $uid_provinsi;
        } elseif ($jenis_indeks == 2) {
            $wHistory = 'deleted = 0 AND jenis_indeks = 2 AND tahun=' . $tahun;
        } else {
            $wHistory = 'deleted = 0 AND jenis_indeks = 0 AND tahun=' . $tahun . " AND uid_provinsi =" . $uid_provinsi . " AND uid_kabkota=" . $uid_kabota;
        }
        $cekHistory = $this -> tables -> query("SELECT * FROM indeks_history WHERE " . $wHistory);
        // $this->debug->show($jenis_indeks);
        $ch = $cekHistory['data'][0];
        $updateHistory['form']['uid_indeks_history'] = ($cekHistory['total'] ? $cekHistory['data'][0]['uid_indeks_history'] : '');
        $updateHistory['form']['jenis_indeks'] = $jenis_indeks;
        $updateHistory['form']['uid_kabkota'] = $uid_kabota;
        $updateHistory['form']['uid_provinsi'] = $uid_provinsi;
        $updateHistory['form']['tahun'] = $tahun;
        $updateHistory['form']['iku'] = ($nilai_indeks ? $nilai_indeks : 0);
        if ($jenis_indeks == 1) {
            if($uid_provinsi == 36){
              $cnIklhTrue = (0.376 * $ch['ika']) + (0.405 * $nilai_indeks) + (0.219 * $ch['ikl']);
              $cnIklhFalse = (0.405 * $nilai_indeks);
              $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            }else{
              $cnIklhTrue = (0.340 * $ch['ika']) + (0.428 * $nilai_indeks) + (0.133 * $ch['ikl']) + (0.099 * $ch['ikal']);
              $cnIklhFalse = (0.428 * $nilai_indeks);
              $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            }
        // =(0,34*J3)+(0,428*I3)+(0,133*K3)+(0,099*L3)
        } elseif ($jenis_indeks == 2) {
            // IKLH = (0.340 x IKA Nasional)+(0.428 x IKU Nasional)+ (0.133 x IKL Nasional)+(0.099 x IKAL Nasional)
            $cnIklhTrue = (0.340 * $ch['ika']) + (0.428 * $nilai_indeks) + (0.133 * $ch['ikl']) + (0.099 * $ch['ikal']);
            $cnIklhFalse = ($nilai_indeks);
            $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
        } else {
            $cnIklhTrue = (0.376 * $ch['ika']) + (0.405 * $nilai_indeks) + (0.219 * $ch['ikl']);
            $cnIklhFalse = (0.405 * $nilai_indeks);
            $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            // =(0,376*J4)+(0,405*I4)+(0,219*K4)
        }
        // $this->debug->show($updateHistory);
        $updateHistory['submit'] = true;
        $this -> tables -> set("indeks_history", "uid_indeks_history");
        if ($this -> tables -> post($updateHistory)) {
            return 1;
        } else {
            return 0;
        }
    }

    private function getDataIndeks($tahunShow)
    {// function get data indeks pelaporan
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
            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w .= " AND a.uid_provinsi IN ({$provinsiLainnya})";
            }
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
                $w .= " AND a.tahun ='" . $post['form']['tahun'] . "'";
            }
            if ($post['form']['short']) {
                $o = "nilai_indeks ".$post['form']['short'];
            }
            // if ($post['form']['src_peruntukan']) {
            //     $w .= " AND uid_rf_peruntukan = " . $post['form']['src_peruntukan'];
            // }
            // if ($post['form']['src_kabkota2']) {
            //     $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            // }
            // if ($post['form']['src_shu']) {
            //     $w .= " AND shu is " . $post['form']['src_shu'];
            // }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        } else {
            $w .= " AND a.tahun ='" . ACTIVE_YEAR . "'";
            $post['form']['tahun'] = ACTIVE_YEAR;
            $this -> view -> assign("search", $post['form']);
        }
        $this -> yearActive = $post['form']['tahun'];
        //PAGING
        $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
        $limit = LIMIT_INDEKS;
        // $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota, d.iku AS target
                FROM indeks_iku a
                LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
                LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota
                LEFT JOIN rf_target_iklh d ON d.uid_provinsi = a.uid_provinsi AND d.uid_kabkota = a.uid_kabkota AND d.tahun = a.tahun AND d.deleted = 0
                WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;

        // $this->debug->show($sql);
        $data = $this -> tables -> query($sql);
        $All = $this -> db -> query('SELECT count(a.uid_indeks_iku) as x FROM indeks_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);
        $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);

        //get Nasional
        $getDataNasional = $this -> tables -> query("SELECT * FROM indeks_iku WHERE jenis_indeks = 2 AND tahun=" . $post['form']['tahun']);
        // NILI INTERVENSI AOH
        if($post['form']['tahun']==2022){
            $getDataNasional['data'][0]['nilai_indeks'] = 88.06;
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

        $kabkota  = $this->tables->query('SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.jenis_indeks=0 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $provinsi = $this->tables->query('SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iku a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.jenis_indeks=1 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        // $this->debug->show($this->params("ex"));

        if ($this->params("ex") == "kabkota") {
            $this->expExcel($kabkota, null);
        } elseif ($this->params("ex") == "provinsi") {
            $this->expExcel(null, $provinsi);
        } else {
            $this -> view -> assign("viewp", $provinsi['data']);
            $this -> view -> assign("viewk", $kabkota['data']);
            $this -> view -> assign("viewn", $nasional['data'][0]);
        }

        $this -> view -> assign("viewp_idx", base64_encode(implode(",",array_column($provinsi["data"],"uid_indeks_iku"))));
        $this -> view -> assign("viewk_idx", base64_encode(implode(",",array_column($kabkota["data"],"uid_indeks_iku"))));
    }

    private function getDataIndeksTitik($tahunShow){
      $viewName = "v_indeks_iku_lokasi";
      $primaryKey = "uid_indeks_iku_lokasi";

      $this -> tables -> set($viewName, $primaryKey);
      // $properties = $this -> _getProperties($viewName);
      $properties["data"] = ["kode_lokasi" , "alamat"];
      $properties['total'] = count($properties["data"]);
      $urlVar = BASEURL . $this -> url . '/';
      $w = $this -> where;
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
        if ($post['form']['tahun']) {
            $w .= " AND tahun =" . $post['form']['tahun'] . "";
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
          $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
          if ($post['form']['keyword']) {
              if ($properties['total']) {
                  $w .= " AND ";
                  $w .= "(";
                  for ($i = 0; $i < $properties['total']; $i++) {
                      $w .= $properties['data'][$i] . " LIKE '%" . $post['form']['keyword'] . "%' OR ";
                  }
                  $w .= $properties['data'][$properties['total'] - 1] . " LIKE '%" . $post['form']['keyword'] . "%' ";
                  $w .= ")";
              }
          }
          $urlVar .= 'xp/showM/search/' . urlencode(json_encode($post['form'])) . '/';
          $this -> view -> assign("search", $post['form']);

          if ($post['form']['lokasi_show']) {
              $showM = 1;
          }
      } else {
          $w .= " AND tahun =" . ACTIVE_YEAR . "";
          $post['form']['tahun'] = ACTIVE_YEAR;
          $post['form']['tampil_data'] = 1;
          // $this->debug->show($this->viewName);
          $urlVar .= 'xp/showM/search/' . urlencode(json_encode($post['form'])) . '/';
          $this -> view -> assign("search", $post['form']);
      }
      $this->yearActive = $post['form']['tahun'];
      $o = $primaryKey . " DESC";
      $search_json = urlencode(json_encode($post['form']));
      $this->view->assign("search_json", $search_json);

      //PAGING
      $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
      $limit = LIMIT;
      $data = $this -> tables -> query('SELECT * FROM ' . $viewName . ' WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
      $All = $this -> db -> query('SELECT count(' . $primaryKey . ') as x FROM ' . $viewName . ' WHERE ' . $w);
      $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);

      $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
      $listExport = $this->_getListExport($totalRow);
      $this->view->assign("listExport", $listExport);
      $this -> view -> assign("urlVar", $urlVar);
      $this -> view -> assign("totalRow", $totalRow);
      $this -> view -> assign("limit", $limit);
      $this -> view -> assign("page", $offset);

      // $this->debug->show($w);

      if ($showM) {
          $this -> view -> assign("showM", 1);
          $this -> view -> assign("vSungai", "");
          $this -> view -> assign("showN", "");
          $this -> view -> assign("showProv", "");
      } else {
          $this -> view -> assign("showM", ($this -> params("xp") ? 1 : ""));
      }
      $this -> view -> assign("viewLokasi", $data['data']);
    }

    private function rfData()
    {//function referensi data index
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

            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $wProvinsi .= " AND kd_propinsi IN ({$provinsiLainnya})";
              $wLokasi = " AND uid_provinsi IN ({$provinsiLainnya})";
            }
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
        $rf = $this -> tables -> fetch("deleted = 0 AND peruntukan = 1");
        $this -> view -> assign("peruntukan", $rf['data']);

        $this -> tables -> set("v_lokasi_pemantauan", "uid_lokasi_pemantauan");
        $rf = $this -> tables -> fetch("deleted = 0 AND uid_rf_component = 1 " . $wLokasi);
        $this -> view -> assign("lokasi", $rf['data']);

        $this -> tables -> set("rf_metode_pemantauan", "uid_metode_pemantauan");
        $rf = $this -> tables -> fetch("deleted = 0 AND peruntukan = 1 ");
        $this -> view -> assign("metode", $rf['data']);

        $this -> tables -> set("rf_kabkota", "kd_kota");
        $rf = $this -> tables -> fetch('deleted = 0');
        $this -> view -> assign("kabkotaSelect2", $rf['data']);

        if ($this->me['role_user']==2) {
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch('deleted=0 AND kd_provinsi='.$this -> me['uid_provinsi']);
            $this -> view -> assign("kabkotaSelect", $rf['data']);
        }
        if ($this->me['role_user']==4 || $this -> me['role_user'] == 5) {
            $this -> view -> assign("regSelect", $regSelect['data']);
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w = " AND kd_propinsi IN ({$provinsiLainnya})";
            }
            $rf = $this -> tables -> fetch('kd_regional='.$this -> me['uid_regional'].$w);
            $this -> view -> assign("propSelect", $rf['data']);
        }
        if ($this->me['role_user'] < 2) {
          $this -> view -> assign("regSelect", $regSelect['data']);
            $this -> view -> assign("propSelect", $propSelect['data']);
        }

        $this -> tables -> set("v_lab", "uid");
        $rf = $this -> tables -> fetch('deleted = 0 AND verifikasi = 1');
        $this -> view -> assign("lab", $rf['data']);
    }

    public function verifikasi()
    {//index verification menu
        $this -> getData();
        $this -> rfData();
        $this -> cekLockSystem(2, 2.1, $this -> me['uid_users']);
        $this->view->assign("rf_catatan",$this->ref->getRekomendasiCatatan('iku','verifikasi')['data']);
        $this -> view -> assign("verifikasiActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("icons", '<i class="la la-cloud"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS UDARA');
        $this -> view -> display("index.html");
    }

    public function getCatatanVerifikasi()
    {
        $uid = $this->params("x");
        $catatan = '';
        if (is_numeric($uid)) {
            $data = $this->tables->query("SELECT catatan_verifikator AS catatan, catatan_provinsi, catatan_verifikator_select, catatan_provinsi_select, catatan_regional, catatan_regional_select FROM ".$this->viewName." WHERE ".$this->primaryKey." = ".$uid);
            if($this->me['role_user'] == 2){
              $catatan = $data['data'][0]['catatan_provinsi'];
              $catatanSelect = $data['data'][0]['catatan_provinsi_select'];
            }else if($this->me['role_user'] == 4){
              $catatan = $data['data'][0]['catatan_regional'];
              $catatanSelect = $data['data'][0]['catatan_regional_select'];
            }else{
              $catatan = $data['data'][0]['catatan'];
              $catatanSelect = $data['data'][0]['catatan_verifikator_select'];
            }
            echo json_encode(array("statusCode"=>200, "catatan"=>$catatan,"catatanSelect"=>explode("|",$catatanSelect)));
        } else {
            echo json_encode(array("statusCode"=>401));
        }
    }
    public function catatanVerifikasi()
    {
        $dataRequest = file_get_contents("php://input");
        $dataRequest = json_decode($dataRequest, true);

        if (is_numeric($dataRequest['uid'])) {
            $dataUpdate['form']['uid_pelaporan_iku'] = $dataRequest['uid'];
            if($this->me['role_user'] == 2){
              $dataUpdate['form']['catatan_provinsi'] = $dataRequest['catatan'];
              $dataUpdate['form']['catatan_provinsi_select'] = implode("|",$dataRequest['catatanSelect']);
            }else if($this->me['role_user'] == 4){
              $dataUpdate['form']['catatan_regional'] = $dataRequest['catatan'];
              $dataUpdate['form']['catatan_regional_select'] = implode("|",$dataRequest['catatanSelect']);
            }else{
              $dataUpdate['form']['catatan_verifikator'] = $dataRequest['catatan'];
              $dataUpdate['form']['catatan_verifikator_select'] = implode("|",$dataRequest['catatanSelect']);
            }
            $dataUpdate['submit'] = true;
            $this->tables->set('pelaporan_iku', 'uid_pelaporan_iku');
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
            $this -> tables -> set("pelaporan_iku", "uid_pelaporan_iku");
            $post['form']['uid_pelaporan_iku'] = $uid;
            // $post['form'][$field] = ($act == 1 ? 1 : 0);
            $post['form'][$field] = $act == 'un' ? 0 : $act;
            $post['form'][$field.'_date'] = date("Y-m-d H:i:s");
            $post['form']['v_reject_status'] = 0;
            $post['submit'] = true;
            if ($this -> tables -> post($post)) {
                $this -> tables -> set("v_pelaporan_iku", "uid_pelaporan_iku");
                $dataLokasi = $this -> tables -> fetch("uid_pelaporan_iku=" . $uid);
                $this -> generatefieldInIndeks(date("Y", strtotime($dataLokasi['data'][0]['tanggal'])), $dataLokasi['data'][0]['uid_provinsi'], $dataLokasi['data'][0]['uid_kabkota']);
                echo 1;
            } else {
                echo 2;
            }
        } else {
            echo 2;
        }
    }

    private function generatefieldInIndeks($tahun, $uid_provinsi, $uid_kabota)
    {// function for generate field indeks
        $cekDataKabkota = $this -> tables -> query("SELECT uid_indeks_iku FROM indeks_iku WHERE deleted = 0 AND jenis_indeks =0  AND uid_kabkota=" . $uid_kabota . " AND tahun=" . $tahun);
        if (!$cekDataKabkota['total']) {
            $this -> tables -> set("indeks_iku", "uid_indeks_iku");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = $uid_provinsi;
            $postIdx['form']['uid_kabkota'] = $uid_kabota;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
        $cekDataProvinsi = $this -> tables -> query("SELECT uid_indeks_iku FROM indeks_iku WHERE deleted = 0 AND jenis_indeks =1 AND uid_provinsi=" . $uid_provinsi . " AND tahun=" . $tahun);
        if (!$cekDataProvinsi['total']) {
            $this -> tables -> set("indeks_iku", "uid_indeks_iku");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = $uid_provinsi;
            $postIdx['form']['uid_kabkota'] = 0;
            $postIdx['form']['jenis_indeks'] = 1;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }

        $cekDataNasional = $this -> tables -> query("SELECT uid_indeks_iku FROM indeks_iku WHERE deleted = 0 AND jenis_indeks =2 AND tahun=" . $tahun);
        if (!$cekDataNasional['total']) {
            $this -> tables -> set("indeks_iku", "uid_indeks_iku");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = 0;
            $postIdx['form']['uid_kabkota'] = 0;
            $postIdx['form']['jenis_indeks'] = 2;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
    }

    private function cekLockSystem($menu, $submenu, $users)
    {
        $messageLock = null;
        $lockAction = 0;
        $data = $this -> tables -> query("SELECT * FROM rf_lock_system WHERE deleted = 0 AND aktif = 1");
        if ($data['total']) {
            $data['data'][0]['menu'] = explode(",", $data['data'][0]['menu']);
            $data['data'][0]['submenu'] = explode(",", $data['data'][0]['submenu']);
            $data['data'][0]['kabkota'] = explode(",", $data['data'][0]['kabkota']);
            $data['data'][0]['provinsi'] = explode(",", $data['data'][0]['provinsi']);
            $data['data'][0]['p3e'] = explode(",", $data['data'][0]['p3e']);
            $data['data'][0]['direktorat'] = explode(",", $data['data'][0]['direktorat']);
            if (is_numeric(array_search($menu, $data['data'][0]['menu']))) {
                // $messageLock .= " abaikan pesan, halaman sedang dalam pengembangan";
                if (strtotime($data['data'][0]['tanggal_mulai']) <= strtotime(date('Y-m-d')) && strtotime($data['data'][0]['tanggal_selesai']) >= strtotime(date('Y-m-d'))) {
                  if (is_numeric(array_search($users, $data['data'][0]['kabkota'])) || is_numeric(array_search($users, $data['data'][0]['provinsi'])) || is_numeric(array_search($users, $data['data'][0]['p3e'])) || is_numeric(array_search($users, $data['data'][0]['direktorat']))) {
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
            }elseif (is_numeric(array_search($submenu, $data['data'][0]['submenu']))) {
              if (strtotime($data['data'][0]['tanggal_mulai']) <= strtotime(date('Y-m-d')) && strtotime($data['data'][0]['tanggal_selesai']) >= strtotime(date('Y-m-d'))) {
                if (is_numeric(array_search($users, $data['data'][0]['kabkota'])) || is_numeric(array_search($users, $data['data'][0]['provinsi'])) || is_numeric(array_search($users, $data['data'][0]['p3e'])) || is_numeric(array_search($users, $data['data'][0]['direktorat']))) {
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
                // $this->view->assign("listLockYear", json_encode($data['data'][0]['tahun']));
            } else {
                $lockActionYear = 0;
            }
        }
        $this -> view -> assign("messageLock", $messageLock);
        $this -> view -> assign("lockAction", $lockAction);
        $this -> view -> assign("lockActionYear", $lockActionYear);
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

    public function dateCek()
    {
        $dateCek = array(
            'timeStart' => strtotime(date("Y-m-d 01:00:00")),
            'timeEnd' => strtotime(date("Y-m-d 23:59:59")),
            'dateStart' => date("Y-m-d H:i:s", 1640887200),
            'dateEnd' => date("Y-m-d H:i:s", 1640969999),
        );
        $this->debug->show($dateCek);
    }
}
