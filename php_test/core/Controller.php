<?php
/**
 * CONTROLLER - Classe de base pour tous les contrôleurs
 */

abstract class Controller {
    protected View $view;
    protected array $params = [];
    
    public function __construct() {
        $this->view = new View();
    }
    
    /**
     * Définir les paramètres de route
     */
    public function setParams(array $params): void {
        $this->params = $params;
    }
    
    /**
     * Rendre une vue avec des données
     */
    protected function render(string $viewName, array $data = [], ?string $layout = 'main'): void {
        $this->view->render($viewName, $data, $layout);
    }
    
    /**
     * Rendre une vue partielle sans layout
     */
    protected function renderPartial(string $viewName, array $data = []): void {
        $this->view->render($viewName, $data, null);
    }
    
    /**
     * Redirection HTTP
     */
    protected function redirect(string $url, int $statusCode = 302): void {
        // Ajouter le basePath si l'URL commence par /
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = '/nodeTest2/mood-checker/php_test/app' . $url;
        }
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
    
    /**
     * Réponse JSON
     */
    protected function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Vérifier si l'utilisateur est connecté, sinon rediriger
     */
    protected function requireAuth(string $redirectTo = '/login'): void {
        if (!User::isLoggedIn()) {
            $this->redirect($redirectTo);
        }
    }
    
    /**
     * Vérifier si l'utilisateur est admin
     */
    protected function requireAdmin(string $redirectTo = '/'): void {
        if (!User::isLoggedIn() || !User::isAdmin()) {
            $this->redirect($redirectTo);
        }
    }
    
    /**
     * Récupérer un paramètre POST
     */
    protected function post(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }
    
    /**
     * Récupérer un paramètre GET
     */
    protected function get(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Récupérer le corps JSON de la requête
     */
    protected function getJsonBody(): ?array {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    /**
     * Vérifier si la requête est POST
     */
    protected function isPost(): bool {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    
    /**
     * Vérifier si c'est une requête AJAX
     */
    protected function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Définir un message flash en session
     */
    protected function flash(string $type, string $message): void {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    /**
     * Récupérer et consommer le message flash
     */
    protected function getFlash(): ?array {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}
