<?php class TagController extends DatabaseController {
       public function affectDataToRow(&$row, $sub_rows){
//filter $sub_rows pour ne garder que les lignes pour lesquelles Id_tag correspondent à Id_tag de row;
        if(isset($sub_rows['article'])){
            $articles = array_values(array_filter($sub_rows['article'], function($item) use($row){
                return $item->Id_tag == $row->Id_tag;

            }));
            //de $articles, on ne souhaite récupérer que le contenu de la propriété 'article'; on stocke ce contenu dans $row->articles_list
            if(isset($articles)){
                $row->articles_list = array_column($articles, 'article');
            }
        }
    }
}?>