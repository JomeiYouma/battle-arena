<?php
/**
 * ⚠️ FICHIER ARCHIVÉ - NE PLUS UTILISER
 * 
 * Ce fichier a été renommé en "multiplayer-selection.php" pour plus de clarté.
 * 
 * NOUVELLE STRUCTURE:
 * ├─ multiplayer.php ...................... Redirecteur → multiplayer-selection.php
 * ├─ multiplayer-selection.php ............ Sélection héros + Queue 30s (NOUVEAU NOM)
 * └─ multiplayer-combat.php .............. Interface de combat multijoueur (ANCIEN multiplayer.php)
 * 
 * Pour accéder au mode multijoueur, utilisez:
 * - multiplayer.php (redirecteur automatique)
 * - index.php avec mode='multi'
 * 
 * Raison du renommage:
 * Il existait une confusion entre:
 * - multi_player.php (ancienne nomenclature) = page de sélection + queue
 * - multiplayer.php = page de combat en temps réel
 * 
 * Les nouveaux noms sont plus explicites:
 * - multiplayer-selection.php = sélection et attente
 * - multiplayer-combat.php = combat réel
 */

// Redirection permanente pour compatibilité
header("Location: multiplayer-selection.php", true, 301);
exit;