<?php
	require_once "db/adodb5/adodb.inc.php";
		
	class Adodb {
		private $_db;
				
		public function __construct(){
			$this->_db = ADONewConnection(DBDRIVER);
            $this->_db->Connect(DBSERVER, DBUSER, DBPASS, DBNAME);			
		}
        public function fetch($query){      	
            $this->_db->SetFetchMode(ADODB_FETCH_ASSOC);
            $recordSet = $this->_db->Execute($query);
			$_data = array();
			if($recordSet){
				$c_field = $recordSet->FieldCount();
	            $_data = array();            
	            if ($recordSet){                                            
	                $j = 0;
	                while (!$recordSet->EOF) {
	                    for($i=0;$i<$c_field;$i++){
	                         $fld = $recordSet->FetchField($i);                         
	                         $_data[$j][$fld->name] = $recordSet->fields[$fld->name];
	                    }                                                           
	                    $recordSet->MoveNext();
	                    $j++;                
	                }
	            }
				$recordSet->Close();
			}
			$_data = array("data"=>$_data, "total"=>count($_data));
            return $_data;            
        }
        public function query($query){
            $this->_db->SetFetchMode(ADODB_FETCH_ASSOC); 
            $recordSet = $this->_db->Execute($query);
            return $recordSet;
        }
        public function insert($table,$data){
            $result = $this->_db->AutoExecute($table, $data, 'INSERT');
            if(!$result) die("Insert $table " . $this->_db->ErrorMsg());
            return $result;
        }
        public function update($table, $data, $where){        	
           	$result = $this->_db->AutoExecute($table, $data, 'UPDATE',$where);
            if(!$result) die("Update $table " . $this->_db->ErrorMsg());
            return $result;
        }
        public function delete($table, $where){        	
        	$query = "DELETE FROM " . $table . " WHERE " . $where; 
            $result = $this->_db->Execute($query);
            if(!$result) die("Delete " . $this->_db->ErrorMsg());
            return $result;
        }
		public function lastInsertID(){
			return $this->_db->Insert_ID();
		}
	}
?>