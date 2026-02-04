<?php
/** CURSEEFFECT - Malédiction (dégâts/tour) */

class CurseEffect extends StatusEffect {
    private int $damageAmount;
    
    public function __construct(int $duration, int $damagePerTurn) {
        parent::__construct('Malédiction', '💀', $duration);
        $this->damageAmount = $damagePerTurn;
    }

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

    public function resolveStats(Personnage $target): ?array {
        return null;
    }

    public function getDescription(): string {
        return "💀 Malédiction : {$this->damageAmount} dégâts/tour ({$this->duration} tour(s))";
    }
}
