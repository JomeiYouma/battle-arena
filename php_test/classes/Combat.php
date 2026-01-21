<?php
/**
 * =============================================================================
 * CLASSE COMBAT - Gestion du système de combat tour par tour
 * =============================================================================
 * 
 * TODO [À RECODER PAR TOI-MÊME] :
 * - Cette classe gère la logique du combat, tu peux la personnaliser
 * - Ajouter des effets de statut (poison, brûlure, etc.)
 * - Améliorer l'IA de l'ennemi (pas juste random)
 * - Ajouter un système de combo ou de contre-attaque
 * 
 * =============================================================================
 */

class Combat {
    private Personnage $player;
    private Personnage $enemy;
    private array $logs = [];
    private int $turn = 1;

    public function __construct(Personnage $player, Personnage $enemy) {
        $this->player = $player;
        $this->enemy = $enemy;
        $this->logs[] = "⚔️ Le combat commence entre " . $player->getName() . " et " . $enemy->getName() . " !";
    }

    // --- GETTERS ---
    public function getPlayer(): Personnage {
        return $this->player;
    }

    public function getEnemy(): Personnage {
        return $this->enemy;
    }

    public function getLogs(): array {
        return $this->logs;
    }

    public function getTurn(): int {
        return $this->turn;
    }

    /**
     * Récupère les actions disponibles du joueur
     * TODO [À RECODER] : Tu peux filtrer les actions selon les conditions (cooldown, mana, etc.)
     */
    public function getPlayerActions(): array {
        return $this->player->getAvailableActions();
    }

    /**
     * Exécute une action du joueur puis fait jouer l'ennemi
     * TODO [À RECODER] : Ajouter des vérifications de cooldown, coût en mana, etc.
     */
    public function executePlayerAction(string $actionKey): void {
        $actions = $this->player->getAvailableActions();
        
        if (!isset($actions[$actionKey])) {
            $this->logs[] = "❌ Action invalide !";
            return;
        }

        $action = $actions[$actionKey];
        $method = $action['method'];

        // Tour du joueur
        $this->logs[] = "--- Tour " . $this->turn . " ---";

        // Vérifie si le joueur esquive (buff actif)
        // L'esquive s'applique au tour PRÉCÉDENT, donc on ne check pas ici

        // Exécute l'action du joueur
        if ($action['needsTarget'] ?? false) {
            $result = $this->player->$method($this->enemy);
        } else {
            $result = $this->player->$method();
        }
        $this->logs[] = "🎮 " . $this->player->getName() . " : " . $result;

        // Vérifie si l'ennemi est mort
        if ($this->enemy->isDead()) {
            $this->logs[] = "🏆 " . $this->player->getName() . " remporte le combat !";
            return;
        }

        // Tour de l'ennemi (IA random)
        $this->executeEnemyTurn();

        $this->turn++;
    }

    /**
     * L'ennemi joue un move au hasard de son moveset
     * TODO [À RECODER] : Améliorer l'IA (prioriser heal si low HP, etc.)
     */
    private function executeEnemyTurn(): void {
        // Vérifie si l'ennemi peut jouer (pas étourdi, etc.)
        if ($this->enemy->isDead()) {
            return;
        }

        // Vérifie si le joueur a activé une esquive
        if ($this->player->isEvading()) {
            $this->logs[] = "💨 " . $this->player->getName() . " esquive l'attaque de " . $this->enemy->getName() . " !";
            $this->player->setEvading(false); // Reset le buff
            return;
        }

        $enemyActions = $this->enemy->getAvailableActions();
        
        // Sélection aléatoire d'une action
        // TODO [À RECODER] : Rendre l'IA plus intelligente
        $actionKeys = array_keys($enemyActions);
        $randomKey = $actionKeys[array_rand($actionKeys)];
        $action = $enemyActions[$randomKey];
        $method = $action['method'];

        // Exécute l'action de l'ennemi
        if ($action['needsTarget'] ?? false) {
            $result = $this->enemy->$method($this->player);
        } else {
            $result = $this->enemy->$method();
        }
        $this->logs[] = "🤖 " . $this->enemy->getName() . " : " . $result;

        // Vérifie si le joueur est mort
        if ($this->player->isDead()) {
            $this->logs[] = "💀 " . $this->player->getName() . " a été vaincu...";
        }
    }

    /**
     * Vérifie si le combat est terminé
     */
    public function isOver(): bool {
        return $this->player->isDead() || $this->enemy->isDead();
    }

    /**
     * Retourne le vainqueur du combat
     */
    public function getWinner(): ?Personnage {
        if ($this->enemy->isDead()) {
            return $this->player;
        } elseif ($this->player->isDead()) {
            return $this->enemy;
        }
        return null;
    }
}
