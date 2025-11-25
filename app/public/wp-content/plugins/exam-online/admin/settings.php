<?php
if (!defined('ABSPATH')) exit;

// Xử lý cập nhật cài đặt
if (isset($_POST['save_settings'])) {
    check_admin_referer('exam_settings_save');
    
    update_option('exam_max_attempts_per_day', intval($_POST['max_attempts_per_day']));
    update_option('exam_enable_file_upload', isset($_POST['enable_file_upload']) ? 1 : 0);
    update_option('exam_max_file_size', intval($_POST['max_file_size']));
    update_option('exam_allowed_file_types', sanitize_text_field($_POST['allowed_file_types']));
    update_option('exam_show_correct_answers', isset($_POST['show_correct_answers']) ? 1 : 0);
    update_option('exam_require_confirmation', isset($_POST['require_confirmation']) ? 1 : 0);
    update_option('exam_auto_save_interval', intval($_POST['auto_save_interval']));
    
    echo '<div class="notice notice-success"><p>Cập nhật cài đặt thành công!</p></div>';
}

// Lấy giá trị hiện tại
$max_attempts = get_option('exam_max_attempts_per_day', 10);
$enable_file_upload = get_option('exam_enable_file_upload', 1);
$max_file_size = get_option('exam_max_file_size', 5);
$allowed_file_types = get_option('exam_allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx');
$show_correct_answers = get_option('exam_show_correct_answers', 1);
$require_confirmation = get_option('exam_require_confirmation', 1);
$auto_save_interval = get_option('exam_auto_save_interval', 30);

global $wpdb;
$total_exams = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_papers");
$total_questions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_questions");
$total_results = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_results");
?>

