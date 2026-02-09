<?php
/**
 * HOMECONTROLLER - Page d'accueil et menu principal
 */

class HomeController extends Controller {
    
    /**
     * Page d'accueil
     */
    public function index(): void {
        // Traitement POST pour choix de mode
        if ($this->isPost()) {
            $mode = $this->post('mode');
            
            if ($mode === 'single') {
                $this->redirect('/game/single');
            } elseif ($mode === 'multi') {
                $this->redirect('/game/multiplayer');
            }
        }
        
        // Traitement déconnexion/nouveau jeu
        if ($this->post('logout') || $this->post('new_game')) {
            // Préserver les données de connexion
            $userId = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? null;
            
            // Nettoyer les données de combat
            unset($_SESSION['combat']);
            unset($_SESSION['hero_img']);
            unset($_SESSION['enemy_img']);
            unset($_SESSION['hero_id']);
            unset($_SESSION['enemy_id']);
            unset($_SESSION['combat_recorded']);
            
            // Restaurer les données de connexion
            if ($userId !== null) {
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
            }
        }
        
        $data = [
            'pageTitle' => 'Horus Battle Arena',
            'extraCss' => [],
            'isLocalhost' => (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false 
                           || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)
        ];
        
        // Utiliser le layout 'home' sans footer
        $this->view->render('home/index', $data, 'home');
    }
}
