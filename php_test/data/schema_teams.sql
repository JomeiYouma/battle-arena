-- =====================================================
-- Horus Battle Arena - Schema d'Équipes
-- Exécuter ce script pour ajouter le support 5v5
-- =====================================================

-- Table des équipes
CREATE TABLE IF NOT EXISTS `teams` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    team_name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_team (user_id, team_name),
    INDEX idx_user_teams (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des membres de l'équipe (5 héros par équipe)
CREATE TABLE IF NOT EXISTS `team_members` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    position INT NOT NULL COMMENT '1-5: position dans l\'équipe',
    hero_id VARCHAR(50) NOT NULL COMMENT 'Référence à heroes.hero_id (ex: brutor)',
    blessing_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_position (team_id, position),
    INDEX idx_team_members (team_id),
    INDEX idx_hero (hero_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- État des équipes pendant le combat 5v5
CREATE TABLE IF NOT EXISTS `team_combat_state` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(100) NOT NULL UNIQUE,
    player1_team_data JSON NOT NULL COMMENT 'État sérialisé équipe 1: [{hero_id, hp, buffs, debuffs}, ...]',
    player2_team_data JSON NOT NULL COMMENT 'État sérialisé équipe 2',
    player1_current_idx INT DEFAULT 0 COMMENT 'Index du héros courant côté 1 (0-4)',
    player2_current_idx INT DEFAULT 0 COMMENT 'Index du héros courant côté 2 (0-4)',
    turn_number INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_match (match_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modifications aux tables existantes

-- Ajouter colonnes à match_queue pour supporter les équipes (5v5)
ALTER TABLE match_queue ADD COLUMN IF NOT EXISTS game_mode ENUM('1v1', '5v5') DEFAULT '1v1';
ALTER TABLE match_queue ADD COLUMN IF NOT EXISTS team_id INT DEFAULT NULL;
ALTER TABLE match_queue ADD FOREIGN KEY IF NOT EXISTS fk_team_id (team_id) REFERENCES teams(id) ON DELETE SET NULL;

-- Modifier combat_stats pour enregistrer le type de match
ALTER TABLE combat_stats MODIFY COLUMN game_mode ENUM('single', 'multi_1v1', 'multi_5v5') DEFAULT 'single';
ALTER TABLE combat_stats ADD COLUMN IF NOT EXISTS team1_id INT DEFAULT NULL;
ALTER TABLE combat_stats ADD COLUMN IF NOT EXISTS team2_id INT DEFAULT NULL;

-- Indices de performance
CREATE INDEX IF NOT EXISTS idx_match_queue_mode ON match_queue(game_mode, joined_at);
CREATE INDEX IF NOT EXISTS idx_team_combat_match ON team_combat_state(match_id);
CREATE INDEX IF NOT EXISTS idx_combat_stats_mode ON combat_stats(user_id, game_mode);
