<?php

class Personnage {

    public $pv;
    public $atk;
    public $name;
    public $basePv;

    public function __construct($pv, $atk, $name) {
        $this->pv = $pv;
        $this->basePv = $pv;
        $this->atk = $atk;
        $this->name = $name;
    }

    public function cri() {
        return "YOU SHALL NOT PASS !";
    }

    public function heal($x = null) {
        if (is_null($x)) {
            $this->pv = $this->basePv;
        } else {
            $this->pv += $x;
            if ($this->pv > $this->basePv) {
                $this->pv = $this->basePv;
            }
        }
    }

    public function isAlive() {
        return $this->pv > 0;
    }

    public function attack(Personnage $target) {
        $target->pv -= $this->atk;

        if ($target->pv < 0) {
            $target->pv = 0;
        }

        if (!$target->isAlive()) {
            return $target->name . " est mort !<br>";
        } else {
            return $target->name . " a " . $target->pv . " PV restants.<br>";
        }
    }
}