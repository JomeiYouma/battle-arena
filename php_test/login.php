<?php
/**
 * LOGIN PAGE - Connexion utilisateur
 */

// Autoloader
function chargerClasse($classe) {
    if (file_exists(__DIR__ . '/classes/' . $classe . '.php')) {
        require __DIR__ . '/classes/' . $classe . '.php';
        return;
    }
    if (file_exists(__DIR__ . '/classes/effects/' . $classe . '.php')) {
        require __DIR__ . '/classes/effects/' . $classe . '.php';
        return;
    }
}
spl_autoload_register('chargerClasse');
session_start();

// Rediriger si déjà connecté
if (User::isLoggedIn()) {
    header('Location: account.php');
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
            header('Location: index.php');
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
    <title>Connexion - Horus Battle Arena</title>
    <link rel="icon" href="./media/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-box {
            background: rgba(20, 20, 30, 0.95);
            border: 2px solid #c41e3a;
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 0 40px rgba(196, 30, 58, 0.3);
        }
        
        .auth-box h2 {
            color: #ffd700;
            text-align: center;
            margin-bottom: 30px;
            font-size: 1.8em;
        }
        
        .auth-form .form-group {
            margin-bottom: 20px;
        }
        
        .auth-form label {
            display: block;
            color: #b8860b;
            margin-bottom: 8px;
            font-size: 0.9em;
        }
        
        .auth-form input {
            width: 100%;
            padding: 12px 15px;
            background: #0a0a0f;
            border: 2px solid #4a0000;
            border-radius: 8px;
            color: #e0e0e0;
            font-size: 16px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .auth-form input:focus {
            outline: none;
            border-color: #c41e3a;
        }
        
        .auth-form button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #c41e3a, #7a1226);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .auth-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(196, 30, 58, 0.4);
        }
        
        .error-msg {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid #ff4444;
            color: #ff6b6b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-msg {
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid #44ff44;
            color: #6bff6b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }
        
        .auth-links a {
            color: #b8860b;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .auth-links a:hover {
            color: #ffd700;
        }
        
        .back-home {
            display: block;
            margin-top: 15px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h2>⚔️ Connexion</h2>
            
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
                <a href="index.php" class="back-home">← Retour au menu</a>
            </div>
        </div>
    </div>
</body>
</html>
