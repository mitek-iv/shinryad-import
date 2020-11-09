<?php
    $start = microtime(true);

	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
    //date_default_timezone_set("Asia/Yekaterinburg");
	
	include("classes/config.class.php");
    include("classes/commonClass.class.php");
	include("classes/db.class.php");
    include("classes/dbImportItem.class.php");
    include("classes/dbImport.class.php");
	
    $conf = new config("includes/config.inc.php");
    $db = new db();
    $fortochki = new dbImport4tochki();
    $fortochki->getFromSource();

    unset($conf);
	unset($db);

    $time = microtime(true) - $start;
    $time = number_format($time, 2, ".", "");
    print "Время выполнения запроса: $time сек.";
?>