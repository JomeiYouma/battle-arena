<?php
/**
 * COMPOSANT: Gestion des Équipes
 * 
 * Affiche et permet de modifier les équipes du joueur
 * Variables disponibles du parent:
 * - $userId
 * - $teamManager
 * - $userTeams
 */
?>

<div class="teams-manager">
    <div class="teams-header">
        <h2>🏆 Mes Équipes</h2>
        <button class="btn-primary btn-create-team" onclick="toggleCreateForm()">+ Créer une Équipe</button>
    </div>

    <!-- Formulaire de création d'équipe (caché par défaut) -->
    <div id="createTeamForm" class="create-team-form" style="display: none;">
        <form method="POST" class="form-create">
            <input type="hidden" name="action" value="create_team">
            
            <div class="form-group">
                <label for="team_name">Nom de l'équipe:</label>
                <input type="text" id="team_name" name="team_name" placeholder="Ex: Les Invincibles" 
                       maxlength="100" required>
            </div>
            
            <div class="form-group">
                <label for="team_description">Description (optionnel):</label>
                <textarea id="team_description" name="team_description" placeholder="Ma stratégie principale..." 
                          maxlength="255" rows="2"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-success">Créer</button>
                <button type="button" class="btn-secondary" onclick="toggleCreateForm()">Annuler</button>
            </div>
        </form>
    </div>

    <!-- Liste des équipes -->
    <div class="teams-list">
        <?php if (empty($userTeams)): ?>
            <div class="empty-state">
                <div class="icon">🏆</div>
                <h3>Aucune équipe</h3>
                <p>Créez votre première équipe pour le combat 5v5!</p>
            </div>
        <?php else: ?>
            <?php foreach ($userTeams as $team): 
                $teamData = $teamManager->getTeamById($team['id']);
                $members = $teamData['members'] ?? [];
                $isComplete = count($members) === 5;
            ?>
            <div class="team-card <?php echo !$teamData['is_active'] ? 'inactive' : ''; ?>">
                <div class="team-header">
                    <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                    <span class="team-status <?php echo $isComplete ? 'complete' : 'incomplete'; ?>">
                        <?php echo $isComplete ? '✅ Complète' : "❌ " . count($members) . "/5"; ?>
                    </span>
                </div>
                
                <?php if ($team['description']): ?>
                    <p class="team-description"><?php echo htmlspecialchars($team['description']); ?></p>
                <?php endif; ?>
                
                <!-- Slots de héros -->
                <div class="team-heroes">
                    <?php 
                    // Mapping des bénédictions vers leurs images
                    $blessingImages = [
                        'WheelOfFortune' => 'wheel.png',
                        'LoversCharm' => 'lovers.png',
                        'JudgmentOfDamned' => 'judgment.png',
                        'StrengthFavor' => 'strength.png',
                        'MoonCall' => 'moon.png',
                        'WatchTower' => 'tower.png',
                        'RaChariot' => 'chariot.png',
                        'HangedMan' => 'hanged.png'
                    ];
                    
                    for ($slot = 1; $slot <= 5; $slot++): 
                        $member = array_values(array_filter($members, fn($m) => $m['position'] == $slot))[0] ?? null;
                    ?>
                        <div class="hero-slot <?php echo $member ? 'filled' : 'empty'; ?>">
                            <?php if ($member): ?>
                                <div class="hero-slot-content">
                                    <img src="<?php echo htmlspecialchars($member['image_p1'] ?? 'media/heroes/default.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($member['name']); ?>"
                                         title="<?php echo htmlspecialchars($member['name']); ?>">
                                    <div class="hero-name"><?php echo htmlspecialchars($member['name']); ?></div>
                                    <?php if ($member['blessing_id']): 
                                        $blessingImg = $blessingImages[$member['blessing_id']] ?? null;
                                    ?>
                                        <div class="blessing-badge" title="<?php echo htmlspecialchars($member['blessing_id']); ?>">
                                            <?php if ($blessingImg): ?>
                                                <img src="media/blessings/<?php echo $blessingImg; ?>" 
                                                     alt="<?php echo htmlspecialchars($member['blessing_id']); ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <a href="#" class="slot-remove" 
                                       onclick="removeFromTeam(<?php echo $team['id']; ?>, <?php echo $slot; ?>); return false;" 
                                       title="Retirer">✕</a>
                                </div>
                            <?php else: ?>
                                <div class="hero-slot-empty">
                                    <span class="position">Slot <?php echo $slot; ?></span>
                                    <button class="btn-add-hero" 
                                            onclick="openHeroSelector(<?php echo $team['id']; ?>, <?php echo $slot; ?>)">
                                        + Ajouter
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <!-- Actions -->
                <div class="team-actions">
                    <button class="btn-secondary" onclick="editTeam(<?php echo $team['id']; ?>)">✏️ Éditer</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_team">
                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                        <button type="submit" class="btn-danger" 
                                onclick="return confirm('Supprimer cette équipe?')">🗑️ Supprimer</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de sélection de héros -->
