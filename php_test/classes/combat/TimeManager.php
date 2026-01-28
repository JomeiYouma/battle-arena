<?php
/**
 * TimeManager - Interface de stratégie pour gérer le temps du combat
 * 
 * Permet de gérer:
 * - Solo: Pas de limite de temps
 * - Multijoueur: Timeouts pour chaque joueur
 */
interface TimeManager {
    /**
     * Retourne true si le combat a une limite de temps
     * @return bool
     */
    public function hasTimeLimit(): bool;
    
    /**
     * Retourne le temps restant pour un combattant (en secondes)
     * @param Personnage $fighter
     * @return int Secondes restantes (-1 si pas de limite)
     */
    public function getRemainingTime(Personnage $fighter): int;
    
    /**
     * Appelé quand une action est soumise
     * @param Personnage $fighter
     */
    public function onActionSubmitted(Personnage $fighter): void;
    
    /**
     * Appelé quand le temps d'un joueur s'écoule
     * @param Personnage $fighter
     */
    public function onTimeout(Personnage $fighter): void;
    
    /**
     * Reset le timer pour un nouveau tour
     */
    public function resetForNewTurn(): void;
}
?>
