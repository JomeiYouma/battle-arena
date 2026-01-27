<?php
// Autoloader (AVANT session_start pour la désérialisation des objets StatusEffect)
function chargerClasse($classe) {
    // Chercher dans classes/
    if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
        require __DIR__ . '/classes/' . $classe . '.php';
        return;
    }
    // Chercher dans classes/effects/
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require __DIR__ . '/classes/effects/' . $classe . '.php';
        return;
    }
}
spl_autoload_register('chargerClasse');
session_start();

// --- LOGIQUE DE RESET GLOBALE ---
// Doit être ici car les formulaires de reset sont soumis vers index.php
if (isset($_POST['logout']) || isset($_POST['new_game'])) {
    // Préserver les données de connexion
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;
    
    // Nettoyer les données de combat
    unset($_SESSION['combat']);
    unset($_SESSION['hero_img']);
    unset($_SESSION['enemy_img']);
    unset($_SESSION['hero_id']);
    unset($_SESSION['enemy_id']);
    unset($_SESSION['combat_recorded']);
    
    // Restaurer les données de connexion si elles existaient
    if ($userId !== null) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
    }
    
    // Afficher le menu principal (pas de mode choisi)
    $modeChoisi = null;
} else {
    $modeChoisi = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mode'])) {
        $modeChoisi = $_POST['mode'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horus Battle Arena</title>
    <link rel="icon" href="./media/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <h1>Horus Battle Arena</h1>
    <?php if (User::isLoggedIn()): ?>
        <div class="user-badge">
            ⚔️ <?php echo htmlspecialchars(User::getCurrentUsername()); ?>
        </div>
    <?php endif; ?>

    <?php if ($modeChoisi === null): ?>

        <!-- DÉCOR ANIMÉ - Braises flottantes -->
        <div class="embers-container">
            <div class="ember"></div>
            <div class="ember"></div>
            <div class="ember"></div>
            <div class="ember"></div>
            <div class="ember"></div>
            <div class="ember"></div>
            <div class="ember"></div>
            <div class="ember"></div>
        </div>

        <!-- ÉLÉMENT CENTRAL INTERACTIF -->
        <div class="geometric-container" id="geometricContainer">
            <!-- Anneaux concentriques -->
            <div class="hexagon-ring ring-1"></div>
            <div class="hexagon-ring ring-2"></div>
            <div class="hexagon-ring ring-3"></div>
            <div class="hexagon-ring ring-4"></div>
            <div class="hexagon-ring ring-5"></div>
            
            <!-- Particules orbitales -->
            <div class="orbital-particle p1"></div>
            <div class="orbital-particle p2"></div>
            <div class="orbital-particle p3"></div>
            <div class="orbital-particle p4"></div>
            
            <!-- Triangles décoratifs -->
            <div class="triangle t1"></div>
            <div class="triangle t2"></div>
            <div class="triangle t3"></div>
            
            <!-- Lignes de connexion -->
            <div class="connection-line l1"></div>
            <div class="connection-line l2"></div>
            <div class="connection-line l3"></div>
            <div class="connection-line l4"></div>
            
            <!-- Cœur central -->
            <div class="center-core">
                <div class="inner-core"></div>
            </div>
        </div>

        <div class="menu-container">
            <form method="POST">
                <button type="submit" name="mode" value="single" class="menu-btn">
                    Solo
                </button>
                
                <button type="submit" name="mode" value="multi" class="menu-btn">
                    Multijoueur
                </button>
            </form>
            
            <a href="simulation.php" class="menu-btn simulate-link">
                Simuler
            </a>
            
            <?php if (User::isLoggedIn()): ?>
                <a href="account.php" class="menu-btn account-link">
                    Mon compte
                </a>
            <?php else: ?>
                <a href="login.php" class="menu-btn account-link">
                    Connexion
                </a>
            <?php endif; ?>
            
            <!-- DEBUG TOOLS (visible localement) -->
            <?php if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false): ?>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #444; display: flex; gap: 15px; justify-content: center;">
                    <a href="debug.php" class="menu-btn" style="background: #1a1a1a; border-color: #333; text-decoration: none;">
                        Debug
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <script>
            // Effet de suivi de souris
            const container = document.getElementById('geometricContainer');
            document.addEventListener('mousemove', (e) => {
                const x = (e.clientX / window.innerWidth - 0.5) * 30;
                const y = (e.clientY / window.innerHeight - 0.5) * 30;
                container.style.transform = `translate(-50%, -50%) rotateY(${x}deg) rotateX(${-y}deg)`;
            });
        </script>

    <?php else: ?>

        <div class="mode-container">
            <?php
            if ($modeChoisi === 'single') {
                require 'single_player.php'; 
            } elseif ($modeChoisi === 'multi') {
                require 'multi_player.php'; 
            }
            ?>
            
            <br><br>
            <a href="index.php" class="back-link">Retour au menu</a>
        </div>

    <?php endif; ?>
    
</body>
</html>