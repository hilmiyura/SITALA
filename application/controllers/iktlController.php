<?php
/**
 * created at 	: 01/10/2020
 * created by 	: dasendria team
 * desc		  	: controller INDEKS KUALITAS LAHAN IKLHK
 *
 */
class iktlController extends Front
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
        $this -> loadModel("iktl");

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

        // $this->debug->show($this->me);

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

    //INDEX FUNCTION IS A DEFAULT ACTION
    public function index()
    {
        $post = $this -> post();
        $forDataDuplicate = null;
        if (isset($post['submit'])) {
            //check reject update
            if(isset($post['form']['uid_pelaporan_iktl'])){
              $checkData = $this -> tables -> query("SELECT v_pusat, v_regional, v_provinsi FROM pelaporan_iktl WHERE uid_pelaporan_iktl =".$post['form']['uid_pelaporan_iktl'])['data'][0];
              $post['form']['v_reject_status'] = ($checkData['v_pusat'] == 2 ? 1 : ($checkData['v_regional'] == 2 ? 1 : ($checkData['v_provinsi'] == 2 ? 1 : 0)));
              if($post['form']['v_reject_status'] == 1){
                $post['form']['v_pusat'] = 0;
                $post['form']['v_regional'] = 0;
                $post['form']['v_provinsi'] = 0;
              }
            }
            //end

            if($this->dev){
              if($post['form']['data_rth_json_file']){
                $dataFile = file_get_contents(UPLOADFOLDER."rth_detail_json/".$post['form']['data_rth_json_file']);
                if($dataFile){
                  $post['form']['data_rth'] = $dataFile;
                }
              }
              if($post['form']['data_tutupan_json_file']){
                $dataFile = file_get_contents(UPLOADFOLDER."tutupan_detail_json/".$post['form']['data_tutupan_json_file']);
                if($dataFile){
                  $post['form']['data_tutupan'] = $dataFile;
                }
              }
            }
            if ($this -> me['role_user'] == 3) {
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
                $post['form']['uid_kabkota'] = $this -> me['uid_kabkota'];
            }
            if ($this -> me['role_user'] == 2) {
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
            }

            $cekDataLaporan['total'] = 0;
            if (!$post['form']['uid_pelaporan_iktl']) {
                $tahunLapor = date("Y", strtotime($post['form']['tanggal']));
                $w = "deleted = 0 AND uid_kabkota =".$post['form']['uid_kabkota']." AND uid_provinsi=".$post['form']['uid_provinsi']." AND YEAR(tanggal)=".$tahunLapor;
                $cekDataLaporan = $this->tables->query("SELECT * FROM v_pelaporan_iktl WHERE ".$w);
            }
            // $this->debug->show($cekDataLaporan);
            if ($cekDataLaporan['total']) {
                $message = "Hanya dapat melakukan 1 kali pelaporan dalam 1 tahun, ".$cekDataLaporan['data'][0]['nama_kabkota']." Provinsi ".$cekDataLaporan['data'][0]['nama_provinsi']." tahun ".$tahunLapor. " sudah pernah melakukan pelaporan.";
                $message .=" jika ada perubahan data silahkan update data tersebut!";
                $forDataDuplicate['keyword'] = $cekDataLaporan['data'][0]['nama_kabkota'];
                $forDataDuplicate['tahun'] = $tahunLapor;
                $forDataDuplicate['src_prop'] = $post['form']['uid_provinsi'];
            } else {
                $file = $_FILES['lampiran'];
                if ($file['name']) {
                    $fileUpload = $this -> functions -> uploadFile($_FILES['lampiran'], "monitoring");
                    $post['form']['lampiran'] = $fileUpload;
                }
                $file = $_FILES['lampiran_rth'];
                if ($file['name']) {
                    $fileUpload = $this -> functions -> uploadFile($_FILES['lampiran_rth'], "rth");
                    $post['form']['lampiran_rth'] = $fileUpload;
                }
                $file = $_FILES['lampiran_tutupan'];
                if ($file['name']) {
                    $fileUpload = $this -> functions -> uploadFile($_FILES['lampiran_tutupan'], "tutupan");
                    $post['form']['lampiran_tutupan'] = $fileUpload;
                }
                $file = $_FILES['lampiran_kml'];
                if ($file['name']) {
                    $fileUpload = $this -> functions -> uploadFile($_FILES['lampiran_kml'], "kml");
                    $post['form']['lampiran_kml'] = $fileUpload;
                }
                $file = $_FILES['lampiran_kml_rhl'];
                if ($file['name']) {
                    $fileUpload = $this -> functions -> uploadFile($_FILES['lampiran_kml_rhl'], "kml");
                    $post['form']['lampiran_kml_rhl'] = $fileUpload;
                }
                $file = $_FILES['lampiran_dtp'];
                if ($file['name']) {
                    $fileUpload = $this -> functions -> uploadFile($_FILES['lampiran_dtp'], "dtp");
                    $post['form']['lampiran_dtp'] = $fileUpload;
                }
                $post['form']['cruser'] = $this -> me['uid_users'];
                if ($post['form'][$this->primaryKey]) {
                    unset($post['form']['cruser']);
                    $post['form']['chuser'] = $this->me['uid_users'];
                }
                $post['form']['luas_wilayah'] = str_replace(",", ".", $post['form']['luas_wilayah']);
                $post['form']['luas_hutan'] = str_replace(",", ".", $post['form']['luas_hutan']);
                $post['form']['luas_belukar_dalam_kawasan'] = str_replace(",", ".", $post['form']['luas_belukar_dalam_kawasan']);
                $post['form']['luas_belukar_pada_fungsi_lindung'] = str_replace(",", ".", $post['form']['luas_belukar_pada_fungsi_lindung']);
                $post['form']['kebun_raya_data_lipi'] = str_replace(",", ".", $post['form']['kebun_raya_data_lipi']);
                $post['form']['rth'] = str_replace(",", ".", $post['form']['rth']);
                $post['form']['taman_kehati'] = str_replace(",", ".", $post['form']['taman_kehati']);
                $post['form']['tutupan_vegetasi'] = str_replace(",", ".", $post['form']['tutupan_vegetasi']);
                $post['form']['rhl'] = str_replace(",", ".", $post['form']['rhl']);
                $post['form']['dkk'] = str_replace(",", ".", $post['form']['dkk']);

                $post['form']['hutan_lahan_kering_primer'] = str_replace(",", ".", $post['form']['hutan_lahan_kering_primer']);
                $post['form']['hutan_lahan_kering_sekunder'] = str_replace(",", ".", $post['form']['hutan_lahan_kering_sekunder']);
                $post['form']['hutan_mangrove_primer'] = str_replace(",", ".", $post['form']['hutan_mangrove_primer']);
                $post['form']['hutan_rawa_primer'] = str_replace(",", ".", $post['form']['hutan_rawa_primer']);
                $post['form']['hutan_tanaman'] = str_replace(",", ".", $post['form']['hutan_tanaman']);
                $post['form']['belukar'] = str_replace(",", ".", $post['form']['belukar']);
                $post['form']['perkebunan'] = str_replace(",", ".", $post['form']['perkebunan']);
                $post['form']['permukiman'] = str_replace(",", ".", $post['form']['permukiman']);
                $post['form']['lahan_terbuka'] = str_replace(",", ".", $post['form']['lahan_terbuka']);
                $post['form']['savanna'] = str_replace(",", ".", $post['form']['savanna']);
                $post['form']['tubuh_air'] = str_replace(",", ".", $post['form']['tubuh_air']);
                $post['form']['hutan_mangrove_sekunder'] = str_replace(",", ".", $post['form']['hutan_mangrove_sekunder']);
                $post['form']['hutan_rawa_sekunder'] = str_replace(",", ".", $post['form']['hutan_rawa_sekunder']);
                $post['form']['belukar_rawa'] = str_replace(",", ".", $post['form']['belukar_rawa']);
                $post['form']['pertanian_lahan_kering'] = str_replace(",", ".", $post['form']['pertanian_lahan_kering']);
                $post['form']['pertanian_lahan_kering_campur_semak'] = str_replace(",", ".", $post['form']['pertanian_lahan_kering_campur_semak']);
                $post['form']['sawah'] = str_replace(",", ".", $post['form']['sawah']);
                $post['form']['tambak'] = str_replace(",", ".", $post['form']['tambak']);
                $post['form']['bandara'] = str_replace(",", ".", $post['form']['bandara']);
                $post['form']['transmigrasi'] = str_replace(",", ".", $post['form']['transmigrasi']);
                $post['form']['pertambangan'] = str_replace(",", ".", $post['form']['pertambangan']);
                $post['form']['rawa'] = str_replace(",", ".", $post['form']['rawa']);

                $post['form']['data_rth_verifikasi'] = $post['form']['data_rth'];
                $post['form']['data_tutupan_verifikasi'] = $post['form']['data_tutupan'];


                $tahun = date("Y", strtotime($post['form']['tanggal']));
                if($tahun >= 2025){
                  $dataPelaporan["data"][0] = $post["form"];
                  $dataKoefisien = $this->db->fetch("SELECT * FROM rf_koefisien_ikl WHERE deleted = 0 AND tahun = {$tahun}");
                  $xKoefisien = ["hutan_lahan_kering_primer","hutan_lahan_kering_sekunder","hutan_mangrove_primer","hutan_rawa_primer","hutan_tanaman","belukar","perkebunan","permukiman","lahan_terbuka","savanna","tubuh_air","hutan_mangrove_sekunder","hutan_rawa_sekunder","belukar_rawa","pertanian_lahan_kering","pertanian_lahan_kering_campur_semak","sawah","tambak","bandara","transmigrasi","pertambangan","rawa"];

                  $dataPelaporan["data"][0]["total_pelaporan"] = 0;
                  $dataPelaporan["data"][0]["total_koefisien_x_pelaporan"] = 0;
                  $dataPelaporan["data"][0]["itl"] = 0;
                  $dataPelaporan["data"][0]["iktl"] = 0;

                  foreach ($xKoefisien as $key => $value) {
                    $dataPelaporan["data"][0]["koefisien_x_{$value}"] = $dataPelaporan["data"][0][$value] * $dataKoefisien["data"][0][$value];
                    $dataPelaporan["data"][0]["total_koefisien_x_pelaporan"] += $dataPelaporan["data"][0]["koefisien_x_{$value}"];
                    $dataPelaporan["data"][0]["total_pelaporan"] += $dataPelaporan["data"][0][$value];
                  }
                  $dataPelaporan["data"][0]["itl"] = $dataPelaporan["data"][0]["total_koefisien_x_pelaporan"]/$dataPelaporan["data"][0]["total_pelaporan"];
                  $dataPelaporan["data"][0]["iktl"] = 100-((84.3-($dataPelaporan["data"][0]["itl"]*100))*50/54.3);
                  $dataPelaporan["data"][0]["tl"] = $dataPelaporan["data"][0]["itl"];
                  $dataPelaporan["data"][0]["tl_dkk"] = $dataPelaporan["data"][0]["tl"] - $json_data['dkk'];

                  $post['form']['tl'] = ($dataPelaporan["data"][0]["tl"] ? $dataPelaporan["data"][0]["tl"] : 0);
                  $post['form']['iktl'] = ($dataPelaporan["data"][0]["iktl"] ? $dataPelaporan["data"][0]["iktl"] : 0);
                  $post['form']['ikl'] = ($post['form']['iktl'] > 100 ? 100 : $post['form']['iktl']);
                  $post['form']['tl_dkk'] = ($dataPelaporan['data'][0]['tl_dkk'] ? $dataPelaporan['data'][0]['tl_dkk'] : 0);

                }else{
                  $post['form']['tl'] = ($post['form']['luas_hutan'] + (($post['form']['luas_belukar_dalam_kawasan'] + $post['form']['luas_belukar_pada_fungsi_lindung'] + $post['form']['kebun_raya_data_lipi'] + $post['form']['rth'] + $post['form']['taman_kehati'] + $post['form']['rhl'] + $post['form']['tutupan_vegetasi']) * 0.6)) / $post['form']['luas_wilayah'];
                  $post['form']['iktl'] = 100 - ((84.3 - ($post['form']['tl'] * 100)) * 50 / 54.3);
                  $post['form']['tl_dkk'] = $post['form']['tl'] - $post['form']['dkk'];
                  $post['form']['ikl'] = 100 - ((84.3 - ($post['form']['tl_dkk'] * 100)) * 50 / 54.3);
                }


                $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
                // $this->debug->show($post);
                if ($this -> tables -> post($post)) {
                    // $this->_count();
                    if($this->dev){
                      if($post['form']['data_rth_json_file']){
                        unlink(UPLOADFOLDER."rth_detail_json/".$post['form']['data_rth_json_file']);
                      }
                      if($post['form']['data_tutupan_json_file']){
                        unlink(UPLOADFOLDER."tutupan_detail_json/".$post['form']['data_tutupan_json_file']);
                      }
                    }
                    $message = "Berhasil menyimpan data !";
                } else {
                    $message = "Gagal menimpan data !";
                }
            }
        }

        $messageExcel = $this->submitExcel($post);
        if($messageExcel){
          $message = $messageExcel;
        }

        $this -> getData($forDataDuplicate);
        $this -> rfData();
        $this -> cekLockSystem(1, 1.3, $this -> me['uid_users']);
        $this -> view -> assign("pelaporanActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("icons", '<i class="la la-map"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS LAHAN');
        $this -> view -> display("index.html");
    }

    private function submitExcel($post){
      if (isset($post['submit-excel'])) {
				$post['form']['cruser'] = $this -> me['uid_users'];
				$val = $_FILES['file_excel'];
				$ext = strtolower(strrchr($val['name'], "."));
				if ($ext == ".xls") {
						$files = $this -> functions -> uploadFile($_FILES['file_excel']);
				}
				if ($files) {
						$excelReader = new Spreadsheet_Excel_Reader(UPLOADFOLDER . "docs/" . $files, true);
						$rows = $excelReader -> rowcount(0);
						for ($c = 1; $c <= 25; $c++) {
								for ($d = 3; $d <= $rows; $d++) {
										if ($excelReader -> val($d, $c)) {
												$data[$d][$c] = trim($excelReader -> val($d, $c));
										} else {
												$data[$d][$c] = "-";
										};
								}
						}
						unlink(UPLOADFOLDER . "docs/" . $files);

						$tmpCn = 1;
						$tmpDuplikasi = NULL;
            $tmpProv = NULL;
						$tmpTanggal = NULL;
						foreach ($data as $key => $vals) {
              if($vals[1] != "-") {
								$postIkl['form']['uid_target_iklh'] = "";
								$postIkl['form']['cruser'] = $post['form']['cruser'];

								$cek_provinsi = $this -> tables -> query("SELECT * FROM rf_provinsi WHERE nama_propinsi LIKE '".$vals[1]."'");
                $cek_kabkota = $this -> tables -> query("SELECT * FROM rf_kabkota WHERE deleted=0 AND nama_kabkot LIKE '".$vals[2]."' AND kd_provinsi=".$cek_provinsi['data'][0]['kd_propinsi']);

                $postIkl['form']['uid_provinsi'] = $cek_provinsi['data'][0]['kd_propinsi'];
                $postIkl['form']['uid_kabkota'] = $cek_kabkota['data'][0]['kd_kota'];
								$postIkl['form']['tanggal'] = ($vals[3] != "-" ? date("Y-m-d", strtotime($vals[3])) : "");
                // $postIkl['form']['luas_wilayah'] = ($vals[4] != "-" ? str_replace(",", ".", $vals[4]) : "");
                // $postIkl['form']['luas_hutan'] = ($vals[5] != "-" ? str_replace(",", ".", $vals[5]) : "");
                // $postIkl['form']['luas_belukar_dalam_kawasan'] = ($vals[6] != "-" ? str_replace(",", ".", $vals[6]) : "");
                // $postIkl['form']['luas_belukar_pada_fungsi_lindung'] = ($vals[7] != "-" ? str_replace(",", ".", $vals[7]) : "");
                // $postIkl['form']['dkk'] = ($vals[8] != "-" ? str_replace(",", ".", $vals[8]) : "");
                // $postIkl['form']['rhl'] = ($vals[9] != "-" ? str_replace(",", ".", $vals[9]) : "");
                $postIkl['form']['hutan_lahan_kering_primer'] = ($vals[4] != "-" ? str_replace(",", ".", $vals[4]) : "");
                $postIkl['form']['hutan_lahan_kering_sekunder'] = ($vals[5] != "-" ? str_replace(",", ".", $vals[5]) : "");
                $postIkl['form']['hutan_mangrove_primer'] = ($vals[6] != "-" ? str_replace(",", ".", $vals[6]) : "");
                $postIkl['form']['hutan_rawa_primer'] = ($vals[7] != "-" ? str_replace(",", ".", $vals[7]) : "");
                $postIkl['form']['hutan_tanaman'] = ($vals[8] != "-" ? str_replace(",", ".", $vals[8]) : "");
                $postIkl['form']['belukar'] = ($vals[9] != "-" ? str_replace(",", ".", $vals[9]) : "");
                $postIkl['form']['perkebunan'] = ($vals[10] != "-" ? str_replace(",", ".", $vals[10]) : "");
                $postIkl['form']['permukiman'] = ($vals[11] != "-" ? str_replace(",", ".", $vals[11]) : "");
                $postIkl['form']['lahan_terbuka'] = ($vals[12] != "-" ? str_replace(",", ".", $vals[12]) : "");
                $postIkl['form']['savanna'] = ($vals[13] != "-" ? str_replace(",", ".", $vals[13]) : "");
                $postIkl['form']['tubuh_air'] = ($vals[14] != "-" ? str_replace(",", ".", $vals[14]) : "");
                $postIkl['form']['hutan_mangrove_sekunder'] = ($vals[15] != "-" ? str_replace(",", ".", $vals[15]) : "");
                $postIkl['form']['hutan_rawa_sekunder'] = ($vals[16] != "-" ? str_replace(",", ".", $vals[16]) : "");
                $postIkl['form']['belukar_rawa'] = ($vals[17] != "-" ? str_replace(",", ".", $vals[17]) : "");
                $postIkl['form']['pertanian_lahan_kering'] = ($vals[18] != "-" ? str_replace(",", ".", $vals[18]) : "");
                $postIkl['form']['pertanian_lahan_kering_campur_semak'] = ($vals[19] != "-" ? str_replace(",", ".", $vals[19]) : "");
                $postIkl['form']['sawah'] = ($vals[20] != "-" ? str_replace(",", ".", $vals[20]) : "");
                $postIkl['form']['tambak'] = ($vals[21] != "-" ? str_replace(",", ".", $vals[21]) : "");
                $postIkl['form']['bandara'] = ($vals[22] != "-" ? str_replace(",", ".", $vals[22]) : "");
                $postIkl['form']['transmigrasi'] = ($vals[23] != "-" ? str_replace(",", ".", $vals[23]) : "");
                $postIkl['form']['pertambangan'] = ($vals[24] != "-" ? str_replace(",", ".", $vals[24]) : "");
                $postIkl['form']['rawa'] = ($vals[25] != "-" ? str_replace(",", ".", $vals[25]) : "");
                // $postIkl['form']['kebun_raya_data_lipi'] = ($vals[25] != "-" ? str_replace(",", ".", $vals[25]) : "");
                // $postIkl['form']['rth'] = ($vals[26] != "-" ? str_replace(",", ".", $vals[26]) : "");
                // $postIkl['form']['taman_kehati'] = ($vals[27] != "-" ? str_replace(",", ".", $vals[27]) : "");
                // $postIkl['form']['tutupan_vegetasi'] = ($vals[28] != "-" ? str_replace(",", ".", $vals[28]) : "");

                if($postIkl['form']['uid_provinsi'] > 0 && $postIkl['form']['uid_kabkota'] > 0 && $postIkl['form']['tanggal'] >= 2015){
                  // $this->debug->show("mmasuk");
                  $cekPelaporan = $this -> db -> fetch("SELECT * FROM pelaporan_iktl WHERE deleted = 0 AND uid_provinsi=".$postIkl['form']['uid_provinsi']." AND uid_kabkota=".$postIkl['form']['uid_kabkota']." AND YEAR(tanggal)=".date('Y', strtotime($postIkl['form']['tanggal'])));
                  if($cekPelaporan['total'] > 0){
                    $tmpDuplikasi[] = $vals[1]."-".$vals[2].' tahun '.date("Y", strtotime($postIkl['form']['tanggal']));
                  }else{
                    $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
                    $postIkl['submit'] = true;
                    $this -> tables -> post($postIkl);
                    $tmpPost[] = $postIkl;
                  }
                }else{
                  if($postIkl['form']['tanggal'] == ""){
                    $tmpTanggal[] = $vals[1]."-".$vals[2];
                  }else{
                    $tmpProv[] = $vals[1]."-".$vals[2];
                  }
                }
							}
							if ($tmpDuplikasi) {
								$message = "Pelaporan IKL ".implode(', ', $tmpDuplikasi)." tidak tersimpan oleh sistem dikarenakan sudah ada data";
                $message .= ($tmpProv ? "<br><br>Pelaporan IKL ".implode(',',array_unique($tmpProv)). " tidak tersimpan karena provinsi/kabkota tidak terdaftar disistem atau nama tidak sesuai dengan disistem" : "");
								$message .= ($tmpTanggal ? "<br><br>Pelaporan IKL ".implode(',',array_unique($tmpTanggal)). " tidak tersimpan karena tanggal kosong" : "");
							}else {
								if (count($data) == $tmpCn) {
										$message = "Berhasil menyimpan data !". ($tmpProv ? ", ".implode(',',$tmpProv). " Tidak terdaftar disistem atau nama tidak sesuai dengan disistem" : "");
                    $message .= ($tmpTanggal ? "<br><br>Pelaporan IKL ".implode(',',array_unique($tmpTanggal)). " tidak tersimpan karena tanggal pemantauan kosong" : "");
								} else {
										$message = "Gagal menyimpan data !";
								}
							}
							$tmpCn++;
						}
            // $this->debug->show($tmpPost);
            return $message;
				}else{
          return "Gagal menyimpan data !, file tidak terbaca oleh sistem";
        }
  		}
    }

    private function getData($cekDataDuplicate = null)
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
        $o = $this -> primaryKey . " ASC";
        $post = $this -> post();
        if ($this -> params('search')) {
            $post['search'] = true;
            $post['form'] = json_decode(urldecode($this -> params('search')), 1);
        }
        if (is_array($cekDataDuplicate)) {
            $post['search'] = true;
            $post['form'] = $cekDataDuplicate;
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
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_rth']) {
              if ($post['form']['src_rth'] == 'NOT NULL') {
                $w .= " AND data_rth <> ''";
              }else {
                $w .= " AND data_rth IS NULL OR data_rth = ''";
              }
            }
            if ($post['form']['src_tutupan']) {
              if ($post['form']['src_tutupan'] == 'NOT NULL') {
                $w .= " AND data_tutupan <> ''";
              }else {
                $w .= " AND data_tutupan IS NULL OR data_tutupan = ''";
              }
            }
            if ($post['form']['src_lampiran']) {
                $w .= " AND lampiran IS " . $post['form']['src_lampiran'];
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
        if ($this -> url == "iktl/verifikasi") {
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

        $idxIdLapor = implode(",",array_keys(array_column($data["data"], null, "uid_pelaporan_iktl")));
        $getDataPelaporanRevisi = $this->tables->query("SELECT a.*, CONCAT(uid_pelaporan_iktl,'_',jenis_data) AS _index FROM pelaporan_iktl_revisi_pusat a WHERE a.deleted = 0 AND a.uid_pelaporan_iktl IN ({$idxIdLapor})")["data"];
        $getDataPelaporanRevisi = array_column($getDataPelaporanRevisi, null, "_index");

        foreach ($data['data'] as $key => $value) {

          if($getDataPelaporanRevisi["{$value['uid_pelaporan_iktl']}_RTH"]){
            $data["data"][$key]["revisi_rth"] = $getDataPelaporanRevisi["{$value['uid_pelaporan_iktl']}_RTH"];
          }
          if($getDataPelaporanRevisi["{$value['uid_pelaporan_iktl']}_RHL"]){
            $data["data"][$key]["revisi_rhl"] = $getDataPelaporanRevisi["{$value['uid_pelaporan_iktl']}_RHL"];
          }

          $dataCountRTHBaru     = $this->tables->query('SELECT * FROM pelaporan_iktl_rth WHERE deleted=0 AND pelaporan_iktl_uid='.$value['uid_pelaporan_iktl']);
          $dataCountTutupanBaru = $this->tables->query('SELECT * FROM pelaporan_iktl_tutupan WHERE deleted=0 AND pelaporan_iktl_uid='.$value['uid_pelaporan_iktl']);

          $data['data'][$key]['catatan_verifikator'] = $value['catatan_verifikator'] .' '.($value['catatan_verifikator_select']?str_replace("|",",",$value['catatan_verifikator_select']) : '');
          $data['data'][$key]['catatan_provinsi'] = $value['catatan_provinsi'] .' '.($value['catatan_provinsi_select']?str_replace("|",",",$value['catatan_provinsi_select']) : '');
          $data['data'][$key]['catatan_regional'] = $value['catatan_regional'] .' '.($value['catatan_regional_select']?str_replace("|",",",$value['catatan_regional_select']) : '');

          if ($value['data_rth'] != '') {
            $dataRTH = json_decode($value['data_rth'], true);
            $jumlahTitikRTH = count($dataRTH['data_rth']);
          } else {
            $jumlahTitikRTH = 0;
          }

          if ($value['data_rth_verifikasi'] != '') {
            $dataRTHVerifikasi = json_decode($value['data_rth_verifikasi'], true);
            $jumlahTitikRTHVerifikasi = count($dataRTHVerifikasi['data_rth']);
          } else {
            $jumlahTitikRTHVerifikasi = 0;
          }

          if ($value['data_tutupan'] != '') {
            $dataTutupan = json_decode($value['data_tutupan'], true);
            $jumlahTitikTutupan = count($dataTutupan['data_tutupan']);
          } else {
            $jumlahTitikTutupan = 0;
          }

          if ($value['data_tutupan_verifikasi'] != '') {
            $dataTutupanVerifikasi = json_decode($value['data_tutupan_verifikasi'], true);
            $jumlahTitikTutupanVerifikasi = count($dataTutupanVerifikasi['data_tutupan']);
          } else {
            $jumlahTitikTutupanVerifikasi = 0;
          }

          $data['data'][$key]['jumlahTitikRTH'] = $jumlahTitikRTH;
          $data['data'][$key]['jumlahTitikRTHVerifikasi'] = $dataCountRTHBaru['total'];
          $data['data'][$key]['jumlahTitikTutupan'] = $jumlahTitikTutupan;
          $data['data'][$key]['jumlahTitikTutupanVerifikasi'] = $dataCountTutupanBaru['total'];


          $data['data'][$key]['verify_status_reject'] = ($value['v_pusat'] == 2 ? 1 : ($value['v_regional'] == 2 ? 1 : ($value['v_provinsi'] == 2 ? 1 : 0)));
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
            $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
            $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_iktl=" . $this -> params("x"));
            // if ($dataEdit['data'][0]['data_rth_verifikasi']) {
            //   $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth_verifikasi'], TRUE);
            //   $dataEdit['data'][0]['data_rth'] = json_encode($dataEdit['data'][0]['data_rth_verifikasi'], TRUE);
            // }else {
            //   $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth'], TRUE);
            //   $dataEdit['data'][0]['data_rth'] = json_encode($dataEdit['data'][0]['data_rth'], TRUE);
            // }
            //
            // if ($dataEdit['data'][0]['data_tutupan_verifikasi']) {
            //   $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan_verifikasi'], TRUE);
            //   $dataEdit['data'][0]['data_tutupan'] = json_encode($dataEdit['data'][0]['data_tutupan_verifikasi'], TRUE);
            // }else {
            //   $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan'], TRUE);
            //   $dataEdit['data'][0]['data_tutupan'] = json_encode($dataEdit['data'][0]['data_tutupan'], TRUE);
            // }
            $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth'], TRUE);
            $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan'], TRUE);
            $dataEdit['data'][0]['data_rth'] = json_encode($dataEdit['data'][0]['data_rth'], TRUE);
            $dataEdit['data'][0]['data_tutupan'] = json_encode($dataEdit['data'][0]['data_tutupan'], TRUE);

            if($this->dev){

              // create json rth
              $dataRthJson = ($dataEdit['data'][0]['data_rth_verifikasi']? $dataEdit['data'][0]['data_rth_verifikasi'] : $dataEdit['data'][0]['data_rth'] );
              $fileName = $this->me['uid_users']."-".time().".json";
              $dirFile = UPLOADFOLDER . "rth_detail_json/".$fileName;
              $createFileJson = file_put_contents($dirFile, $dataRthJson);
              $dataEdit['data'][0]['data_rth_json_file'] = ($createFileJson?$fileName:'');
              unset($dataEdit['data'][0]['data_rth']);
              unset($dataEdit['data'][0]['data_rth_verifikasi']);

              //create tutupan json
              $dataTutupanJson = ($dataEdit['data'][0]['data_tutupan_verifikasi']? $dataEdit['data'][0]['data_tutupan_verifikasi'] : $dataEdit['data'][0]['data_tutupan'] );
              $fileName = $this->me['uid_users']."-".time().".json";
              $dirFile = UPLOADFOLDER . "tutupan_detail_json/".$fileName;
              $createFileJson = file_put_contents($dirFile, $dataTutupanJson);
              $dataEdit['data'][0]['data_tutupan_json_file'] = ($createFileJson?$fileName:'');
              unset($dataEdit['data'][0]['data_tutupan']);
              unset($dataEdit['data'][0]['data_tutupan_verifikasi']);
            }
            echo json_encode($dataEdit['data'][0]);
            // $this->debug->show($dataEdit['data'][0]);
        }
    }

    public function detailDataRth()
    {
        header("Content-Type: application/json; charset=UTF-8");
        if ($this -> params("x")) {
            $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
            $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_iktl=" . $this -> params("x"));
            if ($dataEdit['data'][0]['data_rth_verifikasi']) {
              $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth_verifikasi'], TRUE);
            }else {
              $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth'], TRUE);
            }
            // $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth'], TRUE);
            // $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan'], TRUE);
            echo json_encode($dataEdit['data'][0]['data_rth']);
        }
    }

    public function detailDataRth2()
    {
        header("Content-Type: application/json; charset=UTF-8");
        if ($this -> params("x")) {
            $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
            $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_iktl=" . $this -> params("x"));
            $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth'], TRUE);
            // $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan'], TRUE);
            // echo json_encode($dataEdit['data'][0]['data_rth']);
            foreach ($dataEdit['data'][0]['data_rth'] as $key => $value) {
              $tes[$key] = $value;
            }
            $this->debug->show($tes);
            $sample_data = $dataEdit['data'][0]['data_rth'];

            // just normal getting data
            $raw_data = $sample_data;
            $raw_data = $raw_data['data_rth'];

            // use get variable to paging number
            $page = !isset($_GET['page']) ? 1 : $_GET['page'];
            $limit = 5; // five rows per page
            $offset = ($page - 1) * $limit; // offset
            $total_items = count($raw_data); // total items
            $total_pages = ceil($total_items / $limit);
            $final = array_splice($raw_data, $offset, $limit); // splice them according to offset and limit
        }
    }

    public function rthVerifikasi()
    {
        $dataRequest = file_get_contents("php://input");
        $dataRequest = json_decode($dataRequest, true);

        if (is_numeric($dataRequest['uid'])) {
            $dataUpdate['form']['uid_pelaporan_iktl'] = $dataRequest['uid'];
            // $dataUpdate['form']['data_rth'] = $dataRequest['data_rth'];
            $dataUpdate['form']['data_rth_verifikasi'] = $dataRequest['data_rth'];
            $dataUpdate['form']['kebun_raya_data_lipi'] = str_replace(",", ".", $dataRequest['kebun_raya_data_lipi']);
            $dataUpdate['form']['taman_kehati'] = str_replace(",", ".", $dataRequest['taman_kehati']);
            $dataUpdate['form']['rth'] = str_replace(",", ".", $dataRequest['rth']);
            $dataUpdate['submit'] = true;
            $this->tables->set('pelaporan_iktl', 'uid_pelaporan_iktl');
            if ($this->tables->post($dataUpdate)) {
                echo 1;
            } else {
                echo 2;
            }
        } else {
            echo 2;
        }
    }

    public function tutupanVerifikasi()
    {
        $dataRequest = file_get_contents("php://input");
        $dataRequest = json_decode($dataRequest, true);

        if (is_numeric($dataRequest['uid'])) {
            $dataUpdate['form']['uid_pelaporan_iktl'] = $dataRequest['uid'];
            // $dataUpdate['form']['data_tutupan'] = $dataRequest['data_tutupan'];
            $dataUpdate['form']['data_tutupan_verifikasi'] = $dataRequest['data_tutupan'];
            $dataUpdate['form']['tutupan_vegetasi'] = str_replace(",", ".", $dataRequest['tutupan_vegetasi']);
            $dataUpdate['submit'] = true;
            $this->tables->set('pelaporan_iktl', 'uid_pelaporan_iktl');
            if ($this->tables->post($dataUpdate)) {
                echo 1;
            } else {
                echo 2;
            }
        } else {
            echo 2;
        }
    }

    public function detailDataTutupan()
    {
        header("Content-Type: application/json; charset=UTF-8");
        if ($this -> params("x")) {
            $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
            $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_iktl=" . $this -> params("x"));
            // $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth'], TRUE);
            if ($dataEdit['data'][0]['data_tutupan_verifikasi']) {
              $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan_verifikasi'], TRUE);
            }else {
              $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan'], TRUE);
            }
            // $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan'], TRUE);
            echo json_encode($dataEdit['data'][0]['data_tutupan']);
        }
    }

    public function deletedData()
    {
        $post = $this -> post();
        if (isset($post['x'])) {
            $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
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
        $properties = $this -> _getProperties('v_pelaporan_iktl');
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
        $o = $this -> primaryKey . " ASC";
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
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_lampiran']) {
                $w .= " AND lampiran IS " . $post['form']['src_lampiran'];
            }
            if ($post['form']['src_rth']) {
              if ($post['form']['src_rth'] == 'NOT NULL') {
                $w .= " AND data_rth <> ''";
              }else {
                $w .= " AND data_rth IS NULL OR data_rth = ''";
              }
            }
            if ($post['form']['src_tutupan']) {
              if ($post['form']['src_tutupan'] == 'NOT NULL') {
                $w .= " AND data_tutupan <> ''";
              }else {
                $w .= " AND data_tutupan IS NULL OR data_tutupan = ''";
              }
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
        $this->tables->set("v_pelaporan_iktl", "uid_pelaporan_iktl");
        $offset = ($offset > 0 ? $offset - 1 : 0);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        // $this->debug->show($data);
        $this->view->assign("offset", $offset+1);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="PELAPORAN_IKL_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/iktl/index/excel.html');
        echo $html;
    }

    public function dataExcel2($w=null, $offset=null)
    {
        $offset = $this->params('offset');
        $properties = $this -> _getProperties('v_pelaporan_iktl');
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
        $o = $this -> primaryKey . " ASC";
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
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_lampiran']) {
                $w .= " AND lampiran IS " . $post['form']['src_lampiran'];
            }
            if ($post['form']['src_rth']) {
              if ($post['form']['src_rth'] == 'NOT NULL') {
                $w .= " AND data_rth <> ''";
              }else {
                $w .= " AND data_rth IS NULL OR data_rth = ''";
              }
            }
            if ($post['form']['src_tutupan']) {
              if ($post['form']['src_tutupan'] == 'NOT NULL') {
                $w .= " AND data_tutupan <> ''";
              }else {
                $w .= " AND data_tutupan IS NULL OR data_tutupan = ''";
              }
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
        $o = "v_provinsi,v_regional,v_pusat ASC";
        $this->tables->set("v_pelaporan_iktl", "uid_pelaporan_iktl");
        $offset = ($offset > 0 ? $offset - 1 : 0);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        foreach ($data['data'] as $key => $value) {
          $dataCountRTHBaru     = $this->tables->query('SELECT * FROM pelaporan_iktl_rth WHERE deleted=0 AND pelaporan_iktl_uid='.$value['uid_pelaporan_iktl']);
          $dataCountTutupanBaru = $this->tables->query('SELECT * FROM pelaporan_iktl_tutupan WHERE deleted=0 AND pelaporan_iktl_uid='.$value['uid_pelaporan_iktl']);
          if ($value['data_rth'] != '') {
            $dataRTH = json_decode($value['data_rth'], true);
            $jumlahTitikRTH = count($dataRTH['data_rth']);
          } else {
            $jumlahTitikRTH = 0;
          }

          if ($value['data_rth_verifikasi'] != '') {
            $dataRTHVerifikasi = json_decode($value['data_rth_verifikasi'], true);
            $jumlahTitikRTHVerifikasi = count($dataRTHVerifikasi['data_rth']);
          } else {
            $jumlahTitikRTHVerifikasi = 0;
          }

          if ($value['data_tutupan'] != '') {
            $dataTutupan = json_decode($value['data_tutupan'], true);
            $jumlahTitikTutupan = count($dataTutupan['data_tutupan']);
          } else {
            $jumlahTitikTutupan = 0;
          }

          if ($value['data_tutupan_verifikasi'] != '') {
            $dataTutupanVerifikasi = json_decode($value['data_tutupan_verifikasi'], true);
            $jumlahTitikTutupanVerifikasi = count($dataTutupanVerifikasi['data_tutupan']);
          } else {
            $jumlahTitikTutupanVerifikasi = 0;
          }

          $data['data'][$key]['jumlahTitikRTH'] = $jumlahTitikRTH;
          $data['data'][$key]['jumlahTitikRTHVerifikasi'] = $dataCountRTHBaru['total'];
          $data['data'][$key]['jumlahTitikTutupan'] = $jumlahTitikTutupan;
          $data['data'][$key]['jumlahTitikTutupanVerifikasi'] = $dataCountTutupanBaru['total'];
        }

        $this->view->assign("offset", $offset+1);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="VERIFIKASI_IKL_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/iktl/verifikasi/excel.html');
        echo $html;
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
            header('Content-Disposition: attachment; filename="PERHITUNGAN_IKTL_KABKOTA_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/iktl/indeks/excel_kabkota.html');
            echo $html;
        } elseif ($provinsi) {
            $this -> view -> assign("viewp", $provinsi['data']);
            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="PERHITUNGAN_IKTL_PROVINSI_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/iktl/indeks/excel_provinsi.html');
            echo $html;
        }
    }

    public function indeks()
    {
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
            $counting = $this -> _countIndeks($post['form']['uid_indeks_iktl'], 1);
            $tahun = explode("tahun", $counting);
            if ($counting) {
                $message = "Data Indeks " . $counting . " telah diperbaharui";
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
        }
        if (isset($post['submitProvinsi'])) {
            $counting = $this -> _countIndeks($post['form']['uid_indeks_iktl'], 2);
            $tahun = explode("tahun", $counting);
            if ($counting) {
                $message = "Data Indeks " . $counting . " telah diperbaharui";
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
            $this -> view -> assign("showProv", 1);
        }
        if (isset($post['submitNasional'])) {
            $dataIndeks = $this -> tables -> query("SELECT a.* FROM indeks_iktl a WHERE a.uid_indeks_iktl=" . $post['form']['uid_indeks_iktl']);
            $tahun[1] = $dataIndeks['data'][0]['tahun'];
            $dataBobotProvinsi = $this->db->fetch("SELECT * FROM rf_provinsi_bobot WHERE deleted = 0 AND tahun=".$tahun[1]);
            if ($dataIndeks['total']) {
                $dataProvinsi = $this -> tables -> query("SELECT SUM(a.jumlah_penduduk) AS total_penduduk, SUM(a.luas_wilayah) AS total_luas_wilayah FROM rf_provinsi a");
                $sqlNasional = "SELECT a.* ,b.nama_propinsi, b.jumlah_penduduk, b.luas_wilayah, b.bobot_2023,
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
                        if($tahun[1] < 2023){
                          $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_provinsi'];
                          $nilai_indeks = array_sum($nilai_indeks_tmp);
                        }elseif ($tahun[1] < 2025){
                          // $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_2023'];
                          $idexBobotProvinsi = array_search($value['uid_provinsi'], array_column($dataBobotProvinsi['data'],'uid_provinsi'));
                          $bobotProvinsi = (is_numeric($idexBobotProvinsi) ? $dataBobotProvinsi['data'][$idexBobotProvinsi]['bobot'] : 0);
                          $nilai_indeks_tmp[] = $value['nilai_indeks'] * $bobotProvinsi;
                          $nilai_indeks = array_sum($nilai_indeks_tmp);
                        }else{
                          if($value['nilai_indeks'] > 0){
                            $nilai_indeks_tmp[] = $value['nilai_indeks'];
                            $nilai_indeks = array_sum($nilai_indeks_tmp)/count($nilai_indeks_tmp);
                          }
                        }
                    }
                }
                // $this->debug->show($nilai_indeks_tmp_origin);
                $this -> tables -> set("indeks_iktl", "uid_indeks_iktl");
                $postIdx['form']['uid_indeks_iktl'] = $post['form']['uid_indeks_iktl'];
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

        $this -> getDataIndeks($tahun[1]);
        $this -> cekLockSystem(3, 3.3, $this -> me['uid_users']);
        $this -> view -> assign("rf_catatan",$this->ref->getRekomendasiCatatan('ikl','perhitungan')['data']);
        $this -> view -> assign("indeksActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("scrollTobtn", $post['scrollTo']);
        $this -> view -> assign("activeBtn", $post['form']['uid_indeks_iktl']);
        $this -> view -> assign("icons", '<i class="la la-map"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS LAHAN');
        $this -> view -> display("index.html");
    }

    private function _countIndeks($uid_indeks, $jenis_indeks)
    {//function for counting data pelaporan
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
            $w .= " AND v_provinsi =1 AND v_pusat =1 AND tanggal BETWEEN '" . $dataIndeks['tahun'] . "-01-01' AND '" . $dataIndeks['tahun'] . "-12-31'";
            if ($jenis_indeks == 1) {

                if($dataIndeks["tahun"] >= 2025){
                  $dataIndeksCn = $this->iktl->cnIndeks2025($dataIndeks["tahun"],$dataIndeks['uid_provinsi'],$dataIndeks['uid_kabkota'],false);
                  $json_data['all'] = $dataIndeksCn;
                  $json_data['tl'] = $dataIndeksCn['itl'];
                  $json_data['iktl'] = $dataIndeksCn['iktl'];
                  $json_data['dkk'] = 0;
                  $json_data['tl_dkk'] = 0;
                  $json_data['tl_dkk'] = 0;
                  $nilai_indeks = $dataIndeksCn['iktl'];
                }else{
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
                }
            } elseif ($jenis_indeks == 2) {
                if($dataIndeks["tahun"] >= 2025){
                  $dataIndeksCn = $this->iktl->cnIndeks2025($dataIndeks["tahun"],$dataIndeks['uid_provinsi'],0,false);
                  $json_data['all'] = $dataIndeksCn;
                  $json_data['tl'] = $dataIndeksCn['itl'];
                  $json_data['iktl'] = $dataIndeksCn['iktl'];
                  $json_data['dkk'] = 0;
                  $json_data['tl_dkk'] = 0;
                  $nilai_indeks = $dataIndeksCn['iktl'];
                }else{
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

                  $getDkkProvinsi = $this->tables->query("SELECT * FROM nilai_dkk WHERE deleted = 0 AND peruntukan = 2 AND uid_provinsi=".$dataIndeks['uid_provinsi']." AND tahun=".$dataIndeks['tahun']);
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
            }

            $this -> tables -> set("indeks_iktl", "uid_indeks_iktl");
            $postIdx['form']['uid_indeks_iktl'] = $uid_indeks;
            $postIdx['form']['nilai_indeks'] = ($nilai_indeks > 100 ? 100 : $nilai_indeks);
            $postIdx['form']['json_data'] = json_encode($json_data);
            $postIdx['form']['status_hitung'] = 1;
            $postIdx['submit'] = true;
            // $this->debug->show($postIdx);
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
        }
    }

    private function updateHistory($nilai_indeks = 0, $jenis_indeks = 0, $tahun = 0, $uid_kabkota = 0, $uid_provinsi = 0)
    {
        if ($jenis_indeks == 1) {
            $wHistory = 'deleted = 0 AND jenis_indeks = 1 AND tahun=' . $tahun . " AND uid_provinsi =" . $uid_provinsi;
        } elseif ($jenis_indeks == 2) {
            $wHistory = 'deleted = 0 AND jenis_indeks = 2 AND tahun=' . $tahun;
        } else {
            $wHistory = 'deleted = 0 AND jenis_indeks = 0 AND tahun=' . $tahun . " AND uid_provinsi =" . $uid_provinsi . " AND uid_kabkota=" . $uid_kabkota;
        }
        $cekHistory = $this -> tables -> query("SELECT * FROM indeks_history WHERE " . $wHistory);
        $ch = $cekHistory['data'][0];
        $updateHistory['form']['uid_indeks_history'] = ($cekHistory['total'] ? $cekHistory['data'][0]['uid_indeks_history'] : '');
        $updateHistory['form']['jenis_indeks'] = $jenis_indeks;
        $updateHistory['form']['uid_kabkota'] = $uid_kabkota;
        $updateHistory['form']['uid_provinsi'] = $uid_provinsi;
        $updateHistory['form']['tahun'] = $tahun;
        $updateHistory['form']['ikl'] = ($nilai_indeks ? $nilai_indeks : 0);
        if ($jenis_indeks == 1) {
            if($uid_provinsi == 36){
              $cnIklhTrue = (0.376 * $ch['ika']) + (0.405 * $ch['iku']) + (0.219 * $nilai_indeks);
              $cnIklhFalse = (0.219 * $nilai_indeks);
              $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            }else{
              $cnIklhTrue = (0.340 * $ch['ika']) + (0.428 * $ch['iku']) + (0.133 * $nilai_indeks) + (0.099 * $ch['ikal']);
              $cnIklhFalse = (0.133 * $nilai_indeks);
              $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            }
        // =(0,34*J3)+(0,428*I3)+(0,133*K3)+(0,099*L3)
        } elseif ($jenis_indeks == 2) {
            // IKLH = (0.340 x IKA Nasional)+(0.428 x IKU Nasional)+ (0.133 x IKL Nasional)+(0.099 x IKAL Nasional)
            $cnIklhTrue = (0.340 * $ch['ika']) + (0.428 * $ch['iku']) + (0.133 * $nilai_indeks) + (0.099 * $ch['ikal']);
            $cnIklhFalse = ($nilai_indeks);
            $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
        } else {
            $cnIklhTrue = (0.376 * $ch['ika']) + (0.405 * $ch['iku']) + (0.219 * $nilai_indeks);
            $cnIklhFalse = (0.219 * $nilai_indeks);
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
    {// function get data indeks pelaporan
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
        // $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iktl a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota , d.ikl AS target
                FROM indeks_iktl a
                LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
                LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota
                LEFT JOIN rf_target_iklh d ON d.uid_provinsi = a.uid_provinsi AND d.uid_kabkota = a.uid_kabkota AND d.tahun = a.tahun AND d.deleted = 0
                WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
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

        $this -> view -> assign("urlVar", $urlVar);
        $this -> view -> assign("totalRow", $totalRow);
        $this -> view -> assign("limit", $limit);
        $this -> view -> assign("page", $offset);
        $this -> view -> assign("view", $data['data']);

        $ids = array_column($data['data'], 'uid_kabkota');
        $ids = implode(',', $ids);
        if($data['total']){
          $wkabkota = 'deleted =0 AND YEAR(tanggal) ='.$post['form']['tahun'].' AND uid_kabkota IN('.$ids.')';
          $pelaporan = $this -> tables -> query('SELECT uid_provinsi, uid_kabkota, tanggal, v_provinsi, v_regional, v_pusat FROM pelaporan_iktl WHERE '.$wkabkota);
          $dataP = $pelaporan['data'];
          $groupedItems = array_reduce($dataP, function ($carry, $dataP) {
              $carry[$dataP['uid_kabkota']] = $dataP;
              return $carry;
          }, []);
        }
        // $this->debug->show($data);

        $this -> view -> assign("statusPlp", array('Unverified', 'Verified', 'Rejected'));
        $this -> view -> assign("pelaporan", $groupedItems);

        $kabkota = $this -> tables -> query('SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iktl a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.jenis_indeks=0 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $provinsi = $this -> tables -> query('SELECT a.*, b.nama_propinsi AS nama_provinsi, c.nama_kabkot AS nama_kabkota FROM indeks_iktl a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi LEFT JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota WHERE a.jenis_indeks=1 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);

        if ($this->params("ex") == "kabkota") {
            $this->expExcel($kabkota, null);
        } elseif ($this->params("ex") == "provinsi") {
            $this->expExcel(null, $provinsi);
        }

        $this -> view -> assign("viewp_idx", base64_encode(implode(",",array_column($provinsi["data"],"uid_indeks_iktl"))));
        $this -> view -> assign("viewk_idx", base64_encode(implode(",",array_column($kabkota["data"],"uid_indeks_iktl"))));
    }

    private function rfData()
    {
        $this -> tables -> set("provinsi", "id");
        $rf = $this -> tables -> fetch();
        $this -> view -> assign("provinsi", $rf['data']);
        $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
        $rf = $this -> tables -> fetch("deleted = 0 AND uid_rf_component = 3");
        $this -> view -> assign("lokasi", $rf['data']);
        // $this->debug->show($rf);

        if ($this -> me['role_user'] == 2) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
            $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
            $this -> tables -> set("rf_kabkota", "kd_kota");
            $rf = $this -> tables -> fetch("deleted=0 AND kd_provinsi=" . $this -> me['uid_provinsi']);
            $this -> view -> assign("kabkota", $rf['data']);
        } elseif ($this -> me['role_user'] == 3) {
            $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
            $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $wProvinsi = "kd_regional =" . $this -> me['uid_regional'];

            if($this->me["uid_provinsi_lainnya"]){
              $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
              $wProvinsi .= " AND kd_propinsi IN ({$provinsiLainnya})";
            }
        } else {
            $wProvinsi = "";
        }
        $this -> tables -> set("rf_regional", "kd_regional");
        $rf = $regSelect = $this -> tables -> fetch($wRegional);
        $this -> view -> assign("regional", $rf['data']);
        $this -> tables -> set("rf_provinsi", "kd_propinsi");
        $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
        $this -> view -> assign("provinsi", $rf['data']);
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
    }

    public function verifikasi()
    {//index verification menu
        $message = $this->submitExcelRevisiRTHRHL();
        $this -> getData();
        $this -> rfData();
        $this -> cekLockSystem(2, 2.3, $this -> me['uid_users']);
        $this->view->assign("rf_catatan",$this->ref->getRekomendasiCatatan('ikl','verifikasi')['data']);
        $this -> view -> assign("verifikasiActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("icons", '<i class="la la-map"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS LAHAN');
        $this -> view -> display("index.html");
    }

    private function submitExcelRevisiRTHRHL(){
      $post = $this -> post();
      if (isset($post['submit-excel-revisi'])) {
				$post['form']['cruser'] = $this -> me['uid_users'];
				$val = $_FILES['file_excel'];
				$ext = strtolower(strrchr($val['name'], "."));
				if ($ext == ".xls") {
						$files = $this -> functions -> uploadFile($_FILES['file_excel']);
				}
				if ($files) {
						$excelReader = new Spreadsheet_Excel_Reader(UPLOADFOLDER . "docs/" . $files, true);
						$rows = $excelReader -> rowcount(0);
						for ($c = 1; $c <= 25; $c++) {
								for ($d = 3; $d <= $rows; $d++) {
										if ($excelReader -> val($d, $c)) {
												$data[$d][$c] = trim($excelReader -> val($d, $c));
										} else {
												$data[$d][$c] = "-";
										};
								}
						}
						unlink(UPLOADFOLDER . "docs/" . $files);

						$tmpCn = 1;
						$tmpDuplikasi = NULL;
            $tmpProv = NULL;
						$tmpTanggal = NULL;
						foreach ($data as $key => $vals) {
              if($vals[1] != "-" && $vals[2] != "-" && $vals[3] != "-") {
                $cek_provinsi = $this -> tables -> query("SELECT * FROM rf_provinsi WHERE nama_propinsi LIKE '".$vals[1]."'");
                $cek_kabkota = $this -> tables -> query("SELECT * FROM rf_kabkota WHERE deleted=0 AND nama_kabkot LIKE '".$vals[2]."' AND kd_provinsi=".$cek_provinsi['data'][0]['kd_propinsi']);

                $postIkl['form']['cruser'] = $post['form']['cruser'];
								$postIkl['form']['jenis_data'] = $post['form']['jenis_data'];

                $postIkl['form']['uid_provinsi'] = $cek_provinsi['data'][0]['kd_propinsi'];
                $postIkl['form']['uid_kabkota'] = $cek_kabkota['data'][0]['kd_kota'];
								$postIkl['form']['tanggal'] = ($vals[3] != "-" ? date("Y-m-d", strtotime($vals[3])) : "");

                $postIkl['form']['hutan_lahan_kering_primer'] = ($vals[4] != "-" ? str_replace(",", ".", $vals[4]) : "");
                $postIkl['form']['hutan_lahan_kering_sekunder'] = ($vals[5] != "-" ? str_replace(",", ".", $vals[5]) : "");
                $postIkl['form']['hutan_mangrove_primer'] = ($vals[6] != "-" ? str_replace(",", ".", $vals[6]) : "");
                $postIkl['form']['hutan_rawa_primer'] = ($vals[7] != "-" ? str_replace(",", ".", $vals[7]) : "");
                $postIkl['form']['hutan_tanaman'] = ($vals[8] != "-" ? str_replace(",", ".", $vals[8]) : "");
                $postIkl['form']['belukar'] = ($vals[9] != "-" ? str_replace(",", ".", $vals[9]) : "");
                $postIkl['form']['perkebunan'] = ($vals[10] != "-" ? str_replace(",", ".", $vals[10]) : "");
                $postIkl['form']['permukiman'] = ($vals[11] != "-" ? str_replace(",", ".", $vals[11]) : "");
                $postIkl['form']['lahan_terbuka'] = ($vals[12] != "-" ? str_replace(",", ".", $vals[12]) : "");
                $postIkl['form']['savanna'] = ($vals[13] != "-" ? str_replace(",", ".", $vals[13]) : "");
                $postIkl['form']['tubuh_air'] = ($vals[14] != "-" ? str_replace(",", ".", $vals[14]) : "");
                $postIkl['form']['hutan_mangrove_sekunder'] = ($vals[15] != "-" ? str_replace(",", ".", $vals[15]) : "");
                $postIkl['form']['hutan_rawa_sekunder'] = ($vals[16] != "-" ? str_replace(",", ".", $vals[16]) : "");
                $postIkl['form']['belukar_rawa'] = ($vals[17] != "-" ? str_replace(",", ".", $vals[17]) : "");
                $postIkl['form']['pertanian_lahan_kering'] = ($vals[18] != "-" ? str_replace(",", ".", $vals[18]) : "");
                $postIkl['form']['pertanian_lahan_kering_campur_semak'] = ($vals[19] != "-" ? str_replace(",", ".", $vals[19]) : "");
                $postIkl['form']['sawah'] = ($vals[20] != "-" ? str_replace(",", ".", $vals[20]) : "");
                $postIkl['form']['tambak'] = ($vals[21] != "-" ? str_replace(",", ".", $vals[21]) : "");
                $postIkl['form']['bandara'] = ($vals[22] != "-" ? str_replace(",", ".", $vals[22]) : "");
                $postIkl['form']['transmigrasi'] = ($vals[23] != "-" ? str_replace(",", ".", $vals[23]) : "");
                $postIkl['form']['pertambangan'] = ($vals[24] != "-" ? str_replace(",", ".", $vals[24]) : "");
                $postIkl['form']['rawa'] = ($vals[25] != "-" ? str_replace(",", ".", $vals[25]) : "");

                if($postIkl['form']['uid_provinsi'] > 0 && $postIkl['form']['uid_kabkota'] > 0 && $postIkl['form']['tanggal'] >= 2025 && $postIkl["form"]["jenis_data"]){
                  $cekPelaporan = $this -> db -> fetch("SELECT * FROM pelaporan_iktl WHERE deleted = 0 AND uid_provinsi=".$postIkl['form']['uid_provinsi']." AND uid_kabkota=".$postIkl['form']['uid_kabkota']." AND YEAR(tanggal)=".date('Y', strtotime($postIkl['form']['tanggal'])));
                  $cekPelaporanRevisi = $this -> db -> fetch("SELECT uid_pelaporan_iktl_revisi_pusat FROM pelaporan_iktl_revisi_pusat WHERE deleted = 0 AND jenis_data = '".$postIkl['form']['jenis_data']."' AND uid_provinsi=".$postIkl['form']['uid_provinsi']." AND uid_kabkota=".$postIkl['form']['uid_kabkota']." AND YEAR(tanggal)=".date('Y', strtotime($postIkl['form']['tanggal'])));
                  if($cekPelaporan['total'] == 1 && $cekPelaporanRevisi['total'] <= 1){
                    $this -> tables -> set("pelaporan_iktl_revisi_pusat", "uid_pelaporan_iktl_revisi_pusat");
                    $postIkl['form']['tahun'] = date("Y", strtotime($postIkl['form']['tanggal']));
                    $postIkl['form']['uid_pelaporan_iktl'] = $cekPelaporan["data"][0]["uid_pelaporan_iktl"];
                    $postIkl['form']['uid_pelaporan_iktl_revisi_pusat'] = $cekPelaporanRevisi["data"][0]["uid_pelaporan_iktl_revisi_pusat"];
                    $postIkl['submit'] = true;
                    $this -> tables -> post($postIkl);
                    $tmpPost[] = $postIkl;
                  }else{
                    if($$cekPelaporan['total'] > 1){
                      $tmpDuplikasi[] = $vals[1]."-".$vals[2].' tahun '.date("Y", strtotime($postIkl['form']['tanggal']));
                    }
                  }
                }else{
                  if($postIkl['form']['tanggal'] == ""){
                    $tmpTanggal[] = $vals[1]."-".$vals[2];
                  }else{
                    $tmpProv[] = $vals[1]."-".$vals[2];
                  }
                }
							}
							if ($tmpDuplikasi) {
								$message = "Pelaporan IKL ".implode(', ', $tmpDuplikasi)." tidak tersimpan oleh sistem dikarenakan sudah ada data";
                $message .= ($tmpProv ? "<br><br>Pelaporan IKL ".implode(',',array_unique($tmpProv)). " tidak tersimpan karena provinsi/kabkota tidak terdaftar disistem atau nama tidak sesuai dengan disistem" : "");
								$message .= ($tmpTanggal ? "<br><br>Pelaporan IKL ".implode(',',array_unique($tmpTanggal)). " tidak tersimpan karena tanggal kosong" : "");
							}else {
								if (count($data) == $tmpCn) {
										$message = "Berhasil menyimpan data !". ($tmpProv ? ", ".implode(',',$tmpProv). " Tidak terdaftar disistem atau nama tidak sesuai dengan disistem" : "");
                    $message .= ($tmpTanggal ? "<br><br>Pelaporan IKL ".implode(',',array_unique($tmpTanggal)). " tidak tersimpan karena tanggal pemantauan kosong" : "");
								} else {
										$message = "Gagal menyimpan data !";
								}
							}
							$tmpCn++;
						}
            // $this->debug->show($tmpPost);
            return $message;
				}else{
          return "Gagal menyimpan data !, file tidak terbaca oleh sistem";
        }
  		}
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
            $dataUpdate['form']['uid_pelaporan_iktl'] = $dataRequest['uid'];
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
            $this->tables->set('pelaporan_iktl', 'uid_pelaporan_iktl');
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
            $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
            $post['form']['uid_pelaporan_iktl'] = $uid;
            $post['form'][$field] = $act == 'un' ? 0 : $act;
            $post['form'][$field.'_date'] = date("Y-m-d H:i:s");
            $post['form']['v_reject_status'] = 0;
            $post['submit'] = true;
            if ($this -> tables -> post($post)) {
                $this -> tables -> set("v_pelaporan_iktl", "uid_pelaporan_iktl");
                $dataLokasi = $this -> tables -> fetch("uid_pelaporan_iktl=" . $uid);
                $this -> generatefieldInIndeks(date("Y", strtotime($dataLokasi['data'][0]['tanggal'])), $dataLokasi['data'][0]['uid_provinsi'], $dataLokasi['data'][0]['uid_kabkota']);
                echo 1;
            } else {
                echo 2;
            }
        } else {
            echo 2;
        }
    }

    private function generatefieldInIndeks($tahun, $uid_provinsi, $uid_kabkota)
    {// function for generate field indeks
        $cekDataKabkota = $this -> tables -> query("SELECT uid_indeks_iktl FROM indeks_iktl WHERE deleted = 0 AND jenis_indeks =0  AND uid_kabkota=" . $uid_kabkota . " AND tahun=" . $tahun);
        if (!$cekDataKabkota['total']) {
            $this -> tables -> set("indeks_iktl", "uid_indeks_iktl");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = $uid_provinsi;
            $postIdx['form']['uid_kabkota'] = $uid_kabkota;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
        $cekDataProvinsi = $this -> tables -> query("SELECT uid_indeks_iktl FROM indeks_iktl WHERE deleted = 0 AND jenis_indeks =1 AND uid_provinsi=" . $uid_provinsi . " AND tahun=" . $tahun);
        if (!$cekDataProvinsi['total']) {
            $this -> tables -> set("indeks_iktl", "uid_indeks_iktl");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = $uid_provinsi;
            $postIdx['form']['uid_kabkota'] = 0;
            $postIdx['form']['jenis_indeks'] = 1;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
        $cekDataNasional = $this -> tables -> query("SELECT uid_indeks_iktl FROM indeks_iktl WHERE deleted = 0 AND jenis_indeks =2 AND tahun=" . $tahun);
        if (!$cekDataNasional['total']) {
            $this -> tables -> set("indeks_iktl", "uid_indeks_iktl");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = 0;
            $postIdx['form']['uid_kabkota'] = 0;
            $postIdx['form']['jenis_indeks'] = 2;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
    }

    //new rth

    public function rthDetail(){
      $excel = $this->params("excel");
      if(is_numeric($this->params("x"))){
        $urlExcel = "iktl/rthDetail/x/".$this->params('x')."/ps/".$this->params('ps')."/pe/".$this->params('pe')."/dv/".$this->params('dv')."/excel/1";
        $this->view->assign("url_excel",$urlExcel);

        $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
        $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_iktl=" . $this -> params("x"));
        if ($dataEdit['data'][0]['data_rth_verifikasi']) {
          $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth_verifikasi'], TRUE);
        }else {
          $dataEdit['data'][0]['data_rth'] = json_decode($dataEdit['data'][0]['data_rth'], TRUE);
        }

        $refJenisRth = $this->tables->query("SELECT * FROM rf_jenis_rth WHERE deleted = 0")['data'];
        $this->view->assign("jenisRth",$refJenisRth);


        $this->view->assign("viewDataList", $this->params("x"));
        $this->view->assign("dv", $this->params("dv"));

        $totalRow = count($dataEdit['data'][0]['data_rth']['data_rth']);

        $numList 	= round($totalRow/300);
        $numListRes = $numList * 300;
        if ($totalRow >= $numListRes) {
            $numList += 1;
        }
        $itemCount	= 1;
        $limitCount	= 300;
        for ($itemCount; $itemCount<=$numList; $itemCount++) {
            if ($itemCount == 1) {
                $pagingRow[$itemCount]['offset_start'] = 0;
            } else {
                $pagingRow[$itemCount]['offset_start'] = ($limitCount - 300);
            }

            if ($limitCount >= $totalRow) {
                $pagingRow[$itemCount]['offset_end'] = $totalRow;
            } else {
                $pagingRow[$itemCount]['offset_end'] = $limitCount-1;
            }

            $limitCount += 300;
        }


        $pagingStart = ($this->params("ps")?$this->params("ps"):$pagingRow[1]['offset_start']);
        $pagingEnd = ($this->params("pe")?$this->params("pe"):$pagingRow[1]['offset_end']);

        $offset = $pagingStart;
        $dataAssign = array_slice($dataEdit['data'][0]['data_rth']['data_rth'], $offset, 300);
        $this->view->assign("paginationStart", $pagingStart);
        $this->view->assign("paginationEnd", $pagingEnd);
        $this->view->assign("paginationList", $pagingRow);
        $this->view->assign("dataExcel",$dataAssign);

        // $this->view->assign("dataExcel",$dataEdit['data'][0]['data_rth']['data_rth']);
        if($excel){
          $htmlList = $this->view->fetch('parts/contents/iktl/index/rincian/rthListExcel.html');
        }else{
          $htmlList = $this->view->fetch('parts/contents/iktl/index/rincian/rthList.html');
        }
        $this->view->assign("listRth", $htmlList);
      }
      if($excel){
        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="DATA_RTH-'.time().'.xls"');
        echo $htmlList;
      }else{
        $html = $this->view->fetch('parts/contents/iktl/index/rincian/rth.html');
        $data = array("statusCode"=>200, "html"=>$html, "paging", $pagingRow);
        echo json_encode($data);
      }
    }
    public function rthLocation(){
      $uidProvinsi = $this->params("p");
      $uidKabkota = $this->params("k");
      if(is_numeric($uidKabkota) && is_numeric($uidProvinsi)){
        $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
        $dataEdit = $this->tables->query("SELECT tanggal, data_rth, data_rth_verifikasi FROM pelaporan_iktl WHERE deleted = 0 AND uid_provinsi=" . $uidProvinsi. " AND uid_kabkota =".$uidKabkota);
        foreach ($dataEdit['data'] as $key => $value) {
          if ($dataEdit['data'][$key]['data_rth_verifikasi']) {
            $dataEdit['data'][$key]['data_rth'] = $dataEdit['data'][$key]['data_rth_verifikasi'];
          }else {
            $dataEdit['data'][$key]['data_rth'] = $dataEdit['data'][$key]['data_rth'];
          }
          unset($dataEdit['data'][$key]['data_rth_verifikasi']);
        }
      }
      $data = array("statusCode"=>200, "data"=>$dataEdit['data']);
      echo json_encode($data);
    }
    public function uploadJsonFile(){
      $post = $this->post();
      if($post['data_rth_json_file']){
        unlink(UPLOADFOLDER."rth_detail_json/".$post['data_rth_json_file']);
      }
      // $files = $this -> functions -> uploadFile(,'rth_detail');
      $fname = time() . ".json";
      move_uploaded_file($_FILES['fileJson']['tmp_name'], UPLOADFOLDER . "rth_detail_json/" . $fname);
      echo $fname;
    }
    public function removeJsonFile(){
      $post = $this->post();
      if($post['data_rth_json_file']){
        unlink(UPLOADFOLDER."rth_detail_json/".$post['data_rth_json_file']);
      }
      if($post['data_tutupan_json_file']){
        unlink(UPLOADFOLDER."tutupan_detail_json/".$post['data_tutupan_json_file']);
      }

      echo 1;
    }

    public function rthUpload(){
      $post = $this->post();
      $post['form']['data_rth'] = [];

      $dataFile = file_get_contents(UPLOADFOLDER."rth_detail_json/".$post['file_json']);
      // $this->debug->show(UPLOADFOLDER."rth_detail_json/".$post['file_json']);
      if($dataFile){
        $post['form']['data_rth'] = json_decode($dataFile,TRUE);
        $chekFileOrigin = explode("-",$post['file_json']);
        if(count($chekFileOrigin) > 1){
          // if($checkFileOrigin[0] != $this->me['uid_users']){
          //   unlink(UPLOADFOLDER."rth_detail_json/".$post['file_json']);
          // }
          $post['form']['check'] = $chekFileOrigin;
        }else{
          unlink(UPLOADFOLDER."rth_detail_json/".$post['file_json']);
        }
      }


      $refJenisRth = $this->tables->query("SELECT * FROM rf_jenis_rth WHERE deleted = 0")['data'];

      $val = $_FILES['file'];
      $ext = strtolower(strrchr($val['name'], "."));
      if ($ext == ".xls") {
          $files = $this -> functions -> uploadFile($_FILES['file'],'rth_detail');
      }
      $dirFile = UPLOADFOLDER . "rth_detail/" . $files;
      // $this->debug->show($dirFile);
      // die();
      $idxData = 0;
      $dataDetail = null;
      if($post['jenis_upload_rth'] == 2){
        $idxData = count($post['form']['data_rth']['data_rth']);
        $dataDetail = $post['form']['data_rth']['data_rth'];
      }

      if($files) {
        $excelReader = new Spreadsheet_Excel_Reader($dirFile, true);
        $rows = $excelReader -> rowcount(0);
        for ($c = 1; $c <= 60; $c++) {
            for ($d = 2; $d <= $rows; $d++) {
                if ($excelReader -> val($d, $c)) {
                    $data[$d][$c] = trim($excelReader -> val($d, $c));
                }
            }
        }
        unlink($dirFile);
        foreach ($data as $key => $val) {
          if($val[1]){
            $idxJenisRth = array_search($val[1], array_column($refJenisRth,'name'));
            // $dataDetail[$idxData]['jenis_rth_name'] = $refJenisRth[$idxJenisRth]['name'];
            $dataDetail[$idxData]['jenis_rth'] = $refJenisRth[$idxJenisRth]['idx'];
            $dataDetail[$idxData]['luas_rth'] = $val[2];
            $dataDetail[$idxData]['nama_rth'] = $val[3];
            $dataDetail[$idxData]['nama_desa'] = $val[4];
            $dataDetail[$idxData]['nama_kecamatan'] = $val[5];
            $dataDetail[$idxData]['koordinat_lintang'] = $val[6];
            $dataDetail[$idxData]['koordinat_bujur'] = $val[7];
            // $dataDetail[$idxData]['sumber_dana_name'] = $val[8];
            $dataDetail[$idxData]['sumber_dana'] = ($val[8] == "APBN" ? 1 : ($val[8] == "APBD" ? 2 : ($val[8] == "SWASTA" ? 3 : "-")));
            $dataDetail[$idxData]['nomor_sk'] = $val[9];
            $dataDetail[$idxData]['keterangan'] = $val[10];
            $dataDetail[$idxData]['verify'] = 1;

            $idxData +=1;
          }
        }
      }
      $dataDetail = array_values($dataDetail);
      $this->view->assign("jenisRth",$refJenisRth);
      $this->view->assign("dataExcel",$dataDetail);
      $html = $this->view->fetch('parts/contents/iktl/index/rincian/rthList.html');
      $data = array("statusCode"=>200, "data"=>$dataDetail, "html"=>$html,"post"=>$post);
      echo json_encode($data);
    }

    public function verifyRth(){
      $uidPelaporanIktl = $this->params("x");
      $indexRth = $this->params("xr");
      $verify = $this->params("vr");
      if(is_numeric($uidPelaporanIktl) && is_numeric($indexRth) && ($verify == "" || $verify == 1 || $verify == 2)){

        $getDataRth = $this->tables->query("SELECT uid_pelaporan_iktl, kebun_raya_data_lipi, taman_kehati, rth, data_rth, data_rth_verifikasi FROM pelaporan_iktl WHERE deleted = 0 AND uid_pelaporan_iktl=".$uidPelaporanIktl);
        $dataRth =  ($getDataRth['data'][0]['data_rth_verifikasi'] ? json_decode($getDataRth['data'][0]['data_rth_verifikasi'],TRUE) : json_decode($getDataRth['data'][0]['data_rth'],TRUE));

        $dataRth['filter_krdl'] = array_filter($dataRth['data_rth'], function($obj){
            if ($obj['jenis_rth'] == 1 && $obj['verify'] == 1) {
                return $obj;
            }
        });
        $dataRth['filter_krdl_total'] = array_sum(array_column($dataRth['filter_krdl'],"luas_rth"));
        $dataRth['filter_krdl_total'] = ($dataRth['filter_krdl_total'] ? $dataRth['filter_krdl_total'] : 0);

        $dataRth['filter_th'] = array_filter($dataRth['data_rth'], function($obj){
            if ($obj['jenis_rth'] == 2 && $obj['verify'] == 1) {
                return $obj;
            }
        });
        $dataRth['filter_th_total'] = array_sum(array_column($dataRth['filter_th'],"luas_rth"));
        $dataRth['filter_th_total'] = ($dataRth['filter_th_total'] ? $dataRth['filter_th_total'] : 0);

        $dataRth['filter_rth'] = array_filter($dataRth['data_rth'], function($obj){
            if ($obj['jenis_rth'] > 2 && $obj['verify'] == 1) {
                return $obj;
            }
        });
        $dataRth['filter_rth_total'] = array_sum(array_column($dataRth['filter_rth'],"luas_rth"));
        $dataRth['filter_rth_total'] = ($dataRth['filter_rth_total'] ? $dataRth['filter_rth_total'] : 0);

        $kebun_raya_data_lipi_sum = $dataRth['filter_krdl_total'];
        $taman_kehati_sum = $dataRth['filter_th_total'];
        $rth_sum = $dataRth['filter_rth_total'];

        // $kebun_raya_data_lipi_sum = $getDataRth['data'][0]['kebun_raya_data_lipi'];
        // $taman_kehati_sum = $getDataRth['data'][0]['taman_kehati'];
        // $rth_sum = $getDataRth['data'][0]['rth'];




        if($dataRth['data_rth'][$indexRth]){

          if($verify == 1){
            if($dataRth['data_rth'][$indexRth]['jenis_rth'] == 1){
              $kebun_raya_data_lipi_sum += $dataRth['data_rth'][$indexRth]['luas_rth'];
            }else if ($dataRth['data_rth'][$indexRth]['jenis_rth'] == 2) {
              $taman_kehati_sum += $dataRth['data_rth'][$indexRth]['luas_rth'];
            }else if ($dataRth['data_rth'][$indexRth]['jenis_rth'] > 2) {
              $rth_sum += $dataRth['data_rth'][$indexRth]['luas_rth'];
            }
          }elseif ($verify == 2) {
            if($dataRth['data_rth'][$indexRth]['jenis_rth'] == 1){
              $kebun_raya_data_lipi_sum -= $dataRth['data_rth'][$indexRth]['luas_rth'];
            }else if ($dataRth['data_rth'][$indexRth]['jenis_rth'] == 2) {
              $taman_kehati_sum -= $dataRth['data_rth'][$indexRth]['luas_rth'];
            }else if ($dataRth['data_rth'][$indexRth]['jenis_rth'] > 2) {
              $rth_sum -= $dataRth['data_rth'][$indexRth]['luas_rth'];
            }
          }

          if($rth_sum <= 0){
            $rth_sum = 0;
          }


          $dataRth['data_rth'][$indexRth]['verify'] = $verify;

          $updateRth['form']['kebun_raya_data_lipi'] = $kebun_raya_data_lipi_sum;
          $updateRth['form']['taman_kehati'] = $taman_kehati_sum;
          $updateRth['form']['rth'] = $rth_sum;

          $updateRth['form']['uid_pelaporan_iktl'] = $getDataRth['data'][0]['uid_pelaporan_iktl'];
          $updateRth['form']['data_rth_verifikasi'] = json_encode($dataRth);
          $updateRth['submit'] = 1;
          $this->tables->set("pelaporan_iktl", "uid_pelaporan_iktl");
          if($this->tables->post($updateRth)){
              $response = array("statusCode"=>200, "message"=> "data ".$dataRth['data_rth'][$indexRth]['nama_rth']." berhasil diverifikasi");
          }else{
              $response = array("statusCode"=>400, "message"=> "gagal mengupdate data");
          }
        }else{
          $response = array("statusCode"=>400, "message"=> "data tidak ditemukan");
        }
      }else{
          $response = array("statusCode"=>400, "message"=> "data tidak valid");
      }
      echo json_encode($response);
    }
    public function catatanVerifyRth(){
      $uidPelaporanIktl = $this->params("x");
      $indexRth = $this->params("xr");
      $post = $this->post();
      if(is_numeric($uidPelaporanIktl) && is_numeric($indexRth)){

        $getDataRth = $this->tables->query("SELECT uid_pelaporan_iktl, kebun_raya_data_lipi, taman_kehati, rth, data_rth, data_rth_verifikasi FROM pelaporan_iktl WHERE deleted = 0 AND uid_pelaporan_iktl=".$uidPelaporanIktl);
        $dataRth =  ($getDataRth['data'][0]['data_rth_verifikasi'] ? json_decode($getDataRth['data'][0]['data_rth_verifikasi'],TRUE) : json_decode($getDataRth['data'][0]['data_rth'],TRUE));

        if($dataRth['data_rth'][$indexRth]){
          $dataRth['data_rth'][$indexRth]['catatan'] = $post['catatan'];

          $updateRth['form']['uid_pelaporan_iktl'] = $getDataRth['data'][0]['uid_pelaporan_iktl'];
          $updateRth['form']['data_rth_verifikasi'] = json_encode($dataRth);
          $updateRth['submit'] = 1;
          $this->tables->set("pelaporan_iktl", "uid_pelaporan_iktl");
          if($this->tables->post($updateRth)){
            $response = array("statusCode"=>200, "message"=> "catatan berhasil disimpan");
          }else{
            $response = array("statusCode"=>400, "message"=> "gagal mengupdate data");
          }
        }
      }else{
        $response = array("statusCode"=>400, "message"=> "data tidak valid");
      }
      echo json_encode($response);
    }
    //end

    // new tutupan
    public function tutupanDetail(){
      $excel = $this->params("excel");
      if(is_numeric($this->params("x"))){
        $urlExcel = "iktl/tutupanDetail/x/".$this->params('x')."/ps/".$this->params('ps')."/pe/".$this->params('pe')."/dv/".$this->params('dv')."/excel/1";
        $this->view->assign("url_excel",$urlExcel);

        $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
        $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_iktl=" . $this -> params("x"));
        if ($dataEdit['data'][0]['data_tutupan_verifikasi']) {
          $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan_verifikasi'], TRUE);
        }else {
          $dataEdit['data'][0]['data_tutupan'] = json_decode($dataEdit['data'][0]['data_tutupan'], TRUE);
        }

        $refJenisTutupan = $this->tables->query("SELECT * FROM rf_jenis_tutupan WHERE deleted = 0")['data'];
        $this->view->assign("jenisTutupan",$refJenisTutupan);


        $this->view->assign("viewDataList", $this->params("x"));
        $this->view->assign("dv", $this->params("dv"));

        $totalRow = count($dataEdit['data'][0]['data_tutupan']['data_tutupan']);

        $numList 	= round($totalRow/300);
        $numListRes = $numList * 300;
        if ($totalRow >= $numListRes) {
            $numList += 1;
        }
        $itemCount	= 1;
        $limitCount	= 300;
        for ($itemCount; $itemCount<=$numList; $itemCount++) {
            if ($itemCount == 1) {
                $pagingRow[$itemCount]['offset_start'] = 0;
            } else {
                $pagingRow[$itemCount]['offset_start'] = ($limitCount - 300);
            }

            if ($limitCount >= $totalRow) {
                $pagingRow[$itemCount]['offset_end'] = $totalRow;
            } else {
                $pagingRow[$itemCount]['offset_end'] = $limitCount-1;
            }

            $limitCount += 300;
        }


        $pagingStart = ($this->params("ps")?$this->params("ps"):$pagingRow[1]['offset_start']);
        $pagingEnd = ($this->params("pe")?$this->params("pe"):$pagingRow[1]['offset_end']);

        $offset = $pagingStart;
        $dataAssign = array_slice($dataEdit['data'][0]['data_tutupan']['data_tutupan'], $offset, 300);
        $this->view->assign("paginationStart", $pagingStart);
        $this->view->assign("paginationEnd", $pagingEnd);
        $this->view->assign("paginationList", $pagingRow);
        $this->view->assign("dataExcel",$dataAssign);
        // $this->view->assign("dataExcel",$dataEdit['data'][0]['data_tutupan']['data_tutupan']);
        if($excel){
          $htmlList = $this->view->fetch('parts/contents/iktl/index/rincian/tutupanListExcel.html');
        }else{
          $htmlList = $this->view->fetch('parts/contents/iktl/index/rincian/tutupanList.html');
        }
        $this->view->assign("listTutupan", $htmlList);
      }
      if($excel){
        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="DATA_TUTUPAN_VEGETASI-'.time().'.xls"');
        echo $htmlList;
      }else{
        $html = $this->view->fetch('parts/contents/iktl/index/rincian/tutupan.html');
        $data = array("statusCode"=>200, "html"=>$html, "paging", $pagingRow);
        echo json_encode($data);
      }
    }
    public function tutupanLocation(){
      $uidProvinsi = $this->params("p");
      $uidKabkota = $this->params("k");
      if(is_numeric($uidKabkota) && is_numeric($uidProvinsi)){
        $this -> tables -> set("pelaporan_iktl", "uid_pelaporan_iktl");
        $dataEdit = $this->tables->query("SELECT tanggal, data_tutupan, data_tutupan_verifikasi FROM pelaporan_iktl WHERE deleted = 0 AND uid_provinsi=" . $uidProvinsi. " AND uid_kabkota =".$uidKabkota);
        foreach ($dataEdit['data'] as $key => $value) {
          if ($dataEdit['data'][$key]['data_tutupan_verifikasi']) {
            $dataEdit['data'][$key]['data_tutupan'] = $dataEdit['data'][$key]['data_tutupan_verifikasi'];
          }else {
            $dataEdit['data'][$key]['data_tutupan'] = $dataEdit['data'][$key]['data_tutupan'];
          }
          unset($dataEdit['data'][$key]['data_tutupan_verifikasi']);
        }
      }
      $data = array("statusCode"=>200, "data"=>$dataEdit['data']);
      echo json_encode($data);
    }
    public function uploadJsonFileTutupan(){
      $post = $this->post();
      if($post['data_tutupan_json_file']){
        unlink(UPLOADFOLDER."tutupan_detail_json/".$post['data_tutupan_json_file']);
      }
      $fname = time() . ".json";
      move_uploaded_file($_FILES['fileJson']['tmp_name'], UPLOADFOLDER . "tutupan_detail_json/" . $fname);
      echo $fname;
    }

    public function tutupanUpload(){
      $post = $this->post();
      $post['form']['data_tutupan'] = [];

      $dataFile = file_get_contents(UPLOADFOLDER."tutupan_detail_json/".$post['file_json']);
      if($dataFile){
        $post['form']['data_tutupan'] = json_decode($dataFile,TRUE);
        $chekFileOrigin = explode("-",$post['file_json']);
        if(count($chekFileOrigin) > 1){
          // if($checkFileOrigin[0] != $this->me['uid_users']){
          //   unlink(UPLOADFOLDER."tutupan_detail_json/".$post['file_json']);
          // }
          $post['form']['check'] = $chekFileOrigin;
        }else{
          unlink(UPLOADFOLDER."tutupan_detail_json/".$post['file_json']);
        }
      }


      $refJenisTutupan = $this->tables->query("SELECT * FROM rf_jenis_tutupan WHERE deleted = 0")['data'];

      $val = $_FILES['file'];
      $ext = strtolower(strrchr($val['name'], "."));
      if ($ext == ".xls") {
          $files = $this -> functions -> uploadFile($_FILES['file'],'tutupan_detail');
      }
      $dirFile = UPLOADFOLDER . "tutupan_detail/" . $files;

      $idxData = 0;
      $dataDetail = null;
      if($post['jenis_upload_tutupan'] == 2){
        $idxData = count($post['form']['data_tutupan']['data_tutupan']);
        $dataDetail = $post['form']['data_tutupan']['data_tutupan'];
      }

      if($files) {
        $excelReader = new Spreadsheet_Excel_Reader($dirFile, true);
        $rows = $excelReader -> rowcount(0);
        for ($c = 1; $c <= 60; $c++) {
            for ($d = 2; $d <= $rows; $d++) {
                if ($excelReader -> val($d, $c)) {
                    $data[$d][$c] = trim($excelReader -> val($d, $c));
                }
            }
        }
        unlink($dirFile);

        foreach ($data as $key => $val) {
          if($val[1]){
            $idxJenisTutupan = array_search($val[1], array_column($refJenisTutupan,'name'));
            // $dataDetail[$idxData]['jenis_tutupan_name'] = $refJenisTutupan[$idxJenisTutupan]['name'];
            $dataDetail[$idxData]['jenis_tutupan'] = $refJenisTutupan[$idxJenisTutupan]['idx'];
            $dataDetail[$idxData]['luas_tutupan'] = $val[2];
            $dataDetail[$idxData]['nama_tutupan'] = $val[3];
            $dataDetail[$idxData]['nama_desa'] = $val[4];
            $dataDetail[$idxData]['nama_kecamatan'] = $val[5];
            $dataDetail[$idxData]['koordinat_lintang'] = $val[6];
            $dataDetail[$idxData]['koordinat_bujur'] = $val[7];
            // $dataDetail[$idxData]['sumber_dana_name'] = $val[8];
            $dataDetail[$idxData]['sumber_dana'] = ($val[8] == "APBN" ? 1 : ($val[8] == "APBD" ? 2 : ($val[8] == "SWASTA" ? 3 : "-")));
            $dataDetail[$idxData]['nomor_sk'] = $val[9];
            $dataDetail[$idxData]['keterangan'] = $val[10];
            $dataDetail[$idxData]['verify'] = 1;

            $idxData +=1;
          }
        }
      }
      $dataDetail = array_values($dataDetail);
      $this->view->assign("jenisTutupan",$refJenisTutupan);
      $this->view->assign("dataExcel",$dataDetail);
      $html = $this->view->fetch('parts/contents/iktl/index/rincian/tutupanList.html');
      $data = array("statusCode"=>200, "data"=>$dataDetail, "html"=>$html,"post"=>$post);
      echo json_encode($data);
    }

    public function verifyTutupan(){
      $uidPelaporanIktl = $this->params("x");
      $indexTutupan = $this->params("xr");
      $verify = $this->params("vr");
      if(is_numeric($uidPelaporanIktl) && is_numeric($indexTutupan) && ($verify == "" || $verify == 1 || $verify == 2)){

        $getDataTutupan = $this->tables->query("SELECT uid_pelaporan_iktl, tutupan_vegetasi, data_tutupan, data_tutupan_verifikasi FROM pelaporan_iktl WHERE deleted = 0 AND uid_pelaporan_iktl=".$uidPelaporanIktl);
        $dataTutupan =  ($getDataTutupan['data'][0]['data_tutupan_verifikasi'] ? json_decode($getDataTutupan['data'][0]['data_tutupan_verifikasi'],TRUE) : json_decode($getDataTutupan['data'][0]['data_tutupan'],TRUE));

        $dataTutupan['filter_tv'] = array_filter($dataTutupan['data_tutupan'], function($obj){
            if ($obj['verify'] == 1) {
                return $obj;
            }
        });
        $dataTutupan['filter_tv_total'] = array_sum(array_column($dataTutupan['filter_tv'],"luas_tutupan"));
        $dataTutupan['filter_tv_total'] = ($dataTutupan['filter_tv_total'] ? $dataTutupan['filter_tv_total'] : 0);

        $tutupan_vegetasi = $dataTutupan['filter_tv_total'];

        // $tutupan_vegetasi = $getDataTutupan['data'][0]['tutupan_vegetasi'];

        if($dataTutupan['data_tutupan'][$indexTutupan]){

          if($verify == 1){
            $tutupan_vegetasi += $dataTutupan['data_tutupan'][$indexTutupan]['luas_tutupan'];
          }elseif ($verify == 2) {
            if($tutupan_vegetasi > 0){
              $tutupan_vegetasi -= $dataTutupan['data_tutupan'][$indexTutupan]['luas_tutupan'];
            }else{
              $tutupan_vegetasi = 0;
            }
          }
          if($tutupan_vegetasi <= 0){
            $tutupan_vegetasi = 0;
          }

          $dataTutupan['data_tutupan'][$indexTutupan]['verify'] = $verify;

          $updateTutupan['form']['tutupan_vegetasi'] = $tutupan_vegetasi;
          $updateTutupan['form']['uid_pelaporan_iktl'] = $getDataTutupan['data'][0]['uid_pelaporan_iktl'];
          $updateTutupan['form']['data_tutupan_verifikasi'] = json_encode($dataTutupan);
          $updateTutupan['submit'] = 1;
          $this->tables->set("pelaporan_iktl", "uid_pelaporan_iktl");
          if($this->tables->post($updateTutupan)){
              $response = array("statusCode"=>200, "message"=> "data ".$dataTutupan['data_tutupan'][$indexTutupan]['nama_tutupan']." berhasil diverifikasi");
          }else{
              $response = array("statusCode"=>400, "message"=> "gagal mengupdate data");
          }
        }else{
          $response = array("statusCode"=>400, "message"=> "data tidak ditemukan");
        }
      }else{
          $response = array("statusCode"=>400, "message"=> "data tidak valid");
      }
      echo json_encode($response);
    }
    public function catatanVerifyTutupan(){
      $uidPelaporanIktl = $this->params("x");
      $indexTutupan = $this->params("xr");
      $post = $this->post();
      if(is_numeric($uidPelaporanIktl) && is_numeric($indexTutupan)){

        $getDataTutupan = $this->tables->query("SELECT uid_pelaporan_iktl, tutupan_vegetasi, data_tutupan, data_tutupan_verifikasi FROM pelaporan_iktl WHERE deleted = 0 AND uid_pelaporan_iktl=".$uidPelaporanIktl);
        $dataTutupan =  ($getDataTutupan['data'][0]['data_tutupan_verifikasi'] ? json_decode($getDataTutupan['data'][0]['data_tutupan_verifikasi'],TRUE) : json_decode($getDataTutupan['data'][0]['data_tutupan'],TRUE));

        if($dataTutupan['data_tutupan'][$indexTutupan]){
          $dataTutupan['data_tutupan'][$indexTutupan]['catatan'] = $post['catatan'];

          $updateTutupan['form']['uid_pelaporan_iktl'] = $getDataTutupan['data'][0]['uid_pelaporan_iktl'];
          $updateTutupan['form']['data_tutupan_verifikasi'] = json_encode($dataTutupan);
          $updateTutupan['submit'] = 1;
          $this->tables->set("pelaporan_iktl", "uid_pelaporan_iktl");
          if($this->tables->post($updateTutupan)){
            $response = array("statusCode"=>200, "message"=> "catatan berhasil disimpan");
          }else{
            $response = array("statusCode"=>400, "message"=> "gagal mengupdate data");
          }
        }
      }else{
        $response = array("statusCode"=>400, "message"=> "data tidak valid");
      }
      echo json_encode($response);
    }
    //end

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
                  if (is_numeric(array_search($users, $data['data'][0]['direktorat'])) || is_numeric(array_search($users, $data['data'][0]['kabkota'])) || is_numeric(array_search($users, $data['data'][0]['provinsi'])) || is_numeric(array_search($users, $data['data'][0]['p3e']))) {
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
