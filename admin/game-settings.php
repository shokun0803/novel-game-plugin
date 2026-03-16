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
    $game_title_image = isset( $_POST['game_title_image'] ) ? esc_url_raw( wp_unslash( $_POST['game_title_image'] ) ) : '';
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
    $game_title_image = isset( $_POST['game_title_image'] ) ? esc_url_raw( wp_unslash( $_POST['game_title_image'] ) ) : '';
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

    // 広告プロバイダーの取得とバリデーション
    $ad_provider = isset( $_POST['ad_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['ad_provider'] ) ) : '';
    
    // 許可リスト検証
    $allowed_providers = array( 'none', 'adsense', 'adsterra' );
    if ( ! in_array( $ad_provider, $allowed_providers, true ) ) {
        $ad_provider = 'none';
    }
    
    // タイトル表示設定の取得
    $show_title_overlay = isset( $_POST['show_title_overlay'] ) ? '1' : '0';
    
    // タイトル文字色の取得とバリデーション
    $title_text_color = isset( $_POST['title_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['title_text_color'] ) ) : '#ffffff';
    // 色コードのバリデーション（無効な場合は白をデフォルトにする）
    if ( empty( $title_text_color ) ) {
        $title_text_color = '#ffffff';
    }
    
    $result = noveltool_save_game( $game_data );

    // ゲーム保存が成功した場合のみ、post metaを更新
    if ( $game_id && $result ) {
        update_post_meta( $game_id, 'noveltool_ad_provider', $ad_provider );
        update_post_meta( $game_id, 'noveltool_show_title_overlay', $show_title_overlay );
        update_post_meta( $game_id, 'noveltool_title_text_color', $title_text_color );
    }

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
 * シーンをゴミ箱へ移動します。ゴミ箱から復元または完全削除が可能です。
 *
 * @since 1.2.0
 */
function noveltool_admin_post_delete_scene() {
    // 基本権限チェック
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
        // 個別の投稿に対する削除権限チェック
        if ( ! current_user_can( 'delete_post', $scene_id ) ) {
            error_log( sprintf( '[NovelGamePlugin] User does not have permission to delete scene ID: %d', $scene_id ) );
            $redirect_url = $game_id 
                ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'error' => 'no_permission' ) )
                : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // 開始シーンかどうかをチェック
        $is_start_scene = get_post_meta( $scene_id, '_is_start_scene', true );
        $game_title = get_post_meta( $scene_id, '_game_title', true );

        // シーンをゴミ箱へ移動
        $result = wp_trash_post( $scene_id );

        if ( $result ) {
            // 開始シーンが削除された場合、start_scene_idをクリア
            if ( $is_start_scene && $game_title ) {
                noveltool_update_game_start_scene( $game_title, null );
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf( '[NovelGamePlugin] Start scene (ID: %d) was trashed. Cleared start_scene_id for game "%s"', $scene_id, $game_title ) );
                }
                
                // 開始シーンが削除されたことを通知
                $redirect_url = $game_id 
                    ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'success' => 'scene_trashed', 'notice' => 'start_scene_removed' ) )
                    : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
            } else {
                $redirect_url = $game_id 
                    ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'success' => 'scene_trashed' ) )
                    : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
            }
        } else {
            error_log( sprintf( '[NovelGamePlugin] Failed to trash scene ID: %d', $scene_id ) );
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
 * admin-post ハンドラー: シーン復元
 *
 * ごみ箱からシーンを復元します。
 *
 * @since 1.2.0
 */
function noveltool_admin_post_restore_scene() {
    // 基本権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_scenes' ) ) {
        $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;
        $redirect_url = $game_id 
            ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'status' => 'trash', 'error' => 'security' ) )
            : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    $scene_id = isset( $_POST['scene_id'] ) ? intval( wp_unslash( $_POST['scene_id'] ) ) : 0;
    $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;

    if ( $scene_id ) {
        // 個別の投稿に対する削除権限チェック（復元も削除権限で統一）
        if ( ! current_user_can( 'delete_post', $scene_id ) ) {
            error_log( sprintf( '[NovelGamePlugin] User does not have permission to restore scene ID: %d', $scene_id ) );
            $redirect_url = $game_id 
                ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'status' => 'trash', 'error' => 'no_restore_permission' ) )
                : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // シーンをごみ箱から復元
        $result = wp_untrash_post( $scene_id );

        if ( $result ) {
            // 復元成功：復元後のステータスを確認してメッセージに反映
            $restored_post = get_post( $scene_id );
            $restored_status = $restored_post ? $restored_post->post_status : '';
            
            // 開始シーンが復元された場合の処理
            $is_start_scene = get_post_meta( $scene_id, '_is_start_scene', true );
            $game_title = get_post_meta( $scene_id, '_game_title', true );
            
            if ( $is_start_scene && $game_title ) {
                // start_scene_idを再設定
                noveltool_update_game_start_scene( $game_title, $scene_id );
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf( '[NovelGamePlugin] Start scene (ID: %d) was restored. Updated start_scene_id for game "%s"', $scene_id, $game_title ) );
                }
            }
            
            // ステータスに応じたメッセージパラメータを設定
            $success_param = 'scene_restored';
            if ( 'draft' === $restored_status ) {
                $success_param = 'scene_restored_draft';
            } elseif ( 'publish' === $restored_status ) {
                $success_param = 'scene_restored_published';
            }
            
            // 開始シーンが復元された場合は通知を追加
            $url_params = array( 'success' => $success_param, 'status' => 'all' );
            if ( $is_start_scene ) {
                $url_params['notice'] = 'start_scene_restored';
            }
            
            // 復元成功：デフォルト（All）ビューにリダイレクト
            $redirect_url = $game_id 
                ? noveltool_get_game_manager_url( $game_id, 'scenes', $url_params )
                : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
        } else {
            error_log( sprintf( '[NovelGamePlugin] Failed to restore scene ID: %d', $scene_id ) );
            $redirect_url = $game_id 
                ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'status' => 'trash', 'error' => 'restore_failed' ) )
                : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
        }
    } else {
        $redirect_url = $game_id 
            ? noveltool_get_game_manager_url( $game_id, 'scenes', array( 'status' => 'trash', 'error' => 'invalid_id' ) )
            : admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_noveltool_restore_scene', 'noveltool_admin_post_restore_scene' );

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
 * エクスポート/インポート操作の履歴を記録
 *
 * 保存されるキー名と型: type (string), game_title (string), date (string), scenes (int), flags (int)
 *
 * @param string $type 操作タイプ ('export' または 'import')
 * @param string $game_title ゲームタイトル
 * @param int    $scenes シーン数
 * @param int    $flags フラグ数
 * @return bool 成功時true、失敗時false
 * @since 1.3.0
 */
function noveltool_log_transfer_operation( $type, $game_title, $scenes, $flags ) {
    $logs = get_option( 'noveltool_game_transfer_logs', array() );
    
    if ( ! is_array( $logs ) ) {
        $logs = array();
    }
    
    // 新しいログエントリを追加（型保証）
    $logs[] = array(
        'type'       => sanitize_text_field( $type ),
        'game_title' => sanitize_text_field( $game_title ),
        'date'       => current_time( 'mysql' ),
        'scenes'     => intval( $scenes ),
        'flags'      => intval( $flags ),
    );
    
    // 最大100件まで保持
    if ( count( $logs ) > 100 ) {
        $logs = array_slice( $logs, -100 );
    }
    
    return update_option( 'noveltool_game_transfer_logs', $logs );
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
            'original_post_id'        => $scene->ID,
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
            'dialogue_characters'     => get_post_meta( $scene->ID, '_dialogue_characters', true ),
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
 * 注意: このインポート処理はアトミックではありません。
 * シーン挿入途中でエラーが発生した場合、それまでに挿入されたシーンは残存します。
 * 将来的な改善予定: トランザクション処理またはロールバック機能の追加
 *
 * @param array $import_data インポートデータ
 * @param bool  $download_images 画像をダウンロードするかどうか
 * @return array|WP_Error インポート結果またはエラー
 * @since 1.3.0
 */
function noveltool_import_game_data( $import_data, $download_images = false ) {
    // データ検証（詳細な構造チェック）
    if ( ! is_array( $import_data ) || ! isset( $import_data['game'], $import_data['scenes'] ) ) {
        return new WP_Error( 'invalid_data', __( 'Invalid import data format.', 'novel-game-plugin' ) );
    }

    $game_data = $import_data['game'];
    
    // ゲームデータの型検証
    if ( ! is_array( $game_data ) ) {
        return new WP_Error( 'invalid_game_structure', __( 'Game data must be an object.', 'novel-game-plugin' ) );
    }
    
    // 必須フィールドの確認
    if ( empty( $game_data['title'] ) || ! is_string( $game_data['title'] ) ) {
        return new WP_Error( 'missing_title', __( 'Game title is required and must be a string.', 'novel-game-plugin' ) );
    }
    
    // シーンデータの型検証
    if ( ! is_array( $import_data['scenes'] ) ) {
        return new WP_Error( 'invalid_scenes_structure', __( 'Scenes must be an array.', 'novel-game-plugin' ) );
    }
    
    // シーンデータの各要素検証
    foreach ( $import_data['scenes'] as $index => $scene ) {
        if ( ! is_array( $scene ) ) {
            return new WP_Error( 
                'invalid_scene_structure', 
                sprintf( __( 'Scene at index %d must be an object.', 'novel-game-plugin' ), $index ) 
            );
        }
        if ( empty( $scene['title'] ) || ! is_string( $scene['title'] ) ) {
            return new WP_Error( 
                'invalid_scene_title', 
                sprintf( __( 'Scene at index %d must have a valid title.', 'novel-game-plugin' ), $index ) 
            );
        }
    }

    // タイトルの重複チェックと自動リネーム
    $original_title = sanitize_text_field( $game_data['title'] );
    $title = $original_title;
    $title_suffix = 2;
    
    while ( noveltool_get_game_by_title( $title ) ) {
        $title = $original_title . '-' . $title_suffix;
        $title_suffix++;
        
        // 無限ループ防止（最大100回試行）
        if ( $title_suffix > 100 ) {
            return new WP_Error( 'duplicate_title', __( 'Unable to create unique game title after 100 attempts.', 'novel-game-plugin' ) );
        }
    }
    
    // タイトルが変更された場合のログ記録
    if ( $title !== $original_title ) {
        error_log( sprintf( '[noveltool] Game title auto-renamed from "%s" to "%s" to avoid duplication', $original_title, $title ) );
    }

    
    // ゲームデータの文字列長制限とバリデーション
    $title_max_length = 200;
    $description_max_length = 5000;
    
    if ( mb_strlen( $title ) > $title_max_length ) {
        $title = mb_substr( $title, 0, $title_max_length );
        error_log( sprintf( '[noveltool] Game title truncated to %d characters', $title_max_length ) );
    }
    
    $description = isset( $game_data['description'] ) ? sanitize_textarea_field( $game_data['description'] ) : '';
    if ( mb_strlen( $description ) > $description_max_length ) {
        $description = mb_substr( $description, 0, $description_max_length );
        error_log( sprintf( '[noveltool] Game description truncated to %d characters', $description_max_length ) );
    }

    // ゲームデータのフィールドホワイトリスト
    $allowed_game_fields = array( 'title', 'description', 'title_image', 'game_over_text' );
    
    // ゲームデータを保存
    $new_game_data = array(
        'title'         => $title,
        'description'   => $description,
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

    // フラグマスタの保存（データ検証付き）
    if ( isset( $import_data['flags'] ) && is_array( $import_data['flags'] ) ) {
        // フラグマスタの検証と再構築
        $validated_flags = array();
        $next_flag_id = 1;
        
        foreach ( $import_data['flags'] as $flag ) {
            if ( ! is_array( $flag ) ) {
                continue;
            }
            
            // ID、name、descriptionの検証
            $flag_id = isset( $flag['id'] ) && is_numeric( $flag['id'] ) ? intval( $flag['id'] ) : $next_flag_id;
            $flag_name = isset( $flag['name'] ) && is_string( $flag['name'] ) ? sanitize_text_field( $flag['name'] ) : '';
            $flag_description = isset( $flag['description'] ) && is_string( $flag['description'] ) ? sanitize_text_field( $flag['description'] ) : '';
            
            if ( empty( $flag_name ) ) {
                continue; // 名前のないフラグはスキップ
            }
            
            $validated_flags[] = array(
                'id'          => $flag_id,
                'name'        => $flag_name,
                'description' => $flag_description,
            );
            
            $next_flag_id = max( $next_flag_id, $flag_id + 1 );
        }
        
        noveltool_save_game_flag_master( $new_game_data['title'], $validated_flags );
    }

    // シーンデータの保存（フェーズ1: シーン挿入とマッピング構築）
    $imported_scenes = 0;
    $old_post_id_to_new_map = array();
    $first_scene_post_id = null; // 画像ダウンロード時の親関連付け用
    $image_download_failures = 0; // 画像ダウンロード失敗件数
    
    foreach ( $import_data['scenes'] as $scene_index => $scene_data ) {
        // 大規模データ対応: タイムアウト防止
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 10 );
        }
        
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
        
        // 最初のシーンのIDを記録（画像ダウンロード時の親関連付け用）
        if ( null === $first_scene_post_id ) {
            $first_scene_post_id = $post_id;
        }
        
        // original_post_idが存在する場合は旧ID→新IDマップを構築
        if ( isset( $scene_data['original_post_id'] ) ) {
            $old_post_id = intval( $scene_data['original_post_id'] );
            $old_post_id_to_new_map[ $old_post_id ] = $post_id;
        }

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
            'dialogue_characters'     => '_dialogue_characters',
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
                        $downloaded_url = noveltool_download_image_to_media_library( $meta_value, $first_scene_post_id );
                        if ( ! is_wp_error( $downloaded_url ) ) {
                            $meta_value = $downloaded_url;
                        } else {
                            // 失敗ログ記録とカウント
                            $image_download_failures++;
                            error_log( sprintf( '[noveltool] Image download failed for URL: %s, reason: %s', $meta_value, $downloaded_url->get_error_message() ) );
                        }
                        // 失敗時は元URLをそのまま使用
                    }
                } else {
                    $meta_value = '';
                }
            } elseif ( $meta_key === '_is_ending' ) {
                // Boolean型
                $meta_value = (bool) $meta_value;
            } elseif ( $meta_key === '_dialogue_characters' ) {
                // セリフごとのキャラクター設定: 各位置の画像URLをサニタイズ
                if ( is_string( $meta_value ) ) {
                    $decoded = json_decode( $meta_value, true );
                    if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                        $meta_value = $decoded;
                    } else {
                        $meta_value = array();
                    }
                }
                if ( is_array( $meta_value ) ) {
                    $sanitized_dc = array();
                    foreach ( $meta_value as $idx => $char_set ) {
                        if ( ! is_array( $char_set ) ) {
                            continue;
                        }
                        $sanitized_dc[ $idx ] = array(
                            'left'   => isset( $char_set['left'] ) ? esc_url_raw( $char_set['left'] ) : '',
                            'center' => isset( $char_set['center'] ) ? esc_url_raw( $char_set['center'] ) : '',
                            'right'  => isset( $char_set['right'] ) ? esc_url_raw( $char_set['right'] ) : '',
                        );
                    }
                    $meta_value = wp_json_encode( $sanitized_dc, JSON_UNESCAPED_UNICODE );
                } else {
                    $meta_value = '';
                }
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
    
    // フェーズ2: 選択肢ID再マッピング処理（旧post_id→新post_idベース）
    $remapped_choices = 0;
    
    foreach ( $old_post_id_to_new_map as $old_id => $new_post_id ) {
        $choices_meta = get_post_meta( $new_post_id, '_choices', true );
        
        if ( empty( $choices_meta ) ) {
            continue;
        }
        
        // JSON形式の選択肢を処理
        $json_choices = json_decode( $choices_meta, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_choices ) ) {
            // JSON形式の選択肢: nextを旧post_id→新post_idマップで置換
            $modified = false;
            
            foreach ( $json_choices as &$choice ) {
                if ( isset( $choice['next'] ) && is_numeric( $choice['next'] ) ) {
                    $old_next = intval( $choice['next'] );
                    
                    // マップ内で旧post_idをキーとして検索
                    if ( isset( $old_post_id_to_new_map[ $old_next ] ) ) {
                        $choice['next'] = $old_post_id_to_new_map[ $old_next ];
                        $modified = true;
                    } else {
                        // マップに存在しない場合は警告ログ
                        error_log( sprintf( '[noveltool] Warning: Choice ID remapping failed for scene %d, original next=%d not found in post ID map', $new_post_id, $old_next ) );
                    }
                }
            }
            
            if ( $modified ) {
                update_post_meta( $new_post_id, '_choices', wp_json_encode( $json_choices, JSON_UNESCAPED_UNICODE ) );
                $remapped_choices++;
            }
        }
    }

    // インポート履歴を記録
    noveltool_log_transfer_operation( 'import', $new_game_data['title'], $imported_scenes, isset( $import_data['flags'] ) ? count( $import_data['flags'] ) : 0 );

    return array(
        'success'                 => true,
        'game_id'                 => $game_id,
        'imported_scenes'         => $imported_scenes,
        'remapped_choices'        => $remapped_choices,
        'original_title'          => $original_title,
        'renamed'                 => $title !== $original_title,
        'image_download_failures' => $image_download_failures,
    );
}

