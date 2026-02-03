<?php
/**
 * MULTIPLAYER 5v5 - Sélection d'une équipe pré-créée + Queue
 */

// Autoloader
if (!function_exists('chargerClasse')) {
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
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nettoyer les données de match précédent pour éviter les conflits
unset($_SESSION['matchId']);
unset($_SESSION['queue5v5Status']);
unset($_SESSION['queue5v5Team']);
unset($_SESSION['queue5v5DisplayName']);
unset($_SESSION['queue5v5BlessingId']);

require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Database.php';

// Vérifier si l'utilisateur est connecté
if (!User::isLoggedIn()) {
    header('Location: login.php?redirect=multiplayer_5v5_selection.php');
    exit;
}

$userId = User::getCurrentUserId();
$pdo = Database::getInstance();

// Récupérer les équipes de l'utilisateur (5 héros obligatoires)
$stmt = $pdo->prepare("
    SELECT t.*, 
           COUNT(tm.id) as hero_count
    FROM teams t
    LEFT JOIN team_members tm ON t.id = tm.team_id
    WHERE t.user_id = ? AND t.is_active = 1
    GROUP BY t.id
    HAVING hero_count = 5
    ORDER BY t.updated_at DESC
");
$stmt->execute([$userId]);
$userTeams = $stmt->fetchAll();

$pageTitle = 'Multijoueur 5v5 - Horus Battle Arena';
$extraCss = ['shared-selection', 'multiplayer'];
$showUserBadge = true;
$showMainTitle = false;
require_once __DIR__ . '/includes/header.php';
?>

<div class="multi-container">
    <!-- ÉCRAN 1: SÉLECTION D'UNE ÉQUIPE -->
    <div id="selectionScreen">
        <div class="selection-header">
            <a href="multiplayer.php" class="back-link">← Retour aux modes</a>
            <h2>🛡️ SÉLECTIONNEZ VOTRE ÉQUIPE 5v5</h2>
            <p>Choisissez l'une de vos équipes pré-créées pour le combat</p>
            <a href="account.php?tab=teams" class="btn-manage-teams">⚙️ Gérer mes équipes</a>
        </div>

        <?php if (empty($userTeams)): ?>
            <div class="no-teams-message">
                <div class="message-icon">🛡️</div>
                <h3>Aucune équipe disponible</h3>
                <p>Vous devez d'abord créer une équipe de 5 héros pour jouer en mode 5v5.</p>
                <a href="account.php?tab=teams" class="btn-create-team">Créer une équipe</a>
            </div>
        <?php else: ?>
            <div class="teams-list">
                <?php foreach ($userTeams as $team): 
                    // Récupérer les membres de l'équipe
                    $stmtMembers = $pdo->prepare("
                        SELECT tm.position, tm.hero_id, tm.blessing_id, h.*
                        FROM team_members tm
                        LEFT JOIN heroes h ON tm.hero_id = h.hero_id
                        WHERE tm.team_id = ?
                        ORDER BY tm.position ASC
                    ");
                    $stmtMembers->execute([$team['id']]);
                    $members = $stmtMembers->fetchAll();
                ?>
                    <div class="team-card" data-team-id="<?php echo $team['id']; ?>">
                        <div class="team-header">
                            <h3><?php echo htmlspecialchars($team['team_name']); ?></h3>
                            <?php if ($team['description']): ?>
                                <p class="team-description"><?php echo htmlspecialchars($team['description']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="team-members-preview">
                            <?php foreach ($members as $member): ?>
                                <div class="member-preview">
                                    <img src="<?php echo htmlspecialchars($member['image_p1'] ?? 'media/heroes/default.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($member['name']); ?>"
                                         title="<?php echo htmlspecialchars($member['name'] . ' - ' . $member['type']); ?>">
                                    <div class="member-name"><?php echo htmlspecialchars($member['name']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php 
                        // Encoder les données en JSON puis échapper pour HTML
                        $teamNameJson = json_encode($team['team_name']);
                        $membersJson = json_encode($members);
                        ?>
                        <button class="btn-select-team" onclick="selectTeam(<?php echo $team['id']; ?>, <?php echo htmlspecialchars($teamNameJson, ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($membersJson, ENT_QUOTES, 'UTF-8'); ?>)">
                            ⚔️ Combattre avec cette équipe
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ÉCRAN 2: QUEUE / MATCHMAKING -->
    <div id="queueScreen" style="display:none;">
        <div class="queue-box">
            <h2>🔍 RECHERCHE D'ADVERSAIRE...</h2>
            <div class="queue-spinner"></div>
            
            <div class="queue-team-preview" id="queueTeamPreview">
                <!-- Prévisualisé par JS -->
            </div>
            
            <div class="queue-timer">
                <span id="queueSeconds">0</span>s / 30s
            </div>
            <div class="queue-progress">
                <div class="queue-progress-bar" id="queueProgressBar"></div>
            </div>
            
            <p class="queue-hint">Match contre un joueur réel, ou un bot si aucun adversaire trouvé après 30s</p>
            
            <button id="cancelQueue" class="btn-cancel">❌ Annuler la recherche</button>
        </div>
    </div>
</div>

<style>
/* ===== HEADER ===== */
.selection-header {
    text-align: center;
    margin-bottom: 2rem;
    padding: 2rem;
    position: relative;
}

.back-link {
    position: absolute;
    left: 2rem;
    top: 2rem;
    color: var(--text-dim);
    text-decoration: none;
    transition: color 0.2s;
}

.back-link:hover {
    color: var(--gold-accent);
}

.selection-header h2 {
    color: var(--gold-accent);
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.selection-header p {
    color: var(--text-dim);
    margin-bottom: 1rem;
}

.btn-manage-teams {
    display: inline-block;
    padding: 0.8rem 1.5rem;
    background: var(--stone-gray);
    color: var(--text-light);
    text-decoration: none;
    border-radius: 6px;
    border: 1px solid var(--gold-accent);
    transition: all 0.2s;
}

.btn-manage-teams:hover {
    background: var(--gold-accent);
    color: #000;
}

/* ===== NO TEAMS MESSAGE ===== */
.no-teams-message {
    text-align: center;
    padding: 4rem 2rem;
    max-width: 600px;
    margin: 0 auto;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    border: 2px solid var(--stone-gray);
}

.message-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.no-teams-message h3 {
    color: var(--gold-accent);
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.no-teams-message p {
    color: var(--text-dim);
    margin-bottom: 2rem;
}

.btn-create-team {
    display: inline-block;
    padding: 1rem 2rem;
    background: var(--gold-accent);
    color: #000;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
    transition: all 0.2s;
}

.btn-create-team:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(184, 134, 11, 0.4);
}

/* ===== TEAMS LIST ===== */
.teams-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    padding: 0 2rem 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.team-card {
    background: linear-gradient(145deg, rgba(20, 20, 20, 0.9), rgba(30, 30, 30, 0.95));
    border: 2px solid var(--stone-gray);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.3s;
}

.team-card:hover {
    border-color: var(--gold-accent);
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(184, 134, 11, 0.2);
}

.team-header h3 {
    color: var(--gold-accent);
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
}

.team-description {
    color: var(--text-dim);
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.team-members-preview {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.5rem;
    margin: 1.5rem 0;
}

.member-preview {
    text-align: center;
}

.member-preview img {
    width: 100%;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid var(--stone-gray);
    margin-bottom: 0.3rem;
    transition: all 0.2s;
}

.team-card:hover .member-preview img {
    border-color: var(--gold-accent);
}

.member-name {
    font-size: 0.7rem;
    color: var(--text-dim);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-select-team {
    width: 100%;
    padding: 1rem;
    background: var(--stone-gray);
    color: var(--text-light);
    border: 2px solid var(--gold-accent);
    border-radius: 8px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-select-team:hover {
    background: var(--gold-accent);
    color: #000;
    transform: translateY(-2px);
}

/* ===== QUEUE SCREEN ===== */
.queue-box {
    background: linear-gradient(145deg, rgba(20, 20, 20, 0.95), rgba(30, 30, 30, 0.98));
    border: 2px solid var(--gold-accent);
    border-radius: 12px;
    padding: 3rem;
    max-width: 600px;
    margin: 4rem auto;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.queue-box h2 {
    color: var(--gold-accent);
    margin-bottom: 1rem;
}

.queue-spinner {
    border: 4px solid rgba(184, 134, 11, 0.2);
    border-top: 4px solid var(--gold-accent);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    animation: spin 1s linear infinite;
    margin: 2rem auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.queue-team-preview {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin: 1.5rem 0;
    flex-wrap: wrap;
}

.queue-team-preview img {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid var(--gold-accent);
}

.queue-timer {
    font-size: 2rem;
    color: var(--gold-accent);
    font-weight: bold;
    margin: 1rem 0;
}

.queue-progress {
    width: 100%;
    height: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    overflow: hidden;
    margin: 1rem 0;
}

.queue-progress-bar {
    height: 100%;
    background: var(--gold-accent);
    width: 0%;
    transition: width 1s linear;
}

.queue-hint {
    color: var(--text-dim);
    font-size: 0.9rem;
    margin-top: 1rem;
}

.btn-cancel {
    margin-top: 2rem;
    padding: 0.8rem 2rem;
    background: var(--stone-gray);
    color: var(--text-light);
    border: 1px solid var(--text-dim);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 1rem;
}

.btn-cancel:hover {
    background: var(--accent-red);
    border-color: var(--accent-red);
}

/* ===== RESPONSIVE ===== */
/* Tablettes et écrans moyens */
@media (max-width: 1024px) {
    .teams-list {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }
}

/* Mobiles et petits écrans */
@media (max-width: 768px) {
    .teams-list {
        grid-template-columns: 1fr;
        padding: 0 1rem 1rem;
        gap: 1rem;
    }
    
    .team-card {
        padding: 1rem;
    }
    
    .team-members-preview {
        gap: 0.3rem;
    }
    
    .member-preview img {
        height: 60px;
    }
    
    .selection-header {
        padding: 1rem;
    }
    
    .back-link {
        position: static;
        display: block;
        margin-bottom: 1rem;
    }
    
    .selection-header h2 {
        font-size: 1.3rem;
    }
    
    .btn-manage-teams {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
}

/* Très petits écrans */
@media (max-width: 480px) {
    .team-members-preview {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .member-preview:nth-child(4),
    .member-preview:nth-child(5) {
        grid-column: span 1;
    }
    
    .member-preview img {
        height: 50px;
    }
    
    .member-name {
        font-size: 0.6rem;
    }
    
    .team-header h3 {
        font-size: 1.1rem;
    }
    
    .queue-container {
        padding: 1rem;
    }
    
    .queue-title {
        font-size: 1.2rem;
    }
}
</style>

<script>
let selectedTeamData = null;
let queueInterval = null;
let queueSeconds = 0;
const MAX_QUEUE_TIME = 30;

/**
 * Sélectionner une équipe et démarrer la recherche
 */
function selectTeam(teamId, teamName, membersData) {
    console.log('Équipe sélectionnée:', teamId, teamName, membersData);
    
    // Convertir les données des membres en format attendu par l'API
    // Inclure blessing_id pour chaque héros
    const teamHeroes = membersData.map(member => ({
        id: member.hero_id,
        name: member.name,
        type: member.type,
        pv: parseInt(member.pv),
        atk: parseInt(member.atk),
        def: parseInt(member.def),
        speed: parseInt(member.speed),
        blessing_id: member.blessing_id || null,
        images: {
            p1: member.image_p1 || 'media/heroes/default.png',
            p2: member.image_p2 || 'media/heroes/default.png'
        }
    }));
    
    selectedTeamData = {
        team_id: teamId,
        team_name: teamName,
        heroes: teamHeroes
    };
    
    // Afficher la prévisualisation dans l'écran de queue
    const previewContainer = document.getElementById('queueTeamPreview');
    previewContainer.innerHTML = teamHeroes.map(hero => 
        `<img src="${hero.images.p1}" alt="${hero.name}" title="${hero.name}">`
    ).join('');
    
    // Passer à l'écran de queue
    document.getElementById('selectionScreen').style.display = 'none';
    document.getElementById('queueScreen').style.display = 'block';
    
    startQueue();
}

/**
 * Démarrer la recherche de match
 */
function startQueue() {
    queueSeconds = 0;
    updateQueueDisplay();
    
    fetch('api.php?action=join_queue_5v5', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            team: selectedTeamData.heroes,  // Contient blessing_id pour chaque héros
            team_id: selectedTeamData.team_id,
            team_name: selectedTeamData.team_name,
            display_name: '<?php echo htmlspecialchars($_SESSION['username'] ?? 'Joueur'); ?>'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'error') {
            alert('Erreur: ' + data.message);
            cancelQueue();
            return;
        }
        
        // Commencer le polling
        queueInterval = setInterval(pollQueue, 1000);
    })
    .catch(err => {
        console.error('Erreur join queue:', err);
        alert('Erreur de connexion au serveur');
        cancelQueue();
    });
}

/**
 * Vérifier le statut de la queue
 */
function pollQueue() {
    queueSeconds++;
    updateQueueDisplay();
    
    fetch('api.php?action=poll_queue_5v5', {
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'matched') {
            // Match trouvé !
            clearInterval(queueInterval);
            window.location.href = 'multiplayer-combat.php?match_id=' + data.match_id;
        } else if (data.status === 'waiting') {
            // Toujours en attente
            if (queueSeconds >= MAX_QUEUE_TIME) {
                forceMatchWithBot();
            }
        } else if (data.status === 'error') {
            clearInterval(queueInterval);
            alert('Erreur: ' + data.message);
            cancelQueue();
        }
    })
    .catch(err => {
        console.error('Poll error:', err);
    });
}

/**
 * Mettre à jour l'affichage du timer
 */
function updateQueueDisplay() {
    document.getElementById('queueSeconds').textContent = queueSeconds;
    const progressPercent = Math.min((queueSeconds / MAX_QUEUE_TIME) * 100, 100);
    document.getElementById('queueProgressBar').style.width = progressPercent + '%';
}

/**
 * Forcer un match contre un bot
 */
function forceMatchWithBot() {
    clearInterval(queueInterval);
    
    fetch('api.php?action=force_bot_match_5v5', {
        method: 'POST',
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok' && data.match_id) {
            window.location.href = 'multiplayer-combat.php?match_id=' + data.match_id;
        } else {
            alert('Erreur lors de la création du match contre le bot');
            cancelQueue();
        }
    })
    .catch(err => {
        console.error('Bot match error:', err);
        alert('Erreur de connexion');
        cancelQueue();
    });
}

/**
 * Annuler la recherche
 */
function cancelQueue() {
    clearInterval(queueInterval);
    
    fetch('api.php?action=leave_queue_5v5', {
        method: 'POST',
        credentials: 'same-origin'
    }).catch(() => {}); // Ignorer les erreurs
    
    // Retourner à l'écran de sélection
    document.getElementById('queueScreen').style.display = 'none';
    document.getElementById('selectionScreen').style.display = 'block';
    
    // Reset
    queueSeconds = 0;
    selectedTeamData = null;
}

// Event listener pour le bouton annuler
document.getElementById('cancelQueue').addEventListener('click', cancelQueue);
</script>

<?php 
$showBackLink = false; // Le back-link est déjà dans la page
require_once __DIR__ . '/includes/footer.php'; 
?>
