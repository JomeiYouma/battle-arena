<?php
/**
 * LAYOUT HOME - Template pour la page d'accueil (sans footer)
 */

$pageTitle = $pageTitle ?? 'Horus Battle Arena';
$extraCss = $extraCss ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::e($pageTitle) ?></title>
    <link rel="icon" href="<?= View::asset('media/website/favicon.ico') ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= View::asset('css/style.css') ?>">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?= View::asset('css/' . $css . '.css') ?>">
    <?php endforeach; ?>
</head>
<body>

<h1>Horus Battle Arena</h1>

<?php if (User::isLoggedIn()): ?>
<div class="user-badge">
    <?= View::e(User::getCurrentUsername()) ?>
</div>
<?php endif; ?>

<!-- Contenu principal -->
<?= $content ?>

</body>
</html>
