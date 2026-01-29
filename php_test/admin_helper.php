<?php
/**
 * Protection des pages d'administration
 * Vérifie que l'utilisateur est connecté ET est administrateur
 */

session_start();

function requireAdmin() {
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?error=admin_access_required');
        exit;
    }
    
    // Vérifier si l'utilisateur est admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        header('Location: index.php?error=admin_only');
        exit;
    }
}
