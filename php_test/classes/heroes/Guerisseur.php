<?php
/**
 * GUÉRISSEUR - Support et dégâts sacrés
 * Thème: Énergie divine pour soigner et ignorer les défenses
 */
class Guerisseur extends Personnage {
    
    private $healAmount = 25;
    private $blessDefBonus = 5;

    public function __construct($pv, $atk, $name, $def = 5, $speed = 10) {
        parent::__construct($pv, $atk, $name, $def, "Guérisseur", $speed);
    }

    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => 'Rayon psychique',
                'emoji' => '✨',
                'description' => 'Attaque de base à distance',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'heal' => [
                'label' => 'Soigner',
                'emoji' => '💚',
                'description' => '+22-28 PV',
                'method' => 'heal',
                'needsTarget' => false,
                'pp' => 5
            ],
            'bless' => [
                'label' => 'Bénédiction',
                'emoji' => '🙏',
                'description' => '+5 DEF (3 tours) et +10 PV',
                'method' => 'bless',
                'needsTarget' => false,
                'pp' => 3
            ],
            'smite' => [
                'label' => 'Châtiment',
                'emoji' => '⚡',
                'description' => 'Ignore DEF adverse',
                'method' => 'smite',
                'needsTarget' => true,
                'pp' => 2
            ],
            'barrier' => [
                'label' => 'Barrière',
                'emoji' => '🔮',
                'description' => '+25 DEF pendant 1 tour',
                'method' => 'barrier',
                'needsTarget' => false,
                'pp' => 1
            ]
        ];
    }

    public function attack(Personnage $target): string {
        $damage = $this->randomDamage(max(1, $this->atk - $target->getDef()), 2);
        $target->receiveDamage($damage, $this);
        return $target->isDead() ? "frappe ! $damage dégâts ! K.O. !" : "frappe : $damage dégâts";
    }

    public function heal($x = null): string {
        $oldPv = $this->pv;
        $amount = $this->roll(22, 28);
        $this->setPv($this->pv + $amount);
        $this->triggerHealHooks($amount);
        return "se soigne ! +" . ($this->pv - $oldPv) . " PV";
    }

    public function bless(): string {
        // Vérifier s'il y a déjà un DefenseBoostEffect actif
        $hasBlessing = false;
        foreach ($this->statusEffects as $effect) {
            if ($effect instanceof DefenseBoostEffect) {
                $hasBlessing = true;
                break;
            }
        }
        
        if ($hasBlessing) {
            $this->setPv($this->pv + 10);
            $this->triggerHealHooks(10);
            return "renouvelle sa bénédiction !";
        }
        
        $this->addStatusEffect(new DefenseBoostEffect(3, $this->blessDefBonus));
        $this->setPv($this->pv + 10);
        $this->triggerHealHooks(10);
        return "invoque une bénédiction !";
    }

    public function smite(Personnage $target): string {
        $damage = $this->randomDamage($this->atk + 5, 3);
        $target->receiveDamage($damage, $this);
        return $target->isDead() ? "CHÂTIMENT ! $damage dégâts purs ! K.O. !" : "CHÂTIMENT ! $damage dég (ignore DEF)";
    }

    public function barrier(): string {
        // Vérifier s'il y a déjà un DefenseBoostEffect actif
        foreach ($this->statusEffects as $effect) {
            if ($effect instanceof DefenseBoostEffect) {
                return "barrière déjà active !";
            }
        }
        $this->addStatusEffect(new DefenseBoostEffect(1, 25));
        return "crée une BARRIÈRE ! +25 DEF";
    }
}
