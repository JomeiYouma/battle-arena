<?php
/** USER - Gestion des utilisateurs et statistiques */

class User {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register(string $username, string $email, string $password): array {
        // Validation
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'error' => 'Le pseudo doit faire entre 3 et 50 caractères'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Email invalide'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'Le mot de passe doit faire au moins 6 caractères'];
        }
        
        // Vérifier si username ou email existe déjà
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Ce pseudo ou email est déjà utilisé'];
        }
        
        // Créer l'utilisateur
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        
        try {
            $stmt->execute([$username, $email, $hash]);
            return ['success' => true, 'user_id' => $this->db->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur lors de la création du compte'];
        }
    }
    
    /**
     * Connexion d'un utilisateur
     */
    public function login(string $email, string $password): array {
        $stmt = $this->db->prepare('SELECT id, username, password_hash, is_admin FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
        }
        
        // Stocker en session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = (int)$user['is_admin'];
        
        return ['success' => true, 'user' => ['id' => $user['id'], 'username' => $user['username'], 'is_admin' => $user['is_admin']]];
    }
    
    /**
     * Déconnexion
     */
    public static function logout(): void {
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['is_admin']);
    }
    
    /**
     * Vérifier si connecté
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Récupérer l'ID de l'utilisateur connecté
     */
    public static function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Récupérer le pseudo de l'utilisateur connecté
     */
    public static function getCurrentUsername(): ?string {
        return $_SESSION['username'] ?? null;
    }
    
    /**
     * Vérifier si l'utilisateur connecté est admin
     */
    public static function isAdmin(): bool {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    
    /**
     * Enregistrer un combat
     */
    public function recordCombat(int $userId, string $heroId, bool $victory, ?string $opponentHeroId = null, string $mode = 'single'): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO combat_stats (user_id, hero_id, victory, opponent_hero_id, game_mode) VALUES (?, ?, ?, ?, ?)'
        );
        return $stmt->execute([$userId, $heroId, (int)$victory, $opponentHeroId, $mode]);
    }
    
    /**
     * Enregistrer un combat 5v5 avec tous les héros de l'équipe
     * @param int $userId ID de l'utilisateur
     * @param array $heroIds Liste des hero_id de l'équipe (sans doublons)
     * @param bool $victory Victoire ou défaite
     * @param string $teamName Nom de l'équipe utilisée
     * @param string $opponentName Nom de l'adversaire
     * @param string $matchUuid UUID unique du match pour grouper les stats
     */
    public function recordTeamCombat(int $userId, array $heroIds, bool $victory, string $teamName, string $opponentName, string $matchUuid): bool {
        // Supprimer les doublons de héros
        $uniqueHeroIds = array_unique($heroIds);
        
        $stmt = $this->db->prepare(
            'INSERT INTO combat_stats (user_id, hero_id, victory, game_mode, team_name, opponent_name, match_uuid) 
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        
        $success = true;
        foreach ($uniqueHeroIds as $heroId) {
            if (!$stmt->execute([$userId, $heroId, (int)$victory, '5v5', $teamName, $opponentName, $matchUuid])) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Récupérer les statistiques globales
     */
    public function getGlobalStats(int $userId, ?string $gameMode = null): array {
        $sql = '
            SELECT 
                COUNT(*) as total,
                SUM(victory) as wins,
                COUNT(*) - SUM(victory) as losses
            FROM combat_stats 
            WHERE user_id = ?
        ';
        $params = [$userId];
        
        if ($gameMode !== null) {
            $sql .= ' AND game_mode = ?';
            $params[] = $gameMode;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch();
        
        $total = (int)($stats['total'] ?? 0);
        $wins = (int)($stats['wins'] ?? 0);
        
        return [
            'total' => $total,
            'wins' => $wins,
            'losses' => $total - $wins,
            'ratio' => $total > 0 ? round(($wins / $total) * 100, 1) : 0
        ];
    }
    
    /**
     * Récupérer les statistiques globales 1v1 (exclut le 5v5)
     */
    public function get1v1GlobalStats(int $userId): array {
        $stmt = $this->db->prepare('
            SELECT 
                COUNT(*) as total,
                SUM(victory) as wins,
                COUNT(*) - SUM(victory) as losses
            FROM combat_stats 
            WHERE user_id = ? AND game_mode != ?
        ');
        $stmt->execute([$userId, '5v5']);
        $stats = $stmt->fetch();
        
        $total = (int)($stats['total'] ?? 0);
        $wins = (int)($stats['wins'] ?? 0);
        
        return [
            'total' => $total,
            'wins' => $wins,
            'losses' => $total - $wins,
            'ratio' => $total > 0 ? round(($wins / $total) * 100, 1) : 0
        ];
    }
    
    /**
     * Récupérer les statistiques détaillées par mode de jeu
     */
    public function getStatsByMode(int $userId): array {
        $stmt = $this->db->prepare('
            SELECT 
                game_mode,
                COUNT(*) as total,
                SUM(victory) as wins,
                COUNT(*) - SUM(victory) as losses
            FROM combat_stats 
            WHERE user_id = ?
            GROUP BY game_mode
        ');
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll();
        
        $statsByMode = [];
        foreach ($results as $row) {
            $total = (int)($row['total'] ?? 0);
            $wins = (int)($row['wins'] ?? 0);
            $statsByMode[$row['game_mode']] = [
                'total' => $total,
                'wins' => $wins,
                'losses' => $total - $wins,
                'ratio' => $total > 0 ? round(($wins / $total) * 100, 1) : 0
            ];
        }
        
        return $statsByMode;
    }
    
    /**
     * Récupérer les personnages les plus joués
     */
    public function getMostPlayedHeroes(int $userId, int $limit = 3, ?string $gameMode = null, bool $exclude5v5 = false): array {
        $sql = '
            SELECT 
                hero_id,
                COUNT(*) as games,
                SUM(victory) as wins
            FROM combat_stats 
            WHERE user_id = ?';
        $params = [$userId];
        
        if ($gameMode !== null) {
            $sql .= ' AND game_mode = ?';
            $params[] = $gameMode;
        }
        
        if ($exclude5v5) {
            $sql .= ' AND game_mode != ?';
            $params[] = '5v5';
        }
        
        $sql .= ' GROUP BY hero_id ORDER BY games DESC LIMIT ?';
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer les statistiques par héros
     */
    public function getHeroStats(int $userId, ?string $gameMode = null, bool $exclude5v5 = false): array {
        $sql = '
            SELECT 
                hero_id,
                COUNT(*) as games,
                SUM(victory) as wins,
                COUNT(*) - SUM(victory) as losses
            FROM combat_stats 
            WHERE user_id = ?';
        $params = [$userId];
        
        if ($gameMode !== null) {
            $sql .= ' AND game_mode = ?';
            $params[] = $gameMode;
        }
        
        if ($exclude5v5) {
            $sql .= ' AND game_mode != ?';
            $params[] = '5v5';
        }
        
        $sql .= ' GROUP BY hero_id ORDER BY games DESC';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer l'historique des derniers combats
     */
    public function getRecentCombats(int $userId, int $limit = 10, ?string $gameMode = null, bool $exclude5v5 = false): array {
        $sql = '
            SELECT 
                hero_id,
                victory,
                opponent_hero_id,
                game_mode,
                played_at
            FROM combat_stats 
            WHERE user_id = ?';
        $params = [$userId];
        
        if ($gameMode !== null) {
            $sql .= ' AND game_mode = ?';
            $params[] = $gameMode;
        }
        
        if ($exclude5v5) {
            $sql .= ' AND game_mode != ?';
            $params[] = '5v5';
        }
        
        $sql .= ' ORDER BY played_at DESC LIMIT ?';
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer les statistiques 5v5 détaillées
     */
    public function get5v5Stats(int $userId): array {
        // Stats globales 5v5 (comptées par match unique, pas par héros)
        $globalStats = $this->get5v5GlobalStats($userId);
        
        // Héros les plus joués en 5v5
        $mostPlayed = $this->getMostPlayedHeroes($userId, 5, '5v5');
        
        // Stats par héros en 5v5
        $heroStats = $this->getHeroStats($userId, '5v5');
        
        // Historique récent 5v5 (groupé par match)
        $recentCombats = $this->getRecent5v5Matches($userId, 10);
        
        // Streak actuelle (victoires/défaites consécutives)
        $streak = $this->get5v5Streak($userId);
        
        return [
            'global' => $globalStats,
            'mostPlayed' => $mostPlayed,
            'heroStats' => $heroStats,
            'recentCombats' => $recentCombats,
            'streak' => $streak
        ];
    }
    
    /**
     * Stats globales 5v5 (comptées par match unique, pas par héros)
     */
    public function get5v5GlobalStats(int $userId): array {
        // Compter les matchs uniques via match_uuid
        $stmt = $this->db->prepare('
            SELECT 
                COUNT(DISTINCT match_uuid) as total,
                SUM(CASE WHEN victory = 1 THEN 1 ELSE 0 END) as wins_raw
            FROM combat_stats 
            WHERE user_id = ? AND game_mode = ?
        ');
        $stmt->execute([$userId, '5v5']);
        $rawStats = $stmt->fetch();
        
        // Pour les anciens enregistrements sans match_uuid, compter normalement
        $stmtOld = $this->db->prepare('
            SELECT 
                COUNT(*) as total,
                SUM(victory) as wins
            FROM combat_stats 
            WHERE user_id = ? AND game_mode = ? AND match_uuid IS NULL
        ');
        $stmtOld->execute([$userId, '5v5']);
        $oldStats = $stmtOld->fetch();
        
        // Pour les nouveaux avec match_uuid, compter par match unique
        $stmtNew = $this->db->prepare('
            SELECT 
                COUNT(DISTINCT match_uuid) as total,
                COUNT(DISTINCT CASE WHEN victory = 1 THEN match_uuid END) as wins
            FROM combat_stats 
            WHERE user_id = ? AND game_mode = ? AND match_uuid IS NOT NULL
        ');
        $stmtNew->execute([$userId, '5v5']);
        $newStats = $stmtNew->fetch();
        
        $total = (int)($oldStats['total'] ?? 0) + (int)($newStats['total'] ?? 0);
        $wins = (int)($oldStats['wins'] ?? 0) + (int)($newStats['wins'] ?? 0);
        
        return [
            'total' => $total,
            'wins' => $wins,
            'losses' => $total - $wins,
            'ratio' => $total > 0 ? round(($wins / $total) * 100, 1) : 0
        ];
    }
    
    /**
     * Historique récent 5v5 (groupé par match avec team_name et opponent_name)
     */
    public function getRecent5v5Matches(int $userId, int $limit = 10): array {
        $stmt = $this->db->prepare('
            SELECT 
                match_uuid,
                team_name,
                opponent_name,
                victory,
                played_at,
                GROUP_CONCAT(DISTINCT hero_id) as heroes_used
            FROM combat_stats 
            WHERE user_id = ? AND game_mode = ?
            GROUP BY COALESCE(match_uuid, id), team_name, opponent_name, victory, played_at
            ORDER BY played_at DESC
            LIMIT ?
        ');
        $stmt->execute([$userId, '5v5', $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Série actuelle 5v5 (par match unique)
     */
    public function get5v5Streak(int $userId): array {
        $stmt = $this->db->prepare('
            SELECT victory, match_uuid
            FROM combat_stats 
            WHERE user_id = ? AND game_mode = ?
            GROUP BY COALESCE(match_uuid, id), victory
            ORDER BY MAX(played_at) DESC
            LIMIT 20
        ');
        $stmt->execute([$userId, '5v5']);
        $results = $stmt->fetchAll();
        
        if (empty($results)) {
            return ['type' => 'none', 'count' => 0];
        }
        
        $firstResult = (bool)$results[0]['victory'];
        $streak = 0;
        
        foreach ($results as $row) {
            if ((bool)$row['victory'] === $firstResult) {
                $streak++;
            } else {
                break;
            }
        }
        
        return [
            'type' => $firstResult ? 'win' : 'loss',
            'count' => $streak
        ];
    }
    
    /**
     * Calculer la série actuelle (victoires/défaites consécutives)
     */
    public function getCurrentStreak(int $userId, ?string $gameMode = null): array {
        $sql = '
            SELECT victory
            FROM combat_stats 
            WHERE user_id = ?';
        $params = [$userId];
        
        if ($gameMode !== null) {
            $sql .= ' AND game_mode = ?';
            $params[] = $gameMode;
        }
        
        $sql .= ' ORDER BY played_at DESC LIMIT 20';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        if (empty($results)) {
            return ['type' => 'none', 'count' => 0];
        }
        
        $firstResult = (bool)$results[0]['victory'];
        $streak = 0;
        
        foreach ($results as $row) {
            if ((bool)$row['victory'] === $firstResult) {
                $streak++;
            } else {
                break;
            }
        }
        
        return [
            'type' => $firstResult ? 'win' : 'loss',
            'count' => $streak
        ];
    }
    
    /**
     * Récupérer le meilleur héros par winrate (minimum X parties)
     */
    public function getBestHeroByWinrate(int $userId, ?string $gameMode = null, int $minGames = 3): ?array {
        $sql = '
            SELECT 
                hero_id,
                COUNT(*) as games,
                SUM(victory) as wins,
                (SUM(victory) * 100.0 / COUNT(*)) as winrate
            FROM combat_stats 
            WHERE user_id = ?';
        $params = [$userId];
        
        if ($gameMode !== null) {
            $sql .= ' AND game_mode = ?';
            $params[] = $gameMode;
        }
        
        $sql .= ' GROUP BY hero_id HAVING games >= ? ORDER BY winrate DESC, games DESC LIMIT 1';
        $params[] = $minGames;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    public function getLeaderboard(int $limit = 20): array {
        $sql = '
            SELECT 
                u.id,
                u.username,
                COUNT(cs.id) as total_games,
                SUM(cs.victory) as wins,
                COUNT(cs.id) - SUM(cs.victory) as losses,
                ROUND(SUM(cs.victory) * 100.0 / NULLIF(COUNT(cs.id), 0), 1) as winrate
            FROM users u
            INNER JOIN combat_stats cs ON u.id = cs.user_id
            GROUP BY u.id, u.username
            HAVING total_games >= 1
            ORDER BY wins DESC, winrate DESC, total_games DESC
            LIMIT ?
        ';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        $leaderboard = $stmt->fetchAll();
        
        foreach ($leaderboard as &$player) {
            $mostPlayed = $this->getMostPlayedHeroes((int)$player['id'], 1);
            $player['main_hero'] = !empty($mostPlayed) ? $mostPlayed[0]['hero_id'] : null;
        }
        
        return $leaderboard;
    }
}
