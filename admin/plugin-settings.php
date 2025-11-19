<?php
/**
 * プラグイン全体設定ページ
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * プラグイン設定ページの内容を表示
 *
 * @since 1.2.0
 */
function noveltool_plugin_settings_page() {
    // 権限チェック
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // 保存処理（POST メソッド＆nonce 検証）
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['noveltool_settings_nonce'] ) ) {
        check_admin_referer( 'noveltool_settings_save', 'noveltool_settings_nonce' );
        
        // アンインストール時のデータ削除オプション
        $delete_data_on_uninstall = isset( $_POST['noveltool_delete_data_on_uninstall'] ) ? true : false;
        update_option( 'noveltool_delete_data_on_uninstall', $delete_data_on_uninstall );
        
        // 保存成功メッセージ
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             esc_html__( 'Settings have been saved successfully.', 'novel-game-plugin' ) . 
             '</p></div>';
    }

    // 現在の設定値を取得
    $delete_data_on_uninstall = get_option( 'noveltool_delete_data_on_uninstall', false );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Plugin Settings', 'novel-game-plugin' ); ?></h1>
        
        <div class="noveltool-settings-container">
            <!-- ショートコード一覧セクション -->
            <div class="noveltool-shortcodes-section">
                <h2><?php esc_html_e( 'Available Shortcodes', 'novel-game-plugin' ); ?></h2>
                <p><?php esc_html_e( 'Use these shortcodes to display your games on pages or posts.', 'novel-game-plugin' ); ?></p>
                
                <div class="shortcode-list">
                    <div class="shortcode-item">
                        <h3><?php esc_html_e( 'Display All Games List', 'novel-game-plugin' ); ?></h3>
                        <div class="shortcode-code">
                            <code>[novel_game_list]</code>
                            <button type="button" class="button button-small copy-shortcode" data-shortcode="[novel_game_list]">
                                <?php esc_html_e( 'Copy', 'novel-game-plugin' ); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Displays all games in a beautiful card format. This is the most recommended shortcode for game lists.', 'novel-game-plugin' ); ?></p>
                        
                        <h4><?php esc_html_e( 'Available Options:', 'novel-game-plugin' ); ?></h4>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( 'show_count', 'novel-game-plugin' ); ?></th>
                                <td><?php esc_html_e( 'Show scene count (true/false, default: true)', 'novel-game-plugin' ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'show_description', 'novel-game-plugin' ); ?></th>
                                <td><?php esc_html_e( 'Show game description (true/false, default: false)', 'novel-game-plugin' ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'columns', 'novel-game-plugin' ); ?></th>
                                <td><?php esc_html_e( 'Number of columns (1-6, default: 3)', 'novel-game-plugin' ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'orderby', 'novel-game-plugin' ); ?></th>
                                <td><?php esc_html_e( 'Sort order (title, etc., default: title)', 'novel-game-plugin' ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'order', 'novel-game-plugin' ); ?></th>
                                <td><?php esc_html_e( 'Ascending/Descending (ASC/DESC, default: ASC)', 'novel-game-plugin' ); ?></td>
                            </tr>
                        </table>
                        
                        <h4><?php esc_html_e( 'Usage Examples:', 'novel-game-plugin' ); ?></h4>
                        <ul class="shortcode-examples">
                            <li>
                                <code>[novel_game_list columns="2"]</code>
                                <span class="description"><?php esc_html_e( '- Display in 2 columns', 'novel-game-plugin' ); ?></span>
                            </li>
                            <li>
                                <code>[novel_game_list show_description="true" columns="4"]</code>
                                <span class="description"><?php esc_html_e( '- Show descriptions in 4 columns', 'novel-game-plugin' ); ?></span>
                            </li>
                        </ul>
                    </div>

                    <div class="shortcode-item">
                        <h3><?php esc_html_e( 'Display Specific Game', 'novel-game-plugin' ); ?></h3>
                        <div class="shortcode-code">
                            <code>[novel_game_posts game_title="Game Name"]</code>
                            <button type="button" class="button button-small copy-shortcode" data-shortcode='[novel_game_posts game_title="Game Name"]'>
                                <?php esc_html_e( 'Copy', 'novel-game-plugin' ); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Displays a specific game with a hero image and play button. Replace "Game Name" with your actual game title.', 'novel-game-plugin' ); ?></p>
                        
                        <h4><?php esc_html_e( 'Available Options:', 'novel-game-plugin' ); ?></h4>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e( 'game_title', 'novel-game-plugin' ); ?></th>
                                <td><?php esc_html_e( 'Game title to display (required)', 'novel-game-plugin' ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'game_id', 'novel-game-plugin' ); ?></th>
                                <td><?php esc_html_e( 'Game ID to display (alternative to game_title)', 'novel-game-plugin' ); ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="shortcode-item">
                        <h3><?php esc_html_e( 'Block Editor', 'novel-game-plugin' ); ?></h3>
                        <p class="description">
                            <?php esc_html_e( 'In the Gutenberg block editor, search for "Novel Game List" block. You can easily insert game lists or individual games with intuitive operations.', 'novel-game-plugin' ); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- プラグイン情報セクション -->
            <div class="noveltool-info-section">
                <h2><?php esc_html_e( 'Plugin Information', 'novel-game-plugin' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Version', 'novel-game-plugin' ); ?></th>
                        <td><?php echo esc_html( NOVEL_GAME_PLUGIN_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Text Domain', 'novel-game-plugin' ); ?></th>
                        <td><code><?php echo esc_html( NOVEL_GAME_PLUGIN_TEXT_DOMAIN ); ?></code></td>
                    </tr>
                </table>
            </div>

            <!-- アンインストール設定セクション -->
            <div class="noveltool-uninstall-section">
                <h2><?php esc_html_e( 'Uninstall Settings', 'novel-game-plugin' ); ?></h2>
                
                <form method="post" action="">
                    <?php wp_nonce_field( 'noveltool_settings_save', 'noveltool_settings_nonce' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="noveltool_delete_data_on_uninstall">
                                    <?php esc_html_e( 'Delete all data on uninstall', 'novel-game-plugin' ); ?>
                                </label>
                            </th>
                            <td>
                                <fieldset>
                                    <label for="noveltool_delete_data_on_uninstall">
                                        <input type="checkbox" 
                                               name="noveltool_delete_data_on_uninstall" 
                                               id="noveltool_delete_data_on_uninstall" 
                                               value="1" 
                                               <?php checked( $delete_data_on_uninstall, true ); ?> />
                                        <?php esc_html_e( 'Delete all data on uninstall', 'novel-game-plugin' ); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e( 'When enabled, all game data, posts, and settings will be permanently deleted when you uninstall this plugin. This action cannot be undone. Normally, leave this unchecked.', 'novel-game-plugin' ); ?>
                                    </p>
                                    
                                    <?php if ( $delete_data_on_uninstall ) : ?>
                                        <div class="noveltool-warning-box">
                                            <span class="dashicons dashicons-warning"></span>
                                            <strong><?php esc_html_e( 'Warning: When this setting is enabled, all games you created will be deleted upon uninstallation.', 'novel-game-plugin' ); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( __( 'Save Settings', 'novel-game-plugin' ) ); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/**
 * プラグイン設定ページ用のスタイルとスクリプトを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.2.0
 */
function noveltool_plugin_settings_admin_scripts( $hook ) {
    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-plugin-settings' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'noveltool-plugin-settings-admin',
        NOVEL_GAME_PLUGIN_URL . 'css/admin-plugin-settings.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );

    // インラインスクリプトを追加
    wp_add_inline_script(
        'jquery',
        "jQuery(document).ready(function($) {
            $('.copy-shortcode').on('click', function() {
                var shortcode = $(this).data('shortcode');
                var button = $(this);
                
                navigator.clipboard.writeText(shortcode).then(function() {
                    var originalText = button.text();
                    button.addClass('copy-success');
                    button.text('" . esc_js( __( 'Copied!', 'novel-game-plugin' ) ) . "');
                    
                    setTimeout(function() {
                        button.removeClass('copy-success');
                        button.text(originalText);
                    }, 2000);
                });
            });
        });"
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_plugin_settings_admin_scripts' );
