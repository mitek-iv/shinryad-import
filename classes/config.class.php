<?php
    class config {
        protected $conf = array();
        
        function __construct($path_to_config) {
            if (!file_exists($path_to_config))
                throw new Exception("Пустой конфиг-файл $path_to_config");
            
            include($path_to_config);
            $this->conf = $conf;
        }
        
        
        public function val($index) {
            return $this->conf[$index];
        }
    }
?>