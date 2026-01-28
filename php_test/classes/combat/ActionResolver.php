<?php
/**
 * ActionResolver - Interface de stratégie pour résoudre les actions du combat
 * 
 * Permet de gérer différents modes de résolution:
 * - Solo: Joueur agit → IA réagit (séquentiel)
 * - Multijoueur: Deux joueurs agissent simultanément
 */
interface ActionResolver {
    /**
     * Résout les actions du tour selon le mode
     * Doit appeler performAction() sur le CombatEngine
     * 
     * @param CombatEngine $combat - Le moteur de combat
     * @param string|null $playerAction - Action du joueur (solo) ou du P1 (multi)
     * @param string|null $p2Action - Action du P2 (multi seulement)
     */
    public function resolveActions(CombatEngine $combat, ?string $playerAction, ?string $p2Action = null): void;
    
    /**
     * Retourne les actions disponibles pour le joueur actuel
     * @return array Actions disponibles avec label, emoji, etc.
     */
    public function getPlayerActions(): array;
    
    /**
     * Vérifie si le joueur peut utiliser cette action
     * @param string $actionKey - Clé de l'action
     * @return bool
     */
    public function canUseAction(string $actionKey): bool;
    
    /**
     * Retourne le nom du mode ('solo', 'multiplayer', etc.)
     * @return string
     */
    public function getModeName(): string;
}
?>
