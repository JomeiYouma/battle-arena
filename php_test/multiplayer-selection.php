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

// Charger la liste des héros et définitions des bénédictions (partagés)
// Configuration du header
$pageTitle = 'Multijoueur - Horus Battle Arena';
$extraCss = ['shared-selection', 'multiplayer'];
$showUserBadge = false; // On affiche le nom dans le formulaire
$showMainTitle = false; // Le titre est dans le composant
require_once __DIR__ . '/includes/header.php';

?>

<div class="multi-container">
    <!-- ÉCRAN 1: SÉLECTION DU HÉROS (avec composant partagé) -->
    <div id="selectionScreen">
        <?php
        // Inclure le composant de sélection
        include 'components/selection-screen.php';
        
        // Préparer la configuration
        $selectionConfig = [
            'mode' => 'multiplayer',
            'showPlayerNameInput' => true,
            'displayNameValue' => User::isLoggedIn() ? User::getCurrentUsername() : null,
            'displayNameIsStatic' => User::isLoggedIn()
        ];
        
        renderSelectionScreen($selectionConfig);
        ?>
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

<!-- Tooltip System -->
<div id="customTooltip" class="custom-tooltip"></div>
<script src="js/selection-tooltip.js"></script>

<script>
let selectedHeroId = null;
let selectedBlessingId = null;
let queuePollInterval = null;
let countdownInterval = null;
let remainingTime = 30;
let isInQueue = false;

// Intercepter la soumission du formulaire
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.select-screen form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Récupérer les valeurs du formulaire
            selectedHeroId = document.querySelector('input[name="hero_choice"]:checked')?.value;
            selectedBlessingId = document.querySelector('input[name="blessing_choice"]:checked')?.value || '';
            
            // Récupérer le display name
            let displayName = null;
            const displayNameInput = document.getElementById('playerName') || document.getElementById('displayName');
            if (displayNameInput) {
                displayName = displayNameInput.value?.trim();
            }
            
            // Valider sélection héros
            if (!selectedHeroId) {
                alert('Veuillez choisir un héros !');
                return;
            }
            
            // Récupérer les données du héros pour l'affichage
            const heroRadio = document.querySelector(`input[name="hero_choice"][value="${selectedHeroId}"]`);
            const heroRow = heroRadio?.closest('.hero-row');
            const heroImg = heroRow?.querySelector('img')?.src || 'media/heroes/placeholder.png';
            const heroType = heroRow?.querySelector('.type-badge')?.textContent || 'Hero';
            const heroName = heroRow?.querySelector('h4')?.textContent || 'Unknown';
            
            // Si pas de nom, utiliser le nom du héros par défaut
            if (!displayName) {
                displayName = heroName;
            }
            
            // Afficher l'aperçu dans la queue
            document.getElementById('previewImg').src = heroImg;
            document.getElementById('previewName').textContent = displayName;
            document.getElementById('previewType').textContent = heroType + ' (' + heroName + ')';
            
            // Basculer vers l'écran de queue
            document.querySelector('.select-screen').style.display = 'none';
            document.getElementById('queueScreen').style.display = 'block';
            
            // Démarrer compte à rebours
            remainingTime = 30;
            document.getElementById('countdown').textContent = remainingTime;
            
            countdownInterval = setInterval(() => {
                remainingTime--;
                document.getElementById('countdown').textContent = Math.max(0, remainingTime);
            }, 1000);
            
            // Rejoindre la queue via API
            joinQueue(selectedHeroId, displayName, selectedBlessingId);
        });
    }
});

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
    window.location.href = 'multiplayer-combat.php?match_id=' + matchId;
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
    document.getElementById('queueScreen').style.display = 'none';
    document.querySelector('.select-screen').style.display = 'block';
    
    selectedHeroId = null;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
