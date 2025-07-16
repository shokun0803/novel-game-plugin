<?php

/**
 * 新規ゲーム作成ページの管理
 *
 * @package NovelGamePlugin
 * @since 1.1.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 新規ゲーム作成ページをメニューに追加
 *
 * @since 1.1.0
 */
function noveltool_add_new_game_menu() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __( '新規ゲーム作成', 'novel-game-plugin' ),
        __( '新規ゲーム作成', 'novel-game-plugin' ),
        'edit_posts',
        'novel-game-new',
        'noveltool_new_game_page'
    );
}
add_action( 'admin_menu', 'noveltool_add_new_game_menu' );

/**
 * 新規ゲーム作成フォームの処理
 * admin_initフックで実行して、出力前に処理を完了させる
 *
 * @since 1.1.0
 */
function noveltool_handle_new_game_form() {
    // 新規ゲーム作成ページでのフォーム送信のみ処理
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'novel-game-new' ) {
        return;
    }

    // フォーム送信の処理
    if ( isset( $_POST['create_game'] ) ) {
        // 権限チェック
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'このページにアクセスする権限がありません。', 'novel-game-plugin' ) );
        }

        // nonceチェック
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'create_new_game' ) ) {
            // セキュリティエラーのメッセージを設定してリダイレクト
            $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // ゲームタイトルの取得とバリデーション
        $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
        
        if ( empty( $game_title ) ) {
            $redirect_url = add_query_arg( 'error', 'empty_title', admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        if ( noveltool_game_title_exists( $game_title ) ) {
            $redirect_url = add_query_arg( 
                array( 
                    'error' => 'title_exists', 
                    'title' => urlencode( $game_title ) 
                ), 
                admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) 
            );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // 新しいゲームの作成
        $new_game_id = noveltool_create_new_game( $game_title );
        
        if ( $new_game_id && ! is_wp_error( $new_game_id ) ) {
    // 新規ゲーム作成ページでのフォーム送信のみ処理
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'novel-game-new' ) {
        return;
    }

    // フォーム送信の処理
    if ( isset( $_POST['create_game'] ) ) {
        // 権限チェック
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'このページにアクセスする権限がありません。', 'novel-game-plugin' ) );
        }

        // nonceチェック
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'create_new_game' ) ) {
            // セキュリティエラーのメッセージを設定してリダイレクト
            $redirect_url = add_query_arg( 'error', 'security', admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // ゲームタイトルの取得とバリデーション
        $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
        
        if ( empty( $game_title ) ) {
            $redirect_url = add_query_arg( 'error', 'empty_title', admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        if ( noveltool_game_title_exists( $game_title ) ) {
            $redirect_url = add_query_arg( 
                array( 
                    'error' => 'title_exists', 
                    'title' => urlencode( $game_title ) 
                ), 
                admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) 
            );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        // 新しいゲームの作成
        $new_game_id = noveltool_create_new_game( $game_title );
        
        if ( $new_game_id && ! is_wp_error( $new_game_id ) ) {
            // 成功時は編集画面にリダイレクト
            $edit_url = admin_url( 'post.php?post=' . $new_game_id . '&action=edit' );
            wp_safe_redirect( $edit_url );
            exit;
        } else {
            $redirect_url = add_query_arg( 
                array( 
                    'error' => 'create_failed', 
                    'title' => urlencode( $game_title ) 
                ), 
                admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) 
            );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }
}
?>
                            <input type="text" id="game_title" name="game_title" value="<?php echo isset( $_GET['title'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['title'] ) ) ) : ''; ?>" class="regular-text" placeholder="<?php esc_attr_e( '新しいゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>" required />
                            <p class="description"><?php esc_html_e( 'このタイトルは全体のゲームタイトルとして使用されます。', 'novel-game-plugin' ); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" 
                           name="create_game" 
                           id="submit" 
                           class="button button-primary" 
                           value="<?php esc_attr_e( 'ゲームを作成', 'novel-game-plugin' ); ?>" />
                </p>
            </form>
        </div>

        <div class="noveltool-help-section">
            <h3><?php esc_html_e( 'ヘルプ', 'novel-game-plugin' ); ?></h3>
            <p><?php esc_html_e( 'ゲームタイトルを入力して「ゲームを作成」ボタンをクリックすると、新しいゲームの最初のシーンが作成されます。', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( '作成後は自動的にシーン編集画面に移動し、背景画像、キャラクター、セリフ、選択肢などを設定できます。', 'novel-game-plugin' ); ?></p>
        </div>
    </div>
<?php
}
    
    if (!empty($existing_games)) {
        wp_send_json_error(['message' => 'このタイトルは既に使用されています。']);
    }
    
    // 新規投稿を作成
    $post_data = [
        'post_type' => 'novel_game',
        'post_title' => sprintf('%s - シーン1', $title),
        'post_status' => 'draft',
        'post_author' => get_current_user_id()
    ];
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => '投稿の作成に失敗しました。']);
    }
    
    // ゲームタイトルをメタデータとして保存
    update_post_meta($post_id, '_game_title', $title);
    
    // 初期データを設定
    update_post_meta($post_id, '_dialogue_text', 'ゲームを開始します。');
    
    wp_send_json_success([
        'post_id' => $post_id,
        'title' => $title,
        'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit')
    ]);
}

/**
 * ゲームタイトルが既に存在するかチェック
 *
 * @param string $game_title ゲームタイトル
 * @return bool 存在する場合true、存在しない場合false
 * @since 1.1.0
 */
function noveltool_game_title_exists( $game_title ) {
    $existing_posts = get_posts( array(
        'post_type'      => 'novel_game',
        'meta_key'       => '_game_title',
        'meta_value'     => $game_title,
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ) );

    return ! empty( $existing_posts );
}

/**
 * 新しいゲームを作成
 *
 * @param string $game_title ゲームタイトル
 * @return int|WP_Error 作成された投稿のID、失敗時はWP_Error
 * @since 1.1.0
 */
function noveltool_create_new_game( $game_title ) {
    // 最初のシーンのタイトルを生成
    $scene_title = sprintf( __( '%s - 開始シーン', 'novel-game-plugin' ), $game_title );

    // 新規投稿の作成
    $post_data = array(
        'post_type'    => 'novel_game',
        'post_title'   => $scene_title,
        'post_content' => '',
        'post_status'  => 'publish',
    );

    $new_id = wp_insert_post( $post_data );

    if ( $new_id && ! is_wp_error( $new_id ) ) {
        // ゲームタイトルをメタデータとして保存
        update_post_meta( $new_id, '_game_title', $game_title );
        
        // ゲームタイトルをグローバル設定としても保存
        update_option( 'noveltool_game_title', $game_title );
        
        // デフォルトのセリフを設定
        $default_dialogue = sprintf( __( 'ようこそ「%s」へ！', 'novel-game-plugin' ), $game_title );
        update_post_meta( $new_id, '_dialogue_text', $default_dialogue );
    }

    return $new_id;
}

/**
 * 新規ゲーム作成ページ用のスタイルを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.1.0
 */
function noveltool_new_game_admin_styles( $hook ) {
    if ( 'novel_game_page_novel-game-new' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'noveltool-new-game-admin',
        NOVEL_GAME_PLUGIN_URL . 'css/admin-new-game.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );
}

add_action( 'admin_enqueue_scripts', 'noveltool_new_game_admin_styles' );
