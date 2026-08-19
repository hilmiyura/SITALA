<?php

/**
 * created at : 24/04/2024
 * created by : dasendria team
 * desc : controller DPSIR
 */
class dpsirController extends Front
{
  public function init() {
    ($this->session->get('memberIKLH')?:$this->redirect("login"));

    //SET CUSTOM VIEWS FOLDER
    $this->view->setFolder('be');

    //LOAD MODELS
    $this->loadModel("tables");
    $this->loadModel("users");
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
    $this->viewName 	= "dpsir_isian_pemda";
    $this->primaryKey	= "uid_dpsir_isian_pemda";
    $this->where		= "deleted = 0";
    //$this->debug->show();

  }

  public function index(){

    $refKomponen = $this->db->fetch("SELECT * FROM rf_component WHERE deleted = 0");
    $this->view->assign("komponen", $refKomponen["data"]);

    $provinsi = $this->me["uid_provinsi"];
    $kabkota  = $this->me["uid_kabkota"];

    $w = " deleted = 0";
    if($this->me["uid_regional"]){
      $w .= " AND kd_regional = ".$this->me["uid_regional"];
    }
    if($this->me["uid_provinsi"]){
      $w .= " AND kd_provinsi = ".$this->me["uid_provinsi"];
    }
    if($this->me["uid_kabkota"]){
      $w .= " AND kd_kabkota = ".$this->me["uid_kabkota"];
    }
    $daerah = $this->db->fetch("SELECT * FROM v_daerah WHERE {$w}");
    $dataDaerah = [];
    foreach ($daerah["data"] as $key => $value) {
      $dataDaerah[$value["kd_regional"]]["kd_regional"] = $value["kd_regional"];
      $dataDaerah[$value["kd_regional"]]["nama_regional"] = $value["nama_regional"];
      $dataDaerah[$value["kd_regional"]]["provinsi"][$value["kd_provinsi"]]["kd_provinsi"] = $value["kd_provinsi"];
      $dataDaerah[$value["kd_regional"]]["provinsi"][$value["kd_provinsi"]]["nama_provinsi"] = $value["nama_provinsi"];
      $dataDaerah[$value["kd_regional"]]["provinsi"][$value["kd_provinsi"]]["kd_kabkota"] = 0;
      $dataDaerah[$value["kd_regional"]]["provinsi"][$value["kd_provinsi"]]["kabkota"][$value["kd_kabkota"]]["kd_kabkota"] = $value["kd_kabkota"];
      $dataDaerah[$value["kd_regional"]]["provinsi"][$value["kd_provinsi"]]["kabkota"][$value["kd_kabkota"]]["nama_kabkota"] = $value["nama_kabkot"];
    }
    // $this->debug->show($dataDaerah);
    $this -> cekLockSystem(6, 0, $this -> me['uid_users']);
    $this->view->assign("daerah", $dataDaerah);
    $this->view->assign("dpsirActive","active");
    $this->view->assign("show",$show);
    $this->view->assign("message",$message);
    $this->view->assign("icons",'<i class="la la-tasks"></i>');
    $this->view->assign("title",'DPSIR');
    $this->view->display("index.html");
  }

  public function form(){
    $this -> cekLockSystem(6, 0, $this -> me['uid_users']);
    
    $kategori = $this->params("c");
    $uid = $this->params("x");
    $parent = (is_numeric($this->params("p")) ? $this->params("p") : "");
    $this->view->assign("kategori", $kategori);
    $this->view->assign("parent", $parent);
    $this->view->assign("uid", $uid);

    if(is_numeric($uid)){
      $data = $this->db->fetch("SELECT * FROM dpsir_isian_pemda WHERE uid_dpsir_isian_pemda = {$uid}")["data"][0];
      $this->view->assign("data", $data);
    }

    $html = $this->view->fetch('parts/contents/dpsir/index/pemda-form.html');
    echo json_encode(array("statusCode"=>200,"message"=>"success","html"=>$html));
  }

