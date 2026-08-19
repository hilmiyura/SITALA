<?php
	/**
	 * created at 	: 01/10/2020
	 * created by 	: dasendria team
	 * desc		  	: controller Lokasi Pemantauan IKLHK
	 *
	 */
    class lokasiPemantauanController extends Front{
    		public function init() {
    			($this->session->get('memberIKLH')?:$this->redirect("login"));

	    		//SET CUSTOM VIEWS FOLDER
	    		$this->view->setFolder('be');

	    		//LOAD MODELS
	    		$this->loadModel("tables");
	    		$this->loadModel("ref");
			$this->loadModel("users");

      // LOAD FUNCTION
      require_once "functions.php";
      $this -> functions = new functions();
      $this -> view -> assign("functions", $this -> functions);
      require_once "excelReader.php";

			//GLOBAL VAR
			$this->me 			= $this->session->get('memberIKLH');
			$this->ctrl 			= $this->uri->getController();
			$this->act 			= $this->uri->getAction();
			$this->url			= $this->ctrl . '/' . $this->act;

			//ASSIGN VAR
			$this->view->assign("now",$this->now = date('Y-m-d'));
			$this->view->assign("me",$this->me);
			$this->view->assign("baseUrl",BASEURL);
			$this->view->assign("ctrl", $this->ctrl);
			$this->view->assign("act", $this->act);
			$this->view->assign("format",$this->format);
			$this->view->assign("time",time());
			$this->view->assign("thisYear", date('Y'));
			$this->view->assign("assets",ASSETS);

			$this->view->assign("primaryKey", "uid_lokasi_pemantauan");
      // $this->viewName 		= "v_lokasi_pemantauan";
      $this->viewName 		= "v_lokasi_pemantauan_new";
      $this->viewNameMaps = "lokasi_pemantauan";
			$this->primaryKey	= "uid_lokasi_pemantauan";
			$this->where			= "deleted = 0";

      $this->dev = "";
      if($_SERVER['REMOTE_ADDR'] == '103.144.175.182'){
        $this->dev = 1;
      }
      $this->view->assign("dev", $this->dev);

      $this->view->assign("aksesAdmin", $this->ref->aksesAdmin($this->me));

		}
		//INDEX FUNCTION IS A DEFAULT ACTION
		public function index(){
      // die("under maintenance");
      $uploadexcel = 0;
      if ($this->params('uploadexcel')) {
        $uploadexcel = $this->params('uploadexcel');
      }
      $this->view->assign('uploadexcel', $uploadexcel);

			$post = $this->post();
			if(isset($post['submit'])){
				if(!$post['form'][$this->primaryKey]){
					$post['form']['cruser'] = intval($this->me['uid_users']);
				}
        $post['form']['latitude'] = str_replace(",",".",trim($post['form']['latitude']));
        $post['form']['longitude'] = str_replace(",",".",trim($post['form']['longitude']));
        $post['form']['tahun'] = implode(",",$post['form']['tahun']);
        $post['form']['alamat'] = strip_tags($post['form']['alamat']);
        $post['form']['nama_perusahaan'] = ($post['form']['nama_perusahaan'] ? strip_tags($post['form']['nama_perusahaan']) : null);
        $post['form']['alamat_detail'] = strip_tags($post['form']['alamat_detail']);
        $tahun = $post['form']['tahun'];
        $uid_lokasi_pemantauan = $post['form']['uid_lokasi_pemantauan'];
        $uid_component = $post['form']['uid_rf_component'];
        $uid_pelaksana = $post['form']['uid_rf_pelaksana'];
        $uid_provinsi = $post['form']['uid_provinsi'];
        $uid_kabkota = $post['form']['uid_kabkota'];
        $this->tables->set("rf_component","uid_rf_componet");
  			$rf_component = $this->tables->fetch("deleted = 0 AND uid_rf_component =".$uid_component);
        $this->tables->set("rf_pelaksana","uid_rf_pelaksana");
  			$rf_pelaksana = $this->tables->fetch("deleted = 0 AND uid_rf_pelaksana =".$uid_pelaksana);
        $this->tables->set("rf_provinsi","kd_propinsi");
  			$rf_provinsi = $this->tables->fetch("kd_propinsi =".$uid_provinsi);
        $this->tables->set("rf_kabkota","kd_kota");
  			$rf_kabkota = $this->tables->fetch("deleted=0 AND kd_kota =".$uid_kabkota);
        $kode_component = $rf_component['data'][0]['kode'];
        $kode_pelaksana = $rf_pelaksana['data'][0]['kode'];
        $kode_provinsi = $rf_provinsi['data'][0]['kode'];
        $kode_kabkota = $rf_kabkota['data'][0]['kode'];
        if ($uid_lokasi_pemantauan == "") {
          // $cek = $this -> tables -> query("SELECT kode_lokasi FROM lokasi_pemantauan WHERE deleted = 0 AND uid_rf_component=".$uid_component." AND uid_rf_pelaksana=".$uid_pelaksana." AND tahun LIKE '%".$tahun."%' AND uid_provinsi = ".$uid_provinsi." AND uid_kabkota = ".$uid_kabkota." ORDER BY LENGTH(kode_lokasi), kode_lokasi DESC LIMIT 1");
          $cek = $this -> tables -> query("SELECT kode_lokasi FROM lokasi_pemantauan WHERE deleted = 0 AND uid_rf_component=".$uid_component." AND uid_rf_pelaksana=".$uid_pelaksana." AND uid_provinsi = ".$uid_provinsi." AND uid_kabkota = ".$uid_kabkota." ORDER BY LENGTH(kode_lokasi), kode_lokasi DESC LIMIT 1");
          // $this->debug->show($cek);
          if ($cek['total']) {
            $code = explode("-",$cek['data'][0]['kode_lokasi']);
            $code_explode = (integer) $code[3];
            $counter = $code_explode + 1;
            if (strlen($counter) == 2) {
              $counter = "0".$counter;
            }elseif (strlen($counter) == 1) {
              $counter = "00".$counter;
            }else {
              $counter = $counter;
            }
            $post['form']['kode_lokasi'] = $kode_component."".$kode_pelaksana."-".$kode_provinsi."-".$kode_kabkota."-".$counter;
          }else {
            $post['form']['kode_lokasi'] = $kode_component."".$kode_pelaksana."-".$kode_provinsi."-".$kode_kabkota."-001";
          }
        } else {
          $post['form']['kode_lokasi'] = $post['form']['kode_lokasi'];
        }
				$this->tables->set("lokasi_pemantauan","uid_lokasi_pemantauan");
				if($this->tables->post($post)){
					$message = "Berhasil menyimpan data !";
				}else{
					$message = "Gagal menyimpan data !";
				}
			}
      if (isset($post['submit-excel'])) {
          $post['form']['cruser'] = $this -> me['uid_users'];
          $val = $_FILES['file_excel'];
          $ext = strtolower(strrchr($val['name'], "."));
          if ($ext == ".xls") {
              $files = $this -> functions -> uploadFile($_FILES['file_excel']);
          }
          // $this->debug->show($files);
          if ($files) {
              $excelReader = new Spreadsheet_Excel_Reader(UPLOADFOLDER . "docs/" . $files, true);
              $rows = $excelReader -> rowcount(0);
              for ($c = 1; $c <= 8; $c++) {
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
              $tmpKodeLokasi = NULL;
              foreach ($data as $key => $vals) {
                  if ($vals[1] != "-") {
                      $postLokasi['form']['uid_lokasi_pemantauan'] = "";
                      $postLokasi['form']['cruser'] = $post['form']['cruser'];
                      $postLokasi['form']['kode_lokasi'] = $vals[1];
                      $kode_lokasi_2 = explode("-",$vals[1]);
                      $cek_provinsi = $this -> tables -> query("SELECT * FROM rf_provinsi WHERE kode='".$kode_lokasi_2[1]."'");
                      $postLokasi['form']['uid_provinsi'] = $cek_provinsi['data'][0]['kd_propinsi'];
                      $cek_kabkota = $this -> tables -> query("SELECT * FROM rf_kabkota WHERE deleted=0 AND kode=".$kode_lokasi_2[2]." AND kd_provinsi=".$cek_provinsi['data'][0]['kd_propinsi']);
                      $postLokasi['form']['uid_kabkota'] = $cek_kabkota['data'][0]['kd_kota'];
                      $postLokasi['form']['alamat'] = strip_tags($vals[2]);
                      $postLokasi['form']['alamat_detail'] = strip_tags($vals[3]);
                      $postLokasi['form']['tahun'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[4]);
                      // $postLokasi['form']['uid_provinsi'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[5]);
                      // $postLokasi['form']['uid_kabkota'] = preg_replace('~[\\\\/:*?"<>|]~', "", $vals[6]);
                      // $postLokasi['form']['uid_provinsi'] = $this -> cekProvinsi($vals[5]);
                      // $postLokasi['form']['uid_kabkota'] = $this -> cekKabkota($vals[6]);
                      $postLokasi['form']['latitude'] = str_replace(",", ".", trim($vals[5]));
                      $postLokasi['form']['longitude'] = str_replace(",", ".", trim($vals[6]));
                      $postLokasi['form']['uid_rf_component'] = $this -> cekComponent($vals[7]);
                      $postLokasi['form']['uid_rf_pelaksana'] = $this -> cekPelaksana($vals[8]);
                      $postLokasi['submit'] = true;
                      $kode_lokasi_3 = $vals[1];
                      $cek_kode_lokasi = $this -> tables -> query("SELECT * FROM lokasi_pemantauan WHERE deleted = 0 AND tahun LIKE ".$vals[4]." AND kode_lokasi='".$kode_lokasi_3."'");
                      // $this->debug->show($cek_kode_lokasi);
                      if ($cek_kode_lokasi['total'] != "") {
                        $tmpKodeLokasi[] = $postLokasi['form']['kode_lokasi'];
                      }else {
                        $this -> tables -> set("lokasi_pemantauan", "uid_lokasi_pemantauan");
                        $this -> tables -> post($postLokasi);
                      }
                  }
                  // $this->debug->show($tmpKodeLokasi);
                  if ($tmpKodeLokasi) {
                    $message = "Lokasi pemantauan dengan kode lokasi ".implode(', ', $tmpKodeLokasi)." tidak tersimpan oleh sistem dikarenakan kode lokasi tersebut sudah ada. Selain dari kode lokasi tersebut data berhasil disimpan";
                  }else {
                    if (count($data) == $tmpCn) {
                        $message = "Berhasil menyimpan data !";
                    } else {
                        $message = "Gagal menyimpan data !";
                    }
                  }
                  $tmpCn++;
              }
          }
      }

			$this->getData();
			$this->rfData();
			$this->view->assign("masterActive","active");
			$this->view->assign("show",$show);
			$this->view->assign("message",$message);
			$this->view->assign("icons",'<i class="la la-map-marker"></i>');
			$this->view->assign("title",'Lokasi Pemantauan');
			$this->view->display("index.html");
		}

    private function cekProvinsi($nama)
    {// function for cek peruntukan iku
        $cek = $this -> tables -> query("SELECT * FROM rf_provinsi WHERE nama_propinsi='" . $nama . "'");
        if ($cek['total']) {
            return $cek['data'][0]['kd_propinsi'];
        } else {
            $postProvinsi['form']['nama_propinsi'] = $nama;
            $postProvinsi['submit'] = true;
            $this -> tables -> set("rf_provinsi", "kd_propinsi");
            if ($this -> tables -> post($postProvinsi)) {
                return $this -> tables -> lastInsertID();
            }
        }
    }

    private function cekKabkota($nama)
    {// function for cek peruntukan iku
        $cek = $this -> tables -> query("SELECT * FROM rf_kabkota WHERE deleted=0 AND nama_kabkot='" . $nama . "'");
        if ($cek['total']) {
            return $cek['data'][0]['kd_kota'];
        } else {
            $postKabkota['form']['nama_kabkot'] = $nama;
            $postKabkota['submit'] = true;
            $this -> tables -> set("rf_kabkota", "kd_kota");
            if ($this -> tables -> post($postKabkota)) {
                return $this -> tables -> lastInsertID();
            }
        }
    }

    private function cekComponent($nama)
    {// function for cek peruntukan iku
        $cek = $this -> tables -> query("SELECT * FROM rf_component WHERE deleted=0 AND name='" . $nama . "'");
        if ($cek['total']) {
            return $cek['data'][0]['uid_rf_component'];
        } else {
            $postComponent['form']['name'] = $nama;
            $postComponent['submit'] = true;
            $this -> tables -> set("rf_component", "uid_rf_component");
            if ($this -> tables -> post($postComponent)) {
                return $this -> tables -> lastInsertID();
            }
        }
    }

    private function cekPelaksana($nama)
    {// function for cek peruntukan iku
        $cek = $this -> tables -> query("SELECT * FROM rf_pelaksana WHERE deleted=0 AND name='" . $nama . "'");
        if ($cek['total']) {
            return $cek['data'][0]['uid_rf_pelaksana'];
        } else {
            $postPelaksana['form']['name'] = $nama;
            $postPelaksana['submit'] = true;
            $this -> tables -> set("rf_pelaksana", "uid_rf_pelaksana");
            if ($this -> tables -> post($postPelaksana)) {
                return $this -> tables -> lastInsertID();
            }
        }
    }

		private function getData(){
      $post 		= $this->post();
      $post['form']['tahun'] = (isset($post['form']['tahun']) ? $post['form']['tahun'] : ACTIVE_YEAR);
      // $post['form']['uid_rf_component'] = (isset($post['form']['uid_rf_component']) ? $post['form']['uid_rf_component'] : 1);
      if($this->params('search')){
				$post['search'] = TRUE;
				$post['form'] 	= json_decode(urldecode($this->params('search')),1);
			}
			$this->tables->set($this->viewName,$this->primaryKey);
			$properties	= $this->_getProperties($this->viewName);
			$urlVar  	= BASEURL . $this->url . '/';
			$w 			= $this->where;
      if($this->me['role_user'] == 3){
        $w .=" AND uid_kabkota =".$this->me['uid_kabkota'];
        $getIdRegional = $this->db->fetch("SELECT kd_regional FROM rf_provinsi WHERE kd_propinsi=".$this->me['uid_provinsi'])["data"][0]["kd_regional"];
        $post["form"]["src_reg"] = $getIdRegional;
        $post["form"]["src_prop"] = $this->me['uid_provinsi'];
        // $post['search'] = 1;
        // $post['form']['uid_rf_pelaksana'] = 4;
      }elseif ($this->me['role_user'] == 2) {
        $w .=" AND uid_provinsi =".$this->me['uid_provinsi'];
        $post["form"]["src_prop"] = $this->me['uid_provinsi'];
        // $post['search'] = 1;
        // $post['form']['uid_rf_pelaksana'] = 3;
      }elseif ($this->me['role_user'] == 4 || $this -> me['role_user'] == 5) {
        $w .=" AND kd_regional =".$this->me['uid_regional'];
        $post["form"]["src_reg"] = $this->me['uid_regional'];

        if($this->me["uid_provinsi_lainnya"]){
          $provinsiLainnya = $this->me["uid_provinsi_lainnya"];
          $w .= " AND uid_provinsi IN ({$provinsiLainnya})";
        }
        // $post['search'] = 1;
        // $post['form']['uid_rf_pelaksana'] = 2;
      }

      // elseif ($this->me['role_user'] == 2) {
      //   $w .=" AND uid_provinsi =".$this->me['uid_provinsi']." AND uid_rf_pelaksana=3";
      //   $post['search'] = 1;
      //   $post['form']['uid_rf_pelaksana'] = 3;
      // }elseif ($this->me['role_user'] == 4) {
      //   $w .=" AND kd_regional =".$this->me['uid_regional']." AND uid_rf_pelaksana=2";
      //   $post['search'] = 1;
      //   $post['form']['uid_rf_pelaksana'] = 2;
      // }
			// $o 			= $this->primaryKey . " ASC";
			$o 			  = " uid_rf_component ASC";
			if(isset($post['search'])){
        $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
				if($post['form']['keyword']){
					if($properties['total']){
						$w .= " AND ";
						$w .= "(";
						for($i=5;$i<$properties['total'];$i++){
							$w .= $properties['data'][$i] . " LIKE '%".$post['form']['keyword']."%' OR ";
						}
						$w .= $properties['data'][$properties['total']-1] . " LIKE '%".$post['form']['keyword']."%' ";
						$w .= ")";
					}
				}
        if ($post['form']['uid_rf_component']) {
          $w .= " AND uid_rf_component =".$post['form']['uid_rf_component'];
        }
        if ($post['form']['src_reg']) {
          $w .= " AND kd_regional =".$post['form']['src_reg'];
        }
        if ($post['form']['src_prop']) {
          $w .= " AND uid_provinsi =".$post['form']['src_prop'];
        }
        if ($post['form']['src_kabkota2']) {
          $w .= " AND uid_kabkota =".$post['form']['src_kabkota2'];
        }
        if ($post['form']['uid_rf_pelaksana']) {
          $w .= " AND uid_rf_pelaksana =".$post['form']['uid_rf_pelaksana'];
        }
        if ($post['form']['nama_perusahaan'] == 1) {
          $w .= " AND nama_perusahaan IS NOT NULL AND nama_perusahaan != ''";
        }
        if ($post['form']['nama_perusahaan'] == 2) {
          $w .= " AND nama_perusahaan IS NULL AND nama_perusahaan = ''";
        }

        if ($post['form']['uid_rf_sumber_pencemar']) {
          $w .= " AND uid_rf_sumber_pencemar =".$post['form']['uid_rf_sumber_pencemar'];
        }
        if ($post['form']['status_lokasi']) {
          if($post['form']['status_lokasi'] == 1){
            $w .= " AND digunakan >0";
          }else{
            $w .= " AND (digunakan IS NULL OR digunakan <= 0 OR digunakan = '')";
          }
          $this->viewNameMaps = $this->viewName;
        }
        if ($post['form']['tahun']) {
          // if($post['form']['tahun']==2021){
          //   $w .= " AND (tahun LIKE '%".$post['form']['tahun']."%' OR tahun is NULL)";
          // }else{
          //   $w .= " AND tahun LIKE '%".$post['form']['tahun']."%'";
          // }
          $w .= " AND tahun LIKE '%".$post['form']['tahun']."%'";
        }
				$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
				$this->view->assign("search",$post['form']);
        $search_json = urlencode(json_encode($post['form']));
        $this->view->assign("search_json", $search_json);
			} else {
        $w .= " AND tahun LIKE '%" . ACTIVE_YEAR . "%'";
        // $w .= " AND uid_rf_component =2";
        // $w .= " AND uid_rf_pelaksana =4";
        // if($properties['total']){
        //   $w .= " AND ";
        //   $w .= "(";
        //   for($i=5;$i<$properties['total'];$i++){
        //     $w .= $properties['data'][$i] . " LIKE '%lampung%' OR ";
        //   }
        //   $w .= $properties['data'][$properties['total']-1] . " LIKE '%lampung%' ";
        //   $w .= ")";
        // }

          $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
  				$this->view->assign("search",$post['form']);
          $search_json = urlencode(json_encode($post['form']));
          $this->view->assign("search_json", $search_json);
      }

      //src ref
      if($post['form']['src_reg']){
        $this -> view -> assign("provinsiSelect2", $this->db->fetch("SELECT * FROM rf_provinsi WHERE kd_regional=".$post['form']['src_reg'])["data"]);
      }
      if($post['form']['src_prop']){
        $this -> view -> assign("kabkotaSelect2", $this->db->fetch("SELECT * FROM rf_kabkota WHERE deleted = 0 AND kd_provinsi=".$post['form']['src_prop'])["data"]);
      }

      // $this->debug->show($post['form']);
			//PAGING
			$offset   	= (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
			$limit	  	= LIMIT;
      $data	  	= $this->tables->query('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
      // $data2	  	= $this->tables->query('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o);
			// $data2	  	= $this->tables->query('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o);
			$All	  		= $this->db->query('SELECT count('.$this->primaryKey.') as x FROM '.$this->viewName.' WHERE '. $w);
			$totalRow 	= (isset($All->fields['x']) ? $All->fields['x'] : 0);

			$this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
      $listExport = $this->_getListExport($totalRow);
      $this->view->assign("listExport", $listExport);
			$this->view->assign("urlVar", $urlVar);
			$this->view->assign("totalRow", $totalRow);
			$this->view->assign("limit", $limit);
			$this->view->assign("page", $offset);
      $this->view->assign("view",$data['data']);
      // $this->debug->show($data['data']);
			// $this->view->assign("viewMap",json_encode($data2['data']));
      // if($post['form']['keyword']=='lampung'){
      //   $this->debug->show($data2['data'][82]);
      //   for($i=0;$i<$data2['total'];$i++){
      //     echo $i . '<br>';
      //     echo json_encode($data2['data'][$i]);
      //     echo '<br><hr><br/>';
      //   }
      //   $this->debug->show(json_encode($data2['data'][6]));
      // }
		}

		public function editData(){
			header("Content-Type: application/json; charset=UTF-8");
			if($this->params("x")){
				$this->tables->set("v_lokasi_pemantauan_new","uid_lokasi_pemantauan");
				$dataEdit = $this->tables->fetch("deleted = 0 AND uid_lokasi_pemantauan=".$this->params("x"));
        if($dataEdit['total']){
          $dataEdit['data'][0]['tahun'] = explode(",",$dataEdit['data'][0]['tahun']);
        }
				echo json_encode($dataEdit['data'][0]);
			}
		}

    private function _getListExport($totalRow)
    {
        $numList 	= round($totalRow/LIMIT_DOWNLOAD_EXCEL);
        $numListRes = $numList * LIMIT_DOWNLOAD_EXCEL;
        if ($totalRow >= $numListRes) {
            $numList += 1;
        }

        $itemCount	= 1;
        $limitCount	= LIMIT_DOWNLOAD_EXCEL;
        for ($itemCount; $itemCount<=$numList; $itemCount++) {
            if ($itemCount == 1) {
                $listExport[$itemCount]['offset_start'] = 0;
            } else {
                $listExport[$itemCount]['offset_start'] = ($limitCount - LIMIT_DOWNLOAD_EXCEL) + 1;
            }

            if ($limitCount >= $totalRow) {
                $listExport[$itemCount]['offset_end'] = $totalRow;
            } else {
                $listExport[$itemCount]['offset_end'] = $limitCount;
            }

            $limitCount += LIMIT_DOWNLOAD_EXCEL;
        }

        return $listExport;
    }

    public function dataExcel($w=null, $offset=null)
    {
        $offset = $this->params('offset');
        $properties = $this -> _getProperties('v_lokasi_pemantauan');
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
        $o = " uid_rf_component ASC";
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
            if ($post['form']['uid_rf_component']) {
              $w .= " AND uid_rf_component =".$post['form']['uid_rf_component'];
            }
            if ($post['form']['src_reg']) {
              $w .= " AND kd_regional =".$post['form']['src_reg'];
            }
            if ($post['form']['src_prop']) {
              $w .= " AND uid_provinsi =".$post['form']['src_prop'];
            }
            if ($post['form']['src_kabkota2']) {
              $w .= " AND uid_kabkota =".$post['form']['src_kabkota2'];
            }
            if ($post['form']['uid_rf_pelaksana']) {
              $w .= " AND uid_rf_pelaksana =".$post['form']['uid_rf_pelaksana'];
            }
            if ($post['form']['uid_rf_sumber_pencemar']) {
              $w .= " AND uid_rf_sumber_pencemar =".$post['form']['uid_rf_sumber_pencemar'];
            }
            if ($post['form']['tahun']) {
              $w .= " AND tahun LIKE '%".$post['form']['tahun']."%'";
            }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this->view->assign("search",$post['form']);
            $search_json = urlencode(json_encode($post['form']));
            $this->view->assign("search_json", $search_json);
        } else {
            $w .= " AND tahun LIKE '%" . ACTIVE_YEAR . "%'";
            $post['form']['tahun'] = ACTIVE_YEAR;

            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
    				$this->view->assign("search",$post['form']);
            $search_json = urlencode(json_encode($post['form']));
            $this->view->assign("search_json", $search_json);
        }
        $this->tables->set("v_lokasi_pemantauan", "uid_lokasi_pemantauan");
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        // $this->debug->show($data);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="LOKASI_PEMANTAUAN_'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/lokasiPemantauan/index/excel.html');
        echo $html;
    }

    public function test(){
      // $cnCek = $this->tables->query("SELECT COUNT(uid_pelaporan_iku) AS total FROM pelaporan_iku WHERE deleted = 0 AND uid_lokasi_pemantauan = 1");
      // $cekLapor = ($cnCek['data'][0]['total']> 0 ? 1 :0);
      // $this->debug->show($cekLapor);
    }

    public function setSungai(){
      $uid_lokasi_pemantauan = $this->params("x");
      $uid_sungai = $this->params("s");
      if($uid_lokasi_pemantauan){
        $post['form']['uid_lokasi_pemantauan'] = $uid_lokasi_pemantauan;
        $post['form']['uid_sungai'] = ($uid_sungai ? $uid_sungai : 0);
        $post['submit'] = TRUE;
        $this->tables->set("lokasi_pemantauan","uid_lokasi_pemantauan");
        $returnPost = $this->tables->post($post);
        if($returnPost){
          echo json_encode(array('statusCode' => 200, 'message' => 'Berhasil diupdate'));
        }else{
          echo json_encode(array('statusCode' => 400, 'message' => 'Gagal diupdate'));
        }
      }else{
        echo json_encode(array('statusCode' => 404, 'message' => 'Id tidak valid'));
      }
    }

		public function deletedData(){
			$post = $this->post();
			if(isset($post['x']) && isset($post['c'])){
        $cekLapor = 0;
				$this->tables->set("lokasi_pemantauan","uid_lokasi_pemantauan");
        if($post['c'] == 1){
          $cnCek = $this->tables->query("SELECT COUNT(uid_pelaporan_iku) AS total FROM pelaporan_iku WHERE deleted = 0 AND uid_lokasi_pemantauan = ".$post['x']);
          $cekLapor = ($cnCek['data'][0]['total']> 0 ? 1 :0);
        }elseif($post['c'] == 2) {
          $cnCek = $this->tables->query("SELECT COUNT(uid_pelaporan_ika) AS total FROM pelaporan_ika WHERE deleted = 0 AND uid_lokasi_pemantauan = ".$post['x']);
          $cekLapor = ($cnCek['data'][0]['total']> 0 ? 1 :0);
        }elseif($post['c'] == 5) {
          $cnCek = $this->tables->query("SELECT COUNT(uid_pelaporan_ikal) AS total FROM pelaporan_ikal WHERE deleted = 0 AND uid_lokasi_pemantauan = ".$post['x']);
          $cekLapor = ($cnCek['data'][0]['total']> 0 ? 1 :0);
        }
        if($cekLapor == 0){
          if($this->tables->softDelete($post['x'])){
            echo json_encode(array('statusCode' => 200, 'message' => $this->message->delete('success')));
          }else{
            echo json_encode(array('statusCode' => 400, 'message' => $this->message->delete('failed')));
          }
        }else{
          echo json_encode(array('statusCode' => 400, 'message' => "Titik ini sudah digunakan untuk pelaporan"));
        }
			}else{
				echo json_encode(array('statusCode' => 403, 'message' => $this->message->access()));
			}
		}

    public function copyData(){
      $komponen = $this->params("k");
      $dariTahun = $this->params("dt");
      $keTahun = $this->params("kt");
      $this->tables->set("lokasi_pemantauan","uid_lokasi_pemantauan");
      $w = "deleted = 0 AND uid_rf_component =".$komponen." AND tahun LIKE '%".$dariTahun."%' AND tahun NOT LIKE '%".$keTahun."%' ";
      $data = $this->tables->fetch($w);
      $failed = null;
      foreach ($data['data'] as $key => $value) {
        $update[$key]['form']['tahun'] = ($value['tahun'] ? $value['tahun'].','.$keTahun : $keTahun);
        $update[$key]['form']['uid_lokasi_pemantauan'] = $value['uid_lokasi_pemantauan'];
        $update[$key]['submit'] = TRUE;
        if(!$this->tables->post($update[$key])){
          $failed[] = $value['kode_lokasi'];
        }
      }
      if($failed){
        echo json_encode(array('statusCode' => 400, 'message' => 'kode lokasi gagal dicopy '.implode(",",$failed) ));
      }else{
        echo json_encode(array('statusCode' => 200, 'message' => 'success'));
      }
    }


		private function rfData(){
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

      $this->tables->set("rf_pelaksana","uid_rf_pelaksana");
			$rf = $this->tables->fetch("deleted = 0");
			$this->view->assign("pelaksana",$rf['data']);

      $this -> tables -> set("rf_provinsi", "kd_propinsi");
      $rf = $this -> tables -> fetch($wProvinsi);
      $this -> view -> assign("provinsiSelect2", $rf['data']);

      $this -> tables -> set("rf_kabkota", "kd_kota");
      $rf = $this -> tables -> fetch('deleted = 0');
      $this -> view -> assign("kabkotaSelect2", $rf['data']);

      if ($this->me['role_user']==2) {
          $this -> tables -> set("rf_kabkota", "kd_kota");
          $rf = $this -> tables -> fetch('deleted=0 AND kd_provinsi='.$this -> me['uid_provinsi']);
          $this -> view -> assign("kabkotaSelect", $rf['data']);
      }
      if ($this->me['role_user']==4 || $this->me['role_user']==5) {
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

      // $this -> tables -> set("rf_regional", "kd_regional");
      // $rf = $regSelect = $this -> tables -> fetch($wRegional);
      // $this -> view -> assign("regional", $rf['data']);
      //
      // $this->tables->set("rf_pelaksana","uid_rf_pelaksana");
			// $rf = $this->tables->fetch("deleted = 0");
			// $this->view->assign("pelaksana",$rf['data']);
      //
      // $this->tables->set("rf_provinsi","kd_propinsi");
			// $rf = $this->tables->fetch("");
			// $this->view->assign("provinsi",$rf['data']);
      //
      // $this->tables->set("rf_kabkota","kd_kota");
			// $rf = $this->tables->fetch("deleted = 0");
			// $this->view->assign("kabkota",$rf['data']);

      $this->tables->set("rf_component","uid_rf_componet");
      $rf = $this->tables->fetch("deleted = 0 AND uid_rf_component NOT IN(3,4)");
      $this->view->assign("komponen",$rf['data']);

      $this->tables->set("rf_sumber_pencemar","uid");
      $rf = $this->tables->fetch("deleted = 0");
      $this->view->assign("sumber_pencemar",$rf['data']);

      // $rf = $this->db->fetch("SELECT uid,nama, nama_das, kode_das, nama_provinsi, nama_kabkota FROM v_sungai WHERE deleted = 0");
      // $this->view->assign("sungai",$rf['data']);
      // $this->tables->set("v_sungai","uid");
      // $rf = $this->tables->fetch("deleted = 0");

		}

		private function _getProperties($model){
			$sql = "SHOW COLUMNS FROM ".$model;
			$result = $this->db->fetch($sql);
			//$this->debug->show($result);
			if($result['total']){
				$data = array();
				foreach($result['data'] as $key=>$val){
					$data[$key] = $val['Field'];
				}
				$result['data'] = $data;
				return $result;
			}else{
				die('Coloums of table '. $model .' not found');
			}
		}


 	}
?>
