<?php
/**
 * FOOTER - Inclure en bas de chaque page
 * 
 * Variables optionnelles:
 * - $showBackLink (bool) : Afficher le lien retour menu (défaut: true)
 */

$showBackLink = $showBackLink ?? true;

// Calculer le chemin relatif vers index.php en utilisant getBasePath()
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/autoload.php';
}
$indexPath = getBasePath() . 'index.php';
?>

<?php if ($showBackLink): ?>
<a href="<?php echo $indexPath; ?>" class="back-link">← Retour au menu</a>
<?php endif; ?>

</body>
</html>
