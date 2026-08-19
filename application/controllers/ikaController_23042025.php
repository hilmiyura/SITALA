<?php
/**
 * created at 	: 01/10/2020
 * created by 	: dasendria team
 * desc		  	: controller INDEKS KUALITAS AIR IKLHK
 *
 */
class ikaController extends Front
{
    private $parameterPantau = ["Debit Air","pH","BOD","COD","TSS","DO","NO3-N","TOTAL FOSFAT","FECAL COLIFORM","KECERAHAN","KLOROFIL","TOTAL COLIFORM","TEMPERATUR AIR","TEMPERATUR UDARA","MINYAK LEMAK","DETERGEN","FENOL","TDS","SULFAT","KLORIDA","NITRIT","AMONIAK","TOTAL N","FLOURIDA","BELERANG","SIANIDA","KLORIN","WARNA","SAMPAH","Ba","B","Hg","As","Se","Fe","Cd","Co","Mn","Ni","Zn","Cu","Pb","Cr-6","ALDRIN","BHC","CHLORDANE","DDT","ENDRIN","HEPTACHLOR","LINDANE","METHOXYCHLOR","TOXAPAN","RADIOAKTIVITAS G-A","RADIOAKTIVITAS G-B"];
    public function init()
    {
        ($this -> session -> get('memberIKLH') ?: $this -> redirect("login"));
        date_default_timezone_set("Asia/Jakarta");
        //SET CUSTOM VIEWS FOLDER
        $this -> view -> setFolder('be');
        //LOAD MODELS
        $this -> loadModel("tables");
        $this -> loadModel("ref");
        $this -> loadModel("ika");
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

        // if($_SERVER['REMOTE_ADDR'] =='180.251.181.25') {
        //   $this->view->assign("dev", 1);
        // }
    }

