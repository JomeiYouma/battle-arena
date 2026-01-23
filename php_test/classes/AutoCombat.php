<?php
/**
 * =============================================================================
 * AUTOCOMBAT - Combats automatiques IA vs IA pour simulation
 * =============================================================================
 * 
 * Version optimisée du combat sans interactivité joueur.
 * Les deux personnages sont contrôlés par l'IA.
 * Retourne uniquement le résultat final (gagnant).
 * 
 * =============================================================================
 */

class AutoCombat {
    private Personnage $fighter1;
    private Personnage $fighter2;
    private int $turn = 1;
    private int $maxTurns = 100; // Limite pour éviter les combats infinis
    
    private bool $isFinished = false;
    private ?Personnage $winner = null;

    public function __construct(Personnage $fighter1, Personnage $fighter2) {
        $this->fighter1 = $fighter1;
        $this->fighter2 = $fighter2;
    }

    /**
     * Détermine l'ordre selon la vitesse
     */
    private function getOrderedFighters(): array {
        if ($this->fighter1->getSpeed() >= $this->fighter2->getSpeed()) {
            return [$this->fighter1, $this->fighter2];
        }
        return [$this->fighter2, $this->fighter1];
    }

    /**
     * Vérifie si un personnage est mort
     */
    private function checkDeath(Personnage $character): bool {
        if ($character->isDead()) {
            $this->isFinished = true;
            $this->winner = ($character === $this->fighter1) ? $this->fighter2 : $this->fighter1;
            return true;
        }
        return false;
    }

    /**
     * Résout les effets de dégâts pour un personnage
     */
    private function resolveDamageEffectsFor(Personnage $character): void {
        $character->resolveDamagePhase();
    }

    /**
     * Résout les effets de stats pour un personnage
     */
    private function resolveStatEffectsFor(Personnage $character): void {
        $character->resolveStatsPhase();
        $character->decrementBuffDurations();
    }

    /**
     * IA : Choisit et exécute une action
     */
    private function doAIAction(Personnage $actor, Personnage $target): void {
        if ($actor->isDead()) return;

        // Vérification des blocages
        $blockEffect = $actor->checkActionBlock();
        if ($blockEffect) {
            return; // Bloqué, ne fait rien
        }

        // Esquive de la cible ?
        if ($target->isEvading()) {
            $target->setEvading(false);
            $actor->incrementSuccessfulActions();
            return;
        }

        // Choisir une action disponible
        $actions = $actor->getAvailableActions();
        $available = array_filter($actions, fn($k) => $actor->canUseAction($k), ARRAY_FILTER_USE_KEY);
        
        if (empty($available)) {
            $available = ['attack' => $actions['attack']];
        }

        // Priorité heal si PV bas
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

    /**
     * Exécute un tour complet
     */
    private function executeTurn(): void {
        [$first, $second] = $this->getOrderedFighters();
        $firstTarget = ($first === $this->fighter1) ? $this->fighter2 : $this->fighter1;
        $secondTarget = ($second === $this->fighter1) ? $this->fighter2 : $this->fighter1;

        // Phase 2-3: Dégâts des effets
        if ($this->turn > 1) {
            $this->resolveDamageEffectsFor($first);
            if ($this->checkDeath($first)) return;
            
            $this->resolveDamageEffectsFor($second);
            if ($this->checkDeath($second)) return;
        }

        // Phase 4-5: Effets stats
        if ($this->turn > 1) {
            $this->resolveStatEffectsFor($first);
            $this->resolveStatEffectsFor($second);
        }

        // Phase 6: Action du premier
        $this->doAIAction($first, $firstTarget);
        if ($this->checkDeath($firstTarget)) return;

        // Phase 7: Action du second
        $this->doAIAction($second, $secondTarget);
        if ($this->checkDeath($secondTarget)) return;

        $this->turn++;
    }

    /**
     * Lance le combat complet et retourne le gagnant
     */
    public function run(): ?Personnage {
        while (!$this->isFinished && $this->turn <= $this->maxTurns) {
            $this->executeTurn();
        }
        
        // Si on atteint la limite de tours, le personnage avec le plus de PV gagne
        if (!$this->winner) {
            $pv1Pct = $this->fighter1->getPv() / $this->fighter1->getBasePv();
            $pv2Pct = $this->fighter2->getPv() / $this->fighter2->getBasePv();
            $this->winner = ($pv1Pct >= $pv2Pct) ? $this->fighter1 : $this->fighter2;
        }
        
        return $this->winner;
    }

    /**
     * Retourne le nombre de tours joués
     */
    public function getTurns(): int {
        return $this->turn;
    }
}
