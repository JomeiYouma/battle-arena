/**
 * SYSTÈME D'ANIMATIONS DE COMBAT PARTAGÉ
 * Utilisé par single_player.php et multiplayer.php
 * 
 * Configuration requise globalement:
 * - combatAnimConfig.getActorContainer(isMe)
 * - combatAnimConfig.getTargetContainer(isMe)
 * - combatAnimConfig.getActorFighter(isMe)
 * - combatAnimConfig.updateStats(states)
 * - combatAnimConfig.triggerHitAnimation(fighter)
 * 
 * Variable optionnelle:
 * - ASSET_BASE_PATH: chemin vers le dossier public/ (défini par PHP)
 */

// Helper pour construire les chemins d'assets
function getAssetPath(relativePath) {
    const basePath = (typeof ASSET_BASE_PATH !== 'undefined') ? ASSET_BASE_PATH : '';
    return basePath + relativePath;
}

async function playTurnAnimations(turnActions) {
    if (!turnActions || turnActions.length === 0 || isPlayingAnimations) return;
    
    isPlayingAnimations = true;
    
    for (const action of turnActions) {
        // Handle blessing passive animations
        if (action.phase === 'blessing_passive') {
            await playBlessingAnimation(action);
        } else {
            await playAction(action);
        }
    }
    
    isPlayingAnimations = false;
}

function playBlessingAnimation(action) {
    return new Promise(resolve => {
        // Déterminer si c'est l'action de l'acteur ou du cible
        const isMe = combatAnimConfig.isMyAction(action.actor);
        
        const actorContainer = combatAnimConfig.getActorContainer(isMe);
        const actorFighter = combatAnimConfig.getActorFighter(isMe);
        
        if (!actorFighter) {
            setTimeout(() => resolve(), 1500);
            return;
        }
        
        // 1. Show action name with blessing styling (violet)
        const nameElement = document.createElement('div');
        nameElement.className = 'action-name-display blessing-passive';
        nameElement.textContent = action.label || action.message || 'Blessing activé';
        if (actorContainer) actorContainer.appendChild(nameElement);
        
        // 2. Show large blessing icon
        const imgContainer = document.createElement('div');
        imgContainer.className = 'blessing-action-img on-self';
        
        const img = document.createElement('img');
        img.src = action.icon || getAssetPath('media/blessings/moon.png');
        img.alt = action.name || 'Blessing';
        imgContainer.appendChild(img);
        
        if (actorContainer) actorContainer.appendChild(imgContainer);
        
        // 3. Update stats after delay
        setTimeout(() => {
            if (action.statesAfter && combatAnimConfig.updateStats) {
                combatAnimConfig.updateStats(action.statesAfter);
            }
        }, 750);
        
        // 4. Clean up
        setTimeout(() => {
            if (actorContainer && actorContainer.contains(nameElement)) {
                actorContainer.removeChild(nameElement);
            }
            if (actorContainer && actorContainer.contains(imgContainer)) {
                actorContainer.removeChild(imgContainer);
            }
            resolve();
        }, 1500);
    });
}

function playAction(action) {
    return new Promise(resolve => {
        const isMe = combatAnimConfig.isMyAction(action.actor);
        
        const emoji = action.emoji || '⚔️';
        const actionName = action.label || 'Effet';
        const phase = action.phase || 'action';
        
        // Get containers
        const actorContainer = combatAnimConfig.getActorContainer(isMe);
        const targetContainer = combatAnimConfig.getTargetContainer(isMe);
        const actorFighter = combatAnimConfig.getActorFighter(isMe);
        const targetFighter = combatAnimConfig.getTargetFighter(isMe);
        
        // --- DEATH ANIMATION ---
        if (action.isDeath || phase === 'death') {
            const deathElement = document.createElement('div');
            deathElement.className = 'action-name-display death-label';
            deathElement.textContent = '💀 K.O.';
            if (actorContainer) actorContainer.appendChild(deathElement);
            
            if (actorFighter) {
                actorFighter.classList.add('fighter-dead');
            }
            
            setTimeout(() => {
                if (actorContainer && actorContainer.contains(deathElement)) {
                    actorContainer.removeChild(deathElement);
                }
                resolve();
            }, 2000);
            return;
        }
        
        // --- STANDARD ACTION ---
        // 1. Show action name (on actor's emoji container)
        const nameElement = document.createElement('div');
        nameElement.className = 'action-name-display';
        nameElement.textContent = actionName;
        if (actorContainer) actorContainer.appendChild(nameElement);
        
        // 2. Determine where to show emoji
        let emojiContainer = null;
        let cssClass = 'action-emoji';
        
        if (phase === 'damage_effect' || phase === 'stat_effect') {
            emojiContainer = actorContainer;
            cssClass += ' on-self';
        } else {
            if (action.needsTarget !== false) {
                emojiContainer = targetContainer;
                cssClass += ' on-target';
            } else {
                emojiContainer = actorContainer;
                cssClass += ' on-self';
            }
        }
        
        const emojiElement = document.createElement('div');
        emojiElement.className = cssClass;
        emojiElement.textContent = emoji;
        if (emojiContainer) emojiContainer.appendChild(emojiElement);
        
        // Trigger hit animation on target if action targets them
        if (action.needsTarget !== false && phase === 'action') {
            if (combatAnimConfig.triggerHitAnimation && targetFighter) {
                combatAnimConfig.triggerHitAnimation(targetFighter);
            }
        }
        
        // 3. Update stats after delay
        setTimeout(() => {
            if (action.statesAfter && combatAnimConfig.updateStats) {
                combatAnimConfig.updateStats(action.statesAfter);
            }
        }, 750);
        
        // 4. Clean up
        setTimeout(() => {
            if (actorContainer && actorContainer.contains(nameElement)) {
                actorContainer.removeChild(nameElement);
            }
            if (emojiContainer && emojiContainer.contains(emojiElement)) {
                emojiContainer.removeChild(emojiElement);
            }
            resolve();
        }, 1500);
    });
}

// Add CSS animation for emojis if not already present
if (!document.querySelector('style[data-combat-anim]')) {
    const style = document.createElement('style');
    style.setAttribute('data-combat-anim', 'true');
    style.textContent = `
        @keyframes emojiPop {
            0% { transform: scale(0.5); opacity: 1; }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}
