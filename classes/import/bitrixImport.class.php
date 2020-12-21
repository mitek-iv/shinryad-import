<?php
use Bitrix\Iblock\ElementTable;
//use Bitrix\Main\Entity;
//use Bitrix\Catalog\ProductTable;
//use Bitrix\Main\Diag;


class bitixImportProduct extends dbImportItem {
    public $import_id; //ид-р строки в таблице импорта (imp_product_compact)
    public $bitrix_catalog_id; //ид-р товара в каталоге Битрикса
    public $bitrix_price_id; //ид-р цены в каталоге Битрикса
    public $is_processed = false;

    protected $prop_price_old_id;
    protected $prop_price_min_id;
    
    public function __construct(array $source) {
        $this->import_id = $source["id"];
        $this->id = $source["code"];
        //$this->product_type = $source["type_id"];
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


    /**
    * Обновление остатков у одного товара
    */
    public function updatePriceCount() {
        if (empty($this->bitrix_catalog_id))
            return false;

        //printArray($this);
        $dbResult = \Bitrix\Catalog\ProductTable::update($this->bitrix_catalog_id, ["QUANTITY" => $this->count]);
        $dbResult = \Bitrix\Catalog\PriceTable::update($this->bitrix_price_id, ["PRICE" => $this->price]);

        //Апдейтим свойства Минимальная цена и Старая цена
        $PROP = array();
        $PROP[$this->prop_price_old_id] = $this->roundPrice($this->price * 1.1); //Минимальная цена
        $PROP[$this->prop_price_min_id] = $this->price_opt + 100; //Старая цена
        CIBlockElement::SetPropertyValuesEx($this->bitrix_catalog_id, false, $PROP);

        return true;
    }
}


class bitixImportProductTyre extends bitixImportProduct {
    protected $product_type = 1;
    protected $prop_price_old_id = 421;
    protected $prop_price_min_id = 447;
}


class bitixImportProductDisc extends bitixImportProduct {
    protected $product_type = 2;
    protected $prop_price_old_id = 454;
    protected $prop_price_min_id = 444;
}


//--------------------------------------------------------------

class bitixImport extends commonClass {
    protected $items_per_step; //количество позиций, обрабатываемых за один шаг
    protected $items = array(); //of bitixImportProduct
    protected $item_tree = array(); //items в виде дерева
    protected $catalog_products = array(); //Товары, полученные из каталога битрикс
    
    
    public function __construct($items_per_step = 2000) {
        $this->items_per_step = $items_per_step;
    }
    
    
    public function getTotalStepCount() {
        $this->toLog("Вычисление требуемого количества шагов");
        global $conf;
        
        $db = new db();
        $rc = $db->val("
            SELECT COUNT(*) as rs
            FROM imp_product_compact 
            WHERE 1 
        ");    
        
        $total_step_count = ceil($rc / $this->items_per_step);
        $this->toLog("Всего позиций: $rc; Требуется шагов: $total_step_count");
        return $total_step_count;
    }
    
    
    /**
    * Получает товары с остатками по всем поставщикам, уже свёрнутые (сгруппированные) по размеру
    */
    public function getFromDB($step = 1) {
        $this->toLog("Получение сжатого прайс-листа из БД");
        global $conf;
        
        $limit_from = (($step - 1) * $this->items_per_step);
        $this->toLog("limit_from: $limit_from");
        $db = new db();
        $res = $db->query(sprintf("
            SELECT * 
            FROM imp_product_compact 
            WHERE is_processed = 0
            ORDER BY type_id, marka, model, size
            LIMIT %d, %d
        ", $limit_from, $this->items_per_step));
        
        $products = [];
        $is_processed = [];
        if(!empty($res))
            foreach($res as $item) {
                if ($item["type_id"] == 1)
                    $product = new bitixImportProductTyre($item);
                else
                    $product = new bitixImportProductDisc($item);

                $this->items[] = $product;
                $this->item_tree[$item["type_id"]][$item["marka"]][$item["model"]][$item["size"]] = $product;
            }

        $this->toLog("Загружено " . count($this->items));
        //printArray($this->item_tree);
    }
    
    
    /**
    * Пробегается по товарам из каталога битрикс и пытается найти аналогичный товар из $items
    * Если находит, то обновляет остатки и ставит флаг у товара в $items is_processed = true   
    * Если не находит, то ставит кол-во 0 и деактивирует
    */    
    public function updateExistingProducts() {
        $this->toLog("Обновление остатков у существующих товаров");
        $this->getProductsFromCatalog();
        $this->resetCountInCatalog();
        $this->findAnalogItems();
        $this->updatePriceCount();
        $this->storeProcessedItems();
        //print count($this->catalog_products);


//        $connection = Bitrix\Main\Application::getConnection();
//        /** Bitrix\Main\Diag\SqlTracker $tracker */
//        $tracker = $connection->startTracker();
//        $dbResult = \Bitrix\Catalog\ProductTable::updateMulti($ids, ["QUANTITY" => 0]);
//        foreach ($tracker->getQueries() as $query) {
//            var_dump($query->getSql()); // Текст запроса
//            var_dump($query->getTrace()); // Стек вызовов функций, которые привели к выполнению запроса
//            var_dump($query->getTime()); // Время выполнения запроса в секундах
//        }

        unset($res);
    }
    
    
    /**
    * Пробегается по $items. Если is_processed = false
    * Значит считаем, что товар - новый. Добавляем его в каталог Битрикс
    */
    public function insertNewProducts() {
        $this->toLog("Добавление новых товаров");
    }

    /**
     * Получает список товаров из каталога
     * Значит считаем, что товар - новый. Добавляем его в каталог Битрикс
     */
    protected function getProductsFromCatalog() {
        $dbQuery = ElementTable::query()
            //Подтягиваем родительскую категорию
            //Для подключения возможности джойна свойств элементов необходимо подключить файл include(elementproperty.php) в init.php
            ->registerRuntimeField('MODEL', [
                    'data_type' => '\Bitrix\Iblock\SectionTable',
                    'reference' => ['=this.IBLOCK_SECTION_ID' => 'ref.ID'],
                    'join_type' => "LEFT"
                ]
            )
            //Подтягиваем родительскую родительской категорию
            ->registerRuntimeField('PROIZV', [
                    'data_type' => '\Bitrix\Iblock\SectionTable',
                    'reference' => ['=this.MODEL.IBLOCK_SECTION_ID' => 'ref.ID'],
                    'join_type' => "LEFT"
                ]
            )
            //Вычисляемое поле
            ->registerRuntimeField("SIZE", [
                    "data_type" => "string",
                    "expression" => ["TRIM(REPLACE(%s, %s, ' '))", "NAME", "MODEL.NAME"],
                    'join_type' => "LEFT"
                ]
            )
            //Вычисляемое поле
            ->registerRuntimeField("MODEL_NAME", [
                    "data_type" => "string",
                    "expression" => ["TRIM(REPLACE(%s, %s, ' '))", "MODEL.NAME", "PROIZV.NAME"],
                    'join_type' => "LEFT"
                ]
            )
            ->registerRuntimeField("PRODUCT", [
                    'data_type' => '\Bitrix\Catalog\ProductTable',
                    'reference' => array('=this.ID' => 'ref.ID'),
                    'join_type' => "LEFT"
                ]
            )
            ->registerRuntimeField("PRICE", [
                    'data_type' => '\Bitrix\Catalog\PriceTable',
                    'reference' => array('=this.ID' => 'ref.PRODUCT_ID'),
                    'join_type' => "LEFT"
                ]
            )
            ->setSelect([
                'ID',
                'IBLOCK_ID',
                'IBLOCK_SECTION_ID',
                'NAME',
                "MODEL_NAME",
                "MARKA" => 'PROIZV.NAME',
                'SIZE',
                'QUANTITY' => 'PRODUCT.QUANTITY',
                'PRICE_ID' => 'PRICE.ID',
                'PRICE_VALUE' => 'PRICE.PRICE',
            ])
            ->setFilter(['=IBLOCK_ID' => [16, 19]])
            //->setLimit(1000)
            ->setOrder(['ID' => 'ASC']);
            //->setSelect(['*'])

        //print "<pre>" . $dbQuery->getQuery() . "</pre>";
        $dbItems = $dbQuery->exec();
        while ($arItem = $dbItems->fetch()) {
            //printArray($arItem);
            $this->catalog_products[] = $arItem;
        }
    }

    
    /**
     * Обнуляет остатки во всём каталоге
     */
    protected function resetCountInCatalog() {
        $this->toLog("Обнуляем остатков у всех товаров в каталоге");
        if (empty($this->catalog_products)) return;

        $ids = [];
        foreach($this->catalog_products as $item)
            $ids[] = $item["ID"];

        if (!empty($ids))
            $dbResult = \Bitrix\Catalog\ProductTable::updateMulti($ids, ["QUANTITY" => 0]);
    }

    
    /**
     * Ищет связь между элементами из БД и реальными товарами
     */
    protected function findAnalogItems() {
        $this->toLog("Ищем связь между элементами из БД и реальными товарами");
        if (empty($this->catalog_products)) return;

        foreach($this->catalog_products as $product) {
            $type_id = ($product["IBLOCK_ID"] == 16) ? 1 : 2;
            $marka = $product["MARKA"];
            $model = $product["MODEL_NAME"];
            $size = $product["SIZE"];
            //print "$type_id - $marka - $model - $size<br>";
            if (isset($this->item_tree[$type_id][$marka][$model][$size])) {
                $item = $this->item_tree[$type_id][$marka][$model][$size];
                $item->bitrix_catalog_id = $product["ID"];
                $item->bitrix_price_id = $product["PRICE_ID"];
            }
        }
    }


    /**
     * Обновляет цену и количество у товара
     */
    protected function updatePriceCount() {
        $total = 0;
        foreach($this->items as $item) {
            $result = $item->updatePriceCount();
            if ($result) {
                $item->is_processed = true;
                $total++;
            }
        }

        $this->toLog("Обновил остатки-цены у $total");
    }
    
    
    /**
    * Апдейтим статус у обработанных позиций
    */
    protected function storeProcessedItems() {
        $ids = [];
        foreach($this->items as $item)
            if ($item->is_processed)
                $ids[] = $item->import_id;
        
        if (empty($ids)) return; 
        
        global $conf;
        
        $db = new db();
        $res = $db->query(sprintf("UPDATE imp_product_compact SET `is_processed` = '1' WHERE `code` IN (%s)", implode(", ", $ids)));
        unset($db);
    }
}
?>