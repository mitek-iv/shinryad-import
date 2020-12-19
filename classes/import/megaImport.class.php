<?php
class megaImport {
    public static function getNextStepUrl($mode, $step = 0) {
        if (is_null($mode)) 
            global $mode;
        
        return sprintf("%s?mode=%s&step=%d", $_SERVER["PHP_SELF"], $mode, $step);
    }
    
    
    public static function printMenu() {
        $url_price = self::getNextStepUrl("price");
        $url_export = self::getNextStepUrl("export");

        $result = "
            <h3>НОВЫЙ импорт</h3>
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
            </style>
            <a href='$url_price' class='button'>Сформировать консолидированный прайс</a>
            <a href='$url_export' class='button'>Выгрузить прайс в каталог сайта</a>";

        print $result;
    }
    

    public static function preparePrice() {
        $start = microtime(true);

        if (!isset($_REQUEST["step"]))
            $step = 0;
        else $step = (int) $_REQUEST["step"];

        if (file_exists("start.txt")) {//Если файл существует, то значит скрипт уже выполняется на каком-то шаге
            @unlink("start.txt");
            die();
        }

        if ($step == 0) {
            toLog("\r\n");
            toLog("---Запуск обработки---", true);
            dbImport::clearTables();
            sleep(1);
            $url = self::getNextStepUrl(null, 1);
            toLog("location: $url");
            header("location: $url");
            exit();
        } else {
            toLog("Шаг $step");
        }


        $classes_to_process = [
            "dbImport4tochkiTyre", 
            "dbImport4tochkiDisc", 
            "dbImportKolesaDaromTyre",
            "dbImportKolesaDaromDisc",
            "dbImportShinInvestTyre",
            "dbImportShinInvestDisc",
        ];

        file_put_contents("start.txt", date("d.m.Y H:i:s"), FILE_APPEND | LOCK_EX); //В начале выполнения каждого шага создаём файл
        if (isset($classes_to_process[$step - 1])) {
            $class = $classes_to_process[$step - 1];
            $import = new $class();
            $import->getFromSource();
            //die();
            $import->storeToDB();
            unset($import);
        } elseif ($step == count($classes_to_process) + 1) {
            dbImport::compactProductList();
        }
        @unlink("start.txt"); //В конце выполнения каждого шага удаляем файл

        $time = microtime(true) - $start;
        $time = number_format($time, 2, ".", "");
        toLog("Время выполнения шага: $time сек.");

        unset($conf);

        $step++;

        if ($step > count($classes_to_process) + 1) {
            toLog("---Конец обработки---", true);   
            print "---Конец обработки---";
        } else {
            $url = self::getNextStepUrl(null, $step);
            toLog("location: $url");
            header("location: $url");
            exit;
        }
    }
    
    
    public static function exportPriceToBitrixCatalog() {
        $bitrixImport = new bitixImport();
        $bitrixImport->getFromDB();
        $bitrixImport->updateExistingProducts();
        die();
        
        $start = microtime(true);

        if (!isset($_REQUEST["step"]))
            $step = 0;
        else $step = (int) $_REQUEST["step"];

        if (file_exists("start.txt")) {//Если файл существует, то значит скрипт уже выполняется на каком-то шаге
            @unlink("start.txt");
            die();
        }

        if ($step == 0) {
            toLog("\r\n");
            toLog("---Запуск обработки---", true);
            sleep(1);
            $url = self::getNextStepUrl(null, 1);
            toLog("location: $url");
            header("location: $url");
            exit();
        } else {
            toLog("Шаг $step");
        }

        $step++;
        
        CModule::IncludeModule('iblock');  
        $bitrixImport = new bitixImport();
        $bitrixImport->getFromDB();
        $bitrixImport->updateExistingProducts();
        $bitrixImport->insertNewProducts();

        //printArray($bitrixImport);
        unset($bitrixImport);
        
        if ($step > 1) {
            toLog("---Конец обработки---", true);   
            print "---Конец обработки---";
        } else {
            $url = self::getNextStepUrl(null, $step);
            toLog("location: $url");
            header("location: $url");
            exit;
        }
    }
}
?>