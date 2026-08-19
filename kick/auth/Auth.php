<?php
	class Auth{
		protected $session;
		public function __construct() {
			$this->session = new Session();
		}
		public function isValid($name, $to=""){
			if(!$this->session->checkSession($name)){
				header('location:' . BASEURL . $to);
            }
			return true;
		}
	}
?>
