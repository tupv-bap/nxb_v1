<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Xử lý thêm/sửa/xóa
if (isset($_POST['action'])) {
    check_admin_referer('exam_questions_action');
    
    if ($_POST['action'] == 'save') {
        $data = [
            'subject' => sanitize_text_field($_POST['subject']),
            'question_type' => sanitize_text_field($_POST['question_type']),
            'question_text' => wp_kses_post($_POST['question_text']),
            'points' => intval($_POST['points'])
        ];
        
        // Xử lý options và correct_answer theo loại câu hỏi
        if ($_POST['question_type'] == 'true_false') {
            $data['correct_answer'] = sanitize_text_field($_POST['tf_answer']);
        } elseif ($_POST['question_type'] == 'single_choice') {
            $options = array_map('sanitize_text_field', $_POST['options']);
            $data['options'] = json_encode($options);
            $data['correct_answer'] = sanitize_text_field($_POST['correct_single']);
        } elseif ($_POST['question_type'] == 'multiple_choice') {
            $options = array_map('sanitize_text_field', $_POST['mc_options']);
            $data['options'] = json_encode($options);
            $data['correct_answer'] = json_encode($_POST['correct_multiple']);
        } elseif ($_POST['question_type'] == 'essay') {
            $data['essay_guide'] = wp_kses_post($_POST['essay_guide']);
        }
        
        if (isset($_POST['question_id']) && $_POST['question_id']) {
            // Cập nhật
            $wpdb->update(
                "{$wpdb->prefix}exam_questions",
                $data,
                ['id' => intval($_POST['question_id'])]
            );
            echo '<div class="notice notice-success"><p>Cập nhật câu hỏi thành công!</p></div>';
        } else {
            // Thêm mới
            $wpdb->insert("{$wpdb->prefix}exam_questions", $data);
            echo '<div class="notice notice-success"><p>Thêm câu hỏi thành công!</p></div>';
        }
    } elseif ($_POST['action'] == 'delete') {
        $wpdb->delete(
            "{$wpdb->prefix}exam_questions",
            ['id' => intval($_POST['question_id'])]
        );
        echo '<div class="notice notice-success"><p>Xóa câu hỏi thành công!</p></div>';
    }
}

// Lấy danh sách câu hỏi
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$filter_subject = isset($_GET['filter_subject']) ? sanitize_text_field($_GET['filter_subject']) : '';
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';

$where = "1=1";
if ($search) {
    $where .= $wpdb->prepare(" AND question_text LIKE %s", '%' . $wpdb->esc_like($search) . '%');
}
if ($filter_subject) {
    $where .= $wpdb->prepare(" AND subject = %s", $filter_subject);
}
if ($filter_type) {
    $where .= $wpdb->prepare(" AND question_type = %s", $filter_type);
}

$total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_questions WHERE $where");
$questions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}exam_questions WHERE $where ORDER BY id DESC LIMIT $offset, $per_page");

$subjects = $wpdb->get_col("SELECT DISTINCT subject FROM {$wpdb->prefix}exam_questions ORDER BY subject");

// Form thêm/sửa
$editing = isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']);
$edit_question = null;
if ($editing) {
    $edit_question = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exam_questions WHERE id = %d",
        intval($_GET['id'])
    ));
}

