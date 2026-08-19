<?php

/**
 * created at : 24/04/2024
 * created by : dasendria team
 * desc : controller for input sungai
 */
class refSungaiController extends Front
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

    $this->view->assign("primaryKey", "uid");
    $this->viewName 	= "v_sungai";
    $this->primaryKey	= "uid";
    $this->where		= "deleted = 0";
    // $this->debug->show($this->me);

  }
  //INDEX FUNCTION IS A DEFAULT ACTION
  public function index(){
    if($this->me["kategori"]  > 0){
      $this->redirect('dashboard');
    }
    $post = $this->post();
    if(isset($post['submit'])){
      $kabkotaEx = explode("-",$post['form']['uid_kabkota']);
      $post['form']['uid_provinsi'] = $kabkotaEx[0];
      $post['form']['uid_kabkota'] = $kabkotaEx[1];
      if(!$post['form']['uid']){
        $post['form']['cruser'] = $this->me['uid_users'];
      }else{
        $post['form']['chuser'] = $this->me['uid_users'];
      }
      $post["form"]["nama"] = trim($post["form"]["nama"]);
      $namaSungai = $post["form"]["nama"];
      $uidDas = $post["form"]["uid_das"];
      $this->tables->set("rf_sungai","uid");
      $checkNama = $this->tables->fetch("deleted = 0 AND nama LIKE '{$namaSungai}' AND uid_das={$uid_das}");
      if($checkNama["total"] > 0){
        $message = "Gagal menyimpan data, sungai {$namaSungai} sudah terdaftar !";
      }else{
        if($this->tables->post($post)){
          $message = "Berhasil menyimpan data !";
        }else{
          $message = "Gagal menimpan data !";
        }
      }
    }

    $this->getData();
    $this->rfData();
    $this->view->assign("masterActive","active");
    $this->view->assign("show",$show);
    $this->view->assign("message",$message);
    $this->view->assign("icons",'<i class="la la-tasks"></i>');
    $this->view->assign("title",'Nama Sungai');
    $this->view->display("index.html");
  }

  private function getData(){
    $this->tables->set($this->viewName,$this->primaryKey);
    $properties	= $this->_getProperties($this->viewName);
    $urlVar  	= BASEURL . $this->url . '/';
    $w 			= $this->where;
    $o 			= "uid DESC";
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
      if ($post['form']['uid_das']) {
				$w .= " AND uid_das ='" . $post['form']['uid_das'] . "'";
			}
      if ($post['form']['src_prop']) {
        $w .= " AND uid_provinsi =".$post['form']['src_prop'];
      }
      if ($post['form']['src_kabkota2']) {
        $w .= " AND uid_kabkota =".$post['form']['src_kabkota2'];
      }
      $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
      $this->view->assign("search",$post['form']);
      $search_json = urlencode(json_encode($post['form']));
      $this->view->assign("search_json", $search_json);
    }

    if($post['form']['src_prop']){
      $this -> view -> assign("kabkotaSelect", $this->db->fetch("SELECT * FROM rf_kabkota WHERE deleted = 0 AND kd_provinsi=".$post['form']['src_prop'])["data"]);
    }

    //PAGING
    $offset   	= (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
    $limit	  	= LIMIT;
    $data	  	= $this->tables->query('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
    $All	  		= $this->db->query('SELECT count('.$this->primaryKey.') as x FROM '.$this->viewName.' WHERE '. $w);
    $totalRow 	= (isset($All->fields['x']) ? $All->fields['x'] : 0);
    // $this->debug->show($data);
    $this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
    $listExport = $this->_getListExport($totalRow);
		$this->view->assign("listExport", $listExport);
    $this->view->assign("urlVar", $urlVar);
    $this->view->assign("totalRow", $totalRow);
    $this->view->assign("limit", $limit);
    $this->view->assign("page", $offset);
    $this->view->assign("view",$data['data']);
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
			$properties = $this -> _getProperties($this->viewName);
      $w 			= $this->where;
      $o 			= "uid DESC";
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
        if ($post['form']['uid_das']) {
  				$w .= " AND uid_das ='" . $post['form']['uid_das'] . "'";
  			}
        if ($post['form']['src_prop']) {
          $w .= " AND uid_provinsi =".$post['form']['src_prop'];
        }
        if ($post['form']['src_kabkota2']) {
          $w .= " AND uid_kabkota =".$post['form']['src_kabkota2'];
        }
      }
			$this->tables->set($this->viewName, $this->primaryKey);
			$paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
			$data	= $this->tables->fetch($w, $o, $paging);

			$this->view->assign("viewExcel", $data);

			header("Content-type: application/vnd-ms-excel");
			header('Content-Disposition: attachment; filename="SUNGAI_IKLH_'.time().'.xls"');
			$html = $this->view->fetch('parts/contents/refSungai/index/excel.html');
			echo $html;
	}

  public function editData(){
    header("Content-Type: application/json; charset=UTF-8");
    if($this->params("x")){
      $this->tables->set("rf_sungai","uid");
      $dataEdit = $this->tables->fetch("deleted = 0 AND uid=".$this->params("x"));
      echo json_encode($dataEdit['data'][0]);
    }
  }

  public function deletedData(){
    $post = $this->post();
    if(isset($post['x'])){
      $checkPelaporan = $this->db->fetch("SELECT * FROM lokasi_pemantauan WHERE uid_sungai = {$post['x']}")["data"][0];
      if($checkPelaporan["uid_sungai"] > 0){
        echo json_encode(array('statusCode' => 403, 'message' => 'Tidak bisa dihapus, sudah digunakan dilokasi pemantauan'));
      }else{
        $this->tables->set("rf_sungai","uid");
        if($this->tables->softDelete($post['x'])){
          echo json_encode(array('statusCode' => 200, 'message' => $this->message->delete('success')));
        }else{
          echo json_encode(array('statusCode' => 400, 'message' => $this->message->delete('failed')));
        }
      }
    }else{
      echo json_encode(array('statusCode' => 403, 'message' => $this->message->access()));
    }
  }

  private function rfData(){
    // $das = $this->db->fetch("SELECT * FROM rf_das WHERE deleted = 0");
    // $this->view->assign("das", $das["data"]);

    $prov = $this->db->fetch("SELECT *, kd_propinsi AS id, nama_propinsi AS nama FROM rf_provinsi");
    $this->view->assign("provinsiSelect", $prov['data']);

    // $kabkota = $this->db->fetch('SELECT * FROM rf_kabkota WHERE deleted = 0');
    // $this->view->assign("kabkotaSelect", $kabkota['data']);

    // $rf = $this->db->fetch("SELECT kd_propinsi AS id, nama_propinsi AS nama FROM rf_provinsi");
    foreach ($prov["data"] as $key => $value) {
      $prov["data"][$key]["kabkota"] = $this->db->fetch("SELECT kd_kota AS id, nama_kabkot AS nama  FROM rf_kabkota WHERE kd_provinsi=".$value["id"])["data"];
    }
    // $this->debug->show($prov['data']);
    $this->view->assign("provinsi",$prov['data']);
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
