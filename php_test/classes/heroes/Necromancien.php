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
                'emoji' => '🌑'
            ],
            'ordre_necrotique' => [
                'label' => 'Ordre Nécrotique',
                'description' => 'Intercepte et retourne l\'action de l\'ennemi',
                'pp' => 3,
                'method' => 'ordreNecrotique',
                'needsTarget' => true,
                'emoji' => '👻'
            ],
            'chaines_rituel' => [
                'label' => 'Chaînes du Rituel',
                'description' => 'Echange 25% PV restants entre combattants',
                'pp' => 1,
                'method' => 'chainesRituel',
                'needsTarget' => true,
                'emoji' => '⛓️'
            ],
            'malediction' => [
                'label' => 'Malédiction',
                'description' => '5% PV max/tour (5 tours)',
                'pp' => 2,
                'method' => 'malediction',
                'needsTarget' => true,
                'emoji' => '💀'
            ],
            'manipulation_ame' => [
                'label' => 'Manipulation de l\'Âme',
                'description' => 'Echange ATK/DEF ennemi (2 tours)',
                'pp' => 2,
                'method' => 'manipulationAme',
                'needsTarget' => true,
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
     * Classe une action comme bénéfique, attaque, ou néfaste
     * @return string 'beneficial', 'attack', 'harmful', ou 'neutral'
     */
    private function classifyAction(string $actionKey, Personnage $target): string {
        $beneficialKeywords = ['heal', 'soin', 'buff', 'faveur', 'transe', 'fortif', 'jour', 'nouveau'];
        $attackKeywords = ['attack', 'attaque', 'assaut', 'coup', 'frapp', 'lance', 'concoction', 'foudre', 'conseil', 'sentence', 'noeud', 'chaîne', 'rituel'];
        $harmfulKeywords = ['malediction', 'curse', 'poison', 'brûl', 'paralys', 'gel', 'manipulation', 'échange', 'debuff'];
        
        $lowerKey = strtolower($actionKey);
        
        foreach ($beneficialKeywords as $keyword) {
            if (strpos($lowerKey, strtolower($keyword)) !== false) {
                return 'beneficial';
            }
        }
        
        foreach ($harmfulKeywords as $keyword) {
            if (strpos($lowerKey, strtolower($keyword)) !== false) {
                return 'harmful';
            }
        }
        
        foreach ($attackKeywords as $keyword) {
            if (strpos($lowerKey, strtolower($keyword)) !== false) {
                return 'attack';
            }
        }
        
        return 'neutral';
    }

    /**
     * Ordre Nécrotique - Force l'ennemi à utiliser sa capacité contre lui-même
     * Logique:
     * - Actions bénéfiques: les appliquer au Nécromancien
     * - Attaques: les appliquer à l'ennemi
     * - Actions néfastes: les appliquer à l'ennemi
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
        $classification = $this->classifyAction($selectedKey, $target);
        
        try {
            $result = "";
            
            if ($classification === 'beneficial') {
                // Action bénéfique : l'appliquer à soi-même
                if ($action['needsTarget'] ?? false) {
                    $result = $target->$method($this); // Applique l'action bénéfique au Nécromancien
                } else {
                    $result = $target->$method(); // Applique sans cible
                }
                return "invoque un Ordre Nécrotique ! Détourne " . $action['label'] . " pour soi : " . $result;
            }
            else if ($classification === 'attack' || $classification === 'neutral') {
                // Attaque : l'appliquer à l'ennemi
                if ($action['needsTarget'] ?? false) {
                    $result = $target->$method($target); // Force l'ennemi à attaquer lui-même
                } else {
                    $result = $target->$method();
                }
                return "invoque un Ordre Nécrotique ! Force l'ennemi à utiliser " . $action['label'] . " contre lui-même : " . $result;
            }
            else { // harmful
                // Action néfaste : l'appliquer à l'ennemi
                if ($action['needsTarget'] ?? false) {
                    $result = $target->$method($target); // Force l'ennemi à la subir
                } else {
                    $result = $target->$method();
                }
                return "invoque un Ordre Nécrotique ! Retourne " . $action['label'] . " contre l'ennemi : " . $result;
            }
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
