<?php
	require_once "auth/Auth.php";
	require_once "db/Adodb.php";
	require_once "db/Database.php";
	require_once "debug/Debug.php";
	require_once "email/Email.php";
	require_once "encrypt/Encrypt.php";
	// require_once "error/Error.php";
	require_once "filter/Filter.php";
	require_once "format/Format.php";
	require_once "image/Image.php";
	require_once "message/Message.php";
	require_once "pdf/Pdf.php";
	require_once "session/Session.php";
	require_once "uri/Uri.php";
	require_once "view/View.php";
	require_once "upload/Upload.php";
	require_once "cache/Cache.php";

	class Front{
		protected $auth;
		public $db;
		protected $debug;
		protected $email;
		protected $encryption;
		// protected $error;
		protected $filter;
		protected $format;
		protected $image;
		protected $message;
		protected $pdf;
		protected $uri;
		protected $session;
		protected $view;
		protected $upload;
		public $cache;

		public function __construct() {
			$this->auth 		= new Auth();
			$this->db 			= new Adodb();
			$this->debug		= new Debug();
			$this->email		= new Email();
			$this->encrypt		= new Encrypt();
			// $this->error		= new Error();
			$this->filter		= new Filter();
			$this->format		= new Format();
			$this->image		= new Image();
			$this->message		= new Message();
			$this->pdf			= new Pdf();
			$this->session		= new Session();
			$this->uri			= new Uri();
			$this->view 		= new View();
			$this->upload 		= new Upload();
			$this->cache 		= new Cache();
		}

		public function models($model)
    {
		if (is_array($model)) {
	        foreach ($model as $name) {
	            $file = $name . ".php";
	            $path = MODELS . $file;
	            if (file_exists($path)) {
	                require_once MODELS . $file;

	                $this->$name = new $name($this);
	            }
	        }
		}
    }

		public function loadModel($model){
			$file_model =  $model . ".php";

			if(Kick::checkFile(MODELS, $file_model)){
				require_once MODELS . $file_model;
				$this->$model = new $model;
			}else{
				die("File " . $file_model . " not found in models folder");
			}
		}
		public function params($name=""){
			$params = $this->uri->getParams();
			if($name){
				if(isset($params[$name])){
					return $params[$name];
				}
			}else{
				return $params;
			}
		}
		public function redirect($to=""){
			header('location:' . BASEURL . $to);
		}
		public function post(){
			return $_POST;
		}
		public function getClass($obj){
			return get_class($obj);
		}
	}
?>
