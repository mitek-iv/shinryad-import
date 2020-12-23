<?php
class bitixImportItem extends dbImportItem {
    public $import_id; //ид-р строки в таблице импорта (imp_product_compact)
    public $bitrix_catalog_id; //ид-р товара в каталоге Битрикса
    public $bitrix_price_id; //ид-р цены в каталоге Битрикса
    public $is_processed = false;
    public $bitrix_catalog_sections; //bitrixCatalogSections

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


    public static function convertIBlockIdToProductTypeId($iblock_id) {
        $product_types = array(16 => 1, 19 => 2);

        return $product_types[$iblock_id];
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
    
    /**
    * Добавляет товар в каталог Битрикс
    */
    public function insert() {
        $first_level_section_id = $this->bitrix_catalog_sections->findOrInsert($this->marka, $this->iblock_id, null);
        $parent_section_id = $this->bitrix_catalog_sections->findOrInsert($this->model, $this->iblock_id, $first_level_section_id);
        
        print sprintf("%s - %d; %s - %d<br>", $this->marka, $first_level_section_id, $this->model, $parent_section_id);
        /*
        $this->toLog("Добавляю элемент " . $this->id);
        // Передача основных параметров
        $name_full = sprintf("%s %s", $this->proizvoditel, $this->name);


        $translit_params = Array(
            "max_len" => "200", // обрезает символьный код до 100 символов
            "change_case" => "L", // буквы преобразуются к нижнему регистру
            "replace_space" => "_", // меняем пробелы на нижнее подчеркивание
            "replace_other" => "_", // меняем левые символы на нижнее подчеркивание
            "delete_repeat_replace" => "true", // удаляем повторяющиеся нижние подчеркивания
            "use_google" => "false", // отключаем использование google
        ); 

        $code = CUtil::translit($name_full, "ru" , $translit_params);

        //$code = strtr($name_d, $converter);

        // Проверка на дубли Разделов
        $res_sect = CIBlockSection::GetList(array(), array('IBLOCK_ID' => $this->i_blockid, 'NAME' => $this->proizvoditel), false, array('ID','ACTIVE','NAME'));
        if ($res_sect -> SelectedRowsCount() > 0) {
            $res_sect_list = $res_sect->GetNext();
            $res_sect_list_id = $res_sect_list['ID'];
        } else {
            $bs = new CIBlockSection;
            $arFields = Array(
                "ACTIVE" => date('d.m.Y H:i:s'),
                "IBLOCK_SECTION_ID" => false,
                "ACTIVE" => "Y",
                "CODE" => CUtil::translit($this->proizvoditel, "ru" , $translit_params),
                "IBLOCK_ID" => $this->i_blockid,
                "NAME" => $this->proizvoditel
            );
            $res_sect_list = $bs->Add($arFields);
            $res_sect_list_id = $res_sect_list;
        }

        //Проверка на дубли дочерних элементов
        $child_cat_name = sprintf("%s %s", $this->proizvoditel, $this->categoryname);
        $res_child_sect = CIBlockSection::GetList(array(), array('IBLOCK_ID' => $this->i_blockid, 'SECTION_ID' => $res_sect_list_id, 'NAME' => $child_cat_name), false, array('ID','ACTIVE','NAME'));
        if ($res_child_sect -> SelectedRowsCount() > 0) {
            $res_sect_list = $res_child_sect->GetNext();
            $res_sect_list_id = $res_sect_list['ID'];
        } else {
            $bs_child = new CIBlockSection;
            $child_sections = Array(
                "ACTIVE" => date('d.m.Y H:i:s'),
                "IBLOCK_SECTION_ID" => $res_sect_list_id,
                "ACTIVE" => "Y",
                "IBLOCK_ID" => $this->i_blockid,
                "CODE" => CUtil::translit($this->categoryname, "ru" , $translit_params),
                "NAME" => $child_cat_name,
                "PREVIEW_PICTURE" => CFile::MakeFileArray($this->img_url),
                //"DETAIL_PICTURE" => CFile::MakeFileArray($this->img_url)
            );
            $res_sect_list = $bs_child->Add($child_sections);
            $res_sect_list_id = $res_sect_list;
        }

        $PROP = $this->getProps();

        $arLoadProductArray = Array( 
            "ACTIVE_FROM" => date('d.m.Y H:i:s'), // обязательно нужно указать дату начала активности элемента
            "IBLOCK_SECTION_ID" => $res_sect_list_id, // В корне или нет
            "IBLOCK_ID" => $this->i_blockid,              //  собственно сам id блока куда будем добавлять новый элемент
            "NAME" => $name_full,
            "CODE" => $code, 
            "ACTIVE" => "Y", // активен или  N не активен 
            "PROPERTY_VALUES" => $PROP,  // Добавим нашему элементу заданные свойства
            "DETAIL_PICTURE" => CFile::MakeFileArray($this->img_url)  // ссылка на детальную картинку
        ); 

        $el = new CIBlockElement;
        $this->catalog_id = $el->Add($arLoadProductArray);

        //$this->toLog($new_product_id);
        //file_put_contents($file, $msg, FILE_APPEND | LOCK_EX);

        //Добавляем цену 0. Нужно, чтобы потом сработала ф-я SetBasePrice
        $PRICE_TYPE_ID = 1;
        $arFields = Array(
            "PRODUCT_ID" => $this->catalog_id,
            "CATALOG_GROUP_ID" => $PRICE_TYPE_ID,
            "PRICE" => 0,
            "CURRENCY" => "RUB",
            "QUANTITY_FROM" => false,
            "QUANTITY_TO" => false
        );
        $price_id = CPrice::Add($arFields);
        unset($el);
        */
        
        return true;
    }
    
    
    /**
    * Транслитерация. Используется для автогенерации поля CODE
    */
    protected function bx_translit($title) {
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
}


class bitixImportItemTyre extends bitixImportItem {
    protected $product_type = 1;
    protected $iblock_id = 16;
    protected $prop_price_old_id = 421;
    protected $prop_price_min_id = 447;
}


class bitixImportItemDisc extends bitixImportItem {
    protected $product_type = 2;
    protected $iblock_id = 19;
    protected $prop_price_old_id = 454;
    protected $prop_price_min_id = 444;
}
?>