<?php
/**
 * FOOTER - Inclure en bas de chaque page
 * 
 * Variables optionnelles:
 * - $showBackLink (bool) : Afficher le lien retour menu (défaut: true)
 */

$showBackLink = $showBackLink ?? true;
?>

<?php if ($showBackLink): ?>
<a href="index.php" class="back-link">← Retour au menu</a>
<?php endif; ?>

</body>
</html>
