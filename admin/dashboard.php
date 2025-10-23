<?php
/**
 * ダッシュボードページ
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ダッシュボードページの内容を表示
 *
 * @since 1.2.0
 */
function noveltool_dashboard_page() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // ゲーム統計の取得
    $all_games = noveltool_get_all_games();
    $game_count = count( $all_games );
    
    $total_scenes = 0;
    foreach ( $all_games as $game ) {
        $posts = noveltool_get_posts_by_game_title( $game['title'] );
        $total_scenes += count( $posts );
    }
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Novel Game Plugin Dashboard', 'novel-game-plugin' ); ?></h1>
        
        <div class="noveltool-dashboard-container">
            <!-- 統計情報セクション -->
            <div class="noveltool-stats-section">
                <h2><?php esc_html_e( 'Statistics', 'novel-game-plugin' ); ?></h2>
                <div class="noveltool-stats-grid">
                    <div class="noveltool-stat-card">
                        <div class="stat-icon">🎮</div>
                        <div class="stat-number"><?php echo esc_html( $game_count ); ?></div>
                        <div class="stat-label"><?php esc_html_e( 'Total Games', 'novel-game-plugin' ); ?></div>
                    </div>
                    <div class="noveltool-stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-number"><?php echo esc_html( $total_scenes ); ?></div>
                        <div class="stat-label"><?php esc_html_e( 'Total Scenes', 'novel-game-plugin' ); ?></div>
                    </div>
                </div>
            </div>

            <!-- クイックアクションセクション -->
            <div class="noveltool-quick-actions-section">
                <h2><?php esc_html_e( 'Quick Actions', 'novel-game-plugin' ); ?></h2>
                <div class="noveltool-action-buttons">
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) ); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e( 'Create New Game', 'novel-game-plugin' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ) ); ?>" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-book"></span>
                        <?php esc_html_e( 'My Games', 'novel-game-plugin' ); ?>
                    </a>
                </div>
            </div>

            <!-- 使い方ガイドセクション -->
            <div class="noveltool-guide-section">
                <h2><?php esc_html_e( 'Getting Started Guide', 'novel-game-plugin' ); ?></h2>
                <div class="noveltool-guide-steps">
                    <div class="guide-step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3><?php esc_html_e( 'Create a New Game', 'novel-game-plugin' ); ?></h3>
                            <p><?php esc_html_e( 'Click "Create New Game" to set up your game title, description, and title image.', 'novel-game-plugin' ); ?></p>
                        </div>
                    </div>
                    <div class="guide-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3><?php esc_html_e( 'Create Scenes', 'novel-game-plugin' ); ?></h3>
                            <p><?php esc_html_e( 'Add scenes to your game with backgrounds, characters, dialogue, and choices.', 'novel-game-plugin' ); ?></p>
                        </div>
                    </div>
                    <div class="guide-step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3><?php esc_html_e( 'Publish Your Game', 'novel-game-plugin' ); ?></h3>
                            <p><?php esc_html_e( 'Use shortcodes to display your game on any page or post on your site.', 'novel-game-plugin' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- プラグイン概要セクション -->
            <div class="noveltool-about-section">
                <h2><?php esc_html_e( 'About Novel Game Plugin', 'novel-game-plugin' ); ?></h2>
                <p><?php esc_html_e( 'Novel Game Plugin allows you to create visual novel or sound novel style games directly in WordPress. You can create multiple games, manage scenes with branching storylines, and use flags for conditional content.', 'novel-game-plugin' ); ?></p>
                <p><strong><?php esc_html_e( 'Key Features:', 'novel-game-plugin' ); ?></strong></p>
                <ul>
                    <li><?php esc_html_e( 'Multiple game management', 'novel-game-plugin' ); ?></li>
                    <li><?php esc_html_e( 'Scene-based storytelling with backgrounds and characters', 'novel-game-plugin' ); ?></li>
                    <li><?php esc_html_e( 'Branching dialogue and choices', 'novel-game-plugin' ); ?></li>
                    <li><?php esc_html_e( 'Flag system for conditional content', 'novel-game-plugin' ); ?></li>
                    <li><?php esc_html_e( 'Shortcodes for easy integration', 'novel-game-plugin' ); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <style>
    .noveltool-dashboard-container {
        max-width: 1200px;
    }
    
    .noveltool-stats-section {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .noveltool-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 15px;
    }
    
    .noveltool-stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .stat-icon {
        font-size: 48px;
        margin-bottom: 10px;
    }
    
    .stat-number {
        font-size: 48px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 14px;
        opacity: 0.9;
    }
    
    .noveltool-quick-actions-section {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .noveltool-action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    
    .noveltool-action-buttons .button-hero {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        font-size: 16px;
    }
    
    .noveltool-guide-section {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .noveltool-guide-steps {
        margin-top: 20px;
    }
    
    .guide-step {
        display: flex;
        align-items: flex-start;
        margin-bottom: 20px;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 8px;
    }
    
    .step-number {
        flex-shrink: 0;
        width: 40px;
        height: 40px;
        background: #0073aa;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: bold;
        margin-right: 15px;
    }
    
    .step-content h3 {
        margin: 0 0 8px 0;
        color: #333;
    }
    
    .step-content p {
        margin: 0;
        color: #666;
    }
    
    .noveltool-about-section {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    .noveltool-about-section ul {
        margin-left: 20px;
    }
    
    .noveltool-about-section li {
        margin-bottom: 8px;
        color: #666;
    }
    </style>
    <?php
}
