<?php
class bitrixImportItem extends dbImportItem {
    public $import_id; //ид-р строки в таблице импорта (imp_product_compact)
    public $bitrix_catalog_id; //ид-р товара в каталоге Битрикса
    public $bitrix_price_id; //ид-р цены в каталоге Битрикса
    public $is_processed = false; //флаг того, что соответствующий элемент был обновлён/добавлен в каталоге Битрикс
    public $allow_processing = true; //флаг того, что необходимо обрабатывать текущий элемент
    public $bitrix_catalog_sections; //bitrixCatalogSectionList

    protected $provider_id;
    protected $prop_price_old_id; //Ид. свойства "Старая цена"
    protected $prop_price_min_id; //Ид. свойства "Мин. цена"
    protected $prop_provider_id; //Ид. свойства "Ид. склада"
    protected $prop_delivery_id; //Ид. свойства "Срок поставки"
    protected $price_min;
    protected $price_old;
    
    public function __construct(array $source) {
        $this->import_id = $source["id"];
        $this->id = $source["code"];
        //$this->product_type = $source["type_id"];
        $this->provider_id = $source["provider_id"];
        $this->marka = $source["marka"];
        $this->model = $source["model"];
        $this->size = $source["size"];
        $this->full_title = $source["full_title"];
        $this->price = $source["price"];
        $this->price_opt = $source["price_opt"];
        $this->count = $source["count"];
        $this->img = $source["img"];
        $this->params = json_decode($source["params"], true);
        //$this->is_processed = ($source["is_processed"] == 1);

        $this->price_old = $this->roundPrice($this->price * 1.1);
        $this->price_min = $this->price_opt + 100;
    }


    /**
     * Возвращает тип товара (product_type) по Id инфоблока
     */
    public static function convertIBlockIdToProductTypeId($iblock_id) {
        $product_types = array(16 => 1, 19 => 2);

        return $product_types[$iblock_id];
    }


    /**
     * Транслитерация. Используется для автогенерации поля CODE
     */
    public static function bx_translit($title) {
        $translit_params = Array(
            "max_len" => "200", // обрезает символьный код до 100 символов
            "change_case" => "L", // буквы преобразуются к нижнему регистру
            "replace_space" => "_", // меняем пробелы на нижнее подчеркивание
            "replace_other" => "_", // меняем левые символы на нижнее подчеркивание
            "delete_repeat_replace" => "true", // удаляем повторяющиеся нижние подчеркивания
            "use_google" => "false", // отключаем использование google
        );

        $code = CUtil::translit($title, "ru" , $translit_params);

        return $code;
    }


    /**
    * Обновление остатков у товара
    */
    public function updatePriceCount() {
        if (empty($this->bitrix_catalog_id))
            return false;

        //printArray($this);
        try {
            $dbResult = \Bitrix\Catalog\ProductTable::update($this->bitrix_catalog_id, ["QUANTITY" => $this->count]);
            $dbResult = \Bitrix\Catalog\PriceTable::update($this->bitrix_price_id, ["PRICE" => $this->price]);
        } catch (Exception $e) {
            $this->toLog("!!!Не смог обновить остатки bitrix_catalog_id = " . $this->bitrix_catalog_id);

            return false;
        }

        //Апдейтим свойства Минимальная цена, Старая цена, Поставщик, Срок поставки
        $PROP = array();
        $PROP[$this->prop_price_old_id] = $this->price_old;
        $PROP[$this->prop_price_min_id] = $this->price_min;
        $PROP[$this->prop_provider_id] = $this->provider_id;
        $PROP[$this->prop_delivery_id] = $this->getDeliveryId();

        CIBlockElement::SetPropertyValuesEx($this->bitrix_catalog_id, false, $PROP);

        return true;
    }
    
    /**
    * Добавляет товар в каталог Битрикс
    */
    public function insert() {
        $first_level_section = $this->bitrix_catalog_sections->findOrInsert(
            array(
                "name" => $this->marka,
                "iblock_id" => $this->iblock_id,
                "parent_id" => null
            )
        );

        $parent_section = $this->bitrix_catalog_sections->findOrInsert(
            array(
                "name" => $this->marka . " " . $this->model,
                "iblock_id" => $this->iblock_id,
                "parent_id" => $first_level_section->id,
                "img" => $this->img
            )
        );


        if (!($parent_section->is_active)) {
            $this->toLog(sprintf(sprintf("НЕ добавил %s. Неактивная категория %d", $this->full_title, $parent_section->id)));
            return false;
        }


        $arLoadProductArray = array(
            "ACTIVE_FROM" => date('d.m.Y H:i:s'),
            "IBLOCK_SECTION_ID" => $parent_section->id,
            "IBLOCK_ID" => $this->iblock_id,
            "NAME" => $this->full_title,
            "CODE" => self::bx_translit($this->full_title),
            "ACTIVE" => "Y",
            "PROPERTY_VALUES" => $this->getProperties(),
        );

        if ($this->product_type == 2) { //Для дисков будем добавлять детальную картинку
            //$this->toLog("!добавляю картинку " . $this->img);
            $arLoadProductArray["DETAIL_PICTURE"] = CFile::MakeFileArray($this->img);
        }

        $el = new CIBlockElement;
        $this->bitrix_catalog_id = $el->Add($arLoadProductArray);
        unset($el);

        $this->toLog(sprintf("Добавил %s -> %d", $this->full_title, $this->bitrix_catalog_id));

        $this->addPrice();
        $this->addCount();

        return true;
    }


