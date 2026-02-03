<?php
/**
 * RESET SESSION - Nettoie la session et retire de la queue
 * Endpoint simple pour débloquer rapidement lors des tests
 * PROTÉGÉ PAR MOT DE PASSE
 */

require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/includes/auth_helper.php';
requireDebugAuth();

// Retirer de la queue si présent (via BDD)
$sessionId = session_id();
// MatchQueue est chargé par l'autoloader
$queue = new MatchQueue();
$queue->removeFromQueue($sessionId);

// Détruire la session
session_unset();
session_destroy();

// Rediriger
header("Location: index.php");
exit;
