<?php
	/**
	 * created at 	: 29/09/2020
	 * created by 	: dasendria team
	 * desc		  	: controller Users IKLHK
	 *
	 */
  class usersController extends Front{
    public function init() {
      // die("maintenance");
  		($this->session->get('memberIKLH')? :$this->redirect("login"));

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

      //load function
      require_once "functions.php";
			$this->functions = new functions();
      $this->view->assign("functions",$this->functions);

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

			$this->view->assign("primaryKey", "uid_users");
			$this->viewName 	= "v_users";
			$this->primaryKey	= "uid_users";
			$this->where		= "deleted = 0";
			// $this->debug->show();

      $this->dev = 0;
      if($_SERVER['REMOTE_ADDR'] == '180.252.85.156'){
          $this->dev = 1;
      }
      $this->view->assign("dev", $this->dev);

      $this->view->assign("aksesAdmin", $this->ref->aksesAdmin($this->me));

		}
		//INDEX FUNCTION IS A DEFAULT ACTION
		public function index(){
      	if($this->me['role_user'] != 0 && $this->me['role_user'] != 1){
				$this->redirect('users/profile');
			}

			$post = $this->post();
			if(isset($post['submit'])){
				if($post['form']['password']){
					$post['form']['password'] = base64_encode($this->users->EncDec('encode', $post['form']['password']));
				}else{
					unset($post['form']['password']);
				}
				if($post['form']['role_user'] == 2){
					$post['form']['uid_kabkota'] = 0;
				}
        if($post['form']['role_user'] == 5){
          $post['form']['uid_provinsi_lainnya'] = implode(",",$post['form']['uid_provinsi_lainnya']);
        }else{
          $post['form']['uid_provinsi_lainnya'] = NULL;
        }

				$this->tables->set("users","uid_users");
				if($this->tables->post($post)){
					$message = "Berhasil menyimpan data !";
				}else{
					$message = "Gagal menimpan data !";
				}
			}
			$this->getData();
			$this->rfData();
			$this->view->assign("masterActive","active");
			$this->view->assign("show",$show);
			$this->view->assign("message",$message);
			$this->view->assign("icons",'<i class="la la-user"></i>');
			$this->view->assign("title",'Daftar Pengguna');
			$this->view->display("index.html");
		}

		private function getData(){
			$this->tables->set($this->viewName,$this->primaryKey);
			$properties	= $this->_getProperties($this->viewName);
			$urlVar  	= BASEURL . $this->url . '/';
			$w 			= $this->where;
			$o 			= $this->primaryKey . " ASC";
      if($this->me['role_user'] == 1){
        $w .= " AND role_user > 1";
      }
			$post 		= $this->post();
			if($this->params('search')){
				$post['search'] = TRUE;
				$post['form'] 	= json_decode(urldecode($this->params('search')),1);
			}
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
        if($post["form"]['role_user']){
					$w .= " AND role_user = ".$post["form"]['role_user'];
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
				$this->tables->set("users","uid_users");
				$dataEdit = $this->tables->fetch("deleted = 0 AND uid_users=".$this->params("x"));
        if($dataEdit['data'][0]['password']){
          $dataEdit['data'][0]['password'] = $this->users->EncDec('decode', base64_decode($dataEdit['data'][0]['password'],TRUE));
        }
				echo json_encode($dataEdit['data'][0]);
			}
		}

		public function deletedData(){
			$post = $this->post();
			if(isset($post['x'])){
				$this->tables->set("users","uid_users");
				if($this->tables->softDelete($post['x'])){
					echo json_encode(array('statusCode' => 200, 'message' => $this->message->delete('success')));
				}else{
					echo json_encode(array('statusCode' => 400, 'message' => $this->message->delete('failed')));
				}
			}else{
				echo json_encode(array('statusCode' => 403, 'message' => $this->message->access()));
			}
		}

		private function rfData(){
			$this->tables->set("rf_provinsi","kd_propinsi");
			$rf = $this->tables->fetch();
			$this->view->assign("provinsi",$rf['data']);
      $this->tables->set("rf_regional","kd_regional");
			$rf = $this->tables->fetch();
			$this->view->assign("regional",$rf['data']);
		}

    public function profile(){
      if($this->params("msg")){
        $message = "Silahkan lakukan update password berkala per 30 hari!";
      }

      $post = $this->post();
			if(isset($post['submit'])){
        // $this->debug->show("masuk");
        $file = $_FILES['sk_pelaksana'];
        if ($file['name']) {
            $fileUpload = $this -> functions -> uploadFile($_FILES['sk_pelaksana'], "sk_pelaksana");
            $post['form']['sk_pelaksana'] = $fileUpload;
        }

				if($post['form']['password'] && $post['form']['password_lama']){

          $uppercaseP = preg_match('@[A-Z]@', $post['form']['password']);
          $lowercaseP = preg_match('@[a-z]@', $post['form']['password']);
          $numberP    = preg_match('@[0-9]@', $post['form']['password']);

          if($uppercaseP && $lowercaseP && $numberP && strlen($post['form']['password']) >= 8){
            if($post['form']['password'] != $post['form']['password_lama']){
              if($this->me["password"] == base64_encode($this->users->EncDec('encode', $post["form"]["password_lama"]))){
                $post['form']['uid_users'] = $this->me['uid_users'];
                $post['form']['password'] = base64_encode($this->users->EncDec('encode', $post['form']['password']));
                if($post['form']['password']){
                  $post['form']['password_history'] = $this->me["password"];
                  $post['form']['password_history_time'] = date("Y-m-d H:i:s");
                }

                $this->tables->set("users","uid_users");
                if($this->tables->post($post)){
                  $message = "Berhasil menyimpan data !";

                  $this->session->destroy();
                  $this->redirect('login');
                }else{
                  $message = "Gagal menimpan data !";
                }
              }else{
                $message = "Gagal menimpan data, password lama tidak cocok !";
              }
            }else{
              $message = "Gagal menimpan data, password baru tidak bolleh sama dengan password lama !";
            }
          }else{
            $message = "Gagal menyimpan data, password harus lebih dari 7 karakter, mengandung huruf BESAR, huruf kecil dan angka !";
          }
				}else{
          $message = "Gagal menyimpan data, password lama tidak cocok !";
        }
			}
      $dataUser = $this->tables->query("SELECT * FROM v_users WHERE uid_users=".$this->me['uid_users']);
      // if($dataUser['data'][0]['password']){
      //   $dataUser['data'][0]['password'] = $this->users->EncDec('decode', base64_decode($dataUser['data'][0]['password'],TRUE));
      // }
      // $this->debug->show($this->me);
      $this->view->assign("form",$dataUser['data'][0]);

      // $this->debug->show($this->me);
      if($this->me['role_user'] == 2 || $this->me['role_user'] == 3){
        $sqlCheck = $this->db->fetch("SELECT * FROM users_detail_periode WHERE uid_provinsi=".$this->me['uid_provinsi']." AND uid_kabkota=".$this->me['uid_kabkota']);
        $tahun = array_column($sqlCheck['data'], 'periode');
        $dataDetailPeriode = array_combine($tahun, $sqlCheck['data']);
        $this->view->assign("dataDetailPeriode",$dataDetailPeriode);
      }

      $this->view->assign("location", $this->me['city'].",".$this->me['region'].",".$this->me['country']);
			$this->view->assign("message",$message);
			$this->view->assign("masterActive","active");
			$this->view->assign("icons",'<i class="la la-user"></i>');
			$this->view->assign("title",'Profile');
			$this->view->display("index.html");
    }

    public function postLampiran(){
      if($_FILES['file']['name']){
        $fileUpload = $this -> functions -> uploadFile($_FILES['file'], "sk_pelaksana" ,"document");
        if($fileUpload){
          echo json_encode(array("statusCode"=>200,"message"=>"Berhasil", "filename"=>$fileUpload));
        }else{
          echo json_encode(array("statusCode"=>400,"message"=>"gagal upload, pastikan file berformat .doc|.docx|.pdf|.xls|.xlsx"));
        }
      }else{
        echo json_encode(array("statusCode"=>400,"message"=>"tidak ada lampiran yang dikirim"));
      }
    }
    public function setValueDetail(){
      $post = $this->post();
      $sqlCheck = $this->db->fetch("SELECT * FROM users_detail_periode WHERE uid_provinsi=".$this->me['uid_provinsi']." AND uid_kabkota=".$this->me['uid_kabkota']." AND periode=".$post['tahun']);
      if($post['tahun'] > 0 && $post['field'] ){
        if($sqlCheck['data'][0]['uid_users_detail_periode']){
          $update['form']['uid_users_detail_periode'] = $sqlCheck['data'][0]['uid_users_detail_periode'];
        }else{
          $update['form']['periode'] = $post['tahun'];
          $update['form']['uid_provinsi'] = $this->me['uid_provinsi'];
          $update['form']['uid_kabkota'] = $this->me['uid_kabkota'];
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

    public function syncProfile(){
      die("hello");
      $dataOld = $this->db->fetch("SELECT *  FROM users WHERE deleted = 0 AND role_user IN(2,3)");
      foreach ($dataOld['data'] as $key => $value) {
        $form['chuser'] = $value['uid_users'];
        $form['uid_provinsi'] = $value['uid_provinsi'];
        $form['uid_kabkota'] = $value['uid_kabkota'];
        $form['kepala_daerah'] = $value['kepala_daerah'];
        $form['kepala_dprd'] = $value['kepala_dprd'];
        // $form['luas_wilayah'] = $value['luas_wilayah'];
        $form['luas_wilayah'] = NULL;
        // $form['populasi'] = $value['populasi'];
        $form['populasi'] = NULL;
        // $form['gdp'] = $value['gdp'];
        $form['gdp'] = NULL;
        $form['kategori_daerah'] = $value['kategori_daerah'];
        $form['sk_pelaksana'] = $value['sk_pelaksana'];
        $form['periode'] = 2024;

        $sqlCheck = $this->db->fetch("SELECT * FROM users_detail_periode WHERE uid_provinsi=".$form['uid_provinsi']." AND uid_kabkota=".$form['uid_kabkota']." AND periode=".$form['periode']);
        if($sqlCheck['data'][0]['uid_users_detail_periode']){
          $form['uid_users_detail_periode'] = $sqlCheck['data'][0]['uid_users_detail_periode'];
        }
        $update['form'] = $form;
        $update['submit'] = TRUE;
        $this->tables->set("users_detail_periode","uid_users_detail_periode");
        $this->tables->post($update);
      }
      $this->debug->show($dataOld);
    }

    public function loginAs(){
      if($this->me){
        $id = base64_decode($this->params("x"));
        if($id){
          $this->tables->set("users","uid_users");
          $data = $this->tables->fetch("uid_users=".$id);
          $tmpUser = $this->me;
          unset($tmpUser['login_from']);
          if($data['total']){
            $memberIKLH['data'] = $data['data'][0];
            $memberIKLH['data']['login_from'] = $tmpUser;
            $memberIKLH['data']['decodeid'] = $this->users->EncDec('encode', $id);

            $this->session->set("memberIKLH",$memberIKLH['data']);
            $this->redirect('dashboard');
          }else{
            $this->redirect('users');
          }
        }else{
          $this->redirect('users');
        }
      }else{
        $this->redirect();
      }
    }

    public function indeksRespon(){
      if($this->me){
        echo "<script>window.location.href='".APP_IRLH."login/fromIklh/x/{$this->me['decodeid']}'</script>";
        die();
      }
    }

    public function backTo(){
      $tmpUser = $this->me;
      if($tmpUser['login_from']['uid_users']){
        $this->session->set("memberIKLH",$tmpUser['login_from']);
      }
      $this->redirect('dashboard');
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
