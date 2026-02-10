<?php
/** PERSONNAGE - Classe abstraite de base pour les héros */

require_once __DIR__ . '/StatusEffect.php';
require_once __DIR__ . '/Blessing.php';
require_once __DIR__ . '/effects/DestinyLinkEffect.php';

abstract class Personnage {
    const MAX_PV = 250;
    const MAX_DEF = 50;  // Cap de défense

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
    protected $pp = [];

    // --- SYSTÈME DE BUFFS TEMPORAIRES ---
    protected $activeBuffs = [];

    // --- NOUVEAU SYSTÈME D'EFFETS DE STATUT (POO) ---
    /** @var StatusEffect[] */
    protected array $statusEffects = [];


    // --- SYSTÈME DE BÉNÉDICTIONS ---
    /** @var Blessing[] */
    protected array $blessings = [];
    
    // --- RÉFÉRENCE À L'ADVERSAIRE ACTUEL ---
    protected ?Personnage $currentOpponent = null;
    
    public function setCurrentOpponent(?Personnage $opponent): void {
        $this->currentOpponent = $opponent;
    }
    
    public function getCurrentOpponent(): ?Personnage {
        return $this->currentOpponent;
    }
    

    
    public function receiveDamage(int $amount, ?Personnage $attacker = null): void {
        // Hook: Blessings modifying incoming damage
        foreach ($this->blessings as $blessing) {
            $amount = $blessing->onReceiveDamage($this, $attacker ?? $this, $amount); 
            // If attacker null, we pass self as fallback or need to handle null in Blessing
        }
        $this->pv -= $amount;
        if ($this->pv < 0) $this->pv = 0;
        
        // Check for Destiny Link effect - share damage with linked target
        foreach ($this->statusEffects as $effect) {
            if ($effect instanceof DestinyLinkEffect) {
                $linkedTarget = $effect->getLinkedTarget();
                if ($linkedTarget !== null && !$linkedTarget->isDead()) {
                    $sharedDamage = (int)($amount * $effect->getDamagePercent());
                    if ($sharedDamage > 0) {
                        $linkedTarget->receiveDamage($sharedDamage);
                    }
                }
            }
        }
    }

    public function triggerHealHooks(int $amount, ?Personnage $opponent = null): void {
        // Déclencher sur ses propres blessings
        foreach ($this->blessings as $blessing) {
            $blessing->onHeal($this, $amount);
        }
        
        // Utiliser l'adversaire passé en paramètre, ou currentOpponent par défaut
        $opp = $opponent ?? $this->currentOpponent;
        
        // Déclencher sur les blessings de l'adversaire (passifs "sur le terrain")
        if ($opp !== null) {
            foreach ($opp->getBlessings() as $blessing) {
                $blessing->onHeal($this, $amount);
            }
        }
    }

    public function getCurrentPP(string $actionKey): int {
        return $this->pp[$actionKey]['current'] ?? 0;
    }

    // Compteur d'actions réussies (pour scaling Décharge)
    protected int $successfulActionsCount = 0;

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
        $this->initializePP();
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

    public function getBaseAtk() {
        return $this->baseAtk;
    }

    public function getBaseDef() {
        return $this->baseDef;
    }

    public function getBaseSpeed() {
        return $this->speed; // speed n'a pas de version modifiable séparée
    }

    public function getAtk() {
        $atk = $this->atk;
        
        foreach ($this->statusEffects as $effect) {
            $mods = $effect->getStatModifiers();
            if (isset($mods['atk'])) {
                $atk += $mods['atk'];
            }
        }
        
        foreach ($this->blessings as $blessing) {
            $atk = $blessing->modifyStat('atk', $atk, $this);
        }

        return $atk;
    }

    public function getDef() {
        $def = $this->def;
        
        foreach ($this->statusEffects as $effect) {
            $mods = $effect->getStatModifiers();
            if (isset($mods['def'])) {
                $def += $mods['def'];
            }
        }

        foreach ($this->blessings as $blessing) {
            $def = $blessing->modifyStat('def', $def, $this);
        }

        return $def;
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
        $speed = $this->speed;
        
        foreach ($this->statusEffects as $effect) {
            $mods = $effect->getStatModifiers();
            if (isset($mods['speed'])) {
                $speed += $mods['speed'];
            }
        }

        foreach ($this->blessings as $blessing) {
            $speed = $blessing->modifyStat('speed', $speed, $this);
        }
        
        return max(0, $speed);
    }
    
    public function getSuccessfulActionsCount(): int {
        return $this->successfulActionsCount;
    }

    public function incrementSuccessfulActions(): void {
        $this->successfulActionsCount++;
    }

    public function resetSuccessfulActions(): void {
        $this->successfulActionsCount = 0;
    }

