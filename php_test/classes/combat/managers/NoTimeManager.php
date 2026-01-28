<?php
require_once __DIR__ . '/../TimeManager.php';

/**
 * NoTimeManager - Aucune gestion du temps
 * Utilisé pour le mode solo
 */
class NoTimeManager implements TimeManager {
    
    public function hasTimeLimit(): bool {
        return false;
    }
    
    public function getRemainingTime(Personnage $fighter): int {
        return -1; // Pas de limite
    }
    
    public function onActionSubmitted(Personnage $fighter): void {
        // Rien à faire
    }
    
    public function onTimeout(Personnage $fighter): void {
        // Rien à faire - pas de timeout possible
    }
    
    public function resetForNewTurn(): void {
        // Rien à faire
    }
}
?>
