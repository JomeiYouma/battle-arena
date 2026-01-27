<?php
/**
 * DATABASE - Singleton PDO connection
 */

class Database {
    private static ?PDO $instance = null;
    
    // Configuration - À modifier selon ton environnement
    private const HOST = 'localhost';
    private const DB_NAME = 'horus_arena';
    private const USERNAME = 'root';
    private const PASSWORD = '';  // XAMPP par défaut = vide
    
    private function __construct() {}
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    'mysql:host=' . self::HOST . ';dbname=' . self::DB_NAME . ';charset=utf8mb4',
                    self::USERNAME,
                    self::PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
