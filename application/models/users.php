<?php
	class users extends Database{
		public function __construct(){
			$this->init();
			$this->table 	= "users";
			$this->primary 	= "uid_users";
		}
		public function getUsersByName($name){
			$data = $this->fetch('name LIKE "%' . $name . '%"');
			return $data;
		}
		public function getUsersByUsername($name){
			$data = $this->fetch('username = "' . $name . '"');
			return $data;
		}
		public function getMemberByUid($uid){
			$data = $this->fetch($this->primary . "=" . $uid);
			return $data;
		}
		public function getMemberLogin($username, $password){
			$data = $this->fetch("username='" . $username . "' AND password='" . $password . "'");
			return $data;
		}
		// public function getMemberEmail($email, $password){
			// $data = $this->fetch("email='" . $email . "' AND password='" . $password . "'");
			// return $data;
		// }
		public function updateIsOnline($member_uid, $isOnline=0){
			$this->table 	= "t_users";
			$this->primary  = 'user_uid';
			$update = array('isonline'=>$isOnline);
			$result = $this->update($update, $member_uid);
			//print_r($update);die();
			return $result;
		}
		public function updateLastAccess($uid){
			$update = array('last_access'=>date("Y-m-d H:i:s"));
			$this->update($update, $uid);
		}
		public function validationEmail($email, $uid=0){
			if($uid){
				$data = $this->fetch($this->primary . "<>" . $uid . " AND email='" . $email . "'");
			}else{
				$data = $this->fetch("email='" . $email . "'");
			}
			if($data['total']){
				return false;
			}else{
				return true;
			}
		}
		public function EncDec($action, $string) {
        $output = false;
        $encrypt_method = 'AES-256-CBC';
        $secret_key = 'ini kunci rahasia saya :D';
        $secret_iv = 'ini iv rahasia saya :D';
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ($action == 'encode') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else if ($action == 'decode') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }
		public function EncDecFile($action, $string) {
        $output = false;
        $encrypt_method = 'AES-256-CBC';
        $secret_key = 'ini_File*!IklH-:D';
        $secret_iv = 'ini iv rahasia saya :D';
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ($action == 'encode') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else if ($action == 'decode') {
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        }
        return $output;
    }
	}
?>
