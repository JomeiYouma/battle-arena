<div class="auth-container">
    <div class="auth-box">
        <h2>Inscription</h2>
        
        <?php if ($error): ?>
            <div class="error-msg"><?= View::e($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="<?= View::url('/register') ?>" class="auth-form">
            <div class="form-group">
                <label for="username">Pseudo</label>
                <input type="text" id="username" name="username" required autofocus 
                       minlength="3" maxlength="50"
                       value="<?= View::e($username) ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required
                       value="<?= View::e($email) ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required minlength="6">
                <div class="password-hint">Minimum 6 caractères</div>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            
            <button type="submit">Créer mon compte</button>
        </form>
        
        <div class="auth-links">
            <a href="<?= View::url('/login') ?>">Déjà un compte ? Se connecter</a>
        </div>
    </div>
</div>
