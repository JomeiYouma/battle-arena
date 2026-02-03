<?php
// Autoloader centralisé (démarre la session automatiquement)
require_once __DIR__ . '/../../includes/autoload.php';

// Chemin de base pour les assets (accès direct depuis pages/game/)
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

// L'autoloader se charge des classes User, Database, etc.

// Charger le header
$pageTitle = 'Multijoueur - Horus Battle Arena';
$extraCss = ['shared-selection'];
$showUserBadge = true;
$showMainTitle = false;
require_once INCLUDES_PATH . '/header.php';
?>

<div class="multi-container">
    <div class="mode-selection-header">
        <h2 class="page-title">MODE MULTIJOUEUR</h2>
        <p class="page-subtitle">Choisissez votre mode de jeu</p>
    </div>

<style>
    .mode-selection-header {
        text-align: center;
        margin-bottom: 2rem;
        padding: 2rem;
    }
    
    .page-title {
        color: var(--gold-accent);
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
    }
    
    .page-subtitle {
        color: #aaa;
        font-size: 1.1rem;
    }
    
    /* Styles spécifiques déplacés ici ou dans un CSS à part si tu préfères */
    .mode-cards {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
        justify-content: center;
        padding: 0 2rem;
    }
    
    .mode-card {
        background: linear-gradient(145deg, rgba(20, 20, 20, 0.9), rgba(30, 30, 30, 0.95));
        border: 2px solid #444;
        border-radius: 12px;
        padding: 2rem;
        width: 300px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: var(--text-light);
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    
    .mode-card:hover {
        transform: translateY(-5px);
        border-color: var(--gold-accent);
        box-shadow: 0 10px 20px rgba(184, 134, 11, 0.2);
    }
    
    .mode-card h2 {
        font-size: 2rem;
        margin: 1rem 0;
        color: var(--gold-accent);
    }
    
    .mode-card p {
        color: #aaa;
        margin-bottom: 2rem;
        line-height: 1.5;
    }
    
    .mode-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .mode-icon img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    .mode-btn {
        background: var(--stone-gray);
        color: #fff;
        border: 1px solid var(--gold-accent);
        padding: 0.8rem 2rem;
        border-radius: 6px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.2s;
        margin-top: auto;
    }
    
    .mode-card:hover .mode-btn {
        background: var(--gold-accent);
        color: #000;
    }
    
    /* Specific Styles */
    .card-1v1 {
        border-color: #4a90e2; /* Blue tint for 1v1 */
    }
    .card-1v1:hover {
        border-color: #4a90e2;
        box-shadow: 0 10px 20px rgba(74, 144, 226, 0.2);
    }
    .card-1v1 .mode-btn:hover {
        background: #4a90e2;
    }
    
    .card-5v5 {
        border-color: #e24a4a; /* Red tint for 5v5 */
    }
    .card-5v5:hover {
        border-color: #e24a4a;
        box-shadow: 0 10px 20px rgba(226, 74, 74, 0.2);
    }
    .card-5v5 .mode-btn:hover {
        background: #e24a4a;
    }
    
    .back-btn {
        color: #888;
        text-decoration: none;
        margin-top: 2rem;
        font-size: 0.9rem;
        transition: color 0.2s;
    }
    .back-btn:hover {
        color: #fff;
    }

</style>

<div class="mode-cards">
    <!-- 1v1 DUEL -->
    <a href="<?php echo $basePath; ?>pages/game/multiplayer-selection.php" class="mode-card card-1v1">
        <div class="mode-icon"><img src="<?php echo $basePath; ?>public/media/website/people.png" alt="1v1"></div>
        <h2>DUEL 1v1</h2>
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
