<?php
	class Filter{
		public function __construct() {
		}		
		public function email($email){
			if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
				return true;
			}else{
				return false;
			}
		}
		public function url($url){
			if(filter_var($url, FILTER_VALIDATE_URL)) {
				return true;
			}else{
				return false;
			}
		}
		public function ipAddress($ip){
			if(filter_var($ip, FILTER_VALIDATE_IP)) {
				return true;
			}else{
				return false;
			}
		}
		public function fileExtension($file_name,$format="document") {
			if($format=="document"){
				$ext_array = array(".zip",".rar",".doc",".docx",".xls",".xlsx","ppt", "pptx", ".pdf");	
			}elseif($format=="image"){
				$ext_array = array(".jpg",".gif",".png",".bmp",".jpeg");
			}else{
				$ext_array = array(".zip",".rar",".doc",".docx",".xls",".xlsx","ppt", "pptx", ".pdf",
									".jpg",".gif",".png",".bmp",".jpeg"
									);
			}
		    
		    $extension = strtolower(strrchr($file_name,"."));
		    $ext_count = count($ext_array);
		    if (!$file_name) {
		        return false;
		    }else{
		        if (!$ext_array) {
		            return true;
		        } else {
		            foreach ($ext_array as $value) {
		                $first_char = substr($value,0,1);
		                    if ($first_char <> ".") {
		                        $extensions[] = ".".strtolower($value);
		                    } else {
		                        $extensions[] = strtolower($value);
		                    }
		            }				
		            foreach ($extensions as $value) {
		                if ($value == $extension) {
		                    $valid_extension = "TRUE";
		                }
		            }
		            if ($valid_extension) {
		                return true;
		            } else {
		                return false;
		            }
		        }
		    }
		}
		public function _realURL($title){
			$title = strtolower($title);	
			$title = preg_replace('~[^\\pL0-9_]+~u', '-', $title);
			$title = trim($title, "-");
			//$title = iconv("utf-8", "us-ascii//TRANSLIT", $title);
			$title = preg_replace('~[^-a-z0-9_]+~', '', $title);
			return $title;
		}
	}
?>