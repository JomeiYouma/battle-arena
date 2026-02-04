<?php
/** HEADER - Template haut de page. Options: $pageTitle, $extraCss, $showMainTitle, $showUserBadge */

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
// [1] = le fichier qui a fait require_once du header (pas [0] qui est header.php lui-même)
$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
$callerPath = $backtrace[1]['file'] ?? $backtrace[0]['file'] ?? __FILE__;
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
    <link rel="stylesheet" href="<?php echo $relativePath; ?>css/layout.css">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo $relativePath; ?>css/<?php echo $css; ?>.css">
    <?php endforeach; ?>
</head>
<body>


<h1>Horus Battle Arena</h1>


<?php if ($showUserBadge && User::isLoggedIn()): ?>
<div class="user-badge">
    ⚔️ <?php echo htmlspecialchars(User::getCurrentUsername()); ?>
</div>
<?php endif; ?>
