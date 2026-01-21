<?php
class Vehicule{
    public $km;
    public $vitesse;

public function __construct($x, $y) /* (ou bien $km et $vitesse) */
    {
        $this->km = $x;
        $this->vitesse = $y;
    }


    public function klaxon(){
        return "Pouet Pouet!";
    }

    public function speedUp($x){
        $this->vitesse += $x;
        
    }
};