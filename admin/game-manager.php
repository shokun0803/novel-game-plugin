<?php
/**
 * ゲーム個別管理画面
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ゲーム個別管理画面の内容を表示
 *
 * @param array $game ゲームデータ
 * @since 1.2.0
 */
function noveltool_game_manager_page( $game ) {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // タブの取得と検証
    $allowed_tabs = array( 'scenes', 'new-scene', 'settings' );
    $active_tab = 'scenes';
    if ( isset( $_GET['tab'] ) ) {
        $tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
        if ( in_array( $tab, $allowed_tabs, true ) ) {
            $active_tab = $tab;
        }
    }

    // シーン一覧の取得
    $scenes = noveltool_get_posts_by_game_title( $game['title'] );

    ?>
    <div class="wrap">
        <h1>
            <?php echo esc_html( $game['title'] ); ?>
            <span class="game-manager-subtitle"><?php esc_html_e( '- Game Management', 'novel-game-plugin' ); ?></span>
        </h1>
        
        <div class="noveltool-game-manager-header">
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games&action=select' ) ); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php esc_html_e( 'Back to My Games', 'novel-game-plugin' ); ?>
            </a>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( add_query_arg( array( 'game_id' => $game['id'], 'tab' => 'scenes' ), admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) ) ); ?>" 
               class="nav-tab <?php echo $active_tab === 'scenes' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Scene List', 'novel-game-plugin' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( array( 'game_id' => $game['id'], 'tab' => 'new-scene' ), admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) ) ); ?>" 
               class="nav-tab <?php echo $active_tab === 'new-scene' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Create New Scene', 'novel-game-plugin' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( array( 'game_id' => $game['id'], 'tab' => 'settings' ), admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) ) ); ?>" 
               class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Game Settings', 'novel-game-plugin' ); ?>
            </a>
        </h2>

        <div class="noveltool-game-manager-content">
            <?php
            switch ( $active_tab ) {
                case 'new-scene':
                    noveltool_render_new_scene_tab( $game );
                    break;
                case 'settings':
                    noveltool_render_game_settings_tab( $game );
                    break;
                case 'scenes':
                default:
                    noveltool_render_scenes_tab( $game, $scenes );
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * シーン一覧タブの表示
 *
 * @param array $game   ゲームデータ
 * @param array $scenes シーン一覧
 * @since 1.2.0
 */
function noveltool_render_scenes_tab( $game, $scenes ) {
    ?>
    <div class="noveltool-scenes-tab">
        <?php if ( empty( $scenes ) ) : ?>
            <div class="no-scenes-message">
                <p><?php esc_html_e( 'No scenes have been created for this game yet.', 'novel-game-plugin' ); ?></p>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=novel_game&game_title=' . urlencode( $game['title'] ) ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Create First Scene', 'novel-game-plugin' ); ?>
                    </a>
                </p>
            </div>
        <?php else : ?>
            <div class="scenes-header">
                <p><?php printf( esc_html__( 'Total: %d scenes', 'novel-game-plugin' ), count( $scenes ) ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=novel_game&game_title=' . urlencode( $game['title'] ) ) ); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e( 'Create New Scene', 'novel-game-plugin' ); ?>
                </a>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column"><?php esc_html_e( 'Scene Title', 'novel-game-plugin' ); ?></th>
                        <th class="manage-column"><?php esc_html_e( 'Status', 'novel-game-plugin' ); ?></th>
                        <th class="manage-column"><?php esc_html_e( 'Date', 'novel-game-plugin' ); ?></th>
                        <th class="manage-column"><?php esc_html_e( 'Actions', 'novel-game-plugin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $scenes as $scene ) : ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url( get_edit_post_link( $scene->ID ) ); ?>">
                                        <?php echo esc_html( $scene->post_title ); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <?php
                                $status_obj = get_post_status_object( $scene->post_status );
                                echo esc_html( $status_obj ? $status_obj->label : $scene->post_status );
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html( get_the_date( 'Y/m/d H:i', $scene->ID ) ); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $scene->ID ) ); ?>" class="button button-small">
                                    <?php esc_html_e( 'Edit', 'novel-game-plugin' ); ?>
                                </a>
                                <a href="<?php echo esc_url( get_permalink( $scene->ID ) ); ?>" class="button button-small" target="_blank">
                                    <?php esc_html_e( 'Preview', 'novel-game-plugin' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 新規シーン作成タブの表示
 *
 * @param array $game ゲームデータ
 * @since 1.2.0
 */
function noveltool_render_new_scene_tab( $game ) {
    ?>
    <div class="noveltool-new-scene-tab">
        <div class="new-scene-info">
            <p><?php esc_html_e( 'Create a new scene for this game. After creation, you will be taken to the scene editing screen where you can add backgrounds, characters, dialogue, and choices.', 'novel-game-plugin' ); ?></p>
        </div>
        
        <div class="new-scene-actions">
            <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=novel_game&game_title=' . urlencode( $game['title'] ) ) ); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php esc_html_e( 'Create New Scene', 'novel-game-plugin' ); ?>
            </a>
        </div>
    </div>
    <?php
}

/**
 * ゲーム設定タブの表示
 *
 * @param array $game ゲームデータ
 * @since 1.2.0
 */
function noveltool_render_game_settings_tab( $game ) {
    // ゲーム設定ページから設定機能を統合
    // game-settings.phpの編集フォーム部分を再利用
    $editing_game = $game;
    $edit_mode = true;
    
    // エラー・成功メッセージの取得
    $error_message = '';
    $success_message = '';
    
    if ( isset( $_GET['error'] ) ) {
        switch ( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) {
            case 'security':
                $error_message = __( 'Security check failed.', 'novel-game-plugin' );
                break;
            case 'empty_title':
                $error_message = __( 'Please enter a game title.', 'novel-game-plugin' );
                break;
            case 'duplicate_title':
                $error_message = __( 'A game with that title already exists.', 'novel-game-plugin' );
                break;
            case 'save_failed':
                $error_message = __( 'Failed to save game.', 'novel-game-plugin' );
                break;
        }
    }
    
    if ( isset( $_GET['success'] ) ) {
        switch ( sanitize_text_field( wp_unslash( $_GET['success'] ) ) ) {
            case 'updated':
                $success_message = __( 'Game updated successfully.', 'novel-game-plugin' );
                break;
            case 'flag_added':
                $success_message = __( 'Flag added successfully.', 'novel-game-plugin' );
                break;
            case 'flag_deleted':
                $success_message = __( 'Flag deleted successfully.', 'novel-game-plugin' );
                break;
        }
    }
    
    ?>
    <div class="noveltool-game-settings-tab">
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
        
        <form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) ); ?>">
            <?php wp_nonce_field( 'manage_games' ); ?>
            <input type="hidden" name="game_id" value="<?php echo esc_attr( $editing_game['id'] ); ?>" />
            <input type="hidden" name="old_title" value="<?php echo esc_attr( $editing_game['title'] ); ?>" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="game_title"><?php esc_html_e( 'Game Title', 'novel-game-plugin' ); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="game_title" 
                               name="game_title" 
                               value="<?php echo esc_attr( $editing_game['title'] ); ?>" 
                               class="regular-text"
                               required />
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
                                  class="large-text"><?php echo esc_textarea( $editing_game['description'] ); ?></textarea>
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
                               value="<?php echo esc_attr( $editing_game['title_image'] ); ?>" />
                        <img id="game_title_image_preview"
                             src="<?php echo esc_url( $editing_game['title_image'] ); ?>"
                             alt="<?php esc_attr_e( 'Title Screen Image Preview', 'novel-game-plugin' ); ?>"
                             style="max-width: 400px; height: auto; display: <?php echo $editing_game['title_image'] ? 'block' : 'none'; ?>;" />
                        <p>
                            <button type="button"
                                    class="button"
                                    id="game_title_image_button">
                                <?php esc_html_e( 'Select from Media', 'novel-game-plugin' ); ?>
                            </button>
                            <button type="button"
                                    class="button"
                                    id="game_title_image_remove"
                                    style="display: <?php echo $editing_game['title_image'] ? 'inline-block' : 'none'; ?>;">
                                <?php esc_html_e( 'Delete Image', 'novel-game-plugin' ); ?>
                            </button>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="game_over_text"><?php esc_html_e( 'Game Over Screen Text', 'novel-game-plugin' ); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="game_over_text" 
                               name="game_over_text" 
                               value="<?php echo esc_attr( isset( $editing_game['game_over_text'] ) ? $editing_game['game_over_text'] : 'Game Over' ); ?>" 
                               class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Text displayed when there are no choices or next scenes in a non-ending. Default is "Game Over".', 'novel-game-plugin' ); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Flag Management', 'novel-game-plugin' ); ?></h3>
            <?php
            $current_flags = noveltool_get_game_flag_master( $editing_game['title'] );
            ?>
            
            <div class="noveltool-flags-section">
                <h4><?php esc_html_e( 'Current Flags List', 'novel-game-plugin' ); ?></h4>
                
                <?php if ( ! empty( $current_flags ) ) : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'ID', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Flag Name', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'novel-game-plugin' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $current_flags as $flag ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $flag['id'] ); ?></td>
                                    <td><code><?php echo esc_html( $flag['name'] ); ?></code></td>
                                    <td><?php echo esc_html( $flag['description'] ); ?></td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) ); ?>" style="display: inline;">
                                            <?php wp_nonce_field( 'manage_flags' ); ?>
                                            <input type="hidden" name="game_title" value="<?php echo esc_attr( $editing_game['title'] ); ?>" />
                                            <input type="hidden" name="flag_name" value="<?php echo esc_attr( $flag['name'] ); ?>" />
                                            <input type="submit" 
                                                   name="delete_flag" 
                                                   class="button button-small" 
                                                   value="<?php esc_attr_e( 'Delete', 'novel-game-plugin' ); ?>"
                                                   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this flag?', 'novel-game-plugin' ); ?>');" />
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e( 'No flags have been set for this game yet.', 'novel-game-plugin' ); ?></p>
                <?php endif; ?>
                
                <h4><?php esc_html_e( 'Add New Flag', 'novel-game-plugin' ); ?></h4>
                <form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) ); ?>" class="noveltool-add-flag-form">
                    <?php wp_nonce_field( 'manage_flags' ); ?>
                    <input type="hidden" name="game_title" value="<?php echo esc_attr( $editing_game['title'] ); ?>" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="flag_name"><?php esc_html_e( 'Flag Name', 'novel-game-plugin' ); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="flag_name" 
                                       name="flag_name" 
                                       class="regular-text" 
                                       pattern="[a-zA-Z_][a-zA-Z0-9_]*" />
                                <p class="description"><?php esc_html_e( 'Only alphanumeric characters and underscores are allowed. Cannot start with a number.', 'novel-game-plugin' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="flag_description"><?php esc_html_e( 'Description', 'novel-game-plugin' ); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="flag_description" 
                                       name="flag_description" 
                                       class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" 
                               name="add_flag" 
                               class="button button-secondary" 
                               value="<?php esc_attr_e( 'Add Flag', 'novel-game-plugin' ); ?>" />
                    </p>
                </form>
            </div>

            <p class="submit">
                <input type="submit" 
                       name="update_game" 
                       class="button button-primary" 
                       value="<?php esc_attr_e( 'Update Game', 'novel-game-plugin' ); ?>" />
            </p>
        </form>
    </div>
    <?php
}

/**
 * ゲーム管理画面用のスタイルを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.2.0
 */
function noveltool_game_manager_admin_styles( $hook ) {
    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-my-games' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'noveltool-game-manager-admin',
        NOVEL_GAME_PLUGIN_URL . 'css/admin-game-manager.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_game_manager_admin_styles' );
