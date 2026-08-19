
<?php
	class external extends Database{
		public function __construct(){
			// $this->init();
			// $this->table 	= "config_action";
			// $this->primary 	= "config_action_uid";
		}

		public function captchaGenerate(){
			$captcha = $this->api("https://captcha.just4dev.id/generate?sumary=1");
			$_SESSION['captcha_token'] = $captcha['token'];
			return $captcha;
		}
		public function captchaVerify($number){
			$dataVerify = json_encode(array("token"=>$_SESSION["captcha_token"],"verify"=>$number));
			$captcha = $this->api("https://captcha.just4dev.id/verify", $dataVerify);
			$_SESSION["captcha_token"] = null;
			return $captcha;
		}

    public function api($url, $post = NULL){

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
	}
?>
