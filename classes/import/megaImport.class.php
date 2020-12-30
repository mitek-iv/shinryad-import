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


    public function test() {
        $bitrixImport = new bitixImport(0, 2000);
        //$total_step_count = $bitrixImport->getTotalStepCount();
        //$bitrixImport->getFromDB();
        $bitrixImport->process();
        //$bitrixImport->insertNewProducts();
        unset($bitrixImport);
    }


    public function printMenu() {
        $url_price = $this->getNextStepUrl("price");
        $url_export = $this->getNextStepUrl("export");
        $url_test = $this->getNextStepUrl("test");

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
            <a href='$url_test' class='button'>Test</a>";

        print $result;
    }
    

    public function preparePrice() {
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
    
    
    public function exportPriceToBitrixCatalog() {
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
        
        $bitrixImport = new bitixImport($this->step - 1,3000);
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