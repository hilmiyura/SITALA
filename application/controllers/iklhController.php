<?php
/**
 * created at 	: 11/12/2020
 * created by 	: dasendria team
 * desc		  	  : controller indeks iklh
 *
 */
class iklhController extends Front {
	public function init() {
		($this -> session -> get('memberIKLH') ? : $this -> redirect("login"));
		date_default_timezone_set("Asia/Jakarta");

		//SET CUSTOM VIEWS FOLDER
		$this -> view -> setFolder('be');

		//LOAD MODELS
		$this -> loadModel("tables");
		$this -> loadModel("ref");

		//load function
		require_once "functions.php";
		$this -> functions = new functions();
		$this -> view -> assign("functions", $this -> functions);

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
	public function index() {
		$this -> _indeksIklh();
		// $this->_indeksIklhOld();
		$this -> view -> assign("indeksActive", "active");
		$this -> view -> assign("show", $show);
		$this -> view -> assign("message", $message);
		$this -> view -> assign("title", 'Indeks Kualitas Lingkungan Hidup');
		$this -> view -> assign("icons", '<i class="ft-bar-chart-2"></i>');
		$this -> view -> display("index.html");
	}

	private function _indeksIklh() {
		$post = $this -> post();

		$w = "deleted = 0";
		$wTarget = "deleted = 0";
		if ($this -> me['role_user'] == 3) {
			$w .= " AND uid_kabkota=" . $this -> me['uid_kabkota'];
		} elseif ($this -> me['role_user'] == 2) {
			$w .= " AND uid_provinsi=" . $this -> me['uid_provinsi'];
		} elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
			$w .= " AND kd_regional=" . $this -> me['uid_regional'];

			if($this->me["uid_provinsi_lainnya"]){
				$provinsiLainnya = $this->me["uid_provinsi_lainnya"];
				$w .= " AND uid_provinsi IN ({$provinsiLainnya})";
			}
		}

		if ($this -> params('search')) {
			$post['search'] = TRUE;
			$post['form'] = json_decode(urldecode($this -> params('search')), 1);
		}

		if($this->params("y")){
			$post['search'] = TRUE;
			$post['form']['tahun'] = $this->params("y");
		}

		if (isset($post['search'])) {
			if ($post['form']['tahun']) {
				$post['form']['tahun'] = $post['form']['tahun'];
				$w .= " AND tahun=" . $post['form']['tahun'];
				$wTarget .= " AND tahun=" . $post['form']['tahun'];
				$wn = "deleted = 0 AND tahun=" . $post['form']['tahun'];
			}
			$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
			$this -> view -> assign("search", $post['form']);
		} else {
			$post['form']['tahun'] = ACTIVE_YEAR;
			$w .= " AND tahun=" . ACTIVE_YEAR;
			$wTarget .= " AND tahun=" . ACTIVE_YEAR;
			$wn = "deleted = 0 AND tahun=" . ACTIVE_YEAR;
			$this -> view -> assign("search", $post['form']);
		}

		$kabkota = $this -> tables -> query("SELECT * FROM v_indeks_history WHERE jenis_indeks = 0 AND deleted_kabkota = 0 AND " . $w." ORDER BY uid_kabkota ASC");
		$provinsi = $this -> tables -> query("SELECT * FROM v_indeks_history WHERE jenis_indeks = 1 AND " . $w." ORDER BY uid_provinsi ASC");
		$nasional = $this -> tables -> query("SELECT * FROM v_indeks_history WHERE jenis_indeks = 2 AND " . $wn);
		foreach ($kabkota['data'] as $k => $v) {
			// $wDataOldIndeks = $this->db->fetch("SELECT peta_sebaran_iklh FROM indeks_history WHERE deleted = 0 AND tahun = 2022 AND uid_provinsi=".$v['uid_provinsi']." AND uid_kabkota=".$v['uid_kabkota']." AND jenis_indeks =".$v['jenis_indeks'])['data'][0]['peta_sebaran_iklh'];
			// if($wDataOldIndeks){
			// 	$this->db->query("UPDATE indeks_history SET peta_sebaran_iklh = '".$wDataOldIndeks."' WHERE uid_indeks_history = ".$v['uid_indeks_history']);
			// }

			$kabkota_target = $this -> tables -> query("SELECT * FROM rf_target_iklh WHERE target = 1 AND " . $wTarget ." AND uid_kabkota = ".$v['uid_kabkota']);
			$kabkota['data'][$k]['target'] = $kabkota_target['data'][0]['iklh'];
			$kabkota['data'][$k]['target_iku'] = $kabkota_target['data'][0]['iku'];
			$kabkota['data'][$k]['target_ika'] = $kabkota_target['data'][0]['ika'];
			$kabkota['data'][$k]['target_ikal'] = $kabkota_target['data'][0]['ikal'];
			$kabkota['data'][$k]['target_ikl'] = $kabkota_target['data'][0]['ikl'];

		}
		foreach ($provinsi['data'] as $k => $v) {
			// $wDataOldIndeks = $this->db->fetch("SELECT peta_sebaran_iklh FROM indeks_history WHERE deleted = 0 AND tahun = 2022 AND uid_provinsi=".$v['uid_provinsi']." AND uid_kabkota=".$v['uid_kabkota']." AND jenis_indeks =".$v['jenis_indeks'])['data'][0]['peta_sebaran_iklh'];
			// if($wDataOldIndeks){
			// 	$this->db->query("UPDATE indeks_history SET peta_sebaran_iklh = '".$wDataOldIndeks."' WHERE uid_indeks_history = ".$v['uid_indeks_history']);
			// }


			$provinsi_target = $this -> tables -> query("SELECT * FROM rf_target_iklh WHERE target = 2 AND " . $wTarget ." AND uid_provinsi = ".$v['uid_provinsi']);
			$provinsi['data'][$k]['target'] = $provinsi_target['data'][0]['iklh'];
			$provinsi['data'][$k]['target_iku'] = $provinsi_target['data'][0]['iku'];
			$provinsi['data'][$k]['target_ika'] = $provinsi_target['data'][0]['ika'];
			$provinsi['data'][$k]['target_ikal'] = $provinsi_target['data'][0]['ikal'];
			$provinsi['data'][$k]['target_ikl'] = $provinsi_target['data'][0]['ikl'];
			// $this->debug->show($kabkota);
		}
		// $this->debug->show($kabkota);
		// $this->debug->show($provinsi);
		// $this->debug->show($nasional);

		$this -> _rf();
		if($this->params("ex") == "kabkota"){
			$this->dataExcel($kabkota, null);
		}elseif ($this->params("ex") == "provinsi"){
			$this->dataExcel(null, $provinsi);
		}else{
			// NILI INTERVENSI AOH
			if(isset($post['form']['tahun']) && $post['form']['tahun']==2022){
				$nasional['data'][0]['ikl'] = 60.72;
				$nasional['data'][0]['ika'] = 53.88;
				$nasional['data'][0]['iku'] = 88.06;
				$nasional['data'][0]['iklh'] = 72.42;
				// $nasional['data'][0]['iklh'] = (0.340 * round($nasional['data'][0]['ika'],2)) + (0.428 * round($nasional['data'][0]['iku'],2)) + (0.133 * round($nasional['data'][0]['ikl'],2)) + (0.099 * round($nasional['data'][0]['ikal'],2));
			}
			$this -> view -> assign("viewp", $provinsi['data']);
			$this -> view -> assign("viewk", $kabkota['data']);
			$this -> view -> assign("viewn", $nasional['data'][0]);
			// if($_SERVER['REMOTE_ADDR']=='103.144.175.182'){
			// 	$this->debug->show($nasional);
			// }
		}
	}

