<?php $view = 'dashboard/index'; ?>

<div class="row mb-4">
    <div class="col">
        <h1 class="h3 mb-0"><?= __('dashboard.title') ?></h1>
        <p class="text-muted"><?= __('dashboard.subtitle') ?></p>
    </div>
</div>

<!-- Statistiques principales -->
<div class="row mb-4" id="dashboard-stats">
    <div class="col-md-3 mb-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-paper-plane fa-2x mb-2"></i>
                <h3 class="card-title" id="stats-sent"><?= number_format($stats['sent_sms'] ?? 0) ?></h3>
                <p class="card-text"><?= __('dashboard.sms_sent') ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card warning">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h3 class="card-title" id="stats-pending"><?= number_format($stats['pending_sms'] ?? 0) ?></h3>
                <p class="card-text"><?= __('dashboard.pending') ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card danger">
            <div class="card-body text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <h3 class="card-title" id="stats-failed"><?= number_format($stats['failed_sms'] ?? 0) ?></h3>
                <p class="card-text"><?= __('dashboard.failed') ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stats-card success">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h3 class="card-title" id="stats-success-rate"><?= htmlspecialchars($stats['success_rate'] ?? 0) ?>%</h3>
                <p class="card-text"><?= __('dashboard.success_rate') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- SMS récents -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>
                    <?= __('dashboard.recent_sms') ?>
                </h5>
                <a href="/sms/queue" class="btn btn-sm btn-outline-primary">
                    <?= __('common.view_all') ?> <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_sms)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><?= __('sms.recipient') ?></th>
                                    <th><?= __('sms.message') ?></th>
                                    <th><?= __('sms.status') ?></th>
                                    <th><?= __('common.date') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($recent_sms, 0, 5) as $sms): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sms['recipient']) ?></td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 200px;">
                                                <?= htmlspecialchars($sms['message']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="queue-status <?= $sms['status'] ?>">
                                                <?= ucfirst($sms['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('d/m H:i', strtotime($sms['created_at'])) ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted"><?= __('sms.no_sms_found') ?></p>
                        <a href="/sms/send" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>
                            <?= __('sms.send_first') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statut des modems -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-sim-card me-2"></i>
                    <?= __('dashboard.modem_status') ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($modem_status)): ?>
                    <?php foreach ($modem_status as $modem): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?= htmlspecialchars($modem['name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($modem['device_path']) ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?= $modem['is_online'] ? 'success' : 'danger' ?>">
                                    <?= $modem['is_online'] ? 'En ligne' : 'Hors ligne' ?>
                                </span>
                                <?php if ($modem['last_used']): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?= date('d/m H:i', strtotime($modem['last_used'])) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-mobile-alt fa-2x text-muted mb-2"></i>
                        <div id="modem-list-empty" class="text-muted mb-0"><?= __('common.loading') ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-server me-2"></i>
                    <?= __('dashboard.system_status') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/sms/send" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>
                        Nouveau SMS
                    </a>
                    <a href="/sms/bulk" class="btn btn-outline-primary">
                        <i class="fas fa-upload me-2"></i>
                        Envoi en masse
                    </a>
                    <?php if (Auth::hasRole(ROLE_SUPERVISOR)): ?>
                        <a href="/reports" class="btn btn-outline-secondary">
                            <i class="fas fa-chart-bar me-2"></i>
                            Voir les rapports
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistiques aujourd'hui -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-day me-2"></i>
                    <?= __('dashboard.today_stats') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-6">
                        <div class="border-end">
                            <h3 class="text-primary"><?= number_format($stats['today_total'] ?? 0) ?></h3>
                            <p class="text-muted mb-0"><?= __('dashboard.sent_today') ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h3 class="text-success"><?= number_format($stats['today_sent'] ?? 0) ?></h3>
                        <p class="text-muted mb-0"><?= __('dashboard.delivered_today') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>