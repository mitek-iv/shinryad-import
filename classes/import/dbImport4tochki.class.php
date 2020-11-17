<?php
class dbImport4tochki extends dbImport {
    protected $provider_id = 1;
    protected $source_url = "http://api-b2b.pwrs.ru/WCF/ClientService.svc?wsdl";
    protected $source_login = 'sa21354';
    protected $source_password = '1ws7s%X(%F';
    protected $wrh_list = array(22, 44, 42, 43, 639); //Список складов
    protected $items_on_page = 200; 
    protected $method = null; //Название метода для получения данных через API. Перекрывается в потомках
    
    public function getFromSource() {
        parent::getFromSource();
        $cur_page = 0;
        
        //в зависимости от класса (диски или шины) динамически формируем методы, которые будут вызываться в API
        $method_result = "GetFind" . $this->method . "Result";
        $method_rest = $this->method . "PriceRest";
        
        try {
            $total_page_count = $this->sendRequestToSource(0)->$method_result->totalPages;
            //$total_page_count = 1;
            //print $total_page_count;
            //die();

            //$this->get_full_product_inform(0);
            //die();
            for ($cur_page = 0; $cur_page <= $total_page_count - 1; $cur_page++) {
                $list = $this->sendRequestToSource($cur_page)->$method_result->price_rest_list->$method_rest;
                $this->convertToItems($list);
            }
        } catch (Exception $e) {
            $this->toLog($e->getMessage());
        }
        
        $this->toLog("Итого получено: " . count($this->items));
        //printArray($list);
        //printArray($this->items);
    }
    
    
    protected function sendRequestToSource($cur_page) {
        $client = new SoapClient($this->source_url);
        $params =  array (
            'login' => $this->source_login,
            'password' => $this->source_password,
            'filter' => array("wrh_list" => $this->wrh_list), 
            'page' => $cur_page,
            'pageSize' => $this->items_on_page,
        );
        //$answer = $client->GetFindTyre($params);     
        $answer = call_user_func(array($client, "GetFind" . $this->method), $params);
        
        unset($client);
        
        return $answer;
    }
}


class dbImport4tochkiTyre extends dbImport4tochki {
    protected $product_type = 1;
    protected $item_class = "dbImportItem4tochkiTyre";
    protected $method = "Tyre";
}


class dbImport4tochkiDisc extends dbImport4tochki {
    protected $product_type = 2;
    protected $item_class = "dbImportItem4tochkiDisc";
    protected $method = "Disk";
}
?>