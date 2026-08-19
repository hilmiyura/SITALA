<?php
	/**
	 * created at 	: 29/09/2020
	 * created by 	: dasendria team
	 * desc		  	: controller login IKLHK
	 *
	 */
    class loginController extends Front{
    		public function init() {
          // die('tes');
	    		//SET CUSTOM VIEWS FOLDER
	    		$this->view->setFolder('be');
          // die('under maintenance');

	    		//LOAD MODELS
	    		$this->loadModel("tables");
	    		$this->loadModel("ref");
          $this->loadModel("users");
			    // $this->loadModel("external");

			//GLOBAL VAR
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

      $this -> view -> assign("assets", ASSETS);
		}
		//INDEX FUNCTION IS A DEFAULT ACTION
		public function index(){
			$post = $this->post();
			if(isset($post["submit"])){


          $captchaVerify = $this->captchaVerify($post['captcha']);
          if($captchaVerify['code'] != 200){
            $this->view->assign("captcha", $this->captchaGenerate()['img']);
            $this->view->assign("message", "captcha tidak valid");$this->view->display("login-nw.html");
            exit();
          }

        $post['username'] = strip_tags($post['username']);
        $post['username'] = str_replace("=","",$post['username']);
        $post['username'] = $this->strescape($post['username']);

        $post['password'] = strip_tags($post['password']);
        $post['password'] = str_replace("=","",$post['password']);

				$post['password'] = base64_encode($this->users->EncDec('encode', $post['password']));

				$this->tables->set("users","uid_users");
				$member = $this->tables->fetch("deleted=0 AND hidden=0 AND active =1 AND username ='".$post['username']."' AND password ='".$post['password']."'");

				if($member['total']){
          if($member['data'][0]['username'] != $post['username']){
            $this->redirect('login');
            exit();
          }
          // ini_set("display_errors",TRUE);

          $member['data'][0]['ip'] = $_SERVER['REMOTE_ADDR'];
					$this->users->updateLastAccess($member['data'][0]['uid_users']);
          $member['data'][0]['decodeid'] = $this->users->EncDec('encode', $member['data'][0]['uid_users']);
					$member['data'][0]['last'] = "last-login : ".date("d/m/y h:i:s");
					$this->session->set("memberIKLH",$member['data'][0]);
					$this->redirect('dashboard');
					exit();
				}else{
          $this->view->assign("message", "akun tidak dikenal");
				}
			}

      $this->view->assign("captcha", $this->captchaGenerate()['img']);
      if($this->params("x")){
        $this->view->display("login-nw-2025.html");
      }else{
        $this->view->display("login-nw.html");
      }

		}

    public function recaptcha(){if(parse_url($_SERVER["HTTP_REFERER"], PHP_URL_HOST) == APP_HOST && $_SESSION['captcha_token']){echo $this->captchaGenerate()['img'];}}

    private function strescape($string){
      $string = strip_tags($string);
      $string = str_replace('=', 'eq',$string);
      return preg_replace('~[\x00\x0A\x0D\x1A\x22\x25\x27\x5C]~u', '\\\$0', $string);
    }

    public function indeksRespon(){
      $memberCheck = $this->session->get('memberIKLH');
      // echo json_encode(array("data"=>$memberCheck));
      die("hi");
      $this->session->set("memberIKLH",$memberCheck);
      $this->debug->show($_SESSION);

    }

		public function logout(){
      // $this->me 			= $this->session->get('member');
			// $this->me = $this->session->get('member');
			// $this->session->destroy();
      $this->session->set('memberIKLH',NULL);
      $this->redirect('login');
		}


    private function captchaGenerate(){
			$captcha = $this->api("https://captcha.just4dev.id/generate?sumary=1");
			$_SESSION['captcha_token'] = $captcha['token'];
			return $captcha;
			exit();
		}
		private function captchaVerify($number){
			$dataVerify = json_encode(array("token"=>$_SESSION["captcha_token"],"verify"=>$number));
			$captcha = $this->api("https://captcha.just4dev.id/verify", $dataVerify);
			$_SESSION["captcha_token"] = null;
			return $captcha;
			exit();
		}

    private function api($url, $post = NULL){
      $headers = array("Content-Type: application/json");
      $curlSession = curl_init();
      curl_setopt($curlSession, CURLOPT_URL, $url);
      curl_setopt($curlSession, CURLOPT_BINARYTRANSFER, true);
      curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curlSession, CURLOPT_HTTPHEADER, $headers);
      if ($post) {
          curl_setopt($curlSession, CURLOPT_POSTFIELDS, $post);
      }
      $jsonData = curl_exec($curlSession);
      curl_close($curlSession);
      $json = json_decode($jsonData, true);
      return $json;
    }

    public function maintenance(){
      $this->view->display("development.html");
      die();
    }

    // public function checkLogin(){
    //   echo $_SERVER['REMOTE_ADDR'];
    // }

 	}
?>
