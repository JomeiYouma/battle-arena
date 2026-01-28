<?php
/**
 * CombatEngine - Moteur de combat unifié
 * 
 * Gère tous les modes de combat (solo, multijoueur, etc.) via des stratégies injectées:
 * - ActionResolver: Comment les actions sont résolues
 * - TimeManager: Gestion du temps
 * - MatchManager: Stats et persistance
 * 
 * Séquence: Effets dégâts → Effets stats → Actions → Vérifier mort → Tour++
 */
class CombatEngine {
    protected Personnage $player;
    protected Personnage $enemy;
    protected array $logs = [];
    protected int $turn = 1;
    
    // Stratégies injectées
    protected ActionResolver $actionResolver;
    protected TimeManager $timeManager;
    protected MatchManager $matchManager;
    protected string $gameMode;
    
    // Actions du tour pour animations
    protected array $turnActions = [];
    protected array $initialStates = [];
    
    // État du combat
    protected bool $isFinished = false;
    protected ?Personnage $winner = null;
    
    // Flag pour multi (utilisé dans performAction pour les icônes)
    public bool $isMulti = false;
    
    public function __construct(
        Personnage $player,
        Personnage $enemy,
        ActionResolver $actionResolver,
        TimeManager $timeManager,
        MatchManager $matchManager,
        string $gameMode = 'solo'
    ) {
        $this->player = $player;
        $this->enemy = $enemy;
        $this->actionResolver = $actionResolver;
        $this->timeManager = $timeManager;
        $this->matchManager = $matchManager;
        $this->gameMode = $gameMode;
        $this->isMulti = ($gameMode === 'multiplayer');
        
        $this->logs[] = "⚔️ Combat [" . strtoupper($gameMode) . "] : " . $player->getName() . " VS " . $enemy->getName();
        $this->captureInitialStates();
        $this->matchManager->onMatchStart();
    }
    
    // ============= GETTERS =============
    
    public function getPlayer(): Personnage { return $this->player; }
    public function getEnemy(): Personnage { return $this->enemy; }
    public function getLogs(): array { return $this->logs; }
    public function getTurn(): int { return $this->turn; }
    public function getTurnActions(): array { return $this->turnActions; }
    public function getInitialStates(): array { return $this->initialStates; }
    public function getGameMode(): string { return $this->gameMode; }
    public function getActionResolver(): ActionResolver { return $this->actionResolver; }
    public function getTimeManager(): TimeManager { return $this->timeManager; }
    public function getMatchManager(): MatchManager { return $this->matchManager; }
    
    public function isOver(): bool {
        return $this->isFinished || $this->player->isDead() || $this->enemy->isDead();
    }
    
    public function getWinner(): ?Personnage {
        if ($this->winner) return $this->winner;
        if ($this->enemy->isDead()) return $this->player;
        if ($this->player->isDead()) return $this->enemy;
        return null;
    }
    
    public function getPlayerActions(): array {
        return $this->actionResolver->getPlayerActions();
    }
    
    // ============= LOG & STATE =============
    
    public function addLog(string $log): void {
        $this->logs[] = $log;
    }
    
    protected function captureInitialStates(): void {
        $this->initialStates = $this->getStatesSnapshot();
    }
    
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
    
