<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'queue_hero_id' => $_SESSION['queueHeroId'] ?? 'NOT SET',
    'timestamp' => time()
]);
?>
