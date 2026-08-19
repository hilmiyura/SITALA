<?php
	class functions{
		public function getYears(){
			for($i=STARTYEAR;$i<=THISYEAR;$i++){
				$years[] = $i;
			}
			return $years;
		}
		public function getMonths(){
			for($i=1;$i<=12;$i++){
				$m[$i] = $this->getMonth($i);
			}
			return $m;
		}
		public function getTriwulan(){
			$triwulan = array(
							'1' => array(1,3),
							'2' => array(4,6),
							'3' => array(7,9),
							'4' => array(10,12)
							);
			return $triwulan;
		}
		public function monthRomanFormat($index){
			$m = array(
						1 	=> "I",
						2 	=> "II",
						3 	=> "III",
						4 	=> "IV",
						5 	=> "V",
						6 	=> "VI",
						7 	=> "VII",
						8 	=> "VIII",
						9 	=> "IX",
						10 	=> "X",
						11 	=> "XI",
						12 	=> "XII",
						);
			return $m[$index];
		}
		public function getMonth($index){
			$m = array(
						1 	=> "Januari",
						2 	=> "Februari",
						3 	=> "Maret",
						4 	=> "April",
						5 	=> "Mei",
						6 	=> "Juni",
						7 	=> "Juli",
						8 	=> "Agustus",
						9 	=> "September",
						10 	=> "Oktober",
						11 	=> "November",
						12 	=> "Desember",
						);
			return $m[$index];
		}
		public function dateFormat($date=null){ // date format Y-m-d
			if($date){
				list($y,$m,$d) = explode("-", $date);
				//$m = (int) $m;
				//$m = substr($this->getMonth($m),0,3);
				//$m = $this->getMonth($m);
				return $d . "-" . $m . "-" . $y;
			}
		}
		public function openNewTab($url){
			$script = "
						<script  type='text/javascript'>
						window.open(
							 '" . $url . "',
							  '_blank'
							);
						</script>
					  ";
			return $script;
		}
		public function uploadFile($file=null,$dir=null){
			$filename = '';
			if($file['name']){
				$t = time();
				$check_ext = $this->validateExtension($file['name']);
				if($check_ext){
					$filename = $t . "_" . $file['name'];
					if($dir){
						move_uploaded_file($file['tmp_name'], UPLOADFOLDER . $dir."/" . $filename);
					}else{
						move_uploaded_file($file['tmp_name'], UPLOADFOLDER . "docs/" . $filename);
					}
				}
			}
			return $filename;
		}
		public function uploadFiles($files){
			$filename = array();
			foreach($files['name'] as $k=>$v){
				$t = time();
				if($v){
					$check_ext = $this->validateExtension($v);
					if($check_ext){
						$filename[$k] = $t . "_" . $v;
						move_uploaded_file($files['tmp_name'][$k], UPLOADFOLDER . "docs/" . $filename[$k]);
					}
				}
			}
			//echo "<pre>";print_r($filename);die();
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
									".zip",".rar",".doc",".docx",".xls",".xlsx",".ppt", ".pptx", ".pdf",
									".jpg",".gif",".png",".bmp",".jpeg", ".kml", ".kmz"
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
		public function cURLGET($url){
			// Get cURL resource
			$curl = curl_init();
			// Set some options - we are passing in a useragent too here
			curl_setopt_array($curl, array(
			    CURLOPT_RETURNTRANSFER => 1,
			    CURLOPT_URL => $url,
			    CURLOPT_USERAGENT => 'SIMPEL cURL Request'
			));
			// Send the request & save response to $resp
			$resp = curl_exec($curl);
			if(!$resp){
    			die('Error: "' . curl_error($curl) . '" - Code: ' . curl_errno($curl));
			}
			// Close request to clear up some resources
			curl_close($curl);
			return $resp;
		}
		public function sendMailSMTPGMAIL($from, $pass, $fromName, $to, $subject, $body){
			require_once('phpmailer/PHPMailerAutoload.php');

			$mail 				= new PHPMailer();
			$mail->IsSMTP();
			$mail->CharSet		= "UTF-8";
			$mail->SMTPSecure 	= 'ssl';
			$mail->Host 		= 'smtp.gmail.com';
			$mail->Port 		= 465;
			$mail->Username 	= $from;
			$mail->Password 	= $pass;
			$mail->SMTPAuth 	= true;

			$mail->From 		= $from;
			$mail->FromName 	= $fromName;
			$mail->AddAddress($to);
			//$mail->addCC('ade.ofik@gmail.com, muhamadhaikal@gmail.com');

			$mail->IsHTML(true);
			$mail->Subject	= $subject;
			$mail->Body		= $body;

			if(!$mail->Send()){
			  return FALSE;
			}else{
			  return TRUE;
			}
		}
		public function rating($rating,$nilai){
			foreach ($rating as $key => $value) {
				if($nilai >= $value['min']  && $nilai <= $value['max'] ){
					return array("rating"=>$value['rating'],"color"=>$value['color']);
				}
			}
		}
		public function roleUser($role){
			$roleUser = array(
				array('color' => 'dark','name'=>'SUPER USER'),
				array('color' => 'danger','name'=>'PUSAT'),
				array('color' => 'info','name'=>'PROVINSI'),
				array('color' => 'success','name'=>'KABUPATEN/KOTA'),
				array('color' => 'warning','name'=>'REGIONAL'),
				array('color' => 'warning','name'=>'PSLH/PPLH'),
			);
			return $roleUser[$role];
		}
	}
?>
