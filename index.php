<?php
    /**
     * COPY RIGHT	: DASENDRIA DIGITAL MEDIA (WWW.DASENDRIA.CO.ID)
	 * PROJECT		: KICK FRAMEWORK (WWW.KICKFRAMEWORK.COM)
     * CREATE DATE 	: FRIDAY, FEBRUARY 12, 2016
	 * VERSION		: 3.0
    */

    // die('SEDANG PROSES MIGRASI KE DOMAIN BARU sitala.kemenlh.go.id');
    require_once './temp/allowupload.php';
    new allowupload();

    session_start();
    error_reporting (E_ALL);
	ini_set("display_errors",FALSE);
    date_default_timezone_set("Asia/Jakarta");

    set_include_path("."
	    . PATH_SEPARATOR . "config/"
	    . PATH_SEPARATOR . "kick/"
	    . PATH_SEPARATOR . "libs/"
		. PATH_SEPARATOR . get_include_path()
	);

	require_once 'Kick.php';
	Kick::run();
?>
