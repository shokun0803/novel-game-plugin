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
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/new-game.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/game-settings.php';

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
 * ゲーム設定を取得するヘルパー関数
 *
 * @param string $key 設定キー (title, description, title_image)
 * @return string 設定値
 * @since 1.1.0
 */
function noveltool_get_game_setting( $key ) {
    $settings = array(
        'title'       => get_option( 'noveltool_game_title', '' ),
        'description' => get_option( 'noveltool_game_description', '' ),
        'title_image' => get_option( 'noveltool_game_title_image', '' ),
    );
    
    return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
}

/**
 * すべてのゲーム設定を取得する関数
 *
 * @return array ゲーム設定の配列
 * @since 1.1.0
 */
function noveltool_get_all_game_settings() {
    return array(
        'title'       => get_option( 'noveltool_game_title', '' ),
        'description' => get_option( 'noveltool_game_description', '' ),
        'title_image' => get_option( 'noveltool_game_title_image', '' ),
    );
}


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
    $dialogue_backgrounds = get_post_meta( $post->ID, '_dialogue_backgrounds', true );
    
    // 3体キャラクター対応のメタデータ取得
    $character_left   = get_post_meta( $post->ID, '_character_left', true );
    $character_center = get_post_meta( $post->ID, '_character_center', true );
    $character_right  = get_post_meta( $post->ID, '_character_right', true );
    $dialogue_speakers = get_post_meta( $post->ID, '_dialogue_speakers', true );
    
    // 後方互換性：既存の単一キャラクターをセンターに設定
    if ( $character && ! $character_center ) {
        $character_center = $character;
    }

    // セリフの処理
    $dialogue_lines = array();
    if ( $dialogue ) {
        $dialogue_lines = array_filter( array_map( 'trim', explode( "\n", $dialogue ) ) );
    }
    
    // セリフ背景の処理
    $dialogue_backgrounds_array = array();
    if ( is_string( $dialogue_backgrounds ) ) {
        $dialogue_backgrounds_array = json_decode( $dialogue_backgrounds, true );
    } elseif ( is_array( $dialogue_backgrounds ) ) {
        $dialogue_backgrounds_array = $dialogue_backgrounds;
    }
    
    // セリフ話者の処理
    $dialogue_speakers_array = array();
    if ( is_string( $dialogue_speakers ) ) {
        $dialogue_speakers_array = json_decode( $dialogue_speakers, true );
    } elseif ( is_array( $dialogue_speakers ) ) {
        $dialogue_speakers_array = $dialogue_speakers;
    }
    
    // セリフと背景と話者を組み合わせた配列を作成
    $dialogue_data = array();
    foreach ( $dialogue_lines as $index => $line ) {
        $dialogue_data[] = array(
            'text' => $line,
            'background' => isset( $dialogue_backgrounds_array[ $index ] ) ? $dialogue_backgrounds_array[ $index ] : '',
            'speaker' => isset( $dialogue_speakers_array[ $index ] ) ? $dialogue_speakers_array[ $index ] : ''
        );
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
        <!-- 3体キャラクター表示 -->
        <?php if ( $character_left ) : ?>
            <img id="novel-character-left" class="novel-character novel-character-left" src="<?php echo esc_url( $character_left ); ?>" alt="<?php echo esc_attr__( '左キャラクター', 'novel-game-plugin' ); ?>" />
        <?php endif; ?>
        
        <?php if ( $character_center ) : ?>
            <img id="novel-character-center" class="novel-character novel-character-center" src="<?php echo esc_url( $character_center ); ?>" alt="<?php echo esc_attr__( '中央キャラクター', 'novel-game-plugin' ); ?>" />
        <?php endif; ?>
        
        <?php if ( $character_right ) : ?>
            <img id="novel-character-right" class="novel-character novel-character-right" src="<?php echo esc_url( $character_right ); ?>" alt="<?php echo esc_attr__( '右キャラクター', 'novel-game-plugin' ); ?>" />
        <?php endif; ?>
        
        <!-- 後方互換性のための旧キャラクター表示 -->
        <?php if ( $character && ! $character_center ) : ?>
            <img id="novel-character" class="novel-character novel-character-center" src="<?php echo esc_url( $character ); ?>" alt="<?php echo esc_attr__( 'キャラクター', 'novel-game-plugin' ); ?>" />
        <?php endif; ?>

        <div id="novel-dialogue-box" class="novel-dialogue-box">
            <div id="novel-speaker-name" class="novel-speaker-name"></div>
            <span id="novel-dialogue-text"></span>
            <div id="novel-dialogue-continue" class="novel-dialogue-continue" style="display: none;">
                <span class="continue-indicator">▼</span>
            </div>
        </div>

        <div id="novel-choices" class="novel-choices"></div>

        <script id="novel-dialogue-data" type="application/json">
            <?php echo wp_json_encode( $dialogue_data, JSON_UNESCAPED_UNICODE ); ?>
        </script>
        
        <script id="novel-base-background" type="application/json">
            <?php echo wp_json_encode( $background, JSON_UNESCAPED_UNICODE ); ?>
        </script>
        
        <script id="novel-characters-data" type="application/json">
            <?php echo wp_json_encode( array(
                'left' => $character_left,
                'center' => $character_center,
                'right' => $character_right,
                'legacy' => $character // 後方互換性のため
            ), JSON_UNESCAPED_UNICODE ); ?>
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

/**
 * カスタムテンプレートを読み込む
 *
 * @param string $template 現在のテンプレートパス
 * @return string 適切なテンプレートパス
 * @since 1.1.0
 */
function noveltool_load_custom_templates( $template ) {
    // アーカイブページのテンプレートを読み込む
    if ( is_post_type_archive( 'novel_game' ) ) {
        $custom_template = NOVEL_GAME_PLUGIN_PATH . 'templates/archive-novel_game.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    
    return $template;
}
add_filter( 'template_include', 'noveltool_load_custom_templates' );

/**
 * ゲーム投稿一覧を表示するショートコード
 *
 * @param array $atts ショートコードの属性
 * @return string ショートコードの出力
 * @since 1.0.0
 */
function noveltool_game_posts_shortcode( $atts ) {
    // 属性のデフォルト値
    $atts = shortcode_atts( array(
        'game_title' => '',
        'limit'      => -1,
        'orderby'    => 'date',
        'order'      => 'ASC',
        'show_title' => 'true',
        'show_date'  => 'true',
    ), $atts, 'novel_game_posts' );
    
    // パラメータの処理
    $game_title = sanitize_text_field( $atts['game_title'] );
    $limit      = intval( $atts['limit'] );
    $orderby    = sanitize_text_field( $atts['orderby'] );
    $order      = sanitize_text_field( $atts['order'] );
    $show_title = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
    $show_date  = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
    
    // ゲームタイトルが指定されていない場合は全ゲームを表示
    if ( empty( $game_title ) ) {
        return noveltool_all_games_shortcode_output( $atts );
    }
    
    // 投稿の取得
    $posts = noveltool_get_posts_by_game_title( $game_title, array(
        'posts_per_page' => $limit,
        'orderby'        => $orderby,
        'order'          => $order,
    ) );
    
    if ( empty( $posts ) ) {
        return '<p>' . esc_html__( 'このゲームには投稿がありません。', 'novel-game-plugin' ) . '</p>';
    }
    
    // 出力の開始
    ob_start();
    
    echo '<div class="noveltool-game-posts-list">';
    
    if ( $show_title ) {
        echo '<h3 class="noveltool-game-title">' . esc_html( $game_title ) . '</h3>';
    }
    
    echo '<div class="noveltool-posts-grid">';
    
    foreach ( $posts as $post ) {
        setup_postdata( $post );
        
        $background = get_post_meta( $post->ID, '_background_image', true );
        $dialogue   = get_post_meta( $post->ID, '_dialogue_text', true );
        
        echo '<div class="noveltool-post-item">';
        
        if ( $background ) {
            echo '<div class="noveltool-post-thumbnail">';
            echo '<img src="' . esc_url( $background ) . '" alt="' . esc_attr( $post->post_title ) . '" />';
            echo '</div>';
        }
        
        echo '<div class="noveltool-post-content">';
        echo '<h4 class="noveltool-post-title">';
        echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . esc_html( $post->post_title ) . '</a>';
        echo '</h4>';
        
        if ( $dialogue ) {
            $dialogue_preview = mb_substr( strip_tags( $dialogue ), 0, 100 );
            echo '<p class="noveltool-post-dialogue">' . esc_html( $dialogue_preview ) . '...</p>';
        }
        
        if ( $show_date ) {
            echo '<p class="noveltool-post-date">' . esc_html( get_the_date( 'Y年m月d日', $post->ID ) ) . '</p>';
        }
        
        echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '" class="noveltool-post-link button">';
        echo esc_html__( 'プレイ', 'novel-game-plugin' );
        echo '</a>';
        
        echo '</div>'; // .noveltool-post-content
        echo '</div>'; // .noveltool-post-item
    }
    
    echo '</div>'; // .noveltool-posts-grid
    echo '</div>'; // .noveltool-game-posts-list
    
    wp_reset_postdata();
    
    return ob_get_clean();
}
add_shortcode( 'novel_game_posts', 'noveltool_game_posts_shortcode' );

/**
 * すべてのゲーム一覧を表示するショートコード出力
 *
 * @param array $atts ショートコードの属性
 * @return string ショートコードの出力
 * @since 1.0.0
 */
function noveltool_all_games_shortcode_output( $atts ) {
    $game_titles = noveltool_get_all_game_titles();
    
    if ( empty( $game_titles ) ) {
        return '<p>' . esc_html__( 'まだゲームが作成されていません。', 'novel-game-plugin' ) . '</p>';
    }
    
    ob_start();
    
    echo '<div class="noveltool-all-games-list">';
    echo '<h3>' . esc_html__( 'ゲーム一覧', 'novel-game-plugin' ) . '</h3>';
    echo '<div class="noveltool-games-grid">';
    
    foreach ( $game_titles as $game_title ) {
        $posts = noveltool_get_posts_by_game_title( $game_title, array( 'posts_per_page' => 1 ) );
        
        if ( empty( $posts ) ) {
            continue;
        }
        
        $first_post = $posts[0];
        $background = get_post_meta( $first_post->ID, '_background_image', true );
        $post_count = count( noveltool_get_posts_by_game_title( $game_title ) );
        
        echo '<div class="noveltool-game-item">';
        
        if ( $background ) {
            echo '<div class="noveltool-game-thumbnail">';
            echo '<img src="' . esc_url( $background ) . '" alt="' . esc_attr( $game_title ) . '" />';
            echo '</div>';
        }
        
        echo '<div class="noveltool-game-info">';
        echo '<h4 class="noveltool-game-title">' . esc_html( $game_title ) . '</h4>';
        echo '<p class="noveltool-game-count">' . sprintf( esc_html__( '%d シーン', 'novel-game-plugin' ), $post_count ) . '</p>';
        echo '<a href="' . esc_url( get_permalink( $first_post->ID ) ) . '" class="noveltool-game-link button">';
        echo esc_html__( 'プレイ開始', 'novel-game-plugin' );
        echo '</a>';
        echo '</div>';
        
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    
    return ob_get_clean();
}

/**
 * ショートコード用のスタイルを追加
 *
 * @since 1.0.0
 */
function noveltool_shortcode_styles() {
    ?>
    <style>
    .noveltool-game-posts-list,
    .noveltool-all-games-list {
        margin: 20px 0;
    }
    .noveltool-posts-grid,
    .noveltool-games-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }
    .noveltool-post-item,
    .noveltool-game-item {
        border: 1px solid #ddd;
        border-radius: 5px;
        overflow: hidden;
        background: white;
    }
    .noveltool-post-thumbnail,
    .noveltool-game-thumbnail {
        width: 100%;
        height: 200px;
        overflow: hidden;
    }
    .noveltool-post-thumbnail img,
    .noveltool-game-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .noveltool-post-content,
    .noveltool-game-info {
        padding: 15px;
    }
    .noveltool-post-title,
    .noveltool-game-title {
        margin: 0 0 10px 0;
        font-size: 1.2em;
    }
    .noveltool-post-title a {
        text-decoration: none;
        color: inherit;
    }
    .noveltool-post-title a:hover {
        color: #0073aa;
    }
    .noveltool-post-dialogue {
        color: #666;
        font-size: 0.9em;
        margin-bottom: 10px;
    }
    .noveltool-post-date,
    .noveltool-game-count {
        color: #999;
        font-size: 0.8em;
        margin-bottom: 15px;
    }
    .noveltool-post-link,
    .noveltool-game-link {
        display: inline-block;
        padding: 8px 15px;
        background: #0073aa;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 0.9em;
    }
    .noveltool-post-link:hover,
    .noveltool-game-link:hover {
        background: #005a87;
        color: white;
    }
    </style>
    <?php
}
add_action( 'wp_head', 'noveltool_shortcode_styles' );
?>
