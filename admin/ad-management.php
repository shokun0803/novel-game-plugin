<?php
/**
 * 広告管理ページ
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 広告管理メニューを追加
 *
 * @since 1.2.0
 */
function noveltool_add_ad_management_menu() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __( 'Ad Management', 'novel-game-plugin' ),
        '📢 ' . __( 'Ad Management', 'novel-game-plugin' ),
        'manage_options',
        'novel-game-ad-management',
        'noveltool_ad_management_page',
        4
    );
}
add_action( 'admin_menu', 'noveltool_add_ad_management_menu' );

/**
 * 広告管理ページの内容を表示
 *
 * @since 1.2.0
 */
function noveltool_ad_management_page() {
    // 権限チェック
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // 保存処理（POST メソッド＆nonce 検証）
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        check_admin_referer( 'noveltool_ad_management_save', 'noveltool_ad_management_nonce' );
        
        // Google AdSense ID
        $google_adsense_id = isset( $_POST['noveltool_google_adsense_id'] ) 
            ? sanitize_text_field( wp_unslash( $_POST['noveltool_google_adsense_id'] ) ) 
            : '';
        update_option( 'noveltool_google_adsense_id', $google_adsense_id );
        
        // Adsterra ID
        $adsterra_id = isset( $_POST['noveltool_adsterra_id'] ) 
            ? sanitize_text_field( wp_unslash( $_POST['noveltool_adsterra_id'] ) ) 
            : '';
        update_option( 'noveltool_adsterra_id', $adsterra_id );
        
        // 保存成功メッセージ
        echo '<div class="notice notice-success is-dismissible"><p>' . 
             esc_html__( 'Ad settings have been saved successfully.', 'novel-game-plugin' ) . 
             '</p></div>';
    }

    // 現在の設定値を取得
    $google_adsense_id = get_option( 'noveltool_google_adsense_id', '' );
    $adsterra_id = get_option( 'noveltool_adsterra_id', '' );

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Ad Management', 'novel-game-plugin' ); ?></h1>
        
        <div class="noveltool-ad-management-container">
            <!-- 注意事項セクション -->
            <div class="noveltool-warning-section">
                <h2>⚠️ <?php esc_html_e( 'Important Notice', 'novel-game-plugin' ); ?></h2>
                <div class="noveltool-warning-content">
                    <p><strong><?php esc_html_e( 'Please read carefully before using the advertising feature:', 'novel-game-plugin' ); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e( 'The advertising IDs and settings you enter are your own responsibility.', 'novel-game-plugin' ); ?></li>
                        <li><?php esc_html_e( 'This plugin does not verify the validity or compliance of the advertising IDs you provide.', 'novel-game-plugin' ); ?></li>
                        <li><?php esc_html_e( 'You must comply with the terms of service of each advertising provider.', 'novel-game-plugin' ); ?></li>
                        <li><?php esc_html_e( 'The plugin developer is not responsible for any issues arising from the use of advertising features.', 'novel-game-plugin' ); ?></li>
                        <li><?php esc_html_e( 'Make sure you have properly registered with the advertising provider and have an approved account before entering your IDs.', 'novel-game-plugin' ); ?></li>
                    </ul>
                </div>
            </div>

            <!-- 広告設定フォーム -->
            <form method="post" action="">
                <?php wp_nonce_field( 'noveltool_ad_management_save', 'noveltool_ad_management_nonce' ); ?>
                
                <!-- Google AdSenseセクション -->
                <div class="noveltool-ad-provider-section">
                    <h2>
                        <span class="provider-icon">🔴</span>
                        <?php esc_html_e( 'Google AdSense', 'novel-game-plugin' ); ?>
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="noveltool_google_adsense_id">
                                    <?php esc_html_e( 'AdSense Publisher ID', 'novel-game-plugin' ); ?>
                                </label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="noveltool_google_adsense_id" 
                                    name="noveltool_google_adsense_id" 
                                    value="<?php echo esc_attr( $google_adsense_id ); ?>" 
                                    class="regular-text" 
                                    placeholder="ca-pub-XXXXXXXXXXXXXXXX"
                                />
                                <p class="description">
                                    <?php esc_html_e( 'Enter your Google AdSense Publisher ID (e.g., ca-pub-1234567890123456)', 'novel-game-plugin' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Official Website', 'novel-game-plugin' ); ?>
                            </th>
                            <td>
                                <a href="<?php echo esc_url( 'https://www.google.com/adsense/' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php esc_html_e( 'Visit Google AdSense', 'novel-game-plugin' ); ?>
                                </a>
                                <p class="description">
                                    <?php esc_html_e( 'Register for a Google AdSense account and get your Publisher ID from the official website.', 'novel-game-plugin' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Adsterraセクション -->
                <div class="noveltool-ad-provider-section">
                    <h2>
                        <span class="provider-icon">🟢</span>
                        <?php esc_html_e( 'Adsterra', 'novel-game-plugin' ); ?>
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="noveltool_adsterra_id">
                                    <?php esc_html_e( 'Adsterra Publisher ID', 'novel-game-plugin' ); ?>
                                </label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="noveltool_adsterra_id" 
                                    name="noveltool_adsterra_id" 
                                    value="<?php echo esc_attr( $adsterra_id ); ?>" 
                                    class="regular-text" 
                                    placeholder="123456"
                                />
                                <p class="description">
                                    <?php esc_html_e( 'Enter your Adsterra Publisher ID', 'novel-game-plugin' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Official Website', 'novel-game-plugin' ); ?>
                            </th>
                            <td>
                                <a href="<?php echo esc_url( 'https://adsterra.com/' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php esc_html_e( 'Visit Adsterra', 'novel-game-plugin' ); ?>
                                </a>
                                <p class="description">
                                    <?php esc_html_e( 'Register for an Adsterra account and get your Publisher ID from the official website.', 'novel-game-plugin' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- 保存ボタン -->
                <div class="noveltool-ad-submit-section">
                    <?php submit_button( __( 'Save Ad Settings', 'novel-game-plugin' ), 'primary large', 'submit', false ); ?>
                </div>
            </form>

            <!-- 追加情報セクション -->
            <div class="noveltool-ad-info-section">
                <h2><?php esc_html_e( 'Additional Information', 'novel-game-plugin' ); ?></h2>
                <div class="noveltool-info-content">
                    <p><?php esc_html_e( 'This section allows you to manage advertising provider IDs for displaying ads in your novel games.', 'novel-game-plugin' ); ?></p>
                    <p><?php esc_html_e( 'Currently supported advertising providers:', 'novel-game-plugin' ); ?></p>
                    <ul>
                        <li><strong>Google AdSense:</strong> <?php esc_html_e( 'One of the most popular advertising networks with a wide range of advertisers.', 'novel-game-plugin' ); ?></li>
                        <li><strong>Adsterra:</strong> <?php esc_html_e( 'An alternative advertising network with various ad formats and payment options.', 'novel-game-plugin' ); ?></li>
                    </ul>
                    <p class="noveltool-note">
                        <strong><?php esc_html_e( 'Note:', 'novel-game-plugin' ); ?></strong>
                        <?php esc_html_e( 'Ad display functionality is currently under development. The IDs saved here will be used when the feature is fully implemented.', 'novel-game-plugin' ); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 広告管理ページ用のスタイルを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.2.0
 */
function noveltool_ad_management_admin_styles( $hook ) {
    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-ad-management' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'noveltool-ad-management-admin',
        NOVEL_GAME_PLUGIN_URL . 'css/admin-ad-management.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_ad_management_admin_styles' );

/**
 * Google AdSense IDを取得
 *
 * @return string Google AdSense ID
 * @since 1.2.0
 */
function noveltool_get_google_adsense_id() {
    return get_option( 'noveltool_google_adsense_id', '' );
}

/**
 * Adsterra IDを取得
 *
 * @return string Adsterra ID
 * @since 1.2.0
 */
function noveltool_get_adsterra_id() {
    return get_option( 'noveltool_adsterra_id', '' );
}
