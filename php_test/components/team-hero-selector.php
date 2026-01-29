<?php
/**
 * COMPOSANT: Sélecteur de Héros pour Équipe
 * 
 * Réutilise selection-utils.php et affiche le même layout que selection-screen.php
 * Mais pour sélectionner 1 héros + 1 bénédiction pour une équipe
 * 
 * Paramètres POST:
 * - action: add_hero_to_team
 * - team_id
 * - position
 * - hero_id
 * - blessing_id
 */

// Charger les utilitaires si pas déjà chargés
if (!function_exists('getHeroesList')) {
    include __DIR__ . '/selection-utils.php';
}

function renderTeamHeroSelector($teamId, $position) {
    $personnages = getHeroesList();
    $blessingsList = getBlessingsList();
?>

<div class="team-hero-selector">
    <form method="POST" class="hero-selection-form">
        <input type="hidden" name="action" value="add_hero_to_team">
        <input type="hidden" name="team_id" value="<?php echo htmlspecialchars($teamId); ?>">
        <input type="hidden" name="position" value="<?php echo htmlspecialchars($position); ?>">
        <input type="hidden" name="hero_id" id="selectedHeroId" value="">
        <input type="hidden" name="blessing_id" id="selectedBlessingId" value="">
        
        <div class="selection-columns">
            <!-- HÉROS -->
            <div class="selection-column heroes-column">
                <h3 class="selection-column-title">⚔️ Héros</h3>
                <div class="hero-list hero-list-scroll">
                    <?php foreach ($personnages as $perso): 
                        $isActive = isset($perso['is_active']) ? $perso['is_active'] : 1;
                        if (!$isActive) continue;
                    ?>
                        <div class="hero-card" onclick="selectTeamHero(<?php echo htmlspecialchars(json_encode($perso)); ?>)">
                            <div class="hero-image">
                                <img src="<?php echo htmlspecialchars($perso['image_p1'] ?? 'media/heroes/default.png'); ?>" 
                                     alt="<?php echo htmlspecialchars($perso['name']); ?>">
                            </div>
                            <div class="hero-info">
                                <div class="hero-name"><?php echo htmlspecialchars($perso['name']); ?></div>
                                <div class="hero-type"><?php echo htmlspecialchars($perso['type']); ?></div>
                                <div class="hero-stats">
                                    HP: <?php echo $perso['pv']; ?> | ATK: <?php echo $perso['atk']; ?> | DEF: <?php echo $perso['def']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- BÉNÉDICTIONS -->
            <div class="selection-column blessings-column">
                <h3 class="selection-column-title">✨ Bénédiction</h3>
                <div class="blessings-list">
                    <div class="blessing-card" onclick="selectTeamBlessing(null)">
                        <div class="blessing-icon">─</div>
                        <div class="blessing-name">Aucune</div>
                    </div>
                    <?php foreach ($blessingsList as $blessing): ?>
                        <div class="blessing-card" onclick="selectTeamBlessing('<?php echo htmlspecialchars($blessing['id']); ?>')">
                            <div class="blessing-icon"><?php echo htmlspecialchars($blessing['emoji'] ?? '✨'); ?></div>
                            <div class="blessing-name"><?php echo htmlspecialchars($blessing['name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Affichage de la sélection -->
        <div class="selection-summary">
            <div id="selectedHeroDisplay" class="selected-display">Aucun héros sélectionné</div>
            <div id="selectedBlessingDisplay" class="selected-display">Aucune bénédiction sélectionnée</div>
        </div>

        <!-- Boutons -->
        <div class="form-actions">
            <button type="submit" class="btn-success">Ajouter à l'équipe</button>
            <button type="button" class="btn-secondary" onclick="closeHeroSelector()">Annuler</button>
        </div>
    </form>
</div>

<style>
.team-hero-selector {
    padding: 20px;
}

.selection-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.selection-column {
    display: flex;
    flex-direction: column;
}

.selection-column-title {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.hero-list-scroll,
.blessings-list {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
    background: #fafafa;
}

.hero-card {
    display: flex;
    gap: 10px;
    padding: 10px;
    margin-bottom: 10px;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.hero-card:hover,
.hero-card.selected {
    border-color: #4CAF50;
    background: #f0f8f0;
}

.hero-image {
    width: 60px;
    height: 60px;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
}

.hero-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hero-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.hero-name {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.hero-type {
    font-size: 12px;
    color: #666;
}

.hero-stats {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
}

.blessings-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    padding: 10px;
}

.blessing-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
    min-height: 100px;
}

.blessing-card:hover,
.blessing-card.selected {
    border-color: #FFD700;
    background: #fffbf0;
    box-shadow: 0 0 8px rgba(255, 215, 0, 0.3);
}

.blessing-icon {
    font-size: 32px;
    margin-bottom: 5px;
}

.blessing-name {
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    color: #333;
}

.selection-summary {
    display: flex;
    gap: 20px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 6px;
    margin-bottom: 20px;
}

.selected-display {
    flex: 1;
    padding: 10px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    color: #666;
}

.selected-display.has-selection {
    color: #333;
    font-weight: 600;
    background: #f0f8f0;
    border-color: #4CAF50;
}

.form-actions {
    display: flex;
    gap: 10px;
}

.form-actions button {
    flex: 1;
    padding: 12px;
    font-size: 14px;
    font-weight: 600;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-success {
    background: #4CAF50;
    color: white;
}

.btn-success:hover {
    background: #45a049;
}

.btn-secondary {
    background: #9e9e9e;
    color: white;
}

.btn-secondary:hover {
    background: #858585;
}
</style>

<script>
let selectedTeamHero = null;
let selectedTeamBlessing = null;

function selectTeamHero(heroData) {
    selectedTeamHero = heroData;
    
    // Mettre à jour l'input caché
    document.getElementById('selectedHeroId').value = heroData.id;
    
    // Marquer comme sélectionné visuellement
    document.querySelectorAll('.hero-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.target.closest('.hero-card').classList.add('selected');
    
    // Mettre à jour l'affichage
    const display = document.getElementById('selectedHeroDisplay');
    display.textContent = '✅ ' + heroData.name + ' (' + heroData.type + ')';
    display.classList.add('has-selection');
}

function selectTeamBlessing(blessingId) {
    selectedTeamBlessing = blessingId;
    
    // Mettre à jour l'input caché
    document.getElementById('selectedBlessingId').value = blessingId || '';
    
    // Marquer comme sélectionné visuellement
    document.querySelectorAll('.blessing-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.target.closest('.blessing-card').classList.add('selected');
    
    // Mettre à jour l'affichage
    const display = document.getElementById('selectedBlessingDisplay');
    if (blessingId) {
        const blessingName = event.target.closest('.blessing-card').querySelector('.blessing-name').textContent;
        display.textContent = '✅ ' + blessingName;
    } else {
        display.textContent = '✅ Aucune bénédiction';
    }
    display.classList.add('has-selection');
}

// Validation du formulaire
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.hero-selection-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!selectedTeamHero) {
                e.preventDefault();
                alert('Veuillez sélectionner un héros');
            }
        });
    }
});
</script>

<?php
}
?>
