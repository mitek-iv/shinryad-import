<?php
abstract class dbImport extends commonClass {
    protected $items = array();
    abstract function getFromSource();
}


class dbImport4tochki extends dbImport {
    protected $source_url = "http://api-b2b.pwrs.ru/WCF/ClientService.svc?wsdl";
    protected $source_login = 'sa21354';
    protected $source_password = '1ws7s%X(%F';
    protected $wrh_list = array(22, 44, 42, 43, 639); //Список складов
    protected $items_on_page = 200; 
    
    public function getFromSource() {
        //Выводим список шин по нужным складам, 
        //разбиение по $elems_on_page на странице. Текущая страница $cur_page
        $cur_page = 0;
        $total_page_count = $this->sendRequestToSource(0)->GetFindTyreResult->totalPages;
        //$total_page_count = 1;
        //print $total_page_count;
        //die();
        
        //$this->get_full_product_inform(0);
        //die();
        for ($cur_page = 0; $cur_page <= $total_page_count - 1; $cur_page++) {
            $list = $this->sendRequestToSource($cur_page)->GetFindTyreResult->price_rest_list->TyrePriceRest;
            
            if (!empty($list))
                foreach ($list as $item) {
                    $this->items[] = $this->convert($item);
                }
        }
        printArray($this->items);
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
        $answer = $client->GetFindTyre($params);     
        
        unset($client);
        
        return $answer;
    }
    
    protected function get_full_product_inform($new_ids) {
        $client = new SoapClient("http://api-b2b.pwrs.ru/WCF/ClientService.svc?wsdl");
        $params =  array
        (
          'login' => 'sa21354',
          'password' => '1ws7s%X(%F',
          'code_list' => array("796285"),
        );

        $answer = $client->GetGoodsInfo($params);  
        $list = $answer->GetGoodsInfoResult->tyreList->TyreContainer;
        printArray($list);
        unset($client);
        
        return $list;
    }
    
    
    protected function convert($item) {
        
        $new_item = new dbImportItem4tochkiTyre($item);
        //printArray($item);
        //printArray($new_item);
        return $new_item;
    }
}
?>