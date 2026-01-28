<?php
/**
 * HEADER - Inclure en haut de chaque page
 * 
 * Variables optionnelles à définir avant l'include:
 * - $pageTitle (string) : Titre de la page (défaut: "Horus Battle Arena")
 * - $extraCss (array) : CSS supplémentaires à charger
 * - $showMainTitle (bool) : Afficher le h1 principal (défaut: true)
 * - $showUserBadge (bool) : Afficher le badge utilisateur (défaut: true)
 */

// Valeurs par défaut
$pageTitle = $pageTitle ?? 'Horus Battle Arena';
$extraCss = $extraCss ?? [];
$showMainTitle = $showMainTitle ?? true;
$showUserBadge = $showUserBadge ?? true;

// S'assurer que la session est démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Charger User si pas déjà fait
if (!class_exists('User')) {
    require_once __DIR__ . '/../classes/User.php';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" href="./media/website/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="./style.css">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="./css/<?php echo $css; ?>.css">
    <?php endforeach; ?>
</head>
<body>

<?php if ($showMainTitle): ?>
<h1>Horus Battle Arena</h1>
<?php endif; ?>

<?php if ($showUserBadge && User::isLoggedIn()): ?>
<div class="user-badge">
    ⚔️ <?php echo htmlspecialchars(User::getCurrentUsername()); ?>
</div>
<?php endif; ?>
