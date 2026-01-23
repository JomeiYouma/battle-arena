<?php
require_once __DIR__ . '/Combat.php';

class MultiCombat extends Combat {
    // Flag pour indiquer que c'est du multi (utilisé pour les icones)
    public $isMulti = true;
    
    /**
     * Charge une instance de MultiCombat depuis un fichier
     */
    public static function load($filepath) {
        if (!file_exists($filepath)) return null;
        $content = file_get_contents($filepath);
        $obj = unserialize($content);
        
        // Sécurité ou Cast si jamais on avait sérialisé un Combat simple par erreur
        if ($obj instanceof Combat && !($obj instanceof MultiCombat)) {
            // Conversion forcée (un peu hacky mais ça marche pour les propriétés identiques)
            $serialized = serialize($obj);
            $serialized = str_replace('O:6:"Combat"', 'O:11:"MultiCombat"', $serialized);
            $obj = unserialize($serialized);
        }
        
        return $obj;
    }
    
    /**
     * Factory pour créer un nouveau combat multi
     */
    public static function create($p1Data, $p2Data) {
        $p1 = self::createHeroFromData($p1Data);
        $p2 = self::createHeroFromData($p2Data);
        
        // P1 is "player", P2 is "enemy" in the internal logic structure
        // But for turn resolution, we treat them symmetrically
        return new MultiCombat($p1, $p2);
    }

    private static function createHeroFromData($data) {
        $type = $data['type'];
        if (class_exists($type)) {
            $hero = new $type($data['name']);
            
            // Hydratation des stats
             if (method_exists($hero, 'setPV')) $hero->setPV($data['pv']);
            if (method_exists($hero, 'setMaxPV')) $hero->setMaxPV($data['pv']);
            if (method_exists($hero, 'setAtk')) $hero->setAtk($data['atk']);
            if (method_exists($hero, 'setDef')) $hero->setDef($data['def']);
            if (method_exists($hero, 'setSpeed')) $hero->setSpeed($data['speed']);
            
            return $hero;
        }
        throw new Exception("Classe $type introuvable");
    }

    public function save($filepath) {
        file_put_contents($filepath, serialize($this));
    }

    /**
     * Résout un tour en utilisant les actions des deux joueurs
     */
    public function resolveMultiTurn($p1ActionKey, $p2ActionKey) {
        // Reset logs du tour précédent seulement si on commence un nouveau tour logique
        // Ici on résout TOUT le tour d'un coup.
        
        $this->turnActions = [];
        $this->captureInitialStates();
        $this->logs[] = "--- Tour " . $this->turn . " ---";
        
        // 1. Déterminer l'ordre
        [$first, $second] = $this->getOrderedFighters();
        $playerIsFirst = ($first === $this->player);
        
        // Actions associées aux combattants
        // Si first est player, son action est p1ActionKey
        $firstActionKey = $playerIsFirst ? $p1ActionKey : $p2ActionKey;
        $secondActionKey = $playerIsFirst ? $p2ActionKey : $p1ActionKey;

        // ===== PHASES EFFETS (Identique à Combat) =====
        if ($this->turn > 1) {
            $this->resolveDamageEffectsFor($first);
            if ($this->checkDeath($first)) return;
            
            $this->resolveDamageEffectsFor($second);
            if ($this->checkDeath($second)) return;
            
            $this->resolveStatEffectsFor($first);
            $this->processBuffsFor($first);
            
            $this->resolveStatEffectsFor($second);
            $this->processBuffsFor($second);
        }

        // ===== ACTION 1 =====
        // "performAction" gère les blocages et esquives interne
        $target1 = ($first === $this->player) ? $this->enemy : $this->player;
        $this->performAction($first, $target1, $firstActionKey);
        
        if ($this->checkDeath($target1)) return;

        // ===== ACTION 2 =====
        $target2 = ($second === $this->player) ? $this->enemy : $this->player;
        $this->performAction($second, $target2, $secondActionKey);
        
        if ($this->checkDeath($target2)) return;

        $this->turn++;
    }

    /**
     * Helper pour l'UI JSON
     */
    public function getStateForUser($sessionId, $metaData) {
        $isP1 = ($metaData['player1']['session'] === $sessionId);
        
        $myChar = $isP1 ? $this->player : $this->enemy;
        $oppChar = $isP1 ? $this->enemy : $this->player;
        
        // Renvoyer l'état
        return [
            'turn' => $this->turn,
            'isOver' => $this->isOver(),
            'winner' => $this->getWinner() ? ($this->getWinner() === $myChar ? 'you' : 'opponent') : null,
            'logs' => $this->logs,
            'turnActions' => $this->turnActions, // Important pour anims
            'me' => [
                'name' => $myChar->getName(),
                'type' => $myChar->getType(),
                'pv' => $myChar->getPv(),
                'max_pv' => $myChar->getBasePv(),
                'img' => $metaData[$isP1 ? 'player1' : 'player2']['hero']['images']['p1'] // Simplification: on prend l'image du JSON originel
            ],
            'opponent' => [
                'name' => $oppChar->getName(),
                'type' => $oppChar->getType(),
                'pv' => $oppChar->getPv(),
                'max_pv' => $oppChar->getBasePv(),
                'img' => $metaData[$isP1 ? 'player2' : 'player1']['hero']['images']['p2'] // Image P2 pour l'adversaire
            ]
        ];
    }
}
