<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<link rel="stylesheet" href="./style.css">
<link rel="stylesheet" href="./css/shared-selection.css">
<style>
    /* Styles spécifiques déplacés ici ou dans un CSS à part si tu préfères */
    .mode-cards {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .mode-card {
        background: linear-gradient(145deg, rgba(20, 20, 20, 0.9), rgba(30, 30, 30, 0.95));
        border: 2px solid #444;
        border-radius: 12px;
        padding: 2rem;
        width: 300px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: var(--text-light);
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    
    .mode-card:hover {
        transform: translateY(-5px);
        border-color: var(--gold-accent);
        box-shadow: 0 10px 20px rgba(184, 134, 11, 0.2);
    }
    
    .mode-card h2 {
        font-size: 2rem;
        margin: 1rem 0;
        color: var(--gold-accent);
    }
    
    .mode-card p {
        color: #aaa;
        margin-bottom: 2rem;
        line-height: 1.5;
    }
    
    .mode-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
    
    .mode-btn {
        background: var(--stone-gray);
        color: #fff;
        border: 1px solid var(--gold-accent);
        padding: 0.8rem 2rem;
        border-radius: 6px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.2s;
        margin-top: auto;
    }
    
    .mode-card:hover .mode-btn {
        background: var(--gold-accent);
        color: #000;
    }
    
    /* Specific Styles */
    .card-1v1 {
        border-color: #4a90e2; /* Blue tint for 1v1 */
    }
    .card-1v1:hover {
        border-color: #4a90e2;
        box-shadow: 0 10px 20px rgba(74, 144, 226, 0.2);
    }
    .card-1v1 .mode-btn:hover {
        background: #4a90e2;
    }
    
    .card-5v5 {
        border-color: #e24a4a; /* Red tint for 5v5 */
    }
    .card-5v5:hover {
        border-color: #e24a4a;
        box-shadow: 0 10px 20px rgba(226, 74, 74, 0.2);
    }
    .card-5v5 .mode-btn:hover {
        background: #e24a4a;
    }
    
    .back-btn {
        color: #888;
        text-decoration: none;
        margin-top: 2rem;
        font-size: 0.9rem;
        transition: color 0.2s;
    }
    .back-btn:hover {
        color: #fff;
    }

    h2 .page-title{
        margin-bottom: 25vw;
    }
</style>

<!-- Note: .mode-container est déjà ouvert par index.php -->
<h2 class="page-title">CHOISIR LE MODE DE JEU</h2>

<div class="mode-cards">
    <!-- 1v1 DUEL -->
    <a href="multiplayer-selection.php" class="mode-card card-1v1">
        <div class="mode-icon">⚔️</div>
        <h2>DUEL 1v1</h2>
        <p>Affrontez un adversaire en combat singulier.<br>Choisissez votre meilleur champion.</p>
        <span class="mode-btn">Sélectionner</span>
    </a>
    
    <!-- 5v5 TEAM -->
    <a href="multiplayer_5v5_setup.php" class="mode-card card-5v5">
        <div class="mode-icon">🛡️</div>
        <h2>ÉQUIPE 5v5</h2>
        <p>Dirigez une escouade de 5 héros.<br>Stratégie d'équipe et switch tactique.</p>
        <span class="mode-btn">Sélectionner</span>
    </a>
</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
