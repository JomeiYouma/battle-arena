<?php
/** STATREDISTRIBUTIONEFFECT - Répartition équitable des stats */

class StatRedistributionEffect extends StatusEffect {
    private bool $applied = false;
    private array $originalStats = [];
    
    public function __construct(int $duration) {
        parent::__construct('Jugement', '⚖️', $duration);
    }
    
    public function resolveDamage(Personnage $target): ?array {
        return null;
    }

    public function resolveStats(Personnage $target): ?array {
        if (!$this->applied) {
            $this->originalStats = [
                'atk' => $target->getAtk(),
                'def' => $target->getDef(),
                'speed' => $target->getSpeed()
            ];
            
            $total = array_sum($this->originalStats);
            $avg = (int)($total / 3);
            
            $target->setAtk($avg);
            $target->setDef($avg);
            if (method_exists($target, 'setSpeed')) {
                $target->setSpeed($avg);
            }
            
            $this->applied = true;
            
            return [
                'log' => "⚖️ Le Jugement tombe ! Stats égalisées à $avg !",
                'emoji' => $this->emoji,
                'effectName' => $this->name,
                'type' => 'stat_redistribution'
            ];
        }
        return null;
    }

    public function onExpire(Personnage $target): string {
        if ($this->applied) {
            $target->setAtk($this->originalStats['atk']);
            $target->setDef($this->originalStats['def']);
            if (method_exists($target, 'setSpeed')) {
                $target->setSpeed($this->originalStats['speed']);
            }
        }
        return "✨ Le jugement prend fin. Stats restaurées.";
    }

    public function getDescription(): string {
        return "⚖️ Jugement : ATK/DEF/VIT égalisées ({$this->duration} tour(s))";
    }
}
