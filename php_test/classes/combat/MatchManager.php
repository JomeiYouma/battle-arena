<?php
/**
 * MatchManager - Interface de stratégie pour gérer les stats et persistance du match
 * 
 * Permet de gérer:
 * - Solo: Aucune persistance
 * - Multijoueur: Sauvegarde, stats, enregistrement des victoires
 */
interface MatchManager {
    /**
     * Appelé au démarrage du match
     */
    public function onMatchStart(): void;
    
    /**
     * Appelé à la fin du match
     * @param Personnage $winner - Le gagnant
     * @param Personnage $loser - Le perdant
     */
    public function onMatchEnd(Personnage $winner, Personnage $loser): void;
    
    /**
     * Enregistre les statistiques du match
     * @param Personnage $winner
     * @param Personnage $loser
     * @param int $turn - Nombre de tours joués
     */
    public function recordStats(Personnage $winner, Personnage $loser, int $turn): void;
    
    /**
     * Sauvegarde l'état du combat
     * @param array $state - État complet du combat
     * @return bool Succès
     */
    public function save(array $state): bool;
    
    /**
     * Charge l'état du combat
     * @param string $id - Identifiant du combat
     * @return array|null État du combat ou null
     */
    public function load(string $id): ?array;
    
    /**
     * Retourne l'identifiant du gagnant pour les stats
     * @return string|null
     */
    public function getWinnerId(): ?string;
}
?>
