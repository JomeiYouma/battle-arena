<?php
/**
 * StatSwapEffect - Échange ATK et DEF de la cible
 * 
 * Pendant la durée, les stats ATK et DEF sont échangées.
 */

class StatSwapEffect extends StatusEffect {
    private bool $swapped = false;
    private int $originalAtk = 0;
    private int $originalDef = 0;
    
    public function __construct(int $duration) {
        parent::__construct('Âme Manipulée', '🔄', $duration);
    }
    
    /**
     * Pas de dégâts directs
     */
    public function resolveDamage(Personnage $target): ?array {
        return null;
    }
    
    /**
     * Échange les stats ATK/DEF au premier appel
     */
    public function resolveStats(Personnage $target): ?array {
        if (!$this->swapped) {
            // Premier tour : sauvegarder et échanger
            $this->originalAtk = $target->getAtk();
            $this->originalDef = $target->getDef();
            
            // Échanger les valeurs
            $target->setAtk($this->originalDef);
            $target->setDef($this->originalAtk);
            
            $this->swapped = true;
            
            return [
                'log' => "🔄 " . $target->getName() . " a ses stats échangées ! ATK=" . $target->getAtk() . " DEF=" . $target->getDef(),
                'emoji' => $this->emoji,
                'effectName' => $this->name,
                'type' => 'stat_swap',
                'statChanges' => [
                    'atk' => $this->originalDef - $this->originalAtk,
                    'def' => $this->originalAtk - $this->originalDef
                ]
            ];
        }
        
        return null;
    }
    
    /**
     * Restaurer les stats originales quand l'effet expire
     */
    public function onExpire(Personnage $target): string {
        if ($this->swapped) {
            $target->setAtk($this->originalAtk);
            $target->setDef($this->originalDef);
        }
        return "✨ " . $this->name . " sur " . $target->getName() . " s'est dissipé. Stats restaurées !";
    }
}
