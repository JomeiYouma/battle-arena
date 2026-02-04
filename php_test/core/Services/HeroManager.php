<?php
/** HEROMANAGER - CRUD sur les héros (Pattern Repository) */

require_once __DIR__ . '/../Models/Hero.php';
require_once __DIR__ . '/../Database.php';

class HeroManager {
    private PDO $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getAll(bool $includeInactive = false): array {
        $query = "SELECT * FROM heroes ";
        if (!$includeInactive) {
            $query .= "WHERE is_active = 1 ";
        }
        $query .= "ORDER BY name ASC";
        
        $stmt = $this->db->query($query);
        
        $heroes = [];
        while ($data = $stmt->fetch()) {
            $heroes[] = new Hero($data);
        }
        
        return $heroes;
    }
    
    /**
     * Récupère un héros par son ID de BDD
     */
    public function get(int $id): ?Hero {
        $stmt = $this->db->prepare("SELECT * FROM heroes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();
        
        return $data ? new Hero($data) : null;
    }
    
    /**
     * Récupère un héros par son hero_id (ex: "brutor")
     */
    public function getByHeroId(string $heroId): ?Hero {
        $stmt = $this->db->prepare("SELECT * FROM heroes WHERE hero_id = :hero_id");
        $stmt->execute(['hero_id' => $heroId]);
        $data = $stmt->fetch();
        
        return $data ? new Hero($data) : null;
    }
    
    /**
     * Ajoute un nouveau héros
     */
    public function add(Hero $hero): bool {
        $stmt = $this->db->prepare("
            INSERT INTO heroes (hero_id, name, type, pv, atk, def, speed, description, image_p1, image_p2, is_active)
            VALUES (:hero_id, :name, :type, :pv, :atk, :def, :speed, :description, :image_p1, :image_p2, :is_active)
        ");
        
        return $stmt->execute([
            'hero_id' => $hero->getHeroId(),
            'name' => $hero->getName(),
            'type' => $hero->getType(),
            'pv' => $hero->getPv(),
            'atk' => $hero->getAtk(),
            'def' => $hero->getDef(),
            'speed' => $hero->getSpeed(),
            'description' => $hero->getDescription(),
            'image_p1' => $hero->getImageP1(),
            'image_p2' => $hero->getImageP2(),
            'is_active' => $hero->isActive() ? 1 : 0
        ]);
    }
    
    /**
     * Met à jour un héros existant
     */
    public function update(Hero $hero): bool {
        $stmt = $this->db->prepare("
            UPDATE heroes 
            SET hero_id = :hero_id, 
                name = :name, 
                type = :type, 
                pv = :pv, 
                atk = :atk, 
                def = :def, 
                speed = :speed, 
                description = :description,
                image_p1 = :image_p1,
                image_p2 = :image_p2,
                is_active = :is_active
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $hero->getId(),
            'hero_id' => $hero->getHeroId(),
            'name' => $hero->getName(),
            'type' => $hero->getType(),
            'pv' => $hero->getPv(),
            'atk' => $hero->getAtk(),
            'def' => $hero->getDef(),
            'speed' => $hero->getSpeed(),
            'description' => $hero->getDescription(),
            'image_p1' => $hero->getImageP1(),
            'image_p2' => $hero->getImageP2(),
            'is_active' => $hero->isActive() ? 1 : 0
        ]);
    }
    
    /**
     * Supprime un héros (soft delete)
     */
    public function delete(Hero $hero): bool {
        $hero->setIsActive(false);
        return $this->update($hero);
    }
    
    /**
     * Suppression définitive
     */
    public function hardDelete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM heroes WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Récupère les héros par type (classe)
     * @return Hero[]
     */
    public function getByType(string $type): array {
        $stmt = $this->db->prepare("
            SELECT * FROM heroes 
            WHERE type = :type AND is_active = 1
            ORDER BY name ASC
        ");
        $stmt->execute(['type' => $type]);
        
        $heroes = [];
        while ($data = $stmt->fetch()) {
            $heroes[] = new Hero($data);
        }
        
        return $heroes;
    }
}