/**
 * インポート画像のシグネチャ情報を生成する
 *
 * 同一画像の再利用判定に必要なファイル名・MIME type・ファイルサイズ・SHA-256 を返す。
 *
 * @param string $tmp_path 一時ファイルパス
 * @param string $file_name 元のファイル名
 * @return array|WP_Error シグネチャ配列またはエラー
 * @since 1.5.0
 */
function noveltool_get_import_image_signature( $tmp_path, $file_name ) {
    if ( ! is_string( $tmp_path ) || ! file_exists( $tmp_path ) ) {
        return new WP_Error( 'missing_tmp_file', __( 'Imported image file was not found.', 'novel-game-plugin' ) );
    }

    $image_info = @getimagesize( $tmp_path );
    if ( false === $image_info || empty( $image_info['mime'] ) ) {
        return new WP_Error( 'invalid_image', __( 'Downloaded file is not a valid image.', 'novel-game-plugin' ) );
    }

    $file_size = filesize( $tmp_path );
    if ( false === $file_size ) {
        return new WP_Error( 'invalid_image_size', __( 'Failed to read imported image file size.', 'novel-game-plugin' ) );
    }

    $file_hash = hash_file( 'sha256', $tmp_path );
    if ( false === $file_hash ) {
        return new WP_Error( 'invalid_image_hash', __( 'Failed to calculate imported image hash.', 'novel-game-plugin' ) );
    }

    return array(
        'file_name' => sanitize_file_name( $file_name ),
        'mime_type' => sanitize_mime_type( $image_info['mime'] ),
        'file_size' => intval( $file_size ),
        'sha256'    => $file_hash,
    );
}

/**
 * 添付ファイルにインポート画像のシグネチャ情報を保存する
 *
 * @param int   $attachment_id 添付ファイルID
 * @param array $signature     シグネチャ配列
 * @since 1.5.0
 */
function noveltool_store_import_image_signature( $attachment_id, $signature ) {
    update_post_meta( $attachment_id, '_noveltool_import_file_name', $signature['file_name'] );
    update_post_meta( $attachment_id, '_noveltool_import_mime_type', $signature['mime_type'] );
    update_post_meta( $attachment_id, '_noveltool_import_file_size', (string) $signature['file_size'] );
    update_post_meta( $attachment_id, '_noveltool_import_sha256', $signature['sha256'] );
}

/**
 * シグネチャ一致する既存画像添付を検索する
 *
 * ファイル名・MIME type・ファイルサイズで候補を絞り込み、
 * 最後に SHA-256 で内容一致を確認して再利用可能な添付ファイルを返す。
 *
 * @param array $signature シグネチャ配列
 * @return int 添付ファイルID。一致しない場合は 0
 * @since 1.5.0
 */
function noveltool_find_existing_import_image_attachment( $signature ) {
    global $wpdb;

    $sanitized_file_name = sanitize_file_name( $signature['file_name'] );
    $file_name_like      = '%/' . $wpdb->esc_like( $sanitized_file_name );
    $candidate_ids  = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} file_meta
                ON p.ID = file_meta.post_id
                AND file_meta.meta_key = '_wp_attached_file'
            LEFT JOIN {$wpdb->postmeta} size_meta
                ON p.ID = size_meta.post_id
                AND size_meta.meta_key = '_noveltool_import_file_size'
            WHERE p.post_type = 'attachment'
                AND p.post_status = 'inherit'
                AND p.post_mime_type = %s
                AND ( file_meta.meta_value = %s OR file_meta.meta_value LIKE %s )
                AND ( size_meta.meta_value = %s OR size_meta.post_id IS NULL )
            ORDER BY p.ID ASC",
            $signature['mime_type'],
            $sanitized_file_name,
            $file_name_like,
            (string) $signature['file_size']
        )
    );

    foreach ( $candidate_ids as $candidate_id ) {
        $candidate_id = intval( $candidate_id );
        if ( $candidate_id < 1 ) {
            continue;
        }

        $attached_file = get_attached_file( $candidate_id );
        if ( ! is_string( $attached_file ) || ! file_exists( $attached_file ) ) {
            continue;
        }

        $candidate_size = filesize( $attached_file );
        if ( false === $candidate_size || intval( $candidate_size ) !== intval( $signature['file_size'] ) ) {
            continue;
        }

        $candidate_hash = hash_file( 'sha256', $attached_file );
        if ( false === $candidate_hash || $candidate_hash !== $signature['sha256'] ) {
            continue;
        }

        noveltool_store_import_image_signature( $candidate_id, $signature );

        return $candidate_id;
    }

    return 0;
}

/**
 * 一時画像ファイルから既存添付の再利用または新規添付作成を行う
 *
 * 同一リクエスト内では SHA-256 をキーに結果をキャッシュし、
 * 同じ画像が複数回参照されても media_handle_sideload() を 1 回だけ実行する。
 *
 * @param string $tmp_path        一時ファイルパス
 * @param string $file_name       元のファイル名
 * @param int    $parent_post_id  親投稿ID
 * @return int|WP_Error 添付ファイルIDまたはエラー
 * @since 1.5.0
 */
function noveltool_get_or_create_import_image_attachment( $tmp_path, $file_name, $parent_post_id = 0 ) {
    static $request_cache = array();

    $signature = noveltool_get_import_image_signature( $tmp_path, $file_name );
    if ( is_wp_error( $signature ) ) {
        return $signature;
    }

    if ( isset( $request_cache[ $signature['sha256'] ] ) ) {
        $cached_attachment_id = intval( $request_cache[ $signature['sha256'] ] );
        if ( $cached_attachment_id > 0 && get_post( $cached_attachment_id ) ) {
            return $cached_attachment_id;
        }
    }

    $existing_attachment_id = noveltool_find_existing_import_image_attachment( $signature );
    if ( $existing_attachment_id > 0 ) {
        $request_cache[ $signature['sha256'] ] = $existing_attachment_id;
        return $existing_attachment_id;
    }

    if ( ! function_exists( 'media_handle_sideload' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $file_array = array(
        'name'     => $signature['file_name'],
        'tmp_name' => $tmp_path,
    );

    $attachment_id = media_handle_sideload( $file_array, $parent_post_id );
    if ( is_wp_error( $attachment_id ) ) {
        return $attachment_id;
    }

    noveltool_store_import_image_signature( $attachment_id, $signature );
    $request_cache[ $signature['sha256'] ] = $attachment_id;

    return $attachment_id;
}

/**
 * 外部画像URLをメディアライブラリにダウンロード
 *
 * セキュリティ強化: URLスキーム検証、Content-Type確認、画像検証を実施
 *
 * @param string $image_url ダウンロードする画像のURL
 * @param int    $parent_post_id 親投稿ID（画像を関連付ける投稿）
 * @return string|WP_Error ダウンロードした画像のURLまたはエラー
 * @since 1.3.0
 */
function noveltool_download_image_to_media_library( $image_url, $parent_post_id = 0 ) {
    static $download_cache = array();

    if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
        return new WP_Error( 'invalid_url', __( 'Invalid image URL.', 'novel-game-plugin' ) );
    }

    // 同一URLが同一インポート処理内で複数回参照されるケースでは、
    // 既存添付の再検索だけでなく HTTP リクエスト自体も省略する
    if ( isset( $download_cache[ $image_url ] ) ) {
        return $download_cache[ $image_url ];
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
        $download_cache[ $image_url ] = $response;
        return $response;
    }
    
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    if ( $content_type && strpos( $content_type, 'image/' ) !== 0 ) {
        error_log( '[noveltool] Image download failed: Invalid Content-Type for ' . $image_url . ' (got: ' . $content_type . ')' );
        $error = new WP_Error( 'invalid_content_type', __( 'URL does not point to an image.', 'novel-game-plugin' ) );
        $download_cache[ $image_url ] = $error;
        return $error;
    }

    // WordPress HTTP APIを使用して画像をダウンロード
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $temp_file = download_url( $image_url, 10 );
    if ( is_wp_error( $temp_file ) ) {
        error_log( '[noveltool] Image download failed: ' . $image_url . ' reason: ' . $temp_file->get_error_message() );
        $download_cache[ $image_url ] = $temp_file;
        return $temp_file;
    }
    
    // 画像検証: getimagesizeで画像であることを確認
    $image_info = getimagesize( $temp_file );
    if ( $image_info === false ) {
        if ( file_exists( $temp_file ) ) {
            unlink( $temp_file );
        }
        error_log( '[noveltool] Image download failed: File is not a valid image ' . $image_url );
        $error = new WP_Error( 'invalid_image', __( 'Downloaded file is not a valid image.', 'novel-game-plugin' ) );
        $download_cache[ $image_url ] = $error;
        return $error;
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

    $attachment_id = noveltool_get_or_create_import_image_attachment( $file_array['tmp_name'], $file_array['name'], $parent_post_id );
    
    // 一時ファイルを削除
    if ( file_exists( $temp_file ) ) {
        unlink( $temp_file );
    }

    if ( is_wp_error( $attachment_id ) ) {
        error_log( '[noveltool] Image download failed: ' . $image_url . ' reason: ' . $attachment_id->get_error_message() );
        $download_cache[ $image_url ] = $attachment_id;
        return $attachment_id;
    }

    $attachment_url = wp_get_attachment_url( $attachment_id );
    $download_cache[ $image_url ] = $attachment_url;

    return $attachment_url;
}

/**
 * インポートファイルの最大サイズを返す
 *
 * filter `noveltool_json_import_max_size` / `noveltool_zip_import_max_size` で上書き可能。
 * デフォルト値は低スペック環境（共有レンタルサーバー等）でも問題ない保守的な値。
 *
 * @param string $type 'json' または 'zip'
 * @return int バイト単位の最大サイズ
 * @since 1.4.0
 */
function noveltool_get_import_max_size( $type = 'json' ) {
    if ( 'zip' === $type ) {
        return intval( apply_filters( 'noveltool_zip_import_max_size', 50 * 1024 * 1024 ) ); // 50MB
    }
    return intval( apply_filters( 'noveltool_json_import_max_size', 10 * 1024 * 1024 ) ); // 10MB
}

/**
 * 外部画像URLの安全性を検証する（SSRF対策）
 *
 * 以下をブロックする:
 * - http/https 以外のスキーム
 * - ホスト名なし
 * - localhost / loopback アドレス
 * - プライベート IP アドレス範囲（RFC1918）
 * - 予約済み IP アドレス
 *
 * @param string $url 検証するURL
 * @return bool 安全な外部URLであれば true
 * @since 1.4.0
 */
function noveltool_is_safe_external_url( $url ) {
    $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
    if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
        return false;
    }

    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( empty( $host ) ) {
        return false;
    }

    // localhost・ loopback 名前を明示的にブロック
    $blocked_hostnames = array( 'localhost', 'ip6-localhost', 'ip6-loopback' );
    if ( in_array( strtolower( $host ), $blocked_hostnames, true ) ) {
        return false;
    }

    // IPアドレスの場合: プライベート範囲・予約済み範囲をブロック
    if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
        if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return false;
        }
        // IPアドレスの場合はDNS解決不要
        return true;
    }

    // ホスト名の場合: DNS解決後のIPも検証（DNS Rebinding / 内部DNS対策）
    // gethostbynamel は IPv4 のみ返す。IPv6は検出できないが WordPress が主に利用する環境では許容範囲
    $resolved_ips = gethostbynamel( $host );
    if ( is_array( $resolved_ips ) ) {
        foreach ( $resolved_ips as $ip ) {
            if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return false;
            }
        }
    }

    return true;
}

