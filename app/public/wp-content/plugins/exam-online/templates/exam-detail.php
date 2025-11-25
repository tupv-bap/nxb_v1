<?php
/**
 * Template: Chi tiết đề thi
 * Shortcode: [exam_detail]
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if (!$exam_id) {
    echo '<div class="exam-error">Không tìm thấy đề thi. <a href="?">Quay lại danh sách</a></div>';
    return;
}

// Get exam
$exam = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}exam_papers WHERE id = %d",
    $exam_id
));

if (!$exam) {
    echo '<div class="exam-error">Đề thi không tồn tại. <a href="?">Quay lại danh sách</a></div>';
    return;
}

// Get questions
$question_ids = json_decode($exam->question_ids, true);
$questions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}exam_questions WHERE id IN (" . implode(',', array_fill(0, count($question_ids), '%d')) . ")",
    ...$question_ids
));

// Get user's previous results
$user_ip = exam_get_user_ip();
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}exam_results 
     WHERE exam_id = %d AND user_ip = %s 
     ORDER BY submit_time DESC",
    $exam_id, $user_ip
));

// Check attempts
$attempts_24h = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}exam_results 
     WHERE exam_id = %d AND user_ip = %s 
     AND submit_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
    $exam_id, $user_ip
));

$max_attempts = get_option('exam_max_attempts_per_day', 10);
$remaining_attempts = max(0, $max_attempts - $attempts_24h);
$can_attempt = $remaining_attempts > 0;

// Create question map for showing answers
$question_map = [];
foreach ($questions as $q) {
    $question_map[$q->id] = $q;
}

// Calculate total points
$total_points = array_sum(array_column($questions, 'points'));

?>

<div class="exam-detail-container">
    <!-- Breadcrumb -->
    <div class="exam-breadcrumb">
        <a href="?">← Danh sách đề thi</a>
    </div>

    <!-- Exam Info -->
    <div class="exam-detail-header">
        <div class="exam-header-content">
            <h1 class="exam-title"><?php echo esc_html($exam->title); ?></h1>
            <div class="exam-badges">
                <span class="badge badge-subject"><?php echo esc_html($exam->subject); ?></span>
                <span class="badge badge-category"><?php echo esc_html($exam->category); ?></span>
            </div>
            
            <?php if ($exam->description): ?>
                <p class="exam-description"><?php echo esc_html($exam->description); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Exam Stats -->
    <div class="exam-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">❓</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($questions); ?></div>
                <div class="stat-label">Câu hỏi</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏱️</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $exam->duration; ?></div>
                <div class="stat-label">Phút</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💯</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $total_points; ?></div>
                <div class="stat-label">Tổng điểm</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎯</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $exam->passing_score; ?>%</div>
                <div class="stat-label">Điểm đạt</div>
            </div>
        </div>
    </div>

    <!-- Question Types Breakdown -->
    <div class="exam-section">
        <h2 class="section-title">📊 Cấu trúc đề thi</h2>
        <div class="question-types-grid">
            <?php
            $type_counts = [];
            $type_points = [];
            foreach ($questions as $q) {
                $type_counts[$q->question_type] = ($type_counts[$q->question_type] ?? 0) + 1;
                $type_points[$q->question_type] = ($type_points[$q->question_type] ?? 0) + $q->points;
            }
            
            $type_labels = [
                'true_false' => ['Đúng/Sai', '✓'],
                'single_choice' => ['Trắc nghiệm (1 đáp án)', '○'],
                'multiple_choice' => ['Trắc nghiệm (nhiều đáp án)', '☑'],
                'essay' => ['Tự luận', '📝']
            ];
            
            foreach ($type_counts as $type => $count):
                $label = $type_labels[$type] ?? ['Unknown', '?'];
            ?>
                <div class="type-card">
                    <div class="type-icon"><?php echo $label[1]; ?></div>
                    <div class="type-info">
                        <div class="type-name"><?php echo $label[0]; ?></div>
                        <div class="type-stats">
                            <?php echo $count; ?> câu • <?php echo $type_points[$type]; ?> điểm
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Previous Results -->
    <?php if (!empty($results)): ?>
        <div class="exam-section">
            <h2 class="section-title">📈 Kết quả thi trước đó</h2>
            
            <div class="results-summary">
                <div class="summary-item">
                    <span class="summary-label">Số lần đã thi:</span>
                    <span class="summary-value"><?php echo count($results); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Điểm cao nhất:</span>
                    <span class="summary-value">
                        <?php 
                        $max_score = max(array_map(function($r) {
                            return ($r->score / $r->total_points) * 100;
                        }, $results));
                        echo round($max_score, 1);
                        ?>%
                    </span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Lần thi gần nhất:</span>
                    <span class="summary-value">
                        <?php echo date('d/m/Y H:i', strtotime($results[0]->submit_time)); ?>
                    </span>
                </div>
            </div>

            <div class="results-list">
                <?php foreach (array_slice($results, 0, 5) as $idx => $result): 
                    $percentage = ($result->score / $result->total_points) * 100;
                    $passed = $percentage >= $exam->passing_score;
                    $answers = json_decode($result->answers, true);
                ?>
                    <div class="result-item <?php echo $passed ? 'passed' : 'failed'; ?>">
                        <div class="result-header">
                            <div class="result-number">Lần <?php echo $idx + 1; ?></div>
                            <div class="result-score">
                                <?php echo round($percentage, 1); ?>%
                                <span class="result-status"><?php echo $passed ? '✓ Đạt' : '✗ Không đạt'; ?></span>
                            </div>
                        </div>
                        <div class="result-details">
                            <span><?php echo $result->score; ?>/<?php echo $result->total_points; ?> điểm</span>
                            <span>•</span>
                            <span><?php echo date('d/m/Y H:i', strtotime($result->submit_time)); ?></span>
                        </div>
                        
                        <button class="btn-toggle-answers" onclick="toggleAnswers(<?php echo $idx; ?>)">
                            <span class="toggle-text">Xem đáp án</span>
                            <span class="toggle-icon">▼</span>
                        </button>
                        
                        <div class="result-answers" id="answers-<?php echo $idx; ?>" style="display: none;">
                            <?php foreach ($answers as $q_id => $answer_data): 
                                $question = $question_map[$q_id];
                                $is_correct = $answer_data['is_correct'];
                            ?>
                                <div class="answer-item <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                                    <div class="answer-question">
                                        <strong>Câu <?php echo array_search($q_id, $question_ids) + 1; ?>:</strong>
                                        <?php echo wp_trim_words(strip_tags($question->question_text), 20); ?>
                                    </div>
                                    
                                    <?php if ($question->question_type == 'essay'): ?>
                                        <div class="answer-essay">
                                            <div class="essay-label">Câu trả lời của bạn:</div>
                                            <div class="essay-content"><?php echo esc_html($answer_data['user_answer']); ?></div>
                                            
                                            <?php if ($question->essay_guide): ?>
                                                <div class="essay-guide">
                                                    <strong>Hướng dẫn đáp án:</strong>
                                                    <?php echo wp_kses_post($question->essay_guide); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="essay-note">
                                                ℹ️ Câu tự luận được tự động cho điểm tối đa
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="answer-comparison">
                                            <div class="answer-col">
                                                <span class="answer-label">Bạn chọn:</span>
                                                <span class="answer-value">
                                                    <?php echo is_array($answer_data['user_answer']) 
                                                        ? implode(', ', $answer_data['user_answer']) 
                                                        : $answer_data['user_answer']; ?>
                                                </span>
                                            </div>
                                            <?php if (!$is_correct): ?>
                                                <div class="answer-col">
                                                    <span class="answer-label">Đáp án đúng:</span>
                                                    <span class="answer-value correct-answer">
                                                        <?php 
                                                        $correct = json_decode($question->correct_answer, true);
                                                        echo is_array($correct) ? implode(', ', $correct) : $correct; 
                                                        ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Attempt Status -->
    <div class="exam-section attempt-status">
        <div class="attempt-info">
            <?php if ($can_attempt): ?>
                <div class="status-box status-available">
                    <div class="status-icon">✓</div>
                    <div class="status-content">
                        <h3>Bạn có thể làm bài thi này</h3>
                        <p>Còn lại <strong><?php echo $remaining_attempts; ?></strong> lượt thi trong 24h</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="status-box status-unavailable">
                    <div class="status-icon">✗</div>
                    <div class="status-content">
                        <h3>Đã hết lượt thi</h3>
                        <p>Bạn đã sử dụng hết <strong><?php echo $max_attempts; ?></strong> lượt thi trong 24h</p>
                        <p class="status-note">Vui lòng quay lại sau hoặc chọn đề thi khác</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="exam-actions">
        <a href="?" class="btn btn-secondary">
            ← Quay lại
        </a>
        <?php if ($can_attempt): ?>
            <a href="?exam_id=<?php echo $exam_id; ?>&page=test" class="btn btn-primary btn-large">
                ▶️ Bắt đầu làm bài
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleAnswers(idx) {
    const answersDiv = document.getElementById('answers-' + idx);
    const btn = event.target.closest('.btn-toggle-answers');
    const icon = btn.querySelector('.toggle-icon');
    
    if (answersDiv.style.display === 'none') {
        answersDiv.style.display = 'block';
        icon.textContent = '▲';
        btn.querySelector('.toggle-text').textContent = 'Ẩn đáp án';
    } else {
        answersDiv.style.display = 'none';
        icon.textContent = '▼';
        btn.querySelector('.toggle-text').textContent = 'Xem đáp án';
    }
}
</script>

<style>
.exam-detail-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.exam-breadcrumb {
    margin-bottom: 20px;
}

.exam-breadcrumb a {
    color: #3498db;
    text-decoration: none;
    font-size: 16px;
}

.exam-detail-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.exam-title {
    font-size: 32px;
    margin: 0 0 15px;
}

.exam-badges {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    background: rgba(255,255,255,0.3);
}

.exam-description {
    font-size: 16px;
    line-height: 1.6;
    margin: 15px 0 0;
    opacity: 0.95;
}

.exam-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-icon {
    font-size: 36px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
}

.stat-label {
    color: #7f8c8d;
    font-size: 14px;
}

.exam-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.section-title {
    font-size: 24px;
    margin: 0 0 20px;
    color: #2c3e50;
}

.question-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.type-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.type-icon {
    font-size: 32px;
}

.type-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.type-stats {
    color: #7f8c8d;
    font-size: 14px;
}

.results-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.summary-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.summary-label {
    color: #7f8c8d;
    font-size: 14px;
}

.summary-value {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
}

.results-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.result-item {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
}

.result-item.passed {
    border-color: #27ae60;
    background: #f0fff4;
}

.result-item.failed {
    border-color: #e74c3c;
    background: #fff5f5;
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.result-number {
    font-weight: 600;
    font-size: 18px;
}

.result-score {
    font-size: 24px;
    font-weight: bold;
}

.result-status {
    font-size: 14px;
    margin-left: 10px;
}

.result-details {
    color: #666;
    font-size: 14px;
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.btn-toggle-answers {
    background: #3498db;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background 0.3s;
}

.btn-toggle-answers:hover {
    background: #2980b9;
}

.result-answers {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #e0e0e0;
}

.answer-item {
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 6px;
    background: white;
}

.answer-item.correct {
    border-left: 4px solid #27ae60;
}

.answer-item.incorrect {
    border-left: 4px solid #e74c3c;
}

.answer-question {
    margin-bottom: 12px;
    color: #2c3e50;
}

.answer-comparison {
    display: flex;
    gap: 20px;
}

.answer-col {
    flex: 1;
}

.answer-label {
    display: block;
    font-size: 13px;
    color: #7f8c8d;
    margin-bottom: 5px;
}

.answer-value {
    display: block;
    font-weight: 600;
    color: #2c3e50;
}

.correct-answer {
    color: #27ae60;
}

.essay-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin: 10px 0;
}

.essay-guide {
    background: #fff3cd;
    padding: 15px;
    border-radius: 6px;
    margin: 10px 0;
}

.essay-note {
    color: #666;
    font-size: 13px;
    font-style: italic;
    margin-top: 10px;
}

.attempt-status {
    background: none;
    box-shadow: none;
    padding: 0;
}

.status-box {
    padding: 30px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.status-available {
    background: #d4edda;
    border: 2px solid #27ae60;
}

.status-unavailable {
    background: #f8d7da;
    border: 2px solid #e74c3c;
}

.status-icon {
    font-size: 48px;
}

.status-content h3 {
    margin: 0 0 10px;
    font-size: 22px;
}

.status-content p {
    margin: 5px 0;
    color: #666;
}

.status-note {
    font-size: 14px;
    font-style: italic;
}

.exam-actions {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    margin-top: 30px;
}

.btn {
    padding: 15px 30px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
}

.btn-primary {
    background: #27ae60;
    color: white;
}

.btn-primary:hover {
    background: #229954;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

.btn-large {
    padding: 18px 40px;
    font-size: 18px;
}

@media (max-width: 768px) {
    .exam-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .results-summary {
        grid-template-columns: 1fr;
    }
    
    .answer-comparison {
        flex-direction: column;
    }
    
    .exam-actions {
        flex-direction: column;
    }
}
</style>