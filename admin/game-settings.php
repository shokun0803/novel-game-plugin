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