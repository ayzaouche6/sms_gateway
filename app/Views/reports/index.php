<?php $view = 'reports/index'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><?= __('reports.title') ?></h1>
        <p class="text-muted"><?= __('reports.subtitle') ?></p>
    </div>
    <div>
        <a href="/reports/export?format=csv&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
           class="btn btn-outline-primary">
            <i class="fas fa-download me-2"></i>
            <?= __('reports.export_csv') ?>
        </a>
    </div>
</div>

<!-- Filtres de période -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="date_from" class="form-label"><?= __('reports.date_from') ?></label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label"><?= __('reports.date_to') ?></label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to ?>">
            </div>
            <div class="col-md-3">
                <label for="user_id" class="form-label"><?= __('reports.user_filter') ?></label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value=""><?= __('reports.all_users') ?></option>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_id == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block w-100">
                    <i class="fas fa-filter me-2"></i>
                    <?= __('reports.filter') ?>
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
<?php else: ?>
    <!-- Statistiques principales -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <i class="fas fa-sms fa-2x mb-2"></i>
                    <h3 class="card-title"><?= number_format($stats['total_sms']) ?></h3>
                    <p class="card-text"><?= __('reports.total_sms') ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card success">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <h3 class="card-title"><?= number_format($stats['sent_sms']) ?></h3>
                    <p class="card-text"><?= __('reports.sent') ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card danger">
                <div class="card-body text-center">
                    <i class="fas fa-times-circle fa-2x mb-2"></i>
                    <h3 class="card-title"><?= number_format($stats['failed_sms']) ?></h3>
                    <p class="card-text"><?= __('reports.failed') ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card stats-card warning">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <h3 class="card-title"><?= round($stats['avg_delivery_time'] ?? 0) ?>s</h3>
                    <p class="card-text"><?= __('reports.avg_time') ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Graphique évolution -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        <?= __('reports.daily_evolution') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyChart" height="100"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Répartition par statut -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        <?= __('reports.status_distribution') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (Auth::hasRole(ROLE_SUPERVISOR)): ?>
        <div class="row">
            <!-- Top utilisateurs -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users me-2"></i>
                            <?= __('reports.top_users') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_users)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?= __('users.username') ?></th>
                                            <th><?= __('users.total_sms') ?></th>
                                            <th><?= __('users.sent_sms') ?></th>
                                            <th><?= __('users.success_rate') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_users as $user): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= number_format($user['total_sms']) ?></td>
                                                <td><?= number_format($user['sent_sms']) ?></td>
                                                <td>
                                                    <?php 
                                                    $rate = $user['total_sms'] > 0 ? round(($user['sent_sms'] / $user['total_sms']) * 100, 1) : 0;
                                                    $badgeClass = $rate >= 90 ? 'success' : ($rate >= 70 ? 'warning' : 'danger');
                                                    ?>
                                                    <span class="badge bg-<?= $badgeClass ?>"><?= $rate ?>%</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center"><?= __('reports.no_data') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Codes d'erreur -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= __('reports.frequent_errors') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error_codes)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?= __('reports.error_code') ?></th>
                                            <th><?= __('reports.occurrences') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($error_codes as $error): ?>
                                            <tr>
                                                <td>
                                                    <code><?= htmlspecialchars($error['error_code']) ?></code>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger"><?= number_format($error['count']) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p class="text-muted"><?= __('reports.no_recent_errors') ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Scripts pour les graphiques -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Données pour les graphiques
    const dailyData = <?= json_encode($daily_stats ?? []) ?>;
    const statsData = <?= json_encode($stats ?? []) ?>;
    
    // Graphique évolution journalière
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
            }),
            datasets: [{
                label: window.__('chart_sent'),
                data: dailyData.map(item => item.sent),
                borderColor: '#10b981',
                backgroundColor: '#10b98120',
                fill: true,
                tension: 0.4
            }, {
                label: window.__('chart_failed'),
                data: dailyData.map(item => item.failed),
                borderColor: '#ef4444',
                backgroundColor: '#ef444420',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
    
    // Graphique répartition par statut
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: [window.__('chart_sent'), window.__('chart_pending'), window.__('chart_failed')],
            datasets: [{
                data: [
                    parseInt(statsData.sent_sms || 0),
                    parseInt(statsData.pending_sms || 0),
                    parseInt(statsData.failed_sms || 0)
                ],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>