<?php
/** AQUATIQUE - Esquive et soins aquatiques */

class Aquatique extends Personnage {
    
    private $dodgeChance = 50;

    public function __construct($pv, $atk, $name, $def = 3, $speed = 10) {
        parent::__construct($pv, $atk, $name, $def, "Aquatique", $speed);
    }

    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => 'Jet d\'eau',
                'emoji' => '💧',
                'description' => 'Attaque de base (ignore 3 DEF)',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'dodge' => [
                'label' => 'Esquive',
                'emoji' => '💨',
                'description' => '50% d\'esquiver la prochaine attaque',
                'method' => 'dodge',
                'needsTarget' => false,
                'pp' => 4
            ],
            'heal' => [
                'label' => 'Régénération',
                'emoji' => '🌊',
                'description' => '+12-18 PV',
                'method' => 'heal',
                'needsTarget' => false,
                'pp' => 4
            ],
            'tsunami' => [
                'label' => 'Tsunami',
                'emoji' => '🌀',
                'description' => 'Inflige x1.8 atk',
                'method' => 'tsunami',
                'needsTarget' => true,
                'pp' => 2
            ]
        ];
    }

    public function attack(Personnage $target): string {
        $damage = $this->randomDamage(max(1, $this->getAtk() - $target->getDef()), 3);
        $target->receiveDamage($damage, $this);
        return $target->isDead() ? "torrent ! $damage dégâts ! K.O. !" : "jet d'eau ! $damage dégâts";
    }

    public function dodge(): string {
        if ($this->roll(1, 100) <= $this->dodgeChance) {
            $this->setEvading(true);
            return "devient insaisissable ! Esquive prête !";
        }
        return "rate son esquive...";
    }

    public function heal($x = null): string {
        $oldPv = $this->pv;
        $amount = $this->roll(12, 18);
        $this->setPv($this->pv + $amount);
        $this->triggerHealHooks($amount);
        return "se régénère ! +" . ($this->pv - $oldPv) . " PV";
    }

    public function tsunami(Personnage $target): string {
        $damage = $this->randomDamage(max(1, (int)($this->getAtk() * 1.8) - $target->getDef()), 4);
        $target->receiveDamage($damage, $this);
        return $target->isDead() ? "TSUNAMI ! $damage dégâts ! K.O. !" : "TSUNAMI ! $damage dégâts !";
    }
}
