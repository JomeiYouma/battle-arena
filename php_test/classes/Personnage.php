<?php
/**
 * =============================================================================
 * CLASSE PERSONNAGE - Classe de base pour tous les personnages
 * =============================================================================
 * 
 * Système de buffs temporaires, effets retardés, et PP (Power Points)
 * 
 * =============================================================================
 */

abstract class Personnage {
    const MAX_PV = 150;
    const MAX_DEF = 40;  // Cap de défense

    protected static $nbPersonnages = 0;

    protected $pv;
    protected $atk;
    protected $def;
    protected $speed;  // Vitesse - détermine l'ordre d'action
    protected $name;
    protected $basePv;
    protected $baseAtk;  // ATK de base pour les buffs
    protected $baseDef;  // DEF de base pour les buffs
    protected $type;
    protected $isEvading = false;

    // --- SYSTÈME DE PP (Power Points) ---
    // Format: ['action_key' => ['current' => X, 'max' => Y]]
    protected $pp = [];

    // --- SYSTÈME DE BUFFS TEMPORAIRES ---
    // Format: ['buff_name' => ['value' => X, 'duration' => Y, 'stat' => 'atk'|'def']]
    protected $activeBuffs = [];

    // --- SYSTÈME D'EFFETS RETARDÉS ---
    // Format: ['effect_name' => ['turnsDelay' => X, 'duration' => Y, 'damage' => Z, 'emoji' => '🔥']]
    protected $pendingEffects = [];

    // --- EFFETS ACTIFS (brûlure, saignement, etc.) ---
    // Format: ['effect_name' => ['duration' => X, 'damage' => Y, 'emoji' => '🔥']]
    protected $activeEffects = [];

    // --- CONSTRUCTEUR ---
    public function __construct($pv, $atk, $name, $def = 5, $type = "Personnage", $speed = 10) {
        self::$nbPersonnages++;
        $this->name = $name;
        $this->atk = $atk;
        $this->baseAtk = $atk;
        $this->def = min($def, self::MAX_DEF);
        $this->baseDef = min($def, self::MAX_DEF);
        $this->speed = $speed;
        $this->basePv = $pv;
        $this->type = $type;
        $this->setPv($pv);
        $this->initializePP();  // Initialise les PP selon les actions
    }

