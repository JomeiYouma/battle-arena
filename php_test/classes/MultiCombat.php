<?php
require_once __DIR__ . '/Combat.php';

// Ensure autoloader is registered for unserialize() to work
if (!function_exists('chargerClasseMultiCombat')) {
    function chargerClasseMultiCombat($classe) {
        // Chercher dans classes/
        if (file_exists(__DIR__ . '/' . $classe . '.php')) {
            require_once __DIR__ . '/' . $classe . '.php';
            return;
        }
        // Chercher dans classes/effects/
        if (file_exists(__DIR__ . '/effects/' . $classe . '.php')) {
            require_once __DIR__ . '/effects/' . $classe . '.php';
            return;
        }
        // Chercher dans classes/blessings/
        if (file_exists(__DIR__ . '/blessings/' . $classe . '.php')) {
            require_once __DIR__ . '/blessings/' . $classe . '.php';
            return;
        }
        // Chercher dans classes/heroes/
        if (file_exists(__DIR__ . '/heroes/' . $classe . '.php')) {
            require_once __DIR__ . '/heroes/' . $classe . '.php';
            return;
        }
    }
    spl_autoload_register('chargerClasseMultiCombat');
}

class MultiCombat extends Combat {
    // Flag pour indiquer que c'est du multi (utilisé pour les icones)
    public $isMulti = true;
    
    /**
     * Charge une instance de MultiCombat depuis un fichier
     */
    public static function load($filepath) {
        if (!file_exists($filepath)) return null;
        
        try {
            $content = file_get_contents($filepath);
            if ($content === false) {
                error_log("MultiCombat::load - Impossible de lire le fichier: $filepath");
                return null;
            }
            
            // Vérifier que ce n'est pas du contenu vide ou invalide
            if (empty($content)) {
                error_log("MultiCombat::load - Fichier vide: $filepath");
                return null;
            }
            
            // Unserialize avec gestion d'erreur
            $obj = @unserialize($content);
            
            if ($obj === false) {
                error_log("MultiCombat::load - Erreur d'unserialize pour: $filepath");
                return null;
            }
            
            // Sécurité ou Cast si jamais on avait sérialisé un Combat simple par erreur
            if ($obj instanceof Combat && !($obj instanceof MultiCombat)) {
                // Conversion forcée (un peu hacky mais ça marche pour les propriétés identiques)
                $serialized = serialize($obj);
                $serialized = str_replace('O:6:"Combat"', 'O:11:"MultiCombat"', $serialized);
                $obj = @unserialize($serialized);
                
                if ($obj === false) {
                    error_log("MultiCombat::load - Erreur lors de la conversion de Combat vers MultiCombat: $filepath");
                    return null;
                }
            }
            
            return $obj;
        } catch (Exception $e) {
            error_log("MultiCombat::load - Exception: " . $e->getMessage() . " pour: $filepath");
            return null;
        }
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
        // Handle nested hero data structure if present (when passed from match meta where hero is sub-array)
        // But here specific usage seems to pass the hero array directly.
        // Wait, from api.php: create($meta['player1']['hero'], ...) 
        // But blessing_id is in $meta['player1']['blessing_id'].
        // API needs to pass the WHOLE player array to create, not just hero, OR pass blessing separately.
        
        // I will assume the caller (api.php) will be updated to pass the full player array including 'hero' and 'blessing_id'.
        // Let's enable support for both formats to be safe.
        
        $heroData = isset($data['hero']) ? $data['hero'] : $data;
        $blessingId = $data['blessing_id'] ?? null;
        
        $type = $heroData['type'];
        
        if (class_exists($type)) {
            // Passer tous les paramètres nécessaires au constructeur
            $hero = new $type(
                $heroData['pv'],              // pv
                $heroData['atk'],             // atk
                $heroData['name'],            // name
                $heroData['def'] ?? 5,        // def (défaut 5)
                $heroData['speed'] ?? 10      // speed (défaut 10)
            );
            
            // Apply Blessing if present
            if ($blessingId) {
                // Determine class name from ID (e.g. WheelOfFortune)
                // We trust ID matches class name exactly as we set in index.php
                $blessingClass = $blessingId;
                $blessingPath = __DIR__ . '/blessings/' . $blessingClass . '.php';
                if (file_exists($blessingPath)) {
                     require_once $blessingPath;
                     if (class_exists($blessingClass)) {
                         $hero->addBlessing(new $blessingClass());
                     }
                }
            }
            
            return $hero;
        }
        throw new Exception("Classe $type introuvable");
    }

