<?php
class bitixImportProduct extends dbImportItem {
    public $is_processed = false;
    
    public function __construct(array $source) {
        $this->id = $source["code"];
        $this->product_type = $source["type_id"];
        $this->marka = $source["marka"];
        $this->model = $source["model"];
        $this->size = $source["size"];
        $this->full_title = $source["full_title"];
        $this->price = $source["price"];
        $this->price_opt = $source["price_opt"];
        $this->count = $source["count"];
        $this->img = $source["img"];
        $this->params = json_decode($source["params"], true);
        $this->is_processed = ($source["is_processed"] == 1);
    }
    
}


class bitixImport extends commonClass {
    protected $items = array(); //of bitixImportProduct
    
    /*
    * Получает товары с остатками по всем поставщикам, уже свёрнутые (сгруппированные) по размеру
    */
    public function getFromDB() {
        $this->toLog("Получение сжатого прайс-листа из БД");
        global $conf;
        
        $db = new db();
        $res = $db->query("
            SELECT * 
            FROM imp_product_compact 
            WHERE type_id = 1 
            ORDER BY type_id, marka, model, size
        ");
        
        $products = [];
        $is_processed = [];
        if(!empty($res))
            foreach($res as $item) {
                $product = new bitixImportProduct($item);
                $this->items[$item["type_id"]][$item["marka"]][$item["model"]][$item["size"]] = $product;
            }
    }
    
    
    /*
    * Пробегается по товарам из каталога битрикс и пытается найти аналогичный товар из $items
    * Если находит, то обновляет остатки и ставит флаг у товара в $items is_processed = true   
    * Если не находит, то ставит кол-во 0 и деактивирует
    */    
    public function updateExistingProducts() {
        $this->toLog("Обновление остатков у существующих товаров");
    }
    
    /*
    * Пробегается по $items. Если is_processed = false
    * Значит считаем, что товар - новый. Добавляем его в каталог Битрикс
    */
    public function insertNewProducts() {
        $this->toLog("Добавление новых товаров");
    }
}
?>