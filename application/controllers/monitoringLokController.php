<?php
	/**
	 * created at 	: 01/10/2020
	 * created by 	: dasendria team
	 * desc		  	: controller Lokasi Pemantauan IKLHK
	 *
	 */
    class monitoringLokController extends Front{
    		public function init() {
          // if($_SERVER['REMOTE_ADDR']!='103.144.175.182') die('sedang development');
          // ini_set("display_errors",TRUE);
    			($this->session->get('memberIKLH')?:$this->redirect("login"));

	    		//SET CUSTOM VIEWS FOLDER
	    		$this->view->setFolder('be');

	    		//LOAD MODELS
	    		$this->loadModel("tables");
	    		$this->loadModel("ref");
			$this->loadModel("users");

      // LOAD FUNCTION
      require_once "functions.php";
      $this -> functions = new functions();
      $this -> view -> assign("functions", $this -> functions);
      require_once "excelReader.php";

			//GLOBAL VAR
			$this->me 			= $this->session->get('memberIKLH');
			$this->ctrl 		= $this->uri->getController();
			$this->act 			= $this->uri->getAction();
			$this->url			= $this->ctrl . '/' . $this->act;

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

		}
		//INDEX FUNCTION IS A DEFAULT ACTION
		public function index(){
      // die("under maintenance");

			// $this->getData();
      // $this->rfData();
			$this->view->assign("masterActive","active");
			$this->view->assign("show",$show);
			$this->view->assign("message",$message);
			$this->view->assign("icons",'<i class="la la-map-marker"></i>');
			$this->view->assign("title",'Monitoring Lokasi Pemantauan');
			$this->view->display("index.html");
		}

    public function ika(){
      // die("under maintenance");

      $post 		= $this->post();
      if(isset($post['search'])){
        if ($post['form']['pemantauan'] == 2) {
          $viewName 		= "v_monitoring_ika_use";
        }elseif ($post['form']['pemantauan'] == 1) {
          $viewName 		= "v_monitoring_ika_not_use";
        }
      }else {
        $viewName 		= "v_monitoring_ika_not_use";
      }
			$primaryKey	= "uid_lokasi_pemantauan";

			$this->getData($viewName, $primaryKey);
			$this->rfData();
			$this->view->assign("masterActive","active");
			$this->view->assign("show",$show);
			$this->view->assign("message",$message);
			$this->view->assign("icons",'<i class="la la-map-marker"></i>');
			$this->view->assign("title",'Monitoring Lokasi Pemantauan IKA');
			$this->view->display("index.html");
		}

    public function iku(){
      // die("under maintenance");
      $post 		= $this->post();
      if(isset($post['search'])){
        if ($post['form']['pemantauan'] == 2) {
          $viewName 		= "v_monitoring_iku_use";
        }elseif ($post['form']['pemantauan'] == 1) {
          $viewName 		= "v_monitoring_iku_not_use";
        }
      }else {
        $viewName 		= "v_monitoring_iku_not_use";
      }

			$primaryKey	= "uid_lokasi_pemantauan";

			$this->getData($viewName, $primaryKey);
			$this->rfData();
			$this->view->assign("masterActive","active");
			$this->view->assign("show",$show);
			$this->view->assign("message",$message);
			$this->view->assign("icons",'<i class="la la-map-marker"></i>');
			$this->view->assign("title",'Monitoring Lokasi Pemantauan IKU');
			$this->view->display("index.html");
		}

    public function ikal(){
      // die("under maintenance");
      $post 		= $this->post();
      if(isset($post['search'])){
        if ($post['form']['pemantauan'] == 2) {
          $viewName 		= "v_monitoring_ikal_use";
        }elseif ($post['form']['pemantauan'] == 1) {
          $viewName 		= "v_monitoring_ikal_not_use";
        }
      }else {
        $viewName 		= "v_monitoring_ikal_not_use";
      }
			$primaryKey	= "uid_lokasi_pemantauan";

			$this->getData($viewName, $primaryKey);
			$this->rfData();
			$this->view->assign("masterActive","active");
			$this->view->assign("show",$show);
			$this->view->assign("message",$message);
			$this->view->assign("icons",'<i class="la la-map-marker"></i>');
			$this->view->assign("title",'Monitoring Lokasi Pemantauan IKAL');
			$this->view->display("index.html");
		}

		private function getData($viewName, $primaryKey){
      $post 		= $this->post();
      $post['form']['tahun'] = (isset($post['form']['tahun']) ? $post['form']['tahun'] : ACTIVE_YEAR);
      // $post['form']['uid_rf_component'] = (isset($post['form']['uid_rf_component']) ? $post['form']['uid_rf_component'] : 1);
      if($this->params('search')){
				$post['search'] = TRUE;
				$post['form'] 	= json_decode(urldecode($this->params('search')),1);
			}
			$properties	= $this->_getProperties($viewName);
			$urlVar  	= BASEURL . $this->url . '/';
			$w 			= 'deleted=0 AND hidden=0';
      if($this->me['role_user'] == 3){
        $w .=" AND uid_kabkota =".$this->me['uid_kabkota'];
      }elseif ($this->me['role_user'] == 2) {
        $w .=" AND uid_provinsi =".$this->me['uid_provinsi'];
      }elseif ($this->me['role_user'] == 4 || $this->me['role_user'] == 5) {
        $w .=" AND kd_regional =".$this->me['uid_regional'];
      }
			$o 			  = " uid_lokasi_pemantauan ASC";
			if(isset($post['search'])){
        $post['form']['keyword'] = str_replace("=","",strip_tags($post['form']['keyword']));
				if($post['form']['keyword']){
					if($properties['total']){
						$w .= " AND ";
						$w .= "(";
						for($i=5;$i<$properties['total'];$i++){
							$w .= $properties['data'][$i] . " LIKE '%".$post['form']['keyword']."%' OR ";
						}
						$w .= $properties['data'][$properties['total']-1] . " LIKE '%".$post['form']['keyword']."%' ";
						$w .= ")";
					}
				}
        if ($post['form']['uid_rf_pelaksana']) {
          $w .= " AND uid_rf_pelaksana =".$post['form']['uid_rf_pelaksana'];
        }
        if ($post['form']['tahun']) {
          $w .= " AND tahun LIKE '%".$post['form']['tahun']."%'";
        }
        if ($post['form']['src_kabkota2']) {
            $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
        }
        if ($post['form']['src_prop']) {
            $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
        }
        if ($post['form']['src_reg']) {
            $w .= " AND kd_regional = " . $post['form']['src_reg'];
        }
				$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
				$this->view->assign("search",$post['form']);
        $search_json = urlencode(json_encode($post['form']));
        $this->view->assign("search_json", $search_json);
			} else {
        $w .= " AND tahun LIKE '%" . ACTIVE_YEAR . "%'";

          $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
  				$this->view->assign("search",$post['form']);
          $search_json = urlencode(json_encode($post['form']));
          $this->view->assign("search_json", $search_json);
      }
			//PAGING
			$offset   	= (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
			$limit	  	= LIMIT;
      $data	  	= $this->tables->query('SELECT * FROM ' . $viewName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
			$All	  		= $this->db->query('SELECT count('.$primaryKey.') as x FROM '.$viewName.' WHERE '. $w);
			$totalRow 	= (isset($All->fields['x']) ? $All->fields['x'] : 0);
			// $this->debug->show('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
      // $this->debug->show($All);
			$this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
      $listExport = $this->_getListExport($totalRow);
      $this->view->assign("listExport", $listExport);
			$this->view->assign("urlVar", $urlVar);
			$this->view->assign("totalRow", $totalRow);
			$this->view->assign("limit", $limit);
			$this->view->assign("page", $offset);
      $this->view->assign("view",$data['data']);
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

    public function dataExcelIka($w=null, $offset=null)
    {
      $post = $this -> post();
      if(isset($post['search'])){
        if ($post['form']['pemantauan'] == 2) {
          $viewName 		= "v_monitoring_ika_use";
        }elseif ($post['form']['pemantauan'] == 1) {
          $viewName 		= "v_monitoring_ika_not_use";
        }
      }else {
        $viewName 		= "v_monitoring_ika_not_use";
      }
			$primaryKey	= "uid_lokasi_pemantauan";
        $offset = $this->params('offset');
        $properties = $this -> _getProperties($viewName);
        $w = 'deleted=0 AND hidden=0';
        if ($this -> me['role_user'] == 3) {
            $w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND kd_regional =" . $this -> me['uid_regional'];
        }
        $o = " uid_lokasi_pemantauan ASC";
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
            if ($post['form']['uid_rf_pelaksana']) {
              $w .= " AND uid_rf_pelaksana =".$post['form']['uid_rf_pelaksana'];
            }
            if ($post['form']['tahun']) {
              $w .= " AND tahun LIKE '%".$post['form']['tahun']."%'";
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this->view->assign("search",$post['form']);
            $search_json = urlencode(json_encode($post['form']));
            $this->view->assign("search_json", $search_json);
        } else {
            $w .= " AND tahun LIKE '%" . ACTIVE_YEAR . "%'";
            $post['form']['tahun'] = ACTIVE_YEAR;

            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
    				$this->view->assign("search",$post['form']);
            $search_json = urlencode(json_encode($post['form']));
            $this->view->assign("search_json", $search_json);
        }
        $this->tables->set($viewName, $primaryKey);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        // $this->debug->show($data);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="MONITORING_LOKASI_PEMANTAUAN_IKA'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/monitoringLok/ika/excel.html');
        echo $html;
    }

    public function dataExcelIku($w=null, $offset=null)
    {
      $post = $this -> post();
      if(isset($post['search'])){
        if ($post['form']['pemantauan'] == 2) {
          $viewName 		= "v_monitoring_iku_use";
        }elseif ($post['form']['pemantauan'] == 1) {
          $viewName 		= "v_monitoring_iku_not_use";
        }
      }else {
        $viewName 		= "v_monitoring_iku_not_use";
      }
			$primaryKey	= "uid_lokasi_pemantauan";
        $offset = $this->params('offset');
        $properties = $this -> _getProperties($viewName);
        $w = 'deleted=0 AND hidden=0';
        if ($this -> me['role_user'] == 3) {
            $w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND kd_regional =" . $this -> me['uid_regional'];
        }
        $o = " uid_lokasi_pemantauan ASC";
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
            if ($post['form']['uid_rf_pelaksana']) {
              $w .= " AND uid_rf_pelaksana =".$post['form']['uid_rf_pelaksana'];
            }
            if ($post['form']['tahun']) {
              $w .= " AND tahun LIKE '%".$post['form']['tahun']."%'";
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this->view->assign("search",$post['form']);
            $search_json = urlencode(json_encode($post['form']));
            $this->view->assign("search_json", $search_json);
        } else {
            $w .= " AND tahun LIKE '%" . ACTIVE_YEAR . "%'";
            $post['form']['tahun'] = ACTIVE_YEAR;

            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
    				$this->view->assign("search",$post['form']);
            $search_json = urlencode(json_encode($post['form']));
            $this->view->assign("search_json", $search_json);
        }
        $this->tables->set($viewName, $primaryKey);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        // $this->debug->show($data);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="MONITORING_LOKASI_PEMANTAUAN_IKU'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/monitoringLok/iku/excel.html');
        echo $html;
    }

    public function dataExcelIkal($w=null, $offset=null)
    {
      $post = $this -> post();
      if(isset($post['search'])){
        if ($post['form']['pemantauan'] == 2) {
          $viewName 		= "v_monitoring_ikal_use";
        }elseif ($post['form']['pemantauan'] == 1) {
          $viewName 		= "v_monitoring_ikal_not_use";
        }
      }else {
        $viewName 		= "v_monitoring_ikal_not_use";
      }
			$primaryKey	= "uid_lokasi_pemantauan";
        $offset = $this->params('offset');
        $properties = $this -> _getProperties($viewName);
        $w = 'deleted=0 AND hidden=0';
        if ($this -> me['role_user'] == 3) {
            $w .= " AND uid_kabkota =" . $this -> me['uid_kabkota'];
        } elseif ($this -> me['role_user'] == 2) {
            $w .= " AND uid_provinsi =" . $this -> me['uid_provinsi'];
        } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
            $w .= " AND kd_regional =" . $this -> me['uid_regional'];
        }
        $o = " uid_lokasi_pemantauan ASC";
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
            if ($post['form']['uid_rf_pelaksana']) {
              $w .= " AND uid_rf_pelaksana =".$post['form']['uid_rf_pelaksana'];
            }
            if ($post['form']['tahun']) {
              $w .= " AND tahun LIKE '%".$post['form']['tahun']."%'";
            }
            if ($post['form']['src_kabkota2']) {
                $w .= " AND uid_kabkota = " . $post['form']['src_kabkota2'];
            }
            if ($post['form']['src_prop']) {
                $w .= " AND uid_provinsi = " . $post['form']['src_prop'];
            }
            if ($post['form']['src_reg']) {
                $w .= " AND kd_regional = " . $post['form']['src_reg'];
            }
            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
            $this->view->assign("search",$post['form']);
            $search_json = urlencode(json_encode($post['form']));
            $this->view->assign("search_json", $search_json);
        } else {
            $w .= " AND tahun LIKE '%" . ACTIVE_YEAR . "%'";
            $post['form']['tahun'] = ACTIVE_YEAR;

            $urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
    				$this->view->assign("search",$post['form']);
            $search_json = urlencode(json_encode($post['form']));
            $this->view->assign("search_json", $search_json);
        }
        $this->tables->set($viewName, $primaryKey);
        $paging	= array("offset"=>$offset, "limit"=>LIMIT_DOWNLOAD_EXCEL);
        $data	= $this->tables->fetch($w, $o, $paging);

        // $this->debug->show($data);
        $this->view->assign("viewExcel", $data);

        header("Content-type: application/vnd-ms-excel");
        header('Content-Disposition: attachment; filename="MONITORING_LOKASI_PEMANTAUAN_IKAL'.time().'.xls"');
        $html = $this->view->fetch('parts/contents/monitoringLok/ikal/excel.html');
        echo $html;
    }

		private function rfData(){
      if ($this -> me['role_user'] == 2) {
          $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
          $this -> tables -> set("rf_provinsi", "kd_propinsi");
          $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
          $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
          $wLokasi = " AND uid_provinsi=" . $this -> me['uid_provinsi'];
          $this -> tables -> set("rf_kabkota", "kd_kota");
          $rf = $this -> tables -> fetch("deleted=0 AND kd_provinsi=" . $this -> me['uid_provinsi']);
          $this -> view -> assign("kabkota", $rf['data']);
      } elseif ($this -> me['role_user'] == 3) {
          $wProvinsi = "kd_propinsi=" . $this -> me['uid_provinsi'];
          $this -> tables -> set("rf_provinsi", "kd_propinsi");
          $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
          $wRegional = "kd_regional=".$rf['data'][0]['kd_regional'];
          $wLokasi = " AND uid_kabkota=" . $this -> me['uid_kabkota'];
      } elseif ($this -> me['role_user'] == 4 || $this -> me['role_user'] == 5) {
          $wProvinsi = "kd_regional =" . $this -> me['uid_regional'];
          $wLokasi = " AND kd_regional =" . $this -> me['uid_regional'];
      } else {
          $wProvinsi = "";
          $wLokasi = "";
      }

      $this -> tables -> set("rf_regional", "kd_regional");
      $rf = $regSelect = $this -> tables -> fetch($wRegional);
      $this -> view -> assign("regional", $rf['data']);

      $this -> tables -> set("rf_provinsi", "kd_propinsi");
      $rf = $propSelect = $this -> tables -> fetch($wProvinsi);
      $this -> view -> assign("provinsi", $rf['data']);

      $this->tables->set("rf_pelaksana","uid_rf_pelaksana");
			$rf = $this->tables->fetch("deleted = 0");
			$this->view->assign("pelaksana",$rf['data']);

      $this -> tables -> set("rf_provinsi", "kd_propinsi");
      $rf = $this -> tables -> fetch('');
      $this -> view -> assign("provinsiSelect2", $rf['data']);

      $this -> tables -> set("rf_kabkota", "kd_kota");
      $rf = $this -> tables -> fetch('deleted = 0');
      $this -> view -> assign("kabkotaSelect2", $rf['data']);

      if ($this->me['role_user']==2) {
          $this -> tables -> set("rf_kabkota", "kd_kota");
          $rf = $this -> tables -> fetch('deleted=0 AND kd_provinsi='.$this -> me['uid_provinsi']);
          $this -> view -> assign("kabkotaSelect", $rf['data']);
      }
      if ($this->me['role_user']==4 || $this->me['role_user']==5) {
          $this -> tables -> set("rf_provinsi", "kd_propinsi");
          $rf = $this -> tables -> fetch('kd_regional='.$this -> me['uid_regional']);
          $this -> view -> assign("propSelect", $rf['data']);
          // $this->debug->show($rf);
      }
      if ($this->me['role_user'] < 2) {
        $this -> view -> assign("regSelect", $regSelect['data']);
        $this -> view -> assign("propSelect", $propSelect['data']);
      }
		}

		private function _getProperties($model){
			$sql = "SHOW COLUMNS FROM ".$model;
			$result = $this->db->fetch($sql);
			//$this->debug->show($result);
			if($result['total']){
				$data = array();
				foreach($result['data'] as $key=>$val){
					$data[$key] = $val['Field'];
				}
				$result['data'] = $data;
				return $result;
			}else{
				die('Coloums of table '. $model .' not found');
			}
		}


 	}
?>
