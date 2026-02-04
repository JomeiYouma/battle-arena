<?php
/** REGISTER - Inscription utilisateur */

require_once __DIR__ . '/../../includes/autoload.php';

// Rediriger si déjà connecté
if (User::isLoggedIn()) {
    header('Location: ../account.php');
    exit;
}

$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if ($password !== $passwordConfirm) {
        $error = 'Les mots de passe ne correspondent pas';
    } elseif ($username && $email && $password) {
        $user = new User();
        $result = $user->register($username, $email, $password);
        
        if ($result['success']) {
            header('Location: login.php?registered=1');
            exit;
        } else {
            $error = $result['error'];
        }
    } else {
        $error = 'Veuillez remplir tous les champs';
    }
}

// Configuration du header
$pageTitle = 'Inscription - Horus Battle Arena';
$extraCss = ['auth'];
$showUserBadge = false;
$showMainTitle = false;
require_once INCLUDES_PATH . '/header.php';
?>
    <div class="auth-container">
        <div class="auth-box">
            <h2>Inscription</h2>
            
            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username">Pseudo</label>
                    <input type="text" id="username" name="username" required autofocus 
                           minlength="3" maxlength="50"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <div class="password-hint">Minimum 6 caractères</div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                
                <button type="submit">Créer mon compte</button>
            </form>
            
            <div class="auth-links">
                <a href="login.php">Déjà un compte ? Se connecter</a>
            </div>
        </div>
    </div>
<?php 
$showBackLink = true;
require_once INCLUDES_PATH . '/footer.php'; 
?>
