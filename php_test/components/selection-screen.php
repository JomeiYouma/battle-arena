<?php
/**
 * COMPOSANT DE SÉLECTION DES HÉROS ET BÉNÉDICTIONS
 * 
 * Usage:
 *   include 'components/selection-screen.php';
 *   renderSelectionScreen([
 *       'mode' => 'single',              // ou 'multiplayer'
 *       'showPlayerNameInput' => false,  // Pour multiplayer: true
 *   ]);
 */

// Charger les utilitaires si pas déjà chargés
if (!function_exists('getHeroesList')) {
    include __DIR__ . '/selection-utils.php';
}

function renderSelectionScreen($config = []) {
    $mode = $config['mode'] ?? 'single';
    $showPlayerNameInput = $config['showPlayerNameInput'] ?? false;
    $displayNameValue = $config['displayNameValue'] ?? null;
    $displayNameIsStatic = $config['displayNameIsStatic'] ?? false;
    $teamId = $config['teamId'] ?? null;
    $position = $config['position'] ?? null;
    
    $personnages = getHeroesList();
    $blessingsList = getBlessingsList();
?>

<div class="select-screen">
    <?php if ($mode === 'team_selection'): ?>
        <h2>Sélectionnez un Héros pour le Slot <?php echo htmlspecialchars($position); ?></h2>
    <?php else: ?>
        <h2>Choisissez votre Champion et votre Bénédiction</h2>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
        <?php if ($teamId && $position): ?>
            <input type="hidden" name="action" value="add_hero_to_team">
            <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($teamId); ?>">
            <input type="hidden" name="position" value="<?php echo htmlspecialchars($position); ?>">
        <?php endif; ?>
        
        <!-- Champ nom du joueur (pour multiplayer) -->
        <?php if ($showPlayerNameInput): ?>
            <?php if ($displayNameIsStatic && $displayNameValue): ?>
            <div class="player-name-input-section highlighted">
                <label class="player-name-label">Vous jouez en tant que</label>
                <div class="player-name-value">
                    <?php echo htmlspecialchars($displayNameValue); ?>
                </div>
                <input type="hidden" id="displayName" value="<?php echo htmlspecialchars($displayNameValue); ?>">
            </div>
            <?php else: ?>
            <div class="player-name-input-section">
                <label for="playerName">Votre nom:</label>
                <input type="text" id="playerName" name="player_name" placeholder="Entrez votre nom" maxlength="30">
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="selection-columns">
            <!-- HEROES -->
            <div class="selection-column heroes-column">
                <h3 class="selection-column-title">⚔️ Héros</h3>
                <div class="hero-list hero-list-scroll">
                    <?php foreach ($personnages as $perso): 
                        // Charger la classe pour obtenir les actions
                        $actions = getHeroActions($perso);
                    ?>
                        <label class="hero-row">
                            <input type="radio" name="hero_choice" value="<?php echo $perso['id']; ?>" required checked>
                            <div class="hero-row-content">
                                <img src="<?php echo $perso['images']['p1']; ?>" alt="<?php echo $perso['name']; ?>" class="hero-thumb">
                                <div class="hero-info">
                                    <h4><?php echo $perso['name']; ?></h4>
                                    <span class="type-badge"><?php echo $perso['type']; ?></span>
                                    <div class="hero-stats-mini">
                                        <?php echo $perso['pv']; ?> PV | <?php echo $perso['atk']; ?> ATK | <?php echo $perso['def'] ?? 5; ?> DEF | <?php echo $perso['speed'] ?? 10; ?> SPE
                                    </div>
                                    <div class="hero-abilities">
                                        <?php foreach ($actions as $key => $action): ?>
                                            <span class="ability-tag" data-tooltip="<?php echo htmlspecialchars($action['description']); ?>">
                                                <?php echo $action['emoji'] ?? '⚔️'; ?> <?php echo $action['label']; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- BLESSINGS -->
            <div class="selection-column blessings-column">
                <h3 class="selection-column-title">🔮 Bénédiction <span class="optional-tag">(Optionnel)</span></h3>
                <div class="hero-list hero-list-scroll">
                    <label class="hero-row blessing-row">
                        <input type="radio" name="blessing_choice" value="" checked>
                        <div class="hero-row-content">
                            <div class="blessing-img-container">
                                <span class="no-blessing-icon">✕</span>
                            </div>
                            <div class="hero-info">
                                <h4>Aucune</h4>
                                <p class="hero-theme">Combat classique sans bonus.</p>
                            </div>
                        </div>
                    </label>
                    <?php foreach ($blessingsList as $b): 
                        // Charger la classe de bénédiction pour obtenir les actions
                        $blessingActions = getBlessingActions($b['id']);
                    ?>
                        <label class="hero-row blessing-row">
                            <input type="radio" name="blessing_choice" value="<?php echo $b['id']; ?>">
                            <div class="hero-row-content">
                                <div class="blessing-img-container">
                                    <img src="media/blessings/<?php echo $b['img']; ?>" alt="<?php echo $b['name']; ?>" class="blessing-thumb">
                                </div>
                                <div class="hero-info">
                                    <h4><?php echo $b['name']; ?></h4>
                                    <p class="hero-theme blessing-desc-small"><?php echo $b['desc']; ?></p>
                                    <?php if (!empty($blessingActions)): ?>
                                    <div class="hero-abilities blessing-abilities">
                                        <?php foreach ($blessingActions as $key => $action): ?>
                                            <span class="ability-tag blessing-action" data-tooltip="<?php echo htmlspecialchars($action['description']); ?>">
                                                <?php echo $action['emoji'] ?? '✨'; ?> <?php echo $action['label']; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="submit-section">
            <?php if ($mode === 'team_selection'): ?>
                <button type="submit" class="action-btn enter-arena enter-arena-btn">Ajouter à l'équipe</button>
            <?php else: ?>
                <button type="submit" class="action-btn enter-arena enter-arena-btn">Entrer dans l'arène</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php
} // Fin de renderSelectionScreen()
?>
