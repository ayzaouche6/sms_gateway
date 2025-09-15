<?php $view = 'users/profile'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-user-cog me-2"></i>
                    Mon profil
                </h4>
            </div>
            <div class="card-body">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                
                <form method="POST" action="/profile">
                    <input type="hidden" name="csrf_token" value="<?= SecurityService::generateCSRFToken() ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">
                                Nom d'utilisateur <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       value="<?= htmlspecialchars($user['username']) ?>"
                                       required>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                Adresse email <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Rôle</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-shield-alt"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       value="<?= ucfirst($user['role']) ?>"
                                       readonly>
                            </div>
                            <div class="form-text">
                                Contactez un administrateur pour modifier votre rôle
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="created_at" class="form-label">Membre depuis</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-calendar"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       value="<?= date('d/m/Y', strtotime($user['created_at'])) ?>"
                                       readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="language" class="form-label"><?= __('users.language') ?></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-globe"></i>
                                </span>
                                <select class="form-select" id="language" name="language">
                                    <?php foreach (Language::getInstance()->getSupportedLanguages() as $code => $info): ?>
                                        <option value="<?= $code ?>" <?= ($user['language'] ?? 'fr') === $code ? 'selected' : '' ?>>
                                            <?= $info['flag'] ?> <?= $info['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-text">
                                <?= __('users.select_language') ?>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h5 class="mb-3">
                        <i class="fas fa-key me-2"></i>
                        Changer le mot de passe
                    </h5>
                    <p class="text-muted mb-3">Laissez vide pour conserver le mot de passe actuel</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Nouveau mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password"
                                       minlength="8">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Minimum 8 caractères avec majuscules, minuscules, chiffres et caractères spéciaux
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password_confirm" class="form-label">Confirmer le mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password_confirm" 
                                       name="password_confirm">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_confirm')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="/dashboard" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Retour au tableau de bord
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Mettre à jour le profil
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistiques utilisateur -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Mes statistiques
                </h5>
            </div>
            <div class="card-body">
                <?php
                $db = Database::getInstance();
                $stats = $db->selectOne("
                    SELECT 
                        COUNT(*) as total_sms,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_sms,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_sms,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_sms
                    FROM sms 
                    WHERE user_id = ?
                ", [Auth::id()]);
                
                $successRate = $stats['total_sms'] > 0 ? 
                    round(($stats['sent_sms'] / $stats['total_sms']) * 100, 1) : 0;
                ?>
                
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-primary mb-1"><?= number_format($stats['total_sms']) ?></h4>
                            <small class="text-muted">Total SMS</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-success mb-1"><?= number_format($stats['sent_sms']) ?></h4>
                            <small class="text-muted">Envoyés</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-warning mb-1"><?= number_format($stats['pending_sms']) ?></h4>
                            <small class="text-muted">En attente</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-info mb-1"><?= $successRate ?>%</h4>
                            <small class="text-muted">Taux de réussite</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Validation du mot de passe en temps réel
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const confirmField = document.getElementById('password_confirm');
    
    if (password.length > 0) {
        confirmField.required = true;
    } else {
        confirmField.required = false;
        confirmField.value = '';
    }
});

document.getElementById('password_confirm').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirm = this.value;
    
    if (password && confirm && password !== confirm) {
        this.setCustomValidity('Les mots de passe ne correspondent pas');
    } else {
        this.setCustomValidity('');
    }
});
</script>