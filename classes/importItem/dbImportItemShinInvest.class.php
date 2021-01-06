<?php
class dbImportItemShinInvest extends dbImportItem {
    function __construct(array $item) {
        $this->id = $item["code"];
        $this->marka = $item["producer"];
        $this->model = $item["model"];
        $this->normalizeMarkaModel();

        $this->getPriceCount($item); //Обработка цены и количества
        $this->getParams($item);
        $this->convertToSize($item);
        $this->getFullTitle();
        
        //$this->provider_title = $item->marka . " " . $item->name;
        //printArray($item);
        //printArray($this);
    }
    
    
    protected function getPriceCount($item) {
        $this->price_opt = $item["price"];
        $this->price = $this->roundPrice($this->price_opt * $this->price_coef);
        $this->count = ($item["quantity"] >= $this->min_count) ? $item["quantity"] : 0;
    }
    
    
    protected function compactVal($val) {
        if (is_array($val))
            return 0;
        elseif (strpos($val, ".") !== false)
            return rtrim(rtrim($val, '0'), '.');
        else
            return $val;
    }
}


class dbImportItemShinInvestTyre extends dbImportItemShinInvest {
    protected $product_type = 1;
    protected $min_count = 4;
    protected $price_coef = 1.1;
    

    protected function getParams(array $item) {
        $this->params["width"] = $this->compactVal($item["width"]);
        $this->params["height"] = $this->compactVal($item["height"]);
        $this->params["radius"] = $this->compactVal($item["diametr"]);
        $this->params["index_loading"] = $this->compactVal($item["load_index"]);
        $this->params["index_speed"] = $this->compactVal($item["speed_index"]);
        $this->params["thorn"] = $item["shipi"];
        $seasons = ["Летние" => "s", "Зимние" => "w", "Всесезонные" => "u"];
        
        $this->params["season"] = (empty($item["season"])) ? "u" : $seasons[$item["season"]];
        //$this->params["store_id"] = $item["stockName"];
    }
    

    protected function convertToSize() {
        $this->size = sprintf("%s/%s R%s %s%s", $this->params["width"], $this->params["height"], $this->params["radius"], $this->params["index_loading"], $this->params["index_speed"]);
        $this->size = str_replace("  ", " ", $this->size);
    }
    

    protected function normalizeMarkaModel() {
        parent::normalizeMarkaModel();

        switch($this->marka) {
            case "BF Goodrich": //BF Goodrich => BFGoodrich
                $this->marka = "BFGoodrich";
                break;
            case "Nokian": //Nokian H-8 => Nokian Hakkapeliitta 8
                $this->model = str_replace("H-", "Hakkapeliitta ", $this->model);
                break;
        }
    }
}


class dbImportItemShinInvestDisc extends dbImportItemShinInvest {
    protected $product_type = 2;
    protected $min_count = 4;
    protected $price_coef = 1.1;
    

    protected function getParams(array $item) {
        
        $this->params["width"] = str_replace(",", ".", $item["width"]);
        $this->params["diameter"] = str_replace(",", ".", $item["diametr"]);
        $this->params["bolts_count"] = $item["holes"];
        $this->params["bolts_spacing"] = str_replace(",", ".", $item["pcd"]);
        $this->params["et"] = str_replace(",", ".", $item["et"]);
        $this->params["dia"] = str_replace(",", ".", $item["dia"]);
        $this->params["color"] = $item["color"];
        $this->params["type"] = $item["type"]; //TODO
        //$this->params["store_id"] = $item["stockName"];
    }
    

    protected function convertToSize() {
        $this->size = sprintf("%sx%s %sx%s ET%s D%s %s", 
                              $this->params["width"],
                              $this->params["diameter"],
                              $this->params["bolts_count"],
                              $this->params["bolts_spacing"],
                              $this->params["et"],
                              $this->params["dia"],
                              $this->params["color"]
                             );

        $this->size = str_replace("  ", " ", $this->size);
    }
}
?>