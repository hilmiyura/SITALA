<?php
	class Session{
		public function __construct(){
			$this->_checkSessionStart();
		}
		public function set($name, $value){
			$_SESSION[$name] = $value;
		}
		public function get($name){

			if(isset($_SESSION[$name])){

				// if($_SESSION[$name]['uid_users'] != 681){
				// 	$this->destroy();
				// 	die(header("Location: login/maintenance"));
				// }

				$this->sessionReset($_SESSION[$name]);

				return $_SESSION[$name];
			}
			return 0;
		}
		public function checkSession($name){

			if(isset($_SESSION[$name])){
				// if($_SESSION[$name]['uid_users'] != 681){
				// 	$this->destroy();
				// 	die(header("Location: login/maintenance"));
				// }
				// $this->sessionReset($_SESSION[$name]);

				return true;
			}else{
				return false;
			}
		}
		public function destroy(){
			unset($_SESSION);
			session_destroy();
		}
		private function _checkSessionStart(){
			if(!isset($_SESSION)) {
				session_start();
			}
		}

		private function sessionReset($session){
			// if(strpos($_SERVER[REQUEST_URI],"users/profile")){
			// 	echo "1";
			// }else{
			// 	echo "2";
			// }
			$date30 = date("Y-m-d H:i:s", strtotime("+1 month", strtotime($session["password_history_time"])));
			if($date30 < date("Y-m-d H:i:s") && !strpos($_SERVER[REQUEST_URI],"users/profile")){
				// die($_SERVER[REQUEST_URI]);
				echo "<script>window.location.href='".APP_IKLH."users/profile/msg/1'</script>";
				die();
			}
		}

	}
?>
