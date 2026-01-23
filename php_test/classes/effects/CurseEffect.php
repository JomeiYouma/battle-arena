<?php
/**
 * CurseEffect - Effet de malédiction (dégâts fixes par tour)
 * 
 * Inflige des dégâts fixes à chaque tour pendant la durée.
 */

class CurseEffect extends StatusEffect {
    private int $damageAmount;
    
    public function __construct(int $duration, int $damagePerTurn) {
        parent::__construct('Malédiction', '💀', $duration);
        $this->damageAmount = $damagePerTurn;
    }
    
    /**
     * Applique les dégâts de malédiction
     */
    public function resolveDamage(Personnage $target): ?array {
        $dmg = $this->damageAmount;
        $target->receiveDamage($dmg);
        
        return [
            'damage' => $dmg,
            'log' => "💀 " . $target->getName() . " subit " . $dmg . " dégâts de Malédiction !",
            'emoji' => $this->emoji,
            'effectName' => $this->name,
            'type' => 'curse'
        ];
    }
    
    /**
     * Pas d'effet sur les stats
     */
    public function resolveStats(Personnage $target): ?array {
        return null;
    }
}
