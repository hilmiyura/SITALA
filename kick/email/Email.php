<?php
	class Email{
		public function __construct() {
		}
		public function send($from, $to, $subject,$message, $cc=""){
			$headers  = "From: " . strip_tags($from) . "\r\n";
			$headers .= "Reply-To: ". strip_tags($from) . "\r\n";
			if($cc) $headers .= "CC: ".$cc."\r\n";
			
			$headers .= "MIME-Version: 1.0\r\n";
			$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
			
			//Returns TRUE if the mail was successfully accepted for delivery, FALSE otherwise.			
			return mail($to,$subject,$headers); 
		}
	}
?>