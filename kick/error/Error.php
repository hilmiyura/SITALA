<?php
	class Error{
		public function __construct() {
		}
		public function show(){
			error_reporting (E_ALL);
			ini_set("display_errors",TRUE);
		}
	}
?>
