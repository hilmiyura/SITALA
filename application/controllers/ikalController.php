<?php
/**
 * created at 	: 01/10/2020
 * created by 	: dasendria team
 * desc		  	: controller INDEKS KUALITAS AIR LAUT IKLHK
 *
 */
class ikalController extends Front
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

    //INDEX FUNCTION IS A DEFAULT ACTION
    public function index()
    {
        $post = $this -> post();
        if (isset($post['submit'])) {

            //check reject update
            if(isset($post['form']['uid_pelaporan_ikal'])){
              $checkData = $this -> tables -> query("SELECT v_pusat, v_regional, v_provinsi FROM pelaporan_ikal WHERE uid_pelaporan_ikal =".$post['form']['uid_pelaporan_ikal'])['data'][0];
              $post['form']['v_reject_status'] = ($checkData['v_pusat'] == 2 ? 1 : ($checkData['v_regional'] == 2 ? 1 : ($checkData['v_provinsi'] == 2 ? 1 : 0)));
              if($post['form']['v_reject_status'] == 1){
                $post['form']['v_pusat'] = 0;
                $post['form']['v_regional'] = 0;
                $post['form']['v_provinsi'] = 0;
              }
            }
            //end

            // $ikals = $this->_countByLocation($post);
            if ($this -> me['role_user'] == 2) {
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
            }
            $file = $_FILES['shu'];
            if ($file['name']) {
                $fileUpload = $this -> functions -> uploadFile($_FILES['shu'], "monitoring");
                $post['form']['shu'] = $fileUpload;
            }
            $post['form']['json_data'] = $ikals['json_data'];
            $post['form']['wqi'] = $ikals['wqi'];
            $post['form']['wqr'] = $ikals['wqr'];
            $post['form']['cruser'] = $this -> me['uid_users'];

            $post['form']['uid_lab'] = implode(",",$post['form']['uid_lab']);

            if ($post['form'][$this->primaryKey]) {
                unset($post['form']['cruser']);
                $post['form']['chuser'] = $this->me['uid_users'];
            }
            if (!$post['form']['uid_lokasi_pemantauan']) {
                // $post['form']['uid_rf_component'] = 5;
                // $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
                // if ($this -> tables -> post($post)) {
                //     $post['form']['uid_lokasi_pemantauan'] = $this -> tables -> lastInsertID();
                //     $post['submit'] = true;
                //     $this -> tables -> set("pelaporan_ikal", "uid_pelaporan_ikal");
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
                $this -> tables -> set("pelaporan_ikal", "uid_pelaporan_ikal");
                if ($this -> tables -> post($post)) {
                    $message = "Berhasil menyimpan data !";
                } else {
                    $message = "Gagal menimpan data !";
                }
            }
        }

        if (isset($post['submit-excel'])) {
            if ($this -> me['role_user'] == 2) {
                $post['form']['uid_provinsi'] = $this -> me['uid_provinsi'];
            }
            $post['form']['cruser'] = $this -> me['uid_users'];
            $val = $_FILES['file_excel'];
            $ext = strtolower(strrchr($val['name'], "."));
            if ($ext == ".xls") {
                $files = $this -> functions -> uploadFile($_FILES['file_excel']);
            }
            if ($files) {
                $excelReader = new Spreadsheet_Excel_Reader(UPLOADFOLDER . "docs/" . $files, true);
                $rows = $excelReader -> rowcount(0);
                for ($c = 1; $c <= 11; $c++) {
                    for ($d = 3; $d <= $rows; $d++) {
                        if ($excelReader -> val($d, $c)) {
                            $data[$d][$c] = trim($excelReader -> val($d, $c));
                        }
                        // else {
                        //     $data[$d][$c] = null;
                        // };
                    }
                }
                unlink(UPLOADFOLDER . "docs/" . $files);
                $tmpCn = 0;
                $tmpTgl = NULL;
                $message = '';
                foreach ($data as $key => $vals) {
                    if ($vals[1] != null) {
                        // $vals[4] = str_replace(",", ".", $vals[4]);
                        // $vals[5] = str_replace(",", ".", $vals[5]);
                        $periode = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[3]);
                        // $where = "deleted = 0 AND uid_rf_component= 5 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND alamat='" . $vals[2] . "' AND latitude=" . $vals[4] . " AND longitude=" . $vals[5];
                        // $where = "deleted = 0 AND uid_rf_component= 5 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun=" . date("Y", strtotime($vals[1]));
                        if ($this -> me['role_user'] == 3) { //Kabkota
                          $where = "deleted = 0 AND uid_rf_component= 5 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND uid_kabkota=" . $post['form']['uid_kabkota'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun LIKE '%". date("Y", strtotime($vals[1]))."%'";
                        }else {
                          $where = "deleted = 0 AND uid_rf_component= 5 AND uid_provinsi=" . $post['form']['uid_provinsi'] . " AND kode_lokasi='" . $vals[2] . "' AND tahun LIKE '%". date("Y", strtotime($vals[1]))."%'";
                          // if ($this -> me['role_user'] <= 1) {
                          //   $where = $where.' AND uid_rf_pelaksana=1';
                          // }elseif ($this -> me['role_user'] == 2) {
                          //   $where = $where.' AND uid_rf_pelaksana=3';
                          // }elseif ($this -> me['role_user'] == 4) {
                          //   $where = $where.' AND uid_rf_pelaksana=2';
                          // }
                        }

                        $date = date("Y-m-d", strtotime($vals[1]));
                        if ($this -> cekLocation($where, $vals, $post['form']) && $date <= date("Y-m-d")) {
                          $postLaporan['form']['uid_pelaporan_ikal'] = "";
                          $postLaporan['form']['cruser'] = $post['form']['cruser'];
                          $postLaporan['form']['uid_lokasi_pemantauan'] = $this -> cekLocation($where, $vals, $post['form']);
                          $postLaporan['form']['periode_pemantauan'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[3]);
                          $postLaporan['form']['tanggal'] = date("Y-m-d", strtotime($vals[1]));
                          $postLaporan['form']['uid_rf_peruntukan'] = $this -> cekPeruntukan($vals[4]);
                          $postLaporan['form']['tss'] = str_replace(",", ".", $vals[5]);
                          $postLaporan['form']['do_p'] = str_replace(",", ".", $vals[6]);
                          $postLaporan['form']['amonia_total'] = str_replace(",", ".", $vals[7]);
                          $postLaporan['form']['orto_fosfat'] = str_replace(",", ".", $vals[8]);
                          $postLaporan['form']['minyak_dan_lemak'] = str_replace(",", ".", $vals[9]);
                          $postLaporan['form']['uid_lab'] = $this->checkLab(preg_replace('~[\\\\/:*?"<>|]~', "", $vals[10]));
                          $postLaporan['submit'] = true;
                          // $this->debug->show($postLaporan);
                          $this -> tables -> set("pelaporan_ikal", "uid_pelaporan_ikal");
                          if($this -> tables -> post($postLaporan)){
                              $tmpCn++;
                          }else{
                            $message .= "Gagal menyimpan data kode lokasi ". $vals[2] ."<br>";
                          }
                        }else {
                          $message .= "Gagal menyimpan data kode lokasi ". $vals[2] .", kesalahan pada kode lokasi atau tanggal melebihi tanggal saat ini<br>";
                        }
                    }
                }
                if (count($data) == $tmpCn && $message == '') {
                    $message = "Berhasil menyimpan data !";
                }
            }
        }

        $this -> getData();
        $this -> rfData();
        $this -> cekLockSystem(1, 1.4, $this->me['uid_users']);
        $this -> view -> assign("pelaporanActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("icons", '<i class="la la-line-chart"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS AIR LAUT');
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
    {
        $this -> tables -> set($this -> viewName, $this -> primaryKey);
        $properties = $this -> _getProperties($this -> viewName);
        $urlVar = BASEURL . $this -> url . '/';
        $w = $this -> where;
        if ($this -> me['role_user'] == 2) {
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
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_peruntukan']) {
                $w .= " AND uid_rf_peruntukan = " . $post['form']['src_peruntukan'];
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
        if ($this -> url == "ikal/verifikasi") {
            $o = "v_provinsi,v_regional,v_pusat ASC";
        }
        $search_json = urlencode(json_encode($post['form']));
        $this->view->assign("search_json", $search_json);
        //PAGING
        $offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
        $limit = LIMIT;
        $data = $this -> tables -> query('SELECT * FROM ' . $this -> viewName . ' WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
        $All = $this -> db -> query('SELECT count(' . $this -> primaryKey . ') as x FROM ' . $this -> viewName . ' WHERE ' . $w);
        $totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);

        $uid_lokasi_pemantauan_list = implode(",",array_keys(array_column($data["data"],null,"uid_lokasi_pemantauan")));
        if($uid_lokasi_pemantauan_list){
          $checkJumlahLapor = $this->db->fetch("SELECT uid_lokasi_pemantauan, COUNT(uid_lokasi_pemantauan) AS total FROM pelaporan_ikal WHERE deleted = 0 AND uid_lokasi_pemantauan IN({$uid_lokasi_pemantauan_list}) AND YEAR(tanggal) = ".$post['form']['tahun']." GROUP BY uid_lokasi_pemantauan HAVING total < 2")["data"];
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
            $this -> tables -> set("pelaporan_ikal", "uid_pelaporan_ikal");
            $dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_pelaporan_ikal=" . $this -> params("x"));
            echo json_encode($dataEdit['data'][0]);
        }
    }

    public function deletedData()
    {
        $post = $this -> post();
        if (isset($post['x'])) {
            $this -> tables -> set("pelaporan_ikal", "uid_pelaporan_ikal");
            if ($this -> tables -> softDelete($post['x'])) {
                echo json_encode(array('statusCode' => 200, 'message' => $this -> message -> delete('success')));
            } else {
                echo json_encode(array('statusCode' => 400, 'message' => $this -> message -> delete('failed')));
            }
        } else {
            echo json_encode(array('statusCode' => 403, 'message' => $this -> message -> access()));
        }
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
            header('Content-Disposition: attachment; filename="PERHITUNGAN_IKAL_KABKOTA_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/ikal/indeks/excel_kabkota.html');
            echo $html;
        } elseif ($provinsi) {
            $this -> view -> assign("viewp", $provinsi);
            header("Content-type: application/vnd-ms-excel");
            header('Content-Disposition: attachment; filename="PERHITUNGAN_IKAL_PROVINSI_'.time().'.xls"');
            $html = $this->view->fetch('parts/contents/ikal/indeks/excel_provinsi.html');
            echo $html;
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
        $properties = $this -> _getProperties('v_pelaporan_ikal');
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
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
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
        $this->tables->set("v_pelaporan_ikal", "uid_pelaporan_ikal");
        $offset = ($offset > 0 ? $offset - 1 : 0);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        // $this->debug->show($data);
        $this->view->assign("offset", $offset+1);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="PELAPORAN_IKAL_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/ikal/index/excel.html');
        echo $html;
    }

    public function dataExcel2($w=null, $offset=null)
    {
        $offset = $this->params('offset');
        $properties = $this -> _getProperties('v_pelaporan_ikal');
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
            if ($post['form']['src_periode']) {
                $w .= " AND periode_pemantauan = " . $post['form']['src_periode'];
            }
            if ($post['form']['src_kabkota']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota'];
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
        $this->tables->set("v_pelaporan_ikal", "uid_pelaporan_ikal");
        $offset = ($offset > 0 ? $offset - 1 : 0);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        $this->view->assign("offset", $offset+1);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="VERIFIKASI_IKAL_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/ikal/verifikasi/excel.html');
        echo $html;
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
              $wLokasi .= " AND uid_provinsi IN ({$provinsiLainnya})";
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
        $rf = $this -> tables -> fetch("deleted = 0 AND peruntukan = 2");
        $this -> view -> assign("peruntukan", $rf['data']);

        $this -> tables -> set("v_lokasi_pemantauan", "uid_lokasi_pemantauan");
        $rf = $this -> tables -> fetch("deleted = 0 AND uid_rf_component = 5" . $wLokasi);
        $this -> view -> assign("lokasi", $rf['data']);
        // $this->debug->show($rf);

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

        $this -> tables -> set("v_lab", "uid");
        $rf = $this -> tables -> fetch('deleted = 0 AND verifikasi = 1');
        $this -> view -> assign("lab", $rf['data']);
    }

    public function indeks()
    {
        $post = $this -> post();
        if (isset($post['submitAllProvinsi'])){
          $post['form']['uid_indeks_provinsi_all'] = explode(",",base64_decode($post['form']['uid_indeks_provinsi_all'],TRUE));
          foreach ($post['form']['uid_indeks_provinsi_all'] as $key => $value) {
            $counting[] = $this -> _countIndeks($value);
          }
          $tahun = explode("tahun", $counting[0]);
          if (count($counting) == count($post['form']['uid_indeks_provinsi_all'])) {
              $message = "Data Indeks telah diperbaharui";
          } else {
              $message = "Data Indeks gagal diperbaharui";
          }
          $this -> view -> assign("showProv", 1);
        }
        if (isset($post['submitProvinsi'])) {
            $counting = $this->_countIndeks($post['form']['uid_indeks_ikal']);
            $tahun = explode("tahun", $counting);
            if ($counting) {
                $message = "Data Indeks " . $counting . " telah diperbaharui";
            } else {
                $message = "Data Indeks gagal diperbaharui";
            }
            $this -> view -> assign("showProv", 1);
        }
        if (isset($post['submitNasional'])) {
            $dataIndeks = $this -> tables -> query("SELECT a.* FROM indeks_ikal a WHERE a.uid_indeks_ikal=" . $post['form']['uid_indeks_ikal']);
            $tahun[1] = $dataIndeks['data'][0]['tahun'];
            $dataBobotProvinsi = $this->db->fetch("SELECT * FROM rf_provinsi_bobot WHERE deleted = 0 AND tahun=".$tahun[1]);
            if ($dataIndeks['total']) {
                $dataProvinsi = $this -> tables -> query("SELECT SUM(a.jumlah_penduduk) AS total_penduduk, SUM(a.luas_wilayah) AS total_luas_wilayah FROM rf_provinsi a");
                $sqlNasional = "SELECT a.* ,b.nama_propinsi, b.jumlah_penduduk, b.luas_wilayah, b.bobot_2023,
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
                    if($tahun[1] < 2023){
                      $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_provinsi'];

                    }elseif ($tahun[1] < 2025) {
                      // $nilai_indeks_tmp[] = $value['nilai_indeks'] * $value['bobot_2023'];
                      $idexBobotProvinsi = array_search($value['uid_provinsi'], array_column($dataBobotProvinsi['data'],'uid_provinsi'));
                      $bobotProvinsi = (is_numeric($idexBobotProvinsi) ? $dataBobotProvinsi['data'][$idexBobotProvinsi]['bobot_ikal'] : 0);
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
                $this -> tables -> set("indeks_ikal", "uid_indeks_ikal");
                $postIdx['form']['uid_indeks_ikal'] = $post['form']['uid_indeks_ikal'];
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
        $this -> cekLockSystem(3, 3.4, $this->me['uid_users']);
        $this -> view -> assign("rf_catatan",$this->ref->getRekomendasiCatatan('ikal','perhitungan')['data']);
        $this -> view -> assign("indeksActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("scrollTobtn", $post['scrollTo']);
        $this -> view -> assign("activeBtn", $post['form']['uid_indeks_ikal']);
        $this -> view -> assign("icons", '<i class="la la-line-chart"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS AIR LAUT');
        $this -> view -> display("index.html");
    }

    private function _countIndeks($uid_indeks, $jenis_indeks = 0)
    {//function for counting data pelaporan
        $dataIndeks = $this -> tables -> query("SELECT a.*, b.nama_propinsi FROM indeks_ikal a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE a.uid_indeks_ikal=" . $uid_indeks);
        if ($dataIndeks['total']) {
            $cnLokasi = $this -> _countIndeksLokasi($dataIndeks['data'][0]['uid_provinsi'], $dataIndeks['data'][0]['tahun']);
            if ($cnLokasi) {
                $cnProvinsi = $this -> _countIndeksProvinsi($dataIndeks['data'][0]['uid_provinsi'], $dataIndeks['data'][0]['tahun']);
                return "Provinsi " . $dataIndeks['data'][0]['nama_propinsi'] . " tahun " . $dataIndeks['data'][0]['tahun'];
            } else {
                $cnProvinsi = $this -> _countIndeksProvinsi($dataIndeks['data'][0]['uid_provinsi'], $dataIndeks['data'][0]['tahun']);
                return "Provinsi " . $dataIndeks['data'][0]['nama_propinsi'] . " tahun " . $dataIndeks['data'][0]['tahun'] . " <br><b>Note:</b>silahkan hitung ulang karena ada kegagalan perhitungan per lokasi";
            }
        }
    }

    private function _countIndeksLokasi($uid_provinsi, $tahun)
    {
        /*$sqlAvg = "SELECT YEAR(tanggal) AS tahun, uid_lokasi_pemantauan, AVG(tss) AS tss, AVG(do_p) AS do_p, AVG(minyak_dan_lemak) AS minyak_dan_lemak, AVG(amonia_total) AS amonia_total, AVG(orto_fosfat) AS orto_fosfat FROM v_pelaporan_ikal WHERE YEAR(tanggal) ='" . $tahun . "' AND uid_provinsi=" . $uid_provinsi . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat = 1) GROUP BY uid_lokasi_pemantauan";**/
        // $sqlAvg = "SELECT YEAR(tanggal) AS tahun, uid_lokasi_pemantauan, AVG(tss) AS tss, AVG(do_p) AS do_p, AVG(minyak_dan_lemak) AS minyak_dan_lemak, AVG(amonia_total) AS amonia_total, AVG(orto_fosfat) AS orto_fosfat FROM v_pelaporan_ikal WHERE deleted= 0 AND deleted_lokasi = 0 AND YEAR(tanggal) ='" . $tahun . "' AND uid_provinsi=" . $uid_provinsi . " AND (v_provinsi = 1 OR v_regional = 1 OR v_pusat = 1) GROUP BY uid_lokasi_pemantauan";
        $sqlAvg = "SELECT YEAR(tanggal) AS tahun, uid_lokasi_pemantauan, AVG(tss) AS tss, AVG(do_p) AS do_p, AVG(minyak_dan_lemak) AS minyak_dan_lemak, AVG(amonia_total) AS amonia_total, AVG(orto_fosfat) AS orto_fosfat FROM v_pelaporan_ikal WHERE deleted= 0 AND deleted_lokasi = 0 AND YEAR(tanggal) ='" . $tahun . "' AND uid_provinsi=" . $uid_provinsi . " AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) GROUP BY uid_lokasi_pemantauan";
        $avgData = $this -> tables -> query($sqlAvg);
        $cnPost = 0;

        $getIndeksLokasiByProvinsi = $this->db->fetch("SELECT * FROM indeks_ikal WHERE uid_lokasi > 0 AND uid_provinsi=".$uid_provinsi. " AND tahun=".$tahun);
        $inIdLokasiIndeks = array_column($getIndeksLokasiByProvinsi['data'],'uid_indeks_ikal');

        // $this->debug->show($inIdLokasiIndeks);

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

                $idxInLokasiIndeks = array_search($cekDataLocation['data'][0]['uid_indeks_ikal'], $inIdLokasiIndeks);
                if(is_numeric($idxInLokasiIndeks)){
                  unset($inIdLokasiIndeks[$idxInLokasiIndeks]);
                }
            } else {
                $postIndeks['form']['uid_provinsi'] = $uid_provinsi;
                $postIndeks['form']['uid_lokasi'] = $value['uid_lokasi_pemantauan'];
            }

            $postIndeks['form']['json_data'] = $cnIndeks['json_data'];
            $postIndeks['form']['nilai_indeks'] = $cnIndeks['wqi'];
            $postIndeks['form']['rating_indeks'] = $cnIndeks['wqr'];
            $postIndeks['form']['deleted'] = 0;
            $postIndeks['form']['tahun'] = $tahun;
            $postIndeks['form']['status_hitung'] = 1;
            $postIndeks['submit'] = true;
            $this -> tables -> set("indeks_ikal", "uid_indeks_ikal");
            if ($this -> tables -> post($postIndeks)) {
                $cnPost++;
            }
        }
        if(count($inIdLokasiIndeks)){
            $this->tables->query("UPDATE `indeks_ikal` SET `deleted` = '1' WHERE `indeks_ikal`.`uid_indeks_ikal` IN(".implode(",", $inIdLokasiIndeks).")");
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
            $postIndeks['submit'] = true;

            $this -> tables -> set("indeks_ikal", "uid_indeks_ikal");
            if ($this -> tables -> post($postIndeks)) {
                $statusUpdate = $this -> updateHistory($postIndeks['form']['nilai_indeks'], 1, $tahun, 0, $provinsi);
                if ($statusUpdate) {
                    return 1;
                } else {
                    return 0;
                }
            } else {
                return 0;
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
        $updateHistory['form']['ikal'] = ($nilai_indeks ? $nilai_indeks : 0);
        if ($jenis_indeks == 1) {
            if($uid_provinsi == 36){
              $cnIklhTrue = (0.376 * $ch['ika']) + (0.405 * $ch['iku']) + (0.219 * $ch['ikl']);
              $cnIklhFalse = (0.219 * $nilai_indeks);
              $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            }else{
              $cnIklhTrue = (0.340 * $ch['ika']) + (0.428 * $ch['iku']) + (0.133 * $ch['ikl']) + (0.099 * $nilai_indeks);
              $cnIklhFalse = (0.099 * $nilai_indeks);
              $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
            }
        // =(0,34*J3)+(0,428*I3)+(0,133*K3)+(0,099*L3)
        } elseif ($jenis_indeks == 2) {
            // IKLH = (0.340 x IKA Nasional)+(0.428 x IKU Nasional)+ (0.133 x IKL Nasional)+(0.099 x IKAL Nasional)
            $cnIklhTrue = (0.340 * $ch['ika']) + (0.428 * $ch['iku']) + (0.133 * $ch['ikl']) + (0.099 * $nilai_indeks);
            $cnIklhFalse = ($nilai_indeks);
            $updateHistory['form']['iklh'] = ($cekHistory['total'] ? $cnIklhTrue : $cnIklhFalse);
        } else {
            $cnIklhTrue = (0.376 * $ch['ika']) + (0.405 * $ch['iku']) + (0.219 * $ch['ikl']);
            $cnIklhFalse = (0.219 * $nilai_indeks);
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
    {
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
        if ($this->params('debug')==1) {
          $post['search'] = true;
          // $post['form']['tahun'] = 2020;
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
            if ($post['form']['src_peruntukan']) {
                $w .= " AND uid_rf_peruntukan =" . $post['form']['src_peruntukan'];
            }
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
        // $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi FROM indeks_ikal a
				// 			        LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
				// 			       WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
        $sql = 'SELECT a.*, b.nama_propinsi AS nama_provinsi ,d.ikal AS target
                FROM indeks_ikal a
				        LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
                LEFT JOIN rf_target_iklh d ON d.uid_provinsi = a.uid_provinsi AND d.uid_kabkota = 0 AND d.tahun = a.tahun AND d.deleted = 0
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

        if ($this->params('debug')==1) {
          $this->debug->show($data);
        }

        $provinsi = $this -> tables -> query('SELECT a.*, b.nama_propinsi AS nama_provinsi FROM indeks_ikal a LEFT JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE jenis_indeks=1 AND ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);

        if ($this->params("ex") == "provinsi") {
            foreach ($provinsi['data'] as $key => $value) {
                $sql = $this -> tables -> query("SELECT a.*, b.alamat, b.alamat_detail, b.uid_kabkota, c.nama_kabkot FROM indeks_ikal a
	                  LEFT JOIN lokasi_pemantauan b ON b.uid_lokasi_pemantauan = a.uid_lokasi
	                  LEFT JOIN rf_kabkota c ON c.kd_kota = b.uid_kabkota
	                  WHERE b.deleted = 0 AND a.deleted = 0 AND a.uid_lokasi > 0 AND a.uid_provinsi = ".$value['uid_provinsi']." AND a.tahun=".$value['tahun']."
	                  ORDER BY b.uid_lokasi_pemantauan ASC
	                  ");
                    $provinsi['detail'][$key] = $sql;
            }
        }
        if ($this->params("ex") == "kabkota") {
            $this->expExcel($kabkota, null);
        } elseif ($this->params("ex") == "provinsi") {
            $this->expExcel(null, $provinsi);
        }

        $this -> view -> assign("viewp_idx", base64_encode(implode(",",array_column($provinsi["data"],"uid_indeks_ikal"))));
    }

    public function verifikasi()
    {//index verification menu
        $this -> getData();
        $this -> rfData();
        $this -> cekLockSystem(2, 2.4, $this->me['uid_users']);
        $this->view->assign("rf_catatan",$this->ref->getRekomendasiCatatan('ikal','verifikasi')['data']);
        $this -> view -> assign("verifikasiActive", "active");
        $this -> view -> assign("show", $show);
        $this -> view -> assign("message", $message);
        $this -> view -> assign("icons", '<i class="la la-line-chart"></i>');
        $this -> view -> assign("title", 'INDEKS KUALITAS LAUT');
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
            $dataUpdate['form']['uid_pelaporan_ikal'] = $dataRequest['uid'];
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
            $this->tables->set('pelaporan_ikal', 'uid_pelaporan_ikal');
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
            $this -> tables -> set("pelaporan_ikal", "uid_pelaporan_ikal");
            $post['form']['uid_pelaporan_ikal'] = $uid;
            // $post['form'][$field] = ($act == 1 ? 1 : 0);
            $post['form'][$field] = $act == 'un' ? 0 : $act;
            $post['form'][$field.'_date'] = date("Y-m-d H:i:s");
            $post['form']['v_reject_status'] = 0;
            $post['submit'] = true;
            if ($this -> tables -> post($post)) {
                $this -> tables -> set("v_pelaporan_ikal", "uid_pelaporan_ikal");
                $dataLokasi = $this -> tables -> fetch("uid_pelaporan_ikal=" . $uid);
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
        $cekDataProvinsi = $this -> tables -> query("SELECT uid_indeks_ikal FROM indeks_ikal WHERE deleted = 0 AND uid_lokasi =0 AND jenis_indeks = 1 AND uid_provinsi=" . $uid_provinsi . " AND tahun=" . $tahun);
        if (!$cekDataProvinsi['total']) {
            $this -> tables -> set("indeks_ikal", "uid_indeks_ikal");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = $uid_provinsi;
            $postIdx['form']['uid_kabkota'] = 0;
            $postIdx['form']['uid_lokasi'] = 0;
            $postIdx['form']['jenis_indeks'] = 1;
            $postIdx['submit'] = true;
            $this -> tables -> post($postIdx);
        }
        $cekDataNasional = $this -> tables -> query("SELECT uid_indeks_ikal FROM indeks_ikal WHERE deleted = 0 AND uid_lokasi =0 AND jenis_indeks = 2 AND tahun=" . $tahun);
        if (!$cekDataNasional['total']) {
            $this -> tables -> set("indeks_ikal", "uid_indeks_ikal");
            $postIdx['form']['tahun'] = $tahun;
            $postIdx['form']['uid_provinsi'] = 0;
            $postIdx['form']['uid_kabkota'] = 0;
            $postIdx['form']['uid_lokasi'] = 0;
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
