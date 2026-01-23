<?php
/**
 * SpeedModEffect - Modifie la vitesse (+/-) pendant la durée
 */
class SpeedModEffect extends StatusEffect {
    private int $amount;

    public function __construct(int $duration, int $amount) {
        $emoji = $amount > 0 ? '⏩' : '🐌';
        parent::__construct('Speed', $emoji, $duration);
        $this->amount = $amount;
    }

    public function resolveDamage(Personnage $target): ?array {
        return null;
    }

    public function resolveStats(Personnage $target): ?array {
        return null;
    }

    public function getStatModifiers(): array {
        return ['speed' => $this->amount];
    }
}