/**
 * ゲームで使用されている画像URLを収集する
 *
 * タイトル画像・背景画像・キャラクター画像など全フィールドから
 * http/https スキームのURLのみを重複なく抽出して返す。
 *
 * @param array $export_data noveltool_export_game_data() が返すエクスポートデータ
 * @return string[] 画像URLの配列（重複なし）
 * @since 1.4.0
 */
function noveltool_collect_game_images( $export_data ) {
    $urls = array();

    // タイトル画像
    if ( ! empty( $export_data['game']['title_image'] ) ) {
        $urls[] = $export_data['game']['title_image'];
    }

    // シーン画像
    $image_fields = array( 'background_image', 'character_image', 'character_left', 'character_center', 'character_right' );
    foreach ( $export_data['scenes'] as $scene ) {
        foreach ( $image_fields as $field ) {
            if ( ! empty( $scene[ $field ] ) && is_string( $scene[ $field ] ) ) {
                $urls[] = $scene[ $field ];
            }
        }
        // dialogue_backgrounds 配列
        if ( ! empty( $scene['dialogue_backgrounds'] ) ) {
            $bg_data = $scene['dialogue_backgrounds'];
            if ( is_string( $bg_data ) ) {
                $bg_data = json_decode( $bg_data, true );
            }
            if ( is_array( $bg_data ) ) {
                foreach ( $bg_data as $bg ) {
                    if ( is_string( $bg ) && ! empty( $bg ) ) {
                        $urls[] = $bg;
                    }
                }
            }
        }
        // dialogue_characters 配列（セリフごとの個別キャラクター画像）
        if ( ! empty( $scene['dialogue_characters'] ) ) {
            $dc_data = $scene['dialogue_characters'];
            if ( is_string( $dc_data ) ) {
                $dc_data = json_decode( $dc_data, true );
            }
            if ( is_array( $dc_data ) ) {
                foreach ( $dc_data as $char_set ) {
                    if ( ! is_array( $char_set ) ) {
                        continue;
                    }
                    foreach ( array( 'left', 'center', 'right' ) as $pos ) {
                        if ( ! empty( $char_set[ $pos ] ) && is_string( $char_set[ $pos ] ) ) {
                            $urls[] = $char_set[ $pos ];
                        }
                    }
                }
            }
        }
    }

    // 重複排除 & http/https のみ許可
    $urls = array_unique( $urls );
    $urls = array_values( array_filter( $urls, function( $url ) {
        $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
        return in_array( $scheme, array( 'http', 'https' ), true );
    } ) );

    return $urls;
}

/**
 * 一時ファイルのクリーンアップキューにパスを追加する
 *
 * クリーンアップキューは `noveltool_tmp_cleanup_queue` オプションに保存され、
 * 日次 cron （`noveltool_daily_cleanup`）により削除される。
 *
 * $session_key を指定すると同一キーを持つ既存エントリを削除してから追加する（upsert）。
 * これにより、同一ステージングセッションのパートを複数回アップロードしても
 * 古いキューエントリが有効な一時ファイルを早期削除しない。
 *
 * @param array  $paths                        削除する一時ファイルパスの配列
 * @param int    $expires_at                   削除基準時刻（Unixタイムスタンプ）
 * @param string $session_key Optional.        セッション識別子。指定時は同一キーの既存エントリを新エントリで置き換える
 * @since 1.5.0
 */
function noveltool_enqueue_tmp_cleanup( $paths, $expires_at, $session_key = '' ) {
    $queue = get_option( 'noveltool_tmp_cleanup_queue', array() );

    // 同一 session_key を持つ既存エントリを削除（upsert）
    // session_key が未設定のエントリ、または指定キーと異なるエントリを残し、
    // 同一セッションの古いエントリのみを除去して再登録する
    if ( '' !== $session_key ) {
        $queue = array_values(
            array_filter(
                $queue,
                function ( $item ) use ( $session_key ) {
                    return ! isset( $item['session_key'] ) || $item['session_key'] !== $session_key;
                }
            )
        );
    }

    $entry = array(
        'paths'      => array_values( (array) $paths ),
        'expires_at' => intval( $expires_at ),
    );
    if ( '' !== $session_key ) {
        $entry['session_key'] = $session_key;
    }

    $queue[] = $entry;
    update_option( 'noveltool_tmp_cleanup_queue', $queue, false );
}

/**
 * 期限切れの一時ファイルをクリーンアップする
 *
 * `noveltool_daily_cleanup` cron アクションにフックして呼ばれる。
 *
 * @since 1.5.0
 */
function noveltool_run_tmp_cleanup() {
    $queue = get_option( 'noveltool_tmp_cleanup_queue', array() );
    if ( empty( $queue ) ) {
        return;
    }
    $now       = time();
    $remaining = array();
    foreach ( $queue as $item ) {
        if ( ! isset( $item['expires_at'] ) || $item['expires_at'] < $now ) {
            foreach ( (array) $item['paths'] as $path ) {
                if ( is_string( $path ) && file_exists( $path ) ) {
                    @unlink( $path );
                }
            }
        } else {
            $remaining[] = $item;
        }
    }
    update_option( 'noveltool_tmp_cleanup_queue', $remaining, false );
}
add_action( 'noveltool_daily_cleanup', 'noveltool_run_tmp_cleanup' );

/**
 * 日次クリーンアップの cron スケジュールを登録する
 *
 * @since 1.5.0
 */
