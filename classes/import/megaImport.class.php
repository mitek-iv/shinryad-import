<?php
class megaImport extends commonClass {
    protected $start;
    protected $step_time;
    protected $step;
    protected $tmp_file_name = "start.txt";
    
    
    public function __construct() {
        $this->start = microtime(true);   
        
        if (!isset($_REQUEST["step"]))
            $this->step = 0;
        else 
            $this->step = (int) $_REQUEST["step"];
    }


    public function process($mode) {
        switch ($mode) {
            case "price":
                $this->preparePrice();
                break;
            case "export":
                $this->exportPriceToBitrixCatalog();
                break;
            case "menu":
                $this->printMenu();
                break;
            case "test":
                $this->test();
                break;
            case "clear":
                $this->clearIB();
                break;
            case "get_csv":
                $this->getCSV();
                break;
        }
    }


    protected function getCSV() {
        global $conf;

        $db = new db();

        $res = $db->query("
            SELECT `id`, `provider_id`, `type_id`, `code`, `marka`, `model`, `size`, `full_title`, `provider_title`, `price_opt`, `price`, `count`, `params`, `img`, `is_processed`
            FROM imp_product_compact 
            WHERE 1
            ORDER BY type_id, marka, model, size
        ");

        $csv = new CSVWriter("imp_product_compact.csv");
        $header = array("id", "provider_id", "type_id", "code", "marka", "model", "size", "full_title", "provider_title", "price_opt", "price", "count", "params", "img", "is_processed");
        $csv->add($header);
        foreach($res as $item) {
            //$item["marka"] = htmlspecialchars_decode($item["marka"], ENT_QUOTES);
            //$item["model"] = htmlspecialchars_decode($item["model"], ENT_QUOTES);
            //$item["size"] = htmlspecialchars_decode($item["size"], ENT_QUOTES);
            //$item["full_title"] = htmlspecialchars_decode($item["full_title"], ENT_QUOTES);
            //$item["provider_title"] = htmlspecialchars_decode($item["provider_title"], ENT_QUOTES);
            //printArray($item);
            $csv->add($item);
        }
        $csv->get(false);
        unset($csv);
        unset($db);

        print "<a href='imp_product_compact.csv' target='_blank'>Скачать файл</a>";
    }


    protected function test() {
        $bitrixImport = new bitrixImport(4, 3000);
        //$total_step_count = $bitrixImport->getTotalStepCount();
        //$bitrixImport->getFromDB();
        $bitrixImport->process();
        //$bitrixImport->insertNewProducts();
        unset($bitrixImport);
    }


    protected function clearIB() {
        print "Пока отключил эту функцию";
        die();

        if ($this->step > 10) {
            print "ok";
            return;
        }
        $res = bitrixImport::clearIB(19);
        if ($res) {
            $url = $this->getNextStepUrl(null, $this->step + 1);
            $this->goURL($url);
        }
    }



    protected function printMenu() {
        $url_price = $this->getNextStepUrl("price");
        $url_export = $this->getNextStepUrl("export");
        $url_test = $this->getNextStepUrl("test");
        $url_clear = $this->getNextStepUrl("clear");
        $url_get_csv = $this->getNextStepUrl("get_csv");

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
            <a href='$url_export' class='button'>Выгрузить прайс в каталог сайта</a>
            <a href='$url_test' class='button'>Test</a>
            <a href='$url_get_csv' class='button'>CSV</a>
            <!--<a href='$url_clear' class='button'>Clear</a>-->";

        print $result;
    }


    protected function preparePrice() {
        if ($this->checkScriptIsRunning())
            die();

        if ($this->step == 0) {
            $this->startScript();
            dbImport::clearTables();
            sleep(1);
            $url = $this->getNextStepUrl(null, 1);
            $this->goURL($url);
        } else {
            $this->toLog("Шаг " . $this->step);
        }

        $classes_to_process = [
            "dbImport4tochkiTyre", 
            "dbImport4tochkiDisc", 
            "dbImportKolesaDaromTyre",
            "dbImportKolesaDaromDisc",
            "dbImportShinInvestTyre",
            "dbImportShinInvestDisc",
        ];

        $this->createScriptIsRunningFile();
        
        if (isset($classes_to_process[$this->step - 1])) {
            $class = $classes_to_process[$this->step - 1];
            $import = new $class();
            $import->getFromSource();
            //die();
            $import->storeToDB();
            unset($import);
        } elseif ($this->step == count($classes_to_process) + 1) {
            dbImport::compactProductList();
        }
        
        $this->deleteScriptIsRunningFile();

        $this->step++;
        $this->calcStepTime();

        if ($this->step > count($classes_to_process) + 1) {
            $this->finishScript();
        } else {
            $url = $this->getNextStepUrl(null, $this->step);
            $this->goURL($url);
        }
    }


    protected function exportPriceToBitrixCatalog() {
        if ($this->checkScriptIsRunning())
            die();
        
        if ($this->step == 0) {
            $this->startScript();
            sleep(1);
            $url = $this->getNextStepUrl(null, 1);
            $this->goURL($url);
        } else {
            $this->toLog("Шаг " . $this->step);
        }

        $this->createScriptIsRunningFile();
        
        $bitrixImport = new bitrixImport($this->step - 1,3000);
        $total_step_count = $bitrixImport->getTotalStepCount();
        //$bitrixImport->getFromDB();
        $bitrixImport->process();
        unset($bitrixImport);
        
        $this->deleteScriptIsRunningFile();
        
        $this->step++;
        $this->calcStepTime();
        
        if ($this->step > $total_step_count) {
            $this->finishScript();
        } else {
            $url = $this->getNextStepUrl(null, $this->step);
            $this->goURL($url);
        }
    }
    
    
    protected function calcStepTime() {
        $time = microtime(true) - $this->start;
        $this->step_time = number_format($time, 2, ".", "");
        $this->toLog(sprintf("Время выполнения шага: %d сек.", $this->step_time));
    }
    
    
    protected function getNextStepUrl($mode, $step = 0) {
        if (is_null($mode)) 
            global $mode;
        
        $url = sprintf("%s?mode=%s&step=%d", $_SERVER["PHP_SELF"], $mode, $step);
        
        return $url;
    }
    
    
    protected function checkScriptIsRunning() {
        $result = false;
        if (file_exists($this->tmp_file_name)) {//Если файл существует, то значит скрипт уже выполняется на каком-то шаге
            $this->deleteScriptIsRunningFile();
            $result = true;
        }
        
        return $result;
    }
    
    
    protected function createScriptIsRunningFile() {
        file_put_contents($this->tmp_file_name, date("d.m.Y H:i:s"), FILE_APPEND | LOCK_EX); //В начале выполнения каждого шага создаём файл
    }
    
    
    protected function deleteScriptIsRunningFile() {
        @unlink($this->tmp_file_name);
    }
    
    
    protected function startScript() {
        $this->toLog("\r\n---Запуск обработки---", true);    
    }
    
    
    protected function finishScript() {
        $this->toLog("---Конец обработки---", true);   
        print "---Конец обработки---";
    }
    
    
    protected function goURL($url) {
        $this->toLog("location: $url");
        header("location: $url");
        exit;
    }
}
?>