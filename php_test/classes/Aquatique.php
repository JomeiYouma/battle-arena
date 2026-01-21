<?php
class Aquatique extends Personnage {

    public function heal($x = null) {
        if (is_null($x)) {
            $this->setPv($this->basePv);
        } else {
            $this->setPv($this->pv + $x);
        }
    }

    public function getAvailableActions(): array {
        return [
            'attack' => ['label' => '⚔️ Attaquer', 'method' => 'attack'],
            'shield' => ['label' => '🛡️ Bloquer', 'method' => 'shield']
        ];
    }
};

