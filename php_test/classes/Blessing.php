<?php
/**
 * Abstract class for Blessings
 */
abstract class Blessing {
    protected string $id;
    protected string $name;
    protected string $description;
    protected string $emoji;

    public function __construct(string $id, string $name, string $description, string $emoji) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->emoji = $emoji;
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getEmoji(): string { return $this->emoji; }

    // --- HOOKS ---

    /**
     * Called when checking available actions
     */
    public function getExtraActions(): array {
        return [];
    }

    /**
     * Execute une action spéciale du blessing
     * @param string $actionKey La clé de l'action (ex: 'grand_conseil')
     * @param Personnage $actor Le personnage qui lance l'action
     * @param ?Personnage $target La cible (null si needsTarget=false)
     * @return string Le message de résultat
     */
    public function executeAction(string $actionKey, Personnage $actor, ?Personnage $target = null): string {
        // À implémenter dans les classes enfant
        return "L'action " . $actionKey . " n'est pas implémentée.";
    }

    /**
     * Called during stats calculation (getAtk, getDef, getSpeed)
     * @param string $stat 'atk', 'def', 'speed'
     * @param int $currentValue
     * @param Personnage $owner
     * @return int New value
     */
    public function modifyStat(string $stat, int $currentValue, Personnage $owner): int {
        return $currentValue;
    }

    /**
     * Called in roll method
     */
    public function modifyRoll(int $min, int $max): ?array {
        return null; // Return ['min' => x, 'max' => y] to override
    }

    public function onTurnStart(Personnage $owner, Combat $combat): void {}
    public function onTurnEnd(Personnage $owner, Combat $combat): void {}

    public function onAttack(Personnage $attacker, Personnage $target, int $damage): void {}
    
    /**
     * Called when owner receives damage
     * @return int Modified damage amount
     */
    public function onReceiveDamage(Personnage $victim, Personnage $attacker, int $damage): int {
        return $damage;
    }

    public function onHeal(Personnage $healer, int $amount): void {}
}
