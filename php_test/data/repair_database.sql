-- =====================================================
-- Horus Battle Arena - Script de Réparation de Base de Données
-- Exécuter ce script dans phpMyAdmin pour réparer/mettre à jour la BDD
-- IMPORTANT: Exécuter section par section si nécessaire
-- Date: Février 2026
-- =====================================================

-- Sélectionner la base de données
USE horus_arena;

-- =====================================================
-- 1. TABLE USERS
-- =====================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. TABLE HEROES
-- =====================================================

CREATE TABLE IF NOT EXISTS `heroes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hero_id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `pv` INT NOT NULL,
  `atk` INT NOT NULL,
  `def` INT NOT NULL DEFAULT 5,
  `speed` INT NOT NULL DEFAULT 10,
  `description` TEXT,
  `image_p1` VARCHAR(255),
  `image_p2` VARCHAR(255),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hero_id` (`hero_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. TABLE COMBAT_STATS
-- =====================================================

CREATE TABLE IF NOT EXISTS combat_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    hero_id VARCHAR(50) NOT NULL,
    victory TINYINT(1) NOT NULL,
    opponent_hero_id VARCHAR(50),
    game_mode VARCHAR(20) DEFAULT 'single',
    team1_id INT DEFAULT NULL,
    team2_id INT DEFAULT NULL,
    match_uuid VARCHAR(50) DEFAULT NULL COMMENT 'UUID pour grouper les stats d un meme match 5v5',
    team_name VARCHAR(100) DEFAULT NULL COMMENT 'Nom de l equipe utilisee',
    opponent_name VARCHAR(100) DEFAULT NULL COMMENT 'Nom de l adversaire',
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter les nouvelles colonnes si elles n'existent pas (pour mise à jour)
-- Exécuter ces commandes séparément si erreur
ALTER TABLE combat_stats ADD COLUMN IF NOT EXISTS match_uuid VARCHAR(50) DEFAULT NULL;
ALTER TABLE combat_stats ADD COLUMN IF NOT EXISTS team_name VARCHAR(100) DEFAULT NULL;
ALTER TABLE combat_stats ADD COLUMN IF NOT EXISTS opponent_name VARCHAR(100) DEFAULT NULL;

-- Index pour les requêtes 5v5
CREATE INDEX IF NOT EXISTS idx_match_uuid ON combat_stats(match_uuid);

-- Nettoyer les valeurs NULL dans game_mode
UPDATE combat_stats SET game_mode = 'single' WHERE game_mode IS NULL OR game_mode = '';

-- =====================================================
-- 4. TABLE MATCH_QUEUE
-- =====================================================

CREATE TABLE IF NOT EXISTS match_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    user_id INT DEFAULT NULL,
    hero_data JSON,
    display_name VARCHAR(100) NOT NULL,
    blessing_id VARCHAR(50) DEFAULT NULL,
    game_mode VARCHAR(10) DEFAULT '1v1',
    team_id INT DEFAULT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. TABLE TEAMS
-- =====================================================

CREATE TABLE IF NOT EXISTS `teams` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. TABLE TEAM_MEMBERS
-- =====================================================

CREATE TABLE IF NOT EXISTS `team_members` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    position INT NOT NULL,
    hero_id VARCHAR(50) NOT NULL,
    blessing_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. TABLE TEAM_COMBAT_STATE
-- =====================================================

CREATE TABLE IF NOT EXISTS `team_combat_state` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(100) NOT NULL,
    player1_team_data JSON NOT NULL,
    player2_team_data JSON NOT NULL,
    player1_current_idx INT DEFAULT 0,
    player2_current_idx INT DEFAULT 0,
    turn_number INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. VÉRIFICATION
-- =====================================================

SELECT 'Tables créées/vérifiées:' as Status;
SHOW TABLES;

SELECT 'Structure combat_stats:' as Info;
DESCRIBE combat_stats;
