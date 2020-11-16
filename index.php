<?php
    $start = microtime(true);

	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
    
	include("classes/config.class.php");
    include("classes/commonClass.class.php");
	include("classes/db.class.php");
    include("classes/csv.class.php");
    
    include("classes/importItem/dbImportItem.class.php");
    include("classes/importItem/dbImportItem4tochki.class.php");
    include("classes/importItem/dbImportItemKolesaDarom.class.php");

    include("classes/import/dbImport.class.php");
    include("classes/import/dbImport4tochki.class.php");
    include("classes/import/dbImportKolesaDarom.class.php");
	
    $conf = new config("includes/config.inc.php");
    $db = new db();
    
    
    $classes_to_process = ["dbImport4tochkiTyre", "dbImport4tochkiDisc", "dbImportKolesaDaromTyre"];
    foreach($classes_to_process as $class) {
        $import = new $class();
        $import->getFromSource();
        $import->storeToDB();
    }
    
    //$import = new dbImport4tochkiTyre();
    //$import = new dbImport4tochkiDisc();
    //$import = new dbImportKolesaDaromTyre();
    //$import = new dbImportKolesaDaromDisc();
    

    unset($conf);
	unset($db);

    $time = microtime(true) - $start;
    $time = number_format($time, 2, ".", "");
    print "Время выполнения запроса: $time сек.";
?>