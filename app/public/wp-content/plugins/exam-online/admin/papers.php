<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

// Xử lý thêm/sửa/xóa đề thi
if (isset($_POST['action'])) {
    check_admin_referer('exam_papers_action');
    
    if ($_POST['action'] == 'save') {
        $data = [
            'title' => sanitize_text_field($_POST['title']),
            'subject' => sanitize_text_field($_POST['subject']),
            'category' => sanitize_text_field($_POST['category']),
            'description' => wp_kses_post($_POST['description']),
            'question_ids' => json_encode(array_map('intval', $_POST['question_ids'])),
            'duration' => intval($_POST['duration']),
            'passing_score' => intval($_POST['passing_score'])
        ];
        
        if (isset($_POST['paper_id']) && $_POST['paper_id']) {
            $wpdb->update(
                "{$wpdb->prefix}exam_papers",
                $data,
                ['id' => intval($_POST['paper_id'])]
            );
            echo '<div class="notice notice-success"><p>Cập nhật đề thi thành công!</p></div>';
        } else {
            $wpdb->insert("{$wpdb->prefix}exam_papers", $data);
            echo '<div class="notice notice-success"><p>Thêm đề thi thành công!</p></div>';
        }
    } elseif ($_POST['action'] == 'delete') {
        $wpdb->delete(
            "{$wpdb->prefix}exam_papers",
            ['id' => intval($_POST['paper_id'])]
        );
        echo '<div class="notice notice-success"><p>Xóa đề thi thành công!</p></div>';
    }
}

// Lấy danh sách đề thi
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$filter_subject = isset($_GET['filter_subject']) ? sanitize_text_field($_GET['filter_subject']) : '';
$filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';

$where = "1=1";
if ($search) {
    $where .= $wpdb->prepare(" AND title LIKE %s", '%' . $wpdb->esc_like($search) . '%');
}
if ($filter_subject) {
    $where .= $wpdb->prepare(" AND subject = %s", $filter_subject);
}
if ($filter_category) {
    $where .= $wpdb->prepare(" AND category = %s", $filter_category);
}

$total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}exam_papers WHERE $where");
$papers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}exam_papers WHERE $where ORDER BY id DESC LIMIT $offset, $per_page");

$subjects = $wpdb->get_col("SELECT DISTINCT subject FROM {$wpdb->prefix}exam_papers ORDER BY subject");
$categories = $wpdb->get_col("SELECT DISTINCT category FROM {$wpdb->prefix}exam_papers ORDER BY category");

// Form thêm/sửa
$editing = isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id']);
$edit_paper = null;
$edit_questions = [];
if ($editing) {
    $edit_paper = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}exam_papers WHERE id = %d",
        intval($_GET['id'])
    ));
    if ($edit_paper) {
        $question_ids = json_decode($edit_paper->question_ids, true);
        if (!empty($question_ids)) {
            $edit_questions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}exam_questions WHERE id IN (" . implode(',', array_fill(0, count($question_ids), '%d')) . ")",
                ...$question_ids
            ));
        }
    }
}

$show_form = isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit']);

