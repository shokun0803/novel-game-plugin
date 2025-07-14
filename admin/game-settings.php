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
 * ゲーム設定ページをメニューに追加
 *
 * @since 1.1.0
 */
function noveltool_add_game_settings_menu() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __( 'ゲーム基本情報', 'novel-game-plugin' ),
        __( 'ゲーム基本情報', 'novel-game-plugin' ),
        'edit_posts',
        'novel-game-settings',
        'noveltool_game_settings_page'
    );
}
add_action( 'admin_menu', 'noveltool_add_game_settings_menu' );

/**
 * ゲーム設定フォームの処理
 *
 * @since 1.1.0
 */
function noveltool_handle_game_settings_form() {
    // ゲーム設定ページでのフォーム送信のみ処理
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'novel-game-settings' ) {
        return;
    }

    // フォーム送信の処理
    if ( isset( $_POST['save_game_settings'] ) ) {
        // 権限チェック
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'このページにアクセスする権限がありません。', 'novel-game-plugin' ) );
        }

        // nonceチェック
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'save_game_settings' ) ) {
            // セキュリティエラーのメッセージを設定してリダイレクト
            $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // ゲーム情報の取得とバリデーション
        $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
        $game_description = isset( $_POST['game_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['game_description'] ) ) : '';
        $game_title_image = isset( $_POST['game_title_image'] ) ? sanitize_url( wp_unslash( $_POST['game_title_image'] ) ) : '';

        // 設定を保存
        update_option( 'noveltool_game_title', $game_title );
        update_option( 'noveltool_game_description', $game_description );
        update_option( 'noveltool_game_title_image', $game_title_image );

        // 既存のシーンのゲームタイトルも更新
        if ( $game_title ) {
            noveltool_update_scenes_game_title( $game_title );
        }

        // 成功メッセージを設定してリダイレクト
        $redirect_url = add_query_arg( 'success', '1', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
add_action( 'admin_init', 'noveltool_handle_game_settings_form' );

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
 * @param string $new_title 新しいゲームタイトル
 * @since 1.1.0
 */
function noveltool_update_scenes_game_title( $new_title ) {
    global $wpdb;
    
    // novel_game投稿タイプのすべての投稿のゲームタイトルを更新
    $wpdb->update(
        $wpdb->postmeta,
        array( 'meta_value' => $new_title ),
        array( 'meta_key' => '_game_title' ),
        array( '%s' ),
        array( '%s' )
    );
}

/**
 * ゲーム設定ページの内容
 *
 * @since 1.1.0
 */
function noveltool_game_settings_page() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'このページにアクセスする権限がありません。', 'novel-game-plugin' ) );
    }

    // 現在の設定を取得
    $current_game_title = noveltool_get_current_game_title();
    $game_title = get_option( 'noveltool_game_title', $current_game_title );
    $game_description = get_option( 'noveltool_game_description', '' );
    $game_title_image = get_option( 'noveltool_game_title_image', '' );

    // URLパラメーターからメッセージを取得
    $error_message = '';
    $success_message = '';
    
    if ( isset( $_GET['error'] ) ) {
        switch ( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) {
            case 'security':
                $error_message = __( 'セキュリティチェックに失敗しました。', 'novel-game-plugin' );
                break;
        }
    }
    
    if ( isset( $_GET['success'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['success'] ) ) ) {
        $success_message = __( 'ゲーム設定が正常に保存されました。', 'novel-game-plugin' );
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <?php if ( $error_message ) : ?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $error_message ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( $success_message ) : ?>
            <div class="notice notice-success">
                <p><?php echo esc_html( $success_message ); ?></p>
            </div>
        <?php endif; ?>

        <div class="noveltool-game-settings-form">
            <form method="post" action="">
                <?php wp_nonce_field( 'save_game_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="game_title"><?php esc_html_e( 'ゲームタイトル', 'novel-game-plugin' ); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="game_title" 
                                   name="game_title" 
                                   value="<?php echo esc_attr( $game_title ); ?>" 
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e( 'ゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>" />
                            <p class="description">
                                <?php esc_html_e( 'ノベルゲーム全体のタイトルを設定します。', 'novel-game-plugin' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="game_description"><?php esc_html_e( 'ゲーム概要', 'novel-game-plugin' ); ?></label>
                        </th>
                        <td>
                            <textarea id="game_description" 
                                      name="game_description" 
                                      rows="5" 
                                      cols="50" 
                                      class="large-text"
                                      placeholder="<?php esc_attr_e( 'ゲームの概要・説明を入力してください', 'novel-game-plugin' ); ?>"><?php echo esc_textarea( $game_description ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'ゲームの内容や特徴を説明してください。', 'novel-game-plugin' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="game_title_image"><?php esc_html_e( 'タイトル画面画像', 'novel-game-plugin' ); ?></label>
                        </th>
                        <td>
                            <input type="hidden"
                                   id="game_title_image"
                                   name="game_title_image"
                                   value="<?php echo esc_attr( $game_title_image ); ?>" />
                            <img id="game_title_image_preview"
                                 src="<?php echo esc_url( $game_title_image ); ?>"
                                 alt="<?php esc_attr_e( 'タイトル画面画像プレビュー', 'novel-game-plugin' ); ?>"
                                 style="max-width: 400px; height: auto; display: <?php echo $game_title_image ? 'block' : 'none'; ?>;" />
                            <p>
                                <button type="button"
                                        class="button"
                                        id="game_title_image_button">
                                    <?php esc_html_e( 'メディアから選択', 'novel-game-plugin' ); ?>
                                </button>
                                <button type="button"
                                        class="button"
                                        id="game_title_image_remove"
                                        style="display: <?php echo $game_title_image ? 'inline-block' : 'none'; ?>;">
                                    <?php esc_html_e( '画像を削除', 'novel-game-plugin' ); ?>
                                </button>
                            </p>
                            <p class="description">
                                <?php esc_html_e( 'ゲームのタイトル画面に表示する画像を設定します。', 'novel-game-plugin' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" 
                           name="save_game_settings" 
                           id="submit" 
                           class="button button-primary" 
                           value="<?php esc_attr_e( '設定を保存', 'novel-game-plugin' ); ?>" />
                </p>
            </form>
        </div>

        <div class="noveltool-help-section">
            <h3><?php esc_html_e( 'ヘルプ', 'novel-game-plugin' ); ?></h3>
            <p><?php esc_html_e( 'ここでは、ノベルゲーム全体の基本情報を設定できます。', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( 'ゲームタイトルは、すべてのシーンで共通して使用されます。', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( 'ゲーム概要は、ゲームの紹介やあらすじを記載してください。', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( 'タイトル画面画像は、ゲームの開始時に表示されるメイン画像です。', 'novel-game-plugin' ); ?></p>
        </div>
    </div>
    <?php
}

/**
 * ゲーム設定ページ用のスクリプト・スタイルを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.1.0
 */
function noveltool_game_settings_admin_scripts( $hook ) {
    if ( 'novel_game_page_novel-game-settings' !== $hook ) {
        return;
    }

    // WordPressメディアアップローダー用スクリプトの読み込み
    wp_enqueue_media();

    // 管理画面用スクリプトの読み込み
    wp_enqueue_script(
        'noveltool-game-settings-admin',
        NOVEL_GAME_PLUGIN_URL . 'js/admin-game-settings.js',
        array( 'jquery', 'media-upload', 'media-views' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );

    // JavaScript用の翻訳文字列
    $js_strings = array(
        'selectImage'   => __( '画像を選択', 'novel-game-plugin' ),
        'useThisImage'  => __( 'この画像を使う', 'novel-game-plugin' ),
        'confirmRemove' => __( '本当に画像を削除しますか？', 'novel-game-plugin' ),
        'titleRequired' => __( 'ゲームタイトルを入力してください。', 'novel-game-plugin' ),
    );

    wp_localize_script(
        'noveltool-game-settings-admin',
        'novelGameSettings',
        array(
            'strings' => $js_strings,
        )
    );

    // スタイルシートの読み込み
    wp_enqueue_style(
        'noveltool-game-settings-admin',
        NOVEL_GAME_PLUGIN_URL . 'css/admin-game-settings.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_game_settings_admin_scripts' );