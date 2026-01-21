<?php
/**
 * =============================================================================
 * CLASSE PERSONNAGE - Classe de base pour tous les personnages
 * =============================================================================
 * 
 * TODO [À RECODER PAR TOI-MÊME] :
 * - Ajouter d'autres stats (vitesse, chance critique, résistance magique, etc.)
 * - Implémenter un système de niveau et d'expérience
 * - Ajouter un système d'équipement qui modifie les stats
 * 
 * =============================================================================
 */

abstract class Personnage {
    const MAX_PV = 150;

    protected static $nbPersonnages = 0;

    // --- ATTRIBUTS ---
    protected $pv;
    protected $atk;
    protected $def;      // NOUVEAU: Défense - réduit les dégâts reçus
    protected $name;
    protected $basePv;
    protected $type;     // NOUVEAU: Type de personnage pour affichage
    protected $isEvading = false; // NOUVEAU: État d'esquive actif

    // --- CONSTRUCTEUR ---
    public function __construct($pv, $atk, $name, $def = 5, $type = "Personnage") {
        self::$nbPersonnages++;
        $this->name = $name;
        $this->atk = $atk;
        $this->def = $def;
        $this->basePv = $pv;
        $this->type = $type;
        $this->setPv($pv);
    }

    // --- MÉTHODE STATIQUE ---
    public static function getNbPersonnages() {
        return self::$nbPersonnages;
    }

    // --- GETTERS ---
    public function getPv() {
        return $this->pv;
    }

    public function getAtk() {
        return $this->atk;
    }

    public function getDef() {
        return $this->def;
    }

    public function getName() {
        return $this->name;
    }

    public function getBasePv() {
        return $this->basePv;
    }

    public function getType() {
        return $this->type;
    }

    public function isEvading(): bool {
        return $this->isEvading;
    }

    // --- SETTERS ---
    public function setAtk($atk) {
        if ($atk > 0) {
            $this->atk = $atk;
        }
    }

    public function setDef($def) {
        if ($def >= 0) {
            $this->def = $def;
        }
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setBasePv($x) {
        if ($x > self::MAX_PV) {
            $x = self::MAX_PV;
        }
        if ($x > 0) {
            $this->basePv = $x;
        }
    }

    public function setPv($x) {
        if ($x > self::MAX_PV) {
            $x = self::MAX_PV;
        }
        if ($x < 0) {
            $x = 0;
        }
        if ($x > $this->basePv) {
            $x = $this->basePv;
        }
        $this->pv = $x;
    }

    public function setEvading(bool $value): void {
        $this->isEvading = $value;
    }

    // --- MÉTHODES DE BASE ---
    public function cri() {
        return "YOU SHALL NOT PASS !";
    }

    public function isDead() {
        return $this->pv <= 0;
    }

    /**
     * Attaque de base - Inflige des dégâts réduits par la défense de la cible
     * TODO [À RECODER] : Ajouter des chances de critique, des éléments, etc.
     */
    public function attack(Personnage $target): string {
        // Calcul des dégâts avec réduction de défense
        // TODO [À RECODER] : Tu peux modifier cette formule
        $damage = max(1, $this->atk - $target->getDef());
        $newPv = $target->getPv() - $damage;
        
        $target->setPv($newPv);

        if ($target->isDead()) {
            return "inflige " . $damage . " dégâts ! " . $target->getName() . " est K.O. !";
        } else {
            return "inflige " . $damage . " dégâts à " . $target->getName() . " (" . $target->getPv() . " PV restants)";
        }
    }

    /**
     * MÉTHODE ABSTRAITE : Chaque classe enfant DOIT définir ses actions
     * TODO [À RECODER] : Personnalise les actions pour chaque classe
     * 
     * Format attendu :
     * [
     *     'action_key' => [
     *         'label' => 'Nom affiché',
     *         'description' => 'Description pour l\'infobulle',
     *         'method' => 'nom_de_la_methode',
     *         'needsTarget' => true/false
     *     ]
     * ]
     */
    abstract public function getAvailableActions(): array;

    /**
     * Retourne la description complète du personnage pour les infobulles
     */
    public function getTooltipDescription(): string {
        $actions = $this->getAvailableActions();
        $desc = "📊 Stats:\n";
        $desc .= "❤️ PV: " . $this->pv . "/" . $this->basePv . "\n";
        $desc .= "⚔️ ATK: " . $this->atk . "\n";
        $desc .= "🛡️ DEF: " . $this->def . "\n\n";
        $desc .= "🎯 Compétences:\n";
        
        foreach ($actions as $action) {
            $desc .= "• " . $action['label'] . ": " . ($action['description'] ?? 'Aucune description') . "\n";
        }
        
        return $desc;
    }
}
