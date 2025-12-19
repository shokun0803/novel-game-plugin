<?php
/**
 * Plugin Name: Novel Game Plugin
 * Plugin URI: https://github.com/shokun0803/novel-game-plugin
 * Description: WordPressでノベルゲームを作成できるプラグイン。
 * Version: 1.3.0
 * Author: shokun0803
 * Author URI: https://profiles.wordpress.org/shokun0803/
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
    define( 'NOVEL_GAME_PLUGIN_VERSION', '1.3.0' );
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
require_once NOVEL_GAME_PLUGIN_PATH . 'includes/sample-data.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'includes/sample-images-downloader.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/meta-boxes.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/dashboard.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/my-games.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/game-manager.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/new-game.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/game-settings.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/plugin-settings.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/ad-management.php';
require_once NOVEL_GAME_PLUGIN_PATH . 'admin/export-import.php';

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
    
    // サンプルデータ用言語ファイルの読み込み
    load_plugin_textdomain(
        'novel-game-plugin-sample',
        false,
        dirname( NOVEL_GAME_PLUGIN_BASENAME ) . '/languages'
    );
}
add_action( 'plugins_loaded', 'noveltool_init' );

// サンプルゲームインストールは init フックで実行（翻訳が確実に利用可能になった後）
add_action( 'init', 'noveltool_check_and_install_sample_games', 20 );

/**
 * プラグイン有効化時の処理
 * 
 * カスタム投稿タイプのリライトルールを登録するため、
 * flush_rewrite_rules()を実行してパーマリンク構造を更新する
 * サンプルゲーム追加は init フックで実行されるため、ここではフラグのみ設定
 * 
 * ⚠️ 重要: 既存インストール済みのゲームは自動で削除/上書きされません
 *
 * @since 1.1.0
 */
function noveltool_activate_plugin() {
    // カスタム投稿タイプを登録
    noveltool_register_post_type();
    
    // リライトルールを再生成
    flush_rewrite_rules();
    
    // サンプルゲーム追加フラグを設定（実際のインストールは init フックで実行）
    // これにより、翻訳ファイルが確実にロードされた後にサンプルデータが追加される
    if ( ! get_option( 'noveltool_sample_games_installed' ) ) {
        update_option( 'noveltool_pending_sample_install', true );
    }
}
register_activation_hook( __FILE__, 'noveltool_activate_plugin' );

/**
 * サンプルゲーム追加処理の確認と実行
 * 
 * init フックで実行され、有効化時に設定されたフラグを確認し、
 * 必要であればサンプルゲームをインストールする
 * WordPress 6.7以降では、翻訳ファイルは init アクション以降でのみ完全に利用可能
 *
 * @since 1.3.0
 */
function noveltool_check_and_install_sample_games() {
    // サンプルゲーム追加フラグをチェック
    if ( get_option( 'noveltool_pending_sample_install' ) ) {
        // フラグを削除（一度だけ実行されるように）
        delete_option( 'noveltool_pending_sample_install' );
        
        // Shadow Detectiveゲームをインストール
        $result = noveltool_install_shadow_detective_game();
        
        // インストール完了フラグを設定
        if ( $result ) {
            update_option( 'noveltool_sample_games_installed', true );
        }
    }
}

/**
 * サンプルゲーム（Shadow Detective）インストール用のAJAXハンドラー
 *
 * @since 1.3.0
 */
function noveltool_install_sample_game_ajax() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'novel-game-plugin' ) ) );
    }
    
    // ノンスチェック
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'noveltool_install_sample_game' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed', 'novel-game-plugin' ) ) );
    }
    
    // Shadow Detectiveゲームをインストール
    $result = noveltool_install_shadow_detective_game();
    
    if ( $result ) {
        wp_send_json_success( array( 'message' => __( 'Sample game installed successfully', 'novel-game-plugin' ) ) );
    } else {
        // ⚠️ 重要: 既存インストール済みのゲームは自動で削除/上書きされません
        wp_send_json_error( array( 'message' => __( 'Shadow Detective is already installed. No changes were made.', 'novel-game-plugin' ) ) );
    }
}
add_action( 'wp_ajax_noveltool_install_sample_game', 'noveltool_install_sample_game_ajax' );

