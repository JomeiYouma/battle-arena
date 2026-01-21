<?php
require_once 'classes/Personnage.php';

// Création de Gandalf (PV, ATK, NOM)
$gandalf = new Personnage(100, 20, "Gandalf");

// Création d'un méchant pour tester la bagarre
$orc = new Personnage(50, 10, "Azog");

// Test du var_dump
var_dump($gandalf);
echo "<br><br>";

// Test de l'attaque
echo $gandalf->name . " attaque " . $orc->name . "<br><br>";

echo $gandalf->attack($orc);

var_dump($orc); // On vérifie que l'orc a perdu des PV
echo "<br><br>";
// Test de la régénération sans paramètre (full soin)
$orc->heal(); 
var_dump($orc); // Il devrait être revenu à 50 PV
echo "<br><br>";