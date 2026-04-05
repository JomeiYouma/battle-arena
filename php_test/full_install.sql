-- =====================================================
-- Horus Battle Arena - Script d'installation complète
-- =====================================================

-- 1. Création et sélection de la base de données
CREATE DATABASE IF NOT EXISTS horus_arena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE horus_arena;

-- =====================================================
-- 2. CRÉATION DES TABLES
-- =====================================================

-- Table users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table heroes
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
  UNIQUE KEY `hero_id` (`hero_id`),
  INDEX `idx_type` (`type`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table combat_stats
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
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout d'index pour optimiser (si on utilise mariadb cela ignorera gentiment grâce au IF NOT EXISTS)
-- Si l'ajout direct de CREATE INDEX IF NOT EXISTS pose problème sur de vieilles versions MySQL, ils seront manuels, mais c'est supporté sur MariaDB récent.
# CREATE INDEX idx_user_stats ON combat_stats(user_id, hero_id);
# CREATE INDEX idx_user_date ON combat_stats(user_id, played_at);
# CREATE INDEX idx_match_uuid ON combat_stats(match_uuid);

-- Table match_queue
CREATE TABLE IF NOT EXISTS match_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    user_id INT DEFAULT NULL,
    hero_data JSON,
    display_name VARCHAR(100) NOT NULL,
    blessing_id VARCHAR(50) DEFAULT NULL,
    game_mode VARCHAR(10) DEFAULT '1v1',
    team_id INT DEFAULT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_joined (joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table teams
CREATE TABLE IF NOT EXISTS `teams` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table team_members
CREATE TABLE IF NOT EXISTS `team_members` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    position INT NOT NULL,
    hero_id VARCHAR(50) NOT NULL,
    blessing_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table team_combat_state
CREATE TABLE IF NOT EXISTS `team_combat_state` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(100) NOT NULL UNIQUE,
    player1_team_data JSON NOT NULL,
    player2_team_data JSON NOT NULL,
    player1_current_idx INT DEFAULT 0,
    player2_current_idx INT DEFAULT 0,
    turn_number INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. INSERTION DES HÉROS
-- =====================================================

TRUNCATE TABLE `heroes`;

INSERT INTO heroes (hero_id, name, type, pv, atk, def, speed, description, image_p1, image_p2, is_active) VALUES
('brutor', 'Brutor le Brûlant', 'Pyromane', 100, 22, 5, 12, 'Maître des flammes, sacrifie sa vie pour des dégâts dévastateurs.', 'media/heroes/fire_skol.png', 'media/heroes/fire_skol_2.png', 1),
('ulroc', 'Ulroc le Mastoc', 'Guerrier', 120, 20, 10, 8, 'Tank puissant, alterne rage offensive et défense solide.', 'media/heroes/combat_skol.png', 'media/heroes/combat_skol_2.png', 1),
('siras', 'Siras le Sage', 'Guerisseur', 150, 14, 3, 13, 'Énergie divine pour soigner et infliger des dégâts qui ignorent les défenses.', 'media/heroes/priest_skol.png', 'media/heroes/priest_skol_2.png', 1),
('poissard', 'Poissard le Poisson', 'Aquatique', 85, 20, 6, 15, 'Insaisissable et fluide, esquive et contre-attaque avec des tsunamis.', 'media/heroes/fish_skol.png', 'media/heroes/fish_skol_2.png', 1),
('pedro', 'Pedro', 'Barbare', 110, 20, 0, 10, 'Berserk sanguinaire, plus dangereux quand blessé.', 'media/heroes/kouto_skol.png', 'media/heroes/kouto_skol_2.png', 1),
('krimus', 'Krimus l''Astucieux', 'Guerisseur', 110, 12, 9, 9, 'Guérisseur rusé, combine soins puissants et attaques sacrées.', 'media/heroes/sat_skol.png', 'media/heroes/sat_skol_2.png', 1),
('pikratchu', 'Pikratchu', 'Electrique', 90, 18, 3, 11, 'Vitesse extrême et dégâts électriques. Paralysie et charge dévastatrice.', 'media/heroes/pikachu_skol.png', 'media/heroes/pikachu_skol_2.png', 1),
('norgol', 'Norgol Roi des Ombres', 'Necromancien', 145, 17, 4, 5, 'Manipulateur d''âmes, copie les attaques et maudit ses ennemis.', 'media/heroes/necro_skol.png', 'media/heroes/necro_skol_2.png', 1),
('gorath', 'Gorath le Goliathe', 'Brute', 150, 16, 9, 3, 'Géant lent mais dévastateur. Bombe finale si mal en point.', 'media/heroes/giant_skol.png', 'media/heroes/giant_skol_2.png', 1);

