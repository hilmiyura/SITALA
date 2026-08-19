<?php
	class Uri{
		private $_uri;
		private $_host;
		private $_url;		
		private $_segment;
		
		public function __construct() {
			$this->_uri 	= $_SERVER['REQUEST_URI'];
			$this->_host	= $_SERVER['HTTP_HOST'];			
			$this->_url		= $this->_host . $this->_uri;
			$this->_segment	= $this->_splitUrl();
		}
		private function _splitUrl(){
			$segment = explode("/", $this->_uri);
			$segment[0] = $this->_host;
			
			//split segment
			if(BASEURL == "/"){
				$result = $this->_baseUrlIsRoot($segment);	
			}else{
				$result = $this->_baseUrlNotIsRoot($segment);
			}
			return $result; 
		}
		private function _baseUrlIsRoot($segment){
			$result = array();
			foreach ($segment as $key => $value) {
				if($value){
					if($key==0){
						$result['host'] 		= $value;
					}elseif($key==1){
						$result['controller'] 	= $value;
					}elseif($key==2){
						$result['action'] 		= $value;
					}else{ // params					
						for($i=$key;$i<$key+1;$i++){						
							if($i % 2 != 0){
								if(isset($segment[$i])){
									if(isset($segment[$i+1])){
										$result['params'][$segment[$i]] = $segment[$i+1];	
									}else{
										$result['params'][$segment[$i]] = '';
									}
										
								}							 	
							}
							 
						}
					}	
				}				
			}
			return $result;
		}
		private function _baseUrlNotIsRoot($segment){
			$result = array();
			foreach ($segment as $key => $value) {
				if($value){
					if($key==0){
						$result['host'] 		= $value;
					}elseif($key==1){
						$result['baseurl'] 		= $value;
					}elseif($key==2){
						$result['controller'] 	= $value;
					}elseif($key==3){
						$result['action'] 		= $value;
					}else{ // params					
						for($i=$key;$i<$key+1;$i++){														
							if($i % 2 == 0){
								if(isset($segment[$i])){
									if(isset($segment[$i+1])){							
										$result['params'][$segment[$i]] = $segment[$i+1];
									}else{
										$result['params'][$segment[$i]] = '';
									}	
								}							 	
							}
							 
						}
					}	
				}				
			}
			return $result;
		}
		public function getUriSegment(){
			return $this->_segment;
		}
		public function getHost(){
			return $this->_host;
		}
		public function getController(){			
			if(isset($this->_segment['controller'])){
				return $this->_segment['controller'];
			}else{
				if (defined('DEFAULTCONTROLLER')) {
					return DEFAULTCONTROLLER;
				}else{
					return "index";
				}				
			}
		}
		public function getAction(){			
			if(isset($this->_segment['action'])){
				return $this->_segment['action'];
			}else{
				return "index";
			}
		}
		public function getParams(){
			if(isset($this->_segment['params'])){
				return $this->_segment['params'];
			}else{
				return array();
			}
		}
	}
?>