<div class="wrap">
    <h1>⚙️ Cài đặt Hệ thống</h1>

    <div class="settings-container">
        <form method="post" action="">
            <?php wp_nonce_field('exam_settings_save'); ?>
            
            <div class="settings-section">
                <h2>🔒 Giới hạn và Bảo mật</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="max_attempts_per_day">Số lần thi tối đa / 24h</label>
                        </th>
                        <td>
                            <input type="number" name="max_attempts_per_day" id="max_attempts_per_day" 
                                   value="<?php echo esc_attr($max_attempts); ?>" min="1" max="100" class="small-text">
                            <p class="description">Giới hạn số lần thi cho mỗi IP trong 24 giờ. Khuyến nghị: 10-20 lần.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="require_confirmation">Yêu cầu xác nhận nộp bài</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_confirmation" id="require_confirmation" 
                                       value="1" <?php checked($require_confirmation, 1); ?>>
                                Hiển thị hộp thoại xác nhận khi học viên nộp bài
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h2>📁 Upload File (Câu hỏi Tự luận)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_file_upload">Cho phép upload file</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_file_upload" id="enable_file_upload" 
                                       value="1" <?php checked($enable_file_upload, 1); ?>>
                                Cho phép học viên tải lên file đính kèm cho câu tự luận
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_file_size">Kích thước file tối đa (MB)</label>
                        </th>
                        <td>
                            <input type="number" name="max_file_size" id="max_file_size" 
                                   value="<?php echo esc_attr($max_file_size); ?>" min="1" max="50" class="small-text">
                            <p class="description">Kích thước tối đa cho mỗi file. Khuyến nghị: 5MB.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="allowed_file_types">Định dạng file cho phép</label>
                        </th>
                        <td>
                            <input type="text" name="allowed_file_types" id="allowed_file_types" 
                                   value="<?php echo esc_attr($allowed_file_types); ?>" class="regular-text">
                            <p class="description">Các định dạng file được phép, phân cách bằng dấu phẩy. VD: jpg,png,pdf,doc,docx</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h2>🎯 Trải nghiệm Thi</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="show_correct_answers">Hiển thị đáp án sau thi</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_correct_answers" id="show_correct_answers" 
                                       value="1" <?php checked($show_correct_answers, 1); ?>>
                                Hiển thị đáp án đúng và hướng dẫn giải sau khi nộp bài
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="auto_save_interval">Tự động lưu (giây)</label>
                        </th>
                        <td>
                            <input type="number" name="auto_save_interval" id="auto_save_interval" 
                                   value="<?php echo esc_attr($auto_save_interval); ?>" min="10" max="300" class="small-text">
                            <p class="description">Tự động lưu câu trả lời vào localStorage. Khuyến nghị: 30 giây.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <p class="submit">
                <button type="submit" name="save_settings" class="button button-primary button-large">
                    💾 Lưu Cài đặt
                </button>
            </p>
        </form>

        <!-- Thông tin hệ thống -->
        <div class="settings-section system-info">
            <h2>ℹ️ Thông tin Hệ thống</h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong>Phiên bản Plugin</strong></td>
                        <td>1.0.0</td>
                    </tr>
                    <tr>
                        <td><strong>WordPress Version</strong></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>PHP Version</strong></td>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <td><strong>MySQL Version</strong></td>
                        <td><?php echo $wpdb->db_version(); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Upload Max Filesize</strong></td>
                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Post Max Size</strong></td>
                        <td><?php echo ini_get('post_max_size'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Max Execution Time</strong></td>
                        <td><?php echo ini_get('max_execution_time'); ?>s</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Thống kê database -->
        <div class="settings-section database-info">
            <h2>💾 Database Statistics</h2>
            <div class="db-stats-grid">
                <div class="db-stat-item">
                    <div class="db-stat-icon">📝</div>
                    <div class="db-stat-content">
                        <div class="db-stat-number"><?php echo number_format($total_exams); ?></div>
                        <div class="db-stat-label">Đề thi</div>
                    </div>
                </div>
                <div class="db-stat-item">
                    <div class="db-stat-icon">❓</div>
                    <div class="db-stat-content">
                        <div class="db-stat-number"><?php echo number_format($total_questions); ?></div>
                        <div class="db-stat-label">Câu hỏi</div>
                    </div>
                </div>
                <div class="db-stat-item">
                    <div class="db-stat-icon">✅</div>
                    <div class="db-stat-content">
                        <div class="db-stat-number"><?php echo number_format($total_results); ?></div>
                        <div class="db-stat-label">Kết quả thi</div>
                    </div>
                </div>
            </div>
            
            <div class="database-actions">
                <h3>⚠️ Thao tác Database (Nguy hiểm)</h3>
                <p class="description">Các thao tác dưới đây sẽ xóa dữ liệu vĩnh viễn. Hãy cẩn thận!</p>
                
                <button type="button" class="button" onclick="if(confirm('Xóa TẤT CẢ kết quả thi? Không thể hoàn tác!')) clearResults()">
                    🗑️ Xóa tất cả kết quả thi
                </button>
                
                <button type="button" class="button" onclick="if(confirm('Xóa TẤT CẢ giới hạn IP? Không thể hoàn tác!')) clearLimits()">
                    🔓 Xóa tất cả giới hạn IP
                </button>
            </div>
        </div>

        <!-- Shortcodes hướng dẫn -->
        <div class="settings-section">
            <h2>📋 Hướng dẫn Shortcodes</h2>
            <div class="shortcode-guide">
                <div class="shortcode-item">
                    <code>[exam_list]</code>
                    <p>Hiển thị danh sách môn thi và đề thi</p>
                </div>
                <div class="shortcode-item">
                    <code>[exam_detail]</code>
                    <p>Hiển thị chi tiết đề thi và kết quả trước đó</p>
                </div>
                <div class="shortcode-item">
                    <code>[exam_test]</code>
                    <p>Giao diện thi online</p>
                </div>
            </div>
            
            <h3>Cách sử dụng:</h3>
            <ol>
                <li>Tạo 3 trang mới trong WordPress</li>
                <li>Thêm shortcode tương ứng vào từng trang</li>
                <li>Xuất bản và sử dụng</li>
            </ol>
        </div>
    </div>
</div>

<script>
function clearResults() {
    jQuery.post(ajaxurl, {
        action: 'clear_exam_results',
        nonce: '<?php echo wp_create_nonce('exam_clear_data'); ?>'
    }, function(response) {
        if (response.success) {
            alert('Đã xóa tất cả kết quả thi!');
            location.reload();
        } else {
            alert('Có lỗi xảy ra: ' + response.data);
        }
    });
}

function clearLimits() {
    jQuery.post(ajaxurl, {
        action: 'clear_ip_limits',
        nonce: '<?php echo wp_create_nonce('exam_clear_data'); ?>'
    }, function(response) {
        if (response.success) {
            alert('Đã xóa tất cả giới hạn IP!');
            location.reload();
        } else {
            alert('Có lỗi xảy ra: ' + response.data);
        }
    });
}
</script>

<style>
.settings-container {
    max-width: 1200px;
}

.settings-section {
    background: white;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.settings-section h2 {
    margin-top: 0;
    padding-bottom: 15px;
    border-bottom: 2px solid #2271b1;
    color: #2271b1;
}

.settings-section h3 {
    margin-top: 20px;
    color: #666;
}

.system-info table,
.database-info table {
    margin-top: 15px;
}

.system-info td {
    padding: 10px;
}

.db-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.db-stat-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 6px;
}

.db-stat-icon {
    font-size: 40px;
}

.db-stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #2271b1;
}

.db-stat-label {
    color: #666;
    font-size: 14px;
}

.database-actions {
    margin-top: 30px;
    padding: 20px;
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 6px;
}

.database-actions button {
    margin-right: 10px;
    margin-top: 10px;
}

.shortcode-guide {
    display: grid;
    gap: 15px;
    margin: 20px 0;
}

.shortcode-item {
    padding: 15px;
    background: #f9f9f9;
    border-left: 4px solid #2271b1;
    border-radius: 4px;
}

.shortcode-item code {
    display: block;
    font-size: 16px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 8px;
    padding: 5px 10px;
    background: white;
    border-radius: 3px;
}

.shortcode-item p {
    margin: 0;
    color: #666;
}

.submit {
    margin-top: 30px;
}
</style>