<?php $view = 'users/edit'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-user-edit me-2"></i>
                    Modifier l'utilisateur
                </h4>
            </div>
            <div class="card-body">
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
                
                <form method="POST" action="/users/<?= $user['id'] ?>/update">
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
                            <label for="role" class="form-label">
                                Rôle <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-shield-alt"></i>
                                </span>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Sélectionner un rôle</option>
                                    <option value="operator" <?= $user['role'] === 'operator' ? 'selected' : '' ?>>
                                        Opérateur
                                    </option>
                                    <option value="supervisor" <?= $user['role'] === 'supervisor' ? 'selected' : '' ?>>
                                        Superviseur
                                    </option>
                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>
                                        Administrateur
                                    </option>
                                </select>
                            </div>
                            <div class="form-text">
                                <small>
                                    <strong>Opérateur :</strong> Envoi de SMS<br>
                                    <strong>Superviseur :</strong> + Rapports et statistiques<br>
                                    <strong>Administrateur :</strong> + Gestion des utilisateurs
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Statut</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_active" 
                                       name="is_active" 
                                       <?= $user['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Compte actif
                                </label>
                            </div>
                            <div class="form-text">
                                Un compte inactif ne peut pas se connecter
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
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Informations</label>
                            <div class="bg-light p-3 rounded">
                                <small class="text-muted">
                                    <strong>Créé le :</strong> <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?><br>
                                    <strong>Dernière connexion :</strong> 
                                    <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais' ?><br>
                                    <strong>Nombre de connexions :</strong> <?= number_format($user['login_count'] ?? 0) ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Statistiques SMS</label>
                            <?php
                            $db = Database::getInstance();
                            $stats = $db->selectOne("
                                SELECT 
                                    COUNT(*) as total_sms,
                                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_sms,
                                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_sms
                                FROM sms 
                                WHERE user_id = ?
                            ", [$user['id']]);
                            ?>
                            <div class="bg-light p-3 rounded">
                                <small class="text-muted">
                                    <strong>Total SMS :</strong> <?= number_format($stats['total_sms']) ?><br>
                                    <strong>Envoyés :</strong> <?= number_format($stats['sent_sms']) ?><br>
                                    <strong>Échoués :</strong> <?= number_format($stats['failed_sms']) ?>
                                </small>
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
                        <a href="/users" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>
                            Retour à la liste
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Mettre à jour l'utilisateur
                        </button>
                    </div>
                </form>
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

// Validation de la force du mot de passe
document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    
    if (password.length > 0) {
        const requirements = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^a-zA-Z0-9]/.test(password)
        };
        
        const allMet = Object.values(requirements).every(req => req);
        
        if (!allMet) {
            this.setCustomValidity('Le mot de passe doit contenir au moins 8 caractères avec majuscules, minuscules, chiffres et caractères spéciaux');
        } else {
            this.setCustomValidity('');
        }
    } else {
        this.setCustomValidity('');
    }
});
</script>