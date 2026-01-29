<?php
/**
 * Singleton pour la connexion à la base de données
 */
class Database {
    private static ?PDO $instance = null;
    
    private function __construct() {}
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $host = 'localhost';
            $dbname = 'horus_arena';
            $username = 'root';
            $password = '';
            
            try {
                self::$instance = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die("Erreur de connexion BDD : " . $e->getMessage());
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Empêche le clonage du singleton
     */
    private function __clone() {}
    
    /**
     * Empêche la désérialisation du singleton
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