// Lấy danh sách câu hỏi cho selector
$all_subjects = $wpdb->get_col("SELECT DISTINCT subject FROM {$wpdb->prefix}exam_questions ORDER BY subject");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Quản lý Đề thi</h1>
    
    <?php if (!$show_form): ?>
        <a href="<?php echo admin_url('admin.php?page=exam-papers&action=new'); ?>" class="page-title-action">Tạo đề thi mới</a>
    <?php else: ?>
        <a href="<?php echo admin_url('admin.php?page=exam-papers'); ?>" class="page-title-action">← Quay lại danh sách</a>
    <?php endif; ?>
    
    <hr class="wp-header-end">

    <?php if ($show_form): ?>
        <!-- Form thêm/sửa đề thi -->
        <div class="exam-form-container">
            <h2><?php echo $editing ? 'Chỉnh sửa đề thi' : 'Tạo đề thi mới'; ?></h2>
            
            <form method="post" id="paper-form">
                <?php wp_nonce_field('exam_papers_action'); ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editing): ?>
                    <input type="hidden" name="paper_id" value="<?php echo $edit_paper->id; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="title">Tên đề thi *</label></th>
                        <td>
                            <input type="text" name="title" id="title" class="regular-text" 
                                   value="<?php echo $editing ? esc_attr($edit_paper->title) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="subject">Môn học *</label></th>
                        <td>
                            <input type="text" name="subject" id="subject" class="regular-text" 
                                   value="<?php echo $editing ? esc_attr($edit_paper->subject) : ''; ?>" 
                                   list="subjects-list" required>
                            <datalist id="subjects-list">
                                <?php foreach ($all_subjects as $subj): ?>
                                    <option value="<?php echo esc_attr($subj); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="category">Tỉnh/Category *</label></th>
                        <td>
                            <input type="text" name="category" id="category" class="regular-text" 
                                   value="<?php echo $editing ? esc_attr($edit_paper->category) : ''; ?>" 
                                   list="categories-list" required>
                            <datalist id="categories-list">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="description">Mô tả</label></th>
                        <td>
                            <textarea name="description" id="description" rows="4" class="large-text"><?php echo $editing ? esc_textarea($edit_paper->description) : ''; ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="duration">Thời gian (phút) *</label></th>
                        <td>
                            <input type="number" name="duration" id="duration" min="1" max="300" 
                                   value="<?php echo $editing ? $edit_paper->duration : '60'; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><label for="passing_score">Điểm đạt (%) *</label></th>
                        <td>
                            <input type="number" name="passing_score" id="passing_score" min="0" max="100" 
                                   value="<?php echo $editing ? $edit_paper->passing_score : '50'; ?>" required>
                        </td>
                    </tr>
                </table>
                
                <h3>Chọn câu hỏi cho đề thi</h3>
                <div class="question-selector">
                    <div class="selector-controls">
                        <select id="filter-subject-select">
                            <option value="">-- Chọn môn học --</option>
                            <?php foreach ($all_subjects as $subj): ?>
                                <option value="<?php echo esc_attr($subj); ?>"><?php echo esc_html($subj); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button" onclick="loadQuestions()">Tải câu hỏi</button>
                    </div>
                    
                    <div id="available-questions" class="questions-box">
                        <h4>Câu hỏi có sẵn</h4>
                        <div id="available-list" class="questions-list">
                            <p>Chọn môn học và nhấn "Tải câu hỏi"</p>
                        </div>
                    </div>
                    
                    <div id="selected-questions" class="questions-box">
                        <h4>Câu hỏi đã chọn (<span id="selected-count">0</span>)</h4>
                        <div id="selected-list" class="questions-list">
                            <?php if ($editing && !empty($edit_questions)): ?>
                                <?php foreach ($edit_questions as $q): ?>
                                    <div class="question-item selected" data-id="<?php echo $q->id; ?>">
                                        <input type="hidden" name="question_ids[]" value="<?php echo $q->id; ?>">
                                        <strong><?php echo $q->id; ?>.</strong>
                                        <?php echo wp_trim_words(strip_tags($q->question_text), 10); ?>
                                        <span class="q-type"><?php echo $q->question_type; ?></span>
                                        <span class="q-points"><?php echo $q->points; ?>đ</span>
                                        <button type="button" class="button-link remove-question">✕</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-message">Chưa có câu hỏi nào</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $editing ? 'Cập nhật đề thi' : 'Tạo đề thi'; ?>
                    </button>
                </p>
            </form>
        </div>
        
    <?php else: ?>
        <!-- Danh sách đề thi -->
        <div class="exam-filters">
            <form method="get">
                <input type="hidden" name="page" value="exam-papers">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Tìm kiếm đề thi...">
                
                <select name="filter_subject">
                    <option value="">Tất cả môn học</option>
                    <?php foreach ($subjects as $subj): ?>
                        <option value="<?php echo esc_attr($subj); ?>" <?php selected($filter_subject, $subj); ?>>
                            <?php echo esc_html($subj); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="filter_category">
                    <option value="">Tất cả tỉnh</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>" <?php selected($filter_category, $cat); ?>>
                            <?php echo esc_html($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="button">Lọc</button>
                <a href="<?php echo admin_url('admin.php?page=exam-papers'); ?>" class="button">Xóa lọc</a>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Tên đề thi</th>
                    <th style="width:120px;">Môn học</th>
                    <th style="width:120px;">Tỉnh</th>
                    <th style="width:100px;">Số câu hỏi</th>
                    <th style="width:100px;">Thời gian</th>
                    <th style="width:100px;">Lượt thi</th>
                    <th style="width:150px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($papers): ?>
                    <?php foreach ($papers as $p): 
                        $q_count = count(json_decode($p->question_ids, true));
                        $attempts = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}exam_results WHERE exam_id = %d",
                            $p->id
                        ));
                    ?>
                        <tr>
                            <td><?php echo $p->id; ?></td>
                            <td><strong><?php echo esc_html($p->title); ?></strong></td>
                            <td><?php echo esc_html($p->subject); ?></td>
                            <td><?php echo esc_html($p->category); ?></td>
                            <td><span class="badge"><?php echo $q_count; ?></span></td>
                            <td><?php echo $p->duration; ?> phút</td>
                            <td><?php echo number_format($attempts); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=exam-papers&action=edit&id=' . $p->id); ?>" class="button button-small">Sửa</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa đề thi này?');">
                                    <?php wp_nonce_field('exam_papers_action'); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="paper_id" value="<?php echo $p->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">Không có đề thi nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php
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
let allQuestions = [];

