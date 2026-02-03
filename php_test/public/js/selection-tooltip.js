/**
 * SYSTÈME DE TOOLTIP PARTAGÉ
 * Utilisé pour afficher les descriptions des compétences et bénédictions
 */

function initializeTooltipSystem() {
    const tooltip = document.getElementById('customTooltip');
    if (!tooltip) return;
    
    document.addEventListener('mouseover', function(e) {
        const target = e.target.closest('[data-tooltip]');
        if (target) {
            const text = target.getAttribute('data-tooltip');
            tooltip.textContent = text;
            tooltip.classList.add('visible');
            positionTooltip(e);
        }
    });
    
    document.addEventListener('mouseout', function(e) {
        const target = e.target.closest('[data-tooltip]');
        if (target) {
            tooltip.classList.remove('visible');
        }
    });
    
    document.addEventListener('mousemove', function(e) {
        if (tooltip.classList.contains('visible')) {
            positionTooltip(e);
        }
    });
    
    function positionTooltip(e) {
        const padding = 8;
        const offsetX = 8; // Distance à droite du curseur
        const offsetY = 8; // Distance en dessous du curseur
        
        let x = e.clientX + offsetX; // À droite du curseur
        let y = e.clientY + offsetY; // En dessous du curseur
        
        // Si pas de place à droite, mettre à gauche
        if (x + tooltip.offsetWidth > window.innerWidth - padding) {
            x = e.clientX - tooltip.offsetWidth - offsetX;
        }
        
        // Éviter le débordement en bas
        if (y + tooltip.offsetHeight > window.innerHeight - padding) {
            y = e.clientY - tooltip.offsetHeight - offsetY;
        }
        
        tooltip.style.left = x + 'px';
        tooltip.style.top = y + 'px';
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTooltipSystem);
} else {
    initializeTooltipSystem();
}