<div id="heroSelectorModal" class="modal" style="display: none;">
    <div class="modal-content">
        <button class="modal-close" onclick="closeHeroSelector()">✕</button>
        <h3 id="modalTitle">Sélectionner un héros</h3>
        
        <div id="heroSelectorContent">
            <!-- Rempli par PHP -->
        </div>
    </div>
</div>

<style>
/* Réutiliser les styles existants et les adapter */

.teams-manager {
    padding: 20px;
    color: inherit;
}

.teams-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    border-bottom: 2px solid #d4af37;
    padding-bottom: 15px;
}

.teams-header h2 {
    margin: 0;
    color: #ffd700;
    text-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
}

.create-team-form {
    background: rgba(20, 20, 30, 0.5);
    border: 1px solid #4a0000;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #b8860b;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px;
    background: rgba(10, 10, 15, 0.9);
    border: 2px solid #4a0000;
    border-radius: 4px;
    font-size: 14px;
    color: #ffd700;
}

.form-group input::placeholder,
.form-group textarea::placeholder {
    color: #666;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.teams-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 20px;
}

.team-card {
    background: rgba(20, 20, 30, 0.6);
    border: 2px solid #4a0000;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s;
}

.team-card:hover {
    border-color: #d4af37;
    background: rgba(30, 30, 40, 0.8);
    box-shadow: 0 0 20px rgba(212, 175, 55, 0.2);
}

.team-card.inactive {
    opacity: 0.6;
}

.team-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.team-header h3 {
    margin: 0;
    font-size: 18px;
    color: #ffd700;
}

.team-status {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 20px;
    font-weight: 600;
}

.team-status.complete {
    background: rgba(76, 175, 80, 0.3);
    color: #a8d5a8;
}

.team-status.incomplete {
    background: rgba(220, 38, 38, 0.3);
    color: #f87171;
}

.team-description {
    color: #b8860b;
    font-size: 12px;
    margin: 10px 0;
    font-style: italic;
}

.team-heroes {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    margin: 20px 0;
}

.hero-slot {
    aspect-ratio: 1;
    border: 2px solid #4a0000;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(10, 10, 20, 0.8);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.hero-slot.filled {
    border-color: #ff8c00;
    background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(10, 10, 20, 0.8));
}

.hero-slot.empty {
    border-style: dashed;
    border-color: #4a0000;
    color: #666;
}

.hero-slot.empty:hover {
    border-color: #d4af37;
    background: rgba(212, 175, 55, 0.1);
}

.hero-slot-content {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    position: relative;
    overflow: hidden;
}

.hero-slot-content img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    position: absolute;
    top: 0;
    left: 0;
}

.hero-name {
    position: absolute;
    bottom: 0;
    width: 100%;
    background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
    color: #ffd700;
    text-align: center;
    font-size: 11px;
    padding: 6px 2px;
    font-weight: 600;
    z-index: 2;
}

.blessing-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    background: rgba(153, 50, 204, 0.95);
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 3;
    box-shadow: 0 0 8px rgba(153, 50, 204, 0.6);
    overflow: hidden;
}

.blessing-badge img {
    width: 28px;
    height: 28px;
    object-fit: contain;
    object-position: center;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    filter: drop-shadow(0 0 2px #d4af37);
}

.slot-remove {
    position: absolute;
    top: 4px;
    left: 4px;
    background: #dc2626;
    color: white;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    opacity: 0;
    transition: opacity 0.3s, background 0.3s;
    text-decoration: none;
    cursor: pointer;
    z-index: 3;
}

.hero-slot.filled:hover .slot-remove {
    opacity: 1;
}

.slot-remove:hover {
    background: #b91c1c;
}

.hero-slot-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    width: 100%;
    height: 100%;
    justify-content: center;
}

.position {
    font-size: 11px;
    color: #666;
}

.btn-add-hero {
    background: rgba(255, 140, 0, 0.8);
    color: white;
    border: 1px solid #ff8c00;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-add-hero:hover {
    background: #ff8c00;
    box-shadow: 0 0 10px rgba(255, 140, 0, 0.5);
}

