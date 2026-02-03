<?php
/**
 * BurningEffect - Dégâts de brûlure par tour. Dégâts = 0.2 * ATK + 4
 */
require_once __DIR__ . '/../StatusEffect.php';

class BurningEffect extends StatusEffect {
    
    private int $attackerAtk;
    
    public function __construct(int $duration = 3, int $attackerAtk = 10, int $turnsDelay = 0) {
        parent::__construct('Brûlure', '🔥', $duration, $turnsDelay, 0);
        $this->attackerAtk = $attackerAtk;
    }

    public function resolveDamage(Personnage $target): ?array {
        if ($this->isPending()) return null;

        $damage = max(1, (int) (0.2 * $this->attackerAtk + 4));
        $target->setPv($target->getPv() - $damage);

        return [
            'log' => $this->emoji . " " . $target->getName() . " brûle ! -" . $damage . " PV",
            'damage' => $damage,
            'emoji' => $this->emoji,
            'effectName' => $this->name
        ];
    }

    public function resolveStats(Personnage $target): ?array {
        return null;
    }

    public function onActivate(Personnage $target): string {
        return "💥 La Brûlure s'embrase sur " . $target->getName() . " !";
    }

    public function getDescription(): string {
        $damage = max(1, (int)(0.2 * $this->attackerAtk + 4));
        return "🔥 Brûlure : ~{$damage} dégâts/tour ({$this->duration} tour(s))";
    }
}
