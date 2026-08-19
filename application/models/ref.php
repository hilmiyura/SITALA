<?php
	class ref extends Database{
		public function __construct(){
			$this->init();
			$this->table 	= "";
			$this->primary 	= "";
		}
		public function setTable($table, $pkey){
			$this->table 	= $table;
			$this->primary 	= $pkey;
		}

		public function getRekomendasiCatatan($aspek,$menu){
			$this->table="rf_catatan";
			$data = $this->fetch('kategori = "' . $aspek.'" AND menu = "'.$menu.'"');
			return $data;
		}

		public function aksesAdmin($me, $komponen = [] ){
			$allowId = [1,2,681,693];
			if(in_array($me['uid_users'],$allowId) || (isset($me['komponen']) && in_array($me['komponen'], $komponen))){
				return 1;
			}else{
				return 0;
			}
		}

	}
?>
