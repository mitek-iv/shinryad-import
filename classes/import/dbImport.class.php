<?php
class dbImport extends commonClass {
    protected $provider_id; //Ид. поставщика
    protected $product_type; //Тип продукта (шины/диски)
    protected $items = array(); //Массив обработанных товаров. Элементы - экземпляры класса $item_class
    protected $item_class; //Название класса для элемента массива $items;
    
    /**
    Получение данных от источника
    */
    public function getFromSource() {
        $this->toLog("Получение данных от источника");
    }

    /**
    Сохранение данных из $items в БД
    */
    public function storeToDB() {
        
        $this->toLog("Запись данных в БД");
        global $conf;
        $db = new db();
        
        $db->query(sprintf("DELETE FROM imp_product_full WHERE provider_id = '%d' AND type_id = '%d'", $this->provider_id, $this->product_type));
        
        $total_insert_count = 0;
        if (!empty($this->items)) {
            $insert_queries = [];
            foreach($this->items as $item)
                if ($item->count > 0) {
                    $insert_queries[] = $item->queryString($this->provider_id);
                    $total_insert_count++;
                }
            
            $insert_query = "INSERT INTO imp_product_full (`provider_id`, `type_id`, `code`, `marka`, `model`, `size`, `full_title`, `price_opt`, `price`, `count`, `params`, `provider_title`) VALUES " 
                . implode(",", $insert_queries);
        }
        
        //print $insert_query;
        //die();
        
        $db->query($insert_query);
        $this->toLog("Итого записано в БД: " . $total_insert_count);
        
        unset($db);
    }
    
    
    /**
    Преобразование массива или stdCalss в объект класса, предназначенного для дальнейшей обработки
    */
    protected function convertToItems(array $list) {
        if (empty($list)) return;
        
        foreach ($list as $item)
            $this->items[] = new $this->item_class($item);
    }
}
?>