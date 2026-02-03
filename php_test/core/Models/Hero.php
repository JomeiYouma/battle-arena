<?php
/**
 * Modèle de données pour un héros (BDD)
 * Ne contient QUE les données, pas la logique de jeu
 */
class Hero {
    private ?int $id = null;
    private string $heroId;
    private string $name;
    private string $type;
    private int $pv;
    private int $atk;
    private int $def;
    private int $speed;
    private ?string $description = null;
    private ?string $imageP1 = null;
    private ?string $imageP2 = null;
    private bool $isActive = true;
    
    /**
     * Hydratation depuis tableau associatif
     */
    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    private function hydrate(array $data): void {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }
    
    // GETTERS
    public function getId(): ?int { return $this->id; }
    public function getHeroId(): string { return $this->heroId; }
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getPv(): int { return $this->pv; }
    public function getAtk(): int { return $this->atk; }
    public function getDef(): int { return $this->def; }
    public function getSpeed(): int { return $this->speed; }
    public function getDescription(): ?string { return $this->description; }
    public function getImageP1(): ?string { return $this->imageP1; }
    public function getImageP2(): ?string { return $this->imageP2; }
    public function isActive(): bool { return $this->isActive; }
    
    // SETTERS
    public function setId(int $id): void { $this->id = $id; }
    public function setHeroId(string $heroId): void { $this->heroId = $heroId; }
    public function setName(string $name): void { $this->name = $name; }
    public function setType(string $type): void { $this->type = $type; }
    public function setPv(int $pv): void { $this->pv = $pv; }
    public function setAtk(int $atk): void { $this->atk = $atk; }
    public function setDef(int $def): void { $this->def = $def; }
    public function setSpeed(int $speed): void { $this->speed = $speed; }
    public function setDescription(?string $description): void { $this->description = $description; }
    public function setImageP1(?string $imageP1): void { $this->imageP1 = $imageP1; }
    public function setImageP2(?string $imageP2): void { $this->imageP2 = $imageP2; }
    public function setIsActive($isActive): void { 
        $this->isActive = (bool)$isActive; 
    }
    
    /**
     * Convertit en tableau pour JSON/affichage (compatibilité avec l'ancien système)
     * Les images sont retournées telles quelles depuis la BDD (ex: media/heroes/fire_skol.png)
     * Les composants utiliseront asset_url() pour construire le chemin final
     */
    public function toArray(): array {
        return [
            'id' => $this->heroId,
            'name' => $this->name,
            'type' => $this->type,
            'pv' => $this->pv,
            'atk' => $this->atk,
            'def' => $this->def,
            'speed' => $this->speed,
            'description' => $this->description,
            'images' => [
                'p1' => $this->imageP1,
                'p2' => $this->imageP2 ?? $this->imageP1
            ],
            'image_p1' => $this->imageP1,
            'image_p2' => $this->imageP2 ?? $this->imageP1
        ];
    }
}
