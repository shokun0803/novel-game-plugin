<?php
/**
 * エクスポート/インポート専用画面
 *
 * @package NovelGamePlugin
 * @since 1.3.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * エクスポート/インポート専用画面の表示
 *
 * @since 1.3.0
 */
function noveltool_export_import_page() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'novel-game-plugin' ) );
    }

    // すべてのゲームを取得
    $games = noveltool_get_all_games();
    ?>
    <div class="wrap noveltool-export-import-page">
        <h1><?php esc_html_e( 'Export/Import', 'novel-game-plugin' ); ?></h1>

        <div class="noveltool-export-import-container">
            <!-- エクスポートセクション -->
            <div class="noveltool-section noveltool-export-section">
                <h2><?php esc_html_e( 'Export Game Data', 'novel-game-plugin' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Select a game and export it in JSON format.', 'novel-game-plugin' ); ?></p>

                <?php if ( empty( $games ) ) : ?>
                    <div class="noveltool-no-games-message">
                        <p><?php esc_html_e( 'No games available to export.', 'novel-game-plugin' ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="noveltool-export-form">
                        <label for="noveltool-export-game-select"><?php esc_html_e( 'Select game to export', 'novel-game-plugin' ); ?></label>
                        <select id="noveltool-export-game-select" class="noveltool-export-game-select">
                            <option value=""><?php esc_html_e( '-- Select a game --', 'novel-game-plugin' ); ?></option>
                            <?php foreach ( $games as $game ) : ?>
                                <option value="<?php echo esc_attr( $game['id'] ); ?>"><?php echo esc_html( $game['title'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p>
                            <button type="button" 
                                    class="button button-primary noveltool-export-button"
                                    disabled
                                    aria-label="<?php esc_attr_e( 'Export game data as JSON file', 'novel-game-plugin' ); ?>">
                                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                                <?php esc_html_e( 'Export', 'novel-game-plugin' ); ?>
                            </button>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- インポートセクション -->
            <div class="noveltool-section noveltool-import-section">
                <h2><?php esc_html_e( 'Import Game Data', 'novel-game-plugin' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Import a JSON format game data to create a new game.', 'novel-game-plugin' ); ?></p>

                <div class="noveltool-import-form">
                    <p>
                        <label for="noveltool-import-file"><?php esc_html_e( 'Select JSON file', 'novel-game-plugin' ); ?></label>
                        <input type="file" 
                               id="noveltool-import-file" 
                               accept=".json,application/json" 
                               class="noveltool-import-file"
                               aria-label="<?php esc_attr_e( 'Select JSON file to import', 'novel-game-plugin' ); ?>" />
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
                                disabled
                                aria-label="<?php esc_attr_e( 'Import game data from selected file', 'novel-game-plugin' ); ?>">
                            <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                            <?php esc_html_e( 'Import', 'novel-game-plugin' ); ?>
                        </button>
                    </p>
                    <div class="noveltool-import-progress" style="display: none;">
                        <p><?php esc_html_e( 'Importing...', 'novel-game-plugin' ); ?></p>
                        <div class="noveltool-progress-bar">
                            <div class="noveltool-progress-bar-inner"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 履歴セクション -->
            <div class="noveltool-section noveltool-history-section">
                <h2><?php esc_html_e( 'Export/Import History', 'novel-game-plugin' ); ?></h2>
                <?php
                // エクスポート/インポート履歴を取得
                $transfer_logs = get_option( 'noveltool_game_transfer_logs', array() );
                
                if ( empty( $transfer_logs ) ) {
                    echo '<p>' . esc_html__( 'No export/import history yet.', 'novel-game-plugin' ) . '</p>';
                } else {
                    // 最新10件のみ表示
                    $recent_logs = array_slice( array_reverse( $transfer_logs ), 0, 10 );
                    ?>
                    <table class="wp-list-table widefat fixed striped" aria-label="<?php esc_attr_e( 'Export and Import History', 'novel-game-plugin' ); ?>">
                        <thead>
                            <tr>
                                <th aria-label="<?php esc_attr_e( 'Operation Type', 'novel-game-plugin' ); ?>"><?php esc_html_e( 'Operation', 'novel-game-plugin' ); ?></th>
                                <th aria-label="<?php esc_attr_e( 'Game Title', 'novel-game-plugin' ); ?>"><?php esc_html_e( 'Game Title', 'novel-game-plugin' ); ?></th>
                                <th aria-label="<?php esc_attr_e( 'Number of Scenes', 'novel-game-plugin' ); ?>"><?php esc_html_e( 'Scenes', 'novel-game-plugin' ); ?></th>
                                <th aria-label="<?php esc_attr_e( 'Number of Flags', 'novel-game-plugin' ); ?>"><?php esc_html_e( 'Flags', 'novel-game-plugin' ); ?></th>
                                <th aria-label="<?php esc_attr_e( 'Operation Date and Time', 'novel-game-plugin' ); ?>"><?php esc_html_e( 'Date/Time', 'novel-game-plugin' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_logs as $log ) : 
                                // 防御的表示: 古いスキーマや不整合に対応
                                $scenes_count = isset( $log['scenes'] ) ? intval( $log['scenes'] ) : ( isset( $log['scenes_count'] ) ? intval( $log['scenes_count'] ) : 0 );
                                $flags_count = isset( $log['flags'] ) ? intval( $log['flags'] ) : ( isset( $log['flags_count'] ) ? intval( $log['flags_count'] ) : 0 );
                            ?>
                                <tr>
                                    <td>
                                        <?php
                                        if ( $log['type'] === 'export' ) {
                                            echo '<span class="dashicons dashicons-download" aria-hidden="true"></span>';
                                            echo '<span class="screen-reader-text">' . esc_html__( 'Export Operation', 'novel-game-plugin' ) . '</span> ';
                                            esc_html_e( 'Export', 'novel-game-plugin' );
                                        } else {
                                            echo '<span class="dashicons dashicons-upload" aria-hidden="true"></span>';
                                            echo '<span class="screen-reader-text">' . esc_html__( 'Import Operation', 'novel-game-plugin' ) . '</span> ';
                                            esc_html_e( 'Import', 'novel-game-plugin' );
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html( $log['game_title'] ); ?></td>
                                    <td><?php echo esc_html( $scenes_count ); ?></td>
                                    <td><?php echo esc_html( $flags_count ); ?></td>
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
    </div>
    <?php
}

/**
 * エクスポート/インポート専用画面用のスタイルを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.3.0
 */
function noveltool_export_import_admin_styles( $hook ) {
    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-export-import' !== $hook ) {
        return;
    }

    wp_enqueue_style(
        'noveltool-export-import-admin',
        NOVEL_GAME_PLUGIN_URL . 'css/admin-export-import.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_export_import_admin_styles' );

/**
 * エクスポート/インポート専用画面用のスクリプトを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.3.0
 */
function noveltool_export_import_admin_scripts( $hook ) {
    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-export-import' !== $hook ) {
        return;
    }

    // デバッグフラグの値を決定
    $debug_enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? true : false;

    // 共通デバッグログユーティリティを読み込み
    wp_enqueue_script(
        'novel-game-debug-log',
        NOVEL_GAME_PLUGIN_URL . 'js/debug-log.js',
        array(),
        NOVEL_GAME_PLUGIN_VERSION,
        false
    );

    // 管理画面用デバッグフラグをグローバル変数として設定
    wp_add_inline_script(
        'novel-game-debug-log',
        'window.novelGameAdminDebug = ' . ( $debug_enabled ? 'true' : 'false' ) . ';',
        'before'
    );

    wp_enqueue_script(
        'noveltool-export-import',
        NOVEL_GAME_PLUGIN_URL . 'js/admin-export-import.js',
        array( 'jquery', 'novel-game-debug-log' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );

    // JavaScriptに渡すデータ
    wp_localize_script(
        'noveltool-export-import',
        'noveltoolExportImport',
        array(
            'debug'                   => $debug_enabled,
            'exportNonce'             => wp_create_nonce( 'noveltool_export_game' ),
            'importNonce'             => wp_create_nonce( 'noveltool_import_game' ),
            'myGamesUrl'              => admin_url( 'edit.php?post_type=novel_game&page=novel-game-my-games' ),
            'exportImportUrl'         => admin_url( 'edit.php?post_type=novel_game&page=novel-game-export-import' ),
            'exportButton'            => __( 'Export', 'novel-game-plugin' ),
            'exporting'               => __( 'Exporting...', 'novel-game-plugin' ),
            'exportSuccess'           => __( 'Game data exported successfully.', 'novel-game-plugin' ),
            'exportError'             => __( 'Failed to export game data.', 'novel-game-plugin' ),
            'importSuccess'           => __( 'Game data imported successfully.', 'novel-game-plugin' ),
            'importError'             => __( 'Failed to import game data.', 'novel-game-plugin' ),
            'noFileSelected'          => __( 'Please select a file to import.', 'novel-game-plugin' ),
            'noGameSelected'          => __( 'Please select a game to export.', 'novel-game-plugin' ),
            'fileTooLarge'            => __( 'File size is too large. Maximum 10MB allowed.', 'novel-game-plugin' ),
            'imageDownloadFailures'   => __( 'Note: %d image(s) failed to download.', 'novel-game-plugin' ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_export_import_admin_scripts' );