function noveltool_maybe_schedule_daily_cleanup() {
    if ( ! wp_next_scheduled( 'noveltool_daily_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'noveltool_daily_cleanup' );
    }
}
add_action( 'init', 'noveltool_maybe_schedule_daily_cleanup' );

/**
 * 分割ZIPの1パートあたりのサイズ上限を返す
 *
 * フィルター `noveltool_split_zip_part_size` で上書き可能。
 * デフォルトは共有レンタルサーバーの upload_max_filesize 制約（一般的に 8MB）を
 * 考慮した保守的な 5MB。逐次アップロードで 1 リクエスト 1 ファイルを前提とする。
 *
 * @return int バイト単位の上限サイズ
 * @since 1.5.0
 */
function noveltool_get_split_zip_part_size() {
    return intval( apply_filters( 'noveltool_split_zip_part_size', 5 * 1024 * 1024 ) ); // 5MB
}

/**
 * 分割ZIPを使用するかどうかの閾値を返す
 *
 * フィルター `noveltool_split_zip_threshold` で上書き可能。
 * デフォルトはパートサイズと同値（推定画像合計がパートサイズを超えると分割開始）。
 * 推定画像合計がこの値を超える場合、分割ZIPエクスポートが使用される。
 *
 * @return int バイト単位の閾値
 * @since 1.5.0
 */
function noveltool_get_split_zip_threshold() {
    return intval( apply_filters( 'noveltool_split_zip_threshold', noveltool_get_split_zip_part_size() ) );
}

/**
 * エクスポート用に1枚の画像をバイナリとして取得する
 *
 * ローカル uploads 配下のファイルは直接読み込み、
 * 外部URLは wp_remote_get() で取得する。
 * セキュリティ: パストラバーサル対策・SSRF対策・リダイレクト無効化を実施。
 *
 * @param string $url 画像URL
 * @return string|false バイナリデータ、取得失敗時は false
 * @since 1.5.0
 */
function noveltool_fetch_image_for_export( $url ) {
    $upload_dir_info  = wp_upload_dir();
    $upload_base_url  = trailingslashit( $upload_dir_info['baseurl'] );
    $upload_base_path = $upload_dir_info['basedir'];

    $url_host         = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
    $site_host        = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    $upload_base_host = strtolower( (string) wp_parse_url( $upload_base_url, PHP_URL_HOST ) );
    $allowed_hosts    = array_filter( array_unique( array( $site_host, $upload_base_host ) ) );
    $url_path_part    = wp_parse_url( $url, PHP_URL_PATH );
    $base_path_part   = wp_parse_url( $upload_base_url, PHP_URL_PATH );

    if ( ! empty( $url_host ) && in_array( $url_host, $allowed_hosts, true )
        && ! empty( $url_path_part ) && ! empty( $base_path_part )
        && 0 === strpos( $url_path_part, $base_path_part ) ) {
        // ローカルファイルを直接読み込む
        $relative   = substr( $url_path_part, strlen( $base_path_part ) );
        $local_path = $upload_base_path . DIRECTORY_SEPARATOR . ltrim( $relative, '/\\' );
        $real_local = realpath( $local_path );
        $real_base  = realpath( $upload_base_path );
        if ( false === $real_local || false === $real_base || 0 !== strpos( $real_local, $real_base . DIRECTORY_SEPARATOR ) ) {
            error_log( '[noveltool] fetch_image_for_export: パストラバーサルを検出しスキップしました: ' . $url );
            return false;
        }
        $body = @file_get_contents( $real_local ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ( false === $body ) {
            error_log( '[noveltool] fetch_image_for_export: ローカルファイルの読み込みに失敗しました: ' . $local_path );
            return false;
        }
        return $body;
    }

    // 外部URL: SSRF対策を適用して wp_remote_get() でダウンロード
    if ( ! noveltool_is_safe_external_url( $url ) ) {
        error_log( '[noveltool] fetch_image_for_export: 安全でないURLをスキップしました: ' . $url );
        return false;
    }
    $response = wp_remote_get( $url, array(
        'timeout'     => 30,
        'redirection' => 0,
        'sslverify'   => apply_filters( 'https_ssl_verify', true ),
    ) );
    if ( is_wp_error( $response ) ) {
        error_log( '[noveltool] fetch_image_for_export: 画像のダウンロードに失敗しました: ' . $url );
        return false;
    }
    $response_code = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $response_code ) {
        if ( $response_code >= 300 && $response_code < 400 ) {
            error_log( '[noveltool] fetch_image_for_export: リダイレクトを検出しスキップしました (HTTP ' . $response_code . '): ' . $url );
        } else {
            error_log( '[noveltool] fetch_image_for_export: ダウンロードに失敗しました (HTTP ' . $response_code . '): ' . $url );
        }
        return false;
    }
    $resp_body = wp_remote_retrieve_body( $response );
    if ( empty( $resp_body ) ) {
        error_log( '[noveltool] fetch_image_for_export: 画像の本文が空です: ' . $url );
        return false;
    }
    return $resp_body;
}

/**
 * ゲームデータの画像合計サイズを推定する
 *
 * ローカル uploads 配下の画像は filesize() で実測値を取得する。
 * 外部URLは保守的な推定値 (512KB) を加算する。
 * エクスポート前の分割判定に使用する。
 *
 * @param array $export_data noveltool_export_game_data() が返すエクスポートデータ
 * @return int 推定合計サイズ（バイト）
 * @since 1.5.0
 */
function noveltool_estimate_game_images_size( $export_data ) {
    $image_urls = noveltool_collect_game_images( $export_data );
    if ( empty( $image_urls ) ) {
        return 0;
    }

    $total_size      = 0;
    $upload_dir_info = wp_upload_dir();
    $upload_base_url = trailingslashit( $upload_dir_info['baseurl'] );
    $upload_base_path = $upload_dir_info['basedir'];

    foreach ( $image_urls as $url ) {
        $url_host         = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $site_host        = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        $upload_base_host = strtolower( (string) wp_parse_url( $upload_base_url, PHP_URL_HOST ) );
        $allowed_hosts    = array_filter( array_unique( array( $site_host, $upload_base_host ) ) );
        $url_path_part    = wp_parse_url( $url, PHP_URL_PATH );
        $base_path_part   = wp_parse_url( $upload_base_url, PHP_URL_PATH );

        if ( ! empty( $url_host ) && in_array( $url_host, $allowed_hosts, true )
            && ! empty( $url_path_part ) && ! empty( $base_path_part )
            && 0 === strpos( $url_path_part, $base_path_part ) ) {
            $relative   = substr( $url_path_part, strlen( $base_path_part ) );
            $local_path = $upload_base_path . DIRECTORY_SEPARATOR . ltrim( $relative, '/\\' );
            $real_local = realpath( $local_path );
            $real_base  = realpath( $upload_base_path );
            if ( $real_local && $real_base && 0 === strpos( $real_local, $real_base . DIRECTORY_SEPARATOR ) ) {
                $size = @filesize( $real_local );
                if ( false !== $size ) {
                    $total_size += $size;
                    continue;
                }
            }
        }
        // 外部または不明なURLは保守的な推定値 (512KB) を加算
        $total_size += 512 * 1024;
    }

    return $total_size;
}

/**
 * エクスポートデータ内の画像URLを相対パスに置換したコピーを返す
 *
 * ZIP内の相対パス（images/xxx.jpg 等）への置換を行う。
 * ダウンロード失敗した画像は元の絶対URLを維持する。
 *
 * @param array $export_data       noveltool_export_game_data() が返すエクスポートデータ
 * @param array $url_to_zip_path   成功したURL => ZIPパス のマッピング配列
 * @return array 相対パス置換済みのエクスポートデータ
 * @since 1.5.0
 */
function noveltool_build_export_data_with_relative_paths( $export_data, $url_to_zip_path ) {
    $zip_data = $export_data;

    if ( ! empty( $zip_data['game']['title_image'] ) && isset( $url_to_zip_path[ $zip_data['game']['title_image'] ] ) ) {
        $zip_data['game']['title_image'] = $url_to_zip_path[ $zip_data['game']['title_image'] ];
    }

    $image_fields = array( 'background_image', 'character_image', 'character_left', 'character_center', 'character_right' );
    foreach ( $zip_data['scenes'] as &$scene ) {
        foreach ( $image_fields as $field ) {
            if ( ! empty( $scene[ $field ] ) && isset( $url_to_zip_path[ $scene[ $field ] ] ) ) {
                $scene[ $field ] = $url_to_zip_path[ $scene[ $field ] ];
            }
        }
        if ( ! empty( $scene['dialogue_backgrounds'] ) ) {
            $bg_data = $scene['dialogue_backgrounds'];
            if ( is_string( $bg_data ) ) {
                $bg_data = json_decode( $bg_data, true );
            }
            if ( is_array( $bg_data ) ) {
                foreach ( $bg_data as &$bg ) {
                    if ( is_string( $bg ) && isset( $url_to_zip_path[ $bg ] ) ) {
                        $bg = $url_to_zip_path[ $bg ];
                    }
                }
                unset( $bg );
                $scene['dialogue_backgrounds'] = $bg_data;
            }
        }
        if ( ! empty( $scene['dialogue_characters'] ) ) {
            $dc_data = $scene['dialogue_characters'];
            if ( is_string( $dc_data ) ) {
                $dc_data = json_decode( $dc_data, true );
            }
            if ( is_array( $dc_data ) ) {
                foreach ( $dc_data as &$char_set ) {
                    if ( ! is_array( $char_set ) ) {
                        continue;
                    }
                    foreach ( array( 'left', 'center', 'right' ) as $pos ) {
                        if ( ! empty( $char_set[ $pos ] ) && isset( $url_to_zip_path[ $char_set[ $pos ] ] ) ) {
                            $char_set[ $pos ] = $url_to_zip_path[ $char_set[ $pos ] ];
                        }
                    }
                }
                unset( $char_set );
                $scene['dialogue_characters'] = $dc_data;
            }
        }
    }
    unset( $scene );

    return $zip_data;
}

/**
 * ゲームデータをZIPアーカイブとして作成する
 *
 * JSON + images/ ディレクトリ構造でZIPを生成し、一時ファイルパスを返す。
 * 画像ダウンロード失敗は error_log に記録してスキップ（処理は継続）。
 *
 * @param array  $export_data noveltool_export_game_data() が返すエクスポートデータ
 * @param string $game_title  ゲームタイトル（ファイル名用）
 * @return string|WP_Error 生成したZIPの一時ファイルパス、または WP_Error
 * @since 1.4.0
 */
function noveltool_export_game_data_as_zip( $export_data, $game_title ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'no_ziparchive', __( 'ZipArchive is not available on this server.', 'novel-game-plugin' ) );
    }

    // タイムアウト・メモリ制限の緩和
    @set_time_limit( 120 );
    @ini_set( 'memory_limit', '256M' );

    $image_urls  = noveltool_collect_game_images( $export_data );
    $url_to_file = array(); // 元URL => images/ファイル名 のファイル名候補マッピング

    // 画像ファイル名を決定（重複を避けるために連番付与）
    $used_names = array();
    foreach ( $image_urls as $url ) {
        $basename = sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) );
        if ( empty( $basename ) || strpos( $basename, '.' ) === false ) {
            $basename = 'image-' . md5( $url ) . '.jpg';
        }
        // 重複回避
        $original = $basename;
        $counter  = 1;
        while ( in_array( $basename, $used_names, true ) ) {
            $info     = pathinfo( $original );
            $basename = $info['filename'] . '-' . $counter . '.' . $info['extension'];
            $counter++;
        }
        $used_names[]         = $basename;
        $url_to_file[ $url ]  = 'images/' . $basename;
    }

    // 一時ZIPファイルを作成
    $tmp_zip = wp_tempnam( sanitize_file_name( $game_title ) . '.zip' );
    if ( ! $tmp_zip ) {
        return new WP_Error( 'tempnam_failed', __( 'Failed to create temporary ZIP file.', 'novel-game-plugin' ) );
    }

    $zip = new ZipArchive();
    if ( true !== $zip->open( $tmp_zip, ZipArchive::OVERWRITE ) ) {
        return new WP_Error( 'zip_open_failed', __( 'Failed to open ZIP archive for writing.', 'novel-game-plugin' ) );
    }

    // 画像を取得してZIPに追加し、成功したURLのみ相対パスマッピングに記録
    $succeeded_url_to_file = array(); // ダウンロード成功かつZIP追加に成功したURL => 相対パスのマッピング
    $upload_dir_info  = wp_upload_dir();
    $upload_base_url  = trailingslashit( $upload_dir_info['baseurl'] );
    $upload_base_path = $upload_dir_info['basedir'];
    foreach ( $url_to_file as $url => $zip_path ) {
        $body = false;

        // 同一サイトの uploads ディレクトリ URL かチェック
        // ホストが自サイト（home_url() または wp_upload_dir()['baseurl']）と一致し、
        // かつパスが uploads ディレクトリ配下の場合のみローカルファイル扱いにする
        // wp_upload_dir()['baseurl'] が home_url() と別ホストの構成（別サブドメイン等）でも動作するよう両方を許可
        $url_host         = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
        $site_host        = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        $upload_base_host = strtolower( (string) wp_parse_url( $upload_base_url, PHP_URL_HOST ) );
        $allowed_hosts    = array_filter( array_unique( array( $site_host, $upload_base_host ) ) );
        $url_path_part    = wp_parse_url( $url, PHP_URL_PATH );
        $base_path_part   = wp_parse_url( $upload_base_url, PHP_URL_PATH );
        if ( ! empty( $url_host ) && in_array( $url_host, $allowed_hosts, true ) && ! empty( $url_path_part ) && ! empty( $base_path_part ) && 0 === strpos( $url_path_part, $base_path_part ) ) {
            // ローカルファイルパスへ変換して直接読み込む（wp_remote_get不使用）
            $relative   = substr( $url_path_part, strlen( $base_path_part ) );
            $local_path = $upload_base_path . DIRECTORY_SEPARATOR . ltrim( $relative, '/\\' );
            // パストラバーサル対策: realpath で正規化して uploads ディレクトリ内であることを確認
            $real_local = realpath( $local_path );
            $real_base  = realpath( $upload_base_path );
            if ( false === $real_local || false === $real_base || 0 !== strpos( $real_local, $real_base . DIRECTORY_SEPARATOR ) ) {
                error_log( '[noveltool] ZIP export: パストラバーサルを検出しスキップしました: ' . $url );
                continue;
            }
            $file_body = @file_get_contents( $real_local ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            if ( false === $file_body ) {
                error_log( '[noveltool] ZIP export: ローカルファイルの読み込みに失敗しました: ' . $local_path );
                continue;
            }
            $body = $file_body;
        } else {
            // 外部URL: SSRF対策を適用してから wp_remote_get() でダウンロード
            if ( ! noveltool_is_safe_external_url( $url ) ) {
                error_log( '[noveltool] ZIP export: 安全でないURLをスキップしました: ' . $url );
                continue;
            }
            // リダイレクト無効化: リダイレクト先が内部IPに向く攻撃（SSRF via redirect）を防ぐ
            $response = wp_remote_get( $url, array(
                'timeout'     => 30,
                'redirection' => 0,
                'sslverify'   => apply_filters( 'https_ssl_verify', true ),
            ) );
            if ( is_wp_error( $response ) ) {
                error_log( '[noveltool] ZIP export: 画像のダウンロードに失敗しました: ' . $url );
                continue;
            }
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $response_code ) {
                if ( $response_code >= 300 && $response_code < 400 ) {
                    // リダイレクト無効化のため 30x はセキュリティ上の理由で拒否
                    error_log( '[noveltool] ZIP export: リダイレクトを検出しセキュリティ上の理由でスキップしました (HTTP ' . $response_code . '): ' . $url );
                } else {
                    error_log( '[noveltool] ZIP export: 画像のダウンロードに失敗しました (HTTP ' . $response_code . '): ' . $url );
                }
                continue;
            }
            $resp_body = wp_remote_retrieve_body( $response );
            if ( empty( $resp_body ) ) {
                error_log( '[noveltool] ZIP export: 画像の本文が空です: ' . $url );
                continue;
            }
            $body = $resp_body;
        }

        if ( ! $zip->addFromString( $zip_path, $body ) ) {
            error_log( '[noveltool] ZIP export: ZIPへの画像追加に失敗しました: ' . $url );
            continue;
        }
        $succeeded_url_to_file[ $url ] = $zip_path;
    }

    // 実際にZIPへ追加できた画像のみ相対パスへ置換したJSONコピーを作成
    // ダウンロード失敗した画像は元の絶対URLを維持する
    $zip_data = $export_data;
    if ( ! empty( $zip_data['game']['title_image'] ) && isset( $succeeded_url_to_file[ $zip_data['game']['title_image'] ] ) ) {
        $zip_data['game']['title_image'] = $succeeded_url_to_file[ $zip_data['game']['title_image'] ];
    }
    $image_fields = array( 'background_image', 'character_image', 'character_left', 'character_center', 'character_right' );
    foreach ( $zip_data['scenes'] as &$scene ) {
        foreach ( $image_fields as $field ) {
            if ( ! empty( $scene[ $field ] ) && isset( $succeeded_url_to_file[ $scene[ $field ] ] ) ) {
                $scene[ $field ] = $succeeded_url_to_file[ $scene[ $field ] ];
            }
        }
        if ( ! empty( $scene['dialogue_backgrounds'] ) ) {
            $bg_data = $scene['dialogue_backgrounds'];
            if ( is_string( $bg_data ) ) {
                $bg_data = json_decode( $bg_data, true );
            }
            if ( is_array( $bg_data ) ) {
                foreach ( $bg_data as &$bg ) {
                    if ( is_string( $bg ) && isset( $succeeded_url_to_file[ $bg ] ) ) {
                        $bg = $succeeded_url_to_file[ $bg ];
                    }
                }
                unset( $bg );
                $scene['dialogue_backgrounds'] = $bg_data;
            }
        }
        // dialogue_characters 配列（セリフごとの個別キャラクター画像）の相対パス置換
        if ( ! empty( $scene['dialogue_characters'] ) ) {
            $dc_data = $scene['dialogue_characters'];
            if ( is_string( $dc_data ) ) {
                $dc_data = json_decode( $dc_data, true );
            }
            if ( is_array( $dc_data ) ) {
                foreach ( $dc_data as &$char_set ) {
                    if ( ! is_array( $char_set ) ) {
                        continue;
                    }
                    foreach ( array( 'left', 'center', 'right' ) as $pos ) {
                        if ( ! empty( $char_set[ $pos ] ) && isset( $succeeded_url_to_file[ $char_set[ $pos ] ] ) ) {
                            $char_set[ $pos ] = $succeeded_url_to_file[ $char_set[ $pos ] ];
                        }
                    }
                }
                unset( $char_set );
                $scene['dialogue_characters'] = $dc_data;
            }
        }
    }
    unset( $scene );

    // JSON追加（ダウンロード成功分のみ相対パス置換済み）
    $json_content = wp_json_encode( $zip_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    if ( ! $zip->addFromString( 'game-data.json', $json_content ) ) {
        error_log( '[noveltool] ZIP export: game-data.json のZIPへの追加に失敗しました。' );
        $zip->close();
        @unlink( $tmp_zip );
        return new WP_Error( 'zip_json_write_failed', __( 'Failed to write game-data.json to ZIP.', 'novel-game-plugin' ) );
    }

    $zip->close();

    return $tmp_zip;
}

/**
 * ゲームデータを分割ZIPアーカイブとして作成する
 *
 * 画像合計サイズが単一ZIPの上限を超える場合に使用する。
 * 各パートに manifest.json を含む。Part 1 のみ game-data.json を含む。
 * manifest には export_id・part_number・total_parts・files 一覧等を含む。
 *
 * @param array  $export_data noveltool_export_game_data() が返すエクスポートデータ
 * @param string $game_title  ゲームタイトル（ファイル名用）
 * @return array|WP_Error 成功時: array( 'export_id', 'total_parts', 'zip_paths', 'game_title' ) / 失敗時: WP_Error
 * @since 1.5.0
 */
