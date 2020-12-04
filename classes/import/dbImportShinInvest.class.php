<?php
class dbImportShinInvest extends dbImport {
    protected $provider_id = 3;
    protected $xml_url;
    
    
    public function getFromSource() {
        parent::getFromSource();
        
        /*
        $xml = simplexml_load_file($this->xml_url);
        
        if ($xml === false) {
            $this->toLog("Ошибка загрузки файла по URL " . $this->xml_url);
            return;
        }
        
        $json = json_encode($xml);
        $list = json_decode($json, true);
        */
        $json = file_get_contents($this->json_url);
        $list = json_decode($json, true);
        
        //printArray($list);
        //die();
        $this->convertToItems($list);
        $this->toLog("Итого получено: " . count($this->items));
    }
}


class dbImportShinInvestTyre extends dbImportShinInvest {
    protected $product_type = 1;
    protected $item_class = "dbImportItemShinInvestTyre";
    protected $json_url = "http://online.shininvest.ru/Online8/robot.php?type=tires&xml=0&detail=1&login=03928&pwd=A2AAC90714F3E10880AD5C15CCC2B791";
}

class dbImportShinInvestDisc extends dbImportShinInvest {
    protected $product_type = 2;
    protected $item_class = "dbImportItemShinInvestDisc";
    protected $json_url = "http://online.shininvest.ru/Online8/robot.php?type=disks&xml=0&detail=1&login=03928&pwd=A2AAC90714F3E10880AD5C15CCC2B791";
}
?>