<?php
/** VUE: Multiplayer - Menu de sélection du mode */
?>

<div class="multi-container">
    <div class="mode-selection-header">
        <h2 class="page-title">MODE MULTIJOUEUR</h2>
        <p class="page-subtitle">Choisissez votre mode de jeu</p>
    </div>

    <div class="mode-cards">
        <!-- 1v1 DUEL -->
        <a href="<?php echo View::url('game/multiplayer-selection'); ?>" class="mode-card card-1v1">
            <div class="mode-icon"><img src="<?php echo View::asset('media/website/people.png'); ?>" alt="1v1"></div>
            <h2 class="v1">DUEL 1v1</h2>
            <p>Affrontez un adversaire en combat singulier.<br>Choisissez votre meilleur champion.</p>
            <span class="mode-btn">Sélectionner</span>
        </a>
        
        <!-- 5v5 TEAM -->
        <a href="<?php echo View::url('game/5v5-selection'); ?>" class="mode-card card-5v5">
            <div class="mode-icon"><img src="<?php echo View::asset('media/website/players.png'); ?>" alt="5v5"></div>
            <h2>ÉQUIPE 5v5</h2>
            <p>Dirigez une escouade de 5 héros.<br>Stratégie d'équipe et switch tactique.</p>
            <span class="mode-btn">Sélectionner</span>
        </a>
    </div>
</div>
