<?php
/**
 * Plugin Name: Novel Game Plugin
 * Description: WordPressでノベルゲームを作成できるプラグイン。
 * Version: 1.1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}


require_once plugin_dir_path(__FILE__) . 'includes/post-types.php';
require_once plugin_dir_path(__FILE__) . 'admin/meta-boxes.php';
require_once plugin_dir_path(__FILE__) . 'admin/new-game.php';


// カスタム投稿タイプ「novel_game」の本文出力をノベルゲームビューに置き換え
add_filter('the_content', function($content) {
    global $post;
    if (!is_singular('novel_game') || !in_the_loop() || !is_main_query()) return $content;

    $background = esc_url(get_post_meta($post->ID, '_background_image', true));
    $character = esc_url(get_post_meta($post->ID, '_character_image', true));
    $dialogue = get_post_meta($post->ID, '_dialogue_text', true);
    $choices_raw = get_post_meta($post->ID, '_choices', true);
    $game_title = get_post_meta($post->ID, '_game_title', true);

    $dialogue_lines = array_filter(array_map('trim', explode("\n", $dialogue)));
    $choices = array();
    if ($choices_raw) {
        foreach (explode("\n", $choices_raw) as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 2) {
                $choices[] = array(
                    'text' => trim($parts[0]),
                    'nextScene' => get_permalink(trim($parts[1]))
                );
            }
        }
    }

    ob_start();
    ?>
    <?php if ($game_title): ?>
    <div id="novel-game-title" style="text-align: center; font-size: 24px; font-weight: bold; margin-bottom: 20px; color: #333;">
        <?php echo esc_html($game_title); ?>
    </div>
    <?php endif; ?>
    <div id="novel-game-container" style="background-image:url('<?php echo esc_url($background); ?>'); min-height: 400px; position: relative;">
        <?php if ($character): ?>
        <img id="novel-character" src="<?php echo esc_url($character); ?>" alt="character" style="position:absolute;left:50%;bottom:100px;max-height:50%;transform:translateX(-50%);z-index:2;">
        <?php endif; ?>
        <div id="novel-dialogue-box" style="position:absolute;bottom:0;width:100%;background:rgba(0,0,0,0.7);color:#fff;padding:20px;z-index:3;">
            <span id="novel-dialogue-text"></span>
        </div>
        <div id="novel-choices" style="position:absolute;bottom:80px;width:100%;text-align:center;z-index:4;"></div>
        <script id="novel-dialogue-data" type="application/json"><?php echo json_encode($dialogue_lines, JSON_UNESCAPED_UNICODE); ?></script>
        <script id="novel-choices-data" type="application/json"><?php echo json_encode($choices, JSON_UNESCAPED_UNICODE); ?></script>
    </div>
    <?php
    return ob_get_clean();
}, 20);

function novel_game_enqueue_scripts() {
    wp_enqueue_script('novel-game-frontend', plugin_dir_url(__FILE__) . 'js/frontend.js', array('jquery'), '1.1.0', true);
    wp_enqueue_style('novel-game-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), '1.1.0');
}
add_action('wp_enqueue_scripts', 'novel_game_enqueue_scripts');

/**
 * 管理画面メニューの追加
 */
function noveltool_add_admin_menu() {
    add_menu_page(
        'ノベルゲーム',
        'ノベルゲーム',
        'edit_posts',
        'novel-games',
        'noveltool_games_list_page',
        'dashicons-book',
        25
    );
    
    add_submenu_page(
        'novel-games',
        '新規ゲーム作成',
        '新規作成',
        'edit_posts',
        'novel-games-new',
        'noveltool_new_game_page'
    );
    
    add_submenu_page(
        'novel-games',
        'ゲーム一覧',
        'ゲーム一覧',
        'edit_posts',
        'edit.php?post_type=novel_game'
    );
}
add_action('admin_menu', 'noveltool_add_admin_menu');

/**
 * ゲーム一覧ページ（メインページ）
 */
function noveltool_games_list_page() {
    ?>
    <div class="wrap">
        <h1>ノベルゲーム</h1>
        <p>ノベルゲームを管理します。</p>
        
        <div class="notice notice-info">
            <p>
                <strong>はじめに</strong><br>
                新しいノベルゲームを作成するには、「新規作成」をクリックしてください。
            </p>
        </div>
        
        <div class="card">
            <h2>クイックアクション</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=novel-games-new'); ?>" class="button button-primary">
                    新規ゲーム作成
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=novel_game'); ?>" class="button">
                    ゲーム一覧を表示
                </a>
            </p>
        </div>
        
        <?php
        // 最近作成されたゲームを表示
        $recent_games = get_posts([
            'post_type' => 'novel_game',
            'posts_per_page' => 5,
            'post_status' => ['publish', 'draft'],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        if (!empty($recent_games)) {
            ?>
            <div class="card">
                <h2>最近作成されたゲーム</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>ゲームタイトル</th>
                            <th>シーン名</th>
                            <th>ステータス</th>
                            <th>作成日</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_games as $game): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(get_post_meta($game->ID, '_game_title', true)); ?></strong>
                            </td>
                            <td><?php echo esc_html($game->post_title); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($game->post_status); ?>">
                                    <?php echo esc_html($game->post_status === 'publish' ? '公開中' : '下書き'); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(get_the_date('Y-m-d H:i', $game->ID)); ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($game->ID); ?>" class="button button-small">
                                    編集
                                </a>
                                <?php if ($game->post_status === 'publish'): ?>
                                <a href="<?php echo get_permalink($game->ID); ?>" class="button button-small" target="_blank">
                                    表示
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
        ?>
    </div>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        margin: 16px 0;
        padding: 16px;
    }
    .card h2 {
        margin: 0 0 16px 0;
        padding: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 8px;
    }
    .status-publish {
        color: #46b450;
    }
    .status-draft {
        color: #ffb900;
    }
    </style>
    <?php
}
?>
