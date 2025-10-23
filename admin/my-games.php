<?php
/**
 * マイゲームページ（ゲーム一覧・選択・管理の統合）
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * マイゲームページの内容を表示
 *
 * @since 1.2.0
 */
function noveltool_my_games_page() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // ゲーム選択の処理
    $selected_game_id = 0;
    if ( isset( $_GET['game_id'] ) && isset( $_GET['_wpnonce'] ) ) {
        if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'select_game' ) ) {
            $selected_game_id = intval( $_GET['game_id'] );
            // user_metaに保存
            update_user_meta( get_current_user_id(), 'noveltool_selected_game_id', $selected_game_id );
        }
    } elseif ( isset( $_GET['action'] ) && $_GET['action'] === 'select' ) {
        // ゲーム選択がクリアされた場合
        delete_user_meta( get_current_user_id(), 'noveltool_selected_game_id' );
    } else {
        // user_metaから取得
        $saved_game_id = get_user_meta( get_current_user_id(), 'noveltool_selected_game_id', true );
        if ( $saved_game_id ) {
            $selected_game_id = intval( $saved_game_id );
        }
    }

    // 選択されたゲームの取得
    $selected_game = null;
    if ( $selected_game_id ) {
        $selected_game = noveltool_get_game_by_id( $selected_game_id );
        // ゲームが存在しない場合は選択をクリア
        if ( ! $selected_game ) {
            $selected_game_id = 0;
            delete_user_meta( get_current_user_id(), 'noveltool_selected_game_id' );
        }
    }

    // ゲームが選択されている場合は個別管理画面を表示
    if ( $selected_game ) {
        noveltool_game_manager_page( $selected_game );
        return;
    }

    // ゲーム一覧の取得
    $all_games = noveltool_get_all_games();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'My Games', 'novel-game-plugin' ); ?></h1>
        
        <?php if ( empty( $all_games ) ) : ?>
            <div class="noveltool-no-games">
                <p><?php esc_html_e( 'No games have been created yet.', 'novel-game-plugin' ); ?></p>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Create New Game', 'novel-game-plugin' ); ?>
                    </a>
                </p>
            </div>
        <?php else : ?>
            <div class="noveltool-games-list">
                <p class="description"><?php esc_html_e( 'Select a game to manage its scenes and settings.', 'novel-game-plugin' ); ?></p>
                <div class="noveltool-games-grid">
                    <?php foreach ( $all_games as $game ) : ?>
                        <?php
                        $posts = noveltool_get_posts_by_game_title( $game['title'] );
                        $scene_count = count( $posts );
                        $first_post = ! empty( $posts ) ? $posts[0] : null;
                        
                        // サムネイル画像の取得（優先順位: タイトル画像 > 最初のシーンの背景）
                        $thumbnail = '';
                        if ( ! empty( $game['title_image'] ) ) {
                            $thumbnail = $game['title_image'];
                        } elseif ( $first_post ) {
                            $thumbnail = get_post_meta( $first_post->ID, '_background_image', true );
                        }
                        ?>
                        <div class="noveltool-game-card">
                            <?php if ( $thumbnail ) : ?>
                                <div class="game-thumbnail">
                                    <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $game['title'] ); ?>" />
                                </div>
                            <?php endif; ?>
                            <div class="game-info">
                                <h3><?php echo esc_html( $game['title'] ); ?></h3>
                                <?php if ( ! empty( $game['description'] ) ) : ?>
                                    <p class="game-description"><?php echo esc_html( wp_trim_words( $game['description'], 20, '...' ) ); ?></p>
                                <?php endif; ?>
                                <div class="game-meta">
                                    <span class="scene-count">
                                        <span class="dashicons dashicons-media-document"></span>
                                        <?php printf( esc_html__( '%d Scenes', 'novel-game-plugin' ), $scene_count ); ?>
                                    </span>
                                    <?php if ( isset( $game['created_at'] ) ) : ?>
                                        <span class="game-created">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                            <?php echo esc_html( date_i18n( get_option( 'date_format' ), $game['created_at'] ) ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="game-actions">
                                <a href="<?php echo esc_url( noveltool_get_game_manager_url( $game['id'], 'scenes', array( '_wpnonce' => wp_create_nonce( 'select_game' ) ) ) ); ?>" class="button button-primary">
                                    <?php esc_html_e( 'Manage', 'novel-game-plugin' ); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="noveltool-add-new-game-cta">
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) ); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'Create New Game', 'novel-game-plugin' ); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * マイゲームページ用のスタイルを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.2.0
 */
function noveltool_my_games_admin_styles( $hook ) {
    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-my-games' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'noveltool-my-games-admin',
        NOVEL_GAME_PLUGIN_URL . 'css/admin-my-games.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_my_games_admin_styles' );
