<?php
set_time_limit(600);
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php"); // второй общий пролог
 
include("classes/import/megaImport.class.php");
include("classes/config.class.php");

$conf = new config("includes/config.inc.php");
$mode = (isset($_REQUEST["mode"])) ? $_REQUEST["mode"] : "menu";

switch ($mode) {
    case "price":
        megaImport::preparePrice();
        break;
    case "menu":
        megaImport::printMenu();
        break;
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
?>