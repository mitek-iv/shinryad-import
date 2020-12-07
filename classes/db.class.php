<?php
    class db {
		protected $mysqli;
		
        
        function __construct() {
            $this->connect();     
        }
        
		
		public function query($sql) {
			$res = $this->push_query($sql);
			
			if ($res === true) //не SELECT
				$result = $this->mysqli->insert_id;
			else { //SELECT
				$result = $this->queryToArray($res);
				$res->close();
			}				
			
			//$res->close();
            return $result;
		}
		
		
		public function val($sql) {
			$res = $this->push_query($sql);
			$result = $this->queryToArray($res);
			$res->close();
			
			if (empty($result)) return null;
			
			$fields = array_keys($result[0]);
			$field = $fields[0];
			return $result[0][$field];
		}
		
		
		public static function compactArray($res) {
            $result = array();
            foreach($res as $item) {
                $keys = array_keys($item);
                $result[] = $item[$keys[0]];
            }
            
            return $result;
        }
        
        
        protected function queryToArray($res) {
            //$res = $this->push_query($sql);
			$result = array();
            while($row = $res->fetch_assoc()) {
                $result[] = $row; 
            }
			
			//$res->close();
            return $result;
        }

				
		protected function connect() {
            global $conf;
            
            $this->mysqli = new mysqli($conf->val("db_host"), $conf->val("db_user"), $conf->val("db_pass"), $conf->val("db_name"));
            if ($this->mysqli->connect_error) {
                throw new Exception("Ошибка соединения с БД");
            }
			if (!empty($conf->val("db_charset"))) {
                $db_charset = $conf->val("db_charset");
				$this->mysqli->set_charset($db_charset);
                $this->query("SET NAMES $db_charset");
            }
            $this->query("SET time_zone = 'Asia/Yekaterinburg'");
        }

        
		protected function push_query($sql) {
			//TODO проверка запроса
			$result = $this->mysqli->query($sql);
			if ($result === false)
				throw new Exception(sprintf("Ошибка выполнения SQL-запроса: %s\n SQL-запрос: %s", $this->mysqli->error, $sql));
			
			return $result;
		}
    }
?>