    /**
     * Initialise les PP pour chaque action (appelé après construction)
     */
    protected function initializePP(): void {
        $actions = $this->getAvailableActions();
        foreach ($actions as $key => $action) {
            if (isset($action['pp'])) {
                $this->pp[$key] = [
                    'current' => $action['pp'],
                    'max' => $action['pp']
                ];
            }
        }
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

    public function getSpeed(): int {
        return $this->speed;
    }

    // --- SETTERS ---
    public function setAtk($atk) {
        if ($atk > 0) {
            $this->atk = $atk;
        }
    }

    public function setDef($def) {
        if ($def >= 0) {
            $this->def = min($def, self::MAX_DEF);  // Cap à MAX_DEF
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
     * Dégâts aléatoires dans une fourchette
     */
    public function attack(Personnage $target): string {
        // Dégâts aléatoires (+/- 2 de la valeur de base)
        $baseDamage = max(1, $this->atk - $target->getDef());
        $damage = $baseDamage + rand(-2, 2);
        $damage = max(1, $damage);  // Minimum 1 dégât
        
        $target->setPv($target->getPv() - $damage);

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

    // ==========================================================================
    // SYSTÈME DE BUFFS TEMPORAIRES
    // ==========================================================================

    /**
     * Ajoute un buff temporaire (ex: +10 ATK pendant 2 tours)
     */
    public function addBuff(string $name, string $stat, int $value, int $duration): void {
        $this->activeBuffs[$name] = [
            'stat' => $stat,
            'value' => $value,
            'duration' => $duration
        ];
        
        // Applique immédiatement le buff
        if ($stat === 'atk') {
            $this->atk += $value;
        } elseif ($stat === 'def') {
            $this->setDef($this->def + $value);
        }
    }

    /**
     * Décrémente la durée des buffs et retire ceux expirés
     * @return array Logs des buffs expirés
     */
    public function decrementBuffDurations(): array {
        $logs = [];
        
        foreach ($this->activeBuffs as $name => $buff) {
            $this->activeBuffs[$name]['duration']--;
            
            if ($this->activeBuffs[$name]['duration'] <= 0) {
                // Retire le buff
                if ($buff['stat'] === 'atk') {
                    $this->atk -= $buff['value'];
                } elseif ($buff['stat'] === 'def') {
                    $this->def = max(0, $this->def - $buff['value']);
                }
                
                $logs[] = "⏰ Le buff " . $name . " de " . $this->name . " a expiré !";
                unset($this->activeBuffs[$name]);
            }
        }
        
        return $logs;
    }

    /**
     * Retourne les buffs actifs
     */
    public function getActiveBuffs(): array {
        return $this->activeBuffs;
    }

    // ==========================================================================
    // SYSTÈME D'EFFETS RETARDÉS (Ex: Flèche enflammée)
    // ==========================================================================

    /**
     * Ajoute un effet retardé sur la cible (ex: brûlure qui commence dans X tours)
     */
    public function addPendingEffect(string $name, int $turnsDelay, int $duration, int $damage, string $emoji): void {
        $this->pendingEffects[$name] = [
            'turnsDelay' => $turnsDelay,
            'duration' => $duration,
            'damage' => $damage,
            'emoji' => $emoji
        ];
    }

    /**
     * Résout les effets en attente et les effets actifs
     * @return array ['logs' => [...], 'emojis' => [...]]
     */
    public function resolveEffects(): array {
        $logs = [];
        $emojis = [];

        // 1. Vérifier les effets en attente
        foreach ($this->pendingEffects as $name => $effect) {
            $this->pendingEffects[$name]['turnsDelay']--;
            
            if ($this->pendingEffects[$name]['turnsDelay'] <= 0) {
                // L'effet s'active !
                $this->activeEffects[$name] = [
                    'duration' => $effect['duration'],
                    'damage' => $effect['damage'],
                    'emoji' => $effect['emoji']
                ];
                $emojis[] = $effect['emoji'];
                $logs[] = "💥 " . $name . " s'abat sur " . $this->name . " !";
                unset($this->pendingEffects[$name]);
            }
        }

        // 2. Appliquer les effets actifs (brûlure, poison, etc.)
        foreach ($this->activeEffects as $name => $effect) {
            $this->setPv($this->pv - $effect['damage']);
            $emojis[] = $effect['emoji'];
            $logs[] = $effect['emoji'] . " " . $this->name . " subit " . $effect['damage'] . " dégâts de " . $name . " ! (" . $this->pv . " PV)";
            
            $this->activeEffects[$name]['duration']--;
            if ($this->activeEffects[$name]['duration'] <= 0) {
                $logs[] = "✨ L'effet " . $name . " sur " . $this->name . " s'est dissipé.";
                unset($this->activeEffects[$name]);
            }
        }

        return ['logs' => $logs, 'emojis' => $emojis];
    }

    /**
     * Retourne les effets actifs
     */
    public function getActiveEffects(): array {
        return $this->activeEffects;
    }

    /**
     * Retourne les effets en attente
     */
    public function getPendingEffects(): array {
        return $this->pendingEffects;
    }

    /**
     * Calcule les dégâts aléatoires dans une fourchette
     */
    protected function randomDamage(int $base, int $variance = 2): int {
        return max(1, $base + rand(-$variance, $variance));
    }

    // ==========================================================================
    // SYSTÈME DE PP (Power Points)
    // ==========================================================================

    /**
     * Retourne tous les PP
     */
    public function getPP(): array {
        return $this->pp;
    }

    /**
     * Retourne les PP pour une action spécifique
     */
    public function getPPForAction(string $actionKey): ?array {
        return $this->pp[$actionKey] ?? null;
    }

    /**
     * Vérifie si une action peut être utilisée (assez de PP)
     */
    public function canUseAction(string $actionKey): bool {
        // L'attaque de base n'a pas de PP (illimité)
        if (!isset($this->pp[$actionKey])) {
            return true;
        }
        return $this->pp[$actionKey]['current'] > 0;
    }

    /**
     * Utilise un PP pour une action
     * @return bool True si PP utilisé avec succès
     */
    public function usePP(string $actionKey): bool {
        if (!isset($this->pp[$actionKey])) {
            return true; // Pas de PP = illimité
        }
        
        if ($this->pp[$actionKey]['current'] <= 0) {
            return false;
        }
        
        $this->pp[$actionKey]['current']--;
        return true;
    }

    /**
     * Retourne le texte formaté des PP pour une action
     */
    public function getPPText(string $actionKey): string {
        if (!isset($this->pp[$actionKey])) {
            return "∞"; // Illimité
        }
        return $this->pp[$actionKey]['current'] . "/" . $this->pp[$actionKey]['max'];
    }
}

