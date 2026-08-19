<?php
	//DATABASE SETTING
	define("DBDRIVER", "mysqli");
	define("DBSERVER", "localhost");
	define("DBUSER", "root");
	define("DBPASS", "");
	define("DBNAME", "sitala_iklh");

	//APPLICATION SETTING
	define("DEFAULTCONTROLLER", "dashboard");
	define("BASEURL", "/");

	define("ASSETS", BASEURL . "assets/");
	define("LIMIT", 50);
	define("LIMIT_INDEKS", 600);
	define("LIMIT_DOWNLOAD_EXCEL", 2500);
	// define("ACTIVE_YEAR", ((int)date("Y")) - 1);
	define("ACTIVE_YEAR", ((int) date("Y")));

	//APPLICATION PATH SETTING
	define("APPLICATION", "application/");
	define("CONTROLLERS", APPLICATION . "controllers/");
	define("MODELS", APPLICATION . "models/");
	define("VIEWS", APPLICATION . "views/");

	//GLOBAL PATH SETTING
	define("DOCROOT", $_SERVER["DOCUMENT_ROOT"] . BASEURL);
	define("TEMPFOLDER", DOCROOT . "temp/");
	define("UPLOADFOLDER", DOCROOT . "uploads/");
	define("COMPILEFOLDER", TEMPFOLDER . "tpl_compile/");

	define("APP_HOST","{$_SERVER['HTTP_HOST']}");
	define("APP_IKLH", "https://{$_SERVER['HTTP_HOST']}".BASEURL);
	define("APP_IRLH", "https://{$_SERVER['HTTP_HOST']}/irlh/");
?>
