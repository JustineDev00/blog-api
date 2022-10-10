<?php


header("Access-Control-Allow-Origin: http://localhost:3000");  //premiere securite : autorise la connexion depuis un domaine donné (ici localhost:3000)
header("Access-Control-Allow-Headers: Authorization");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: *");
if($_SERVER['REQUEST_METHOD'] == "OPTIONS"){
header('HTTP/1.0 200 OK');
die;
}


   $_ENV["current"] = "dev";
   $config = file_get_contents("configs/".$_ENV["current"].".config.json");
   $_ENV["config"] = json_decode($config);

   
   
   
   
   
    require_once 'services/database.service.php';//import de la classe DatabaseService
    require_once 'controllers/database.controller.php';
    require_once ('vendor/autoload.php');  //nécessaire à l'utilisation de 
     // TEST DE QUERY()
    // $dbs = new DatabaseService("test"); //initialisation d'une instance de classe DatabaseService, permettant d'exécuter la fonction query()
    // $query_resp = $dbs->query("SELECT table_name FROM information_schema.tables WHERE table_schema = ?", ['mcd-blog-db']); //cette requête permet d'obtenir des informations sur la BDD, ici table_name from information_schema.tables nous donne les noms des tables d'une BDD; table_schema correspond au nom de la BDD (passée en 2e paramètre de query())
    // $rows = $query_resp->statement->fetchAll(PDO::FETCH_COLUMN);
    //récupère toute la colonne table_name sous la forme d'un tableau indexe (grâce à FETCH_COLUMN)
    
    
    //attention! dans les PDOs:
    // -- $result correspond à un booléen indiquant si la connexion a été réalisée ou non
    // -- $statment stocke les données dont on a besoin!


    // echo var_export($rows) . "<br/>";
    


    $route = trim($_SERVER["REQUEST_URI"], '/'); //enlève le slash au début et à la fin de la route
    $route = filter_var($route, FILTER_SANITIZE_URL); // 	Remove all characters except letters, digits and $-_.+!*'(),{}|\\^~[]`<>#%";/?:@&=. 
    $route = explode('/', $route); //transforme route en array
    $controllerName = array_shift($route); //modifie route pour retirer le premier elt, et assigne à $controllerName l'élément retiré
    
    //initialisation de l'API (création automatique des classes Controller en fonction des tables de la BDD interrogée)

    if($_ENV["current"] == "dev" && $controllerName == 'init'){
        $dbs = new DatabaseService(null);
        $query_resp = $dbs->query("SELECT table_name FROM information_schema.tables WHERE table_schema = ?", ['mcd-blog-db']);
        //demande à la BDD une liste des noms des tables existantes dans le fichier information_schema.tables
        $rows = $query_resp->statement->fetchAll(PDO::FETCH_COLUMN);
        //récupère tous les noms de tables sous forme d'un tableau indexé de 0 à n = nbre de tables
        foreach ($rows as $tableName) { //$rows = tableau que l'on parcourt, $tableName = un élément du tableau (ici un nom de table donc $tableName)
            $controllerFile = "controllers/$tableName.controller.php"; //URL du fichier à créer si il n'existe pas
            if(!file_exists($controllerFile)){
                $fileContent = "<?php class ".ucfirst($tableName)."Controller extends DatabaseController {\r\n\r\n}?>";   //contenu à mettre dans le fichier à créer
                file_put_contents($controllerFile, $fileContent); //écriture du contenu de $fileContent dans le fichier $controllerFile
                echo ucfirst($tableName)."Controller created\r\n";
            }
        }
        echo 'api initialized';
    }
    
    
    
    
    
    $controllerFilePath = "controllers/$controllerName.controller.php"; //chemin vers les fichiers du dossier controller (attention à la syntaxe!)

    if(!file_exists($controllerFilePath)){
        header('HTTP/1.O 404 Not Found'); //arrête le script si le fichier est introuvable 
        die;

    }
    require_once $controllerFilePath; 
    $controllerClassName = ucfirst($controllerName)."Controller"; //nom de classe à récupérer : "$controllerName avec une majuscule"+"Controller"
    //exemple : si $controllerName == blorbo, $controllerClassName = BlorboController
    $controller = new $controllerClassName($route); //nouvelle instance de classe ayant pour nom $controllerClassName et prenant comme props $route

    $response = $controller->action;
    if(!isset($response)){
    header('HTTP/1.0 404 Not Found');
    die;
    }

    
    echo json_encode($response);

    // echo $controllerClassName . '<br/>'; //affiche le premier elt de la route, ex : route = /tagged/blorbo/ , affiché = tagged
    // echo implode('-', $route); //affiche le reste des élements sous forme de string reliés par un -

    //affiche l'action assignée en fonction de ce qu'a reçu le controlleur
    ?>