.team-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    border-top: 1px solid #4a0000;
    padding-top: 15px;
}

.team-actions button {
    flex: 1;
    padding: 8px;
    font-size: 13px;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state .icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin: 10px 0;
    color: #b8860b;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: rgba(30, 30, 40, 0.95);
    border: 2px solid #d4af37;
    border-radius: 8px;
    padding: 30px;
    max-width: 900px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #b8860b;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #ffd700;
}

#modalTitle {
    color: #ffd700;
    margin-bottom: 20px;
}
</style>

<script>
let currentTeamId = null;
let currentSlot = null;

// Liste des héros et bénédictions (à charger depuis PHP)
const heroesData = <?php 
    require_once __DIR__ . '/selection-utils.php';
    $heroes = getHeroesList();
    echo json_encode($heroes ?? []);
?>;

const blessings = ['blessing_1', 'blessing_2', 'blessing_3', 'blessing_4', 'blessing_5', 'blessing_6', 'blessing_7', 'blessing_8'];

function toggleCreateForm() {
    const form = document.getElementById('createTeamForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function openHeroSelector(teamId, slot) {
    currentTeamId = teamId;
    currentSlot = slot;
    document.getElementById('heroSelectorModal').style.display = 'flex';
    document.getElementById('modalTitle').textContent = `Sélectionner un héros pour le slot ${slot}`;
    
    // Charger le composant via AJAX
    loadHeroSelectorComponent(teamId, slot);
}

function closeHeroSelector() {
    document.getElementById('heroSelectorModal').style.display = 'none';
    currentTeamId = null;
    currentSlot = null;
}

function loadHeroSelectorComponent(teamId, slot) {
    // Créer et envoyer une requête pour charger le composant
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'components/team-hero-selector-loader.php?team_id=' + teamId + '&position=' + slot, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('heroSelectorContent').innerHTML = xhr.responseText;
            // Attacher l'écouteur au formulaire une fois chargé
            const form = document.querySelector('#heroSelectorContent form');
            if (form) {
                form.addEventListener('submit', submitHeroSelection);
            }
        } else {
            document.getElementById('heroSelectorContent').innerHTML = '<p>Erreur lors du chargement</p>';
        }
    };
    xhr.send();
}

function submitHeroSelection(e) {
    e.preventDefault();
    
    const form = e.target;
    const heroChoice = form.querySelector('input[name="hero_choice"]:checked');
    const blessingChoice = form.querySelector('input[name="blessing_choice"]:checked');
    
    if (!heroChoice) {
        alert('Sélectionnez un héros!');
        return;
    }
    
    // Créer et soumettre un formulaire POST
    const postForm = document.createElement('form');
    postForm.method = 'POST';
    postForm.innerHTML = `
        <input type="hidden" name="action" value="add_hero_to_team">
        <input type="hidden" name="team_id" value="${currentTeamId}">
        <input type="hidden" name="position" value="${currentSlot}">
        <input type="hidden" name="hero_id" value="${heroChoice.value}">
        <input type="hidden" name="blessing_id" value="${blessingChoice ? blessingChoice.value : ''}">
    `;
    document.body.appendChild(postForm);
    postForm.submit();
    closeHeroSelector();
}

function removeFromTeam(teamId, slot) {
    if (confirm('Retirer ce héros de l\'équipe?')) {
        // Créer et soumettre un formulaire caché
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="remove_hero_from_team">' +
                        '<input type="hidden" name="team_id" value="' + teamId + '">' +
                        '<input type="hidden" name="position" value="' + slot + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function editTeam(teamId) {
    alert('Fonctionnalité à venir: éditer l\'équipe ' + teamId);
}
</script>

<style>
.hero-selection-form {
    padding: 20px 0;
}

.heroes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.hero-option {
    cursor: pointer;
    border: 2px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
}

.hero-option:hover {
    border-color: #4CAF50;
    transform: scale(1.05);
}

.hero-option.selected {
    border-color: #4CAF50;
    background: #f0f8f0;
    box-shadow: 0 0 10px rgba(76, 175, 80, 0.3);
}

.hero-option img {
    width: 100%;
    height: 140px;
    object-fit: cover;
    display: block;
}

.hero-option-name {
    padding: 8px;
    background: #f5f5f5;
    text-align: center;
    font-size: 12px;
    font-weight: 600;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}

.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}
</style>
