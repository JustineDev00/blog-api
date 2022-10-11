<?php

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthMiddleware{
    public function __construct($req){
        $restrictedRoutes = (array)$_ENV['config']->restricted;     //tableau associatif associant chaque requête (clé) à une string qui correspond à une condition (exemple : une valeur d'userRole particulière)
        $params = explode('/', $req);  //req est décomposée en un array, ex $req = "GET/appUser" => $params = ["GET", "appUser"]
        $this->id = array_pop($params);  //pop : retourne le dernier élément d'un array
        if(isset($restrictedRoutes[$req])){ //existe-t-il une valeur associée à la clé $req dans le tableau $restrictedRoutes?
            $this->condition = $restrictedRoutes[$req];  //stockage de cette valeur dans $this->condition
        }
        foreach($restrictedRoutes as $k=>$v){  //lecture du tableau $restrictedRoutes
            $restricted = str_replace(":id", $this->id, $k);  //on stocke dans $restricted la clé $k, si elle contient :id, :id est remplacé par $this->id;
            if($restricted == $req){
                $this->condition = $v;
                break;
            }
        }
    }
    public function verify(){
        if(isset($this->condition)){  //this->condition = la condition qui doit être remplie pour accéder à la requête demandée;
            $headers = apache_request_headers();
            if(isset($headers["Authorization"])){
                $token = $headers["Authorization"];  //recuperation du token renvoyé par la page web
            }
            $secretKey = $_ENV['config']->jwt->secret;
            if(isset($token) && !empty($token)){
                try{
                    $payload = JWT::decode($token, new Key($secretKey, 'HS512'));  //décodage du token
                }catch(Exception $e){
                    $payload = null;
                }
                if(isset($payload) &&    //vérification du contenu du payload
                $payload->iss === "blog.api" &&  //verification origine
                $payload->nbf < time() && //verification date d'émission
                $payload->exp > time() //vérification date d'expiration
                ){
                    $userRole = $payload->userRole;
                    $userId = $payload->userId;
                    $id = $this->id;
                    $test = false;
                    eval("\$test=".$this->condition);  //$this->condition devient une expression booléene (eval permet de la traiter comme du code php); la valeur booléenne renvoyée (true ou false) est stockée dans la variable $test
                    if($test){
                        return true;
                    }
                }
            }
            header('HTTP/1.0 401 Unauthorized');
            die;
        }
        return true;
    }
}


?>