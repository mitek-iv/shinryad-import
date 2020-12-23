<?php
class bitrixCatalogSection {
    protected $iblock_ids = array();
    protected $first_level_sections = array();
    protected $section_tree = array();


    public function __construct(array $iblock_ids) {
        $this->iblock_ids = $iblock_ids;
        $this->get();
    }


    public function print() {
        print "<pre>";
        //print_r($this->first_level_sections);
	    print_r($this->section_tree);
        print "</pre>";
    }


    public function find(string $section_name, int $iblock_id, $parent_section_id = null) {
        $section_name = mb_strtolower($section_name);
        if (is_null($parent_section_id)) {//1-ый уровень
            if (isset($this->first_level_sections[$iblock_id][$section_name]))
                return $this->first_level_sections[$iblock_id][$section_name];
            else
                return -1;
        } else {//2-ой уровень
            //print "$iblock_id --- $parent_section_id --- $section_name<br>";
            if (isset($this->section_tree[$iblock_id][$parent_section_id][$section_name]))
                return $this->section_tree[$iblock_id][$parent_section_id][$section_name];
            else
                return -1;
        }
    }


    /**
     * Ищет в каталоге Битрикс секцию, в которую нужно добавить товар. Если не находит, то добавляет
     * $parent_section_id - id родительской секции. Если ищем на 1-ом уровне, то $parent_section_id = null
     */
    public function findOrInsert($name, $iblock_id, $parent_section_id = null) {
        $section_id = $this->find($name, $iblock_id, $parent_section_id);

        return $section_id;
    }


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
                    "expression" => ["TRIM(REPLACE(%s, %s, ' '))", "NAME", "PARENT.NAME"],
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
                $iblock_id = $arItem["IBLOCK_ID"];
                $id = $arItem["ID"];
                $section_name = mb_strtolower($arItem["SECTION_NAME"]);
                $name = mb_strtolower($arItem["NAME"]);
                $parent_id = $arItem["PARENT_ID"];
                $parent_name = $arItem["PARENT_NAME"];

                if (empty($parent_id))
                    $this->first_level_sections[$iblock_id][$name] = $id;
                else
                    $this->section_tree[$iblock_id][$parent_id][$section_name] = $id;
            }
    }
}
?>