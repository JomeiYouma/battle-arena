<?php
/**
 * AUTHCONTROLLER - Gestion de l'authentification (login, register, logout)
 */

class AuthController extends Controller {
    
    /**
     * Afficher le formulaire de connexion
     */
    public function showLogin(): void {
        // Rediriger si déjà connecté
        if (User::isLoggedIn()) {
            $this->redirect('/account');
        }
        
        $data = [
            'pageTitle' => 'Connexion - Horus Battle Arena',
            'extraCss' => ['auth'],
            'showUserBadge' => false,
            'showMainTitle' => true,
            'error' => '',
            'success' => $this->get('registered', false),
            'email' => ''
        ];
        
        $this->render('auth/login', $data);
    }
    
    /**
     * Traiter la connexion
     */
    public function login(): void {
        // Rediriger si déjà connecté
        if (User::isLoggedIn()) {
            $this->redirect('/account');
        }
        
        $email = trim($this->post('email', ''));
        $password = $this->post('password', '');
        $error = '';
        
        if ($email && $password) {
            $user = new User();
            $result = $user->login($email, $password);
            
            if ($result['success']) {
                $this->redirect('/');
            } else {
                $error = $result['error'];
            }
        } else {
            $error = 'Veuillez remplir tous les champs';
        }
        
        $data = [
            'pageTitle' => 'Connexion - Horus Battle Arena',
            'extraCss' => ['auth'],
            'showUserBadge' => false,
            'showMainTitle' => true,
            'error' => $error,
            'success' => false,
            'email' => $email
        ];
        
        $this->render('auth/login', $data);
    }
    
    /**
     * Afficher le formulaire d'inscription
     */
    public function showRegister(): void {
        // Rediriger si déjà connecté
        if (User::isLoggedIn()) {
            $this->redirect('/account');
        }
        
        $data = [
            'pageTitle' => 'Inscription - Horus Battle Arena',
            'extraCss' => ['auth'],
            'showUserBadge' => false,
            'showMainTitle' => true,
            'error' => '',
            'username' => '',
            'email' => ''
        ];
        
        $this->render('auth/register', $data);
    }
    
    /**
     * Traiter l'inscription
     */
    public function register(): void {
        // Rediriger si déjà connecté
        if (User::isLoggedIn()) {
            $this->redirect('/account');
        }
        
        $username = trim($this->post('username', ''));
        $email = trim($this->post('email', ''));
        $password = $this->post('password', '');
        $passwordConfirm = $this->post('password_confirm', '');
        $error = '';
        
        if ($password !== $passwordConfirm) {
            $error = 'Les mots de passe ne correspondent pas';
        } elseif ($username && $email && $password) {
            $user = new User();
            $result = $user->register($username, $email, $password);
            
            if ($result['success']) {
                $this->redirect('/login?registered=1');
            } else {
                $error = $result['error'];
            }
        } else {
            $error = 'Veuillez remplir tous les champs';
        }
        
        $data = [
            'pageTitle' => 'Inscription - Horus Battle Arena',
            'extraCss' => ['auth'],
            'showUserBadge' => false,
            'showMainTitle' => true,
            'error' => $error,
            'username' => $username,
            'email' => $email
        ];
        
        $this->render('auth/register', $data);
    }
    
    /**
     * Déconnexion
     */
    public function logout(): void {
        User::logout();
        $this->redirect('/');
    }
}
