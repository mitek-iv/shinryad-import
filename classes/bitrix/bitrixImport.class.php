<?php
class bitrixImport extends commonClass {
    protected $step;
    protected $items_per_step; //количество позиций, обрабатываемых за один шаг
    protected $total_step_count = 0;
    protected $items = array(); //of bitrixImportProduct
    protected $item_tree = array(); //items в виде дерева
    protected $catalog_products = array(); //Товары, полученные из каталога битрикс
    protected $bitrix_catalog_sections; //bitrixCatalogSectionList
    protected $iblock_ids = array(16, 19);
    
    
    public function __construct(int $step, int $items_per_step = 2000) {
        $this->step = $step;
        $this->items_per_step = $items_per_step;
        $this->bitrix_catalog_sections = new bitrixCatalogSectionList($this->iblock_ids);
        //$this->bitrix_catalog_sections->print();
        //die();
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
        
        $this->total_step_count = ceil($rc / $this->items_per_step);
        $this->toLog("Всего позиций: $rc; Требуется шагов: $this->total_step_count");

        return $this->total_step_count;
    }
    
    
    /**
    * Предварительно обнуляет остатки
    * Пробегается по товарам из каталога битрикс и пытается найти аналогичный товар из $items
    * Если находит, то ставит у $item->bitrix_catalog_id;
    * Обновляет остатки у $items, у которых был найден аналог из каталога Битрикс, и ставит is_processed = true   
    * Все прочие позиции, у которых аналог не найден, будем считать новыми. Их добавляем
    */    
    public function process() {
        $this->toLog("Обработка товаров");
        $this->getFromDB();
        $this->getProductsFromCatalog();
        if ($this->step == 0)
            $this->resetCountInCatalog();

        $this->findAnalogItems();
        $this->updatePriceCount();
        $this->insertNewProducts();
        $this->storeProcessedItems();

        if ($this->step == $this->total_step_count - 1)
            $this->updateActivity();
    }
    
    
    /**
    * Пробегается по $items. Если bitrix_catalog_id не установлен,
    * Значит считаем, что товар - новый. Добавляем его в каталог Битрикс
    */
    protected function insertNewProducts() {
        $this->toLog("Добавление новых товаров");
        
        $total = 0;
        $total_not_processed = 0;
        foreach($this->items as $item) {
            if (empty($item->bitrix_catalog_id)) {
                $result = $item->insert();
                if ($result) {
                    $item->is_processed = true;
                    $total++;
                } else {
                    $total_not_processed++;
                }
            }
        }

        $this->toLog("Добавил товаров: $total, Не добавил: $total_not_processed");
    }


    /**
     * Чистит инфоблок. За 1 раз удаляет одну категорию
     */
    public static function clearIB($iblock_id) {
        global $DB;

        $dbQuery = \Bitrix\Iblock\SectionTable::query()
            //Подтягиваем родительскую родительской категорию
            ->registerRuntimeField('PARENT', [
                    'data_type' => '\Bitrix\Iblock\SectionTable',
                    'reference' => ['=this.IBLOCK_SECTION_ID' => 'ref.ID'],
                    'join_type' => "LEFT"
                ]
            )
            //Вычисляемое поле
            ->registerRuntimeField("SECTION_NAME", [
                    "data_type" => "string",
                    "expression" => ["TRIM(SUBSTRING(%s, CHAR_LENGTH(%s) + 1))", "NAME", "PARENT.NAME"], //["TRIM(REPLACE(%s, %s, ' '))", "NAME", "PARENT.NAME"],
                    'join_type' => "LEFT"
                ]
            )
            ->setSelect([
                'ID',
                'IBLOCK_ID',
                'IBLOCK_SECTION_ID',
                'NAME',
                'SECTION_NAME',
                'PARENT_ID' => 'PARENT.ID',
                'PARENT_NAME' => 'PARENT.NAME'
            ])
            ->setFilter(['=IBLOCK_ID' => array($iblock_id), '=IBLOCK_SECTION_ID' => null])
            ->setOrder(['IBLOCK_ID' => 'ASC', 'IBLOCK_SECTION_ID' => 'ASC', 'NAME' => 'ASC']);


        $dbItems = $dbQuery->exec();
        if ($dbItems->getSelectedRowsCount() <= 0) return false;

        $i = 0;
        while ($arItem = $dbItems->fetch()) {
            toLog($arItem["ID"]);
            $DB->StartTransaction();
            if(!CIBlockSection::Delete($arItem["ID"])) {
                $strWarning .= 'Error.';
                $DB->Rollback();
            } else {
                $DB->Commit();
            }

            if ($i >= 0) break;
            $i++;
        }

        return true;
    }


