<?php class ThemeDetailController {
    public function __construct($params)
    {
        $id = array_shift($params);
        $this->action = null;
        if(isset($id) && !ctype_digit($id)){
            return $this;
        //empêche requêtes sans id ou les requêtes où $id n'est pas un nombre
        }
        if($_SERVER['REQUEST_METHOD'] == 'GET' &&isset($id)){
            $this->action = $this->getData($id);
        }
    }
    public function getData($id){
        require_once 'theme.controller.php';
        $themeCtrl = new ThemeController([$id]);
        $row = $themeCtrl->getOneWith($id, ["article"]);
        //récupère la ligne de la table 'theme" où Id_theme === $id, avec une propriété supplémentaire articles_list contenant toutes les lignes de la table 'article' où Id_theme de l'article === $id
        require_once 'article.controller.php';
        $articleCtrl = new ArticleController([]);
        $articles = $articleCtrl->getAllWith(["appuser"]);
        //récupère tous les lignes  de la table 'article', avec pour chaque ligne une propriété supplémentaire appUser contenant la ligne de la table 'appuser' où Id_appUser === Id_appUser de chaque article
        foreach($row->articles_list as &$article){
            //on regarde chaque 'article' récupéré grâce à getOneWith === un article de la liste des articles du thème récupéré
            $filtered_articles = array_values(array_filter($articles, function($item) use($article){
                return $item->Id_article == $article->Id_article;
            //on regarde dans la liste de tous nos articles quel est l'article qui a le même Id que l'article que l'on 'regarde' actuellement; on ne garde que cet article dans $filtered_articles
            }));
            $article = count($filtered_articles) == 1 ? array_pop($filtered_articles) : null;
            //on remplace dans $row->articles_list l'article que l'on 'regarde' par le résultat du filtre; comme ça on obtient en plus les infos de l'auteur de l'article
        }
        return $row;
    }
} ?>