<?php

/**
 * created at : 18/11/2022
 * created by : Dasendria team
 * desc : controller for raport IKLH
 */
class raportController extends Front {
	public function init() {
		$showRaportQr = base64_decode($this->params("xq"),TRUE);
		$this->arrQr = explode("/",$showRaportQr);
		// $this->debug->show($this->arrQr);
		if(count($this->arrQr) >= 10){
			$this->contentOnly = 1;
			$this->view->assign("contentOnly", 1);
		}else{
			($this -> session -> get('memberIKLH') ? : $this -> redirect("login"));
		}
		// ini_set("display_errors",TRUE);

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
		$this -> viewName = "";
		$this -> primaryKey = "";
		$this -> where = "deleted = 0";

		$this->dev = "1";
		if($_SERVER['REMOTE_ADDR'] == '103.144.175.182'){
			$this->dev = 1;
		}
		$this->view->assign("dev", $this->dev);
	}
  public function index(){
		$uidProvinsi = (count($this->arrQr) >= 10 ? $this->arrQr[5] : $this->params("p"));
		$uidKabkota = (count($this->arrQr) >= 10 ? $this->arrQr[3] : $this->params("k"));
		$tahun = (count($this->arrQr) >= 10 ? $this->arrQr[7] : $this->params("t"));
		$group = (count($this->arrQr) >= 10 ? $this->arrQr[1] : $this->params("x"));
		$keyUrlQr = "x/".$group."/k/".$uidKabkota."/p/".$uidProvinsi."/t/".$tahun."/s/1";
		$valueUrlQr = APP_IKLH."uploads/raport/"."raport-".$group."-".$tahun."-".$uidProvinsi. "-" .$uidKabkota.".pdf?v".time();
		$urlQr = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=".$valueUrlQr."&choe=UTF-8";

		$this->view->assign("keyQr",$keyUrlQr);
		// if($_SERVER['REMOTE_ADDR'] == '103.144.175.182'){
		// 	$this->view->assign("urlQr",$urlQr);
		// }
		$this->view->assign("urlQr",$urlQr);
		$dataRaport['urlQr'] = $urlQr;

		$group = ($group == "iklhk" ? "iklh" : $group);
		if($tahun >= 2023){
			$groupList = array(
				"iku"=>"IKU",
				"ika"=>"IKA",
				"ikal"=>"IKAL",
				"ikl"=>"IKL",
				"iklh"=>"IKLH"
			);
		}else{
			$groupList = array(
				"iku"=>"Indeks Kualitas Udara",
				"ika"=>"Indeks Kualitas Air",
				"ikal"=>"Indeks Kualitas Air Laut",
				"ikl"=>"Indeks Kualitas Lahan",
				"iklh"=>"Indeks Kualitas Lingkungan Hidup"
			);
		}
		// $groupListIndeks = array(
		// 	"iku"=>"Langit Biru",
		// 	"ika"=>"Kali Bersih",
		// 	"ikal"=>"Pantai Lestari",
		// 	"ikl"=>"Indonesia Hijau"
		// );
		$groupListIndeks = array(
			"iku"=>"Udara",
			"ika"=>"Air",
			"ikal"=>"Air Laut",
			"ikl"=>"Tutupan Lahan"
		);
		$dataShow = 0;

		$w = "deleted = 0 AND tahun=".$tahun;
		$wUser = "deleted = 0";

		if(is_numeric($uidProvinsi) && (is_numeric($uidKabkota)) && $uidKabkota > 0){
			$w .= " AND uid_provinsi = ".$uidProvinsi. " AND uid_kabkota = ".$uidKabkota;
			$wUser .= " AND uid_provinsi = ".$uidProvinsi. " AND uid_kabkota = ".$uidKabkota;
			$dataShow = 2;
		}elseif (is_numeric($uidProvinsi)) {
			$w .= " AND uid_provinsi = ".$uidProvinsi. " AND uid_kabkota = 0";
			$wUser .= " AND uid_provinsi = ".$uidProvinsi. " AND uid_kabkota = 0";
			$dataShow = 1;
		}
		if($dataShow == 0) die("url tidak dikenal sistem");

		$dataIndeks = $this->tables->query("SELECT * FROM v_indeks_history WHERE ".$w)['data'][0];
		$dataRaport['indeks']['gambut'] = ($dataShow == 2 ? $dataIndeks['gambut_kabkota'] : $dataIndeks['gambut_provinsi']);
		$dataRaport['indeks']['nama_provinsi'] = $dataIndeks['nama_provinsi'];
		$dataRaport['indeks']['nama_kabkota'] = $dataIndeks['nama_kabkota'];
		$dataRaport['indeks']['kd_regional'] = $dataIndeks['kd_regional'];
		$dataRaport['indeks']['peta_sebaran'] = $dataIndeks['peta_sebaran_'.$group];
		$dataRaport['indeks']['logo'] = ($dataShow == 2 ? "logo-kabkota/".$dataIndeks['logo_kabkota'] : "logo-provinsi/".$dataIndeks['logo_provinsi']);
		$dataRaport['indeks']['rekomendasi'] = $dataIndeks['rekomendasi_'.$group].' '.($dataIndeks['rekomendasi_'.$group.'_select'] ? '<br>'.str_replace('|','<br>',$dataIndeks['rekomendasi_'.$group.'_select']) : '');
		if($group == "iklh"){
			$dataUser = $this->db->fetch("SELECT IF(kepala_daerah != '',kepala_daerah,'-') AS kepala_daerah,IF(kepala_dprd !='',kepala_dprd,'-') AS kepala_dprd,IF(kategori_daerah != '',kategori_daerah,'-') AS kategori_daerah ,IF(luas_wilayah !='',luas_wilayah,'-') AS luas_wilayah ,IF(populasi !='',populasi,'-') AS populasi ,IF(gdp !='',gdp,'-') AS gdp FROM users_detail_periode WHERE {$wUser} AND periode = {$tahun} ")["data"][0];

			// $sqlUser = "SELECT IF(kepala_daerah != '',kepala_daerah,'-') AS kepala_daerah,IF(kepala_dprd !='',kepala_dprd,'-') AS kepala_dprd,IF(kategori_daerah != '',kategori_daerah,'-') AS kategori_daerah ,IF(luas_wilayah !='',luas_wilayah,'-') AS luas_wilayah ,IF(populasi !='',populasi,'-') AS populasi ,IF(gdp !='',gdp,'-') AS gdp FROM users WHERE ".$wUser." ".($dataShow == 2 ? " AND role_user = 3" : " AND role_user = 2" );
			// $dataUser = $this->tables->query($sqlUser)['data'][0];
			// $dataIkl = $this->tables->query("SELECT json_data FROM indeks_iktl WHERE deleted = 0 AND tahun=".$tahun." AND uid_kabkota=".($dataShow == 2 ? $uidKabkota : 0)." AND uid_provinsi=".$uidProvinsi)['data'][0]['json_data'];
			// $dataUser['luas_wilayah'] = "-";
			// if($dataIkl){
			// 	$luasWilayahIkl = json_decode($dataIkl,TRUE)['luas_wilayah'];
			// 	if((float)$luasWilayahIkl > 0){
			// 		$dataUser['luas_wilayah'] = $luasWilayahIkl;
			// 	}
			// }

			$dataRaport['indeks']['user'] = $dataUser;
		}

		if($dataShow == 2){
			$wPeringkat = "deleted = 0 AND tahun=".$tahun." AND uid_kabkota > 0 AND uid_provinsi=".$uidProvinsi;
			$wPeringkatNasional = "deleted = 0 AND tahun=".$tahun." AND uid_kabkota > 0 AND jenis_indeks = 0";
		}else{
			$wPeringkat = "deleted = 0 AND tahun=".$tahun." AND uid_kabkota = 0 AND kd_regional=".$dataRaport['indeks']['kd_regional'];
			$wPeringkatNasional = "deleted = 0 AND tahun=".$tahun." AND uid_kabkota = 0 AND uid_provinsi > 0 AND jenis_indeks = 1";
		}

		$dataRaport['indeks']['nilai_indeks'] = $dataIndeks[$group];
		$dataRaport['indeks']['aspek'] = $groupList[$group];
		$dataRaport['indeks']['aspek_indeks'] = $groupListIndeks[$group];
		$peringkat = $this->tables->query("SELECT uid_provinsi, uid_kabkota, FORMAT(".$group.",2) AS ".$group." FROM v_indeks_history WHERE ".$wPeringkat." ORDER BY FORMAT( IF(".$group." >= 100, 99.99 , ".$group."),2) DESC");
		$peringkatGroup = $this->tables->query("SELECT FORMAT(".$group.",2) AS ".$group." FROM v_indeks_history WHERE ".$wPeringkat." GROUP BY FORMAT(".$group.",2) ORDER BY FORMAT( IF(".$group." >= 100, 99.99 , ".$group."),2) DESC");
		$peringkatNasional = $this->tables->query("SELECT uid_provinsi, uid_kabkota, FORMAT(".$group.",2) AS ".$group." FROM v_indeks_history WHERE ".$wPeringkatNasional." ORDER BY FORMAT( IF(".$group." >= 100, 99.99 , ".$group."),2) DESC");
		$peringkatNasionalGroup = $this->tables->query("SELECT FORMAT(".$group.",2) AS ".$group." FROM v_indeks_history WHERE ".$wPeringkatNasional." GROUP BY FORMAT(".$group.",2) ORDER BY FORMAT( IF(".$group." >= 100, 99.99 , ".$group."),2) DESC");

		$dataPosisiIKLH = $this->posisiIndeksKualitasLingkunganhidup($uidKabkota,$uidProvinsi,$tahun,$dataShow, $group);
		$dataRaport['posisiIndeks'] = $dataPosisiIKLH['data'];
		$dataRaport['posisiIndeksAVG'] = $dataPosisiIKLH['avg'];
		$dataRaport['trenIklh'] = $this->trenIklh($uidKabkota,$uidProvinsi,$tahun,$dataShow, $group);
		$dataRaport['titikPantau'] = $this->titikPantau($uidKabkota,$uidProvinsi,$tahun,$dataShow, $group);
		if($tahun >= 2024){
			$dataRaport['aspek'] = $this->indeksrespon($uidKabkota,$uidProvinsi,$tahun,$dataShow, $group);

		}else{
			$dataRaport['aspek'] = $this->aspek($uidKabkota,$uidProvinsi,$tahun,$dataShow, $group);
			// $this->debug->show($dataRaport['aspek']);
		}
		if($tahun >= 2024){
			$dataRaport['irBobot'] = $this->indeksResponKriteria($uidKabkota,$uidProvinsi,$tahun,$dataShow);

			$historynilai = $this->db->fetch("SELECT ir_lb AS iku, ir_kb AS ika, ir_pl AS ikal, ir_ih AS ikl, ir_gl AS ikeg, ir_lh AS iklh FROM indeks_history WHERE tahun={$tahun} AND uid_provinsi={$uidProvinsi} AND uid_kabkota={$uidKabkota} AND deleted=0")["data"][0];

			$dataRaport['irBobot']['iklh'] = $historynilai['iklh'];
		}elseif($tahun == 2023){
			$dataRaport['irBobot'] = $this->indeksReponBobot($tahun, $uidKabkota, $uidProvinsi);
		}

		if($group == "ikl"){
			if($tahun >= 2024){
				$dataRaport['aspek_gambut'] = $this->indeksrespon($uidKabkota,$uidProvinsi,$tahun,$dataShow, "ikeg");
			}else{
				$dataRaport['aspek_gambut'] = $this->aspek($uidKabkota,$uidProvinsi,$tahun,$dataShow, "ikeg");
			}
			$this->view->assign("grafGambut",1);
		}
		$dataMaps = $this->maps($uidKabkota,$uidProvinsi,$tahun,$dataShow, $group);
		$dataRaport['maps'] = $dataMaps['data'];
		$dataRaport['mapsset'] = $dataMaps['latlng'];

		$idxData = '';
		if($dataShow == 2){
			$idxData = array_search($uidKabkota,array_column($peringkat['data'],'uid_kabkota'));
			$idxDataPeringkat = array_search($peringkat['data'][$idxData][$group], array_column($peringkatGroup['data'], $group));
			$idxData = $idxDataPeringkat;

			$idxDataNasional = array_search($uidKabkota,array_column($peringkatNasional['data'],'uid_kabkota'));
			$idxDataNasionalPeringkat = array_search($peringkatNasional['data'][$idxDataNasional][$group], array_column($peringkatNasionalGroup['data'], $group));
			$idxDataNasional = $idxDataNasionalPeringkat;

		}else{
			$idxData = array_search($uidProvinsi,array_column($peringkat['data'],'uid_provinsi'));
			$idxDataPeringkat = array_search($peringkat['data'][$idxData][$group], array_column($peringkatGroup['data'], $group));
			$idxData = $idxDataPeringkat;

			$idxDataNasional = array_search($uidProvinsi,array_column($peringkatNasional['data'],'uid_provinsi'));
			$idxDataNasionalPeringkat = array_search($peringkatNasional['data'][$idxDataNasional][$group], array_column($peringkatNasionalGroup['data'], $group));
			$idxDataNasional = $idxDataNasionalPeringkat;

		}

		if(is_numeric($idxData)){
			$dataRaport['indeks']['peringkat_nasional'] = $idxDataNasional + 1;
			$dataRaport['indeks']['peringkat_nasional_dari'] = $peringkatNasional['total'];
			$dataRaport['indeks']['peringkat'] = $idxData + 1;
			$dataRaport['indeks']['peringkat_dari'] = $peringkat['total'];
		}else{
			$dataRaport['indeks']['peringkat_nasional'] = "-";
			$dataRaport['indeks']['peringkat_nasional_dari'] = $peringkatNasional['total'];
			$dataRaport['indeks']['peringkat'] = "-";
			$dataRaport['indeks']['peringkat_dari'] = $peringkat['total'];
		}
		$dataRaport['tahun'] = $tahun;
		if($tahun >= 2023){
			$dataRaport['file_raport'] = 'template2.html';
			$dataRaport['file_raport_print'] = 'templatePrint2.html';
		}else{
			$dataRaport['file_raport'] = 'template1.html';
			$dataRaport['file_raport_print'] = 'templatePrint.html';
		}
		$dataRaport['indeks']['nilai_indeks_repon'] = $dataRaport['irBobot'][$group];
    // ini_set("display_errors",TRUE);

		// if($_SERVER['REMOTE_ADDR'] == '180.251.182.12'){
		// 	$this->debug->show($dataRaport);
		// }

		$this->_rf();
		$this -> view -> assign("tahunRaport", $tahun);
		$this -> view -> assign("dataRaport", $dataRaport);
		$this -> view -> assign("dataRaportJs", json_encode($dataRaport));
		$this -> view -> assign("dataShow", $dataShow);
		$this -> view -> assign("group", $group);
		$this -> view -> assign("icons", '<i class="la la-book"></i>');
		$this -> view -> assign("title", 'Raport Nilai IKLH');
		if($this->contentOnly == 1){
			$this -> view -> display("contentOnly.html");
		}else{
			$this -> view -> display("index.html");
		}
  }

