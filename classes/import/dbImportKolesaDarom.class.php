<?php
class dbImport4KolesaDarom extends dbImport {
    protected $provider_id = 2;
    protected $fileName = "1/importcsv.csv";
    
    protected $products; //массив из csv, до формирования $this->items
    
    public function getFromSource() {
        parent::getFromSource();
        $csv = new CsvReader($this->fileName, ",");
        $csv_array = $csv->GetCsv();
        unset($csv);

        //Выделяем строчки одного типа в общей выгрузке (диски+шины)
        $i = 0;
        $pos_shina = 0;
        $pos_disk = 0;
        
        while ($i < count($csv_array)) {
            if ($csv_array[$i][0] == "shina") {
                $pos_shina = $i;
            }
            
            if ($csv_array[$i][0] == "disk") {
                $pos_disk = $i;
            }
                
            $i++;
        }
        
        
        if ($this->product_type == 1) {//Для шин
            if ($pos_disk < $pos_shina) {
                array_splice($csv_array, $pos_disk, $pos_shina - $pos_disk);
            } else {
                array_splice($csv_array, $pos_disk, $i - $pos_disk);
            }
        } else {//Для диска
            if ($pos_disk < $pos_shina) {
                array_splice($csv_array, $pos_shina, $i - $pos_shina);
            } else {
                array_splice($csv_array, $pos_shina, $pos_disk - $pos_shina);
            }
        }
        //!
        
        //printArray($csv_array);
        
        //Для каждой строки формируем ассоциированный массив вида поле = значение
        $fields = $csv_array[1];
        $this->products = [];

        for ($i = 2; $i <= count($csv_array) - 1; $i++) {  //
            $item = $csv_array[$i];
            //printArray($item);
            $product = array();
            for ($j = 0; $j <= count($fields) - 1; $j++) 
                $product[$fields[$j]] = $item[$j];
            
            $product["img"] = "";
            $this->products[$product["id"]] = $product;
        }
        //!
        
        $this->get_images_for_products();
        //printArray($this->products);
        
        $this->convertToItems($this->products);
        $this->toLog("Итого получено: " . count($this->items));
    }
    
    
    protected function get_images_for_products() {
        if (empty($this->products)) return;
        
        //Разбиваем массив по $max элементов для того, чтобы не превысить длину строки GET-запроса в curl
        $j = 0;
        $i = 0;
        $max = 50;
        $part = array();
        foreach($this->products as $product) {
            $part[] = $product["id"];
            if (($j >= $max) || ($i >= count($this->products) - 1)) {
                
                $this->step_get_images_for_products($part);
                $part = array();
                $j = 0;
            } else {
                $j++;
            }
            $i++;
        }
    }
    
    
    /**
    Получает изображения с помощью API для ограниченного числа элементов.
    */
    protected function step_get_images_for_products($ids) {
        if (empty($ids)) return;
        
        $query = array();
        foreach($ids as $id)
            $query[] = "goods_id[]=$id";
        
        $query = implode("&", $query);
        $query = "https://apiopt.kolesa-darom.ru/v2/search/?" . $query;
        
        //print ($query);
                
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $query);

	    $request_headers = array();
	    $request_headers[] = 'Authorization: Bearer HLmkk9ahjFq5bLvq_AtLGtNR3WrYv73d';
	    $request_headers[] = 'Accept: application/json';
	    $request_headers[] = 'Content-type: application/json';

	    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        
	    //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $output = curl_exec($ch);

        // close curl resource to free up system resources
        curl_close($ch);    
        
        $answer = json_decode($output, true);
        if (empty($answer)) return;
        //printArray($answer);
        $result = true;
        if (isset($answer["status"]))
            if ($answer["status"] == "403") {
                $result = false;
//                foreach ($ids as $product_id)
//                    if (isset($this->products[$product_id]))
//                        $this->products[$product_id]["img"] = "!!!";
            }
        if ($result) {
            foreach($answer as $item) {
                $product_id = $item["goods_id"];
                $img_url = $item["img_url"];
                if (isset($this->products[$product_id]))
                    $this->products[$product_id]["img"] = $img_url;
            }
        }
    }
}


class dbImportKolesaDaromTyre extends dbImport4KolesaDarom {
    protected $product_type = 1;
    protected $item_class = "dbImportItemKolesaDaromTyre";
}


class dbImportKolesaDaromDisc extends dbImport4KolesaDarom {
    protected $product_type = 2;
    protected $item_class = "dbImportItemKolesaDaromDisc";
}
?>