<?php

class Personnage {

    private $pv;
    private $atk;
    private $name;
    private $basePv;

    public function __construct($pv, $atk, $name) {
        $this->pv = $pv;
        $this->basePv = $pv;
        $this->atk = $atk;
        $this->name = $name;
    }
//Getters
    public function getPv() {
        return $this->pv;
    }

    public function getAtk() {
        return $this->atk;
    }

    public function getName() {
        return $this->name;
    }

    public function getBasePv($pv) {
        $this->pv = $pv;
    }
//Setters
    public function setAtk($atk) {
        $this->atk = $atk;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function setBasePv($pv) {
        $this->basePv = $pv;
    }

    public function setPv($pv) {
        $this->pv = $pv;
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

    public function isDead() {
        return $this->pv > 0;
    }

    public function attack(Personnage $target) {
        $target->pv -= $this->atk;

        if ($target->pv < 0) {
            $target->pv = 0;
        }

        if (!$target->isDead()) {
            return $target->name . " est mort !<br>";
        } else {
            return $target->name . " a " . $target->pv . " PV restants.<br>";
        }
    }
}