	public function exportData(){
		$this->_indeksIklh();
	}

	public function dataExcel($kabkota = null, $provinsi = null){

		//load function
		require_once "functions.php";
		$this -> functions = new functions();
		$this -> view -> assign("functions", $this -> functions);
		if($kabkota){
			$this -> view -> assign("viewk", $kabkota['data']);
			header("Content-type: application/vnd-ms-excel");
			header('Content-Disposition: attachment; filename="PELAPORAN_IKLH_KABKOTA'.time().'.xls"');
			$html = $this->view->fetch('parts/contents/iklh/index/excel_kabkota.html');
			echo $html;
		}elseif ($provinsi) {
			$this -> view -> assign("viewp", $provinsi['data']);
			header("Content-type: application/vnd-ms-excel");
			header('Content-Disposition: attachment; filename="PELAPORAN_IKLH_PROVINSI'.time().'.xls"');
			$html = $this->view->fetch('parts/contents/iklh/index/excel_provinsi.html');
			echo $html;
		}
	}

	// private function _indeksIklhOld(){
	//
	//   $post 		= $this->post();
	// 	if($this->params('search')){
	// 		$post['search'] = TRUE;
	// 		$post['form'] 	= json_decode(urldecode($this->params('search')),1);
	// 	}
	//   if(isset($post['search'])){
	// 		if($post['form']['tahun']){
	//       $post['form']['tahun'] = $post['form']['tahun'];
	// 		}
	// 		$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
	// 		$this->view->assign("search",$post['form']);
	// 	}else{
	// 		$post['form']['tahun'] = ACTIVE_YEAR;
	// 		$this->view->assign("search",$post['form']);
	// 	}
	//
	//   /*
	//   $sqlIka = "SELECT * FROM indeks_ika WHERE jenis_indeks = 1 ";
	//   $dataIka = $this->tables->query($sqlIka);
	//   $sqlIku = "SELECT * FROM indeks_iku WHERE jenis_indeks = 1 ";
	//   $dataIku = $this->tables->query($sqlIku);
	//   $sqlIktl = "SELECT * FROM indeks_iktl WHERE jenis_indeks = 1 ";
	//   $dataIktl = $this->tables->query($sqlIktl);
	//   $sqlIkal = "SELECT * FROM indeks_ikal WHERE jenis_indeks = 1 AND uid_lokasi = 0 ";
	//   $dataIkal = $this->tables->query($sqlIkal);
	//   $mergeData = array_merge($dataIka['data'],$dataIku['data'],$dataIktl['data'],$dataIkal['data']);
	//   $key = array_search(1, array_column($mergeData, 'uid_provinsi'));
	//   php verion does not support function array_cloumn -
	//   */
	//   // $this->debug->show($this->me);
	//   if($this->me['role_user'] == 3){
	//     $wKabkota ="WHERE a.kd_kota =".$this->me['uid_kabkota'];
	//     $wProvinsi ="WHERE a.kd_kota =".$this->me['uid_kabkota'];
	//   }elseif ($this->me['role_user'] == 2) {
	//     $wKabkota ="WHERE a.kd_provinsi =".$this->me['uid_provinsi'];
	//     $wProvinsi ="WHERE a.kd_propinsi =".$this->me['uid_provinsi'];
	//   }elseif ($this->me['role_user'] == 4) {
	//     $wKabkota ="WHERE b.kd_regional =".$this->me['uid_regional'];
	//     $wProvinsi ="WHERE a.kd_regional =".$this->me['uid_regional'];
	//   }
	//
	//   $sqlIklhProv = "ABS(
	//               (0.340 * if((SELECT nilai_indeks FROM indeks_ika WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi) > 0,(SELECT nilai_indeks FROM indeks_ika WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi),0 )) +
	//               (0.428 * if((SELECT nilai_indeks FROM indeks_iku WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi)> 0,(SELECT nilai_indeks FROM indeks_iku WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi),0 )) +
	//               (0.133 * if((SELECT nilai_indeks FROM indeks_iktl WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi)> 0,(SELECT nilai_indeks FROM indeks_iktl WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi),0 )) +
	//               (0.099 * if((SELECT nilai_indeks FROM indeks_ikal WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi)> 0,(SELECT nilai_indeks FROM indeks_ikal WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi),0 ))) AS iklh";
	//   $sqlProvinsi ="SELECT a.*,
	//                   (SELECT nilai_indeks FROM indeks_ika WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi) AS ika,
	//                   (SELECT nilai_indeks FROM indeks_iku WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi) AS iku,
	//                   (SELECT nilai_indeks FROM indeks_iktl WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi) AS ikl,
	//                   (SELECT nilai_indeks FROM indeks_ikal WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=1 AND uid_provinsi=a.kd_propinsi) AS ikal,
	//                   ".$sqlIklhProv."
	//                   FROM rf_provinsi a ".$wProvinsi;
	//   $provinsi = $this->tables->query($sqlProvinsi);
	//
	//   $sqlIklhKabkota = "ABS(
	//               (0.376 * if((SELECT nilai_indeks FROM indeks_ika WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota) > 0,(SELECT nilai_indeks FROM indeks_ika WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota),0 )) +
	//               (0.405 * if((SELECT nilai_indeks FROM indeks_iku WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota) > 0,(SELECT nilai_indeks FROM indeks_iku WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota),0 )) +
	//               (0.219 * if((SELECT nilai_indeks FROM indeks_iktl WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota)> 0,(SELECT nilai_indeks FROM indeks_iktl WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota),0 ))) AS iklh";
	//   $sqlKabkota="SELECT a.*,b.nama_propinsi,
	//                   (SELECT nilai_indeks FROM indeks_ika WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota) AS ika,
	//                   (SELECT nilai_indeks FROM indeks_iku WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota) AS iku,
	//                   (SELECT nilai_indeks FROM indeks_iktl WHERE tahun=".$post['form']['tahun']." AND jenis_indeks=0 AND uid_kabkota=a.kd_kota) AS ikl,
	//                   ".$sqlIklhKabkota."
	//                   FROM rf_kabkota a
	//                   LEFT JOIN rf_provinsi b ON a.kd_provinsi = b.kd_propinsi ". $wKabkota;
	//   $kabkota = $this->tables->query($sqlKabkota);
	//
	//   $this->view->assign("viewp",$provinsi['data']);
	//   $this->view->assign("viewk",$kabkota['data']);
	//   $this->_rf();
	//
	// }

	private function _rf() {
		$rating = $this -> tables -> query("SELECT * FROM rf_rating_iklh WHERE deleted = 0");
		// $this->debug->show($rating);
		$this -> view -> assign("rating", $rating['data']);
	}

}
?>