$show_form = isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit']);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Quản lý Câu hỏi</h1>
    
    <?php if (!$show_form): ?>
        <a href="<?php echo admin_url('admin.php?page=exam-questions&action=new'); ?>" class="page-title-action">Thêm câu hỏi mới</a>
    <?php else: ?>
        <a href="<?php echo admin_url('admin.php?page=exam-questions'); ?>" class="page-title-action">← Quay lại danh sách</a>
    <?php endif; ?>
    
    <hr class="wp-header-end">

    <?php if ($show_form): ?>
        <!-- Form thêm/sửa câu hỏi -->
        <div class="exam-form-container">
            <h2><?php echo $editing ? 'Chỉnh sửa câu hỏi' : 'Thêm câu hỏi mới'; ?></h2>
            
            <form method="post" id="question-form">
                <?php wp_nonce_field('exam_questions_action'); ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editing): ?>
                    <input type="hidden" name="question_id" value="<?php echo $edit_question->id; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="subject">Môn học *</label></th>
                        <td>
                            <input type="text" name="subject" id="subject" class="regular-text" 
                                   value="<?php echo $editing ? esc_attr($edit_question->subject) : ''; ?>" 
                                   list="subjects-list" required>
                            <datalist id="subjects-list">
                                <?php foreach ($subjects as $subj): ?>
                                    <option value="<?php echo esc_attr($subj); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="question_type">Loại câu hỏi *</label></th>
                        <td>
                            <select name="question_type" id="question_type" required>
                                <option value="">-- Chọn loại --</option>
                                <option value="true_false" <?php echo ($editing && $edit_question->question_type == 'true_false') ? 'selected' : ''; ?>>Đúng/Sai</option>
                                <option value="single_choice" <?php echo ($editing && $edit_question->question_type == 'single_choice') ? 'selected' : ''; ?>>Trắc nghiệm (1 đáp án)</option>
                                <option value="multiple_choice" <?php echo ($editing && $edit_question->question_type == 'multiple_choice') ? 'selected' : ''; ?>>Trắc nghiệm (nhiều đáp án)</option>
                                <option value="essay" <?php echo ($editing && $edit_question->question_type == 'essay') ? 'selected' : ''; ?>>Tự luận</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="question_text">Nội dung câu hỏi *</label></th>
                        <td>
                            <?php 
                            wp_editor(
                                $editing ? $edit_question->question_text : '', 
                                'question_text',
                                ['textarea_rows' => 8, 'media_buttons' => true]
                            ); 
                            ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="points">Điểm *</label></th>
                        <td>
                            <input type="number" name="points" id="points" min="1" max="100" 
                                   value="<?php echo $editing ? $edit_question->points : '1'; ?>" required>
                        </td>
                    </tr>
                </table>
                
                <!-- Options cho từng loại câu hỏi -->
                <div id="tf-options" class="question-type-options" style="display:none;">
                    <h3>Đáp án Đúng/Sai</h3>
                    <label>
                        <input type="radio" name="tf_answer" value="true" <?php echo ($editing && $edit_question->correct_answer == 'true') ? 'checked' : ''; ?>> Đúng
                    </label>
                    <label style="margin-left: 20px;">
                        <input type="radio" name="tf_answer" value="false" <?php echo ($editing && $edit_question->correct_answer == 'false') ? 'checked' : ''; ?>> Sai
                    </label>
                </div>
                
                <div id="single-options" class="question-type-options" style="display:none;">
                    <h3>Các đáp án (Chọn 1 đáp án đúng)</h3>
                    <div id="single-options-list">
                        <?php
                        $single_options = $editing && $edit_question->options ? json_decode($edit_question->options, true) : ['', '', '', ''];
                        $correct_single = $editing ? $edit_question->correct_answer : '';
                        foreach ($single_options as $idx => $opt):
                        ?>
                            <div class="option-row">
                                <input type="radio" name="correct_single" value="<?php echo $idx; ?>" <?php echo ($correct_single == $idx) ? 'checked' : ''; ?>>
                                <input type="text" name="options[]" value="<?php echo esc_attr($opt); ?>" class="regular-text" placeholder="Đáp án <?php echo chr(65 + $idx); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" onclick="addSingleOption()">+ Thêm đáp án</button>
                </div>
                
                <div id="multiple-options" class="question-type-options" style="display:none;">
                    <h3>Các đáp án (Chọn nhiều đáp án đúng)</h3>
                    <div id="multiple-options-list">
                        <?php
                        $mc_options = $editing && $edit_question->options ? json_decode($edit_question->options, true) : ['', '', '', ''];
                        $correct_multiple = $editing && $edit_question->correct_answer ? json_decode($edit_question->correct_answer, true) : [];
                        foreach ($mc_options as $idx => $opt):
                        ?>
                            <div class="option-row">
                                <input type="checkbox" name="correct_multiple[]" value="<?php echo $idx; ?>" <?php echo in_array($idx, $correct_multiple) ? 'checked' : ''; ?>>
                                <input type="text" name="mc_options[]" value="<?php echo esc_attr($opt); ?>" class="regular-text" placeholder="Đáp án <?php echo chr(65 + $idx); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" onclick="addMultipleOption()">+ Thêm đáp án</button>
                </div>
                
                <div id="essay-options" class="question-type-options" style="display:none;">
                    <h3>Hướng dẫn đáp án</h3>
                    <?php 
                    wp_editor(
                        $editing ? $edit_question->essay_guide : '', 
                        'essay_guide',
                        ['textarea_rows' => 6, 'media_buttons' => false]
                    ); 
                    ?>
                    <p class="description">Đáp án tự luận luôn được tính điểm tối đa.</p>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $editing ? 'Cập nhật câu hỏi' : 'Thêm câu hỏi'; ?>
                    </button>
                </p>
            </form>
        </div>
        
    <?php else: ?>
        <!-- Danh sách câu hỏi -->
        <div class="exam-filters">
            <form method="get">
                <input type="hidden" name="page" value="exam-questions">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm kiếm câu hỏi...">
                
                <select name="filter_subject">
                    <option value="">Tất cả môn học</option>
                    <?php foreach ($subjects as $subj): ?>
                        <option value="<?php echo esc_attr($subj); ?>" <?php selected($filter_subject, $subj); ?>>
                            <?php echo esc_html($subj); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="filter_type">
                    <option value="">Tất cả loại</option>
                    <option value="true_false" <?php selected($filter_type, 'true_false'); ?>>Đúng/Sai</option>
                    <option value="single_choice" <?php selected($filter_type, 'single_choice'); ?>>Trắc nghiệm (1 đáp án)</option>
                    <option value="multiple_choice" <?php selected($filter_type, 'multiple_choice'); ?>>Trắc nghiệm (nhiều đáp án)</option>
                    <option value="essay" <?php selected($filter_type, 'essay'); ?>>Tự luận</option>
                </select>
                
                <button type="submit" class="button">Lọc</button>
                <a href="<?php echo admin_url('admin.php?page=exam-questions'); ?>" class="button">Xóa lọc</a>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Câu hỏi</th>
                    <th style="width:120px;">Môn học</th>
                    <th style="width:150px;">Loại</th>
                    <th style="width:80px;">Điểm</th>
                    <th style="width:150px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($questions): ?>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td><?php echo $q->id; ?></td>
                            <td>
                                <strong><?php echo wp_trim_words(strip_tags($q->question_text), 15); ?></strong>
                            </td>
                            <td><?php echo esc_html($q->subject); ?></td>
                            <td>
                                <?php
                                $types = [
                                    'true_false' => 'Đúng/Sai',
                                    'single_choice' => 'Trắc nghiệm (1)',
                                    'multiple_choice' => 'Trắc nghiệm (nhiều)',
                                    'essay' => 'Tự luận'
                                ];
                                echo $types[$q->question_type];
                                ?>
                            </td>
                            <td><span class="badge"><?php echo $q->points; ?></span></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=exam-questions&action=edit&id=' . $q->id); ?>" class="button button-small">Sửa</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa câu hỏi này?');">
                                    <?php wp_nonce_field('exam_questions_action'); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="question_id" value="<?php echo $q->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Không có câu hỏi nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php
        // Pagination
        if ($total > $per_page) {
            $total_pages = ceil($total / $per_page);
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $page
            ]);
            echo '</div></div>';
        }
        ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle options based on question type
    $('#question_type').on('change', function() {
        $('.question-type-options').hide();
        var type = $(this).val();
        if (type === 'true_false') {
            $('#tf-options').show();
        } else if (type === 'single_choice') {
            $('#single-options').show();
        } else if (type === 'multiple_choice') {
            $('#multiple-options').show();
        } else if (type === 'essay') {
            $('#essay-options').show();
        }
    }).trigger('change');
});

