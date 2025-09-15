<?php $view = 'sms/queue'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><?= __('sms.queue') ?></h1>
        <p class="text-muted"><?= __('sms.queue_management') ?></p>
    </div>
    <div>
        <!-- Auto-refresh control -->
        <div class="dropdown me-2 d-inline-block">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="refreshDropdown" data-bs-toggle="dropdown">
                <i class="fas fa-sync-alt me-2"></i>
                <span id="refresh-status"><?= __('common.auto_refresh') ?>: <?= __('common.disabled') ?></span>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(0)">
                    <i class="fas fa-stop me-2"></i><?= __('common.disabled') ?>
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(5)">
                    <i class="fas fa-clock me-2"></i>5 <?= __('common.seconds') ?>
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(10)">
                    <i class="fas fa-clock me-2"></i>10 <?= __('common.seconds') ?>
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(15)">
                    <i class="fas fa-clock me-2"></i>15 <?= __('common.seconds') ?>
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(30)">
                    <i class="fas fa-clock me-2"></i>30 <?= __('common.seconds') ?>
                </a></li>
            </ul>
        </div>
        
        <!-- Auto-refresh control -->
        <div class="dropdown me-2 d-inline-block">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="refreshDropdown" data-bs-toggle="dropdown">
                <i class="fas fa-sync-alt me-2"></i>
                <span id="refresh-status"><?= __('common.auto_refresh') ?>: <?= __('common.disabled') ?></span>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(0)">
                    <i class="fas fa-stop me-2"></i><?= __('common.disabled') ?>
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(5)">
                    <i class="fas fa-clock me-2"></i>5 <?= __('common.seconds') ?>
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(10)">
                    <i class="fas fa-clock me-2"></i>10 <?= __('common.seconds') ?>
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(15)">
                    <i class="fas fa-clock me-2"></i>15 <?= __('common.seconds') ?>
                </a></li>
                <li><a class="dropdown-item" href="#" onclick="setAutoRefresh(30)">
                    <i class="fas fa-clock me-2"></i>30 <?= __('common.seconds') ?>
                </a></li>
            </ul>
        </div>
        
        <?php if (Auth::hasRole(ROLE_ADMIN)): ?>
            <button class="btn btn-outline-danger clear-queue me-2">
                <i class="fas fa-trash me-2"></i>
                <?= __('sms.clear_queue') ?>
            </button>
        <?php endif; ?>
        <a href="/sms/send" class="btn btn-primary">
            <i class="fas fa-paper-plane me-2"></i>
            <?= __('sms.new_sms') ?>
        </a>
    </div>
</div>

<!-- Tabs for SMS Queue and Received SMS -->
<ul class="nav nav-tabs mb-4" id="smsQueueTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="sent-sms-tab" data-bs-toggle="tab" data-bs-target="#sent-sms" type="button" role="tab">
            <i class="fas fa-paper-plane me-2"></i>
            <?= __('sms.sent_queue') ?>
            <span class="badge bg-primary ms-2" id="sent-count"><?= number_format($total ?? 0) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="received-sms-tab" data-bs-toggle="tab" data-bs-target="#received-sms" type="button" role="tab">
            <i class="fas fa-inbox me-2"></i>
            <?= __('sms.received_queue') ?>
            <span class="badge bg-info ms-2" id="received-count">0</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="smsQueueTabsContent">
    <!-- Sent SMS Tab -->
    <div class="tab-pane fade show active" id="sent-sms" role="tabpanel">
