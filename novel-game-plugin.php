<?php
/**
 * Plugin Name: Novel Game Plugin
 * Plugin URI: https://github.com/shokun0803/novel-game-plugin
 * Description: WordPressでノベルゲームを作成できるプラグイン。
 * Version: 1.1.2
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
    // キャッシュ更新のためバージョンを更新
    define( 'NOVEL_GAME_PLUGIN_VERSION', '1.1.2' );
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
require_once NOVEL_GAME_PLUGIN_PATH . 'includes/blocks.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'includes/revisions.php';
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
 * プラグイン有効化時の処理
 * 
 * カスタム投稿タイプのリライトルールを登録するため、
 * flush_rewrite_rules()を実行してパーマリンク構造を更新する
 *
 * @since 1.1.0
 */
function noveltool_activate_plugin() {
    // カスタム投稿タイプを登録
    noveltool_register_post_type();
    
    // リライトルールを再生成
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'noveltool_activate_plugin' );

/**
 * プラグイン無効化時の処理
 * 
 * カスタム投稿タイプのリライトルールをクリーンアップするため、
 * flush_rewrite_rules()を実行してパーマリンク構造をクリーンアップする
 *
 * @since 1.1.0
 */
function noveltool_deactivate_plugin() {
    // リライトルールを再生成（カスタム投稿タイプのルールを削除）
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'noveltool_deactivate_plugin' );

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
    
    // ゲーム削除時にフラグマスタも削除
    delete_option( 'noveltool_game_flags_' . $game_id );
    
    return update_option( 'noveltool_games', $filtered_games );
}

/**
 * ゲームのフラグマスタを取得する関数
 *
 * @param string $game_title ゲームタイトル
 * @return array フラグマスタ配列
 * @since 1.2.0
 */
function noveltool_get_game_flag_master( $game_title ) {
    if ( ! $game_title ) {
        return array();
    }
    
    // ゲームIDを取得
    $game_id = noveltool_get_game_id_by_title( $game_title );
    if ( ! $game_id ) {
        return array();
    }
    
    $flag_master = get_option( 'noveltool_game_flags_' . $game_id, array() );
    
    // データの整合性チェック
    if ( ! is_array( $flag_master ) ) {
        return array();
    }
    
    return $flag_master;
}

/**
 * ゲームのフラグマスタを保存する関数
 *
 * @param string $game_title ゲームタイトル
 * @param array $flag_master フラグマスタ配列
 * @return bool 保存成功の場合true
 * @since 1.2.0
 */
function noveltool_save_game_flag_master( $game_title, $flag_master ) {
    if ( ! $game_title || ! is_array( $flag_master ) ) {
        return false;
    }
    
    // ゲームIDを取得
    $game_id = noveltool_get_game_id_by_title( $game_title );
    if ( ! $game_id ) {
        return false;
    }
    
    // フラグマスタデータの検証とサニタイズ
    $sanitized_flags = array();
    foreach ( $flag_master as $flag ) {
        if ( isset( $flag['id'], $flag['name'] ) ) {
            $sanitized_flags[] = array(
                'id'          => intval( $flag['id'] ),
                'name'        => sanitize_text_field( $flag['name'] ),
                'description' => isset( $flag['description'] ) ? sanitize_text_field( $flag['description'] ) : '',
            );
        }
    }
    
    return update_option( 'noveltool_game_flags_' . $game_id, $sanitized_flags );
}

/**
 * ゲームタイトルからゲームIDを取得する関数
 *
 * @param string $game_title ゲームタイトル
 * @return int|null ゲームID
 * @since 1.2.0
 */
function noveltool_get_game_id_by_title( $game_title ) {
    if ( ! $game_title ) {
        return null;
    }
    
    $games = noveltool_get_all_games();
    foreach ( $games as $game ) {
        if ( $game['title'] === $game_title ) {
            return $game['id'];
        }
    }
    
    return null;
}

/**
 * ゲームにフラグを追加する関数
 *
 * @param string $game_title ゲームタイトル
 * @param string $flag_name フラグ名
 * @param string $flag_description フラグの説明
 * @return bool 追加成功の場合true
 * @since 1.2.0
 */
