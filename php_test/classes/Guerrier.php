<?php
/**
 * GUERRIER - Force brute et défense
 * Thème: Rage et protection - Alterner attaques dévastatrices et défense solide
 */
class Guerrier extends Personnage {
    
    private $rageBonus = 10;
    private $shieldBonus = 15;

    public function __construct($pv, $atk, $name, $def = 10, $speed = 10) {
        parent::__construct($pv, $atk, $name, $def, "Guerrier", $speed);
    }

    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => 'Tranche',
                'emoji' => '⚔️',
                'description' => 'Attaque de base',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'rage' => [
                'label' => 'Rage',
                'emoji' => '🔥',
                'description' => '+' . $this->rageBonus . ' ATK pendant 2 tours',
                'method' => 'rage',
                'needsTarget' => false,
                'pp' => 3
            ],
            'shield' => [
                'label' => 'Levée de bouclier',
                'emoji' => '🛡️',
                'description' => '+' . $this->shieldBonus . ' DEF pendant 2 tours',
                'method' => 'shield',
                'needsTarget' => false,
                'pp' => 3
            ],
            'charge' => [
                'label' => 'Charge',
                'emoji' => '💥',
                'description' => '1.5x dégâts mais -5 DEF',
                'method' => 'charge',
                'needsTarget' => true,
                'pp' => 2
            ]
        ];
    }

    public function attack(Personnage $target): string {
        $baseDamage = max(1, $this->atk - $target->getDef() + 5);
        $damage = $this->randomDamage($baseDamage, 3);
        $target->setPv($target->getPv() - $damage);
        return $target->isDead() 
            ? "frappe violemment ! $damage dégâts ! K.O. !"
            : "frappe : $damage dégâts";
    }

    public function rage(): string {
        if (isset($this->activeBuffs['Rage'])) return "est déjà en rage !";
        $this->addBuff('Rage', 'atk', $this->rageBonus, 2);
        return "entre en RAGE ! +{$this->rageBonus} ATK";
    }

    public function shield(): string {
        if (isset($this->activeBuffs['Bouclier'])) return "bouclier déjà actif !";
        $this->addBuff('Bouclier', 'def', $this->shieldBonus, 2);
        return "lève son bouclier ! +{$this->shieldBonus} DEF";
    }

    public function charge(Personnage $target): string {
        $this->def = max(0, $this->def - 5);
        $baseDamage = max(1, (int)(($this->atk * 1.5) - $target->getDef()));
        $damage = $this->randomDamage($baseDamage, 4);
        $target->setPv($target->getPv() - $damage);
        return $target->isDead() 
            ? "CHARGE ! $damage dégâts ! K.O. !"
            : "CHARGE ! $damage dégâts !";
    }
}