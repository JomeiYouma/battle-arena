<?php
/** DEBUG_TEAMS - Test données équipes */

require_once __DIR__ . '/../../includes/autoload.php';

$pdo = Database::getInstance();

echo "=== USERS ===\n";
$stmt = $pdo->query('SELECT id, username FROM users LIMIT 5');
foreach ($stmt as $row) {
    echo $row['id'] . ': ' . $row['username'] . "\n";
}

echo "\n=== TEAMS ===\n";
$stmt = $pdo->query('
    SELECT t.id, t.user_id, t.team_name, t.is_active, COUNT(tm.id) as heroes 
    FROM teams t 
    LEFT JOIN team_members tm ON t.id = tm.team_id 
    GROUP BY t.id 
    LIMIT 10
');
foreach ($stmt as $row) {
    echo $row['id'] . ': ' . $row['team_name'] . ' (user=' . $row['user_id'] . ', heroes=' . $row['heroes'] . ', active=' . $row['is_active'] . ")\n";
}
