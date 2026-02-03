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

// Charger autoloader si pas déjà fait (pour User class)
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/autoload.php';
}

// Calculer le chemin relatif vers public/ depuis le fichier appelant
$callerPath = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? __FILE__;
$callerDir = dirname($callerPath);
$publicPath = str_replace(BASE_PATH, '', PUBLIC_PATH);
$relativePath = '';

// Calculer la profondeur relative
$relativeFromCaller = str_replace(BASE_PATH, '', $callerDir);
$depth = substr_count($relativeFromCaller, DIRECTORY_SEPARATOR);
$relativePath = str_repeat('../', $depth) . ltrim($publicPath, '/\\');
$relativePath = rtrim($relativePath, '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" href="<?php echo $relativePath; ?>media/website/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo $relativePath; ?>css/style.css">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo $relativePath; ?>css/<?php echo $css; ?>.css">
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
