<?php
	/*
	 * THIS CLASS FOR ACCESS ALL MAIN TABLE, WHAT YOU NEED
	 * POST DATA, HARD DELETE, SOFT DELETE
	 */
	class tables extends Database{
		public function __construct(){
			$this->init();
			$this->table 	= "";
			$this->primary 	= "";
			$this->lastInsertID = "";
		}
		public function set($table, $primary){
			$this->table 	= $table;
			$this->primary 	= $primary;
		}
		public function post($post){
			$result = FALSE;
			if(isset($post['submit'])){
				if(isset($post['form'][$this->primary]) && $post['form'][$this->primary]){ //update row
					//required chdate colom in your table
					$post['form']['chdate'] = time();
					if($this->update($post['form'],$post['form'][$this->primary])){
						$result = TRUE;
					}
				}else{ //insert new row
					//required crdate colom in your table
					$post['form']['crdate'] = time();
					if($this->insert($post['form'])){
						$result = TRUE;
						$this->lastInsertID = $this->lastInsertID();
					}
				}
			}
			return $result;
		}

		public function hardDelete($uid){
			$result = FALSE;
			if($uid && is_numeric($uid)){
				if($this->delete($uid)){
					$result = TRUE;
				}
			}
			return $result;
		}
		public function softDelete($uid, $uid_users =  null){
			$result = FALSE;
			if($uid && is_numeric($uid)){
				$post['form']['chdate'] = time();
				$post['form']['deleted'] = 1;
				if($uid_users){
					$post['form']['chuser'] = $uid_users;
				}
				if($this->update($post['form'],$uid)){
					$result = TRUE;
				}
			}
			return $result;
		}
		public function softDeleteBy($where){
			$result = FALSE;
			if($where){
				$post['form']['chdate'] = time();
				$post['form']['deleted'] = 1;
				if($this->updateBy($post['form'],$where)){
					$result = TRUE;
				}
			}
			return $result;
		}
	}
?>
