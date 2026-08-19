<?php

/**
 * created at : 14/03/2024
 * created by : Dasendria team
 * desc : controller for referensi ikeg
 */
class ikeg_rfController extends Front {
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

		$this -> view -> assign("primaryKey", "uid_indeks_history");
		$this -> viewName = "v_indeks_history";
		$this -> primaryKey = "uid_indeks_history";
		$this -> where = "deleted = 0";

		$this->view->assign("aksesAdmin", $this->ref->aksesAdmin($this->me));
	}

  public function index() {

    $this->getData();
    $this->view->assign("indeksActive","active");
    $this->view->assign("show",$show);
    $this->view->assign("message",$message);
    $this->view->assign("icons",'<i class="la la-tree"></i>');
    $this->view->assign("title",'INDEKS KUALITAS EKOSISTEM GAMBUT');
    $this->view->display("index.html");
  }

  public function setValueDetail(){
		$post = $this->post();
		if($post['tahun'] > 0 && $post['field'] && $post['prov'] > 0 && $post['field'] == "ikeg"){
			$sqlCheck = $this->db->fetch("SELECT * FROM indeks_history WHERE uid_provinsi=".$post['prov']." AND uid_kabkota=".$post['kab']." AND tahun=".$post['tahun']);
			if($sqlCheck['data'][0]['uid_indeks_history']){
				$update['form']['uid_indeks_history'] = $sqlCheck['data'][0]['uid_indeks_history'];

				$update['form']['tahun'] = $post['tahun'];
				$update['form']['uid_provinsi'] = $post['prov'];
				$update['form']['uid_kabkota'] = $post['kab'];
        $update['form']['chuser'] = $this->me['uid_users'];
        $update['form'][$post['field']] = ($post['type'] == 'number' ? str_replace(",","",$post['value']) : $post['value']);
        $update['form'][$post['field']] = ($update['form'][$post['field']] ? $update['form'][$post['field']] : NULL);
        $update['submit'] = TRUE;
        $this->tables->set('indeks_history', 'uid_indeks_history');
        $updateStatus = $this->tables->post($update);
        if($updateStatus){
          echo json_encode(array("statusCode"=>200,"message"=>"Data berhasil diupdate"));
        }else{
          echo json_encode(array("statusCode"=>400,"message"=>"Data gagal diupdate"));
        }
			}else{
        echo json_encode(array("statusCode"=>400,"message"=>"Data gagal diupdate, belum ada data indeks IKA,IKU,IKL,IKTL"));
      }
		}
		die();
	}

  private function getData() {
		$this -> tables -> set($this -> viewName, $this -> primaryKey);
		$properties = $this -> _getProperties($this -> viewName);
		$urlVar = BASEURL . $this -> url . '/';
		$w = $this -> where. " AND uid_provinsi > 0 AND jenis_indeks <= 2";

		if ($this -> me['role_user'] == 3) {
			$w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
		} elseif ($this -> me['role_user'] == 2) {
			$w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
		} elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
			$w .= " AND kd_regional =" . $this -> me['uid_regional'];
		}

		$o = "uid_provinsi,uid_kabkota,tahun ASC";
		$post = $this -> post();
		if ($this -> params('search')) {
			$post['search'] = TRUE;
			$post['form'] = json_decode(urldecode($this -> params('search')), 1);
		}
		if (isset($post['search'])) {
			$post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
			if ($post['form']['keyword']) {
				if ($properties['total']) {
					$w .= " AND ";
					$w .= "(";
					for ($i = 5; $i < $properties['total']; $i++) {
						$w .= $properties['data'][$i] . " LIKE '%" . $post['form']['keyword'] . "%' OR ";
					}
					$w .= $properties['data'][$properties['total'] - 1] . " LIKE '%" . $post['form']['keyword'] . "%' ";
					$w .= ")";
				}
			}
			if ($post['form']['tahun']) {
				$w .= " AND tahun ='" . $post['form']['tahun'] . "'";
			}
			$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
			$this -> view -> assign("search", $post['form']);
		} else {
			$tahunDefault = ACTIVE_YEAR;
			$w .= " AND tahun ='" . $tahunDefault . "'";
			$post['form']['tahun'] = $tahunDefault;

			$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
			$this -> view -> assign("search", $post['form']);
		}
		$search_json = urlencode(json_encode($post['form']));
		$this->view->assign("search_json", $search_json);
		//PAGING
		$offset = (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
		$limit = LIMIT;
		$data = $this -> tables -> query('SELECT * FROM ' . $this -> viewName . ' WHERE ' . $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
		$All = $this -> db -> query('SELECT count(' . $this -> primaryKey . ') as x FROM ' . $this -> viewName . ' WHERE ' . $w);
		$totalRow = (isset($All -> fields['x']) ? $All -> fields['x'] : 0);
		// $this->debug->show($data);

		$this->cekLockSystem(4, $post['form']['tahun']);
		$this -> view -> pagination($this -> view, $totalRow, $offset + 1, $limit, $urlVar);

		$this->view->assign("listExport", $listExport);
		$this -> view -> assign("urlVar", $urlVar);
		$this -> view -> assign("totalRow", $totalRow);
		$this -> view -> assign("limit", $limit);
		$this -> view -> assign("page", $offset);
		$this -> view -> assign("view", $data['data']);
	}

  private function cekLockSystem($menu,$tahun) {
		$messageLock = null;
		$lockAction = 0;
		$data = $this -> tables -> query("SELECT * FROM rf_lock_system WHERE deleted = 0 AND aktif_tahunan = 1");
		if ($data['total']) {
			$data['data'][0]['menu_tahunan'] = explode(",", $data['data'][0]['menu_tahunan']);
			$data['data'][0]['tahun'] = explode(",", $data['data'][0]['tahun']);
			if (is_numeric(array_search($menu, $data['data'][0]['menu_tahunan'])) && is_numeric(array_search($tahun, $data['data'][0]['tahun']))) {
				$lockAction = 1;
			}
		}
		$this -> view -> assign("messageLock", $messageLock);
		$this -> view -> assign("lockAction", $lockAction);
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
