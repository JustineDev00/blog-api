<?php class ArticleController extends DatabaseController {
       public function affectDataToRow(&$row, $sub_rows){
        //ajouter l'auteur et le thème
        if(isset($sub_rows['appuser'])){
            $appuser = array_filter($sub_rows["appuser"], function($item) use($row){
                return $item->Id_appUser == $row->Id_appUser;
            });
            $row->appUser = count($appuser) == 1 ? array_shift($appuser) : null;

        }
        if(isset($sub_rows['theme'])){
            $themes = array_filter($sub_rows["theme"], function($item) use($row){
                return $item->Id_theme == $row->Id_theme;
            });
            $row->theme = count($themes) == 1 ? array_shift($themes) : null;

        }

        if(isset($sub_rows['image'])){
            $images = array_values(array_filter($sub_rows["image"], function($item) use($row){
                return $item->Id_article == $row->Id_article;

            }));
            $row->images_list = $images;
        }
        if(isset($sub_rows['comment'])){
            $comments = array_values(array_filter($sub_rows["comment"], function($item) use($row){
                return $item->Id_article == $row->Id_article;

            }));
            $row->comments_list = $comments;
        }
        if(isset($sub_rows['tag'])){
            $tags = array_values(array_filter($sub_rows['tag'], function($item) use($row){
                return $item->Id_article == $row->Id_article;
//Après avoir récupéré toutes les lignes de article_tag contenant la ligne correspondante dans tag (voir DatabaseService), on filtre cette liste pour ne garder que les lignes où Id_article est égal à celui de row->Id_article
            }));
            if(isset($tags)){
                $row->tags_list = array_column($tags, 'tag');
            }
//si on a bien des valeurs après filtrage, on ajoute à row la propriété 'tags_list' qui ne contient non pas les lignes filtrées, mais ce qui est stocké dans leur propriété "tag" (en fait, les lignes extraites de la table tag proprement dite)
        }


    }
}?>