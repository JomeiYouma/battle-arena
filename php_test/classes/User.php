<?php
/**
 * USER - Gestion des utilisateurs et statistiques
 */

class User {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Inscription d'un nouvel utilisateur
     */
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
        $stmt = $this->db->prepare('SELECT id, username, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Email ou mot de passe incorrect'];
        }
        
        // Stocker en session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        return ['success' => true, 'user' => ['id' => $user['id'], 'username' => $user['username']]];
    }
    
    /**
     * Déconnexion
     */
    public static function logout(): void {
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
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
     * Enregistrer un combat
     */
    public function recordCombat(int $userId, string $heroId, bool $victory, ?string $opponentHeroId = null, string $mode = 'single'): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO combat_stats (user_id, hero_id, victory, opponent_hero_id, game_mode) VALUES (?, ?, ?, ?, ?)'
        );
        return $stmt->execute([$userId, $heroId, (int)$victory, $opponentHeroId, $mode]);
    }
    
    /**
     * Récupérer les statistiques globales
     */
    public function getGlobalStats(int $userId): array {
        $stmt = $this->db->prepare('
            SELECT 
                COUNT(*) as total,
                SUM(victory) as wins,
                COUNT(*) - SUM(victory) as losses
            FROM combat_stats 
            WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
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
     * Récupérer les personnages les plus joués
     */
    public function getMostPlayedHeroes(int $userId, int $limit = 3): array {
        $stmt = $this->db->prepare('
            SELECT 
                hero_id,
                COUNT(*) as games,
                SUM(victory) as wins
            FROM combat_stats 
            WHERE user_id = ?
            GROUP BY hero_id
            ORDER BY games DESC
            LIMIT ?
        ');
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer les statistiques par héros
     */
    public function getHeroStats(int $userId): array {
        $stmt = $this->db->prepare('
            SELECT 
                hero_id,
                COUNT(*) as games,
                SUM(victory) as wins,
                COUNT(*) - SUM(victory) as losses
            FROM combat_stats 
            WHERE user_id = ?
            GROUP BY hero_id
            ORDER BY games DESC
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer l'historique des derniers combats
     */
    public function getRecentCombats(int $userId, int $limit = 10): array {
        $stmt = $this->db->prepare('
            SELECT 
                hero_id,
                victory,
                opponent_hero_id,
                game_mode,
                played_at
            FROM combat_stats 
            WHERE user_id = ?
            ORDER BY played_at DESC
            LIMIT ?
        ');
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}
