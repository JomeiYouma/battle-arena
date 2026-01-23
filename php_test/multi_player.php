<?php
/**
 * MULTIPLAYER MODE - Sélection héros + Queue
 */

session_start();

// Reset queue
if (isset($_POST['leave_queue'])) {
    unset($_SESSION['queueHeroId']);
    unset($_SESSION['queueStartTime']);
    header("Location: multi_player.php");
    exit;
}

// Charger la liste des héros
$heros = json_decode(file_get_contents('heros.json'), true);
?>

<link rel="stylesheet" href="./style.css">

<div class="multi-container">
    <?php if (!isset($_SESSION['queueHeroId'])): ?>
        <!-- ÉCRAN 1: SÉLECTION DU HÉROS -->
        <div class="screen active" id="screenSelection">
            <h2 style="text-align: center; color: #e0e0e0; margin-bottom: 30px;">🎮 Choisissez votre Champion</h2>
            
            <div class="hero-select-grid">
                <?php foreach ($heros as $h): ?>
                <div class="hero-card" onclick="selectHero('<?php echo $h['id']; ?>')">
                    <div class="hero-card-content">
                        <img src="<?php echo $h['images']['p1']; ?>" alt="<?php echo $h['name']; ?>">
                        <h4><?php echo $h['name']; ?></h4>
                        <span class="type-badge"><?php echo ucfirst($h['type']); ?></span>
                        <div class="hero-stats-preview">
                            <span>❤️ <?php echo $h['pv']; ?></span> | 
                            <span>⚔️ <?php echo $h['atk']; ?></span> |
                            <span>⚡ <?php echo $h['speed']; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- ÉCRAN 2: QUEUE D'ATTENTE -->
        <div class="screen active" id="screenQueue">
            <div class="queue-container" style="max-width: 600px; margin: 80px auto; text-align: center;">
                <h2 style="color: #e0e0e0; margin-bottom: 40px;">⏳ Recherche d'adversaire...</h2>
                
                <div class="loader"></div>
                
                <!-- COMPTEUR QUEUE -->
                <div style="background: rgba(20, 20, 30, 0.9); border: 2px solid #c41e3a; border-radius: 10px; padding: 20px; margin: 30px 0;">
                    <div style="font-size: 14px; color: #b8860b; margin-bottom: 10px;">JOUEURS EN ATTENTE</div>
                    <div style="font-size: 48px; font-weight: bold; color: #ffd700; font-family: monospace;" id="queueCount">--</div>
                    <div style="font-size: 12px; color: #808080; margin-top: 10px;">Y compris vous</div>
                </div>
                
                <!-- COUNTDOWN -->
                <div style="color: #c41e3a; font-size: 18px; margin: 20px 0; font-weight: bold;">
                    ⏰ Timeout dans <span id="countdownTimer">30</span>s
                </div>
                
                <!-- MESSAGE -->
                <p style="color: #b8860b; font-size: 16px; margin: 20px 0; line-height: 1.6;">
                    Un combat contre <strong>un bot</strong> débutera si personne ne se présente.
                </p>
                
                <!-- CANCEL BUTTON -->
                <form method="POST" style="margin-top: 40px;">
                    <button type="submit" name="leave_queue" class="action-btn abandon">❌ Annuler la Recherche</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const QUEUE_HERO_ID = '<?php echo $_SESSION['queueHeroId'] ?? ''; ?>';

function selectHero(heroId) {
    // Store in backend via AJAX
    fetch('api.php?action=join_queue', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'hero_id=' + heroId
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'matched') {
            // Direct match - go to combat
            window.location.href = 'combat.php?match_id=' + data.matchId;
        } else if (data.status === 'waiting') {
            // Go to queue screen
            document.getElementById('screenSelection').classList.remove('active');
            document.getElementById('screenQueue').classList.add('active');
            startQueue();
        } else {
            alert("Erreur: " + data.message);
        }
    })
    .catch(err => {
        alert("Erreur réseau: " + err);
    });
}

let countdownInterval = null;
let queueStartTime = null;
let pollInterval = null;

function startQueue() {
    queueStartTime = Date.now();
    startCountdown();
    pollQueue();
    pollInterval = setInterval(pollQueue, 1000);
}

function startCountdown() {
    const timerEl = document.getElementById('countdownTimer');
    countdownInterval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - queueStartTime) / 1000);
        const remaining = Math.max(0, 30 - elapsed);
        timerEl.innerText = remaining;
        
        if (remaining === 0) {
            clearInterval(countdownInterval);
        }
    }, 100);
}

function pollQueue() {
    fetch('api.php?action=poll_queue')
        .then(r => r.json())
        .then(data => {
            // Update queue count
            if (data.queue_count !== undefined) {
                document.getElementById('queueCount').innerText = data.queue_count;
            }
            
            // Check for match
            if (data.status === 'matched') {
                clearInterval(pollInterval);
                clearInterval(countdownInterval);
                window.location.href = 'combat.php?match_id=' + data.matchId;
            } else if (data.status === 'timeout') {
                clearInterval(pollInterval);
                clearInterval(countdownInterval);
                window.location.href = 'combat.php?match_id=' + data.matchId;
            }
        })
        .catch(err => {
            console.error('Poll error:', err);
        });
}

// Start if already in queue
if (QUEUE_HERO_ID) {
    document.getElementById('screenSelection').classList.remove('active');
    document.getElementById('screenQueue').classList.add('active');
    startQueue();
</script>
