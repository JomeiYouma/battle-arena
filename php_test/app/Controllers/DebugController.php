<?php
/**
 * DEBUGCONTROLLER - Outils de debug (local seulement)
 */

class DebugController extends Controller {
    
    /**
     * Page debug principale
     */
    public function index(): void {
        // Redirection temporaire vers l'ancienne page
        header('Location: /nodeTest2/mood-checker/php_test/pages/debug/debug.php');
        exit;
    }
}
