<?php $view = 'users/index'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Gestion des utilisateurs</h1>
        <p class="text-muted">Administration des comptes utilisateurs</p>
    </div>
    <div>
        <a href="/users/create" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>
            Nouvel utilisateur
        </a>
    </div>
</div>

<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           name="search" 
                           placeholder="Rechercher par nom d'utilisateur ou email..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search me-2"></i>
                    Rechercher
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($_GET['success']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Table des utilisateurs -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            Utilisateurs (<?= number_format($total ?? 0) ?> au total)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Dernière connexion</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                            <?php if ($user['id'] == Auth::id()): ?>
                                                <span class="badge bg-info ms-1">Vous</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php
                                    $roleColors = [
                                        'admin' => 'danger',
                                        'supervisor' => 'warning',
                                        'operator' => 'info'
                                    ];
                                    $roleColor = $roleColors[$user['role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $roleColor ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>" 
                                          id="status-badge-<?= $user['id'] ?>">
                                        <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Jamais</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="/users/<?= $user['id'] ?>/edit" 
                                           class="btn btn-outline-primary"
                                           title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user['id'] != Auth::id()): ?>
                                            <button class="btn btn-outline-<?= $user['is_active'] ? 'warning' : 'success' ?>" 
                                                    onclick="toggleUserStatus(<?= $user['id'] ?>)"
                                                    title="<?= $user['is_active'] ? 'Désactiver' : 'Activer' ?>"
                                                    id="toggle-btn-<?= $user['id'] ?>">
                                                <i class="fas fa-<?= $user['is_active'] ? 'user-slash' : 'user-check' ?>"></i>
                                            </button>
                                            
                                            <button class="btn btn-outline-danger" 
                                                    onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if (($total_pages ?? 1) > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun utilisateur trouvé</h5>
                <p class="text-muted">
                    <?php if ($search): ?>
                        Essayez de modifier vos critères de recherche.
                    <?php else: ?>
                        Commencez par créer votre premier utilisateur.
                    <?php endif; ?>
                </p>
                <a href="/users/create" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>
                    Créer un utilisateur
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (Auth::hasRole(ROLE_ADMIN)): ?>
    <!-- Failed Login Attempts Table -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-shield-alt me-2"></i>
                <?= __('security.failed_login_attempts') ?>
            </h5>
        </div>
        <div class="card-body">
            <?php
            $db = Database::getInstance();
            $failedAttempts = $db->select("
                SELECT la.*, u.username 
                FROM login_attempts la
                LEFT JOIN users u ON la.user_id = u.id
                WHERE la.success = 0
                ORDER BY la.created_at DESC
                LIMIT 20
            ");
            ?>
            
            <?php if (!empty($failedAttempts)): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th><?= __('security.email') ?></th>
                                <th><?= __('security.ip_address') ?></th>
                                <th><?= __('security.failure_reason') ?></th>
                                <th><?= __('security.attempt_time') ?></th>
                                <th><?= __('security.user_agent') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failedAttempts as $attempt): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($attempt['email']) ?>
                                        <?php if ($attempt['username']): ?>
                                            <br><small class="text-muted">(<?= htmlspecialchars($attempt['username']) ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars($attempt['ip_address']) ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <?= htmlspecialchars($attempt['failure_reason'] ?? __('security.invalid_credentials')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i:s', strtotime($attempt['created_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted" title="<?= htmlspecialchars($attempt['user_agent'] ?? '') ?>">
                                            <?= htmlspecialchars(substr($attempt['user_agent'] ?? '', 0, 50)) ?>
                                            <?= strlen($attempt['user_agent'] ?? '') > 50 ? '...' : '' ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-3">
                    <i class="fas fa-shield-check fa-2x text-success mb-2"></i>
                    <p class="text-muted"><?= __('security.no_failed_attempts') ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleUserStatus(userId) {
    if (!confirm(window.__('confirm_user_status_change'))) {
        return;
    }
    
    fetch(`/users/${userId}/toggle-status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?= SecurityService::generateCSRFToken() ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const badge = document.getElementById(`status-badge-${userId}`);
            const button = document.getElementById(`toggle-btn-${userId}`);
            const icon = button.querySelector('i');
            
            if (data.new_status) {
                badge.className = 'badge bg-success';
                badge.textContent = 'Actif';
                button.className = 'btn btn-outline-warning';
                button.title = 'Désactiver';
                icon.className = 'fas fa-user-slash';
            } else {
                badge.className = 'badge bg-secondary';
                badge.textContent = 'Inactif';
                button.className = 'btn btn-outline-success';
                button.title = 'Activer';
                icon.className = 'fas fa-user-check';
            }
            
            window.smsApp.showToast('success', window.__('user_status_updated'));
        } else {
            window.smsApp.showToast('error', data.message);
        }
    })
    .catch(error => {
        window.smsApp.showToast('error', window.__('network_error'));
    });
}

function deleteUser(userId, username) {
    const confirmMessage = window.__('confirm_delete_user').replace(':username', username);
    if (!confirm(confirmMessage)) {
        return;
    }
    
    fetch(`/users/${userId}/delete`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?= SecurityService::generateCSRFToken() ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.smsApp.showToast('success', window.__('user_deleted'));
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            window.smsApp.showToast('error', data.message);
        }
    })
    .catch(error => {
        window.smsApp.showToast('error', window.__('sms_delete_error'));
    });
}
</script>

<style>
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
    font-weight: 600;
}
</style>