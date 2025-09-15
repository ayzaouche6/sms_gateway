/**
 * SMS Gateway JavaScript Application
 */

class SMSGatewayApp {
    constructor() {
        this.init();
        this.setupEventListeners();
        this.setupAjaxDefaults();
    }

    init() {
        // Initialize tooltips and popovers
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize CSRF token
        this.csrfToken = $('meta[name="csrf-token"]').attr('content');
        
        // Start real-time updates
        this.startRealTimeUpdates();
    }

    setupAjaxDefaults() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': this.csrfToken
            },
            error: (xhr, status, error) => {
                if (xhr.status === 401) {
                    window.location.href = '/auth/login';
                    return;
                }
                this.showToast('error', 'Une erreur est survenue: ' + error);
            }
        });
    }

    setupEventListeners() {
        // Sidebar toggle for mobile
        $(document).on('click', '.sidebar-toggle', () => {
            $('.sidebar').toggleClass('show');
        });

        // Close sidebar when clicking outside on mobile
        $(document).on('click', (e) => {
            if ($(window).width() <= 768) {
                if (!$(e.target).closest('.sidebar, .sidebar-toggle').length) {
                    $('.sidebar').removeClass('show');
                }
            }
        });

        // SMS form submission
        $(document).on('submit', '#sms-form', this.handleSmsSubmit.bind(this));

        // Bulk SMS form
        $(document).on('submit', '#bulk-sms-form', this.handleBulkSmsSubmit.bind(this));

        // CSV file upload with drag & drop
        this.setupFileUpload();

        // Queue management
        $(document).on('click', '.retry-sms', this.retrySms.bind(this));
        $(document).on('click', '.delete-sms', this.deleteSms.bind(this));
        $(document).on('click', '.clear-queue', this.clearQueue.bind(this));

        // Search and filter
        $(document).on('keyup', '#search-sms', this.searchSms.bind(this));
        $(document).on('change', '#filter-status', this.filterSms.bind(this));
    }

    startRealTimeUpdates() {
        // Update queue status every 5 seconds
        setInterval(() => {
            this.updateQueueStatus();
            this.updateDashboardStats();
        }, 5000);
    }

    updateQueueStatus() {
        $.get('/api/queue/status', (data) => {
            if (data.success) {
                $('#queue-count').text(data.pending);
                $('#processing-count').text(data.processing);
                this.updateQueueTable();
            }
        });
    }

    updateDashboardStats() {
        if ($('#dashboard-stats').length) {
            $.get('/api/stats', (data) => {
                if (data.success) {
                    $('#stats-sent').text(data.sent);
                    $('#stats-pending').text(data.pending);
                    $('#stats-failed').text(data.failed);
                    $('#stats-success-rate').text(data.success_rate + '%');
                }
            });
        }
    }

    updateQueueTable() {
        if ($('#sms-queue-table').length) {
            const query = $('#search-sms').val() || '';
            const status = $('#filter-status').val() || '';
            
            $.get('/api/sms/list', { search: query, status: status }, (data) => {
                if (data.success) {
                    this.renderSmsTable(data.sms);
                }
            });
        }
    }

    renderSmsTable(smsData) {
        const tbody = $('#sms-queue-table tbody');
        tbody.empty();

        smsData.forEach(sms => {
            // Translate status
            let statusText = sms.status;
            switch(sms.status) {
                case 'pending': statusText = window.__('status_pending'); break;
                case 'processing': statusText = window.__('status_processing'); break;
                case 'sent': statusText = window.__('status_sent'); break;
                case 'failed': statusText = window.__('status_failed'); break;
                case 'scheduled': statusText = window.__('status_scheduled'); break;
            }
            
            const row = `
                <tr class="fade-in">
                    <td>${sms.id}</td>
                    <td>${sms.recipient}</td>
                    <td>${sms.message.substring(0, 50)}${sms.message.length > 50 ? '...' : ''}</td>
                    <td><span class="queue-status ${sms.status}">${statusText}</span></td>
                    <td>${this.formatDate(sms.created_at)}</td>
                    <td>
                        ${sms.status === 'failed' ? '<button class="btn btn-sm btn-warning retry-sms" data-id="' + sms.id + '" title="' + window.__('sms_retry') + '"><i class="fas fa-redo"></i></button>' : ''}
                        <button class="btn btn-outline-danger delete-sms" data-id="${sms.id}" title="${window.__('sms_delete')}"><i class="fas fa-trash"></i></button>
                        <button class="btn btn-outline-info" onclick="showSmsDetails(${sms.id})" title="${window.__('sms_details')}">
                </tr>
            `;
            tbody.append(row);
        });
    }

    handleSmsSubmit(e) {
        e.preventDefault();
        const form = $(e.target);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();

        submitBtn.prop('disabled', true).html('<span class="loading-spinner"></span> ' + window.__('processing'));

        $.post('/api/sms/send', form.serialize(), (data) => {
            if (data.success) {
                this.showToast('success', window.__('sms_sent'));
                form[0].reset();
                this.updateQueueStatus();
            } else {
                this.showToast('error', data.message || window.__('sms_send_error'));
            }
        }).always(() => {
            submitBtn.prop('disabled', false).text(originalText);
        });
    }

    handleBulkSmsSubmit(e) {
        e.preventDefault();
        const form = $(e.target);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();
        const formData = new FormData(form[0]);

        submitBtn.prop('disabled', true).html('<span class="loading-spinner"></span> ' + window.__('processing'));

        $.ajax({
            url: '/api/sms/bulk',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (data) => {
                if (data.success) {
                    this.showToast('success', window.__('bulk_processed').replace(':count', data.count));
                    form[0].reset();
                    $('.file-info').hide();
                    this.updateQueueStatus();
                } else {
                    this.showToast('error', data.message || window.__('file_upload_error'));
                }
            }
        }).always(() => {
            submitBtn.prop('disabled', false).text(originalText);
        });
    }

    setupFileUpload() {
        const dropzone = $('.dropzone');
        const fileInput = $('#csv-file');

        dropzone.on('dragover dragenter', (e) => {
            e.preventDefault();
            dropzone.addClass('dragover');
        });

        dropzone.on('dragleave drop', (e) => {
            e.preventDefault();
            dropzone.removeClass('dragover');
        });

        dropzone.on('drop', (e) => {
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                fileInput[0].files = files;
                this.displayFileInfo(files[0]);
            }
        });

        dropzone.on('click', () => {
            fileInput.click();
        });

        fileInput.on('change', (e) => {
            if (e.target.files.length > 0) {
                this.displayFileInfo(e.target.files[0]);
            }
        });
    }

    displayFileInfo(file) {
        $('.file-info').show();
        $('.file-name').text(file.name);
        $('.file-size').text(this.formatFileSize(file.size));
    }

    retrySms(e) {
        const smsId = $(e.target).closest('button').data('id');
        
        $.post('/api/sms/retry', { id: smsId }, (data) => {
            if (data.success) {
                this.showToast('success', window.__('sms_retry_queued'));
                this.updateQueueTable();
            } else {
                this.showToast('error', data.message || window.__('sms_retry_error'));
            }
        });
    }

    deleteSms(e) {
        const smsId = $(e.target).closest('button').data('id');
        
        if (confirm(window.__('confirm_delete_sms'))) {
            $.ajax({
                url: '/api/sms/delete',
                type: 'DELETE',
                data: { id: smsId },
                success: (data) => {
                    if (data.success) {
                        this.showToast('success', window.__('sms_deleted'));
                        this.updateQueueTable();
                    } else {
                        this.showToast('error', data.message || window.__('sms_delete_error'));
                    }
                }
            });
        }
    }

    clearQueue() {
        if (confirm(window.__('confirm_clear_queue'))) {
            $.post('/api/queue/clear', (data) => {
                if (data.success) {
                    this.showToast('success', window.__('queue_cleared'));
                    this.updateQueueTable();
                    this.updateQueueStatus();
                } else {
                    this.showToast('error', data.message || window.__('queue_clear_error'));
                }
            });
        }
    }

    searchSms() {
        const query = $('#search-sms').val();
        const status = $('#filter-status').val();
        
        $.get('/api/sms/list', { search: query, status: status }, (data) => {
            if (data.success) {
                this.renderSmsTable(data.sms);
                // Update total count in header
                const headerTitle = $('.card-header .card-title');
                if (headerTitle.length) {
                    headerTitle.text(`${window.__('sms_list')} (${data.total.toLocaleString()} ${window.__('total')})`);
                }
            } else {
                this.showToast('error', data.message || window.__('sms_search_error'));
            }
        }).fail(() => {
            this.showToast('error', window.__('sms_search_error'));
        });
    }

    filterSms() {
        this.searchSms();
    }

    showToast(type, message) {
        const toastHtml = `
            <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} text-white">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                    <strong class="me-auto">${window.__(type)}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;

        const toastContainer = $('#toast-container');
        if (!toastContainer.length) {
            $('body').append('<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>');
        }

        const $toast = $(toastHtml);
        $('#toast-container').append($toast);

        const bsToast = new bootstrap.Toast($toast[0], { delay: 5000 });
        bsToast.show();

        $toast.on('hidden.bs.toast', () => {
            $toast.remove();
        });
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const locale = window.currentLanguage === 'ar' ? 'ar-SA' : 
                      window.currentLanguage === 'en' ? 'en-US' : 'fr-FR';
        return date.toLocaleString(locale);
                      window.currentLanguage === 'en' ? 'en-US' : 'fr-FR';
        return date.toLocaleString(locale);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Initialize app when document is ready
$(document).ready(() => {
    window.smsApp = new SMSGatewayApp();
});