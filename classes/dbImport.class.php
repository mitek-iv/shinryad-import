<?php
abstract class dbImport extends commonClass {
    protected $provider_id; //Ид. поставщика
    protected $product_type;
    protected $items = array();
    abstract function getFromSource();
    abstract function storeToDB();
}
?>