<?php
class dbImportItem4tochki extends dbImportItem {
    function __construct(stdClass $item) {
        $this->id = $item->code;
        $this->marka = $item->marka;
        $this->model = $this->normalizeModel($this->marka, $item->model);
        $this->img = $item->img_big_my;
        
        $this->getPriceCount($item->whpr->wh_price_rest); //Обработкацены и количества
        $this->provider_title = $item->marka . " " . $item->name;
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
    
    
    protected function convertToSize($item) {
        $this->size = str_replace(",", ".", $item->name);
        $this->size = str_replace(" " . $item->model, "", $this->size);
        $this->size = str_replace("х", "x", $this->size);
    }
}


class dbImportItem4tochkiTyre extends dbImportItem4tochki {
    protected $product_type = 1;
    protected $min_count = 4;
    protected $price_coef = 1.1;
    
    function __construct(stdClass $item) {
        parent::__construct($item);
        $this->convertToSize($item);
        
        $this->getParams(); //Получаем параметры
        $this->params["thorn"] = (int) $item->thorn;
        $this->params["season"] = $item->season;
        
        $this->getFullTitle();
        //printArray($item);
        //printArray($this);
    }  
    

    protected function getParams() {
        $pos_digit = -1;
        if (preg_match('/[0-9]/', $this->size, $pos, PREG_OFFSET_CAPTURE) > 0) {
            $pos_digit = $pos[0][1];
            //print "!!!" . $pos_digit;
            if ($pos_digit > 0)
                $this->size = substr($this->size, $pos_digit);
            
            //$this->size = $size;
        }
        //print "!!!" . $this->size . "<br>";
        //ставим пробел перед радиусом R17
        $pos_R = -1;
        if (preg_match('/[a-zA-Z]/', $this->size, $pos, PREG_OFFSET_CAPTURE) > 0) {
            $pos_R = $pos[0][1];
            $size = substr($this->size, 0, $pos_R) . " " . substr($this->size, $pos_R);
            $this->size = $size;
        }
        //print "!!!" . $this->size . "<br>";
        $this->params = array();
        
        $prms = explode(" ", $this->size);
        $this->params["width"] = substr($prms[0], 0, strpos($prms[0], "/"));
        $this->params["height"] = substr($prms[0], strpos($prms[0], "/") + 1);
        if (preg_match('/[0-9]/', $prms[1], $pos, PREG_OFFSET_CAPTURE) > 0) {
            $this->params["radius"] = substr($prms[1], $pos[0][1]);
        }
        if (preg_match('/[a-zA-Z]/', $prms[2], $pos, PREG_OFFSET_CAPTURE) > 0) {
            $this->params["index_loading"] = substr($prms[2], 0, $pos[0][1]);
            $this->params["index_speed"] = substr($prms[2], $pos[0][1]);
        }    
    }
    
    
     protected function convertToSize($item) {
         parent::convertToSize($item);
         
         $parts_to_delete = array("XL", "TL", "RBT", "TA", "FR", "SD", "M+S", "(шип.)");
         foreach($parts_to_delete as $part) {
            $this->size = str_replace(" " . $part, "", $this->size);    
         }
        
         $this->size = str_replace("x", "/", $this->size);
         $this->size = rtrim($this->size);
     }
}


class dbImportItem4tochkiDisc extends dbImportItem4tochki {
    protected $product_type = 2;
    protected $min_count = 4;
    protected $price_coef = 1.1;
    
    function __construct(stdClass $item) {
        parent::__construct($item);
        $this->convertToSize($item);
        
        $this->getParams(); //Получаем параметры
        
        $this->size = str_replace("/", " ", $this->size);
        $this->params["color"] = $item->color;
        $this->params["type"] = $item->type; //0 => "Литой", 1 => "Штампованный", 2 => "Кованный")
        $this->getFullTitle();
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
        $this->params["dia"] = substr($prms[2], 1); //CB67.1 проверить корректность
        if ($a = strpos($this->size, "("))
            $this->params["mount"] = substr($this->size, $a + 1, -1);
    }
}
?>