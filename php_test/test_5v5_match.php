<?php
/**
 * TEST 5v5 MATCH GENERATOR
 * Génère un match 5v5 fictif pour tester l'UI
 */

session_start();

// Autoloader
function chargerClasse($classe) {
    if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
        require __DIR__ . '/classes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require __DIR__ . '/classes/effects/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/blessings/' . $classe . '.php')) {
        require __DIR__ . '/classes/blessings/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/heroes/' . $classe . '.php')) {
        require __DIR__ . '/classes/heroes/' . $classe . '.php';
        return;
    }
}
spl_autoload_register('chargerClasse');

// Charger quelques héros pour le test
$heroesData = [
    [
        'id' => 'brutor',
        'name' => 'Brutor le Brûlant',
        'type' => 'Pyromane',
        'pv' => 100,
        'atk' => 22,
        'def' => 5,
        'speed' => 10,
        'images' => ['p1' => 'media/heroes/brutor.png', 'p2' => 'media/heroes/brutor.png']
    ],
    [
        'id' => 'aquatique',
        'name' => 'Aquatique',
        'type' => 'Aquamancien',
        'pv' => 110,
        'atk' => 18,
        'def' => 8,
        'speed' => 12,
        'images' => ['p1' => 'media/heroes/aquatique.png', 'p2' => 'media/heroes/aquatique.png']
    ],
    [
        'id' => 'barbare',
        'name' => 'Barbare',
        'type' => 'Guerrier',
        'pv' => 130,
        'atk' => 20,
        'def' => 10,
        'speed' => 8,
        'images' => ['p1' => 'media/heroes/barbare.png', 'p2' => 'media/heroes/barbare.png']
    ],
    [
        'id' => 'rougemont',
        'name' => 'Rougemont',
        'type' => 'Chevalier',
        'pv' => 120,
        'atk' => 19,
        'def' => 9,
        'speed' => 9,
        'images' => ['p1' => 'media/heroes/rougemont.png', 'p2' => 'media/heroes/rougemont.png']
    ],
    [
        'id' => 'brute',
        'name' => 'Brute',
        'type' => 'Colosse',
        'pv' => 140,
        'atk' => 21,
        'def' => 7,
        'speed' => 7,
        'images' => ['p1' => 'media/heroes/brute.png', 'p2' => 'media/heroes/brute.png']
    ],
];

// Créer 2 équipes de 5 héros
$team1 = array_slice($heroesData, 0, 5);
$team2 = array_slice($heroesData, 0, 5);
// Alterner les héros pour que ce ne soit pas exact
shuffle($team2);
$team2 = array_slice($team2, 0, 5);

// Ajouter la propriété isDead à tous les héros (tous vivants au départ)
foreach ($team1 as &$hero) {
    $hero['isDead'] = false;
}
foreach ($team2 as &$hero) {
    $hero['isDead'] = false;
}
unset($hero); // Break reference

// Créer l'ID du match
$matchId = uniqid('match_5v5_test_');
$now = time();

// Créer l'état du match 5v5
$matchData = [
    'id' => $matchId,
    'created_at' => $now,
    'status' => 'active',
    'mode' => '5v5',
    'turn' => 1,
    'player1' => [
        'session' => session_id(),
        'display_name' => 'Team Testeur 1',
        'user_id' => null,
        'team_id' => 1,
        'heroes' => $team1,
        'last_poll' => $now
    ],
    'player2' => [
        'session' => 'bot_' . uniqid(),
        'display_name' => 'Team Bot 2',
        'user_id' => null,
        'is_bot' => true,
        'team_id' => 2,
        'heroes' => $team2,
        'last_poll' => $now
    ],
    'logs' => ["🏆 MATCH 5v5 DE TEST"],
    'current_turn_actions' => [],
    'last_update' => $now
];

// Créer le répertoire s'il n'existe pas
$matchesDir = __DIR__ . '/data/matches/';
if (!is_dir($matchesDir)) {
    mkdir($matchesDir, 0777, true);
}

// Sauvegarder le match
$matchFile = $matchesDir . $matchId . '.json';
file_put_contents($matchFile, json_encode($matchData, JSON_PRETTY_PRINT));

// NOTE: Pas de création d'objet TeamCombat pour le test UI
// L'UI testera juste l'affichage, pas la logique du combat

// Stocker dans session et rediriger
$_SESSION['matchId'] = $matchId;
$_SESSION['test_5v5_mode'] = true;

// Afficher un message ou rediriger directement
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test 5v5 Match</title>
    <style>
        body {
            background: #1a1a1a;
            color: #fff;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            padding: 2rem;
            background: rgba(0, 0, 0, 0.8);
            border: 2px solid #ff6b35;
            border-radius: 10px;
        }
        h1 {
            color: #ffd700;
            margin-bottom: 1rem;
        }
        p {
            margin: 1rem 0;
            font-size: 1.1rem;
        }
        .match-id {
            background: rgba(255, 107, 53, 0.2);
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            font-family: monospace;
            word-break: break-all;
        }
        .button {
            background: linear-gradient(135deg, #ff6b35, #ff8c42);
            color: #000;
            border: 2px solid #ffd700;
            padding: 1rem 2rem;
            font-size: 1rem;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 1rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .button:hover {
            box-shadow: 0 0 15px rgba(255, 107, 53, 0.6);
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏆 Match 5v5 de Test Généré ✅</h1>
        <p>Un match 5v5 fictif a été créé pour tester l'UI</p>
        
        <div class="match-id">
            Match ID: <strong><?php echo htmlspecialchars($matchId); ?></strong>
        </div>
        
        <p>Équipe 1: 5 héros testeurs</p>
        <p>Équipe 2: 5 héros Bot</p>
        
        <a href="multiplayer-combat.php?match_id=<?php echo urlencode($matchId); ?>" class="button">
            ⚔️ Lancer le combat
        </a>
        
        <br><br>
        
        <a href="index.php" class="button" style="background: #666;">← Retour</a>
    </div>
</body>
</html>
