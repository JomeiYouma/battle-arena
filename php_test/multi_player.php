<?php
/**
 * MULTIPLAYER MODE - Sélection héros + Queue 30s → Combat vs Bot
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
    }
    spl_autoload_register('chargerClasse');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger la liste des héros et définitions des bénédictions (temporaire, idéalement en DB ou JSON)
$heros = json_decode(file_get_contents('heros.json'), true);

$blessingsList = [
    ['id' => 'WheelOfFortune', 'name' => 'Roue de Fortune', 'emoji' => '🎰', 'desc' => 'Portée aléatoire doublée (ex: 1-10 → -4-15) + Action "Concoction Maladroite"'],
    ['id' => 'LoversCharm', 'name' => 'Charmes Amoureux', 'emoji' => '💘', 'desc' => 'Renvoie 25% des dégâts reçus + Action "Foudre de l\'Amour" (Paralysie)'],
    ['id' => 'JudgmentOfDamned', 'name' => 'Jugement des Maudits', 'emoji' => '⚖️', 'desc' => 'Se soigner baisse la DEF. Actions "Grand Conseil" & "Sentence"'],
    ['id' => 'StrengthFavor', 'name' => 'Faveur de Force', 'emoji' => '💪', 'desc' => 'DEF -75%, ATK +33%. Action "Transe Guerrière" (Immunité)'],
    ['id' => 'MoonCall', 'name' => 'Appel de la Lune', 'emoji' => '🌙', 'desc' => 'Cycle 4 tours : Stats boostées mais coût PP double.'],
    ['id' => 'WatchTower', 'name' => 'La Tour de Garde', 'emoji' => '🏰', 'desc' => 'ATK utilise DEF. Action "Fortifications" (+5 DEF)'],
    ['id' => 'RaChariot', 'name' => 'Chariot de Ra', 'emoji' => '☀️', 'desc' => '+50% VIT. Durée effets: Ennemis +2, Soi -1. Action "Jour Nouveau"'],
    ['id' => 'HangedMan', 'name' => 'Corde du Pendu', 'emoji' => '🪢', 'desc' => 'Action "Nœud de Destin" (Lien de dégâts)']
];
?>

<link rel="stylesheet" href="./style.css">
<link rel="stylesheet" href="./css/multiplayer.css">

<div class="multi-container">
    <!-- ÉCRAN 1: SÉLECTION DU HÉROS -->
    <div class="selection-screen" id="selectionScreen">
        <h2 class="multi-title">⚔️ CHOISISSEZ VOTRE COMPOSITION ⚔️</h2>
        <p class="multi-subtitle">Sélectionnez un héros pour entrer dans l'arène multijoueur</p>
        
        <!-- CHAMP DISPLAY NAME -->
        <?php if (User::isLoggedIn()): ?>
            <div class="display-name-section highlighted">
                <label class="display-name-label">Vous jouez en tant que</label>
                <div class="display-name-value">
                    <?php echo htmlspecialchars(User::getCurrentUsername()); ?>
                </div>
            </div>
            <input type="hidden" id="displayName" value="<?php echo htmlspecialchars(User::getCurrentUsername()); ?>">
        <?php else: ?>
            <div class="display-name-section">
                <label for="displayName">Votre nom de joueur</label>
                <input type="text" 
                       id="displayName" 
                       class="display-name-input" 
                       placeholder="Entrez votre pseudo..." 
                       maxlength="20">
            </div>
        <?php endif; ?>
        
        <div class="step-indicator" id="stepIndicator">
            <span class="active">HÉROS</span> &nbsp;->&nbsp; <span>BÉNÉDICTION</span>
        </div>

        <!-- STEP 1: HEROES -->
        <div id="heroStep">
            <div class="hero-select-grid">
                <?php foreach ($heros as $h): ?>
                <button type="button" class="hero-card-btn" onclick="preSelectHero('<?php echo $h['id']; ?>', '<?php echo addslashes($h['name']); ?>', '<?php echo $h['images']['p1']; ?>', '<?php echo ucfirst($h['type']); ?>')">
                    <div class="hero-card-content">
                        <img src="<?php echo $h['images']['p1']; ?>" alt="<?php echo $h['name']; ?>">
                        <h4><?php echo $h['name']; ?></h4>
                        <span class="type-badge"><?php echo ucfirst($h['type']); ?></span>
                        <div class="hero-stats-preview">
                            <span>PV: <?php echo $h['pv']; ?></span> | 
                            <span>ATK: <?php echo $h['atk']; ?></span> | 
                            <span>DEF: <?php echo $h['def'] ?? 5; ?></span> | 
                            <span>SPE: <?php echo $h['speed']; ?></span>
                        </div>
                    </div>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- STEP 2: BLESSINGS (Hidden initially) -->
        <div id="blessingStep" class="blessing-step">
            <h3 class="blessing-step-title">Choisissez une Bénédiction</h3>
            
            <div class="blessing-grid">
                <?php foreach ($blessingsList as $b): ?>
                <div class="blessing-card" onclick="selectBlessing('<?php echo $b['id']; ?>', this)">
                    <div class="blessing-header">
                        <span class="blessing-emoji"><?php echo $b['emoji']; ?></span>
                        <span><?php echo $b['name']; ?></span>
                    </div>
                    <div class="blessing-desc">
                        <?php echo $b['desc']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="blessing-step-actions">
                <button type="button" class="cancel-queue-btn secondary" onclick="backToHero()">
                    Retour
                </button>
                <button type="button" class="cancel-queue-btn confirm" onclick="confirmSelection()">
                    COMBATTRE
                </button>
            </div>
        </div>
    </div>

    <!-- ÉCRAN 2: QUEUE D'ATTENTE -->
    <div class="queue-screen" id="queueScreen">
        <div class="queue-loader"></div>
        
        <h2 class="queue-title">Recherche d'adversaire...</h2>
        
        <div class="queue-box">
            <div class="queue-hero-preview" id="heroPreview">
                <img src="" alt="Hero" id="previewImg">
                <div class="info">
                    <h4 id="previewName">-</h4>
                    <span id="previewType">-</span>
                </div>
            </div>
            
            <div class="queue-countdown" id="countdown">30</div>
            <div class="queue-countdown-label">secondes restantes</div>
            <div id="queueInfo" class="queue-info">Connexion...</div>
        </div>
        
        <p class="queue-message">
            🎮 Si aucun joueur ne se présente,<br>
            un <strong>bot</strong> vous affrontera !
        </p>
        
        <button type="button" class="cancel-queue-btn" onclick="cancelQueue()">
            ❌ Annuler la recherche
        </button>
    </div>
    
</div>

<script>
let selectedHeroId = null;
let selectedHeroData = {}; // {name, img, type}
let selectedBlessingId = null;
let queuePollInterval = null;
let countdownInterval = null;
let remainingTime = 30;
let isInQueue = false;

function preSelectHero(heroId, heroName, heroImg, heroType) {
    selectedHeroId = heroId;
    selectedHeroData = { name: heroName, img: heroImg, type: heroType };
    
    // Switch to step 2
    document.getElementById('heroStep').style.display = 'none';
    document.getElementById('blessingStep').style.display = 'block';
    
    // Update indicator
    const spans = document.getElementById('stepIndicator').querySelectorAll('span');
    spans[0].classList.remove('active');
    spans[1].classList.add('active');
}

function backToHero() {
    document.getElementById('heroStep').style.display = 'block';
    document.getElementById('blessingStep').style.display = 'none';
    
    selectedHeroId = null;
    selectedBlessingId = null;
    
    // Reset selection logic visual if needed
    document.querySelectorAll('.blessing-card').forEach(c => c.classList.remove('selected'));
    
    const spans = document.getElementById('stepIndicator').querySelectorAll('span');
    spans[0].classList.add('active');
    spans[1].classList.remove('active');
}

function selectBlessing(id, cardElement) {
    selectedBlessingId = id;
    
    document.querySelectorAll('.blessing-card').forEach(c => c.classList.remove('selected'));
    cardElement.classList.add('selected');
}

function confirmSelection() {
    if (!selectedHeroId) {
        alert("Veuillez choisir un héros !");
        backToHero();
        return;
    }
    // Si pas de bénédiction sélectionnée, on pourrait dire que c'est optionnel ou en forcer une ?
    // Optionnel pour l'instant (null)
    
    finalizeSelection(selectedHeroId, selectedHeroData.name, selectedHeroData.img, selectedHeroData.type, selectedBlessingId);
}

function finalizeSelection(heroId, heroName, heroImg, heroType, blessingId) {
    // Récupérer le display name
    const displayNameInput = document.getElementById('displayName');
    const displayName = displayNameInput.value.trim() || heroName;
    
    // Afficher l'aperçu
    document.getElementById('previewImg').src = heroImg;
    document.getElementById('previewName').textContent = displayName;
    document.getElementById('previewType').textContent = heroType + ' (' + heroName + ')';
    
    // Basculer vers l'écran de queue
    document.getElementById('selectionScreen').classList.add('hidden');
    document.getElementById('queueScreen').classList.add('active');
    
    // Démarrer compte à rebours
    remainingTime = 30;
    document.getElementById('countdown').textContent = remainingTime;
    
    countdownInterval = setInterval(() => {
        remainingTime--;
        document.getElementById('countdown').textContent = Math.max(0, remainingTime);
    }, 1000);
    
    // Rejoindre la queue via API
    joinQueue(heroId, displayName, blessingId);
}

function joinQueue(heroId, displayName, blessingId) {
    let body = 'hero_id=' + encodeURIComponent(heroId) + '&display_name=' + encodeURIComponent(displayName);
    if (blessingId) {
        body += '&blessing_id=' + encodeURIComponent(blessingId);
    }

    fetch('api.php?action=join_queue', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'matched') {
            // Match trouvé immédiatement !
            goToMatch(data.matchId);
        } else if (data.status === 'waiting') {
            // En attente, commencer le polling
            isInQueue = true;
            updateQueueInfo(data.queue_count || 0);
            queuePollInterval = setInterval(pollQueueStatus, 2000);
        } else if (data.status === 'error') {
            console.error('Join queue error:', data.message);
            showQueueError(data.message);
        }
    })
    .catch(err => {
        console.error('Join queue failed:', err);
        showQueueError('Erreur de connexion au serveur');
    });
}

function pollQueueStatus() {
    fetch('api.php?action=poll_queue', {
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'matched' || data.status === 'timeout') {
            // Match trouvé (PvP ou bot après timeout)
            goToMatch(data.matchId);
        } else if (data.status === 'waiting') {
            updateQueueInfo(data.queue_count || 0);
        } else if (data.status === 'error') {
            console.error('Poll error:', data.message);
        }
    })
    .catch(err => {
        console.error('Poll failed:', err);
    });
}

function goToMatch(matchId) {
    // Arrêter les intervals
    if (queuePollInterval) clearInterval(queuePollInterval);
    if (countdownInterval) clearInterval(countdownInterval);
    
    // Rediriger vers le combat
    window.location.href = 'multiplayer.php?match_id=' + matchId;
}

function updateQueueInfo(count) {
    const queueInfo = document.getElementById('queueInfo');
    if (queueInfo) {
        if (count > 0) {
            queueInfo.textContent = count + ' joueur(s) en recherche';
            queueInfo.style.color = '#4ade80';
        } else {
            queueInfo.textContent = 'Recherche en cours...';
            queueInfo.style.color = '#b8860b';
        }
    }
}

function showQueueError(message) {
    if (countdownInterval) clearInterval(countdownInterval);
    if (queuePollInterval) clearInterval(queuePollInterval);
    
    const countdown = document.getElementById('countdown');
    if (countdown) {
        countdown.textContent = '!';
        countdown.style.color = '#ff4444';
    }
    
    alert('Erreur: ' + message);
    cancelQueue();
}

function cancelQueue() {
    if (countdownInterval) clearInterval(countdownInterval);
    if (queuePollInterval) clearInterval(queuePollInterval);
    
    // Informer le serveur qu'on quitte la queue
    if (isInQueue) {
        fetch('api.php?action=leave_queue', {
            method: 'POST',
            credentials: 'same-origin'
        }).catch(() => {});
        isInQueue = false;
    }
    
    // Retour à la sélection
    document.getElementById('queueScreen').classList.remove('active');
    document.getElementById('selectionScreen').classList.remove('hidden');
    
    selectedHeroId = null;
}
</script>
