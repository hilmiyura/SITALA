<?php

/**
 * created at : 23/08/2021
 * created by : dasendria team
 * desc : controller for input dkk provinsi
 */
class nilaiDkkController extends Front
{
  public function init() {
    ($this->session->get('memberIKLH')?:$this->redirect("login"));

    //SET CUSTOM VIEWS FOLDER
    $this->view->setFolder('be');

    //LOAD MODELS
    $this->loadModel("tables");
    $this->loadModel("ref");

    //GLOBAL VAR
    $this->me 			= $this->session->get('memberIKLH');
    $this->ctrl 			= $this->uri->getController();
    $this->act 			= $this->uri->getAction();
    $this->url			= $this->ctrl . '/' . $this->act;

    //load function
    require_once "functions.php";
    $this->functions = new functions();
    $this->view->assign("functions",$this->functions);
    require_once "excelReader.php";

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

    $this->view->assign("primaryKey", "uid_nilai_dkk");
    $this->viewName 	= "v_nilai_dkk";
    $this->primaryKey	= "uid_nilai_dkk";
    $this->where		= "deleted = 0";
    //$this->debug->show($this->me);
    $this->view->assign("aksesAdmin", $this->ref->aksesAdmin($this->me, ["IKL","PKG"]));

  }
  //INDEX FUNCTION IS A DEFAULT ACTION
  public function index(){
    $post = $this->post();
    if(isset($post['submit'])){
      if ($post['form']['uid_kabkota']) {
        $cekData = $this->tables->query("SELECT uid_nilai_dkk FROM nilai_dkk WHERE tahun=".$post['form']['tahun']." AND peruntukan = 1 AND uid_provinsi=".$post['form']['uid_provinsi']." AND uid_kabkota =".$post['form']['uid_kabkota']);
      }else{
        $cekData = $this->tables->query("SELECT uid_nilai_dkk FROM nilai_dkk WHERE tahun=".$post['form']['tahun']." AND peruntukan = 2 AND uid_provinsi=".$post['form']['uid_provinsi']);
      }

      if($cekData['total']){
        $post['form']['uid_nilai_dkk'] = $cekData['data'][0]['uid_nilai_dkk'];
      }
      $post['form']['nilai'] = str_replace(",",".",$post['form']['nilai']);
      if(!$post['form']['uid_nilai_dkk']){
        $post['form']['cruser'] = $this->me['uid_users'];
      }
      $this->tables->set("nilai_dkk","uid_nilai_dkk");
      if($this->tables->post($post)){
        $message = "Berhasil menyimpan data !";
      }else{
        $message = "Gagal menimpan data !";
      }
    }
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
						$tmpTahun = NULL;
						$tmpProv = NULL;
						foreach ($data as $key => $vals) {
								if ($vals[1] != "-") {
										$postExcel['form'][$this->primaryKey] = "";
										$postExcel['form']['cruser'] = $post['form']['cruser'];
										if ($vals[4] == "KABUPATEN") {
											$postExcel['form']['peruntukan'] = 1;
										} else {
											$postExcel['form']['peruntukan'] = 2;
										}
										$cek_provinsi = $this -> tables -> query("SELECT * FROM rf_provinsi WHERE nama_propinsi LIKE '".$vals[1]."'");
										if($cek_provinsi['total'] == 0){
											$tmpProv[] = $vals[1];
										}
										$postExcel['form']['uid_provinsi'] = $cek_provinsi['data'][0]['kd_propinsi'];
										if ($vals[2] == "NULL") {
											$postExcel['form']['uid_kabkota'] = 0;
										} else {
											$cek_kabkota = $this -> tables -> query("SELECT * FROM rf_kabkota WHERE deleted=0 AND nama_kabkot LIKE '".$vals[2]."' AND kd_provinsi=".$cek_provinsi['data'][0]['kd_propinsi']);
											$postExcel['form']['uid_kabkota'] = $cek_kabkota['data'][0]['kd_kota'];
										}
										$postExcel['form']['tahun'] = $vals[3];
										$postExcel['form']['nilai'] = str_replace(",", '.', $vals[5]);
										if ($postExcel['form']['uid_kabkota']) {
											$wPeruntukan = " deleted = 0 AND uid_provinsi=".$postExcel['form']['uid_provinsi']." AND uid_kabkota =".$postExcel['form']['uid_kabkota']." AND peruntukan = 1 AND tahun=".$postExcel['form']['tahun'];
										} else {
											$wPeruntukan = " deleted = 0 AND uid_provinsi=".$postExcel['form']['uid_provinsi']." AND peruntukan = 2 AND tahun=".$postExcel['form']['tahun'];
										}
										$cek_tahun_peruntukan = $this -> tables -> query("SELECT * FROM {$this->viewName} WHERE ".$wPeruntukan);
										$postExcel['submit'] = true;
										if ($cek_tahun_peruntukan['total'] != "") {
											$wil = $vals[1].' - '.$vals[2];
											$tmpTahun[] = $wil.' tahun '.$postExcel['form']['tahun'];
										}else {
											if($postExcel['form']['uid_provinsi']){
												$this -> tables -> set("nilai_dkk", $this->primaryKey);
												$this -> tables -> post($postExcel);
											}
										}
								}
								// $this->debug->show($tmpKodeLokasi);
								if ($tmpTahun) {
									$message = "Nilai DKK provinsi ".implode(', ', $tmpTahun)." tidak tersimpan oleh sistem dikarenakan tahun tersebut sudah ada nilai. Selain dari tahun tersebut data berhasil disimpan";
									$message .= ($tmpProv ? "<br><br>Nilai DKK IKLH provinsi ".implode(',',array_unique($tmpProv)). " tidak tersimpan karena provinsi tidak terdaftar disistem atau nama tidak sesuai dengan disistem" : "");
								}else {
									if (count($data) == $tmpCn) {
											$message = "Berhasil menyimpan data !". ($tmpProv ? ", provinsi ".implode(',',$tmpProv). " Tidak terdaftar disistem atau nama tidak sesuai dengan disistem" : "");
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
    $this->view->assign("icons",'<i class="la la-tasks"></i>');
    $this->view->assign("title",'Faktor Koreksi Gambut');
    $this->view->display("index.html");
  }

  private function getData(){
    $this->tables->set($this->viewName,$this->primaryKey);
    $properties	= $this->_getProperties($this->viewName);
    $urlVar  	= BASEURL . $this->url . '/';
    $w 			= $this->where;
    if ($this -> me['role_user'] == 3) {
			$w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
		} elseif ($this -> me['role_user'] == 2) {
			$w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
		} elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
			$w .= " AND kd_regional =" . $this -> me['uid_regional'];
		}

		$o = "uid_provinsi,uid_kabkota,tahun ASC";
    $post 		= $this->post();
    if($this->params('search')){
      $post['search'] = TRUE;
      $post['form'] 	= json_decode(urldecode($this->params('search')),1);
    }
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
      if ($post['form']['tahun']) {
				$w .= " AND tahun ='" . $post['form']['tahun'] . "'";
			}
      $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
      $this->view->assign("search",$post['form']);
    }else{
      $tahunDefault = ACTIVE_YEAR;
			$w .= " AND tahun ='" . $tahunDefault . "'";
			$post['form']['tahun'] = $tahunDefault;

      $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
      $this->view->assign("search",$post['form']);
    }
    //PAGING
    $offset   	= (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
    $limit	  	= LIMIT;
    $data	  	= $this->tables->query('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
    $All	  		= $this->db->query('SELECT count('.$this->primaryKey.') as x FROM '.$this->viewName.' WHERE '. $w);
    $totalRow 	= (isset($All->fields['x']) ? $All->fields['x'] : 0);
    // $this->debug->show($data);
    $this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
    $this->view->assign("urlVar", $urlVar);
    $this->view->assign("totalRow", $totalRow);
    $this->view->assign("limit", $limit);
    $this->view->assign("page", $offset);
    $this->view->assign("view",$data['data']);
  }

  public function editData(){
    header("Content-Type: application/json; charset=UTF-8");
    if($this->params("x")){
      $this->tables->set("nilai_dkk","uid_nilai_dkk");
      $dataEdit = $this->tables->fetch("deleted = 0 AND uid_nilai_dkk=".$this->params("x"));
      echo json_encode($dataEdit['data'][0]);
    }
  }

  public function deletedData(){
    $post = $this->post();
    if(isset($post['x'])){
      $this->tables->set("nilai_dkk","uid_nilai_dkk");
      if($this->tables->softDelete($post['x'])){
        echo json_encode(array('statusCode' => 200, 'message' => $this->message->delete('success')));
      }else{
        echo json_encode(array('statusCode' => 400, 'message' => $this->message->delete('failed')));
      }
    }else{
      echo json_encode(array('statusCode' => 403, 'message' => $this->message->access()));
    }
  }

  private function rfData(){
    $this->tables->set("rf_provinsi","kd_propinsi");
    $rf = $this->tables->fetch();
    $this->view->assign("provinsi",$rf['data']);
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
