-- =====================================================
-- Horus Battle Arena - Database Schema
-- Execute this script in phpMyAdmin
-- =====================================================

-- Create database (optional, you can use an existing one)
CREATE DATABASE IF NOT EXISTS horus_arena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE horus_arena;

-- Table des héros
CREATE TABLE IF NOT EXISTS `heroes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hero_id` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identifiant unique (ex: brutor)',
  `name` VARCHAR(100) NOT NULL COMMENT 'Nom affiché du héros',
  `type` VARCHAR(50) NOT NULL COMMENT 'Classe: Pyromane, Guerrier, etc.',
  `pv` INT NOT NULL COMMENT 'Points de vie',
  `atk` INT NOT NULL COMMENT 'Attaque',
  `def` INT NOT NULL DEFAULT 5 COMMENT 'Défense',
  `speed` INT NOT NULL DEFAULT 10 COMMENT 'Vitesse',
  `description` TEXT COMMENT 'Description du héros',
  `image_p1` VARCHAR(255) COMMENT 'Chemin image joueur 1',
  `image_p2` VARCHAR(255) COMMENT 'Chemin image joueur 2',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Actif/Inactif',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_type` (`type`),
  INDEX `idx_hero_id` (`hero_id`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0 COMMENT 'Administrateur (1) ou utilisateur normal (0)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table de la file d'attente multijoueur
CREATE TABLE IF NOT EXISTS match_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL UNIQUE,
    user_id INT DEFAULT NULL,
    hero_data JSON NOT NULL COMMENT 'Données du héros sélectionné',
    display_name VARCHAR(100) NOT NULL,
    blessing_id VARCHAR(50) DEFAULT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_joined (joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des statistiques de combat
CREATE TABLE IF NOT EXISTS combat_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    hero_id VARCHAR(50) NOT NULL,
    victory BOOLEAN NOT NULL,
    opponent_hero_id VARCHAR(50),
    game_mode ENUM('single', 'multi') DEFAULT 'single',
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index pour optimiser les requêtes de stats
CREATE INDEX idx_user_stats ON combat_stats(user_id, hero_id);
CREATE INDEX idx_user_date ON combat_stats(user_id, played_at);
