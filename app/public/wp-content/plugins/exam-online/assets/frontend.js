/**
 * Frontend JavaScript cho Plugin Thi Online
 * File: assets/frontend.js
 */

(function($) {
    'use strict';

    // Global object
    window.ExamSystem = {
        currentExam: null,
        answers: {},
        timer: null,
        autoSaveInterval: null
    };

    /**
     * Initialize exam system
     */
    function initExamSystem() {
        // Check if on exam test page
        if ($('#exam-app').length) {
            initExamTest();
        }

        // Smooth scroll for navigation
        initSmoothScroll();

        // Initialize tooltips
        initTooltips();

        // Track page views
        trackPageView();
    }

    /**
     * Initialize exam test page
     */
    function initExamTest() {
        console.log('Exam test initialized');

        // Load saved progress
        loadSavedProgress();

        // Setup auto-save
        setupAutoSave();

        // Visibility change handler
        handleVisibilityChange();

        // Keyboard shortcuts
        setupKeyboardShortcuts();
    }

    /**
     * Load saved progress from localStorage
     */
    function loadSavedProgress() {
        const examId = getExamIdFromUrl();
        if (!examId) return;

        const savedData = localStorage.getItem(`exam_${examId}_progress`);
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                console.log('Loaded saved progress:', data);
                // Progress loaded by PHP template
            } catch (e) {
                console.error('Error loading saved progress:', e);
            }
        }
    }

    /**
     * Setup auto-save functionality
     */
    function setupAutoSave() {
        const interval = parseInt(examData?.autoSaveInterval || 30) * 1000;
        
        ExamSystem.autoSaveInterval = setInterval(() => {
            saveProgress();
        }, interval);
    }

    /**
     * Save current progress
     */
    function saveProgress() {
        const examId = getExamIdFromUrl();
        if (!examId) return;

        const progress = {
            answers: ExamSystem.answers,
            timestamp: new Date().toISOString(),
            scrollPosition: window.scrollY
        };

        localStorage.setItem(`exam_${examId}_progress`, JSON.stringify(progress));
        showNotification('Đã tự động lưu', 'success', 2000);
    }

    /**
     * Handle visibility change (tab switching)
     */
    function handleVisibilityChange() {
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Tab is hidden
                saveProgress();
                console.log('Tab hidden - progress saved');
            } else {
                // Tab is visible
                console.log('Tab visible');
            }
        });
    }

    /**
     * Setup keyboard shortcuts
     */
    function setupKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + S: Save progress
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveProgress();
                showNotification('Đã lưu tiến độ', 'success');
            }

            // Escape: Scroll to top
            if (e.key === 'Escape') {
                $('html, body').animate({ scrollTop: 0 }, 500);
            }
        });
    }

    /**
     * Initialize smooth scroll
     */
    function initSmoothScroll() {
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });
    }

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('[data-tooltip]').hover(
            function() {
                const tooltip = $('<div class="exam-tooltip">')
                    .text($(this).data('tooltip'))
                    .appendTo('body');

                const pos = $(this).offset();
                tooltip.css({
                    top: pos.top - tooltip.outerHeight() - 10,
                    left: pos.left + ($(this).outerWidth() - tooltip.outerWidth()) / 2
                }).fadeIn(200);
            },
            function() {
                $('.exam-tooltip').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        );
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info', duration = 3000) {
        const colors = {
            success: '#27ae60',
            error: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db'
        };

        const notification = $('<div class="exam-notification">')
            .text(message)
            .css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                padding: '15px 25px',
                background: colors[type] || colors.info,
                color: 'white',
                borderRadius: '8px',
                boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
                zIndex: 10001,
                fontSize: '15px',
                fontWeight: '600',
                opacity: 0
            })
            .appendTo('body')
            .animate({ opacity: 1, top: '30px' }, 300);

        setTimeout(() => {
            notification.animate({ opacity: 0, top: '20px' }, 300, function() {
                $(this).remove();
            });
        }, duration);
    }

    /**
     * Track page view (analytics)
     */
    function trackPageView() {
        // Send analytics data
        $.ajax({
            url: examData?.ajaxurl,
            method: 'POST',
            data: {
                action: 'track_page_view',
                page: window.location.pathname,
                exam_id: getExamIdFromUrl()
            },
            success: function(response) {
                console.log('Page view tracked');
            }
        });
    }

    /**
     * Get exam ID from URL
     */
    function getExamIdFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return params.get('exam_id');
    }

    /**
     * Format time (seconds to MM:SS)
     */
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    /**
     * Confirm navigation away
     */
    function confirmNavigation() {
        return 'Bạn có chắc muốn rời khỏi trang? Tiến độ của bạn sẽ được lưu.';
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
            // Fallback
            const textarea = $('<textarea>')
                .val(text)
                .appendTo('body')
                .select();
            document.execCommand('copy');
            textarea.remove();
            showNotification('Đã sao chép!', 'success', 2000);
        }
    }

    /**
     * Share results
     */
    function shareResults(score, percentage) {
        const text = `Tôi vừa hoàn thành bài thi và đạt ${percentage}% (${score} điểm)! 🎉`;
        
        if (navigator.share) {
            navigator.share({
                title: 'Kết quả thi',
                text: text,
                url: window.location.href
            }).catch(err => console.log('Share failed:', err));
        } else {
            copyToClipboard(text);
        }
    }

    /**
     * Print exam
     */
    function printExam() {
        window.print();
    }

    /**
     * Full screen mode
     */
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log('Fullscreen error:', err);
            });
        } else {
            document.exitFullscreen();
        }
    }

    /**
     * Calculate statistics
     */
    function calculateStats() {
        const answered = Object.keys(ExamSystem.answers).length;
        const total = $('.question-card').length;
        const percentage = Math.round((answered / total) * 100);

        return {
            answered: answered,
            total: total,
            percentage: percentage,
            remaining: total - answered
        };
    }

    /**
     * Validate answers before submit
     */
    function validateAnswers() {
        const stats = calculateStats();
        
        if (stats.remaining > 0) {
            const message = `Bạn còn ${stats.remaining} câu chưa trả lời. Các câu này sẽ được tính là 0 điểm.`;
            return confirm(message + '\n\nBạn có chắc muốn nộp bài?');
        }
        
        return true;
    }

    /**
     * Highlight unanswered questions
     */
    function highlightUnanswered() {
        $('.question-card').each(function() {
            const qId = $(this).data('question-id');
            if (!ExamSystem.answers[qId]) {
                $(this).addClass('unanswered-highlight');
                setTimeout(() => {
                    $(this).removeClass('unanswered-highlight');
                }, 3000);
            }
        });

        $('html, body').animate({
            scrollTop: $('.question-card:not(.answered)').first().offset().top - 100
        }, 500);
    }

    /**
     * Export answers to JSON
     */
    function exportAnswers() {
        const data = {
            examId: getExamIdFromUrl(),
            answers: ExamSystem.answers,
            timestamp: new Date().toISOString(),
            stats: calculateStats()
        };

        const dataStr = JSON.stringify(data, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });
        const url = URL.createObjectURL(dataBlob);
        
        const link = $('<a>')
            .attr('href', url)
            .attr('download', `exam_${data.examId}_answers.json`)
            .appendTo('body');
        
        link[0].click();
        link.remove();
        URL.revokeObjectURL(url);

        showNotification('Đã tải xuống file câu trả lời', 'success');
    }

    /**
     * Show exam instructions modal
     */
    function showInstructions() {
        const modal = $('<div class="exam-modal">')
            .html(`
                <div class="modal-overlay" onclick="$(this).parent().remove()"></div>
                <div class="modal-box">
                    <h2>📋 Hướng dẫn làm bài</h2>
                    <ul class="instructions-list">
                        <li>Đọc kỹ đề bài trước khi trả lời</li>
                        <li>Bài thi sẽ tự động lưu định kỳ</li>
                        <li>Bạn có thể xem lại và sửa câu trả lời bất kỳ lúc nào</li>
                        <li>Kiểm tra kỹ trước khi nộp bài</li>
                        <li>Không được sử dụng tài liệu nếu không cho phép</li>
                    </ul>
                    <button class="btn btn-primary" onclick="$(this).closest('.exam-modal').remove()">
                        Đã hiểu
                    </button>
                </div>
            `)
            .appendTo('body');
    }

    /**
     * Dark mode toggle
     */
    function toggleDarkMode() {
        $('body').toggleClass('dark-mode');
        const isDark = $('body').hasClass('dark-mode');
        localStorage.setItem('darkMode', isDark ? 'true' : 'false');
        showNotification(isDark ? 'Đã bật chế độ tối' : 'Đã tắt chế độ tối', 'info');
    }

    /**
     * Check dark mode preference
     */
    function checkDarkModePreference() {
        const preference = localStorage.getItem('darkMode');
        if (preference === 'true') {
            $('body').addClass('dark-mode');
        }
    }

    /**
     * Accessibility: Increase font size
     */
    function increaseFontSize() {
        const currentSize = parseFloat($('body').css('font-size'));
        $('body').css('font-size', (currentSize + 2) + 'px');
    }

    /**
     * Accessibility: Decrease font size
     */
    function decreaseFontSize() {
        const currentSize = parseFloat($('body').css('font-size'));
        $('body').css('font-size', Math.max(12, currentSize - 2) + 'px');
    }

    /**
     * Show progress summary
     */
    function showProgressSummary() {
        const stats = calculateStats();
        
        const modal = $('<div class="exam-modal">')
            .html(`
                <div class="modal-overlay" onclick="$(this).parent().remove()"></div>
                <div class="modal-box">
                    <h2>📊 Tiến độ làm bài</h2>
                    <div class="progress-summary">
                        <div class="summary-row">
                            <span>Đã trả lời:</span>
                            <strong>${stats.answered}/${stats.total}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Hoàn thành:</span>
                            <strong>${stats.percentage}%</strong>
                        </div>
                        <div class="summary-row">
                            <span>Còn lại:</span>
                            <strong>${stats.remaining} câu</strong>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="$(this).closest('.exam-modal').remove()">
                        Đóng
                    </button>
                </div>
            `)
            .appendTo('body');
    }

    // Expose public methods
    window.ExamSystem = $.extend(window.ExamSystem, {
        showNotification: showNotification,
        saveProgress: saveProgress,
        copyToClipboard: copyToClipboard,
        shareResults: shareResults,
        printExam: printExam,
        toggleFullscreen: toggleFullscreen,
        calculateStats: calculateStats,
        validateAnswers: validateAnswers,
        highlightUnanswered: highlightUnanswered,
        exportAnswers: exportAnswers,
        showInstructions: showInstructions,
        toggleDarkMode: toggleDarkMode,
        increaseFontSize: increaseFontSize,
        decreaseFontSize: decreaseFontSize,
        showProgressSummary: showProgressSummary
    });

    // Initialize when document ready
    $(document).ready(function() {
        initExamSystem();
        checkDarkModePreference();
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (ExamSystem.autoSaveInterval) {
            clearInterval(ExamSystem.autoSaveInterval);
        }
    });

})(jQuery);