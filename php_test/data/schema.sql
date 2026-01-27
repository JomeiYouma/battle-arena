-- =====================================================
-- Horus Battle Arena - Database Schema
-- Execute this script in phpMyAdmin
-- =====================================================

-- Create database (optional, you can use an existing one)
CREATE DATABASE IF NOT EXISTS horus_arena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE horus_arena;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