function noveltool_export_game_data_as_split_zips( $export_data, $game_title ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'no_ziparchive', __( 'ZipArchive is not available on this server.', 'novel-game-plugin' ) );
    }

    @set_time_limit( 300 );
    @ini_set( 'memory_limit', '256M' );

    $part_size_limit = noveltool_get_split_zip_part_size();
    $export_id       = 'ng-' . substr( md5( uniqid( '', true ) ), 0, 16 );
    $export_date     = current_time( 'mysql' );

    // 画像URLを収集し、ZIP内パス候補（images/ファイル名）を決定
    $image_urls  = noveltool_collect_game_images( $export_data );
    $url_to_file = array();
    $used_names  = array();
    foreach ( $image_urls as $url ) {
        $basename = sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) );
        if ( empty( $basename ) || strpos( $basename, '.' ) === false ) {
            $basename = 'image-' . md5( $url ) . '.jpg';
        }
        $original = $basename;
        $counter  = 1;
        while ( in_array( $basename, $used_names, true ) ) {
            $info     = pathinfo( $original );
            $basename = $info['filename'] . '-' . $counter . '.' . $info['extension'];
            $counter++;
        }
        $used_names[]        = $basename;
        $url_to_file[ $url ] = 'images/' . $basename;
    }

    // 画像バイナリをダウンロード
    $succeeded_url_to_file = array();
    $image_bodies          = array(); // zip_path => binary
    foreach ( $url_to_file as $url => $zip_path ) {
        $body = noveltool_fetch_image_for_export( $url );
        if ( false === $body ) {
            continue;
        }
        $succeeded_url_to_file[ $url ] = $zip_path;
        $image_bodies[ $zip_path ]     = $body;
    }

    // 相対パス置換済みの game-data.json コンテンツを生成
    $zip_data     = noveltool_build_export_data_with_relative_paths( $export_data, $succeeded_url_to_file );
    $json_content = wp_json_encode( $zip_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

    // 画像をパートに分割（Part 1 は game-data.json + manifest 分を加算）
    $parts        = array();
    $current_part = array();
    $current_size = strlen( $json_content ) + 2048; // JSON + manifest オーバーヘッド

    foreach ( $image_bodies as $zip_path => $body ) {
        $image_size = strlen( $body );
        if ( ! empty( $current_part ) && ( $current_size + $image_size ) > $part_size_limit ) {
            $parts[]      = $current_part;
            $current_part = array();
            $current_size = 2048; // manifest オーバーヘッドのみ
        }
        $current_part[ $zip_path ] = $body;
        $current_size             += $image_size;
    }
    $parts[] = $current_part; // 最後のパート（画像なしでも最低1パート確保）

    $total_parts   = count( $parts );
    $zip_tmp_paths = array();

    for ( $i = 0; $i < $total_parts; $i++ ) {
        $part_number = $i + 1;
        $part_files  = $parts[ $i ];

        $manifest = array(
            'version'       => '1.0',
            'export_id'     => $export_id,
            'game_title'    => $game_title,
            'export_date'   => $export_date,
            'part_number'   => $part_number,
            'total_parts'   => $total_parts,
            'has_game_data' => ( 1 === $part_number ),
            'files'         => array_keys( $part_files ),
        );

        $tmp_zip = wp_tempnam( sanitize_file_name( $game_title ) . '-part' . $part_number . '.zip' );
        if ( ! $tmp_zip ) {
            foreach ( $zip_tmp_paths as $path ) {
                @unlink( $path );
            }
            return new WP_Error( 'tempnam_failed', __( 'Failed to create temporary ZIP file.', 'novel-game-plugin' ) );
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $tmp_zip, ZipArchive::OVERWRITE ) ) {
            @unlink( $tmp_zip );
            foreach ( $zip_tmp_paths as $path ) {
                @unlink( $path );
            }
            return new WP_Error( 'zip_open_failed', __( 'Failed to open ZIP archive for writing.', 'novel-game-plugin' ) );
        }

        // manifest.json を追加
        $manifest_json = wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        if ( ! $zip->addFromString( 'manifest.json', $manifest_json ) ) {
            $zip->close();
            @unlink( $tmp_zip );
            foreach ( $zip_tmp_paths as $path ) {
                @unlink( $path );
            }
            return new WP_Error( 'zip_manifest_write_failed', __( 'Failed to write manifest.json to ZIP.', 'novel-game-plugin' ) );
        }

        // Part 1 のみ game-data.json を追加
        if ( 1 === $part_number ) {
            if ( ! $zip->addFromString( 'game-data.json', $json_content ) ) {
                $zip->close();
                @unlink( $tmp_zip );
                foreach ( $zip_tmp_paths as $path ) {
                    @unlink( $path );
                }
                return new WP_Error( 'zip_json_write_failed', __( 'Failed to write game-data.json to ZIP.', 'novel-game-plugin' ) );
            }
        }

        // 画像を追加
        foreach ( $part_files as $zip_path => $body ) {
            if ( ! $zip->addFromString( $zip_path, $body ) ) {
                error_log( '[noveltool] 分割ZIPエクスポート: ZIPへの画像追加に失敗しました: ' . $zip_path );
            }
        }

        $zip->close();
        $zip_tmp_paths[] = $tmp_zip;
    }

    return array(
        'export_id'   => $export_id,
        'total_parts' => $total_parts,
        'zip_paths'   => $zip_tmp_paths,
        'game_title'  => $game_title,
    );
}

/**
 * AJAXハンドラー: ゲームデータのエクスポート
 *
 * エラーメッセージは静的文言のみ利用し外部入力を直接連結しない方針
 * ZIP形式エクスポート時はバイナリを直接ストリーム出力してメモリ使用量を抑制する。
 *
 * @since 1.3.0
 */
function noveltool_ajax_export_game() {
    // 権限チェック（filter で上書き可能、デフォルトは管理者権限）
    $export_capability = apply_filters( 'noveltool_export_capability', 'manage_options' );
    if ( ! current_user_can( $export_capability ) ) {
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

    // ZIP形式オプション
    $export_as_zip = isset( $_POST['export_as_zip'] ) && 'true' === $_POST['export_as_zip'];

    // エクスポート実行
    $export_data = noveltool_export_game_data( $game_id );
    if ( is_wp_error( $export_data ) ) {
        wp_send_json_error( array( 'message' => $export_data->get_error_message() ) );
    }

    // ZIP形式でエクスポート: バイナリを直接ストリーム出力（メモリ安全）
    if ( $export_as_zip ) {
        $game_title = isset( $export_data['game']['title'] ) ? $export_data['game']['title'] : 'game';

        // 分割判定: 推定画像サイズが閾値を超える場合は分割ZIPを使用
        $split_threshold = noveltool_get_split_zip_threshold();
        $estimated_size  = noveltool_estimate_game_images_size( $export_data );

        if ( $estimated_size > $split_threshold ) {
            // 分割ZIPエクスポート
            $split_result = noveltool_export_game_data_as_split_zips( $export_data, $game_title );
            if ( is_wp_error( $split_result ) ) {
                error_log( '[noveltool] 分割ZIPエクスポート失敗: ' . $split_result->get_error_message() . ' – JSONにフォールバック。' );
                wp_send_json_success( array(
                    'data'     => $export_data,
                    'filename' => sanitize_file_name( $game_title . '-export.json' ),
                    'format'   => 'json',
                    'warning'  => __( 'ZIP creation failed. Falling back to JSON format.', 'novel-game-plugin' ),
                ) );
            }

            // 各パートをトランジェントに保存しメタ情報を返す
            $parts_info = array();
            foreach ( $split_result['zip_paths'] as $i => $part_zip_path ) {
                $part_number   = $i + 1;
                $transient_key = 'noveltool_szp_' . md5( $split_result['export_id'] ) . '_' . $part_number;
                set_transient(
                    $transient_key,
                    array(
                        'path'        => $part_zip_path,
                        'game_title'  => $game_title,
                        'total_parts' => $split_result['total_parts'],
                    ),
                    HOUR_IN_SECONDS
                );
                $parts_info[] = array(
                    'part'     => $part_number,
                    'filename' => sanitize_file_name( $game_title . '-export-part' . $part_number . '-of-' . $split_result['total_parts'] . '.zip' ),
                    'size'     => @filesize( $part_zip_path ),
                );
            }

            // クリーンアップキューにエクスポートZIPパスを登録（トランジェント（1時間）+余裕時間後に削除）
            noveltool_enqueue_tmp_cleanup( $split_result['zip_paths'], time() + HOUR_IN_SECONDS + 10 * MINUTE_IN_SECONDS );

            wp_send_json_success( array(
                'format'      => 'split_zip',
                'export_id'   => $split_result['export_id'],
                'total_parts' => $split_result['total_parts'],
                'parts'       => $parts_info,
            ) );
        }

        $zip_path = noveltool_export_game_data_as_zip( $export_data, $game_title );
        if ( is_wp_error( $zip_path ) ) {
            // ZIP作成失敗時はJSON形式にフォールバック
            error_log( '[noveltool] ZIP export failed: ' . $zip_path->get_error_message() . ' – falling back to JSON.' );
            wp_send_json_success( array(
                'data'     => $export_data,
                'filename' => sanitize_file_name( $game_title . '-export.json' ),
                'format'   => 'json',
                'warning'  => __( 'ZIP creation failed. Falling back to JSON format.', 'novel-game-plugin' ),
            ) );
        }

        // 出力バッファをすべてクリアしてバイナリ直接送信
        while ( ob_get_level() ) {
            ob_end_clean();
        }
        // ASCII フォールバック（非ASCII文字は除去）と UTF-8 filename*= の両方を設定して文字化けを防ぐ
        $filename_ascii   = sanitize_file_name( $game_title . '-export.zip' );
        $filename_encoded = rawurlencode( $game_title . '-export.zip' );
        $filesize = @filesize( $zip_path );
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $filename_ascii . '"; filename*=UTF-8\'\'' . $filename_encoded );
        if ( false !== $filesize ) {
            header( 'Content-Length: ' . $filesize );
        }
        header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );
        $bytes_sent = readfile( $zip_path );
        @unlink( $zip_path );
        if ( false === $bytes_sent ) {
            error_log( '[noveltool] ZIP export: readfile() failed to read ZIP file: ' . $zip_path );
            if ( ! headers_sent() ) {
                http_response_code( 500 );
            }
        }
        wp_die();
    }

    wp_send_json_success( array(
        'data'     => $export_data,
        'filename' => sanitize_file_name( $export_data['game']['title'] . '-export.json' ),
        'format'   => 'json',
    ) );
}
add_action( 'wp_ajax_noveltool_export_game', 'noveltool_ajax_export_game' );

/**
 * AJAXハンドラー: エクスポートサイズ情報の取得
 *
 * ゲーム選択時にエクスポートが単一ZIPか分割ZIPかを事前に通知するための情報を返す。
 * 実際の画像ダウンロードを行わず、ローカルファイルサイズのみで推定する。
 *
 * @since 1.5.0
 */
function noveltool_ajax_get_export_size_info() {
    $export_capability = apply_filters( 'noveltool_export_capability', 'manage_options' );
    if ( ! current_user_can( $export_capability ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'novel-game-plugin' ) ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'noveltool_export_game' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'novel-game-plugin' ) ) );
    }

    $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;
    if ( ! $game_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid game ID.', 'novel-game-plugin' ) ) );
    }

    $export_data = noveltool_export_game_data( $game_id );
    if ( is_wp_error( $export_data ) ) {
        wp_send_json_error( array( 'message' => $export_data->get_error_message() ) );
    }

    $image_urls      = noveltool_collect_game_images( $export_data );
    $image_count     = count( $image_urls );
    $estimated_size  = noveltool_estimate_game_images_size( $export_data );
    $split_threshold = noveltool_get_split_zip_threshold();
    $part_size_limit = noveltool_get_split_zip_part_size();
    $will_split      = ( $estimated_size > $split_threshold );
    $estimated_parts = $will_split ? max( 1, (int) ceil( $estimated_size / $part_size_limit ) ) : 1;

    wp_send_json_success( array(
        'image_count'     => $image_count,
        'estimated_size'  => $estimated_size,
        'estimated_parts' => $estimated_parts,
        'will_split'      => $will_split,
        'part_size_mb'    => round( $part_size_limit / ( 1024 * 1024 ) ),
    ) );
}
add_action( 'wp_ajax_noveltool_get_export_size_info', 'noveltool_ajax_get_export_size_info' );

/**
 * AJAXハンドラー: 分割ZIPパートのダウンロード
 *
 * noveltool_ajax_export_game() がトランジェントに保存した分割ZIPをストリーム出力する。
 * ダウンロード完了後にトランジェントと一時ファイルを削除する。
 *
 * @since 1.5.0
 */
function noveltool_ajax_download_split_zip_part() {
    $export_capability = apply_filters( 'noveltool_export_capability', 'manage_options' );
    if ( ! current_user_can( $export_capability ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'novel-game-plugin' ) ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'noveltool_export_game' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'novel-game-plugin' ) ) );
    }

    $export_id   = isset( $_POST['export_id'] ) ? sanitize_text_field( wp_unslash( $_POST['export_id'] ) ) : '';
    $part_number = isset( $_POST['part'] ) ? intval( wp_unslash( $_POST['part'] ) ) : 0;

    if ( empty( $export_id ) || $part_number < 1 ) {
        wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'novel-game-plugin' ) ) );
    }

    $transient_key = 'noveltool_szp_' . md5( $export_id ) . '_' . $part_number;
    $part_data     = get_transient( $transient_key );

    if ( false === $part_data || empty( $part_data['path'] ) ) {
        wp_send_json_error( array( 'message' => __( 'Download link has expired. Please export again.', 'novel-game-plugin' ) ) );
    }

    $zip_path = $part_data['path'];
    if ( ! file_exists( $zip_path ) ) {
        delete_transient( $transient_key );
        wp_send_json_error( array( 'message' => __( 'ZIP file not found. Please export again.', 'novel-game-plugin' ) ) );
    }

    $filename_ascii   = sanitize_file_name( $part_data['game_title'] . '-export-part' . $part_number . '-of-' . $part_data['total_parts'] . '.zip' );
    $filename_encoded = rawurlencode( $part_data['game_title'] . '-export-part' . $part_number . '-of-' . $part_data['total_parts'] . '.zip' );
    $filesize         = @filesize( $zip_path );

    while ( ob_get_level() ) {
        ob_end_clean();
    }
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . $filename_ascii . '"; filename*=UTF-8\'\'' . $filename_encoded );
    if ( false !== $filesize ) {
        header( 'Content-Length: ' . $filesize );
    }
    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Pragma: no-cache' );
    readfile( $zip_path );

    // 一時ファイルとトランジェントを削除
    @unlink( $zip_path );
    delete_transient( $transient_key );

    wp_die();
}
add_action( 'wp_ajax_noveltool_download_split_zip_part', 'noveltool_ajax_download_split_zip_part' );

