<?php
class dbImportItem extends commonClass { //Элемент (товар), полученный при конвертации данных из источника (выгрузки поставщика)
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
        if ($this->count <= 0) return null;
        if (htmlspecialchars($this->model, ENT_QUOTES) == "") return null;
            
        return sprintf("('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s')",
                        $provider_id,
                        $this->product_type,
                        $this->id,
                        trim(htmlspecialchars($this->marka, ENT_QUOTES)),
                        trim(htmlspecialchars($this->model, ENT_QUOTES)),
                        trim(htmlspecialchars($this->size, ENT_QUOTES)),
                        trim(htmlspecialchars($this->full_title, ENT_QUOTES)),
                        $this->price_opt,
                        $this->price,
                        $this->count,
                        toJSON($this->params),
                        trim(htmlspecialchars($this->provider_title, ENT_QUOTES)),
                        $this->img
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
    
    
    /**
    *
    * Первое слово модели приводим к виду Xxxxxxxx
    */
    protected function normalizeModel($marka, $model) {
        if ($marka == "Continental")
            return $model;
        
        $mdl = explode(" ", $model);
        if (strpos($mdl[0], "-") !== false) {
            $mdl_parts = explode("-", $mdl[0]);  
            foreach($mdl_parts as &$part) {
                $part = mb_convert_case($part, MB_CASE_TITLE);
            }
            $mdl[0] = implode("-", $mdl_parts);
        } else {
            $mdl[0] = mb_convert_case($mdl[0], MB_CASE_TITLE);
        }
        
        return implode(" ", $mdl);
    }
}
?>