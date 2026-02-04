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
$basePath = getBasePath();
$indexPath = $basePath . 'index.php';
$legalPath = $basePath . 'pages/legal.php';
?>

<footer class="site-footer">
    <?php if ($showBackLink): ?>
    <a href="<?php echo $indexPath; ?>" class="back-link">← Retour au menu</a>
    <?php endif; ?>
    
    <div class="footer-links">
        <a href="<?php echo $legalPath; ?>">Mentions légales & Crédits</a>
        <span class="separator">|</span>
        <span class="copyright">© <?php echo date('Y'); ?> Horus Battle Arena</span>
    </div>
</footer>

</body>
</html>
