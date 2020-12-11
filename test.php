<?php
    $start = microtime(true);
	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
    
	include("classes/config.class.php");
    include("classes/commonClass.class.php");
	include("classes/db.class.php");
    include("classes/importItem/dbImportItem.class.php");
    include("classes/import/bitrixImport.class.php");

    $conf = new config("includes/config.inc.php");
    /*    
    if (!empty($is_processed)) {
        $db->query(sprintf("UPDATE imp_product_compact SET is_processed = 1 WHERE id IN (%s)", implode(", ", $is_processed)));
    }
        
    unset($db);
    */
    $bitrixImport = new bitixImport();
    $bitrixImport->getFromDB();
    $bitrixImport->updateExistingProducts();
    $bitrixImport->insertNewProducts();

    //printArray($bitrixImport);
    unset($bitrixImport);
?>