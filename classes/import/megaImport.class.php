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
            case "replace_show":
                $this->showReplacementDict();
                break;
            case "replace_add":
                $this->addReplacementToDict();
                break;
            case "replace_del":
                $this->delReplacementToDict();
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
        /*
        //Тест поиска и добавления категории Bridgestone Blizzak DM V2
        $bitrix_catalog_sections = new bitrixCatalogSectionList(array(16, 19));
        $first_level_section =$bitrix_catalog_sections->findOrInsert(
            array(
                "name" => "Bridgestone",
                "iblock_id" => 16,
                "parent_id" => null
            )
        );

        print "1st: " . $first_level_section . "<br>";

        $parent_section = $bitrix_catalog_sections->findOrInsert(
            array(
                "name" => "Bridgestone BLIZZAK Dm v2",
                "iblock_id" => 16,
                "parent_id" => $first_level_section->id,
                "img" => $this->img
            )
        );
        print "2nd: " . $parent_section . "<br>";
        */


        //Тест обновления активности
        $bitrixImport = new bitrixImport(4, 3000);
        $bitrixImport->updateActivity();
        //$bitrixImport->process();
        unset($bitrixImport);

        /*
        $bitrixImport = new bitrixImport(4, 3000);
        $bitrixImport->getProductsFromCatalog();
        unset($bitrixImport);
        */
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
        $url_show_replace = $this->getNextStepUrl("replace_show");

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
            <a href='$url_show_replace' class='button'>Справочник замен</a>
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


    protected function showReplacementDict() {
        global $conf;

        $db = new db();

        $ar = $db->query("SELECT * FROM imp_replace WHERE 1 ORDER BY marka, model_find");

        $result = "";
        if (!empty($ar)) {
            $back_url = $_SERVER["PHP_SELF"];
            $result = "
                <a href='$back_url'><-Назад</a><br><br>
                <table cellpadding='5' cellspacing='0' border='1'>
                    <tr>
                        <th>Марка</th>
                        <th>Найти</th>
                        <th>Заменить&nbsp;на</th>
                        <th>&nbsp;</th>
                    </tr>
            ";
            foreach ($ar as $item) {
                $link_del = $this->getNextStepUrl("replace_del") . "&id=" . $item["id"];
                $result .= "
                    <tr>
                        <td>$item[marka]</td>
                        <td>$item[model_find]</td>
                        <td>$item[model_replace]</td>
                        <td><a href='$link_del'>Уд.</a></td>
                    </tr>
                ";
            }

            $result .= "</table>";
        }

        $url_add_replace = $this->getNextStepUrl("replace_add");
        $result .= "
            <h3>Добавление новой записи</h3>
            <form action='$url_add_replace' method='post'>
                <input type='text' value='' placeholder='Марка' name='edt_marka'><br>
                <input type='text' value='' placeholder='Модель найти' name='edt_model_find'><br>
                <input type='text' value='' placeholder='Модель заменить' name='edt_model_replace'><br>
                <input type='submit' value='Добавить'>
            </form>
        ";
        print $result;
    }


    protected function addReplacementToDict() {
        global $conf;

        $marka = trim(filter_var($_POST["edt_marka"], FILTER_SANITIZE_MAGIC_QUOTES));
        $model_find = trim(filter_var($_POST["edt_model_find"], FILTER_SANITIZE_MAGIC_QUOTES));
        $model_replace = trim(filter_var($_POST["edt_model_replace"], FILTER_SANITIZE_MAGIC_QUOTES));

        if ((!empty($marka)) && (!empty($model_find)) && (!empty($model_replace))) {
            $db = new db();
            $db->query(sprintf("INSERT INTO imp_replace (marka, model_find, model_replace) VALUES ('%s', '%s', '%s')", $marka, $model_find, $model_replace));
            unset($db);
        }

        $url_show_replace = $this->getNextStepUrl("replace_show");
        $this->goURL($url_show_replace);
    }


    protected function delReplacementToDict() {
        global $conf;

        if (isset($_GET["id"])) {
            $db = new db();
            $db->query(sprintf("DELETE FROM imp_replace WHERE `id` = '%d'", $_GET["id"]));
            unset($db);
        }

        $url_show_replace = $this->getNextStepUrl("replace_show");
        $this->goURL($url_show_replace);
    }
}
?>