<?php
	date_default_timezone_set('Etc/UTC');
	require 'phpmailer/PHPMailerAutoload.php';
	
	class sendMail {
		private $email;
		public function __construct($host, $mail, $pass, $replyemail='', $replyname='',$port=25, $debug=FALSE){
			$this->email = new PHPMailer();
			$this->email->isSMTP();
			
			if($debug){
				//Enable SMTP debugging
				// 0 = off (for production use)
				// 1 = client messages
				// 2 = client and server messages
				$this->email->SMTPDebug = 3;
				//Ask for HTML-friendly debug output
				$this->email->Debugoutput = 'html';	
			}
			
			//this->email->Host 	= gethostbyname($host);
			$this->email->Host 	= $host;
			$this->email->Port 	= $port;
			//$mail->SMTPAuth = TRUE;
			$mail->SMTPAutoTLS = false; 
			
			$this->email->SMTPSecure 	= 'tls';
			$this->email->SMTPAuth 		= true;
			
			$this->email->Username = $mail;
			$this->email->Password = $pass;
			
			if($replyemail && $replyname){
				$this->email->addReplyTo($replyemail, $replyname);
			}
		}
		
		public function send($from, $to, $subject, $message){
			//echo '<pre>'; print_r($to);die();
			if(is_array($from)){				
				$this->email->setFrom($from[0], $from[1]);
				
				foreach ($to as $val) {
					$this->email->addAddress($val[0],$val[1]);	
				}
				
				$this->email->Subject = $subject;
				
				$this->email->msgHTML($message);
				$result = 1;	
				if (!$this->email->send()) {
					$result = 0;
				}
				$this->email->ClearAddresses(); 
				//echo '<pre>'; print_r($message);die();
				return $result;
			}
		}
	}
?>