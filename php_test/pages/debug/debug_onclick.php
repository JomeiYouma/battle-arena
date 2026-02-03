<?php
require_once __DIR__ . "/../../includes/autoload.php";

$pdo = Database::getInstance();
$stmt = $pdo->prepare("SELECT t.*, COUNT(tm.id) as hero_count FROM teams t LEFT JOIN team_members tm ON t.id = tm.team_id WHERE t.user_id = ? AND t.is_active = 1 GROUP BY t.id HAVING hero_count = 5");
$stmt->execute([1]);
$userTeams = $stmt->fetchAll();

foreach ($userTeams as $team) {
    $stmtMembers = $pdo->prepare("SELECT tm.position, tm.hero_id, tm.blessing_id, h.* FROM team_members tm LEFT JOIN heroes h ON tm.hero_id = h.hero_id WHERE tm.team_id = ? ORDER BY tm.position ASC");
    $stmtMembers->execute([$team["id"]]);
    $members = $stmtMembers->fetchAll();
    
    // Nouvelle m�thode
    $teamNameJson = json_encode($team["team_name"]);
    $membersJson = json_encode($members);
    
    $onclick = "selectTeam(" . $team["id"] . ", " . htmlspecialchars($teamNameJson, ENT_QUOTES, "UTF-8") . ", " . htmlspecialchars($membersJson, ENT_QUOTES, "UTF-8") . ")";
    
    echo "onclick (first 300 chars):\n";
    echo substr($onclick, 0, 300) . "...\n\n";
    
    // V�rifier que c''est du HTML valide
    echo "Quotes check - contains unescaped double quotes in data: " . (strpos($onclick, "\"Trudor\"") !== false ? "YES (BAD)" : "NO (GOOD)") . "\n";
}
