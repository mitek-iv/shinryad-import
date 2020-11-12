<?php
class dbImport4KolesaDarom extends dbImport {
    protected $fileName;
    
    function __construct($fileName) {
        $this->fileName = $fileName;    
    }
    
    
    public function getFromSource() {
        $csv = new CsvReader($this->fileName, ",");
        $csv_array = $csv->GetCsv();
        unset($csv);

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
        
        printArray($csv_array);
    }
    
    public function storeToDB() {
        
    }
}

class dbImport4KolesaDaromTyre extends dbImport4KolesaDarom {
    protected $product_type = 1;
}

class dbImport4KolesaDaromDisc extends dbImport4KolesaDarom {
    protected $product_type = 2;
}
?>