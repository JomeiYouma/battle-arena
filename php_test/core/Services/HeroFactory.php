<?php
/** HEROFACTORY - Convertit Hero (BDD) en Personnage (jeu) */

require_once __DIR__ . '/../Models/Hero.php';

class HeroFactory {
    
    public static function createFromModel(Hero $heroModel): Personnage {
        $className = $heroModel->getType();
        
        // Vérifier que la classe existe
        $classFile = __DIR__ . '/../heroes/' . $className . '.php';
        if (!file_exists($classFile)) {
            throw new Exception("Classe de héros non trouvée: $className");
        }
        
        require_once $classFile;
        
        if (!class_exists($className)) {
            throw new Exception("Classe $className n'existe pas");
        }
        
        // Instancier avec les paramètres
        return new $className(
            $heroModel->getPv(),
            $heroModel->getAtk(),
            $heroModel->getName(),
            $heroModel->getDef(),
            $heroModel->getSpeed()
        );
    }
    
    /**
     * Crée depuis un tableau (pour compatibilité JSON)
     */
    public static function createFromArray(array $data): Personnage {
        $className = $data['type'];
        
        $classFile = __DIR__ . '/../heroes/' . $className . '.php';
        if (!file_exists($classFile)) {
            throw new Exception("Classe de héros non trouvée: $className");
        }
        
        require_once $classFile;
        
        return new $className(
            $data['pv'],
            $data['atk'],
            $data['name'],
            $data['def'] ?? 5,
            $data['speed'] ?? 10
        );
    }
}
