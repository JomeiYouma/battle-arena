<?php
require_once __DIR__ . '/../MatchManager.php';

/**
 * SoloMatchManager - Pas de persistance ni de stats
 * Utilisé pour le mode solo
 */
class SoloMatchManager implements MatchManager {
    
    public function onMatchStart(): void {
        // Rien à faire
    }
    
    public function onMatchEnd(Personnage $winner, Personnage $loser): void {
        // Rien à faire
    }
    
    public function recordStats(Personnage $winner, Personnage $loser, int $turn): void {
        // Rien à faire - pas de stats à enregistrer en solo
    }
    
    public function save(array $state): bool {
        // Rien à sauvegarder en solo
        return true;
    }
    
    public function load(string $id): ?array {
        // Rien à charger
        return null;
    }
    
    public function getWinnerId(): ?string {
        return null;
    }
}
?>