/**
 * ZIPアーカイブからゲームデータをインポートする
 *
 * ZIPを解凍し game-data.json を読み込む。
 * JSON内の相対パス（images/ プレフィックス）の画像は WordPress メディアライブラリにアップロードし、
 * 絶対URLに変換してからゲームデータを作成する。
 * パストラバーサル対策として images/ プレフィックス以外のパスはスキップする。
 * Zip bomb対策として展開前にファイル数・単一サイズ・総サイズを検証する。
 *
 * @param string $zip_path アップロードされたZIPファイルの一時パス
 * @return array|WP_Error インポート結果または WP_Error
 * @since 1.4.0
 */
function noveltool_import_from_zip( $zip_path ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'no_ziparchive', __( 'ZipArchive is not available on this server.', 'novel-game-plugin' ) );
    }

    @set_time_limit( 120 );
    @ini_set( 'memory_limit', '256M' );

    $zip = new ZipArchive();
    if ( true !== $zip->open( $zip_path ) ) {
        return new WP_Error( 'zip_open_failed', __( 'Failed to open ZIP archive.', 'novel-game-plugin' ) );
    }

    // --- Zip bomb対策: 展開前にサイズ・ファイル数を検証 ---
    $zip_max_files       = intval( apply_filters( 'noveltool_zip_import_max_files', 100 ) );
    $zip_max_single_size = intval( apply_filters( 'noveltool_zip_import_max_single_size', 10 * 1024 * 1024 ) ); // 10MB
    $zip_max_total_size  = intval( apply_filters( 'noveltool_zip_import_max_total_size', 50 * 1024 * 1024 ) );  // 50MB

    if ( $zip->numFiles > $zip_max_files ) {
        $zip->close();
        return new WP_Error( 'zip_too_many_files', __( 'ZIP archive contains too many files.', 'novel-game-plugin' ) );
    }

    $total_uncompressed = 0;
    for ( $j = 0; $j < $zip->numFiles; $j++ ) {
        $stat = $zip->statIndex( $j );
        if ( false === $stat ) {
            continue;
        }
        if ( $stat['size'] > $zip_max_single_size ) {
            $zip->close();
            return new WP_Error( 'zip_file_too_large', __( 'A file in the ZIP archive exceeds the size limit.', 'novel-game-plugin' ) );
        }
        $total_uncompressed += $stat['size'];
        if ( $total_uncompressed > $zip_max_total_size ) {
            $zip->close();
            return new WP_Error( 'zip_total_too_large', __( 'Total extracted size of ZIP archive exceeds the limit.', 'novel-game-plugin' ) );
        }
    }
    // --- Zip bomb対策ここまで ---

    // game-data.json を取得
    $json_content = $zip->getFromName( 'game-data.json' );
    if ( false === $json_content ) {
        $zip->close();
        return new WP_Error( 'no_json', __( 'game-data.json not found in ZIP archive.', 'novel-game-plugin' ) );
    }

    $import_data = json_decode( $json_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        $zip->close();
        return new WP_Error( 'invalid_json', __( 'Invalid JSON encoding.', 'novel-game-plugin' ) );
    }

    if ( ! is_array( $import_data ) || ! isset( $import_data['game'] ) || ! isset( $import_data['scenes'] ) ) {
        $zip->close();
        return new WP_Error( 'invalid_structure', __( 'Missing required "game" or "scenes" keys.', 'novel-game-plugin' ) );
    }

    // ZIP内の画像を収集し一時ディレクトリに解凍してメディアライブラリへアップロード
    $relative_to_url = array(); // 相対パス => メディアライブラリURL
    for ( $i = 0; $i < $zip->numFiles; $i++ ) {
        $zip_entry_name = $zip->getNameIndex( $i );
        // パストラバーサル対策: images/ で始まるエントリのみ許可
        if ( strpos( $zip_entry_name, 'images/' ) !== 0 ) {
            continue;
        }
        $basename = basename( $zip_entry_name );
        if ( empty( $basename ) ) {
            continue;
        }
        // 許可拡張子チェック
        $ext = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
            continue;
        }

        $image_content = $zip->getFromIndex( $i );
        if ( false === $image_content || empty( $image_content ) ) {
            continue;
        }

        // 一時ファイルに書き出す
        $tmp = wp_tempnam( $basename );
        if ( ! $tmp ) {
            continue;
        }
        if ( false === file_put_contents( $tmp, $image_content ) ) {
            @unlink( $tmp );
            continue;
        }

        // MIMEタイプの検証
        $image_info = @getimagesize( $tmp );
        if ( false === $image_info ) {
            @unlink( $tmp );
            continue;
        }

        $attachment_id = noveltool_get_or_create_import_image_attachment(
            $tmp,
            sanitize_file_name( $basename ),
            0
        );
        @unlink( $tmp );

        if ( ! is_wp_error( $attachment_id ) ) {
            $relative_to_url[ $zip_entry_name ] = wp_get_attachment_url( $attachment_id );
        }
    }
    $zip->close();

    // JSON内の相対パスをメディアライブラリURLに置換
    if ( ! empty( $relative_to_url ) ) {
        if ( isset( $import_data['game']['title_image'] ) && isset( $relative_to_url[ $import_data['game']['title_image'] ] ) ) {
            $import_data['game']['title_image'] = $relative_to_url[ $import_data['game']['title_image'] ];
        }
        $image_fields = array( 'background_image', 'character_image', 'character_left', 'character_center', 'character_right' );
        foreach ( $import_data['scenes'] as &$scene ) {
            foreach ( $image_fields as $field ) {
                if ( ! empty( $scene[ $field ] ) && isset( $relative_to_url[ $scene[ $field ] ] ) ) {
                    $scene[ $field ] = $relative_to_url[ $scene[ $field ] ];
                }
            }
            if ( ! empty( $scene['dialogue_backgrounds'] ) ) {
                $bg_data = $scene['dialogue_backgrounds'];
                if ( is_string( $bg_data ) ) {
                    $bg_data = json_decode( $bg_data, true );
                }
                if ( is_array( $bg_data ) ) {
                    foreach ( $bg_data as &$bg ) {
                        if ( is_string( $bg ) && isset( $relative_to_url[ $bg ] ) ) {
                            $bg = $relative_to_url[ $bg ];
                        }
                    }
                    unset( $bg );
                    $scene['dialogue_backgrounds'] = $bg_data;
                }
            }
            // dialogue_characters 配列（セリフごとの個別キャラクター画像）の相対パス復元
            if ( ! empty( $scene['dialogue_characters'] ) ) {
                $dc_data = $scene['dialogue_characters'];
                if ( is_string( $dc_data ) ) {
                    $dc_data = json_decode( $dc_data, true );
                }
                if ( is_array( $dc_data ) ) {
                    foreach ( $dc_data as &$char_set ) {
                        if ( ! is_array( $char_set ) ) {
                            continue;
                        }
                        foreach ( array( 'left', 'center', 'right' ) as $pos ) {
                            if ( ! empty( $char_set[ $pos ] ) && isset( $relative_to_url[ $char_set[ $pos ] ] ) ) {
                                $char_set[ $pos ] = $relative_to_url[ $char_set[ $pos ] ];
                            }
                        }
                    }
                    unset( $char_set );
                    $scene['dialogue_characters'] = $dc_data;
                }
            }
        }
        unset( $scene );
    }

    return noveltool_import_game_data( $import_data, false );
}

/**
 * 複数の分割ZIPファイルからゲームデータをインポートする
 *
 * すべてのパートの manifest を検証した後に images と game-data.json を結合し、
 * noveltool_import_game_data() に引き渡す。
 * manifest 不整合・欠落パート・重複パート・別エクスポート混在はエラーとして中断する。
 *
 * @param string[] $zip_paths 分割ZIPファイルの一時パスの配列
 * @return array|WP_Error インポート結果または WP_Error
 * @since 1.5.0
 */
function noveltool_import_from_split_zips( $zip_paths ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'no_ziparchive', __( 'ZipArchive is not available on this server.', 'novel-game-plugin' ) );
    }

    @set_time_limit( 300 );
    @ini_set( 'memory_limit', '256M' );

    // Zip bomb 対策パラメータ
    $zip_max_files       = intval( apply_filters( 'noveltool_zip_import_max_files', 100 ) );
    $zip_max_single_size = intval( apply_filters( 'noveltool_zip_import_max_single_size', 10 * 1024 * 1024 ) );
    $zip_max_total_size  = intval( apply_filters( 'noveltool_split_zip_import_max_total_size', 200 * 1024 * 1024 ) ); // 分割ZIP総量: 200MB

    $manifests   = array(); // part_number => manifest 配列
    $zip_handles = array(); // part_number => ZipArchive

    // Step 1: 各ZIPを開いて manifest を読み込み基本検証
    foreach ( $zip_paths as $zip_path ) {
        $zip = new ZipArchive();
        if ( true !== $zip->open( $zip_path ) ) {
            foreach ( $zip_handles as $z ) {
                $z->close();
            }
            return new WP_Error( 'zip_open_failed', __( 'Failed to open ZIP archive.', 'novel-game-plugin' ) );
        }

        // Zip bomb 対策: ファイル数チェック
        if ( $zip->numFiles > $zip_max_files ) {
            $zip->close();
            foreach ( $zip_handles as $z ) {
                $z->close();
            }
            return new WP_Error( 'zip_too_many_files', __( 'ZIP archive contains too many files.', 'novel-game-plugin' ) );
        }

        $manifest_json = $zip->getFromName( 'manifest.json' );
        if ( false === $manifest_json ) {
            $zip->close();
            foreach ( $zip_handles as $z ) {
                $z->close();
            }
            return new WP_Error( 'no_manifest', __( 'manifest.json not found. This is not a split ZIP file.', 'novel-game-plugin' ) );
        }

        $manifest = json_decode( $manifest_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $manifest ) ) {
            $zip->close();
            foreach ( $zip_handles as $z ) {
                $z->close();
            }
            return new WP_Error( 'invalid_manifest', __( 'Invalid manifest.json.', 'novel-game-plugin' ) );
        }

        if ( ! isset( $manifest['export_id'], $manifest['part_number'], $manifest['total_parts'], $manifest['version'] ) ) {
            $zip->close();
            foreach ( $zip_handles as $z ) {
                $z->close();
            }
            return new WP_Error( 'manifest_missing_fields', __( 'manifest.json is missing required fields.', 'novel-game-plugin' ) );
        }

        $part_number = intval( $manifest['part_number'] );
        if ( isset( $manifests[ $part_number ] ) ) {
            $zip->close();
            foreach ( $zip_handles as $z ) {
                $z->close();
            }
            /* translators: %d: 重複しているパート番号 */
            return new WP_Error( 'duplicate_part', sprintf( __( 'Duplicate part %d detected.', 'novel-game-plugin' ), $part_number ) );
        }

        $manifests[ $part_number ]   = $manifest;
        $zip_handles[ $part_number ] = $zip;
    }

    // Step 2: 整合性検証
    if ( empty( $manifests ) ) {
        return new WP_Error( 'no_parts', __( 'No valid split ZIP parts found.', 'novel-game-plugin' ) );
    }

    $first_manifest       = reset( $manifests );
    $expected_export_id   = $first_manifest['export_id'];
    $expected_total_parts = intval( $first_manifest['total_parts'] );

    foreach ( $manifests as $manifest ) {
        if ( $manifest['export_id'] !== $expected_export_id ) {
            foreach ( $zip_handles as $z ) {
                $z->close();
            }
            return new WP_Error( 'export_id_mismatch', __( 'Files are from different exports. Please upload only parts from the same export.', 'novel-game-plugin' ) );
        }
        if ( intval( $manifest['total_parts'] ) !== $expected_total_parts ) {
            foreach ( $zip_handles as $z ) {
                $z->close();
            }
            return new WP_Error( 'total_parts_mismatch', __( 'Inconsistent total part count in manifests.', 'novel-game-plugin' ) );
        }
    }

    // 欠落パートの確認
    $missing_parts = array();
    for ( $i = 1; $i <= $expected_total_parts; $i++ ) {
        if ( ! isset( $manifests[ $i ] ) ) {
            $missing_parts[] = $i;
        }
    }
    if ( ! empty( $missing_parts ) ) {
        foreach ( $zip_handles as $z ) {
            $z->close();
        }
        /* translators: %s: 欠落しているパート番号のカンマ区切りリスト */
        return new WP_Error( 'missing_parts', sprintf( __( 'Missing parts: %s. Please upload all parts.', 'novel-game-plugin' ), implode( ', ', $missing_parts ) ) );
    }

    // Step 3: Part 1 から game-data.json を取得
    $json_content = $zip_handles[1]->getFromName( 'game-data.json' );
    if ( false === $json_content ) {
        foreach ( $zip_handles as $z ) {
            $z->close();
        }
        return new WP_Error( 'no_json', __( 'game-data.json not found in part 1.', 'novel-game-plugin' ) );
    }

    $import_data = json_decode( $json_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        foreach ( $zip_handles as $z ) {
            $z->close();
        }
        return new WP_Error( 'invalid_json', __( 'Invalid JSON encoding.', 'novel-game-plugin' ) );
    }

    if ( ! is_array( $import_data ) || ! isset( $import_data['game'] ) || ! isset( $import_data['scenes'] ) ) {
        foreach ( $zip_handles as $z ) {
            $z->close();
        }
        return new WP_Error( 'invalid_structure', __( 'Missing required "game" or "scenes" keys.', 'novel-game-plugin' ) );
    }

    // Step 4: 全パートから画像を取得してメディアライブラリへアップロード
    $total_uncompressed = 0;
    $relative_to_url    = array();

    foreach ( $zip_handles as $zip ) {
        for ( $idx = 0; $idx < $zip->numFiles; $idx++ ) {
            $zip_entry_name = $zip->getNameIndex( $idx );
            // パストラバーサル対策: images/ で始まるエントリのみ許可し、'..' を含むパスを拒否
            if ( strpos( $zip_entry_name, 'images/' ) !== 0 || false !== strpos( $zip_entry_name, '..' ) ) {
                continue;
            }
            $basename = basename( $zip_entry_name );
            if ( empty( $basename ) ) {
                continue;
            }
            $ext = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
                continue;
            }

            // Zip bomb 対策: ファイルサイズ・総サイズチェック
            $stat = $zip->statIndex( $idx );
            if ( false !== $stat ) {
                if ( $stat['size'] > $zip_max_single_size ) {
                    foreach ( $zip_handles as $z ) {
                        $z->close();
                    }
                    return new WP_Error( 'zip_file_too_large', __( 'A file in the ZIP archive exceeds the size limit.', 'novel-game-plugin' ) );
                }
                $total_uncompressed += $stat['size'];
                if ( $total_uncompressed > $zip_max_total_size ) {
                    foreach ( $zip_handles as $z ) {
                        $z->close();
                    }
                    return new WP_Error( 'zip_total_too_large', __( 'Total extracted size of split ZIP archives exceeds the limit.', 'novel-game-plugin' ) );
                }
            }

            $image_content = $zip->getFromIndex( $idx );
            if ( false === $image_content || empty( $image_content ) ) {
                continue;
            }

            $tmp = wp_tempnam( $basename );
            if ( ! $tmp ) {
                continue;
            }
            if ( false === file_put_contents( $tmp, $image_content ) ) {
                @unlink( $tmp );
                continue;
            }

            // MIMEタイプの検証
            $image_info = @getimagesize( $tmp );
            if ( false === $image_info ) {
                @unlink( $tmp );
                continue;
            }

            $attachment_id = noveltool_get_or_create_import_image_attachment(
                $tmp,
                sanitize_file_name( $basename ),
                0
            );
            @unlink( $tmp );

            if ( ! is_wp_error( $attachment_id ) ) {
                $relative_to_url[ $zip_entry_name ] = wp_get_attachment_url( $attachment_id );
            }
        }
    }

    foreach ( $zip_handles as $z ) {
        $z->close();
    }

    // Step 5: JSON内の相対パスをメディアライブラリURLに置換（noveltool_import_from_zip と同じロジック）
    if ( ! empty( $relative_to_url ) ) {
        if ( isset( $import_data['game']['title_image'] ) && isset( $relative_to_url[ $import_data['game']['title_image'] ] ) ) {
            $import_data['game']['title_image'] = $relative_to_url[ $import_data['game']['title_image'] ];
        }
        $image_fields = array( 'background_image', 'character_image', 'character_left', 'character_center', 'character_right' );
        foreach ( $import_data['scenes'] as &$scene ) {
            foreach ( $image_fields as $field ) {
                if ( ! empty( $scene[ $field ] ) && isset( $relative_to_url[ $scene[ $field ] ] ) ) {
                    $scene[ $field ] = $relative_to_url[ $scene[ $field ] ];
                }
            }
            if ( ! empty( $scene['dialogue_backgrounds'] ) ) {
                $bg_data = $scene['dialogue_backgrounds'];
                if ( is_string( $bg_data ) ) {
                    $bg_data = json_decode( $bg_data, true );
                }
                if ( is_array( $bg_data ) ) {
                    foreach ( $bg_data as &$bg ) {
                        if ( is_string( $bg ) && isset( $relative_to_url[ $bg ] ) ) {
                            $bg = $relative_to_url[ $bg ];
                        }
                    }
                    unset( $bg );
                    $scene['dialogue_backgrounds'] = $bg_data;
                }
            }
            if ( ! empty( $scene['dialogue_characters'] ) ) {
                $dc_data = $scene['dialogue_characters'];
                if ( is_string( $dc_data ) ) {
                    $dc_data = json_decode( $dc_data, true );
                }
                if ( is_array( $dc_data ) ) {
                    foreach ( $dc_data as &$char_set ) {
                        if ( ! is_array( $char_set ) ) {
                            continue;
                        }
                        foreach ( array( 'left', 'center', 'right' ) as $pos ) {
                            if ( ! empty( $char_set[ $pos ] ) && isset( $relative_to_url[ $char_set[ $pos ] ] ) ) {
                                $char_set[ $pos ] = $relative_to_url[ $char_set[ $pos ] ];
                            }
                        }
                    }
                    unset( $char_set );
                    $scene['dialogue_characters'] = $dc_data;
                }
            }
        }
        unset( $scene );
    }

    return noveltool_import_game_data( $import_data, false );
}

