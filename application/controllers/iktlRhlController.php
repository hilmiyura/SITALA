<?php

/**
 * created at : 22/02/2024
 * created by : Dasendria team
 * desc : controller for ikl rhl
 */
class iktlRhlController extends Front {
	public function init() {
		($this -> session -> get('memberIKLH') ? : $this -> redirect("login"));

		//SET CUSTOM VIEWS FOLDER
		$this -> view -> setFolder('be');

		//LOAD MODELS
		$this -> loadModel("tables");
		$this -> loadModel("ref");
		$this -> loadModel("users");

		//GLOBAL VAR
		$this -> me = $this -> session -> get('memberIKLH');
		$this -> ctrl = $this -> uri -> getController();
		$this -> act = $this -> uri -> getAction();
		$this -> url = $this -> ctrl . '/' . $this -> act;

		//load function
		require_once "functions.php";
		$this -> functions = new functions();
		$this -> view -> assign("functions", $this -> functions);
		require_once "excelReader.php";

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

		$this -> view -> assign("primaryKey", "pelaporan_iktl_rhl_uid");
		$this -> viewName = "pelaporan_iktl_rhl";
		$this -> primaryKey = "pelaporan_iktl_rhl_uid";
		$this -> where = "deleted = 0";

		if($_SERVER['REMOTE_ADDR'] =='180.251.181.25') {
			$this->view->assign("dev", 1);
		}
	}

	public function index() {

    $statusPost = $this->postData();

    if($statusPost){
      $message = $statusPost;
    }
		$refJenisRhl = $this->tables->query("SELECT * FROM rf_jenis_rhl WHERE deleted = 0")['data'];
    $this->view->assign("jenisRhl",$refJenisRhl);
    $this->getData();


		$dataPelaporan = $this->tables->query("SELECT * FROM v_pelaporan_iktl WHERE deleted = 0 AND uid_pelaporan_iktl=".base64_decode($this->params("x")));
		// $this->debug->show($dataPelaporan);
		$textInfoPelaporan = "";
		if($dataPelaporan['total']){
			$this->yearActive = date("Y", strtotime($dataPelaporan['data'][0]['tanggal']));
			$dataPelaporan['data'][0]['verify_status_reject'] = ($dataPelaporan['data'][0]['v_pusat'] == 2 ? 1 : ($dataPelaporan['data'][0]['v_regional'] == 2 ? 1 : ($dataPelaporan['data'][0]['v_provinsi'] == 2 ? 1 : 0)));
			$textInfoPelaporan = "Provinsi ".$dataPelaporan["data"][0]["nama_provinsi"].", ".$dataPelaporan["data"][0]["nama_kabkota"]." | Pelaporan ".$dataPelaporan["data"][0]["tanggal"];

			$dataHistory = $this->db->fetch("SELECT uid_pelaporan_iktl,tanggal FROM pelaporan_iktl WHERE deleted = 0 AND uid_provinsi=".$dataPelaporan['data'][0]['uid_provinsi']." AND uid_kabkota=".$dataPelaporan['data'][0]['uid_kabkota']." AND uid_pelaporan_iktl = ".$dataPelaporan['data'][0]['uid_pelaporan_iktl']." ORDER BY tanggal ASC");
			$this->view->assign("data_history", $dataHistory);
			// $this->debug->show($dataHistory);

		}else{
			die("Data pelaporan tidak ditemukan");
		}
		if($this->params("verif")){
			$this -> cekLockSystem(1, 2.3, $this -> me['uid_users']);
		}else{
			$this -> cekLockSystem(1, 1.3, $this -> me['uid_users']);
		}
		$this->view->assign("textInfoPelaporan", $textInfoPelaporan);
		$this->view->assign("dataPelaporan", $dataPelaporan['data'][0]);
		$this -> view -> assign("pelaporanActive", "active");
		$this -> view -> assign("show", $show);
    $this -> view -> assign("message", $message);
		$this -> view -> assign("icons", '<i class="la la-tasks"></i>');
		$this -> view -> assign("title", 'RHL Indek Kualitas Lahan ');
		$this -> view -> display("index.html");
	}

