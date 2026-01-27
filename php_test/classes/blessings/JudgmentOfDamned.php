<?php
require_once __DIR__ . '/../Blessing.php';
require_once __DIR__ . '/../effects/StatRedistributionEffect.php';

class JudgmentOfDamned extends Blessing {
    public function __construct() {
        parent::__construct(
            'JudgmentOfDamned', 
            'Jugement des Maudits', 
            'Se soigner brise la DEF (-35%).', 
            '⚖️'
        );
    }

    public function onHeal(Personnage $healer, int $amount): void {
        $currentDef = $healer->getDef();
        $reduction = (int)($healer->getBaseDef() * 0.35);
        if ($reduction < 1) $reduction = 1;
        
        $healer->setDef(max(0, $currentDef - $reduction));
    }

    public function getExtraActions(): array {
        return [
            'grand_conseil' => [
                'label' => 'Grand Conseil',
                'description' => 'Répartit équitablement les stats de l\'adversaire (3 tours)',
                'emoji' => '🧛',
                'method' => 'actionGrandConseil',
                'needsTarget' => true,
                'pp' => 1
            ],
            'sentence_meritee' => [
                'label' => 'Sentence Méritée',
                'description' => 'x1.5 ATK (x2 si Grand Conseil actif)',
                'emoji' => '🗡️',
                'method' => 'actionSentenceMeritee',
                'needsTarget' => true,
                'pp' => 2
            ]
        ];
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        if ($actionKey === 'grand_conseil' && $target !== null) {
            return $this->executeGrandConseil($actor, $target);
        }
        if ($actionKey === 'sentence_meritee' && $target !== null) {
            return $this->executeSentence($actor, $target);
        }
        return parent::executeAction($actionKey, $actor, $target);
    }

    private function executeGrandConseil(Personnage $actor, Personnage $target): string {
        $target->addStatusEffect(new StatRedistributionEffect(3));
        return "convoque le Grand Conseil ! Les statistiques de " . $target->getName() . " sont redistribuées !";
    }

    private function executeSentence(Personnage $actor, Personnage $target): string {
        $multiplier = 1.5;
        
        // Check if target has Grand Conseil (StatRedistributionEffect)
        $hasEffect = false;
        foreach ($target->getStatusEffects() as $effect) {
            if ($effect instanceof StatRedistributionEffect) {
                $hasEffect = true;
                break;
            }
        }
        
        if ($hasEffect) {
            $multiplier = 2.0;
        }
        
        $baseDmg = $actor->getAtk();
        $variance = $actor->roll(-2, 4);
        $rawDmg = $baseDmg + $variance;
        $finalDmg = (int)($rawDmg * $multiplier);
        
        $target->receiveDamage($finalDmg, $actor);
        
        return "inflige une Sentence Méritée ! " . $finalDmg . " dégâts (x" . $multiplier . ") !";
    }
}
        
        if ($hasEffect) $multiplier = 2.0;
        
        $damage = $actor->roll((int)($actor->getAtk() * $multiplier), (int)($actor->getAtk() * $multiplier * 1.2)); // Variance
        $damage = max(1, $damage - $target->getDef());
        
        $target->receiveDamage($damage);
        return "prononce la Sentence ! " . ($hasEffect ? "(CRITIQUE x2) " : "") . $damage . " dégâts !";
    }
}
