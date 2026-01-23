<?php
/**
 * PYROMANE - Feu et dégâts massifs. Sacrifie sa vie pour des dégâts dévastateurs.
 */
class Pyromane extends Personnage {
    
    private $overheatDamageBonus = 20;
    private $overheatCost = 10;

    public function __construct($pv, $atk, $name, $def = 4, $speed = 10) {
        parent::__construct($pv, $atk, $name, $def, "Pyromane", $speed);
    }

    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => 'Boule de feu',
                'emoji' => '🔥',
                'description' => 'Lance du feu (ignore 3 DEF)',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'overheat' => [
                'label' => 'Surchauffe',
                'emoji' => '☀️',
                'description' => '+20 ATK (2 tours), coûte 10 PV',
                'method' => 'overheat',
                'needsTarget' => false,
                'pp' => 2
            ],
            'flamearrow' => [
                'label' => 'Flèche enflammée',
                'emoji' => '🏹',
                'description' => 'Brûle l\'ennemi 3 tours après impact',
                'method' => 'flameArrow',
                'needsTarget' => true,
                'pp' => 2
            ],
            'heal' => [
                'label' => 'Chaleur vitale',
                'emoji' => '❤️‍🔥',
                'description' => 'Absorbe la chaleur, +12-18 PV',
                'method' => 'heal',
                'needsTarget' => false,
                'pp' => 3
            ],
            'inferno' => [
                'label' => 'Inferno',
                'emoji' => '💀',
                'description' => 'x2 dégâts mais coûte 20 PV !',
                'method' => 'inferno',
                'needsTarget' => true,
                'pp' => 1
            ]
        ];
    }

    public function attack(Personnage $target): string {
        $effectiveDef = max(0, $target->getDef() - 3);
        $damage = $this->randomDamage(max(1, $this->atk - $effectiveDef), 3);
        $target->setPv($target->getPv() - $damage);
        return $target->isDead() ? "FLAMMES ! $damage dégâts ! K.O. !" : "boule de feu ! $damage dégâts";
    }

    public function overheat(): string {
        if (isset($this->activeBuffs['Surchauffe'])) return "déjà en surchauffe !";
        $this->addBuff('Surchauffe', 'atk', $this->overheatDamageBonus, 2);
        $this->setPv($this->pv - $this->overheatCost);
        return "SURCHAUFFE ! +20 ATK (-10 PV)";
    }

    public function flameArrow(Personnage $target): string {
        $damage = $this->randomDamage(5, 2);
        $target->setPv($target->getPv() - $damage);
        $target->addStatusEffect(new BurningEffect(3, $this->getAtk(), 1));
        return $target->isDead() 
            ? "FLÈCHE ! $damage dégâts ! K.O. !"
            : "FLÈCHE ENFLAMMÉE ! $damage dég, brûlure imminente...";
    }

    public function heal($x = null): string {
        $oldPv = $this->pv;
        $this->setPv($this->pv + rand(12, 18));
        return "absorbe la chaleur ! +" . ($this->pv - $oldPv) . " PV";
    }

    public function inferno(Personnage $target): string {
        $this->setPv($this->pv - 20);
        $damage = $this->randomDamage(max(1, ($this->atk * 2) - $target->getDef()), 5);
        $target->setPv($target->getPv() - $damage);
        return $target->isDead() 
            ? "INFERNO ! $damage dégâts ! K.O. !"
            : "INFERNO ! $damage dég (-20 PV)";
    }
}