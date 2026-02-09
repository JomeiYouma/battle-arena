<?php
/**
 * ROUTES - Définition des routes de l'application
 * Format: 'METHOD /path' => [Controller::class, 'method']
 */

return [
    // Auth routes
    'GET /login' => ['AuthController', 'showLogin'],
    'POST /login' => ['AuthController', 'login'],
    'GET /register' => ['AuthController', 'showRegister'],
    'POST /register' => ['AuthController', 'register'],
    'POST /logout' => ['AuthController', 'logout'],
    
    // Account routes
    'GET /account' => ['AccountController', 'index'],
    'POST /account/team/create' => ['AccountController', 'createTeam'],
    'POST /account/team/delete' => ['AccountController', 'deleteTeam'],
    'POST /account/team/add-hero' => ['AccountController', 'addHeroToTeam'],
    'POST /account/team/remove-hero' => ['AccountController', 'removeHeroFromTeam'],
    
    // Game routes
    'GET /game/single' => ['GameController', 'singlePlayer'],
    'POST /game/single' => ['GameController', 'singlePlayer'],
    'GET /game/multiplayer' => ['GameController', 'multiplayer'],
    'GET /game/multiplayer-selection' => ['GameController', 'multiplayerSelection'],
    'POST /game/multiplayer-selection' => ['GameController', 'multiplayerSelection'],
    'GET /game/multiplayer-combat' => ['GameController', 'multiplayerCombat'],
    'POST /game/multiplayer-combat' => ['GameController', 'multiplayerCombat'],
    'GET /game/5v5-setup' => ['GameController', 'setup5v5'],
    'GET /game/5v5-selection' => ['GameController', 'selection5v5'],
    'POST /game/5v5-selection' => ['GameController', 'selection5v5'],
    'GET /game/simulation' => ['GameController', 'simulation'],
    'POST /game/simulation' => ['GameController', 'simulation'],
    
    // API routes (gardera une logique séparée pour compatibilité)
    'GET /api' => ['ApiController', 'handle'],
    'POST /api' => ['ApiController', 'handle'],
    
    // Legal & Debug
    'GET /legal' => ['LegalController', 'index'],
    'GET /debug' => ['DebugController', 'index'],
    
    // Home
    'GET /' => ['HomeController', 'index'],
    'POST /' => ['HomeController', 'index'],
];
