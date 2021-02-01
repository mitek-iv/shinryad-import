<?php
class dbImportItemKolesaDarom extends dbImportItem {
    function __construct(array $item) {
        $this->id = $item["id"];
        $this->marka = $item["maker"];
        $this->model = $item["categoryname"];
        $this->normalizeMarkaModel();

        $this->img = $item["img"];
        $this->provider_title = $item["name"];
        
        $this->getPriceCount($item);    
        $this->getParams($item);
        $this->convertToSize($item);
        $this->getFullTitle();
        
        //printArray($item);
        //printArray($this);
    }
    
    
    protected function getPriceCount(array $item) {
        $this->price_opt = $item["priceOpt"];
        $this->price = $this->roundPrice($this->price_opt * $this->price_coef);
        $this->count = ($item["countAll"] >= $this->min_count) ? $item["countAll"] : 0;
    }
}


class dbImportItemKolesaDaromTyre extends dbImportItemKolesaDarom {
    protected $product_type = 1;
    protected $min_count = 4;
    protected $price_coef = 1.1;
    
    
    protected function convertToSize(array $item) {
        $name = $item["name"];
        $name = str_replace($item["categoryname"], "", $name);
        $name = str_replace($item["maker"], "", $name);
        
        $parts_to_delete = array("XL", "TL", "RBT", "TA", "FR", "SD", "M+S", "шип");
        foreach($parts_to_delete as $part) {
            $name = str_replace(" " . $part, "", $name);    
        }
        
        $name_part = explode(" ", trim($name));
        $r = $name_part[0];
        $name_part[0] = $name_part[1];
        $name_part[1] = $r;
        //printArray($name_part);
        $this->size = implode(" ", $name_part);
        $this->size = str_replace(",", ".", $this->size);
        $this->size = str_replace("  ", " ", $this->size);
        $this->size = trim($this->size);
    }
    
    
    protected function getParams(array $item) {
        $this->params["width"] = $item["shirina_secheniya"];
        $this->params["height"] = $item["visota_secheniya"];
        $this->params["radius"] = $item["radius"];
        $this->params["index_loading"] = $item["index_loading"];
        $this->params["index_speed"] = $item["index_speed"];
        $this->params["thorn"] = ($item["ship"] == "Шипованные") ? 1 : 0;
        $seasons = ["Лето" => "s", "Зима" => "w", "Всесезонные" => "u"];
        $this->params["season"] = $seasons[$item["seasonality"]];
        $this->params["store_id"] = $item["stockName"];
    }
    
    
    protected function normalizeMarkaModel() {
        parent::normalizeMarkaModel();

        switch($this->marka) {
            /*
            case "BF Goodrich": //BF Goodrich => BFGoodrich
                $this->marka = "BFGoodrich";
                $this->model = str_replace("G Grip", "G-Grip", $this->model);
                $this->model = str_replace("G Force", "G-Force", $this->model);
                $this->model = str_replace("Mud Terrain", "Mud-Terrain", $this->model);
                break;
            */
            case "Continental": //Conti Cross Contact LX2 => ContiCrossContact LX2
                $m_part = explode(" ", $this->model);
                if (count($m_part) > 1) {
                    $m_last = $m_part[count($m_part) - 1];
                    unset($m_part[count($m_part) - 1]);
                    $this->model = implode("", $m_part) . " " . $m_last;
                }
                break;
        }
    }
}


class dbImportItemKolesaDaromDisc extends dbImportItemKolesaDarom {
    protected $product_type = 2;
    protected $min_count = 4;
    protected $price_coef = 1.1;
    
    
    protected function convertToSize(array $item) {
        $name = $item["name"];
        $name = str_replace($item["categoryname"], "", $name);
        $name = str_replace($item["maker"], "", $name);
        
        $name_part = explode(" ", trim($name));
        if (strpos($name_part[0], "R") !== false)
            $name_part[0] = substr($name_part[0], 1);
        //printArray($name_part);
        $this->size = implode(" ", $name_part);
        $this->size = str_replace(",", ".", $this->size);
        $this->size = str_replace("  ", " ", $this->size);
        $this->size = trim($this->size);
    }
    
    
    protected function getParams(array $item) {
        $this->params["width"] = $item["shirina_diska"];
        $this->params["diameter"] = $item["radius"];
        $this->params["bolts_count"] = $item["boltnum"];
        $this->params["bolts_spacing"] = $item["boltdistance"];
        $this->params["et"] = $item["et"];
        $this->params["dia"] = $item["dia"];
        $this->params["type"] = $item["material"];
        //if ($a = strpos($this->size, "("))
//            $this->params["mount"] = substr($this->size, $a + 1, -1);
        $this->params["store_id"] = $item["stockName"];
    }
}
?>