/**
 * Shadow Detectiveゲームインストール用のAJAXハンドラー
 *
 * @since 1.3.0
 */
function noveltool_install_shadow_detective_ajax() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'novel-game-plugin' ) ) );
    }
    
    // ノンスチェック
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'noveltool_install_shadow_detective' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed', 'novel-game-plugin' ) ) );
    }
    
    // Shadow Detectiveゲームをインストール
    $result = noveltool_install_shadow_detective_game();
    
    if ( $result ) {
        wp_send_json_success( array( 'message' => __( 'Shadow Detective game installed successfully', 'novel-game-plugin' ) ) );
    } else {
        // ⚠️ 重要: 既存インストール済みのゲームは自動で削除/上書きされません
        wp_send_json_error( array( 'message' => __( 'Shadow Detective is already installed. No changes were made.', 'novel-game-plugin' ) ) );
    }
}
add_action( 'wp_ajax_noveltool_install_shadow_detective', 'noveltool_install_shadow_detective_ajax' );

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
 * 機械識別子（machine_name）でゲームを取得する関数
 *
 * @param string $machine_name 機械識別子（例: 'shadow_detective_v1'）
 * @return array|null ゲームデータ または null
 * @since 1.3.0
 */
function noveltool_get_game_by_machine_name( $machine_name ) {
    if ( ! $machine_name ) {
        return null;
    }

    $games = noveltool_get_all_games();
    foreach ( $games as $game ) {
        if ( isset( $game['machine_name'] ) && $game['machine_name'] === $machine_name ) {
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
        
        // start_scene_id が未設定の場合は null で初期化
        if ( ! isset( $game_data['start_scene_id'] ) ) {
            $game_data['start_scene_id'] = null;
        }
        
        $games[] = $game_data;
    } else {
        // 既存ゲームの更新
        $found = false;
        for ( $i = 0; $i < count( $games ); $i++ ) {
            if ( $games[$i]['id'] == $game_data['id'] ) {
                $game_data['created_at'] = $games[$i]['created_at']; // 作成日時を保持
                $game_data['updated_at'] = current_time( 'timestamp' );
                
                // start_scene_id の保持（明示的に更新されない限り既存値を維持）
                if ( ! array_key_exists( 'start_scene_id', $game_data ) && isset( $games[$i]['start_scene_id'] ) ) {
                    $game_data['start_scene_id'] = $games[$i]['start_scene_id'];
                }
                
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
 * ゲームの開始シーンIDを更新する関数
 *
 * @param string $game_title ゲームタイトル
 * @param int|null $scene_id シーンID（nullの場合はクリア）
 * @return bool 更新成功の場合true
 * @since 1.4.0
 */
function noveltool_update_game_start_scene( $game_title, $scene_id ) {
    $game = noveltool_get_game_by_title( $game_title );
    
    if ( ! $game ) {
        return false;
    }
    
    $game['start_scene_id'] = $scene_id;
    $result = noveltool_save_game( $game );
    
    return (bool) $result;
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
    
    // ゲームタイトルを取得
    $game_title = '';
    foreach ( $games as $game ) {
        if ( $game['id'] == $game_id ) {
            $game_title = $game['title'];
            break;
        }
    }
    
    // 関連するすべてのシーンを削除（公開中・下書き・ごみ箱を含む全て）
    if ( $game_title ) {
        $scenes = noveltool_get_posts_by_game_title( $game_title, array( 'post_status' => 'any' ) );
        foreach ( $scenes as $scene ) {
            wp_delete_post( $scene->ID, true ); // 完全削除（ゴミ箱に入れない）
        }
    }
    
    // ゲームデータを削除
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
    
    // JSONベースのデータが存在する場合に使用
    if ( ! empty( $dialogue_speakers_array ) || ! empty( $dialogue_backgrounds_array ) || ! empty( $dialogue_texts_array ) ) {
        $max_count = max(
            count( (array) $dialogue_speakers_array ),
            count( (array) $dialogue_backgrounds_array ),
            count( (array) $dialogue_texts_array )
        );
        if ( $max_count > 0 ) {
            for ( $i = 0; $i < $max_count; $i++ ) {
                $dialogue_lines[] = isset( $dialogue_texts_array[ $i ] ) ? $dialogue_texts_array[ $i ] : '';
            }
        }
    }
    
    // セリフ話者の処理（既に上で処理済み）
    // $dialogue_speakers_array は既に定義済み
    
    // セリフフラグ条件データの取得
    $dialogue_flag_conditions = get_post_meta( $post->ID, '_dialogue_flag_conditions', true );
    if ( ! is_array( $dialogue_flag_conditions ) ) {
        $dialogue_flag_conditions = array();
    }
    
    // セリフごとのキャラクター設定データの取得
    $dialogue_characters = get_post_meta( $post->ID, '_dialogue_characters', true );
    if ( is_string( $dialogue_characters ) ) {
        $dialogue_characters_array = json_decode( $dialogue_characters, true );
    } elseif ( is_array( $dialogue_characters ) ) {
        $dialogue_characters_array = $dialogue_characters;
    } else {
        $dialogue_characters_array = array();
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
            $dialogue_item['alternativeText'] = isset( $flag_condition['alternativeText'] ) ? $flag_condition['alternativeText'] : '';
        } else {
            // デフォルト値
            $dialogue_item['flagConditions'] = array();
            $dialogue_item['flagConditionLogic'] = 'AND';
            $dialogue_item['displayMode'] = 'normal';
            $dialogue_item['alternativeText'] = '';
        }
        
        // セリフごとのキャラクター設定がある場合は追加
        if ( isset( $dialogue_characters_array[ $index ] ) && is_array( $dialogue_characters_array[ $index ] ) ) {
            $char_setting = $dialogue_characters_array[ $index ];
            $dialogue_item['characters'] = array(
                'left'   => isset( $char_setting['left'] ) ? $char_setting['left'] : '',
                'center' => isset( $char_setting['center'] ) ? $char_setting['center'] : '',
                'right'  => isset( $char_setting['right'] ) ? $char_setting['right'] : '',
            );
        }
        
        $dialogue_data[] = $dialogue_item;
    }

    // 選択肢の処理（JSON形式）
    $choices = array();
    if ( $choices_raw ) {
        // JSON形式を処理
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
                        
                        // 設定フラグがある場合はサニタイズして追加
                        if ( isset( $choice_data['setFlags'] ) && is_array( $choice_data['setFlags'] ) ) {
                            $sanitized_set_flags = array();
                            foreach ( $choice_data['setFlags'] as $flag_data ) {
                                if ( is_array( $flag_data ) && isset( $flag_data['name'] ) ) {
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
            <?php echo esc_html__( 'Start Game', 'novel-game-plugin' ); ?>
        </button>
        <button id="novel-game-clear-progress-btn" class="novel-game-clear-progress-btn" style="display: none;">
            <?php echo esc_html__( 'Clear Progress', 'novel-game-plugin' ); ?>
        </button>
    </div>

    <!-- モーダルオーバーレイ -->
    <div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;">
        <!-- モーダルコンテンツ -->
        <div id="novel-game-modal-content" class="novel-game-modal-content">
            <!-- ゲーム閉じるボタン -->
            <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="<?php echo esc_attr__( 'Close Game', 'novel-game-plugin' ); ?>" title="<?php echo esc_attr__( 'Close Game', 'novel-game-plugin' ); ?>">
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
                            <?php echo esc_html__( 'Start from Beginning', 'novel-game-plugin' ); ?>
                        </button>
                        <button id="novel-title-continue" class="novel-title-btn novel-title-continue-btn" style="display: none;">
                            <?php echo esc_html__( 'Continue from Save', 'novel-game-plugin' ); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- ゲームコンテナ -->
            <div id="novel-game-container" class="novel-game-container" style="background-image: url('<?php echo esc_url( $background ); ?>');">
                <!-- 広告コンテナ（プレイ開始後に表示） -->
                <div id="novel-ad-container" class="novel-ad-container" style="display: none;">
                    <!-- 広告がここに動的に挿入されます -->
                </div>
                
                <!-- 3体キャラクター表示 -->
                <?php if ( $character_left ) : ?>
                    <img id="novel-character-left" class="novel-character novel-character-left" src="<?php echo esc_url( $character_left ); ?>" data-scene-src="<?php echo esc_attr( $character_left ); ?>" alt="<?php echo esc_attr__( 'Left Character', 'novel-game-plugin' ); ?>" />
                <?php endif; ?>
                
                <?php if ( $character_center ) : ?>
                    <img id="novel-character-center" class="novel-character novel-character-center" src="<?php echo esc_url( $character_center ); ?>" data-scene-src="<?php echo esc_attr( $character_center ); ?>" alt="<?php echo esc_attr__( 'Center Character', 'novel-game-plugin' ); ?>" />
                <?php endif; ?>
                
                <?php if ( $character_right ) : ?>
                    <img id="novel-character-right" class="novel-character novel-character-right" src="<?php echo esc_url( $character_right ); ?>" data-scene-src="<?php echo esc_attr( $character_right ); ?>" alt="<?php echo esc_attr__( 'Right Character', 'novel-game-plugin' ); ?>" />
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

                <script id="novel-ad-config" type="application/json">
                    <?php 
                    // 広告設定データをフロントエンドに渡す
                    $ad_config = array(
                        'provider' => 'none',
                        'publisherId' => '',
                    );
                    
                    if ( $game_title ) {
                        // ゲームデータから広告プロバイダーを取得
                        $game_data = noveltool_get_game_by_title( $game_title );
                        if ( $game_data && isset( $game_data['id'] ) ) {
                            $ad_provider = get_post_meta( $game_data['id'], 'noveltool_ad_provider', true );
                            if ( empty( $ad_provider ) ) {
                                $ad_provider = 'none';
                            }
                            $ad_config['provider'] = $ad_provider;
                            
                            // プロバイダーに応じてグローバルIDを取得
                            if ( $ad_provider === 'adsense' ) {
                                $ad_config['publisherId'] = noveltool_get_google_adsense_id();
                            } elseif ( $ad_provider === 'adsterra' ) {
                                $ad_config['publisherId'] = noveltool_get_adsterra_id();
                            }
                        }
                    }
                    
                    echo wp_json_encode( $ad_config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); 
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
    // デバッグフラグの値を決定（WP_DEBUG をデフォルトとする）
    $debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? true : false;

    // 共通デバッグログユーティリティを読み込み
    wp_enqueue_script(
        'novel-game-debug-log',
        NOVEL_GAME_PLUGIN_URL . 'js/debug-log.js',
        array(),
        NOVEL_GAME_PLUGIN_VERSION,
        false // <head> セクションで読み込み（依存スクリプトより先にグローバル関数を定義）
    );

    // デバッグフラグをグローバル変数として設定
    wp_add_inline_script(
        'novel-game-debug-log',
        'window.novelGameDebug = ' . ( $debug_enabled ? 'true' : 'false' ) . ';',
        'before'
    );

    wp_enqueue_script(
        'novel-game-frontend',
        NOVEL_GAME_PLUGIN_URL . 'js/frontend.js',
        array( 'jquery', 'novel-game-debug-log' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );

    // フロントエンド用のi18n文字列をローカライズ
    wp_localize_script(
        'novel-game-frontend',
        'novelGameFront',
        array(
            'debug'   => $debug_enabled,
            'strings' => array(
                'leftCharacter'          => esc_html__( 'Left Character', 'novel-game-plugin' ),
                'centerCharacter'        => esc_html__( 'Center Character', 'novel-game-plugin' ),
                'rightCharacter'         => esc_html__( 'Right Character', 'novel-game-plugin' ),
                'settingsTitle'          => esc_html__( 'Saved play data', 'novel-game-plugin' ),
                'noData'                 => esc_html__( 'No saved data.', 'novel-game-plugin' ),
                'deleteLabel'            => esc_html__( 'Delete', 'novel-game-plugin' ),
                'clearAllLabel'          => esc_html__( 'Clear all data', 'novel-game-plugin' ),
                'closeLabel'             => esc_html__( 'Close', 'novel-game-plugin' ),
                /* translators: %s is the data label name */
                'confirmDeleteMsg'       => esc_html__( 'Are you sure you want to delete "%s"? This action cannot be undone.', 'novel-game-plugin' ),
                'confirmClearAllMsg'     => esc_html__( 'Are you sure you want to delete all saved data for this game? This action cannot be undone.', 'novel-game-plugin' ),
                'localStorageNotSupport' => esc_html__( 'Your browser does not support local storage; saved data cannot be managed.', 'novel-game-plugin' ),
                'deleteErrorMsg'         => esc_html__( 'Failed to delete data.', 'novel-game-plugin' ),
                'clearErrorMsg'          => esc_html__( 'Failed to clear data.', 'novel-game-plugin' ),
                'savedAt'                => esc_html__( 'Saved at:', 'novel-game-plugin' ),
                'size'                   => esc_html__( 'Size:', 'novel-game-plugin' ),
                'unknown'                => esc_html__( 'Unknown', 'novel-game-plugin' ),
                'progressData'           => esc_html__( 'Progress data', 'novel-game-plugin' ),
                'lastChoice'             => esc_html__( 'Last choice', 'novel-game-plugin' ),
                'flagData'               => esc_html__( 'Flag data', 'novel-game-plugin' ),
                'gameData'               => esc_html__( 'Game data', 'novel-game-plugin' ),
                'settingsButtonLabel'    => esc_html__( 'Settings', 'novel-game-plugin' ),
                'settingsButtonTitle'    => esc_html__( 'Manage saved data', 'novel-game-plugin' ),
            )
        )
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
        // autostart=1 パラメータのセキュリティチェック
        // 管理者権限を持つログインユーザーのみ autostart を許可
        if ( isset( $_GET['autostart'] ) && '1' === $_GET['autostart'] ) {
            if ( ! is_user_logged_in() || ! current_user_can( 'edit_post', get_the_ID() ) ) {
                // 未ログインまたは編集権限がない場合はリダイレクト
                $redirect_url = '';
                
                // 可能であればゲームの最初のシーンにリダイレクト
                $first_scene_id = get_post_meta( get_the_ID(), '_first_scene_id', true );
                if ( $first_scene_id && get_post_status( $first_scene_id ) === 'publish' ) {
                    // 最初のシーンに shortcode=1 を付けてリダイレクト
                    $redirect_url = add_query_arg( 'shortcode', '1', get_permalink( $first_scene_id ) );
                } else {
                    // 最初のシーンが取得できない場合はアーカイブページへ
                    $redirect_url = get_post_type_archive_link( 'novel_game' );
                    if ( ! $redirect_url ) {
                        // アーカイブページが無効な場合はトップページへ
                        $redirect_url = home_url( '/' );
                    }
                }
                
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
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
               '<p>' . esc_html__( 'No games have been created yet.', 'novel-game-plugin' ) . '</p>' .
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
            echo '<p class="noveltool-game-count">' . sprintf( esc_html__( '%d Scenes', 'novel-game-plugin' ), $post_count ) . '</p>';
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
        echo esc_html__( 'Start Playing', 'novel-game-plugin' );
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
    echo '        <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="' . esc_attr__( 'Close Game', 'novel-game-plugin' ) . '" title="' . esc_attr__( 'Close Game', 'novel-game-plugin' ) . '">';
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
    echo                          esc_html__( 'Start from Beginning', 'novel-game-plugin' );
    echo '                    </button>';
    echo '                    <button id="novel-title-continue" class="novel-title-btn novel-title-continue-btn" style="display: none;">';
    echo                          esc_html__( 'Continue from Save', 'novel-game-plugin' );
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
        return '<p>' . esc_html__( 'There are no posts for this game.', 'novel-game-plugin' ) . '</p>';
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
    
    // タイトル表示設定を取得（ゲームが存在する場合のみ）
    $show_title_overlay = '1'; // デフォルトはオン
    $title_text_color = '#ffffff'; // デフォルトは白
    
    if ( $game && isset( $game['id'] ) ) {
        $show_title_overlay = get_post_meta( $game['id'], 'noveltool_show_title_overlay', true );
        // デフォルトはオン（既存の動作を維持）
        if ( $show_title_overlay === '' ) {
            $show_title_overlay = '1';
        }
        
        // タイトル文字色を取得
        $title_text_color = get_post_meta( $game['id'], 'noveltool_title_text_color', true );
        if ( empty( $title_text_color ) ) {
            $title_text_color = '#ffffff';
        }
    }
    
    // 表示用の画像を決定：タイトル画像を優先、なければ最初のシーンの背景画像
    $display_image = ! empty( $game_title_image ) ? $game_title_image : $background;
    
    // 出力の開始
    ob_start();
    
    echo '<div class="noveltool-single-game-display noveltool-shortcode-container">';
    
    // 背景画像とタイトルのオーバーレイ表示
    if ( $display_image ) {
        echo '<div class="noveltool-game-hero" style="background-image: url(\'' . esc_url( $display_image ) . '\'); --noveltool-title-color: ' . esc_attr( $title_text_color ) . ';">';
        echo '<div class="noveltool-game-hero-overlay">';
        // タイトルオーバーレイ設定がオンで、かつshow_titleもtrueの場合のみ表示
        if ( $show_title && $show_title_overlay === '1' ) {
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
    echo esc_html__( 'Start Game', 'novel-game-plugin' );
    echo '</button>';
    echo '</div>';
    
    echo '</div>'; // .noveltool-single-game-display
    
    // モーダルオーバーレイを追加（個別ゲーム表示用）
    echo '<div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;">';
    echo '    <div id="novel-game-modal-content" class="novel-game-modal-content">';
    echo '        <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="' . esc_attr__( 'Close Game', 'novel-game-plugin' ) . '" title="' . esc_attr__( 'Close Game', 'novel-game-plugin' ) . '">';
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
    echo                          esc_html__( 'Start from Beginning', 'novel-game-plugin' );
    echo '                    </button>';
    echo '                    <button id="novel-title-continue" class="novel-title-btn novel-title-continue-btn" style="display: none;">';
    echo                          esc_html__( 'Continue from Save', 'novel-game-plugin' );
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
        return '<p>' . esc_html__( 'No games have been created yet.', 'novel-game-plugin' ) . '</p>';
    }
    
    ob_start();
    
    echo '<div class="noveltool-all-games-list noveltool-shortcode-container">';
    echo '<h3>' . esc_html__( 'Game List', 'novel-game-plugin' ) . '</h3>';
    echo '<div class="noveltool-games-grid">';
    
    foreach ( $game_titles as $game_title ) {
        $posts = noveltool_get_posts_by_game_title( $game_title, array( 'posts_per_page' => 1 ) );
        
        if ( empty( $posts ) ) {
            continue;
        }
        
        $first_post = $posts[0];
        $background = get_post_meta( $first_post->ID, '_background_image', true );
        $post_count = count( noveltool_get_posts_by_game_title( $game_title ) );
        
        // ゲーム概要・タイトル用画像を取得
        $game_description = '';
        $game_title_image = '';
        
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
        echo '<p class="noveltool-game-count">' . sprintf( esc_html__( '%d Scenes', 'novel-game-plugin' ), $post_count ) . '</p>';
        echo '<a href="' . esc_url( add_query_arg( 'shortcode', '1', get_permalink( $first_post->ID ) ) ) . '" class="noveltool-game-link button">';
        echo esc_html__( 'Start Playing', 'novel-game-plugin' );
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
        padding: 40px 30px 30px;
        color: white;
    }
    
    .noveltool-game-hero-title {
        margin: 0;
        font-size: 2.5em;
        font-weight: bold;
        line-height: 1.2;
        /* CSS カスタムプロパティで色を設定（デフォルトは白） */
        color: var(--noveltool-title-color, #ffffff);
        /* 強力な縁取りと影で可読性を確保（画像を覆わない） */
        -webkit-text-stroke: 2px rgba(0, 0, 0, 0.9);
        /* paint-order: stroke fill はブラウザサポートが限定的だが、対応ブラウザでは縁取りを背後に配置 */
        paint-order: stroke fill;
        text-shadow:
            3px 3px 6px rgba(0, 0, 0, 0.9),
            -1px -1px 4px rgba(0, 0, 0, 0.8),
            1px -1px 4px rgba(0, 0, 0, 0.8),
            -1px 1px 4px rgba(0, 0, 0, 0.8),
            1px 1px 4px rgba(0, 0, 0, 0.8);
    }
    
    /* Webkit非対応ブラウザ向けフォールバック：多重text-shadowで擬似縁取りを実現 */
    @supports not (-webkit-text-stroke: 2px black) {
        .noveltool-game-hero-title {
            /* 縁取り効果のための多重text-shadow */
            text-shadow:
                0 0 6px rgba(0, 0, 0, 0.9),
                0 0 6px rgba(0, 0, 0, 0.9),
                2px 2px 4px rgba(0, 0, 0, 0.9),
                -2px -2px 4px rgba(0, 0, 0, 0.9),
                2px -2px 4px rgba(0, 0, 0, 0.9),
                -2px 2px 4px rgba(0, 0, 0, 0.9),
                3px 3px 6px rgba(0, 0, 0, 0.9);
        }
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
            /* タブレット・モバイルでは縁取りを少し細く */
            -webkit-text-stroke: 1.5px rgba(0, 0, 0, 0.9);
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
            /* 小画面では縁取りをさらに細く */
            -webkit-text-stroke: 1px rgba(0, 0, 0, 0.9);
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

/**
 * ゲーム管理画面のURLを生成
 *
 * @param int    $game_id ゲームID
 * @param string $tab     タブ名（'scenes', 'new-scene', 'settings'）
 * @param array  $args    追加のクエリパラメータ
 * @return string ゲーム管理画面のURL
 * @since 1.2.0
 */
function noveltool_get_game_manager_url( $game_id, $tab = 'scenes', $args = array() ) {
    $default_args = array(
        'game_id' => $game_id,
        'tab'     => $tab,
    );
    
    $query_args = wp_parse_args( $args, $default_args );
    
    return add_query_arg(
        $query_args,
        admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' )
    );
}

/**
 * マイゲームページのURLを生成
 *
 * @param array $args 追加のクエリパラメータ
 * @return string マイゲームページのURL
 * @since 1.2.0
 */
function noveltool_get_my_games_url( $args = array() ) {
    return add_query_arg(
        $args,
        admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' )
    );
}
?>