    /**
     * Vérifie si une action est bloquée par un effet de statut (ex: paralysie)
     * @return string|null Nom de l'effet bloquant ou null si libre
     */
    public function checkActionBlock(): ?string {
        foreach ($this->statusEffects as $effect) {
            if ($effect->blocksAction($this)) {
                return $effect->getName();
            }
        }
        return null; // Pas de blocage
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

    public function setSpeed(int $value): void {
        $this->speed = $value;
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
        $damage = $baseDamage + $this->roll(-2, 2);
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

    // --- BUFFS TEMPORAIRES ---

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

    // --- EFFETS DE STATUT ---

    /**
     * Ajoute un effet de statut au personnage
     * @param StatusEffect $effect L'effet à ajouter
     * @param ?Personnage $source Le personnage qui applique l'effet (pour les hooks de blessing)
     */
    public function addStatusEffect(StatusEffect $effect, ?Personnage $source = null): void {
        // Check immunity
        foreach ($this->statusEffects as $e) {
            if ($e instanceof ImmunityEffect && $effect->getName() !== 'Immunité') {
                // Immunisé ! Si fonction onResist existe?
                return; 
            }
        }

        // Hook: Let blessings modify effect duration
        foreach ($this->blessings as $blessing) {
            $modifiedDuration = $blessing->modifyEffectDuration($effect, $this, $source ?? $this);
            if ($modifiedDuration !== null) {
                $effect->setDuration($modifiedDuration);
            }
        }

        // Éviter les doublons du même type
        $effectClass = get_class($effect);
        foreach ($this->statusEffects as $key => $existing) {
            if (get_class($existing) === $effectClass) {
                // Remplace l'ancien effet par le nouveau
                $this->statusEffects[$key] = $effect;
                $effect->onActivate($this); // Reactivate?
                return;
            }
        }
        $this->statusEffects[] = $effect;
        $effect->onActivate($this);
    }

    /**
     * Phase de résolution des DÉGÂTS (Phase 2-3 du tour)
     * Applique les dégâts des effets actifs (brûlure, poison, etc.)
     * @return array Liste des résultats pour animations
     */
    public function resolveDamagePhase(): array {
        $results = [];
        $toRemove = [];

        foreach ($this->statusEffects as $key => $effect) {
            // Tick le délai si en attente
            if ($effect->isPending()) {
                $justActivated = $effect->tick();
                if ($justActivated) {
                    $results[] = [
                        'type' => 'activation',
                        'log' => $effect->onActivate($this),
                        'emoji' => $effect->getEmoji(),
                        'effectName' => $effect->getName()
                    ];
                }
                continue;
            }

            // Résoudre les dégâts
            $damageResult = $effect->resolveDamage($this);
            if ($damageResult !== null) {
                $results[] = array_merge($damageResult, ['type' => 'damage']);
            }

            // Tick la durée
            $effect->tick();

            // Marquer pour suppression si expiré
            if ($effect->isExpired()) {
                $results[] = [
                    'type' => 'expire',
                    'log' => $effect->onExpire($this),
                    'emoji' => '✨',
                    'effectName' => $effect->getName()
                ];
                $toRemove[] = $key;
            }
        }

        // Supprimer les effets expirés
        foreach ($toRemove as $key) {
            unset($this->statusEffects[$key]);
        }
        $this->statusEffects = array_values($this->statusEffects);

        return $results;
    }

    /**
     * Phase de résolution des STATS (Phase 4-5 du tour)
     * Applique les modifications de stats des effets (gel, etc.)
     * @return array Liste des résultats pour animations
     */
    public function resolveStatsPhase(): array {
        $results = [];

        foreach ($this->statusEffects as $effect) {
            if ($effect->isPending()) continue;

            $statsResult = $effect->resolveStats($this);
            if ($statsResult !== null) {
                $results[] = array_merge($statsResult, ['type' => 'stats']);
            }
        }

        return $results;
    }

    /**
     * Retourne tous les effets de statut (pour affichage)
     * @return StatusEffect[]
     */
    public function getStatusEffects(): array {
        return $this->statusEffects;
    }

    /**
     * Retourne les effets actifs (non pending) pour affichage UI
     */
    public function getActiveEffects(): array {
        $active = [];
        foreach ($this->statusEffects as $effect) {
            if (!$effect->isPending()) {
                $active[$effect->getName()] = [
                    'emoji' => $effect->getEmoji(),
                    'duration' => $effect->getDuration(),
                    'description' => $effect->getDescription(),
                    'isPending' => false
                ];
            }
        }
        return $active;
    }

    /**
     * Retourne les effets en attente pour affichage UI
     */
    public function getPendingEffects(): array {
        $pending = [];
        foreach ($this->statusEffects as $effect) {
            if ($effect->isPending()) {
                $pending[$effect->getName()] = [
                    'emoji' => $effect->getEmoji(),
                    'turnsDelay' => $effect->getTurnsDelay(),
                    'description' => $effect->getDescription(),
                    'isPending' => true
                ];
            }
        }
        return $pending;
    }

    /**
     * Retourne TOUS les effets (actifs + pending) pour affichage UI complet
     * Avec durée restante et description pour les tooltips
     */
    public function getAllEffectsForUI(): array {
        $effects = [];
        foreach ($this->statusEffects as $effect) {
            $isPending = $effect->isPending();
            $effects[$effect->getName()] = [
                'emoji' => $effect->getEmoji(),
                'duration' => $isPending ? $effect->getTurnsDelay() : $effect->getDuration(),
                'description' => $effect->getDescription(),
                'isPending' => $isPending
            ];
        }
        return $effects;
    }

    /**
     * Méthode legacy pour compatibilité - utilise le nouveau système
     * @deprecated Utiliser addStatusEffect() avec un objet StatusEffect
     */
    public function addPendingEffect(string $name, int $turnsDelay, int $duration, int $damage, string $emoji): void {
        require_once __DIR__ . '/effects/BurningEffect.php';
        $effect = new BurningEffect($duration, $damage, $turnsDelay);
        $this->addStatusEffect($effect);
    }

    /**
     * Méthode legacy pour compatibilité
     * @deprecated Utiliser resolveDamagePhase() + resolveStatsPhase()
     */
    public function resolveEffects(): array {
        $damageResults = $this->resolveDamagePhase();
        $statsResults = $this->resolveStatsPhase();
        
        $logs = [];
        $emojis = [];
        
        foreach (array_merge($damageResults, $statsResults) as $result) {
            $logs[] = $result['log'];
            $emojis[] = $result['emoji'];
        }
        
        return ['logs' => $logs, 'emojis' => $emojis];
    }

    /**
     * Calcule les dégâts aléatoires dans une fourchette
     */
    protected function randomDamage(int $base, int $variance = 2): int {
        return max(1, $base + $this->roll(-$variance, $variance));
    }

    /**
     * Génère un nombre aléatoire en prenant en compte les bénédictions (ex: Roue de Fortune)
     */
    public function roll(int $min, int $max): int {
        foreach ($this->blessings as $blessing) {
            $mod = $blessing->modifyRoll($min, $max);
            if ($mod !== null) {
                $min = $mod['min'];
                $max = $mod['max'];
            }
        }
        return rand((int)$min, (int)$max);
    }

    // --- GESTION DES BÉNÉDICTIONS ---

    public function addBlessing(Blessing $blessing): void {
        $this->blessings[$blessing->getId()] = $blessing;
        
        // Initialiser les PP pour les actions du blessing
        $extraActions = $blessing->getExtraActions();
        foreach ($extraActions as $key => $action) {
            if (isset($action['pp'])) {
                $this->pp[$key] = [
                    'current' => $action['pp'],
                    'max' => $action['pp']
                ];
            }
        }
    }

    public function removeBlessing(string $id): void {
        if (isset($this->blessings[$id])) {
            // Supprimer les PP des actions de ce blessing
            $blessing = $this->blessings[$id];
            $extraActions = $blessing->getExtraActions();
            foreach ($extraActions as $key => $action) {
                if (isset($this->pp[$key])) {
                    unset($this->pp[$key]);
                }
            }
            unset($this->blessings[$id]);
        }
    }

    public function hasBlessing(string $id): bool {
        return isset($this->blessings[$id]);
    }

    public function getBlessings(): array {
        return $this->blessings;
    }
    
    /** 
     * Récupère la liste des actions disponibles, y compris celles des bénédictions 
     */
    public function getAllActions(): array {
        $actions = $this->getAvailableActions();
        foreach ($this->blessings as $blessing) {
            $extra = $blessing->getExtraActions();
            $actions = array_merge($actions, $extra);
        }
        return $actions;
    }

    // --- EXECUTION ACTIONS SPECIALES BENEDICTIONS ---
    
    /**
     * Exécute une action blessing par son clé
     * @param string $actionKey La clé de l'action (ex: 'grand_conseil')
     * @param ?Personnage $target La cible optionnelle
     * @return string Le message de résultat
     */
    public function executeBlessingAction(string $actionKey, ?Personnage $target = null): string {
        foreach ($this->blessings as $blessing) {
            $actions = $blessing->getExtraActions();
            if (isset($actions[$actionKey])) {
                return $blessing->executeAction($actionKey, $this, $target);
            }
        }
        throw new Exception("Action blessing '$actionKey' introuvable pour " . $this->getName());
    }

    // Dynamic dispatch for blessing actions (fallback pour compatibilité)
    public function __call($name, $arguments) {
        // Try to find and execute a blessing action
        foreach ($this->blessings as $blessing) {
            $actions = $blessing->getExtraActions();
            foreach ($actions as $key => $data) {
                if ($data['method'] === $name) {
                    $target = $arguments[0] ?? null;
                    return $blessing->executeAction($key, $this, $target);
                }
            }
        }
        throw new Exception("Méthode $name inexistante sur " . get_class($this));
    }

    // --- PP (Power Points) ---

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