<!-- Filtres et recherche -->
<div class="card mb-4">
    <div class="card-body">
        <form id="search-form" class="row">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           id="search-sms" 
                           name="search"
                           placeholder="<?= __('sms.search_placeholder') ?>"
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filter-status" name="status">
                    <option value=""><?= __('sms.all_statuses') ?></option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= __('status.pending') ?></option>
                    <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>><?= __('status.processing') ?></option>
                    <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>><?= __('status.sent') ?></option>
                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>><?= __('status.failed') ?></option>
                    <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>><?= __('status.scheduled') ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search me-2"></i>
                    <?= __('common.search') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Table des SMS -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <?= __('sms.sms_list') ?> (<?= number_format($total) ?> <?= __('common.total') ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($sms_list)): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="sms-queue-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?= __('sms.recipient') ?></th>
                            <th><?= __('sms.message') ?></th>
                            <th><?= __('sms.status') ?></th>
                            <th><?= __('sms.created_at') ?></th>
                            <th><?= __('sms.sent_at') ?></th>
                            <th><?= __('sms.sender') ?></th>
                            <th><?= __('sms.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sms_list as $sms): ?>
                            <tr>
                                <td>
                                    <span class="font-monospace"><?= $sms['id'] ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($sms['recipient']) ?></strong>
                                </td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 250px;" 
                                          title="<?= htmlspecialchars($sms['message']) ?>">
                                        <?= htmlspecialchars($sms['message']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="queue-status <?= $sms['status'] ?>">
                                        <?= ucfirst($sms['status']) ?>
                                    </span>
                                    <?php if ($sms['error_code']): ?>
                                        <br>
                                        <small class="text-danger" title="<?= htmlspecialchars($sms['error_message'] ?? '') ?>">
                                            <?= htmlspecialchars($sms['error_code']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('d/m/Y H:i', strtotime($sms['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($sms['sent_at']): ?>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($sms['sent_at'])) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($sms['sender_name'] ?? 'Inconnu') ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if ($sms['status'] === 'failed'): ?>
                                            <button class="btn btn-outline-warning retry-sms" 
                                                    data-id="<?= $sms['id'] ?>"
                                                    title="<?= __('sms.retry') ?>">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if (Auth::hasRole(ROLE_SUPERVISOR)): ?>
                                            <button class="btn btn-outline-danger delete-sms" 
                                                    data-id="<?= $sms['id'] ?>"
                                                    title="<?= __('sms.delete') ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-outline-info" 
                                                onclick="showSmsDetails(<?= $sms['id'] ?>)"
                                                title="<?= __('sms.details') ?>">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                <h5 class="text-muted"><?= __('sms.no_sms_found') ?></h5>
                <p class="text-muted">
                    <?php if ($search || $status): ?>
                        <?= __('sms.modify_search_criteria') ?>
                    <?php else: ?>
                        <?= __('sms.send_first') ?>
                    <?php endif; ?>
                </p>
                <a href="/sms/send" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i>
                    <?= __('sms.send') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal détails SMS -->
<div class="modal fade" id="smsDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('sms.details') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="smsDetailsContent">
                <!-- Contenu chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.close') ?></button>
            </div>
        </div>
    </div>
</div>
    </div>
    
    <!-- Received SMS Tab -->
    <div class="tab-pane fade" id="received-sms" role="tabpanel">
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="search-received-sms" 
                                   placeholder="<?= __('sms.search_received_placeholder') ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="searchReceivedSms()">
                            <i class="fas fa-search me-2"></i>
                            <?= __('common.search') ?>
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-info w-100" onclick="refreshReceivedSms()">
                            <i class="fas fa-sync-alt me-2"></i>
                            <?= __('common.refresh_page') ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <?= __('sms.received_sms') ?> (<span id="received-total">0</span> <?= __('common.total') ?>)
                </h5>
            </div>
            <div class="card-body">
                <div id="received-sms-content">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary me-2"></div>
                        <?= __('common.loading') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh functionality
let autoRefreshInterval = null;
let currentRefreshRate = 0;

function setAutoRefresh(seconds) {
    // Clear existing interval
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    
    currentRefreshRate = seconds;
    const statusElement = document.getElementById('refresh-status');
    
    if (seconds === 0) {
        statusElement.textContent = '<?= __('common.auto_refresh') ?>: <?= __('common.disabled') ?>';
    } else {
        statusElement.textContent = `<?= __('common.auto_refresh') ?>: ${seconds} <?= __('common.seconds') ?>`;
        
        // Set new interval
        autoRefreshInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                const activeTab = document.querySelector('.nav-link.active').id;
                if (activeTab === 'sent-sms-tab') {
                    window.smsApp.updateQueueTable();
                    window.smsApp.updateQueueStatus();
                } else if (activeTab === 'received-sms-tab') {
                    refreshReceivedSms();
                }
            }
        }, seconds * 1000);
    }
    
    // Save preference to localStorage
    localStorage.setItem('sms_queue_refresh_rate', seconds);
}

// Load saved refresh preference
document.addEventListener('DOMContentLoaded', function() {
    const savedRate = localStorage.getItem('sms_queue_refresh_rate');
    if (savedRate !== null) {
        setAutoRefresh(parseInt(savedRate));
    }
    
    // Load received SMS when tab is shown
    document.getElementById('received-sms-tab').addEventListener('shown.bs.tab', function() {
        loadReceivedSms();
    });
    
    // Setup search for received SMS
    const searchReceivedInput = document.getElementById('search-received-sms');
    let searchReceivedTimeout;
    searchReceivedInput.addEventListener('input', function() {
        clearTimeout(searchReceivedTimeout);
        searchReceivedTimeout = setTimeout(searchReceivedSms, 500);
    });
});

function loadReceivedSms() {
    fetch('/api/sms/received')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderReceivedSms(data.sms);
                document.getElementById('received-count').textContent = data.total;
                document.getElementById('received-total').textContent = data.total.toLocaleString();
            } else {
                document.getElementById('received-sms-content').innerHTML = 
                    '<div class="alert alert-danger">' + (data.message || '<?= __('sms.load_error') ?>') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('received-sms-content').innerHTML = 
                '<div class="alert alert-danger"><?= __('sms.load_error') ?></div>';
        });
}

function renderReceivedSms(smsData) {
    const container = document.getElementById('received-sms-content');
    
    if (!smsData || smsData.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                <h5 class="text-muted"><?= __('sms.no_received_sms') ?></h5>
                <p class="text-muted"><?= __('sms.no_received_sms_desc') ?></p>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= __('sms.sender') ?></th>
                        <th><?= __('sms.message') ?></th>
                        <th><?= __('sms.received_at') ?></th>
                        <th><?= __('sms.modem') ?></th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    smsData.forEach(sms => {
        html += `
            <tr>
                <td><span class="font-monospace">${sms.id}</span></td>
                <td><strong>${sms.sender}</strong></td>
                <td>
                    <span class="text-truncate d-inline-block" style="max-width: 300px;" 
                          title="${sms.message}">
                        ${sms.message}
                    </span>
                </td>
                <td>
                    <small class="text-muted">
                        ${new Date(sms.received_at).toLocaleString()}
                    </small>
                </td>
                <td>
                    <small class="text-muted">
                        ${sms.modem_name || '<?= __('common.unknown') ?>'}
                    </small>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = html;
}

function searchReceivedSms() {
    const query = document.getElementById('search-received-sms').value;
    
    fetch(`/api/sms/received?search=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderReceivedSms(data.sms);
                document.getElementById('received-total').textContent = data.total.toLocaleString();
            }
        })
        .catch(error => {
            console.error('Search error:', error);
        });
}

function refreshReceivedSms() {
    loadReceivedSms();
}

// Auto-refresh functionality
let autoRefreshInterval = null;
let currentRefreshRate = 0;

function setAutoRefresh(seconds) {
    // Clear existing interval
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
    
    currentRefreshRate = seconds;
    const statusElement = document.getElementById('refresh-status');
    
    if (seconds === 0) {
        statusElement.textContent = '<?= __('common.auto_refresh') ?>: <?= __('common.disabled') ?>';
    } else {
        statusElement.textContent = `<?= __('common.auto_refresh') ?>: ${seconds} <?= __('common.seconds') ?>`;
        
        // Set new interval
        autoRefreshInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                window.smsApp.updateQueueTable();
                window.smsApp.updateQueueStatus();
            }
        }, seconds * 1000);
    }
    
    // Save preference to localStorage
    localStorage.setItem('sms_queue_refresh_rate', seconds);
}

