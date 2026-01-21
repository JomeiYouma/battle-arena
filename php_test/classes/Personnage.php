<?php

class Personnage {
const MAX_PV = 100;

private static $nbPersonnages = 0;
public static function getNbPersonnages() {
        return self::$nbPersonnages;
    }

    private $pv;
    private $atk;
    private $name;
    private $basePv;

    public function __construct($pv, $atk, $name) {
         self::$nbPersonnages++;
        $this->name = $name;
        $this->atk = $atk;
        $this->basePv = $pv;
        
        $this->setPv($pv); 
    }

    // --- GETTERS (Accesseurs) ---

    public function getPv() {
        return $this->pv;
    }

    public function getAtk() {
        return $this->atk;
    }

    public function getName() {
        return $this->name;
    }

    public function getBasePv() {
        return $this->basePv;
    }

    // --- SETTERS (Mutateurs) ---

    public function setAtk($atk) {
        if ($atk > 0) {
            $this->atk = $atk;
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

    // --- MÉTHODES ---

    public function cri() {
        return "YOU SHALL NOT PASS !";
    }

    public function heal($x = null) {
        if (is_null($x)) {
            $this->setPv($this->basePv);
        } else {
            $this->setPv($this->pv + $x);
        }
    }

    public function isDead() {
        return $this->pv <= 0;
    }

    public function attack(Personnage $target) {
        $newPv = $target->getPv() - $this->atk;
        
        $target->setPv($newPv);

        if ($target->isDead()) {
            return $target->getName() . " est mort !<br>";
        } else {
            return $target->getName() . " a " . $target->getPv() . " PV restants.<br>";
        }
    }
}