  private function postData(){
    $post = $this->post();
    if(isset($post['submit'])){
      if($post['pelaporan_iktl_uid'] > 0){
        $val = $_FILES['file'];
        $ext = strtolower(strrchr($val['name'], "."));
        if ($ext == ".xls") {
            $files = $this -> functions -> uploadFile($_FILES['file'],'rhl_detail');
        }
        $dirFile = UPLOADFOLDER . "rhl_detail/" . $files;
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
          $onUpdateMultiple = [];
          $created = time();
          $chdate = time();
          $refJenisRhl = $this->tables->query("SELECT * FROM rf_jenis_rhl WHERE deleted = 0")['data'];
          foreach ($data as $key => $val) {
            if($val[1]){
              $idxJenisRhl = array_search($val[1], array_column($refJenisRhl,'name'));
              $dataDetail[$idxData]['jenis_rhl'] = trim($refJenisRhl[$idxJenisRhl]['idx']);
              $dataDetail[$idxData]['nama_rhl'] = ($val[2]? "'".trim($this->replaceinput($val[2]))."'" : "''");
              $dataDetail[$idxData]['tahun_tanam'] = ($val[3]? "'".trim($this->replaceinput($val[3]))."'" : "''");
              $dataDetail[$idxData]['koordinat_lintang'] = ($val[4]? "'".trim($this->replaceinput($val[4]))."'" : "''");
              $dataDetail[$idxData]['koordinat_bujur'] = ($val[5]? "'".trim($this->replaceinput($val[5]))."'" : "''");
              $dataDetail[$idxData]['luas_rhl'] = ($val[6]? "'".trim(str_replace("'","''", $val[6]))."'" : "''");
              $dataDetail[$idxData]['pola_tanam'] = ($val[7]? "'".trim($this->replaceinput($val[7]))."'" : "''");
              $dataDetail[$idxData]['jenis_pohon'] = ($val[8]? "'".trim($this->replaceinput($val[8]))."'" : "''");
              $dataDetail[$idxData]['keterangan'] = ($val[9]? "'".trim($this->replaceinput($val[9]))."'" : "''");
              $dataDetail[$idxData]['verify'] = 1;

              $arrayPush = array(
                0,0,$created,$chdate,
                $post['pelaporan_iktl_uid'],
                $dataDetail[$idxData]['jenis_rhl'],
                $dataDetail[$idxData]['nama_rhl'],
                $dataDetail[$idxData]['tahun_tanam'],
                $dataDetail[$idxData]['koordinat_lintang'],
                $dataDetail[$idxData]['koordinat_bujur'],
                $dataDetail[$idxData]['luas_rhl'],
                $dataDetail[$idxData]['pola_tanam'],
                $dataDetail[$idxData]['jenis_pohon'],
                $dataDetail[$idxData]['keterangan'],
                $dataDetail[$idxData]['verify'],
              );
    					$onUpdateMultiple[] = "(".implode(',',$arrayPush).")";
              $idxData +=1;
            }
          }
        }
        // $this->debug->show($onUpdateMultiple);
        if(count($onUpdateMultiple) > 0){
            $valUpdate = implode(",", $onUpdateMultiple);
            $sql = "INSERT INTO pelaporan_iktl_rhl (deleted,hidden,crdate,chdate,pelaporan_iktl_uid,jenis_rhl,nama_rhl,tahun_tanam,koordinat_lintang,koordinat_bujur,luas_rhl,pola_tanam,jenis_pohon,keterangan,verify) VALUES ".$valUpdate."";
            // $this->debug->show($sql);
            $return = $this->db->query($sql);
            if($return){
							$this->countData($post['pelaporan_iktl_uid']);
              return "Success, Data berhasil disimpan";
            }else{
              return "Error, Gagal menyimpan data!";
            }
        }else{
          return "Error, Data file tidak terbaca";
        }
      }else{
        return "Error, id pemantauan ikl tidak ditemukan";
      }
    }

