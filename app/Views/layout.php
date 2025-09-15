<!DOCTYPE html>
<html lang="<?= currentLang() ?>" <?= isRTL() ? 'dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= SecurityService::generateCSRFToken() ?>">
    <title><?= $title ?? __('app.name') ?> - <?= __('app.name') ?></title>
    
    <!-- Bootstrap CSS -->
    <?php if (isRTL()): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php if (currentLang() === 'ar'): ?>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php else: ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>
    
    <!-- Custom CSS -->
    <link href="/css/style.css" rel="stylesheet">
    
    <?php if (isRTL()): ?>
    <style>
        body { font-family: 'Noto Sans Arabic', Arial, sans-serif; }
        .sidebar { <?= isRTL() ? 'right: 0; left: auto;' : '' ?> }
        .main-content { <?= isRTL() ? 'margin-right: 250px; margin-left: 0;' : '' ?> }
        @media (max-width: 768px) {
            .sidebar { <?= isRTL() ? 'right: -250px; left: auto;' : '' ?> }
            .sidebar.show { <?= isRTL() ? 'right: 0;' : '' ?> }
            .main-content { <?= isRTL() ? 'margin-right: 0 !important;' : '' ?> }
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <?php if (Auth::check()): ?>
        <div class="d-flex">
            <!-- Sidebar -->
            <nav class="sidebar d-flex flex-column">
                <div class="p-3">
                    <h4 class="text-white mb-0">
                        <i class="fas fa-sms me-2"></i>
                        <?= __('app.name') ?>
                    </h4>
                </div>
                
                <ul class="nav nav-pills flex-column p-3">
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/dashboard') === 0 ? 'active' : '' ?>" href="/dashboard">
                            <i class="fas fa-chart-line me-2"></i> <?= __('navigation.dashboard') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/sms/send') === 0 ? 'active' : '' ?>" href="/sms/send">
                            <i class="fas fa-paper-plane me-2"></i> <?= __('navigation.send_sms') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/sms/bulk') === 0 ? 'active' : '' ?>" href="/sms/bulk">
                            <i class="fas fa-upload me-2"></i> <?= __('navigation.bulk_sms') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/sms/queue') === 0 ? 'active' : '' ?>" href="/sms/queue">
                            <i class="fas fa-list me-2"></i> <?= __('navigation.sms_queue') ?>
                        </a>
                    </li>
                    <?php if (Auth::hasRole(ROLE_SUPERVISOR)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/reports') === 0 ? 'active' : '' ?>" href="/reports">
                            <i class="fas fa-chart-bar me-2"></i> <?= __('navigation.reports') ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (Auth::hasRole(ROLE_ADMIN)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/users') === 0 ? 'active' : '' ?>" href="/users">
                            <i class="fas fa-users me-2"></i> <?= __('navigation.users') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/network') === 0 ? 'active' : '' ?>" href="/network">
                            <i class="fas fa-network-wired me-2"></i> <?= __('navigation.network') ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="mt-auto p-3">
                    <!-- Language Selector -->
                    <div class="dropdown mb-3">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                            <?php 
                            $currentLangInfo = Language::getInstance()->getLanguageInfo();
                            echo $currentLangInfo['flag'] . ' ' . $currentLangInfo['name'];
                            ?>
                        </button>
                        <ul class="dropdown-menu w-100">
                            <?php foreach (Language::getInstance()->getSupportedLanguages() as $code => $info): ?>
                                <li>
                                    <a class="dropdown-item <?= currentLang() === $code ? 'active' : '' ?>" 
                                       href="/language/switch?lang=<?= $code ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                                        <?= $info['flag'] ?> <?= $info['name'] ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="dropdown">
                        <a class="nav-link text-white dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i>
                            <?= Auth::user()['username'] ?? Auth::user()['email'] ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/profile"><i class="fas fa-user-cog me-2"></i> <?= __('navigation.profile') ?></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout"><i class="fas fa-sign-out-alt me-2"></i> <?= __('auth.logout') ?></a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <div class="main-content flex-grow-1" style="margin-left: 250px;">
                <!-- Top Navigation -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
                    <div class="container-fluid">
                        <button class="btn btn-link sidebar-toggle d-md-none">
                            <i class="fas fa-bars"></i>
                        </button>
                        
                        <div class="navbar-nav ms-auto">
                            <div class="nav-item me-3">
                                <span class="badge bg-primary" id="queue-count">0</span>
                                <small class="text-muted"><?= __('dashboard.pending') ?></small>
                            </div>
                            <div class="nav-item">
                                <span class="badge bg-info" id="processing-count">0</span>
                                <small class="text-muted"><?= __('status.processing') ?></small>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <div class="container-fluid">
                    <?php include $view . '.php'; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php include $view . '.php'; ?>
    <?php endif; ?>
    
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="/js/app.js"></script>
    
    <script>
    // Configuration globale pour les traductions JavaScript
    window.translations = {
        // Common translations
        success: '<?= __("common.success") ?>',
        error: '<?= __("common.error") ?>',
        warning: '<?= __("common.warning") ?>',
        info: '<?= __("common.info") ?>',
        loading: '<?= __("common.loading") ?>',
        processing: '<?= __("common.processing") ?>',
        confirm: '<?= __("common.confirm") ?>',
        yes: '<?= __("common.yes") ?>',
        no: '<?= __("common.no") ?>',
        close: '<?= __("common.close") ?>',
        save: '<?= __("common.save") ?>',
        cancel: '<?= __("common.cancel") ?>',
        delete: '<?= __("common.delete") ?>',
        
        // SMS specific
        sms_sent: '<?= __("messages.success.sms_sent") ?>',
        sms_queued: '<?= __("messages.success.sms_queued") ?>',
        sms_deleted: '<?= __("messages.success.sms_deleted") ?>',
        sms_retry_queued: '<?= __("messages.success.sms_retry_queued") ?>',
        queue_cleared: '<?= __("messages.success.queue_cleared") ?>',
        bulk_processed: '<?= __("messages.success.bulk_processed") ?>',
        
        // Error messages
        sms_send_error: '<?= __("messages.error.sms_send_error") ?>',
        sms_delete_error: '<?= __("messages.error.sms_delete_error") ?>',
        sms_retry_error: '<?= __("messages.error.sms_retry_error") ?>',
        queue_clear_error: '<?= __("messages.error.queue_clear_error") ?>',
        file_upload_error: '<?= __("messages.error.file_upload_error") ?>',
        network_error: '<?= __("messages.error.network_error") ?>',
        details_load_error: '<?= __("sms.details_load_error") ?>',
        
        // User management
        user_created: '<?= __("messages.success.user_created") ?>',
        user_updated: '<?= __("messages.success.user_updated") ?>',
        user_deleted: '<?= __("messages.success.user_deleted") ?>',
        user_status_updated: '<?= __("messages.success.user_status_updated") ?>',
        
        // Network
        config_updated: '<?= __("messages.success.config_updated") ?>',
        backup_created: '<?= __("messages.success.backup_created") ?>',
        config_restored: '<?= __("messages.success.config_restored") ?>',
        
        // Confirmations
        confirm_delete_sms: '<?= __("confirmations.delete_sms") ?>',
        confirm_delete_user: '<?= __("confirmations.delete_user") ?>',
        confirm_clear_queue: '<?= __("confirmations.clear_queue") ?>',
        confirm_user_status_change: '<?= __("confirmations.user_status_change") ?>',
        confirm_network_config: '<?= __("confirmations.network_config") ?>',
        
        // Chart labels
        chart_sent: '<?= __("status.sent") ?>',
        chart_failed: '<?= __("status.failed") ?>',
        chart_pending: '<?= __("status.pending") ?>',
        chart_processing: '<?= __("status.processing") ?>'
    };
    
    window.currentLanguage = '<?= currentLang() ?>';
    window.isRTL = <?= isRTL() ? 'true' : 'false' ?>;
    
    // Translation function for JavaScript
    window.__ = function(key, params = {}) {
        let translation = window.translations[key] || key;
        
        // Replace parameters
        for (const [param, value] of Object.entries(params)) {
            translation = translation.replace(':' + param, value);
        }
        
        return translation;
    };
    </script>
</body>
</html>