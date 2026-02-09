<!-- DÉCOR ANIMÉ - Braises flottantes -->
<div class="embers-container">
    <div class="ember"></div>
    <div class="ember"></div>
    <div class="ember"></div>
    <div class="ember"></div>
    <div class="ember"></div>
    <div class="ember"></div>
    <div class="ember"></div>
    <div class="ember"></div>
</div>

<!-- ÉLÉMENT CENTRAL INTERACTIF -->
<div class="geometric-container" id="geometricContainer">
    <!-- Anneaux concentriques -->
    <div class="hexagon-ring ring-1"></div>
    <div class="hexagon-ring ring-2"></div>
    <div class="hexagon-ring ring-3"></div>
    <div class="hexagon-ring ring-4"></div>
    <div class="hexagon-ring ring-5"></div>
    
    <!-- Particules orbitales -->
    <div class="orbital-particle p1"></div>
    <div class="orbital-particle p2"></div>
    <div class="orbital-particle p3"></div>
    <div class="orbital-particle p4"></div>
    
    <!-- Triangles décoratifs -->
    <div class="triangle t1"></div>
    <div class="triangle t2"></div>
    <div class="triangle t3"></div>
    
    <!-- Lignes de connexion -->
    <div class="connection-line l1"></div>
    <div class="connection-line l2"></div>
    <div class="connection-line l3"></div>
    <div class="connection-line l4"></div>
    
    <!-- Cœur central -->
    <div class="center-core">
        <div class="inner-core"></div>
    </div>
</div>

<div class="menu-container">
    <form method="POST" action="<?= View::url('/') ?>">
        <button type="submit" name="mode" value="single" class="menu-btn">
            Solo
        </button>
        
        <button type="submit" name="mode" value="multi" class="menu-btn">
            Multijoueur
        </button>
    </form>
    
    <a href="<?= View::url('/game/simulation') ?>" class="menu-btn simulate-link">
        Simuler
    </a>
    
    <?php if (User::isLoggedIn()): ?>
        <a href="<?= View::url('/account') ?>" class="menu-btn account-link">
            Mon compte
        </a>
    <?php else: ?>
        <a href="<?= View::url('/login') ?>" class="menu-btn account-link">
            Connexion
        </a>
    <?php endif; ?>
    
    <!-- DEBUG TOOLS (visible localement) -->
    <?php if ($isLocalhost): ?>
        <a href="<?= View::url('/debug') ?>" class="menu-btn debug-btn">
            Debug
        </a>
    <?php endif; ?>
</div>

<script>
    // Effet de suivi de souris
    const container = document.getElementById('geometricContainer');
    document.addEventListener('mousemove', (e) => {
        const x = (e.clientX / window.innerWidth - 0.5) * 30;
        const y = (e.clientY / window.innerHeight - 0.5) * 30;
        container.style.transform = `translate(-50%, -50%) rotateY(${x}deg) rotateX(${-y}deg)`;
    });
</script>