		if(isset($post['copy'])){
			if(isset($post['pelaporan_iktl_uid']) && isset($post['periode'])){
				$data = $this->db->fetch("SELECT * FROM pelaporan_iktl_rhl WHERE deleted = 0 AND pelaporan_iktl_uid=".$post['periode']);
				if($data['total'] > 0){
					$onUpdateMultiple = [];
					foreach ($data['data'] as $key => $value) {
						$dataDetail[$key]['jenis_rhl'] = trim($value['jenis_rhl']);
						$dataDetail[$key]['nama_rhl'] = ($value['nama_rhl']  ? "'".trim($this->replaceinput($value['nama_rhl']))."'" : "''");
						$dataDetail[$key]['tahun_tanam'] = ($value['tahun_tanam']  ? "'".trim($this->replaceinput($value['tahun_tanam']))."'" : "''");
						$dataDetail[$key]['koordinat_lintang'] = ($value['koordinat_lintang']  ? "'".trim($this->replaceinput($value['koordinat_lintang']))."'" : "''");
						$dataDetail[$key]['koordinat_bujur'] = ($value['koordinat_bujur']  ? "'".trim($this->replaceinput($value['koordinat_bujur']))."'" : "''");
						$dataDetail[$key]['luas_rhl'] = ($value['luas_rhl']  ? "'".trim($this->replaceinput($value['luas_rhl']))."'" : "''");
						$dataDetail[$key]['pola_tanam'] = ($value['pola_tanam']  ? "'".trim($this->replaceinput($value['pola_tanam']))."'" : "''");
						$dataDetail[$key]['jenis_pohon'] = ($value['jenis_pohon']  ? "'".trim($this->replaceinput($value['jenis_pohon']))."'" : "''");
						$dataDetail[$key]['keterangan'] = ($value['keterangan']  ? "'".trim($this->replaceinput($value['keterangan']))."'" : "''");
						$dataDetail[$key]['verify'] = ($value['verify']  ? "'".trim($this->replaceinput($value['verify']))."'" : "''");

						$arrayPush = array(
							0,0,$value['crdate'],$value['chdate'],
							$post['pelaporan_iktl_uid'],
							$dataDetail[$key]['jenis_rhl'],
							$dataDetail[$key]['nama_rhl'],
							$dataDetail[$key]['tahun_tanam'],
							$dataDetail[$key]['koordinat_lintang'],
							$dataDetail[$key]['koordinat_bujur'],
							$dataDetail[$key]['luas_rhl'],
							$dataDetail[$key]['pola_tanam'],
							$dataDetail[$key]['jenis_pohon'],
							$dataDetail[$key]['keterangan'],
							$dataDetail[$key]['verify'],
							$post['periode']
						);
						$onUpdateMultiple[] = "(".implode(',',$arrayPush).")";
					}
					// $this->debug->show($onUpdateMultiple);
					if(count($onUpdateMultiple) > 0){
	            $valUpdate = implode(",", $onUpdateMultiple);
	            $sql = "INSERT INTO pelaporan_iktl_rhl (deleted,hidden,crdate,chdate,pelaporan_iktl_uid,jenis_rhl,nama_rhl,tahun_tanam,koordinat_lintang,koordinat_bujur,luas_rhl,pola_tanam,jenis_pohon,keterangan,verify,from_data) VALUES ".$valUpdate."";
	            $return = $this->db->query($sql);
	            if($return){
								$this->countData($post['pelaporan_iktl_uid']);
	              return "Success, Data berhasil disimpan";
	            }else{
	              return "Error, Gagal menyimpan data!";
	            }
	        }else{
	          return "Error, Data file tidak terbaca";
	        }
				}else{
					return "Error, data tidak ditemukan";
				}
			}
		}

  }


	private function replaceinput($value){
		$v1 = str_replace("'","\'", $value);
		$v2 = str_replace('"','\"', $v1);
		return $v2;
	}

	public function rejectAll(){
		$post['x'] = $this->params("x");
    if (isset($post['x']) && is_numeric($post['x']) ) {
        $this -> tables -> set("pelaporan_iktl_rhl", "pelaporan_iktl_uid");
				$post['form']['pelaporan_iktl_uid'] = $post['x'];
				$post['form']['verify'] = 2;
				$post['submit'] = TRUE;
        if ($this -> tables -> post($post)) {
						$this->countData($post['x']);
            echo json_encode(array('statusCode' => 200, 'message' => $this -> message -> save('success'), 'href'=>'iktlRhl/index/x/'.base64_encode($this->params("x"))."/verify/1"));
        } else {
            echo json_encode(array('statusCode' => 400, 'message' => $this -> message -> save('failed')));
        }
    } else {
        echo json_encode(array('statusCode' => 403, 'message' => $this -> message -> access()));
    }
	}
  public function deletedDataAll(){
    $post = $this -> post();
    if (isset($post['x'])) {
        $this -> tables -> set("pelaporan_iktl_rhl", "pelaporan_iktl_uid");
        if ($this -> tables -> softDelete($post['x'])) {
					$this->countData($post['x']);
            echo json_encode(array('statusCode' => 200, 'message' => $this -> message -> delete('success'), 'href'=>'iktlRhl/index/x/'.base64_encode($this->params("x"))));
        } else {
            echo json_encode(array('statusCode' => 400, 'message' => $this -> message -> delete('failed')));
        }
    } else {
        echo json_encode(array('statusCode' => 403, 'message' => $this -> message -> access()));
    }
  }
  public function deletedData()
  {
      $post = $this -> post();
      if (isset($post['x'])) {
          $this -> tables -> set("pelaporan_iktl_rhl", "pelaporan_iktl_rhl_uid");
          if ($this -> tables -> softDelete($post['x'])) {
              echo json_encode(array('statusCode' => 200, 'message' => $this -> message -> delete('success'), 'href'=>'iktlRhl/index/x/'.base64_encode($this->params("x"))));
          } else {
              echo json_encode(array('statusCode' => 400, 'message' => $this -> message -> delete('failed')));
          }
      } else {
          echo json_encode(array('statusCode' => 403, 'message' => $this -> message -> access()));
      }
  }

	public function countData($idPelaporanByFunction = null){
		$idPelaporan = ($idPelaporanByFunction?$idPelaporanByFunction:$this->params("x"));
		if($idPelaporan>0){
			$totalRhl = $this->db->query("SELECT SUM(luas_rhl) AS total FROM pelaporan_iktl_rhl WHERE deleted = 0 AND pelaporan_iktl_uid=".$idPelaporan);
			// $totalRhl = $this->db->query("SELECT SUM(luas_rhl) AS total FROM pelaporan_iktl_rhl WHERE deleted = 0 AND verify = 1 AND jenis_rhl > 2 AND pelaporan_iktl_uid=".$idPelaporan);
			// $totalLipi = $this->db->query("SELECT SUM(luas_rhl) AS total FROM pelaporan_iktl_rhl WHERE deleted = 0 AND verify = 1 AND jenis_rhl = 1 AND pelaporan_iktl_uid=".$idPelaporan);
			// $totalKehati = $this->db->query("SELECT SUM(luas_rhl) AS total FROM pelaporan_iktl_rhl WHERE deleted = 0 AND verify = 1 AND jenis_rhl = 2 AND pelaporan_iktl_uid=".$idPelaporan);

			$result['uid_pelaporan_iktl'] = $idPelaporan;
			$result['rhl'] = ($totalRhl->fields['total']?$totalRhl->fields['total']:0);
			// $result['kebun_raya_data_lipi'] = ($totalLipi->fields['total']?$totalLipi->fields['total']:0);
			// $result['taman_kehati'] = ($totalKehati->fields['total']?$totalKehati->fields['total']:0);
			$post['form'] = $result;
			$post['submit'] = TRUE;
			$this->tables->set('pelaporan_iktl','uid_pelaporan_iktl');
			$resultPost = $this->tables->post($post);
			if(!$idPelaporanByFunction){
				echo json_encode(array("statusCode"=>200,"message"=>"Data berhasi dihitung","data"=>$result));
			}
		}
	}

  private function getData(){
    $this->tables->set($this->viewName,$this->primaryKey);
    // $properties	= $this->_getProperties($this->viewName);
    $properties['data']	= ['luas_rhl','nama_rhl','tahun_tanam','koordinat_lintang','koordinat_bujur','luas_rhl','pola_tanam','jenis_pohon','keterangan','catatan'];
    $properties['total'] = count($properties['data']);
    // $this->debug->show($this->me);
    $urlVar  	= BASEURL . $this->url . '/x/'.$this->params('x').'/';
    $w 				= $this->where;
    $o 				= $this->primaryKey . " ASC";
    $post 		= $this->post();

		if($this->params("verif")){
			$this->view->assign("verif", $this->params("verif"));
			$urlVar .= "verif/1/";
		}

    $idPelaporan = base64_decode($this->params("x"));
    if(!$idPelaporan){
      die("id pelaporan ikl tidak ditemukan");
    }else{
      $this->view->assign("uid_pelaporan_iktl", $idPelaporan);
      $w .= " AND pelaporan_iktl_uid =".$idPelaporan;
    }
    if($this->params('search')){
      $post['search'] = TRUE;
      $post['form'] 	= json_decode(urldecode($this->params('search')),1);
    }
    if(isset($post['search'])){
      if($post['form']['keyword']){
        if($properties['total']){
          $w .= " AND ";
          $w .= "(";
          for($i=0;$i<$properties['total'];$i++){
            $w .= $properties['data'][$i] . " LIKE '%".$post['form']['keyword']."%' OR ";
          }
          $w .= $properties['data'][$properties['total']-1] . " LIKE '%".$post['form']['keyword']."%' ";
          $w .= ")";
        }
      }
      if($post['form']['jenis_rhl']){
        $w .= ' AND jenis_rhl='.$post['form']['jenis_rhl'];
      }
      if($post['form']['verify']){
        $w .= ' AND verify='.$post['form']['verify'];
      }
			if($post['form']['data_sumber'] == 1){
        $w .= ' AND from_data = 0';
      }
			if($post['form']['data_sumber'] == 2){
        $w .= ' AND from_data > 0';
      }

			if($post['form']['order_by'] && $post['form']['order_type']){
				$o = $post['form']['order_by'] ." ".$post['form']['order_type'];
			}

      $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
      $this->view->assign("search",$post['form']);

			$search_json = urlencode(json_encode($post['form']));
			$this->view->assign("search_json", $search_json);
    }else{

			$search_json = urlencode(json_encode($post['form']));
			$this->view->assign("search_json", $search_json);
		}
    //PAGING
    $offset   	= (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
    // $limit	  	= LIMIT;
    $limit	  	= 100;
		if($this->params("export")){
			$offset = ($this->params("offset") > 1 ? $this->params("offset") - 1 : 0);
			$limit = LIMIT_DOWNLOAD_EXCEL;
		}

    $data	  	= $this->tables->query('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
    $All	  		= $this->db->query('SELECT count('.$this->primaryKey.') as x FROM '.$this->viewName.' WHERE '. $w);
    $totalRow 	= (isset($All->fields['x']) ? $All->fields['x'] : 0);

		if ($this->params("export")){
			$this->view->assign("page", ($offset > 0 ? $offset : 1));
			$this->exportExcel($data);
		}

    $this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
		$listExport = $this->_getListExport($totalRow);
		$this->view->assign("listExport", $listExport);
    $this->view->assign("urlVar", $urlVar);
    $this->view->assign("totalRow", $totalRow);
    $this->view->assign("limit", $limit);
    $this->view->assign("page", $offset);
    $this->view->assign("view",$data['data']);
  }

	private function exportExcel($data){
		$this->view->assign("viewExcel", $data);
		header("Content-type: application/vnd-ms-excel");
		header('Content-Disposition: attachment; filename="IKTL_RHL_'.time().'.xls"');
		$html = $this->view->fetch('parts/contents/iktlRhl/index/excel.html');
		echo $html;
		die();
	}

  public function updateData(){
    $post = $this->post();
    if($post['idx'] > 0 && $post['field'] ){
      $update['form'][$this->primaryKey] = $post['idx'];
      $update['form'][$post['field']] = $post['value'];
      $update['submit'] = TRUE;
      $this->tables->set('pelaporan_iktl_rhl', $this->primaryKey);
      $updateStatus = $this->tables->post($update);
      if($updateStatus){
				if($post['field'] == 'verify'){
					$dataId = $this->db->fetch("SELECT pelaporan_iktl_uid FROM pelaporan_iktl_rhl WHERE pelaporan_iktl_rhl_uid = ".$post['idx'])['data'][0];
					$this->countData($dataId['pelaporan_iktl_uid']);
				}
        echo json_encode(array("statusCode"=>200,"message"=>"Data berhasil diupdate"));
      }else{
        echo json_encode(array("statusCode"=>400,"message"=>"Data gagal diupdate"));
      }
    }
    die();
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
					} else {
							$lockActionYear = 0;
					}
			}
      // $lockActionYear = 0;
      // $lockAction = 0;
			$this -> view -> assign("messageLock", $messageLock);
			$this -> view -> assign("lockAction", $lockAction);
			$this -> view -> assign("lockActionYear", $lockActionYear);
	}

  // public function syncDataRhl(){
  //   $data = $this->db->query("SELECT * FROM pelaporan_iktl WHERE deleted = 0 AND data_rhl != ''");
  //   $dataTmp = [];
  //   $onUpdateMultiple = [];
  //   $created = time();
  //   $chdate = time();
  //   foreach ($data as $key => $value) {
  //     if(is_array(json_decode($value['data_rhl'],TRUE))){
  //       $dataRhl = ($value['data_rhl_verifikasi'] ? json_decode($value['data_rhl_verifikasi'],TRUE) : json_decode($value['data_rhl'],TRUE));
  //       foreach ($dataRhl['data_rhl'] as $ki => $vi) {
  //         $arrayPush = array(
  //           0,0,$created,$chdate,
  //           $value['uid_pelaporan_iktl'],
  //           ($vi['jenis_rhl']? trim($vi['jenis_rhl']) : 0),
  //           ($vi['luas_rhl']? "'".trim(str_replace("'","''", $vi['luas_rhl']))."'" : "''"),
  //           ($vi['nama_rhl']? "'".trim(str_replace("'","''", $vi['nama_rhl']))."'" : "''"),
  //           ($vi['nama_desa']? "'".trim(str_replace("'","''", $vi['nama_desa']))."'" : "''"),
  //           ($vi['nama_kecamatan']? "'".trim(str_replace("'","''", $vi['nama_kecamatan']))."'" : "''"),
  //           ($vi['koordinat_lintang']? "'".trim(str_replace("'","''", $vi['koordinat_lintang']))."'" : "''"),
  //           ($vi['koordinat_bujur']? "'".trim(str_replace("'","''", $vi['koordinat_bujur']))."'" : "''"),
  //           ($vi['sumber_dana']? trim($vi['sumber_dana']) : 0),
  //           ($vi['nomor_sk']? "'".trim(str_replace("'","''", $vi['nomor_sk']))."'" : "''"),
  //           ($vi['keterangan']? "'".trim(str_replace("'","''", $vi['keterangan']))."'" : "''"),
  //           ($vi['catatan']? "'".trim(str_replace("'","''", $vi['catatan']))."'" : "''"),
  //           ($vi['verify']? trim($vi['verify']) : 1)
  //         );
	// 				$onUpdateMultiple[] = "(".implode(',',$arrayPush).")";
  //       }
  //     }
  //   }
  //   $this->debug->show($onUpdateMultiple);
  //   $valUpdate = implode(",", $onUpdateMultiple);
  //   $sql = "INSERT INTO pelaporan_iktl_rhl (deleted,hidden,crdate,chdate,pelaporan_iktl_uid,jenis_rhl,luas_rhl,nama_rhl,nama_desa,nama_kecamatan,koordinat_lintang,koordinat_bujur,sumber_dana,nomor_sk,keterangan,catatan,verify) VALUES ".$valUpdate."";
  //   // $this->debug->show($sql);
  //   $return = $this->db->query($sql);
  //   $this->debug->show($return);
  // }
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
}
?>
