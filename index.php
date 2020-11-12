<?php
    $start = microtime(true);

	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
    
	
	include("classes/config.class.php");
    include("classes/commonClass.class.php");
	include("classes/db.class.php");
    include("classes/csv.class.php");
    include("classes/dbImportItem.class.php");
    include("classes/dbImport.class.php");
    include("classes/dbImport4tochki.class.php");
    include("classes/dbImportKolesaDarom.class.php");
	
    $conf = new config("includes/config.inc.php");
    $db = new db();
    //$import = new dbImport4tochkiTyre();
    //$import = new dbImport4tochkiDisc();
    //$import = new dbImport4KolesaDaromTyre("1/importcsv.csv");
    $import = new dbImport4KolesaDaromDisc("1/importcsv.csv");
    $import->getFromSource();
    //$import->storeToDB();

    unset($conf);
	unset($db);

    $time = microtime(true) - $start;
    $time = number_format($time, 2, ".", "");
    print "Время выполнения запроса: $time сек.";
?>