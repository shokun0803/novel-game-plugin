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
            <a href="<?php echo esc_url( noveltool_get_my_games_url( array( 'action' => 'select' ) ) ); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php esc_html_e( 'Back to My Games', 'novel-game-plugin' ); ?>
            </a>
        </div>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url( noveltool_get_game_manager_url( $game['id'], 'scenes' ) ); ?>" 
               class="nav-tab <?php echo $active_tab === 'scenes' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Scene List', 'novel-game-plugin' ); ?>
            </a>
            <a href="<?php echo esc_url( noveltool_get_game_manager_url( $game['id'], 'new-scene' ) ); ?>" 
               class="nav-tab <?php echo $active_tab === 'new-scene' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Create New Scene', 'novel-game-plugin' ); ?>
            </a>
            <a href="<?php echo esc_url( noveltool_get_game_manager_url( $game['id'], 'settings' ) ); ?>" 
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
    // エラー・成功メッセージの取得
    $error_message = '';
    $success_message = '';
    
    if ( isset( $_GET['error'] ) ) {
        switch ( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) {
            case 'security':
                $error_message = __( 'Security check failed.', 'novel-game-plugin' );
                break;
            case 'delete_failed':
                $error_message = __( 'Failed to delete.', 'novel-game-plugin' );
                break;
            case 'invalid_id':
                $error_message = __( 'Invalid ID.', 'novel-game-plugin' );
                break;
        }
    }
    
    if ( isset( $_GET['success'] ) ) {
        switch ( sanitize_text_field( wp_unslash( $_GET['success'] ) ) ) {
            case 'scene_deleted':
                $success_message = __( 'Scene has been deleted.', 'novel-game-plugin' );
                break;
        }
    }
    
    ?>
    <div class="noveltool-scenes-tab">
        <?php if ( $error_message ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html( $error_message ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( $success_message ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( $success_message ); ?></p>
            </div>
        <?php endif; ?>
        
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
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;" class="noveltool-delete-scene-form">
                                    <?php wp_nonce_field( 'manage_scenes' ); ?>
                                    <input type="hidden" name="action" value="noveltool_delete_scene" />
                                    <input type="hidden" name="scene_id" value="<?php echo esc_attr( $scene->ID ); ?>" />
                                    <input type="hidden" name="game_id" value="<?php echo esc_attr( $game['id'] ); ?>" />
                                    <button type="submit" class="button button-small noveltool-delete-button" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this scene? This action cannot be undone.', 'novel-game-plugin' ) ); ?>');">
                                        <?php esc_html_e( 'Delete', 'novel-game-plugin' ); ?>
                                    </button>
                                </form>
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
        
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'manage_games' ); ?>
            <input type="hidden" name="action" value="noveltool_update_game" />
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

            <h3><?php esc_html_e( 'Ad Settings', 'novel-game-plugin' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Ad Provider', 'novel-game-plugin' ); ?></label>
                    </th>
                    <td>
                        <?php
                        // 現在の広告設定を取得
                        $current_ad_provider = get_post_meta( $editing_game['id'], 'noveltool_ad_provider', true );
                        if ( empty( $current_ad_provider ) ) {
                            $current_ad_provider = 'none';
                        }
                        ?>
                        <fieldset>
                            <label>
                                <input type="radio" 
                                       name="ad_provider" 
                                       value="none" 
                                       <?php checked( $current_ad_provider, 'none' ); ?> />
                                <?php esc_html_e( 'No Ads', 'novel-game-plugin' ); ?>
                            </label><br />
                            <label>
                                <input type="radio" 
                                       name="ad_provider" 
                                       value="adsense" 
                                       <?php checked( $current_ad_provider, 'adsense' ); ?> />
                                <?php esc_html_e( 'Google AdSense', 'novel-game-plugin' ); ?>
                            </label><br />
                            <label>
                                <input type="radio" 
                                       name="ad_provider" 
                                       value="adsterra" 
                                       <?php checked( $current_ad_provider, 'adsterra' ); ?> />
                                <?php esc_html_e( 'Adsterra', 'novel-game-plugin' ); ?>
                            </label>
                        </fieldset>
                        <p class="description">
                            <?php 
                            printf(
                                /* translators: %s: Link to ad management page */
                                esc_html__( 'Note: To display ads, you must first configure the ad provider IDs in %s. If IDs are not set, ads will not be displayed.', 'novel-game-plugin' ),
                                '<a href="' . esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-ad-management' ) ) . '">' . esc_html__( 'Ad Management', 'novel-game-plugin' ) . '</a>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" 
                       name="update_game" 
                       class="button button-primary" 
                       value="<?php esc_attr_e( 'Update Game', 'novel-game-plugin' ); ?>" />
            </p>
        </form>

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
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
                                            <?php wp_nonce_field( 'manage_flags' ); ?>
                                            <input type="hidden" name="action" value="noveltool_delete_flag" />
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
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="noveltool-add-flag-form">
                    <?php wp_nonce_field( 'manage_flags' ); ?>
                    <input type="hidden" name="action" value="noveltool_add_flag" />
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

            <h3><?php esc_html_e( 'Export/Import Game Data', 'novel-game-plugin' ); ?></h3>
            <div class="noveltool-export-import-section">
                <h4><?php esc_html_e( 'Export Game Data', 'novel-game-plugin' ); ?></h4>
                <p><?php esc_html_e( 'Export all game data including scenes, settings, and flag definitions as a JSON file.', 'novel-game-plugin' ); ?></p>
                <p>
                    <button type="button" 
                            class="button button-primary noveltool-export-button"
                            data-game-id="<?php echo esc_attr( $editing_game['id'] ); ?>">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Export Data', 'novel-game-plugin' ); ?>
                    </button>
                </p>

                <h4><?php esc_html_e( 'Import Game Data', 'novel-game-plugin' ); ?></h4>
                <p><?php esc_html_e( 'Import game data from a JSON file. This will create a new game with all scenes and settings.', 'novel-game-plugin' ); ?></p>
                <div class="noveltool-import-form">
                    <p>
                        <label>
                            <input type="file" 
                                   id="noveltool-import-file" 
                                   accept=".json,application/json" 
                                   class="noveltool-import-file" />
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" 
                                   id="noveltool-download-images" 
                                   class="noveltool-download-images" />
                            <?php esc_html_e( 'Download images to media library (may take longer)', 'novel-game-plugin' ); ?>
                        </label>
                    </p>
                    <p>
                        <button type="button" 
                                class="button button-primary noveltool-import-button"
                                disabled>
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e( 'Import Data', 'novel-game-plugin' ); ?>
                        </button>
                    </p>
                    <div class="noveltool-import-progress" style="display: none;">
                        <p><?php esc_html_e( 'Importing...', 'novel-game-plugin' ); ?></p>
                        <div class="noveltool-progress-bar">
                            <div class="noveltool-progress-bar-inner"></div>
                        </div>
                    </div>
                </div>
                
                <h4><?php esc_html_e( 'Export/Import History', 'novel-game-plugin' ); ?></h4>
                <?php
                // エクスポート/インポート履歴を取得
                $transfer_logs = get_option( 'noveltool_game_transfer_logs', array() );
                
                if ( empty( $transfer_logs ) ) {
                    echo '<p>' . esc_html__( 'No export/import history yet.', 'novel-game-plugin' ) . '</p>';
                } else {
                    // 最新10件のみ表示
                    $recent_logs = array_slice( array_reverse( $transfer_logs ), 0, 10 );
                    ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Operation', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Game Title', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Scenes', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Flags', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Date/Time', 'novel-game-plugin' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_logs as $log ) : ?>
                                <tr>
                                    <td>
                                        <?php
                                        if ( $log['type'] === 'export' ) {
                                            echo '<span class="dashicons dashicons-download"></span> ';
                                            esc_html_e( 'Export', 'novel-game-plugin' );
                                        } else {
                                            echo '<span class="dashicons dashicons-upload"></span> ';
                                            esc_html_e( 'Import', 'novel-game-plugin' );
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html( $log['game_title'] ); ?></td>
                                    <td><?php echo esc_html( $log['scenes_count'] ); ?></td>
                                    <td><?php echo esc_html( $log['flags_count'] ); ?></td>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log['date'] ) ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                }
                ?>
            </div>
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

/**
 * ゲーム管理画面用のスクリプトを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.3.0
 */
function noveltool_game_manager_admin_scripts( $hook ) {
    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-my-games' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'noveltool-export-import',
        NOVEL_GAME_PLUGIN_URL . 'js/admin-export-import.js',
        array( 'jquery' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );

    // JavaScriptに渡すデータ
    wp_localize_script(
        'noveltool-export-import',
        'noveltoolExportImport',
        array(
            'exportNonce'    => wp_create_nonce( 'noveltool_export_game' ),
            'importNonce'    => wp_create_nonce( 'noveltool_import_game' ),
            'myGamesUrl'     => admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ),
            'exportButton'   => __( 'Export Data', 'novel-game-plugin' ),
            'exporting'      => __( 'Exporting...', 'novel-game-plugin' ),
            'exportSuccess'  => __( 'Game data exported successfully.', 'novel-game-plugin' ),
            'exportError'    => __( 'Failed to export game data.', 'novel-game-plugin' ),
            'importSuccess'  => __( 'Game data imported successfully.', 'novel-game-plugin' ),
            'importError'    => __( 'Failed to import game data.', 'novel-game-plugin' ),
            'noFileSelected' => __( 'Please select a file to import.', 'novel-game-plugin' ),
            'fileTooLarge'   => __( 'File size is too large. Maximum 10MB allowed.', 'novel-game-plugin' ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_game_manager_admin_scripts' );
