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
    }
    spl_autoload_register('chargerClasse');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger la liste des héros
$heros = json_decode(file_get_contents('heros.json'), true);
?>

<style>
/* ===== HERO SELECTION GRID AMÉLIORÉ ===== */
.multi-container {
    max-width: 1000px;
    margin: 30px auto;
    padding: 0 20px;
}

.multi-title {
    text-align: center;
    color: #ffd700;
    font-size: 28px;
    margin-bottom: 10px;
    text-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
    letter-spacing: 2px;
}

.multi-subtitle {
    text-align: center;
    color: #b8860b;
    font-size: 14px;
    margin-bottom: 30px;
    font-style: italic;
}

/* ===== DISPLAY NAME INPUT ===== */
.display-name-section {
    max-width: 400px;
    margin: 0 auto 30px;
    background: rgba(20, 20, 30, 0.8);
    border: 2px solid #4a0000;
    border-radius: 10px;
    padding: 20px;
}

.display-name-section label {
    display: block;
    color: #b8860b;
    font-size: 14px;
    margin-bottom: 8px;
}

.display-name-input {
    width: 100%;
    padding: 12px 15px;
    background: rgba(10, 10, 15, 0.9);
    border: 2px solid #4a0000;
    border-radius: 8px;
    color: #ffd700;
    font-size: 16px;
    text-align: center;
    transition: all 0.3s;
}

.display-name-input:focus {
    outline: none;
    border-color: #c41e3a;
    box-shadow: 0 0 15px rgba(196, 30, 58, 0.3);
}

.display-name-input::placeholder {
    color: #666;
}

.hero-select-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.hero-card-btn {
    background: linear-gradient(145deg, rgba(30, 30, 40, 0.95), rgba(20, 15, 25, 0.98));
    border: 2px solid #4a0000;
    border-radius: 12px;
    padding: 0;
    cursor: pointer;
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.hero-card-btn:hover {
    transform: translateY(-8px) scale(1.02);
    border-color: #c41e3a;
    box-shadow: 0 15px 40px rgba(196, 30, 58, 0.4), 
                0 0 30px rgba(255, 215, 0, 0.2),
                inset 0 0 20px rgba(196, 30, 58, 0.1);
}

.hero-card-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #c41e3a, transparent);
    opacity: 0;
    transition: opacity 0.3s;
}

.hero-card-btn:hover::before {
    opacity: 1;
}

.hero-card-content {
    padding: 15px;
    text-align: center;
}

.hero-card-content img {
    width: 100px;
    height: 100px;
    object-fit: contain;
    margin-bottom: 10px;
    filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.5));
    transition: transform 0.3s, filter 0.3s;
}

.hero-card-btn:hover .hero-card-content img {
    transform: scale(1.1);
    filter: drop-shadow(0 8px 20px rgba(196, 30, 58, 0.5));
}

.hero-card-content h4 {
    color: #e0e0e0;
    font-size: 16px;
    margin: 8px 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
}

