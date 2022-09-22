<?php class AppuserController extends DatabaseController {

public function affectDataToRow(&$row, $sub_rows){
    //filtrage de sub_rows[account] pour ne sélectionner que la ligne associée à un appuser particulier
if(isset($sub_rows['account'])){
    $accounts = array_filter($sub_rows['account'], function($item) use($row){
        return $item->Id_appUser == $row->Id_appUser;
    });
    $row->account = count($accounts) == 1 ? array_shift($accounts) : null;
}
    //filtrage de sub_rows["role"] pour ne sélectionner que le rôle associé à un appuser particulier
if(isset($sub_rows['role'])){
    $roles = array_filter($sub_rows['role'], function($item) use($row){
        return $item->Id_role == $row->Id_role;
    });
    $row->role = count($roles) == 1 ? array_shift($roles) : null;
}
if(isset($sub_rows['article'])){
    $articles = array_values(array_filter($sub_rows['article'], function($item) use($row){
        return $item->Id_appUser == $row->Id_appUser;
    }));
    if(isset($articles)){
        $row->articles_list = $articles;
    }
}
if(isset($sub_rows['comment'])){
    $comments = array_values(array_filter($sub_rows['comment'], function($item) use($row){
        return $item->Id_appUser == $row->Id_appUser;  //trie la table comment pour ne récupérer que ceux écrit par l'utilisateur ciblé avec Id_appUser;
    }));
    if(isset($comments)){
        $row->comments_list = $comments; //stocke le résultat du tri dans une nouvelle propriété $comments_list
    }

}


}

}?>