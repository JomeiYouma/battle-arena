<?php
/**
 * ROUTER - Système de routage simple pour l'application MVC
 */

class Router {
    private array $routes = [];
    private string $basePath = '';
    
    public function __construct(string $basePath = '') {
        $this->basePath = rtrim($basePath, '/');
    }
    
    /**
     * Charger les routes depuis un fichier de configuration
     */
    public function loadRoutes(string $routesFile): void {
        if (file_exists($routesFile)) {
            $routes = require $routesFile;
            $this->routes = $routes;
        }
    }
    
    /**
     * Ajouter une route manuellement
     */
    public function add(string $method, string $path, string $controller, string $action): void {
        $key = strtoupper($method) . ' ' . $path;
        $this->routes[$key] = [$controller, $action];
    }
    
    /**
     * Résoudre la requête courante
     * @return array|null [controllerClass, method, params] ou null si non trouvé
     */
    public function resolve(string $requestMethod, string $requestUri): ?array {
        // Nettoyer l'URI
        $uri = parse_url($requestUri, PHP_URL_PATH);
        
        // Retirer le basePath si présent
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }
        
        $uri = '/' . trim($uri, '/');
        if ($uri === '/') {
            $uri = '/';
        }
        
        $method = strtoupper($requestMethod);
        
        // Chercher une correspondance exacte
        $routeKey = $method . ' ' . $uri;
        if (isset($this->routes[$routeKey])) {
            return [
                'controller' => $this->routes[$routeKey][0],
                'action' => $this->routes[$routeKey][1],
                'params' => []
            ];
        }
        
        // Chercher une correspondance avec paramètres (ex: /user/{id})
        foreach ($this->routes as $route => $handler) {
            list($routeMethod, $routePath) = explode(' ', $route, 2);
            
            if ($routeMethod !== $method) {
                continue;
            }
            
            // Convertir {param} en regex
            $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $uri, $matches)) {
                // Extraire seulement les paramètres nommés
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                return [
                    'controller' => $handler[0],
                    'action' => $handler[1],
                    'params' => $params
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Générer une URL à partir d'un nom de route
     */
    public function url(string $path, array $params = []): string {
        $url = $this->basePath . $path;
        
        // Remplacer les paramètres
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }
        
        return $url;
    }
    
    /**
     * Obtenir toutes les routes enregistrées
     */
    public function getRoutes(): array {
        return $this->routes;
    }
}
