<?php

/**
 * created at : 14/03/2024
 * created by : Dasendria team
 * desc : controller for user detail periode
 */
class usersDetailPeriodeController extends Front {
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

		$this -> view -> assign("primaryKey", "uid_users_detail_periode");
		$this -> viewName = "v_users_detail_periode";
		$this -> primaryKey = "uid_users_detail_periode";
		$this -> where = "deleted = 0";

		$this->view->assign("aksesAdmin", $this->ref->aksesAdmin($this->me));
	}

	public function index() {
			$post = $this->post();
		if (isset($post['submit-excel'])) {
				// $post['form']['cruser'] = $this -> me['uid_users'];
				$val = $_FILES['file_excel'];
				$ext = strtolower(strrchr($val['name'], "."));
				if ($ext == ".xls") {
						$files = $this -> functions -> uploadFile($_FILES['file_excel']);
				}
				if ($files) {
						$excelReader = new Spreadsheet_Excel_Reader(UPLOADFOLDER . "docs/" . $files, true);
						$rows = $excelReader -> rowcount(0);
						for ($c = 1; $c <= 11; $c++) {
								for ($d = 3; $d <= $rows; $d++) {
										if ($excelReader -> val($d, $c)) {
												$data[$d][$c] = trim($excelReader -> val($d, $c));
										}elseif (is_float($excelReader -> val($d, $c))) {
											$data[$d][$c] = trim($excelReader -> val($d, $c));
										}elseif (is_numeric($excelReader -> val($d, $c))) {
											$data[$d][$c] = trim($excelReader -> val($d, $c));
										}else {
												$data[$d][$c] = "-";
										};
								}
						}
						unlink(UPLOADFOLDER . "docs/" . $files);
						$tmpGagal = [];
						$tmpCn = 0;
						foreach ($data as $key => $vals) {
							if ($vals[1] != "-") {
								$cek_provinsi = $this -> tables -> query("SELECT * FROM rf_provinsi WHERE nama_propinsi LIKE '".$vals[1]."'");
								$postDetail['form']['uid_provinsi'] = $cek_provinsi['data'][0]['kd_propinsi'];
								$kabkotaField = mb_strtoupper($vals[2]);
								if ($kabkotaField == "NULL") {
									$postDetail['form']['uid_kabkota'] = 0;
								}elseif ($kabkotaField == "-") {
									$postDetail['form']['uid_kabkota'] = 0;
								} else {
									$cek_kabkota = $this -> tables -> query("SELECT * FROM rf_kabkota WHERE deleted=0 AND nama_kabkot LIKE '".$vals[2]."' AND kd_provinsi=".$cek_provinsi['data'][0]['kd_propinsi']);
									$postDetail['form']['uid_kabkota'] = $cek_kabkota['data'][0]['kd_kota'];
								}
								$postDetail['form']['periode'] = $vals[3];
								$postDetail['form']['luas_mangrove'] = str_replace(".", '', $vals[4]);
								$postDetail['form']['eg_rusak_berat'] = str_replace(".", '', $vals[5]);
								$postDetail['form']['eg_rusak_ringan'] = str_replace(".", '', $vals[6]);
								$postDetail['form']['eg_rusak_sangat_berat'] = str_replace(".", '', $vals[7]);
								$postDetail['form']['eg_rusak_sedang'] = str_replace(".", '', $vals[8]);
								$postDetail['form']['eg_tidak_rusak'] = str_replace(".", '', $vals[9]);
								$postDetail['form']['eg_fungsi_budaya'] = str_replace(".", '', $vals[10]);
								$postDetail['form']['eg_fungsi_lindung'] = str_replace(".", '', $vals[11]);

								$postDetail['form']['luas_mangrove'] = str_replace(",", '.', $postDetail['form']['luas_mangrove']);
								$postDetail['form']['eg_rusak_berat'] = str_replace(",", '.', $postDetail['form']['eg_rusak_berat']);
								$postDetail['form']['eg_rusak_ringan'] = str_replace(",", '.', $postDetail['form']['eg_rusak_ringan']);
								$postDetail['form']['eg_rusak_sangat_berat'] = str_replace(",", '.', $postDetail['form']['eg_rusak_sangat_berat']);
								$postDetail['form']['eg_rusak_sedang'] = str_replace(",", '.', $postDetail['form']['eg_rusak_sedang']);
								$postDetail['form']['eg_tidak_rusak'] = str_replace(",", '.', $postDetail['form']['eg_tidak_rusak']);
								$postDetail['form']['eg_fungsi_budaya'] = str_replace(",", '.', $postDetail['form']['eg_fungsi_budaya']);
								$postDetail['form']['eg_fungsi_lindung'] = str_replace(",", '.', $postDetail['form']['eg_fungsi_lindung']);

								$wDetail = "deleted = 0 AND periode =".$postDetail['form']['periode']." AND uid_provinsi=".$postDetail['form']['uid_provinsi']." AND uid_kabkota=".$postDetail['form']['uid_kabkota'];
								$checkDetail = $this -> tables -> query("SELECT * FROM users_detail_periode WHERE ".$wDetail);

								if($postDetail['form']['uid_provinsi'] > 0 && $postDetail['form']['periode'] > 0){
									$postDetail['submit'] = true;
									if($checkDetail['total']){
										$postDetail['form']['uid_users_detail_periode'] = $checkDetail['data'][0]['uid_users_detail_periode'];
									}
									$this -> tables -> set("users_detail_periode", "uid_users_detail_periode");
									$this -> tables -> post($postDetail);
								}else{
									$gProvinsi = ($vals[1] ? "Provinsi ".$vals[1] : "null");
									$gKabkota = ($vals[2] ? $vals[2] : "null");
									$gPeriode = ($vals[3] ? $vals[3] : "null");
									$tmpGagal[] = "Baris ".$key." Periode ".$gPeriode." - provinsi ".$gProvinsi." - kabkota ".$gKabkota;
								}
							}
							$tmpCn++;
						}
						if(count($tmpGagal)){
							$message = "Daftar data gagal tersimpan".implode(", ",$tmpGagal);
						}else{
							if (count($data) == $tmpCn) {
								$message = "Data berhasil disimpan";
							}else{
								$message = "Data gagal disimpan";
							}
						}
				}
		}

		$this -> getData();
		$this -> view -> assign("masterActive", "active");
		$this -> view -> assign("show", $show);
		$this -> view -> assign("message", $message);
		$this -> view -> assign("icons", '<i class="la la-tasks"></i>');
		$this -> view -> assign("title", 'Database Mangrove dan Ekosistem Gambut');
		$this -> view -> display("index.html");
	}

	public function setValueDetail(){
		$post = $this->post();
		if($post['tahun'] > 0 && $post['field'] && $post['prov'] > 0 ){
			$sqlCheck = $this->db->fetch("SELECT * FROM users_detail_periode WHERE uid_provinsi=".$post['prov']." AND uid_kabkota=".$post['kab']." AND periode=".$post['tahun']);
			if($sqlCheck['data'][0]['uid_users_detail_periode']){
				$update['form']['uid_users_detail_periode'] = $sqlCheck['data'][0]['uid_users_detail_periode'];
			}else{
				$update['form']['periode'] = $post['tahun'];
				$update['form']['uid_provinsi'] = $post['prov'];
				$update['form']['uid_kabkota'] = $post['kab'];
			}
			$update['form']['chuser'] = $this->me['uid_users'];
			$update['form'][$post['field']] = ($post['type'] == 'number' ? str_replace(",","",$post['value']) : $post['value']);
			$update['form'][$post['field']] = ($update['form'][$post['field']] ? $update['form'][$post['field']] : NULL);
			$update['submit'] = TRUE;
			$this->tables->set('users_detail_periode', 'uid_users_detail_periode');
			$updateStatus = $this->tables->post($update);
			if($updateStatus){
				echo json_encode(array("statusCode"=>200,"message"=>"Data berhasil diupdate"));
			}else{
				echo json_encode(array("statusCode"=>400,"message"=>"Data gagal diupdate"));
			}
		}
		die();
	}

	private function getData() {
		$this -> tables -> set($this -> viewName, $this -> primaryKey);
		$properties = $this -> _getProperties($this -> viewName);
		$urlVar = BASEURL . $this -> url . '/';
		$w = $this -> where;

		if ($this -> me['role_user'] == 3) {
			$w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
		} elseif ($this -> me['role_user'] == 2) {
			$w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
		} elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
			$w .= " AND kd_regional =" . $this -> me['uid_regional'];
		}

		$o = "uid_provinsi,uid_kabkota,periode ASC";
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
				$w .= " AND periode ='" . $post['form']['tahun'] . "'";
			}
			$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
			$this -> view -> assign("search", $post['form']);
		} else {
			$tahunDefault = ACTIVE_YEAR;
			$w .= " AND periode ='" . $tahunDefault . "'";
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
		$listExport = $this->_getListExport($totalRow);
		$this->view->assign("listExport", $listExport);
		$this -> view -> assign("urlVar", $urlVar);
		$this -> view -> assign("totalRow", $totalRow);
		$this -> view -> assign("limit", $limit);
		$this -> view -> assign("page", $offset);
		$this -> view -> assign("view", $data['data']);
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
			$w = $this -> where;
			if ($this -> me['role_user'] == 3) {
					$w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
			} elseif ($this -> me['role_user'] == 2) {
					$w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
			} elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
					$w .= " AND kd_regional =" . $this -> me['uid_regional'];
			}
			$o = " uid_provinsi,uid_kabkota,periode ASC";
			$post = $this -> post();
			// $this->debug->show($post);
			if ($this -> params('search')) {
					$post['search'] = true;
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
						$w .= " AND periode LIKE '%".$post['form']['tahun']."%'";
					}
					$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
					$this -> view -> assign("search", $post['form']);
			} else {
				$tahunDefault = ACTIVE_YEAR;
				$w .= " AND periode ='" . $tahunDefault . "'";
				$post['form']['tahun'] = $tahunDefault;

				$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
				$this -> view -> assign("search", $post['form']);
			}
			$this->tables->set($this->viewName, $this->primaryKey);
			$paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
			$data	= $this->tables->fetch($w, $o, $paging);

			$this->view->assign("viewExcel", $data);

			header("Content-type: application/vnd-ms-excel");
			header('Content-Disposition: attachment; filename="DETAIL_DAERAH_'.time().'.xls"');
			$html = $this->view->fetch('parts/contents/usersDetailPeriode/index/excel.html');
			echo $html;
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
