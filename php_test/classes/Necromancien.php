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
                'description' => 'Ennemi utilise une de ses attaques sur lui-même',
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
        $variance = rand(-2, 4);
        $rawDmg = $baseDmg + $variance;
        $effectiveDef = (int) ($target->getDef() * 0.5);
        $finalDmg = max(1, $rawDmg - $effectiveDef);
        $target->receiveDamage($finalDmg);
        return "lance une attaque sombre et inflige " . $finalDmg . " dégâts !";
    }

    // Ordre Nécrotique - Force l'ennemi à utiliser sa capacité contre lui-même
    public function ordreNecrotique(Personnage $target): string {
        $enemyActions = $target->getAvailableActions();
        
        if (empty($enemyActions)) {
            return "tente un Ordre Nécrotique mais l'ennemi n'a pas d'actions !";
        }
        
        $actionKeys = array_keys($enemyActions);
        $selectedKey = $actionKeys[array_rand($actionKeys)];
        $action = $enemyActions[$selectedKey];
        $method = $action['method'];
        
        if (!method_exists($target, $method)) {
            return "tente un Ordre Nécrotique mais l'invocation échoue...";
        }
        
        try {
            if ($action['needsTarget'] ?? false) {
                $result = $target->$method($target);
                return "invoque un Ordre Nécrotique ! Force l'ennemi à utiliser " . $action['label'] . " contre lui-même : " . $result;
            } else {
                $result = $target->$method();
                return "invoque un Ordre Nécrotique ! Corrompt " . $action['label'] . " de l'ennemi : " . $result;
            }
        } catch (Exception $e) {
            return "tente un Ordre Nécrotique mais l'invocation échoue mystérieusement...";
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