function addSingleOption() {
    var container = document.getElementById('single-options-list');
    var count = container.children.length;
    var div = document.createElement('div');
    div.className = 'option-row';
    div.innerHTML = '<input type="radio" name="correct_single" value="' + count + '">' +
                    '<input type="text" name="options[]" class="regular-text" placeholder="Đáp án ' + String.fromCharCode(65 + count) + '">';
    container.appendChild(div);
}

function addMultipleOption() {
    var container = document.getElementById('multiple-options-list');
    var count = container.children.length;
    var div = document.createElement('div');
    div.className = 'option-row';
    div.innerHTML = '<input type="checkbox" name="correct_multiple[]" value="' + count + '">' +
                    '<input type="text" name="mc_options[]" class="regular-text" placeholder="Đáp án ' + String.fromCharCode(65 + count) + '">';
    container.appendChild(div);
}
</script>

<style>
.exam-form-container {
    background: white;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.exam-filters {
    margin: 20px 0;
    padding: 15px;
    background: white;
    border: 1px solid #ccc;
}

.exam-filters form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.exam-filters input[type="search"] {
    flex: 1;
    max-width: 300px;
}

.question-type-options {
    margin: 20px 0;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.option-row {
    margin: 10px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.option-row input[type="text"] {
    flex: 1;
}

.badge {
    background: #2271b1;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}
</style>