    /**
     * Получает товары с остатками по всем поставщикам, уже свёрнутые (сгруппированные) по размеру
     */
    protected function getFromDB() {
        $this->toLog("Получение сжатого прайс-листа из БД");
        global $conf;

        $limit_from = (($this->step) * $this->items_per_step);
        $this->toLog("limit_from: $limit_from");
        $db = new db();

        //WHERE is_processed = 0
        $res = $db->query(sprintf("
            SELECT * 
            FROM imp_product_compact 
            WHERE 1
            ORDER BY type_id, marka, model, size
            LIMIT %d, %d
        ", $limit_from, $this->items_per_step));

        //$products = [];
        //$is_processed = [];
        if(!empty($res))
            foreach($res as $item) {
                if ($item["type_id"] == 1)
                    $product = new bitrixImportItemTyre($item);
                else
                    $product = new bitrixImportItemDisc($item);

                $product->bitrix_catalog_sections = $this->bitrix_catalog_sections;
                $this->items[] = $product;


                $type_id = $item["type_id"];
                $marka = mb_strtoupper($item["marka"]);
                $model = mb_strtoupper($item["model"]);
                $size = mb_strtoupper($item["size"]);

                $this->item_tree[$type_id][$marka][$model][$size] = $product;
            }

        $this->toLog("Загружено " . count($this->items));
        //printArray($this->item_tree);
    }


    /**
     * Получает список товаров из каталога
     * Если родительская категория неактивна, то такой элемент в выборке появляется, но в обработке не участвует
     */
    public function getProductsFromCatalog() {
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
                    "expression" => ["TRIM(SUBSTRING(%s, CHAR_LENGTH(%s) + 1))", "NAME", "MODEL.NAME"], //["TRIM(REPLACE(%s, %s, ' '))", "NAME", "MODEL.NAME"],
                    'join_type' => "LEFT"
                ]
            )
            //Вычисляемое поле
            ->registerRuntimeField("MODEL_NAME", [
                    "data_type" => "string",
                    "expression" => ["TRIM(SUBSTRING(%s, CHAR_LENGTH(%s) + 1))", "MODEL.NAME", "PROIZV.NAME"], //["TRIM(REPLACE(%s, %s, ' '))", "MODEL.NAME", "PROIZV.NAME"],
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
                'PROCESS' => 'MODEL.ACTIVE'
            ])
            ->setFilter(['=IBLOCK_ID' => $this->iblock_ids])
            //->setLimit(1000) //!!!
            ->setOrder(['ID' => 'ASC']);
            //->setSelect(['*'])

        //print "<pre>" . $dbQuery->getQuery() . "</pre>";
        $dbItems = $dbQuery->exec();

        while ($arItem = $dbItems->fetch()) {
            $arItem["PROCESS"] = ($arItem["PROCESS"] == 'Y');
            // printArray($arItem); //!!!
            $this->catalog_products[] = $arItem;
        }
    }

    
    /**
     * Обнуляет остатки во всём каталоге. Товары в неактивных секциях не обрабатываем.
     */
    protected function resetCountInCatalog() {
        $this->toLog("Обнуляем остатков у всех товаров в каталоге");
        if (empty($this->catalog_products)) return;

        $ids = [];
        foreach($this->catalog_products as $item)
            if ($item["PROCESS"])
                $ids[] = $item["ID"];


        if (!empty($ids)) {
            $dbResult = Bitrix\Catalog\ProductTable::updateMulti($ids, ["QUANTITY" => 0]);
            $dbResult = Bitrix\Iblock\ElementTable::updateMulti($ids, ["ACTIVE" => 'N']);
        }
    }

    
    /**
     * Ищет связь между элементами из БД и реальными товарами
     */
    protected function findAnalogItems() {
        $this->toLog("Ищем связь между элементами из БД и реальными товарами");
        if (empty($this->catalog_products)) return;

        foreach($this->catalog_products as $product) {
            $type_id = bitrixImportItem::convertIBlockIdToProductTypeId($product["IBLOCK_ID"]);
            $marka = mb_strtoupper($product["MARKA"]);
            $model = mb_strtoupper($product["MODEL_NAME"]);
            $size = mb_strtoupper($product["SIZE"]);

            //print "$type_id - $marka - $model - $size - $product[ID]<br>";
            if (isset($this->item_tree[$type_id][$marka][$model][$size])) {
                $item = $this->item_tree[$type_id][$marka][$model][$size];
                $item->allow_processing = $product["PROCESS"];
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
            if (($item->allow_processing) && !(empty($item->bitrix_catalog_id))) {
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
        $not_processed_ids = [];
        foreach($this->items as $item) {
            if ($item->is_processed)
                $ids[] = $item->import_id;
            else
                $not_processed_ids[] = $item->import_id;
        }
        $this->toLog("Не обработано: " . count($not_processed_ids));
        if (empty($ids)) return;
        
        global $conf;
        $db = new db();
        $query = sprintf("UPDATE imp_product_compact SET `is_processed` = '1' WHERE `id` IN (%s)", implode(", ", $ids));
        //$this->toLog($query);

        $res = $db->query($query);
        unset($db);
    }


    /**
     * Выставляет активность у товаров, кол-во которых > 0, не обрабатываем товары в неактивных категориях
     */
    public function updateActivity() {
        $this->toLog("Обновление активности ненулевых позиций");

        $dbQuery = Bitrix\Iblock\ElementTable::query()
            ->registerRuntimeField("PRODUCT", [
                    'data_type' => '\Bitrix\Catalog\ProductTable',
                    'reference' => array('=this.ID' => 'ref.ID'),
                ]
            )
            ->registerRuntimeField('SECTION', [
                    'data_type' => '\Bitrix\Iblock\SectionTable',
                    'reference' => ['=this.IBLOCK_SECTION_ID' => 'ref.ID'],
                    'join_type' => "LEFT"
                ]
            )
            ->setSelect([
                'ID', 'NAME', 'ACTIVE', 'PRODUCT.QUANTITY'
            ])
            ->setFilter(['=IBLOCK_ID' => $this->iblock_ids,
                         '>PRODUCT.QUANTITY' => 0,
                         '=ACTIVE' => 'N',
                         '=SECTION.ACTIVE' => 'Y'
                        ])
            //->setOrder(['IBLOCK_ID' => 'ASC', 'IBLOCK_SECTION_ID' => 'ASC', 'NAME' => 'ASC']);
        ;

        //print "<pre>" . $dbQuery->getQuery() . "</pre>";
        $dbItems = $dbQuery->exec();

        $ids = [];
        while ($arItem = $dbItems->fetch()) {
            //printArray($arItem);
            $ids[] = $arItem["ID"];
        }

        if (!empty($ids))
            $dbResult = Bitrix\Iblock\ElementTable::updateMulti($ids, ["ACTIVE" => 'Y']);
    }
}
?>