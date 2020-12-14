<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

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
?>