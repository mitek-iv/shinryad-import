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
    Избавляется от дублирующихся предложений, оставляя предложения с наименьшей ценой
    
    Во внутреннем запросе происходит группировка по позициям с определением минимальной цены
    Во внешнем запросе избавляемся от дублирующих строчек с одинаковой минимальной ценой
    Потом результат сохраняем в таблицу imp_product_compact - уникальные товарные предложения
    */
    public static function compactProductList() {
        toLog("Сжимаем список товарных предложений");
        global $conf;
        $db = new db();
        $db->query("TRUNCATE TABLE imp_product_compact");
        $db->query("
            INSERT INTO imp_product_compact
            SELECT * FROM imp_product_full WHERE id IN (
                SELECT MAX(id) as id
                FROM (
                    SELECT P1.id, P1.`type_id`, P1.`marka`, P1.`model`, P1.`size`, P1.`price`
                    FROM imp_product_full P1
                    INNER JOIN (
                        SELECT `type_id`, `marka`, `model`, `size`, MIN(price) as min_price, COUNT(*) as cnt
                        FROM `imp_product_full`
                        WHERE 1
                        GROUP BY `type_id`, `marka`, `model`, `size`
                    ) P2
                    ON (P1.`type_id` = P2.`type_id`) AND (P1.`marka` = P2.`marka`) AND (P1.`model` = P2.`model`) AND (P1.`size` = P2.`size`) AND (P1.`price` = P2.`min_price`)
                ) P3
                WHERE 1
                GROUP BY  `type_id`, `marka`, `model`, `size`, `price`
            )
        ");
        
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