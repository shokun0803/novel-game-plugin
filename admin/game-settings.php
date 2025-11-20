<?php
/**
 * ゲーム設定管理画面
 *
 * @package NovelGamePlugin
 * @since 1.1.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * プラグイン設定ページをメニューに追加
 *
 * @since 1.1.0
 */
function noveltool_add_plugin_settings_menu() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __( 'Settings', 'novel-game-plugin' ),
        '⚙️ ' . __( 'Settings', 'novel-game-plugin' ),
        'manage_options',
        'novel-game-plugin-settings',
        'noveltool_plugin_settings_page',
        3
    );
}
add_action( 'admin_menu', 'noveltool_add_plugin_settings_menu' );

/**
 * admin-post ハンドラー: ゲーム追加
 *
 * @since 1.2.0
 */
function noveltool_admin_post_add_game() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_games' ) ) {
        $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    // ゲーム情報の取得とバリデーション
    $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
    $game_description = isset( $_POST['game_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['game_description'] ) ) : '';
    $game_title_image = isset( $_POST['game_title_image'] ) ? sanitize_url( wp_unslash( $_POST['game_title_image'] ) ) : '';
    $game_over_text = isset( $_POST['game_over_text'] ) ? sanitize_text_field( wp_unslash( $_POST['game_over_text'] ) ) : 'Game Over';

    if ( empty( $game_title ) ) {
        $redirect_url = add_query_arg( 'error', 'empty_title', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    // 同じタイトルのゲームが既に存在するかチェック
    if ( noveltool_get_game_by_title( $game_title ) ) {
        $redirect_url = add_query_arg( 'error', 'duplicate_title', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    // ゲームを保存
    $game_data = array(
        'title'         => $game_title,
        'description'   => $game_description,
        'title_image'   => $game_title_image,
        'game_over_text' => $game_over_text,
    );

    $game_id = noveltool_save_game( $game_data );

    if ( $game_id ) {
        $redirect_url = noveltool_get_game_manager_url( $game_id, 'settings', array( 'success' => 'added' ) );
    } else {
        $redirect_url = add_query_arg( 'error', 'save_failed', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_noveltool_add_game', 'noveltool_admin_post_add_game' );

/**
 * admin-post ハンドラー: ゲーム更新
 *
 * @since 1.2.0
 */
function noveltool_admin_post_update_game() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_games' ) ) {
        $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;
    $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
    $game_description = isset( $_POST['game_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['game_description'] ) ) : '';
    $game_title_image = isset( $_POST['game_title_image'] ) ? sanitize_url( wp_unslash( $_POST['game_title_image'] ) ) : '';
    $game_over_text = isset( $_POST['game_over_text'] ) ? sanitize_text_field( wp_unslash( $_POST['game_over_text'] ) ) : 'Game Over';
    $old_title = isset( $_POST['old_title'] ) ? sanitize_text_field( wp_unslash( $_POST['old_title'] ) ) : '';

    if ( empty( $game_title ) ) {
        $redirect_url = noveltool_get_game_manager_url( $game_id, 'settings', array( 'error' => 'empty_title' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    // 同じタイトルのゲームが既に存在するかチェック（自分以外）
    $existing_game = noveltool_get_game_by_title( $game_title );
    if ( $existing_game && $existing_game['id'] != $game_id ) {
        $redirect_url = noveltool_get_game_manager_url( $game_id, 'settings', array( 'error' => 'duplicate_title' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    // ゲームを更新
    $game_data = array(
        'id'            => $game_id,
        'title'         => $game_title,
        'description'   => $game_description,
        'title_image'   => $game_title_image,
        'game_over_text' => $game_over_text,
    );

    $result = noveltool_save_game( $game_data );

    // 広告プロバイダーの取得とバリデーション
    $ad_provider = isset( $_POST['ad_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['ad_provider'] ) ) : '';
    
    // 許可リスト検証
    $allowed_providers = array( 'none', 'adsense', 'adsterra' );
    if ( ! in_array( $ad_provider, $allowed_providers, true ) ) {
        $ad_provider = 'none';
    }
    
    // post metaに保存
    update_post_meta( $game_id, 'noveltool_ad_provider', $ad_provider );

    if ( $result ) {
        // タイトルが変更された場合は、既存のシーンのゲームタイトルも更新
        if ( $old_title && $old_title !== $game_title ) {
            noveltool_update_scenes_game_title( $old_title, $game_title );
        }
        
        $redirect_url = noveltool_get_game_manager_url( 
            $game_id, 
            'settings', 
            array( 'success' => 'updated' ) 
        );
    } else {
        $redirect_url = noveltool_get_game_manager_url( 
            $game_id, 
            'settings', 
            array( 'error' => 'save_failed' ) 
        );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_noveltool_update_game', 'noveltool_admin_post_update_game' );

/**
 * admin-post ハンドラー: ゲーム削除
 *
 * @since 1.2.0
 */
function noveltool_admin_post_delete_game() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_games' ) ) {
        $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;

    if ( $game_id ) {
        $result = noveltool_delete_game( $game_id );

        if ( $result ) {
            $redirect_url = add_query_arg( 'success', 'deleted', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        } else {
            $redirect_url = add_query_arg( 'error', 'delete_failed', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        }
    } else {
        $redirect_url = add_query_arg( 'error', 'invalid_id', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_noveltool_delete_game', 'noveltool_admin_post_delete_game' );

/**
 * admin-post ハンドラー: シーン削除
 *
 * @since 1.2.0
 */
function noveltool_admin_post_delete_scene() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_scenes' ) ) {
        $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;
        $redirect_url = $game_id 
            ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'error' => 'security' ) )
            : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $scene_id = isset( $_POST['scene_id'] ) ? intval( wp_unslash( $_POST['scene_id'] ) ) : 0;
    $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;

    if ( $scene_id ) {
        $result = wp_delete_post( $scene_id, true ); // 完全削除（ゴミ箱に入れない）

        if ( $result ) {
            $redirect_url = $game_id 
                ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'success' => 'scene_deleted' ) )
                : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
        } else {
            $redirect_url = $game_id 
                ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'error' => 'delete_failed' ) )
                : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
        }
    } else {
        $redirect_url = $game_id 
            ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'error' => 'invalid_id' ) )
            : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_noveltool_delete_scene', 'noveltool_admin_post_delete_scene' );

/**
 * admin-post ハンドラー: フラグ追加
 *
 * @since 1.2.0
 */
function noveltool_admin_post_add_flag() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_flags' ) ) {
        $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
    $flag_name = isset( $_POST['flag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['flag_name'] ) ) : '';
    $flag_description = isset( $_POST['flag_description'] ) ? sanitize_text_field( wp_unslash( $_POST['flag_description'] ) ) : '';

    if ( empty( $game_title ) || empty( $flag_name ) ) {
        $game_data = noveltool_get_game_by_title( $game_title );
        $game_id = $game_data ? $game_data['id'] : 0;
        if ( $game_id ) {
            $redirect_url = noveltool_get_game_manager_url( $game_id, 'settings', array( 'error' => 'empty_flag_data' ) );
        } else {
            $redirect_url = admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
        }
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $result = noveltool_add_game_flag( $game_title, $flag_name, $flag_description );

    if ( $result ) {
        // ゲームIDを取得
        $game_data = noveltool_get_game_by_title( $game_title );
        $game_id = $game_data ? $game_data['id'] : 0;
        $redirect_url = noveltool_get_game_manager_url( 
            $game_id, 
            'settings', 
            array( 'success' => 'flag_added' ) 
        );
    } else {
        $game_data = noveltool_get_game_by_title( $game_title );
        $game_id = $game_data ? $game_data['id'] : 0;
        $redirect_url = noveltool_get_game_manager_url( 
            $game_id, 
            'settings', 
            array( 'error' => 'flag_add_failed' ) 
        );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_noveltool_add_flag', 'noveltool_admin_post_add_flag' );

/**
 * admin-post ハンドラー: フラグ削除
 *
 * @since 1.2.0
 */
function noveltool_admin_post_delete_flag() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_flags' ) ) {
        $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
    $flag_name = isset( $_POST['flag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['flag_name'] ) ) : '';

    if ( empty( $game_title ) || empty( $flag_name ) ) {
        $game_data = noveltool_get_game_by_title( $game_title );
        $game_id = $game_data ? $game_data['id'] : 0;
        if ( $game_id ) {
            $redirect_url = noveltool_get_game_manager_url( $game_id, 'settings', array( 'error' => 'empty_flag_data' ) );
        } else {
            $redirect_url = admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
        }
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $result = noveltool_remove_game_flag( $game_title, $flag_name );

    if ( $result ) {
        // ゲームIDを取得
        $game_data = noveltool_get_game_by_title( $game_title );
        $game_id = $game_data ? $game_data['id'] : 0;
        $redirect_url = noveltool_get_game_manager_url( 
            $game_id, 
            'settings', 
            array( 'success' => 'flag_deleted' ) 
        );
    } else {
        $game_data = noveltool_get_game_by_title( $game_title );
        $game_id = $game_data ? $game_data['id'] : 0;
        $redirect_url = noveltool_get_game_manager_url( 
            $game_id, 
            'settings', 
            array( 'error' => 'flag_delete_failed' ) 
        );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_noveltool_delete_flag', 'noveltool_admin_post_delete_flag' );

/**
 * admin-post ハンドラー: 広告設定更新
 *
 * @since 1.2.0
 */
function noveltool_admin_post_update_ad_setting() {
    // nonceチェック
    if ( ! isset( $_POST['_wpnonce'] ) || ! check_admin_referer( 'noveltool_ad_setting_nonce', '_wpnonce' ) ) {
        wp_die( __( 'Security check failed.', 'novel-game-plugin' ) );
    }

    // ゲームIDの取得と検証
    $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;
    if ( ! $game_id ) {
        wp_die( __( 'Invalid game ID.', 'novel-game-plugin' ) );
    }

    // 権限チェック
    if ( ! current_user_can( 'edit_post', $game_id ) ) {
        wp_die( __( 'You do not have permission to edit this game.', 'novel-game-plugin' ) );
    }

    // 広告プロバイダーの取得とバリデーション
    $ad_provider = isset( $_POST['ad_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['ad_provider'] ) ) : '';
    
    // 許可リスト検証
    $allowed_providers = array( 'none', 'adsense', 'adsterra' );
    if ( ! in_array( $ad_provider, $allowed_providers, true ) ) {
        // 不正値の場合はデフォルト値にフォールバック
        $ad_provider = 'none';
    }

    // post metaに保存
    update_post_meta( $game_id, 'noveltool_ad_provider', $ad_provider );

    // 成功メッセージ付きでリダイレクト
    $redirect_url = noveltool_get_game_manager_url( 
        $game_id, 
        'settings', 
        array( 'success' => 'ad_setting_updated' ) 
    );
    
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_noveltool_update_ad_setting', 'noveltool_admin_post_update_ad_setting' );

/**
 * 現在のゲームタイトルを取得（シーンから）
 *
 * @return string 現在のゲームタイトル
 * @since 1.1.0
 */
function noveltool_get_current_game_title() {
    global $wpdb;
    
    $current_title = $wpdb->get_var( 
        $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = %s 
             AND meta_value != '' 
             LIMIT 1",
            '_game_title'
        )
    );
    
    return $current_title ? $current_title : '';
}

/**
 * 既存のシーンのゲームタイトルを更新
 *
 * 両引数は必須。指定した旧タイトルに一致するシーンの「_game_title」メタのみを新しいタイトルへ更新します。
 *
 * @param string $old_title 旧ゲームタイトル  
 * @param string $new_title 新ゲームタイトル
 * @global wpdb  $wpdb      WordPress データベースアクセスオブジェクト
 * @since 1.1.0
 */
function noveltool_update_scenes_game_title( $old_title, $new_title ) {
    global $wpdb;
    
    // 特定のゲームタイトルを持つシーンのみ更新
    $wpdb->update(
        $wpdb->postmeta,
        array( 'meta_value' => $new_title ),
        array( 
            'meta_key' => '_game_title',
            'meta_value' => $old_title
        ),
        array( '%s' ),
        array( '%s', '%s' )
    );
}

/**
 * ゲームデータをエクスポート
 *
 * @param int $game_id ゲームID
 * @return array|WP_Error エクスポートデータまたはエラー
 * @since 1.3.0
 */
function noveltool_export_game_data( $game_id ) {
    // ゲームデータの取得
    $game = noveltool_get_game_by_id( $game_id );
    if ( ! $game ) {
        return new WP_Error( 'invalid_game', __( 'Game not found.', 'novel-game-plugin' ) );
    }

    // シーンデータの取得
    $scenes = noveltool_get_posts_by_game_title( $game['title'] );
    $scenes_data = array();

    // シーンごとに安定識別子を付与
    foreach ( $scenes as $i => $scene ) {
        $scene_meta = array(
            'original_index'          => $i,
            'title'                   => $scene->post_title,
            'background_image'        => get_post_meta( $scene->ID, '_background_image', true ),
            'character_image'         => get_post_meta( $scene->ID, '_character_image', true ),
            'character_left'          => get_post_meta( $scene->ID, '_character_left', true ),
            'character_center'        => get_post_meta( $scene->ID, '_character_center', true ),
            'character_right'         => get_post_meta( $scene->ID, '_character_right', true ),
            'character_left_name'     => get_post_meta( $scene->ID, '_character_left_name', true ),
            'character_center_name'   => get_post_meta( $scene->ID, '_character_center_name', true ),
            'character_right_name'    => get_post_meta( $scene->ID, '_character_right_name', true ),
            'dialogue_text'           => get_post_meta( $scene->ID, '_dialogue_text', true ),
            'dialogue_texts'          => get_post_meta( $scene->ID, '_dialogue_texts', true ),
            'dialogue_speakers'       => get_post_meta( $scene->ID, '_dialogue_speakers', true ),
            'dialogue_backgrounds'    => get_post_meta( $scene->ID, '_dialogue_backgrounds', true ),
            'dialogue_flag_conditions' => get_post_meta( $scene->ID, '_dialogue_flag_conditions', true ),
            'choices'                 => get_post_meta( $scene->ID, '_choices', true ),
            'is_ending'               => get_post_meta( $scene->ID, '_is_ending', true ),
            'ending_text'             => get_post_meta( $scene->ID, '_ending_text', true ),
            'scene_arrival_flags'     => get_post_meta( $scene->ID, '_scene_arrival_flags', true ),
        );

        $scenes_data[] = $scene_meta;
    }

    // フラグマスタの取得
    $flag_master = noveltool_get_game_flag_master( $game['title'] );

    // エクスポートデータの構築
    $export_data = array(
        'version'        => '1.0',
        'plugin_version' => NOVEL_GAME_PLUGIN_VERSION,
        'export_date'    => current_time( 'mysql' ),
        'game'           => array(
            'title'         => $game['title'],
            'description'   => isset( $game['description'] ) ? $game['description'] : '',
            'title_image'   => isset( $game['title_image'] ) ? $game['title_image'] : '',
            'game_over_text' => isset( $game['game_over_text'] ) ? $game['game_over_text'] : 'Game Over',
        ),
        'flags'          => $flag_master,
        'scenes'         => $scenes_data,
    );

    // エクスポート履歴を記録
    noveltool_log_transfer_operation( 'export', $game['title'], count( $scenes_data ), count( $flag_master ) );

    return $export_data;
}

/**
 * JSON形式のメタフィールドをサニタイズ
 *
 * @param mixed  $value メタ値
 * @param string $meta_key メタキー
 * @return string|array サニタイズ済みの値
 * @since 1.3.0
 */
function noveltool_sanitize_json_meta_field( $value, $meta_key ) {
    // 既にJSON文字列の場合はデコード
    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            $value = $decoded;
        }
    }
    
    if ( ! is_array( $value ) ) {
        return '';
    }
    
    // メタキーに応じたサニタイズ
    switch ( $meta_key ) {
        case '_dialogue_texts':
            // 各要素をテキストとしてサニタイズ
            return wp_json_encode( array_map( 'sanitize_text_field', $value ), JSON_UNESCAPED_UNICODE );
            
        case '_dialogue_speakers':
            // 各要素をテキストとしてサニタイズ
            return wp_json_encode( array_map( 'sanitize_text_field', $value ), JSON_UNESCAPED_UNICODE );
            
        case '_dialogue_backgrounds':
            // URL要素はesc_url_raw、それ以外はsanitize_text_field
            $sanitized = array();
            foreach ( $value as $item ) {
                if ( filter_var( $item, FILTER_VALIDATE_URL ) ) {
                    $sanitized[] = esc_url_raw( $item );
                } else {
                    $sanitized[] = sanitize_text_field( $item );
                }
            }
            return wp_json_encode( $sanitized, JSON_UNESCAPED_UNICODE );
            
        case '_dialogue_flag_conditions':
            // 各条件の要素をサニタイズ
            $sanitized = array();
            foreach ( $value as $condition ) {
                if ( ! is_array( $condition ) ) {
                    continue;
                }
                $sanitized_condition = array();
                if ( isset( $condition['conditions'] ) && is_array( $condition['conditions'] ) ) {
                    $sanitized_condition['conditions'] = noveltool_sanitize_flag_conditions( $condition['conditions'] );
                }
                if ( isset( $condition['logic'] ) ) {
                    $sanitized_condition['logic'] = sanitize_text_field( $condition['logic'] );
                }
                if ( isset( $condition['displayMode'] ) ) {
                    $sanitized_condition['displayMode'] = sanitize_text_field( $condition['displayMode'] );
                }
                if ( isset( $condition['alternativeText'] ) ) {
                    $sanitized_condition['alternativeText'] = sanitize_text_field( $condition['alternativeText'] );
                }
                $sanitized[] = $sanitized_condition;
            }
            return wp_json_encode( $sanitized, JSON_UNESCAPED_UNICODE );
            
        case '_choices':
            // 選択肢の各フィールドをサニタイズ
            $sanitized = array();
            foreach ( $value as $choice ) {
                if ( ! is_array( $choice ) ) {
                    continue;
                }
                
                $sanitized_choice = array(
                    'text' => isset( $choice['text'] ) ? sanitize_text_field( $choice['text'] ) : '',
                    'next' => isset( $choice['next'] ) ? intval( $choice['next'] ) : 0,
                );
                
                if ( isset( $choice['flagConditions'] ) && is_array( $choice['flagConditions'] ) ) {
                    $sanitized_choice['flagConditions'] = noveltool_sanitize_flag_conditions( $choice['flagConditions'] );
                }
                
                if ( isset( $choice['flagConditionLogic'] ) ) {
                    $sanitized_choice['flagConditionLogic'] = sanitize_text_field( $choice['flagConditionLogic'] );
                }
                
                if ( isset( $choice['setFlags'] ) && is_array( $choice['setFlags'] ) ) {
                    $sanitized_choice['setFlags'] = noveltool_sanitize_flag_conditions( $choice['setFlags'] );
                }
                
                $sanitized[] = $sanitized_choice;
            }
            return wp_json_encode( $sanitized, JSON_UNESCAPED_UNICODE );
            
        default:
            return wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
    }
}

/**
 * フラグ条件配列をサニタイズ
 *
 * @param array $conditions フラグ条件配列
 * @return array サニタイズ済みの条件配列
 * @since 1.3.0
 */
function noveltool_sanitize_flag_conditions( $conditions ) {
    if ( ! is_array( $conditions ) ) {
        return array();
    }
    
    $sanitized = array();
    foreach ( $conditions as $condition ) {
        if ( ! is_array( $condition ) || ! isset( $condition['name'] ) ) {
            continue;
        }
        
        $sanitized[] = array(
            'name'  => sanitize_text_field( $condition['name'] ),
            'state' => isset( $condition['state'] ) ? (bool) $condition['state'] : true,
        );
    }
    
    return $sanitized;
}

/**
 * ゲームデータをインポート
 *
 * @param array $import_data インポートデータ
 * @param bool  $download_images 画像をダウンロードするかどうか
 * @return array|WP_Error インポート結果またはエラー
 * @since 1.3.0
 */
function noveltool_import_game_data( $import_data, $download_images = false ) {
    // データ検証
    if ( ! is_array( $import_data ) || ! isset( $import_data['game'], $import_data['scenes'] ) ) {
        return new WP_Error( 'invalid_data', __( 'Invalid import data format.', 'novel-game-plugin' ) );
    }

    $game_data = $import_data['game'];
    
    // 必須フィールドの確認
    if ( empty( $game_data['title'] ) ) {
        return new WP_Error( 'missing_title', __( 'Game title is required.', 'novel-game-plugin' ) );
    }

    // タイトルの重複チェック
    $existing_game = noveltool_get_game_by_title( $game_data['title'] );
    if ( $existing_game ) {
        return new WP_Error( 'duplicate_title', __( 'A game with this title already exists.', 'novel-game-plugin' ) );
    }

    // ゲームデータを保存
    $new_game_data = array(
        'title'         => sanitize_text_field( $game_data['title'] ),
        'description'   => isset( $game_data['description'] ) ? sanitize_textarea_field( $game_data['description'] ) : '',
        'title_image'   => isset( $game_data['title_image'] ) ? esc_url_raw( $game_data['title_image'] ) : '',
        'game_over_text' => isset( $game_data['game_over_text'] ) ? sanitize_text_field( $game_data['game_over_text'] ) : 'Game Over',
    );

    // 画像のダウンロード処理
    if ( $download_images && ! empty( $new_game_data['title_image'] ) ) {
        $downloaded_url = noveltool_download_image_to_media_library( $new_game_data['title_image'] );
        if ( ! is_wp_error( $downloaded_url ) ) {
            $new_game_data['title_image'] = $downloaded_url;
        }
    }

    $game_id = noveltool_save_game( $new_game_data );
    if ( ! $game_id ) {
        return new WP_Error( 'save_failed', __( 'Failed to save game data.', 'novel-game-plugin' ) );
    }

    // フラグマスタの保存
    if ( isset( $import_data['flags'] ) && is_array( $import_data['flags'] ) ) {
        noveltool_save_game_flag_master( $new_game_data['title'], $import_data['flags'] );
    }

    // シーンデータの保存（フェーズ1: シーン挿入とインデックスマッピング）
    $imported_scenes = 0;
    $index_to_post_id_map = array();
    
    foreach ( $import_data['scenes'] as $scene_index => $scene_data ) {
        // シーンの作成
        $post_data = array(
            'post_type'   => 'novel_game',
            'post_title'  => isset( $scene_data['title'] ) ? sanitize_text_field( $scene_data['title'] ) : '',
            'post_status' => 'publish',
        );

        $post_id = wp_insert_post( $post_data );
        if ( is_wp_error( $post_id ) ) {
            continue;
        }
        
        // original_indexが存在する場合はそれを使用、なければ配列インデックスを使用
        $original_index = isset( $scene_data['original_index'] ) ? intval( $scene_data['original_index'] ) : $scene_index;
        $index_to_post_id_map[ $original_index ] = $post_id;

        // ゲームタイトルを設定
        update_post_meta( $post_id, '_game_title', $new_game_data['title'] );

        // 各メタデータの保存（サニタイズ強化）
        $meta_fields = array(
            'background_image'        => '_background_image',
            'character_image'         => '_character_image',
            'character_left'          => '_character_left',
            'character_center'        => '_character_center',
            'character_right'         => '_character_right',
            'character_left_name'     => '_character_left_name',
            'character_center_name'   => '_character_center_name',
            'character_right_name'    => '_character_right_name',
            'dialogue_text'           => '_dialogue_text',
            'dialogue_texts'          => '_dialogue_texts',
            'dialogue_speakers'       => '_dialogue_speakers',
            'dialogue_backgrounds'    => '_dialogue_backgrounds',
            'dialogue_flag_conditions' => '_dialogue_flag_conditions',
            'choices'                 => '_choices',
            'is_ending'               => '_is_ending',
            'ending_text'             => '_ending_text',
            'scene_arrival_flags'     => '_scene_arrival_flags',
        );

        foreach ( $meta_fields as $field_name => $meta_key ) {
            if ( ! isset( $scene_data[ $field_name ] ) ) {
                continue;
            }
            
            $meta_value = $scene_data[ $field_name ];
            
            // メタデータサニタイズ強化
            $image_meta_keys = array( '_background_image', '_character_image', '_character_left', '_character_center', '_character_right' );
            
            if ( in_array( $meta_key, $image_meta_keys, true ) ) {
                // 画像URLの処理
                if ( ! empty( $meta_value ) && filter_var( $meta_value, FILTER_VALIDATE_URL ) ) {
                    $meta_value = esc_url_raw( $meta_value );
                    
                    if ( $download_images ) {
                        $downloaded_url = noveltool_download_image_to_media_library( $meta_value );
                        if ( ! is_wp_error( $downloaded_url ) ) {
                            $meta_value = $downloaded_url;
                        }
                        // 失敗時は元URLをそのまま使用
                    }
                } else {
                    $meta_value = '';
                }
            } elseif ( $meta_key === '_is_ending' ) {
                // Boolean型
                $meta_value = (bool) $meta_value;
            } elseif ( in_array( $meta_key, array( '_dialogue_texts', '_dialogue_speakers', '_dialogue_backgrounds', '_dialogue_flag_conditions', '_choices' ), true ) ) {
                // JSON配列/オブジェクト: デコード→サニタイズ→エンコード
                $meta_value = noveltool_sanitize_json_meta_field( $meta_value, $meta_key );
            } elseif ( in_array( $meta_key, array( '_character_left_name', '_character_center_name', '_character_right_name', '_dialogue_text', '_ending_text' ), true ) ) {
                // テキストフィールド
                $meta_value = sanitize_text_field( $meta_value );
            } elseif ( $meta_key === '_scene_arrival_flags' ) {
                // 配列フィールド
                if ( is_array( $meta_value ) ) {
                    $meta_value = array_map( 'sanitize_text_field', $meta_value );
                } elseif ( is_string( $meta_value ) ) {
                    $decoded = json_decode( $meta_value, true );
                    if ( is_array( $decoded ) ) {
                        $meta_value = array_map( 'sanitize_text_field', $decoded );
                    }
                }
            }
            
            update_post_meta( $post_id, $meta_key, $meta_value );
        }

        $imported_scenes++;
    }
    
    // フェーズ2: 選択肢ID再マッピング処理
    $remapped_choices = 0;
    
    foreach ( $index_to_post_id_map as $original_index => $post_id ) {
        $choices_meta = get_post_meta( $post_id, '_choices', true );
        
        if ( empty( $choices_meta ) ) {
            continue;
        }
        
        // JSON形式の選択肢を処理
        $json_choices = json_decode( $choices_meta, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_choices ) ) {
            // JSON形式の選択肢: nextをマップで置換
            $modified = false;
            
            foreach ( $json_choices as &$choice ) {
                if ( isset( $choice['next'] ) && is_numeric( $choice['next'] ) ) {
                    $old_next = intval( $choice['next'] );
                    
                    // マップ内でold_nextをキーとして検索
                    if ( isset( $index_to_post_id_map[ $old_next ] ) ) {
                        $choice['next'] = $index_to_post_id_map[ $old_next ];
                        $modified = true;
                    } else {
                        // マップに存在しない場合は警告ログ
                        error_log( sprintf( '[noveltool] Warning: Choice ID remapping failed for scene %d, original next=%d not found in index map', $post_id, $old_next ) );
                    }
                }
            }
            
            if ( $modified ) {
                update_post_meta( $post_id, '_choices', wp_json_encode( $json_choices, JSON_UNESCAPED_UNICODE ) );
                $remapped_choices++;
            }
        }
    }

    return array(
        'success'          => true,
        'game_id'          => $game_id,
        'imported_scenes'  => $imported_scenes,
        'remapped_choices' => $remapped_choices,
    );
}

/**
 * 画像URLからメディアライブラリにダウンロード
 *
 * セキュリティ強化: URLスキーム検証、Content-Type確認、画像検証を実施
 *
 * @param string $image_url 画像URL
 * @return string|WP_Error ダウンロードされた画像のURLまたはエラー
 * @since 1.3.0
 */
function noveltool_download_image_to_media_library( $image_url ) {
    if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
        return new WP_Error( 'invalid_url', __( 'Invalid image URL.', 'novel-game-plugin' ) );
    }
    
    // URLスキーム検証: httpまたはhttpsのみ許可
    $parsed_url = wp_parse_url( $image_url );
    if ( ! isset( $parsed_url['scheme'] ) || ! in_array( $parsed_url['scheme'], array( 'http', 'https' ), true ) ) {
        error_log( '[noveltool] Image download failed: Invalid URL scheme for ' . $image_url );
        return new WP_Error( 'invalid_scheme', __( 'Only HTTP/HTTPS URLs are allowed.', 'novel-game-plugin' ) );
    }
    
    // Content-Type事前チェック
    $response = wp_remote_head( $image_url, array( 'timeout' => 10 ) );
    if ( is_wp_error( $response ) ) {
        error_log( '[noveltool] Image download failed: ' . $image_url . ' reason: ' . $response->get_error_message() );
        return $response;
    }
    
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    if ( $content_type && strpos( $content_type, 'image/' ) !== 0 ) {
        error_log( '[noveltool] Image download failed: Invalid Content-Type for ' . $image_url . ' (got: ' . $content_type . ')' );
        return new WP_Error( 'invalid_content_type', __( 'URL does not point to an image.', 'novel-game-plugin' ) );
    }

    // WordPress HTTP APIを使用して画像をダウンロード
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $temp_file = download_url( $image_url, 10 );
    if ( is_wp_error( $temp_file ) ) {
        error_log( '[noveltool] Image download failed: ' . $image_url . ' reason: ' . $temp_file->get_error_message() );
        return $temp_file;
    }
    
    // 画像検証: getimagesizeで画像であることを確認
    $image_info = getimagesize( $temp_file );
    if ( $image_info === false ) {
        if ( file_exists( $temp_file ) ) {
            unlink( $temp_file );
        }
        error_log( '[noveltool] Image download failed: File is not a valid image ' . $image_url );
        return new WP_Error( 'invalid_image', __( 'Downloaded file is not a valid image.', 'novel-game-plugin' ) );
    }

    // MIMEタイプに基づいて拡張子を決定
    $mime_to_ext = array(
        'image/jpeg' => '.jpg',
        'image/png'  => '.png',
        'image/gif'  => '.gif',
        'image/webp' => '.webp',
    );
    
    $mime_type = $image_info['mime'];
    $extension = isset( $mime_to_ext[ $mime_type ] ) ? $mime_to_ext[ $mime_type ] : '.jpg';

    // ファイル名を取得
    $file_name = basename( wp_parse_url( $image_url, PHP_URL_PATH ) );
    if ( empty( $file_name ) || strpos( $file_name, '.' ) === false ) {
        $file_name = 'imported-image-' . time() . $extension;
    }

    // メディアライブラリに追加
    $file_array = array(
        'name'     => sanitize_file_name( $file_name ),
        'tmp_name' => $temp_file,
    );

    $attachment_id = media_handle_sideload( $file_array, 0 );
    
    // 一時ファイルを削除
    if ( file_exists( $temp_file ) ) {
        unlink( $temp_file );
    }

    if ( is_wp_error( $attachment_id ) ) {
        error_log( '[noveltool] Image download failed: ' . $image_url . ' reason: ' . $attachment_id->get_error_message() );
        return $attachment_id;
    }

    return wp_get_attachment_url( $attachment_id );
}

/**
 * AJAXハンドラー: ゲームデータのエクスポート
 *
 * エラーメッセージは静的文言のみ利用し外部入力を直接連結しない方針
 *
 * @since 1.3.0
 */
function noveltool_ajax_export_game() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'novel-game-plugin' ) ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'noveltool_export_game' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'novel-game-plugin' ) ) );
    }

    // ゲームIDの取得
    $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;
    if ( ! $game_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid game ID.', 'novel-game-plugin' ) ) );
    }

    // エクスポート実行
    $export_data = noveltool_export_game_data( $game_id );
    if ( is_wp_error( $export_data ) ) {
        wp_send_json_error( array( 'message' => $export_data->get_error_message() ) );
    }

    wp_send_json_success( array(
        'data'     => $export_data,
        'filename' => sanitize_file_name( $export_data['game']['title'] . '-export.json' ),
    ) );
}
add_action( 'wp_ajax_noveltool_export_game', 'noveltool_ajax_export_game' );

/**
 * AJAXハンドラー: ゲームデータのインポート
 *
 * セキュリティ強化: ファイルサイズ、MIME、アップロードエラーの詳細チェックを実施
 * エラーメッセージは静的文言のみ利用し外部入力を直接連結しない方針
 *
 * @since 1.3.0
 */
function noveltool_ajax_import_game() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'novel-game-plugin' ) ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'noveltool_import_game' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'novel-game-plugin' ) ) );
    }

    // ファイルアップロードエラーの詳細チェック
    if ( ! isset( $_FILES['import_file'] ) ) {
        wp_send_json_error( array( 'message' => __( 'No file selected.', 'novel-game-plugin' ) ) );
    }
    
    if ( isset( $_FILES['import_file']['error'] ) && $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
        switch ( $_FILES['import_file']['error'] ) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                wp_send_json_error( array( 'message' => __( 'File size exceeds limit.', 'novel-game-plugin' ) ) );
                break;
            case UPLOAD_ERR_PARTIAL:
                wp_send_json_error( array( 'message' => __( 'File upload was interrupted.', 'novel-game-plugin' ) ) );
                break;
            case UPLOAD_ERR_NO_FILE:
                wp_send_json_error( array( 'message' => __( 'No file selected.', 'novel-game-plugin' ) ) );
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                wp_send_json_error( array( 'message' => __( 'Server upload error.', 'novel-game-plugin' ) ) );
                break;
            case UPLOAD_ERR_EXTENSION:
                wp_send_json_error( array( 'message' => __( 'File upload blocked by extension.', 'novel-game-plugin' ) ) );
                break;
            default:
                wp_send_json_error( array( 'message' => __( 'File upload failed.', 'novel-game-plugin' ) ) );
        }
    }
    
    // ファイルサイズチェック（10MB制限）
    if ( isset( $_FILES['import_file']['size'] ) && $_FILES['import_file']['size'] > 10 * 1024 * 1024 ) {
        wp_send_json_error( array( 'message' => __( 'File size exceeds 10MB limit.', 'novel-game-plugin' ) ) );
    }
    
    // MIMEタイプ検証
    $file_type = wp_check_filetype_and_ext( $_FILES['import_file']['tmp_name'], $_FILES['import_file']['name'] );
    
    // JSONファイルかどうかを確認
    $allowed_types = array( 'application/json', 'text/plain' );
    $is_json = false;
    
    if ( isset( $file_type['type'] ) && in_array( $file_type['type'], $allowed_types, true ) ) {
        $is_json = true;
    } elseif ( isset( $_FILES['import_file']['type'] ) && in_array( $_FILES['import_file']['type'], $allowed_types, true ) ) {
        $is_json = true;
    } elseif ( isset( $_FILES['import_file']['name'] ) && substr( $_FILES['import_file']['name'], -5 ) === '.json' ) {
        $is_json = true;
    }
    
    if ( ! $is_json ) {
        wp_send_json_error( array( 'message' => __( 'Only JSON files are allowed.', 'novel-game-plugin' ) ) );
    }

    // ファイルの読み込み
    $file_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
    if ( $file_content === false ) {
        wp_send_json_error( array( 'message' => __( 'Failed to read file.', 'novel-game-plugin' ) ) );
    }

    // JSONデコード
    $import_data = json_decode( $file_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( array( 'message' => __( 'Invalid JSON format.', 'novel-game-plugin' ) ) );
    }

    // 画像ダウンロードオプション
    $download_images = isset( $_POST['download_images'] ) && $_POST['download_images'] === 'true';

    // インポート実行
    $result = noveltool_import_game_data( $import_data, $download_images );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    wp_send_json_success( array(
        'message'         => sprintf(
            __( 'Successfully imported game with %d scenes (%d choices remapped).', 'novel-game-plugin' ),
            $result['imported_scenes'],
            isset( $result['remapped_choices'] ) ? $result['remapped_choices'] : 0
        ),
        'game_id'         => $result['game_id'],
        'imported_scenes' => $result['imported_scenes'],
        'remapped_choices' => isset( $result['remapped_choices'] ) ? $result['remapped_choices'] : 0,
    ) );
}
add_action( 'wp_ajax_noveltool_import_game', 'noveltool_ajax_import_game' );
