<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('errors.page_not_found') ?> - <?= __('app.name') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 text-center">
                <div class="card border-0 shadow-lg">
                    <div class="card-body p-5">
                        <div class="mb-4">
                            <i class="fas fa-search fa-4x text-muted mb-3"></i>
                            <h1 class="display-4 fw-bold text-primary">404</h1>
                        </div>
                        
                        <h2 class="h4 mb-3"><?= __('errors.page_not_found') ?></h2>
                        <p class="text-muted mb-4">
                            <?= __('errors.page_not_found_desc') ?>
                        </p>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="/dashboard" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>
                                <?= __('common.back_to_dashboard') ?>
                            </a>
                            <a href="/sms/send" class="btn btn-outline-primary">
                                <i class="fas fa-paper-plane me-2"></i>
                                <?= __('sms.send') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>