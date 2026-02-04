<?php
/** MULTIPLAYER - Page de sélection du mode multijoueur */

require_once __DIR__ . '/../../includes/autoload.php';

$basePath = '../../';

// Nettoyer les données de match précédent pour éviter les conflits
unset($_SESSION['matchId']);
unset($_SESSION['queue5v5Status']);
unset($_SESSION['queue5v5Team']);
unset($_SESSION['queue5v5DisplayName']);
unset($_SESSION['queue5v5BlessingId']);
// Nettoyer aussi les données 1v1
unset($_SESSION['queueHeroId']);
unset($_SESSION['queueStartTime']);
unset($_SESSION['queueHeroData']);
unset($_SESSION['queueDisplayName']);
unset($_SESSION['queueBlessingId']);

$pageTitle = 'Multijoueur - Horus Battle Arena';
$extraCss = ['shared-selection', 'multiplayer-mode'];
$showUserBadge = true;
$showMainTitle = false;
require_once INCLUDES_PATH . '/header.php';
?>

<div class="multi-container">
    <div class="mode-selection-header">
        <h2 class="page-title">MODE MULTIJOUEUR</h2>
        <p class="page-subtitle">Choisissez votre mode de jeu</p>
    </div>

<div class="mode-cards">
    <!-- 1v1 DUEL -->
    <a href="<?php echo $basePath; ?>pages/game/multiplayer-selection.php" class="mode-card card-1v1">
        <div class="mode-icon"><img src="<?php echo $basePath; ?>public/media/website/people.png" alt="1v1"></div>
        <h2 class="v1">DUEL 1v1</h2>
        <p>Affrontez un adversaire en combat singulier.<br>Choisissez votre meilleur champion.</p>
        <span class="mode-btn">Sélectionner</span>
    </a>
    
    <!-- 5v5 TEAM -->
    <a href="<?php echo $basePath; ?>pages/game/multiplayer_5v5_selection.php" class="mode-card card-5v5">
        <div class="mode-icon"><img src="<?php echo $basePath; ?>public/media/website/players.png" alt="5v5"></div>
        <h2>ÉQUIPE 5v5</h2>
        <p>Dirigez une escouade de 5 héros.<br>Stratégie d'équipe et switch tactique.</p>
        <span class="mode-btn">Sélectionner</span>
    </a>
</div>

</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
