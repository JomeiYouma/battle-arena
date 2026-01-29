<?php
/**
 * UTILITAIRE DE SÉLECTION
 * Fonctions partagées pour charger héros, bénédictions et actions
 * 
 * Usage:
 *   $heroes = getHeroesList();
 *   $blessings = getBlessingsList();
 */

function getHeroesList() {
    static $heroesCache = null;
    if ($heroesCache !== null) return $heroesCache;
    
    $heroesCache = json_decode(file_get_contents(__DIR__ . '/../heros.json'), true);
    return $heroesCache;
}

function getBlessingsList() {
    static $blessingsCache = null;
    if ($blessingsCache !== null) return $blessingsCache;
    
    $blessingsCache = [
        ['id' => 'WheelOfFortune', 'name' => 'Roue de Fortune', 'img' => 'wheel.png', 'desc' => 'Portée aléatoire doublée'],
        ['id' => 'LoversCharm', 'name' => 'Charmes Amoureux', 'img' => 'lovers.png', 'desc' => 'Renvoie 25% des dégâts reçus'],
        ['id' => 'JudgmentOfDamned', 'name' => 'Jugement des Maudits', 'img' => 'judgment.png', 'desc' => 'Soin baisse DEF. +2 Actions'],
        ['id' => 'StrengthFavor', 'name' => 'Faveur de Force', 'img' => 'strength.png', 'desc' => 'DEF -75%, ATK +33%'],
        ['id' => 'MoonCall', 'name' => 'Appel de la Lune', 'img' => 'moon.png', 'desc' => 'Cycle 4 tours : Boost stats, coût PP x2'],
        ['id' => 'WatchTower', 'name' => 'La Tour de Garde', 'img' => 'tower.png', 'desc' => 'ATK utilise DEF'],
        ['id' => 'RaChariot', 'name' => 'Chariot de Ra', 'img' => 'chariot.png', 'desc' => '+50% VIT. Bonus durées effets'],
        ['id' => 'HangedMan', 'name' => 'Corde du Pendu', 'img' => 'hanged.png', 'desc' => 'Mystérieux...']
    ];
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