/**
 * 分割ZIPステージングの一時ファイルをクリーンアップする
 *
 * @param array $staging ステージング配列（staged_images キーを含む）
 * @since 1.5.0
 */
function noveltool_cleanup_split_zip_staging( $staging ) {
    if ( ! is_array( $staging ) || empty( $staging['staged_images'] ) ) {
        return;
    }
    foreach ( $staging['staged_images'] as $tmp_path ) {
        if ( is_string( $tmp_path ) && file_exists( $tmp_path ) ) {
            @unlink( $tmp_path );
        }
    }
}

/**
 * 分割ZIPパートをステージングエリアに追加する
 *
 * 単一の分割ZIPパートを受け取り、manifest を検証し、
 * manifest.files と実際の ZIP 内容を照合する。
 * 問題がなければ画像を一時ファイルに展開してステージングトランジェントに保存する。
 * 全パートが揃った時点で noveltool_finalize_split_zip_import() を呼んでインポートを完結させる。
 *
 * @param string $zip_path アップロードされた分割ZIPパートの一時ファイルパス
 * @return array|WP_Error ステージング状態の配列（status='staging'）またはインポート結果、あるいは WP_Error
 * @since 1.5.0
 */
function noveltool_stage_split_zip_part( $zip_path ) {
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'no_ziparchive', __( 'ZipArchive is not available on this server.', 'novel-game-plugin' ) );
    }

    $zip_max_files       = intval( apply_filters( 'noveltool_zip_import_max_files', 100 ) );
    $zip_max_single_size = intval( apply_filters( 'noveltool_zip_import_max_single_size', 10 * 1024 * 1024 ) );

    // ZIPを開く
    $zip = new ZipArchive();
    if ( true !== $zip->open( $zip_path ) ) {
        return new WP_Error( 'zip_open_failed', __( 'Failed to open ZIP archive.', 'novel-game-plugin' ) );
    }

    // Zip bomb 対策: ファイル数チェック
    if ( $zip->numFiles > $zip_max_files ) {
        $zip->close();
        return new WP_Error( 'zip_too_many_files', __( 'ZIP archive contains too many files.', 'novel-game-plugin' ) );
    }

    // manifest.json を読み込む
    $manifest_json = $zip->getFromName( 'manifest.json' );
    if ( false === $manifest_json ) {
        $zip->close();
        return new WP_Error( 'no_manifest', __( 'manifest.json not found. This is not a split ZIP file.', 'novel-game-plugin' ) );
    }

    $manifest = json_decode( $manifest_json, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $manifest ) ) {
        $zip->close();
        return new WP_Error( 'invalid_manifest', __( 'Invalid manifest.json.', 'novel-game-plugin' ) );
    }

    if ( ! isset( $manifest['export_id'], $manifest['part_number'], $manifest['total_parts'], $manifest['version'], $manifest['files'] ) ) {
        $zip->close();
        return new WP_Error( 'manifest_missing_fields', __( 'manifest.json is missing required fields.', 'novel-game-plugin' ) );
    }

    $export_id              = sanitize_text_field( $manifest['export_id'] );
    $part_number            = intval( $manifest['part_number'] );
    $total_parts            = intval( $manifest['total_parts'] );
    $expected_files_in_part = is_array( $manifest['files'] ) ? $manifest['files'] : array();

    if ( $part_number < 1 || $total_parts < 1 || $part_number > $total_parts ) {
        $zip->close();
        return new WP_Error( 'invalid_manifest_numbers', __( 'Invalid part number or total parts in manifest.', 'novel-game-plugin' ) );
    }

    // manifest.files と実際の ZIP 内容を照合（欠落・不整合の検出）
    // 全パート検証前の media_handle_sideload 実行を防ぐため、ここで先行チェックする
    // manifest.files に宣言されていない余分な images/ ファイルも拒否する（完全一致）
    $expected_image_set = array();
    foreach ( $expected_files_in_part as $expected_file ) {
        $safe_file = sanitize_text_field( $expected_file );
        if ( empty( $safe_file ) || strpos( $safe_file, 'images/' ) !== 0 || false !== strpos( $safe_file, '..' ) ) {
            continue;
        }
        // 宣言済みファイルが ZIP 内に存在することを確認
        if ( false === $zip->locateName( $safe_file ) ) {
            $zip->close();
            return new WP_Error(
                'manifest_file_missing',
                sprintf(
                    /* translators: %s: ファイル名 */
                    __( 'File declared in manifest not found in ZIP: %s', 'novel-game-plugin' ),
                    basename( $safe_file )
                )
            );
        }
        $expected_image_set[ $safe_file ] = true;
    }

    // ZIP 内の images/ ファイルが manifest.files に存在することを確認（余分ファイルの拒否）
    for ( $idx = 0; $idx < $zip->numFiles; $idx++ ) {
        $entry_name = $zip->getNameIndex( $idx );
        if ( strpos( $entry_name, 'images/' ) !== 0 || false !== strpos( $entry_name, '..' ) ) {
            continue;
        }
        $basename = basename( $entry_name );
        if ( empty( $basename ) ) {
            continue;
        }
        $ext = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
            continue;
        }
        if ( ! isset( $expected_image_set[ $entry_name ] ) ) {
            $zip->close();
            return new WP_Error(
                'extra_file_in_zip',
                sprintf(
                    /* translators: %s: ファイル名 */
                    __( 'ZIP contains a file not declared in manifest: %s', 'novel-game-plugin' ),
                    basename( $entry_name )
                )
            );
        }
    }

    // ステージングトランジェントを取得または作成
    $staging_key = 'noveltool_szstage_' . md5( $export_id );
    $staging     = get_transient( $staging_key );

    if ( false === $staging ) {
        $staging = array(
            'export_id'      => $export_id,
            'total_parts'    => $total_parts,
            'game_title'     => isset( $manifest['game_title'] ) ? sanitize_text_field( $manifest['game_title'] ) : '',
            'created_at'     => time(),
            'staged_parts'   => array(),
            'game_data_json' => null,
            'staged_images'  => array(), // zip_entry_name => tmp_file_path
            'expected_files' => array(), // part_number => array of file names
        );
    } else {
        // 既存ステージングの整合性チェック
        if ( $staging['export_id'] !== $export_id ) {
            $zip->close();
            return new WP_Error( 'export_id_mismatch', __( 'Files are from different exports. Please start a new import.', 'novel-game-plugin' ) );
        }
        if ( intval( $staging['total_parts'] ) !== $total_parts ) {
            $zip->close();
            return new WP_Error( 'total_parts_mismatch', __( 'Inconsistent total part count in manifests.', 'novel-game-plugin' ) );
        }
        if ( in_array( $part_number, $staging['staged_parts'], true ) ) {
            $zip->close();
            /* translators: %d: パート番号 */
            return new WP_Error( 'duplicate_part', sprintf( __( 'Part %d has already been uploaded.', 'novel-game-plugin' ), $part_number ) );
        }
    }

    // このパートの期待ファイルリストを記録
    $staging['expected_files'][ $part_number ] = $expected_files_in_part;

    // Part 1 から game-data.json を取得
    if ( 1 === $part_number ) {
        $json_content = $zip->getFromName( 'game-data.json' );
        if ( false === $json_content ) {
            $zip->close();
            return new WP_Error( 'no_json', __( 'game-data.json not found in part 1.', 'novel-game-plugin' ) );
        }
        $staging['game_data_json'] = $json_content;
    }

    // 画像を一時ファイルに展開してステージングに追加
    // $expected_image_set は検証済みの images/ エントリのみを含むため、余分なファイルは既に拒否済み
    for ( $idx = 0; $idx < $zip->numFiles; $idx++ ) {
        $entry_name = $zip->getNameIndex( $idx );
        // manifest.files に宣言されていないエントリはスキップ（パストラバーサル対策も兼ねる）
        if ( ! isset( $expected_image_set[ $entry_name ] ) ) {
            continue;
        }
        // $expected_image_set に含まれるエントリは検証済みで basename が空になることはないが
        // $basename は後続のパスで使用するため代入する
        $basename = basename( $entry_name );

        // Zip bomb 対策: サイズチェック
        $stat = $zip->statIndex( $idx );
        if ( false !== $stat && $stat['size'] > $zip_max_single_size ) {
            $zip->close();
            noveltool_cleanup_split_zip_staging( $staging );
            return new WP_Error( 'zip_file_too_large', __( 'A file in the ZIP archive exceeds the size limit.', 'novel-game-plugin' ) );
        }

        $image_content = $zip->getFromIndex( $idx );
        if ( false === $image_content || empty( $image_content ) ) {
            continue;
        }

        $tmp = wp_tempnam( $basename );
        if ( ! $tmp ) {
            continue;
        }
        if ( false === file_put_contents( $tmp, $image_content ) ) {
            @unlink( $tmp );
            continue;
        }

        // MIMEタイプ検証
        $image_info = @getimagesize( $tmp );
        if ( false === $image_info ) {
            @unlink( $tmp );
            continue;
        }

        $staging['staged_images'][ $entry_name ] = $tmp;
    }

    $zip->close();

    // このパートをステージング済みとしてマーク
    $staging['staged_parts'][] = $part_number;

    // ステージングトランジェントを更新（フィルターで上書き可能なTTL）
    $staging_ttl = intval( apply_filters( 'noveltool_split_zip_staging_ttl', DAY_IN_SECONDS ) );
    set_transient( $staging_key, $staging, $staging_ttl );

    // TTL 切れや離脱時に一時ファイルが残らないよう、クリーンアップキューに登録する
    // $staging_key を session_key として渡すことで、同一セッションの古いエントリを
    // 置き換え（upsert）し、先行パートの tmp が早期削除される競合を防ぐ
    $cleanup_grace = intval( apply_filters( 'noveltool_staging_cleanup_grace', 10 * MINUTE_IN_SECONDS ) );
    noveltool_enqueue_tmp_cleanup(
        array_values( $staging['staged_images'] ),
        time() + $staging_ttl + $cleanup_grace,
        $staging_key  // セッション単位で最新エントリに置き換え（upsert）
    );

    // 全パートが揃ったかチェック
    if ( count( $staging['staged_parts'] ) >= $total_parts ) {
        // ファイナライズ: 全パート検証完了後に初めてメディア登録・ゲーム作成を実行
        return noveltool_finalize_split_zip_import( $staging_key );
    }

    // まだパートが足りない: ステージング状態を返す
    sort( $staging['staged_parts'] );
    $missing_parts = array();
    for ( $i = 1; $i <= $total_parts; $i++ ) {
        if ( ! in_array( $i, $staging['staged_parts'], true ) ) {
            $missing_parts[] = $i;
        }
    }

    return array(
        'status'        => 'staging',
        'staged_parts'  => $staging['staged_parts'],
        'total_parts'   => $total_parts,
        'missing_parts' => $missing_parts,
        'export_id'     => $export_id,
        'game_title'    => $staging['game_title'],
    );
}

