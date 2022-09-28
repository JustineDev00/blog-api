<?php

class DatabaseService{

    public function __construct($table)
    {
        $this->table = $table;
    }

    private static $connection = null;
    private function connect() {
        //connexion à la base de données

        if(self::$connection == null){
            $db_config = $_ENV["config"]->db; //récupère l'objet db  contenu dans $_ENV["config"] (initialisée dans index.php)
            $host =  $db_config->host; //recupère la valeur de la propriété host de $db_config
            $port = $db_config->port;
            $dbName = $db_config->dbName;
            $dsn = "mysql:host=$host;port=$port;dbname=$dbName";
            $user = $db_config->user;
            $pass = $db_config->pass;
            try{
                $db_connection = new PDO(
                    $dsn,
                    $user,
                    $pass,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                        )
                        
    
                    );
            } 
            catch(PDOException $e){
                die("erreur de connexion à la base de données: $e->getMessage()");
    
    
            }
            self::$connection = $db_connection;
        }
        return self::$connection;
    }
        public function query($sql, $params){
        $statement = $this->connect()->prepare($sql);
        $result = $statement->execute($params);
        return (object)['result' => $result, 'statement' => $statement];
        }
        

         public function selectAll(){
            $sql = "SELECT * FROM $this->table WHERE is_deleted = ?";

            //SELECT 'toutes les lignes' FROM (paramètre du constructeur de DatabaseService)
             $resp = $this->query($sql, [0]);
            //exécute la fonction query avec la requête $sql définie plus haut + 0 en paramètres (récupère ainsi seulement les lignes non supprimées)
            $rows = $resp->statement->fetchAll(PDO::FETCH_CLASS);
            //recupère l'ensemble des données, sous la forme d'objets (FETCH_CLASS)
            return $rows;
         }


        public function selectOne($id){
            $sql = "SELECT * FROM $this->table WHERE is_deleted = ? AND
            Id_$this->table = ?";
            $resp = $this->query($sql, [0, $id]);
            $rows = $resp->statement->fetchAll(PDO::FETCH_CLASS); //recupère toutes les lignes où chaque ligne est au format objet
            $row = $resp->result && count($rows) == 1 ? $rows[0] : null; //si la réponse a abouti et si rows ne contient qu'une classe, $row correspond au premier (et unique) objet de $rows
            return $row; //retourne l'objet récupéré
                }

        public function selectWhere($where = null){
            $sql = "SELECT * FROM $this->table". (isset($where) ?? "WHERE $where") . ";";
         //sélectionne tout dans $this->table (param obtenu lors de la construction de l'instance de DBS) et SI isset($where) est true, alors on ajoute "WHERE $where ;" à la requête
            $resp = $this->query($sql, [0]);
            $rows = $resp->statement->fetchAll(PDO::FETCH_CLASS);
            return $rows;
            }

        
            public function insertOne($body = []){ //TODO insertMany
                $columns = "";
                $values = "";
                if(isset($fields["Id_$this->table"])){
                    unset($fields["Id_$this->table"]);
                }
                $valuesToBind = array();
                foreach ($body as $k => $v) {
                    $columns .= $k . ",";
                    $values .= "?,";
                    array_push($valuesToBind, $v);
                }
                $columns = trim($columns, ',');
                $values = trim($values, ',');
                $sql = "INSERT INTO $this->table ($columns) VALUES ($values)";
                $resp = $this->query($sql, $valuesToBind);
                if($resp->result && $resp->statement->rowCount() == 1){
                    $insertedId = self::$connection->lastInsertId();
                    $row = $this->selectOne($insertedId);
                    return $row;
                }
                return false;
            }
         

    }
                
            

?>