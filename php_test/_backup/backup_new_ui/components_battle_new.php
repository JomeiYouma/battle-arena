<?php
/**
 * COMPOSANTS DE COMBAT UNIFIÉS - NOUVELLE VERSION
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
    $pvBarClass = ($position === 'left') ? 'pv-bar' : 'pv-bar enemy-pv';
    
    return <<<HTML
    <div class="fighter-stats {$position}">
        <div class="fighter-name-row">
            <span class="fighter-name" id="{$idPrefix}Name">{$name}</span>
            <span class="fighter-type">{$type}</span>
        </div>
        <div class="stat-bar">
            <div class="{$pvBarClass}" id="{$idPrefix}PvBar" style="width: {$pvPercent}%;"></div>
        </div>
        <div class="stats-detail" id="{$idPrefix}Stats">
            <span class="stat">❤️ <span id="{$idPrefix}Pv">{$pv}</span>/{$maxPv}</span>
            <span class="stat">⚔️ <span id="{$idPrefix}Atk">{$atk}</span></span>
            <span class="stat">🛡️ <span id="{$idPrefix}Def">{$def}</span></span>
            <span class="stat">⚡ <span id="{$idPrefix}Speed">{$speed}</span></span>
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
        
        $html .= "<div class=\"{$class}\">" . htmlspecialchars($log) . "</div>";
    }
    
    $html .= '</div>';
    return $html;
}
