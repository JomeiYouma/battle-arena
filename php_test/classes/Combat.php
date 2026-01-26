<?php
/**
 * Combat - Système de combat tour par tour
 * 
 * Séquence: Effets dégâts → Effets stats → Action rapide → Action lent
 * Vérifie la mort à chaque phase
 */

class Combat {
    protected Personnage $player;
    protected Personnage $enemy;
    protected array $logs = [];
    protected int $turn = 1;

    // Actions du tour pour animations séquentielles par phase
    protected array $turnActions = [];
    
    // États initiaux avant le tour (pour animations progressives)
    protected array $initialStates = [];
    
    // État du combat
    protected bool $isFinished = false;
    protected ?Personnage $winner = null;

    public function __construct(Personnage $player, Personnage $enemy) {
        $this->player = $player;
        $this->enemy = $enemy;
        $this->logs[] = "⚔️ Combat : " . $player->getName() . " VS " . $enemy->getName();
        $this->captureInitialStates();
    }

    // --- GETTERS ---
    public function getPlayer(): Personnage { return $this->player; }
    public function getEnemy(): Personnage { return $this->enemy; }
    public function getLogs(): array { return $this->logs; }
    public function getTurn(): int { return $this->turn; }
    public function getTurnActions(): array { return $this->turnActions; }
    public function getInitialStates(): array { return $this->initialStates; }

    /**
     * Capture les états des deux combattants
     */
    protected function captureInitialStates(): void {
        $this->initialStates = $this->getStatesSnapshot();
    }

    /**
     * Retourne un snapshot des états actuels
     */
    protected function getStatesSnapshot(): array {
        return [
            'player' => [
                'pv' => $this->player->getPv(),
                'basePv' => $this->player->getBasePv(),
                'atk' => $this->player->getAtk(),
                'def' => $this->player->getDef(),
                'speed' => $this->player->getSpeed()
            ],
            'enemy' => [
                'pv' => $this->enemy->getPv(),
                'basePv' => $this->enemy->getBasePv(),
                'atk' => $this->enemy->getAtk(),
                'def' => $this->enemy->getDef(),
                'speed' => $this->enemy->getSpeed()
            ]
        ];
    }

    public function getPlayerActions(): array {
        return $this->player->getAvailableActions();
    }

    /**
     * Détermine qui est le plus rapide
     */
    public function playerIsFaster(): bool {
        return $this->player->getSpeed() >= $this->enemy->getSpeed();
    }

    /**
     * Retourne [premier, second] selon la vitesse
     */
    protected function getOrderedFighters(): array {
        if ($this->playerIsFaster()) {
            return [$this->player, $this->enemy];
        }
        return [$this->enemy, $this->player];
    }

    /**
     * Vérifie si un personnage est mort et gère la fin de combat
     */
    protected function checkDeath(Personnage $character): bool {
        if ($character->isDead()) {
            $isPlayer = ($character === $this->player);
            $this->isFinished = true;
            $this->winner = $isPlayer ? $this->enemy : $this->player;
            
            // Ajouter action de mort pour animation
            $this->turnActions[] = [
                'phase' => 'death',
                'actor' => $isPlayer ? 'player' : 'enemy',
                'emoji' => '💀',
                'label' => 'K.O.',
                'isDeath' => true,
                'statesAfter' => $this->getStatesSnapshot()
            ];

            if ($isPlayer) {
                $this->logs[] = "💀 " . $this->player->getName() . " a été vaincu...";
            } else {
                $this->logs[] = "🏆 " . $this->player->getName() . " remporte le combat !";
            }
            
            return true;
        }
        return false;
    }

    /**
     * Phase de dégâts des effets pour un personnage
     */
    protected function resolveDamageEffectsFor(Personnage $character): void {
        $isPlayer = ($character === $this->player);
        $results = $character->resolveDamagePhase();
        
        foreach ($results as $result) {
            $this->logs[] = $result['log'];
            $this->turnActions[] = [
                'phase' => 'damage_effect',
                'actor' => $isPlayer ? 'player' : 'enemy',
                'emoji' => $result['emoji'],
                'label' => $result['effectName'] ?? 'Effet',
                'damage' => $result['damage'] ?? 0,
                'type' => $result['type'],
                'statesAfter' => $this->getStatesSnapshot()
            ];
        }
    }

    /**
     * Phase de stats des effets pour un personnage
     */
    protected function resolveStatEffectsFor(Personnage $character): void {
        $isPlayer = ($character === $this->player);
        $results = $character->resolveStatsPhase();
        
        foreach ($results as $result) {
            $this->logs[] = $result['log'];
            $this->turnActions[] = [
                'phase' => 'stat_effect',
                'actor' => $isPlayer ? 'player' : 'enemy',
                'emoji' => $result['emoji'],
                'label' => $result['effectName'] ?? 'Effet',
                'statChanges' => $result['statChanges'] ?? [],
                'type' => $result['type'],
                'statesAfter' => $this->getStatesSnapshot()
            ];
        }
    }

    /**
     * Décrémente les buffs d'un personnage
     */
    protected function processBuffsFor(Personnage $character): void {
        $logs = $character->decrementBuffDurations();
        foreach ($logs as $log) {
            $this->logs[] = $log;
        }
    }

