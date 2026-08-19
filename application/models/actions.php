<?php
	class actions extends Database{
		public function __construct(){
			$this->init();
			$this->table 	= "config_action";
			$this->primary 	= "config_action_uid";
		}
		public function getAction($memberCategory){
			$data = $this->fetch('kategori_user_uid = ' . $memberCategory);
			return json_decode($data['data'][0]['actions'], TRUE);
		}
	}
?>