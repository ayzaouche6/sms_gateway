<?php $view = 'sms/send'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">
                    <i class="fas fa-paper-plane me-2"></i>
                    <?= __('sms.send') ?>
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
                
                <form id="sms-form" method="POST" action="/sms/send">
                    <input type="hidden" name="csrf_token" value="<?= SecurityService::generateCSRFToken() ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="recipient" class="form-label">
                                <?= __('sms.recipient') ?> <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="recipient" 
                                       name="recipient" 
                                       value="<?= htmlspecialchars($recipient ?? '') ?>"
                                       placeholder="+33612345678" 
                                       required>
                            </div>
                            <div class="form-text">
                                <?= __('sms.phone_format') ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="scheduled_at" class="form-label">
                                <?= __('sms.schedule') ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-calendar"></i>
                                </span>
                                <input type="datetime-local" 
                                       class="form-control" 
                                       id="scheduled_at" 
                                       name="scheduled_at" 
                                       min="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">
                            <?= __('sms.message') ?> <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  id="message" 
                                  name="message" 
                                  rows="4" 
                                  maxlength="<?= SMS_MAX_LENGTH ?>"
                                  placeholder="<?= __('sms.message_placeholder') ?>" 
                                  required><?= htmlspecialchars($message ?? '') ?></textarea>
                        <div class="d-flex justify-content-between">
                            <div class="form-text">
                                <span id="char-count">0</span> / <?= SMS_MAX_LENGTH ?> <?= __('sms.characters') ?>
                            </div>
                            <div class="form-text">
                                <span id="sms-count">0</span> <?= __('sms.sms_count') ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>
                                <?= __('sms.send_button') ?>
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
        
        <!-- Conseils d'utilisation -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    <?= __('sms.usage_tips') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <?= __('sms.international_format') ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <?= __('sms.max_chars', ['count' => SMS_MAX_LENGTH]) ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <?= __('sms.emoji_support') ?>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-clock text-info me-2"></i>
                                <?= __('sms.schedule_limit') ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-redo text-warning me-2"></i>
                                <?= __('sms.auto_retry') ?>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-shield-alt text-primary me-2"></i>
                                <?= __('sms.secure_tracking') ?>
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
    const messageInput = document.getElementById('message');
    const charCount = document.getElementById('char-count');
    const smsCount = document.getElementById('sms-count');
    
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