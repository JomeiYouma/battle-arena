/**
 * MULTIPLAYER 5v5 SELECTION - Queue System
 * Gestion de la sélection d'équipe et du matchmaking 5v5
 */

// Configuration globale (initialisée depuis PHP)
let ASSET_BASE_PATH = '';
let PLAYER_DISPLAY_NAME = 'Joueur';

let selectedTeamData = null;
let queueInterval = null;
let queueSeconds = 0;
const MAX_QUEUE_TIME = 30;

/**
 * Initialiser le module avec les paramètres PHP
 */
function init5v5Selection(assetBasePath, displayName) {
    ASSET_BASE_PATH = assetBasePath;
    PLAYER_DISPLAY_NAME = displayName;
    
    // Event listener pour le bouton annuler
    const cancelBtn = document.getElementById('cancelQueue');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', cancelQueue);
    }
}

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
    previewContainer.innerHTML = teamHeroes.map(hero => {
        const imgPath = hero.images && hero.images.p1 ? ASSET_BASE_PATH + hero.images.p1 : ASSET_BASE_PATH + 'media/heroes/default.png';
        return `<img src="${imgPath}" alt="${hero.name}" title="${hero.name}">`;
    }).join('');
    
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
    
    fetch('../../api.php?action=join_queue_5v5', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            team: selectedTeamData.heroes,  // Contient blessing_id pour chaque héros
            team_id: selectedTeamData.team_id,
            team_name: selectedTeamData.team_name,
            display_name: PLAYER_DISPLAY_NAME
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
    
    fetch('../../api.php?action=poll_queue_5v5', {
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
    
    fetch('../../api.php?action=force_bot_match_5v5', {
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
    
    fetch('../../api.php?action=leave_queue_5v5', {
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
