<?php
/**
 * UTILITAIRE DE SÉLECTION
 * Fonctions partagées pour charger héros, bénédictions et actions
 * 
 * Usage:
 *   $heroes = getHeroesList();
 *   $blessings = getBlessingsList();
 */

// Détection du mode : BDD ou JSON
define('USE_DATABASE', file_exists(__DIR__ . '/../classes/Services/HeroManager.php'));

function getHeroesList() {
    static $heroesCache = null;
    if ($heroesCache !== null) return $heroesCache;
    
    if (USE_DATABASE) {
        // Mode BDD
        try {
            require_once __DIR__ . '/../classes/Services/HeroManager.php';
            $manager = new HeroManager();
            $heroModels = $manager->getAll();
            
            // Convertir les modèles Hero en tableaux pour compatibilité
            $heroesCache = array_map(function($hero) {
                return $hero->toArray();
            }, $heroModels);
        } catch (Exception $e) {
            // Fallback sur JSON en cas d'erreur BDD
            error_log("Erreur BDD, fallback JSON: " . $e->getMessage());
            $heroesCache = json_decode(file_get_contents(__DIR__ . '/../heros.json'), true);
        }
    } else {
        // Mode JSON (par défaut si BDD pas configurée)
        $heroesCache = json_decode(file_get_contents(__DIR__ . '/../heros.json'), true);
    }
    
    return $heroesCache;
}

function getBlessingsList() {
    static $blessingsCache = null;
    if ($blessingsCache !== null) return $blessingsCache;
    
    $blessingsCache = [];
    $blessingsDir = __DIR__ . '/../classes/blessings';
    
    // Mapping des noms de fichiers vers les images (peut être personnalisé)
    $imageMapping = [
        'WheelOfFortune' => 'wheel.png',
        'LoversCharm' => 'lovers.png',
        'JudgmentOfDamned' => 'judgment.png',
        'StrengthFavor' => 'strength.png',
        'MoonCall' => 'moon.png',
        'WatchTower' => 'tower.png',
        'RaChariot' => 'chariot.png',
        'HangedMan' => 'hanged.png'
    ];
    
    // Scanner tous les fichiers PHP dans le dossier blessings
    $files = glob($blessingsDir . '/*.php');
    
    foreach ($files as $file) {
        $className = basename($file, '.php');
        
        try {
            // Inclure le fichier de la classe
            require_once $file;
            
            // Instancier la bénédiction pour récupérer ses informations
            if (class_exists($className)) {
                $blessing = new $className();
                
                $blessingsCache[] = [
                    'id' => $className,
                    'name' => $blessing->getName(),
                    'img' => $imageMapping[$className] ?? 'default.png',
                    'desc' => $blessing->getDescription(),
                    'emoji' => $blessing->getEmoji()
                ];
            }
        } catch (Exception $e) {
            // Ignorer les blessings qui ne peuvent pas être chargées
            error_log("Impossible de charger la bénédiction $className: " . $e->getMessage());
        }
    }
    
    return $blessingsCache;
}

/**
 * Charge les actions d'un héros
 * @param array $heroData Données du héro (id, type, pv, atk, name, def, speed)
 * @return array Actions disponibles
 */
function getHeroActions($heroData) {
    try {
        $heroClass = $heroData['type'];
        $tempHero = new $heroClass(
            $heroData['pv'],
            $heroData['atk'],
            $heroData['name'],
            $heroData['def'] ?? 5,
            $heroData['speed'] ?? 10
        );
        return $tempHero->getAvailableActions();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Charge les actions d'une bénédiction
 * @param string $blessingId ID de la bénédiction
 * @return array Actions extra de la bénédiction
 */
function getBlessingActions($blessingId) {
    try {
        $blessingClass = $blessingId;
        $tempBlessing = new $blessingClass();
        return $tempBlessing->getExtraActions();
    } catch (Exception $e) {
        return [];
    }
}
?>