    /**
     * Exécute une action spécifique pour un personnage
     */
    protected function performAction(Personnage $actor, Personnage $target, string $actionKey): void {
        $actorType = ($actor === $this->player) ? 'player' : 'enemy';
        $targetType = ($target === $this->player) ? 'player' : 'enemy';

        // Vérification des blocages (Statuts comme Paralysie)
        $blockEffect = $actor->checkActionBlock();
        if ($blockEffect) {
            $this->logs[] = "🚫 " . $actor->getName() . " est bloqué par " . $blockEffect . " !";
            $this->turnActions[] = [
                'phase' => 'action',
                'actor' => $actorType,
                'emoji' => '🚫',
                'label' => 'Bloqué',
                'text' => 'Bloqué par ' . $blockEffect,
                'statesAfter' => $this->getStatesSnapshot()
            ];
            return;
        }

        $actions = $actor->getAvailableActions();
        $action = $actions[$actionKey];
        $actor->usePP($actionKey);

        // Esquive de la cible ?
        if (($action['needsTarget'] ?? false) && $target->isEvading()) {
            $this->logs[] = "💨 " . $target->getName() . " esquive !";
            $target->setEvading(false);
            
            // Action comptée comme lancée (succès de l'invocation, échec du résultat)
            $actor->incrementSuccessfulActions();

            $this->turnActions[] = [
                'phase' => 'action',
                'actor' => $actorType,
                'emoji' => '💨',
                'label' => 'Esquivé !',
                'needsTarget' => true,
                'statesAfter' => $this->getStatesSnapshot()
            ];
            return;
        }

        $method = $action['method'];
        $result = ($action['needsTarget'] ?? false) 
            ? $actor->$method($target) 
            : $actor->$method();
        
        // Emojis différents selon qui joue
        $icon = ($actor === $this->player) ? "🎮" : "🤖";
        if ($actorType === 'enemy' && isset($this->isMulti) && $this->isMulti) $icon = "🎮"; // En multi, les deux sont des joueurs

        $this->logs[] = $icon . " " . $actor->getName() . " : " . $result;
        
        // Action réussie !
        $actor->incrementSuccessfulActions();
        
        $this->turnActions[] = [
            'phase' => 'action',
            'actor' => $actorType,
            'emoji' => $action['emoji'] ?? '⚔️',
            'label' => $action['label'],
            'needsTarget' => $action['needsTarget'] ?? false,
            'statesAfter' => $this->getStatesSnapshot()
        ];
    }

    /**
     * Exécute l'action du joueur
     */
    private function doPlayerAction(string $actionKey): void {
        $actions = $this->player->getAvailableActions();
        if (!isset($actions[$actionKey]) || !$this->player->canUseAction($actionKey)) {
            return;
        }
        $this->performAction($this->player, $this->enemy, $actionKey);
    }

    /**
     * Exécute l'action de l'ennemi (IA)
     */
    private function doEnemyAction(): void {
        if ($this->enemy->isDead()) return;

        // IA : choisir une action
        $actions = $this->enemy->getAvailableActions();
        $available = array_filter($actions, fn($k) => $this->enemy->canUseAction($k), ARRAY_FILTER_USE_KEY);
        if (empty($available)) $available = ['attack' => $actions['attack']];

        // Priorité heal si PV bas
        $healthPct = $this->enemy->getPv() / $this->enemy->getBasePv();
        $selectedKey = 'attack'; // Default

        if ($healthPct < 0.3 && isset($available['heal'])) {
            $selectedKey = 'heal';
        } else {
            $keys = array_keys($available);
            $selectedKey = $keys[array_rand($keys)];
        }
        
        // Exécution via la méthode partagée
        $this->performAction($this->enemy, $this->player, $selectedKey);
    }

    /**
     * MÉTHODE PRINCIPALE : Exécute un tour complet avec les 7 phases
     */
    public function executePlayerAction(string $actionKey): void {
        $actions = $this->player->getAvailableActions();
        
        if (!isset($actions[$actionKey])) {
            $this->logs[] = "❌ Action invalide !";
            return;
        }
        if (!$this->player->canUseAction($actionKey)) {
            $this->logs[] = "❌ Plus de PP !";
            return;
        }

        // Reset des actions du tour et capturer les états initiaux
        $this->turnActions = [];
        $this->captureInitialStates();
        $this->logs[] = "--- Tour " . $this->turn . " ---";

        // Déterminer l'ordre
        [$first, $second] = $this->getOrderedFighters();
        $playerIsFirst = ($first === $this->player);

        // ===== PHASE 2 : Dégâts Effets - Premier =====
        if ($this->turn > 1) {
            $this->resolveDamageEffectsFor($first);
            if ($this->checkDeath($first)) return;
        }

        // ===== PHASE 3 : Dégâts Effets - Second =====
        if ($this->turn > 1) {
            $this->resolveDamageEffectsFor($second);
            if ($this->checkDeath($second)) return;
        }

        // ===== PHASE 4 : Effets Stats - Premier =====
        if ($this->turn > 1) {
            $this->resolveStatEffectsFor($first);
            $this->processBuffsFor($first);
        }

        // ===== PHASE 5 : Effets Stats - Second =====
        if ($this->turn > 1) {
            $this->resolveStatEffectsFor($second);
            $this->processBuffsFor($second);
        }

        // ===== PHASE 6 : Action - Premier =====
        if ($playerIsFirst) {
            $this->doPlayerAction($actionKey);
        } else {
            $this->doEnemyAction();
        }
        
        $target = $playerIsFirst ? $this->enemy : $this->player;
        if ($this->checkDeath($target)) return;

        // ===== PHASE 7 : Action - Second =====
        if ($playerIsFirst) {
            $this->doEnemyAction();
        } else {
            $this->doPlayerAction($actionKey);
        }
        
        $target2 = $playerIsFirst ? $this->player : $this->enemy;
        if ($this->checkDeath($target2)) return;

        $this->turn++;
    }

    public function isOver(): bool {
        return $this->isFinished || $this->player->isDead() || $this->enemy->isDead();
    }

    public function getWinner(): ?Personnage {
        if ($this->winner) return $this->winner;
        if ($this->enemy->isDead()) return $this->player;
        if ($this->player->isDead()) return $this->enemy;
        return null;
    }
}
