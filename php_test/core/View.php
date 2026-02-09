<?php
/**
 * VIEW - Système de rendu des vues avec layouts
 */

class View {
    private string $viewsPath;
    private string $layoutsPath;
    private array $globalData = [];
    
    public function __construct() {
        $this->viewsPath = dirname(__DIR__) . '/views';
        $this->layoutsPath = $this->viewsPath . '/layouts';
    }
    
    /**
     * Définir des données globales accessibles à toutes les vues
     */
    public function setGlobal(string $key, $value): void {
        $this->globalData[$key] = $value;
    }
    
    /**
     * Rendre une vue
     * 
     * @param string $viewName Nom de la vue (ex: 'account/index')
     * @param array $data Données à passer à la vue
     * @param string|null $layout Nom du layout (null = pas de layout)
     */
    public function render(string $viewName, array $data = [], ?string $layout = 'main'): void {
        // Fusionner données globales et locales
        $data = array_merge($this->globalData, $data);
        
        // Rendre le contenu de la vue
        $content = $this->renderView($viewName, $data);
        
        if ($layout !== null) {
            // Injecter le contenu dans le layout
            $data['content'] = $content;
            echo $this->renderView('layouts/' . $layout, $data);
        } else {
            echo $content;
        }
    }
    
    /**
     * Rendre une vue et retourner le HTML
     */
    private function renderView(string $viewName, array $data): string {
        $viewFile = $this->viewsPath . '/' . $viewName . '.php';
        
        if (!file_exists($viewFile)) {
            throw new Exception("Vue non trouvée: {$viewName} ({$viewFile})");
        }
        
        // Extraire les données en variables locales
        extract($data);
        
        // Capturer la sortie
        ob_start();
        require $viewFile;
        return ob_get_clean();
    }
    
    /**
     * Inclure une vue partielle (pour réutilisation)
     */
    public function partial(string $partialName, array $data = []): string {
        return $this->renderView('partials/' . $partialName, $data);
    }
    
    /**
     * Échapper une chaîne pour l'affichage HTML
     */
    public static function e(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Helper pour les assets (CSS, JS, images)
     */
    public static function asset(string $path): string {
        return '/nodeTest2/mood-checker/php_test/public/' . ltrim($path, '/');
    }
    
    /**
     * Helper pour générer des URLs (routes MVC)
     */
    public static function url(string $path): string {
        return '/nodeTest2/mood-checker/php_test/app/' . ltrim($path, '/');
    }
    
    /**
     * Helper pour les URLs relatives simples
     */
    public static function basePath(): string {
        return '/nodeTest2/mood-checker/php_test';
    }
}
