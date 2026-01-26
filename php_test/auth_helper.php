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
        <style>
            body { 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                min-height: 100vh;
                background: #1a1a2e;
            }
            .auth-box {
                background: rgba(20, 20, 30, 0.95);
                border: 2px solid #c41e3a;
                border-radius: 15px;
                padding: 40px;
                text-align: center;
                max-width: 400px;
            }
            .auth-box h2 {
                color: #ffd700;
                margin-bottom: 20px;
            }
            .auth-box input[type="password"] {
                width: 100%;
                padding: 12px;
                background: #0a0a0f;
                border: 2px solid #4a0000;
                border-radius: 8px;
                color: #e0e0e0;
                font-size: 16px;
                margin-bottom: 15px;
            }
            .auth-box input[type="password"]:focus {
                outline: none;
                border-color: #c41e3a;
            }
            .auth-box button {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #c41e3a, #7a1226);
                border: none;
                border-radius: 8px;
                color: white;
                font-size: 16px;
                cursor: pointer;
                transition: transform 0.2s;
            }
            .auth-box button:hover {
                transform: scale(1.02);
            }
            .error {
                color: #ff6b6b;
                margin-bottom: 15px;
            }
            .back-link {
                margin-top: 20px;
                display: block;
                color: #b8860b;
            }
        </style>
    </head>
    <body>
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
