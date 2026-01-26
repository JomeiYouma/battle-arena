<?php
/**
 * RESET SESSION - Nettoie la session et retire de la queue
 * Endpoint simple pour débloquer rapidement lors des tests
 * PROTÉGÉ PAR MOT DE PASSE
 */

require_once __DIR__ . '/auth_helper.php';
requireDebugAuth();

// Retirer de la queue si présent
$sessionId = session_id();
$queueFile = __DIR__ . '/data/queue.json';

if (file_exists($queueFile)) {
    $fp = fopen($queueFile, 'r+');
    if (flock($fp, LOCK_EX)) {
        $queue = json_decode(stream_get_contents($fp), true) ?: [];
        $queue = array_filter($queue, function($entry) use ($sessionId) {
            return $entry['sessionId'] !== $sessionId;
        });
        $queue = array_values($queue); // Réindexer
        
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($queue, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// Détruire la session
session_unset();
session_destroy();

// Rediriger
header("Location: index.php");
exit;
