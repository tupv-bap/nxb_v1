<?php
/**
 * Template: Thi Online
 * Shortcode: [exam_test]
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if (!$exam_id) {
    echo '<div class="exam-error">Không tìm thấy đề thi. <a href="?">Quay lại</a></div>';
    return;
}

// Get exam
$exam = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}exam_papers WHERE id = %d",
    $exam_id
));

if (!$exam) {
    echo '<div class="exam-error">Đề thi không tồn tại. <a href="?">Quay lại</a></div>';
    return;
}

// Check attempts
$user_ip = exam_get_user_ip();
$attempts_24h = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}exam_results 
     WHERE exam_id = %d AND user_ip = %s 
     AND submit_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
    $exam_id, $user_ip
));

$max_attempts = get_option('exam_max_attempts_per_day', 10);
if ($attempts_24h >= $max_attempts) {
    echo '<div class="exam-error">Bạn đã vượt quá số lần thi cho phép. <a href="?">Quay lại</a></div>';
    return;
}

// Get questions
$question_ids = json_decode($exam->question_ids, true);
$questions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}exam_questions 
     WHERE id IN (" . implode(',', array_fill(0, count($question_ids), '%d')) . ")
     ORDER BY FIELD(id, " . implode(',', $question_ids) . ")",
    ...$question_ids
));

$total_points = array_sum(array_column($questions, 'points'));
$enable_file_upload = get_option('exam_enable_file_upload', 1);

?>

<div class="exam-test-container" id="exam-app">
    <!-- Header -->
    <div class="exam-test-header">
        <div class="header-content">
            <h1 class="exam-test-title"><?php echo esc_html($exam->title); ?></h1>
            <div class="exam-meta-info">
                <span class="meta-badge"><?php echo esc_html($exam->subject); ?></span>
                <span class="meta-badge"><?php echo count($questions); ?> câu</span>
                <span class="meta-badge"><?php echo $total_points; ?> điểm</span>
            </div>
        </div>
        
        <!-- Timer -->
        <div class="exam-timer" id="timer">
            <div class="timer-icon">⏱️</div>
            <div class="timer-display" id="timer-display">
                <span id="minutes"><?php echo $exam->duration; ?></span>:<span id="seconds">00</span>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="exam-progress">
        <div class="progress-info">
            <span>Tiến độ: <strong id="progress-text">0%</strong></span>
            <span>Đã trả lời: <strong id="answered-count">0</strong>/<?php echo count($questions); ?></span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="progress-fill"></div>
        </div>
    </div>

    <!-- Questions -->
    <div class="exam-questions-container">
        <?php foreach ($questions as $idx => $q): 
            $options = $q->options ? json_decode($q->options, true) : [];
        ?>
            <div class="question-card" data-question-id="<?php echo $q->id; ?>" data-question-index="<?php echo $idx; ?>">
                <div class="question-header">
                    <span class="question-number">Câu <?php echo $idx + 1; ?></span>
                    <span class="question-points"><?php echo $q->points; ?> điểm</span>
                    <span class="question-type-badge"><?php 
                        echo match($q->question_type) {
                            'true_false' => 'Đúng/Sai',
                            'single_choice' => 'Trắc nghiệm',
                            'multiple_choice' => 'Nhiều đáp án',
                            'essay' => 'Tự luận',
                            default => ''
                        };
                    ?></span>
                </div>
                
                <div class="question-text">
                    <?php echo wp_kses_post($q->question_text); ?>
                </div>
                
                <div class="question-answers">
                    <?php if ($q->question_type == 'true_false'): ?>
                        <label class="answer-option">
                            <input type="radio" name="q_<?php echo $q->id; ?>" value="true" 
                                   onchange="saveAnswer(<?php echo $q->id; ?>, this.value)">
                            <span class="option-content">Đúng</span>
                        </label>
                        <label class="answer-option">
                            <input type="radio" name="q_<?php echo $q->id; ?>" value="false" 
                                   onchange="saveAnswer(<?php echo $q->id; ?>, this.value)">
                            <span class="option-content">Sai</span>
                        </label>
                        
                    <?php elseif ($q->question_type == 'single_choice'): ?>
                        <?php foreach ($options as $opt_idx => $option): ?>
                            <label class="answer-option">
                                <input type="radio" name="q_<?php echo $q->id; ?>" value="<?php echo $opt_idx; ?>" 
                                       onchange="saveAnswer(<?php echo $q->id; ?>, this.value)">
                                <span class="option-label"><?php echo chr(65 + $opt_idx); ?>.</span>
                                <span class="option-content"><?php echo esc_html($option); ?></span>
                            </label>
                        <?php endforeach; ?>
                        
                    <?php elseif ($q->question_type == 'multiple_choice'): ?>
                        <?php foreach ($options as $opt_idx => $option): ?>
                            <label class="answer-option">
                                <input type="checkbox" name="q_<?php echo $q->id; ?>[]" value="<?php echo $opt_idx; ?>" 
                                       onchange="saveMultipleAnswer(<?php echo $q->id; ?>)">
                                <span class="option-label"><?php echo chr(65 + $opt_idx); ?>.</span>
                                <span class="option-content"><?php echo esc_html($option); ?></span>
                            </label>
                        <?php endforeach; ?>
                        
                    <?php elseif ($q->question_type == 'essay'): ?>
                        <div class="essay-answer">
                            <textarea name="q_<?php echo $q->id; ?>" 
                                      rows="8" 
                                      placeholder="Nhập câu trả lời của bạn..."
                                      onchange="saveAnswer(<?php echo $q->id; ?>, this.value)"
                                      onkeyup="saveAnswer(<?php echo $q->id; ?>, this.value)"></textarea>
                            
                            <?php if ($enable_file_upload): ?>
                                <div class="file-upload-area">
                                    <label for="file_<?php echo $q->id; ?>" class="btn-upload">
                                        📎 Đính kèm file (tùy chọn)
                                    </label>
                                    <input type="file" 
                                           id="file_<?php echo $q->id; ?>" 
                                           accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx"
                                           onchange="uploadFile(<?php echo $q->id; ?>, this)"
                                           style="display: none;">
                                    <div id="file_preview_<?php echo $q->id; ?>" class="file-preview"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Navigation -->
    <div class="exam-navigation">
        <div class="nav-grid">
            <?php for ($i = 0; $i < count($questions); $i++): ?>
                <button class="nav-btn" data-index="<?php echo $i; ?>" onclick="scrollToQuestion(<?php echo $i; ?>)">
                    <?php echo $i + 1; ?>
                </button>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Submit Section -->
    <div class="exam-submit-section">
        <div class="submit-warning">
            ⚠️ Hãy kiểm tra kỹ bài làm trước khi nộp. Bạn không thể sửa sau khi nộp bài!
        </div>
        <button class="btn-submit" onclick="confirmSubmit()">
            📤 Nộp bài
        </button>
    </div>
</div>

<!-- Result Modal -->
<div id="result-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h2 id="result-title">Kết quả thi</h2>
        <div id="result-body"></div>
        <div class="modal-actions">
            <a href="../exam-detail?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary">Xem chi tiết</a>
            <a href="../" class="btn btn-secondary">Danh sách đề thi</a>
        </div>
    </div>
</div>

<script>
// Exam data
const examData = {
    id: <?php echo $exam_id; ?>,
    duration: <?php echo $exam->duration; ?>,
    totalQuestions: <?php echo count($questions); ?>,
    passingScore: <?php echo $exam->passing_score; ?>
};

// Answers storage
let answers = {};
let startTime = new Date();
let timerInterval;
let timeRemaining = examData.duration * 60; // seconds

// Load saved answers from localStorage
function loadSavedAnswers() {
    const saved = localStorage.getItem('exam_' + examData.id + '_answers');
    if (saved) {
        answers = JSON.parse(saved);
        restoreAnswers();
    }
}

function restoreAnswers() {
    for (let qId in answers) {
        const value = answers[qId];
        if (Array.isArray(value)) {
            value.forEach(v => {
                const checkbox = document.querySelector(`input[name="q_${qId}[]"][value="${v}"]`);
                if (checkbox) checkbox.checked = true;
            });
        } else {
            const input = document.querySelector(`input[name="q_${qId}"][value="${value}"], textarea[name="q_${qId}"]`);
            if (input) {
                if (input.type === 'radio') {
                    input.checked = true;
                } else if (input.tagName === 'TEXTAREA') {
                    input.value = value;
                }
            }
        }
    }
    updateProgress();
}

function saveAnswer(questionId, value) {
    answers[questionId] = value;
    localStorage.setItem('exam_' + examData.id + '_answers', JSON.stringify(answers));
    updateProgress();
}

function saveMultipleAnswer(questionId) {
    const checkboxes = document.querySelectorAll(`input[name="q_${questionId}[]"]:checked`);
    const values = Array.from(checkboxes).map(cb => parseInt(cb.value));
    answers[questionId] = values;
    localStorage.setItem('exam_' + examData.id + '_answers', JSON.stringify(answers));
    updateProgress();
}

function updateProgress() {
    const answered = Object.keys(answers).filter(k => {
        const val = answers[k];
        return val !== '' && val !== null && (!Array.isArray(val) || val.length > 0);
    }).length;
    
    const percentage = Math.round((answered / examData.totalQuestions) * 100);
    
    document.getElementById('answered-count').textContent = answered;
    document.getElementById('progress-text').textContent = percentage + '%';
    document.getElementById('progress-fill').style.width = percentage + '%';
    
    // Update navigation buttons
    document.querySelectorAll('.nav-btn').forEach(btn => {
        const index = parseInt(btn.dataset.index);
        const questionCard = document.querySelectorAll('.question-card')[index];
        const qId = questionCard.dataset.questionId;
        
        if (answers[qId] !== undefined && answers[qId] !== '' && 
            (!Array.isArray(answers[qId]) || answers[qId].length > 0)) {
            btn.classList.add('answered');
        } else {
            btn.classList.remove('answered');
        }
    });
}

function scrollToQuestion(index) {
    const question = document.querySelectorAll('.question-card')[index];
    question.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function startTimer() {
    timerInterval = setInterval(() => {
        timeRemaining--;
        
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        
        document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
        document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
        
        if (timeRemaining <= 300) { // 5 minutes
            document.getElementById('timer').classList.add('timer-warning');
        }
        
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            alert('Hết giờ! Bài thi sẽ được tự động nộp.');
            submitExam();
        }
    }, 1000);
}

function confirmSubmit() {
    const answered = Object.keys(answers).length;
    const unanswered = examData.totalQuestions - answered;
    
    let message = 'Bạn có chắc chắn muốn nộp bài?';
    if (unanswered > 0) {
        message += `\n\nBạn còn ${unanswered} câu chưa trả lời!`;
    }
    
    if (confirm(message)) {
        submitExam();
    }
}

function submitExam() {
    clearInterval(timerInterval);
    
    const submitBtn = document.querySelector('.btn-submit');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Đang nộp bài...';
    
    jQuery.ajax({
        url: examData.ajaxurl,
        method: 'POST',
        data: {
            action: 'submit_exam',
            nonce: examData.nonce,
            exam_id: examData.id,
            answers: JSON.stringify(answers),
            start_time: startTime.toISOString()
        },
        success: function(response) {
            if (response.success) {
                localStorage.removeItem('exam_' + examData.id + '_answers');
                showResult(response.data);
            } else {
                alert('Lỗi: ' + response.data.message);
                submitBtn.disabled = false;
                submitBtn.textContent = '📤 Nộp bài';
            }
        },
        error: function() {
            alert('Có lỗi xảy ra. Vui lòng thử lại!');
            submitBtn.disabled = false;
            submitBtn.textContent = '📤 Nộp bài';
        }
    });
}

function showResult(data) {
    const percentage = data.percentage;
    const passed = percentage >= examData.passingScore;
    
    document.getElementById('result-title').textContent = passed ? '🎉 Chúc mừng!' : '😔 Chưa đạt';
    document.getElementById('result-body').innerHTML = `
        <div class="result-score ${passed ? 'passed' : 'failed'}">
            <div class="score-circle">
                <div class="score-value">${percentage}%</div>
            </div>
            <div class="score-details">
                <p>Điểm: <strong>${data.score}/${data.total_points}</strong></p>
                <p>Điểm đạt: <strong>${examData.passingScore}%</strong></p>
                <p class="result-status ${passed ? 'passed' : 'failed'}">
                    ${passed ? '✓ Đạt yêu cầu' : '✗ Chưa đạt yêu cầu'}
                </p>
            </div>
        </div>
    `;
    
    document.getElementById('result-modal').style.display = 'flex';
}

function uploadFile(questionId, input) {
    if (input.files.length === 0) return;
    
    const file = input.files[0];
    const formData = new FormData();
    formData.append('action', 'upload_essay_file');
    formData.append('nonce', examData.nonce);
    formData.append('file', file);
    
    const preview = document.getElementById('file_preview_' + questionId);
    preview.innerHTML = '<span class="uploading">Đang tải lên...</span>';
    
    jQuery.ajax({
        url: examData.ajaxurl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                preview.innerHTML = `<span class="file-uploaded">✓ ${file.name}</span>`;
                saveAnswer(questionId, 'FILE:' + response.data.url);
            } else {
                alert('Lỗi upload: ' + response.data.message);
                preview.innerHTML = '';
            }
        },
        error: function() {
            alert('Lỗi upload file!');
            preview.innerHTML = '';
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    examData.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    examData.nonce = '<?php echo wp_create_nonce('exam_frontend_nonce'); ?>';
    
    loadSavedAnswers();
    startTimer();
    updateProgress();
    
    // Auto-save every 30 seconds
    setInterval(() => {
        if (Object.keys(answers).length > 0) {
            console.log('Auto-saved');
        }
    }, 30000);
});

// Warn before leaving
window.addEventListener('beforeunload', function(e) {
    if (Object.keys(answers).length > 0 && timerInterval) {
        e.preventDefault();
        e.returnValue = '';
        return '';
    }
});
</script>

<style>
.exam-test-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.exam-test-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.exam-test-title {
    font-size: 28px;
    margin: 0 0 10px;
}

.exam-meta-info {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.meta-badge {
    background: rgba(255,255,255,0.3);
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 13px;
}

.exam-timer {
    background: rgba(255,255,255,0.2);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    min-width: 120px;
}

.timer-icon {
    font-size: 32px;
    margin-bottom: 5px;
}

.timer-display {
    font-size: 28px;
    font-weight: bold;
}

.timer-warning {
    animation: pulse 1s infinite;
    background: #e74c3c !important;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.exam-progress {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 14px;
    color: #666;
}

.progress-bar {
    height: 20px;
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #27ae60, #2ecc71);
    transition: width 0.3s;
}

.exam-questions-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}

.question-card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.question-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.question-number {
    background: #3498db;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
}

.question-points {
    background: #f39c12;
    color: white;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 13px;
}

.question-type-badge {
    background: #95a5a6;
    color: white;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 13px;
    margin-left: auto;
}

.question-text {
    font-size: 18px;
    line-height: 1.6;
    color: #2c3e50;
    margin-bottom: 25px;
}

.question-answers {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.answer-option {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px;
    background: #f8f9fa;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.answer-option:hover {
    background: #e9ecef;
    border-color: #3498db;
}

.answer-option input[type="radio"],
.answer-option input[type="checkbox"] {
    margin-top: 3px;
    cursor: pointer;
}

.answer-option input:checked ~ .option-content {
    font-weight: 600;
    color: #3498db;
}

.option-label {
    font-weight: 600;
    color: #666;
}

.option-content {
    flex: 1;
    line-height: 1.5;
}

.essay-answer textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    font-family: inherit;
    resize: vertical;
    min-height: 150px;
}

.essay-answer textarea:focus {
    outline: none;
    border-color: #3498db;
}

.file-upload-area {
    margin-top: 15px;
}

.btn-upload {
    display: inline-block;
    padding: 10px 20px;
    background: #95a5a6;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.btn-upload:hover {
    background: #7f8c8d;
}

.file-preview {
    margin-top: 10px;
    font-size: 14px;
}

.file-uploaded {
    color: #27ae60;
    font-weight: 600;
}

.uploading {
    color: #f39c12;
}

.exam-navigation {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.nav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
    gap: 10px;
}

.nav-btn {
    padding: 12px;
    background: #f8f9fa;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.nav-btn:hover {
    background: #e9ecef;
}

.nav-btn.answered {
    background: #27ae60;
    color: white;
    border-color: #27ae60;
}

.exam-submit-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.submit-warning {
    background: #fff3cd;
    color: #856404;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.btn-submit {
    background: #27ae60;
    color: white;
    border: none;
    padding: 18px 50px;
    font-size: 18px;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-submit:hover {
    background: #229954;
    transform: translateY(-2px);
}

.btn-submit:disabled {
    background: #95a5a6;
    cursor: not-allowed;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: white;
    padding: 40px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
}

.modal-content h2 {
    margin: 0 0 30px;
    text-align: center;
    font-size: 32px;
}

.result-score {
    text-align: center;
    padding: 30px;
}

.score-circle {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.result-score.failed .score-circle {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
}

.score-value {
    font-size: 48px;
    font-weight: bold;
    color: white;
}

.score-details {
    font-size: 18px;
}

.score-details p {
    margin: 10px 0;
}

.result-status {
    font-size: 20px;
    font-weight: bold;
    margin-top: 15px;
}

.result-status.passed {
    color: #27ae60;
}

.result-status.failed {
    color: #e74c3c;
}

.modal-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
}

.btn {
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

@media (max-width: 768px) {
    .exam-test-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .nav-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}
</style>