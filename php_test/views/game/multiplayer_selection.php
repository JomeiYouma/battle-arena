<?php
/** VUE: Multiplayer Selection - Sélection héros + Queue 1v1 */
?>

<div class="multi-container">
    <!-- ÉCRAN 1: SÉLECTION DU HÉROS (avec composant partagé) -->
    <div id="selectionScreen">
        <?php
        // Inclure le composant de sélection
        include COMPONENTS_PATH . '/selection-screen.php';
        
        // Préparer la configuration
        $selectionConfig = [
            'mode' => 'multiplayer',
            'showPlayerNameInput' => true,
            'displayNameValue' => $displayNameValue ?? null,
            'displayNameIsStatic' => $displayNameIsStatic ?? false
        ];
        
        renderSelectionScreen($selectionConfig);
        ?>
    </div>

    <!-- ÉCRAN 2: QUEUE D'ATTENTE -->
    <div class="queue-screen" id="queueScreen">
        <div class="queue-loader"></div>
        
        <h2 class="queue-title">Recherche d'adversaire...</h2>
        
        <div class="queue-box">
            <div class="queue-hero-preview" id="heroPreview">
                <img src="" alt="Hero" id="previewImg">
                <div class="info">
                    <h4 id="previewName">-</h4>
                    <span id="previewType">-</span>
                </div>
            </div>
            
            <div class="queue-countdown" id="countdown">30</div>
            <div class="queue-countdown-label">secondes restantes</div>
            <div id="queueInfo" class="queue-info">Connexion...</div>
        </div>
        
        <p class="queue-message">
            Si aucun joueur ne se présente,<br>
            un <strong>bot</strong> vous affrontera !
        </p>
        
        <button type="button" class="cancel-queue-btn" onclick="cancelQueue()">
            Annuler la recherche
        </button>
    </div>

</div>

<!-- Tooltip System -->
<div id="customTooltip" class="custom-tooltip"></div>
<script src="<?php echo View::asset('js/selection-tooltip.js'); ?>"></script>
<script>
// Configuration API pour le mode MVC
window.API_BASE_PATH = '<?php echo View::url("api"); ?>';
window.APP_BASE_PATH = '<?php echo View::url(""); ?>';
</script>
<script src="<?php echo View::asset('js/multiplayer-selection.js'); ?>"></script>
