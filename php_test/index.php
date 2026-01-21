<?php
require_once 'classes/Vehicule.php';

$bmw = new Vehicule(15000, 120);

echo $bmw->vitesse . "<br>";
$bmw->speedUp(30);
echo $bmw->vitesse . "<br>";
echo $bmw->klaxon() . "<br>";
?>