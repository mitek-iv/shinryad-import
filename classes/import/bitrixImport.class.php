<?php
use Bitrix\Iblock\ElementTable;
//use Bitrix\Main\Entity;
//use Bitrix\Catalog\ProductTable;
//use Bitrix\Main\Diag;


class bitixImportProduct extends dbImportItem {
    public $bitrix_catalog_id; //ид-р товара в каталоге Битрикса
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
    protected $item_tree = array(); //items в виде дерева
    protected $catalog_products = array(); //Товары, полученные из каталога битрикс
    
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
                $this->items[] = $product;
                $this->item_tree[$item["type_id"]][$item["marka"]][$item["model"]][$item["size"]] = $product;
            }

        print("Загружено " . count($this->items) . "<br>");
        //printArray($this->item_tree);
    }
    
    
    /*
    * Пробегается по товарам из каталога битрикс и пытается найти аналогичный товар из $items
    * Если находит, то обновляет остатки и ставит флаг у товара в $items is_processed = true   
    * Если не находит, то ставит кол-во 0 и деактивирует
    */    
    public function updateExistingProducts() {
        //$dbResult = \Bitrix\Catalog\ProductTable::updateMulti([], ["QUANTITY" => 0]);
        //$dbResult = \Bitrix\Catalog\PriceTable::updateMulti([20048, 20049, 20050], ["PRICE" => 999]);

        $this->toLog("Обновление остатков у существующих товаров");
        $this->getProductsFromCatalog();
        $this->ResetCountInCatalog();
        $this->findAnalogItems();

        print count($this->catalog_products);


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
    /*
    * Пробегается по $items. Если is_processed = false
    * Значит считаем, что товар - новый. Добавляем его в каталог Битрикс
    */
    public function insertNewProducts() {
        $this->toLog("Добавление новых товаров");
    }


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
                    "expression" => ["TRIM(REPLACE(%s, %s, ' '))", "NAME", "MODEL.NAME"]
                ]
            )
            //Вычисляемое поле
            ->registerRuntimeField("MODEL_NAME", [
                    "data_type" => "string",
                    "expression" => ["TRIM(REPLACE(%s, %s, ' '))", "MODEL.NAME", "PROIZV.NAME"]
                ]
            )
//            ->registerRuntimeField("PROPERTY", [
//                    'data_type' => 'IblockElementPropertyTable',
//                    'reference' => array('=this.ID' => 'ref.IBLOCK_ELEMENT_ID', '=ref.IBLOCK_PROPERTY_ID' => array(116)), //,
//                ]
//            )
//            ->registerRuntimeField("PROPERTY1", [
//                    'data_type' => 'IblockElementPropertyTable',
//                    'reference' => array('=this.ID' => 'ref.IBLOCK_ELEMENT_ID', '=ref.IBLOCK_PROPERTY_ID' => array(117)), //,
//                ]
//            )
            ->registerRuntimeField("PRODUCT", [
                    'data_type' => '\Bitrix\Catalog\ProductTable',
                    'reference' => array('=this.ID' => 'ref.ID'),
                ]
            )
            ->registerRuntimeField("PRICE", [
                    'data_type' => '\Bitrix\Catalog\PriceTable',
                    'reference' => array('=this.ID' => 'ref.PRODUCT_ID'),
                ]
            )
            ->setSelect(['ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME', "MODEL_NAME", "MARKA" => 'PROIZV.NAME', 'SIZE', 'QUANTITY' => 'PRODUCT.QUANTITY', 'PRICE_ID' => 'PRICE.ID', 'PRICE_VALUE' => 'PRICE.PRICE']) //'WIDTH' => 'PROPERTY.VALUE', 'HEIGHT' => 'PROPERTY1.VALUE',
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

    /*
     * Обнуляет остатки во всём каталоге
     */
    protected function ResetCountInCatalog() {
        $this->toLog("Обнуляем остатков у всех товаров в каталоге");
        if (empty($this->catalog_products)) return;

        $ids = [];
        foreach($this->catalog_products as $item)
            $ids[] = $item["ID"];

        if (!empty($ids))
            $dbResult = \Bitrix\Catalog\ProductTable::updateMulti($ids, ["QUANTITY" => 0]);
    }


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
            }
        }

        $total = 0;
        foreach($this->items as $item) {
            if (!empty($item->bitrix_catalog_id)) {
                //printArray($item);
                $total++;
            }
        }

        print "Нашёл связь у $total<br>";
    }
}
?>