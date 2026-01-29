<?php
/**
 * TEAMCOMBAT - Combat multijoueur 5v5 avec équipes
 * 
 * Hérite de Combat et ajoute la gestion des équipes avec système de switch
 * 
 * Fonctionnalités:
 * - Gestion de 5 héros par équipe
 * - Switch de héros en combat (action spéciale)
 * - Persistance des HP et buffs après switch
 * - Récupération des équipes initiales depuis BD
 */

class TeamCombat extends Combat {
    // Équipes complètes
    private array $player1Team = [];  // 5 Personnage objects
    private array $player2Team = [];
    
    // Indices des héros actuels
    private int $currentPlayer1Index = 0;
    private int $currentPlayer2Index = 0;
    
    // Enregistrement des informations de switch
    private array $switchLogs = [];

    /**
     * Initialiser un combat d'équipe
     * 
     * @param array $player1Team Array de 5 Personnage objects (P1)
     * @param array $player2Team Array de 5 Personnage objects (P2)
     * @throws Exception Si les équipes n'ont pas 5 héros
     */
    public function __construct(array $player1Team, array $player2Team) {
        // Validation
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

    /**
     * Établir les références croisées pour que les passifs fonctionnent
     */
    private function setupOpponentReferences(): void {
        // Player 1's current opponent = Player 2's current hero
        // Player 2's current opponent = Player 1's current hero
        $this->player1Team[0]->setCurrentOpponent($this->player2Team[0]);
        $this->player2Team[0]->setCurrentOpponent($this->player1Team[0]);
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
     */
    private function executeSwitchAction(int $targetIndex): void {
        // Déterminer quel joueur fait le switch (basé sur le tour)
        $isPlayer1 = $this->turn % 2 === 1; // Tours impairs = P1, pairs = P2

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
                    'hp' => $hero->getPV(),
                    'hp_max' => $hero->getPVMax(),
                    'hp_percent' => round(($hero->getPV() / $hero->getPVMax()) * 100)
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
}
