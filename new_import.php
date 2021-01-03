<?php
set_time_limit(600);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php"); // второй общий пролог
 
include("classes/commonClass.class.php");
include("classes/config.class.php");
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
include("classes/import/megaImport.class.php");

include("classes/bitrix/bitrixImport.class.php");
include("classes/bitrix/bitrixImportItem.class.php");
include("classes/bitrix/bitrixCatalogSection.class.php");

$conf = new config("includes/config.inc.php");
$mode = (isset($_REQUEST["mode"])) ? $_REQUEST["mode"] : "menu";

$megaImport = new megaImport();
$megaImport->process($mode);
unset($megaImport);

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
?>