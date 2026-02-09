<?php
/**
 * LAYOUT MAIN - Template principal avec header/footer
 * Variables disponibles: $pageTitle, $extraCss, $showMainTitle, $showUserBadge, $showBackLink, $content
 */

// Valeurs par défaut
$pageTitle = $pageTitle ?? 'Horus Battle Arena';
$extraCss = $extraCss ?? [];
$showMainTitle = $showMainTitle ?? true;
$showUserBadge = $showUserBadge ?? true;
$showBackLink = $showBackLink ?? true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($pageTitle) ?></title>
    <link rel="icon" href="<?= View::asset('media/website/favicon.ico') ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= View::asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= View::asset('css/layout.css') ?>">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?= View::asset('css/' . $css . '.css') ?>">
    <?php endforeach; ?>
</head>
<body>

<?php if ($showMainTitle): ?>
<h1>Horus Battle Arena</h1>
<?php endif; ?>

<?php if ($showUserBadge && User::isLoggedIn()): ?>
<div class="user-badge">
    ⚔️ <?= View::e(User::getCurrentUsername()) ?>
</div>
<?php endif; ?>

<?php 
// Afficher le message flash s'il existe
if (isset($_SESSION['flash'])): 
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
?>
<div class="notification notification-<?= $flash['type'] ?>">
    <?= View::e($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Contenu principal -->
<?= $content ?>

<footer class="site-footer">
    <?php if ($showBackLink): ?>
    <a href="<?= View::url('/') ?>" class="back-link">← Retour au menu</a>
    <?php endif; ?>
    
    <div class="footer-links">
        <a href="<?= View::url('/legal') ?>">Mentions légales & Crédits</a>
        <span class="separator">|</span>
        <span class="copyright">© <?= date('Y') ?> Horus Battle Arena</span>
    </div>
</footer>

</body>
</html>
