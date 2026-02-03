<?php
/**
 * StatSwapEffect - Échange ATK et DEF pendant la durée
 */
class StatSwapEffect extends StatusEffect {
    private bool $swapped = false;
    private int $originalAtk = 0;
    private int $originalDef = 0;
    
    public function __construct(int $duration) {
        parent::__construct('Âme Manipulée', '🔄', $duration);
    }

    public function resolveDamage(Personnage $target): ?array {
        return null;
    }

    public function resolveStats(Personnage $target): ?array {
        if (!$this->swapped) {
            $this->originalAtk = $target->getAtk();
            $this->originalDef = $target->getDef();
            $target->setAtk($this->originalDef);
            $target->setDef($this->originalAtk);
            $this->swapped = true;
            
            return [
                'log' => "🔄 " . $target->getName() . " a ses stats échangées ! ATK=" . $target->getAtk() . " DEF=" . $target->getDef(),
                'emoji' => $this->emoji,
                'effectName' => $this->name,
                'type' => 'stat_swap'
            ];
        }
        return null;
    }

    public function onExpire(Personnage $target): string {
        if ($this->swapped) {
            $target->setAtk($this->originalAtk);
            $target->setDef($this->originalDef);
        }
        return "✨ " . $this->name . " sur " . $target->getName() . " s'est dissipé. Stats restaurées !";
    }

    public function getDescription(): string {
        return "🔄 ATK et DEF inversées ({$this->duration} tour(s))";
    }
}
