<?php
	require_once "db/Adodb.php";
	
	class Database {
		public $table;
		public $primary;
		private $_db;
		
		public function init(){
			$this->_db = new Adodb();
		}
		public function fetch($where="",$order="",$limit=array()){			
			if($this->table){
				if($where){
					$where = " WHERE " . $where;
				}
				if($order){
					$order = " ORDER BY " . $order;
				}
				if(is_array($limit) && count($limit) && array_key_exists("offset",$limit) && array_key_exists("limit",$limit)){				
					$limit = " LIMIT " . $limit['offset'] . "," . $limit['limit'];
				}else{
					$limit = "";
				}
				$query = "SELECT * FROM " . $this->table . $where . $order . $limit;
				$_data = $this->_db->fetch($query);
	            return $_data;	
			}else{
				die("Table not found");
			}
			            
        }
		public function query($query){
			$_data = $this->_db->fetch($query);
	        return $_data;	
		}	
        public function insert($data){
        	if($this->table){
	        	$result = $this->_db->insert($this->table,$data);
				return $result;	
			}else{
				die("Table not found");
			}            
        }
        public function update($data, $uid){
        	if($this->table && $this->primary){        		        		
	        	$result = $this->_db->update($this->table, $data, $this->primary . "=" . $uid);				
	            return $result;	
			}else{
				die("Table & primary field not found");
			}            
        }
		public function updateBy($data, $where){			
			if($this->table && $this->primary){        		        		
	        	$result = $this->_db->update($this->table, $data, $where);				
	            return $result;	
			}else{
				die("Table & primary field not found");
			}
        }
        public function delete($uid){
        	if($this->table && $this->primary){
	        	$result = $this->_db->delete($this->table, $this->primary . "=" . $uid);
	           	return $result;	
			}else{
				die("Table & primary field not found");
			}        	
        }
		public function deleteBy($where){        	
        	if($this->table && $this->primary){
	        	$result = $this->_db->delete($this->table, $where);
	           	return $result;	
			}else{
				die("Table & primary field not found");
			}
        }
		public function lastInsertID(){
			return $this->_db->lastInsertID();
		}
	}
?>