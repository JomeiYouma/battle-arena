<?php
/**
 * =============================================================================
 * COMBAT - Système de combat tour par tour avec phases structurées
 * =============================================================================
 * 
 * SÉQUENCE DE TOUR (7 phases) :
 * 1. Déterminer qui est le plus rapide
 * 2. Résoudre dégâts effets → Plus Rapide
 * 3. Résoudre dégâts effets → Plus Lent
 * 4. Résoudre effets stats → Plus Rapide
 * 5. Résoudre effets stats → Plus Lent
 * 6. Action choisie → Plus Rapide
 * 7. Action choisie → Plus Lent
 * 
 * Vérification de mort à chaque phase.
 * 
 * =============================================================================
 */

class Combat {
    private Personnage $player;
    private Personnage $enemy;
    private array $logs = [];
    private int $turn = 1;

    // Actions du tour pour animations séquentielles par phase
    private array $turnActions = [];
    
    // États initiaux avant le tour (pour animations progressives)
    private array $initialStates = [];
    
    // État du combat
    private bool $isFinished = false;
    private ?Personnage $winner = null;

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
    private function captureInitialStates(): void {
        $this->initialStates = $this->getStatesSnapshot();
    }

    /**
     * Retourne un snapshot des états actuels
     */
    private function getStatesSnapshot(): array {
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
    private function getOrderedFighters(): array {
        if ($this->playerIsFaster()) {
            return [$this->player, $this->enemy];
        }
        return [$this->enemy, $this->player];
    }

    /**
     * Vérifie si un personnage est mort et gère la fin de combat
     */
    private function checkDeath(Personnage $character): bool {
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
    private function resolveDamageEffectsFor(Personnage $character): void {
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
    private function resolveStatEffectsFor(Personnage $character): void {
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
    private function processBuffsFor(Personnage $character): void {
        $logs = $character->decrementBuffDurations();
        foreach ($logs as $log) {
            $this->logs[] = $log;
        }
    }

    /**
     * Exécute l'action du joueur
     */
    private function doPlayerAction(string $actionKey): void {
        $actions = $this->player->getAvailableActions();
        if (!isset($actions[$actionKey]) || !$this->player->canUseAction($actionKey)) {
            return;
        }

        // Vérification des blocages (Statuts comme Paralysie)
        $blockEffect = $this->player->checkActionBlock();
        if ($blockEffect) {
            $this->logs[] = "🚫 " . $this->player->getName() . " est bloqué par " . $blockEffect . " !";
            $this->turnActions[] = [
                'phase' => 'action',
                'actor' => 'player',
                'emoji' => '🚫',
                'label' => 'Bloqué',
                'text' => 'Bloqué par ' . $blockEffect,
                'statesAfter' => $this->getStatesSnapshot()
            ];
            // Le coût en PP est payé même si bloqué ? Généralement oui dans les RPG
            // Mais ici on n'a pas encore appelé usePP
            // On peut décider de payer ou non. Disons qu'on ne paie pas pour l'instant.
            return;
        }

        $action = $actions[$actionKey];
        $this->player->usePP($actionKey);

        // Esquive de l'ennemi ?
        if (($action['needsTarget'] ?? false) && $this->enemy->isEvading()) {
            $this->logs[] = "💨 " . $this->enemy->getName() . " esquive !";
            $this->enemy->setEvading(false);
            $this->turnActions[] = [
                'phase' => 'action',
                'actor' => 'player',
                'emoji' => '💨',
                'label' => 'Esquivé !',
                'needsTarget' => true,
                'statesAfter' => $this->getStatesSnapshot()
            ];
            // Action lancée mais esquivée = succès de lancement ou pas ?
            // L'utilisateur a dit "quand le héros n'a pas pu lancer d'action".
            // Ici il l'a lancée, donc ça compte comme succès.
            $this->player->incrementSuccessfulActions();
            return;
        }

        $method = $action['method'];
        $result = ($action['needsTarget'] ?? false) 
            ? $this->player->$method($this->enemy) 
            : $this->player->$method();
        
        $this->logs[] = "🎮 " . $this->player->getName() . " : " . $result;
        
        // Action réussie !
        $this->player->incrementSuccessfulActions();
        
        $this->turnActions[] = [
            'phase' => 'action',
            'actor' => 'player',
            'emoji' => $action['emoji'] ?? '⚔️',
            'label' => $action['label'],
            'needsTarget' => $action['needsTarget'] ?? false,
            'statesAfter' => $this->getStatesSnapshot()
        ];
    }

    /**
     * Exécute l'action de l'ennemi (IA)
     */
    private function doEnemyAction(): void {
        if ($this->enemy->isDead()) return;

        // Vérification des blocages (Paralysie, etc.)
        $blockEffect = $this->enemy->checkActionBlock();
        if ($blockEffect) {
            $this->logs[] = "🚫 " . $this->enemy->getName() . " est bloqué par " . $blockEffect . " !";
            $this->turnActions[] = [
                'phase' => 'action',
                'actor' => 'enemy',
                'emoji' => '🚫',
                'label' => 'Bloqué',
                'text' => 'Bloqué par ' . $blockEffect,
                'statesAfter' => $this->getStatesSnapshot()
            ];
            return;
        }

        // Esquive du joueur ?
        if ($this->player->isEvading()) {
            $this->logs[] = "💨 " . $this->player->getName() . " esquive !";
            $this->player->setEvading(false);

            // Action comptée comme lancée
            $this->enemy->incrementSuccessfulActions();
            
            // L'ennemi fait quand même une action (mais elle est esquivée)
            $this->turnActions[] = [
                'phase' => 'action',
                'actor' => 'enemy',
                'emoji' => '💨',
                'label' => 'Esquivé !',
                'needsTarget' => true,
                'statesAfter' => $this->getStatesSnapshot()
            ];
            return;
        }

        // IA : choisir une action
        $actions = $this->enemy->getAvailableActions();
        $available = array_filter($actions, fn($k) => $this->enemy->canUseAction($k), ARRAY_FILTER_USE_KEY);
        if (empty($available)) $available = ['attack' => $actions['attack']];

        // Priorité heal si PV bas
        $healthPct = $this->enemy->getPv() / $this->enemy->getBasePv();
        if ($healthPct < 0.3 && isset($available['heal'])) {
            $selectedKey = 'heal';
        } else {
            $keys = array_keys($available);
            $selectedKey = $keys[array_rand($keys)];
        }
        
        $action = $available[$selectedKey];
        $this->enemy->usePP($selectedKey);

        $method = $action['method'];
        $result = ($action['needsTarget'] ?? false) 
            ? $this->enemy->$method($this->player) 
            : $this->enemy->$method();
        
        $this->logs[] = "🤖 " . $this->enemy->getName() . " : " . $result;

        // Action réussie
        $this->enemy->incrementSuccessfulActions();
        
        $this->turnActions[] = [
            'phase' => 'action',
            'actor' => 'enemy',
            'emoji' => $action['emoji'] ?? '⚔️',
            'label' => $action['label'],
            'needsTarget' => $action['needsTarget'] ?? false,
            'statesAfter' => $this->getStatesSnapshot()
        ];
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
