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
        if($_SERVER["REQUEST_METHOD"] == 'POST' && $this->method == 'forgottenPassword'){
            $this->action = $this->checkForPasswordChange();
        }
        if($_SERVER["REQUEST_METHOD"] == 'POST' && $this->method == 'validatePasswordChange'){
            $this->action = $this->validateForPwdChange();
        }
        if($_SERVER["REQUEST_METHOD"] == 'POST' && $this->method == 'changePassword'){
            $this->action = $this->changePassword();
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
        $pseudo = $this->body['pseudo'];
        $mail = $this->body['mail'];

        //si les deux mots de passe sont identiques:
        if($this->body['password'] == $this->body['passwordConfirm']){
            //si OK : cryptage du mot de passe
            $prefix = $_ENV['config']->hash->prefix;
            $password = str_replace($prefix, '',password_hash($this->body["password"], PASSWORD_ARGON2ID, [
                'memory_cost' => 1024,
                'time_cost' => 2,
                'threads' => 2
            ]));
            $dbs = new DatabaseService('appuser');
            //DONE: créer une nouvelle ligne appUser (lignes : pseudo, is_deleted, Id_role)
            $body = ['pseudo' => $pseudo, 'is_deleted' => 0, 'Id_role' => 2];
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
        
    }


    public function checkForPasswordChange(){
        $email = $this->body['email'];
        //DONE : vérifier si $mail existe dans la BDD
        $dbs = new DatabaseService('account');
        $where = "is_deleted = ? AND email = ?";
        $rows = $dbs->selectWhere($where, [0, $email]);
        if(count($rows) === 1){
             //DONE : créer token content l'Id de l'account
            $row = $rows[0];
            $id = $row->Id_account;
            $secretKey = $_ENV['config']->jwt->secret;
            $issuedAt = time();
            $expireAt = $issuedAt + 60*60;
            $serverName = "blog-api";
            $requestData = [
                'iat' => $issuedAt,
                'iss' => $serverName,
                'nbf' => $issuedAt,
                'exp' =>$expireAt,
                'id' => $id,
            ];
            $token = JWT::encode($requestData, $secretKey, 'HS512');
            //TO DO : si oui, envoyer un email avec token dans l'URL autorisant le changement de mot de passe
            $ms = new MailerService();
            $href = "http://localhost:3000/changePassword/$token";
            $mailParams = [
                "fromAddress" => ["register@monblog.com", "inscription monblog.com"],
                "destAddresses" => [$email],
                "replyAddress" => ['info@monblog.com', "information monblog.com"],
                "subject" => "Réinitialisation de mot de passe de votre compte monblog.com",
                "body" => "cliquer ci-dessous pour réinitialiser votre mot de passe. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.
                <br>
                <a href='$href'>Valider</a>",
                "altBody" => "Veuillez copier/coller l'adresse suivante dans votre navigateur pour réinitialiser votre mot de passe : $href. Si vous n'êtes pas à l'origine de cette demande, ignorez ce message."
            ];
            $sent = $ms->send($mailParams);
            return ['result' => $sent, "message" => $sent? "Vérifiez votre boîte mail et suivez les instructions pour réinitialiser votre mot de passe" : "Une erreur est survenue, merci de recommencer l'opération"];
           
        }
        else{
            return ["result" => false, "message" => "Adresse email inconnue"];
        }
    }

    public function validateForPwdChange(){
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
                    return ["result" => true, "id" => $payload->id];
                }
            }
           return ["result" => false];  
    }

    public function changePassword(){
        $id = $this->body['id'];
        if($this->body['password'] == $this->body['passwordConfirm']){
            //si OK : cryptage du mot de passe
            $prefix = $_ENV['config']->hash->prefix;
            $password = str_replace($prefix, '',password_hash($this->body["password"], PASSWORD_ARGON2ID, [
                'memory_cost' => 1024,
                'time_cost' => 2,
                'threads' => 2
            ]));
        $dbs = new DatabaseService('account');
        $row = $dbs->updateOne(['password' => $password], $id);
        if(isset($row)){
            return ["result" => true, "message" => "Votre mot de passe a bien été modifié."];
        }
        return ["result" => false, "message" => "Une erreur est survenue, merci de recommencer l'opération"];

    }
}

}
 
   