    public function save($filepath) {
        try {
            $dir = dirname($filepath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $result = file_put_contents($filepath, serialize($this));
            if ($result === false) {
                error_log("MultiCombat::save - Impossible d'écrire le fichier: $filepath");
                return false;
            }
            return true;
        } catch (Exception $e) {
            error_log("MultiCombat::save - Exception: " . $e->getMessage() . " pour: $filepath");
            return false;
        }
    }

    /**
     * Retourne l'identifiant du gagnant ('p1' ou 'p2') pour les stats
     */
    public function getWinnerId() {
        $winner = $this->getWinner();
        if ($winner === null) {
            return null;
        }
        // player = P1, enemy = P2 dans la structure interne
        return ($winner === $this->player) ? 'p1' : 'p2';
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
        
        // Récupérer les actions disponibles pour le joueur
        $availableActions = [];
        $actions = [];
        
        try {
            if (method_exists($myChar, 'getAllActions')) {
                $availableActions = $myChar->getAllActions();
                
                // Ajouter les statuts du PP pour chaque action
                foreach ($availableActions as $key => $action) {
                    $ppText = '';
                    $canUse = true;
                    
                    if (method_exists($myChar, 'getPPText')) {
                        $ppText = $myChar->getPPText($key);
                    }
                    if (method_exists($myChar, 'canUseAction')) {
                        $canUse = $myChar->canUseAction($key);
                    }
                    
                    $actions[$key] = array_merge($action, [
                        'ppText' => $ppText,
                        'canUse' => $canUse
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Error getting available actions: " . $e->getMessage());
        }
        
        // Récupérer les effets actifs
        $myEffects = [];
        $oppEffects = [];
        
        try {
            if (method_exists($myChar, 'getActiveEffects')) {
                $myEffects = $myChar->getActiveEffects();
            }
            if (method_exists($oppChar, 'getActiveEffects')) {
                $oppEffects = $oppChar->getActiveEffects();
            }
        } catch (Exception $e) {
            error_log("Error getting active effects: " . $e->getMessage());
        }
        
        // Renvoyer l'état
        return [
            'turn' => $this->turn,
            'isOver' => $this->isOver(),
            'winner' => $this->getWinner() ? ($this->getWinner() === $myChar ? 'you' : 'opponent') : null,
            'logs' => $this->logs,
            'turnActions' => $this->turnActions, // Important pour anims
            'me' => [
                'name' => $metaData[$isP1 ? 'player1' : 'player2']['display_name'] ?? $myChar->getName(),
                'type' => $myChar->getType(),
                'pv' => $myChar->getPv(),
                'max_pv' => $myChar->getBasePv(),
                'atk' => $myChar->getAtk(),
                'def' => $myChar->getDef(),
                'speed' => $myChar->getSpeed(),
                'img' => $metaData[$isP1 ? 'player1' : 'player2']['hero']['images']['p1'],
                'activeEffects' => $myEffects
            ],
            'opponent' => [
                'name' => $metaData[$isP1 ? 'player2' : 'player1']['display_name'] ?? $oppChar->getName(),
                'type' => $oppChar->getType(),
                'pv' => $oppChar->getPv(),
                'max_pv' => $oppChar->getBasePv(),
                'atk' => $oppChar->getAtk(),
                'def' => $oppChar->getDef(),
                'speed' => $oppChar->getSpeed(),
                'img' => $metaData[$isP1 ? 'player2' : 'player1']['hero']['images']['p2'],
                'activeEffects' => $oppEffects
            ],
            'actions' => $actions
        ];
    }
}
