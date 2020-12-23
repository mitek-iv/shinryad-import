<?php
class bitixImport extends commonClass {
    protected $items_per_step; //количество позиций, обрабатываемых за один шаг
    protected $items = array(); //of bitixImportProduct
    protected $item_tree = array(); //items в виде дерева
    protected $catalog_products = array(); //Товары, полученные из каталога битрикс
    protected $bitrix_catalog_sections; //bitrixCatalogSections
    protected $iblock_ids = array(16, 19);
    
    
    public function __construct($items_per_step = 2000) {
        $this->items_per_step = $items_per_step;
        $this->bitrix_catalog_sections = new bitrixCatalogSection($this->iblock_ids);
        $this->bitrix_catalog_sections->print();
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
                    $product = new bitixImportItemTyre($item);
                else
                    $product = new bitixImportItemDisc($item);

                $product->bitrix_catalog_sections = $this->bitrix_catalog_sections;
                $this->items[] = $product;
                $this->item_tree[$item["type_id"]][$item["marka"]][$item["model"]][$item["size"]] = $product;
            }

        $this->toLog("Загружено " . count($this->items));
        //printArray($this->item_tree);
    }
    
    
    /**
    * Предварительно обнуляет остатки
    * Пробегается по товарам из каталога битрикс и пытается найти аналогичный товар из $items
    * Если находит, то ставит у $item->bitrix_catalog_id;
    * Обновляет остатки у $items, у которых был найден аналог из каталога Битрикс, и ставит is_processed = true   
    * Все прочие позиции, у которых аналог не найден, будем считать новыми. Их добавляем
    */    
    public function process() {
        $this->toLog("Обновление остатков у существующих товаров");
        $this->getProductsFromCatalog();
        $this->resetCountInCatalog();
        $this->findAnalogItems();
        $this->updatePriceCount();
        $this->insertNewProducts();
        $this->storeProcessedItems();
    }
    
    
    /**
    * Пробегается по $items. Если is_processed = false
    * Значит считаем, что товар - новый. Добавляем его в каталог Битрикс
    */
    public function insertNewProducts() {
        $this->toLog("Добавление новых товаров");
        
        $total = 0;
        foreach($this->items as $item) {
            if (empty($item->bitrix_catalog_id)) {
                $result = $item->insert();
                if ($result) {
                    $item->is_processed = true;
                    $total++;
                }
            }
        }

        $this->toLog("Добавил товаров: $total");
    }
    

    /**
     * Получает список товаров из каталога
     * Значит считаем, что товар - новый. Добавляем его в каталог Битрикс
     */
    protected function getProductsFromCatalog() {
        $dbQuery = Bitrix\Iblock\ElementTable::query()
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
            ->setFilter(['=IBLOCK_ID' => $this->iblock_ids])
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
            $type_id = bitixImportItem::convertIBlockIdToProductTypeId($product["IBLOCK_ID"]);
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
            if (!(empty($item->bitrix_catalog_id))) {
                $result = $item->updatePriceCount();
                if ($result) {
                    $item->is_processed = true;
                    $total++;
                }
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