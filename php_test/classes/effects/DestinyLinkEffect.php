<?php
require_once __DIR__ . '/../StatusEffect.php';

/**
 * DestinyLinkEffect - Lie le destin : inflige un pourcentage des dégâts reçus à l'adversaire
 */
class DestinyLinkEffect extends StatusEffect {
    private float $damagePercent;
    private ?Personnage $linkedTarget;

    public function __construct(int $duration, float $damagePercent = 0.35, ?Personnage $linkedTarget = null) {
        parent::__construct('Lien de Destin', '♾️', $duration, 0, 0);
        $this->damagePercent = $damagePercent;
        $this->linkedTarget = $linkedTarget;
    }

    public function setLinkedTarget(?Personnage $target): void {
        $this->linkedTarget = $target;
    }

    public function getLinkedTarget(): ?Personnage {
        return $this->linkedTarget;
    }

    public function getDamagePercent(): float {
        return $this->damagePercent;
    }

    public function resolveDamage(Personnage $owner): ?array {
        // Les dégâts sont gérés via le hook onReceiveDamage dans Personnage
        return null;
    }

    public function resolveStats(Personnage $owner): ?array {
        // Pas de modification de stats, juste un lien passif
        return null;
    }

    public function getStatModifiers(): array {
        return [];
    }

    public function canAct(): bool {
        return true;
    }

    public function getDescription(): string {
        $percent = (int)($this->damagePercent * 100);
        return "Lien de Destin actif ({$percent}% dégâts partagés)";
    }

    public function onExpire(Personnage $owner): string {
        return $owner->getName() . " : Le Lien de Destin se brise...";
    }
}