  public function deleted(){
    $uid = $this->params("x");
    if(is_numeric($uid)){
      $update["form"]["uid_dpsir_isian_pemda"] = $uid;
      $update["form"]["deleted"] = 1;
      $update["submit"] = TRUE;
      $this->tables->set("dpsir_isian_pemda","uid_dpsir_isian_pemda");
      $statusUpdate = $this->tables->post($update);
      if($statusUpdate){
        echo json_encode(array("statusCode"=>200,"message"=>"success"));
      }else{
        echo json_encode(array("statusCode"=>400,"message"=>"error save"));
      }
    }
  }
  public function submit(){

    $dataRequest = file_get_contents("php://input");
    $dataRequest = json_decode($dataRequest, true);

    $update["form"] = $dataRequest;
    if ($this->me['role_user'] <= 1) {
      $update["form"]["isian_role"] = 'PUSAT';
      $daerah   = explode("-",$dataRequest["src_kabkota"]);
      $this->me['uid_provinsi'] = $daerah[1];
      $this->me['uid_kabkota'] = $daerah[2];
    }
    if($this->me['uid_provinsi'] <= 0){
      echo json_encode(array("statusCode"=>400,"message"=>"Id provinsi tidak ada"));
      die();
    }
    $update["form"]["tahun"] = 2024;
    $update["form"]["uid_provinsi"] = $this->me['uid_provinsi'];
    $update["form"]["uid_kabkota"] = $this->me['uid_kabkota'];

    $update["submit"] = TRUE;

    $this->tables->set("dpsir_isian_pemda","uid_dpsir_isian_pemda");
    $statusUpdate = $this->tables->post($update);
    if($statusUpdate){
      echo json_encode(array("statusCode"=>200,"message"=>"success"));
      die();
    }else{
      echo json_encode(array("statusCode"=>400,"message"=>"error save"));
      die();
    }
  }
  public function uploadFile(){
    $lampiran = $_FILES['lampiran'];
    if($lampiran){
      $files = $this -> functions -> uploadFile($lampiran,'dpsir');
      if($files){
        echo json_encode(array("statusCode"=>200,"message"=>"Success", "lampiran"=>$files));
        die();
      }else{
        echo json_encode(array("statusCode"=>400,"message"=>"error save"));
        die();
      }
    }else{
      echo json_encode(array("statusCode"=>400,"message"=>"error save"));
      die();
    }
  }

