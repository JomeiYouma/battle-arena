<?php
/**
 * COMPOSANTS DE COMBAT UNIFIÉS
 * Interface harmonisée pour single et multiplayer
 */

/**
 * Génère l'en-tête de l'arène (tour + mode + timer)
 */
function renderBattleHeader(int $turn, string $mode = 'solo', int $timerSeconds = 30): string {
    $modeLabel = $mode === 'multiplayer' ? '⚔️ PvP' : '🤖 Solo';
    return <<<HTML
    <div class="battle-header">
        <span class="mode-badge">{$modeLabel}</span>
        <span class="turn-indicator" id="turnIndicator">Tour {$turn}</span>
        <div class="header-timer" id="headerTimer">
            <span class="timer-icon">⏱️</span>
            <span class="timer-seconds" id="timerSeconds">{$timerSeconds}</span>s
        </div>
    </div>
HTML;
}

/**
 * Génère les stats d'un combattant
 */
function renderFighterStats(array $fighter, string $position = 'left', string $idPrefix = 'hero'): string {
    $name = htmlspecialchars($fighter['name']);
    $type = $fighter['type'];
    $pv = $fighter['pv'];
    $maxPv = $fighter['max_pv'] ?? $fighter['basePv'] ?? $pv;
    $atk = $fighter['atk'];
    $def = $fighter['def'];
    $speed = $fighter['speed'] ?? 10;
    $pvPercent = ($maxPv > 0) ? round(($pv / $maxPv) * 100) : 0;
    
    $posClass = ($position === 'left') ? 'hero-stats' : 'enemy-stats';
    $pvBarClass = ($position === 'left') ? 'pv-bar' : 'pv-bar enemy-pv';
    
    return <<<HTML
    <div class="fighter-stats {$posClass}">
        <div class="fighter-identity">
            <strong class="fighter-name">{$name}</strong>
            <span class="type-badge">{$type}</span>
        </div>
        <div class="stat-bar">
            <div class="{$pvBarClass}" id="{$idPrefix}PvBar" style="width: {$pvPercent}%"></div>
        </div>
        <div class="stats-detail" id="{$idPrefix}Stats">
            <span class="stat"><span class="stat-icon">❤️</span> {$pv}/{$maxPv}</span>
            <span class="stat"><span class="stat-icon">⚔️</span> {$atk}</span>
            <span class="stat"><span class="stat-icon">🛡️</span> {$def}</span>
            <span class="stat"><span class="stat-icon">💨</span> {$speed}</span>
        </div>
    </div>
HTML;
}

/**
 * Génère la zone de combat avec les deux combattants
 */
function renderFightersArea(string $heroImg, string $enemyImg, string $heroId = 'hero', string $enemyId = 'enemy'): string {
    return <<<HTML
    <div class="fighters-area">
        <div class="fighter hero" id="{$heroId}Fighter">
            <img src="{$heroImg}" alt="Hero" class="fighter-img">
            <div id="{$heroId}EmojiContainer" class="emoji-container"></div>
            <div class="effects-container" id="{$heroId}Effects"></div>
        </div>

        <div class="vs-indicator">VS</div>

        <div class="fighter enemy" id="{$enemyId}Fighter">
            <img src="{$enemyImg}" alt="Enemy" class="fighter-img enemy-img">
            <div id="{$enemyId}EmojiContainer" class="emoji-container"></div>
            <div class="effects-container" id="{$enemyId}Effects"></div>
        </div>
    </div>
HTML;
}

/**
 * Génère le log de combat
 */
function renderBattleLog(array $logs, string $heroName = '', string $enemyName = ''): string {
    $html = '<div class="battle-log" id="battleLog">';
    
    foreach ($logs as $log) {
        $class = 'log-line';
        if ($heroName && strpos($log, $heroName) !== false && strpos($log, ':') !== false) {
            $class .= ' player-action';
        }
        if ($enemyName && strpos($log, $enemyName) !== false && strpos($log, ':') !== false) {
            $class .= ' enemy-action';
        }
        if (strpos($log, 'VICTOIRE') !== false || strpos($log, 'remporte') !== false) {
            $class .= ' victory';
        }
        if (strpos($log, 'vaincu') !== false) {
            $class .= ' defeat';
        }
        if (strpos($log, '---') !== false) {
            $class .= ' turn-separator';
        }
        
        $escapedLog = htmlspecialchars($log);
        $html .= "<div class=\"{$class}\">{$escapedLog}</div>";
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Génère les boutons d'action (version compacte pour multiplayer polling)
 */
function renderActionButtons(array $actions, bool $disabled = false): string {
    $html = '<div class="actions-grid">';
    
    foreach ($actions as $key => $action) {
        $emoji = $action['emoji'] ?? '⚔️';
        $label = $action['label'] ?? 'Action';
        $desc = $action['description'] ?? '';
        $canUse = ($action['canUse'] ?? true) && !$disabled;
        $ppText = $action['ppText'] ?? '';
        
        $disabledAttr = $canUse ? '' : 'disabled';
        $disabledClass = $canUse ? '' : 'disabled';
        
        $html .= <<<HTML
        <button type="button" 
                class="action-btn {$disabledClass}" 
                data-action="{$key}"
                data-description="{$desc}"
                {$disabledAttr}>
            <span class="action-emoji">{$emoji}</span>
            <span class="action-label">{$label}</span>
            <span class="action-pp">{$ppText}</span>
        </button>
HTML;
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Génère le panneau de contrôles unifié
 */
function renderControlsPanel(bool $isMultiplayer = false, bool $isGameOver = false, ?string $winner = null): string {
    $waitingHtml = '';
    $gameOverHtml = '';
    $abandonHtml = '';
    
    if ($isMultiplayer) {
        $waitingHtml = '<div id="waitingMessage" class="waiting-message" style="display:none;">⏳ En attente de l\'adversaire...</div>';
    }
    
    if ($isGameOver && $winner !== null) {
        $resultClass = ($winner === 'you' || $winner === 'player') ? 'victory-text' : 'defeat-text';
        $resultText = ($winner === 'you' || $winner === 'player') ? '🏆 VICTOIRE !' : '💀 DÉFAITE...';
        $gameOverHtml = <<<HTML
        <div id="gameOverSection" class="game-over-panel">
            <h3 class="{$resultClass}">{$resultText}</h3>
            <a href="index.php" class="action-btn new-game">Menu Principal</a>
        </div>
HTML;
    }
    
    $abandonLabel = $isMultiplayer ? 'Abandonner' : 'Quitter';
    $abandonName = $isMultiplayer ? 'abandon_multi' : 'logout';
    $abandonDisabled = $isGameOver ? 'disabled' : '';
    
    $abandonHtml = <<<HTML
    <form method="POST" class="abandon-form">
        <button type="submit" name="{$abandonName}" class="action-btn abandon" {$abandonDisabled}>{$abandonLabel}</button>
    </form>
HTML;
    
    return <<<HTML
    <div class="controls-panel">
        {$waitingHtml}
        <div id="actionsContainer"></div>
        {$gameOverHtml}
        {$abandonHtml}
    </div>
HTML;
}

/**
 * Génère les effets actifs d'un personnage
 */
function renderActiveEffects(array $effects): string {
    if (empty($effects)) return '';
    
    $html = '';
    foreach ($effects as $name => $effect) {
        $emoji = $effect['emoji'] ?? '✨';
        $duration = $effect['duration'] ?? '';
        $title = htmlspecialchars($name);
        $html .= "<div class=\"effect-indicator\" title=\"{$title}\">{$emoji}<span class=\"effect-duration\">{$duration}</span></div>";
    }
    return $html;
}
?>
