<?php
	/**
	 * created at 	: 01/10/2020
	 * created by 	: dasendria team
	 * desc		  	: controller INDEKS KUALITAS EKOSISTEM GAMBUT IKLHK
	 *
	 */
    class ikegController extends Front{
    		public function init() {
    			($this->session->get('memberIKLH')?:$this->redirect("login"));

	    		//SET CUSTOM VIEWS FOLDER
	    		$this->view->setFolder('be');

	    		//LOAD MODELS
	    		$this->loadModel("tables");
	    		$this->loadModel("ref");
			$this->loadModel("users");

			//GLOBAL VAR
			$this->me 			= $this->session->get('memberIKLH');
			$this->ctrl 			= $this->uri->getController();
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

			$this->view->assign("primaryKey","uid_pelaporan_ikeg");
			$this->viewName 		= "v_pelaporan_ikeg";
			$this->primaryKey 	= "uid_pelaporan_ikeg";
			$this->where			= "deleted = 0";

		}
		//INDEX FUNCTION IS A DEFAULT ACTION
		public function index(){

			$post = $this->post();
			if(isset($post['submit'])){
				$post['form']['cruser'] = $this->me['uid_users'];
				if(!$post['form']['uid_lokasi_pemantauan']){
					$post['form']['uid_rf_component'] = 4;
					$this->tables->set("lokasi_pemantauan","uid_lokasi_pemantauan");
					if($this->tables->post($post)){
						$post['form']['uid_lokasi_pemantauan'] = $this->tables->lastInsertID();
						$post['submit'] = TRUE;
						$post['form']['jumlah_burn_fleg'] 	= $post['form']['areal_terbakar_fleg'] + $post['form']['non_terbakar_fleg'];
						$post['form']['jumlah_burn_fbeg'] 	= $post['form']['areal_terbakar_fbeg'] + $post['form']['non_terbakar_fbeg'];
						$post['form']['jumlah_kanal_fleg'] 	= $post['form']['kanal_fleg'] + $post['form']['non_kanal_fleg'];
						$post['form']['jumlah_kanal_fbeg'] 	= $post['form']['kanal_fbeg'] + $post['form']['non_kanal_fbeg'];
						$this->tables->set("pelaporan_ikeg","uid_pelaporan_ikeg");
						if($this->tables->post($post)){
							$this->_count();
							$message = "Berhasil menyimpan data !";
						}else{
							$message = "Gagal menimpan data !";
						}
					}else{
						$message = "Gagal menimpan data !";
					}
				}else{
					$post['form']['jumlah_burn_fleg'] 	= $post['form']['areal_terbakar_fleg'] + $post['form']['non_terbakar_fleg'];
					$post['form']['jumlah_burn_fbeg'] 	= $post['form']['areal_terbakar_fbeg'] + $post['form']['non_terbakar_fbeg'];
					$post['form']['jumlah_kanal_fleg'] 	= $post['form']['kanal_fleg'] + $post['form']['non_kanal_fleg'];
					$post['form']['jumlah_kanal_fbeg'] 	= $post['form']['kanal_fbeg'] + $post['form']['non_kanal_fbeg'];
					$this->tables->set("pelaporan_ikeg","uid_pelaporan_ikeg");
					if($this->tables->post($post)){
						$this->_count();
						$message = "Berhasil menyimpan data !";
					}else{
						$message = "Gagal menimpan data !";
					}
				}
			}

			$this->getData();
			$this->rfData();
			$this->view->assign("pelaporanActive","active");
			$this->view->assign("show",$show);
			$this->view->assign("message",$message);
			$this->view->assign("icons",'<i class="la la-tree"></i>');
			$this->view->assign("title",'INDEKS KUALITAS EKOSISTEM GAMBUT');
			$this->view->display("index.html");
		}

		private function getData(){
			$this->tables->set($this->viewName,$this->primaryKey);
			$properties	= $this->_getProperties($this->viewName);
			$urlVar  	= BASEURL . $this->url . '/';
			if($this->me['role_user'] > 1){
				$w 		= $this->where. " AND cruser=".$this->me['uid_users'];
			}else{
				$w		= $this->where;
			}
			$o 			= $this->primaryKey . " DESC";
			$post 		= $this->post();
			if($this->params('search')){
				$post['search'] = TRUE;
				$post['form'] 	= json_decode(urldecode($this->params('search')),1);
			}
			if(isset($post['search'])){
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
				$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
				$this->view->assign("search",$post['form']);
			}
			//PAGING
			$offset   	= (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
			$limit	  	= LIMIT;
			$data	  	= $this->tables->query('SELECT * FROM ' . $this->viewName . ' WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit);
			$All	  		= $this->db->query('SELECT count('.$this->primaryKey.') as x FROM '.$this->viewName.' WHERE '. $w);
			$totalRow 	= (isset($All->fields['x']) ? $All->fields['x'] : 0);
			// $this->debug->show($data);
			$this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);
			$this->view->assign("urlVar", $urlVar);
			$this->view->assign("totalRow", $totalRow);
			$this->view->assign("limit", $limit);
			$this->view->assign("page", $offset);
			$this->view->assign("view",$data['data']);
		}

		public function editData(){
			header("Content-Type: application/json; charset=UTF-8");
			if($this->params("x")){
				$this->tables->set("pelaporan_ikeg","uid_pelaporan_ikeg");
				$dataEdit = $this->tables->fetch("deleted = 0 AND uid_pelaporan_ikeg=".$this->params("x"));
				echo json_encode($dataEdit['data'][0]);
			}
		}

		public function deletedData(){
			$post = $this->post();
			if(isset($post['x'])){
				$this->tables->set("pelaporan_ikeg","uid_pelaporan_ikeg");
				if($this->tables->softDelete($post['x'])){
					echo json_encode(array('statusCode' => 200, 'message' => $this->message->delete('success')));
				}else{
					echo json_encode(array('statusCode' => 400, 'message' => $this->message->delete('failed')));
				}
			}else{
				echo json_encode(array('statusCode' => 403, 'message' => $this->message->access()));
			}
		}

		private function _count(){
			$skemaIkeg = array();
			$post = $this->post();
			if(isset($post['submit'])){
				$tahun = date("Y",strtotime($post['form']['tanggal']));
				if($post['form']['uid_indeks_ikeg']){
					$cekData = $this->tables->query("SELECT a.tahun, a.uid_provinsi, a.uid_kabkota, a.uid_indeks_ikeg, b.nama_provinsi, c.nama_kabkota
													FROM indeks_ikeg a
													LEFT JOIN provinsi b ON b.id = a.uid_provinsi
													LEFT JOIN kabkota c ON c.id = a.uid_kabkota
													WHERE deleted =0 AND uid_indeks_ikeg=".$post['form']['uid_indeks_ikeg']);
					$tahun 	 						= $cekData['data'][0]['tahun'];
					$post['form']['uid_provinsi'] 	= $cekData['data'][0]['uid_provinsi'];
					$post['form']['uid_kabkota'] 	= $cekData['data'][0]['uid_kabkota'];
				}else{
					$cekData = $this->tables->query("SELECT uid_indeks_ikeg FROM indeks_ikeg WHERE deleted = 0 AND uid_kabkota=".$post['form']['uid_kabkota']." AND tahun=".$tahun);
				}

				if($cekData['total']){
					$skemaIkeg['uid_indeks_ikeg'] = $cekData['data'][0]['uid_indeks_ikeg'];
				}else{
					$skemaIkeg['uid_indeks_ikeg'] ="";
				}

				$BobotFleg 					= $this->tables->query("SELECT * FROM rf_bobot_ikeg WHERE jenis=1");
				$BobotFleg 					= $BobotFleg['data'][0];
				$BobotFleg['bobot_ikeg']		= $BobotFleg['bobot_ikeg'] / 100;
				$BobotFleg['bobot_terbakar']	= $BobotFleg['bobot_terbakar'] / 100;
				$BobotFleg['bobot_kanal']	= $BobotFleg['bobot_kanal'] / 100;

				$BobotFbeg = $this->tables->query("SELECT * FROM rf_bobot_ikeg WHERE jenis=2");
				$BobotFbeg 					= $BobotFbeg['data'][0];
				$BobotFbeg['bobot_ikeg']		= $BobotFbeg['bobot_ikeg'] / 100;
				$BobotFbeg['bobot_terbakar']	= $BobotFbeg['bobot_terbakar'] / 100;
				$BobotFbeg['bobot_kanal']	= $BobotFbeg['bobot_kanal'] / 100;

				$tanggal = " AND tanggal BETWEEN '".$tahun."-01-01' AND '".$tahun."-12-31' ";
				if($post['form']['uid_kabkota']){

					//terbakar
					$terbakarFleg 		= $BobotFleg['bobot_terbakar'] * $BobotFleg['terbakar'];
					$nonTerbakarFleg 	= $BobotFleg['bobot_terbakar'] * $BobotFleg['non_terbakar'];
					$terbakarFbeg 		= $BobotFbeg['bobot_terbakar'] * $BobotFbeg['terbakar'];
					$nonTerbakarFbeg 	= $BobotFbeg['bobot_terbakar'] * $BobotFbeg['non_terbakar'];

					//kanal
					$kanalFleg			= $BobotFleg['bobot_kanal'] * $BobotFleg['kanal'];
					$nonKanalFleg		= $BobotFleg['bobot_kanal'] * $BobotFleg['non_kanal'];
					$kanalFbeg			= $BobotFbeg['bobot_kanal'] * $BobotFbeg['kanal'];
					$nonKanalFbeg		= $BobotFbeg['bobot_kanal'] * $BobotFbeg['non_kanal'];

					$sql = "SELECT
						SUM(a.areal_terbakar_fleg) AS areal_terbakar_fleg,
						SUM(a.non_terbakar_fleg) AS non_terbakar_fleg,
						SUM(a.areal_terbakar_fbeg) AS areal_terbakar_fbeg,
						SUM(a.non_terbakar_fbeg) AS non_terbakar_fbeg,

						SUM(a.kanal_fleg) AS kanal_fleg,
						SUM(a.non_kanal_fleg) AS non_kanal_fleg,
						SUM(a.kanal_fbeg) AS kanal_fbeg,
						SUM(a.non_kanal_fbeg) AS non_kanal_fbeg,

						SUM(a.jumlah_burn_fleg) AS  jumlah_burn_fleg,
						SUM(a.jumlah_burn_fbeg) AS  jumlah_burn_fbeg,

						SUM(a.jumlah_kanal_fleg) AS  jumlah_kanal_fleg,
						SUM(a.jumlah_kanal_fbeg) AS  jumlah_kanal_fbeg

					FROM v_pelaporan_ikeg a
					WHERE deleted = 0 AND uid_kabkota=".$post['form']['uid_kabkota'].$tanggal;
					$dataNilaiIkeg = $this->tables->query($sql);
					$dataNilaiIkeg = $dataNilaiIkeg['data'][0];
					//$this->debug->show($sql);

					$skemaIkeg['json_data']['luas'] = $dataNilaiIkeg;

					$skemaIkeg['json_data']['persentase']['burn']['fleg']['areal_terbakar'] 	    = (($dataNilaiIkeg['areal_terbakar_fleg'] / $dataNilaiIkeg['jumlah_burn_fleg'])*100);
					$skemaIkeg['json_data']['persentase']['burn']['fleg']['non_terbakar'] 	    = (($dataNilaiIkeg['non_terbakar_fleg'] / $dataNilaiIkeg['jumlah_burn_fleg'])*100);
					$skemaIkeg['json_data']['persentase']['burn']['fbeg']['areal_terbakar'] 	    = (($dataNilaiIkeg['areal_terbakar_fbeg'] / $dataNilaiIkeg['jumlah_burn_fbeg'])*100);
					$skemaIkeg['json_data']['persentase']['burn']['fbeg']['non_terbakar'] 	    = (($dataNilaiIkeg['non_terbakar_fbeg'] / $dataNilaiIkeg['jumlah_burn_fbeg'])*100);
					$skemaIkeg['json_data']['persentase']['burn']['fleg']['jumlah_burn'] 	        = array_sum($skemaIkeg['json_data']['persentase']['burn']['fleg']);
					$skemaIkeg['json_data']['persentase']['burn']['fbeg']['jumlah_burn'] 	        = array_sum($skemaIkeg['json_data']['persentase']['burn']['fbeg']);

					$skemaIkeg['json_data']['persentase']['kanal']['fleg']['kanal'] 				= (($dataNilaiIkeg['kanal_fleg'] / $dataNilaiIkeg['jumlah_kanal_fleg'])*100);
					$skemaIkeg['json_data']['persentase']['kanal']['fleg']['non_kanal'] 			= (($dataNilaiIkeg['non_kanal_fleg'] / $dataNilaiIkeg['jumlah_kanal_fleg'])*100);
					$skemaIkeg['json_data']['persentase']['kanal']['fbeg']['kanal'] 				= (($dataNilaiIkeg['kanal_fbeg'] / $dataNilaiIkeg['jumlah_kanal_fbeg'])*100);
					$skemaIkeg['json_data']['persentase']['kanal']['fbeg']['non_kanal'] 			= (($dataNilaiIkeg['non_kanal_fbeg'] / $dataNilaiIkeg['jumlah_kanal_fbeg'])*100);
					$skemaIkeg['json_data']['persentase']['kanal']['fleg']['jumlah_kanal'] 		= array_sum($skemaIkeg['json_data']['persentase']['kanal']['fleg']);
					$skemaIkeg['json_data']['persentase']['kanal']['fbeg']['jumlah_kanal'] 		= array_sum($skemaIkeg['json_data']['persentase']['kanal']['fbeg']);

					$skemaIkeg['json_data']['bobot']['burn']['fleg']['areal_terbakar'] 			= ((($dataNilaiIkeg['areal_terbakar_fleg'] / $dataNilaiIkeg['jumlah_burn_fleg'])*100) * $terbakarFleg);
					$skemaIkeg['json_data']['bobot']['burn']['fleg']['non_terbakar'] 				= ((($dataNilaiIkeg['non_terbakar_fleg'] / $dataNilaiIkeg['jumlah_burn_fleg'])*100) * $nonTerbakarFleg);
					$skemaIkeg['json_data']['bobot']['burn']['fbeg']['areal_terbakar'] 			= ((($dataNilaiIkeg['areal_terbakar_fbeg'] / $dataNilaiIkeg['jumlah_burn_fbeg'])*100) * $terbakarFbeg);
					$skemaIkeg['json_data']['bobot']['burn']['fbeg']['non_terbakar'] 				= ((($dataNilaiIkeg['non_terbakar_fbeg'] / $dataNilaiIkeg['jumlah_burn_fbeg'])*100) * $nonTerbakarFbeg);
					$skemaIkeg['json_data']['bobot']['burn']['fleg']['jumlah_burn'] 				= array_sum($skemaIkeg['json_data']['bobot']['burn']['fleg']);
					$skemaIkeg['json_data']['bobot']['burn']['fbeg']['jumlah_burn'] 				= array_sum($skemaIkeg['json_data']['bobot']['burn']['fbeg']);

					$skemaIkeg['json_data']['bobot']['kanal']['fleg']['kanal'] 					= ((($dataNilaiIkeg['kanal_fleg'] / $dataNilaiIkeg['jumlah_kanal_fleg'])*100) * $kanalFleg);
					$skemaIkeg['json_data']['bobot']['kanal']['fleg']['non_kanal'] 				= ((($dataNilaiIkeg['non_kanal_fleg'] / $dataNilaiIkeg['jumlah_kanal_fleg'])*100) * $nonKanalFleg);
					$skemaIkeg['json_data']['bobot']['kanal']['fbeg']['kanal'] 					= ((($dataNilaiIkeg['kanal_fbeg'] / $dataNilaiIkeg['jumlah_kanal_fbeg'])*100) * $kanalFbeg);
					$skemaIkeg['json_data']['bobot']['kanal']['fbeg']['non_kanal'] 				= ((($dataNilaiIkeg['non_kanal_fbeg'] / $dataNilaiIkeg['jumlah_kanal_fbeg'])*100) * $nonKanalFbeg);
					$skemaIkeg['json_data']['bobot']['kanal']['fleg']['jumlah_kanal'] 			= array_sum($skemaIkeg['json_data']['bobot']['kanal']['fleg']);
					$skemaIkeg['json_data']['bobot']['kanal']['fbeg']['jumlah_kanal'] 			= array_sum($skemaIkeg['json_data']['bobot']['kanal']['fbeg']);

					$skemaIkeg['total_persen']['fleg'] 	= $skemaIkeg['json_data']['bobot']['burn']['fleg']['jumlah_burn'] + $skemaIkeg['json_data']['bobot']['kanal']['fleg']['jumlah_kanal'];
					$skemaIkeg['total_persen']['fbeg'] 	= $skemaIkeg['json_data']['bobot']['burn']['fbeg']['jumlah_burn'] + $skemaIkeg['json_data']['bobot']['kanal']['fbeg']['jumlah_kanal'];
					$skemaIkeg['ideks_ikeg'] 			= ($BobotFleg['bobot_ikeg']*$skemaIkeg['total_persen']['fleg'])+($BobotFbeg['bobot_ikeg']*$skemaIkeg['total_persen']['fbeg']);

					$postIkeg['form']['uid_indeks_ikeg'] = $skemaIkeg['uid_indeks_ikeg'];
					$postIkeg['form']['uid_provinsi'] 	= $post['form']['uid_provinsi'];
					$postIkeg['form']['uid_kabkota'] 	= $post['form']['uid_kabkota'];
					$postIkeg['form']['tahun'] 			= $tahun;
					$postIkeg['form']['json_data'] 		= json_encode($skemaIkeg['json_data']);
					$postIkeg['form']['persen_fleg'] 	= $skemaIkeg['total_persen']['fleg'];
					$postIkeg['form']['persen_fbeg'] 	= $skemaIkeg['total_persen']['fbeg'];
					$postIkeg['form']['nilai_indeks'] 	= $skemaIkeg['ideks_ikeg'];
					$postIkeg['form']['jenis_indeks'] 	= 0;
					$postIkeg['submit']					= TRUE;


					$this->tables->set("indeks_ikeg", "uid_indeks_ikeg");
					if($this->tables->post($postIkeg)){
						$this->countProvinsi($post['form']['uid_provinsi'], $tahun);
						return $cekData['data'][0]['nama_kabkota'].", Provinsi ".$cekData['data'][0]['nama_provinsi'];
					}else{
						return 0;
					}
				}
			}
		}

		private function countProvinsi($uid_provinsi, $tahun){
			if($uid_provinsi){
				$BobotFleg 					= $this->tables->query("SELECT * FROM rf_bobot_ikeg WHERE jenis=1");
				$BobotFleg 					= $BobotFleg['data'][0];
				$BobotFleg['bobot_ikeg']		= $BobotFleg['bobot_ikeg'] / 100;
				$BobotFleg['bobot_terbakar']	= $BobotFleg['bobot_terbakar'] / 100;
				$BobotFleg['bobot_kanal']	= $BobotFleg['bobot_kanal'] / 100;

				$BobotFbeg = $this->tables->query("SELECT * FROM rf_bobot_ikeg WHERE jenis=2");
				$BobotFbeg 					= $BobotFbeg['data'][0];
				$BobotFbeg['bobot_ikeg']		= $BobotFbeg['bobot_ikeg'] / 100;
				$BobotFbeg['bobot_terbakar']	= $BobotFbeg['bobot_terbakar'] / 100;
				$BobotFbeg['bobot_kanal']	= $BobotFbeg['bobot_kanal'] / 100;

				//terbakar
				$terbakarFleg 		= $BobotFleg['bobot_terbakar'] * $BobotFleg['terbakar'];
				$nonTerbakarFleg 	= $BobotFleg['bobot_terbakar'] * $BobotFleg['non_terbakar'];
				$terbakarFbeg 		= $BobotFbeg['bobot_terbakar'] * $BobotFbeg['terbakar'];
				$nonTerbakarFbeg 	= $BobotFbeg['bobot_terbakar'] * $BobotFbeg['non_terbakar'];

				//kanal
				$kanalFleg			= $BobotFleg['bobot_kanal'] * $BobotFleg['kanal'];
				$nonKanalFleg		= $BobotFleg['bobot_kanal'] * $BobotFleg['non_kanal'];
				$kanalFbeg			= $BobotFbeg['bobot_kanal'] * $BobotFbeg['kanal'];
				$nonKanalFbeg		= $BobotFbeg['bobot_kanal'] * $BobotFbeg['non_kanal'];

				$tanggal = " AND tanggal BETWEEN '".$tahun."-01-01' AND '".$tahun."-12-31' ";
				$sql = "SELECT
					SUM(a.areal_terbakar_fleg) AS areal_terbakar_fleg,
					SUM(a.non_terbakar_fleg) AS non_terbakar_fleg,
					SUM(a.areal_terbakar_fbeg) AS areal_terbakar_fbeg,
					SUM(a.non_terbakar_fbeg) AS non_terbakar_fbeg,

					SUM(a.kanal_fleg) AS kanal_fleg,
					SUM(a.non_kanal_fleg) AS non_kanal_fleg,
					SUM(a.kanal_fbeg) AS kanal_fbeg,
					SUM(a.non_kanal_fbeg) AS non_kanal_fbeg,

					SUM(a.jumlah_burn_fleg) AS  jumlah_burn_fleg,
					SUM(a.jumlah_burn_fbeg) AS  jumlah_burn_fbeg,

					SUM(a.jumlah_kanal_fleg) AS  jumlah_kanal_fleg,
					SUM(a.jumlah_kanal_fbeg) AS  jumlah_kanal_fbeg

				FROM v_pelaporan_ikeg a
				WHERE deleted = 0 AND uid_provinsi=".$uid_provinsi.$tanggal;
				$dataNilaiIkeg = $this->tables->query($sql);
				$dataNilaiIkeg = $dataNilaiIkeg['data'][0];

				$skemaIkeg['json_data']['luas'] = $dataNilaiIkeg;

				$skemaIkeg['json_data']['persentase']['burn']['fleg']['areal_terbakar'] 	    = (($dataNilaiIkeg['areal_terbakar_fleg'] / $dataNilaiIkeg['jumlah_burn_fleg'])*100);
				$skemaIkeg['json_data']['persentase']['burn']['fleg']['non_terbakar'] 	    = (($dataNilaiIkeg['non_terbakar_fleg'] / $dataNilaiIkeg['jumlah_burn_fleg'])*100);
				$skemaIkeg['json_data']['persentase']['burn']['fbeg']['areal_terbakar'] 	    = (($dataNilaiIkeg['areal_terbakar_fbeg'] / $dataNilaiIkeg['jumlah_burn_fbeg'])*100);
				$skemaIkeg['json_data']['persentase']['burn']['fbeg']['non_terbakar'] 	    = (($dataNilaiIkeg['non_terbakar_fbeg'] / $dataNilaiIkeg['jumlah_burn_fbeg'])*100);
				$skemaIkeg['json_data']['persentase']['burn']['fleg']['jumlah_burn'] 	        = array_sum($skemaIkeg['json_data']['persentase']['burn']['fleg']);
				$skemaIkeg['json_data']['persentase']['burn']['fbeg']['jumlah_burn'] 	        = array_sum($skemaIkeg['json_data']['persentase']['burn']['fbeg']);

				$skemaIkeg['json_data']['persentase']['kanal']['fleg']['kanal'] 				= (($dataNilaiIkeg['kanal_fleg'] / $dataNilaiIkeg['jumlah_kanal_fleg'])*100);
				$skemaIkeg['json_data']['persentase']['kanal']['fleg']['non_kanal'] 			= (($dataNilaiIkeg['non_kanal_fleg'] / $dataNilaiIkeg['jumlah_kanal_fleg'])*100);
				$skemaIkeg['json_data']['persentase']['kanal']['fbeg']['kanal'] 				= (($dataNilaiIkeg['kanal_fbeg'] / $dataNilaiIkeg['jumlah_kanal_fbeg'])*100);
				$skemaIkeg['json_data']['persentase']['kanal']['fbeg']['non_kanal'] 			= (($dataNilaiIkeg['non_kanal_fbeg'] / $dataNilaiIkeg['jumlah_kanal_fbeg'])*100);
				$skemaIkeg['json_data']['persentase']['kanal']['fleg']['jumlah_kanal'] 		= array_sum($skemaIkeg['json_data']['persentase']['kanal']['fleg']);
				$skemaIkeg['json_data']['persentase']['kanal']['fbeg']['jumlah_kanal'] 		= array_sum($skemaIkeg['json_data']['persentase']['kanal']['fbeg']);

				$skemaIkeg['json_data']['bobot']['burn']['fleg']['areal_terbakar'] 			= ((($dataNilaiIkeg['areal_terbakar_fleg'] / $dataNilaiIkeg['jumlah_burn_fleg'])*100) * $terbakarFleg);
				$skemaIkeg['json_data']['bobot']['burn']['fleg']['non_terbakar'] 				= ((($dataNilaiIkeg['non_terbakar_fleg'] / $dataNilaiIkeg['jumlah_burn_fleg'])*100) * $nonTerbakarFleg);
				$skemaIkeg['json_data']['bobot']['burn']['fbeg']['areal_terbakar'] 			= ((($dataNilaiIkeg['areal_terbakar_fbeg'] / $dataNilaiIkeg['jumlah_burn_fbeg'])*100) * $terbakarFbeg);
				$skemaIkeg['json_data']['bobot']['burn']['fbeg']['non_terbakar'] 				= ((($dataNilaiIkeg['non_terbakar_fbeg'] / $dataNilaiIkeg['jumlah_burn_fbeg'])*100) * $nonTerbakarFbeg);
				$skemaIkeg['json_data']['bobot']['burn']['fleg']['jumlah_burn'] 				= array_sum($skemaIkeg['json_data']['bobot']['burn']['fleg']);
				$skemaIkeg['json_data']['bobot']['burn']['fbeg']['jumlah_burn'] 				= array_sum($skemaIkeg['json_data']['bobot']['burn']['fbeg']);

				$skemaIkeg['json_data']['bobot']['kanal']['fleg']['kanal'] 					= ((($dataNilaiIkeg['kanal_fleg'] / $dataNilaiIkeg['jumlah_kanal_fleg'])*100) * $kanalFleg);
				$skemaIkeg['json_data']['bobot']['kanal']['fleg']['non_kanal'] 				= ((($dataNilaiIkeg['non_kanal_fleg'] / $dataNilaiIkeg['jumlah_kanal_fleg'])*100) * $nonKanalFleg);
				$skemaIkeg['json_data']['bobot']['kanal']['fbeg']['kanal'] 					= ((($dataNilaiIkeg['kanal_fbeg'] / $dataNilaiIkeg['jumlah_kanal_fbeg'])*100) * $kanalFbeg);
				$skemaIkeg['json_data']['bobot']['kanal']['fbeg']['non_kanal'] 				= ((($dataNilaiIkeg['non_kanal_fbeg'] / $dataNilaiIkeg['jumlah_kanal_fbeg'])*100) * $nonKanalFbeg);
				$skemaIkeg['json_data']['bobot']['kanal']['fleg']['jumlah_kanal'] 			= array_sum($skemaIkeg['json_data']['bobot']['kanal']['fleg']);
				$skemaIkeg['json_data']['bobot']['kanal']['fbeg']['jumlah_kanal'] 			= array_sum($skemaIkeg['json_data']['bobot']['kanal']['fbeg']);

				$skemaIkeg['total_persen']['fleg'] 	= $skemaIkeg['json_data']['bobot']['burn']['fleg']['jumlah_burn'] + $skemaIkeg['json_data']['bobot']['kanal']['fleg']['jumlah_kanal'];
				$skemaIkeg['total_persen']['fbeg'] 	= $skemaIkeg['json_data']['bobot']['burn']['fbeg']['jumlah_burn'] + $skemaIkeg['json_data']['bobot']['kanal']['fbeg']['jumlah_kanal'];
				$skemaIkeg['ideks_ikeg'] 			= ($BobotFleg['bobot_ikeg']*$skemaIkeg['total_persen']['fleg'])+($BobotFbeg['bobot_ikeg']*$skemaIkeg['total_persen']['fbeg']);

				$cekDataUpdate = $this->tables->query("SELECT * FROM indeks_ikeg WHERE deleted= 0 AND uid_provinsi=".$uid_provinsi." AND uid_kabkota = 0 AND tahun=".$tahun);
				$postIkeg['form']['uid_indeks_ikeg'] = $cekDataUpdate['data'][0]['uid_indeks_ikeg'];
				$postIkeg['form']['uid_provinsi'] 	= $uid_provinsi;
				$postIkeg['form']['uid_kabkota'] 	= 0;
				$postIkeg['form']['tahun'] 			= $tahun;
				$postIkeg['form']['json_data'] 		= json_encode($skemaIkeg['json_data']);
				$postIkeg['form']['persen_fleg'] 	= $skemaIkeg['total_persen']['fleg'];
				$postIkeg['form']['persen_fbeg'] 	= $skemaIkeg['total_persen']['fbeg'];
				$postIkeg['form']['nilai_indeks'] 	= $skemaIkeg['ideks_ikeg'];
				$postIkeg['form']['jenis_indeks'] 	= 1;
				$postIkeg['submit']					= TRUE;

				// $this->debug->show($skemaIkeg);

				$this->tables->set("indeks_ikeg", "uid_indeks_ikeg");
				if($this->tables->post($postIkeg)){
					$this->countProvinsi($post['form']['uid_provinsi'], $tahun);
					return "Data Berhasil diupdate";
				}else{
					return 0;
				}

			}
		}

		public function indeks(){
			$post = $this->post();
			if(isset($post['submit'])){
				$update = $this->_count();
				if($update){
					$message = "Data Indeks ". $update." telah diperbaharui";
				}else{
					$message = "Data Indeks gagal diperbaharui";
				}
			}
			if(isset($post['submitProvinsi'])){
				$cekData = $this->tables->query("SELECT uid_indeks_ikeg, uid_provinsi, uid_kabkota, tahun FROM indeks_ikeg WHERE deleted= 0 AND uid_indeks_ikeg=".$post['form']['uid_indeks_ikeg']);
				if($cekData['total']){
					$update = $this->countProvinsi($cekData['data'][0]['uid_provinsi'], $cekData['data'][0]['tahun']);
					if($update){
						$message = "Data Indeks Provinsi telah diperbaharui";
					}else{
						$message = "Data Indeks gagal diperbaharui";
					}
				}else{
					$message = "Data Indeks gagal diperbaharui";
				}
			}

			$this->getDataIndeks();
			$this->view->assign("indeksActive","active");
			$this->view->assign("show",$show);
			$this->view->assign("message",$message);
			$this->view->assign("icons",'<i class="la la-tree"></i>');
			$this->view->assign("title",'INDEKS KUALITAS EKOSISTEM GAMBUT');
			$this->view->display("index.html");
		}

		private function getDataIndeks(){
			$urlVar  	= BASEURL . $this->url . '/';
			$w			= $this->where;
			$o 			= "uid_provinsi ASC";
			$post 		= $this->post();
			if($this->params('search')){
				$post['search'] = TRUE;
				$post['form'] 	= json_decode(urldecode($this->params('search')),1);
			}
			if(isset($post['search'])){
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
				if($post['form']['tahun']){
					$w	.= " AND tahun =".$post['form']['tahun'];
				}
				$urlVar .= 'search/' . urlencode(json_encode($post['form'])) . '/';
				$this->view->assign("search",$post['form']);
			}else{
				$w	.= " AND tahun =".date("Y");
				$post['form']['tahun'] = date("Y");
				$this->view->assign("search",$post['form']);
			}
			//PAGING
			$offset   	= (isset($_REQUEST['page']) && $_REQUEST['page'] > 1 ? $_REQUEST['page'] - 1 : 0);
			$limit	  	= LIMIT;
			$sql 		= 'SELECT a.*, b.nama_provinsi, c.nama_kabkota
							FROM indeks_ikeg a
							LEFT JOIN provinsi b ON b.id = a.uid_provinsi
							LEFT JOIN kabkota c ON c.id = a.uid_kabkota
							WHERE '. $w . ' ORDER BY ' . $o . ' LIMIT ' . $offset . ',' . $limit;
			$data	  	= $this->tables->query($sql);
			$All	  		= $this->db->query('SELECT count(uid_indeks_ikeg) as x FROM indeks_ikeg WHERE '. $w);
			$totalRow 	= (isset($All->fields['x']) ? $All->fields['x'] : 0);
			$this->view->pagination($this->view, $totalRow, $offset+1, $limit, $urlVar);

			$this->view->assign("urlVar", $urlVar);
			$this->view->assign("totalRow", $totalRow);
			$this->view->assign("limit", $limit);
			$this->view->assign("page", $offset);
			$this->view->assign("view",$data['data']);
		}

		private function rfData(){
			$this->tables->set("provinsi","id");
			$rf = $this->tables->fetch();
			$this->view->assign("provinsi",$rf['data']);
			$this->tables->set("lokasi_pemantauan","uid_lokasi_pemantauan");
			$rf = $this->tables->fetch("deleted = 0 AND uid_rf_component = 4");
			$this->view->assign("lokasi",$rf['data']);
			// $this->debug->show($rf);
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
