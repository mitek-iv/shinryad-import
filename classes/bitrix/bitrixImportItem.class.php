<?php
class bitixImportItem extends dbImportItem {
    public $import_id; //ид-р строки в таблице импорта (imp_product_compact)
    public $bitrix_catalog_id; //ид-р товара в каталоге Битрикса
    public $bitrix_price_id; //ид-р цены в каталоге Битрикса
    public $is_processed = false;
    public $bitrix_catalog_sections; //bitrixCatalogSectionList

    protected $provider_id;
    protected $prop_price_old_id;
    protected $prop_price_min_id;
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
        $this->is_processed = ($source["is_processed"] == 1);

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
        $dbResult = \Bitrix\Catalog\ProductTable::update($this->bitrix_catalog_id, ["QUANTITY" => $this->count]);
        $dbResult = \Bitrix\Catalog\PriceTable::update($this->bitrix_price_id, ["PRICE" => $this->price]);

        //Апдейтим свойства Минимальная цена и Старая цена
        $PROP = array();
        $PROP[$this->prop_price_old_id] = $this->price_old;
        $PROP[$this->prop_price_min_id] = $this->price_min;
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
        
        $arLoadProductArray = Array(
            "ACTIVE_FROM" => date('d.m.Y H:i:s'),
            "IBLOCK_SECTION_ID" => $parent_section->id,
            "IBLOCK_ID" => $this->iblock_id,
            "NAME" => $this->full_title,
            "CODE" => self::bx_translit($this->full_title),
            "ACTIVE" => "Y",
            "PROPERTY_VALUES" => $this->getProperties(),
            //"DETAIL_PICTURE" => CFile::MakeFileArray($this->img_url)  // ссылка на детальную картинку
        ); 

        $el = new CIBlockElement;
        $this->bitrix_catalog_id = $el->Add($arLoadProductArray);
        unset($el);

        print sprintf("%s -> %d<br>", $this->full_title, $this->bitrix_catalog_id);

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
}


class bitixImportItemTyre extends bitixImportItem {
    protected $product_type = 1;
    protected $iblock_id = 16;
    protected $prop_price_old_id = 421;
    protected $prop_price_min_id = 447;

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
            121 => Array("VALUE" => $ship),
            120 => Array("VALUE" => $season),
            155 => "Шины легковые", //Тип автошины – Шины легковые
            422 => "Y", //Выгружать в Яндекс.Маркет
            130 => $this->model, //Модель автошины
            119 => $this->marka, //Производитель
            459 => $this->provider_id,
            420 => Array("VALUE" => 182), //Срок доставки 1-3 дня
            415 => Array("VALUE" => 141), //Мониторить
            $this->prop_price_old_id => $this->price_old,
            $this->prop_price_min_id => $this->price_min
        );

        return $result;
    }
}


class bitixImportItemDisc extends bitixImportItem {
    protected $product_type = 2;
    protected $iblock_id = 19;
    protected $prop_price_old_id = 454;
    protected $prop_price_min_id = 444;

    protected function getProperties() {
        //{"width":"8.5","diameter":"20","bolts_count":"10","bolts_spacing":"335","et":"163","dia":"281","color":"","type":"СТ"}
        return array();
    }
}
?>