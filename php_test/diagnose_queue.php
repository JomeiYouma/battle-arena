<?php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    require_once 'classes/MatchQueue.php';

    $queue = new MatchQueue();
    
    // Récupérer la file actuelle
    $queueFile = __DIR__ . '/data/queue.json';
    $queueContent = file_get_contents($queueFile);
    $queueData = json_decode($queueContent, true) ?: [];
    
    echo json_encode([
        'status' => 'diagnostic',
        'message' => 'Queue Status Check',
        'queue_file_exists' => file_exists($queueFile),
        'queue_content' => $queueData,
        'queue_count' => count($queueData),
        'current_time' => time(),
        'timeout_seconds' => 30,
        'test_session_id' => session_id()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