.hero-card-content .type-badge {
    display: inline-block;
    background: linear-gradient(135deg, #c41e3a, #7a1226);
    color: #fff;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.hero-stats-preview {
    color: #808080;
    font-size: 11px;
    line-height: 1.6;
}

.hero-stats-preview span {
    color: #b8860b;
}

/* ===== QUEUE SCREEN ===== */
.queue-screen {
    display: none;
    text-align: center;
    padding: 60px 20px;
}

.queue-screen.active {
    display: block;
}

.queue-loader {
    width: 80px;
    height: 80px;
    border: 4px solid #2a2a3a;
    border-top: 4px solid #c41e3a;
    border-radius: 50%;
    margin: 0 auto 30px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.queue-title {
    color: #ffd700;
    font-size: 24px;
    margin-bottom: 20px;
    text-shadow: 0 0 15px rgba(255, 215, 0, 0.4);
}

.queue-box {
    background: rgba(20, 20, 30, 0.9);
    border: 2px solid #c41e3a;
    border-radius: 15px;
    padding: 30px;
    max-width: 400px;
    margin: 0 auto 30px;
}

.queue-countdown {
    font-size: 64px;
    font-weight: bold;
    color: #c41e3a;
    font-family: monospace;
    text-shadow: 0 0 30px rgba(196, 30, 58, 0.6);
    margin-bottom: 10px;
}

.queue-countdown-label {
    color: #808080;
    font-size: 14px;
}

.queue-message {
    color: #b8860b;
    font-size: 15px;
    margin: 25px 0;
    line-height: 1.6;
}

.queue-hero-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 10px;
}

.queue-hero-preview img {
    width: 60px;
    height: 60px;
    object-fit: contain;
}

.queue-hero-preview .info {
    text-align: left;
}

.queue-hero-preview .info h4 {
    color: #ffd700;
    margin: 0 0 5px;
}

.queue-hero-preview .info span {
    color: #808080;
    font-size: 12px;
}

.cancel-queue-btn {
    background: linear-gradient(135deg, #4a0000, #2a0000);
    border: 2px solid #6a0000;
    color: #ff6b6b;
    padding: 12px 30px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.cancel-queue-btn:hover {
    background: linear-gradient(135deg, #6a0000, #4a0000);
    border-color: #c41e3a;
    transform: scale(1.05);
}

.selection-screen.hidden {
    display: none;
}
</style>

<link rel="stylesheet" href="./style.css">

<div class="multi-container">
    <!-- ÉCRAN 1: SÉLECTION DU HÉROS -->
    <div class="selection-screen" id="selectionScreen">
        <h2 class="multi-title">⚔️ CHOISISSEZ VOTRE CHAMPION ⚔️</h2>
        <p class="multi-subtitle">Sélectionnez un héros pour entrer dans l'arène multijoueur</p>
        
        <!-- CHAMP DISPLAY NAME -->
        <?php if (User::isLoggedIn()): ?>
            <div class="display-name-section" style="background: linear-gradient(135deg, rgba(20, 20, 30, 0.9), rgba(184, 134, 11, 0.1)); border-color: #b8860b;">
                <label style="margin-bottom: 12px;">👤 Vous jouez en tant que</label>
                <div style="color: #ffd700; font-size: 20px; font-weight: bold; text-shadow: 0 0 15px rgba(255, 215, 0, 0.4);">
                    <?php echo htmlspecialchars(User::getCurrentUsername()); ?>
                </div>
            </div>
            <input type="hidden" id="displayName" value="<?php echo htmlspecialchars(User::getCurrentUsername()); ?>">
        <?php else: ?>
            <div class="display-name-section">
                <label for="displayName">👤 Votre nom de joueur</label>
                <input type="text" 
                       id="displayName" 
                       class="display-name-input" 
                       placeholder="Entrez votre pseudo..." 
                       maxlength="20">
            </div>
        <?php endif; ?>
        
        <div class="hero-select-grid">
            <?php foreach ($heros as $h): ?>
            <button type="button" class="hero-card-btn" onclick="selectHero('<?php echo $h['id']; ?>', '<?php echo addslashes($h['name']); ?>', '<?php echo $h['images']['p1']; ?>', '<?php echo ucfirst($h['type']); ?>')">
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
        
<!--         <div style="text-align: center;">
            <a href="index.php" class="action-btn abandon">Retour au menu</a>
        </div> -->
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
            <div id="queueInfo" style="margin-top: 15px; font-size: 14px; color: #b8860b;">Connexion...</div>
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
let queuePollInterval = null;
let countdownInterval = null;
let remainingTime = 30;
let isInQueue = false;

function selectHero(heroId, heroName, heroImg, heroType) {
    selectedHeroId = heroId;
    
    // Récupérer le display name
    const displayNameInput = document.getElementById('displayName');
    const displayName = displayNameInput.value.trim() || heroName;
    
    // Afficher l'aperçu du héros avec le display name
    document.getElementById('previewImg').src = heroImg;
    document.getElementById('previewName').textContent = displayName;
    document.getElementById('previewType').textContent = heroType + ' (' + heroName + ')';
    
    // Basculer vers l'écran de queue
    document.getElementById('selectionScreen').classList.add('hidden');
    document.getElementById('queueScreen').classList.add('active');
    
    // Démarrer le compte à rebours visuel
    remainingTime = 30;
    document.getElementById('countdown').textContent = remainingTime;
    
    countdownInterval = setInterval(() => {
        remainingTime--;
        document.getElementById('countdown').textContent = Math.max(0, remainingTime);
    }, 1000);
    
    // Rejoindre la queue via API
    joinQueue(heroId, displayName);
}

function joinQueue(heroId, displayName) {
    fetch('api.php?action=join_queue', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'hero_id=' + encodeURIComponent(heroId) + '&display_name=' + encodeURIComponent(displayName)
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