	private function _rf() {
		$rating = $this -> tables -> query("SELECT * FROM rf_rating_iklh WHERE deleted = 0");
		$this -> view -> assign("rating", $rating['data']);
	}

	public function posisiIndeksKualitasLingkunganhidup($uidKabkota,$uidProvinsi,$tahun,$dataShow, $target){
		$avg['nasional']['start'] = 0;
		$avg['nasional']['end'] = 0;
		$avg['daerah']['start'] = 0;
		$avg['daerah']['end'] = 0;

		if($dataShow == 2){
			$data = $this->tables->query("SELECT  uid_kabkota AS id, ".$target." AS value FROM v_indeks_history WHERE deleted = 0 AND tahun=".$tahun. " AND uid_kabkota > 0 ORDER BY ".$target.",uid_kabkota  ASC");
			$idx = array_search($uidKabkota, array_column($data['data'],'id'));
			if(is_numeric($idx)){
				$data['data'][$idx]['color'] = 1;
			}

			$dataAvgnasional = $this->tables->query("SELECT AVG(".$target.") AS value, STDDEV_POP(".$target.") AS value_stdev FROM v_indeks_history WHERE deleted = 0 AND tahun=".$tahun. " AND uid_kabkota > 0 ORDER BY AVG(".$target.") ASC");
			// $dataStdevnasional = $this->tables->query("SELECT STDDEV_POP(".$target.") AS value FROM v_indeks_history WHERE deleted = 0 AND tahun=".$tahun. " AND uid_kabkota > 0 ORDER BY ".$target." ASC");
			$dataAvg = $this->tables->query("SELECT AVG(".$target.") AS value FROM v_indeks_history WHERE deleted = 0 AND tahun=".$tahun. " AND uid_provinsi=".$uidProvinsi." ORDER BY ".$target." ASC");
			$avg['nasional']['start'] = ($dataAvgnasional['data'][0]['value']?$dataAvgnasional['data'][0]['value']:0);
			$avg['nasional']['end'] = ($avg['nasional']['start']?($avg['nasional']['start']+0.3):0);
			$avg['daerah']['start'] = ($dataAvg['data'][0]['value']?$dataAvg['data'][0]['value']:0);
			$avg['daerah']['end'] = ($avg['daerah']['start']?($avg['daerah']['start']+0.3):0);

			foreach ($data['data'] as $key => $value) {
				$z = ($value['value']-$avg['nasional']['start'])/$dataAvgnasional['data'][0]['value_stdev'];
				$data['data'][$key]['norm_dist'] = $this->_stats_dens_normal($value['value'], $dataAvgnasional['data'][0]['value'], $dataAvgnasional['data'][0]['value_stdev']);
				$data['data'][$key]['norm_dist'] = round($data['data'][$key]['norm_dist'],5);
				$data['data'][$key]['norm_dist'] = floatval($data['data'][$key]['norm_dist']);
			}
			// $this->debug->show($dataAvgnasional);
		}else{
			$data = $this->tables->query("SELECT kd_regional ,uid_provinsi AS id, ".$target." AS value FROM v_indeks_history WHERE deleted = 0 AND tahun=".$tahun. " AND uid_provinsi > 0 AND uid_kabkota = 0 ORDER BY ".$target.",uid_provinsi ASC");
			$dataAvgnasional = $this->tables->query("SELECT AVG(".$target.") AS value, STDDEV_POP(".$target.") AS value_stdev FROM v_indeks_history WHERE deleted = 0 AND tahun=".$tahun. " AND uid_provinsi > 0 AND uid_kabkota = 0 ORDER BY AVG(".$target.") ASC");
			// $dataStdevnasional = $this->tables->query("SELECT STDDEV_POP(".$target.") AS value FROM v_indeks_history WHERE deleted = 0 AND tahun=".$tahun. " AND uid_provinsi > 0 AND uid_kabkota = 0 ORDER BY ".$target." ASC");
			$idx = array_search($uidProvinsi, array_column($data['data'],'id'));
			if(is_numeric($idx)){
				$dataAvg = $this->tables->query("SELECT AVG(".$target.") AS value FROM v_indeks_history WHERE deleted = 0 AND tahun=".$tahun. " AND kd_regional=".$data['data'][$idx]['kd_regional']." AND uid_kabkota = 0 ORDER BY ".$target." ASC");
				$data['data'][$idx]['color'] = 1;
			}
			$avg['nasional']['start'] = ($dataAvgnasional['data'][0]['value']?$dataAvgnasional['data'][0]['value']:0);
			$avg['nasional']['end'] = ($avg['nasional']['start']?($avg['nasional']['start']+0.3):0);
			$avg['daerah']['start'] = ($dataAvg['data'][0]['value']?$dataAvg['data'][0]['value']:0);
			$avg['daerah']['end'] = ($avg['daerah']['start']?($avg['daerah']['start']+0.3):0);

			foreach ($data['data'] as $key => $value) {
				$z = ($value['value']-$avg['nasional']['start'])/$dataAvgnasional['data'][0]['value_stdev'];
				$data['data'][$key]['norm_dist'] = $this->_stats_dens_normal($value['value'], $dataAvgnasional['data'][0]['value'], $dataAvgnasional['data'][0]['value_stdev']);
				$data['data'][$key]['norm_dist'] = round($data['data'][$key]['norm_dist'],5);
				$data['data'][$key]['norm_dist'] = floatval($data['data'][$key]['norm_dist']);
			}

		}
		$dataReturn['data'] = $data['data'];
		$dataReturn['avg'] = $avg;

		return $dataReturn;
	}
	private function _stats_dens_normal($x, $mean, $stdev) {
		// pengganti fungsi php5 -> stats_dens_normal
	  $exponent = -pow($x - $mean, 2) / (2 * pow($stdev, 2));
	  $return = (1 / ($stdev * sqrt(2 * pi()))) * exp($exponent);
	  return $return;
	}
	public function trenIklh($uidKabkota,$uidProvinsi,$tahun,$dataShow, $target){
		if($dataShow == 2){
			$w = "deleted = 0 AND uid_provinsi =".$uidProvinsi." AND uid_kabkota=".$uidKabkota;
			// $minTahun = 1;
			$minTahun = $tahun - 2021;
		}else{
			$w = "deleted = 0 AND uid_provinsi =".$uidProvinsi." AND uid_kabkota=0";
			$minTahun = $tahun - 2021;
		}

		for ($i=($tahun-$minTahun); $i <= $tahun; $i++){
			$data[$i]['tahun'] = "".$i."";
			$data[$i]['target'] = $this->tables->query("SELECT * FROM rf_target_iklh WHERE ".$w." AND tahun=".$i)['data'][0][$target];
			$data[$i]['target'] = (int)($data[$i]['target'] > 0 ? $data[$i]['target'] : 0);
			if($data[$i]['target'] == 0){
				unset($data[$i]['target']);
			}
			$data[$i]['capaian'] = $this->tables->query("SELECT * FROM v_indeks_history WHERE ".$w." AND tahun=".$i)['data'][0][$target];
			$data[$i]['capaian'] = (int)($data[$i]['capaian'] > 0 ? $data[$i]['capaian'] : 0);
		}
		return array_values($data);
	}
	public function titikPantau($uidKabkota,$uidProvinsi,$tahun,$dataShow, $target){
		if($dataShow == 2){
			$w = "deleted = 0 AND uid_provinsi =".$uidProvinsi." AND uid_kabkota=".$uidKabkota;
		}else{
			$w = "deleted = 0 AND uid_provinsi =".$uidProvinsi;
		}
		if($target == "iklh"){
			$data = $this->tables->query("SELECT COUNT(uid_lokasi_pemantauan) AS jumlah_titik_pantau, uid_rf_component, if(uid_rf_component = 1, 'UDARA' , if(uid_rf_component = 2 , 'AIR' ,if(uid_rf_component = 5, 'LAUT' , if(uid_rf_component = 3 , 'LAHAN', '-') ))) AS nama FROM v_lokasi_pemantauan_new WHERE ".$w." AND tahun LIKE '%".$tahun."%' AND digunakan > 0 GROUP BY uid_rf_component");
			// $this->debug->show($data);
			if($data['total']){
				$data['data'][$data['total']]['jumlah_titik_pantau'] = 0;
				$data['data'][$data['total']]['uid_rf_component'] = 3;
				$data['data'][$data['total']]['nama'] = "LAHAN";
			}
			foreach ($data['data'] as $key => $value) {
				if($value['nama'] == "UDARA"){
					$dataMasuk = $this->tables->query("SELECT COUNT(uid_pelaporan_iku) AS total FROM v_pelaporan_iku WHERE ".$w." AND YEAR(tanggal)=".$tahun)['data'][0];
					$data['data'][$key]['jumlah_data_masuk'] = (int)($dataMasuk['total'] > 0 ? $dataMasuk['total'] : 0);
					// $dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_iku) AS total FROM v_pelaporan_iku WHERE ".$w." AND YEAR(tanggal)=".$tahun." AND (v_provinsi > 0 OR v_regional > 0 OR v_pusat > 0)")['data'][0];
					$dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_iku) AS total FROM v_pelaporan_iku WHERE ".$w." AND YEAR(tanggal)=".$tahun." AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)")['data'][0];
					$data['data'][$key]['jumlah_data_terverifikasi'] = (int)($dataTerverifikasi['total'] > 0 ? $dataTerverifikasi['total'] : 0);

				}elseif ($value['nama'] == "AIR") {
					$dataMasuk = $this->tables->query("SELECT COUNT(uid_pelaporan_ika) AS total FROM v_pelaporan_ika WHERE ".$w." AND YEAR(tanggal)=".$tahun)['data'][0];
					$data['data'][$key]['jumlah_data_masuk'] = (int)($dataMasuk['total'] > 0 ? $dataMasuk['total'] : 0);
					// $dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_ika) AS total FROM v_pelaporan_ika WHERE ".$w." AND YEAR(tanggal)=".$tahun." AND (v_provinsi > 0 OR v_regional > 0 OR v_pusat > 0)")['data'][0];
					$dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_ika) AS total FROM v_pelaporan_ika WHERE ".$w." AND YEAR(tanggal)=".$tahun." AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)")['data'][0];
					$data['data'][$key]['jumlah_data_terverifikasi'] = (int)($dataTerverifikasi['total'] > 0 ? $dataTerverifikasi['total'] : 0);

				}elseif ($value['nama'] == "LAUT") {
					$dataMasuk = $this->tables->query("SELECT COUNT(uid_pelaporan_ikal) AS total FROM v_pelaporan_ikal WHERE ".$w." AND YEAR(tanggal)=".$tahun)['data'][0];
					$data['data'][$key]['jumlah_data_masuk'] = (int)($dataMasuk['total'] > 0 ? $dataMasuk['total'] : 0);
					// $dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_ikal) AS total FROM v_pelaporan_ikal WHERE ".$w." AND YEAR(tanggal)=".$tahun." AND (v_provinsi > 0 OR v_regional > 0 OR v_pusat > 0)")['data'][0];
					$dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_ikal) AS total FROM v_pelaporan_ikal WHERE ".$w." AND YEAR(tanggal)=".$tahun." AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)")['data'][0];
					$data['data'][$key]['jumlah_data_terverifikasi'] = (int)($dataTerverifikasi['total'] > 0 ? $dataTerverifikasi['total'] : 0);

				}elseif ($value['nama'] == "LAHAN") {
					$dataMasuk = $this->tables->query("SELECT COUNT(uid_pelaporan_iktl) AS total FROM v_pelaporan_iktl WHERE ".$w." AND YEAR(tanggal)=".$tahun)['data'][0];
					$data['data'][$key]['jumlah_data_masuk'] = (int)($dataMasuk['total'] > 0 ? $dataMasuk['total'] : 0);
					// $dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_iktl) AS total FROM v_pelaporan_iktl WHERE ".$w." AND YEAR(tanggal)=".$tahun." AND (v_provinsi > 0 OR v_regional > 0 OR v_pusat > 0)")['data'][0];
					$dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_iktl) AS total FROM v_pelaporan_iktl WHERE ".$w." AND YEAR(tanggal)=".$tahun." AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)")['data'][0];
					$data['data'][$key]['jumlah_data_terverifikasi'] = (int)($dataTerverifikasi['total'] > 0 ? $dataTerverifikasi['total'] : 0);

				}
			}
			return $data['data'];
		}else{
			$dataPelaksana = $this->tables->query("SELECT uid_rf_pelaksana, name AS nama FROM rf_pelaksana WHERE deleted = 0");
			if($target != "ikl"){
			  $wAspek = ($target == 'iku' ? " AND uid_rf_component = 1" : ($target == 'ika' ? " AND uid_rf_component = 2" : ($target == 'ikal' ? " AND uid_rf_component = 5" : "" )));
			  $data = $this->tables->query("SELECT COUNT(uid_lokasi_pemantauan) AS jumlah_titik_pantau, uid_rf_pelaksana, name_pelaksana AS nama FROM v_lokasi_pemantauan_new WHERE ".$w." ".$wAspek." AND tahun LIKE '%".$tahun."%' AND digunakan > 0 GROUP BY uid_rf_pelaksana");
				// $this->debug->show($data);
				// $this->debug->show("SELECT COUNT(uid_lokasi_pemantauan) AS jumlah_titik_pantau, uid_rf_pelaksana, name_pelaksana AS nama FROM v_lokasi_pemantauan WHERE ".$w." ".$wAspek." AND tahun=".$tahun." GROUP BY uid_rf_pelaksana");
			}
			$tmpPemantauanUser = null;
			$tmpTerVerifikasiUser = null;
			foreach ($data['data'] as $key => $value) {
			  if($target != "ikl"){
			    // $dataMasuk = $this->tables->query("SELECT COUNT(uid_pelaporan_".$target.") AS total FROM v_pelaporan_".$target." WHERE ".$w." AND uid_rf_pelaksana =".$value['uid_rf_pelaksana']." AND YEAR(tanggal)=".$tahun)['data'][0];
			    // $data['data'][$key]['jumlah_data_masuk'] = (int)($dataMasuk['total'] > 0 ? $dataMasuk['total'] : 0);
			    // $dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_".$target.") AS total FROM v_pelaporan_".$target." WHERE ".$w." AND uid_rf_pelaksana =".$value['uid_rf_pelaksana']." AND YEAR(tanggal)=".$tahun." AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1)")['data'][0];
			    // $data['data'][$key]['jumlah_data_terverifikasi'] = (int)($dataTerverifikasi['total'] > 0 ? $dataTerverifikasi['total'] : 0);

					// $dataMasuk = $this->tables->query("SELECT COUNT(uid_pelaporan_".$target.") AS total, IF(role_user=1,1,IF(role_user=2,3,IF(role_user=3,4,IF(role_user=4,2,0)))) AS pelaksana FROM v_pelaporan_".$target." WHERE ".$w." AND uid_rf_pelaksana =".$value['uid_rf_pelaksana']." AND YEAR(tanggal)=".$tahun." GROUP BY role_user");
			    // $tmpPemantauanUser[] = $dataMasuk['data'];
			    // $dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_".$target.") AS total, IF(role_user=1,1,IF(role_user=2,3,IF(role_user=3,4,IF(role_user=4,2,0)))) AS pelaksana FROM v_pelaporan_".$target." WHERE ".$w." AND uid_rf_pelaksana =".$value['uid_rf_pelaksana']." AND YEAR(tanggal)=".$tahun." AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) GROUP BY role_user");
			    // $tmpTerVerifikasiUser[] = $dataTerverifikasi['data'];

					$dataMasuk = $this->tables->query("SELECT COUNT(uid_pelaporan_".$target.") AS total, IF(role_user=1,1,IF(role_user=2,3,IF(role_user=3,4,IF(role_user=4,2,0)))) AS pelaksana FROM v_pelaporan_".$target." WHERE ".$w."  AND YEAR(tanggal)=".$tahun." GROUP BY role_user");
			    $tmpPemantauanUser[] = $dataMasuk['data'];
			    $dataTerverifikasi = $this->tables->query("SELECT COUNT(uid_pelaporan_".$target.") AS total, IF(role_user=1,1,IF(role_user=2,3,IF(role_user=3,4,IF(role_user=4,2,0)))) AS pelaksana FROM v_pelaporan_".$target." WHERE ".$w."  AND YEAR(tanggal)=".$tahun." AND v_pusat = 1 AND IF(role_user = 2, v_regional = 1, v_pusat = 1) AND IF(role_user = 3, v_provinsi = 1, v_pusat = 1) GROUP BY role_user");
			    $tmpTerVerifikasiUser[] = $dataTerverifikasi['data'];
			  }else{
			    // $data['data'][$key]['jumlah_data_masuk'] = 0;
			    // $data['data'][$key]['jumlah_data_terverifikasi'] = 0;
			    $tmpPemantauanUser[] = [];
			    $tmpTerVerifikasiUser[] = [];
			  }
			}

			foreach ($dataPelaksana['data'] as $key => $value) {
			  $idxPelaksana = array_search($value['uid_rf_pelaksana'], array_column($data['data'], 'uid_rf_pelaksana'));
			  $dataPelaksana['data'][$key]['jumlah_titik_pantau'] = (is_numeric($idxPelaksana) ? $data['data'][$idxPelaksana]['jumlah_titik_pantau'] : 0);
			  // $dataPelaksana['data'][$key]['jumlah_data_masuk'] = (is_numeric($idxPelaksana) ? $data['data'][$idxPelaksana]['jumlah_data_masuk'] : 0);
			  // $dataPelaksana['data'][$key]['jumlah_data_terverifikasi'] = (is_numeric($idxPelaksana) ? $data['data'][$idxPelaksana]['jumlah_data_terverifikasi'] : 0);
			  $dataPelaksana['data'][$key]['jumlah_data_masuk'] = 0;
			  foreach ($tmpPemantauanUser as $ki => $vi) {
			    $idxPemantauanByPelaksana = array_search($value['uid_rf_pelaksana'],array_column($vi,'pelaksana'));
			    if(is_numeric($idxPemantauanByPelaksana)){
			      $dataPelaksana['data'][$key]['jumlah_data_masuk'] = $vi[$idxPemantauanByPelaksana]['total'];
			    }
			  }
			  $dataPelaksana['data'][$key]['jumlah_data_terverifikasi'] = 0;
			  foreach ($tmpTerVerifikasiUser as $ki => $vi) {
			    $idxTerVerifikasiByPelaksana = array_search($value['uid_rf_pelaksana'],array_column($vi,'pelaksana'));
			    if(is_numeric($idxTerVerifikasiByPelaksana)){
			      $dataPelaksana['data'][$key]['jumlah_data_terverifikasi'] = $vi[$idxTerVerifikasiByPelaksana]['total'];
			    }
			  }
			}
			return $dataPelaksana['data'];
		}
	}

	public function indeksResponKriteria($uidKabkota,$uidProvinsi,$tahun,$dataShow){
		$kodekriteria = array(
			"iku" => "A0000001",
			"ika" => "A0000003",
			"ikal" => "A0000002",
			"ikl" => "A0000004",
			"ikeg" => "A0000005",
		);

		$dataReturn = [];
		if($dataShow == 2){
			$dataIndeksKabkota = $this->db->fetch("SELECT kode_kriteria, SUM(nilai) AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid={$uidKabkota} AND provinsi_uid={$uidProvinsi} GROUP BY kode_kriteria")["data"];
			$dataIndeksKabkota = array_column($dataIndeksKabkota, null, "kode_kriteria");
			foreach ($kodekriteria as $key => $value) {
				$dataReturn[$key] = $dataIndeksKabkota[$value]['nilai'];
			}
		}
		if($dataShow == 1){
			$dataIndeksProvinsi = $this->db->fetch("SELECT kode_kriteria, SUM(nilai) AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid=0 AND provinsi_uid={$uidProvinsi} GROUP BY kode_kriteria")["data"];
			$dataIndeksProvinsi = array_column($dataIndeksProvinsi, null, "kode_kriteria");
			foreach ($kodekriteria as $key => $value) {
				$dataReturn[$key] = $dataIndeksProvinsi[$value]['nilai'];
			}
		}
		return $dataReturn;
	}

	public function indeksrespon($uidKabkota,$uidProvinsi,$tahun,$dataShow, $target){

		$kodekriteria = array(
			"iku" => "A0000001",
			"ika" => "A0000003",
			"ikal" => "A0000002",
			"ikl" => "A0000004",
			"ikeg" => "A0000005",
		);

		if($target == "iklh"){

			$dataIndeksRespon['group'][0]['forData'] ="Kabkota";
			$dataIndeksRespon['group'][1]['forData'] ="Provinsi";
			foreach ($dataIndeksRespon['group'] as $key => $value) {
				$dataIndeksRespon['group'][$key]['color'] = ($key == 0 ? '#ff9d00' : ($key == 1 ? '#c00' : ( $key == 2 ? '#60d74e' : '-')));
				$dataIndeksRespon['group'][$key]['idx_value'] = ($key == 0 ? 'kabkota' : ($key == 1 ? 'provinsi' : ( $key == 2 ? 'nasional' : '_')));
			}

			if($dataShow == 2){
				$dataIndeksRespon['group'][0]['dashed'] =0;
				$dataIndeksRespon['group'][1]['dashed'] =1;
			}else{
				$dataIndeksRespon['group'][0]['dashed'] =1;
				$dataIndeksRespon['group'][1]['dashed'] =0;
			}

			// $dataIndeksRespon['chart'][0]['nama'] = "Indonesia Hijau";
			$dataIndeksRespon['chart']['A0000004']['nama'] = "Indonesia Hijau";
			$dataIndeksRespon['chart']['A0000004']['tipe'] = 4;
			$dataIndeksRespon['chart']['A0000004']['kabkota'] 	= 0;
			$dataIndeksRespon['chart']['A0000004']['provinsi'] 	= 0;

			// $dataIndeksRespon['chart'][1]['nama'] = "Langit Biru";
			$dataIndeksRespon['chart']['A0000001']['nama'] = "Langit Biru";
			$dataIndeksRespon['chart']['A0000001']['tipe'] = 1;
			$dataIndeksRespon['chart']['A0000001']['kabkota'] 	= 0;
			$dataIndeksRespon['chart']['A0000001']['provinsi'] 	= 0;

			// $dataIndeksRespon['chart'][2]['nama'] = "Kali Bersih";
			$dataIndeksRespon['chart']['A0000003']['nama'] = "Kali Bersih";
			$dataIndeksRespon['chart']['A0000003']['tipe'] = 3;
			$dataIndeksRespon['chart']['A0000003']['kabkota'] 	= 0;
			$dataIndeksRespon['chart']['A0000003']['provinsi'] 	= 0;

			// $dataIndeksRespon['chart'][3]['nama'] = "Pantai Lestari";
			$dataIndeksRespon['chart']['A0000002']['nama'] = "Pantai Lestari";
			$dataIndeksRespon['chart']['A0000002']['tipe'] = 2;
			$dataIndeksRespon['chart']['A0000002']['kabkota'] 	= 0;
			$dataIndeksRespon['chart']['A0000002']['provinsi'] 	= 0;

			// $dataIndeksRespon['chart'][4]['nama'] = "Gambut Lestari";
			$dataIndeksRespon['chart']['A0000005']['nama'] = "Gambut Lestari";
			$dataIndeksRespon['chart']['A0000005']['tipe'] = 5;
			$dataIndeksRespon['chart']['A0000005']['kabkota'] 	= 0;
			$dataIndeksRespon['chart']['A0000005']['provinsi'] 	= 0;

			if($dataShow == 2){
				$dataIndeksKabkota = $this->db->fetch("SELECT kode_kriteria, SUM(nilai) AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid={$uidKabkota} AND provinsi_uid={$uidProvinsi} GROUP BY kode_kriteria")["data"];
				// $dataIndeksKabkota = $this->db->fetch("SELECT kode_kriteria, SUM(nilai_avg) AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid={$uidKabkota} AND provinsi_uid={$uidProvinsi} GROUP BY kode_kriteria")["data"];
				$dataIndeksKabkota = array_column($dataIndeksKabkota, null, "kode_kriteria");
			}
			$dataIndeksProvinsi = $this->db->fetch("SELECT kode_kriteria, SUM(nilai) AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid=0 AND provinsi_uid={$uidProvinsi} GROUP BY kode_kriteria")["data"];
			// $dataIndeksProvinsi = $this->db->fetch("SELECT kode_kriteria, SUM(nilai_avg) AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid=0 AND provinsi_uid={$uidProvinsi} GROUP BY kode_kriteria")["data"];
			$dataIndeksProvinsi = array_column($dataIndeksProvinsi, null, "kode_kriteria");

			foreach ($dataIndeksRespon['chart'] as $key => $value) {
				$dataIndeksRespon['chart'][$key]['kabkota'] = round($dataIndeksKabkota[$key]['nilai'],2);
				$dataIndeksRespon['chart'][$key]['provinsi'] = round($dataIndeksProvinsi[$key]['nilai'],2);
			}
			$dataIndeksRespon['chart'] = array_values($dataIndeksRespon['chart']);
			if($dataShow == 1){
				unset($dataIndeksRespon['group'][0]);
			}

			$dataIndeksRespon['group'] = array_values($dataIndeksRespon['group']);
			$dataIndeksRespon['dataChart'] = 2;
			return $dataIndeksRespon;

		}else{
			if($dataShow == 2){

				$whereikal = ($target == "ikal" ? " AND peruntukan = 'PROVINSI' " : "");

				$dataIndeksRespon['chart'] = $this->db->fetch("SELECT nama, kode FROM v_indeks_respon_aspek WHERE deleted = 0 AND periode_uid={$tahun} AND kode_kriteria='{$kodekriteria[$target]}'")["data"];

				$dataIndeksKabkota = $this->db->fetch("SELECT nama_aspek, kode_aspek, kode_kriteria, nilai_avg AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid={$uidKabkota} AND provinsi_uid={$uidProvinsi}")["data"];
				$dataIndeksKabkota = array_column($dataIndeksKabkota, null, "kode_aspek");
				$dataIndeksProvinsi = $this->db->fetch("SELECT nama_aspek, kode_aspek, kode_kriteria, nilai_avg AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid=0 AND provinsi_uid={$uidProvinsi}")["data"];
				$dataIndeksProvinsi = array_column($dataIndeksProvinsi, null, "kode_aspek");

				$dataIndeksKabkotaProvinsi = $this->db->fetch("SELECT nama_aspek, kode_aspek, kode_kriteria, nilai_avg AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid>0 AND provinsi_uid={$uidProvinsi} {$whereikal} GROUP BY kode_aspek")["data"];
				$dataIndeksKabkotaProvinsi = array_column($dataIndeksKabkotaProvinsi, null, "kode_aspek");

				foreach ($dataIndeksRespon['chart'] as $key => $value) {
					$dataIndeksRespon['chart'][$key]['Kabkota'] = ($dataIndeksKabkota[$value["kode"]]["nilai"] ? $dataIndeksKabkota[$value["kode"]]["nilai"] : 0);

					$kabkotaProvinsi = ($dataIndeksKabkotaProvinsi[$value["kode"]]["nilai"] ? $dataIndeksKabkotaProvinsi[$value["kode"]]["nilai"] : 0);
					$dataIndeksRespon['chart'][$key]['Provinsi'] = ($dataIndeksProvinsi[$value["kode"]]["nilai"] ? $dataIndeksProvinsi[$value["kode"]]["nilai"] : 0);
					$dataIndeksRespon['chart'][$key]['Provinsi'] = ($kabkotaProvinsi * (40/100)) + ($dataIndeksRespon['chart'][$key]['Provinsi'] * (60/100));
				}

				$dataIndeksRespon['group'][0]['forData'] = 'Kabkota';
				$dataIndeksRespon['group'][0]['idx_value'] = 'Kabkota';
				$dataIndeksRespon['group'][0]['color'] = '#ff9d00';
				$dataIndeksRespon['group'][0]['dashed'] =0;

				$dataIndeksRespon['group'][1]['forData'] = 'Provinsi';
				$dataIndeksRespon['group'][1]['idx_value'] = 'Provinsi';
				$dataIndeksRespon['group'][1]['color'] = '#c00';
				$dataIndeksRespon['group'][1]['dashed'] =1;

				$dataIndeksRespon['dataChart'] = 2;
				$dataIndeksRespon['group'] = array_values($dataIndeksRespon['group']);

				return $dataIndeksRespon;

			}else{

				$whereikal = ($target == "ikal" ? " AND peruntukan = 'PROVINSI' " : "");
				$dataIndeksRespon['chart'] = $this->db->fetch("SELECT nama, kode FROM v_indeks_respon_aspek WHERE deleted = 0 AND periode_uid={$tahun} AND kode_kriteria='{$kodekriteria[$target]}' {$whereikal}")["data"];

				$dataIndeksKabkota = $this->db->fetch("SELECT nama_aspek, kode_aspek, kode_kriteria, nilai_avg AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid>0 AND provinsi_uid={$uidProvinsi} {$whereikal} GROUP BY kode_aspek")["data"];
				$dataIndeksKabkota = array_column($dataIndeksKabkota, null, "kode_aspek");
				$dataIndeksProvinsi = $this->db->fetch("SELECT nama_aspek, kode_aspek, kode_kriteria, nilai_avg AS nilai FROM v_indeks_respon_aspek_nilai WHERE periode_uid={$tahun} AND kabkota_uid=0 AND provinsi_uid={$uidProvinsi}")["data"];
				$dataIndeksProvinsi = array_column($dataIndeksProvinsi, null, "kode_aspek");

				foreach ($dataIndeksRespon['chart'] as $key => $value) {
					$kabkota 	= ($dataIndeksKabkota[$value["kode"]]["nilai"] ? $dataIndeksKabkota[$value["kode"]]["nilai"] : 0);
					$provinsi = ($dataIndeksProvinsi[$value["kode"]]["nilai"] ? $dataIndeksProvinsi[$value["kode"]]["nilai"] : 0);
					$dataIndeksRespon['chart'][$key]['Provinsi'] = ($kabkota * (40/100)) + ($provinsi * (60/100));
				}


				$dataIndeksRespon['group'][0]['forData'] = 'Provinsi';
				$dataIndeksRespon['group'][0]['idx_value'] = 'Provinsi';
				$dataIndeksRespon['group'][0]['color'] = '#c00';
				$dataIndeksRespon['group'][0]['dashed'] =0;

				$dataIndeksRespon['dataChart'] = 2;
				$dataIndeksRespon['group'] = array_values($dataIndeksRespon['group']);

				return $dataIndeksRespon;

			}
		}
	}
	public function aspek($uidKabkota,$uidProvinsi,$tahun,$dataShow, $target){ //indeks respon 2023
		if($target == "iklh"){

			$dataIndeksRespon['group'][0]['forData'] ="Kabkota";
			$dataIndeksRespon['group'][1]['forData'] ="Provinsi";
			// $dataIndeksRespon['group'][2]['forData'] ="Nasional";
			foreach ($dataIndeksRespon['group'] as $key => $value) {
				$dataIndeksRespon['group'][$key]['color'] = ($key == 0 ? '#ff9d00' : ($key == 1 ? '#c00' : ( $key == 2 ? '#60d74e' : '-')));
				$dataIndeksRespon['group'][$key]['idx_value'] = ($key == 0 ? 'kabkota' : ($key == 1 ? 'provinsi' : ( $key == 2 ? 'nasional' : '_')));
			}

			if($dataShow == 2){
				$dataIndeksRespon['group'][0]['dashed'] =0;
				$dataIndeksRespon['group'][1]['dashed'] =1;
			}else{
				$dataIndeksRespon['group'][0]['dashed'] =1;
				$dataIndeksRespon['group'][1]['dashed'] =0;
			}

			// $dataIndeksRespon['chart'][0]['nama'] = "Indonesia Hijau";
			$dataIndeksRespon['chart'][0]['nama'] = "Tutupan Lahan";
			$dataIndeksRespon['chart'][0]['tipe'] = 4;
			$dataIndeksRespon['chart'][0]['kabkota'] 	= 0;
			$dataIndeksRespon['chart'][0]['provinsi'] 	= 0;
			// $dataIndeksRespon['chart'][0]['nasional'] 	= 0;

			// $dataIndeksRespon['chart'][1]['nama'] = "Langit Biru";
			$dataIndeksRespon['chart'][1]['nama'] = "Udara";
			$dataIndeksRespon['chart'][1]['tipe'] = 1;
			$dataIndeksRespon['chart'][1]['kabkota'] 	= 0;
			$dataIndeksRespon['chart'][1]['provinsi'] 	= 0;
			// $dataIndeksRespon['chart'][1]['nasional'] 	= 0;

			// $dataIndeksRespon['chart'][2]['nama'] = "Kali Bersih";
			$dataIndeksRespon['chart'][2]['nama'] = "Air";
			$dataIndeksRespon['chart'][2]['tipe'] = 3;
			$dataIndeksRespon['chart'][2]['kabkota'] 	= 0;
			$dataIndeksRespon['chart'][2]['provinsi'] 	= 0;
			// $dataIndeksRespon['chart'][2]['nasional'] 	= 0;

			// $dataIndeksRespon['chart'][3]['nama'] = "Pantai Lestari";
			$dataIndeksRespon['chart'][3]['nama'] = "Air Laut";
			$dataIndeksRespon['chart'][3]['tipe'] = 2;
			$dataIndeksRespon['chart'][3]['kabkota'] 	= 0;
			$dataIndeksRespon['chart'][3]['provinsi'] 	= 0;
			// $dataIndeksRespon['chart'][2]['nasional'] 	= 0;

			// $dataIndeksRespon['chart'][4]['nama'] = "Gambut Lestari";
			$dataIndeksRespon['chart'][4]['nama'] = "Ekosistem Gambut";
			$dataIndeksRespon['chart'][4]['tipe'] = 5;
			$dataIndeksRespon['chart'][4]['kabkota'] 	= 0;
			$dataIndeksRespon['chart'][4]['provinsi'] 	= 0;
			// $dataIndeksRespon['chart'][2]['nasional'] 	= 0;

			$w = "a.deleted = 0 AND a.tahun=".$tahun;
			if($dataShow == 2){
				$inEva[0] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks, tipe FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND a.tipe IN(1,3,4,2,5) AND b.uid_kabkota = ".$uidKabkota." AND b.uid_provinsi = ".$uidProvinsi." GROUP BY a.tipe,a.tahun ORDER BY a.tipe ASC";
				$inEva[1] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks, tipe FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND a.tipe IN(1,3,4,2,5) AND b.uid_provinsi = ".$uidProvinsi." AND b.uid_kabkota = 0 GROUP BY a.tipe,a.tahun ORDER BY a.tipe ASC";
				// $inEva[2] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks, tipe FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND a.tipe IN(1,3,4,2,5) AND b.uid_kabkota > 0 GROUP BY a.tipe,a.tahun ORDER BY a.tipe ASC";
			}else{
				$inEva[0] = "";
				$inEva[1] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks, tipe FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND a.tipe IN(1,3,4,2,5) AND b.uid_provinsi = ".$uidProvinsi." AND b.uid_kabkota = 0 GROUP BY a.tipe,a.tahun ORDER BY a.tipe ASC";
				// $inEva[2] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks, tipe FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND a.tipe IN(1,3,4) AND b.uid_provinsi > 0 AND uid_kabkota = 0 GROUP BY a.tipe,a.tahun ORDER BY a.tipe ASC";
			}
			foreach ($inEva as $key => $value) {
				if($value){
					$getInId = 	$this->tables->query($value);
					foreach ($getInId['data'] as $i => $vi) {
						// $dataEvaluasi[$key][$vi['tipe']]['indeks_respon'] = ($vi['tipe'] == 1 ? 'Langit Biru' : ($vi['tipe'] == 3 ? 'Kali Bersih' : ($vi['tipe'] == 4 ? 'Indonesia Hijau' : ($vi['tipe'] == 2 ? 'Pantai Lestari' : ($vi['tipe'] == 5 ? 'Gambut Lestari' : '' )))));
						$dataEvaluasi[$key][$vi['tipe']]['indeks_respon'] = ($vi['tipe'] == 1 ? 'Udara' : ($vi['tipe'] == 3 ? 'Air' : ($vi['tipe'] == 4 ? 'Tutupan Lahan' : ($vi['tipe'] == 2 ? 'Air Laut' : ($vi['tipe'] == 5 ? 'Ekosistem Gambut' : '' )))));
						if($key == 2){
							$varAvg = ($tahun >= 2023 ? "AVG(nilai_rataan_isian)" : "AVG(nilai)");
							$dataEvaluasi[$key][$vi['tipe']]['data'] = $this->tables->query("SELECT ".$varAvg." AS _value, ref_uid FROM indeks_respon_evaluasi WHERE deleted = 0 AND parent_uid IN(".$vi['uid_indeks'].") GROUP BY tipe")["data"][0];
							// $dataEvaluasi[$key][$vi['tipe']]['data'] = $this->tables->query("SELECT AVG(nilai) AS _value, ref_uid FROM indeks_respon_evaluasi WHERE deleted = 0 AND parent_uid IN(".$vi['uid_indeks'].") GROUP BY tipe")["data"][0];
						}else{
							$varSum = ($tahun >= 2023 ? "SUM(nilai_rataan_isian)" : "SUM(nilai)");
							$dataEvaluasi[$key][$vi['tipe']]['data'] = $this->tables->query("SELECT ".$varSum." AS _value, ref_uid FROM indeks_respon_evaluasi WHERE deleted = 0 AND parent_uid IN(".$vi['uid_indeks'].") GROUP BY tipe")["data"][0];
							// $dataEvaluasi[$key][$vi['tipe']]['data'] = $this->tables->query("SELECT SUM(nilai) AS _value, ref_uid FROM indeks_respon_evaluasi WHERE deleted = 0 AND parent_uid IN(".$vi['uid_indeks'].") GROUP BY tipe")["data"][0];
						}
					}
				}
			}
			// $this->debug->show($dataEvaluasi);
			foreach ($dataIndeksRespon['chart'] as $key => $value) {
				foreach ($dataEvaluasi as $ki => $vi) {
					$dataIndeksRespon['chart'][$key]['kabkota'] = round($dataEvaluasi[0][$value['tipe']]['data']['_value'],2);
					$dataIndeksRespon['chart'][$key]['provinsi'] = round($dataEvaluasi[1][$value['tipe']]['data']['_value'],2);
					// $dataIndeksRespon['chart'][$key]['nasional'] = round($dataEvaluasi[2][$value['tipe']]['data']['_value'],2);
				}
			}
			if($dataShow == 1){
				unset($dataIndeksRespon['group'][0]);
			}
			$dataIndeksRespon['group'] = array_values($dataIndeksRespon['group']);
			$dataIndeksRespon['dataChart'] = 2;
			return $dataIndeksRespon;
		}else{
			$w = "a.deleted = 0 AND a.tahun=".$tahun;

			$tableIndeks = array(
				"iku"=>array(
					"tb" => "irf_langitbiru",
					"tipe" => 1
				),
				"ika"=>array(
					"tb" => "irf_pkb",
					"tipe" => 3
				),
				"ikl"=>array(
					"tb" => "irf_indohijau",
					"tipe" => 4
				),
				"ikeg"=>array(
					"tb" => "irf_gambut",
					"tipe" => 5
				),
				"ikal"=>array(
					"tb" => "irf_pantaibersih",
					"tipe" => 2
				)
			);
			$dataIrf = $this->tables->query("SELECT id,indikator FROM ".$tableIndeks[$target]['tb']." WHERE deleted = 0 AND parent_1 = 0 AND parent_2 = 0 AND parent_3 = 0");

			$w .= " AND tipe = ".$tableIndeks[$target]['tipe'];


			if($dataShow == 2){
				$inEva[1] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND b.uid_kabkota = ".$uidKabkota." AND b.uid_provinsi = ".$uidProvinsi." GROUP BY a.tipe,a.tahun ORDER BY indeks_uid ASC";
				$inEva[2] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks  FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND b.uid_kabkota > 0 AND b.uid_provinsi = ".$uidProvinsi." GROUP BY a.tipe,a.tahun ORDER BY indeks_uid ASC";
				$inEva[3] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND b.uid_provinsi = ".$uidProvinsi." AND b.uid_kabkota = 0 GROUP BY a.tipe,a.tahun ORDER BY indeks_uid ASC";
				// $inEva[4] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND b.uid_kabkota > 0 GROUP BY a.tipe,a.tahun ORDER BY indeks_uid ASC";

				foreach ($inEva as $key => $value) {
					$getInId = 	$this->tables->query($value)['data'][0]['uid_indeks'];
					$varAvg = ($tahun >= 2023 ? "AVG(nilai_rataan_isian)" : "AVG(nilai)");
					$dataEvaluasi[$key] = $this->tables->query("SELECT ".$varAvg." AS _value, ref_uid FROM indeks_respon_evaluasi WHERE deleted = 0 AND parent_uid IN(".$getInId.") GROUP BY ref_uid")["data"];
					// $dataEvaluasi[$key] = $this->tables->query("SELECT AVG(nilai) AS _value, ref_uid FROM indeks_respon_evaluasi WHERE deleted = 0 AND parent_uid IN(".$getInId.") GROUP BY ref_uid")["data"];
				}


				$dataIndeksRespon = [];

				foreach ($dataEvaluasi as $key => $value) {
					foreach ($dataIrf['data'] as $ki => $vi) {
						$expld = explode(". ",$vi['indikator']);
						$dataIrf['data'][$ki]['indikator'] = (count($expld) > 1 ? $expld[1] : $vi['indikator']);

						$idxValue = array_search($vi['id'], array_column($value, 'ref_uid'));

						$dataIndeksRespon['group'][$key]['forData'] = ($key == 1 ? 'Kabkota' : ($key == 2 ? 'ProvinsiChild' : ( $key == 3 ? 'Provinsi' : 'Nasional')));
						$dataIndeksRespon['group'][$key]['color'] = ($key == 1 ? '#ff9d00' : ($key == 2 ? '#c00' : ( $key == 3 ? '#c00' : '#60d74e')));

						$idxValueChart = $dataIndeksRespon['group'][$key]['forData'];
						$dataIndeksRespon['group'][$key]['idx_value'] = $idxValueChart;
						$dataIndeksRespon['chart'][$ki]['nama'] = $dataIrf['data'][$ki]['indikator'];
						$dataIndeksRespon['chart'][$ki][$idxValueChart] = (is_numeric($idxValue) ? $value[$idxValue]['_value'] : 0);

					}
				}
				foreach ($dataIndeksRespon['chart'] as $key => $value) {
					$nilaiProvinsi = ($value['ProvinsiChild'] * (40/100)) + ($value['Provinsi'] * (60/100));
					$dataIndeksRespon['chart'][$key]['Provinsi'] = $nilaiProvinsi;

					unset($dataIndeksRespon['chart'][$key]['ProvinsiChild']);
				}
				unset($dataIndeksRespon['group'][2]);
				$dataIndeksRespon['dataChart'] = 2;
				$dataIndeksRespon['group'] = array_values($dataIndeksRespon['group']);
				if($dataShow == 2){
					$dataIndeksRespon['group'][0]['dashed'] =0;
					$dataIndeksRespon['group'][1]['dashed'] =1;
				}else{
					$dataIndeksRespon['group'][0]['dashed'] =1;
					$dataIndeksRespon['group'][1]['dashed'] =0;
				}
				return $dataIndeksRespon;
			}else{
				$inEva[1] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks  FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND b.uid_kabkota > 0 AND b.uid_provinsi = ".$uidProvinsi." GROUP BY a.tipe,a.tahun ORDER BY indeks_uid ASC";
				$inEva[2] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND b.uid_provinsi = ".$uidProvinsi." AND b.uid_kabkota = 0 GROUP BY a.tipe,a.tahun ORDER BY indeks_uid ASC";
				// $inEva[3] = "SELECT GROUP_CONCAT(a.indeks_uid SEPARATOR ',') AS uid_indeks FROM ir_parent a LEFT JOIN users b ON a.users_uid = b.uid_users WHERE ".$w." AND b.uid_provinsi > 0 AND b.uid_kabkota = 0 GROUP BY a.tipe,a.tahun ORDER BY indeks_uid ASC";
				foreach ($inEva as $key => $value) {
					$getInId = 	$this->tables->query($value)['data'][0]['uid_indeks'];
					$varAvg = ($tahun >= 2023 ? "AVG(nilai_rataan_isian)" : "AVG(nilai)");
					$dataEvaluasi[$key] = $this->tables->query("SELECT ".$varAvg." AS _value, ref_uid FROM indeks_respon_evaluasi WHERE deleted = 0 AND parent_uid IN(".$getInId.") GROUP BY ref_uid")["data"];
				}

				$dataIndeksRespon = [];

				foreach ($dataEvaluasi as $key => $value) {
					foreach ($dataIrf['data'] as $ki => $vi) {
						$expld = explode(". ",$vi['indikator']);
						$dataIrf['data'][$ki]['indikator'] = (count($expld) > 1 ? $expld[1] : $vi['indikator']);

						$idxValue = array_search($vi['id'], array_column($value, 'ref_uid'));

						$dataIndeksRespon['group'][$key]['forData'] = ($key == 1 ? 'Kabkota' : ($key == 2 ? 'Provinsi' : 'Nasional'));
						$dataIndeksRespon['group'][$key]['color'] = ($key == 1 ? '#ff9d00' : ($key == 2 ? '#c00' : '#60d74e'));

						$idxValueChart = $dataIndeksRespon['group'][$key]['forData'];
						$dataIndeksRespon['group'][$key]['idx_value'] = $idxValueChart;
						$dataIndeksRespon['chart'][$ki]['nama'] = $dataIrf['data'][$ki]['indikator'];
						$dataIndeksRespon['chart'][$ki][$idxValueChart] = (is_numeric($idxValue) ? $value[$idxValue]['_value'] : 0);

					}
				}
				foreach ($dataIndeksRespon['chart'] as $key => $value) {
					$nilaiProvinsi = ($value['Kabkota'] * (40/100)) + ($value['Provinsi'] * (60/100));
					$dataIndeksRespon['chart'][$key]['Provinsi'] = $nilaiProvinsi;
				}
				unset($dataIndeksRespon['group'][1]);
				$dataIndeksRespon['dataChart'] = 2;
				$dataIndeksRespon['group'] = array_values($dataIndeksRespon['group']);
				$dataIndeksRespon['group'][0]['dashed'] =0;
				return $dataIndeksRespon;
			}
		}
	}

	public function indeksReponBobot($tahun, $kabkota, $provinsi){
		$w = ' a.deleted=0 AND a.hidden=0 ';
		$w .= ' AND a.tahun ='.$tahun;
		$w .= ' AND b.uid_kabkota ='.$kabkota;
		$w .= ' AND b.uid_provinsi ='.$provinsi;

		$sql = 'SELECT MIN(a.indeks_uid) as min_index, GROUP_CONCAT(a.indeks_uid SEPARATOR ",") indeks_gabung, a.users_uid, a.tahun, MIN(a.tipe) as min_tipe, GROUP_CONCAT(a.tipe SEPARATOR ",") AS tipe_gabung,
									 b.name, b.role_user, b.uid_provinsi, b.uid_kabkota, c.gambut, d.gambut as gambut_kabkota, c.kd_regional, c.nama_propinsi, d.nama_kabkot
						FROM ir_parent a
						LEFT JOIN users b ON a.users_uid = b.uid_users
						LEFT JOIN rf_provinsi c ON b.uid_provinsi = c.kd_propinsi
						LEFT JOIN rf_kabkota d ON b.uid_kabkota = d.kd_kota
						LEFT JOIN rf_regional e ON c.kd_regional = e.kd_regional
						WHERE '. $w .' GROUP BY a.users_uid ORDER BY a.tahun DESC';
		$data1 = $data2 = $data3 = $data4 = $data5 = null;
		$dataParent = $this->tables->query($sql);
		$this->tables->set('bobot_ir', 'bobot_ir_uid');
		$data = $this->tables->fetch('deleted = 0');
		foreach ($data['data'] as $key => $value) {
			$bobot[$value['kategori']] =$value;
		}
		// $this->debug->show($bobot);
		foreach ($dataParent['data'] as $key => $val) {
			if($val['uid_provinsi'] == 0 && $val['uid_kabkota'] == 0){
				// unset($dataParent['data'][$key]);
			}else{
				if($val['nama_kabkot'] && $val['gambut'] != 1){
					$nb[$key] = $bobot[4];
				}elseif($val['nama_kabkot'] && $val['gambut'] == 1){
					$nb[$key] = $bobot[3];
				}elseif(!$val['nama_kabkot'] && $val['gambut'] != 1){
					$nb[$key] = $bobot[2];
				}elseif(!$val['nama_kabkot'] && $val['gambut'] == 1){
					$nb[$key] = $bobot[1];
				}
				// lANGIT BIRU
				$data1['data'][$key] = $val;
				$data1['data']['total'] = $dataParent['total'];
				$data1['data'][$key]['bobot'] = $nb[$key];


				$sql = 'SELECT nilai
				FROM indeks_respon_evaluasi
				WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 1 GROUP BY ref_uid';
				$child = $this->tables->query($sql);
				$nilai[$key] = array_column($child['data'], 'nilai');
				$data1['data'][$key]['lb_all'] = array_sum($nilai[$key]);
				$data1['data'][$key]['lb_final'] = $nilai_final[$key][0] = round($data1['data'][$key]['lb_all'] * $nb[$key]['lb'] / 100, 2);
				//END

				//PANTAI BERSIH
				$sql = 'SELECT nilai
				FROM indeks_respon_evaluasi
				WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 2 GROUP BY ref_uid';
				$child = $this->tables->query($sql);
				$nilai[$key] = array_column($child['data'], 'nilai');
				$data1['data'][$key]['pb_all'] = array_sum($nilai[$key]);
				$data1['data'][$key]['pb_final'] = $nilai_final[$key][1] = round($data1['data'][$key]['pb_all'] * $nb[$key]['pl'] / 100, 2);
				//END

				// // PROGRAM KALI BERSIH
				$sql = 'SELECT nilai
				FROM indeks_respon_evaluasi
				WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 3 GROUP BY ref_uid';
				$child = $this->tables->query($sql);
				$nilai[$key] = array_column($child['data'], 'nilai');
				$data1['data'][$key]['kb_all'] = array_sum($nilai[$key]);
				$data1['data'][$key]['kb_final'] = $nilai_final[$key][2] = round($data1['data'][$key]['kb_all'] * $nb[$key]['kb'] / 100, 2);
				//END

				//INDONESIA HIJAU
				$sql = 'SELECT nilai
				FROM indeks_respon_evaluasi
				WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 4 GROUP BY ref_uid';
				$child = $this->tables->query($sql);
				$nilai[$key] = array_column($child['data'], 'nilai');
				$data1['data'][$key]['ih_all'] = array_sum($nilai[$key]);
				$data1['data'][$key]['ih_final'] = $nilai_final[$key][3] = round($data1['data'][$key]['ih_all'] * $nb[$key]['ih'] / 100, 2);
				//END

				// GAMBUT LESTARI
				$sql = 'SELECT nilai
				FROM indeks_respon_evaluasi
				WHERE deleted=0 AND hidden=0 AND parent_uid IN (' . $val['indeks_gabung'] . ') AND tipe = 5 GROUP BY ref_uid';
				$child = $this->tables->query($sql);
				$nilai[$key] = array_column($child['data'], 'nilai');
				$data1['data'][$key]['gl_all'] = array_sum($nilai[$key]);
				$data1['data'][$key]['gl_final'] = $nilai_final[$key][4] = round($data1['data'][$key]['gl_all'] * $nb[$key]['gl'] / 100, 2);
				//END

				$data1['data'][$key]['nilai_final'] = array_sum($nilai_final[$key]);
			}

			$data1['data'] = array_values($data1['data']);
		}
		// $dataReturn = array(
		// 	'iku'=>(is_numeric($data1['data'][0]['lb_final']) ? $data1['data'][0]['lb_final'] : '-'),
		// 	'ikal'=>(is_numeric($data1['data'][0]['pb_final']) ? $data1['data'][0]['pb_final'] : '-'),
		// 	'ika'=>(is_numeric($data1['data'][0]['kb_final']) ? $data1['data'][0]['kb_final'] : '-'),
		// 	'gambut'=>(is_numeric($data1['data'][0]['gl_final']) ? $data1['data'][0]['gl_final'] : '-'),
		// 	'ikl'=>(is_numeric($data1['data'][0]['ih_final']) ? $data1['data'][0]['ih_final'] : '-'),
		// 	'iklh'=>(is_numeric($data1['data'][0]['nilai_final']) ? $data1['data'][0]['nilai_final'] : '-'),
		// );
		$dataReturn = array(
			'iku'=>(is_numeric($data1['data'][0]['lb_all']) ? round($data1['data'][0]['lb_all'], 2) : '-'),
			'ikal'=>(is_numeric($data1['data'][0]['pb_all']) ? round($data1['data'][0]['pb_all'], 2) : '-'),
			'ika'=>(is_numeric($data1['data'][0]['kb_all']) ? round($data1['data'][0]['kb_all'], 2) : '-'),
			'gambut'=>(is_numeric($data1['data'][0]['gl_all']) ? round($data1['data'][0]['gl_all'], 2) : '-'),
			'ikl'=>(is_numeric($data1['data'][0]['ih_all']) ? round($data1['data'][0]['ih_all'], 2) : '-'),
			'iklh'=>(is_numeric($data1['data'][0]['nilai_final']) ? round($data1['data'][0]['nilai_final'], 2) : '-'),
		);
		return $dataReturn;
	}

	public function maps($uidKabkota,$uidProvinsi,$tahun,$dataShow, $target){
		$latlng = $this->tables->query("SELECT * FROM rf_provinsi WHERE  kd_propinsi=".$uidProvinsi)['data'][0];
		if($dataShow == 2){
			$w = "deleted = 0 AND uid_provinsi =".$uidProvinsi." AND uid_kabkota=".$uidKabkota." AND tahun=".$tahun;
			$wLokasi = "deleted = 0 AND uid_provinsi =".$uidProvinsi." AND uid_kabkota=".$uidKabkota." AND tahun LIKE '%".$tahun."%'";
		}else{
			$w = "deleted = 0 AND uid_provinsi =".$uidProvinsi." AND tahun=".$tahun;
			$wLokasi = "deleted = 0 AND uid_provinsi =".$uidProvinsi." AND tahun LIKE '%".$tahun."%'";
		}
		if($target == "iku"){
			$w .= " AND uid_rf_component = 1";
		}elseif ($target == "ika") {
			$w .= " AND uid_rf_component = 2";
		}elseif ($target == "ikl") {
			$w .= " AND uid_rf_component = 3";
		}elseif ($target == "ikal") {
			$w .= " AND uid_rf_component = 5";
		}

		$data = $this->tables->query("SELECT uid_lokasi_pemantauan, latitude, longitude, uid_rf_component, uid_rf_pelaksana FROM v_lokasi_pemantauan WHERE ".$wLokasi);
		if($target == "iku"){
			$dataId = $this->tables->query("SELECT GROUP_CONCAT(uid_lokasi_pemantauan) AS uid_lokasi_pemantauan FROM v_lokasi_pemantauan WHERE ".$wLokasi)['data'][0]['uid_lokasi_pemantauan'];
			$dataPemantauan = $this->tables->query("SELECT uid_rf_peruntukan, uid_lokasi_pemantauan FROM pelaporan_iku WHERE deleted = 0 AND uid_lokasi_pemantauan IN(".$dataId.") GROUP BY uid_lokasi_pemantauan");
			foreach ($data['data'] as $key => $value) {
				$idxPeruntukan = array_search($value['uid_lokasi_pemantauan'], array_column($dataPemantauan['data'],'uid_lokasi_pemantauan'));
				if(is_numeric($idxPeruntukan)){
					$data['data'][$key]['peruntukan'] = $dataPemantauan['data'][$idxPeruntukan]['uid_rf_peruntukan'];
				}else{
					$data['data'][$key]['peruntukan'] = 0;
				}
			}
		}
		// $this->debug->show($dataPemantauan);
		$data['latlng'] = $latlng;
		return $data;
	}

	public function getCatatanRekomendasi(){
		$tahun = $this->params("t");
		$provinsi = $this->params("p");
		$kabkota = $this->params("k");
		$aspek = $this->params("a");

		if(is_numeric($tahun) && is_numeric($provinsi) && is_numeric($kabkota) && $aspek){
			if($kabkota > 0){
				$w = "deleted = 0 AND uid_provinsi=".$provinsi." AND uid_kabkota =".$kabkota." AND tahun=".$tahun;
			}else{
				$w = "deleted = 0 AND uid_provinsi=".$provinsi." AND uid_kabkota =0 AND tahun=".$tahun;
			}
			$this->tables->set('indeks_history', 'uid_indeks_history');
			$data = $this->tables->fetch($w)['data'][0];
			echo $this->responseJson(200, $data);
		}else{
			echo $this->responseJson(400, []);
		}
	}

	public function catatanVerifikasi(){
		$dataRequest = file_get_contents("php://input");
		$dataRequest = json_decode($dataRequest, true);

		$tahun = $this->params("t");
		$provinsi = $this->params("p");
		$kabkota = $this->params("k");
		$aspek = $this->params("a");

		if(is_numeric($tahun) && is_numeric($provinsi) && is_numeric($kabkota) && $aspek){
			if($kabkota > 0){
				$w = "deleted = 0 AND uid_provinsi=".$provinsi." AND uid_kabkota =".$kabkota." AND tahun=".$tahun;
			}else{
				$w = "deleted = 0 AND uid_provinsi=".$provinsi." AND uid_kabkota =0 AND tahun=".$tahun;
			}
			$this->tables->set('indeks_history', 'uid_indeks_history');
			$data = $this->tables->fetch($w)['data'][0];
			if($data['uid_indeks_history']){
				if(strlen($dataRequest['catatan']) <= 700){
					$post['form']['rekomendasi_'.$aspek] = $dataRequest['catatan'];
					$post['form']['rekomendasi_'.$aspek.'_select'] = implode("|",$dataRequest['catatanSelect']);
					$post['form']['uid_indeks_history'] = (int)$data['uid_indeks_history'];
					$post['submit'] = TRUE;
					$this->tables->post($post);
					// $data = $this->tables->fetch($w)['data'][0];

					$aspek = ($aspek == 'ikl' ? 'iktl' : $aspek);
					$this->tables->set('indeks_'.$aspek, 'uid_indeks_'.$aspek);
					$data = $this->tables->fetch($w)['data'][0];
					if($data['uid_indeks_'.$aspek]){
						$postAspek['form']['rekomendasi'] = $dataRequest['catatan'];
						$postAspek['form']['rekomendasi_select'] = implode("|",$dataRequest['catatanSelect']);
						$postAspek['form']['uid_indeks_'.$aspek] = (int)$data['uid_indeks_'.$aspek];
						$postAspek['submit'] = TRUE;
						if($aspek != "iklh"){
							$this->tables->post($postAspek);
						}
					}

					$this->tables->set('indeks_history', 'uid_indeks_history');
					$data = $this->tables->fetch($w)['data'][0];
					echo $this->responseJson(200, $data, "Berhasil disimpan");
				}else{
					echo $this->responseJson(500,[], "Rekomendasi tidak boleh melebihi 700 karakter");
				}
			}else{
				echo $this->responseJson(400,[], "Id Tidak ditemukan");
			}
		}else{
			echo $this->responseJson(400, [], "Data required tidak lengkap");
		}

	}

	public function uploadPeta(){
		if(isset($_FILES['file'])){
			$tahun = $this->params("t");
			$provinsi = $this->params("p");
			$kabkota = $this->params("k");
			$aspek = $this->params("a");
			$filename = $this->upload->uploadFile($_FILES['file'], "peta_sebaran");
			if(is_numeric($tahun) && is_numeric($provinsi) && is_numeric($kabkota) && $aspek && $filename != ""){
				if($kabkota > 0){
					$w = "deleted = 0 AND uid_provinsi=".$provinsi." AND uid_kabkota =".$kabkota." AND tahun=".$tahun;
				}else{
					$w = "deleted = 0 AND uid_provinsi=".$provinsi." AND uid_kabkota =0 AND tahun=".$tahun;
				}
				$this->tables->set('indeks_history', 'uid_indeks_history');
				$data = $this->tables->fetch($w)['data'][0];
				if($data['uid_indeks_history']){
					if($data['peta_sebaran_'.$aspek]){
						unlink(UPLOADFOLDER."peta_sebaran/".$data['peta_sebaran_'.$aspek]);
					}

					$post['form']['peta_sebaran_'.$aspek] = $filename;
					$post['form']['uid_indeks_history'] = (int)$data['uid_indeks_history'];
					$post['submit'] = TRUE;
					$this->tables->post($post);
					$data = $this->tables->fetch($w)['data'][0];

					$aspek = ($aspek == 'ikl' ? 'iktl' : $aspek);
					$this->tables->set('indeks_'.$aspek, 'uid_indeks_'.$aspek);
					$data = $this->tables->fetch($w)['data'][0];
					if($data['uid_indeks_'.$aspek]){
						$postAspek['form']['peta_sebaran'] = $filename;
						$postAspek['form']['uid_indeks_'.$aspek] = (int)$data['uid_indeks_'.$aspek];
						$postAspek['submit'] = TRUE;
						if($aspek != "iklh"){
							$this->tables->post($postAspek);
						}
					}
					$this->tables->set('indeks_history', 'uid_indeks_history');
					$data = $this->tables->fetch($w)['data'][0];

					echo $this->responseJson(200, $data);
				}else{
					echo $this->responseJson(400, []);
				}
			}
		}else{
			echo $this->responseJson(404, []);
		}
	}


	public function uploadRaportPdf(){
		$uidProvinsi = $this->params("p");
		$uidKabkota = $this->params("k");
		$tahun = $this->params("t");
		$group = $this->params("x");
		$template = $this->params("tmplt");
		$file = $_FILES['file'];
		if($file['name']){

			$check_ext = $this->upload->validateExtension($file['name'], 'document');
			if($check_ext){
				$filename = "raport-".$group."-".$tahun."-".$uidProvinsi. "-" .$uidKabkota.".pdf";
				unlink(TEMPFOLDER."raport/" . $filename);

				$upload = move_uploaded_file($file['tmp_name'], UPLOADFOLDER ."raport/" . $filename);
				if($upload){
					echo $this->responseJson(200, array("url"=>BASEURL."uploads/raport/" . $filename), "success");
				}else{
					echo $this->responseJson(400, array("url"=>BASEURL), "error");
				}
			}else{
				echo $this->responseJson(400, array("url"=>BASEURL), "error");
			}
		}else{
			echo $this->responseJson(404, array("url"=>BASEURL), "error");
		}

	}

	public function responseJson($code, $data, $message=""){
		return json_encode(array("statusCode"=>$code, "data"=>$data, "message"=>$message));
	}

}

?>
