<?php

/**
 * Classe Router, permet de router les URLs pour un Pattern MVC
 * Le .htaccess doit router toutes les requetes vers l'index
 * Les regles de routages doivent être sous la forme ["ma/route/:param1/:param2"=>["nomController","methodeController"]]
 * Les paramètres sont passés au controlleur par le routeru dans l'ordre de l'URL
 * Le controllerPath est le chemin vers le dossier contenant les controllers, il doit obligatoirement être renseigné
 * Le defaultController doit être renseigné car c'est le controlleur que l'on va appeller si on à pas de regles de routage
 * L'errorController est le controlleur de gestions d'erreurs il peut être renseigné mais ce n'est pas obligatoire
 * Les controllers doivent être sous la forme actionController, par exemple pour l'index on appelera le controlleur indexController,
 * Ils doivent obligatoirement être des classes statiques avec des methodes statiques.
 */
class Router
{

    //instance du singleton router
    private static $_instance;
    //Dossier ou se trouvent les controllers
    private $controllerPath;
    //Controller par default à appeller dans la methode index 
    private $defaultController = array();
    //Controller de gestion d'erreurs en cas d'url mal formées
    private $errorControllerAction = array();
    //Regles de routage
    private $rules = array();
    //Controlleur à appeller
    private $controller;
    //Methode du controlleur à appeller
    private $methodeController;
    //parametres des methodes (url => /:param/:param2)
    private $params;

    /**
     * Empêche la création externe d'instances.
     */
    private function __construct()
    {
        
    }

    /**
     * Empêche la copie externe de l'instance.
     */
    private function __clone()
    {
        
    }

    /**
     * Renvoi de l'instance et initialisation si nécessaire.
     */
    public static function getInstance()
    {
        if (!(self::$_instance instanceof self))
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function getControllerPath()
    {
        return $this->controllerPath;
    }

    public function setControllerPath($path)
    {
        if (!is_dir($path)) //Si ce n'est pas un directory
        {
            throw new Exception('Dossier Controller invalide : ' . $path);
        }
        else
        {
            $this->controllerPath = $path;
        }
    }

    public function setDefaultController($controller, $methode)
    {
        $this->defaultController[$controller] = $methode;
    }

    public function setErrorControllerAction($controller, $action)
    {
        array_push($this->errorControllerAction, array($controller => $action));
    }

    public function addRules($url, $arrayController)
    {
        if ($url[0] !== "/")
        {
            $url = "/" . $url;
        }
        $this->rules[$url] = $arrayController;
    }

    /**
     * Routes les urls vers les controlleurs qui vont bien
     */
    public function doRouter()
    {
        $URI = filter_input(INPUT_SERVER, "REQUEST_URI");
        $SCRIPT = filter_input(INPUT_SERVER, "SCRIPT_NAME");
        $arrayUrls = $this->formatUrl($URI, $SCRIPT);
        if (count($this->rules) > 0) //Si on a des regles de routage
        {
            foreach ($this->rules as $key => $value)
            {
                $result = $this->doesUrlMatch($arrayUrls, $key);
                if (!empty($result) && is_array($result))
                {
                    //Affectation des attributs privés
                    $this->controller = $value['controller'];
                    $this->methodeController = $value['action'];
                    $this->params = $result;
                    break;
                }
                elseif($result === true) //Si result est un boolean c'est que l'url correspond à une regle de routage mais sans parametres
                {
                    //Affectation des attributs privés
                    $this->controller = $value['controller'];
                    $this->methodeController = $value['action'];
                    break;
                }
            }
            $pathController = (!empty($this->controller)) ? $this->controllerPath . "/" . $this->controller . "Controller.php" : null;
            $nomController = (!empty($this->controller)) ? $this->controller . "Controller" : null;
            $nomMethode = (!empty($this->methodeController)) ? $this->methodeController : null;
            /* Inclusion du controller, appel de la methode */
            if ($pathController !== null && $nomController !== null && $nomMethode !== null)
            {
                if (is_file($pathController))
                {
                    include $pathController;
                    if (method_exists($nomController, $this->methodeController)) //Si la methode est appellable (existe)
                    {
                        //Appel de la methode
                        if (!empty($this->params))
                        {
                            call_user_func_array(array($nomController,$nomMethode),$this->params);
                        }
                        else
                        {
                            $nomController::$nomMethode();
                        }
                    }
                    else
                    {
                        throw new Exception("Method " . $this->methodeController . " is not callable for the controller " . $this->controller);
                    }
                }
                else
                {
                    //Leve l'exception
                    throw new Exception("Controller " . $this->controller . " is not a valid controller, please make sure this controller is a file.");
                }
            }
            else
            {
                $this->callDefaultController();
            }
        }
        else
        {
            //Si on a pas de regles de routages on appelle le controlleur par defaut
            $this->callDefaultController();
        }
    }

    /**
     * Supprime les sous dossiers d'une url si nécessaire
     * @param string $url
     * @return string
     */
    private function formatUrl($url, $script)
    {
        $tabUrl = explode('/', $url);
        $tabScript = explode('/', $script);
        $size = count($tabScript);
        for ($i = 0; $i < $size; $i++)
        {
            if ($tabScript[$i] == $tabUrl[$i])
            {
                unset($tabUrl[$i]);
            }
        }
        return array_values($tabUrl);
    }

    /*
     * Permet de check si l'url corresponds à une regle de routage
     * Renvoi un array avec cle valeur pour les parametres si oui, false sinon
     */

    private function doesUrlMatch($arrayUrls, $rule)
    {
        $arrayRules = $this->clearEmptyValues(explode("/", $rule));
        if (count($arrayUrls) == count($arrayRules))
        {
            $result = array();
            foreach ($arrayRules as $key => $value)
            {
                if ($value[0] == ':')
                {
                    $value = substr($value, 1); //Supprime les : de la clé
                    $result[$value] = $arrayUrls[$key];
                }
                else
                {
                    if ($value != $arrayUrls[$key])
                    {
                        return false;
                    }
                }
            }
            if (!empty($result)) //si l'array contient des parametres
            {
                return $result;
            }
            else
            {
                return true;
            }

            unset($result);
        }
        return false;
    }

    /**
     * Supprime les entrées vides du tableau et réorganise les clés
     * @param type $array
     * @return type
     */
    private function clearEmptyValues($array)
    {
        for ($i = 0; $i < count($array); $i++)
        {
            if ($array[$i] == "")
            {
                unset($array[$i]);
            }
        }
        return array_values($array);
    }

    /**
     * Inclue le controlleur par défault et appelle sa methode index
     * @throws Exception
     */
    private function callDefaultController()
    {
        //Si il n'y a pas de regles de routage, on appele le default controller
        if (count($this->defaultController) > 0)
        {
            $controller = array_keys($this->defaultController);
            $controllerName = $controller[0] . "Controller";
            $methode = $this->defaultController[$controller[0]];
            //Inclusion controller par default
            if (is_file($this->controllerPath . "/" . $controllerName . ".php"))
            {
                include $this->controllerPath . "/" . $controllerName . ".php";
                $arrayController = array($controllerName, $methode);
                //Vérifie si la methode est appellable pour la classe concerné
                if (is_callable($arrayController))
                {
                    //Appel methode default
                    $controllerName::$methode();
                }
                else
                {
                    throw new Exception("The methode " . $methode . " is not callable for class " . $controllerName);
                }
            }
            else
            {
                throw new Exception("The default controller isn't a file");
            }
        }
        else
        {
            throw new Exception("Please register your default controller");
        }
    }

}

?>
