<?php
/**
 * REGISTER PAGE - Inscription utilisateur
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Horus Battle Arena</title>
    <link rel="icon" href="../../public/media/website/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/auth.css">
</head>
<body>
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
                <a href="../../index.php" class="back-home">← Retour au menu</a>
            </div>
        </div>
    </div>
</body>
</html>
