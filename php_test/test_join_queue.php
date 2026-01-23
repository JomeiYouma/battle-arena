<?php
session_start();
header('Content-Type: application/json');

// Simuler le POST
$_POST['hero_id'] = 'pedro';

require_once 'classes/MatchQueue.php';

$sessionId = session_id();
echo json_encode([
    'message' => 'Testing join_queue flow',
    'session_id' => $sessionId,
    'action' => 'Simulating POST to join_queue with hero_id=pedro',
    'test' => 'Check logs'
], JSON_PRETTY_PRINT);

// Récupérer stats du héros
$heroes = json_decode(file_get_contents('heros.json'), true);
$heroData = null;
foreach ($heroes as $h) {
    if ($h['id'] === $_POST['hero_id']) {
        $heroData = $h;
        break;
    }
}

if (!$heroData) {
    die(json_encode(['error' => 'Héros invalide']));
}

// Store in session
$_SESSION['queueHeroId'] = $_POST['hero_id'];
$_SESSION['queueStartTime'] = time();
$_SESSION['queueHeroData'] = $heroData;

error_log("TEST: join_queue - sessionId=$sessionId, hero=" . $heroData['name']);

$queue = new MatchQueue();
$result = $queue->findMatch($sessionId, $heroData);

error_log("TEST: join_queue result=" . json_encode($result));

echo json_encode([
    'result' => $result,
    'queue_file' => file_get_contents(__DIR__ . '/data/queue.json')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
