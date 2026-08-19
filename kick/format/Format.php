<?php
	class Format{
		public function __construct() {
		}
		public function leadingZeros($num,$numDigits) {
   			return sprintf("%0".$numDigits."d",$num);
		}
		public function dateFormat($date){ // date format Y-m-d			
			if($date){
				list($y,$m,$d) = explode("-", $date);
				$m = (int) $m;
				$m = $this->_getMonth($m);	
			}else{
				$d = date("d");
				$m = date("m");
				$m = (int) $m;
				$m = $this->_getMonth($m);
				$y = date("y"); 
			}
			return $d . " " . $m . " " . $y;
		}
		private function _getMonth($index,$lang='id'){
			if($lang=='id'){
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
			}
			return $m[$index];
		}
		public function dateMonth($m){
			$m = $this->_getMonth($m);	
			return $m;
		}
	}
?>