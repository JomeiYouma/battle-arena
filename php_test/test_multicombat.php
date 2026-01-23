<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    require_once 'classes/Personnage.php';
    require_once 'classes/Barbare.php';
    require_once 'classes/Electrique.php';
    require_once 'classes/Guerrier.php';
    require_once 'classes/Guerisseur.php';
    require_once 'classes/Necromancien.php';
    require_once 'classes/Pyromane.php';
    require_once 'classes/Aquatique.php';
    require_once 'classes/StatusEffect.php';
    require_once 'classes/Combat.php';
    require_once 'classes/MultiCombat.php';

    $p1Data = [
        "id" => "pedro",
        "name" => "Pedro",
        "type" => "Barbare",
        "pv" => 110,
        "atk" => 20,
        "def" => 0,
        "speed" => 10,
    ];

    $p2Data = [
        "id" => "pikratchu",
        "name" => "Pikratchu",
        "type" => "Electrique",
        "pv" => 90,
        "atk" => 18,
        "def" => 3,
        "speed" => 11,
    ];

    $multiCombat = MultiCombat::create($p1Data, $p2Data);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'MultiCombat créé avec succès',
        'player' => $multiCombat->getPlayer()->getName(),
        'enemy' => $multiCombat->getEnemy()->getName(),
        'player_pv' => $multiCombat->getPlayer()->getPv(),
        'enemy_pv' => $multiCombat->getEnemy()->getPv()
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