    //INDEX FUNCTION IS A DEFAULT ACTION
    public function index()
    {
        $post = $this -> post();
        if (isset($post['submit'])) {
            $statusDuplikasi = 0;
            $checkDuplikasiData = $this->db->fetch("SELECT * FROM pelaporan_ika WHERE deleted = 0 AND tanggal='".$post['form']['tanggal']."' AND uid_lokasi_pemantauan=".$post['form']['uid_lokasi_pemantauan']);
            if($checkDuplikasiData['total']){
              if($checkDuplikasiData['data'][0]['uid_pelaporan_ika'] != $post['form']['uid_pelaporan_ika']){
                $getlokasi = $this->db->fetch("SELECT * FROM lokasi_pemantauan WHERE uid_lokasi_pemantauan=".$post['form']['uid_lokasi_pemantauan']);
                $getlokasiText = $getlokasi['data'][0]['kode_lokasi']." - ".$getlokasi['data'][0]['alamat']." - ".$getlokasi['data'][0]['alamat_detail'];
                $statusDuplikasi = 1;
              }
            }
            if($statusDuplikasi == 0){
              //check reject update
              if(isset($post['form']['uid_pelaporan_ika'])){
                $checkData = $this -> tables -> query("SELECT v_pusat, v_regional, v_provinsi,shu FROM pelaporan_ika WHERE uid_pelaporan_ika =".$post['form']['uid_pelaporan_ika'])['data'][0];
                if($checkData['shu']){
                  $post['form']['shu'] = $checkData['shu'];
                }
                $post['form']['v_reject_status'] = ($checkData['v_pusat'] == 2 ? 1 : ($checkData['v_regional'] == 2 ? 1 : ($checkData['v_provinsi'] == 2 ? 1 : 0)));
                if($post['form']['v_reject_status'] == 1){
                  $post['form']['v_pusat'] = 0;
                  $post['form']['v_regional'] = 0;
                  $post['form']['v_provinsi'] = 0;
                }
              }
              //end
              // $this->debug->show($checkData);
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

              $post['form']['uid_rf_bma'] = 2;
              $post['form']['cruser'] = $this -> me['uid_users'];
              if ($post['form'][$this->primaryKey]) {
                  unset($post['form']['cruser']);
                  $post['form']['chuser'] = $this->me['uid_users'];
              }
              if (!$post['form']['uid_lokasi_pemantauan']) {
                  // $post['form']['uid_rf_component'] = 2;
                  // $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
                  // if ($this -> tables -> post($post)) {
                  //     $post['form']['uid_lokasi_pemantauan'] = $this -> tables -> lastInsertID();
                  //     $post['submit'] = true;
                  //     $this -> tables -> set("pelaporan_ika", "uid_pelaporan_ika");
                  //     if ($this -> tables -> post($post)) {
                  //         $message = "Berhasil menyimpan data !";
                  //     } else {
                  //         $message = "Gagal menyimpan data !";
                  //     }
                  // } else {
                  //     $message = "Gagal menyimpan data !";
                  // }
                  $message = 'Kode lokasi harus dipilih';
              } else {
                $statusReject = $this->autoReject($post['form']);
                // $this->debug->show($statusReject);
                if($statusReject){
                  $post['form']['v_pusat'] = 2;
                  $post['form']['v_pusat_date'] = date("Y-m-d H:i:s");
                  $post['form']['catatan_verifikator'] = $statusReject;
                }
                // $this->debug->show($statusReject);
                $this -> tables -> set("pelaporan_ika", "uid_pelaporan_ika");
                if ($this -> tables -> post($post)) {
                    $message = "Berhasil menyimpan data !";
                } else {
                    $message = "Gagal menyimpan data !";
                }
              }
            }else{
              $message = "Gagal menyimpan data, data titik <b>".$getlokasiText."</b>  pada tanggal <b>".$post['form']['tanggal']."</b> sudah pernah diinput !";
            }
        }

        if (isset($post['submit-excel'])) {
            if ($this -> me['role_user'] == 3) {
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
                $post['form']['uid_kabkota'] = $this -> me['uid_kabkota'];
            }
            if ($this -> me['role_user'] == 2) {
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
            }
            $post['form']['cruser'] = $this -> me['uid_users'];
            $val = $_FILES['file_excel'];
            $ext = strtolower(strrchr($val['name'], "."));
            if ($ext == ".xls") {
                $files = $this -> functions -> uploadFile($_FILES['file_excel']);
            }
            // if ($_SERVER['REMOTE_ADDR'] == "180.251.188.111") {
            // 	if ($ext == ".xlsx") {
            // 		$files = $this -> functions -> uploadFile($_FILES['file_excel']);
            // 		// $this->debug->show($files);
            // 	}
            // }
            if ($files) {
                $excelReader = new Spreadsheet_Excel_Reader(UPLOADFOLDER . "docs/" . $files, true);
                $rows = $excelReader -> rowcount(0);
                for ($c = 1; $c <= 61; $c++) {
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
                if ($_SERVER['REMOTE_ADDR'] == '180.252.91.237') {
                    // $this->debug->show($data);
                }

                $tmpCn = 0;
                $tmpTgl = NULL;
                $message = '';
                foreach ($data as $key => $vals) {
                    if ($vals[1] != "-") {
                        // $latitude = str_replace(",", ".", trim($vals[4]));
                        // $longitude = str_replace(",", ".", trim($vals[5]));
                        $periode = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[3]);
                        // $where = "deleted = 0 AND uid_rf_component= 2 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND uid_kabkota=" . $post['form']['uid_kabkota'] . " AND alamat='" . $vals[2] . "' AND latitude=" . $latitude . " AND longitude=" . $longitude;
                        // $where = "deleted = 0 AND uid_rf_component= 2 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND uid_kabkota=" . $post['form']['uid_kabkota'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun=" . date("Y", strtotime($vals[1]));
                        // $where = "deleted = 0 AND uid_rf_component= 2 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun=" . date("Y", strtotime($vals[1]));
                        if ($this -> me['role_user'] == 3) { //Kabkota
                          // $where = "deleted = 0 AND uid_rf_component= 2 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND uid_kabkota=" . $post['form']['uid_kabkota'] . " AND uid_rf_pelaksana=4 AND kode_lokasi='" . $vals[2] . "' AND tahun=". date("Y", strtotime($vals[1]));
                          $where = "deleted = 0 AND uid_rf_component= 2 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND uid_kabkota=" . $post['form']['uid_kabkota'] . " AND uid_rf_pelaksana=4 AND kode_lokasi='" . $vals[2] . "' AND tahun LIKE '%". date("Y", strtotime($vals[1]))."%'";
                        }else {
                          // $where = "deleted = 0 AND uid_rf_component= 2 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun=". date("Y", strtotime($vals[1]));
                          $where = "deleted = 0 AND uid_rf_component= 2 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun LIKE '%". date("Y", strtotime($vals[1]))."%'";
                          if ($this -> me['role_user'] <= 1) {
                            $where = $where.' AND uid_rf_pelaksana=1';
                          }elseif ($this -> me['role_user'] == 2) {
                            $where = $where.' AND uid_rf_pelaksana=3';
                          }elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
                            $where = $where.' AND uid_rf_pelaksana=2';
                          }
                        }

                        $date = date("Y-m-d", strtotime($vals[1]));

                        if ($this -> cekLocation($where, $vals, $post['form']) && $date <= date("Y-m-d")) {
                          $postLaporan['form']['uid_pelaporan_ika'] = "";
                          $postLaporan['form']['cruser'] = $post['form']['cruser'];
                          $postLaporan['form']['uid_rf_bma'] = 2;
                          $postLaporan['form']['uid_lokasi_pemantauan'] = $this -> cekLocation($where, $vals, $post['form']);
                          $postLaporan['form']['periode_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[3]);
                          // $dates = explode("/",$vals[1]);
                          // $postLaporan['form']['tanggal'] = $dates[2]."-".$dates[1]."-".$dates[0];
                          $postLaporan['form']['tanggal'] = date("Y-m-d", strtotime($vals[1]));
                          $postLaporan['form']['kategori'] = $this -> kategori($vals[4]);
                          $postLaporan['form']['debit'] = str_replace(",", ".", $vals[5]);
                          $postLaporan['form']['ph'] = str_replace(",", ".", $vals[6]);
                          $postLaporan['form']['bod'] = str_replace(",", ".", $vals[7]);
                          $postLaporan['form']['cod'] = str_replace(",", ".", $vals[8]);
                          $postLaporan['form']['tss'] = str_replace(",", ".", $vals[9]);
                          $postLaporan['form']['do_p'] = str_replace(",", ".", $vals[10]);
                          $postLaporan['form']['do_max_p'] = str_replace(",", ".", $vals[11]);
                          $postLaporan['form']['no3_n'] = str_replace(",", ".", $vals[12]);
                          $postLaporan['form']['total_phosphat'] = str_replace(",", ".", $vals[13]);
                          $postLaporan['form']['fecal_coliform'] = str_replace(",", ".", $vals[14]);
                          $postLaporan['form']['kecerahan'] = str_replace(",", ".", $vals[15]);
                          $postLaporan['form']['klorofil_a'] = str_replace(",", ".", $vals[16]);
                          $postLaporan['form']['total_coliform'] = str_replace(",", ".", $vals[17]);
                          $postLaporan['form']['temperatur_air'] = str_replace(",", ".", $vals[18]);
                          $postLaporan['form']['temperatur_udara'] = str_replace(",", ".", $vals[19]);
                          $postLaporan['form']['minyak_lemak'] = str_replace(",", ".", $vals[20]);
                          $postLaporan['form']['detergen_total'] = str_replace(",", ".", $vals[21]);
                          $postLaporan['form']['fenol'] = str_replace(",", ".", $vals[22]);
                          $postLaporan['form']['tds'] = str_replace(",", ".", $vals[23]);
                          $postLaporan['form']['sulfat'] = str_replace(",", ".", $vals[24]);
                          $postLaporan['form']['klorida'] = str_replace(",", ".", $vals[25]);
                          $postLaporan['form']['nitrit'] = str_replace(",", ".", $vals[26]);
                          $postLaporan['form']['amoniak'] = str_replace(",", ".", $vals[27]);
                          $postLaporan['form']['total_nitrogen'] = str_replace(",", ".", $vals[28]);
                          $postLaporan['form']['florida'] = str_replace(",", ".", $vals[29]);
                          $postLaporan['form']['belerang_sbg_h2s'] = str_replace(",", ".", $vals[30]);
                          $postLaporan['form']['sianida'] = str_replace(",", ".", $vals[31]);
                          $postLaporan['form']['klorin_bebas'] = str_replace(",", ".", $vals[32]);
                          $postLaporan['form']['warna'] = str_replace(",", ".", $vals[33]);
                          $postLaporan['form']['sampah'] = str_replace(",", ".", $vals[34]);
                          $postLaporan['form']['ba'] = str_replace(",", ".", $vals[35]);
                          $postLaporan['form']['bo'] = str_replace(",", ".", $vals[36]);
                          $postLaporan['form']['hg'] = str_replace(",", ".", $vals[37]);
                          $postLaporan['form']['as_'] = str_replace(",", ".", $vals[38]);
                          $postLaporan['form']['se'] = str_replace(",", ".", $vals[39]);
                          $postLaporan['form']['fe'] = str_replace(",", ".", $vals[40]);
                          $postLaporan['form']['cd'] = str_replace(",", ".", $vals[41]);
                          $postLaporan['form']['co'] = str_replace(",", ".", $vals[42]);
                          $postLaporan['form']['mn'] = str_replace(",", ".", $vals[43]);
                          $postLaporan['form']['ni'] = str_replace(",", ".", $vals[44]);
                          $postLaporan['form']['zn'] = str_replace(",", ".", $vals[45]);
                          $postLaporan['form']['cu'] = str_replace(",", ".", $vals[46]);
                          $postLaporan['form']['pb'] = str_replace(",", ".", $vals[47]);
                          $postLaporan['form']['cr_6'] = str_replace(",", ".", $vals[48]);
                          $postLaporan['form']['aldrin'] = str_replace(",", ".", $vals[49]);
                          $postLaporan['form']['bhc'] = str_replace(",", ".", $vals[50]);
                          $postLaporan['form']['chlordane'] = str_replace(",", ".", $vals[51]);
                          $postLaporan['form']['ddt'] = str_replace(",", ".", $vals[52]);
                          $postLaporan['form']['endrin'] = str_replace(",", ".", $vals[53]);
                          $postLaporan['form']['heptachlor'] = str_replace(",", ".", $vals[54]);
                          $postLaporan['form']['lindane'] = str_replace(",", ".", $vals[55]);
                          $postLaporan['form']['methoxychlor'] = str_replace(",", ".", $vals[56]);
                          $postLaporan['form']['toxapan'] = str_replace(",", ".", $vals[57]);
                          $postLaporan['form']['radioaktivitas_gross_a'] = str_replace(",", ".", $vals[58]);
                          $postLaporan['form']['radioaktivitas_gross_b'] = str_replace(",", ".", $vals[59]);
                          $postLaporan['form']['e_coli'] = str_replace(",", ".", $vals[60]);

                          $checkDuplikasiData = $this->db->fetch("SELECT * FROM pelaporan_ika WHERE deleted = 0 AND tanggal='".$postLaporan['form']['tanggal']."' AND uid_lokasi_pemantauan=".$postLaporan['form']['uid_lokasi_pemantauan']);
                          if($checkDuplikasiData['total'] == 0){
                            $postLaporan['submit'] = true;

                            $statusReject = $this->autoReject($postLaporan['form']);
                            if($statusReject){
                              $postLaporan['form']['v_pusat'] = 2;
                              $postLaporan['form']['v_pusat_date'] = date("Y-m-d H:i:s");
                              $postLaporan['form']['catatan_verifikator'] = $statusReject;
                            }

                            $this -> tables -> set("pelaporan_ika", "uid_pelaporan_ika");
                            if ($this -> tables -> post($postLaporan)) {
                              $tmpCn++;
                            }else{
                              $message .= "Gagal menyimpan data kode lokasi ". $vals[2] ."<br>";
                            }
                          }else{
                            $message .= "Gagal menyimpan data kode lokasi ". $vals[2] ." pada tanggal ".$postLaporan['form']['tanggal'].", sudah pernah diinput<br>";
                          }
                        }else {
                          $message .= "Gagal menyimpan data kode lokasi ". $vals[2] .", kesalahan pada kode lokasi atau tanggal melebihi tanggal saat ini<br>";
                        }
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
        $this -> cekLockSystem(1, 1.2, $this -> me['uid_users']);
        $this -> view -> assign("pelaporanActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("icons", '<i class="la la-tint"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS AIR');
        $this -> view -> display("index.html");
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
            // if ($this -> me['role_user'] == 3) {
            //     $post['uid_provinsi'] = $this -> me['uid_provinsi'];
            //     $post['uid_kabkota'] = $this -> me['uid_kabkota'];
            // }
            // if ($this -> me['role_user'] == 2) {
            //     $post['uid_provinsi'] = $this -> me['uid_provinsi'];
            // }
            // $push_location['form']['uid_provinsi'] = $post['uid_provinsi'];
            // $push_location['form']['uid_kabkota'] = $post['uid_kabkota'];
            // $push_location['form']['latitude'] = str_replace(",", ".", trim($vals[4]));
            // $push_location['form']['longitude'] = str_replace(",", ".", trim($vals[5]));
            // $push_location['form']['uid_rf_component'] = 2;
            // $push_location['submit'] = true;
            // if ($this -> tables -> post($push_location)) {
            //     return $this -> tables -> lastInsertID();
            // }
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

    private function autoReject($post){
      $pesanReject = [];
      if(!$post['shu']){
        $pesanReject[] = "Mohon lengkapi SHU";
      }

      if(!$post['kategori']){
        $pesanReject[] = "Mohon lengkapi kategori lokasi";
      }

      if($post['kategori'] <= 2){
        if(!$post['ph'] || !$post['bod'] || !$post['cod'] || !$post['do_p'] || !$post['tss'] || !$post['no3_n'] || !$post['total_phosphat'] || !$post['fecal_coliform']){
          $pesanReject[] = "Mohon lengkapi parameter wajib IKA";
        }
      }

      if($post['kategori'] == 3){
        if(!$post['ph'] || !$post['bod'] || !$post['cod'] || !$post['do_p'] || !$post['tss'] || !$post['total_nitrogen'] || !$post['total_phosphat'] || !$post['fecal_coliform'] || !$post['kecerahan']){
          $pesanReject[] = "Mohon lengkapi parameter wajib IKA";
        }

        // if($post['klorofil_a'] < 1){
        //   $pesanReject[] = "Terdapat data anomali Klorofil a";
        // }
      }

      if($post['bod'] > $post['cod']){
        $pesanReject[] = "Terdapat data anomali BOD > COD";
      }

      if($post['total_coliform'] > 0){
        if($post['fecal_coliform'] > $post['total_coliform']){
          $pesanReject[] = "Terdapat data anomali Fecal Coli > Total Coli";
        }
      }

      // if($post['fecal_coliform'] < 1.8){
      //   $pesanReject[] = "Terdapat data anomali Fecal Coli";
      // }

      if($post['ph'] == 0 || $post['ph'] >= 14){
        $pesanReject[] = "Terdapat data anomali Ph";
      }

      // if($post['do_p'] < 0.1 || $post['do_p'] >= 10){
      //   $pesanReject[] = "Terdapat data anomali DO";
      // }

      // if($post['total_phosphat'] < 0.01){
      //   $pesanReject[] = "Terdapat data anomali Total Fosfat";
      // }

      // if($post['tss'] < 2.5){
      //   $pesanReject[] = "Terdapat data anomali TSS";
      // }

      if(count($pesanReject)){
        return implode(",", $pesanReject);
      }else{
        return "";
      }
    }

    private function getData()
    {
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
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_shu']) {
                $w .= " AND shu IS " . $post['form']['src_shu'];
            }
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_kategori']) {
                $w .= " AND kategori = " . $post['form']['src_kategori'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }

            if ($post['form']['src_pemantauan']) {
                $w .= " AND uid_lokasi_pemantauan = " . $post['form']['src_pemantauan'];
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
        if ($this -> url == "ika/verifikasi") {
            $o = "v_provinsi,v_regional,v_pusat ASC";
        }
        // $this->debug->show($w);
        $search_json = urlencode(json_encode($post['form']));
        $this->view->assign("search_json", $search_json);
        //PAGING
        $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
        $limit = LIMIT;
        $data = $this -> tables -> query('SELECT * FROM ' . $this -> viewName . ' WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $All = $this -> db -> query('SELECT count(' . $this -> primaryKey . ') as x FROM ' . $this -> viewName . ' WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);
        // $this->debug->show('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        // $this->debug->show($data);

        $uid_lokasi_pemantauan_list = implode(",",array_keys(array_column($data["data"],null,"uid_lokasi_pemantauan")));
        if($uid_lokasi_pemantauan_list){
          $checkJumlahLapor = $this->db->fetch("SELECT uid_lokasi_pemantauan, COUNT(uid_lokasi_pemantauan) AS total FROM pelaporan_ika WHERE deleted = 0 AND uid_lokasi_pemantauan IN({$uid_lokasi_pemantauan_list}) AND YEAR(tanggal) = ".$post['form']['tahun']." GROUP BY uid_lokasi_pemantauan HAVING total < 2")["data"];
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
        }

        $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);
        $listExport = $this->_getListExport($totalRow);
        $this->view->assign("listExport", $listExport);
        $this -> view -> assign("urlVar", $urlVar);
        $this -> view -> assign("totalRow", $totalRow);
        $this -> view -> assign("limit", $limit);
        $this -> view -> assign("page", $offset);
        $this -> view -> assign("view", $data['data']);
    }

    public function editData()
    {
        header("Content-Type: application/json; charset=UTF-8");
        if ($this -> params("x")) {
            $this -> tables -> set("pelaporan_ika", "uid_pelaporan_ika");
            $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_ika=" . $this -> params("x"));
            echo json_encode($dataEdit['data'][0]);
        }
    }

    public function deletedData()
    {
        $post = $this -> post();
        if (isset($post['x'])) {
            $this -> tables -> set("pelaporan_ika", "uid_pelaporan_ika");
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

    public function dataExcel($w, $offset)
    {
        $offset = $this->params('offset');
        $properties = $this -> _getProperties('v_pelaporan_ika');
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
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_shu']) {
                $w .= " AND shu IS " . $post['form']['src_shu'];
            }
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_kategori']) {
                $w .= " AND kategori = " . $post['form']['src_kategori'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }
            if ($post['form']['src_pemantauan']) {
                $w .= " AND uid_lokasi_pemantauan = " . $post['form']['src_pemantauan'];
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

        $this->tables->set("v_pelaporan_ika", "uid_pelaporan_ika");
        $offset = ($offset > 0 ? $offset - 1 : 0);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);
        // $this->debug->show($data);
        $this->view->assign("offset", $offset+1);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="PELAPORAN_IKA_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/ika/index/excel.html');
        echo $html;
    }

    public function dataExcel2($w, $offset)
    {
        $offset = $this->params('offset');
        $properties = $this -> _getProperties('v_pelaporan_ika');
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
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_shu']) {
                $w .= " AND shu IS " . $post['form']['src_shu'];
            }
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_kategori']) {
                $w .= " AND kategori = " . $post['form']['src_kategori'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }
            if ($post['form']['src_pemantauan']) {
                $w .= " AND uid_lokasi_pemantauan = " . $post['form']['src_pemantauan'];
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
            $o = "v_provinsi,v_regional,v_pusat ASC";
        $this->tables->set("v_pelaporan_ika", "uid_pelaporan_ika");
        $offset = ($offset > 0 ? $offset - 1 : 0);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        $this->view->assign("offset", $offset+1);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="VERIFIKASI_IKA_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/ika/verifikasi/excel.html');
        echo $html;
    }

    public function dataExcelStatusMutu($w, $offset, $tahunShow)
    {
        $offset = $this->params('offset');
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

            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w .= " AND uid_provinsi IN ({$provinsiLainnya})";
            }
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
        // $this->debug->show($w);
        //PAGING
        $limit = LIMIT_DOWNLOAD_EXCEL;
        $offset = ($offset > 0 ? $offset - 1 : 0);
        // $paging	= array("offset"=>$offset, "limit"=>500);
        // $data	= $this->tables->fetch($w, $o, $paging);
        $data = $this -> tables -> query('SELECT * FROM ' . $this -> viewName . ' WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);

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
        // $this->debug->show($data);
        if ($showM) {
            $this -> view -> assign("showM", 1);
            $this -> view -> assign("vSungai", "");
            $this -> view -> assign("showN", "");
            $this -> view -> assign("showProv", "");
        } else {
            $this -> view -> assign("showM", ($this -> params("xp") ? 1 : ""));
        }
        // $this->debug->show($data["data"]);
        $this->view->assign("offset", $offset+1);
        $this -> view -> assign("viewMutu", $data['data']);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="PERHITUNGAN_IKA_STATUS_MUTU_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/ika/indeks/excel_statusmutu.html');
        echo $html;
    }

    public function indeks()
    {
        date_default_timezone_set("Asia/Jakarta");
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
            $counting[] = $this -> _countIndeks($value, 2);
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
            $counting = $this -> _countIndeks($post['form']['uid_indeks_ika'], 1);
            $tahun = explode("tahun", $counting);
            if ($counting) {
                $message = "Data Indeks " . $counting . " telah diperbaharui";
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
        }
        if (isset($post['submitProvinsi'])) {
            $counting = $this -> _countIndeks($post['form']['uid_indeks_ika'], 2);
            $tahun = explode("tahun", $counting);
            if ($counting) {
                $message = "Data Indeks " . $counting . " telah diperbaharui";
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
            $this -> view -> assign("showProv", 1);
        }

        if (isset($post['submitNasional'])) {
            $dataIndeks = $this -> tables -> query("SELECT a.* FROM indeks_ika a WHERE a.uid_indeks_ika=" . $post['form']['uid_indeks_ika']);
            $tahun[1] = $dataIndeks['data'][0]['tahun'];
            $dataBobotProvinsi = $this->db->fetch("SELECT * FROM rf_provinsi_bobot WHERE deleted = 0 AND tahun=".$tahun[1]);
            if ($dataIndeks['total']) {
                $dataProvinsi = $this -> tables -> query("SELECT SUM(a.jumlah_penduduk) AS total_penduduk, SUM(a.luas_wilayah) AS total_luas_wilayah FROM rf_provinsi a");
                $sqlNasional = "SELECT a.* ,b.nama_propinsi, b.jumlah_penduduk, b.luas_wilayah, b.bobot_2023,
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
                      if($tahun[1] < 2023){
                        $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_provinsi'];
                      }else{
                        // $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_2023'];
                        $idexBobotProvinsi = array_search($value['uid_provinsi'], array_column($dataBobotProvinsi['data'],'uid_provinsi'));
                        $bobotProvinsi = (is_numeric($idexBobotProvinsi) ? $dataBobotProvinsi['data'][$idexBobotProvinsi]['bobot'] : 0);
                        $nilai_indeks_tmp[] = $value['nilai_indeks'] * $bobotProvinsi;
                      }
                    }
                    $nilai_indeks = array_sum($nilai_indeks_tmp);
                }
                $this -> tables -> set("indeks_ika", "uid_indeks_ika");
                $postIdx['form']['uid_indeks_ika'] = $post['form']['uid_indeks_ika'];
                $postIdx['form']['nilai_indeks'] = $nilai_indeks;
                $postIdx['submit'] = true;
                if ($this -> tables -> post($postIdx)) {
                    $statusUpdate = $this -> updateHistory($nilai_indeks, 2, $dataIndeks['data'][0]['tahun'], 0, 0);
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
        $this -> getDataIndeksMutu($tahun[1]);
        $this -> cekLockSystem(3, 3.2, $this -> me['uid_users']);
        $this->view->assign("parameter_pantau",$this->parameterPantau);
        $this -> view -> assign("rf_catatan",$this->ref->getRekomendasiCatatan('ika','perhitungan')['data']);
        $this -> view -> assign("indeksActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("scrollTobtn", $post['scrollTo']);
        $this -> view -> assign("activeBtn", $post['form']['uid_indeks_ika']);
        $this -> view -> assign("icons", '<i class="la la-tint"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS AIR');
        $this -> view -> display("index.html");
    }

    private function _countIndeks($uid_indeks, $jenis_indeks)
    {//function for counting data pelaporan
        $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi, c.nama_kabkot FROM indeks_ika a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.uid_indeks_ika=" . $uid_indeks);
        if($dataIndeks['total']) {
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
          $w .= " AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31' AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) ";

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

          if($dataIndeks['tahun'] >= 2025){
            $parameterSql = $this->ika->parameterIndeks2025();
          }elseif ($dataIndeks['tahun'] < 2025) {
            $parameterSql = $this->ika->parameterIndeks2024();
          }

          $sql = "SELECT a.uid_pelaporan_ika, a.kategori, a.kode_lokasi, {$parameterSql} FROM v_pelaporan_ika a INNER JOIN rf_bma b ON b.uid_rf_bma = a.uid_rf_bma WHERE " . $w;

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
            $tmpCn['countParams'][$key]['total_phosphat_l'] = ($value['kategori'] == 3 ? $value['total_phosphat_danau_l'] : $value['total_phosphat_l']);
            $tmpCn['countParams'][$key]['fecal_coliform_l'] = $value['fecal_coliform_l'];
            $tmpCn['countParams'][$key]['kecerahan_l'] = $value['kecerahan_l'];
            $tmpCn['countParams'][$key]['klorofil_a_l'] = $value['klorofil_a_l'];
            $tmpCn['countParams'][$key]['total_nitrogen_l'] = ($value['kategori'] == 3 ? $value['total_nitrogen_danau_l'] : $value['total_nitrogen_l']);

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
          // if ($devIP) {
          //   $this->debug->show($cnIndeks);
          // }
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
          $postIdx['submit'] = true;
          $this -> tables -> set("indeks_ika", "uid_indeks_ika");
          if ($this -> tables -> post($postIdx)) {
              $jenis_indeks = ($jenis_indeks == 2 ? 1 : 0);
              $statusUpdate = $this -> updateHistory($postIdx['form']['nilai_indeks'], $jenis_indeks, $dataIndeks['tahun'], $dataIndeks['uid_kabkota'], $dataIndeks['uid_provinsi']);
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
    }

    public function test()
    {
        // $data = $this->_countIndekStatusMutuGroup($this->params("x"),2020,2,1,'CILIWUNG');
        // $this->debug->show($data);
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
            $this -> view -> assign("viewk", $kabkota['data']);
            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="PERHITUNGAN_IKA_KABKOTA_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/ika/indeks/excel_kabkota.html');
            echo $html;
        } elseif ($provinsi) {
            $this -> view -> assign("viewp", $provinsi['data']);
            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="PERHITUNGAN_IKA_PROVINSI_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/ika/indeks/excel_provinsi.html');
            echo $html;
        }
    }

    // public function tests(){
    //   $data = $this->_countIndekStatusMutu(69415);
    //   $this->debug->show($data);
    // }

    public function _countIndekStatusMutu($idIndeks)
    {
        $this->loadModel('tables');
        // $whereIndeksParams = "deleted= 0 AND uid_pelaporan_ika=".$idIndeks." AND a.ph > 0 AND a.temperatur_air > 0 AND a.tds > 0 AND a.do_p > 0 AND a.tss > 0 AND a.bod > 0 AND a.cod > 0 AND a.nitrit > 0 AND a.no3_n > 0 AND a.amoniak > 0 AND a.total_phosphat > 0 AND a.klorin_bebas > 0 AND a.fenol > 0 AND a.minyak_lemak > 0 AND a.detergen_total > 0 AND a.fecal_coliform > 0 AND a.total_coliform > 0 AND a.sianida > 0 AND a.sulfat > 0 AND a.pb > 0 AND a.cd > 0";
        $whereIndeksParams = "deleted= 0 AND uid_pelaporan_ika=" . $idIndeks;
        $cekData = $this -> tables -> query("SELECT a.uid_pelaporan_ika, YEAR(a.tanggal) AS tahun, a.uid_lokasi_pemantauan FROM pelaporan_ika a WHERE " . $whereIndeksParams);
        if ($cekData['total']) {
            $dataKelas = null;
            $sqlKelas = null;
            $parameterSql = $this->ika->parameterIndeks2024StatusMutu();

            for ($i = 1; $i <= 4; $i++) {
                $sql = "SELECT a.kategori, {$parameterSql} FROM pelaporan_ika a INNER JOIN rf_bma b ON b.uid_rf_bma = a.bma_" . $i . " WHERE deleted = 0 AND uid_pelaporan_ika=" . $idIndeks;
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
        $ch = $cekHistory['data'][0];
        $updateHistory['form']['uid_indeks_history'] = ($cekHistory['total'] ? $cekHistory['data'][0]['uid_indeks_history'] : '');
        $updateHistory['form']['jenis_indeks'] = $jenis_indeks;
        $updateHistory['form']['uid_kabkota'] = $uid_kabota;
        $updateHistory['form']['uid_provinsi'] = $uid_provinsi;
        $updateHistory['form']['tahun'] = $tahun;
        $updateHistory['form']['ika'] = ($nilai_indeks ? $nilai_indeks : 0);
        if ($jenis_indeks == 1) {
            if($uid_provinsi == 36){
              $cnIklhTrue = (0.376 * $nilai_indeks) + (0.405 * $ch['iku']) + (0.219 * $ch['ikl']);
              $cnIklhFalse = (0.376 * $nilai_indeks);
              $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            }else{
              $cnIklhTrue = (0.340 * $nilai_indeks) + (0.428 * $ch['iku']) + (0.133 * $ch['ikl']) + (0.099 * $ch['ikal']);
              $cnIklhFalse = (0.340 * $nilai_indeks);
              $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            }
        // =(0,34*J3)+(0,428*I3)+(0,133*K3)+(0,099*L3)
        } elseif ($jenis_indeks == 2) {
            // IKLH = (0.340 x IKA Nasional)+(0.428 x IKU Nasional)+ (0.133 x IKL Nasional)+(0.099 x IKAL Nasional)
            $cnIklhTrue = (0.340 * $nilai_indeks) + (0.428 * $ch['iku']) + (0.133 * $ch['ikl']) + (0.099 * $ch['ikal']);
            $cnIklhFalse = ($nilai_indeks);
            $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
        } else {
            $cnIklhTrue = (0.376 * $nilai_indeks) + (0.405 * $ch['iku']) + (0.219 * $ch['ikl']);
            $cnIklhFalse = (0.376 * $nilai_indeks);
            $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            // =(0,376*J4)+(0,405*I4)+(0,219*K4)
        }
        $updateHistory['submit'] = true;
        $this -> tables -> set("indeks_history", "uid_indeks_history");
        if ($this -> tables -> post($updateHistory)) {
            return 1;
        } else {
            return 0;
        }
    }

    private function getDataIndeks($tahunShow)
    {
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
                $w .= " AND a.tahun =" . $post['form']['tahun'];
            }
            if ($post['form']['short']) {
                $o = "nilai_indeks ".$post['form']['short'];
            }

            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this -> view -> assign("search", $post['form']);
        } else {
            $w .= " AND a.tahun ='" . ACTIVE_YEAR . "'";
            $post['form']['tahun'] = ACTIVE_YEAR;
            $this -> view -> assign("search", $post['form']);
        }
        $this->yearActive = $post['form']['tahun'];
        //PAGING
        $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
        $limit = LIMIT_INDEKS;
        // $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_ika a
				// 			LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
				// 			LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota
				// 			WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota , d.ika AS target
              FROM indeks_ika a
							LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
							LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota
              LEFT JOIN rf_target_iklh d ON d.uid_provinsi = a.uid_provinsi AND d.uid_kabkota = a.uid_kabkota AND d.tahun = a.tahun AND d.deleted = 0
              WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $data = $this -> tables -> query($sql);
        $All = $this -> db -> query('SELECT count(a.uid_indeks_ika) as x FROM indeks_ika a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);
        $this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);

        // foreach ($data['data'] as $key => $value) {
        //   $data['data'][$key]['json'] = json_decode($value['json_data']);
        // }
        // if($_SERVER['REMOTE_ADDR']=='103.144.175.143'){
        //   $this->debug->show($data);
        // }

        //get Nasional
        $getDataNasional = $this -> tables -> query("SELECT * FROM indeks_ika WHERE jenis_indeks = 2 AND tahun=" . $post['form']['tahun']);
        // NILI INTERVENSI AOH
        if($post['form']['tahun']==2022){
            $getDataNasional['data'][0]['nilai_indeks'] = 53.88;
            // $this->debug->show($getDataNasional);
        }
        $this -> view -> assign("indeksNasional", $getDataNasional['data'][0]);
        //end
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

        $this -> view -> assign("viewp_idx", base64_encode(implode(",",array_column($provinsi["data"],"uid_indeks_ika"))));
        $this -> view -> assign("viewk_idx", base64_encode(implode(",",array_column($kabkota["data"],"uid_indeks_ika"))));
    }

    private function getDataIndeksMutu($tahunShow)
    {
      // $this->debug->show($this -> viewName);
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

            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w .= " AND uid_provinsi IN ({$provinsiLainnya})";
            }
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
            $data['data'][$key]['parameter_kritis'] = explode(",",$data['data'][$key]['parameter_kritis']);
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
        // $this->debug->show($data);

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
    public function indeksSetPrioritas(){
      $uid_indeks_ika_sungai = $this->params("x");
      $prioritas = $this->params("p");
      if(is_numeric($uid_indeks_ika_sungai)){
        $update["form"]["uid_indeks_ika_sungai"] = $uid_indeks_ika_sungai;
        $update["form"]["prioritas"] = $prioritas;
        $update["submit"] = TRUE;
        $this->tables->set("indeks_ika_sungai","uid_indeks_ika_sungai");
        $check = $this->tables->fetch("uid_indeks_ika_sungai={$uid_indeks_ika_sungai}");
        if($check["data"][0]["prioritas"] == $prioritas){
          $update["form"]["prioritas"] = NULL;
        }
        $statusUpdate = $this->tables->post($update);
        if($statusUpdate){
          echo json_encode(array("statusCode"=>200,"message"=>"Berhasil","prioritas"=>$update["form"]["prioritas"]));
        }else {
          echo json_encode(array("statusCode"=>400,"message"=>"Gagal update"));
        }
      }else{
        echo json_encode(array("statusCode"=>400,"message"=>"Gagal update, Id tidak dikenal"));
      }
    }
    public function indeksSetParameter(){
      $uid_indeks_ika_sungai = $this->params("x");
      $parameter = base64_decode($this->params("p"));
      if(is_numeric($uid_indeks_ika_sungai)){
        $update["form"]["uid_indeks_ika_sungai"] = $uid_indeks_ika_sungai;
        $update["form"]["parameter_kritis"] = ($parameter ? $parameter : NULL);
        $update["submit"] = TRUE;
        $this->tables->set("indeks_ika_sungai","uid_indeks_ika_sungai");
        $check = $this->tables->fetch("uid_indeks_ika_sungai={$uid_indeks_ika_sungai}");
        $statusUpdate = $this->tables->post($update);
        if($statusUpdate){
          echo json_encode(array("statusCode"=>200,"message"=>"Berhasil"));
        }else {
          echo json_encode(array("statusCode"=>400,"message"=>"Gagal update"));
        }
      }else{
        echo json_encode(array("statusCode"=>400,"message"=>"Gagal update, Id tidak dikenal"));
      }
    }

    public function titikLokasi()
    {
        if ($this->params(uid)) {
            $wTitik = 'uid_indeks_ika='.$this->params(uid);
            $this -> tables -> set("indeks_ika", "uid_indeks_ika");
            $rf = $this -> tables -> fetch($wTitik);
            $rf['data'][0]['json'] = json_decode($rf['data'][0]['json_data'], true);
            $html = '';
            $i = 1;
            foreach ($rf['data'][0]['json'] as $key=>$val) {
                $html .= '<tr>';
                $html .= '<td class="text-center">'.$i.'</td>';
                $html .= '<td class="text-uppercase"><b>'.$val['kode_lokasi'].'</b></td>';
                $html .= '<td class="text-uppercase text-center"><b>'.number_format($val['nilai_pij'],2,".","").'</b></td>';
                $html .= '<td class="text-uppercase text-center"><b>'.$val['status_mutu'].'</b></td>';
                $html .= '</tr>';
                $i++;
            }
            echo $html;
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

            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $wProvinsi .= " AND kd_propinsi IN ({$provinsiLainnya})";
              $wLokasi .= " AND uid_provinsi IN({$provinsiLainnya})";
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
          $this -> view -> assign("regSelect", $regSelect['data']);
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w = " AND kd_propinsi IN ({$provinsiLainnya})";
            }
            $rf = $this -> tables -> fetch('kd_regional='.$this -> me['uid_regional'].$w);
            $this -> view -> assign("propSelect", $rf['data']);
            // $this->debug->show($rf);
        }
        if ($this->me['role_user'] < 2) {
          $this -> view -> assign("regSelect", $regSelect['data']);
            $this -> view -> assign("propSelect", $propSelect['data']);
        }

        $this -> tables -> set("rf_lab", "uid");
        $rf = $this -> tables -> fetch('deleted = 0');
        $this -> view -> assign("lab", $rf['data']);
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
        if ($this -> me['role_user'] == 3) {
            $wStatusMutu .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
        }
        if ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $wStatusMutu = ' AND kd_regional='.$this -> me['uid_regional'];

            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $w .= " AND uid_provinsi IN ({$provinsiLainnya})";
            }
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
        $this -> cekLockSystem(2, 2.2, $this -> me['uid_users']);
        $this->view->assign("rf_catatan",$this->ref->getRekomendasiCatatan('ika','verifikasi')['data']);
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
            $dataUpdate['form']['uid_pelaporan_ika'] = $dataRequest['uid'];
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
            $post['form']['v_reject_status'] = 0;
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
