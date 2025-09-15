<?php $view = 'login'; ?>

<div class="login-container d-flex align-items-center justify-content-center">
    <div class="login-card p-5" style="width: 100%; max-width: 400px;">
        <div class="text-center mb-4">
            <i class="fas fa-sms fa-3x text-primary mb-3"></i>
            <h2 class="fw-bold"><?= __('app.name') ?></h2>
            <p class="text-muted"><?= __('auth.login_subtitle') ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $field => $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="/login">
            <input type="hidden" name="csrf_token" value="<?= SecurityService::generateCSRFToken() ?>">
            
            <div class="mb-3">
                <label for="email" class="form-label"><?= __('auth.email') ?></label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           value="<?= htmlspecialchars($email ?? '') ?>"
                           required 
                           autocomplete="email">
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label"><?= __('auth.password') ?></label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           required 
                           autocomplete="current-password">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>
                <?= __('auth.login_button') ?>
            </button>
            
            <div class="text-center">
                <small class="text-muted">
                    <?= __('auth.secured_by') ?> <?= __('app.name') ?> v<?= APP_VERSION ?>
                </small>
            </div>
        </form>
    </div>
</div>

<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.login-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}
</style>