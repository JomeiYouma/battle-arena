<?php
require_once __DIR__ . '/classes/Database.php';

$pdo = Database::getInstance();
$stmt = $pdo->prepare('SELECT tm.position, tm.hero_id, tm.blessing_id, h.* FROM team_members tm LEFT JOIN heroes h ON tm.hero_id = h.hero_id WHERE tm.team_id = ? ORDER BY tm.position ASC');
$stmt->execute([4]);
$members = $stmt->fetchAll();

echo "Members count: " . count($members) . "\n";

$json = json_encode($members, JSON_HEX_APOS | JSON_HEX_QUOT);
if ($json === false) {
    echo "JSON ERROR: " . json_last_error_msg() . "\n";
    // Debug each field
    foreach ($members as $i => $member) {
        echo "\nMember $i:\n";
        foreach ($member as $key => $value) {
            $testJson = json_encode($value);
            if ($testJson === false) {
                echo "  PROBLEM with '$key': " . json_last_error_msg() . " - value type: " . gettype($value) . "\n";
            }
        }
    }
} else {
    echo "JSON OK, length: " . strlen($json) . "\n";
    echo substr($json, 0, 800) . "...\n";
}
