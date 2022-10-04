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
    
  

    public function getAccount(){
        $email = filter_var($this->body['email'], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return ["result" => false];
        }
        
        $dbs = new DatabaseService($this->table);
        $where = "is_deleted = ? AND email = ?";
        $rows = $dbs->selectWhere($where, [0, $email]);
        $row = $rows ? $rows[0] : null;
        if($row == null || $row->password != $this->body['password']){
            return["result" => false];

        }
        else{
            
            $dbs = new DatabaseService('appuser');
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


 
   




