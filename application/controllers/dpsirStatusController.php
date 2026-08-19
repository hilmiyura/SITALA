<?php

/**
 * created at : 14/10/2024
 * created by : Dasendria team
 * desc : controller for dpsir status
 */
class dpsirStatusController extends Front {
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

		$this -> view -> assign("primaryKey", "kd_kabkota");
		$this -> viewName = "v_daerah";
		$this -> primaryKey = "kd_kabkota";
		$this -> where = "deleted = 0";

		$this->view->assign("aksesAdmin", $this->ref->aksesAdmin($this->me));
	}

	public function index() {

		$this -> getData();
    $this->view->assign("dpsirActive","active");
    $this->view->assign("show",$show);
    $this->view->assign("message",$message);
    $this->view->assign("icons",'<i class="la la-tasks"></i>');
    $this->view->assign("title",'DPSIR');
    $this->view->display("index.html");
	}

	private function getData() {

    $provinsi = $this->me["uid_provinsi"];
    $kabkota  = $this->me["uid_kabkota"];

    $w = " deleted = 0";
    $wisian = " deleted = 0";
    if($this->me["uid_regional"]){
      $w .= " AND kd_regional = ".$this->me["uid_regional"];
      $idProvinsi = $this->db->fetch("SELECT kd_propinsi FROM rf_provinsi WHERE kd_regional =".$this->me["uid_regional"]);
      $idProvinsi = implode(",", array_column($idProvinsi["data"],"kd_propinsi"));
      $wisian .= " AND uid_provinsi IN ({$idProvinsi}) ";
    }
    if($this->me["uid_provinsi"]){
      $w .= " AND kd_provinsi = ".$this->me["uid_provinsi"];
      $wisian .= " AND uid_provinsi = ".$this->me["uid_provinsi"];
    }
    if($this->me["uid_kabkota"]){
      $w .= " AND kd_kabkota = ".$this->me["uid_kabkota"];
      $wisian .= " AND uid_kabkota = ".$this->me["uid_kabkota"];
    }

    //ref option
    $daerahOption = [];
    $daerahOptionData = $this->db->fetch("SELECT * FROM v_daerah WHERE {$w}");
    foreach ($daerahOptionData["data"] as $key => $value) {

      $idxRegional = $value['kd_regional']."_0_0";
      $idxProvinsi = $value['kd_regional']."_".$value['kd_provinsi']."_0";
      $idxKabkota = $value['kd_regional']."_".$value['kd_provinsi']."_".$value['kd_kabkota'];

      $daerahOption[$idxRegional]['id'] = $value['kd_regional']."_0_0";
      $daerahOption[$idxRegional]['text'] = $value['nama_regional'];

      $daerahOption[$idxProvinsi]['id'] = $value['kd_regional']."_".$value['kd_provinsi']."_0";
      $daerahOption[$idxProvinsi]['text'] = $value['nama_regional']." - ".$value['nama_provinsi'];

      $daerahOption[$idxKabkota]['id'] = $value['kd_regional']."_".$value['kd_provinsi']."_".$value['kd_kabkota'];
      $daerahOption[$idxKabkota]['text'] = $value['nama_regional']." - ".$value['nama_provinsi']." - ".$value['nama_kabkot'];
    }
    $daerahOption = array_values($daerahOption);
    $this->view->assign("daerahSrc", $daerahOption);

    $post = $this->post();
    if(isset($post['search']) || isset($post['export'])){
      if($post['src']['tahun']){
        $wisian .= " AND tahun = ".$post['src']['tahun'];
      }
      if($post['src']['daerah']){
        $srcDaerah = explode("_",$post['src']['daerah']);
        if((int)$srcDaerah[0] > 0){
          $w .= " AND kd_regional = ".$srcDaerah[0];
          $idProvinsi = $this->db->fetch("SELECT kd_propinsi FROM rf_provinsi WHERE kd_regional =".$srcDaerah[0]);
          $idProvinsi = implode(",", array_column($idProvinsi["data"],"kd_propinsi"));
          $wisian .= " AND uid_provinsi IN ({$idProvinsi}) ";
        }
        if((int)$srcDaerah[1] > 0){
          $w .= " AND kd_provinsi = ".$srcDaerah[1];
          $wisian .= " AND uid_provinsi = ".$srcDaerah[1];
        }
        if((int)$srcDaerah[2] > 0){
          $w .= " AND kd_kabkota = ".$srcDaerah[2];
          $wisian .= " AND uid_kabkota = ".$srcDaerah[2];
        }
      }
    }else{
      $wisian .= " AND tahun = ".date("Y");
      $post['src']['tahun'] = date("Y");
    }

    $this->view->assign("src", $post["src"]);


		$dpsirIsian = $this->db->fetch("SELECT CONCAT(kategori_isian,'-',uid_komponen,'-',uid_provinsi,'-',uid_kabkota) AS status_data FROM dpsir_isian_pemda WHERE {$wisian} AND parent = 0  GROUP BY kategori_isian, uid_komponen, uid_provinsi, uid_kabkota");
		$dpsirRespon = $this->db->fetch("SELECT CONCAT(uid_komponen,'-',uid_provinsi,'-',uid_kabkota) AS status_data FROM dpsir_isian_pemda WHERE {$wisian} AND parent > 0  GROUP BY uid_komponen, uid_provinsi, uid_kabkota");

    $komponen = array(
      array("id" => 'DRIVER',"text" => "driver"),
      array("id" => 'PRESSURE',"text" => "pressure"),
      array("id" => 'STATE',"text" => "state"),
			array("id" => 'IMPACT',"text" => "impact"),
      array("id" => 'RESPONSE',"text" => "response")
    );
    $this->view->assign("komponen", $komponen);
    $aspek = array(
      array("id" => 1,"text" => "iku"),
      array("id" => 2,"text" => "ika"),
      array("id" => 3,"text" => "ikl"),
			array("id" => 5,"text" => "ikal"),
      array("id" => 4,"text" => "ikeg"),
    );
    $this->view->assign("aspek", $aspek);

    $data = [];
    $daerah = $this->db->fetch("SELECT * FROM v_daerah WHERE {$w}");
    foreach ($daerah["data"] as $key => $value) {

      $idxProvinsi = $value['kd_provinsi']."_0";
      $idxKabkota = $value['kd_provinsi']."_".$value['kd_kabkota'];

      $statusProvinsi = 0;
      if(isset($data[$idxProvinsi]['kd_provinsi'])){
        $statusProvinsi = 1;
      }
      $data[$idxProvinsi]['kd_kabkota'] = 0;
      $data[$idxProvinsi]['kd_regional'] = $value['kd_regional'];
      $data[$idxProvinsi]['kd_provinsi'] = $value['kd_provinsi'];
      $data[$idxProvinsi]['nama_provinsi'] = $value['nama_provinsi'];
      $data[$idxProvinsi]['nama_regional'] = $value['nama_regional'];

      if($statusProvinsi == 0){
        foreach ($komponen as $ki => $vi) {
          foreach ($aspek as $kj => $vj) {
						if($vi['text'] == "response"){
							$data[$idxProvinsi]['dpsir'][$vi['text']][$vj['text']] = $this->getStatus($dpsirRespon, $data[$idxProvinsi], $vi['id'] , $vj['id']);
						}else{
							$data[$idxProvinsi]['dpsir'][$vi['text']][$vj['text']] = $this->getStatus($dpsirIsian, $data[$idxProvinsi], $vi['id'] , $vj['id']);
						}
          }
        }
      }


      $data[$idxKabkota] = $value;
      foreach ($komponen as $ki => $vi) {
        foreach ($aspek as $kj => $vj) {
					if($vi['text'] == "response"){
						$data[$idxKabkota]['dpsir'][$vi['text']][$vj['text']] = $this->getStatus($dpsirRespon, $value, $vi['id'] , $vj['id']);
					}else{
						$data[$idxKabkota]['dpsir'][$vi['text']][$vj['text']] = $this->getStatus($dpsirIsian, $value, $vi['id'] , $vj['id']);
					}
        }
      }
    }

    $data = array_values($data);
		$this->view->assign("data",$data);
		if(isset($post['export'])){
			$this->exportData();
			exit();
		}
	}

  private function getStatus($dpsirIsian, $value, $komponen, $aspek){
		if($komponen == "RESPONSE"){
			$search = $aspek."-".$value['kd_provinsi']."-".$value['kd_kabkota'];
		}else{
			$search = $komponen."-".$aspek."-".$value['kd_provinsi']."-".$value['kd_kabkota'];
		}
    $idx = array_search($search, array_column($dpsirIsian["data"],"status_data"));
    if(is_numeric($idx)){
      return 1;
    }else{
      return 0;
    }
  }

	private function exportData(){
		require_once('HtmlExcel.php');
    $xls = new HtmlExcel();

		$html = $this->view->fetch('parts/contents/dpsirStatus/index/export.html');
    $xls->addSheet("data", $html);
    $xls->headers("status_dpsir.xls");
    echo $xls->buildFile();
	}

}
?>
