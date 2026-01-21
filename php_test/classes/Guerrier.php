<?php
class Guerrier extends Personnage {
    public function attack(Personnage $target) {
        $newPv = $target->getPv() - $this->atk + 20;
        
        $target->setPv($newPv);

        if ($target->isDead()) {
            return $target->getName() . " est mort !<br>";
        } else {
            return $target->getName() . " a " . $target->getPv() . " PV restants.<br>";
        }
    }
};
?>