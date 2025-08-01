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
 * 複数ゲーム設定を取得する関数
 *
 * @return array 複数ゲーム設定の配列
 * @since 1.1.0
 */
function noveltool_get_all_games() {
    $games = get_option( 'noveltool_games', array() );
    
    // 後方互換性: 古い単一ゲーム設定が存在する場合は移行
    if ( empty( $games ) ) {
        $legacy_title = get_option( 'noveltool_game_title', '' );
        $legacy_description = get_option( 'noveltool_game_description', '' );
        $legacy_title_image = get_option( 'noveltool_game_title_image', '' );
        
        if ( $legacy_title ) {
            $games = array(
                array(
                    'id'          => 1,
                    'title'       => $legacy_title,
                    'description' => $legacy_description,
                    'title_image' => $legacy_title_image,
                    'created_at'  => current_time( 'timestamp' ),
                    'updated_at'  => current_time( 'timestamp' ),
                )
            );
            update_option( 'noveltool_games', $games );
        }
    }
    
    return $games;
}

/**
 * 特定のゲームを取得する関数
 *
 * @param int $game_id ゲームID
 * @return array|null ゲームデータまたはnull
 * @since 1.1.0
 */
function noveltool_get_game_by_id( $game_id ) {
    $games = noveltool_get_all_games();
    
    foreach ( $games as $game ) {
        if ( $game['id'] == $game_id ) {
            return $game;
        }
    }
    
    return null;
}

/**
 * ゲームタイトルでゲームを取得する関数
 *
 * @param string $title ゲームタイトル
 * @return array|null ゲームデータまたはnull
 * @since 1.1.0
 */
function noveltool_get_game_by_title( $title ) {
    $games = noveltool_get_all_games();
    
    foreach ( $games as $game ) {
        if ( $game['title'] === $title ) {
            return $game;
        }
    }
    
    return null;
}

/**
 * ゲームを保存する関数
 *
 * @param array $game_data ゲームデータ
 * @return int|false ゲームID または false
 * @since 1.1.0
 */
function noveltool_save_game( $game_data ) {
    $games = noveltool_get_all_games();
    
    // 必須フィールドの確認
    if ( empty( $game_data['title'] ) ) {
        return false;
    }
    
    // 新規ゲームの場合
    if ( empty( $game_data['id'] ) ) {
        $max_id = 0;
        foreach ( $games as $game ) {
            if ( $game['id'] > $max_id ) {
                $max_id = $game['id'];
            }
        }
        
        $game_data['id'] = $max_id + 1;
        $game_data['created_at'] = current_time( 'timestamp' );
        $game_data['updated_at'] = current_time( 'timestamp' );
        
        $games[] = $game_data;
    } else {
        // 既存ゲームの更新
        $found = false;
        for ( $i = 0; $i < count( $games ); $i++ ) {
            if ( $games[$i]['id'] == $game_data['id'] ) {
                $game_data['created_at'] = $games[$i]['created_at']; // 作成日時を保持
                $game_data['updated_at'] = current_time( 'timestamp' );
                $games[$i] = $game_data;
                $found = true;
                break;
            }
        }
        
        if ( ! $found ) {
            return false;
        }
    }
    
    $result = update_option( 'noveltool_games', $games );
    
    return $result ? $game_data['id'] : false;
}

/**
 * ゲームを削除する関数
 *
 * @param int $game_id ゲームID
 * @return bool 削除成功の場合true
 * @since 1.1.0
 */