    protected function addPrice() {
        $arFields = Array(
            "PRODUCT_ID" => $this->bitrix_catalog_id,
            "CATALOG_GROUP_ID" => 1,
            "PRICE" => $this->price,
            "CURRENCY" => "RUB",
            "QUANTITY_FROM" => false,
            "QUANTITY_TO" => false
        );

        CPrice::Add($arFields);
    }


    protected function addCount() {
        $arFields = array(
            "ID" => $this->bitrix_catalog_id,
            "QUANTITY" => $this->count
        );

        CCatalogProduct::Add($arFields);
    }


    /**
     * В зависимости от постащика и типа товара возвращает Ид. значения поля "Срок поставки" в битриксе
     */
    protected function getDeliveryId() {
        if (isset($this->delivery_ids[$this->provider_id]))
            $result = $this->delivery_ids[$this->provider_id];
        else
            $result = $this->default_delivery_id;

        return $result;
    }
}


class bitrixImportItemTyre extends bitrixImportItem {
    protected $product_type = 1;
    protected $iblock_id = 16;
    protected $prop_price_old_id = 421;
    protected $prop_price_min_id = 447;
    protected $prop_provider_id = 459; //Ид. свойства "Ид. склада"
    protected $prop_delivery_id = 420; //Ид. свойства "Срок поставки"

    protected $default_delivery_id = 182;
    protected $delivery_ids = array(//142 - 1-2 дня, 143 - 2-4 дня, 144 - в наличии, 182 - 1-3 дня
        "1" => 182,
        "2" => 143,
    );

    protected function getProperties() {
        //{"width":"235","height":"65","radius":"17","index_loading":"108","index_speed":"V","thorn":0,"season":"u"}

        //сезонность
        switch($this->params["season"]) {
            case "w": $season = 56; break;
            case "s": $season = 55; break;
            default: $season = 54; break;
        }

        //шипы
        if ($this->params["season"] == "s")
            $this->ship = null;
        else {
            switch($this->params["thorn"]) {
                case 1: $ship = 58; break;
                case 0: $ship = 57; break;
                default: $ship = null; break;
            }
        }
        $result = array(
            127 => $this->id,
            116 => $this->params["width"],
            118 => $this->params["radius"],
            117 => $this->params["height"],
            123 => $this->params["index_loading"],
            124 => $this->params["index_speed"],
            121 => array("VALUE" => $ship),
            120 => array("VALUE" => $season),
            155 => "Шины легковые", //Тип автошины – Шины легковые
            422 => "Y", //Выгружать в Яндекс.Маркет
            130 => $this->model, //Модель автошины
            119 => $this->marka, //Производитель
            459 => $this->provider_id,
            420 => $this->getDeliveryId(), //Array("VALUE" => 182), //Срок доставки 1-3 дня
            415 => array("VALUE" => 141), //Мониторить
            $this->prop_price_old_id => $this->price_old,
            $this->prop_price_min_id => $this->price_min
        );

        return $result;
    }
}


class bitrixImportItemDisc extends bitrixImportItem {
    protected $product_type = 2;
    protected $iblock_id = 19;
    protected $prop_price_old_id = 454;
    protected $prop_price_min_id = 444;
    protected $prop_provider_id = 458; //Ид. свойства "Ид. склада"
    protected $prop_delivery_id = 432; //Ид. свойства "Срок поставки"

    protected $default_delivery_id = 183;
    protected $delivery_ids = array(//152 - 1-2 дня, 153 - 1-4 дня, 154 - в наличии, 183 - 1-3 дня
        "1" => 152,
        "2" => 153,
        "3" => 153,
    );

    protected function getProperties() {
        //{"width":"8.5","diameter":"20","bolts_count":"10","bolts_spacing":"335","et":"163","dia":"281","color":"","type":"СТ"}
        $result = array(
            243 => $this->id,
            229 => $this->params["width"],
            230 => $this->params["diameter"],
            231 => $this->params["bolts_count"],
            232 => $this->params["bolts_spacing"],
            234 => $this->params["dia"],
            267 => $this->params["color"],
            233 => $this->params["et"],
            458 => $this->provider_id,
            432 => $this->getDeliveryId(), //array("VALUE" => 183), //Срок доставки 1-3 дня
            253 => $this->params["type"], //материал
            266 => $this->marka, //Производитель
            256 => $this->model, //Модель диска
            424 => "Y", //Выгружать в Яндекс.Маркет
            423 => array("VALUE" => 145), //Мониторит
            $this->prop_price_old_id => $this->price_old,
            $this->prop_price_min_id => $this->price_min
        );

        return $result;
    }
}
?>