function loadQuestions() {
    const subject = document.getElementById('filter-subject-select').value;
    if (!subject) {
        alert('Vui lòng chọn môn học');
        return;
    }
    
    jQuery.post(ajaxurl, {
        action: 'load_questions_by_subject',
        subject: subject
    }, function(response) {
        if (response.success) {
            allQuestions = response.data;
            renderAvailableQuestions();
        }
    });
}

function renderAvailableQuestions() {
    const container = document.getElementById('available-list');
    const selectedIds = Array.from(document.querySelectorAll('#selected-list input[name="question_ids[]"]'))
        .map(input => parseInt(input.value));
    
    container.innerHTML = allQuestions
        .filter(q => !selectedIds.includes(q.id))
        .map(q => `
            <div class="question-item" data-id="${q.id}">
                <strong>${q.id}.</strong>
                ${q.question_text.substring(0, 100)}...
                <span class="q-type">${q.question_type}</span>
                <span class="q-points">${q.points}đ</span>
                <button type="button" class="button-link add-question">+</button>
            </div>
        `).join('') || '<p class="empty-message">Không có câu hỏi nào</p>';
    
    attachQuestionEvents();
}

function renderSelectedQuestions() {
    updateSelectedCount();
}

function attachQuestionEvents() {
    document.querySelectorAll('.add-question').forEach(btn => {
        btn.onclick = function() {
            const item = this.closest('.question-item');
            const id = item.dataset.id;
            const question = allQuestions.find(q => q.id == id);
            
            const selectedList = document.getElementById('selected-list');
            const emptyMsg = selectedList.querySelector('.empty-message');
            if (emptyMsg) emptyMsg.remove();
            
            const newItem = document.createElement('div');
            newItem.className = 'question-item selected';
            newItem.dataset.id = id;
            newItem.innerHTML = `
                <input type="hidden" name="question_ids[]" value="${id}">
                <strong>${id}.</strong>
                ${question.question_text.substring(0, 100)}...
                <span class="q-type">${question.question_type}</span>
                <span class="q-points">${question.points}đ</span>
                <button type="button" class="button-link remove-question">✕</button>
            `;
            selectedList.appendChild(newItem);
            
            item.remove();
            updateSelectedCount();
            attachRemoveEvents();
        };
    });
}

function attachRemoveEvents() {
    document.querySelectorAll('.remove-question').forEach(btn => {
        btn.onclick = function() {
            const item = this.closest('.question-item');
            item.remove();
            updateSelectedCount();
            renderAvailableQuestions();
        };
    });
}

function updateSelectedCount() {
    const count = document.querySelectorAll('#selected-list .question-item').length;
    document.getElementById('selected-count').textContent = count;
}

document.addEventListener('DOMContentLoaded', function() {
    attachRemoveEvents();
    updateSelectedCount();
});
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

.question-selector {
    background: #f9f9f9;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin: 20px 0;
}

.selector-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.questions-box {
    margin: 10px 0;
}

.questions-list {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
    background: white;
}

.question-item {
    padding: 10px;
    margin: 5px 0;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 3px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.question-item.selected {
    background: #e7f5ff;
    border-color: #2271b1;
}

.q-type {
    background: #666;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}

.q-points {
    background: #f0ad4e;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}

.button-link {
    background: none;
    border: none;
    color: #2271b1;
    cursor: pointer;
    font-size: 18px;
    padding: 0 5px;
}

.button-link:hover {
    color: #135e96;
}

.remove-question {
    color: #dc3232;
    margin-left: auto;
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