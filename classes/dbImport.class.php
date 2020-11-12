<?php
abstract class dbImport extends commonClass {
    protected $provider_id; //Ид. поставщика
    protected $product_type;
    protected $items = array();
    abstract function getFromSource();
    abstract function storeToDB();
}


class dbImport4tochki extends dbImport {
    protected $provider_id = 1;
    protected $source_url = "http://api-b2b.pwrs.ru/WCF/ClientService.svc?wsdl";
    protected $source_login = 'sa21354';
    protected $source_password = '1ws7s%X(%F';
    protected $wrh_list = array(22, 44, 42, 43, 639); //Список складов
    protected $items_on_page = 200; 
    protected $method = null; //Название метода для получения данных через API. Перекрывается в потомках
    
    public function getFromSource() {
        $cur_page = 0;
        
        //в зависимости от класса (диски или шины) динамически формируем методы, которые будут вызываться в API
        $method_result = "GetFind" . $this->method . "Result";
        $method_rest = $this->method . "PriceRest";
        
        $total_page_count = $this->sendRequestToSource(0)->$method_result->totalPages;
        //$total_page_count = 1;
        //print $total_page_count;
        //die();
        
        //$this->get_full_product_inform(0);
        //die();
        for ($cur_page = 0; $cur_page <= $total_page_count - 1; $cur_page++) {
            
            $list = $this->sendRequestToSource($cur_page)->$method_result->price_rest_list->$method_rest;
            
            if (!empty($list))
                foreach ($list as $item) {
                    $this->items[] = $this->convert($item);
                }
        }
        //printArray($this->items);
    }
    
    
    public function storeToDB() {
        global $db;
        
        $db->query(sprintf("DELETE FROM imp_product_full WHERE provider_id = '%d' AND type_id = '%d'", $this->provider_id, $this->product_type));
        
        if (!empty($this->items)) {
            $insert_queries = [];
            foreach($this->items as $item)
                if ($item->count > 0)
                    $insert_queries[] = $item->queryString($this->provider_id);
            
            $insert_query = "INSERT INTO imp_product_full (`provider_id`, `type_id`, `code`, `marka`, `model`, `size`, `full_title`, `price_opt`, `price`, `count`, `params`) VALUES " 
                . implode(",", $insert_queries);
        }
        
        //print $insert_query;
        //die();
        
        $db->query($insert_query);
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
    
    /*
    protected function get_full_product_inform($new_ids) {
        $client = new SoapClient("http://api-b2b.pwrs.ru/WCF/ClientService.svc?wsdl");
        $params =  array
        (
          'login' => 'sa21354',
          'password' => '1ws7s%X(%F',
          'code_list' => array("WHS029554"),
        );

        $answer = $client->GetGoodsInfo($params);  
        //$list = $answer->GetGoodsInfoResult->tyreList->TyreContainer;
        $list = $answer->GetGoodsInfoResult->rimList->RimContainer;
        printArray($list);
        unset($client);
        
        return $list;
    }
    */
}


class dbImport4tochkiTyre extends dbImport4tochki {
    protected $product_type = 1;
    protected $method = "Tyre";
    
    protected function convert($item) {
        return new dbImportItem4tochkiTyre($item);
    }
}


class dbImport4tochkiDisc extends dbImport4tochki {
    protected $product_type = 2;
    protected $method = "Disk";
    
    protected function convert($item) {
        return new dbImportItem4tochkiDisc($item);
    }
}

?>