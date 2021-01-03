<?php
class CsvReader
{ 
    private $file;
    private $delimiter; 
    private $length;
    private $handle; 
    private $csvArray; 
    
    public function __construct($file, $delimiter=";", $length = 8000) 
    { 
       $this->file = $file; 
       $this->length = $length; 
       $this->delimiter = $delimiter; 
       $this->FileOpen(); 
    } 
    public function __destruct() 
    { 
       $this->FileClose(); 
    } 
    public function GetCsv()
    {
        $this->SetCsv();
        if(is_array($this->csvArray)) 
         return $this->csvArray;
    }
    
    private function SetCsv()
    {
        if($this->GetSize())
        {
            while (($data = @fgetcsv($this->handle, $this->length, $this->delimiter)) !== FALSE)
            {
                $this->csvArray[] = $data;
            }
        }
    }
    private function FileOpen()
    {
        $this->handle=($this->IsFile())?fopen($this->file, 'r'):null;
    }
    private function FileClose()
    {
        if($this->handle) 
         @fclose($this->handle); 
    }
    private function GetSize()
    {
        if($this->IsFile())
            return (filesize($this->file));
        else
            return false;
    }
    private function IsFile()
    {
        if(is_file($this->file) && file_exists($this->file))
            return true;
        else
            return false;
    }
} 

class CSVWriter {
    private $file;
    private $delimiter;
    private $array = array();
    private $handle;
    
    
    public function __construct($file, $delimiter=";") {
        $this->file = $file; 
        $this->delimiter = $delimiter;
        $this->open();
    }
    
    
    public function __destruct() {
        $this->close();
    }
    
    
    public function get($to_utf8 = true) {
        $this->set_csv($to_utf8);
        $this->close();
    }
    
    
    public function add($array) {
        $this->array[] = $array;
    }
    
    
    public function return_file() {
        header('Content-type: application/csv');
        header('Content-Disposition: attachment; filename="goods.csv"');
        readfile($this->file);
    }
    
    
    private function is_writable() {
        if(is_writable($this->file)) return true;
        else return false;
    }
    
    
    private function set_csv($to_utf8) { 
        if($this->is_writable()) {
          $content = ""; 
          foreach($this->array as $ar) { 
		if ($to_utf8)
	        	$content .= implode($this->delimiter, $ar);
		else
			$content .= iconv("utf-8", "cp1251", implode($this->delimiter, $ar));  
             $content .= "\r\n"; 
          } 
          if (fwrite($this->handle, $content) === false) exit;
        }
    }
    
    
    private function open() {
        $this->handle = fopen($this->file, 'w+');
    }
    
    
    private function close() {
        if($this->handle) @fclose($this->handle); 
    } 
}
?>