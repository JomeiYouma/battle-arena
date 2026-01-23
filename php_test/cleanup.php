<?php
/**
 * Cleanup script to reset broken match state
 * This allows testing a fresh multiplayer session
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the match ID from the URL
$matchId = $_GET['match_id'] ?? null;

if (!$matchId) {
    die("No match_id provided. Use: cleanup.php?match_id=match_69736ec683576");
}

$dataDir = __DIR__ . '/data/matches/';
$stateFile = $dataDir . $matchId . '.state';
$jsonFile = $dataDir . $matchId . '.json';

$deleted = false;

// Delete the .state file
if (file_exists($stateFile)) {
    if (unlink($stateFile)) {
        echo "✓ Deleted state file: $stateFile<br>";
        $deleted = true;
    } else {
        echo "✗ Failed to delete state file<br>";
    }
}

// Reset the JSON file (clear actions and logs)
if (file_exists($jsonFile)) {
    $data = json_decode(file_get_contents($jsonFile), true);
    if ($data) {
        $data['current_turn_actions'] = [];
        $data['logs'] = ["Le match a été réinitialisé"];
        $data['turn'] = 1;
        $data['status'] = 'active';
        $data['last_update'] = time();
        
        if (file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT))) {
            echo "✓ Reset match JSON file: $jsonFile<br>";
            $deleted = true;
        }
    }
}

// Clear session
$_SESSION['matchId'] = null;
$_SESSION['queueHeroId'] = null;
$_SESSION['queueHeroData'] = null;

if ($deleted) {
    echo "<br><strong>✓ Cleanup completed!</strong><br>";
    echo "You can now start a fresh multiplayer match.<br>";
    echo '<a href="index.php">Back to Main Menu</a>';
} else {
    echo "<br><strong>✗ No cleanup was necessary</strong><br>";
    echo "The match state appears to be clean.<br>";
    echo '<a href="index.php">Back to Main Menu</a>';
}
?>