  public function component(){
    $this -> cekLockSystem(6, 0, $this -> me['uid_users']);
    $daerah   = explode("-",$this->params("d"));
    $provinsi_src = $daerah[1];
    $kabkota_src = $daerah[2];
    $provinsi = ($this->me['uid_provinsi'] ? $this->me['uid_provinsi'] : $provinsi_src);
    $kabkota = ($this->me['uid_kabkota'] ? $this->me['uid_kabkota'] : $kabkota_src);
    $tahun = $this->params("t");
    $komponen = $this->params("k");
    $kategori = $this->params("c");
    $response = $this->params("r");

    $gambut = 1;
    if((int)$komponen == 4){
      // $this->debug->show($gambut);
      if($kabkota > 0){
        $gambut = $this->db->fetch("SELECT gambut FROM rf_kabkota WHERE kd_kota ={$kabkota}")["data"][0]["gambut"];
      }else {
        $gambut = $this->db->fetch("SELECT gambut FROM rf_provinsi WHERE kd_propinsi ={$provinsi}")["data"][0]["gambut"];
      }
    }

    if($response){
      $data = $this->db->fetch("SELECT * FROM dpsir_isian_pemda WHERE deleted = 0 AND isian_role IS NULL AND tahun = {$tahun} AND uid_komponen = {$komponen} AND uid_provinsi = {$provinsi} AND uid_kabkota = {$kabkota} AND parent > 0 AND kategori_isian = '".$kategori."' ORDER BY uid_dpsir_isian_pemda ASC ")["data"];
    }else{
      $wstate = "";
      if($gambut != 1){
          $wstate .= " AND kode_isian NOT IN (IKEG1,IKEG2) ";
      }
      $data = $this->db->fetch("SELECT * FROM dpsir_isian_pemda WHERE deleted = 0 AND tahun = {$tahun} AND uid_komponen = {$komponen} AND uid_provinsi = {$provinsi} AND uid_kabkota = {$kabkota} AND parent = 0 AND kategori_isian = '".$kategori."' ORDER BY uid_dpsir_isian_pemda ASC ")["data"];

      if($kategori == "STATE"){
        $statePusat = [];
        $statePusat = $this->db->fetch("SELECT * FROM dpsir_isian_pemda WHERE deleted = 0 AND isian_role = 'PUSAT' {$wstate} AND tahun = {$tahun} AND uid_komponen = {$komponen} AND uid_provinsi = 0 AND uid_kabkota = 0 AND parent = 0 AND kategori_isian = '".$kategori."' ORDER BY uid_dpsir_isian_pemda ASC ")["data"];

        $indeks = $this->getIndeks($tahun, $komponen, $provinsi, $kabkota);

        $jumlahStatusMutu = [];
        if($komponen == 2){
          $jumlahStatusMutu = $this->hitungStatusMutu($tahun, $provinsi, $kabkota);
        }
        $rataanIKU = [];
        if($komponen == 1){
          $rataanIKU = $this->rataanIKU($tahun, $provinsi, $kabkota);
        }
        $komponenIndeksList = array("IKU","IKA","IKL","IKEG","IKAL");
        foreach ($komponenIndeksList as $key => $value) {
          $idxKodeIndeks = array_search($value."1",array_column($statePusat,"kode_isian"));
          if(is_numeric($idxKodeIndeks)){
            $statePusat[$idxKodeIndeks]["isian"] = $statePusat[$idxKodeIndeks]["isian"]." ({$indeks[0]})";
            $statePusat[$idxKodeIndeks]["_nodetail"] = 1;
          }
          $idxKodeIndeks = array_search($value."2",array_column($statePusat,"kode_isian"));
          if(is_numeric($idxKodeIndeks)){
            $statePusat[$idxKodeIndeks]["isian"] = $statePusat[$idxKodeIndeks]["isian"]." ($indeks[1])";
            $statePusat[$idxKodeIndeks]["_nodetail"] = 1;
          }
          $idxKodeIndeks = array_search($value."3",array_column($statePusat,"kode_isian"));
          if(is_numeric($idxKodeIndeks) && $komponen == 1){
            foreach ($rataanIKU as $kriku => $vriku) {
              $statePusat[$idxKodeIndeks]["isian"] .= " <br><br> Rata-rata Konsentrasi ".mb_strtoupper($vriku['parameter']);
              foreach ($vriku['peruntukan'] as $kriku1 => $vriku1) {
                $statePusat[$idxKodeIndeks]["isian"] .= " <br> ".$vriku1['text']." : ".$vriku1['nilai'];
              }
            }
          }
          $idxKodeIndeks = array_search($value."3",array_column($statePusat,"kode_isian"));
          if(is_numeric($idxKodeIndeks) && $komponen == 2){
            $statePusat[$idxKodeIndeks]["isian"] .= " <br> MEMENUHI : ".$jumlahStatusMutu['kelas2']['memenuhi'];
            $statePusat[$idxKodeIndeks]["isian"] .= " <br> CEMAR BERAT : ".$jumlahStatusMutu['kelas2']['berat'];
            $statePusat[$idxKodeIndeks]["isian"] .= " <br> CEMAR RINGAN : ".$jumlahStatusMutu['kelas2']['ringan'];
            $statePusat[$idxKodeIndeks]["isian"] .= " <br> CEMAR SEDANG : ".$jumlahStatusMutu['kelas2']['sedang'];
          }
        }
        $data = array_merge($statePusat, $data);
      }
    }

    $this->view->assign("komponen",$kategori);
    $this->view->assign("data", $data);
    $html = $this->view->fetch('parts/contents/dpsir/index/pemda-component.html');
    echo json_encode(array("statusCode"=>200,"message"=>"success","html"=>$html, "s"=>$jumlahStatusMutu));
  }