function noveltool_add_game_flag( $game_title, $flag_name, $flag_description = '' ) {
    if ( ! $game_title || ! $flag_name ) {
        return false;
    }
    
    $flag_master = noveltool_get_game_flag_master( $game_title );
    
    // 既存のフラグ名が重複していないかチェック
    foreach ( $flag_master as $flag ) {
        if ( $flag['name'] === $flag_name ) {
            return false; // 重複エラー
        }
    }
    
    // 新しいIDを生成（最大ID + 1）
    $max_id = -1;
    foreach ( $flag_master as $flag ) {
        $max_id = max( $max_id, $flag['id'] );
    }
    
    $new_flag = array(
        'id'          => $max_id + 1,
        'name'        => sanitize_text_field( $flag_name ),
        'description' => sanitize_text_field( $flag_description ),
    );
    
    $flag_master[] = $new_flag;
    
    return noveltool_save_game_flag_master( $game_title, $flag_master );
}

/**
 * ゲームからフラグを削除する関数
 *
 * @param string $game_title ゲームタイトル
 * @param string $flag_name フラグ名
 * @return bool 削除成功の場合true
 * @since 1.2.0
 */
function noveltool_remove_game_flag( $game_title, $flag_name ) {
    if ( ! $game_title || ! $flag_name ) {
        return false;
    }
    
    $flag_master = noveltool_get_game_flag_master( $game_title );
    
    $new_flag_master = array();
    $found = false;
    
    foreach ( $flag_master as $flag ) {
        if ( $flag['name'] !== $flag_name ) {
            $new_flag_master[] = $flag;
        } else {
            $found = true;
        }
    }
    
    if ( ! $found ) {
        return false; // フラグが見つからない
    }
    
    return noveltool_save_game_flag_master( $game_title, $new_flag_master );
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
    
    // エンディング設定の取得
    $is_ending = get_post_meta( $post->ID, '_is_ending', true );
    $ending_text = get_post_meta( $post->ID, '_ending_text', true );
    
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
    
    // セリフフラグ条件データの取得
    $dialogue_flag_conditions = get_post_meta( $post->ID, '_dialogue_flag_conditions', true );
    if ( ! is_array( $dialogue_flag_conditions ) ) {
        $dialogue_flag_conditions = array();
    }
    
    // セリフと背景と話者を組み合わせた配列を作成
    $dialogue_data = array();
    foreach ( $dialogue_lines as $index => $line ) {
        $dialogue_item = array(
            'text' => $line,
            'background' => isset( $dialogue_backgrounds_array[ $index ] ) ? $dialogue_backgrounds_array[ $index ] : '',
            'speaker' => isset( $dialogue_speakers_array[ $index ] ) ? $dialogue_speakers_array[ $index ] : ''
        );
        
        // フラグ条件データがある場合は追加
        if ( isset( $dialogue_flag_conditions[ $index ] ) && is_array( $dialogue_flag_conditions[ $index ] ) ) {
            $flag_condition = $dialogue_flag_conditions[ $index ];
            
            $dialogue_item['flagConditions'] = isset( $flag_condition['conditions'] ) ? $flag_condition['conditions'] : array();
            $dialogue_item['flagConditionLogic'] = isset( $flag_condition['logic'] ) ? $flag_condition['logic'] : 'AND';
            $dialogue_item['displayMode'] = isset( $flag_condition['displayMode'] ) ? $flag_condition['displayMode'] : 'normal';
        } else {
            // デフォルト値
            $dialogue_item['flagConditions'] = array();
            $dialogue_item['flagConditionLogic'] = 'AND';
            $dialogue_item['displayMode'] = 'normal';
        }
        
        $dialogue_data[] = $dialogue_item;
    }

    // 選択肢の処理（JSON形式とレガシー形式の両方に対応）
    $choices = array();
    if ( $choices_raw ) {
        // JSON形式を試行
        $json_choices = json_decode( $choices_raw, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_choices ) ) {
            // JSON形式の場合
            foreach ( $json_choices as $choice_data ) {
                if ( isset( $choice_data['text'], $choice_data['next'] ) ) {
                    $post_id = intval( $choice_data['next'] );
                    $permalink = get_permalink( $post_id );
                    
                    if ( $permalink ) {
                        $choice_item = array(
                            'text' => sanitize_text_field( $choice_data['text'] ),
                            'nextScene' => $permalink,
                        );
                        
                        // フラグ条件がある場合はサニタイズして追加（name/state のみ許可）
                        if ( isset( $choice_data['flagConditions'] ) && is_array( $choice_data['flagConditions'] ) ) {
                            $sanitized_conditions = array();
                            foreach ( $choice_data['flagConditions'] as $condition ) {
                                if ( is_array( $condition ) && isset( $condition['name'] ) ) {
                                    $name = trim( sanitize_text_field( $condition['name'] ) );
                                    if ( $name !== '' ) {
                                        $sanitized_conditions[] = array(
                                            'name'  => $name,
                                            'state' => isset( $condition['state'] ) ? (bool) $condition['state'] : true,
                                        );
                                    }
                                }
                            }
                            if ( ! empty( $sanitized_conditions ) ) {
                                $choice_item['flagConditions'] = $sanitized_conditions;
                            } else {
                                $choice_item['flagConditions'] = array();
                            }
                        }
                        
                        // フラグ条件ロジックがある場合は追加
                        if ( isset( $choice_data['flagConditionLogic'] ) ) {
                            $choice_item['flagConditionLogic'] = sanitize_text_field( $choice_data['flagConditionLogic'] );
                        }
                        
                        // 設定フラグがある場合はサニタイズして追加（新旧両形式対応）
                        if ( isset( $choice_data['setFlags'] ) && is_array( $choice_data['setFlags'] ) ) {
                            $sanitized_set_flags = array();
                            foreach ( $choice_data['setFlags'] as $flag_data ) {
                                if ( is_string( $flag_data ) ) {
                                    // 旧形式: "flagName"（常にON）
                                    $name = trim( sanitize_text_field( $flag_data ) );
                                    if ( $name !== '' ) {
                                        $sanitized_set_flags[] = $name; // 旧形式は文字列のまま（フロントでtrue扱い）
                                    }
                                } elseif ( is_array( $flag_data ) && isset( $flag_data['name'] ) ) {
                                    // 新形式: { name: string, state: bool }
                                    $name = trim( sanitize_text_field( $flag_data['name'] ) );
                                    if ( $name !== '' ) {
                                        $sanitized_set_flags[] = array(
                                            'name'  => $name,
                                            'state' => isset( $flag_data['state'] ) ? (bool) $flag_data['state'] : true,
                                        );
                                    }
                                }
                            }
                            $choice_item['setFlags'] = $sanitized_set_flags;
                        }
                        
                        $choices[] = $choice_item;
                    }
                }
            }
        } else {
            // レガシー形式（"テキスト | 投稿ID" の行形式）
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
            
            <!-- タイトル画面 -->
            <div id="novel-title-screen" class="novel-title-screen" style="display: none;">
                <div class="novel-title-content">
                    <h2 id="novel-title-main" class="novel-title-main"></h2>
                    <p id="novel-title-subtitle" class="novel-title-subtitle"></p>
                    <p id="novel-title-description" class="novel-title-description"></p>
                    <div class="novel-title-buttons">
                        <button id="novel-title-start-new" class="novel-title-btn novel-title-start-btn">
                            <?php echo esc_html__( '最初から開始', 'novel-game-plugin' ); ?>
                        </button>
                        <button id="novel-title-continue" class="novel-title-btn novel-title-continue-btn" style="display: none;">
                            <?php echo esc_html__( '続きから始める', 'novel-game-plugin' ); ?>
                        </button>
                    </div>
                </div>
            </div>
            
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
                    <?php echo wp_json_encode( $dialogue_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
                </script>
                
                <script id="novel-base-background" type="application/json">
                    <?php echo wp_json_encode( $background, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
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
                    ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
                </script>

                <script id="novel-choices-data" type="application/json">
                    <?php echo wp_json_encode( $choices, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
                </script>
                
                <script id="novel-ending-data" type="application/json">
                    <?php echo wp_json_encode( (bool) $is_ending, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
                </script>

                <script id="novel-ending-text" type="application/json">
                    <?php echo wp_json_encode( $ending_text ? $ending_text : 'おわり', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>
                </script>

                <script id="novel-scene-arrival-flags" type="application/json">
                    <?php 
                    // シーン到達時フラグの取得
                    $scene_arrival_flags = get_post_meta( $post->ID, '_scene_arrival_flags', true );
                    if ( ! is_array( $scene_arrival_flags ) ) {
                        $scene_arrival_flags = array();
                    }
                    
                    // フラグ名をキーとしたフラグ設定オブジェクトに変換
                    $scene_flags_object = array();
                    foreach ( $scene_arrival_flags as $flag_name ) {
                        $scene_flags_object[ $flag_name ] = true; // フラグをONに設定
                    }
                    
                    echo wp_json_encode( $scene_flags_object, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); 
                    ?>
                </script>

                <script id="novel-game-over-text" type="application/json">
                    <?php 
                    // Game Over テキストをゲーム設定から取得
                    $game_over_text = 'Game Over'; // デフォルト
                    if ( $game_title ) {
                        $game_data = noveltool_get_game_by_title( $game_title );
                        if ( $game_data && isset( $game_data['game_over_text'] ) && ! empty( $game_data['game_over_text'] ) ) {
                            $game_over_text = $game_data['game_over_text'];
                        }
                    }
                    echo wp_json_encode( $game_over_text, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); 
                    ?>
                </script>

                <script id="novel-flag-master" type="application/json">
                    <?php 
                    // フラグマスタデータをフロントエンドに渡す
                    $flag_master_data = array();
                    if ( $game_title ) {
                        $flag_master_data = noveltool_get_game_flag_master( $game_title );
                    }
                    echo wp_json_encode( $flag_master_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); 
                    ?>
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
    
    // 単一投稿ページのテンプレート（必要に応じて）
    if ( is_singular( 'novel_game' ) ) {
        $custom_template = NOVEL_GAME_PLUGIN_PATH . 'templates/single-novel_game.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    
    return $template;
}
add_filter( 'template_include', 'noveltool_load_custom_templates' );

/**
 * 個別シーンURL直接アクセス時のリダイレクト処理
 *
 * @since 1.1.1
 */
function noveltool_redirect_single_scene_direct_access() {
    // 個別シーンページかどうかをチェック
    if ( ! is_singular( 'novel_game' ) ) {
        return;
    }
    
    // shortcode=1パラメータが付いている場合はモーダル表示用なのでリダイレクトしない
    if ( isset( $_GET['shortcode'] ) && $_GET['shortcode'] === '1' ) {
        return;
    }
    
    // Ajax リクエストの場合はリダイレクトしない
    if ( wp_doing_ajax() ) {
        return;
    }
    
    // 管理画面からのアクセスの場合はリダイレクトしない
    if ( is_admin() ) {
        return;
    }
    
    // REST API リクエストの場合はリダイレクトしない
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return;
    }
    
    // プレビュー表示の場合はリダイレクトしない
    if ( is_preview() ) {
        return;
    }
    
    // 404エラーページの場合はリダイレクトしない
    if ( is_404() ) {
        return;
    }
    
    // サイトトップページにリダイレクト
    wp_redirect( home_url( '/' ) );
    exit;
}
add_action( 'template_redirect', 'noveltool_redirect_single_scene_direct_access' );

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
    
    echo '<div class="noveltool-game-list noveltool-shortcode-container">';
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
        // data-game-description属性用に常に取得
        $game_description = '';
        $game = noveltool_get_game_by_title( $game_title );
        if ( $game && ! empty( $game['description'] ) ) {
            $game_description = $game['description'];
        }
        
        // ゲーム専用のタイトル画像を取得
        $game_title_image = '';
        $all_games = noveltool_get_all_games();
        if ( ! empty( $all_games ) ) {
            foreach ( $all_games as $game_data ) {
                if ( $game_data['title'] === $game_title ) {
                    $game_title_image = isset( $game_data['title_image'] ) ? $game_data['title_image'] : '';
                    break;
                }
            }
        }
        
        // 表示用の画像を決定：タイトル画像を優先、なければ背景画像
        $display_image = ! empty( $game_title_image ) ? $game_title_image : $background;
        
        echo '<div class="noveltool-game-list-item noveltool-game-item">';
        
        if ( $display_image ) {
            echo '<div class="noveltool-game-thumbnail">';
            echo '<a href="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $first_post->ID ) ) ) . '">';
            echo '<img src="' . esc_url( $display_image ) . '" alt="' . esc_attr( $game_title ) . '" />';
            echo '</a>';
            echo '</div>';
        }
        
        echo '<div class="noveltool-game-content">';
        echo '<h3 class="noveltool-game-title">';
        echo '<a href="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $first_post->ID ) ) ) . '">' . esc_html( $game_title ) . '</a>';
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
        
        // ゲーム専用のタイトル画像を取得
        $game_title_image = '';
        $all_games = noveltool_get_all_games();
        if ( ! empty( $all_games ) ) {
            foreach ( $all_games as $game_data ) {
                if ( $game_data['title'] === $game_title ) {
                    $game_title_image = isset( $game_data['title_image'] ) ? $game_data['title_image'] : '';
                    break;
                }
            }
        }
        
        echo '<div class="noveltool-game-actions">';
        echo '<button class="noveltool-play-button" ' .
             'data-game-url="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $first_post->ID ) ) ) . '" ' .
             'data-game-title="' . esc_attr( $game_title ) . '" ' .
             'data-game-description="' . esc_attr( $game_description ) . '" ' .
             'data-game-image="' . esc_attr( $game_title_image ) . '" ' .
             'data-game-subtitle="">';
        echo esc_html__( 'プレイ開始', 'novel-game-plugin' );
        echo '</button>';
        echo '</div>';
        
        echo '</div>'; // .noveltool-game-content
        echo '</div>'; // .noveltool-game-list-item
    }
    
    echo '</div>'; // .noveltool-game-list-grid
    echo '</div>'; // .noveltool-game-list
    
    // モーダルオーバーレイを追加（ゲーム表示用・タイトル画面統合版）
    echo '<div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;">';
    echo '    <div id="novel-game-modal-content" class="novel-game-modal-content">';
    echo '        <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="' . esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ) . '" title="' . esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ) . '">';
    echo '            <span class="close-icon">×</span>';
    echo '        </button>';
    echo '        <!-- タイトル画面 -->';
    echo '        <div id="novel-title-screen" class="novel-title-screen" style="display: none;">';
    echo '            <div class="novel-title-content">';
    echo '                <h2 id="novel-title-main" class="novel-title-main"></h2>';
    echo '                <p id="novel-title-subtitle" class="novel-title-subtitle"></p>';
    echo '                <p id="novel-title-description" class="novel-title-description"></p>';
    echo '                <div class="novel-title-buttons">';
    echo '                    <button id="novel-title-start-new" class="novel-title-btn novel-title-start-btn">';
    echo                          esc_html__( '最初から開始', 'novel-game-plugin' );
    echo '                    </button>';
    echo '                    <button id="novel-title-continue" class="novel-title-btn novel-title-continue-btn" style="display: none;">';
    echo                          esc_html__( '続きから始める', 'novel-game-plugin' );
    echo '                    </button>';
    echo '                </div>';
    echo '            </div>';
    echo '        </div>';
    echo '        <div id="novel-game-container" class="novel-game-container">';
    echo '            <!-- ゲーム内容は動的に読み込まれます -->';
    echo '        </div>';
    echo '    </div>';
    echo '</div>';
    
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
    
    // 個別ゲーム紹介画面の表示
    $first_post = $posts[0];
    $background = get_post_meta( $first_post->ID, '_background_image', true );
    
    // ゲーム説明とタイトル画像を取得
    $game_description = '';
    $game_title_image = '';
    $game = noveltool_get_game_by_title( $game_title );
    if ( $game ) {
        $game_description = ! empty( $game['description'] ) ? $game['description'] : '';
        $game_title_image = ! empty( $game['title_image'] ) ? $game['title_image'] : '';
    }
    
    // 表示用の画像を決定：タイトル画像を優先、なければ最初のシーンの背景画像
    $display_image = ! empty( $game_title_image ) ? $game_title_image : $background;
    
    // 出力の開始
    ob_start();
    
    echo '<div class="noveltool-single-game-display noveltool-shortcode-container">';
    
    // 背景画像とタイトルのオーバーレイ表示
    if ( $display_image ) {
        echo '<div class="noveltool-game-hero" style="background-image: url(\'' . esc_url( $display_image ) . '\');">';
        echo '<div class="noveltool-game-hero-overlay">';
        if ( $show_title ) {
            echo '<h1 class="noveltool-game-hero-title">' . esc_html( $game_title ) . '</h1>';
        }
        echo '</div>'; // .noveltool-game-hero-overlay
        echo '</div>'; // .noveltool-game-hero
    } else {
        // 画像がない場合は通常のタイトル表示
        if ( $show_title ) {
            echo '<h1 class="noveltool-game-title-fallback">' . esc_html( $game_title ) . '</h1>';
        }
    }
    
    // 概要テキストの表示
    if ( $game_description ) {
        echo '<div class="noveltool-game-description-section">';
        echo '<p class="noveltool-game-description">' . esc_html( $game_description ) . '</p>';
        echo '</div>';
    }
    
    // スタートボタンの配置
    echo '<div class="noveltool-game-start-section">';
    echo '<button class="noveltool-single-game-start-button" ';
    echo 'data-game-url="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $first_post->ID ) ) ) . '" ';
    echo 'data-game-title="' . esc_attr( $game_title ) . '" ';
    echo 'data-game-description="' . esc_attr( $game_description ) . '" ';
    echo 'data-game-image="' . esc_attr( $game_title_image ) . '" ';
    echo 'data-game-subtitle="">';
    echo esc_html__( 'ゲームを開始', 'novel-game-plugin' );
    echo '</button>';
    echo '</div>';
    
    echo '</div>'; // .noveltool-single-game-display
    
    // モーダルオーバーレイを追加（個別ゲーム表示用）
    echo '<div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;">';
    echo '    <div id="novel-game-modal-content" class="novel-game-modal-content">';
    echo '        <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="' . esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ) . '" title="' . esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ) . '">';
    echo '            <span class="close-icon">×</span>';
    echo '        </button>';
    echo '        <!-- タイトル画面 -->';
    echo '        <div id="novel-title-screen" class="novel-title-screen" style="display: none;">';
    echo '            <div class="novel-title-content">';
    echo '                <h2 id="novel-title-main" class="novel-title-main"></h2>';
    echo '                <p id="novel-title-subtitle" class="novel-title-subtitle"></p>';
    echo '                <p id="novel-title-description" class="novel-title-description"></p>';
    echo '                <div class="novel-title-buttons">';
    echo '                    <button id="novel-title-start-new" class="novel-title-btn novel-title-start-btn">';
    echo                          esc_html__( '最初から開始', 'novel-game-plugin' );
    echo '                    </button>';
    echo '                    <button id="novel-title-continue" class="novel-title-btn novel-title-continue-btn" style="display: none;">';
    echo                          esc_html__( '続きから始める', 'novel-game-plugin' );
    echo '                    </button>';
    echo '                </div>';
    echo '            </div>';
    echo '        </div>';
    echo '        <div id="novel-game-container" class="novel-game-container">';
    echo '            <!-- ゲーム内容は動的に読み込まれます -->';
    echo '        </div>';
    echo '    </div>';
    echo '</div>';
    
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
    
    echo '<div class="noveltool-all-games-list noveltool-shortcode-container">';
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
        
        // ゲーム概要・タイトル用画像を取得（改善版）
        $game_description = '';
        $game_title_image = '';
        
        // 1. 新しいオプション形式から取得を試行
        $all_games = noveltool_get_all_games();
        if ( ! empty( $all_games ) ) {
            foreach ( $all_games as $game_data ) {
                if ( $game_data['title'] === $game_title ) {
                    $game_description = isset( $game_data['description'] ) ? $game_data['description'] : '';
                    $game_title_image = isset( $game_data['title_image'] ) ? $game_data['title_image'] : '';
                    break;
                }
            }
        }
        
        // 2. データが見つからない場合は後方互換性のため従来の単一ゲーム設定から取得
        if ( empty( $game_description ) && empty( $game_title_image ) ) {
            $legacy_title = get_option( 'noveltool_game_title', '' );
            if ( $legacy_title === $game_title ) {
                $game_description = get_option( 'noveltool_game_description', '' );
                $game_title_image = get_option( 'noveltool_game_title_image', '' );
            }
        }
        
        // 表示用の画像を決定：タイトル画像を優先、なければ背景画像
        $display_image = ! empty( $game_title_image ) ? $game_title_image : $background;
        
        echo '<div class="noveltool-game-item" ' .
             'data-game-url="' . esc_attr( add_query_arg( 'shortcode', '1', get_permalink( $first_post->ID ) ) ) . '" ' .
             'data-game-title="' . esc_attr( $game_title ) . '" ' .
             'data-game-description="' . esc_attr( $game_description ) . '" ' .
             'data-game-image="' . esc_attr( $game_title_image ) . '" ' .
             'data-game-subtitle="">';
        
        if ( $display_image ) {
            echo '<div class="noveltool-game-thumbnail">';
            echo '<img src="' . esc_url( $display_image ) . '" alt="' . esc_attr( $game_title ) . '" />';
            echo '</div>';
        }
        
        echo '<div class="noveltool-game-info">';
        echo '<h4 class="noveltool-game-title">' . esc_html( $game_title ) . '</h4>';
        if ( $game_description ) {
            echo '<p class="noveltool-game-description">' . esc_html( wp_trim_words( $game_description, 15, '...' ) ) . '</p>';
        }
        echo '<p class="noveltool-game-count">' . sprintf( esc_html__( '%d シーン', 'novel-game-plugin' ), $post_count ) . '</p>';
        echo '<a href="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $first_post->ID ) ) ) . '" class="noveltool-game-link button">';
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
    
    /* 新しい個別ゲーム表示のスタイル */
    .noveltool-single-game-display {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .noveltool-game-hero {
        position: relative;
        width: 100%;
        height: 400px;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        margin-bottom: 30px;
    }
    
    .noveltool-game-hero-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
        padding: 40px 30px 30px;
        color: white;
    }
    
    .noveltool-game-hero-title {
        margin: 0;
        font-size: 2.5em;
        font-weight: bold;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
        line-height: 1.2;
    }
    
    .noveltool-game-title-fallback {
        text-align: center;
        font-size: 2.2em;
        margin: 0 0 30px 0;
        color: #333;
    }
    
    .noveltool-game-description-section {
        margin-bottom: 40px;
        padding: 0 20px;
    }
    
    .noveltool-game-description {
        font-size: 1.1em;
        line-height: 1.6;
        color: #555;
        margin: 0;
        text-align: center;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .noveltool-game-start-section {
        text-align: center;
        margin-top: 40px;
    }
    
    .noveltool-single-game-start-button {
        display: inline-block;
        padding: 18px 40px;
        background: linear-gradient(135deg, #ff6b6b, #ff5252);
        color: white;
        text-decoration: none;
        border-radius: 50px;
        font-size: 1.2em;
        font-weight: bold;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .noveltool-single-game-start-button:hover {
        background: linear-gradient(135deg, #ff5252, #e53935);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(255, 107, 107, 0.6);
    }
    
    .noveltool-single-game-start-button:active {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
    }
    
    /* レスポンシブ対応 */
    @media (max-width: 768px) {
        .noveltool-single-game-display {
            padding: 15px;
        }
        
        .noveltool-game-hero {
            height: 300px;
            margin-bottom: 20px;
        }
        
        .noveltool-game-hero-title {
            font-size: 1.8em;
        }
        
        .noveltool-game-title-fallback {
            font-size: 1.8em;
            margin-bottom: 20px;
        }
        
        .noveltool-game-description-section {
            margin-bottom: 30px;
            padding: 0 10px;
        }
        
        .noveltool-game-description {
            font-size: 1em;
        }
        
        .noveltool-single-game-start-button {
            padding: 15px 30px;
            font-size: 1.1em;
        }
    }
    
    @media (max-width: 480px) {
        .noveltool-game-hero {
            height: 250px;
            border-radius: 8px;
        }
        
        .noveltool-game-hero-title {
            font-size: 1.5em;
        }
        
        .noveltool-game-hero-overlay {
            padding: 30px 20px 20px;
        }
        
        .noveltool-single-game-start-button {
            padding: 12px 25px;
            font-size: 1em;
            border-radius: 30px;
        }
    }
    </style>
    <?php
}
add_action( 'wp_head', 'noveltool_shortcode_styles' );
?>
