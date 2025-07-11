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
 * アーカイブテンプレートの読み込み
 * 
 * @param string $template テンプレートファイルパス
 * @return string 変更後のテンプレートファイルパス
 */
function novel_game_load_archive_template($template) {
    if (is_post_type_archive('novel_game')) {
        $plugin_template = plugin_dir_path(__FILE__) . 'templates/archive-novel_game.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}
add_filter('archive_template', 'novel_game_load_archive_template');
?>
