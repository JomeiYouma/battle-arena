<div class="auth-container">
    <div class="auth-box">
        <h2>Connexion</h2>
        
        <?php if ($success): ?>
            <div class="success-msg">Compte créé avec succès ! Connectez-vous.</div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= View::e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="<?= View::url('/login') ?>" class="auth-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus 
                       value="<?= View::e($email) ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit">Se connecter</button>
        </form>
        
        <div class="auth-links">
            <a href="<?= View::url('/register') ?>">Pas encore de compte ? S'inscrire</a>
        </div>
    </div>
</div>
