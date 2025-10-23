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
        __( 'Create New Game', 'novel-game-plugin' ),
        '➕ ' . __( 'Create New Game', 'novel-game-plugin' ),
        'edit_posts',
        'novel-game-new',
        'noveltool_new_game_page',
        2
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
            wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
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

        // ゲーム情報の追加取得
        $game_description = isset( $_POST['game_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['game_description'] ) ) : '';
        $game_title_image = isset( $_POST['game_title_image'] ) ? sanitize_url( wp_unslash( $_POST['game_title_image'] ) ) : '';
        
        // ゲームを作成（ゲームエントリを追加）
        $game_data = array(
            'title'         => $game_title,
            'description'   => $game_description,
            'title_image'   => $game_title_image,
            'game_over_text' => 'Game Over',
        );
        
        $game_id = noveltool_save_game( $game_data );
        
        if ( $game_id ) {
            // 成功時はマイゲーム画面（ゲーム個別管理）にリダイレクト
            $redirect_url = noveltool_get_game_manager_url( $game_id, 'new-scene' );
            wp_safe_redirect( $redirect_url );
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
add_action( 'admin_init', 'noveltool_handle_new_game_form' );

/**
 * 新規ゲーム作成ページの内容
 *
 * @since 1.1.0
 */
function noveltool_new_game_page() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // URLパラメーターからエラーメッセージを取得
    $error_message = '';
    if ( isset( $_GET['error'] ) ) {
        switch ( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) {
            case 'security':
                $error_message = __( 'Security check failed.', 'novel-game-plugin' );
                break;
            case 'empty_title':
                $error_message = __( 'Please enter a game title.', 'novel-game-plugin' );
                break;
            case 'title_exists':
                $error_message = __( 'This game title is already in use.', 'novel-game-plugin' );
                break;
            case 'create_failed':
                $error_message = __( 'Failed to create game.', 'novel-game-plugin' );
                break;
        }
    }

    $success_message = '';

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
                            <label for="game_title"><?php esc_html_e( 'Game Title', 'novel-game-plugin' ); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="game_title" 
                                   name="game_title" 
                                   value="<?php echo isset( $_GET['title'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['title'] ) ) ) : ''; ?>" 
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e( 'Please enter a title for the new game', 'novel-game-plugin' ); ?>"
                                   required />
                            <p class="description">
                                <?php esc_html_e( 'This title will be used as the overall game title.', 'novel-game-plugin' ); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="game_description"><?php esc_html_e( 'Game Overview', 'novel-game-plugin' ); ?></label>
                        </th>
                        <td>
                            <textarea id="game_description" 
                                      name="game_description" 
                                      rows="5" 
                                      class="large-text"
                                      placeholder="<?php esc_attr_e( 'Please enter a game overview/description', 'novel-game-plugin' ); ?>"></textarea>
                            <p class="description">
                                <?php esc_html_e( 'Optional description of your game.', 'novel-game-plugin' ); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="game_title_image"><?php esc_html_e( 'Title Screen Image', 'novel-game-plugin' ); ?></label>
                        </th>
                        <td>
                            <input type="hidden"
                                   id="game_title_image"
                                   name="game_title_image"
                                   value="" />
                            <img id="game_title_image_preview"
                                 src=""
                                 alt="<?php esc_attr_e( 'Title Screen Image Preview', 'novel-game-plugin' ); ?>"
                                 style="max-width: 400px; height: auto; display: none;" />
                            <p>
                                <button type="button"
                                        class="button"
                                        id="game_title_image_button">
                                    <?php esc_html_e( 'Select from Media', 'novel-game-plugin' ); ?>
                                </button>
                                <button type="button"
                                        class="button"
                                        id="game_title_image_remove"
                                        style="display: none;">
                                    <?php esc_html_e( 'Delete Image', 'novel-game-plugin' ); ?>
                                </button>
                            </p>
                            <p class="description">
                                <?php esc_html_e( 'Optional title screen image for your game.', 'novel-game-plugin' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" 
                           name="create_game" 
                           id="submit" 
                           class="button button-primary" 
                           value="<?php esc_attr_e( 'Create Game', 'novel-game-plugin' ); ?>" />
                </p>
            </form>
        </div>

        <div class="noveltool-help-section">
            <h3><?php esc_html_e( 'Help', 'novel-game-plugin' ); ?></h3>
            <p><?php esc_html_e( 'Enter a game title and click the "Create Game" button to create a new game.', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( 'After creation, you will be taken to the game management screen where you can create scenes, manage settings, and configure your game.', 'novel-game-plugin' ); ?></p>
            <p><?php esc_html_e( 'You can optionally add a game overview and title screen image for a better presentation.', 'novel-game-plugin' ); ?></p>
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
    $scene_title = sprintf( __( '%s - Start Scene', 'novel-game-plugin' ), $game_title );

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
        $default_dialogue = sprintf( __( 'Welcome to "%s"!', 'novel-game-plugin' ), $game_title );
        update_post_meta( $new_id, '_dialogue_text', $default_dialogue );
    }

    return $new_id;
}

/**
 * 新規ゲーム作成ページ用のスタイルとスクリプトを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.1.0
 */
function noveltool_new_game_admin_scripts( $hook ) {
    // get_current_screen() の null チェック
    $current_screen = get_current_screen();
    if ( ! $current_screen ) {
        return;
    }

    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-new' !== $hook ) {
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

    // スタイルシートの読み込み
    if ( file_exists( NOVEL_GAME_PLUGIN_PATH . 'css/admin-new-game.css' ) ) {
        wp_enqueue_style(
            'noveltool-new-game-admin',
            NOVEL_GAME_PLUGIN_URL . 'css/admin-new-game.css',
            array(),
            NOVEL_GAME_PLUGIN_VERSION
        );
    }
}
add_action( 'admin_enqueue_scripts', 'noveltool_new_game_admin_scripts' );