function noveltool_delete_game( $game_id ) {
    $games = noveltool_get_all_games();
    
    $filtered_games = array();
    foreach ( $games as $game ) {
        if ( $game['id'] != $game_id ) {
            $filtered_games[] = $game;
        }
    }
    
    return update_option( 'noveltool_games', $filtered_games );
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
    
    // キャラクター名前の取得
    $character_left_name   = get_post_meta( $post->ID, '_character_left_name', true );
    $character_center_name = get_post_meta( $post->ID, '_character_center_name', true );
    $character_right_name  = get_post_meta( $post->ID, '_character_right_name', true );
    
    // 後方互換性：既存の単一キャラクターをセンターに設定
    if ( $character && ! $character_center ) {
        $character_center = $character;
    }

    // セリフの処理
    $dialogue_lines = array();
    
    // JSONベースのデータが存在する場合は、それを使用
    if ( is_string( $dialogue_speakers ) ) {
        $dialogue_speakers_array = json_decode( $dialogue_speakers, true );
    } elseif ( is_array( $dialogue_speakers ) ) {
        $dialogue_speakers_array = $dialogue_speakers;
    } else {
        $dialogue_speakers_array = array();
    }
    
    // セリフ背景の処理
    $dialogue_backgrounds_array = array();
    if ( is_string( $dialogue_backgrounds ) ) {
        $dialogue_backgrounds_array = json_decode( $dialogue_backgrounds, true );
    } elseif ( is_array( $dialogue_backgrounds ) ) {
        $dialogue_backgrounds_array = $dialogue_backgrounds;
    }
    
    // セリフテキストデータの処理（新しいJSON形式）
    $dialogue_texts = get_post_meta( $post->ID, '_dialogue_texts', true );
    $dialogue_texts_array = array();
    if ( is_string( $dialogue_texts ) ) {
        $dialogue_texts_array = json_decode( $dialogue_texts, true );
    } elseif ( is_array( $dialogue_texts ) ) {
        $dialogue_texts_array = $dialogue_texts;
    }
    
    // JSONベースのデータが存在する場合はそれを優先
    if ( ! empty( $dialogue_speakers_array ) || ! empty( $dialogue_backgrounds_array ) || ! empty( $dialogue_texts_array ) ) {
        // 新しいJSONベースのシステムを使用
        $max_count = max(
            count( (array) $dialogue_speakers_array ),
            count( (array) $dialogue_backgrounds_array ),
            count( (array) $dialogue_texts_array )
        );
        if ( $max_count > 0 ) {
            // 新しいJSONベースのテキストデータが存在する場合は、それを使用
            if ( ! empty( $dialogue_texts_array ) ) {
                for ( $i = 0; $i < $max_count; $i++ ) {
                    $dialogue_lines[] = isset( $dialogue_texts_array[ $i ] ) ? $dialogue_texts_array[ $i ] : '';
                }
            } else {
                // 古いテキストデータから改行で分割して取得（後方互換性のため）
                $old_dialogue_lines = array();
                if ( $dialogue ) {
                    $old_dialogue_lines = array_filter( array_map( 'trim', explode( "\n", $dialogue ) ) );
                }
                
                // JSON データの数に合わせてセリフを構築
                for ( $i = 0; $i < $max_count; $i++ ) {
                    $dialogue_lines[] = isset( $old_dialogue_lines[ $i ] ) ? $old_dialogue_lines[ $i ] : '';
                }
            }
        }
    } else {
        // 古いシステムの場合、改行で分割（後方互換性のため）
        if ( $dialogue ) {
            $dialogue_lines = array_filter( array_map( 'trim', explode( "\n", $dialogue ) ) );
        }
    }
    
    // セリフ話者の処理（既に上で処理済み）
    // $dialogue_speakers_array は既に定義済み
    
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

    <!-- ゲーム開始ボタン -->
    <div id="novel-game-start-container" class="novel-game-start-container">
        <button id="novel-game-start-btn" class="novel-game-start-btn">
            <?php echo esc_html__( 'ゲームを開始', 'novel-game-plugin' ); ?>
        </button>
        <button id="novel-game-clear-progress-btn" class="novel-game-clear-progress-btn" style="display: none;">
            <?php echo esc_html__( '進捗をクリア', 'novel-game-plugin' ); ?>
        </button>
    </div>

    <!-- モーダルオーバーレイ -->
    <div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;">
        <!-- モーダルコンテンツ -->
        <div id="novel-game-modal-content" class="novel-game-modal-content">
            <!-- ゲーム閉じるボタン -->
            <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="<?php echo esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ); ?>" title="<?php echo esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ); ?>">
                <span class="close-icon">×</span>
            </button>
            
            <!-- ゲームコンテナ -->
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

                <div id="novel-speaker-name" class="novel-speaker-name"></div>
                
                <div id="novel-dialogue-box" class="novel-dialogue-box">
                    <div id="novel-dialogue-text-container" class="novel-dialogue-text-container">
                        <span id="novel-dialogue-text"></span>
                    </div>
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
                        'legacy' => $character, // 後方互換性のため
                        'left_name' => $character_left_name,
                        'center_name' => $character_center_name,
                        'right_name' => $character_right_name,
                    ), JSON_UNESCAPED_UNICODE ); ?>
                </script>

                <script id="novel-choices-data" type="application/json">
                    <?php echo wp_json_encode( $choices, JSON_UNESCAPED_UNICODE ); ?>
                </script>
            </div>
        </div>
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
 * 複数ゲーム一覧専用のショートコード
 *
 * @param array $atts ショートコードの属性
 * @return string ショートコードの出力
 * @since 1.1.0
 */