// Load saved refresh preference
document.addEventListener('DOMContentLoaded', function() {
    const savedRate = localStorage.getItem('sms_queue_refresh_rate');
    if (savedRate !== null) {
        setAutoRefresh(parseInt(savedRate));
    }
    
    // Setup search form
    const searchForm = document.getElementById('search-form');
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        performSearch();
    });
    
    // Real-time search on input
    const searchInput = document.getElementById('search-sms');
    const statusFilter = document.getElementById('filter-status');
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 500); // Debounce search
    });
    
    statusFilter.addEventListener('change', performSearch);
});

function performSearch() {
    const query = document.getElementById('search-sms').value;
    const status = document.getElementById('filter-status').value;
    
    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('search', query);
    url.searchParams.set('status', status);
    url.searchParams.delete('page'); // Reset to first page
    window.history.replaceState({}, '', url);
    
    // Perform search via AJAX
    fetch(`/api/sms/list?search=${encodeURIComponent(query)}&status=${encodeURIComponent(status)}`, {
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.smsApp.renderSmsTable(data.sms);
            updatePagination(data);
        } else {
            window.smsApp.showToast('error', data.message || window.__('sms_search_error'));
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        window.smsApp.showToast('error', window.__('sms_search_error'));
    });
}

function updatePagination(data) {
    // Update total count in header
    const headerTitle = document.querySelector('.card-header .card-title');
    if (headerTitle) {
        headerTitle.textContent = `<?= __('sms.sms_list') ?> (${data.total.toLocaleString()} <?= __('common.total') ?>)`;
    }
    
    // Hide/show pagination based on results
    const pagination = document.querySelector('.pagination');
    if (pagination) {
        if (data.total_pages <= 1) {
            pagination.parentElement.style.display = 'none';
        } else {
            pagination.parentElement.style.display = 'block';
            // Update pagination links (simplified - you might want to rebuild completely)
        }
    }
}

