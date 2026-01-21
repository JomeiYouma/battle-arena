<?php
/**
 * =============================================================================
 * CLASSE GUERRIER - Spécialiste du combat au corps à corps
 * =============================================================================
 * 
 * TODO [À RECODER PAR TOI-MÊME] :
 * - Ajouter un système de combo (plusieurs attaques d'affilée)
 * - Implémenter une jauge de rage qui augmente les dégâts
 * - Ajouter des attaques spéciales débloquables
 * 
 * =============================================================================
 */

class Guerrier extends Personnage {
    
    private $isBlocking = false;
    private $rageActive = false;
    private $rageBonus = 10;

    public function __construct($pv, $atk, $name, $def = 10) {
        // Le guerrier a une défense de base plus élevée
        parent::__construct($pv, $atk, $name, $def, "Guerrier");
    }

    /**
     * Liste des actions disponibles pour le Guerrier
     * TODO [À RECODER] : Ajoute tes propres compétences !
     */
    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => '⚔️ Attaquer',
                'description' => 'Attaque basique infligeant des dégâts basés sur l\'ATK',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'rage' => [
                'label' => '🔥 Rage',
                'description' => 'Entre en rage ! +' . $this->rageBonus . ' ATK pour la prochaine attaque',
                'method' => 'rage',
                'needsTarget' => false
            ],
            'shield' => [
                'label' => '🛡️ Bloquer',
                'description' => 'Adopte une posture défensive, +15 DEF ce tour',
                'method' => 'shield',
                'needsTarget' => false
            ]
        ];
    }

    /**
     * Attaque améliorée du guerrier - utilise le bonus de rage si actif
     */
    public function attack(Personnage $target): string {
        $originalAtk = $this->atk;
        
        // Applique le bonus de rage si actif
        if ($this->rageActive) {
            $this->atk += $this->rageBonus;
            $this->rageActive = false;
        }

        // Calcul des dégâts avec bonus de force
        // TODO [À RECODER] : Personnalise la formule de dégâts du guerrier
        $damage = max(1, $this->atk - $target->getDef() + 5); // Bonus de force +5
        $newPv = $target->getPv() - $damage;
        
        $target->setPv($newPv);

        // Restaure l'ATK original
        $this->atk = $originalAtk;

        if ($target->isDead()) {
            return "frappe violemment et inflige " . $damage . " dégâts ! " . $target->getName() . " est K.O. !";
        } else {
            return "frappe et inflige " . $damage . " dégâts à " . $target->getName() . " (" . $target->getPv() . " PV)";
        }
    }

    /**
     * Rage - Augmente l'attaque pour le prochain coup
     * TODO [À RECODER] : Tu peux faire durer la rage plusieurs tours
     */
    public function rage(): string {
        $this->rageActive = true;
        return "entre en RAGE ! 🔥 Prochaine attaque +" . $this->rageBonus . " dégâts !";
    }

    /**
     * Bouclier - Augmente la défense pour ce tour
     * TODO [À RECODER] : Faire en sorte que le buff dure ou ajouter une contre-attaque
     */
    public function shield(): string {
        $oldDef = $this->def;
        $this->def += 15;
        return "lève son bouclier ! 🛡️ DEF: " . $oldDef . " → " . $this->def;
    }
}