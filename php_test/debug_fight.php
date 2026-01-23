<?php
// Script de debug séquentiel qui force un combat
$apiUrl = 'http://localhost/nodeTest2/mood-checker/php_test/api.php';
$cookieA = 'debug_cookie_A.txt';
$cookieB = 'debug_cookie_B.txt';

if (file_exists($cookieA)) unlink($cookieA);
if (file_exists($cookieB)) unlink($cookieB);

function call($action, $cookieFile, $postData = []) {
    global $apiUrl;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '?action=' . $action);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($postData)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

echo "1. Player A joins queue...\n";
$resA = call('join_queue', $cookieA, ['hero_id' => 'brutor']);
print_r($resA);

echo "2. Player B joins queue...\n";
$resB = call('join_queue', $cookieB, ['hero_id' => 'ulroc']);
print_r($resB);

if (($resB['status'] ?? '') !== 'matched') die("Match not found!\n");
$matchId = $resB['matchId'];
echo "MATCH ID: $matchId\n";

echo "3. Player A moves (attack)...\n";
$moveA = call('submit_move', $cookieA, ['match_id' => $matchId, 'move' => 'attack']);
print_r($moveA);

echo "4. Player B moves (attack)...\n";
$moveB = call('submit_move', $cookieB, ['match_id' => $matchId, 'move' => 'attack']);
print_r($moveB);

echo "5. Checking Status (Player A)...\n";
$statusA = call('poll_status', $cookieA, ['match_id' => $matchId]);
echo "Turn: " . $statusA['turn'] . "\n";
echo "Logs count: " . count($statusA['logs']) . "\n";
// print_r($statusA['logs']);

if ($statusA['turn'] > 1) {
    echo "SUCCESS: Turn advanced!\n";
} else {
    echo "FAIL: Still turn 1\n";
}
