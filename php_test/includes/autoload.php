<?php
/** AUTOLOADER - Chargement automatique des classes du projet */

define('BASE_PATH', dirname(__DIR__));
define('CORE_PATH', BASE_PATH . '/core');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('PAGES_PATH', BASE_PATH . '/pages');
define('DATA_PATH', BASE_PATH . '/data');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('COMPONENTS_PATH', BASE_PATH . '/components');

spl_autoload_register(function ($classe) {
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

function asset($path) {
    return PUBLIC_PATH . '/' . ltrim($path, '/');
}

function asset_url($path) {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    if (strpos($scriptName, '/pages/game/') !== false || 
        strpos($scriptName, '/pages/auth/') !== false || 
        strpos($scriptName, '/pages/admin/') !== false ||
        strpos($scriptName, '/pages/debug/') !== false) {
        return '../../public/' . ltrim($path, '/');
    } elseif (strpos($scriptName, '/pages/') !== false) {
        return '../public/' . ltrim($path, '/');
    } elseif (strpos($scriptName, '/components/') !== false) {
        return '../public/' . ltrim($path, '/');
    }
    return 'public/' . ltrim($path, '/');
}

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

function component($name) {
    $file = COMPONENTS_PATH . '/' . $name . '.php';
    if (file_exists($file)) {
        include $file;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
