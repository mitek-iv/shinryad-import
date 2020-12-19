<?php
namespace Bitrix\Iblock;
use Bitrix\Main\Entity;

class IblockElementPropertyTable extends Entity\DataManager {

    public static function getTableName(){
        return 'b_iblock_element_property';
    }
    public static function getMap(){
        return
            array(
                new Entity\IntegerField(
                    'IBLOCK_ELEMENT_ID',
                    array(
                        'primary' => true
                    )
                ),
		new Entity\IntegerField(
                    'IBLOCK_PROPERTY_ID',
                    array(
                        'required' => true
                    )
                ),
                new Entity\StringField(
                    'VALUE',
                    array(
                        'required' => true
                    )
                )
            );
    }
}
?>