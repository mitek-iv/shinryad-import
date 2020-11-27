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
    include("classes/importItem/dbImportItemShinInvest.class.php");

    include("classes/import/dbImport.class.php");
    include("classes/import/dbImport4tochki.class.php");
    include("classes/import/dbImportKolesaDarom.class.php");
    include("classes/import/dbImportShinInvest.class.php");
	
    $conf = new config("includes/config.inc.php");
    
    if (!isset($_REQUEST["step"]))
        $step = 0;
    else $step = (int) $_REQUEST["step"];

    if ($step == 0)
        toLog("\r\n---Запуск обработки---", true);
    else
        toLog("Шаг $step");

    $classes_to_process = [
        //"dbImport4tochkiTyre", 
        //"dbImport4tochkiDisc", 
        //"dbImportKolesaDaromTyre",
        //"dbImportKolesaDaromDisc",
        "dbImportShinInvestTyre",
    ];
    
    if (isset($classes_to_process[$step])) {
        $class = $classes_to_process[$step];
        
        $import = new $class();
        $import->getFromSource();
        //die();
        $import->storeToDB();
        unset($import);
    } elseif ($step == count($classes_to_process)) {
        dbImport::compactProductList();
    }

    
    $time = microtime(true) - $start;
    $time = number_format($time, 2, ".", "");
    toLog("Время выполнения шага: $time сек.");

    unset($conf);

    $step++;
    
    if ($step > count($classes_to_process)) {
        toLog("---Конец обработки---", true);    
    } else {
        $hr = $_SERVER["PHP_SELF"];
        header("location: $hr?step=$step");
        exit;
    }
?>