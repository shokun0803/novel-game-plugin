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
 * 新規ゲーム作成ページの内容
 *
 * @since 1.1.0
 */
function noveltool_new_game_page() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'このページにアクセスする権限がありません。', 'novel-game-plugin' ) );
    }

    $error_message = '';
    $success_message = '';

    // フォーム送信の処理
    if ( isset( $_POST['create_game'] ) ) {
        // nonceチェック
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'create_new_game' ) ) {
            $error_message = __( 'セキュリティチェックに失敗しました。', 'novel-game-plugin' );
        } else {
            // ゲームタイトルの取得とバリデーション
            $game_title = isset( $_POST['game_title'] ) ? sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) : '';
            
            if ( empty( $game_title ) ) {
                $error_message = __( 'ゲームタイトルを入力してください。', 'novel-game-plugin' );
            } elseif ( noveltool_game_title_exists( $game_title ) ) {
                $error_message = __( 'このゲームタイトルは既に使用されています。', 'novel-game-plugin' );
            } else {
                // 新しいゲームの作成
                $new_game_id = noveltool_create_new_game( $game_title );
                
                if ( $new_game_id && ! is_wp_error( $new_game_id ) ) {
                    // 成功時は編集画面にリダイレクト
                    $edit_url = admin_url( 'post.php?post=' . $new_game_id . '&action=edit' );
                    wp_redirect( $edit_url );
                    exit;
                } else {
                    $error_message = __( 'ゲームの作成に失敗しました。', 'novel-game-plugin' );
                }
            }
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

        <div class="noveltool-new-game-form">
            <form method="post" action="">
                <?php wp_nonce_field( 'create_new_game' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="game_title"><?php esc_html_e( 'ゲームタイトル', 'novel-game-plugin' ); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="game_title" 
                                   name="game_title" 
                                   value="<?php echo isset( $_POST['game_title'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_POST['game_title'] ) ) ) : ''; ?>" 
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e( '新しいゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>"
                                   required />
                            <p class="description">
                                <?php esc_html_e( 'このタイトルは全体のゲームタイトルとして使用されます。', 'novel-game-plugin' ); ?>
                            </p>
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