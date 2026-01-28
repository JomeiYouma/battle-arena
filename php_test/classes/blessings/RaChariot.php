<?php
require_once __DIR__ . '/../Blessing.php';
require_once __DIR__ . '/../StatusEffect.php';

class RaChariot extends Blessing {
    private bool $durationModifiedThisTurn = false;

    public function __construct() {
        parent::__construct(
            'RaChariot', 
            'Chariot de Ra', 
            '+50% VIT. Durée effets neg ennemis +2, nous -1.', 
            '☀️'
        );
    }

    public function onTurnStart(Personnage $owner, Combat $combat): void {
        $this->durationModifiedThisTurn = false;
    }

    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        if ($stat === 'speed') {
            return (int)($currentValue * 1.5);
        }
        return $currentValue;
    }

    public function modifyEffectDuration(StatusEffect $effect, Personnage $target, Personnage $source): ?int {
        // +2 turns to enemy negative effects
        // -1 turn to own negative effects (reduce their duration faster for us)
        if ($target === $source) {
            // Own effect - reduce by 1
            return max(1, $effect->getDuration() - 1);
        } else {
            // Enemy effect - increase by 2
            $this->durationModifiedThisTurn = true;
            return $effect->getDuration() + 2;
        }
    }

    public function getExtraActions(): array {
        return [
            'jour_nouveau' => [
                'label' => 'Jour Nouveau',
                'description' => 'Ajoute 50% VIT à ATK et DEF (2 tours)',
                'emoji' => '🌅',
                'method' => 'actionJourNouveau',
                'needsTarget' => false,
                'pp' => 2
            ]
        ];
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        if ($actionKey === 'jour_nouveau') {
            return $this->executeJourNouveau($actor);
        }
        return parent::executeAction($actionKey, $actor, $target);
    }

    private function executeJourNouveau(Personnage $actor): string {
        $speedBoost = (int)($actor->getSpeed() * 0.5);
        $actor->addBuff('Jour Nouveau (ATK)', 'atk', $speedBoost, 2);
        $actor->addBuff('Jour Nouveau (DEF)', 'def', $speedBoost, 2);
        return "appelle un Jour Nouveau ! +$speedBoost ATK/DEF";
    }

    public function getAnimationData(): ?array {
        if ($this->durationModifiedThisTurn) {
            return [
                'emoji' => $this->emoji,
                'name' => $this->name,
                'message' => 'Durée des effets modifiée !',
                'type' => 'duration_mod',
                'icon' => 'media/blessings/chariot.png'
            ];
        }
        return null;
    }
}
