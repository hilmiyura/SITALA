<?php

/**
 * created at : 13/07/2021
 * created by : Dasendria team
 * desc : controller for target iklh
 */
class targetiklhController extends Front {
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
		$this -> viewName = "v_target_iklh";
		$this -> primaryKey = "uid_target_iklh";
		$this -> where = "deleted = 0";

		$this->view->assign("aksesAdmin", $this->ref->aksesAdmin($this->me));
	}

	public function index() {

		$post = $this -> post();
		if (isset($post['submit'])) {
			$post['form']['cruser'] = $this -> me['uid_users'];
			if($post['form'][$this->primaryKey]){
				unset($post['form']['cruser']);
				$post['form']['chuser'] = $this->me['uid_users'];
			}
			$post['form']['iku'] = str_replace(",", '.', $post['form']['iku']);
			$post['form']['ika'] = str_replace(",", '.', $post['form']['ika']);
			$post['form']['ikl'] = str_replace(",", '.', $post['form']['ikl']);
			$post['form']['ikal'] = str_replace(",", '.', $post['form']['ikal']);
			$post['form']['uid_kabkota'] = ($post['form']['target'] == 1 ? $post['form']['uid_kabkota'] : 0);
			if ($post['form']['uid_kabkota']) {
				$wTarget = " deleted = 0 AND uid_provinsi=".$post['form']['uid_provinsi']." AND uid_kabkota =".$post['form']['uid_kabkota']." AND target = 1 AND tahun=".$post['form']['tahun'];
				$post['form']['iklh'] = (0.376 * $post['form']['ika']) + (0.405 * $post['form']['iku']) + (0.219 * $post['form']['ikl']);
				// =(0,376*J4)+(0,405*I4)+(0,219*K4)
			} else {
				$wTarget = " deleted = 0 AND uid_provinsi=".$post['form']['uid_provinsi']." AND target = 2 AND tahun=".$post['form']['tahun'];
				$post['form']['iklh'] = (0.34 * $post['form']['ika']) + (0.428 * $post['form']['iku']) + (0.133 * $post['form']['ikl']) + (0.099 * $post['form']['ikal']);
				// =(0,34*J3)+(0,428*I3)+(0,133*K3)+(0,099*L3)
			}
			if(!$post['form'][$this->primaryKey]){
				$cekTarget = $this->tables->query("SELECT * FROM rf_target_iklh WHERE ".$wTarget);
			}
			if($cekTarget['total']){
				$message = "Gagal menyimpan data, tahun ".$post['form']['tahun']." sudah memiliki target nilai IKLH, silahkan update data yang sudah ada jika ada perubahan !";
			}else{
				$this -> tables -> set("rf_target_iklh", "uid_target_iklh");
				if ($this -> tables -> post($post)) {
					$message = "Berhasil menyimpan data !";
				} else {
					$message = "Gagal menyimpan data !";
				}
			}
		}

		if (isset($post['submit-excel'])) {
				$post['form']['cruser'] = $this -> me['uid_users'];
				$val = $_FILES['file_excel'];
				$ext = strtolower(strrchr($val['name'], "."));
				if ($ext == ".xls") {
						$files = $this -> functions -> uploadFile($_FILES['file_excel']);
				}
				if ($files) {
						$excelReader = new Spreadsheet_Excel_Reader(UPLOADFOLDER . "docs/" . $files, true);
						$rows = $excelReader -> rowcount(0);
						for ($c = 1; $c <= 8; $c++) {
								for ($d = 3; $d <= $rows; $d++) {
										if ($excelReader -> val($d, $c)) {
												$data[$d][$c] = trim($excelReader -> val($d, $c));
										} else {
												$data[$d][$c] = "-";
										};
								}
						}
						unlink(UPLOADFOLDER . "docs/" . $files);

						$tmpCn = 1;
						$tmpTahun = NULL;
						$tmpProv = NULL;
						foreach ($data as $key => $vals) {
								if ($vals[1] != "-") {
										$postTarget['form']['uid_target_iklh'] = "";
										$postTarget['form']['cruser'] = $post['form']['cruser'];
										if ($vals[4] == "KABUPATEN") {
											$postTarget['form']['target'] = 1;
										} else {
											$postTarget['form']['target'] = 2;
										}
										$cek_provinsi = $this -> tables -> query("SELECT * FROM rf_provinsi WHERE nama_propinsi LIKE '".$vals[1]."'");
										if($cek_provinsi['total'] == 0){
											$tmpProv[] = $vals[1];
										}
										$postTarget['form']['uid_provinsi'] = $cek_provinsi['data'][0]['kd_propinsi'];
										if ($vals[2] == "NULL") {
											$postTarget['form']['uid_kabkota'] = 0;
										} else {
											$cek_kabkota = $this -> tables -> query("SELECT * FROM rf_kabkota WHERE deleted=0 AND nama_kabkot LIKE '".$vals[2]."' AND kd_provinsi=".$cek_provinsi['data'][0]['kd_propinsi']);
											$postTarget['form']['uid_kabkota'] = $cek_kabkota['data'][0]['kd_kota'];
										}
										// $this->debug->show($postTarget);
										$postTarget['form']['tahun'] = $vals[3];
										$postTarget['form']['iku'] = str_replace(",", '.', $vals[5]);
										$postTarget['form']['ika'] = str_replace(",", '.', $vals[6]);
										$postTarget['form']['ikl'] = str_replace(",", '.', $vals[7]);
										$postTarget['form']['ikal'] = str_replace(",", '.', $vals[8]);
										// $this->debug->show($postTarget);
										if ($postTarget['form']['uid_kabkota']) {
											$wTarget = " deleted = 0 AND uid_provinsi=".$postTarget['form']['uid_provinsi']." AND uid_kabkota =".$postTarget['form']['uid_kabkota']." AND target = 1 AND tahun=".$postTarget['form']['tahun'];
											$postTarget['form']['iklh'] = (0.376 * $postTarget['form']['ika']) + (0.405 * $postTarget['form']['iku']) + (0.219 * $postTarget['form']['ikl']);
											// =(0,376*J4)+(0,405*I4)+(0,219*K4)
										} else {
											$wTarget = " deleted = 0 AND uid_provinsi=".$postTarget['form']['uid_provinsi']." AND target = 2 AND tahun=".$postTarget['form']['tahun'];
											$postTarget['form']['iklh'] = (0.34 * $postTarget['form']['ika']) + (0.428 * $postTarget['form']['iku']) + (0.133 * $postTarget['form']['ikl']) + (0.099 * $postTarget['form']['ikal']);
											// =(0,34*J3)+(0,428*I3)+(0,133*K3)+(0,099*L3)
										}
										$cek_tahun_target = $this -> tables -> query("SELECT * FROM rf_target_iklh WHERE ".$wTarget);
										$postTarget['submit'] = true;
										if ($cek_tahun_target['total'] != "") {
											$wil = $vals[1].' - '.$vals[2];
											$tmpTahun[] = $wil.' tahun '.$postTarget['form']['tahun'];
										}else {
											if($postTarget['form']['uid_provinsi']){
												$this -> tables -> set("rf_target_iklh", "uid_target_iklh");
												$this -> tables -> post($postTarget);
											}
										}
								}
								// $this->debug->show($tmpKodeLokasi);
								if ($tmpTahun) {
									$message = "Target nilai IKLH provinsi ".implode(', ', $tmpTahun)." tidak tersimpan oleh sistem dikarenakan tahun tersebut sudah ada nilai. Selain dari tahun tersebut data berhasil disimpan";
									$message .= ($tmpProv ? "<br><br>Target nilai IKLH provinsi ".implode(',',array_unique($tmpProv)). " tidak tersimpan karena provinsi tidak terdaftar disistem atau nama tidak sesuai dengan disistem" : "");
								}else {
									if (count($data) == $tmpCn) {
											$message = "Berhasil menyimpan data !". ($tmpProv ? ", provinsi ".implode(',',$tmpProv). " Tidak terdaftar disistem atau nama tidak sesuai dengan disistem" : "");
									} else {
											$message = "Gagal menyimpan data !";
									}
								}
								$tmpCn++;
						}
				}
		}

		$this -> rfData();
		$this -> getData();
		$this -> view -> assign("masterActive", "active");
		$this -> view -> assign("show", $show);
		$this -> view -> assign("message", $message);
		$this -> view -> assign("icons", '<i class="la la-tasks"></i>');
		$this -> view -> assign("title", 'Target Nilai IKLH');
		$this -> view -> display("index.html");
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
			$o = " uid_provinsi,uid_kabkota,tahun ASC";
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
						$w .= " AND tahun LIKE '%".$post['form']['tahun']."%'";
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
			$this->tables->set($this->viewName, $this->primaryKey);
			$paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
			$data	= $this->tables->fetch($w, $o, $paging);

			$this->view->assign("viewExcel", $data);

			header("Content-type: application/vnd-ms-excel");
			header('Content-Disposition: attachment; filename="TARGET_IKLH_'.time().'.xls"');
			$html = $this->view->fetch('parts/contents/targetiklh/index/excel.html');
			echo $html;
	}

	public function editData() {
		header("Content-Type: application/json; charset=UTF-8");
		if ($this -> params("x")) {
			$this -> tables -> set("rf_target_iklh", "uid_target_iklh");
			$dataEdit = $this -> tables -> fetch("deleted = 0 AND uid_target_iklh=" . $this -> params("x"));
			echo json_encode($dataEdit['data'][0]);
		}
	}

	public function deletedData() {
		$post = $this -> post();
		if (isset($post['x'])) {
			$this -> tables -> set("rf_target_iklh", "uid_target_iklh");
			if ($this -> tables -> softDelete($post['x'])) {
				echo json_encode(array('statusCode' => 200, 'message' => $this -> message -> delete('success')));
			} else {
				echo json_encode(array('statusCode' => 400, 'message' => $this -> message -> delete('failed')));
			}
		} else {
			echo json_encode(array('statusCode' => 403, 'message' => $this -> message -> access()));
		}
	}

	private function rfData() {

		if ($this -> me['role_user'] == 2) {
			$wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
			$this -> tables -> set("rf_kabkota", "kd_kota");
			$rf = $this -> tables -> fetch("deleted=0 AND kd_provinsi=" . $this -> me['uid_provinsi']);
			$this -> view -> assign("kabkota", $rf['data']);
		} elseif ($this -> me['role_user'] == 3) {
			$wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
		} elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
			$wProvinsi = "kd_regional =" . $this -> me['uid_regional'];
		} else {
			$wProvinsi = "";
		}

		$this -> tables -> set("rf_provinsi", "kd_propinsi");
		$rf = $this -> tables -> fetch($wProvinsi);
		$this -> view -> assign("provinsi", $rf['data']);
		// $this->debug->show($rf);
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
		// if($_SERVER['REMOTE_ADDR'] == '180.252.90.234'){
		// 	$lockAction = 0;
		// }
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
