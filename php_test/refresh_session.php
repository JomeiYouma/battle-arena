<?php
/**
 * Rafraîchir la session avec les données actuelles de la BDD
 */

require_once __DIR__ . '/includes/autoload.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    die('Erreur: Vous devez être connecté');
}

// Recharger les infos depuis la BDD
$db = Database::getInstance();
$stmt = $db->prepare('SELECT id, username, is_admin FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    die('Erreur: Utilisateur non trouvé');
}

// Mettre à jour la session
$_SESSION['username'] = $user['username'];
$_SESSION['is_admin'] = (int)$user['is_admin'];

echo '<h1>✅ Session rafraîchie!</h1>';
echo '<p>Vos données de session ont été mises à jour:</p>';
echo '<ul>';
echo '<li>User ID: ' . $_SESSION['user_id'] . '</li>';
echo '<li>Username: ' . $_SESSION['username'] . '</li>';
echo '<li>Is Admin: ' . $_SESSION['is_admin'] . '</li>';
echo '</ul>';
echo '<p><a href="debug.php">Aller au debug</a></p>';
?>
