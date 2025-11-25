<?php
/**
 * AJAX Handlers cho plugin Thi Online
 * Include file này vào plugin chính
 */

if (!defined('ABSPATH')) exit;

// Load questions by filters (for paper form with multiple filters)
add_action('wp_ajax_load_questions_by_filters', 'exam_load_questions_by_filters');
function exam_load_questions_by_filters() {
    global $wpdb;
    
    $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    
    $where = "1=1";
    
    if (!empty($subject)) {
        $where .= $wpdb->prepare(" AND subject = %s", $subject);
    }
    
    if (!empty($type)) {
        $where .= $wpdb->prepare(" AND question_type = %s", $type);
    }
    
    if (!empty($search)) {
        $where .= $wpdb->prepare(" AND question_text LIKE %s", '%' . $wpdb->esc_like($search) . '%');
    }
    
    $questions = $wpdb->get_results("
        SELECT id, question_text, question_type, points 
        FROM {$wpdb->prefix}exam_questions 
        WHERE $where
        ORDER BY id DESC
    ");
    
    // Trim question text for display
    foreach ($questions as $q) {
        $q->question_text = wp_trim_words(strip_tags($q->question_text), 15);
    }
    
    wp_send_json_success($questions);
}

// Load questions by subject (for backward compatibility)
add_action('wp_ajax_load_questions_by_subject', 'exam_load_questions_by_subject');
function exam_load_questions_by_subject() {
    global $wpdb;
    
    $subject = sanitize_text_field($_POST['subject']);
    
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT id, question_text, question_type, points 
         FROM {$wpdb->prefix}exam_questions 
         WHERE subject = %s 
         ORDER BY id DESC",
        $subject
    ));
    
    // Trim question text for display
    foreach ($questions as $q) {
        $q->question_text = wp_trim_words(strip_tags($q->question_text), 15);
    }
    
    wp_send_json_success($questions);
}

// Clear all exam results
add_action('wp_ajax_clear_exam_results', 'exam_clear_all_results');
function exam_clear_all_results() {
    check_ajax_referer('exam_clear_data', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}exam_results");
    
    wp_send_json_success();
}

// Clear IP limits
add_action('wp_ajax_clear_ip_limits', 'exam_clear_all_limits');
function exam_clear_all_limits() {
    check_ajax_referer('exam_clear_data', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}exam_ip_limits");
    
    wp_send_json_success();
}

// Get exam details for frontend
add_action('wp_ajax_get_exam_details', 'exam_get_details');
add_action('wp_ajax_nopriv_get_exam_details', 'exam_get_details');
function exam_get_details() {
    check_ajax_referer('exam_frontend_nonce', 'nonce');
    
    global $wpdb;
    $exam_id = intval($_POST['exam_id']);
    
    $exam = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exam_papers WHERE id = %d",
        $exam_id
    ));
    
    if (!$exam) {
        wp_send_json_error(['message' => 'Không tìm thấy đề thi']);
    }
    
    $question_ids = json_decode($exam->question_ids, true);
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exam_questions WHERE id IN (" . implode(',', array_fill(0, count($question_ids), '%d')) . ") ORDER BY FIELD(id, " . implode(',', $question_ids) . ")",
        ...$question_ids
    ));
    
    // Format questions
    foreach ($questions as $q) {
        if ($q->options) {
            $q->options = json_decode($q->options, true);
        }
    }
    
    wp_send_json_success([
        'exam' => $exam,
        'questions' => $questions
    ]);
}

// Get previous results for a user IP
add_action('wp_ajax_get_previous_results', 'exam_get_previous_results');
add_action('wp_ajax_nopriv_get_previous_results', 'exam_get_previous_results');
function exam_get_previous_results() {
    check_ajax_referer('exam_frontend_nonce', 'nonce');
    
    global $wpdb;
    $exam_id = intval($_POST['exam_id']);
    $user_ip = exam_get_user_ip();
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exam_results 
         WHERE exam_id = %d AND user_ip = %s 
         ORDER BY submit_time DESC",
        $exam_id, $user_ip
    ));
    
    // Get exam info
    $exam = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exam_papers WHERE id = %d",
        $exam_id
    ));
    
    // Get questions for showing correct answers
    if (!empty($results)) {
        $question_ids = json_decode($exam->question_ids, true);
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}exam_questions WHERE id IN (" . implode(',', array_fill(0, count($question_ids), '%d')) . ")",
            ...$question_ids
        ));
        
        // Create question map
        $question_map = [];
        foreach ($questions as $q) {
            $question_map[$q->id] = $q;
        }
        
        // Add question details to results
        foreach ($results as $result) {
            $result->answers_decoded = json_decode($result->answers, true);
            $result->questions = $question_map;
        }
    }
    
    wp_send_json_success([
        'results' => $results,
        'exam' => $exam
    ]);
}

// Upload file for essay question
add_action('wp_ajax_upload_essay_file', 'exam_upload_essay_file');
add_action('wp_ajax_nopriv_upload_essay_file', 'exam_upload_essay_file');
function exam_upload_essay_file() {
    check_ajax_referer('exam_frontend_nonce', 'nonce');
    
    if (!get_option('exam_enable_file_upload', 1)) {
        wp_send_json_error(['message' => 'Upload file đã bị tắt']);
    }
    
    if (empty($_FILES['file'])) {
        wp_send_json_error(['message' => 'Không có file nào được upload']);
    }
    
    $file = $_FILES['file'];
    $max_size = get_option('exam_max_file_size', 5) * 1024 * 1024; // Convert MB to bytes
    $allowed_types = explode(',', get_option('exam_allowed_file_types', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx'));
    
    // Check file size
    if ($file['size'] > $max_size) {
        wp_send_json_error(['message' => 'File quá lớn. Tối đa ' . get_option('exam_max_file_size', 5) . 'MB']);
    }
    
    // Check file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        wp_send_json_error(['message' => 'Định dạng file không được phép']);
    }
    
    // Use WordPress upload handler
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    
    $upload = wp_handle_upload($file, ['test_form' => false]);
    
    if (isset($upload['error'])) {
        wp_send_json_error(['message' => $upload['error']]);
    }
    
    wp_send_json_success([
        'url' => $upload['url'],
        'file' => $upload['file'],
        'type' => $upload['type']
    ]);
}

/**
 * Helper function to get real user IP address
 * Fixed for localhost environment
 */
function exam_get_user_ip() {
    // Check for various header values that might contain the IP
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    // Fallback to REMOTE_ADDR
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    
    // Convert IPv6 localhost to IPv4
    if ($ip === '::1') {
        $ip = '127.0.0.1';
    }
    
    // For localhost development, use a unique identifier based on session
    if ($ip === '127.0.0.1' || $ip === 'localhost') {
        // Try to use session ID if available
        if (session_id() === '') {
            session_start();
        }
        $session_id = session_id();
        
        // Create a unique IP-like identifier for localhost users
        // This ensures different browsers/sessions are treated as different users
        if (!empty($session_id)) {
            // Convert session ID to a pseudo-IP format
            $hash = substr(md5($session_id), 0, 8);
            $ip = '127.0.' . hexdec(substr($hash, 0, 2)) . '.' . hexdec(substr($hash, 2, 2));
        } else {
            // If no session, use browser fingerprint
            $fingerprint = md5(
                $_SERVER['HTTP_USER_AGENT'] . 
                (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '')
            );
            $hash = substr($fingerprint, 0, 8);
            $ip = '127.1.' . hexdec(substr($hash, 0, 2)) . '.' . hexdec(substr($hash, 2, 2));
        }
    }
    
    return $ip;
}