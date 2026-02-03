<?php
/**
 * BRUTE - Colosse lent et dévastateur. Bombe finale quand presque mort.
 */
class Brute extends Personnage {
    
    public function __construct($pv, $atk, $name, $def = 8, $speed = 3) {
        parent::__construct($pv, $atk, $name, $def, 'Brute', $speed);
    }

    public function getAvailableActions(): array {
        $actions = [
            'attack' => [
                'label' => 'Coup Écrasant',
                'emoji' => '👊',
                'description' => 'Attaque de base, ignore 30% de la DEF',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'charge' => [
                'label' => 'Charge Brutale',
                'emoji' => '💀',
                'description' => 'Inflige x1.5 ATK, perd 15 PV',
                'method' => 'charge',
                'needsTarget' => true,
                'pp' => 2
            ],
            'stomp' => [
                'label' => 'Piétinement',
                'emoji' => '🦶',
                'description' => 'Réduit la vitesse de l\'adversaire de 10 pendant 3 tours',
                'method' => 'stomp',
                'needsTarget' => true,
                'pp' => 2
            ],
            'bonearmor' => [
                'label' => 'Armure d\'Os',
                'emoji' => '🦴',
                'description' => '+10 DEF pendant 2 tours',
                'method' => 'boneArmor',
                'needsTarget' => false,
                'pp' => 2
            ]
        ];
        
        // Bombe finale disponible seulement si PV <= 20%
        $healthPct = $this->pv / $this->basePv;
        if ($healthPct <= 0.20 && (!isset($this->pp['deathbomb']) || $this->pp['deathbomb']['current'] > 0)) {
            $actions['deathbomb'] = [
                'label' => '💣 BOMBE FINALE',
                'emoji' => '💣',
                'description' => 'Dans 1 tour : inflige 60% de ses PV max à votre adversaire !',
                'method' => 'deathBomb',
                'needsTarget' => true,
                'pp' => 1
            ];
        }
        
        return $actions;
    }

    // Coup écrasant - Ignore 30% DEF
    public function attack(Personnage $target): string {
        $effectiveDef = (int) ($target->getDef() * 0.7);
        $damage = $this->randomDamage(max(1, $this->atk - $effectiveDef), 4);
        $target->receiveDamage($damage, $this);
        return $target->isDead() 
            ? "ÉCRASE ! $damage dégâts ! K.O. !" 
            : "coup écrasant ! $damage dégâts";
    }

    // Charge brutale - x1.5 dégâts, -15 PV
    public function charge(Personnage $target): string {
        $this->setPv($this->pv - 15);
        $baseDamage = max(1, (int)($this->atk * 1.5) - $target->getDef());
        $damage = $this->randomDamage($baseDamage, 5);
        $target->receiveDamage($damage, $this);
        return $target->isDead() 
            ? "CHARGE BRUTALE ! $damage dégâts ! K.O. !" 
            : "CHARGE ! $damage dég (-15 PV)";
    }

    // Piétinement - Dégâts + ralentissement
    public function stomp(Personnage $target): string {
        $damage = $this->randomDamage(max(1, $this->atk - $target->getDef()), 3);
        $target->receiveDamage($damage, $this);
        $target->addStatusEffect(new SpeedModEffect(3, -10), $this);
        return $target->isDead() 
            ? "PIÉTINE ! $damage dégâts ! K.O. !" 
            : "PIÉTINE ! $damage dég, ennemi ralenti !";
    }

    // Armure d'os - +10 DEF
    public function boneArmor(): string {
        // Vérifier s'il y a déjà un DefenseBoostEffect actif
        foreach ($this->statusEffects as $effect) {
            if ($effect instanceof DefenseBoostEffect) {
                return "armure déjà active !";
            }
        }
        $this->addStatusEffect(new DefenseBoostEffect(2, 10));
        return "renforce ses os ! +10 DEF";
    }

    // Bombe finale - Dégâts différés au tour suivant (60% PV max)
    public function deathBomb(Personnage $target): string {
        $damage = (int)($this->basePv * 0.6);
        $target->addStatusEffect(new BombEffect(1, $damage), $this);
        $this->setPv(0);
        return "AMORCE LA BOMBE FINALE ! Explosion imminente ($damage dégâts) !";
    }
}
