<?php
/**
 * BombEffect - Explosion différée (dégâts au tour suivant)
 */
class BombEffect extends StatusEffect {
    private int $explosionDamage;
    
    public function __construct(int $turnsDelay, int $damage) {
        parent::__construct('Bombe', '💣', 1, $turnsDelay, 0);
        $this->explosionDamage = $damage;
    }

    public function resolveDamage(Personnage $target): ?array {
        if ($this->isPending()) return null;
        
        $target->receiveDamage($this->explosionDamage);
        return [
            'damage' => $this->explosionDamage,
            'log' => "💥 LA BOMBE EXPLOSE ! " . $target->getName() . " subit " . $this->explosionDamage . " dégâts !",
            'emoji' => '💥',
            'effectName' => $this->name,
            'type' => 'bomb'
        ];
    }

    public function resolveStats(Personnage $target): ?array {
        return null;
    }

    public function onActivate(Personnage $target): string {
        return "💣 La bombe va exploser sur " . $target->getName() . " !";
    }
}
