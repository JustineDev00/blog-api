<?php 
require_once './services/mailer.service.php';

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
        if($_SERVER['REQUEST_METHOD'] == "POST" && $this->method == "validate"){
            $this->action = $this->validate();
        }
        if($_SERVER['REQUEST_METHOD'] == "POST" && $this->method == "create"){
            $this->action = $this->create();
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
                $payload->iss === "blog-api" &&
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
                $secretKey = $_ENV['config']->jwt->secret;
                $issuedAt = time();
                $expireAt = $issuedAt + 60*60;
                $serverName = "blog-api";
                $requestData = [
                    'iat' => $issuedAt,
                    'iss' => $serverName,
                    'nbf' => $issuedAt,
                    'exp' =>$expireAt,
                    'mail' => $email,
                    'pseudo' => $pseudo,


                ];
                $token = JWT::encode($requestData, $secretKey, 'HS512');
                $href = "http://localhost:3000/account/validate/$token";
                $mailParams = [
                    "fromAddress" => ["register@monblog.com", "inscription monblog.com"],
                    "destAddresses" => [$email],
                    "replyAddress" => ['info@monblog.com', "information monblog.com"],
                    "subject" => "Inscription à monblog.com",
                    "body" => "cliquer ci-dessous pour valider la création du compte
                    <br>
                    <a href='$href'>Valider</a>",
                    "altBody" => "Veuillez copier/coller l'adresse suivante dans votre navigateur: $href"
                ];
                $ms = new MailerService();
                $sent = $ms->send($mailParams);
                return ['result' => $sent, "message" => $sent? "Vérifiez votre boîte mail et confirmer votre inscription sur monblog.com" : "Une erreur est survenue, merci de recommencer l'inscription"];


            

            }



            
        }
           

    }
    public function validate(){
        $token = $this->body['token'];
        $secretKey = $_ENV['config']->jwt->secret;
        if(isset($token) && !empty($token)){
            try{
                $payload = JWT::decode($token, new Key($secretKey, 'HS512'));
            }
            catch(Exception $e){
                $payload = null;
            }
            if(isset($payload) &&
                $payload->iss === "blog-api" &&
                $payload->nbf < time() &&
                $payload->exp > time())
                {
                    return ["result" => true, "pseudo" => $payload->pseudo, "mail" => $payload->mail];
                }
            }
           return ["result" => false];  
        }

    public function create(){
        //TODO : crypter les mots de passes (mis de côté pour le moment, besoin de demander des questions à Laurent)
       
        //TO DO : vérifier que les deux mots de passe sont identiques (faire la validation côté React également)
        $pseudo = $this->body['pseudo'];
        $mail = $this->body['mail'];

        //si les deux mots de passe sont identiques:
        if($this->body['password'] == $this->body['password-confirm']){
            //si OK : cryptage du mot de passe
            $prefix = $_ENV['config']->hash->prefix;
            $password = str_replace($prefix, '',password_hash($this->body["password"], PASSWORD_ARGON2ID, [
                'memory_cost' => 1024,
                'time_cost' => 2,
                'threads' => 2
            ]));
            $dbs = new DatabaseService('appuser');
            //TO DO : créer une nouvelle ligne appUser (lignes : pseudo, is_deleted, Id_role)
            $body = ['pseudo' => $pseudo, 'is_deleted' => 0, Id_role => 2];
            //DONE : récupérer la ligne créée (retournée par DBS)
            $appUserRow = $dbs->insertOne($body);
            if(isset($appUserRow)){
                $dbs = new DatabaseService('account');
                $body = ['email' => $mail, 'password' => $password, 'is_deleted' => 0, 'Id_appUser' => $appUserRow->Id_appUser];
                 //done: créer une nouvelle ligne account(lignes : mail, mot de passe crypté, Id_appUser)
                $accountRow = $dbs->insertOne($body);
                if(isset($accountRow)){
                    //done : renvoyer une réponse à blog-admin 
                    return ["result" => true];
                }
                else{
                    return ["result" => false];
                }
                
            }
            else{
                return ["result" => false];
            }
        }else{
            return ["result" => false];
        }
        

        
       
        //done : renvoyer une réponse à blog-admin 
        
        
    }
    }


 
   




