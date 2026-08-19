<?php
/**
 * created at 	: 07/02/2023
 * created by 	: dasendria team
 * desc		  	  : controller indeks iklh raport
 *
 */
class iklhRaportController extends Front {
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
		$this -> view -> assign("raportActive", "active");
		$this -> view -> assign("show", $show);
		$this -> view -> assign("message", $message);
		$this -> view -> assign("title", 'Raport Indeks Kualitas Lingkungan Hidup');
		$this -> view -> assign("icons", '<i class="ft-bar-chart-2"></i>');
		$this -> view -> display("index.html");
	}

	private function _indeksIklh() {
		$post = $this -> post();

		$w = "deleted = 0";
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
				$wn = "deleted = 0 AND tahun=" . $post['form']['tahun'];
			}
			$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
			$this -> view -> assign("search", $post['form']);
		} else {
			$post['form']['tahun'] = ACTIVE_YEAR;
			$w .= " AND tahun=" . ACTIVE_YEAR;
			$wn = "deleted = 0 AND tahun=" . ACTIVE_YEAR;
			$this -> view -> assign("search", $post['form']);
		}

		$kabkota = $this -> tables -> query("SELECT * FROM v_indeks_history WHERE jenis_indeks = 0 AND deleted_kabkota = 0 AND " . $w." ORDER BY uid_kabkota ASC");
		$provinsi = $this -> tables -> query("SELECT * FROM v_indeks_history WHERE jenis_indeks = 1 AND " . $w." ORDER BY uid_provinsi ASC");

		$this -> _rf();
    $this -> view -> assign("viewp", $provinsi['data']);
    $this -> view -> assign("viewk", $kabkota['data']);
	}

	private function _rf() {
		$rating = $this -> tables -> query("SELECT * FROM rf_rating_iklh WHERE deleted = 0");
		$this -> view -> assign("rating", $rating['data']);
	}

}
?>
