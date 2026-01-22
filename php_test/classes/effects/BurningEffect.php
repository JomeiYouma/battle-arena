<?php
/**
 * =============================================================================
 * BurningEffect - Effet de brûlure
 * =============================================================================
 * 
 * Inflige des dégâts de feu à chaque tour pendant la phase de dégâts.
 * Peut avoir un délai avant activation (ex: Flèche enflammée).
 * 
 * =============================================================================
 */

require_once __DIR__ . '/../StatusEffect.php';

class BurningEffect extends StatusEffect {
    
    public function __construct(int $duration = 3, int $damagePerTurn = 4, int $turnsDelay = 0) {
        parent::__construct(
            name: 'Brûlure',
            emoji: '🔥',
            duration: $duration,
            turnsDelay: $turnsDelay,
            damagePerTurn: $damagePerTurn
        );
    }

    /**
     * Phase Dégâts : Inflige des dégâts de feu
     */
    public function resolveDamage(Personnage $target): ?array {
        if ($this->isPending()) {
            return null; // Pas encore actif
        }

        $damage = $this->damagePerTurn;
        $oldPv = $target->getPv();
        $target->setPv($oldPv - $damage);

        return [
            'log' => $this->emoji . " " . $target->getName() . " brûle ! -" . $damage . " PV (" . $target->getPv() . " PV)",
            'damage' => $damage,
            'emoji' => $this->emoji,
            'effectName' => $this->name
        ];
    }

    /**
     * Phase Stats : La brûlure n'affecte pas les stats
     */
    public function resolveStats(Personnage $target): ?array {
        return null;
    }

    /**
     * Message d'activation personnalisé
     */
    public function onActivate(Personnage $target): string {
        return "💥 La Brûlure s'embrase sur " . $target->getName() . " !";
    }
}
