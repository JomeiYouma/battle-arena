<?php
/**
 * LOGIN PAGE - Connexion utilisateur
 */

// Autoloader centralisé
require_once __DIR__ . '/../../includes/autoload.php';
// Note: autoload.php démarre déjà la session

// Rediriger si déjà connecté
if (User::isLoggedIn()) {
    header('Location: ../account.php');
    exit;
}

$error = '';
$success = $_GET['registered'] ?? false;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        $user = new User();
        $result = $user->login($email, $password);
        
        if ($result['success']) {
            header('Location: ../../index.php');
            exit;
        } else {
            $error = $result['error'];
        }
    } else {
        $error = 'Veuillez remplir tous les champs';
    }
}

// Configuration du header
$pageTitle = 'Connexion - Horus Battle Arena';
$extraCss = ['auth'];
$showUserBadge = false;
$showMainTitle = false;
require_once INCLUDES_PATH . '/header.php';
?>
    <div class="auth-container">
        <div class="auth-box">
            <h2>Connexion</h2>
            
            <?php if ($success): ?>
                <div class="success-msg">Compte créé avec succès ! Connectez-vous.</div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit">Se connecter</button>
            </form>
            
            <div class="auth-links">
                <a href="register.php">Pas encore de compte ? S'inscrire</a>
            </div>
        </div>
    </div>
<?php 
$showBackLink = true;
require_once INCLUDES_PATH . '/footer.php'; 
?>
