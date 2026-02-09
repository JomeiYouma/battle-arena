<?php
/**
 * FRONT CONTROLLER - Point d'entrée unique de l'application MVC
 * Toutes les requêtes passent par ce fichier via .htaccess
 */

// Définir le chemin de base
define('APP_ROOT', dirname(__DIR__));

// Charger l'autoloader existant
require_once APP_ROOT . '/includes/autoload.php';

// Charger les classes MVC core
require_once CORE_PATH . '/Router.php';
require_once CORE_PATH . '/Controller.php';
require_once CORE_PATH . '/View.php';

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialiser le routeur avec le bon basePath
$router = new Router('/nodeTest2/mood-checker/php_test/app');

// Charger les routes
$router->loadRoutes(APP_ROOT . '/config/routes.php');

// Résoudre la requête
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Retirer le basePath du chemin si présent
$basePath = '/nodeTest2/mood-checker/php_test/app';
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}
if (empty($requestUri)) {
    $requestUri = '/';
}

$match = $router->resolve($requestMethod, $requestUri);

if ($match === null) {
    // Route non trouvée - 404
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 - Page non trouvée</title>';
    echo '<link rel="stylesheet" href="/nodeTest2/mood-checker/php_test/public/css/style.css"></head>';
    echo '<body><h1>404 - Page non trouvée</h1>';
    echo '<p>La page demandée n\'existe pas.</p>';
    echo '<p>URI: ' . htmlspecialchars($_SERVER['REQUEST_URI']) . '</p>';
    echo '<a href="/nodeTest2/mood-checker/php_test/app/">Retour à l\'accueil</a></body></html>';
    exit;
}

// Charger et instancier le contrôleur
$controllerName = $match['controller'];
$actionName = $match['action'];
$params = $match['params'];

// Charger le fichier du contrôleur
$controllerFile = APP_ROOT . '/app/Controllers/' . $controllerName . '.php';

if (!file_exists($controllerFile)) {
    throw new Exception("Contrôleur non trouvé: {$controllerName}");
}

require_once $controllerFile;

if (!class_exists($controllerName)) {
    throw new Exception("Classe contrôleur non trouvée: {$controllerName}");
}

// Instancier et exécuter
$controller = new $controllerName();
$controller->setParams($params);

if (!method_exists($controller, $actionName)) {
    throw new Exception("Action non trouvée: {$controllerName}::{$actionName}");
}

// Appeler l'action
$controller->$actionName();
