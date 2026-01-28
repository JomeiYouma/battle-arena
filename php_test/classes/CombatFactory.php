<?php
require_once __DIR__ . '/CombatEngine.php';
require_once __DIR__ . '/CombatState.php';
require_once __DIR__ . '/combat/ActionResolver.php';
require_once __DIR__ . '/combat/TimeManager.php';
require_once __DIR__ . '/combat/MatchManager.php';
require_once __DIR__ . '/combat/resolvers/SoloActionResolver.php';
require_once __DIR__ . '/combat/managers/NoTimeManager.php';
require_once __DIR__ . '/combat/managers/SoloMatchManager.php';

/**
 * CombatFactory - Factory pour créer des combats simplement
 * 
 * Remplace progressivement Combat et MultiCombat
 */
class CombatFactory {
    
    /**
     * Crée un combat solo (vs IA)
     */
    public static function createSolo(Personnage $player, Personnage $enemy): CombatEngine {
        $actionResolver = new SoloActionResolver($player, $enemy);
        $timeManager = new NoTimeManager();
        $matchManager = new SoloMatchManager();
        
        return new CombatEngine(
            $player,
            $enemy,
            $actionResolver,
            $timeManager,
            $matchManager,
            'solo'
        );
    }
    
    /**
     * Crée un combat multijoueur
     */
    public static function createMultiplayer(
        Personnage $player1,
        Personnage $player2,
        ?string $matchId = null,
        ?array $metadata = null,
        int $timePerTurn = 60
    ): CombatEngine {
        require_once __DIR__ . '/combat/resolvers/MultiplayerActionResolver.php';
        require_once __DIR__ . '/combat/managers/TimeoutManager.php';
        require_once __DIR__ . '/combat/managers/MultiplayerMatchManager.php';
        
        $actionResolver = new MultiplayerActionResolver($player1, $player2);
        $timeManager = new TimeoutManager($timePerTurn);
        $matchManager = new MultiplayerMatchManager($matchId, $metadata);
        
        $engine = new CombatEngine(
            $player1,
            $player2,
            $actionResolver,
            $timeManager,
            $matchManager,
            'multiplayer'
        );
        
        $engine->isMulti = true;
        return $engine;
    }
    
    /**
     * Crée un combat depuis un CombatState
     */
    public static function fromState(CombatState $state, ?string $mode = null): CombatEngine {
        $mode = $mode ?? $state->gameMode ?? 'solo';
        
        if ($mode === 'solo') {
            return static::createSolo($state->player, $state->enemy);
        } else if ($mode === 'multiplayer') {
            return static::createMultiplayer($state->player, $state->enemy, null, $state->metadata);
        }
        
        // Fallback
        return static::createSolo($state->player, $state->enemy);
    }
}
?>
