<?php
/** ATTACKBOOSTEFFECT - Boost ATK temporaire */

require_once __DIR__ . '/../StatusEffect.php';

class AttackBoostEffect extends StatusEffect {
    private $atkBoost;
    private $originalAtk;
    
    public function __construct(int $duration, int $atkBoost) {
        parent::__construct('Attack Boost', '📈', $duration, 0, 0);
        $this->atkBoost = $atkBoost;
        $this->originalAtk = 0;
    }
    
    public function resolveDamage(Personnage $target): ?array {
        return null; // Pas de dégâts associés
    }
    
    public function resolveStats(Personnage $target): ?array {
        return null; // Les stats sont modifiées au moment de l'application/suppression
    }
    
    public function onApply(Personnage $character): void {
        $this->originalAtk = $character->getAtk();
        $character->setAtk($this->originalAtk + $this->atkBoost);
    }
    
    public function onTurnEnd(Personnage $character): void {
        // Rien à faire chaque tour
    }
    
    public function onRemove(Personnage $character): void {
        $character->setAtk($this->originalAtk);
    }
    
    public function getDescription(): string {
        return "+{$this->atkBoost} ATK pour {$this->duration} tour(s)";
    }
}
?>
