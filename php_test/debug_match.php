<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (!isset($_GET['match_id'])) {
    die(json_encode(['error' => 'match_id manquant']));
}

$matchId = $_GET['match_id'];
$matchFile = __DIR__ . '/data/matches/' . $matchId . '.json';

if (!file_exists($matchFile)) {
    die(json_encode(['error' => 'Match non trouvé']));
}

$metaData = json_decode(file_get_contents($matchFile), true);

echo json_encode([
    'match_id' => $matchId,
    'player1_hero' => $metaData['player1']['hero'] ?? null,
    'player2_hero' => $metaData['player2']['hero'] ?? null,
    'player1_hero_type' => gettype($metaData['player1']['hero']),
    'player2_hero_type' => gettype($metaData['player2']['hero']),
    'full_match_data' => $metaData
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
