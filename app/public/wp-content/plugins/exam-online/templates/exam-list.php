<?php
/**
 * Template: Danh sách đề thi
 * Shortcode: [exam_list]
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// Lấy danh sách môn học
$subjects = $wpdb->get_results("
    SELECT subject, COUNT(*) as exam_count
    FROM {$wpdb->prefix}exam_papers
    GROUP BY subject
    ORDER BY subject ASC
");

// Lấy danh sách tỉnh/category
$categories = $wpdb->get_results("
    SELECT category, COUNT(*) as exam_count
    FROM {$wpdb->prefix}exam_papers
    GROUP BY category
    ORDER BY category ASC
");

// Filter
$selected_subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '';
$selected_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Build query
$where = "1=1";
if ($selected_subject) {
    $where .= $wpdb->prepare(" AND subject = %s", $selected_subject);
}
if ($selected_category) {
    $where .= $wpdb->prepare(" AND category = %s", $selected_category);
}
if ($search) {
    $where .= $wpdb->prepare(" AND (title LIKE %s OR description LIKE %s)", 
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%'
    );
}

// Get exams
$exams = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}exam_papers
    WHERE $where
    ORDER BY created_at DESC
");

// Get attempt counts
$user_ip = exam_get_user_ip();
foreach ($exams as $exam) {
    $exam->my_attempts = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}exam_results 
         WHERE exam_id = %d AND user_ip = %s",
        $exam->id, $user_ip
    ));
    $exam->total_attempts = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}exam_results WHERE exam_id = %d",
        $exam->id
    ));
}

?>

<div class="exam-list-container">
    <!-- Header -->
    <div class="exam-list-header">
        <h1 class="exam-list-title">📚 Danh Sách Đề Thi</h1>
        <p class="exam-list-subtitle">Chọn môn học và đề thi để bắt đầu làm bài</p>
    </div>

    <!-- Filters -->
    <div class="exam-filters-box">
        <form method="get" class="exam-filter-form">
            <div class="filter-grid">
                <div class="filter-item">
                    <label for="subject-filter">📖 Môn học</label>
                    <select name="subject" id="subject-filter" onchange="this.form.submit()">
                        <option value="">-- Tất cả môn học --</option>
                        <?php foreach ($subjects as $subj): ?>
                            <option value="<?php echo esc_attr($subj->subject); ?>" 
                                    <?php selected($selected_subject, $subj->subject); ?>>
                                <?php echo esc_html($subj->subject); ?> (<?php echo $subj->exam_count; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <label for="category-filter">🗺️ Tỉnh/Khu vực</label>
                    <select name="category" id="category-filter" onchange="this.form.submit()">
                        <option value="">-- Tất cả tỉnh --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->category); ?>" 
                                    <?php selected($selected_category, $cat->category); ?>>
                                <?php echo esc_html($cat->category); ?> (<?php echo $cat->exam_count; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-item filter-search">
                    <label for="search-input">🔍 Tìm kiếm</label>
                    <input type="text" name="search" id="search-input" 
                           value="<?php echo esc_attr($search); ?>" 
                           placeholder="Nhập tên đề thi...">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="?" class="btn btn-secondary">Xóa lọc</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Exam Cards -->
    <div class="exam-cards-container">
        <?php if (!empty($exams)): ?>
            <div class="exam-cards-grid">
                <?php foreach ($exams as $exam): 
                    $question_count = count(json_decode($exam->question_ids, true));
                    $can_attempt = $exam->my_attempts < get_option('exam_max_attempts_per_day', 10);
                ?>
                    <div class="exam-card">
                        <div class="exam-card-header">
                            <h3 class="exam-card-title"><?php echo esc_html($exam->title); ?></h3>
                            <div class="exam-card-badges">
                                <span class="badge badge-subject"><?php echo esc_html($exam->subject); ?></span>
                                <span class="badge badge-category"><?php echo esc_html($exam->category); ?></span>
                            </div>
                        </div>

                        <div class="exam-card-body">
                            <?php if ($exam->description): ?>
                                <p class="exam-description"><?php echo esc_html(wp_trim_words($exam->description, 20)); ?></p>
                            <?php endif; ?>

                            <div class="exam-meta">
                                <div class="meta-item">
                                    <span class="meta-icon">❓</span>
                                    <span class="meta-text"><?php echo $question_count; ?> câu hỏi</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-icon">⏱️</span>
                                    <span class="meta-text"><?php echo $exam->duration; ?> phút</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-icon">📊</span>
                                    <span class="meta-text">Đạt: <?php echo $exam->passing_score; ?>%</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-icon">👥</span>
                                    <span class="meta-text"><?php echo number_format($exam->total_attempts); ?> lượt thi</span>
                                </div>
                            </div>

                            <div class="exam-attempts">
                                <?php if ($exam->my_attempts > 0): ?>
                                    <span class="attempts-info">
                                        Bạn đã thi: <strong><?php echo $exam->my_attempts; ?></strong> lần
                                    </span>
                                <?php else: ?>
                                    <span class="attempts-info new-exam">Đề thi mới</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="exam-card-footer">
                            <a href="?exam_id=<?php echo $exam->id; ?>&page=detail" class="btn btn-detail">
                                📋 Chi tiết
                            </a>
                            <?php if ($can_attempt): ?>
                                <a href="?exam_id=<?php echo $exam->id; ?>&page=test" class="btn btn-start">
                                    ▶️ Bắt đầu thi
                                </a>
                            <?php else: ?>
                                <button class="btn btn-disabled" disabled title="Đã vượt quá số lần thi cho phép">
                                    🚫 Đã hết lượt
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="exam-empty-state">
                <div class="empty-icon">📭</div>
                <h3>Không tìm thấy đề thi nào</h3>
                <p>Vui lòng thử lại với bộ lọc khác hoặc quay lại sau.</p>
                <a href="?" class="btn btn-primary">Xóa bộ lọc</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stats Summary -->
    <?php if (!empty($exams)): ?>
        <div class="exam-stats-summary">
            <div class="stat-box">
                <div class="stat-number"><?php echo count($exams); ?></div>
                <div class="stat-label">Đề thi</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo array_sum(array_column($exams, 'total_attempts')); ?></div>
                <div class="stat-label">Lượt thi</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo get_option('exam_max_attempts_per_day', 10); ?></div>
                <div class="stat-label">Giới hạn/24h</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.exam-list-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.exam-list-header {
    text-align: center;
    margin-bottom: 40px;
}

.exam-list-title {
    font-size: 36px;
    margin: 0 0 10px;
    color: #2c3e50;
}

.exam-list-subtitle {
    font-size: 18px;
    color: #7f8c8d;
    margin: 0;
}

.exam-filters-box {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 20px;
    align-items: end;
}

.filter-item label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #2c3e50;
}

.filter-item select,
.filter-item input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 15px;
    transition: border-color 0.3s;
}

.filter-item select:focus,
.filter-item input:focus {
    outline: none;
    border-color: #3498db;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
    text-align: center;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

.exam-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.exam-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}

.exam-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.exam-card-header {
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.exam-card-title {
    font-size: 20px;
    margin: 0 0 12px;
    line-height: 1.4;
}

.exam-card-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-subject {
    background: rgba(255,255,255,0.3);
    color: white;
}

.badge-category {
    background: rgba(255,255,255,0.2);
    color: white;
}

.exam-card-body {
    padding: 20px;
}

.exam-description {
    color: #555;
    line-height: 1.6;
    margin: 0 0 15px;
}

.exam-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 15px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #666;
}

.meta-icon {
    font-size: 18px;
}

.exam-attempts {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: center;
    margin-top: 15px;
}

.attempts-info {
    font-size: 14px;
    color: #666;
}

.attempts-info.new-exam {
    color: #27ae60;
    font-weight: 600;
}

.exam-card-footer {
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    gap: 10px;
}

.btn-detail {
    flex: 1;
    background: #95a5a6;
    color: white;
}

.btn-detail:hover {
    background: #7f8c8d;
}

.btn-start {
    flex: 2;
    background: #27ae60;
    color: white;
}

.btn-start:hover {
    background: #229954;
}

.btn-disabled {
    flex: 2;
    background: #e0e0e0;
    color: #999;
    cursor: not-allowed;
}

.exam-empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 12px;
}

.empty-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.exam-empty-state h3 {
    font-size: 24px;
    margin: 0 0 10px;
    color: #2c3e50;
}

.exam-empty-state p {
    color: #7f8c8d;
    margin: 0 0 20px;
}

.exam-stats-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.stat-box {
    background: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    color: #3498db;
    margin-bottom: 8px;
}

.stat-label {
    color: #7f8c8d;
    font-size: 14px;
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .exam-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .exam-stats-summary {
        grid-template-columns: 1fr;
    }
    
    .exam-meta {
        grid-template-columns: 1fr;
    }
}
</style>