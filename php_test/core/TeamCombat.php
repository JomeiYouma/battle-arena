<?php
/** TEAMCOMBAT - Combat multijoueur 5v5 avec équipes et switch */

require_once __DIR__ . '/MultiCombat.php';

class TeamCombat extends MultiCombat {
    // Équipes complètes
    private array $player1Team = [];  // 5 Personnage objects
    private array $player2Team = [];
    
    // Indices des héros actuels
    private int $currentPlayer1Index = 0;
    private int $currentPlayer2Index = 0;
    
    // Enregistrement des informations de switch
    private array $switchLogs = [];
    
    // État du switch obligatoire après mort
    private bool $player1NeedsForcedSwitch = false;
    private bool $player2NeedsForcedSwitch = false;

    public function __construct(array $player1Team, array $player2Team) {
        if (count($player1Team) !== 5 || count($player2Team) !== 5) {
            throw new Exception("Les équipes doivent contenir exactement 5 héros");
        }

        // Stocker les équipes complètes
        $this->player1Team = $player1Team;
        $this->player2Team = $player2Team;

        // Initialiser le combat parent avec le premier héros de chaque équipe
        parent::__construct($player1Team[0], $player2Team[0]);

        // Établir les références croisées pour les passifs
        $this->setupOpponentReferences();

        // Log du début du combat d'équipe
        $this->logs[] = "🏆 COMBAT D'ÉQUIPE 5v5";
        $this->logs[] = "⚔️ Équipe 1: " . implode(", ", array_map(fn($h) => $h->getName(), $player1Team));
        $this->logs[] = "⚔️ Équipe 2: " . implode(", ", array_map(fn($h) => $h->getName(), $player2Team));
    }

    private function setupOpponentReferences(): void {
        // Player 1's current opponent = Player 2's current hero
        // Player 2's current opponent = Player 1's current hero
        $this->player1Team[0]->setCurrentOpponent($this->player2Team[0]);
        $this->player2Team[0]->setCurrentOpponent($this->player1Team[0]);
    }

    // ============================================
    // FACTORY METHOD
    // ============================================

