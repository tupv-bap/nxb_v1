<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Thống kê theo khoảng thời gian
$period = isset($_GET['period']) ? $_GET['period'] : '7days';
$date_condition = match($period) {
    'today' => "DATE(submit_time) = CURDATE()",
    '7days' => "submit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30days' => "submit_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    'all' => "1=1",
    default => "submit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
};

// 1. Thống kê tổng quan
$total_attempts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_results WHERE $date_condition");
$unique_ips = $wpdb->get_var("SELECT COUNT(DISTINCT user_ip) FROM {$wpdb->prefix}exam_results WHERE $date_condition");
$avg_score = $wpdb->get_var("SELECT AVG(score / total_points * 100) FROM {$wpdb->prefix}exam_results WHERE $date_condition");

// 2. Thống kê theo IP
$ip_stats = $wpdb->get_results("
    SELECT 
        user_ip,
        COUNT(*) as total_attempts,
        COUNT(DISTINCT exam_id) as unique_exams,
        AVG(score / total_points * 100) as avg_percentage,
        MAX(submit_time) as last_attempt
    FROM {$wpdb->prefix}exam_results
    WHERE $date_condition
    GROUP BY user_ip
    ORDER BY total_attempts DESC
    LIMIT 20
");

// 3. Thống kê theo đề thi
$exam_stats = $wpdb->get_results("
    SELECT 
        e.id,
        e.title,
        e.subject,
        e.category,
        COUNT(r.id) as total_attempts,
        AVG(r.score / r.total_points * 100) as avg_percentage,
        SUM(CASE WHEN (r.score / r.total_points * 100) >= e.passing_score THEN 1 ELSE 0 END) as passed_count
    FROM {$wpdb->prefix}exam_papers e
    LEFT JOIN {$wpdb->prefix}exam_results r ON e.id = r.exam_id AND $date_condition
    GROUP BY e.id
    ORDER BY total_attempts DESC
    LIMIT 20
");

// 4. Thống kê theo môn học
$subject_stats = $wpdb->get_results("
    SELECT 
        e.subject,
        COUNT(r.id) as total_attempts,
        AVG(r.score / r.total_points * 100) as avg_percentage,
        COUNT(DISTINCT r.user_ip) as unique_users
    FROM {$wpdb->prefix}exam_results r
    JOIN {$wpdb->prefix}exam_papers e ON r.exam_id = e.id
    WHERE $date_condition
    GROUP BY e.subject
    ORDER BY total_attempts DESC
");

// 5. Thống kê theo tỉnh/category
$category_stats = $wpdb->get_results("
    SELECT 
        e.category,
        COUNT(r.id) as total_attempts,
        AVG(r.score / r.total_points * 100) as avg_percentage,
        COUNT(DISTINCT r.user_ip) as unique_users
    FROM {$wpdb->prefix}exam_results r
    JOIN {$wpdb->prefix}exam_papers e ON r.exam_id = e.id
    WHERE $date_condition
    GROUP BY e.category
    ORDER BY total_attempts DESC
");

// 6. Biểu đồ theo ngày (7 ngày gần nhất)
$daily_stats = $wpdb->get_results("
    SELECT 
        DATE(submit_time) as date,
        COUNT(*) as attempts,
        COUNT(DISTINCT user_ip) as unique_ips,
        AVG(score / total_points * 100) as avg_score
    FROM {$wpdb->prefix}exam_results
    WHERE submit_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(submit_time)
    ORDER BY date DESC
");

// 7. Top IP vi phạm giới hạn
$limit_violations = $wpdb->get_results("
    SELECT 
        user_ip,
        exam_id,
        attempt_count,
        last_attempt
    FROM {$wpdb->prefix}exam_ip_limits
    WHERE attempt_count >= " . get_option('exam_max_attempts_per_day', 10) . "
    ORDER BY last_attempt DESC
    LIMIT 20
");

?>

<div class="wrap">
    <h1>📊 Thống kê Chi tiết</h1>
    
    <div class="exam-stats-filter">
        <form method="get">
            <input type="hidden" name="page" value="exam-statistics">
            <label>Khoảng thời gian:</label>
            <select name="period" onchange="this.form.submit()">
                <option value="today" <?php selected($period, 'today'); ?>>Hôm nay</option>
                <option value="7days" <?php selected($period, '7days'); ?>>7 ngày qua</option>
                <option value="30days" <?php selected($period, '30days'); ?>>30 ngày qua</option>
                <option value="all" <?php selected($period, 'all'); ?>>Tất cả</option>
            </select>
        </form>
    </div>

    <!-- Tổng quan -->
    <div class="exam-stats-overview">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($total_attempts); ?></div>
            <div class="stat-label">Tổng lượt thi</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($unique_ips); ?></div>
            <div class="stat-label">IP duy nhất</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo round($avg_score, 1); ?>%</div>
            <div class="stat-label">Điểm trung bình</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_attempts > 0 ? round($total_attempts / $unique_ips, 1) : 0; ?></div>
            <div class="stat-label">TB lượt/IP</div>
        </div>
    </div>

    <!-- Biểu đồ theo ngày -->
    <div class="stats-section">
        <h2>📈 Xu hướng 7 ngày gần nhất</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Ngày</th>
                    <th>Lượt thi</th>
                    <th>IP duy nhất</th>
                    <th>Điểm TB</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_stats as $day): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($day->date)); ?></td>
                        <td><strong><?php echo number_format($day->attempts); ?></strong></td>
                        <td><?php echo number_format($day->unique_ips); ?></td>
                        <td>
                            <div class="mini-progress">
                                <div class="mini-progress-bar" style="width: <?php echo round($day->avg_score); ?>%"></div>
                                <span><?php echo round($day->avg_score, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="stats-grid">
        <!-- Thống kê theo môn học -->
        <div class="stats-section">
            <h2>📚 Thống kê theo Môn học</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Môn học</th>
                        <th>Lượt thi</th>
                        <th>Người dùng</th>
                        <th>Điểm TB</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($subject_stats)): ?>
                        <?php foreach ($subject_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo esc_html($stat->subject); ?></strong></td>
                                <td><?php echo number_format($stat->total_attempts); ?></td>
                                <td><?php echo number_format($stat->unique_users); ?></td>
                                <td>
                                    <div class="mini-progress">
                                        <div class="mini-progress-bar" style="width: <?php echo round($stat->avg_percentage); ?>%"></div>
                                        <span><?php echo round($stat->avg_percentage, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">Không có dữ liệu</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Thống kê theo tỉnh -->
        <div class="stats-section">
            <h2>🗺️ Thống kê theo Tỉnh</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Tỉnh</th>
                        <th>Lượt thi</th>
                        <th>Người dùng</th>
                        <th>Điểm TB</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($category_stats)): ?>
                        <?php foreach ($category_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo esc_html($stat->category); ?></strong></td>
                                <td><?php echo number_format($stat->total_attempts); ?></td>
                                <td><?php echo number_format($stat->unique_users); ?></td>
                                <td>
                                    <div class="mini-progress">
                                        <div class="mini-progress-bar" style="width: <?php echo round($stat->avg_percentage); ?>%"></div>
                                        <span><?php echo round($stat->avg_percentage, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">Không có dữ liệu</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Thống kê theo đề thi -->
    <div class="stats-section">
        <h2>📝 Thống kê theo Đề thi</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Đề thi</th>
                    <th>Môn</th>
                    <th>Tỉnh</th>
                    <th>Lượt thi</th>
                    <th>Đậu/Tổng</th>
                    <th>Tỉ lệ đậu</th>
                    <th>Điểm TB</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($exam_stats)): ?>
                    <?php foreach ($exam_stats as $stat): ?>
                        <?php 
                        $pass_rate = $stat->total_attempts > 0 ? ($stat->passed_count / $stat->total_attempts * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($stat->title); ?></strong></td>
                            <td><?php echo esc_html($stat->subject); ?></td>
                            <td><?php echo esc_html($stat->category); ?></td>
                            <td><?php echo number_format($stat->total_attempts); ?></td>
                            <td><?php echo number_format($stat->passed_count); ?>/<?php echo number_format($stat->total_attempts); ?></td>
                            <td>
                                <span class="pass-rate <?php echo $pass_rate >= 70 ? 'high' : ($pass_rate >= 50 ? 'medium' : 'low'); ?>">
                                    <?php echo round($pass_rate, 1); ?>%
                                </span>
                            </td>
                            <td>
                                <div class="mini-progress">
                                    <div class="mini-progress-bar" style="width: <?php echo round($stat->avg_percentage); ?>%"></div>
                                    <span><?php echo round($stat->avg_percentage, 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">Không có dữ liệu</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Thống kê theo IP -->
    <div class="stats-section">
        <h2>👥 Top 20 IP Hoạt động</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Tổng lượt thi</th>
                    <th>Số đề thi</th>
                    <th>Điểm TB</th>
                    <th>Lần thi cuối</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($ip_stats)): ?>
                    <?php foreach ($ip_stats as $stat): ?>
                        <tr>
                            <td><code><?php echo esc_html($stat->user_ip); ?></code></td>
                            <td><strong><?php echo number_format($stat->total_attempts); ?></strong></td>
                            <td><?php echo number_format($stat->unique_exams); ?></td>
                            <td>
                                <div class="mini-progress">
                                    <div class="mini-progress-bar" style="width: <?php echo round($stat->avg_percentage); ?>%"></div>
                                    <span><?php echo round($stat->avg_percentage, 1); ?>%</span>
                                </div>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($stat->last_attempt)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">Không có dữ liệu</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- IP vi phạm giới hạn -->
    <?php if (!empty($limit_violations)): ?>
    <div class="stats-section violation-section">
        <h2>⚠️ IP Vượt Giới hạn</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>ID Đề thi</th>
                    <th>Số lượt thi</th>
                    <th>Lần cuối</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($limit_violations as $violation): ?>
                    <tr>
                        <td><code><?php echo esc_html($violation->user_ip); ?></code></td>
                        <td><?php echo $violation->exam_id; ?></td>
                        <td><span class="violation-badge"><?php echo $violation->attempt_count; ?></span></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($violation->last_attempt)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.exam-stats-filter {
    background: white;
    padding: 15px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.exam-stats-filter form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.exam-stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 10px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.stats-section {
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 20px 0;
}

.stats-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #2271b1;
}

.mini-progress {
    position: relative;
    height: 25px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.mini-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #5ba0d0);
    transition: width 0.3s;
}

.mini-progress span {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-weight: bold;
    font-size: 12px;
    color: #333;
}

.pass-rate {
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: bold;
    font-size: 13px;
}

.pass-rate.high {
    background: #d4edda;
    color: #155724;
}

.pass-rate.medium {
    background: #fff3cd;
    color: #856404;
}

.pass-rate.low {
    background: #f8d7da;
    color: #721c24;
}

.violation-section {
    border-color: #dc3232;
}

.violation-section h2 {
    color: #dc3232;
    border-bottom-color: #dc3232;
}

.violation-badge {
    background: #dc3232;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: bold;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>