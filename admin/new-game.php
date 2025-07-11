<?php
/**
 * 新規ゲーム作成用管理画面
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 新規ゲーム作成画面のHTML出力
 */
function noveltool_new_game_page() {
    ?>
    <div class="wrap">
        <h1>新規ゲーム作成</h1>
        <p>新しいノベルゲームを作成します。まずはゲームタイトルを入力してください。</p>
        
        <form method="post" action="" id="new-game-form">
            <?php wp_nonce_field('noveltool_new_game_nonce', 'noveltool_new_game_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="game_title">ゲームタイトル</label>
                    </th>
                    <td>
                        <input type="text" id="game_title" name="game_title" class="regular-text" value="" required>
                        <p class="description">
                            ゲームのタイトルを入力してください。他のゲームと重複しないユニークなタイトルにしてください。
                        </p>
                        <div id="title-validation-message" style="display: none;"></div>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="create_game" id="create-game-btn" class="button button-primary" value="ゲームを作成">
                <span class="spinner" id="create-game-spinner"></span>
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var titleInput = $('#game_title');
        var submitBtn = $('#create-game-btn');
        var spinner = $('#create-game-spinner');
        var validationMessage = $('#title-validation-message');
        
        // リアルタイムバリデーション
        titleInput.on('input', function() {
            var title = $(this).val().trim();
            
            if (title.length === 0) {
                validationMessage.hide();
                submitBtn.prop('disabled', false);
                return;
            }
            
            if (title.length < 2) {
                validationMessage
                    .text('タイトルは2文字以上で入力してください。')
                    .css('color', 'red')
                    .show();
                submitBtn.prop('disabled', true);
                return;
            }
            
            // 重複チェック
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'noveltool_check_game_title',
                    title: title,
                    nonce: '<?php echo wp_create_nonce('noveltool_check_title_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.exists) {
                            validationMessage
                                .text('このタイトルは既に使用されています。')
                                .css('color', 'red')
                                .show();
                            submitBtn.prop('disabled', true);
                        } else {
                            validationMessage
                                .text('このタイトルは使用可能です。')
                                .css('color', 'green')
                                .show();
                            submitBtn.prop('disabled', false);
                        }
                    }
                }
            });
        });
        
        // フォーム送信
        $('#new-game-form').on('submit', function(e) {
            e.preventDefault();
            
            var title = titleInput.val().trim();
            
            if (title.length === 0) {
                alert('ゲームタイトルを入力してください。');
                return;
            }
            
            submitBtn.prop('disabled', true);
            spinner.addClass('is-active');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'noveltool_create_new_game',
                    title: title,
                    nonce: '<?php echo wp_create_nonce('noveltool_create_game_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // 成功時はシーン編集画面へリダイレクト
                        window.location.href = response.data.edit_url;
                    } else {
                        alert('ゲームの作成に失敗しました: ' + response.data.message);
                        submitBtn.prop('disabled', false);
                        spinner.removeClass('is-active');
                    }
                },
                error: function() {
                    alert('エラーが発生しました。');
                    submitBtn.prop('disabled', false);
                    spinner.removeClass('is-active');
                }
            });
        });
    });
    </script>
    
    <style>
    .form-table th {
        width: 200px;
    }
    #title-validation-message {
        margin-top: 5px;
        font-weight: bold;
    }
    .spinner.is-active {
        visibility: visible;
    }
    </style>
    <?php
}

/**
 * AJAX: ゲームタイトルの重複チェック
 */
add_action('wp_ajax_noveltool_check_game_title', 'noveltool_ajax_check_game_title');
function noveltool_ajax_check_game_title() {
    // nonce検証
    if (!wp_verify_nonce($_POST['nonce'], 'noveltool_check_title_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました。']);
    }
    
    $title = sanitize_text_field($_POST['title']);
    
    if (empty($title)) {
        wp_send_json_error(['message' => 'タイトルが入力されていません。']);
    }
    
    // 同じゲームタイトルが存在するかチェック
    $existing_games = get_posts([
        'post_type' => 'novel_game',
        'meta_query' => [
            [
                'key' => '_game_title',
                'value' => $title,
                'compare' => '='
            ]
        ],
        'post_status' => ['publish', 'draft', 'private'],
        'posts_per_page' => 1
    ]);
    
    wp_send_json_success([
        'exists' => !empty($existing_games),
        'title' => $title
    ]);
}

/**
 * AJAX: 新規ゲーム作成
 */
add_action('wp_ajax_noveltool_create_new_game', 'noveltool_ajax_create_new_game');
function noveltool_ajax_create_new_game() {
    // nonce検証
    if (!wp_verify_nonce($_POST['nonce'], 'noveltool_create_game_nonce')) {
        wp_send_json_error(['message' => 'セキュリティチェックに失敗しました。']);
    }
    
    // 権限チェック
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => '権限が不足しています。']);
    }
    
    $title = sanitize_text_field($_POST['title']);
    
    if (empty($title)) {
        wp_send_json_error(['message' => 'ゲームタイトルを入力してください。']);
    }
    
    if (strlen($title) < 2) {
        wp_send_json_error(['message' => 'タイトルは2文字以上で入力してください。']);
    }
    
    // 重複チェック
    $existing_games = get_posts([
        'post_type' => 'novel_game',
        'meta_query' => [
            [
                'key' => '_game_title',
                'value' => $title,
                'compare' => '='
            ]
        ],
        'post_status' => ['publish', 'draft', 'private'],
        'posts_per_page' => 1
    ]);
    
    if (!empty($existing_games)) {
        wp_send_json_error(['message' => 'このタイトルは既に使用されています。']);
    }
    
    // 新規投稿を作成
    $post_data = [
        'post_type' => 'novel_game',
        'post_title' => sprintf('%s - シーン1', $title),
        'post_status' => 'draft',
        'post_author' => get_current_user_id()
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => '投稿の作成に失敗しました。']);
    }
    
    // ゲームタイトルをメタデータとして保存
    update_post_meta($post_id, '_game_title', $title);
    
    // 初期データを設定
    update_post_meta($post_id, '_dialogue_text', 'ゲームを開始します。');
    
    wp_send_json_success([
        'post_id' => $post_id,
        'title' => $title,
        'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit')
    ]);
}