  public function getIndeks($tahun, $komponen, $provinsi, $kabkota){
    $tahun = $tahun-1;
    $indeks = array(1=>"iku",2=>"ika",3=>"ikl",4=>"ikeg",5=>"ikal");
    $fieldGet = $indeks[$komponen];
    $nilai = $this->db->fetch("SELECT {$fieldGet} AS nilai FROM indeks_history WHERE deleted =0 AND tahun={$tahun} AND uid_provinsi={$provinsi} AND uid_kabkota={$kabkota}")["data"][0]["nilai"];
    $nilai = ($nilai > 0 ? number_format($nilai,2,".","") : 0);
    $nilai = ($nilai > 100 ? 100 : $nilai);

    $ratingList = $this->db->fetch("SELECT * FROM rf_rating_iklh WHERE deleted = 0")["data"];
    $rating = $this->functions->rating($ratingList,$nilai);
    $dataReturn = [$nilai, $rating["rating"]];
    return $dataReturn;
  }

  private  function hitungStatusMutu($tahun, $provinsi, $kabkota){
    $tahun = $tahun-1;
    $wStatusMutu = " AND uid_provinsi = {$provinsi} ";
    if($kabkota > 0){
      $wStatusMutu .= " AND uid_kabkota = {$kabkota} ";
    }
    for ($i = 2; $i <= 2; $i++) {
      $sql = "SELECT COUNT(uid_indeks_ika_sungai) AS jumlah, status_mutu_" . $i . " FROM v_indeks_ika_sungai WHERE deleted = 0 AND uid_lokasi_pemantauan > 0 AND status_mutu_" . $i . " IS NOT NULL  AND tahun=" . $tahun . $wStatusMutu . " GROUP BY status_mutu_" . $i;
      $data = $this -> tables -> query($sql);

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

    return $rekap;
  }

  private function rataanIKU($tahun, $provinsi, $kabkota){
    $tahun = $tahun-1;
    $wdaerah = " AND uid_provinsi = {$provinsi} ";
    if($kabkota > 0){
      $wdaerah .= " AND uid_kabkota = {$kabkota} ";
    }
    $data = $this->db->fetch("SELECT peruntukan, AVG(CASE WHEN no2 > 0 THEN no2 END) AS no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS so2 FROM v_pelaporan_iku WHERE deleted = 0 AND YEAR(tanggal) = {$tahun} {$wdaerah} AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) GROUP BY peruntukan")["data"];
    $dataReturn = [];
    foreach ($data as $key => $value) {
      $dataReturn['no2']['parameter'] = 'no2';
      $dataReturn['no2']['peruntukan'][$key]['text'] = $value['peruntukan'];
      $dataReturn['no2']['peruntukan'][$key]['nilai'] = number_format($value['no2'],2);

      $dataReturn['so2']['parameter'] = 'so2';
      $dataReturn['so2']['peruntukan'][$key]['text'] = $value['peruntukan'];
      $dataReturn['so2']['peruntukan'][$key]['nilai'] = number_format($value['so2'],2);
    }
    return array_values($dataReturn);
  }


  public function detailOnModal(){
    $daerah   = explode("-",$this->params("d"));
    $provinsi_src = $daerah[1];
    $kabkota_src = $daerah[2];
    $uid = $this->params("x");
    if(is_numeric($uid)){
      $check = $this->db->fetch("SELECT * FROM dpsir_isian_pemda WHERE uid_dpsir_isian_pemda = {$uid}")["data"][0];
      $tahun = $check["tahun"]-1;

      $wdaerah = "";
      $wdaerah = " AND uid_provinsi = {$provinsi_src} ";
      if($kabkota_src > 0){
        $wdaerah .= " AND uid_kabkota = {$kabkota_src} ";
      }

      if($check["kode_isian"] == "IKA3"){
        $dataStatusMutu = $this->db->fetch("SELECT * FROM v_indeks_ika_sungai WHERE deleted = 0 AND tahun={$tahun} AND uid_lokasi_pemantauan > 0 {$wdaerah}");
        foreach ($dataStatusMutu['data'] as $key => $value) {
            $dataStatusMutu['data'][$key]['status_mutu_detail'] = json_decode($dataStatusMutu['data'][$key]['status_mutu_detail'], true);
            $idx1 = array_search(1, array_column($dataStatusMutu['data'][$key]['status_mutu_detail'], 'kelas'));
            $idx2 = array_search(2, array_column($dataStatusMutu['data'][$key]['status_mutu_detail'], 'kelas'));
            $idx3 = array_search(3, array_column($dataStatusMutu['data'][$key]['status_mutu_detail'], 'kelas'));
            $idx4 = array_search(4, array_column($dataStatusMutu['data'][$key]['status_mutu_detail'], 'kelas'));

            $dataStatusMutu['data'][$key]['pij1'] = $dataStatusMutu['data'][$key]['status_mutu_detail'][$idx1]['nilai_pij'];
            $dataStatusMutu['data'][$key]['pij2'] = $dataStatusMutu['data'][$key]['status_mutu_detail'][$idx2]['nilai_pij'];
            $dataStatusMutu['data'][$key]['pij3'] = $dataStatusMutu['data'][$key]['status_mutu_detail'][$idx3]['nilai_pij'];
            $dataStatusMutu['data'][$key]['pij4'] = $dataStatusMutu['data'][$key]['status_mutu_detail'][$idx4]['nilai_pij'];
        }

        $this->view->assign("data",$dataStatusMutu["data"]);
        $html = $this->view->fetch('parts/contents/dpsir/index/detail/ika.html');
        echo json_encode(array("statusCode"=>200,"htmls"=>$html));
      }elseif ($check["kode_isian"] == "IKU3"){
        $dataParameter = $this->db->fetch("SELECT nama_provinsi,nama_kabkota,alamat,alamat_detail,kode_lokasi,peruntukan, metode, AVG(CASE WHEN no2 > 0 THEN no2 END) AS no2, AVG(CASE WHEN so2 > 0 THEN so2 END) AS so2 FROM v_pelaporan_iku WHERE deleted = 0 AND YEAR(tanggal) = {$tahun} {$wdaerah} AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) GROUP BY uid_provinsi, uid_kabkota, uid_lokasi_pemantauan, peruntukan")["data"];
        $this->view->assign("data",$dataParameter);
        $html = $this->view->fetch('parts/contents/dpsir/index/detail/iku.html');
        echo json_encode(array("statusCode"=>200,"htmls"=>$html));
      }elseif ($check["isian_lampiran"]) {
        $this->view->assign("baseUrl",BASEURL);
        $this->view->assign("data",$check);
        $html = $this->view->fetch('parts/contents/dpsir/index/detail/lampiran.html');
        echo json_encode(array("statusCode"=>200,"htmls"=>$html));
      }
    }
  }

  public function export(){
    // die("hello");
    require_once('HtmlExcel.php');
    $xls = new HtmlExcel();

    $tahun = $this->params("t");
    $daerah   = explode("-",$this->params("d"));
    $provinsi = $daerah[1];
    $kabkota = $daerah[2];

    $namafile = "DPSIR";

    $provinsi_nama = $this->db->fetch("SELECT nama_propinsi FROM rf_provinsi WHERE kd_propinsi ={$provinsi}")["data"][0]["nama_propinsi"];
    $namafile .= "-".str_replace(" ","_",mb_strtoupper($provinsi_nama));
    $kabkota_nama = $this->db->fetch("SELECT nama_kabkot FROM rf_kabkota WHERE kd_kota ={$kabkota}")["data"][0]["nama_kabkot"];
    if($kabkota_nama){
      $namafile .= "-".str_replace(" ","_",mb_strtoupper($kabkota_nama));
    }


    $komponenList = array(1=>"iku",2=>"ika",3=>"ikl",4=>"ikeg",5=>"ikal");
    $kategoriList = ["DRIVER", "PRESSURE", "STATE", "IMPACT"];

    $total["main"] = [];
    $total["respon"] = [];
    foreach ($komponenList as $key => $value) {
      foreach ($kategoriList as $ki => $vi) {
        $data[$value][$vi]["main"] = $this->dataDpsir($tahun, $key, $vi, 0, $provinsi, $kabkota);
        $data[$value][$vi]["response"] = $this->dataDpsir($tahun, $key, $vi, 1, $provinsi, $kabkota);

        $total["main"][] = count($data[$value][$vi]["main"]);
        $total["respon"][] = count($data[$value][$vi]["response"]);
      }

    }
    // $this->debug->show($data);
    $this->view->assign("max_main", max($total["main"]));
    $this->view->assign("max_respon", max($total["respon"]));
    $this->view->assign("data",$data);
    $html = $this->view->fetch('parts/contents/dpsir/index/export.html');
    // die($html);
    $xls->addSheet("data", $html);
    $xls->headers($namafile.".xls");
    echo $xls->buildFile();
  }
  public function dataDpsir($tahun, $komponen, $kategori, $response, $provinsi, $kabkota){

    $gambut = 1;
    if((int)$komponen == 4){
      if($kabkota > 0){
        $gambut = $this->db->fetch("SELECT gambut FROM rf_kabkota WHERE kd_kota ={$kabkota}")["data"][0]["gambut"];
      }else {
        $gambut = $this->db->fetch("SELECT gambut FROM rf_provinsi WHERE kd_propinsi ={$provinsi}")["data"][0]["gambut"];
      }
    }

    if($response){
      $data = $this->db->fetch("SELECT * FROM dpsir_isian_pemda WHERE deleted = 0 AND isian_role IS NULL AND tahun = {$tahun} AND uid_komponen = {$komponen} AND uid_provinsi = {$provinsi} AND uid_kabkota = {$kabkota} AND parent > 0 AND kategori_isian = '".$kategori."' ORDER BY uid_dpsir_isian_pemda ASC ")["data"];
      foreach ($data as $key => $value) {
        $data[$key]["isian"] = str_replace(array("<br>","\r", "\n"),"<br style='mso-data-placement:same-cell;'>",$data[$key]["isian"]);
      }
    }else{
      $wstate = "";
      if($gambut != 1){
          $wstate .= " AND kode_isian NOT IN (IKEG1,IKEG2) ";
      }
      $data = $this->db->fetch("SELECT * FROM dpsir_isian_pemda WHERE deleted = 0 AND tahun = {$tahun} AND uid_komponen = {$komponen} AND uid_provinsi = {$provinsi} AND uid_kabkota = {$kabkota} AND parent = 0 AND kategori_isian = '".$kategori."' ORDER BY uid_dpsir_isian_pemda ASC ")["data"];
      foreach ($data as $key => $value) {
        $data[$key]["isian"] = str_replace(array("<br>","\r", "\n"),"<br style='mso-data-placement:same-cell;'>",$data[$key]["isian"]);
      }

      if($kategori == "STATE"){
        $statePusat = [];
        $statePusat = $this->db->fetch("SELECT * FROM dpsir_isian_pemda WHERE deleted = 0 AND isian_role = 'PUSAT' {$wstate} AND tahun = {$tahun} AND uid_komponen = {$komponen} AND uid_provinsi = 0 AND uid_kabkota = 0 AND parent = 0 AND kategori_isian = '".$kategori."' ORDER BY uid_dpsir_isian_pemda ASC ")["data"];

        $indeks = $this->getIndeks($tahun, $komponen, $provinsi, $kabkota);

        $jumlahStatusMutu = [];
        if($komponen == 2){
          $jumlahStatusMutu = $this->hitungStatusMutu($tahun, $provinsi, $kabkota);
        }
        $rataanIKU = [];
        if($komponen == 1){
          $rataanIKU = $this->rataanIKU($tahun, $provinsi, $kabkota);
        }
        $komponenIndeksList = array("IKU","IKA","IKL","IKEG","IKAL");
        foreach ($komponenIndeksList as $key => $value) {
          $idxKodeIndeks = array_search($value."1",array_column($statePusat,"kode_isian"));
          if(is_numeric($idxKodeIndeks)){
            $statePusat[$idxKodeIndeks]["isian"] = $statePusat[$idxKodeIndeks]["isian"]." ({$indeks[0]})";
            $statePusat[$idxKodeIndeks]["isian"] = str_replace(array("<br>","\r", "\n"),"<br style='mso-data-placement:same-cell;'>",$statePusat[$idxKodeIndeks]["isian"]);
            $statePusat[$idxKodeIndeks]["_nodetail"] = 1;
          }
          $idxKodeIndeks = array_search($value."2",array_column($statePusat,"kode_isian"));
          if(is_numeric($idxKodeIndeks)){
            $statePusat[$idxKodeIndeks]["isian"] = $statePusat[$idxKodeIndeks]["isian"]." ($indeks[1])";
            $statePusat[$idxKodeIndeks]["isian"] = str_replace(array("<br>","\r", "\n"),"<br style='mso-data-placement:same-cell;'>",$statePusat[$idxKodeIndeks]["isian"]);
            $statePusat[$idxKodeIndeks]["_nodetail"] = 1;
          }
          $idxKodeIndeks = array_search($value."3",array_column($statePusat,"kode_isian"));
          if(is_numeric($idxKodeIndeks) && $komponen == 1){
            foreach ($rataanIKU as $kriku => $vriku) {
              $statePusat[$idxKodeIndeks]["isian"] .= " <br><br> Rata-rata Konsentrasi ".mb_strtoupper($vriku['parameter']);
              foreach ($vriku['peruntukan'] as $kriku1 => $vriku1) {
                $statePusat[$idxKodeIndeks]["isian"] .= " <br> ".$vriku1['text']." : ".$vriku1['nilai'];
              }
            }
            $statePusat[$idxKodeIndeks]["isian"] = str_replace(array("<br>","\r", "\n"),"<br style='mso-data-placement:same-cell;'>",$statePusat[$idxKodeIndeks]["isian"]);
          }
          $idxKodeIndeks = array_search($value."3",array_column($statePusat,"kode_isian"));
          if(is_numeric($idxKodeIndeks) && $komponen == 2){
            $statePusat[$idxKodeIndeks]["isian"] .= " <br> MEMENUHI : ".$jumlahStatusMutu['kelas2']['memenuhi'];
            $statePusat[$idxKodeIndeks]["isian"] .= " <br> CEMAR BERAT : ".$jumlahStatusMutu['kelas2']['berat'];
            $statePusat[$idxKodeIndeks]["isian"] .= " <br> CEMAR RINGAN : ".$jumlahStatusMutu['kelas2']['ringan'];
            $statePusat[$idxKodeIndeks]["isian"] .= " <br> CEMAR SEDANG : ".$jumlahStatusMutu['kelas2']['sedang'];
            $statePusat[$idxKodeIndeks]["isian"] = str_replace(array("<br>","\r", "\n"),"<br style='mso-data-placement:same-cell;'>",$statePusat[$idxKodeIndeks]["isian"]);
          }
        }
        $data = array_merge($statePusat, $data);
      }
    }
    return $data;
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
  			// $this->debug->show($lockAction);
  			$this -> view -> assign("messageLock", $messageLock);
  			$this -> view -> assign("lockAction", $lockAction);
  			$this -> view -> assign("lockActionYear", $lockActionYear);
  	}

}
?>
