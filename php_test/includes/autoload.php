<?php
/**
 * AUTOLOADER CENTRALISÉ
 * 
 * Ce fichier gère le chargement automatique de toutes les classes du projet.
 * Inclure ce fichier une seule fois au début de chaque page.
 */

// Définir le chemin de base du projet
define('BASE_PATH', dirname(__DIR__));
define('CORE_PATH', BASE_PATH . '/core');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('PAGES_PATH', BASE_PATH . '/pages');
define('DATA_PATH', BASE_PATH . '/data');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('COMPONENTS_PATH', BASE_PATH . '/components');

/**
 * Autoloader principal
 */
spl_autoload_register(function ($classe) {
    // Liste des répertoires où chercher les classes
    $directories = [
        CORE_PATH,
        CORE_PATH . '/heroes',
        CORE_PATH . '/effects',
        CORE_PATH . '/blessings',
        CORE_PATH . '/Models',
        CORE_PATH . '/Services',
    ];
    
    foreach ($directories as $dir) {
        $file = $dir . '/' . $classe . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

/**
 * Helper pour obtenir le chemin vers les ressources publiques
 */
function asset($path) {
    return PUBLIC_PATH . '/' . ltrim($path, '/');
}

/**
 * Helper pour obtenir l'URL relative vers les ressources publiques
 * (pour les liens CSS/JS/images dans le HTML)
 */
function asset_url($path) {
    // Calculer le chemin relatif depuis la page courante vers public/
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // Déterminer la profondeur relative au répertoire php_test
    if (strpos($scriptName, '/pages/game/') !== false || 
        strpos($scriptName, '/pages/auth/') !== false || 
        strpos($scriptName, '/pages/admin/') !== false ||
        strpos($scriptName, '/pages/debug/') !== false) {
        // 2 niveaux de profondeur: pages/game/, pages/auth/, pages/admin/, pages/debug/
        return '../../public/' . ltrim($path, '/');
    } elseif (strpos($scriptName, '/pages/') !== false) {
        // 1 niveau de profondeur: pages/
        return '../public/' . ltrim($path, '/');
    } elseif (strpos($scriptName, '/components/') !== false) {
        // Dans les composants, c'est relatif au fichier qui les inclut
        return '../public/' . ltrim($path, '/');
    }
    // Racine php_test/
    return 'public/' . ltrim($path, '/');
}

/**
 * Calculer le chemin de base relatif vers la racine du projet
 * Utile pour les liens et les formulaires
 */
function getBasePath() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    if (strpos($scriptName, '/pages/game/') !== false || 
        strpos($scriptName, '/pages/auth/') !== false || 
        strpos($scriptName, '/pages/admin/') !== false ||
        strpos($scriptName, '/pages/debug/') !== false) {
        return '../../';
    } elseif (strpos($scriptName, '/pages/') !== false) {
        return '../';
    }
    return '';
}

/**
 * Helper pour inclure un composant
 */
function component($name) {
    $file = COMPONENTS_PATH . '/' . $name . '.php';
    if (file_exists($file)) {
        include $file;
    }
}

/**
 * Démarrer la session si pas déjà fait
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
