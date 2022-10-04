<?php 

class LoginController{

    public function __construct($params){

        $this->action = null;
        $request_body = file_get_contents('php://input');
        $this->body = $request_body ? json_decode($request_body, true) : null;
        $this->table = 'account';
        if($_SERVER['REQUEST_METHOD'] == "POST"){
            $this->action = $this->getAccount();
    
        }
        }
    private static $connection = null;
    private function connect(){
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

    public function getAccount(){
        $sql = "SELECT * FROM $this->table WHERE is_deleted = ? AND email = ?";
        
        $email = filter_var($this->body['email'], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return ["result" => false];
        }
        $valuesToBind = [0, $email];
        $resp = $this->query($sql, $valuesToBind);
        $rows = $resp->statement->fetchAll(PDO::FETCH_CLASS);
        $row = $resp->result && count($rows) == 1 ? $rows[0] : null;
        if($row == null || $row->password != $this->body['password']){
            return["result" => false];

        }
        else{
            $table = 'appuser';
            $dbs = new DatabaseService($table);
            $appUser = $dbs->selectOne($row->Id_appUser);
            if(isset($appUser)){
                return["result" => true, "role" => $appUser->Id_role];


            }
            else{
                return["result" => false];
            }

        }
        

    }


     

    }


 
   




