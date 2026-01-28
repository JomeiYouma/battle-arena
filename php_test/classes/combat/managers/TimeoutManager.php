<?php
require_once __DIR__ . '/../TimeManager.php';

/**
 * TimeoutManager - Gestion du temps limité en multijoueur
 * 
 * Chaque joueur dispose d'un temps limité pour soumettre son action
 */
class TimeoutManager implements TimeManager {
    
    private int $timePerTurn; // Secondes
    private array $timers; // ['player1' => [...], 'player2' => [...]]
    
    public function __construct(int $secondsPerTurn = 60) {
        $this->timePerTurn = $secondsPerTurn;
        $this->timers = [];
    }
    
    public function hasTimeLimit(): bool {
        return true;
    }
    
    public function getRemainingTime(Personnage $fighter): int {
        $fighterId = spl_object_hash($fighter);
        
        if (!isset($this->timers[$fighterId])) {
            return $this->timePerTurn;
        }
        
        $elapsed = time() - $this->timers[$fighterId]['startTime'];
        $remaining = $this->timePerTurn - $elapsed;
        
        return max(0, $remaining);
    }
    
    public function onActionSubmitted(Personnage $fighter): void {
        // L'action a été soumise à temps, on réinitialise pour le prochain tour
        $fighterId = spl_object_hash($fighter);
        unset($this->timers[$fighterId]);
    }
    
    public function onTimeout(Personnage $fighter): void {
        // Le temps d'un joueur s'est écoulé
        // Sera géré au niveau du match/API
    }
    
    public function resetForNewTurn(): void {
        // Réinitialiser les timers pour le nouveau tour
        $this->timers = [];
        
        // Chaque joueur a maintenant timePerTurn secondes
        // Les timers seront créés au moment où on commence à attendre leurs actions
    }
    
    /**
     * Commence le timing pour un joueur
     */
    public function startTimingFor(Personnage $fighter): void {
        $fighterId = spl_object_hash($fighter);
        $this->timers[$fighterId] = [
            'startTime' => time(),
            'fighter' => $fighter
        ];
    }
    
    /**
     * Vérifie si le timeout est dépassé
     */
    public function isTimedOut(Personnage $fighter): bool {
        return $this->getRemainingTime($fighter) <= 0;
    }
}
?>
