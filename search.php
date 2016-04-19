<?php
	/**
	 Поиск по сайту
	*/
    require_once "config_class.php";         //класс с параметрами сайта 
    
    class DataBase{
    
        private $config;
        private $mysqli;
        
        public function __construct(){

            $this->config = new Config();
            $this->mysqli = new mysqli($this->config->host, $this->config->user,$this->config->password,$this->config->db);
            $this->mysqli->query("SET NAMES 'utf8'");   
        }
        
        private function query($query){ // функция для более удобного создания запросов к БД

            return $this->mysqli->query($query);
        }
        
        private function select($table_name, $fields, $where = "", $order = "", $up = true, $limit = ""){

            for($i = 0; $i < count($fields);$i++)
                if ((strpos($fields[$i], "(")===false) && ($fields[$i] != "*")) $fields[$i] = "`".$fields[$i]."`";   
            
            $fields = implode("," , $fields);
            $table_name = $this->config->db_prefix.$table_name;

            if(!$order) $order = "ORDER BY `id`";

            else 
            {
                if($order != "RAND()")
                {
                    $order = "ORDER BY `$order`";
                    if(!$up) $order .= " DESC";
                }
                else $order = "ORDER BY $order";
            }

            if($limit) $limit = "LIMIT $limit";

            if($where) $query = "SELECT $fields FROM $table_name WHERE $where $order $limit";
            else $query = "SELECT $fields FROM $table_name $order $limit";

            $result_set = $this->query($query);

            if(!$result_set) return false;
            $i = 0;

            while ($row = $result_set->fetch_assoc())
            {
                $data[$i] = $row;
                $i++;
            } 
            $result_set->close();
            return $data;           
        }
       
        public function search($table_name, $words, $fields){ // имя таблицы и поля для поиска передаются из другого класса, 
        													 //а слова для поиска беруться из формы и через контроллер вызывается эта функция
            $words = mb_strtolower($words);
            $words = trim($words);
            $words = quotemeta($words);

            if($words == "") return false;  // если поле запроса пустое, возвращается ложь

            $where = "";
            $arraywords = explode(" ",$words);
            $logic = "OR";                // для ввода нескольких слов в строку поиска

            foreach ($arraywords as $key => $value)
             {
                if(isset($arraywords[$key - 1])) $where .=$logic; // если слово не одно, то в предикат вставляем коньюнкцию

                for($i = 0; $i < count($fields); $i++)
                {
                    $where .= "`".$fields[$i]."` LIKE '%".addslashes($value)."%'"; // формирование предиката
                    if(($i + 1) != count($fields)) $where .= " OR ";
                }
            }   

            $result = $this->select($table_name, array("*"), $where); 

            if(!$result) return false;
            $k = 0;
            $data = array();

            for($i = 0; $i < count($result);$i++)
            {
                for($j = 0; $j <count($fields); $j++)
                    $result[$i][$fields[$j]] = mb_strtolower(strip_tags($result[$i][$fields[$j]]));

                $data[$k] = $result[$i];
                $data[$k]["relevant"] = $this->getRelevantForSearch($result[$i], $fields, $words); // подсчет релевантности(количества совпадений с запросом)
                $k++;																			   
            }

            $data = $this->orderResultSearch($data, "relevant");// ранжирование результатов по релевантности
            return $data;        				                // где больше совпадений, то выше

        }

        private function getRelevantForSearch($result, $fields, $words)
        {
            $relevant = 0;
            $arraywords = explode(" ", $words);
            for($i = 0; $i < count($fields);$i++)
                for($j = 0; $j < count($arraywords); $j++)
                    $relevant += substr_count($result[$fields[$i]], $arraywords[$j]);
            return $relevant;
        }

        private function orderResultSearch($data, $order){

            for($i = 0; $i < count($data) - 1; $i++)
            {
                $k = $i;
                for($j = $i+1; $j<count($data);$j++)
                    if($data[$j][$order] > $data[$k][$order]) $k = $j; 

                $temp = $data[$k];
                $data[$k] = $data[$i];
                $data[$i] = $temp;
            }
            return $data;
        }
        public function __destruct(){
            if($this->mysqli) $this->mysqli->close(); 
        }        
    }
?>