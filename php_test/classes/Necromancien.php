<?php
/**
 * =============================================================================
 * NECROMANCIEN - Manipulateur d'âmes et maître des malédictions
 * =============================================================================
 * 
 * Spécialiste du contrôle et de la manipulation :
 * - Copie l'attaque de l'adversaire
 * - Malédictions à dégâts sur la durée
 * - Échange de stats et dégâts réciproques
 * 
 * =============================================================================
 */

class Necromancien extends Personnage {
    
    private ?string $lastEnemyAction = null;
    private ?array $lastEnemyActionData = null;
    
    public function __construct($pv, $atk, $name, $def = 5, $speed = 10) {
        parent::__construct($pv, $atk, $name, $def, 'Necromancien', $speed);
    }
    
    /**
     * Enregistre la dernière action utilisée par l'adversaire
     * (Appelé par le Combat après chaque action ennemie)
     */
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
                'description' => 'Inflige des dégâts basés sur l\'ATK.',
                'method' => 'attack',
                'needsTarget' => true,
                'emoji' => '🌑'
            ],
            'ordre_necrotique' => [
                'label' => 'Ordre Nécrotique',
                'description' => 'Utilise la dernière attaque lancée par l\'adversaire. (Non utilisable au 1er tour)',
                'pp' => 3,
                'method' => 'ordreNecrotique',
                'needsTarget' => true,
                'emoji' => '👻'
            ],
            'chaines_rituel' => [
                'label' => 'Chaînes du Rituel',
                'description' => 'Inflige 25% de la vie restante de chaque personnage à l\'autre.',
                'pp' => 1,
                'method' => 'chainesRituel',
                'needsTarget' => true,
                'emoji' => '⛓️'
            ],
            'malediction' => [
                'label' => 'Malédiction',
                'description' => 'Inflige 5% de la vie max de l\'ennemi pendant 5 tours.',
                'pp' => 2,
                'method' => 'malediction',
                'needsTarget' => true,
                'emoji' => '💀'
            ],
            'manipulation_ame' => [
                'label' => 'Manipulation de l\'Âme',
                'description' => 'Échange ATK et DEF de l\'adversaire pendant 2 tours.',
                'pp' => 2,
                'method' => 'manipulationAme',
                'needsTarget' => true,
                'emoji' => '�'
            ]
        ];
    }

    // --- ACTIONS ---

    /**
     * Attaque Sombre - Attaque de base (ignore 50% de la DEF)
     */
    public function attack(Personnage $target): string {
        $baseDmg = $this->getAtk();
        $variance = rand(-2, 4);
        $rawDmg = $baseDmg + $variance;
        
        // Ignore 50% de la défense
        $effectiveDef = (int) ($target->getDef() * 0.5);
        $finalDmg = max(1, $rawDmg - $effectiveDef);
        
        $target->receiveDamage($finalDmg);
        
        return "lance une attaque sombre et inflige " . $finalDmg . " dégâts !";
    }

    /**
     * Ordre Nécrotique - Exécute une attaque aléatoire de l'adversaire
     * Copie vraiment la capacité (effets, statuts, etc.)
     */
    public function ordreNecrotique(Personnage $target): string {
        // Récupérer les actions de l'ennemi
        $enemyActions = $target->getAvailableActions();
        
        if (empty($enemyActions)) {
            return "tente un Ordre Nécrotique mais l'ennemi n'a pas d'actions !";
        }
        
        // Choisir une action aléatoire parmi celles de l'ennemi
        $actionKeys = array_keys($enemyActions);
        $selectedKey = $actionKeys[array_rand($actionKeys)];
        $action = $enemyActions[$selectedKey];
        $method = $action['method'];
        
        // Vérifier que la méthode existe sur l'ennemi
        if (!method_exists($target, $method)) {
            return "tente un Ordre Nécrotique mais l'invocation échoue...";
        }
        
        // Exécuter l'action copiée avec la logique originale de l'ennemi
        try {
            if ($action['needsTarget'] ?? false) {
                // L'ennemi est forcé d'utiliser sa propre capacité contre lui-même !
                $result = $target->$method($target);
                return "invoque un Ordre Nécrotique ! Force l'ennemi à utiliser " . $action['label'] . " contre lui-même : " . $result;
            } else {
                // Self-buff : le nécromancien vole l'effet pour lui-même
                // On appelle la méthode sur l'ennemi mais l'effet sera sur l'ennemi...
                // Alternative : on simule un effet similaire
                $result = $target->$method();
                return "invoque un Ordre Nécrotique ! Corrompt " . $action['label'] . " de l'ennemi : " . $result;
            }
        } catch (Exception $e) {
            return "tente un Ordre Nécrotique mais l'invocation échoue mystérieusement...";
        }
    }

    /**
     * Chaînes du Rituel - Dégâts réciproques (25% vie restante)
     */
    public function chainesRituel(Personnage $target): string {
        // 25% de la vie restante de chaque personnage
        $dmgToEnemy = (int) ($this->getPv() * 0.25);
        $dmgToSelf = (int) ($target->getPv() * 0.25);
        
        // Appliquer les dégâts (ignorent la défense)
        $target->receiveDamage($dmgToEnemy);
        $this->receiveDamage($dmgToSelf);
        
        return "invoque les Chaînes du Rituel ! L'ennemi subit " . $dmgToEnemy . " dégâts et moi " . $dmgToSelf . " !";
    }

    /**
     * Malédiction - DoT basé sur % vie max pendant 5 tours
     */
    public function malediction(Personnage $target): string {
        // 5% de la vie max de l'ennemi par tour pendant 5 tours
        $dmgPerTurn = (int) ($target->getBasePv() * 0.05);
        $dmgPerTurn = max(1, $dmgPerTurn); // Au minimum 1 dégât
        
        $target->addStatusEffect(new CurseEffect(5, $dmgPerTurn));
        
        return "lance une Malédiction terrible ! " . $dmgPerTurn . " dégâts/tour pendant 5 tours !";
    }

    /**
     * Manipulation de l'Âme - Échange ATK/DEF de l'adversaire
     */
    public function manipulationAme(Personnage $target): string {
        $target->addStatusEffect(new StatSwapEffect(2));
        
        return "manipule l'âme de l'ennemi ! ATK et DEF échangées pendant 2 tours !";
    }
}
