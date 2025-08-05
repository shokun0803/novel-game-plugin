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

    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'このページにアクセスする権限がありません。', 'novel-game-plugin' ) );
    }

    // ゲーム追加の処理
    if ( isset( $_POST['add_game'] ) ) {
        // nonceチェック
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_games' ) ) {
            $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // ゲーム情報の取得とバリデーション
        $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
        $game_subtitle = isset( $_POST['game_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['game_subtitle'] ) ) : '';
        $game_description = isset( $_POST['game_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['game_description'] ) ) : '';
        $game_title_image = isset( $_POST['game_title_image'] ) ? sanitize_url( wp_unslash( $_POST['game_title_image'] ) ) : '';

        if ( empty( $game_title ) ) {
            $redirect_url = add_query_arg( 'error', 'empty_title', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // 同じタイトルのゲームが既に存在するかチェック
        if ( noveltool_get_game_by_title( $game_title ) ) {
            $redirect_url = add_query_arg( 'error', 'duplicate_title', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // ゲームを保存
        $game_data = array(
            'title'       => $game_title,
            'subtitle'    => $game_subtitle,
            'description' => $game_description,
            'title_image' => $game_title_image,
        );

        $game_id = noveltool_save_game( $game_data );

        if ( $game_id ) {
            $redirect_url = add_query_arg( 'success', 'added', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
        } else {
            $redirect_url = add_query_arg( 'error', 'save_failed', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    // ゲーム更新の処理
    if ( isset( $_POST['update_game'] ) ) {
        // nonceチェック
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_games' ) ) {
            $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;
        $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
        $game_subtitle = isset( $_POST['game_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['game_subtitle'] ) ) : '';
        $game_description = isset( $_POST['game_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['game_description'] ) ) : '';
        $game_title_image = isset( $_POST['game_title_image'] ) ? sanitize_url( wp_unslash( $_POST['game_title_image'] ) ) : '';
        $old_title = isset( $_POST['old_title'] ) ? sanitize_text_field( wp_unslash( $_POST['old_title'] ) ) : '';

        if ( empty( $game_title ) ) {
            $redirect_url = add_query_arg( array( 'error' => 'empty_title', 'edit' => $game_id ), admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // 同じタイトルのゲームが既に存在するかチェック（自分以外）
        $existing_game = noveltool_get_game_by_title( $game_title );
        if ( $existing_game && $existing_game['id'] != $game_id ) {
            $redirect_url = add_query_arg( array( 'error' => 'duplicate_title', 'edit' => $game_id ), admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // ゲームを更新
        $game_data = array(
            'id'          => $game_id,
            'title'       => $game_title,
            'subtitle'    => $game_subtitle,
            'description' => $game_description,
            'title_image' => $game_title_image,
        );

        $result = noveltool_save_game( $game_data );

        if ( $result ) {
            // タイトルが変更された場合は、既存のシーンのゲームタイトルも更新
            if ( $old_title && $old_title !== $game_title ) {
                noveltool_update_scenes_game_title( $old_title, $game_title );
            }
            
            $redirect_url = add_query_arg( 'success', 'updated', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
        } else {
            $redirect_url = add_query_arg( array( 'error' => 'save_failed', 'edit' => $game_id ), admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    // ゲーム削除の処理
    if ( isset( $_POST['delete_game'] ) ) {
        // nonceチェック
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'manage_games' ) ) {
            $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        $game_id = isset( $_POST['game_id'] ) ? intval( wp_unslash( $_POST['game_id'] ) ) : 0;

        if ( $game_id ) {
            $result = noveltool_delete_game( $game_id );

            if ( $result ) {
                $redirect_url = add_query_arg( 'success', 'deleted', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            } else {
                $redirect_url = add_query_arg( 'error', 'delete_failed', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
            }
        } else {
            $redirect_url = add_query_arg( 'error', 'invalid_id', admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) );
        }

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
 * @param string $old_title 旧ゲームタイトル  
 * @param string $new_title 新ゲームタイトル
 * @since 1.1.0
 */
function noveltool_update_scenes_game_title( $old_title, $new_title = null ) {
    global $wpdb;
    
    // 単一引数の場合は後方互換性のための処理
    if ( $new_title === null ) {
        $new_title = $old_title;
        // novel_game投稿タイプのすべての投稿のゲームタイトルを更新
        $wpdb->update(
            $wpdb->postmeta,
            array( 'meta_value' => $new_title ),
            array( 'meta_key' => '_game_title' ),
            array( '%s' ),
            array( '%s' )
        );
    } else {
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

    // 編集モードの確認
    $edit_mode = isset( $_GET['edit'] ) ? intval( $_GET['edit'] ) : 0;
    $editing_game = null;
    
    if ( $edit_mode ) {
        $editing_game = noveltool_get_game_by_id( $edit_mode );
        if ( ! $editing_game ) {
            $edit_mode = 0;
        }
    }

    // 全ゲームの取得
    $all_games = noveltool_get_all_games();

    // URLパラメーターからメッセージを取得
    $error_message = '';
    $success_message = '';
    
    if ( isset( $_GET['error'] ) ) {
        switch ( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) {
            case 'security':
                $error_message = __( 'セキュリティチェックに失敗しました。', 'novel-game-plugin' );
                break;
            case 'empty_title':
                $error_message = __( 'ゲームタイトルを入力してください。', 'novel-game-plugin' );
                break;
            case 'duplicate_title':
                $error_message = __( 'そのタイトルのゲームは既に存在します。', 'novel-game-plugin' );
                break;
            case 'save_failed':
                $error_message = __( 'ゲームの保存に失敗しました。', 'novel-game-plugin' );
                break;
            case 'delete_failed':
                $error_message = __( 'ゲームの削除に失敗しました。', 'novel-game-plugin' );
                break;
            case 'invalid_id':
                $error_message = __( '無効なゲームIDです。', 'novel-game-plugin' );
                break;
        }
    }
    
    if ( isset( $_GET['success'] ) ) {
        switch ( sanitize_text_field( wp_unslash( $_GET['success'] ) ) ) {
            case 'added':
                $success_message = __( 'ゲームが正常に追加されました。', 'novel-game-plugin' );
                break;
            case 'updated':
                $success_message = __( 'ゲームが正常に更新されました。', 'novel-game-plugin' );
                break;
            case 'deleted':
                $success_message = __( 'ゲームが正常に削除されました。', 'novel-game-plugin' );
                break;
        }
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

        <div class="noveltool-games-container">
            <?php if ( $edit_mode && $editing_game ) : ?>
                <!-- ゲーム編集フォーム -->
                <div class="noveltool-game-edit-form">
                    <h2><?php esc_html_e( 'ゲームを編集', 'novel-game-plugin' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'manage_games' ); ?>
                        <input type="hidden" name="game_id" value="<?php echo esc_attr( $editing_game['id'] ); ?>" />
                        <input type="hidden" name="old_title" value="<?php echo esc_attr( $editing_game['title'] ); ?>" />
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="game_title"><?php esc_html_e( 'ゲームタイトル', 'novel-game-plugin' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="game_title" 
                                           name="game_title" 
                                           value="<?php echo esc_attr( $editing_game['title'] ); ?>" 
                                           class="regular-text"
                                           required
                                           placeholder="<?php esc_attr_e( 'ゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="game_subtitle"><?php esc_html_e( 'サブタイトル', 'novel-game-plugin' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="game_subtitle" 
                                           name="game_subtitle" 
                                           value="<?php echo esc_attr( isset( $editing_game['subtitle'] ) ? $editing_game['subtitle'] : '' ); ?>" 
                                           class="regular-text"
                                           placeholder="<?php esc_attr_e( 'サブタイトル（任意）', 'novel-game-plugin' ); ?>" />
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
                                              placeholder="<?php esc_attr_e( 'ゲームの概要・説明を入力してください', 'novel-game-plugin' ); ?>"><?php echo esc_textarea( $editing_game['description'] ); ?></textarea>
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
                                           value="<?php echo esc_attr( $editing_game['title_image'] ); ?>" />
                                    <img id="game_title_image_preview"
                                         src="<?php echo esc_url( $editing_game['title_image'] ); ?>"
                                         alt="<?php esc_attr_e( 'タイトル画面画像プレビュー', 'novel-game-plugin' ); ?>"
                                         style="max-width: 400px; height: auto; display: <?php echo $editing_game['title_image'] ? 'block' : 'none'; ?>;" />
                                    <p>
                                        <button type="button"
                                                class="button"
                                                id="game_title_image_button">
                                            <?php esc_html_e( 'メディアから選択', 'novel-game-plugin' ); ?>
                                        </button>
                                        <button type="button"
                                                class="button"
                                                id="game_title_image_remove"
                                                style="display: <?php echo $editing_game['title_image'] ? 'inline-block' : 'none'; ?>;">
                                            <?php esc_html_e( '画像を削除', 'novel-game-plugin' ); ?>
                                        </button>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" 
                                   name="update_game" 
                                   class="button button-primary" 
                                   value="<?php esc_attr_e( 'ゲームを更新', 'novel-game-plugin' ); ?>" />
                            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) ); ?>" 
                               class="button button-secondary"><?php esc_html_e( 'キャンセル', 'novel-game-plugin' ); ?></a>
                        </p>
                    </form>
                </div>
            <?php else : ?>
                <!-- ゲーム一覧とフォーム -->
                <div class="noveltool-games-list">
                    <h2><?php esc_html_e( 'ゲーム一覧', 'novel-game-plugin' ); ?></h2>
                    
                    <?php if ( ! empty( $all_games ) ) : ?>
                        <div class="noveltool-games-grid">
                            <?php foreach ( $all_games as $game ) : ?>
                                <div class="noveltool-game-card">
                                    <div class="game-info">
                                        <h3><?php echo esc_html( $game['title'] ); ?></h3>
                                        <?php if ( isset( $game['subtitle'] ) && $game['subtitle'] ) : ?>
                                            <p class="game-subtitle"><?php echo esc_html( $game['subtitle'] ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( $game['description'] ) : ?>
                                            <p class="game-description"><?php echo esc_html( mb_substr( $game['description'], 0, 100 ) ); ?><?php echo mb_strlen( $game['description'] ) > 100 ? '...' : ''; ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ( $game['title_image'] ) : ?>
                                            <div class="game-image">
                                                <img src="<?php echo esc_url( $game['title_image'] ); ?>" alt="<?php echo esc_attr( $game['title'] ); ?>" />
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="game-meta">
                                            <span class="game-id">ID: <?php echo esc_html( $game['id'] ); ?></span>
                                            <?php if ( isset( $game['created_at'] ) ) : ?>
                                                <span class="game-created"><?php echo esc_html( date_i18n( 'Y年m月d日', $game['created_at'] ) ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="game-actions">
                                        <a href="<?php echo esc_url( add_query_arg( 'edit', $game['id'] ) ); ?>" 
                                           class="button button-primary"><?php esc_html_e( '編集', 'novel-game-plugin' ); ?></a>
                                        <form method="post" action="" style="display: inline;">
                                            <?php wp_nonce_field( 'manage_games' ); ?>
                                            <input type="hidden" name="game_id" value="<?php echo esc_attr( $game['id'] ); ?>" />
                                            <input type="submit" 
                                                   name="delete_game" 
                                                   class="button button-secondary" 
                                                   value="<?php esc_attr_e( '削除', 'novel-game-plugin' ); ?>"
                                                   onclick="return confirm('<?php esc_attr_e( '本当にこのゲームを削除しますか？', 'novel-game-plugin' ); ?>');" />
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="no-games-message">
                            <p><?php esc_html_e( 'まだゲームが作成されていません。', 'novel-game-plugin' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 新規ゲーム作成フォーム -->
                <div class="noveltool-add-game-form">
                    <h2><?php esc_html_e( '新しいゲームを追加', 'novel-game-plugin' ); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'manage_games' ); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="game_title"><?php esc_html_e( 'ゲームタイトル', 'novel-game-plugin' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="game_title" 
                                           name="game_title" 
                                           value="" 
                                           class="regular-text"
                                           required
                                           placeholder="<?php esc_attr_e( 'ゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>" />
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="game_subtitle"><?php esc_html_e( 'サブタイトル', 'novel-game-plugin' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="game_subtitle" 
                                           name="game_subtitle" 
                                           value="" 
                                           class="regular-text"
                                           placeholder="<?php esc_attr_e( 'サブタイトル（任意）', 'novel-game-plugin' ); ?>" />
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
                                              placeholder="<?php esc_attr_e( 'ゲームの概要・説明を入力してください', 'novel-game-plugin' ); ?>"></textarea>
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
                                           value="" />
                                    <img id="game_title_image_preview"
                                         src=""
                                         alt="<?php esc_attr_e( 'タイトル画面画像プレビュー', 'novel-game-plugin' ); ?>"
                                         style="max-width: 400px; height: auto; display: none;" />
                                    <p>
                                        <button type="button"
                                                class="button"
                                                id="game_title_image_button">
                                            <?php esc_html_e( 'メディアから選択', 'novel-game-plugin' ); ?>
                                        </button>
                                        <button type="button"
                                                class="button"
                                                id="game_title_image_remove"
                                                style="display: none;">
                                            <?php esc_html_e( '画像を削除', 'novel-game-plugin' ); ?>
                                        </button>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" 
                                   name="add_game" 
                                   class="button button-primary" 
                                   value="<?php esc_attr_e( 'ゲームを追加', 'novel-game-plugin' ); ?>" />
                        </p>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- ショートコードセクション -->
        <div class="noveltool-shortcode-section">
            <h3><?php esc_html_e( 'ショートコード', 'novel-game-plugin' ); ?></h3>
            <p><?php esc_html_e( '固定ページや投稿にゲーム一覧を表示する際に使用するショートコードです。', 'novel-game-plugin' ); ?></p>
            
            <div class="noveltool-shortcode-examples">
                <div class="shortcode-item">
                    <h4><?php esc_html_e( '全ゲーム一覧を表示（推奨）', 'novel-game-plugin' ); ?></h4>
                    <div class="shortcode-box">
                        <code id="shortcode-game-list">[novel_game_list]</code>
                        <button type="button" class="button button-small copy-shortcode" data-shortcode="[novel_game_list]">
                            <?php esc_html_e( 'コピー', 'novel-game-plugin' ); ?>
                        </button>
                    </div>
                    <p class="shortcode-description"><?php esc_html_e( '作成された全てのゲームを美しいカード形式で一覧表示します。最も使いやすく推奨のショートコードです。', 'novel-game-plugin' ); ?></p>
                </div>

                <div class="shortcode-item">
                    <h4><?php esc_html_e( '全ゲーム一覧を表示（従来版）', 'novel-game-plugin' ); ?></h4>
                    <div class="shortcode-box">
                        <code id="shortcode-all-games">[novel_game_posts]</code>
                        <button type="button" class="button button-small copy-shortcode" data-shortcode="[novel_game_posts]">
                            <?php esc_html_e( 'コピー', 'novel-game-plugin' ); ?>
                        </button>
                    </div>
                    <p class="shortcode-description"><?php esc_html_e( '作成された全てのゲームをカード形式で一覧表示します。', 'novel-game-plugin' ); ?></p>
                </div>

                <?php if ( ! empty( $all_games ) ) : ?>
                    <div class="shortcode-item">
                        <h4><?php esc_html_e( '特定のゲームを表示', 'novel-game-plugin' ); ?></h4>
                        <?php foreach ( $all_games as $game ) : ?>
                            <div class="shortcode-box">
                                <code id="shortcode-game-<?php echo esc_attr( $game['id'] ); ?>">[novel_game_posts game_title="<?php echo esc_attr( $game['title'] ); ?>"]</code>
                                <button type="button" class="button button-small copy-shortcode" data-shortcode='[novel_game_posts game_title="<?php echo esc_attr( $game['title'] ); ?>"]'>
                                    <?php esc_html_e( 'コピー', 'novel-game-plugin' ); ?>
                                </button>
                            </div>
                            <p class="shortcode-description"><?php printf( esc_html__( '「%s」のシーン一覧を表示します。', 'novel-game-plugin' ), esc_html( $game['title'] ) ); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="shortcode-item">
                    <h4><?php esc_html_e( 'オプション付きの使用例', 'novel-game-plugin' ); ?></h4>
                    <div class="shortcode-box">
                        <code id="shortcode-with-options">[novel_game_posts limit="5" show_date="false" orderby="title" order="ASC"]</code>
                        <button type="button" class="button button-small copy-shortcode" data-shortcode='[novel_game_posts limit="5" show_date="false" orderby="title" order="ASC"]'>
                            <?php esc_html_e( 'コピー', 'novel-game-plugin' ); ?>
                        </button>
                    </div>
                    <p class="shortcode-description"><?php esc_html_e( '表示件数制限、日付非表示、タイトル順ソートなどのオプションが利用できます。', 'novel-game-plugin' ); ?></p>
                </div>
            </div>

            <div class="shortcode-options">
                <h4><?php esc_html_e( 'ゲーム一覧ショートコード（[novel_game_list]）のオプション', 'novel-game-plugin' ); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'show_count', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( 'シーン数の表示（true/false、デフォルト: true）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'show_description', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( 'ゲーム説明の表示（true/false、デフォルト: false）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'columns', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( '表示列数（1-6、デフォルト: 3）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'orderby', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( '並び順（title等、デフォルト: title）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'order', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( '昇順/降順（ASC/DESC、デフォルト: ASC）', 'novel-game-plugin' ); ?></td>
                    </tr>
                </table>

                <h4><?php esc_html_e( 'ゲーム投稿ショートコード（[novel_game_posts]）のオプション', 'novel-game-plugin' ); ?></h4>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'game_title', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( '特定のゲームタイトルを指定（未指定の場合は全ゲーム表示）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'limit', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( '表示する投稿数（デフォルト: -1 = 全て）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'orderby', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( '並び順（date, title等）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'order', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( '昇順/降順（ASC/DESC）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'show_title', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( 'ゲームタイトル表示（true/false）', 'novel-game-plugin' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'show_date', 'novel-game-plugin' ); ?></th>
                        <td><?php esc_html_e( '日付表示（true/false）', 'novel-game-plugin' ); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="noveltool-help-section">
            <h3><?php esc_html_e( 'ヘルプ', 'novel-game-plugin' ); ?></h3>
            <p><?php esc_html_e( 'ここでは、複数のノベルゲームの基本情報を管理できます。', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( 'ゲームタイトルは、シーン作成時に選択できます。', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( 'ゲーム概要は、ゲームの紹介やあらすじを記載してください。', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( 'タイトル画面画像は、ゲームの開始時に表示されるメイン画像です。', 'novel-game-plugin' ); ?></p>
        </div>
    </div>
    
    <style>
    .noveltool-games-container {
        max-width: 1200px;
        margin-top: 20px;
    }
    
    .noveltool-games-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .noveltool-game-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .noveltool-game-card h3 {
        margin: 0 0 10px 0;
        color: #333;
    }
    
    .game-description {
        color: #666;
        margin-bottom: 15px;
        line-height: 1.4;
    }
    
    .game-subtitle {
        color: #888;
        font-style: italic;
        margin: 5px 0 10px 0;
        font-size: 0.9em;
    }
    
    .game-image {
        margin-bottom: 15px;
    }
    
    .game-image img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
    }
    
    .game-meta {
        margin-bottom: 15px;
        font-size: 0.9em;
        color: #999;
    }
    
    .game-meta span {
        margin-right: 15px;
    }
    
    .game-actions {
        border-top: 1px solid #eee;
        padding-top: 15px;
    }
    
    .game-actions .button {
        margin-right: 10px;
    }
    
    .noveltool-add-game-form {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-top: 30px;
    }
    
    .noveltool-add-game-form h2 {
        margin-top: 0;
        color: #333;
    }
    
    .noveltool-game-edit-form {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .noveltool-game-edit-form h2 {
        margin-top: 0;
        color: #333;
    }
    
    .no-games-message {
        text-align: center;
        padding: 40px;
        color: #666;
    }
    
    .noveltool-shortcode-section {
        margin-top: 40px;
        padding: 20px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .noveltool-shortcode-section h3 {
        margin-top: 0;
        color: #333;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    
    .noveltool-shortcode-examples {
        margin-bottom: 30px;
    }
    
    .shortcode-item {
        margin-bottom: 25px;
        padding: 15px;
        border: 1px solid #eee;
        border-radius: 4px;
        background: #f9f9f9;
    }
    
    .shortcode-item h4 {
        margin: 0 0 10px 0;
        color: #0073aa;
    }
    
    .shortcode-box {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 3px;
        padding: 8px;
    }
    
    .shortcode-box code {
        flex: 1;
        background: transparent;
        color: #d63638;
        font-family: Consolas, Monaco, monospace;
        font-size: 13px;
        padding: 0;
        user-select: all;
    }
    
    .shortcode-box .copy-shortcode {
        margin-left: 10px;
        padding: 3px 8px;
        font-size: 11px;
        line-height: 1;
    }
    
    .shortcode-description {
        margin: 5px 0 0 0;
        font-size: 0.9em;
        color: #666;
        font-style: italic;
    }
    
    .shortcode-options {
        margin-top: 30px;
    }
    
    .shortcode-options h4 {
        margin: 0 0 15px 0;
        color: #333;
    }
    
    .shortcode-options .form-table {
        margin-top: 0;
    }
    
    .shortcode-options .form-table th {
        font-weight: 600;
        color: #0073aa;
        font-family: Consolas, Monaco, monospace;
        font-size: 13px;
    }
    
    .copy-success {
        background-color: #00a32a !important;
        color: white !important;
    }
    
    .noveltool-help-section {
        margin-top: 40px;
        padding: 20px;
        background: #f0f0f0;
        border-radius: 8px;
    }
    
    .noveltool-help-section h3 {
        margin-top: 0;
        color: #333;
    }
    </style>
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