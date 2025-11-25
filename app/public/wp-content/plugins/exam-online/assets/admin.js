/**
 * Admin JavaScript cho Plugin Thi Online
 * File: assets/admin.js
 */

(function($) {
    'use strict';

    // Global admin object
    window.ExamAdmin = {
        selectedQuestions: [],
        currentTab: 'general'
    };

    /**
     * Initialize admin functionality
     */
    function initAdmin() {
        console.log('Exam Admin initialized');

        // Tab switching
        initTabs();

        // Confirm delete actions
        initDeleteConfirmation();

        // Form validation
        initFormValidation();

        // AJAX handlers
        setupAjaxHandlers();

        // Sortable lists
        initSortable();

        // Chart initialization
        initCharts();

        // Tooltips
        initTooltips();

        // Auto-save drafts
        initAutoSave();
    }

    /**
     * Initialize tabs
     */
    function initTabs() {
        $('.exam-tab').on('click', function() {
            const target = $(this).data('tab');
            
            $('.exam-tab').removeClass('active');
            $(this).addClass('active');
            
            $('.exam-tab-content').removeClass('active');
            $(`.exam-tab-content[data-tab="${target}"]`).addClass('active');
            
            ExamAdmin.currentTab = target;
        });
    }

    /**
     * Initialize delete confirmation
     */
    function initDeleteConfirmation() {
        $('[data-confirm-delete]').on('click', function(e) {
            const message = $(this).data('confirm-delete') || 'Bạn có chắc muốn xóa?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        $('form[data-validate]').on('submit', function(e) {
            let isValid = true;
            const requiredFields = $(this).find('[required]');
            
            requiredFields.each(function() {
                const $field = $(this);
                const value = $field.val();
                
                if (!value || value.trim() === '') {
                    isValid = false;
                    $field.addClass('error');
                    showError($field, 'Trường này là bắt buộc');
                } else {
                    $field.removeClass('error');
                    hideError($field);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Vui lòng điền đầy đủ thông tin bắt buộc', 'error');
                return false;
            }
        });
        
        // Clear error on input
        $('[required]').on('input change', function() {
            $(this).removeClass('error');
            hideError($(this));
        });
    }

    /**
     * Show field error
     */
    function showError($field, message) {
        hideError($field);
        $field.after(`<span class="exam-field-error">${message}</span>`);
    }

    /**
     * Hide field error
     */
    function hideError($field) {
        $field.next('.exam-field-error').remove();
    }

    /**
     * Setup AJAX handlers
     */
    function setupAjaxHandlers() {
        // Bulk delete
        $('#bulk-delete').on('click', function() {
            const selected = $('input[name="selected_items[]"]:checked');
            
            if (selected.length === 0) {
                showNotification('Vui lòng chọn ít nhất một mục', 'warning');
                return;
            }
            
            if (!confirm(`Xóa ${selected.length} mục đã chọn?`)) {
                return;
            }
            
            const ids = selected.map(function() {
                return $(this).val();
            }).get();
            
            bulkDelete(ids);
        });
        
        // Select all checkbox
        $('#select-all').on('change', function() {
            $('input[name="selected_items[]"]').prop('checked', $(this).prop('checked'));
        });
    }

    /**
     * Bulk delete items
     */
    function bulkDelete(ids) {
        $.ajax({
            url: examAdmin.ajaxurl,
            method: 'POST',
            data: {
                action: 'exam_bulk_delete',
                nonce: examAdmin.nonce,
                ids: ids
            },
            beforeSend: function() {
                showLoading();
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showNotification('Đã xóa thành công', 'success');
                    location.reload();
                } else {
                    showNotification(response.data.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Lỗi kết nối', 'error');
            }
        });
    }

    /**
     * Initialize sortable lists
     */
    function initSortable() {
        if (typeof Sortable !== 'undefined') {
            const sortableElements = document.querySelectorAll('.exam-sortable');
            
            sortableElements.forEach(function(el) {
                new Sortable(el, {
                    animation: 150,
                    handle: '.drag-handle',
                    onEnd: function(evt) {
                        saveSortOrder(el);
                    }
                });
            });
        }
    }

    /**
     * Save sort order
     */
    function saveSortOrder(element) {
        const items = $(element).find('[data-id]');
        const order = items.map(function() {
            return $(this).data('id');
        }).get();
        
        $.ajax({
            url: examAdmin.ajaxurl,
            method: 'POST',
            data: {
                action: 'exam_save_order',
                nonce: examAdmin.nonce,
                order: order
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Đã lưu thứ tự', 'success', 2000);
                }
            }
        });
    }

    /**
     * Initialize charts
     */
    function initCharts() {
        // Example using Chart.js
        const chartCanvas = document.getElementById('exam-chart');
        
        if (chartCanvas && typeof Chart !== 'undefined') {
            const ctx = chartCanvas.getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Lượt thi',
                        data: [12, 19, 3, 5, 2, 3, 7],
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('[data-tooltip]').hover(
            function() {
                const text = $(this).data('tooltip');
                const tooltip = $('<div class="exam-admin-tooltip">')
                    .text(text)
                    .appendTo('body');
                
                const pos = $(this).offset();
                tooltip.css({
                    top: pos.top - tooltip.outerHeight() - 10,
                    left: pos.left + ($(this).outerWidth() - tooltip.outerWidth()) / 2
                }).fadeIn(200);
            },
            function() {
                $('.exam-admin-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        );
    }

    /**
     * Initialize auto-save
     */
    function initAutoSave() {
        const $form = $('form[data-autosave]');
        
        if ($form.length === 0) return;
        
        let saveTimeout;
        
        $form.on('input change', function() {
            clearTimeout(saveTimeout);
            
            saveTimeout = setTimeout(function() {
                saveDraft($form);
            }, 3000);
        });
    }

    /**
     * Save draft
     */
    function saveDraft($form) {
        const formData = $form.serialize();
        
        $.ajax({
            url: examAdmin.ajaxurl,
            method: 'POST',
            data: formData + '&action=exam_save_draft&nonce=' + examAdmin.nonce,
            success: function(response) {
                if (response.success) {
                    showNotification('Đã tự động lưu', 'success', 2000);
                }
            }
        });
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info', duration = 3000) {
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#2271b1'
        };
        
        const notification = $('<div class="exam-admin-notification">')
            .text(message)
            .css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                padding: '15px 25px',
                background: colors[type] || colors.info,
                color: type === 'warning' ? '#212529' : 'white',
                borderRadius: '6px',
                boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
                zIndex: 100000,
                fontSize: '14px',
                fontWeight: '600',
                opacity: 0
            })
            .appendTo('body')
            .animate({ opacity: 1, top: '32px' }, 300);
        
        setTimeout(() => {
            notification.animate({ opacity: 0, top: '20px' }, 300, function() {
                $(this).remove();
            });
        }, duration);
    }

    /**
     * Show loading overlay
     */
    function showLoading() {
        if ($('.exam-loading-overlay').length === 0) {
            $('<div class="exam-loading-overlay">')
                .html('<div class="exam-loading"></div>')
                .css({
                    position: 'fixed',
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    background: 'rgba(255,255,255,0.9)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    zIndex: 99999
                })
                .appendTo('body');
        }
    }

    /**
     * Hide loading overlay
     */
    function hideLoading() {
        $('.exam-loading-overlay').fadeOut(300, function() {
            $(this).remove();
        });
    }

    /**
     * Export data to CSV
     */
    function exportToCSV(data, filename) {
        const csv = convertToCSV(data);
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        
        const link = $('<a>')
            .attr('href', url)
            .attr('download', filename)
            .appendTo('body');
        
        link[0].click();
        link.remove();
        URL.revokeObjectURL(url);
        
        showNotification('Đã xuất file CSV', 'success');
    }

    /**
     * Convert data to CSV
     */
    function convertToCSV(data) {
        if (!data || data.length === 0) return '';
        
        const headers = Object.keys(data[0]);
        const csv = [
            headers.join(','),
            ...data.map(row => 
                headers.map(header => 
                    JSON.stringify(row[header] || '')
                ).join(',')
            )
        ].join('\n');
        
        return csv;
    }

    /**
     * Show modal
     */
    function showModal(title, content) {
        const modal = $('<div class="exam-modal active">')
            .html(`
                <div class="exam-modal-content">
                    <div class="exam-modal-header">
                        <h2>${title}</h2>
                        <button class="exam-modal-close">&times;</button>
                    </div>
                    <div class="exam-modal-body">
                        ${content}
                    </div>
                </div>
            `)
            .appendTo('body');
        
        modal.find('.exam-modal-close').on('click', function() {
            modal.remove();
        });
        
        modal.on('click', function(e) {
            if ($(e.target).hasClass('exam-modal')) {
                modal.remove();
            }
        });
    }

    /**
     * Confirm action
     */
    function confirmAction(message, callback) {
        const modal = $('<div class="exam-modal active">')
            .html(`
                <div class="exam-modal-content">
                    <div class="exam-modal-body">
                        <p>${message}</p>
                    </div>
                    <div class="exam-modal-footer">
                        <button class="exam-btn exam-btn-secondary cancel-btn">Hủy</button>
                        <button class="exam-btn exam-btn-danger confirm-btn">Xác nhận</button>
                    </div>
                </div>
            `)
            .appendTo('body');
        
        modal.find('.cancel-btn').on('click', function() {
            modal.remove();
        });
        
        modal.find('.confirm-btn').on('click', function() {
            callback();
            modal.remove();
        });
    }

    /**
     * Copy to clipboard
     */
    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Đã sao chép!', 'success', 2000);
            });
        } else {
            const textarea = $('<textarea>')
                .val(text)
                .css({ position: 'fixed', opacity: 0 })
                .appendTo('body')
                .select();
            
            document.execCommand('copy');
            textarea.remove();
            showNotification('Đã sao chép!', 'success', 2000);
        }
    }

    /**
     * Format number
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Expose public methods
    window.ExamAdmin = $.extend(window.ExamAdmin, {
        showNotification: showNotification,
        showLoading: showLoading,
        hideLoading: hideLoading,
        exportToCSV: exportToCSV,
        showModal: showModal,
        confirmAction: confirmAction,
        copyToClipboard: copyToClipboard,
        formatNumber: formatNumber,
        debounce: debounce
    });

    // Initialize on document ready
    $(document).ready(function() {
        initAdmin();
    });

})(jQuery);