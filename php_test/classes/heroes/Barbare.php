<?php
/**
 * BARBARE - Force brute et berserk
 * Thème: Plus dangereux quand blessé - Risque et récompense
 */
class Barbare extends Personnage {
    
    private $berserkThreshold = 0.3;
    private $berserkBonus = 15;
    private $warcryBonus = 8;

    public function __construct($pv, $atk, $name, $def = 3, $speed = 10) {
        parent::__construct($pv, $atk, $name, $def, "Barbare", $speed);
    }

    private function isBerserk(): bool {
        return ($this->pv / $this->basePv) <= $this->berserkThreshold;
    }

    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => 'Coup de hache',
                'emoji' => '🪓',
                'description' => 'Attaque de base (+15 si PV < 30%)',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'warcry' => [
                'label' => 'Cri de guerre',
                'emoji' => '📢',
                'description' => '+8 ATK pendant 3 tours',
                'method' => 'warcry',
                'needsTarget' => false,
                'pp' => 2
            ],
            'heal' => [
                'label' => 'Dévorer',
                'emoji' => '🍖',
                'description' => '+18-22 PV',
                'method' => 'heal',
                'needsTarget' => false,
                'pp' => 3
            ],
            'fury' => [
                'label' => 'Fureur',
                'emoji' => '💢',
                'description' => 'Inflige 2x ATK et perd 15 PV',
                'method' => 'fury',
                'needsTarget' => true,
                'pp' => 2
            ]
        ];
    }

    public function attack(Personnage $target): string {
        $bonusDamage = $this->isBerserk() ? $this->berserkBonus : 0;
        $berserkText = $this->isBerserk() ? " [BERSERK!]" : "";
        $baseDamage = max(1, $this->atk + $bonusDamage - $target->getDef());
        $damage = $this->randomDamage($baseDamage, 4);
        $target->receiveDamage($damage, $this);
        return $target->isDead() 
            ? "DÉCAPITE !$berserkText $damage dégâts ! K.O. !"
            : "frappe !$berserkText $damage dégâts";
    }

    public function warcry(): string {
        if (isset($this->activeBuffs['Cri de guerre'])) return "a déjà crié !";
        $this->addBuff('Cri de guerre', 'atk', $this->warcryBonus, 3);
        return "CRI DE GUERRE ! +{$this->warcryBonus} ATK";
    }

    public function heal($x = null): string {
        $oldPv = $this->pv;
        $amount = $this->roll(18, 22);
        $this->setPv($this->pv + $amount);
        $this->triggerHealHooks($amount);
        return "dévore de la viande ! +" . ($this->pv - $oldPv) . " PV";
    }

    public function fury(Personnage $target): string {
        $this->setPv($this->pv - 15);
        $baseDamage = max(1, $this->atk - $target->getDef());
        $damage1 = $this->randomDamage($baseDamage, 3);
        $damage2 = $this->randomDamage($baseDamage, 3);
        $total = $damage1 + $damage2;
        $target->receiveDamage($total, $this);
        return $target->isDead() 
            ? "FUREUR ! $total dégâts ! K.O. !"
            : "FUREUR ! $damage1+$damage2=$total dég (-15 PV)";
    }
}
