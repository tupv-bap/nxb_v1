<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Thống kê tổng quan
$total_exams = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_papers");
$total_questions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_questions");
$total_results = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_results");
$unique_ips = $wpdb->get_var("SELECT COUNT(DISTINCT user_ip) FROM {$wpdb->prefix}exam_results");

// Thống kê 24h qua
$results_24h = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_results WHERE submit_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$ips_24h = $wpdb->get_var("SELECT COUNT(DISTINCT user_ip) FROM {$wpdb->prefix}exam_results WHERE submit_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");

// Top đề thi phổ biến
$top_exams = $wpdb->get_results("
    SELECT e.id, e.title, e.subject, e.category, COUNT(r.id) as attempt_count
    FROM {$wpdb->prefix}exam_papers e
    LEFT JOIN {$wpdb->prefix}exam_results r ON e.id = r.exam_id
    GROUP BY e.id
    ORDER BY attempt_count DESC
    LIMIT 5
");

// Điểm trung bình theo môn
$avg_scores = $wpdb->get_results("
    SELECT e.subject, 
           COUNT(r.id) as total_attempts,
           AVG(r.score / r.total_points * 100) as avg_percentage
    FROM {$wpdb->prefix}exam_results r
    JOIN {$wpdb->prefix}exam_papers e ON r.exam_id = e.id
    GROUP BY e.subject
    ORDER BY avg_percentage DESC
");

?>

<div class="wrap">
    <h1>Dashboard - Thi Online System</h1>
    
    <div class="exam-dashboard">
        <!-- Thống kê tổng quan -->
        <div class="exam-stats-grid">
            <div class="exam-stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_exams); ?></h3>
                    <p>Tổng số đề thi</p>
                </div>
            </div>
            
            <div class="exam-stat-card">
                <div class="stat-icon">❓</div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_questions); ?></h3>
                    <p>Tổng số câu hỏi</p>
                </div>
            </div>
            
            <div class="exam-stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_results); ?></h3>
                    <p>Lượt thi tổng</p>
                </div>
            </div>
            
            <div class="exam-stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?php echo number_format($unique_ips); ?></h3>
                    <p>Người dùng (IP)</p>
                </div>
            </div>
        </div>

        <!-- Thống kê 24h -->
        <div class="exam-stats-24h">
            <h2>📊 Thống kê 24 giờ qua</h2>
            <div class="stats-24h-grid">
                <div class="stat-24h-item">
                    <strong><?php echo number_format($results_24h); ?></strong>
                    <span>Lượt thi</span>
                </div>
                <div class="stat-24h-item">
                    <strong><?php echo number_format($ips_24h); ?></strong>
                    <span>IP truy cập</span>
                </div>
                <div class="stat-24h-item">
                    <strong><?php echo $results_24h > 0 ? number_format($results_24h / $ips_24h, 1) : '0'; ?></strong>
                    <span>TB lượt thi/IP</span>
                </div>
            </div>
        </div>

        <div class="exam-dashboard-row">
            <!-- Top đề thi -->
            <div class="exam-dashboard-col">
                <h2>🏆 Top đề thi phổ biến</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Đề thi</th>
                            <th>Môn</th>
                            <th>Tỉnh</th>
                            <th>Lượt thi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_exams)): ?>
                            <?php foreach ($top_exams as $exam): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($exam->title); ?></strong></td>
                                    <td><?php echo esc_html($exam->subject); ?></td>
                                    <td><?php echo esc_html($exam->category); ?></td>
                                    <td><span class="badge"><?php echo number_format($exam->attempt_count); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">Chưa có dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Điểm trung bình theo môn -->
            <div class="exam-dashboard-col">
                <h2>📈 Điểm trung bình theo môn</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Môn học</th>
                            <th>Lượt thi</th>
                            <th>Điểm TB</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($avg_scores)): ?>
                            <?php foreach ($avg_scores as $score): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($score->subject); ?></strong></td>
                                    <td><?php echo number_format($score->total_attempts); ?></td>
                                    <td>
                                        <div class="score-bar">
                                            <div class="score-fill" style="width: <?php echo round($score->avg_percentage); ?>%"></div>
                                            <span><?php echo round($score->avg_percentage, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3">Chưa có dữ liệu</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="exam-quick-actions">
            <h2>⚡ Thao tác nhanh</h2>
            <div class="quick-actions-grid">
                <a href="<?php echo admin_url('admin.php?page=exam-papers&action=new'); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Tạo đề thi mới
                </a>
                <a href="<?php echo admin_url('admin.php?page=exam-questions&action=new'); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-edit"></span>
                    Thêm câu hỏi
                </a>
                <a href="<?php echo admin_url('admin.php?page=exam-statistics'); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-chart-bar"></span>
                    Xem thống kê
                </a>
                <a href="<?php echo admin_url('admin.php?page=exam-settings'); ?>" class="quick-action-btn">
                    <span class="dashicons dashicons-admin-settings"></span>
                    Cài đặt
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.exam-dashboard {
    padding: 20px 0;
}

.exam-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.exam-stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-icon {
    font-size: 48px;
}

.stat-info h3 {
    margin: 0;
    font-size: 32px;
    color: #2271b1;
}

.stat-info p {
    margin: 5px 0 0;
    color: #666;
}

.exam-stats-24h {
    background: #f0f6fc;
    border: 1px solid #c3d6e8;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.stats-24h-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 15px;
}

.stat-24h-item {
    text-align: center;
    padding: 15px;
    background: white;
    border-radius: 6px;
}

.stat-24h-item strong {
    display: block;
    font-size: 28px;
    color: #2271b1;
    margin-bottom: 5px;
}

.exam-dashboard-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.exam-dashboard-col {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.exam-dashboard-col h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #2271b1;
}

.badge {
    background: #2271b1;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.score-bar {
    position: relative;
    height: 30px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.score-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #5ba0d0);
    transition: width 0.3s;
}

.score-bar span {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-weight: bold;
    color: #333;
}

.exam-quick-actions {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.quick-action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px;
    background: #2271b1;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: background 0.3s;
}

.quick-action-btn:hover {
    background: #135e96;
    color: white;
}

@media (max-width: 768px) {
    .exam-dashboard-row {
        grid-template-columns: 1fr;
    }
    
    .stats-24h-grid {
        grid-template-columns: 1fr;
    }
}
</style>