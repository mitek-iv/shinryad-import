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
    
    
    public function queryString(int $provider_id) {
        return sprintf("('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s')",
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
                        toJSON($this->params)
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
}


class dbImportItem4tochki extends dbImportItem {
    function __construct(stdClass $item) {
        $this->id = $item->code;
        $this->marka = $item->marka;
        $this->model = $item->model;
        $this->img = $item->img_big_my;
        $this->size = str_replace(",", ".", $item->name);
        $this->size = str_replace(" " . $item->model, "", $this->size);
        
        $this->getPriceCount($item->whpr->wh_price_rest); //Обработкацены и количества
    }
    
    
    protected function getPriceCount($stores) {
        if (is_array($stores)) {//несколько ценовых предложений
            $cur_price = PHP_INT_MAX;
            $cur_count = 0;
            $cur_store = null;
            foreach ($stores as $store) {//ищем минимальную цену предложения
                if ($store->rest < $this->min_count) continue;
                if ($store->price < $cur_price)
                    $cur_price = $store->price;
            }
            foreach ($stores as $store) {//ищем предложение с макс. количеством (для минимальной цены)
                if ($store->price == $cur_price)
                    if ($store->rest > $cur_count) {
                        $cur_count = $store->rest;
                        $cur_store = $store;
                    }
            }
            
            if (is_null($cur_store)) {//Не нашли предложения (кол-во < минимального)
                $this->count = 0;
                $this->price_opt = $store->price;
            } else {
                $this->count = $cur_count;
                $this->price_opt = $cur_price;
                $this->params["store_id"] = $cur_store->wrh;
            }
            
        } else {//одно ценовое предложение
            $this->price_opt = $stores->price;
            $this->count = ($stores->rest >= $this->min_count) ? $stores->rest : 0;
            if ($this->count > 0)
                $this->params["store_id"] = $stores->wrh;
        }
        
        $this->price = $this->roundPrice($this->price_coef * $this->price_opt);    
    }
}


class dbImportItem4tochkiTyre extends dbImportItem4tochki {
    protected $product_type = 1;
    protected $min_count = 4;
    protected $price_coef = 1.1;
    
    function __construct(stdClass $item) {
        parent::__construct($item);
        
        $this->size = str_replace("(шип.)", "шип", $this->size);
        $this->getParams(); //Получаем параметры
        $this->params["thorn"] = (int) $item->thorn;
        $this->params["season"] = $item->season;
        
        $this->full_title = sprintf("%s %s %s", $this->marka, $this->model, $this->size);
    }  
    

    protected function getParams() {
        //ставим пробел перед радиусом R17
        $pos_R = -1;
        if (preg_match('/[a-zA-Z]/', $this->size, $pos, PREG_OFFSET_CAPTURE) > 0) {
            $pos_R = $pos[0][1];
            $size = substr($this->size, 0, $pos_R) . " " . substr($this->size, 6);
            $this->size = $size;
        }
        $this->params = array();
        
        $prms = explode(" ", $this->size);
        $this->params["width"] = substr($prms[0], 0, strpos($prms[0], "/"));
        $this->params["height"] = substr($prms[0], strpos($prms[0], "/") + 1);
        if (preg_match('/[0-9]/', $prms[1], $pos, PREG_OFFSET_CAPTURE) > 0) {
            $this->params["radius"] = substr($prms[1], $pos[0][1]);
        }
        if (preg_match('/[a-zA-Z]/', $prms[2], $pos, PREG_OFFSET_CAPTURE) > 0) {
            $this->params["loading"] = substr($prms[2], 0, $pos[0][1]);
            $this->params["speed_index"] = substr($prms[2], $pos[0][1]);
        }    
    }
}


class dbImportItem4tochkiDisc extends dbImportItem4tochki {
    protected $product_type = 2;
    protected $min_count = 4;
    protected $price_coef = 1.1;
    
    function __construct(stdClass $item) {
        parent::__construct($item);
        
        $this->size = str_replace("х", "x", $this->size);
        $this->getParams(); //Получаем параметры
        $this->params["color"] = $item->color;
        $this->params["type"] = $item->type; //0 => "Литой", 1 => "Штампованный", 2 => "Кованный")
        $this->full_title = sprintf("%s %s %s", $this->marka, $this->model, $this->size);
    }
    
    
    protected function getParams() {
        $prms = explode(" ", $this->size);
        $sizes = explode("/", $prms[0]);
        //printArray($sizes);
        $this->params["width"] = substr($sizes[0], 0, strpos($sizes[0], "x"));
        $this->params["diameter"] = substr($sizes[0], strpos($sizes[0], "x") + 1);
        $this->params["bolts_count"] = substr($sizes[1], 0, strpos($sizes[1], "x"));
        $this->params["bolts_spacing"] = substr($sizes[1], strpos($sizes[1], "x") + 1);
        $this->params["et"] = substr($prms[1], strpos($prms[1], "ET") + 2);
        $this->params["dia"] = substr($prms[2], 1);
        if ($a = strpos($this->size, "("))
            $this->params["mount"] = substr($this->size, $a + 1, -1);
    }
}
?>