/**
 * ステージングエリアからゲームデータのインポートを完結させる
 *
 * 全パートが揃った後に noveltool_stage_split_zip_part() から呼ばれる。
 * staged_images と expected_files の完全照合を行い、問題がなければ
 * media_handle_sideload() でメディア登録、noveltool_import_game_data() でゲーム作成。
 * 失敗時は作成済みの添付ファイルをロールバックし、ステージングを消去する。
 *
 * @param string $staging_key ステージングトランジェントのキー
 * @return array|WP_Error インポート結果またはWP_Error
 * @since 1.5.0
 */
function noveltool_finalize_split_zip_import( $staging_key ) {
    $staging = get_transient( $staging_key );
    if ( false === $staging ) {
        return new WP_Error( 'staging_not_found', __( 'Import session expired. Please upload all parts again.', 'novel-game-plugin' ) );
    }

    // staged_images と expected_files の完全照合
    // manifest.files で宣言されたすべてのファイルがステージングされているか確認
    foreach ( $staging['expected_files'] as $part_num => $expected_list ) {
        foreach ( $expected_list as $expected_entry ) {
            $safe_entry = sanitize_text_field( $expected_entry );
            if ( empty( $safe_entry ) || strpos( $safe_entry, 'images/' ) !== 0 ) {
                continue;
            }
            if ( ! isset( $staging['staged_images'][ $safe_entry ] ) ) {
                noveltool_cleanup_split_zip_staging( $staging );
                delete_transient( $staging_key );
                return new WP_Error(
                    'staged_file_missing',
                    sprintf(
                        /* translators: %s: ファイル名 */
                        __( 'Expected file not found in staging: %s. Import aborted.', 'novel-game-plugin' ),
                        basename( $safe_entry )
                    )
                );
            }
        }
    }

    // game-data.json の検証
    if ( empty( $staging['game_data_json'] ) ) {
        noveltool_cleanup_split_zip_staging( $staging );
        delete_transient( $staging_key );
        return new WP_Error( 'no_json', __( 'game-data.json not found in staging. Please upload part 1 again.', 'novel-game-plugin' ) );
    }

    $import_data = json_decode( $staging['game_data_json'], true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        noveltool_cleanup_split_zip_staging( $staging );
        delete_transient( $staging_key );
        return new WP_Error( 'invalid_json', __( 'Invalid JSON encoding.', 'novel-game-plugin' ) );
    }

    if ( ! is_array( $import_data ) || ! isset( $import_data['game'] ) || ! isset( $import_data['scenes'] ) ) {
        noveltool_cleanup_split_zip_staging( $staging );
        delete_transient( $staging_key );
        return new WP_Error( 'invalid_structure', __( 'Missing required "game" or "scenes" keys.', 'novel-game-plugin' ) );
    }

    @set_time_limit( 300 );
    @ini_set( 'memory_limit', '256M' );

    // メディアライブラリに画像を登録
    // 注意: 全パートの検証が完了した後に初めて実行される
    $relative_to_url        = array();
    $created_attachment_ids = array();

    foreach ( $staging['staged_images'] as $entry_name => $tmp_path ) {
        if ( ! file_exists( $tmp_path ) ) {
            continue;
        }
        $basename      = basename( $entry_name );
        $attachment_id = noveltool_get_or_create_import_image_attachment(
            $tmp_path,
            sanitize_file_name( $basename ),
            0
        );

        if ( is_wp_error( $attachment_id ) ) {
            // ロールバック: 作成済みの添付ファイルを削除し、一時ファイルをクリーンアップ
            foreach ( $created_attachment_ids as $att_id ) {
                wp_delete_attachment( $att_id, true );
            }
            noveltool_cleanup_split_zip_staging( $staging );
            delete_transient( $staging_key );
            return new WP_Error( 'attachment_failed', __( 'Failed to create media attachment. Import aborted.', 'novel-game-plugin' ) );
        }

        $created_attachment_ids[]           = $attachment_id;
        $relative_to_url[ $entry_name ] = wp_get_attachment_url( $attachment_id );
    }

    // 一時ファイルを明示的に削除（media_handle_sideload は tmp_name を保持するため）
    foreach ( $staging['staged_images'] as $tmp_path ) {
        @unlink( $tmp_path );
    }
    delete_transient( $staging_key );

    // JSON内の相対パスをメディアライブラリURLに置換
    if ( ! empty( $relative_to_url ) ) {
        if ( isset( $import_data['game']['title_image'] ) && isset( $relative_to_url[ $import_data['game']['title_image'] ] ) ) {
            $import_data['game']['title_image'] = $relative_to_url[ $import_data['game']['title_image'] ];
        }
        $image_fields = array( 'background_image', 'character_image', 'character_left', 'character_center', 'character_right' );
        foreach ( $import_data['scenes'] as &$scene ) {
            foreach ( $image_fields as $field ) {
                if ( ! empty( $scene[ $field ] ) && isset( $relative_to_url[ $scene[ $field ] ] ) ) {
                    $scene[ $field ] = $relative_to_url[ $scene[ $field ] ];
                }
            }
            if ( ! empty( $scene['dialogue_backgrounds'] ) ) {
                $bg_data = $scene['dialogue_backgrounds'];
                if ( is_string( $bg_data ) ) {
                    $bg_data = json_decode( $bg_data, true );
                }
                if ( is_array( $bg_data ) ) {
                    foreach ( $bg_data as &$bg ) {
                        if ( is_string( $bg ) && isset( $relative_to_url[ $bg ] ) ) {
                            $bg = $relative_to_url[ $bg ];
                        }
                    }
                    unset( $bg );
                    $scene['dialogue_backgrounds'] = $bg_data;
                }
            }
            if ( ! empty( $scene['dialogue_characters'] ) ) {
                $dc_data = $scene['dialogue_characters'];
                if ( is_string( $dc_data ) ) {
                    $dc_data = json_decode( $dc_data, true );
                }
                if ( is_array( $dc_data ) ) {
                    foreach ( $dc_data as &$char_set ) {
                        if ( ! is_array( $char_set ) ) {
                            continue;
                        }
                        foreach ( array( 'left', 'center', 'right' ) as $pos ) {
                            if ( ! empty( $char_set[ $pos ] ) && isset( $relative_to_url[ $char_set[ $pos ] ] ) ) {
                                $char_set[ $pos ] = $relative_to_url[ $char_set[ $pos ] ];
                            }
                        }
                    }
                    unset( $char_set );
                    $scene['dialogue_characters'] = $dc_data;
                }
            }
        }
        unset( $scene );
    }

    // ゲームデータをインポート
    $result = noveltool_import_game_data( $import_data, false );
    if ( is_wp_error( $result ) ) {
        // ゲーム作成失敗: 作成済みの添付ファイルをロールバック
        foreach ( $created_attachment_ids as $att_id ) {
            wp_delete_attachment( $att_id, true );
        }
        return $result;
    }

    return $result;
}

/**
 * AJAXハンドラー: ゲームデータのインポート
 *
 * セキュリティ強化: ファイルサイズ、MIME、アップロードエラーの詳細チェックを実施
 * エラーメッセージは静的文言のみ利用し外部入力を直接連結しない方針
 * 分割ZIP: manifest.json が含まれる ZIP を検出し、逐次ステージング方式で処理する
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

    // ファイルサイズチェック（ファイル種別に応じた上限）
    $file_name = isset( $_FILES['import_file']['name'] ) ? $_FILES['import_file']['name'] : '';
    $is_zip    = ( substr( strtolower( $file_name ), -4 ) === '.zip' );
    $max_size  = noveltool_get_import_max_size( $is_zip ? 'zip' : 'json' );
    if ( isset( $_FILES['import_file']['size'] ) && $_FILES['import_file']['size'] > $max_size ) {
        wp_send_json_error( array( 'message' => __( 'File size exceeds the allowed limit.', 'novel-game-plugin' ) ) );
    }

    // ZIPインポート: manifest.json の有無で分割ZIP（ステージング）か通常ZIPかを判定
    if ( $is_zip ) {
        $zip_tmp     = $_FILES['import_file']['tmp_name'];
        $has_manifest = false;

        if ( class_exists( 'ZipArchive' ) ) {
            $zip_peek = new ZipArchive();
            if ( true === $zip_peek->open( $zip_tmp ) ) {
                $has_manifest = ( false !== $zip_peek->locateName( 'manifest.json' ) );
                $zip_peek->close();
            }
        }

        if ( $has_manifest ) {
            // 分割ZIPパート: 逐次ステージング方式で処理
            $stage_result = noveltool_stage_split_zip_part( $zip_tmp );
            if ( is_wp_error( $stage_result ) ) {
                wp_send_json_error( array( 'message' => $stage_result->get_error_message() ) );
            }

            if ( isset( $stage_result['status'] ) && 'staging' === $stage_result['status'] ) {
                // まだ全パートが揃っていない: ステージング進捗を返す
                wp_send_json_success( array(
                    'format'        => 'split_zip_staging',
                    'staged_parts'  => $stage_result['staged_parts'],
                    'total_parts'   => $stage_result['total_parts'],
                    'missing_parts' => $stage_result['missing_parts'],
                    'export_id'     => $stage_result['export_id'],
                    'game_title'    => $stage_result['game_title'],
                ) );
            }

            // 全パート揃ってファイナライズ完了: 通常のインポート結果として扱う
            $result = $stage_result;
        } else {
            // 通常の単一ZIPインポート（後方互換性を維持）
            $result = noveltool_import_from_zip( $zip_tmp );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $message = sprintf(
            __( 'Successfully imported game with %d scenes (%d choices remapped).', 'novel-game-plugin' ),
            $result['imported_scenes'],
            isset( $result['remapped_choices'] ) ? $result['remapped_choices'] : 0
        );
        if ( isset( $result['renamed'] ) && $result['renamed'] ) {
            $message .= ' ' . sprintf(
                __( 'Game title was auto-renamed from "%s" to avoid duplication.', 'novel-game-plugin' ),
                $result['original_title']
            );
        }

        wp_send_json_success( array(
            'message'          => $message,
            'game_id'          => $result['game_id'],
            'imported_scenes'  => $result['imported_scenes'],
            'remapped_choices' => isset( $result['remapped_choices'] ) ? $result['remapped_choices'] : 0,
            'renamed'          => isset( $result['renamed'] ) ? $result['renamed'] : false,
        ) );
    }

    // MIMEタイプ検証（JSONの場合）
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
        wp_send_json_error( array( 'message' => __( 'Only JSON or ZIP files are allowed.', 'novel-game-plugin' ) ) );
    }

    // ファイルの読み込み
    $file_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
    if ( $file_content === false ) {
        wp_send_json_error( array( 'message' => __( 'Failed to read file.', 'novel-game-plugin' ) ) );
    }

    // JSONデコードと構造検証
    $import_data = json_decode( $file_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( array( 'message' => __( 'Invalid JSON encoding.', 'novel-game-plugin' ) ) );
    }

    // JSON構造の検証
    if ( ! is_array( $import_data ) || ! isset( $import_data['game'] ) || ! isset( $import_data['scenes'] ) ) {
        wp_send_json_error( array( 'message' => __( 'Missing required "game" or "scenes" keys.', 'novel-game-plugin' ) ) );
    }

    // 画像ダウンロードオプション
    $download_images = isset( $_POST['download_images'] ) && $_POST['download_images'] === 'true';

    // インポート実行
    $result = noveltool_import_game_data( $import_data, $download_images );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    // 成功メッセージの構築（自動リネーム情報を含む）
    $message = sprintf(
        __( 'Successfully imported game with %d scenes (%d choices remapped).', 'novel-game-plugin' ),
        $result['imported_scenes'],
        isset( $result['remapped_choices'] ) ? $result['remapped_choices'] : 0
    );

    if ( isset( $result['renamed'] ) && $result['renamed'] ) {
        $message .= ' ' . sprintf(
            __( 'Game title was auto-renamed from "%s" to avoid duplication.', 'novel-game-plugin' ),
            $result['original_title']
        );
    }

    // 画像ダウンロード失敗件数を通知
    if ( isset( $result['image_download_failures'] ) && $result['image_download_failures'] > 0 ) {
        $message .= ' ' . sprintf(
            __( 'Note: %d image(s) failed to download.', 'novel-game-plugin' ),
            $result['image_download_failures']
        );
    }

    wp_send_json_success( array(
        'message'                 => $message,
        'game_id'                 => $result['game_id'],
        'imported_scenes'         => $result['imported_scenes'],
        'remapped_choices'        => isset( $result['remapped_choices'] ) ? $result['remapped_choices'] : 0,
        'renamed'                 => isset( $result['renamed'] ) ? $result['renamed'] : false,
        'image_download_failures' => isset( $result['image_download_failures'] ) ? $result['image_download_failures'] : 0,
    ) );
}
add_action( 'wp_ajax_noveltool_import_game', 'noveltool_ajax_import_game' );
