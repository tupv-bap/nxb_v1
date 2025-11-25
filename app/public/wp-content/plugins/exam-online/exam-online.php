<?php
/**
 * Plugin Name: Thi Online System
 * Plugin URI: https://example.com
 * Description: Hệ thống thi online hoàn chỉnh với quản lý đề thi, câu hỏi và thống kê
 * Version: 1.0.1
 * Author: Your Name
 * Text Domain: exam-online
 */

if (!defined('ABSPATH')) exit;

define('EXAM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EXAM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include AJAX handlers
require_once EXAM_PLUGIN_DIR . 'includes/ajax-handlers.php';

class ExamOnlineSystem {
    
    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('init', [$this, 'register_post_types']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_ajax_submit_exam', [$this, 'ajax_submit_exam']);
        add_action('wp_ajax_nopriv_submit_exam', [$this, 'ajax_submit_exam']);
        add_action('wp_ajax_save_temp_answer', [$this, 'ajax_save_temp_answer']);
        add_action('wp_ajax_nopriv_save_temp_answer', [$this, 'ajax_save_temp_answer']);
        add_action('wp_ajax_check_exam_limit', [$this, 'ajax_check_exam_limit']);
        add_action('wp_ajax_nopriv_check_exam_limit', [$this, 'ajax_check_exam_limit']);
        
        add_shortcode('exam_list', [$this, 'exam_list_shortcode']);
        add_shortcode('exam_detail', [$this, 'exam_detail_shortcode']);
        add_shortcode('exam_test', [$this, 'exam_test_shortcode']);
    }
    
    public function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        // Bảng câu hỏi (đã bỏ trường category)
        $sql_questions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}exam_questions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subject varchar(100) NOT NULL,
            question_type varchar(20) NOT NULL,
            question_text text NOT NULL,
            options text,
            correct_answer text,
            essay_guide text,
            points int(11) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subject (subject),
            KEY question_type (question_type)
        ) $charset;";
        
        // Bảng đề thi
        $sql_exams = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}exam_papers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            subject varchar(100) NOT NULL,
            category varchar(100) NOT NULL,
            description text,
            question_ids text NOT NULL,
            duration int(11) DEFAULT 60,
            passing_score int(11) DEFAULT 50,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY subject (subject),
            KEY category (category)
        ) $charset;";
        
        // Bảng kết quả thi
        $sql_results = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}exam_results (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) NOT NULL,
            user_ip varchar(45) NOT NULL,
            answers text NOT NULL,
            score decimal(5,2) NOT NULL,
            total_points int(11) NOT NULL,
            start_time datetime NOT NULL,
            submit_time datetime NOT NULL,
            PRIMARY KEY (id),
            KEY exam_id (exam_id),
            KEY user_ip (user_ip),
            KEY submit_time (submit_time)
        ) $charset;";
        
        // Bảng giới hạn IP
        $sql_limits = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}exam_ip_limits (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_ip varchar(45) NOT NULL,
            exam_id bigint(20) NOT NULL,
            attempt_count int(11) DEFAULT 1,
            last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_exam (user_ip, exam_id),
            KEY last_attempt (last_attempt)
        ) $charset;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_questions);
        dbDelta($sql_exams);
        dbDelta($sql_results);
        dbDelta($sql_limits);
        
        // Check if category column exists in exam_questions and remove it
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}exam_questions LIKE 'category'");
        if (!empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}exam_questions DROP COLUMN category");
        }
        
        // Thêm options
        add_option('exam_max_attempts_per_day', 10);
        add_option('exam_enable_file_upload', 1);
        add_option('exam_max_file_size', 5);
        add_option('exam_allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx');
    }
    
    public function deactivate() {
        // Cleanup nếu cần
    }
    
    public function register_post_types() {
        // Không cần post type tùy chỉnh, dùng database trực tiếp
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Quản lý Thi Online',
            'Thi Online',
            'manage_options',
            'exam-online',
            [$this, 'admin_dashboard_page'],
            'dashicons-welcome-learn-more',
            30
        );
        
        add_submenu_page('exam-online', 'Quản lý Đề thi', 'Đề thi', 'manage_options', 'exam-papers', [$this, 'admin_papers_page']);
        add_submenu_page('exam-online', 'Quản lý Câu hỏi', 'Câu hỏi', 'manage_options', 'exam-questions', [$this, 'admin_questions_page']);
        add_submenu_page('exam-online', 'Thống kê', 'Thống kê', 'manage_options', 'exam-statistics', [$this, 'admin_statistics_page']);
        add_submenu_page('exam-online', 'Cài đặt', 'Cài đặt', 'manage_options', 'exam-settings', [$this, 'admin_settings_page']);
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'exam-') !== false) {
            wp_enqueue_style('exam-admin-css', EXAM_PLUGIN_URL . 'assets/admin.css', [], '1.0.1');
            wp_enqueue_script('exam-admin-js', EXAM_PLUGIN_URL . 'assets/admin.js', ['jquery'], '1.0.1', true);
            wp_localize_script('exam-admin-js', 'examAdmin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('exam_admin_nonce')
            ]);
        }
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style('exam-frontend-css', EXAM_PLUGIN_URL . 'assets/frontend.css', [], '1.0.1');
        wp_enqueue_script('exam-frontend-js', EXAM_PLUGIN_URL . 'assets/frontend.js', ['jquery'], '1.0.1', true);
        wp_localize_script('exam-frontend-js', 'examData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('exam_frontend_nonce'),
            'autoSaveInterval' => get_option('exam_auto_save_interval', 30)
        ]);
    }
    
    // Admin Pages
    public function admin_dashboard_page() {
        include EXAM_PLUGIN_DIR . 'admin/dashboard.php';
    }
    
    public function admin_papers_page() {
        include EXAM_PLUGIN_DIR . 'admin/papers.php';
    }
    
    public function admin_questions_page() {
        include EXAM_PLUGIN_DIR . 'admin/questions.php';
    }
    
    public function admin_statistics_page() {
        include EXAM_PLUGIN_DIR . 'admin/statistics.php';
    }
    
    public function admin_settings_page() {
        include EXAM_PLUGIN_DIR . 'admin/settings.php';
    }
    
    // Shortcodes
    public function exam_list_shortcode($atts) {
        ob_start();
        include EXAM_PLUGIN_DIR . 'templates/exam-list.php';
        return ob_get_clean();
    }
    
    public function exam_detail_shortcode($atts) {
        ob_start();
        include EXAM_PLUGIN_DIR . 'templates/exam-detail.php';
        return ob_get_clean();
    }
    
    public function exam_test_shortcode($atts) {
        ob_start();
        include EXAM_PLUGIN_DIR . 'templates/exam-test.php';
        return ob_get_clean();
    }
    
    // AJAX Handlers
    public function ajax_submit_exam() {
        check_ajax_referer('exam_frontend_nonce', 'nonce');
        
        global $wpdb;
        $exam_id = intval($_POST['exam_id']);
        $answers = json_decode(stripslashes($_POST['answers']), true);
        $user_ip = exam_get_user_ip(); // Use the helper function
        
        // Kiểm tra giới hạn
        if (!$this->check_attempt_limit($user_ip, $exam_id)) {
            wp_send_json_error(['message' => 'Bạn đã vượt quá số lần thi cho phép trong 24h']);
        }
        
        // Lấy đề thi
        $exam = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}exam_papers WHERE id = %d",
            $exam_id
        ));
        
        if (!$exam) {
            wp_send_json_error(['message' => 'Không tìm thấy đề thi']);
        }
        
        $question_ids = json_decode($exam->question_ids, true);
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}exam_questions WHERE id IN (" . implode(',', array_fill(0, count($question_ids), '%d')) . ")",
            ...$question_ids
        ));
        
        // Tính điểm
        $score = 0;
        $total_points = 0;
        $detailed_results = [];
        
        foreach ($questions as $q) {
            $total_points += $q->points;
            $user_answer = isset($answers[$q->id]) ? $answers[$q->id] : null;
            $is_correct = false;
            
            if ($q->question_type === 'essay') {
                // Tự luận luôn cho điểm tối đa
                $score += $q->points;
                $is_correct = true;
            } else {
                $correct = json_decode($q->correct_answer, true);
                if ($q->question_type === 'true_false') {
                    $is_correct = ($user_answer === $correct);
                } elseif ($q->question_type === 'single_choice') {
                    $is_correct = ($user_answer === $correct);
                } elseif ($q->question_type === 'multiple_choice') {
                    if (is_array($user_answer) && is_array($correct)) {
                        sort($user_answer);
                        sort($correct);
                        $is_correct = ($user_answer === $correct);
                    }
                }
                
                if ($is_correct) {
                    $score += $q->points;
                }
            }
            
            $detailed_results[$q->id] = [
                'user_answer' => $user_answer,
                'is_correct' => $is_correct,
                'points' => $is_correct ? $q->points : 0
            ];
        }
        
        // Lưu kết quả
        $wpdb->insert(
            "{$wpdb->prefix}exam_results",
            [
                'exam_id' => $exam_id,
                'user_ip' => $user_ip,
                'answers' => json_encode($detailed_results),
                'score' => $score,
                'total_points' => $total_points,
                'start_time' => $_POST['start_time'],
                'submit_time' => current_time('mysql')
            ]
        );
        
        // Cập nhật giới hạn
        $this->update_attempt_limit($user_ip, $exam_id);
        
        wp_send_json_success([
            'score' => $score,
            'total_points' => $total_points,
            'percentage' => round(($score / $total_points) * 100, 2),
            'detailed_results' => $detailed_results
        ]);
    }
    
    public function ajax_save_temp_answer() {
        check_ajax_referer('exam_frontend_nonce', 'nonce');
        wp_send_json_success(['message' => 'Lưu tạm thành công']);
    }
    
    public function ajax_check_exam_limit() {
        check_ajax_referer('exam_frontend_nonce', 'nonce');
        
        $exam_id = intval($_POST['exam_id']);
        $user_ip = exam_get_user_ip(); // Use the helper function
        
        $can_attempt = $this->check_attempt_limit($user_ip, $exam_id);
        $remaining = $this->get_remaining_attempts($user_ip, $exam_id);
        
        wp_send_json_success([
            'can_attempt' => $can_attempt,
            'remaining' => $remaining
        ]);
    }
    
    // Helper functions
    private function check_attempt_limit($ip, $exam_id) {
        global $wpdb;
        $max_attempts = get_option('exam_max_attempts_per_day', 10);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}exam_results 
            WHERE user_ip = %s AND exam_id = %d 
            AND submit_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $ip, $exam_id
        ));
        
        return $count < $max_attempts;
    }
    
    private function get_remaining_attempts($ip, $exam_id) {
        global $wpdb;
        $max_attempts = get_option('exam_max_attempts_per_day', 10);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}exam_results 
            WHERE user_ip = %s AND exam_id = %d 
            AND submit_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $ip, $exam_id
        ));
        
        return max(0, $max_attempts - $count);
    }
    
    private function update_attempt_limit($ip, $exam_id) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}exam_ip_limits (user_ip, exam_id, attempt_count, last_attempt)
            VALUES (%s, %d, 1, NOW())
            ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, last_attempt = NOW()",
            $ip, $exam_id
        ));
    }
}

new ExamOnlineSystem();