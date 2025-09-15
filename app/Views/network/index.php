<?php $view = 'network/index'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><?= __('network.configuration') ?></h1>
        <p class="text-muted"><?= __('network.management') ?></p>
    </div>
    <div>
        <button class="btn btn-outline-info me-2" onclick="testConnectivity()">
            <i class="fas fa-network-wired me-2"></i>
            <?= __('network.test_connectivity') ?>
        </button>
        <button class="btn btn-outline-secondary me-2" onclick="backupConfiguration()">
            <i class="fas fa-save me-2"></i>
            <?= __('network.backup') ?>
        </button>
        <button class="btn btn-outline-warning" onclick="restoreConfiguration()">
            <i class="fas fa-undo me-2"></i>
            <?= __('network.restore') ?>
        </button>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Configuration actuelle -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <?= __('network.current_config') ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($current_config)): ?>
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted"><?= __('network.network_interface') ?></label>
                            <div class="fw-bold"><?= htmlspecialchars($current_config['interface_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted"><?= __('network.status') ?></label>
                            <div>
                                <?php if (isset($network_interface['details']['status']) && $network_interface['details']['status'] === 'up'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <?= __('network.active') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times-circle me-1"></i>
                                        <?= __('network.inactive') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted"><?= __('network.primary_ip') ?></label>
                            <div class="fw-bold"><?= htmlspecialchars($current_config['primary_ip'] ?? 'N/A') ?>/<?= htmlspecialchars($current_config['subnet_mask'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted"><?= __('network.secondary_ip') ?></label>
                            <div class="fw-bold text-info"><?= htmlspecialchars($current_config['secondary_ip'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted"><?= __('network.gateway') ?></label>
                            <div class="fw-bold"><?= htmlspecialchars($current_config['gateway'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted"><?= __('network.mac_address') ?></label>
                            <div class="fw-bold font-monospace"><?= htmlspecialchars($network_interface['details']['mac_address'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted"><?= __('network.dns_primary') ?></label>
                            <div class="fw-bold"><?= htmlspecialchars($current_config['dns_primary'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <label class="form-label text-muted"><?= __('network.dns_secondary') ?></label>
                            <div class="fw-bold"><?= htmlspecialchars($current_config['dns_secondary'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                    
                    <?php if (isset($network_interface['details']['ip_addresses'])): ?>
                        <div class="mb-3">
                            <label class="form-label text-muted"><?= __('network.active_addresses') ?></label>
                            <div>
                                <?php foreach ($network_interface['details']['ip_addresses'] as $ip): ?>
                                    <span class="badge bg-light text-dark me-1"><?= htmlspecialchars($ip) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <p class="text-muted"><?= __('network.config_load_error') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modification de la configuration -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-edit me-2"></i>
                    <?= __('network.modify_config') ?>
                </h5>
            </div>
            <div class="card-body">
                <form id="network-config-form">
                    <input type="hidden" name="csrf_token" value="<?= SecurityService::generateCSRFToken() ?>">
                    
                    <div class="mb-3">
                        <label for="primary_ip" class="form-label">
                            <?= __('network.primary_ip') ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-network-wired"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="primary_ip" 
                                   name="primary_ip" 
                                   value="<?= htmlspecialchars($current_config['primary_ip'] ?? '') ?>"
                                   placeholder="192.168.1.10"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subnet_mask" class="form-label">
                            <?= __('network.subnet_mask') ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-layer-group"></i>
                            </span>
                            <select class="form-select" id="subnet_mask" name="subnet_mask" required>
                                <option value=""><?= __('common.select') ?></option>
                                <option value="8" <?= ($current_config['subnet_mask'] ?? '') === '8' ? 'selected' : '' ?>>/8 (255.0.0.0)</option>
                                <option value="16" <?= ($current_config['subnet_mask'] ?? '') === '16' ? 'selected' : '' ?>>/16 (255.255.0.0)</option>
                                <option value="24" <?= ($current_config['subnet_mask'] ?? '') === '24' ? 'selected' : '' ?>>/24 (255.255.255.0)</option>
                                <option value="25" <?= ($current_config['subnet_mask'] ?? '') === '25' ? 'selected' : '' ?>>/25 (255.255.255.128)</option>
                                <option value="26" <?= ($current_config['subnet_mask'] ?? '') === '26' ? 'selected' : '' ?>>/26 (255.255.255.192)</option>
                                <option value="27" <?= ($current_config['subnet_mask'] ?? '') === '27' ? 'selected' : '' ?>>/27 (255.255.255.224)</option>
                                <option value="28" <?= ($current_config['subnet_mask'] ?? '') === '28' ? 'selected' : '' ?>>/28 (255.255.255.240)</option>
                                <option value="29" <?= ($current_config['subnet_mask'] ?? '') === '29' ? 'selected' : '' ?>>/29 (255.255.255.248)</option>
                                <option value="30" <?= ($current_config['subnet_mask'] ?? '') === '30' ? 'selected' : '' ?>>/30 (255.255.255.252)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gateway" class="form-label">
                            <?= __('network.default_gateway') ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-route"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="gateway" 
                                   name="gateway" 
                                   value="<?= htmlspecialchars($current_config['gateway'] ?? '') ?>"
                                   placeholder="192.168.1.1"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dns_primary" class="form-label">
                            <?= __('network.dns_primary') ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-server"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="dns_primary" 
                                   name="dns_primary" 
                                   value="<?= htmlspecialchars($current_config['dns_primary'] ?? '') ?>"
                                   placeholder="1.1.1.1"
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dns_secondary" class="form-label">
                            <?= __('network.dns_secondary') ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-server"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="dns_secondary" 
                                   name="dns_secondary" 
                                   value="<?= htmlspecialchars($current_config['dns_secondary'] ?? '') ?>"
                                   placeholder="8.8.8.8"
                                   required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong><?= __('common.note') ?>:</strong> <?= __('network.fixed_note') ?>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>
                            <?= __('network.apply_config') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SSL Certificate Management -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-lock me-2"></i>
                    <?= __('network.ssl_management') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Current SSL Info -->
                    <div class="col-lg-6 mb-4">
                        <h6><?= __('network.current_ssl_cert') ?></h6>
                        <div id="ssl-info">
                            <div class="text-center">
                                <div class="spinner-border text-primary me-2"></div>
                                <?= __('common.loading') ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SSL Actions -->
                    <div class="col-lg-6 mb-4">
                        <h6><?= __('network.ssl_actions') ?></h6>
                        
                        <!-- Generate Self-Signed -->
                        <div class="mb-3">
                            <button class="btn btn-outline-primary w-100" onclick="generateSelfSignedCert()">
                                <i class="fas fa-certificate me-2"></i>
                                <?= __('network.generate_self_signed') ?>
                            </button>
                            <small class="form-text text-muted">
                                <?= __('network.generate_self_signed_desc') ?>
                            </small>
                        </div>
                        
                        <!-- Upload Custom Certificate -->
                        <div class="mb-3">
                            <button class="btn btn-outline-success w-100" onclick="showUploadModal()">
                                <i class="fas fa-upload me-2"></i>
                                <?= __('network.upload_custom_cert') ?>
                            </button>
                            <small class="form-text text-muted">
                                <?= __('network.upload_custom_cert_desc') ?>
                            </small>
                        </div>
                        
                        <!-- Restore Default -->
                        <div class="mb-3">
                            <button class="btn btn-outline-warning w-100" onclick="restoreDefaultSSL()">
                                <i class="fas fa-undo me-2"></i>
                                <?= __('network.restore_default_ssl') ?>
                            </button>
                            <small class="form-text text-muted">
                                <?= __('network.restore_default_ssl_desc') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test de connectivité -->
<div class="row">
    <div class="col-12">
        <div class="card" id="connectivity-test" style="display: none;">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-wifi me-2"></i>
                    <?= __('network.connectivity_test') ?>
                </h5>
            </div>
            <div class="card-body">
                <div id="connectivity-results">
                    <!-- Résultats du test -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SSL Upload Modal -->
<div class="modal fade" id="sslUploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('network.upload_ssl_certificate') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ssl-upload-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= SecurityService::generateCSRFToken() ?>">
                    
                    <div class="mb-3">
                        <label for="cert_file" class="form-label">
                            <?= __('network.certificate_file') ?> <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="cert_file" name="cert_file" 
                               accept=".crt,.pem,.cer" required>
                        <div class="form-text">
                            <?= __('network.certificate_file_desc') ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="key_file" class="form-label">
                            <?= __('network.private_key_file') ?> <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" id="key_file" name="key_file" 
                               accept=".key,.pem" required>
                        <div class="form-text">
                            <?= __('network.private_key_file_desc') ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= __('network.ssl_upload_warning') ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?= __('common.cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" onclick="uploadSSLCertificate()">
                    <i class="fas fa-upload me-2"></i>
                    <?= __('network.install_certificate') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('network-config-form');
    
    // Load SSL info on page load
    loadSSLInfo();
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('<?= __('network.config_warning') ?>')) {
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><?= __('common.processing') ?>';
        
        const formData = new FormData(form);
        
        fetch('/network/update', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.smsApp.showToast('success', data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                window.smsApp.showToast('error', data.message);
            }
        })
        .catch(error => {
            window.smsApp.showToast('error', 'Erreur lors de la mise à jour de la configuration');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});

function loadSSLInfo() {
    fetch('/network/ssl/info')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySSLInfo(data.ssl_info);
            } else {
                document.getElementById('ssl-info').innerHTML = 
                    '<div class="alert alert-warning">' + window.__('network.ssl_info_error') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('ssl-info').innerHTML = 
                '<div class="alert alert-danger">' + window.__('network.ssl_info_error') + '</div>';
        });
}

function displaySSLInfo(info) {
    const container = document.getElementById('ssl-info');
    
    if (!info.exists) {
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                ${window.__('network.no_ssl_cert')}
            </div>
        `;
        return;
    }
    
    const validUntil = new Date(info.valid_until);
    const now = new Date();
    const daysLeft = Math.ceil((validUntil - now) / (1000 * 60 * 60 * 24));
    
    let statusBadge = '';
    if (daysLeft > 30) {
        statusBadge = '<span class="badge bg-success">Valide</span>';
    } else if (daysLeft > 0) {
        statusBadge = '<span class="badge bg-warning">Expire bientôt</span>';
    } else {
        statusBadge = '<span class="badge bg-danger">Expiré</span>';
    }
    
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-sm">
                <tr><th>${window.__('network.ssl_status')}:</th><td>${statusBadge}</td></tr>
                <tr><th>${window.__('network.ssl_subject')}:</th><td><small>${info.subject || 'N/A'}</small></td></tr>
                <tr><th>${window.__('network.ssl_issuer')}:</th><td><small>${info.issuer || 'N/A'}</small></td></tr>
                <tr><th>${window.__('network.ssl_valid_from')}:</th><td>${info.valid_from || 'N/A'}</td></tr>
                <tr><th>${window.__('network.ssl_valid_until')}:</th><td>${info.valid_until || 'N/A'}</td></tr>
                <tr><th>${window.__('network.ssl_days_left')}:</th><td>${daysLeft} ${window.__('common.days')}</td></tr>
            </table>
        </div>
    `;
}

function generateSelfSignedCert() {
    if (!confirm(window.__('network.confirm_generate_ssl'))) {
        return;
    }
    
    const originalBtn = event.target;
    const originalText = originalBtn.innerHTML;
    
    originalBtn.disabled = true;
    originalBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + window.__('common.processing');
    
    fetch('/network/ssl/generate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?= SecurityService::generateCSRFToken() ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.smsApp.showToast('success', data.message);
            loadSSLInfo();
        } else {
            window.smsApp.showToast('error', data.message);
        }
    })
    .catch(error => {
        window.smsApp.showToast('error', window.__('network.ssl_generation_error'));
    })
    .finally(() => {
        originalBtn.disabled = false;
        originalBtn.innerHTML = originalText;
    });
}

function showUploadModal() {
    new bootstrap.Modal(document.getElementById('sslUploadModal')).show();
}

function uploadSSLCertificate() {
    const form = document.getElementById('ssl-upload-form');
    const formData = new FormData(form);
    
    const certFile = document.getElementById('cert_file').files[0];
    const keyFile = document.getElementById('key_file').files[0];
    
    if (!certFile || !keyFile) {
        window.smsApp.showToast('error', window.__('network.ssl_files_required'));
        return;
    }
    
    const submitBtn = event.target;
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + window.__('common.processing');
    
    fetch('/network/ssl/upload', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.smsApp.showToast('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('sslUploadModal')).hide();
            loadSSLInfo();
        } else {
            window.smsApp.showToast('error', data.message);
        }
    })
    .catch(error => {
        window.smsApp.showToast('error', window.__('network.ssl_upload_error'));
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

function restoreDefaultSSL() {
    if (!confirm(window.__('network.confirm_restore_ssl'))) {
        return;
    }
    
    fetch('/network/ssl/restore', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?= SecurityService::generateCSRFToken() ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.smsApp.showToast('success', data.message);
            loadSSLInfo();
        } else {
            window.smsApp.showToast('error', data.message);
        }
    })
    .catch(error => {
        window.smsApp.showToast('error', window.__('network.ssl_restore_error'));
    });
}

function testConnectivity() {
    const testCard = document.getElementById('connectivity-test');
    const resultsDiv = document.getElementById('connectivity-results');
    
    testCard.style.display = 'block';
    resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary me-2"></div><?= __('common.testing') ?></div>';
    
    fetch('/network/test')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayConnectivityResults(data.results);
            } else {
                resultsDiv.innerHTML = '<div class="alert alert-danger"><?= __('network.test_error') ?>: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            resultsDiv.innerHTML = '<div class="alert alert-danger"><?= __('network.connectivity_error') ?></div>';
        });
}

function backupConfiguration() {
    if (!confirm(window.__('network.backup_confirm'))) {
        return;
    }
    
    fetch('/network/backup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?= SecurityService::generateCSRFToken() ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.smsApp.showToast('success', data.message);
        } else {
            window.smsApp.showToast('error', data.message);
        }
    })
    .catch(error => {
        window.smsApp.showToast('error', window.__('network.backup_error'));
    });
}

function restoreConfiguration() {
    if (!confirm(window.__('network.restore_confirm'))) {
        return;
    }
    
    fetch('/network/restore', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '<?= SecurityService::generateCSRFToken() ?>'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.smsApp.showToast('success', data.message);
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            window.smsApp.showToast('error', data.message);
        }
    })
    .catch(error => {
        window.smsApp.showToast('error', window.__('network.restore_error'));
    });
}

function displayConnectivityResults(results) {
    const resultsDiv = document.getElementById('connectivity-results');
    
    let html = '<div class="row">';
    
    const tests = [
        { key: 'gateway', label: '<?= __('network.gateway_test') ?>', icon: 'route' },
        { key: 'dns_primary', label: '<?= __('network.dns_test') ?>', icon: 'server' },
        { key: 'dns_secondary', label: '<?= __('network.dns_test') ?>', icon: 'server' },
        { key: 'internet', label: '<?= __('network.internet_test') ?>', icon: 'globe' }
    ];
    
    tests.forEach(test => {
        const status = results.tests[test.key];
        const badgeClass = status ? 'success' : 'danger';
        const iconClass = status ? 'check-circle' : 'times-circle';
        
        html += `
            <div class="col-md-3 mb-3">
                <div class="text-center">
                    <i class="fas fa-${test.icon} fa-2x text-${badgeClass} mb-2"></i>
                    <h6>${test.label}</h6>
                    <span class="badge bg-${badgeClass}">
                        <i class="fas fa-${iconClass} me-1"></i>
                        ${status ? '<?= __('network.test_ok') ?>' : '<?= __('network.test_failed') ?>'}
                    </span>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    const overallStatus = results.overall_status;
    const alertClass = overallStatus ? 'success' : 'warning';
    const alertIcon = overallStatus ? 'check-circle' : 'exclamation-triangle';
    
    html += `
        <div class="alert alert-${alertClass} mt-3">
            <i class="fas fa-${alertIcon} me-2"></i>
            <strong><?= __('network.overall_status') ?>:</strong> ${overallStatus ? '<?= __('network.all_tests_passed') ?>' : '<?= __('network.some_tests_failed') ?>'}
        </div>
    `;
    
    resultsDiv.innerHTML = html;
}
</script>