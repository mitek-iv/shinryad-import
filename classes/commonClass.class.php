<?php
    function toLog($msg, $show_time = false) {
        //$file = $_SERVER["DOCUMENT_ROOT"] . "/_cm/log.txt";
        $file = dirname(dirname(__FILE__)) . "/log.txt";
        
        if ($show_time) {
            $time = commonClass::getTime() . "\r\n";
        } else {
            $time = "";
        }
        if ((is_object($msg)) || (is_array($msg)))
            //$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
            $msg = toJSON($msg);
        $msg = sprintf("%s %s\r\n", $time, $msg);

        // Пишем содержимое в файл,
        // используя флаг FILE_APPEND для дописывания содержимого в конец файла
        // и флаг LOCK_EX для предотвращения записи данного файла кем-нибудь другим в данное время
        file_put_contents($file, $msg, FILE_APPEND | LOCK_EX);
    }


    function toJSON($array) {
        return json_encode($array, JSON_UNESCAPED_UNICODE);
    } 


    function isJSON($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }


    function printArray($array) {
        print "<pre>";
        print_r($array);
        print "</pre>";
    }

    function siteURL() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'].'/';
        return $protocol.$domainName;
    }

    
    class commonClass { 
        public function __toString() {
            return "<pre>" . print_r($this, true) . "</pre>";
        } 
        
        
		public function getField($field_name) {
            return $this->$field_name;
        }
        
        
        public function getInform() {
			$result = array();
			foreach ($this->inform_fields as $field)
				$result[$field] = $this->$field;
				
			return $result; 	
		}
		
		
        protected function toLog($msg, $show_time = false) {
            global $conf;
            
            if  (!is_null($conf))
                $file = $conf->val("log_file");
            else
                $file = dirname(dirname(__FILE__)) . "/log.txt";
                        
            if ($show_time) {
                $time = $this->getTime() . "\r\n";
            } else {
                $time = "";
            }
            if ((is_object($msg)) || (is_array($msg)))
                //$msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
                $msg = toJSON($msg);
            $msg = sprintf("%s %s: %s\r\n", $time, get_class($this), $msg);
            
            // Пишем содержимое в файл,
            // используя флаг FILE_APPEND для дописывания содержимого в конец файла
            // и флаг LOCK_EX для предотвращения записи данного файла кем-нибудь другим в данное время
            file_put_contents($file, $msg, FILE_APPEND | LOCK_EX);
        }
        
        
        protected function arrayToClassFields(array $source, array $array_fields, array $class_fields) {
            //Передаёт значения из массива $source в поля класса
            //каждому полю из $class_fields ставится в соответствие поле из $array_fields
            $all_class_fields = array_keys(get_class_vars(get_class($this))); //Получаем все поля класса
            
            for ($i = 0; $i < count($array_fields); $i++) {
                $array_field = $array_fields[$i];
                $class_field = $class_fields[$i];
                //isset($source[$array_field])
                //in_array($class_field, $all_class_fields)
                if ((isset($source[$array_field])) && (in_array($class_field, $all_class_fields))) {
                    $this->$class_field = $source[$array_field];   
                }
            }
        }
        
        
        protected function arrayToClassFieldsAuto(array $source, array $exclude = array()) {
            //Передаёт значения из массива в поля класса
            //$exclude - список полей класса, которые не надо обрабатывать
            $fields = array_keys(get_class_vars(get_class($this)));
            foreach ($fields as $field) 
                if (!(in_array($field, $exclude)))
                    if (isset($source[$field]))
                        $this->$field = $source[$field];
        }
        
        
        protected function sendMail($to, $subject, $message) {
            global $conf;
            
            $from = $conf->val("e_mail_from");
            $headers = "From: $from" . "\r\n" .
                "Reply-To: $from" . "\r\n" .
                'X-Mailer: PHP/' . phpversion();

            mail($to, $subject, $message, $headers);
        }
        
        
        public static function getTime() {
            return date('d.m.Y H:i:s', time());
        }
    }
?>