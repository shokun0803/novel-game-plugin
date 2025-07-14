<?php
/**
 * Plugin Name: Novel Game Plugin
 * Plugin URI: https://github.com/shokun0803/novel-game-plugin
 * Description: WordPressでノベルゲームを作成できるプラグイン。
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://github.com/shokun0803
 * Text Domain: novel-game-plugin
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package NovelGamePlugin
 * @since 1.0.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグインの基本定数を定義
if ( ! defined( 'NOVEL_GAME_PLUGIN_VERSION' ) ) {
    define( 'NOVEL_GAME_PLUGIN_VERSION', '1.1.0' );
}
if ( ! defined( 'NOVEL_GAME_PLUGIN_URL' ) ) {
    define( 'NOVEL_GAME_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'NOVEL_GAME_PLUGIN_PATH' ) ) {
    define( 'NOVEL_GAME_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'NOVEL_GAME_PLUGIN_BASENAME' ) ) {
    define( 'NOVEL_GAME_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'NOVEL_GAME_PLUGIN_TEXT_DOMAIN' ) ) {
    define( 'NOVEL_GAME_PLUGIN_TEXT_DOMAIN', 'novel-game-plugin' );
}

// 必要なファイルをインクルード
require_once NOVEL_GAME_PLUGIN_PATH . 'includes/post-types.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/meta-boxes.php';

/**
 * プラグインの初期化
 *
 * @since 1.0.0
 */
function noveltool_init() {
    // 言語ファイルの読み込み
    load_plugin_textdomain(
        NOVEL_GAME_PLUGIN_TEXT_DOMAIN,
        false,
        dirname( NOVEL_GAME_PLUGIN_BASENAME ) . '/languages'
    );
}
add_action( 'plugins_loaded', 'noveltool_init' );


/**
 * カスタム投稿タイプ「novel_game」のコンテンツをノベルゲームビューに置き換える
 *
 * @param string $content 投稿のコンテンツ
 * @return string 変更されたコンテンツ
 * @since 1.0.0
 */
function noveltool_filter_novel_game_content( $content ) {
    global $post;

    // 適切な条件でのみ処理を実行
    if ( ! is_singular( 'novel_game' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    // セキュリティチェック：投稿が存在するかチェック
    if ( ! $post || ! isset( $post->ID ) ) {
        return $content;
    }

    // メタデータの取得
    $background = get_post_meta( $post->ID, '_background_image', true );
    $character  = get_post_meta( $post->ID, '_character_image', true );
    $dialogue   = get_post_meta( $post->ID, '_dialogue_text', true );
    $choices_raw = get_post_meta( $post->ID, '_choices', true );
    $game_title = get_post_meta( $post->ID, '_game_title', true );

    // セリフの処理
    $dialogue_lines = array();
    if ( $dialogue ) {
        $dialogue_lines = array_filter( array_map( 'trim', explode( "\n", $dialogue ) ) );
    }

    // 選択肢の処理
    $choices = array();
    if ( $choices_raw ) {
        foreach ( explode( "\n", $choices_raw ) as $line ) {
            $parts = explode( '|', $line );
            if ( count( $parts ) === 2 ) {
                $post_id   = intval( trim( $parts[1] ) );
                $permalink = get_permalink( $post_id );

                if ( $permalink ) {
                    $choices[] = array(
                        'text'      => trim( $parts[0] ),
                        'nextScene' => $permalink,
                    );
                }
            }
        }
    }

    // テンプレートの出力
    ob_start();

    if ( $game_title ) :
        ?>
        <div id="novel-game-title" class="novel-game-title">
            <?php echo esc_html( $game_title ); ?>
        </div>
        <?php
    endif;
    ?>

    <div id="novel-game-container" class="novel-game-container" style="background-image: url('<?php echo esc_url( $background ); ?>');">
        <?php if ( $character ) : ?>
            <img id="novel-character" class="novel-character" src="<?php echo esc_url( $character ); ?>" alt="<?php echo esc_attr__( 'キャラクター', 'novel-game-plugin' ); ?>" />
        <?php endif; ?>

        <div id="novel-dialogue-box" class="novel-dialogue-box">
            <span id="novel-dialogue-text"></span>
        </div>

        <div id="novel-choices" class="novel-choices"></div>

        <script id="novel-dialogue-data" type="application/json">
            <?php echo wp_json_encode( $dialogue_lines, JSON_UNESCAPED_UNICODE ); ?>
        </script>

        <script id="novel-choices-data" type="application/json">
            <?php echo wp_json_encode( $choices, JSON_UNESCAPED_UNICODE ); ?>
        </script>
    </div>

    <?php
    return ob_get_clean();
}
add_filter( 'the_content', 'noveltool_filter_novel_game_content', 20 );

/**
 * フロントエンドとバックエンドのスクリプト・スタイルを読み込む
 *
 * @since 1.0.0
 */
function noveltool_enqueue_scripts() {
    wp_enqueue_script(
        'novel-game-frontend',
        NOVEL_GAME_PLUGIN_URL . 'js/frontend.js',
        array( 'jquery' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );

    wp_enqueue_style(
        'novel-game-style',
        NOVEL_GAME_PLUGIN_URL . 'css/style.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );
}
add_action( 'wp_enqueue_scripts', 'noveltool_enqueue_scripts' );
?>