    // ============= PHASES DE COMBAT =============
    
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
     * Appelée par ActionResolver
     */
    public function performAction(Personnage $actor, Personnage $target, string $actionKey): void {
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

        $actions = $actor->getAllActions();
        $action = $actions[$actionKey];
        $actor->usePP($actionKey);

        // Esquive de la cible ?
        if (($action['needsTarget'] ?? false) && $target->isEvading()) {
            $this->logs[] = "💨 " . $target->getName() . " esquive !";
            $target->setEvading(false);
            
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
        
        // Vérifier si c'est une action blessing
        $isBlessingAction = $this->isActionFromBlessing($actor, $actionKey);
        
        // Exécuter l'action
        if ($isBlessingAction) {
            $result = ($action['needsTarget'] ?? false) 
                ? $actor->executeBlessingAction($actionKey, $target) 
                : $actor->executeBlessingAction($actionKey, null);
        } else {
            $result = ($action['needsTarget'] ?? false) 
                ? $actor->$method($target) 
                : $actor->$method();
        }
        
        // Emojis différents selon qui joue
        $icon = ($actor === $this->player) ? "🎮" : "🤖";
        if ($actorType === 'enemy' && $this->isMulti) $icon = "🎮"; // En multi, les deux sont des joueurs

        $this->logs[] = $icon . " " . $actor->getName() . " : " . $result;
        
        // Action réussie !
        $actor->incrementSuccessfulActions();
        
        // === DÉCLENCHER LES HOOKS DE BLESSINGS ===
        // onAttack si c'est une attaque
        if ($actionKey === 'attack' || strpos($action['method'] ?? '', 'attack') !== false) {
            foreach ($actor->getBlessings() as $blessing) {
                $blessing->onAttack($actor, $target, 0);
            }
        }
        
        // onTurnEnd pour l'acteur
        foreach ($actor->getBlessings() as $blessing) {
            $blessing->onTurnEnd($actor, $this);
        }
        
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
     * Vérifie si une action vient d'une bénédiction
     */
    protected function isActionFromBlessing(Personnage $actor, string $actionKey): bool {
        foreach ($actor->getBlessings() as $blessing) {
            $actions = $blessing->getExtraActions();
            if (isset($actions[$actionKey])) {
                return true;
            }
        }
        return false;
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
            
            // Notifier le MatchManager
            $this->matchManager->onMatchEnd($this->winner, $character);
            $this->matchManager->recordStats($this->winner, $character, $this->turn);
            
            return true;
        }
        return false;
    }
    
    // ============= POINT D'ENTRÉE UNIQUE DU TOUR =============
    
    /**
     * Exécute un tour complet du combat
     * Point d'entrée principal pour les deux modes
     */
    public function executeRound(?string $playerAction, ?string $p2Action = null): void {
        // Reset actions du tour et capturer les états initiaux
        $this->turnActions = [];
        $this->captureInitialStates();
        $this->logs[] = "--- Tour " . $this->turn . " ---";

        // === HOOK onTurnStart ===
        foreach ($this->player->getBlessings() as $blessing) {
            $blessing->onTurnStart($this->player, $this);
        }
        foreach ($this->enemy->getBlessings() as $blessing) {
            $blessing->onTurnStart($this->enemy, $this);
        }

        // ===== PHASE 1 : Dégâts Effets =====
        if ($this->turn > 1) {
            // Déterminer l'ordre pour les effets aussi
            $playerIsFaster = $this->player->getSpeed() >= $this->enemy->getSpeed();
            $first = $playerIsFaster ? $this->player : $this->enemy;
            $second = $playerIsFaster ? $this->enemy : $this->player;
            
            $this->resolveDamageEffectsFor($first);
            if ($this->checkDeath($first)) return;
            
            $this->resolveDamageEffectsFor($second);
            if ($this->checkDeath($second)) return;
            
            // ===== PHASE 2 : Effets Stats =====
            $this->resolveStatEffectsFor($first);
            $this->processBuffsFor($first);
            
            $this->resolveStatEffectsFor($second);
            $this->processBuffsFor($second);
        }

        // ===== PHASE 3 : Actions =====
        // Utiliser le ActionResolver pour résoudre selon le mode
        $this->actionResolver->resolveActions($this, $playerAction, $p2Action);
        
        if ($this->isFinished) return;

        $this->turn++;
    }
    
    // ============= SÉRIALISATION =============
    
    /**
     * Crée un snapshot du combat pour sérialisation
     */
    public function getState(): CombatState {
        $state = new CombatState(
            $this->player,
            $this->enemy,
            $this->turn,
            $this->isFinished,
            $this->winner,
            $this->gameMode
        );
        $state->logs = $this->logs;
        $state->turnActions = $this->turnActions;
        return $state;
    }
    
    /**
     * Restaure un combat depuis un snapshot
     */
    public static function fromState(CombatState $state, ActionResolver $actionResolver, TimeManager $timeManager, MatchManager $matchManager): self {
        $engine = new self(
            $state->player,
            $state->enemy,
            $actionResolver,
            $timeManager,
            $matchManager,
            $state->gameMode ?? 'solo'
        );
        $engine->turn = $state->turn;
        $engine->logs = $state->logs;
        $engine->turnActions = $state->turnActions;
        $engine->isFinished = $state->isFinished;
        $engine->winner = $state->winner;
        return $engine;
    }
    
    /**
     * Sauvegarde l'état du combat dans un fichier
     */
    public function save(string $filepath): bool {
        try {
            $dir = dirname($filepath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            $state = $this->getState();
            $json = [
                'state' => $state->toArray(),
                'turn' => $this->turn,
                'logs' => $this->logs,
                'isFinished' => $this->isFinished,
                'gameMode' => $this->gameMode,
                'savedAt' => time()
            ];
            
            $result = file_put_contents($filepath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $result !== false;
        } catch (Exception $e) {
            error_log("CombatEngine::save - Exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Helper pour l'UI JSON - fonctionne pour solo et multi
     */
    public function getStateForUser(string $sessionId, array $metaData): array {
        $isP1 = ($metaData['player1']['session'] === $sessionId);
        
        $myChar = $isP1 ? $this->player : $this->enemy;
        $oppChar = $isP1 ? $this->enemy : $this->player;
        
        // Récupérer les actions disponibles
        $actions = [];
        
        try {
            if (method_exists($myChar, 'getAllActions')) {
                $availableActions = $myChar->getAllActions();
                
                foreach ($availableActions as $key => $action) {
                    $ppText = '';
                    $canUse = true;
                    
                    if (method_exists($myChar, 'getPPText')) {
                        $ppText = $myChar->getPPText($key);
                    }
                    if (method_exists($myChar, 'canUseAction')) {
                        $canUse = $myChar->canUseAction($key);
                    }
                    
                    $actions[$key] = array_merge($action, [
                        'ppText' => $ppText,
                        'canUse' => $canUse
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Error getting available actions: " . $e->getMessage());
        }
        
        // Récupérer les effets actifs
        $myEffects = [];
        $oppEffects = [];
        
        try {
            if (method_exists($myChar, 'getActiveEffects')) {
                $myEffects = $myChar->getActiveEffects();
            }
            if (method_exists($oppChar, 'getActiveEffects')) {
                $oppEffects = $oppChar->getActiveEffects();
            }
        } catch (Exception $e) {
            error_log("Error getting active effects: " . $e->getMessage());
        }
        
        return [
            'turn' => $this->turn,
            'isOver' => $this->isOver(),
            'winner' => $this->getWinner() ? ($this->getWinner() === $myChar ? 'you' : 'opponent') : null,
            'logs' => $this->logs,
            'turnActions' => $this->turnActions,
            'me' => [
                'name' => $metaData[$isP1 ? 'player1' : 'player2']['display_name'] ?? $myChar->getName(),
                'type' => $myChar->getType(),
                'pv' => $myChar->getPv(),
                'max_pv' => $myChar->getBasePv(),
                'atk' => $myChar->getAtk(),
                'def' => $myChar->getDef(),
                'speed' => $myChar->getSpeed(),
                'img' => $metaData[$isP1 ? 'player1' : 'player2']['hero']['images']['p1'] ?? '',
                'activeEffects' => $myEffects
            ],
            'opponent' => [
                'name' => $metaData[$isP1 ? 'player2' : 'player1']['display_name'] ?? $oppChar->getName(),
                'type' => $oppChar->getType(),
                'pv' => $oppChar->getPv(),
                'max_pv' => $oppChar->getBasePv(),
                'atk' => $oppChar->getAtk(),
                'def' => $oppChar->getDef(),
                'speed' => $oppChar->getSpeed(),
                'img' => $metaData[$isP1 ? 'player2' : 'player1']['hero']['images']['p2'] ?? '',
                'activeEffects' => $oppEffects
            ],
            'actions' => $actions
        ];
    }
    
    /**
     * Retourne l'identifiant du gagnant ('p1' ou 'p2') pour les stats
     */
    public function getWinnerId(): ?string {
        return $this->matchManager->getWinnerId();
    }
}
?>
