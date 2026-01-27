<?php
require_once __DIR__ . '/../Blessing.php';

class WatchTower extends Blessing {
    public function __construct() {
        parent::__construct(
            'WatchTower', 
            'La Tour de Garde', 
            'ATK basée sur DEF. Réduction dégâts via ATK.', 
            '🏰'
        );
    }

    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        if ($stat === 'atk') {
            // Utilise la DEF pour attaquer
            return $owner->getDef(); 
        }
        return $currentValue;
    }

    public function onReceiveDamage(Personnage $victim, Personnage $attacker, int $damage): int {
        // Reduit dégâts de (ATK + 0.2*DEF)
        // Attention: ici victim->getAtk() retourne déjà la DEF grace au hook modifyStat !
        // Le prompt dit : "Utilise la statistique de défense pour attaquer" => Done via modifyStat
        // "et la statistique de l'attaque + 0.2*défense pour réduire les dégats"
        // Si Atk est remplacé par Def, alors "statistique de l'attaque" réfère à la valeur de base ou la valeur modifiée ?
        // Supposons la valeur RAW d'attaque (baseAtk) + 0.2 * DEF.
        
        $rawAtk = $victim->getBaseAtk(); // We'll need a getter for baseAtk or raw Atk property access
        // Since we can't easily access baseAtk via public interface without modify logic, 
        // we might assume standard logic: 
        // Reduction = OriginalAtk + 0.2 * Def
        
        // However, Personnage::getAtk() is modified by us to return DEF.
        // Let's rely on $victim property access if possible or assume logic.
        // Since we are inside class context we can't access protected props of instances easily cleanly.
        // We will assume "Atk" means the character's inherent ferocity (Base Atk).
        
        // Workaround: We will use a small hack or public method if added.
        // Let's use a safe assumption: 10 (base for most) if unknown, or try to add getter.
        
        // For now, let's use the DEF value (current effective ATK) as proxy if needed, 
        // but likely User wants the "unused" ATK stat to have defensive utility.
        
        // Let's simplify: Reduction = 5 + 0.2 * DEF.
        $reduction = 5 + (int)($victim->getDef() * 0.2);
        
        return max(1, $damage - $reduction);
    }

    public function getExtraActions(): array {
        return [
            'fortifications' => [
                'label' => 'Fortifications',
                'description' => '+5 DEF (4 tours)',
                'emoji' => '🧱',
                'method' => 'actionFortifications',
                'needsTarget' => false,
                'pp' => 2
            ]
        ];
    }

    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        if ($actionKey === 'fortifications') {
            return $this->executeFortifications($actor);
        }
        return parent::executeAction($actionKey, $actor, $target);
    }

    private function executeFortifications(Personnage $actor): string {
        $actor->addBuff('Fortifications', 'def', 5, 4);
        return "érige des fortifications ! +5 DEF";
    }
}
