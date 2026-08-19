<?php
	class Encrypt{
		private $key = "K1ck Fr4m3w0rk";
		public function __construct(){
			//";","/", "?", ":", "@", "=" and "&" in URls
		}
		public function toMD5($string,$iteration=1){
			for($i=0;$i<$iteration;$i++){
				$string = md5($string);
			}
			return $string;
		}
		public function encode($string){			
			$encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($this->key), $string, MCRYPT_MODE_CBC, md5(md5($this->key))));
			$encrypted = str_replace("/", "-", $encrypted);
			return $encrypted;
		}
		public function decode($string){
			$string = str_replace("-", "/", $string);
			$decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($this->key), base64_decode($string), MCRYPT_MODE_CBC, md5(md5($this->key))), "\0");			
			return $decrypted;			
		}
		
	}
?>