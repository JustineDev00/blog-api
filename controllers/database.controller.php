<?php
   abstract class DatabaseController
    {
        public function __construct($params)
        {
            $id = array_shift($params);
            $this->action = null;

            if(isset($id) && !ctype_digit($id)){
                return $this;
                }

            
            $request_body = file_get_contents('php://input');
            

            $this->body = $request_body ? json_decode($request_body, true) : null;

            
            $this->table = lcfirst(str_replace("Controller", "", get_called_class()));



            //str_replace cherche dans get_called_class() (le nom de la classe appelant cette fonction, les instances de DBC elle-même ou les instances de ses classes-filles) l'expression "Controller", par ""; puis la premiere lettre est mise en minuscule;

            //exemple avec une instance de ThemeController:
            //str_replace : ThemeController --> Theme
            //lcfirst : Theme --> theme
            //$this->table peut etre passe en parametres dans la création d'une instance de DatabaseService

            if($_SERVER['REQUEST_METHOD'] == "GET" && !isset($id)){

                $this->action = $this->getAll();  //si pas d'ID reçu : getAll()
                }
                if($_SERVER['REQUEST_METHOD'] == "GET" && isset($id)){
                $this->action = $this->getOne($id); //si un ID reçu : getOne()
                }
                if($_SERVER['REQUEST_METHOD'] == "POST" &&!isset($id)){
                $this->action = $this->create();
                }
                if($_SERVER['REQUEST_METHOD'] == "PUT" && isset($id)){
                $this->action = $this->update($id);
                }
                if($_SERVER['REQUEST_METHOD'] == "PATCH" && isset($id)){
                $this->action = $this->softDelete($id);
                }
                if($_SERVER['REQUEST_METHOD'] == "DELETE" && isset($id)){
                $this->action = $this->hardDelete($id);
                }

                if($_SERVER['REQUEST_METHOD'] == "POST" && isset($id)){
                    if($id == 0){ //POST/table/0
                        $this->action = $this->getAllWith($this->body["with"]);

                    }
                    if($id > 0){ //POST/table/:id
                        $this ->action = $this->getOneWith($id, $this->body["with"]);
                    }


                }

    
        }

        public abstract function affectDataToRow(&$row, $sub_rows);
        //cette fonction est transmise aux classes filles cependant c'est dans ces classes-filles que le paramètre &$row sera défini
        


        public function getAll(){
            $dbs = new DatabaseService($this->table);
            //genère une instance de classe DatabaseService où $this = tag
            $rows = $dbs->selectAll();
            //appelle la fonction selectAll() de $dbs; dans la requête SQL envoyée, FROM $this->table sera remplacé par tag
            return $rows; //"Select all rows from table tag";
        }
       
        public function getAllWith($with){
            $rows = $this->getAll();
            $sub_rows = [];
            foreach($with as $table){
                if(is_array($table)){ //si un des éléments de l'array $with est lui-même un tableau (associatif ou indexé) 
                    $final_table = key($table); //table dans laquelle on va chercher la donnée finale; dans l'exemple de relation MtoM entre article et tag, tag est la table finale
                    $through_table = $table[$final_table];//table dont on va se servir pour accéder à la bonne ligne dans la table finale
                    $dbs = new DatabaseService($through_table); //création d'une instance de dbs ciblant la table "article_tag"
                    $through_table_rows = $dbs->selectWhere(); //on récupère toutes les lignes de la table article_tag

                    $dbs = new DatabaseService($final_table); //création d'une instance de DBS qui va cibler la table "tag"
                    $final_table_rows = $dbs->selectAll(); //on récupère toutes les lignes de la table "tag" sauf celles qui sont soft deleted 
                    foreach($through_table_rows as $through_table_row){
                            //Pour chaque ligne de la table "article_tag"
                        $row_to_add = array_values(array_filter($final_table_rows, function($item) use($through_table_row, $final_table){
                        $prop = 'Id_'.$final_table;
                        return $item->{$prop} == $through_table_row->{$prop};
                                //on filtre l'ensemble des lignes dans 'tag' pour ne garder que les lignes qui ont le même Id_tag que la ligne $through_table_row
                            }));
                        $through_table_row->$final_table = count($row_to_add) == 1 ? array_pop($row_to_add) : null ;
                        //on vérifie que pour chaque ligne dans through_table_row il n'y a qu'une seule ligne trouvée dans final_table
                        }
                    $sub_rows[$final_table] = $through_table_rows;
                    //chaque ligne dans article_tag possède maintenant une propriété $final_table dans lequel on retrouve la ligne de tag ayant la même Id_tag
                    continue;
                    
                    }
                    //si l'élément dans l'array $with n'est pas lui-même un array
                $dbs = new DatabaseService($table);
                $table_rows = $dbs->selectAll();
                $sub_rows[$table] = $table_rows;
                }
            foreach ($rows as $row) {
                 $this->affectDataToRow($row, $sub_rows);
                }
            return $rows;
           }     
            
        public function getOne($id){
        $dbs = new DatabaseService($this->table); //idem getAll
        $row = $dbs->selectOne($id); //appelle la fonction selectOne() de $dbs; la requête SQL, $this->table = "tag", et AND Id_$this->table = ? ==  Id_tag = $id
        return $row;
            }



        public function create(){
            $dbs = new DatabaseService($this->table);
            $row = $dbs->insertOne($this->body);
            return $row;
            }
        public function update($id){
            $dbs = new DatabaseService($this->table);
            $row = $dbs->updateOne($this->body, $id);
            return $row;
            // return "Update row with id = $id in table tag";
            }
        public function softDelete($id){
            $dbs = new DatabaseService($this->table);
            $row = $dbs->updateOne(['is_deleted' => 1], $id);
            if(isset($row) && $row == false){
            return false;
            }
            return !isset($row);
            }

        public function hardDelete($id){
        $dbs = new DatabaseService($this->table);
        $row = $dbs->deleteOne(["Id_$this->table" => $id]);
        return $row;
            }


        public function getOneWith($id, $with){
            $row = $this->getOne($id);
            $sub_rows = [];
            foreach($with as $table){
                if(is_array($table)){
                    $final_table = key($table);
                    $through_table = $table[$final_table];
                    $dbs = new DatabaseService($through_table);
                    $through_table_rows = $dbs->selectWhere();
                    $dbs = new DatabaseService($final_table);
                    $final_table_rows = $dbs->selectAll();
                    foreach($through_table_rows as $through_table_row){
                        $row_to_add = array_values((array_filter($final_table_rows, function($item) use($through_table_row, $final_table)
                        {
                            $prop = 'Id_'.$final_table;
                            return $item->{$prop} == $through_table_row->{$prop};
                        })));
                       if(count($row_to_add) == 1){
                        $through_table_row->$final_table = array_pop($row_to_add);
                       }

                        //idem que getAllWith() : permet de récupérer les valeurs d'une table associative et d'associer à chaque valeur sa ligne correspondante dans la "table finale"
                        }
                    $sub_rows[$final_table] = $through_table_rows;
                    continue;
                    }



                $dbs = new DatabaseService($table);
                $table_rows = $dbs->selectAll();
                $sub_rows[$table] = $table_rows;
                }
                
            $this ->affectDataToRow($row, $sub_rows);
            return $row;


            }
    }