    /**
     * Créer un combat d'équipe à partir des données d'un match JSON
     * 
     * @param array $p1Data Données joueur 1 avec 'team_id' ou 'heroes' array
     * @param array $p2Data Données joueur 2 avec 'team_id' ou 'heroes' array
     * @return TeamCombat|null Retourne null si impossible de créer
     */
    public static function create($p1Data, $p2Data): ?TeamCombat {
        try {
            // Récupérer les héros à partir des données
            $p1Heroes = $p1Data['heroes'] ?? null;
            $p2Heroes = $p2Data['heroes'] ?? null;
            
            if (!$p1Heroes || !$p2Heroes || count($p1Heroes) !== 5 || count($p2Heroes) !== 5) {
                return null;
            }
            
            // Blessing global pour l'équipe (fallback si les héros n'ont pas leur propre blessing)
            $p1GlobalBlessing = $p1Data['blessing_id'] ?? null;
            $p2GlobalBlessing = $p2Data['blessing_id'] ?? null;
            
            // Créer les objets Personnage pour chaque héros
            // Priorité: blessing du héros > blessing global de l'équipe
            $team1 = [];
            foreach ($p1Heroes as $heroData) {
                $blessingId = $heroData['blessing_id'] ?? $p1GlobalBlessing;
                $team1[] = self::createHeroFromData($heroData, $blessingId);
            }
            
            $team2 = [];
            foreach ($p2Heroes as $heroData) {
                $blessingId = $heroData['blessing_id'] ?? $p2GlobalBlessing;
                $team2[] = self::createHeroFromData($heroData, $blessingId);
            }
            
            return new TeamCombat($team1, $team2);
        } catch (Exception $e) {
            error_log("TeamCombat::create error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Créer un objet Personnage à partir de données héros
     * (Réutilise la logique de MultiCombat)
     */
    private static function createHeroFromData($heroData, $blessingId = null): Personnage {
        error_log("createHeroFromData: " . json_encode($heroData));
        
        $type = $heroData['type'];
        error_log("Hero type: $type");
        
        // Les types sont les vrais noms des classes
        $heroClass = $type;
        
        $fullClassName = '\\' . $heroClass;
        error_log("Full class name: $fullClassName");
        
        if (!class_exists($fullClassName)) {
            error_log("Class not found: $fullClassName - trying to load");
            // Try loading with require_once
            $heroFile = __DIR__ . '/heroes/' . $type . '.php';
            error_log("Trying to load: $heroFile");
            if (file_exists($heroFile)) {
                require_once $heroFile;
            }
            
            if (!class_exists($fullClassName)) {
                throw new Exception("Hero class not found: $fullClassName (file: $heroFile)");
            }
        }
        
        // Créer le héros avec ses paramètres
        $hero = new $fullClassName(
            $heroData['pv'] ?? 100,
            $heroData['atk'] ?? 15,
            $heroData['name'] ?? 'Héros',
            $heroData['def'] ?? 5,
            $heroData['speed'] ?? 10
        );
        
        error_log("Hero created: " . $hero->getName());
        
        // Appliquer une bénédiction si fournie
        if ($blessingId) {
            $blessingClass = $blessingId;
            $blessingPath = __DIR__ . '/blessings/' . $blessingClass . '.php';
            if (file_exists($blessingPath)) {
                require_once $blessingPath;
                if (class_exists($blessingClass)) {
                    $hero->addBlessing(new $blessingClass());
                    error_log("Blessing applied: $blessingClass");
                }
            }
        }
        
        return $hero;
    }

    // ============================================
    // OVERRIDE STATE GETTERS
    // ============================================

    /**
     * Override getStateForUser pour retourner l'image du héros actuel en 5v5
     */
    public function getStateForUser($sessionId, $metaData) {
        // Appeler la méthode parent
        $state = parent::getStateForUser($sessionId, $metaData);
        
        // Récupérer l'équipe et l'index du héros actuel
        $isP1 = ($metaData['player1']['session'] === $sessionId);
        $heroesData = $isP1 ? ($metaData['player1']['heroes'] ?? []) : ($metaData['player2']['heroes'] ?? []);
        $currentIndex = $isP1 ? $this->currentPlayer1Index : $this->currentPlayer2Index;
        $oppCurrentIndex = $isP1 ? $this->currentPlayer2Index : $this->currentPlayer1Index;
        
        // En 5v5, utiliser le NOM DU HÉROS ACTIF (pas le display_name qui est le nom d'équipe)
        $myTeam = $isP1 ? $this->player1Team : $this->player2Team;
        $oppTeam = $isP1 ? $this->player2Team : $this->player1Team;
        
        $myActiveHero = $myTeam[$currentIndex] ?? null;
        $oppActiveHero = $oppTeam[$oppCurrentIndex] ?? null;
        
        if ($myActiveHero) {
            $state['me']['name'] = $myActiveHero->getName();
            $state['me']['type'] = $myActiveHero->getType();
        }
        
        if ($oppActiveHero) {
            $state['opponent']['name'] = $oppActiveHero->getName();
            $state['opponent']['type'] = $oppActiveHero->getType();
        }
        
        // Retourner l'image du héros actuel - toujours 'p1' pour le joueur (à gauche de l'écran)
        if (isset($heroesData[$currentIndex]['images'])) {
            $state['me']['img'] = $heroesData[$currentIndex]['images']['p1'] ?? $state['me']['img'];
        }
        
        // Même chose pour l'adversaire - toujours 'p2' pour l'adversaire (à droite de l'écran)
        $oppHeroesData = $isP1 ? ($metaData['player2']['heroes'] ?? []) : ($metaData['player1']['heroes'] ?? []);
        
        if (isset($oppHeroesData[$oppCurrentIndex]['images'])) {
            $state['opponent']['img'] = $oppHeroesData[$oppCurrentIndex]['images']['p2'] ?? $state['opponent']['img'];
        }
        
        // Ajouter le flag de forced switch si le héros actif est mort
        $state['needsForcedSwitch'] = $isP1 ? $this->player1NeedsForcedSwitch : $this->player2NeedsForcedSwitch;
        
        // Ajouter l'action SWITCH si des héros sont disponibles pour remplacer
        $teamNum = $isP1 ? 1 : 2;
        $availableSwitches = $this->getAvailableSwitchTargets($teamNum);
        
        if (!empty($availableSwitches) && !$state['needsForcedSwitch']) {
            // Ajouter l'action SWITCH uniquement si pas en forced switch (qui a son propre UI)
            $state['actions']['switch'] = [
                'label' => 'Switch',
                'emoji' => '🔄',
                'canUse' => true,
                'ppText' => '',
                'description' => 'Changer de héros actif (' . count($availableSwitches) . ' disponible' . (count($availableSwitches) > 1 ? 's' : '') . ')'
            ];
        }
        
        // ===== AJOUTER LES DONNÉES D'ÉQUIPE POUR LES SIDEBARS =====
        $myTeam = $isP1 ? $this->player1Team : $this->player2Team;
        $oppTeam = $isP1 ? $this->player2Team : $this->player1Team;
        
        $state['myTeam'] = $this->serializeTeamForClient($myTeam, $heroesData);
        $state['oppTeam'] = $this->serializeTeamForClient($oppTeam, $oppHeroesData);
        $state['myActiveIndex'] = $currentIndex;
        $state['oppActiveIndex'] = $oppCurrentIndex;
        
        return $state;
    }
    
    /**
     * Sérialiser une équipe pour le client (avec HP actuels)
     */
    private function serializeTeamForClient(array $team, array $originalHeroesData): array {
        $result = [];
        foreach ($team as $index => $hero) {
            $originalData = $originalHeroesData[$index] ?? [];
            $result[] = [
                'name' => $hero->getName(),
                'type' => $hero->getType(),
                'pv' => $hero->getPV(),
                'max_pv' => $hero->getBasePv(),
                'atk' => $hero->getAtk(),
                'def' => $hero->getDef(),
                'speed' => $hero->getSpeed(),
                'isDead' => $hero->isDead(),
                'images' => $originalData['images'] ?? null
            ];
        }
        return $result;
    }

    /**
     * Vérifier et marquer les switches obligatoires après mort
     */
    public function checkAndMarkForcedSwitches(): void {
        // Vérifier si le héros actif du joueur 1 est mort
        if ($this->player1Team[$this->currentPlayer1Index]->isDead()) {
            $this->player1NeedsForcedSwitch = true;
            $this->logs[] = "💀 " . $this->player1Team[$this->currentPlayer1Index]->getName() . " est mort! Choisissez un remplaçant.";
        }
        
        // Vérifier si le héros actif du joueur 2 est mort
        if ($this->player2Team[$this->currentPlayer2Index]->isDead()) {
            $this->player2NeedsForcedSwitch = true;
            $this->logs[] = "💀 " . $this->player2Team[$this->currentPlayer2Index]->getName() . " est mort! Choisissez un remplaçant.";
        }
    }

    /**
     * Effectuer un switch obligatoire (après mort)
     * Retourne true si le switch a réussi, false sinon
     */
    public function performForcedSwitch(int $playerNum, int $targetIndex): bool {
        if ($playerNum !== 1 && $playerNum !== 2) return false;
        if ($targetIndex < 0 || $targetIndex >= 5) return false;
        
        $team = $playerNum === 1 ? $this->player1Team : $this->player2Team;
        $newHero = $team[$targetIndex];
        
        // Vérifier que le héros n'est pas mort
        if ($newHero->isDead()) {
            return false;
        }
        
        // Effectuer le switch
        if ($playerNum === 1) {
            $this->switchHeroTeam1($targetIndex);
            $this->player1NeedsForcedSwitch = false;
        } else {
            $this->switchHeroTeam2($targetIndex);
            $this->player2NeedsForcedSwitch = false;
        }
        
        // Log du switch obligatoire
        $this->logs[] = "🔄 " . $newHero->getName() . " remplace le héros tombé!";
        
        return true;
    }

    // ============================================
    // RÉSOLUTION DU TOUR (override pour switch prioritaire)
    // ============================================

    /**
     * Override resolveMultiTurn pour que les switchs soient exécutés EN PREMIER
     * avant toutes les autres actions, indépendamment de la vitesse.
     */
    public function resolveMultiTurn($p1ActionKey, $p2ActionKey) {
        $this->turnActions = [];
        $this->captureInitialStates();
        $this->logs[] = "--- Tour " . $this->turn . " ---";

        // === HOOKS onTurnStart pour les blessings ===
        foreach ($this->player->getBlessings() as $blessing) {
            $blessing->onTurnStart($this->player, $this);
        }
        foreach ($this->enemy->getBlessings() as $blessing) {
            $blessing->onTurnStart($this->enemy, $this);
        }

        // ===== PHASE SWITCH (PRIORITAIRE) =====
        // Les switchs sont toujours exécutés en premier
        $p1IsSwitch = strpos($p1ActionKey, 'switch:') === 0;
        $p2IsSwitch = strpos($p2ActionKey, 'switch:') === 0;

        if ($p1IsSwitch) {
            $parts = explode(':', $p1ActionKey);
            if (count($parts) === 2 && is_numeric($parts[1])) {
                $targetIndex = (int)$parts[1];
                $this->executeSwitchAction($targetIndex, $this->player);
            }
        }

        if ($p2IsSwitch) {
            $parts = explode(':', $p2ActionKey);
            if (count($parts) === 2 && is_numeric($parts[1])) {
                $targetIndex = (int)$parts[1];
                $this->executeSwitchAction($targetIndex, $this->enemy);
            }
        }

        // Si les deux ont switché, passer le tour sans actions de combat
        if ($p1IsSwitch && $p2IsSwitch) {
            $this->turn++;
            return;
        }

        // Déterminer les actions non-switch
        $p1FinalAction = $p1IsSwitch ? 'attack' : $p1ActionKey; // Si switch déjà fait, attaque par défaut
        $p2FinalAction = $p2IsSwitch ? 'attack' : $p2ActionKey;

        // ===== PHASES EFFETS (Identique à Combat) =====
        if ($this->turn > 1) {
            $first = $this->player;
            $second = $this->enemy;
            
            $this->resolveDamageEffectsFor($first);
            if ($this->checkDeath($first)) return;
            
            $this->resolveDamageEffectsFor($second);
            if ($this->checkDeath($second)) return;
            
            $this->resolveStatEffectsFor($first);
            $this->processBuffsFor($first);
            
            $this->resolveStatEffectsFor($second);
            $this->processBuffsFor($second);
        }

        // ===== PHASE ACTIONS DE COMBAT =====
        // Déterminer l'ordre par vitesse
        [$first, $second] = $this->getOrderedFighters();
        $playerIsFirst = ($first === $this->player);

        $firstActionKey = $playerIsFirst ? $p1FinalAction : $p2FinalAction;
        $secondActionKey = $playerIsFirst ? $p2FinalAction : $p1FinalAction;

        // Action 1
        $target1 = ($first === $this->player) ? $this->enemy : $this->player;
        $this->performAction($first, $target1, $firstActionKey);
        
        if ($this->checkDeath($target1)) return;

        // Action 2
        $target2 = ($second === $this->player) ? $this->enemy : $this->player;
        $this->performAction($second, $target2, $secondActionKey);
        
        if ($this->checkDeath($target2)) return;

        $this->turn++;
    }

    // ============================================
    // ACTIONS DE COMBAT
    // ============================================

    /**
     * Exécuter une action de combat (incluant le switch de héros)
     * 
     * @param string $action 'attack', 'spell', 'defend', 'switch', etc.
     * @param mixed $params Paramètres additionnels (ex: index pour switch)
     */
    public function executeAction(string $action, mixed $params = null): void {
        // Si c'est une action de switch, la gérer spécifiquement
        if ($action === 'switch') {
            $this->executeSwitchAction($params);
        } else {
            // Sinon, exécuter l'action normale via le parent
            parent::executeAction($action, $params);
        }
    }

    /**
     * Exécuter une action de switch vers un autre héros
     * 
     * @param int $targetIndex Index du héros cible (0-4)
     * @param Personnage|null $actor Acteur du switch si disponible
     */
    private function executeSwitchAction(int $targetIndex, ?Personnage $actor = null): void {
        // Déterminer quel joueur fait le switch (basé sur l'acteur si possible)
        $isPlayer1 = null;
        if ($actor !== null) {
            if (in_array($actor, $this->player1Team, true)) {
                $isPlayer1 = true;
            } elseif (in_array($actor, $this->player2Team, true)) {
                $isPlayer1 = false;
            }
        }
        // Fallback: utiliser la parité du tour si l'acteur n'est pas trouvé
        if ($isPlayer1 === null) {
            $isPlayer1 = $this->turn % 2 === 1; // Tours impairs = P1, pairs = P2
        }

        // Récupérer le héros courant et vérifier si l'index est valide
        $team = $isPlayer1 ? $this->player1Team : $this->player2Team;
        $currentIndex = $isPlayer1 ? $this->currentPlayer1Index : $this->currentPlayer2Index;

        // Validations
        if ($targetIndex < 0 || $targetIndex >= 5) {
            $this->logs[] = "❌ Index de héros invalide: $targetIndex";
            return;
        }

        if ($targetIndex === $currentIndex) {
            $this->logs[] = "❌ Déjà en combat avec ce héros";
            return;
        }

        $newHero = $team[$targetIndex];

        // Vérifier que le héros n'est pas mort
        if ($newHero->isDead()) {
            $this->logs[] = "❌ " . $newHero->getName() . " est mort";
            return;
        }

        // Effectuer le switch
        if ($isPlayer1) {
            $this->switchHeroTeam1($targetIndex);
        } else {
            $this->switchHeroTeam2($targetIndex);
        }

        // Log du switch
        $this->logs[] = "🔄 " . $newHero->getName() . " entre en combat!";
        $this->switchLogs[] = [
            'turn' => $this->turn,
            'player' => $isPlayer1 ? 1 : 2,
            'hero' => $newHero->getName(),
            'heroIndex' => $targetIndex
        ];
    }

    /**
     * Override performAction pour gérer les actions de switch (format: "switch:X")
     * @param Personnage $actor L'acteur effectuant l'action
     * @param Personnage $target La cible (peut être null pour le switch)
     * @param string $actionKey La clé d'action (ex: "attack", "switch:3")
     */
    protected function performAction(Personnage $actor, Personnage $target, string $actionKey): void {
        // Vérifier si c'est un action de switch
        if (strpos($actionKey, 'switch:') === 0) {
            // Parser le format "switch:X" pour extraire l'index
            $parts = explode(':', $actionKey);
            if (count($parts) === 2 && is_numeric($parts[1])) {
                $targetIndex = (int)$parts[1];
                $this->executeSwitchAction($targetIndex, $actor);
                return;
            } else {
                $this->logs[] = "❌ Format d'action switch invalide: " . $actionKey;
                return;
            }
        }
        
        // Pour toutes les autres actions, utiliser la logique parent
        parent::performAction($actor, $target, $actionKey);
    }

    /**
     * Switcher le héros pour l'équipe 1
     */
    private function switchHeroTeam1(int $targetIndex): void {
        // Mettre à jour le joueur courant
        $this->player = $this->player1Team[$targetIndex];
        $this->currentPlayer1Index = $targetIndex;

        // Mettre à jour les références pour les passifs
        $this->player->setCurrentOpponent($this->enemy);
    }

    /**
     * Switcher le héros pour l'équipe 2
     */
    private function switchHeroTeam2(int $targetIndex): void {
        // Mettre à jour l'ennemi courant
        $this->enemy = $this->player2Team[$targetIndex];
        $this->currentPlayer2Index = $targetIndex;

        // Mettre à jour les références pour les passifs
        $this->enemy->setCurrentOpponent($this->player);
    }

    // ============================================
    // GETTERS POUR LES ÉQUIPES
    // ============================================

    /**
     * Récupérer l'équipe complète du joueur 1
     */
    public function getPlayer1Team(): array {
        return $this->player1Team;
    }

    /**
     * Récupérer l'équipe complète du joueur 2
     */
    public function getPlayer2Team(): array {
        return $this->player2Team;
    }

    /**
     * Récupérer les indices actuels des héros
     */
    public function getCurrentIndices(): array {
        return [
            'player1' => $this->currentPlayer1Index,
            'player2' => $this->currentPlayer2Index
        ];
    }
    
    /**
     * Récupérer l'index actuel du joueur 1
     */
    public function getCurrentPlayer1Index(): int {
        return $this->currentPlayer1Index;
    }
    
    /**
     * Récupérer l'index actuel du joueur 2
     */
    public function getCurrentPlayer2Index(): int {
        return $this->currentPlayer2Index;
    }

    /**
     * Récupérer un héros par équipe et index
     */
    public function getHeroByTeamAndIndex(int $teamNum, int $index): ?Personnage {
        if ($teamNum === 1 && isset($this->player1Team[$index])) {
            return $this->player1Team[$index];
        } elseif ($teamNum === 2 && isset($this->player2Team[$index])) {
            return $this->player2Team[$index];
        }
        return null;
    }

    // ============================================
    // CAPTURE D'ÉTAT (pour sauvegarde BD)
    // ============================================

    /**
     * Capturer l'état complet d'une équipe pour sauvegarde
     * 
     * Retourne les infos de tous les héros (HP, buffs, debuffs, etc)
     */
    public function captureTeamState(int $teamNum): array {
        $team = $teamNum === 1 ? $this->player1Team : $this->player2Team;
        
        return array_map(function($hero, $index) {
            return [
                'index' => $index,
                'name' => $hero->getName(),
                'hero_class' => get_class($hero),
                'hp' => $hero->getPV(),
                'hp_max' => $hero->getPVMax(),
                'stats' => [
                    'atk' => $hero->getAtk(),
                    'def' => $hero->getDef(),
                    'speed' => $hero->getSpeed()
                ],
                'buffs' => $this->captureBuffs($hero),
                'debuffs' => $this->captureDebuffs($hero),
                'is_dead' => $hero->isDead()
            ];
        }, $team, array_keys($team));
    }

    /**
     * Capturer les buffs d'un héros
     */
    private function captureBuffs(Personnage $hero): array {
        // Accès via reflection ou getter dépendant de l'implémentation
        // Placeholder - adapter selon la structure réelle de Personnage
        return [];
    }

    /**
     * Capturer les debuffs d'un héros
     */
    private function captureDebuffs(Personnage $hero): array {
        // Accès via reflection ou getter dépendant de l'implémentation
        // Placeholder - adapter selon la structure réelle de Personnage
        return [];
    }

    /**
     * Capturer l'état complet du combat
     */
    public function getCombatState(): array {
        return [
            'turn' => $this->turn,
            'is_finished' => $this->isFinished,
            'current_indices' => $this->getCurrentIndices(),
            'player1_team_state' => $this->captureTeamState(1),
            'player2_team_state' => $this->captureTeamState(2),
            'switch_history' => $this->switchLogs
        ];
    }

    // ============================================
    // VÉRIFICATIONS
    // ============================================

    /**
     * Vérifier si une équipe a au moins un héros vivant
     */
    public function isTeamAlive(int $teamNum): bool {
        $team = $teamNum === 1 ? $this->player1Team : $this->player2Team;
        
        return !empty(array_filter($team, fn($hero) => !$hero->isDead()));
    }

    /**
     * Obtenir le nombre de héros vivants dans une équipe
     */
    public function countAliveHeroes(int $teamNum): int {
        $team = $teamNum === 1 ? $this->player1Team : $this->player2Team;
        
        return count(array_filter($team, fn($hero) => !$hero->isDead()));
    }

    /**
     * Obtenir la liste des héros disponibles pour switch (vivants et pas actuels)
     */
    public function getAvailableSwitchTargets(int $teamNum): array {
        $team = $teamNum === 1 ? $this->player1Team : $this->player2Team;
        $currentIndex = $teamNum === 1 ? $this->currentPlayer1Index : $this->currentPlayer2Index;
        
        $available = [];
        foreach ($team as $index => $hero) {
            if ($index !== $currentIndex && !$hero->isDead()) {
                $available[] = [
                    'index' => $index,
                    'name' => $hero->getName(),
                    'hp' => $hero->getPv(),
                    'hp_max' => $hero->getBasePv(),
                    'hp_percent' => round(($hero->getPv() / $hero->getBasePv()) * 100)
                ];
            }
        }
        
        return $available;
    }

    /**
     * Vérifier si le combat est terminé (une équipe entière est morte)
     */
    public function checkTeamElimination(): bool {
        if (!$this->isTeamAlive(1)) {
            $this->isFinished = true;
            $this->winner = $this->player2Team[0]; // Simplification
            $this->logs[] = "🎉 Équipe 1 entièrement éliminée! Victoire Équipe 2!";
            return true;
        }

        if (!$this->isTeamAlive(2)) {
            $this->isFinished = true;
            $this->winner = $this->player1Team[0]; // Simplification
            $this->logs[] = "🎉 Équipe 2 entièrement éliminée! Victoire Équipe 1!";
            return true;
        }

        return false;
    }

    // ============================================
    // OVERRIDE COMBAT METHODS FOR 5v5
    // ============================================

    /**
     * Override checkDeath pour gérer le switch forcé en 5v5
     * Ne termine le combat que si toute l'équipe est morte
     */
    protected function checkDeath(Personnage $character): bool {
        if (!$character->isDead()) {
            return false;
        }

        // Déterminer quelle équipe perd ce héros
        $isTeam1 = in_array($character, $this->player1Team, true);
        $teamNum = $isTeam1 ? 1 : 2;
        $team = $isTeam1 ? $this->player1Team : $this->player2Team;

        // Ajouter action de mort pour animation
        $isPlayer = ($character === $this->player);
        $this->turnActions[] = [
            'phase' => 'death',
            'actor' => $isPlayer ? 'player' : 'enemy',
            'emoji' => '💀',
            'label' => 'K.O.',
            'isDeath' => true,
            'statesAfter' => $this->getStatesSnapshot()
        ];

        $this->logs[] = "💀 " . $character->getName() . " est tombé au combat!";

        // Vérifier si l'équipe entière est éliminée
        if (!$this->isTeamAlive($teamNum)) {
            $this->isFinished = true;
            $this->winner = $isTeam1 ? $this->player2Team[0] : $this->player1Team[0];
            $this->logs[] = "🎉 Équipe $teamNum entièrement éliminée! Victoire de l'équipe adverse!";
            return true;
        }

        // Sinon, marquer le forced switch nécessaire
        if ($isTeam1) {
            $this->player1NeedsForcedSwitch = true;
            $this->logs[] = "🔄 Équipe 1 doit choisir un remplaçant!";
        } else {
            $this->player2NeedsForcedSwitch = true;
            $this->logs[] = "🔄 Équipe 2 doit choisir un remplaçant!";
        }

        // Retourner true pour stopper le tour actuel (le joueur doit switch)
        return true;
    }

    /**
     * Override isOver pour vérifier si une équipe entière est éliminée
     */
    public function isOver(): bool {
        if ($this->isFinished) {
            return true;
        }
        
        // Le combat n'est terminé que si une équipe ENTIÈRE est morte
        return !$this->isTeamAlive(1) || !$this->isTeamAlive(2);
    }

    /**
     * Override getWinner pour retourner le bon gagnant en 5v5
     */
    public function getWinner(): ?Personnage {
        if ($this->winner) {
            return $this->winner;
        }
        
        if (!$this->isTeamAlive(1)) {
            return $this->player2Team[$this->currentPlayer2Index];
        }
        if (!$this->isTeamAlive(2)) {
            return $this->player1Team[$this->currentPlayer1Index];
        }
        
        return null;
    }
}
