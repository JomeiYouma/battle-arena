<?php
/**
 * AUTOCOMBAT - Combats automatiques IA vs IA pour simulation de matchups
 */
class AutoCombat {
    private Personnage $fighter1;
    private Personnage $fighter2;
    private int $turn = 1;
    private int $maxTurns = 100;
    private bool $isFinished = false;
    private ?Personnage $winner = null;

    public function __construct(Personnage $fighter1, Personnage $fighter2) {
        $this->fighter1 = $fighter1;
        $this->fighter2 = $fighter2;
        
        // Établir la référence croisée pour les passifs qui réagissent aux actions adverses
        $fighter1->setCurrentOpponent($fighter2);
        $fighter2->setCurrentOpponent($fighter1);
    }

    private function getOrderedFighters(): array {
        if ($this->fighter1->getSpeed() >= $this->fighter2->getSpeed()) {
            return [$this->fighter1, $this->fighter2];
        }
        return [$this->fighter2, $this->fighter1];
    }

    private function checkDeath(Personnage $character): bool {
        if ($character->isDead()) {
            $this->isFinished = true;
            $this->winner = ($character === $this->fighter1) ? $this->fighter2 : $this->fighter1;
            return true;
        }
        return false;
    }

    private function resolveDamageEffectsFor(Personnage $character): void {
        $character->resolveDamagePhase();
    }

    private function resolveStatEffectsFor(Personnage $character): void {
        $character->resolveStatsPhase();
        $character->decrementBuffDurations();
    }

    private function doAIAction(Personnage $actor, Personnage $target): void {
        if ($actor->isDead()) return;

        $blockEffect = $actor->checkActionBlock();
        if ($blockEffect) return;

        if ($target->isEvading()) {
            $target->setEvading(false);
            $actor->incrementSuccessfulActions();
            return;
        }

        $actions = $actor->getAvailableActions();
        $available = array_filter($actions, fn($k) => $actor->canUseAction($k), ARRAY_FILTER_USE_KEY);
        
        if (empty($available)) {
            $available = ['attack' => $actions['attack']];
        }

        // IA : heal si PV < 30%
        $healthPct = $actor->getPv() / $actor->getBasePv();
        if ($healthPct < 0.3 && isset($available['heal'])) {
            $selectedKey = 'heal';
        } else {
            $keys = array_keys($available);
            $selectedKey = $keys[array_rand($keys)];
        }
        
        $action = $available[$selectedKey];
        $actor->usePP($selectedKey);

        $method = $action['method'];
        if ($action['needsTarget'] ?? false) {
            $actor->$method($target);
        } else {
            $actor->$method();
        }
        
        $actor->incrementSuccessfulActions();
    }

    private function executeTurn(): void {
        [$first, $second] = $this->getOrderedFighters();
        $firstTarget = ($first === $this->fighter1) ? $this->fighter2 : $this->fighter1;
        $secondTarget = ($second === $this->fighter1) ? $this->fighter2 : $this->fighter1;

        if ($this->turn > 1) {
            $this->resolveDamageEffectsFor($first);
            if ($this->checkDeath($first)) return;
            
            $this->resolveDamageEffectsFor($second);
            if ($this->checkDeath($second)) return;

            $this->resolveStatEffectsFor($first);
            $this->resolveStatEffectsFor($second);
        }

        $this->doAIAction($first, $firstTarget);
        if ($this->checkDeath($firstTarget)) return;

        $this->doAIAction($second, $secondTarget);
        if ($this->checkDeath($secondTarget)) return;

        $this->turn++;
    }

    public function run(): ?Personnage {
        while (!$this->isFinished && $this->turn <= $this->maxTurns) {
            $this->executeTurn();
        }
        
        if (!$this->winner) {
            $pv1Pct = $this->fighter1->getPv() / $this->fighter1->getBasePv();
            $pv2Pct = $this->fighter2->getPv() / $this->fighter2->getBasePv();
            $this->winner = ($pv1Pct >= $pv2Pct) ? $this->fighter1 : $this->fighter2;
        }
        
        return $this->winner;
    }

    public function getTurns(): int {
        return $this->turn;
    }
}
