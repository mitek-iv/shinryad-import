<?php
$start_time = microtime(true);
set_time_limit(600);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php"); // второй общий пролог


// Подключение класса и его указание
CModule::IncludeModule('iblock');  
//$mode = 
print "<h3>НОВЫЙ импорт</h3>";
        //print_r($_SERVER);
        $hr = $_SERVER["PHP_SELF"];
        print "
        <style>
            .button {
                background-color: green;
                padding: 10px 20px; 
                color: #fff;
                text-decoration: none;
                margin-right: 20px;
            }
            .button:hover {
                text-decoration: none;
                opacity: 0.8;
            }
        </style>";
        print "<a href='$hr?mode=price' class='button'>Сформировать консолидированный прайс</a>
        <a href='#' class='button'>Выгрузить прайс в каталог сайта</a>";

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>