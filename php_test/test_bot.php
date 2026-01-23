<?php
// Script CLI pour simuler un jour 2
// Usage: php test_bot.php [suffix] [hero_id]
$suffix = $argv[1] ?? 'A';
$heroId = $argv[2] ?? 'brutor';

echo "Démarrage du BOT joueur $suffix ($heroId)...\n";

$apiUrl = 'http://localhost/nodeTest2/mood-checker/php_test/api.php';
$cookieFile = __DIR__ . '/bot_cookie_' . $suffix . '.txt';

if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Join Queue
echo "Joining queue...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl . '?action=join_queue');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'hero_id=brutor');
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$json = json_decode($response, true);
echo "Join response: " . print_r($json, true) . "\n";

if (!$json || ($json['status'] !== 'waiting' && $json['status'] !== 'matched')) {
    die("Failed to join queue\n");
}

$matchId = $json['matchId'] ?? null;

// 2. Poll loop
while (true) {
    if (!$matchId) {
        // Poll queue
        echo "Polling queue...\n";
        curl_setopt($ch, CURLOPT_URL, $apiUrl . '?action=poll_status');
        $resp = curl_exec($ch);
        $data = json_decode($resp, true);
        
        if ($data['status'] === 'matched') {
            $matchId = $data['matchId'];
            echo "MATCH FOUND! ID: $matchId\n";
        }
    } else {
        // Poll combat
        echo "Polling combat $matchId...\n";
        curl_setopt($ch, CURLOPT_URL, $apiUrl . '?action=poll_status&match_id=' . $matchId);
        $resp = curl_exec($ch);
        $data = json_decode($resp, true);
        
        if ($data['status'] === 'active') {
            if ($data['waiting_for_me']) {
                echo "MY TURN! Attacking...\n";
                // Submit move
                sleep(1);
                curl_setopt($ch, CURLOPT_URL, $apiUrl . '?action=submit_move');
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'match_id=' . $matchId . '&move=attack');
                $moveResp = curl_exec($ch);
                echo "Move response: $moveResp\n";
            } else {
                echo "Waiting for opponent...\n";
            }
        } elseif ($data['status'] === 'finished' || $data['isOver']) {
            echo "Combat finished!\n";
            break;
        }
    }
    
    sleep(2);
}
curl_close($ch);
