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
        echo '<button class="noveltool-play-button" data-game-url="' . esc_url( get_permalink( $first_post->ID ) ) . '" data-game-title="' . esc_attr( $game_title ) . '">';
        echo esc_html__( 'プレイ開始', 'novel-game-plugin' );
        echo '</button>';
        echo '</div>';
        
        echo '</div>'; // .noveltool-game-content
        echo '</div>'; // .noveltool-game-list-item
    }
    
    echo '</div>'; // .noveltool-game-list-grid
    
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
    
    // JavaScript event handling を追加
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // プレイボタンのクリックイベント
        const playButtons = document.querySelectorAll('.noveltool-play-button');
        
        // モーダル関数が利用可能になるまで待機
        function waitForModalAndSetupEvents() {
            if (typeof window.novelGameModal !== 'undefined' && window.novelGameModal && window.novelGameModal.isAvailable && window.novelGameModal.isAvailable()) {
                console.log('Modal functions found and available, setting up shortcode events');
                setupPlayButtonEvents();
            } else if (typeof window.novelGameModal !== 'undefined' && window.novelGameModal) {
                console.log('Modal functions found but not available, setting up shortcode events anyway');
                setupPlayButtonEvents();
            } else {
                console.log('Modal functions not yet available for shortcode, waiting...');
                setTimeout(waitForModalAndSetupEvents, 100);
            }
        }
        
        function setupPlayButtonEvents() {
            playButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault(); // デフォルト動作を防ぐ
                    e.stopPropagation();
                    
                    const gameUrl = this.getAttribute('data-game-url');
                    const gameTitle = this.getAttribute('data-game-title');
                    console.log('Play button clicked in shortcode, URL:', gameUrl, 'Title:', gameTitle);
                    
                    if (gameUrl && window.novelGameModal && typeof window.novelGameModal.open === 'function') {
                        console.log('Calling modal open from shortcode');
                        // モーダルでゲームを開始（ページ遷移せずに）
                        window.novelGameModal.open(gameUrl);
                    } else if (gameUrl) {
                        console.log('Modal not available, using page navigation from shortcode');
                        // フォールバック：ページ遷移
                        window.location.href = gameUrl;
                    } else {
                        console.error('No game URL found on button');
                    }
                });
            });
        }
        
        // モーダル関数の準備を待機
        waitForModalAndSetupEvents();
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
    </style>
    <?php
}
add_action( 'wp_head', 'noveltool_shortcode_styles' );
?>
