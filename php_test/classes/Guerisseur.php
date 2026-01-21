<?php
/**
 * =============================================================================
 * CLASSE GUERISSEUR - Spécialiste du soin et du support
 * =============================================================================
 * 
 * TODO [À RECODER PAR TOI-MÊME] :
 * - Ajouter un système de mana pour limiter les soins
 * - Implémenter des buffs pour les alliés (en multijoueur)
 * - Ajouter des compétences de résurrection
 * 
 * =============================================================================
 */

class Guerisseur extends Personnage {
    
    private $blessingActive = false;
    private $healAmount = 25;

    public function __construct($pv, $atk, $name, $def = 5) {
        parent::__construct($pv, $atk, $name, $def, "Guérisseur");
    }

    /**
     * Liste des actions disponibles pour le Guérisseur
     * TODO [À RECODER] : Ajoute des soins plus puissants, des résurrections, etc.
     */
    public function getAvailableActions(): array {
        return [
            'attack' => [
                'label' => '⚔️ Attaquer',
                'description' => 'Attaque faible avec le bâton sacré',
                'method' => 'attack',
                'needsTarget' => true
            ],
            'heal' => [
                'label' => '💚 Soigner',
                'description' => 'Restaure ' . $this->healAmount . ' PV instantanément',
                'method' => 'heal',
                'needsTarget' => false
            ],
            'bless' => [
                'label' => '✨ Bénédiction',
                'description' => 'Bénit soi-même, +5 DEF et soigne 10 PV',
                'method' => 'bless',
                'needsTarget' => false
            ]
        ];
    }

    /**
     * Soin - Restaure des PV
     * TODO [À RECODER] : Ajouter un coût en mana, améliorer avec le niveau
     */
    public function heal($x = null): string {
        $oldPv = $this->pv;
        
        if (is_null($x)) {
            $healValue = $this->healAmount;
            $this->setPv($this->pv + $healValue);
        } else {
            $healValue = $x;
            $this->setPv($this->pv + $x);
        }
        
        $actualHeal = $this->pv - $oldPv;
        return "invoque une lumière curative ! 💚 +" . $actualHeal . " PV (" . $this->pv . "/" . $this->basePv . ")";
    }

    /**
     * Bénédiction - Buff défensif + petit soin
     * TODO [À RECODER] : Faire durer plusieurs tours ou affecter les alliés
     */
    public function bless(): string {
        $this->def += 5;
        $oldPv = $this->pv;
        $this->setPv($this->pv + 10);
        $actualHeal = $this->pv - $oldPv;
        
        return "invoque une bénédiction divine ! ✨ DEF +5, PV +" . $actualHeal;
    }
}
