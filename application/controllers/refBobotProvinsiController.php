<?php

/**
 * created at : 14/11/2023
 * created by : Dasendria team
 * desc : controller for bobot provinsi
 */
class refBobotProvinsiController extends Front {
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

		$this -> view -> assign("primaryKey", "uid_target_iklh");
		$this -> viewName = "rf_provinsi_bobot";
		$this -> primaryKey = "uid";
		$this -> where = "deleted = 0";
	}

	public function index() {

		$this -> getData();
		$this -> view -> assign("masterActive", "active");
		$this -> view -> assign("show", $show);
		$this -> view -> assign("message", $message);
		$this -> view -> assign("icons", '<i class="la la-tasks"></i>');
		$this -> view -> assign("title", 'Bobot Provinsi IKLH');
		$this -> view -> display("index.html");
	}

	private function getData() {
		$tahun = (is_numeric($this->params("y")) ? $this->params("y") : (int)date("Y"));
		$this -> tables -> set("rf_provinsi_bobot", "uid");
		$rfBobot = $this -> tables -> fetch("tahun=".$tahun);
		$this -> tables -> set("rf_provinsi", "kd_propinsi");
		$rfProvinsi = $this -> tables -> fetch("");
		foreach ($rfProvinsi['data'] as $key => $value) {
			$idxBobot = array_search($value['kd_propinsi'], array_column($rfBobot['data'],'uid_provinsi'));

			$rfProvinsi['data'][$key]['bobot'] = (is_numeric($idxBobot) ? $rfBobot['data'][$idxBobot]['bobot'] : '');
			$rfProvinsi['data'][$key]['bobot_ikal'] = (is_numeric($idxBobot) ? $rfBobot['data'][$idxBobot]['bobot_ikal'] : '');
			$rfProvinsi['data'][$key]['tahun'] = (is_numeric($idxBobot) ? $rfBobot['data'][$idxBobot]['tahun'] : $tahun);
			$rfProvinsi['data'][$key]['tahun'] = ($rfProvinsi[$key]['tahun'] ? $rfProvinsi[$key]['tahun'] : $tahun);
		}
		$this -> view -> assign("src_tahun", $tahun);
		$this -> view -> assign("view", $rfProvinsi['data']);
	}


	public function onUpdate() {
		header("Content-Type: application/json; charset=UTF-8");
		$provinsi = $this->params("x");
		$tahun = $this->params("tahun");
		$field = $this->params("field");
		$value = $this->params("v");

		if ($provinsi && $tahun && $field && is_numeric($value)) {
			$this->tables->set("rf_provinsi_bobot", "uid");
			$check = $this->tables->fetch("uid_provinsi=".$provinsi." AND tahun=".$tahun)['data'][0];
			if($check['uid']){
				$update['form']['uid'] = $check['uid'];
			}
			$update['form']['uid_provinsi'] = $provinsi;
			$update['form']['tahun'] = $tahun;
			$update['form'][$field] = $value;
			$update['submit'] = TRUE;
			if($this->tables->post($update)){
				echo 1;
			}else{
				echo 0;
			}
		}
	}

	private function _getProperties($model) {
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
?>
