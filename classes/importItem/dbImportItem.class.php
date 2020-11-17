<?php
class dbImportItem { //Элемент (товар), полученный при конвертации данных из источника (выгрузки поставщика)
    protected $product_type; //Тип продукта 1 - шины, 2 - диски
    public $id;
    public $marka;
    public $model;
    public $size; //типоразмер
    public $full_title;
    public $price;
    public $price_opt;
    public $count;
    public $img;
    public $params = array();
    
    public $provider_title; //исходное название, которое фигурирует в выгрузке
    
    
    public function queryString(int $provider_id) {
        return sprintf("('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s')",
                        $provider_id,
                        $this->product_type,
                        $this->id,
                        htmlspecialchars($this->marka, ENT_QUOTES),
                        htmlspecialchars($this->model, ENT_QUOTES),
                        htmlspecialchars($this->size, ENT_QUOTES),
                        htmlspecialchars($this->full_title, ENT_QUOTES),
                        $this->price_opt,
                        $this->price,
                        $this->count,
                        toJSON($this->params),
                        htmlspecialchars($this->provider_title, ENT_QUOTES)
                      );
    }
    
    protected function roundPrice($value) {
        $base = 5;
        $ost = $value%$base; //вычисляем остаток от деления
        $chast = floor($value/$base); //находим количество целых округлителей в аргументе
        
        $res = $chast * $base;
        
        if ($res >= $value) $res -= $base;
        
        return $res;
    }
    
    protected function getFullTitle() {
        $this->full_title = sprintf("%s %s %s", $this->marka, $this->model, $this->size);
    }
}
?>