function noveltool_game_list_shortcode( $atts ) {
    // 属性のデフォルト値
    $atts = shortcode_atts( array(
        'show_count' => 'true',
        'show_description' => 'false',
        'orderby' => 'title',
        'order' => 'ASC',
        'columns' => '3',
    ), $atts, 'novel_game_list' );
    
    // パラメータの処理
    $show_count = filter_var( $atts['show_count'], FILTER_VALIDATE_BOOLEAN );
    $show_description = filter_var( $atts['show_description'], FILTER_VALIDATE_BOOLEAN );
    $orderby = sanitize_text_field( $atts['orderby'] );
    $order = sanitize_text_field( $atts['order'] );
    $columns = max( 1, min( 6, intval( $atts['columns'] ) ) ); // 1-6の範囲に制限
    
    // ゲーム一覧の取得
    $game_titles = noveltool_get_all_game_titles();
    
    if ( empty( $game_titles ) ) {
        return '<div class="noveltool-no-games">' . 
               '<p>' . esc_html__( 'まだゲームが作成されていません。', 'novel-game-plugin' ) . '</p>' .
               '</div>';
    }
    
    // ソート処理
    if ( $orderby === 'title' ) {
        if ( $order === 'DESC' ) {
            rsort( $game_titles );
        } else {
            sort( $game_titles );
        }
    }
    
    ob_start();
    
    echo '<div class="noveltool-game-list-grid noveltool-columns-' . esc_attr( $columns ) . '">';
    
    foreach ( $game_titles as $game_title ) {
        $posts = noveltool_get_posts_by_game_title( $game_title, array( 'posts_per_page' => 1 ) );
        
        if ( empty( $posts ) ) {
            continue;
        }
        
        $first_post = $posts[0];
        $background = get_post_meta( $first_post->ID, '_background_image', true );
        $post_count = count( noveltool_get_posts_by_game_title( $game_title ) );
        
        // ゲーム説明の取得（新しいゲーム管理システムから）
        $game_description = '';
        if ( $show_description ) {
            $game = noveltool_get_game_by_title( $game_title );
            if ( $game && ! empty( $game['description'] ) ) {
                $game_description = $game['description'];
            }
        }
        
        echo '<div class="noveltool-game-list-item">';
        
        if ( $background ) {
            echo '<div class="noveltool-game-thumbnail">';
            echo '<a href="' . esc_url( get_permalink( $first_post->ID ) ) . '">';
            echo '<img src="' . esc_url( $background ) . '" alt="' . esc_attr( $game_title ) . '" />';
            echo '</a>';
            echo '</div>';
        }
        
        echo '<div class="noveltool-game-content">';
        echo '<h3 class="noveltool-game-title">';
        echo '<a href="' . esc_url( get_permalink( $first_post->ID ) ) . '">' . esc_html( $game_title ) . '</a>';
        echo '</h3>';
        
        if ( $show_description && $game_description ) {
            echo '<p class="noveltool-game-description">' . esc_html( mb_substr( $game_description, 0, 120 ) );
            if ( mb_strlen( $game_description ) > 120 ) {
                echo '...';
            }
            echo '</p>';
        }
        
        if ( $show_count ) {
            echo '<p class="noveltool-game-count">' . sprintf( esc_html__( '%d シーン', 'novel-game-plugin' ), $post_count ) . '</p>';
        }
        
        echo '<div class="noveltool-game-actions">';
        echo '<button class="noveltool-play-button" ';
        echo 'data-game-url="' . esc_url( get_permalink( $first_post->ID ) ) . '" ';
        echo 'data-game-title="' . esc_attr( $game_title ) . '" ';
        echo 'data-game-description="' . esc_attr( $game_description ) . '" ';
        echo 'data-game-image="' . esc_attr( $background ) . '" ';
        echo 'data-scene-count="' . esc_attr( $post_count ) . '">';
        echo esc_html__( 'プレイ開始', 'novel-game-plugin' );
        echo '</button>';
        echo '</div>';
        
        echo '</div>'; // .noveltool-game-content
        echo '</div>'; // .noveltool-game-list-item
    }
    
    echo '</div>'; // .noveltool-game-list-grid
    
    // ゲーム選択モーダル CSS を追加
    ?>
    <style>
    /* ゲーム選択モーダル */
    .game-selection-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.85);
        z-index: 2147483646; /* ゲームモーダルより少し低い */
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
        transition: opacity 0.3s ease;
    }

    /* ゲーム選択モーダルコンテンツ */
    .game-selection-modal-content {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 20px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        position: relative;
        animation: game-selection-appear 0.4s ease-out;
    }

    @keyframes game-selection-appear {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(-30px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    /* 閉じるボタン */
    .game-selection-close-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.1);
        border: none;
        border-radius: 50%;
        color: #666;
        font-size: 18px;
        cursor: pointer;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .game-selection-close-btn:hover {
        background: rgba(0, 0, 0, 0.2);
        color: #333;
        transform: scale(1.1);
    }

    /* ゲーム選択ヘッダー */
    .game-selection-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 30px 30px 20px 30px;
        text-align: center;
    }

    /* ゲーム選択画像 */
    .game-selection-image {
        width: 100%;
        max-width: 300px;
        height: 180px;
        border-radius: 15px;
        overflow: hidden;
        margin-bottom: 20px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .game-selection-bg-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .game-selection-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 16px;
    }

    /* ゲーム選択情報 */
    .game-selection-info {
        width: 100%;
    }

    .game-selection-title {
        font-size: clamp(1.4em, 4vw, 2em);
        font-weight: bold;
        color: #333;
        margin: 0 0 15px 0;
        line-height: 1.3;
    }

    .game-selection-description {
        font-size: clamp(0.9em, 2.5vw, 1.1em);
        color: #666;
        line-height: 1.6;
        margin: 0 0 15px 0;
        max-height: 100px;
        overflow-y: auto;
    }

    .game-selection-scene-count {
        font-size: clamp(0.8em, 2vw, 1em);
        color: #888;
        margin: 0;
        font-weight: 500;
    }

    /* ゲーム選択アクション */
    .game-selection-actions {
        padding: 20px 30px 30px 30px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    /* ゲームアクションボタン */
    .game-action-btn {
        display: flex;
        align-items: center;
        padding: 20px 25px;
        border: none;
        border-radius: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: inherit;
        position: relative;
        overflow: hidden;
        min-height: 70px;
        text-align: left;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }

    .game-action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .game-action-btn:active {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }

    /* ゲーム開始ボタン */
    .game-start-btn {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
    }

    .game-start-btn:hover {
        box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
    }

    .game-start-btn:active {
        box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
    }

    /* ゲーム再開ボタン */
    .game-resume-btn {
        background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
    }

    .game-resume-btn:hover {
        box-shadow: 0 10px 30px rgba(76, 175, 80, 0.4);
    }

    .game-resume-btn:active {
        box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
    }

    /* ボタン内のアイコン */
    .btn-icon {
        font-size: 1.5em;
        margin-right: 15px;
        flex-shrink: 0;
    }

    /* ボタンテキストコンテナ */
    .game-action-btn .btn-text,
    .game-action-btn .btn-subtext {
        display: block;
        margin: 0;
    }

    .btn-text {
        font-size: clamp(1.1em, 3vw, 1.3em);
        font-weight: bold;
        margin-bottom: 5px;
    }

    .btn-subtext {
        font-size: clamp(0.8em, 2vw, 0.9em);
        opacity: 0.9;
        font-weight: normal;
    }

    /* レスポンシブ対応: ゲーム選択モーダル */
    @media (max-width: 768px) {
        .game-selection-modal-content {
            width: 95%;
            max-height: 90vh;
            border-radius: 15px;
        }
        
        .game-selection-header {
            padding: 25px 20px 15px 20px;
        }
        
        .game-selection-image {
            height: 150px;
            margin-bottom: 15px;
        }
        
        .game-selection-actions {
            padding: 15px 20px 25px 20px;
            gap: 12px;
        }
        
        .game-action-btn {
            min-height: 60px;
            padding: 15px 20px;
        }
    }

    @media (max-width: 480px) {
        .game-selection-modal-content {
            width: 95%;
            max-height: 95vh;
            border-radius: 12px;
        }
        
        .game-selection-header {
            padding: 20px 15px 10px 15px;
        }
        
        .game-selection-image {
            height: 120px;
            margin-bottom: 12px;
        }
        
        .game-selection-actions {
            padding: 10px 15px 20px 15px;
            gap: 10px;
        }
        
        .game-action-btn {
            min-height: 55px;
            padding: 12px 15px;
        }
        
        .btn-icon {
            margin-right: 10px;
        }
    }

    /* 高コントラストモードへの対応 */
    @media (prefers-contrast: high) {
        .game-selection-modal-content {
            background: #ffffff;
            border: 3px solid #000000;
        }
        
        .game-action-btn {
            border: 2px solid rgba(255, 255, 255, 0.5);
        }
        
        .game-selection-close-btn {
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid #666;
        }
    }

    /* 動きを減らす設定への対応 */
    @media (prefers-reduced-motion: reduce) {
        .game-selection-modal-content {
            animation: none;
        }
        
        .game-action-btn:hover {
            transform: none;
        }
        
        .game-action-btn:active {
            transform: none;
        }
    }
    </style>
    <?php
    
    // モーダルオーバーレイを追加（ゲーム表示用）
    echo '<div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;">';
    echo '    <div id="novel-game-modal-content" class="novel-game-modal-content">';
    echo '        <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="' . esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ) . '" title="' . esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ) . '">';
    echo '            <span class="close-icon">×</span>';
    echo '        </button>';
    echo '        <div id="novel-game-container" class="novel-game-container">';
    echo '            <!-- ゲーム内容は動的に読み込まれます -->';
    echo '        </div>';
    echo '    </div>';
    echo '</div>';
    
    // ゲーム選択モーダル
    echo '<div id="game-selection-modal-overlay" class="game-selection-modal-overlay" style="display: none;">';
    echo '    <div id="game-selection-modal-content" class="game-selection-modal-content">';
    echo '        <button id="game-selection-close-btn" class="game-selection-close-btn" aria-label="' . esc_attr__( '閉じる', 'novel-game-plugin' ) . '" title="' . esc_attr__( '閉じる', 'novel-game-plugin' ) . '">';
    echo '            <span class="close-icon">×</span>';
    echo '        </button>';
    echo '        <div class="game-selection-header">';
    echo '            <div id="game-selection-image" class="game-selection-image"></div>';
    echo '            <div class="game-selection-info">';
    echo '                <h2 id="game-selection-title" class="game-selection-title"></h2>';
    echo '                <p id="game-selection-description" class="game-selection-description"></p>';
    echo '                <p id="game-selection-scene-count" class="game-selection-scene-count"></p>';
    echo '            </div>';
    echo '        </div>';
    echo '        <div class="game-selection-actions">';
    echo '            <button id="game-start-new-btn" class="game-action-btn game-start-btn">';
    echo '                <span class="btn-icon">▶</span>';
    echo '                <span class="btn-text">' . esc_html__( 'ゲーム開始', 'novel-game-plugin' ) . '</span>';
    echo '                <small class="btn-subtext">' . esc_html__( '最初から始める', 'novel-game-plugin' ) . '</small>';
    echo '            </button>';
    echo '            <button id="game-resume-btn" class="game-action-btn game-resume-btn" style="display: none;">';
    echo '                <span class="btn-icon">⏯</span>';
    echo '                <span class="btn-text">' . esc_html__( '途中から始める', 'novel-game-plugin' ) . '</span>';
    echo '                <small class="btn-subtext">' . esc_html__( '前回の続きから', 'novel-game-plugin' ) . '</small>';
    echo '            </button>';
    echo '        </div>';
    echo '    </div>';
    echo '</div>';
    
    // ゲーム選択モーダル JavaScript を追加
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // プレイボタンのクリックイベント
        const playButtons = document.querySelectorAll('.noveltool-play-button');
        
        // ゲーム選択モーダル要素
        const gameSelectionOverlay = document.getElementById('game-selection-modal-overlay');
        const gameSelectionTitle = document.getElementById('game-selection-title');
        const gameSelectionDescription = document.getElementById('game-selection-description');
        const gameSelectionSceneCount = document.getElementById('game-selection-scene-count');
        const gameSelectionImage = document.getElementById('game-selection-image');
        const gameStartNewBtn = document.getElementById('game-start-new-btn');
        const gameResumeBtn = document.getElementById('game-resume-btn');
        const gameSelectionCloseBtn = document.getElementById('game-selection-close-btn');
        
        // 現在選択されているゲーム情報
        let currentGameData = null;
        
        /**
         * ゲーム選択モーダルを表示
         */
        function showGameSelectionModal(gameData) {
            currentGameData = gameData;
            
            // ゲーム情報を設定
            gameSelectionTitle.textContent = gameData.title;
            gameSelectionDescription.textContent = gameData.description || '<?php echo esc_js(__("ゲームの説明はありません。", "novel-game-plugin")); ?>';
            gameSelectionSceneCount.textContent = gameData.sceneCount + ' <?php echo esc_js(__("シーン", "novel-game-plugin")); ?>';
            
            // ゲーム画像を設定
            if (gameData.image) {
                gameSelectionImage.innerHTML = '<img src="' + gameData.image + '" alt="' + gameData.title + '" class="game-selection-bg-image">';
            } else {
                gameSelectionImage.innerHTML = '<div class="game-selection-placeholder"><span><?php echo esc_js(__("No Image", "novel-game-plugin")); ?></span></div>';
            }
            
            // 保存された進捗をチェック
            checkGameProgress(gameData.title);
            
            // モーダルを表示
            gameSelectionOverlay.style.display = 'flex';
            gameSelectionOverlay.style.opacity = '0';
            setTimeout(() => {
                gameSelectionOverlay.style.opacity = '1';
            }, 10);
            
            // ボディのスクロールを無効化
            document.body.style.overflow = 'hidden';
        }
        
        /**
         * ゲーム選択モーダルを閉じる
         */
        function closeGameSelectionModal() {
            gameSelectionOverlay.style.opacity = '0';
            setTimeout(() => {
                gameSelectionOverlay.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
            currentGameData = null;
        }
        
        /**
         * ゲームの進捗をチェックして「途中から始める」ボタンの表示を制御
         */
        function checkGameProgress(gameTitle) {
            try {
                // ストレージキーを生成（frontend.jsと同じ方式）
                const hostname = window.location.hostname || 'localhost';
                const pathname = window.location.pathname || '/';
                const pathDir = pathname.substring(0, pathname.lastIndexOf('/') + 1);
                const siteId = hostname + pathDir;
                const encodedTitle = btoa(unescape(encodeURIComponent(gameTitle))).replace(/[^a-zA-Z0-9]/g, '');
                const encodedSiteId = btoa(unescape(encodeURIComponent(siteId))).replace(/[^a-zA-Z0-9]/g, '');
                const storageKey = 'noveltool_progress_' + encodedSiteId + '_' + encodedTitle;
                
                const savedData = localStorage.getItem(storageKey);
                
                if (savedData) {
                    const progressData = JSON.parse(savedData);
                    const currentTime = Date.now();
                    const savedTime = progressData.timestamp || 0;
                    const maxAge = 30 * 24 * 60 * 60 * 1000; // 30日
                    
                    if (currentTime - savedTime <= maxAge) {
                        // 有効な進捗データがある場合は「途中から始める」ボタンを表示
                        gameResumeBtn.style.display = 'block';
                        return;
                    }
                }
            } catch (error) {
                console.warn('進捗チェックに失敗:', error);
            }
            
            // 進捗データがない場合は「途中から始める」ボタンを非表示
            gameResumeBtn.style.display = 'none';
        }
        
        /**
         * ゲームを開始（新規）
         */
        function startNewGame() {
            if (!currentGameData) return;
            
            // 保存された進捗をクリア
            try {
                const hostname = window.location.hostname || 'localhost';
                const pathname = window.location.pathname || '/';
                const pathDir = pathname.substring(0, pathname.lastIndexOf('/') + 1);
                const siteId = hostname + pathDir;
                const encodedTitle = btoa(unescape(encodeURIComponent(currentGameData.title))).replace(/[^a-zA-Z0-9]/g, '');
                const encodedSiteId = btoa(unescape(encodeURIComponent(siteId))).replace(/[^a-zA-Z0-9]/g, '');
                const storageKey = 'noveltool_progress_' + encodedSiteId + '_' + encodedTitle;
                localStorage.removeItem(storageKey);
            } catch (error) {
                console.warn('進捗クリアに失敗:', error);
            }
            
            closeGameSelectionModal();
            
            // ゲームをモーダルで開始
            if (typeof window.novelGameModal !== 'undefined' && window.novelGameModal && typeof window.novelGameModal.open === 'function') {
                window.novelGameModal.open(currentGameData.url);
            } else {
                // フォールバック：ページ遷移
                window.location.href = currentGameData.url;
            }
        }
        
        /**
         * ゲームを再開
         */
        function resumeGame() {
            if (!currentGameData) return;
            
            closeGameSelectionModal();
            
            // ゲームをモーダルで再開
            if (typeof window.novelGameModal !== 'undefined' && window.novelGameModal && typeof window.novelGameModal.open === 'function') {
                window.novelGameModal.open(currentGameData.url);
            } else {
                // フォールバック：ページ遷移
                window.location.href = currentGameData.url;
            }
        }
        
        // プレイボタンのクリックイベント設定
        playButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const gameData = {
                    url: this.getAttribute('data-game-url'),
                    title: this.getAttribute('data-game-title'),
                    description: this.getAttribute('data-game-description'),
                    image: this.getAttribute('data-game-image'),
                    sceneCount: this.getAttribute('data-scene-count')
                };
                
                showGameSelectionModal(gameData);
            });
        });
        
        // ボタンイベント設定
        if (gameStartNewBtn) gameStartNewBtn.addEventListener('click', startNewGame);
        if (gameResumeBtn) gameResumeBtn.addEventListener('click', resumeGame);
        if (gameSelectionCloseBtn) gameSelectionCloseBtn.addEventListener('click', closeGameSelectionModal);
        
        // ESCキーで閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && gameSelectionOverlay && gameSelectionOverlay.style.display === 'flex') {
                closeGameSelectionModal();
            }
        });
        
        // オーバーレイクリックで閉じる（モーダル外をクリック）
        if (gameSelectionOverlay) {
            gameSelectionOverlay.addEventListener('click', function(e) {
                if (e.target === gameSelectionOverlay) {
                    closeGameSelectionModal();
                }
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
}
add_shortcode( 'novel_game_list', 'noveltool_game_list_shortcode' );

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
        'game_id'    => '',
        'limit'      => -1,
        'orderby'    => 'date',
        'order'      => 'ASC',
        'show_title' => 'true',
        'show_date'  => 'true',
        'show_navigation' => 'true',
    ), $atts, 'novel_game_posts' );
    
    // パラメータの処理
    $game_title = sanitize_text_field( $atts['game_title'] );
    $game_id = intval( $atts['game_id'] );
    $limit = intval( $atts['limit'] );
    $orderby = sanitize_text_field( $atts['orderby'] );
    $order = sanitize_text_field( $atts['order'] );
    $show_title = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
    $show_date = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
    $show_navigation = filter_var( $atts['show_navigation'], FILTER_VALIDATE_BOOLEAN );
    
    // ゲームIDが指定されている場合、そのゲームのタイトルを取得
    if ( $game_id && ! $game_title ) {
        $game = noveltool_get_game_by_id( $game_id );
        if ( $game ) {
            $game_title = $game['title'];
        }
    }
    
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
    
    echo '<div class="noveltool-game-posts-list noveltool-shortcode-container">';
    
    if ( $show_title ) {
        echo '<h3 class="noveltool-game-title">' . esc_html( $game_title ) . '</h3>';
    }
    
    if ( $show_navigation ) {
        echo '<div class="noveltool-game-start-navigation">';
        echo '<a href="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $posts[0]->ID ) ) ) . '" class="noveltool-start-button button">';
        echo esc_html__( 'スタート', 'novel-game-plugin' );
        echo '</a>';
        echo '</div>';
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
        echo '<a href="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $post->ID ) ) ) . '">' . esc_html( $post->post_title ) . '</a>';
        echo '</h4>';
        
        if ( $dialogue ) {
            $dialogue_preview = mb_substr( strip_tags( $dialogue ), 0, 100 );
            echo '<p class="noveltool-post-dialogue">' . esc_html( $dialogue_preview ) . '...</p>';
        }
        
        if ( $show_date ) {
            echo '<p class="noveltool-post-date">' . esc_html( get_the_date( 'Y年m月d日', $post->ID ) ) . '</p>';
        }
        
        echo '<a href="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $post->ID ) ) ) . '" class="noveltool-post-link button">';
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
    
    .noveltool-game-start-navigation {
        text-align: center;
        margin: 20px 0;
    }
    
    .noveltool-start-button {
        display: inline-block;
        padding: 15px 30px;
        background: #ff6b6b;
        color: white;
        text-decoration: none;
        border-radius: 25px;
        font-size: 18px;
        font-weight: bold;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        min-height: 50px;
        min-width: 150px;
        box-sizing: border-box;
        text-align: center;
        line-height: 1.2;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    
    .noveltool-start-button:hover {
        background: #ff5252;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
    }
    
    .noveltool-start-button:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    .noveltool-posts-grid,
    .noveltool-games-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }
    
    /* 新しいゲーム一覧グリッド */
    .noveltool-game-list-grid {
        display: grid;
        gap: 20px;
        margin: 20px 0;
    }
    
    .noveltool-game-list-grid.noveltool-columns-1 {
        grid-template-columns: 1fr;
    }
    
    .noveltool-game-list-grid.noveltool-columns-2 {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
    
    .noveltool-game-list-grid.noveltool-columns-3 {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }
    
    .noveltool-game-list-grid.noveltool-columns-4 {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
    
    .noveltool-game-list-grid.noveltool-columns-5 {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
    
    .noveltool-game-list-grid.noveltool-columns-6 {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    @media (max-width: 768px) {
        .noveltool-game-list-grid {
            grid-template-columns: 1fr !important;
        }
    }
    
    .noveltool-game-list-item {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .noveltool-game-list-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .noveltool-no-games {
        text-align: center;
        padding: 40px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        color: #666;
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
        transition: transform 0.3s ease;
    }
    
    .noveltool-game-list-item .noveltool-game-thumbnail img {
        height: 180px;
    }
    
    .noveltool-game-list-item:hover .noveltool-game-thumbnail img {
        transform: scale(1.05);
    }
    
    .noveltool-post-content,
    .noveltool-game-info,
    .noveltool-game-content {
        padding: 15px;
    }
    .noveltool-post-title,
    .noveltool-game-title {
        margin: 0 0 10px 0;
        font-size: 1.2em;
    }
    .noveltool-post-title a,
    .noveltool-game-title a {
        text-decoration: none;
        color: inherit;
        transition: color 0.3s ease;
    }
    .noveltool-post-title a:hover,
    .noveltool-game-title a:hover {
        color: #0073aa;
    }
    .noveltool-post-dialogue {
        color: #666;
        font-size: 0.9em;
        margin-bottom: 10px;
    }
    
    .noveltool-game-description {
        color: #666;
        font-size: 0.9em;
        margin-bottom: 10px;
        line-height: 1.4;
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
        transition: background 0.3s ease;
    }
    .noveltool-post-link:hover,
    .noveltool-game-link:hover {
        background: #005a87;
        color: white;
    }
    
    .noveltool-play-button {
        display: inline-block;
        padding: 10px 20px;
        background: #ff6b6b;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .noveltool-play-button:hover {
        background: #ff5252;
        color: white;
        transform: translateY(-1px);
    }
    
    .noveltool-game-actions {
        text-align: center;
        margin-top: 10px;
    }
    </style>
    <?php
}
add_action( 'wp_head', 'noveltool_shortcode_styles' );
?>
