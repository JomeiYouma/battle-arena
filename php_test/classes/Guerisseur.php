<?php
class Guerisseur extends Personnage {

    public function heal($x = null) {
        if (is_null($x)) {
            $this->setPv($this->basePv);
        } else {
            $this->setPv($this->pv + $x);
        }
    }
};
