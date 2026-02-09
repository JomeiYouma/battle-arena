<?php
/**
 * APICONTROLLER - Intègre l'ancienne API pour compatibilité
 */

class ApiController extends Controller {
    
    /**
     * Inclure et exécuter l'ancienne API
     */
    public function handle(): void {
        // Inclure directement l'ancienne API pour éviter les problèmes de redirection
        require_once APP_ROOT . '/api.php';
        exit;
    }
}
