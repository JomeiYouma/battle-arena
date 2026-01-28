<?php
/**
 * AUTH HELPER - Protection par mot de passe pour les pages sensibles
 */

define('DEBUG_PASSWORD', 'admin123'); // Mot de passe à modifier selon vos besoins

function requireDebugAuth() {
    session_start();
    
    // Vérifier si déjà authentifié
    if (isset($_SESSION['debug_auth']) && $_SESSION['debug_auth'] === true) {
        return true;
    }
    
    // Vérifier si le formulaire est soumis
    if (isset($_POST['debug_password'])) {
        if ($_POST['debug_password'] === DEBUG_PASSWORD) {
            $_SESSION['debug_auth'] = true;
            return true;
        } else {
            $error = "Mot de passe incorrect";
        }
    }
    
    // Afficher le formulaire de connexion
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Accès Protégé</title>
        <link rel="stylesheet" href="./style.css">
        <link rel="stylesheet" href="./css/auth.css">
    </head>
    <body class="auth-body">
        <div class="auth-box">
            <h2>🔐 Accès Protégé</h2>
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="debug_password" placeholder="Mot de passe" required autofocus>
                <button type="submit">Accéder</button>
            </form>
            <a href="index.php" class="back-link">← Retour au menu</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
