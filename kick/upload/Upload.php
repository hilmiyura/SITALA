<?php
	class Upload{
		public function __construct(){

		}
		public function uploadFile($file, $folder, $ext=''){
			$filename = '';
			if($file['name']){
				$t = time();
				$check_ext = $this->validateExtension($file['name'], $ext);
				if($check_ext){
					$filename = $t . "_" . $file['name'];
					move_uploaded_file($file['tmp_name'], UPLOADFOLDER . $folder . "/" . $filename);
				}
			}
			// echo "<pre>";print_r($filename);die();
			return $filename;
		}
		public function uploadFiles($files, $folder, $ext=''){
			$filename = array();
			foreach($files['name'] as $k=>$v){
				$t = time();
				if($v){
					$check_ext = $this->validateExtension($v, $ext);
					if($check_ext){
						$filename[$k] = $t . "_" . $v;
						move_uploaded_file($files['tmp_name'][$k], UPLOADFOLDER . $folder . "/" . $filename[$k]);
					}
				}
			}
			if(count($filename)){
				$filename = array_values($filename);
			}
			return $filename;
		}
		public function validateExtension($file_name,$format="") {
			if($format=="document"){
				$ext_array = array(".zip",".rar",".doc",".docx",".xls",".xlsx",".ppt", ".pptx", ".pdf");
			}elseif($format=="image"){
				$ext_array = array(".jpg",".gif",".png",".bmp",".jpeg");
			}elseif($format=="json"){
				$ext_array = array(".json");
			}else{
				$ext_array = array(
									".kml",".kmz",".shp",".zip",".rar",".doc",".docx",".xls",".xlsx",".ppt", ".pptx", ".pdf",
									".jpg",".gif",".png",".bmp",".jpeg"
									);
			}
		    $extension = strtolower(strrchr($file_name,"."));
		    $ext_count = count($ext_array);
		    if (!$file_name) {
		        return FALSE;
		    }else{
		        if (!$ext_array) {
		            return TRUE;
		        } else {
		            foreach ($ext_array as $value) {
		                $first_char = substr($value,0,1);
		                    if ($first_char <> ".") {
		                        $extensions[] = ".".strtolower($value);
		                    } else {
		                        $extensions[] = strtolower($value);
		                    }
		            }
		            $valid_extension = FALSE;
		           	foreach ($extensions as $value) {
		                if ($value == $extension) {
		                    $valid_extension = TRUE;
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
	}
?>
