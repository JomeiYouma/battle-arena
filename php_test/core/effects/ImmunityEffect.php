<?php
/**
 * ImmunityEffect - Empêche l'application de nouveaux effets négatifs
 */
class ImmunityEffect extends StatusEffect {
    
    public function __construct(int $duration) {
        parent::__construct('Immunité', '🛡️', $duration);
    }

    public function resolveDamage(Personnage $target): ?array {
        return null; // Pas de dégâts, juste préventif
    }

    public function resolveStats(Personnage $target): ?array {
        // Logique préventive gérée dans Personnage::addStatusEffect
        return [
            'log' => "🛡️ " . $target->getName() . " est immunisé aux effets !",
            'emoji' => $this->emoji,
            'effectName' => $this->name,
            'type' => 'immunity'
        ];
    }

    public function getDescription(): string {
        return "🛡️ Immunité aux effets négatifs ({$this->duration} tour(s))";
    }
}
