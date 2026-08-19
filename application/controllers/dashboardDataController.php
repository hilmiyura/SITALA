<?php
/**
 * created at 	: 25/04/2024
 * created by 	: dasendria team
 * desc		  	: controller data index IKLHK
 *
 */
  class dashboardDataController extends Front
  {
    public function init()
    {
      // die("maintenance");
      // ini_set("display_errors",true);
        ($this -> session -> get('memberIKLH') ?: $this -> redirect("login"));

        //SET CUSTOM VIEWS FOLDER
        $this -> view -> setFolder('be');

        //LOAD MODELS
        $this -> loadModel("tables");
        $this -> loadModel("ref");

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
        if ($this->params("n")) {
            $this->view->assign("news",1);
        }
    }

    public function index(){
      $Y = $this->params("x") ? $this->params("x") : ACTIVE_YEAR;
      $sumary = $this->_summary($Y);
      $this->info();

      $this->view->assign("sumary", $sumary);
      $this -> view -> assign("title", 'Dashboard');
      $this -> view -> assign('maintenance', false);
      $this -> view -> assign("tahunAktif", $Y);
      $this -> view -> assign("icons", '<i class="ft-home"></i>');
      $this -> view -> display("index.html");
    }

    private function info(){
      if ($this->me['role_user'] == 2 || $this->me['role_user'] == 3) {
        $where = "deleted = 0 AND periode=".date('Y')." AND
                  (kepala_daerah IS NOT NULL OR kepala_daerah != '') AND
                  (kepala_dprd IS NOT NULL OR kepala_dprd != '') AND
                  (luas_wilayah IS NOT NULL OR luas_wilayah != '') AND
                  (populasi IS NOT NULL OR populasi != '')  AND
                  (kategori_daerah IS NOT NULL OR kategori_daerah != '') ";
        $where .= ($this->me['uid_provinsi'] ? " AND uid_provinsi=".$this->me['uid_provinsi'] : "");
        $where .= ($this->me['uid_kabkota'] ? " AND uid_kabkota=".$this->me['uid_kabkota'] : "");
        $checkProfile = $this->db->fetch("SELECT * FROM users_detail_periode WHERE ".$where);
        if($checkProfile['total'] == 0){
          $this->view->assign("update_profile", 1);
        }
      }

      $this->tables->set('pengumuman', 'uid_pengumuman');
      $data = $this->tables->fetch('deleted = 0 AND status = 1 ORDER BY tanggal DESC LIMIT 3');
      $this -> view -> assign("pengumuman", $data['data']);
    }

    private function _summary($Y)
    {
        $w = 'deleted=0 AND hidden=0';
        if ($this->params(tahun) && is_numeric($this->params(tahun))) {
            $w .= " AND YEAR(tanggal) ='" . $this->params(tahun) . "'";
            $Y	= $this->params(tahun);
        } else {
            $w .= " AND YEAR(tanggal) ='" . $Y . "'";
        }
        $this -> view -> assign("year", $Y);

        if ($this->me['role_user']==2) {
            $w .= ' AND uid_provinsi='.$this->me['uid_provinsi'];
        } elseif ($this->me['role_user']==3) {
            $w .= ' AND uid_kabkota='.$this->me['uid_kabkota'];
        } elseif ($this->me['role_user']==4 || $this -> me['role_user'] == 5) {
            $w .= ' AND kd_regional='.$this->me['uid_regional'];
        }

        $this->_years();
        $data["sumary"]["iku"] = $this->_sumaryAspek($w,'iku');
        $data["sumary"]["ika"] = $this->_sumaryAspek($w,'ika');
        $data["sumary"]["ikal"] = $this->_sumaryAspek($w,'ikal');
        $data["sumary"]["ikl"] = $this->_sumaryAspek($w,'iktl');
        return $data["sumary"];
    }

    private function _sumaryAspek($w, $aspek){

      $tabel = "pelaporan_".$aspek;
      $view = "v_".$tabel;
      $primary = "uid_".$tabel;

      $result 			= array(
        'total'=>0,
        'verify'=>0,
        'unverify'=>0,
        'reject'=>0,
        'update'=>'',
        'persen'=>0,
        'persen_un'=>0,
        'persen_re'=>0
      );
      //TOTAL DATA
      $sql 			= 'SELECT COUNT('.$primary.') AS x FROM v_'.$tabel.' WHERE '.$w;
      $data			= $this->db->query($sql);
      $result['total'] 	= (int)$data->fields['x'];
      //VERIFIED
      $sql 			= 'SELECT COUNT('.$primary.') AS x FROM v_'.$tabel.' WHERE '.$w.' AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)';
      $data			= $this->db->query($sql);
      $result['verify']	= (int)$data->fields['x'];
      //REJECTED
      $sql 			= 'SELECT COUNT('.$primary.') AS x FROM v_'.$tabel.' WHERE '.$w.' AND (v_pusat = 2 OR v_regional = 2 OR v_provinsi = 2)';
      $data			= $this->db->query($sql);
      $result['reject']	= (int)$data->fields['x'];
      //LAST UPDATE
      $sql 			= 'SELECT MAX(crdate) x, MAX(chdate) AS y FROM v_'.$tabel.' WHERE '.$w;
      $data			= $this->db->query($sql);
      $result['update'] 	= ($data->fields['y'] ? $data->fields['y'] : $data->fields['x']);
      $result['update']	= ($data->fields['x'] ? date('d-m-Y : H:i', $result['update']) : '-');
      //UNVERIFY
      if ($result['total']) {
          $result['unverify']	= $result['total'] - ($result['verify'] + $result['reject']);
      }
      //PERSEN
      if ($result['total']) {
          $result['persen']	= ($result['verify'] / $result['total']) * 100;
          $result['persen_un']	= ($result['unverify'] > 0 ? ($result['unverify'] / $result['total']) * 100 : 0);
          $result['persen_re']	= ($result['reject'] > 0 ? ($result['reject'] / $result['total']) * 100 : 0);
      }
      return $result;
    }

    private function _years()
    {
        for ($i=ACTIVE_YEAR;$i>=(ACTIVE_YEAR-2);$i--) {
            $years[] = $i;
        }
        // $this->debug->show($years);
        $this -> view -> assign("years", $years);
    }

    // ajax maps
    public function lokasiByProvinsi(){
      $tahun = $this->params("y");
      $sql = "SELECT COUNT(a.uid_lokasi_pemantauan) AS total, a.uid_provinsi, b.nama_propinsi AS nama, b.latitude, b.longitude
              FROM lokasi_pemantauan a JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi WHERE a.deleted=0 AND a.tahun LIKE '%".$tahun."%' GROUP BY a.uid_provinsi";
      $data = $this->db->fetch($sql);
      echo json_encode($data);
    }
    public function lokasiByKabkota(){
      $uid_provinsi = $this->params("x");
      $tahun = $this->params("y");
      if(is_numeric($uid_provinsi)){
        $sql = "SELECT COUNT(a.uid_lokasi_pemantauan) AS total, a.uid_provinsi, b.nama_propinsi AS nama_provinsi, a.uid_kabkota, c.nama_kabkot AS nama, c.latitude, c.longitude
                FROM lokasi_pemantauan a
                JOIN rf_provinsi b ON b.kd_propinsi = a.uid_provinsi
                JOIN rf_kabkota c ON c.kd_kota = a.uid_kabkota
                WHERE a.deleted=0 AND a.uid_provinsi =".$uid_provinsi." AND a.tahun LIKE '%".$tahun."%' GROUP BY a.uid_kabkota";
        $data = $this->db->fetch($sql);
        echo json_encode($data);
      }else{
        echo json_encode(array("data"=>[], "total"=>0));
      }
    }
    public function sebaranTitik(){
      $uid_kabkota = $this->params("x");
      $tahun = $this->params("y");
      $sql = "SELECT uid_lokasi_pemantauan, alamat AS nama, alamat_detail, latitude, longitude, uid_rf_pelaksana, name_pelaksana, name AS jenis, kode_lokasi FROM v_lokasi_pemantauan WHERE deleted = 0 AND tahun LIKE '%".$tahun."%' AND uid_kabkota=".$uid_kabkota;
      $data = $this->db->fetch($sql);
      echo json_encode($data);
    }

    public function getSebaranTitik(){
      // if($_SERVER['REMOTE_ADDR'] == '180.252.95.48'){
      //   $sql = "SELECT * FROM v_lokasi_pemantauan WHERE deleted = 0";
      //   $data = $this->db->fetch($sql);
      //   echo json_encode($data);
      // }
    }

    public function getGeo(){
      $path = DOCROOT."application/views/be/assets/assets/leaflet/provinsi.json";
      // die($path);
      $jsonString = file_get_contents($path);
      $jsonData = json_decode($jsonString, true);
      foreach ($jsonData['features'] as $key => $value) {
        $value["properties"]["NAME_1"] = trim($value["properties"]["NAME_1"]);
        // if($value["properties"]["NAME_1"]){
        //   $this->debug->show($value);
        // }
        if((int)$value["id"] > 30){
          $jsonData['features'][$key]["properties"]["COLOR"] = 1;
        }elseif ((int)$value["id"] > 27) {
          $jsonData['features'][$key]["properties"]["COLOR"] = 2;
        }elseif ((int)$value["id"] > 20) {
          $jsonData['features'][$key]["properties"]["COLOR"] = 3;
        }elseif ((int)$value["id"] > 10) {
          $jsonData['features'][$key]["properties"]["COLOR"] = 4;
        }elseif ((int)$value["id"] > 0) {
          $jsonData['features'][$key]["properties"]["COLOR"] = 5;
        }

      }
      echo json_encode($jsonData);

      // success rgb(94 216 79); sangat baik
      // danger rgb(250 98 107); kurang
      // info rgb(40 175 208); baik
      // warning rgb(253 185 1); sedang
      // dark rgb(70 72 85); sangat kurang
    }

  }
?>
