<?php
/**
 * DefenseBoostEffect - Augmente la DEF durant plusieurs tours
 * Utilisé par: Guerrier (Levée de bouclier +15 DEF/2 tours), Brute (Armure d'Os +10 DEF/2 tours), 
 *             Guérisseur (Bénédiction +5 DEF/3 tours), Guérisseur (Barrière +25 DEF/1 tour)
 */
require_once __DIR__ . '/../StatusEffect.php';

class DefenseBoostEffect extends StatusEffect {
    private $defBoost;
    private $originalDef;
    
    public function __construct(int $duration, int $defBoost) {
        parent::__construct('Defense Boost', '🛡️', $duration, 0, 0);
        $this->defBoost = $defBoost;
        $this->originalDef = 0;
    }
    
    public function resolveDamage(Personnage $target): ?array {
        return null; // Pas de dégâts associés
    }
    
    public function resolveStats(Personnage $target): ?array {
        return null; // Les stats sont modifiées au moment de l'application/suppression
    }
    
    public function onApply(Personnage $character): void {
        $this->originalDef = $character->getDef();
        $character->setDef($this->originalDef + $this->defBoost);
    }
    
    public function onTurnEnd(Personnage $character): void {
        // Rien à faire chaque tour
    }
    
    public function onRemove(Personnage $character): void {
        $character->setDef($this->originalDef);
    }
    
    public function getDescription(): string {
        return "+{$this->defBoost} DEF pour {$this->duration} tour(s)";
    }
}
?>
