<?php
require_once __DIR__ . '/../ActionResolver.php';

/**
 * MultiplayerActionResolver - Résolution des actions en mode multijoueur
 * 
 * Logique:
 * - Les deux joueurs envoient leurs actions simultanément
 * - Les actions sont résolues selon l'ordre de vitesse
 */
class MultiplayerActionResolver implements ActionResolver {
    
    private Personnage $player1;
    private Personnage $player2;
    
    public function __construct(Personnage $player1, Personnage $player2) {
        $this->player1 = $player1;
        $this->player2 = $player2;
    }
    
    public function resolveActions(CombatEngine $combat, ?string $playerAction, ?string $p2Action = null): void {
        // Valider les deux actions
        if (!$playerAction || !$this->canUseActionFor($this->player1, $playerAction)) {
            $combat->addLog("❌ Action P1 invalide ou PP insuffisant !");
            return;
        }
        
        if (!$p2Action || !$this->canUseActionFor($this->player2, $p2Action)) {
            $combat->addLog("❌ Action P2 invalide ou PP insuffisant !");
            return;
        }
        
        // Déterminer l'ordre de jeu selon la vitesse
        $player1IsFaster = $this->player1->getSpeed() >= $this->player2->getSpeed();
        
        if ($player1IsFaster) {
            // Player1 agit en premier
            $combat->performAction($this->player1, $this->player2, $playerAction);
            if ($this->player2->isDead()) return;
            
            // Player2 réagit
            $combat->performAction($this->player2, $this->player1, $p2Action);
        } else {
            // Player2 agit en premier
            $combat->performAction($this->player2, $this->player1, $p2Action);
            if ($this->player1->isDead()) return;
            
            // Player1 réagit
            $combat->performAction($this->player1, $this->player2, $playerAction);
        }
    }
    
    public function getPlayerActions(): array {
        return $this->player1->getAllActions();
    }
    
    public function canUseAction(string $actionKey): bool {
        return $this->player1->canUseAction($actionKey);
    }
    
    private function canUseActionFor(Personnage $player, string $actionKey): bool {
        return $player->canUseAction($actionKey);
    }
    
    public function getModeName(): string {
        return 'multiplayer';
    }
}
?>
