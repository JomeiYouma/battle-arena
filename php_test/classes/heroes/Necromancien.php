<?php
/**
 * NECROMANCIEN - Manipulateur d'âmes, copie les attaques et inflige des malédictions
 */
class Necromancien extends Personnage {
    
    private ?string $lastEnemyAction = null;
    private ?array $lastEnemyActionData = null;
    
    public function __construct($pv, $atk, $name, $def = 5, $speed = 10) {
        parent::__construct($pv, $atk, $name, $def, 'Necromancien', $speed);
    }
    
    public function setLastEnemyAction(string $actionKey, array $actionData): void {
        $this->lastEnemyAction = $actionKey;
        $this->lastEnemyActionData = $actionData;
    }
    
    public function getLastEnemyAction(): ?string {
        return $this->lastEnemyAction;
    }

    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => 'Attaque Sombre',
                'description' => 'Attaque de base (ignore 50% DEF)',
                'method' => 'attack',
                'needsTarget' => true,
                'target' => 'offensive',  // Attaque l'adversaire
                'emoji' => '🌑'
            ],
            'ordre_necrotique' => [
                'label' => 'Ordre Nécrotique',
                'description' => 'Intercepte et retourne l\'action de l\'ennemi',
                'pp' => 3,
                'method' => 'ordreNecrotique',
                'needsTarget' => true,
                'target' => 'adaptive',  // S'adapte selon l'action interceptée
                'emoji' => '👻'
            ],
            'chaines_rituel' => [
                'label' => 'Chaînes du Rituel',
                'description' => 'Echange 25% PV restants entre combattants',
                'pp' => 1,
                'method' => 'chainesRituel',
                'needsTarget' => true,
                'target' => 'offensive',  // Attaque l'adversaire (réciproque)
                'emoji' => '⛓️'
            ],
            'malediction' => [
                'label' => 'Malédiction',
                'description' => '5% PV max/tour (5 tours)',
                'pp' => 2,
                'method' => 'malediction',
                'needsTarget' => true,
                'target' => 'offensive',  // Attaque l'adversaire
                'emoji' => '💀'
            ],
            'manipulation_ame' => [
                'label' => 'Manipulation de l\'Âme',
                'description' => 'Echange ATK/DEF ennemi (2 tours)',
                'pp' => 2,
                'method' => 'manipulationAme',
                'needsTarget' => true,
                'target' => 'offensive',  // Affecte l'adversaire
                'emoji' => '🔄'
            ]
        ];
    }

    // Attaque de base - Ignore 50% DEF
    public function attack(Personnage $target): string {
        $baseDmg = $this->getAtk();
        $variance = $this->roll(-2, 4);
        $rawDmg = $baseDmg + $variance;
        $effectiveDef = (int) ($target->getDef() * 0.5);
        $finalDmg = max(1, $rawDmg - $effectiveDef);
        $target->receiveDamage($finalDmg, $this);
        return "lance une attaque sombre et inflige " . $finalDmg . " dégâts !";
    }

    /**
     * Détermine le type de cible d'une action ('offensive', 'defensive', ou 'adaptive')
     * Utilise d'abord la métadonnée 'target' si disponible, sinon utilise la classification par mots-clés
     * @return string 'offensive' (cible l'adversaire), 'defensive' (cible soi-même), ou 'adaptive' (s'adapte)
     */
    private function classifyActionTarget(string $actionKey, Personnage $target): string {
        // Récupérer les actions disponibles de l'ennemi
        $availableActions = $target->getAvailableActions();
        
        // Si la métadonnée 'target' existe, l'utiliser
        if (isset($availableActions[$actionKey]['target'])) {
            return $availableActions[$actionKey]['target'];
        }
        
        // Sinon, utiliser la classification par mots-clés
        $beneficialKeywords = ['heal', 'soin', 'buff', 'faveur', 'transe', 'fortif', 'jour', 'nouveau', 'shield', 'bouclier', 'regen', 'restoration'];
        $offensiveKeywords = ['attack', 'attaque', 'assaut', 'coup', 'frapp', 'lance', 'concoction', 'foudre', 'conseil', 'sentence', 'noeud', 'chaîne', 'rituel', 'curse', 'poison', 'brûl', 'paralys', 'gel', 'manipulation', 'échange', 'debuff', 'malédiction'];
        
        $lowerKey = strtolower($actionKey);
        
        foreach ($beneficialKeywords as $keyword) {
            if (strpos($lowerKey, strtolower($keyword)) !== false) {
                return 'defensive';
            }
        }
        
        foreach ($offensiveKeywords as $keyword) {
            if (strpos($lowerKey, strtolower($keyword)) !== false) {
                return 'offensive';
            }
        }
        
        return 'offensive'; // Par défaut, considérer comme offensif
    }

    /**
     * Ordre Nécrotique - Force l'ennemi à utiliser sa capacité contre lui-même ou pour vous
     * Logique basée sur la classification de l'action:
     * - Actions OFFENSIVES: les appliquer à l'ennemi (le forcer à se blesser)
     * - Actions DÉFENSIVES: les appliquer au Nécromancien (voler ses bénéfices)
     * - Actions ADAPTATIVES: déterminer intelligemment la meilleure cible
     */
    public function ordreNecrotique(Personnage $target): string {
        // Récupérer toutes les actions possibles de l'ennemi
        $availableActions = [];
        $baseActions = $target->getAvailableActions();
        
        foreach ($baseActions as $key => $action) {
            if ($key !== 'attack') { // Ignorer attaque de base pour plus d'intérêt
                $availableActions[$key] = $action;
            }
        }
        
        // Si aucune action spéciale, utiliser attaque
        if (empty($availableActions)) {
            $availableActions = ['attack' => $baseActions['attack'] ?? null];
        }
        
        $actionKeys = array_keys($availableActions);
        $selectedKey = $actionKeys[array_rand($actionKeys)];
        $action = $availableActions[$selectedKey];
        
        if (!$action || !method_exists($target, $action['method'])) {
            return "tente un Ordre Nécrotique mais l'invocation échoue...";
        }
        
        $method = $action['method'];
        $targetClassification = $this->classifyActionTarget($selectedKey, $target);
        
        try {
            $result = "";
            
            // Déterminer la cible appropriée selon la classification
            $actionTarget = $target; // Par défaut, l'ennemi
            
            if ($targetClassification === 'defensive') {
                // Action défensive : l'appliquer au Nécromancien
                $actionTarget = $this;
                $message = "invoque un Ordre Nécrotique ! Détourne " . $action['label'] . " pour votre bénéfice : ";
            } 
            else if ($targetClassification === 'offensive') {
                // Action offensive : la retourner contre l'ennemi
                $actionTarget = $target;
                $message = "invoque un Ordre Nécrotique ! Force l'ennemi à utiliser " . $action['label'] . " contre lui-même : ";
            }
            else { // 'adaptive'
                // Pour les actions adaptatives, déterminer la meilleure cible
                // Généralement, les appliquer contre l'ennemi si elles ont un effet négatif
                $actionTarget = $target;
                $message = "invoque un Ordre Nécrotique ! Retourne " . $action['label'] . " : ";
            }
            
            // Exécuter l'action avec la cible appropriée
            if ($action['needsTarget'] ?? false) {
                $result = $target->$method($actionTarget);
            } else {
                $result = $target->$method();
            }
            
            return $message . $result;
        } catch (Exception $e) {
            return "tente un Ordre Nécrotique mais l'invocation échoue mystérieusement (" . $e->getMessage() . ")...";
        }
    }

    // Chaînes du Rituel - Dégâts réciproques (25% PV)
    public function chainesRituel(Personnage $target): string {
        $dmgToEnemy = (int) ($this->getPv() * 0.25);
        $dmgToSelf = (int) ($target->getPv() * 0.25);
        $target->receiveDamage($dmgToEnemy);
        $this->receiveDamage($dmgToSelf);
        return "invoque les Chaînes du Rituel ! L'ennemi subit " . $dmgToEnemy . " dégâts et moi " . $dmgToSelf . " !";
    }

    // Malédiction - DoT 5% vie max pendant 5 tours
    public function malediction(Personnage $target): string {
        $dmgPerTurn = max(1, (int) ($target->getBasePv() * 0.05));
        $target->addStatusEffect(new CurseEffect(5, $dmgPerTurn));
        return "lance une Malédiction terrible ! " . $dmgPerTurn . " dégâts/tour pendant 5 tours !";
    }

    // Manipulation de l'Âme - Échange ATK/DEF pendant 2 tours
    public function manipulationAme(Personnage $target): string {
        $target->addStatusEffect(new StatSwapEffect(2));
        return "manipule l'âme de l'ennemi ! ATK et DEF échangées pendant 2 tours !";
    }
}
