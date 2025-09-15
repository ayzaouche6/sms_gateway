<?php $view = 'sms/bulk'; ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-upload me-2"></i>
                    <?= __('sms.bulk_send') ?>
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
                
                <form id="bulk-sms-form" method="POST" action="/sms/bulk" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= SecurityService::generateCSRFToken() ?>">
                    
                    <div class="row">
                        <!-- Upload de fichier -->
                        <div class="col-lg-6 mb-4">
                            <label class="form-label">
                                <?= __('sms.csv_file') ?> <span class="text-danger">*</span>
                            </label>
                            
                            <div class="dropzone" id="file-dropzone">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <p class="mb-2"><?= __('sms.drag_drop') ?></p>
                                <p class="text-muted mb-3"><?= __('sms.click_select') ?></p>
                                <input type="file" 
                                       id="csv-file" 
                                       name="csv_file" 
                                       accept=".csv,.xlsx,.xls" 
                                       style="display: none;" 
                                       required>
                            </div>
                            
                            <div class="file-info mt-3" style="display: none;">
                                <div class="alert alert-info">
                                    <strong><?= __('sms.file_selected') ?>:</strong><br>
                                    <span class="file-name"></span> (<span class="file-size"></span>)
                                </div>
                            </div>
                            
                            <div class="form-text">
                                <?= __('sms.supported_formats') ?>: CSV, Excel (.xlsx, .xls)<br>
                                <?= __('sms.max_size') ?>: <?= round(UPLOAD_MAX_SIZE / (1024*1024)) ?>MB
                            </div>
                        </div>
                        
                        <!-- Message -->
                        <div class="col-lg-6 mb-4">
                            <label for="bulk-message" class="form-label">
                                <?= __('sms.message') ?> <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" 
                                      id="bulk-message" 
                                      name="message" 
                                      rows="8" 
                                      maxlength="<?= SMS_MAX_LENGTH ?>"
                                      placeholder="<?= __('sms.message') ?>..." 
                                      required></textarea>
                            <div class="d-flex justify-content-between">
                                <div class="form-text">
                                    <span id="bulk-char-count">0</span> / <?= SMS_MAX_LENGTH ?> <?= __('sms.characters') ?>
                                </div>
                                <div class="form-text">
                                    <span id="bulk-sms-count">0</span> <?= __('sms.sms_count') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-upload me-2"></i>
                                <?= __('sms.process_file') ?>
                            </button>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <a href="/sms/queue" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>
                                <?= __('sms.view_queue') ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Format de fichier -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-csv me-2"></i>
                            <?= __('sms.csv_format') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><?= __('sms.csv_format_desc') ?></p>
                        <div class="bg-light p-3 rounded">
                            <code>
                                <?= __('sms.recipient') ?><br>
                                +33612345678<br>
                                +33698765432<br>
                                +33612345679<br>
                                ...
                            </code>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <?= __('sms.csv_header_note') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <?= __('sms.recommendations') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <?= __('sms.international_format') ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <?= __('sms.verify_numbers') ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <?= __('sms.avoid_duplicates') ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                <?= __('sms.invalid_numbers_ignored') ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-clock text-info me-2"></i>
                                <?= __('sms.processing_time_note') ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('bulk-message');
    const charCount = document.getElementById('bulk-char-count');
    const smsCount = document.getElementById('bulk-sms-count');
    
    function updateCounts() {
        const length = messageInput.value.length;
        const isUnicode = /[\u0080-\uFFFF]/.test(messageInput.value);
        const maxLength = isUnicode ? <?= SMS_UNICODE_MAX_LENGTH ?> : <?= SMS_MAX_LENGTH ?>;
        
        charCount.textContent = length;
        
        // Calculer le nombre de SMS
        const smsNeeded = Math.ceil(length / maxLength);
        smsCount.textContent = Math.max(1, smsNeeded);
        
        // Changer la couleur selon la limite
        if (length > maxLength * 0.8) {
            charCount.className = 'text-warning';
        } else if (length > maxLength * 0.9) {
            charCount.className = 'text-danger';
        } else {
            charCount.className = 'text-muted';
        }
    }
    
    messageInput.addEventListener('input', updateCounts);
    messageInput.addEventListener('keyup', updateCounts);
    updateCounts(); // Initial count
});
</script>