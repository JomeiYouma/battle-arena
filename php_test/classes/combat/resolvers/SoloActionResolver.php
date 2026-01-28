<?php
require_once __DIR__ . '/../ActionResolver.php';

/**
 * SoloActionResolver - Résolution des actions en mode solo
 * 
 * Logique:
 * - Le joueur choisit son action
 * - L'IA choisit une action aléatoire
 * - Les actions sont résolues séquentiellement selon l'ordre de vitesse
 */
class SoloActionResolver implements ActionResolver {
    
    private Personnage $player;
    private Personnage $enemy;
    
    public function __construct(Personnage $player, Personnage $enemy) {
        $this->player = $player;
        $this->enemy = $enemy;
    }
    
    public function resolveActions(CombatEngine $combat, ?string $playerAction, ?string $p2Action = null): void {
        // Valider l'action du joueur
        if (!$playerAction || !$this->canUseAction($playerAction)) {
            $combat->addLog("❌ Action invalide ou PP insuffisant !");
            return;
        }
        
        // Déterminer l'ordre de jeu selon la vitesse
        $playerIsFaster = $this->player->getSpeed() >= $this->enemy->getSpeed();
        
        if ($playerIsFaster) {
            // Joueur agit en premier
            $combat->performAction($this->player, $this->enemy, $playerAction);
            if ($this->enemy->isDead()) return;
            
            // IA réagit
            $enemyAction = $this->selectEnemyAction();
            $combat->performAction($this->enemy, $this->player, $enemyAction);
        } else {
            // IA agit en premier
            $enemyAction = $this->selectEnemyAction();
            $combat->performAction($this->enemy, $this->player, $enemyAction);
            if ($this->player->isDead()) return;
            
            // Joueur réagit
            $combat->performAction($this->player, $this->enemy, $playerAction);
        }
    }
    
    /**
     * Sélectionne une action pour l'IA
     * Logique simple: heal si PV bas, sinon action aléatoire
     */
    private function selectEnemyAction(): string {
        $actions = $this->enemy->getAllActions();
        $available = array_filter($actions, fn($k) => $this->enemy->canUseAction($k), ARRAY_FILTER_USE_KEY);
        
        if (empty($available)) {
            return 'attack';
        }
        
        // Priorité heal si PV < 30%
        $healthPercent = $this->enemy->getPv() / $this->enemy->getBasePv();
        if ($healthPercent < 0.3) {
            foreach ($available as $key => $action) {
                if (strpos(strtolower($action['label'] ?? ''), 'heal') !== false || 
                    strpos(strtolower($action['label'] ?? ''), 'soin') !== false ||
                    strpos(strtolower($action['label'] ?? ''), 'dévore') !== false) {
                    return $key;
                }
            }
        }
        
        // Sinon, choisir aléatoirement
        $keys = array_keys($available);
        return $keys[array_rand($keys)];
    }
    
    public function getPlayerActions(): array {
        return $this->player->getAllActions();
    }
    
    public function canUseAction(string $actionKey): bool {
        return $this->player->canUseAction($actionKey);
    }
    
    public function getModeName(): string {
        return 'solo';
    }
}
?>
