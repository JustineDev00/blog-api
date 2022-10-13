<?php 
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class LoginController{

    public function __construct($params){
        $this->method = null;
        $this->action = null;
        $request_body = file_get_contents('php://input');
        $this->body = $request_body ? json_decode($request_body, true) : null;
        $this->table = 'account';
        if(count($params) >= 1){
            $this->method = $params[0];
        }
        if($_SERVER['REQUEST_METHOD'] == "POST" && $this->method == null){
            $this->action = $this->getAccount();
    
        }
        if($this->method == "check"){
            $this->action = $this->check();
        }
        if($_SERVER['REQUEST_METHOD'] == "POST" && $this->method == "register"){
            $this->action = $this->register();
        }
        }
    
  

    public function getAccount(){
        $email = filter_var($this->body['email'], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return ["result" => false];
        }
        
        $dbs = new DatabaseService($this->table);
        $where = "is_deleted = ? AND email = ?";
        $rows = $dbs->selectWhere($where, [0, $email]);
        $row = $rows ? $rows[0] : null;
        $prefix = $_ENV['config']->hash->prefix;


        if($row == null || !password_verify($this->body['password'], $prefix . $row->password)){
            return["result" => false];
            //what happens when login not OK
        }
        else{
            //what happens when login OK 
            $dbs = new DatabaseService('appuser');
            $appUser = $dbs->selectOne($row->Id_appUser);
            //construction du JSON Web Token
            if(isset($appUser)){
                $secretKey = $_ENV['config']->jwt->secret;
                $issuedAt = time();
                $expireAt = $issuedAt + 60*60*24; //expiration du token à date de création + 24 heures
                $serverName = "blog-api";
                $userRole = $appUser->Id_role; 
                $userId = $appUser->Id_appUser;
                $requestData = [
                    'iat' => $issuedAt,
                    'iss' => $serverName,
                    'nbf' => $issuedAt,
                    'exp' =>$expireAt,
                    'userRole' => $userRole,
                    'userId' => $userId
                ];
                //payload : informations transmises par le JWT, les noms de clés suivent les noms standards des déclarations des JWT, liste ici : https://www.iana.org/assignments/jwt/jwt.xhtml
            $token = JWT::encode($requestData, $secretKey, 'HS512');
            //encodage des informations fournies par le JWT, HS512 est l'algorithme d'encodage
            return["result" => true, "role" => $appUser->Id_role,"id" =>$appUser->Id_appUser, "token" => $token];
                    //Le contenu du token encodé est transmis dans la réponse de l'API au site web

            }
            else{
                return["result" => false];
            }
        }
        
    }

    public function check(){
      if(isset($_COOKIE['blog'])){
        $token = $_COOKIE['blog'];
      }
       
        $secretKey = $_ENV['config']->jwt->secret;
        if(isset($token) && !empty($token)){
            try{
                $payload = JWT::decode($token, new Key($secretKey, 'HS512'));
            }
            catch(Exception $e){
                $payload = null;
            }
            if(isset($payload) &&
                $payload->iss === "blog.api" &&
                $payload->nbf < time() &&
                $payload->exp > time())
                {
                    return ["result" => true, "role" => $payload->userRole];
                }
        }
        return ["result" => false];
    }
    public function register(){
        $pseudo = $this->body["pseudo"];
        $email = filter_var($this->body["email"], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return ["message" => "invalid email address"];
        }
        $dbs = new DatabaseService($this->table);
        $where = "is_deleted = ? AND email = ?";
        $rows = $dbs->selectWhere($where, [0, $email]);
        if(count($rows) > 0){
            return ["message" => "email address already in use"];

        }
        else{
            $dbs = new DatabaseService("appuser");
            $where = "is_deleted = ? AND pseudo = ?";
            $rows = $dbs->selectWhere($where, [0, $pseudo]);
            if(count($rows) > 0){
                return ["message" => "pseudo already taken"];
            }
            else{
                return ["message" => "pseudo and email available for account creation"];
            }



            
        }
           
        


    }
     

    }


 
   




