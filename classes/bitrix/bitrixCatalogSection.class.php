<?php
class bitrixCatalogSection extends commonClass {
    public $id;
    public $name;
    public $parent_id;
    public $iblock_id;
    public $img;


    public function __construct(array $source) {
        $this->id = (isset($source["id"])) ? $source["id"] : null;
        $this->name = $source["name"];
        $this->parent_id = $source["parent_id"];
        $this->iblock_id = $source["iblock_id"];
        $this->img = (isset($source["img"])) ? $source["img"] : null;
    }


    public function isFirstLevel() {
        return is_null($this->parent_id);
    }


    public function insert() {
        $bs_child = new CIBlockSection;
        $child_sections = Array(
            //"ACTIVE" => date('d.m.Y H:i:s'),
            "IBLOCK_SECTION_ID" => (is_null($this->parent_id)) ? false : $this->parent_id,
            "ACTIVE" => "Y",
            "IBLOCK_ID" => $this->iblock_id,
            "CODE" => bitrixImportItem::bx_translit($this->name), //CUtil::translit($this->categoryname, "ru" , $translit_params),
            "NAME" => $this->name,
            "SORT" => "500",
            "PICTURE" => CFile::MakeFileArray($this->img),
            //"DETAIL_PICTURE" => CFile::MakeFileArray($this->img)
        );

        $this->id = $bs_child->Add($child_sections); //, false, false, true
        $this->toLog(sprintf("Добавил категорию %d/%s; ID = %d; img = %s", $this->parent_id, $this->name, $this->id, $this->img));

        return $this->id;
    }
}


class bitrixCatalogSectionList extends commonClass {
    protected $iblock_ids = array();
    protected $first_level_sections = array();
    protected $section_tree = array();


    public function __construct(array $iblock_ids) {
        $this->iblock_ids = $iblock_ids;
        //$this->get();
    }


    public function print() {
        print "<pre>";
        //print_r($this->first_level_sections);
	    print_r($this->section_tree);
        print "</pre>";
    }

    /**
     * Depricated
     */
    public function find2(bitrixCatalogSection $section) {
        if ($section->isFirstLevel()) {//1-ый уровень
            if (isset($this->first_level_sections[$section->iblock_id][$section->name]))
                return $this->first_level_sections[$section->iblock_id][$section->name];
            else {
                $this->toLog(sprintf("Не нашёл категорию 1-го уровня !%s!", $section->name));
                return null;
            }
        } else {//2-ой уровень
            if (isset($this->section_tree[$section->iblock_id][$section->parent_id][$section->name]))
                return $this->section_tree[$section->iblock_id][$section->parent_id][$section->name];
            else {
                $this->toLog(sprintf("Не нашёл категорию !%s! в родительской #%d", $section->name, $section->parent_id));
                return null;
            }
        }
    }


    public function find(bitrixCatalogSection $section) {
        if ($section->isFirstLevel()) {
            $filter = ['=IBLOCK_ID' => $this->iblock_ids, '=UPPER_NAME' => mb_strtoupper($section->name), '==IBLOCK_SECTION_ID' => null];
            $warning_not_found = sprintf("Не нашёл категорию 1-го уровня |%s|", $section->name);
        } else {
            $filter = ['=IBLOCK_ID' => $this->iblock_ids, '=UPPER_NAME' => mb_strtoupper($section->name), '=IBLOCK_SECTION_ID' => $section->parent_id];
            $warning_not_found = sprintf("Не нашёл категорию |%s| в родительской #%d", $section->name, $section->parent_id);
        }

        $dbQuery = \Bitrix\Iblock\SectionTable::query()
            ->registerRuntimeField("UPPER_NAME", [
                    "data_type" => "string",
                    "expression" => ["UPPER(%s)", "NAME"], //["TRIM(REPLACE(%s, %s, ' '))", "NAME", "PARENT.NAME"],
                ]
            )
            ->setSelect([
                'ID',
            ])
            ->setFilter($filter);

        $dbItems = $dbQuery->exec();

        //print "<pre>" . $dbQuery->getQuery() . "</pre>";

        if ($dbItems->getSelectedRowsCount() <= 0) {
            $this->toLog($warning_not_found);

            return null;
        }

        $arItem = $dbItems->fetch();
        $section->id = $arItem["ID"];

        return $section;
    }


    /**
     * Ищет в каталоге Битрикс секцию, в которую нужно добавить товар. Если не находит, то добавляет
     * $parent_section_id - id родительской секции. Если ищем на 1-ом уровне, то $parent_section_id = null
     */
    public function findOrInsert(array $sect) {
        $find_request_section = new bitrixCatalogSection($sect);

        $section = $this->find($find_request_section);

        if (is_null($section)) {//не нашёл
            $section = $find_request_section;
            $section->insert($section);

            if ($section->isFirstLevel()) {
                $this->first_level_sections[$section->iblock_id][$section->name] = $section;
            }  else {
                $this->section_tree[$section->iblock_id][$section->parent_id][$section->name] = $section;
            }
        }

        return $section;
    }

    /**
     * Depricated
     */
    protected function get() {
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
            ->setFilter(['=IBLOCK_ID' => $this->iblock_ids]) //, '!=IBLOCK_SECTION_ID' => null
            ->setOrder(['IBLOCK_ID' => 'ASC', 'IBLOCK_SECTION_ID' => 'ASC', 'NAME' => 'ASC']);

            //print "<pre>" . $dbQuery->getQuery() . "</pre>";
            $dbItems = $dbQuery->exec();
            $this->section_tree = [];
            while ($arItem = $dbItems->fetch()) {
                $section = new bitrixCatalogSection(
                    array(
                        "id" => $arItem["ID"],
                        "name" => (empty($arItem["PARENT_ID"])) ? $arItem["NAME"] : $arItem["SECTION_NAME"],
                        "parent_id" => $arItem["PARENT_ID"],
                        "iblock_id" => $arItem["IBLOCK_ID"],
                        "img" => null
                    )
                );

                //$name = $arItem["NAME"];

                if ($section->isFirstLevel())
                    $this->first_level_sections[$section->iblock_id][$section->name] = $section;
                else
                    $this->section_tree[$section->iblock_id][$section->parent_id][$section->name] = $section;
            }
    }
}
?>