function showSmsDetails(smsId) {
    fetch(`/api/sms/status/${smsId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const sms = data.sms;
                const content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?= __('sms.general_info') ?></h6>
                            <table class="table table-sm">
                                <tr><th><?= __('sms.recipient') ?>:</th><td>${sms.recipient}</td></tr>
                                <tr><th><?= __('sms.status') ?>:</th><td><span class="queue-status ${sms.status}">${sms.status}</span></td></tr>
                                <tr><th><?= __('sms.created_at') ?>:</th><td>${new Date(sms.created_at).toLocaleString()}</td></tr>
                                ${sms.scheduled_at ? `<tr><th><?= __('sms.scheduled_at') ?>:</th><td>${new Date(sms.scheduled_at).toLocaleString()}</td></tr>` : ''}
                                ${sms.sent_at ? `<tr><th><?= __('sms.sent_at') ?>:</th><td>${new Date(sms.sent_at).toLocaleString()}</td></tr>` : ''}
                                ${sms.error_code ? `<tr><th><?= __('sms.error_code') ?>:</th><td class="text-danger">${sms.error_code}</td></tr>` : ''}
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6><?= __('sms.message') ?></h6>
                            <div class="bg-light p-3 rounded">
                                ${sms.message || '<?= __('sms.no_message') ?>'}
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('smsDetailsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('smsDetailsModal')).show();
            } else {
                window.smsApp.showToast('error', window.__('details_load_error'));
            }
        })
        .catch(error => {
            window.smsApp.showToast('error', window.__('details_load_error'));
        });
}
</script>