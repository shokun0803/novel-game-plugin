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

    // メッセージの取得
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
            case 'deleted':
                $success_message = __( 'Game and all associated scenes have been permanently deleted.', 'novel-game-plugin' );
                break;
        }
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'My Games', 'novel-game-plugin' ); ?></h1>

        <?php
        // ダウンロード進捗バナー（進行中のダウンロードがある場合に表示）
        $user_id = get_current_user_id();
        $job_id = get_user_meta( $user_id, 'noveltool_download_job_id', true );
        $download_status = get_option( 'noveltool_sample_images_download_status', 'not_started' );
        
        if ( current_user_can( 'manage_options' ) && $job_id && $download_status === 'in_progress' ) :
        ?>
            <div id="noveltool-download-progress-banner" class="notice notice-info" style="position: relative; padding: 15px; margin-top: 10px;">
                <p style="margin: 0 0 10px 0;">
                    <strong><?php esc_html_e( 'Sample Images Download in Progress', 'novel-game-plugin' ); ?></strong>
                </p>
                <div class="noveltool-banner-progress">
                    <div class="noveltool-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="noveltool-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="noveltool-progress-status" aria-live="polite" aria-atomic="true">
                        <?php esc_html_e( 'Checking status...', 'novel-game-plugin' ); ?>
                    </div>
                </div>
                <button type="button" id="noveltool-show-download-details" class="button button-small" style="margin-top: 10px;">
                    <?php esc_html_e( 'View Details', 'novel-game-plugin' ); ?>
                </button>
            </div>
        <?php endif; ?>

        <noscript>
            <?php if ( current_user_can( 'manage_options' ) && ! noveltool_sample_images_exists() ) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e( 'JavaScript is disabled. Sample image download progress cannot be displayed in real-time.', 'novel-game-plugin' ); ?></p>
                    <p>
                        <button type="button" class="button button-primary" disabled>
                            <?php esc_html_e( 'Download Sample Images (requires JavaScript)', 'novel-game-plugin' ); ?>
                        </button>
                    </p>
                    <p><small><?php esc_html_e( 'Please enable JavaScript to download sample images.', 'novel-game-plugin' ); ?></small></p>
                </div>
            <?php endif; ?>
        </noscript>

        <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:12px;">
                <?php wp_nonce_field( 'noveltool_download_diagnostic' ); ?>
                <input type="hidden" name="action" value="noveltool_download_diagnostic" />
                <button type="submit" class="button button-secondary">
                    <?php esc_html_e( 'Download Diagnostic Package', 'novel-game-plugin' ); ?>
                </button>
            </form>
        <?php endif; ?>
        
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
        
        <?php
        // Shadow Detectiveサンプルゲームが存在するかチェック
        $shadow_detective_exists = noveltool_get_game_by_machine_name( 'shadow_detective_v1' ) !== null;
        $download_status = get_option( 'noveltool_sample_images_download_status', 'not_started' );

        // サンプル画像がなく、ユーザーが「後で」を選択している場合は通知バナーを表示
        $is_dismissed = get_user_meta( get_current_user_id(), 'noveltool_sample_images_prompt_dismissed', true );
        $should_show_missing_images_decision = current_user_can( 'manage_options' )
            && $shadow_detective_exists
            && ! noveltool_sample_images_exists()
            && 'in_progress' !== $download_status;
        $should_show_banner = current_user_can( 'manage_options' )
            && $shadow_detective_exists
            && ! noveltool_sample_images_exists()
            && $is_dismissed
            && 'in_progress' !== $download_status;
        
        if ( $should_show_banner ) :
        ?>
            <div class="notice notice-warning">
                <p>
                    <?php esc_html_e( 'Sample images are not installed. You can download them to use with sample games.', 'novel-game-plugin' ); ?>
                </p>
                <p>
                    <button id="noveltool-download-sample-images-banner" class="button button-secondary">
                        <?php esc_html_e( 'Download Sample Images', 'novel-game-plugin' ); ?>
                    </button>
                </p>
            </div>
        <?php endif; ?>

        <?php if ( $should_show_missing_images_decision ) : ?>
            <div class="notice notice-warning">
                <p>
                    <?php esc_html_e( 'Sample game is installed, but sample images are missing in uploads. Download sample images now?', 'novel-game-plugin' ); ?>
                </p>
                <p>
                    <button id="noveltool-download-sample-images-banner" class="button button-primary">
                        <?php esc_html_e( 'Download Sample Images', 'novel-game-plugin' ); ?>
                    </button>
                    <button id="noveltool-skip-sample-images-download" class="button button-secondary" style="margin-left: 8px;">
                        <?php esc_html_e( 'Not now', 'novel-game-plugin' ); ?>
                    </button>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if ( ! $shadow_detective_exists ) : ?>
            <div class="notice notice-info">
                <p><?php esc_html_e( 'Sample game is not installed. You can install a sample game to see how the plugin works.', 'novel-game-plugin' ); ?></p>
                <p>
                    <button id="noveltool-install-sample-game" class="button button-primary">
                        <?php esc_html_e( 'Install Sample Game', 'novel-game-plugin' ); ?>
                    </button>
                </p>
            </div>
        <?php endif; ?>
        
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
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;" class="noveltool-delete-game-form">
                                    <?php wp_nonce_field( 'manage_games' ); ?>
                                    <input type="hidden" name="action" value="noveltool_delete_game" />
                                    <input type="hidden" name="game_id" value="<?php echo esc_attr( $game['id'] ); ?>" />
                                    <button type="submit" class="button noveltool-delete-button" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this game? This action cannot be undone.', 'novel-game-plugin' ) ); ?>');">
                                        <?php esc_html_e( 'Delete', 'novel-game-plugin' ); ?>
                                    </button>
                                </form>
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

/**
 * マイゲームページ用のスクリプトを読み込み
 *
 * @param string $hook 現在のページフック
 * @since 1.2.0
 */
function noveltool_my_games_admin_scripts( $hook ) {
    // 対象ページでのみ実行
    if ( 'novel_game_page_novel-game-my-games' !== $hook ) {
        return;
    }

    // サンプル画像プロンプトのスクリプトとスタイルを読み込み
    // 管理者権限を持つユーザーのみに表示（REST API と同じ権限）
    // モーダルはプラグイン有効化直後または手動インストール直後の一度だけ表示する
    $user_id = get_current_user_id();
    $pending = get_option( 'noveltool_sample_images_prompt_pending', false );
    $user_show = get_user_meta( $user_id, 'noveltool_sample_images_prompt_show', true );
    $is_dismissed = get_user_meta( $user_id, 'noveltool_sample_images_prompt_dismissed', true );
    $download_status = get_option( 'noveltool_sample_images_download_status', 'not_started' );
    $shadow_detective_exists = noveltool_get_game_by_machine_name( 'shadow_detective_v1' ) !== null;
    
    $should_prompt = current_user_can( 'manage_options' )
        && $shadow_detective_exists
        && ! noveltool_sample_images_exists()
        && ! $is_dismissed
        && ( $pending || $user_show );
    
    $has_active_download = ( $user_id && get_user_meta( $user_id, 'noveltool_download_job_id', true ) && 'in_progress' === $download_status );
    $should_show_missing_images_decision = current_user_can( 'manage_options' )
        && $shadow_detective_exists
        && ! noveltool_sample_images_exists()
        && 'in_progress' !== $download_status;
    $should_show_banner = current_user_can( 'manage_options' )
        && $shadow_detective_exists
        && ! noveltool_sample_images_exists()
        && $is_dismissed
        && 'in_progress' !== $download_status;
    
    // モーダルを表示する場合はフラグをクリアして一度だけ表示するようにする
    if ( $should_prompt ) {
        delete_option( 'noveltool_sample_images_prompt_pending' );
        delete_user_meta( $user_id, 'noveltool_sample_images_prompt_show' );
    }
    
    // モーダルまたはバナーのいずれかを表示する場合はスクリプトを読み込む
    if ( $should_prompt || $should_show_banner || $should_show_missing_images_decision || $has_active_download ) {
        wp_enqueue_style(
            'noveltool-sample-images-prompt',
            NOVEL_GAME_PLUGIN_URL . 'css/admin-sample-images-prompt.css',
            array(),
            NOVEL_GAME_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'noveltool-sample-images-prompt',
            NOVEL_GAME_PLUGIN_URL . 'js/admin-sample-images-prompt.js',
            array( 'jquery' ),
            NOVEL_GAME_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script(
            'noveltool-sample-images-prompt',
            'novelToolSampleImages',
            array(
                'shouldPrompt'  => $should_prompt,
                'showBanner'    => $should_show_banner,
                'nonce'         => wp_create_nonce( 'noveltool_sample_images_prompt' ),
                'restNonce'     => wp_create_nonce( 'wp_rest' ),
                'apiDownload'   => rest_url( 'novel-game-plugin/v1/sample-images/download' ),
                'apiStatus'     => rest_url( 'novel-game-plugin/v1/sample-images/status' ),
                'apiResetStatus' => rest_url( 'novel-game-plugin/v1/sample-images/reset-status' ),
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'hasActiveDownload' => $has_active_download,
                // ポーリング・タイムアウト設定（サーバー側で調整可能）
                'fallbackTimeoutMs' => 5000,  // 5秒
                'pollIntervalMs'    => 3000,  // 3秒
                'maxPollTimeMs'     => 300000, // 5分
                'xhrTimeoutMs'      => 120000, // 120秒
                'strings'       => array(
                    'modalTitle'           => __( 'Download Sample Images', 'novel-game-plugin' ),
                    'modalMessage'         => sprintf(
                        /* translators: %s: estimated file size */
                        __( 'Sample game images are not installed. Would you like to download them now? Download size: approximately %s. The download will be processed in the background.', 'novel-game-plugin' ),
                        '15 MB'
                    ),
                    'downloadButton'       => __( 'Download', 'novel-game-plugin' ),
                    'laterButton'          => __( 'Later', 'novel-game-plugin' ),
                    'cancelButton'         => __( 'Cancel', 'novel-game-plugin' ),
                    'downloading'          => __( 'ダウンロード中...', 'novel-game-plugin' ),
                    'pleaseWait'           => __( 'サンプル画像をダウンロードしています。バックグラウンドで処理されるため、他の操作を続けられます。', 'novel-game-plugin' ),
                    'backgroundNote'       => __( '(Processing in background)', 'novel-game-plugin' ),
                    'success'              => __( 'Success', 'novel-game-plugin' ),
                    'error'                => __( 'Error', 'novel-game-plugin' ),
                    'downloadSuccess'      => __( 'Sample images downloaded and installed successfully.', 'novel-game-plugin' ),
                    'downloadFailed'       => __( 'Failed to download sample images. Please try again later.', 'novel-game-plugin' ),
                    'downloadTimeout'      => __( 'Download timeout. Please try again.', 'novel-game-plugin' ),
                    'retryButton'          => __( 'Retry', 'novel-game-plugin' ),
                    'closeButton'          => __( 'Close', 'novel-game-plugin' ),
                    'resetting'            => __( 'Resetting...', 'novel-game-plugin' ),
                    'resetFailed'          => __( 'Failed to reset download status. Please try again later or contact the administrator.', 'novel-game-plugin' ),
                    'troubleshooting'      => __( 'Troubleshooting:', 'novel-game-plugin' ),
                    'troubleshootingSteps' => array(
                        __( 'Check your internet connection', 'novel-game-plugin' ),
                        __( 'Verify that the assets directory has write permissions', 'novel-game-plugin' ),
                        __( 'Check server error logs for detailed information', 'novel-game-plugin' ),
                        __( 'If the problem persists, try manual installation (see documentation)', 'novel-game-plugin' ),
                    ),
                    'statusConnecting'     => __( 'Connecting to server...', 'novel-game-plugin' ),
                    'statusDownloading'    => __( 'Downloading sample images...', 'novel-game-plugin' ),
                    'statusDownloadingBytes' => __( 'Downloading: ', 'novel-game-plugin' ),
                    'statusVerifying'      => __( 'Verifying downloaded files...', 'novel-game-plugin' ),
                    'statusExtracting'     => __( 'Extracting files...', 'novel-game-plugin' ),
                    'statusCompleted'      => __( 'Completed', 'novel-game-plugin' ),
                    'showErrorDetails'     => __( 'Show detailed error', 'novel-game-plugin' ),
                    'hideErrorDetails'     => __( 'Hide details', 'novel-game-plugin' ),
                    'errorTimestamp'       => __( 'Error occurred at: ', 'novel-game-plugin' ),
                    'errorDetailFetchFailed' => __( 'Failed to retrieve error details. Please check the server logs.', 'novel-game-plugin' ),
                    'errorDetailNotAvailable' => __( 'Error details are not available. Please check the server logs.', 'novel-game-plugin' ),
                    'diagnosticCode'       => __( 'Diagnostic code', 'novel-game-plugin' ),
                    'errorBadRequest'      => __( 'Invalid request.', 'novel-game-plugin' ),
                    'errorForbidden'       => __( 'Permission denied. Please contact the administrator.', 'novel-game-plugin' ),
                    'errorNotFound'        => __( 'Resource not found.', 'novel-game-plugin' ),
                    'errorServerError'     => __( 'A server error occurred.', 'novel-game-plugin' ),
                    'errorServiceUnavailable' => __( 'Service temporarily unavailable.', 'novel-game-plugin' ),
                    'errorUnknown'         => __( 'An error occurred', 'novel-game-plugin' ),
                    'errorStage'           => __( 'Error stage', 'novel-game-plugin' ),
                    'errorCode'            => __( 'Error code', 'novel-game-plugin' ),
                    'stageFetchRelease'    => __( 'Fetching release information', 'novel-game-plugin' ),
                    'stageDownload'        => __( 'Downloading', 'novel-game-plugin' ),
                    'stageVerifyChecksum'  => __( 'Verifying checksum', 'novel-game-plugin' ),
                    'stageExtract'         => __( 'Extracting', 'novel-game-plugin' ),
                    'stageFilesystem'      => __( 'Filesystem operation', 'novel-game-plugin' ),
                    'stageOther'           => __( 'Other', 'novel-game-plugin' ),
                    'stageEnvironmentCheck' => __( 'Environment check', 'novel-game-plugin' ),
                    'stageBackground'      => __( 'Background processing', 'novel-game-plugin' ),
                    'errorMemoryLimit'     => __( 'Server memory limit is too low. Please increase memory_limit to 256M or higher in php.ini.', 'novel-game-plugin' ),
                    'errorNoExtension'     => __( 'Server does not support ZIP extraction. Please install PHP ZipArchive extension or unzip command.', 'novel-game-plugin' ),
                    'bannerTitle'          => __( 'Sample Images Download in Progress', 'novel-game-plugin' ),
                    'checkingStatus'       => __( '状態を確認中...', 'novel-game-plugin' ),
                    'viewDetails'          => __( '詳細を見る', 'novel-game-plugin' ),
                    'hideDetails'          => __( '詳細を隠す', 'novel-game-plugin' ),
                    'detailStatusLabel'    => __( 'ステータス', 'novel-game-plugin' ),
                    'detailProgressLabel'  => __( '進捗', 'novel-game-plugin' ),
                    'detailStepLabel'      => __( 'ステップ', 'novel-game-plugin' ),
                    'detailJobIdLabel'     => __( 'ジョブID', 'novel-game-plugin' ),
                    'detailUpdatedLabel'   => __( '更新時刻', 'novel-game-plugin' ),
                    'detailAssetsLabel'    => __( 'アセット数', 'novel-game-plugin' ),
                    'detailQueuedLabel'    => __( '成功ジョブ', 'novel-game-plugin' ),
                    'detailFailedQueuedLabel' => __( '失敗ジョブ', 'novel-game-plugin' ),
                    'detailFailedAssetsLabel' => __( '失敗したアセット', 'novel-game-plugin' ),
                    'detailUnknownLabel'   => __( '不明', 'novel-game-plugin' ),
                    'detailTotalFilesLabel' => __( '対象ファイル数', 'novel-game-plugin' ),
                    'detailDownloadedFilesLabel' => __( '完了ファイル数', 'novel-game-plugin' ),
                    'detailTotalBytesLabel' => __( '総容量', 'novel-game-plugin' ),
                    'detailDownloadedBytesLabel' => __( 'ダウンロード済み容量（推定）', 'novel-game-plugin' ),
                    'detailConfirmedBytesLabel' => __( 'ダウンロード済み容量（確定）', 'novel-game-plugin' ),
                    'detailDestinationLabel' => __( '保存先ディレクトリ', 'novel-game-plugin' ),
                    'detailMissingJobsLabel' => __( '未検出ジョブ数', 'novel-game-plugin' ),
                    'detailActiveJobsLabel' => __( '処理中ジョブ数', 'novel-game-plugin' ),
                    'detailJobLastUpdatedLabel' => __( '最終ジョブ更新時刻', 'novel-game-plugin' ),
                    'detailJobLagLabel' => __( '最終更新からの経過', 'novel-game-plugin' ),
                    'detailNextProcessLabel' => __( '次回ジョブ実行予定', 'novel-game-plugin' ),
                    'detailNextWatchLabel' => __( '次回監視実行予定', 'novel-game-plugin' ),
                    'detailProgressHintLabel' => __( '進捗表示について', 'novel-game-plugin' ),
                    'detailProgressHintText' => __( 'この進捗%はフェーズの目安です。特にダウンロード段階（5〜45%）では大容量ファイル取得中に同じ値が続くことがあります。', 'novel-game-plugin' ),
                    'abortDownloadButton' => __( 'ダウンロードを中断', 'novel-game-plugin' ),
                    'abortDownloadConfirm' => __( '現在のダウンロードを中断して初期化します。よろしいですか？', 'novel-game-plugin' ),
                    'aborting' => __( '中断しています...', 'novel-game-plugin' ),
                    'abortFailed' => __( '中断処理に失敗しました。ページを再読み込みして再試行してください。', 'novel-game-plugin' ),
                ),
            )
        );
    }

    // インラインスクリプトでAJAX処理を追加（サンプルゲームインストールボタン用）
    $inline_script = "
    jQuery(document).ready(function($) {
        // サンプルゲームインストールボタンのクリックイベント
        $('#noveltool-install-sample-game').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('" . esc_js( __( 'Install sample game?', 'novel-game-plugin' ) ) . "')) {
                return;
            }
            
            var button = $(this);
            button.prop('disabled', true).text('" . esc_js( __( 'Installing...', 'novel-game-plugin' ) ) . "');
            
            // AJAX リクエストでサンプルゲームをインストール
            $.post(ajaxurl, {
                action: 'noveltool_install_sample_game',
                nonce: '" . wp_create_nonce( 'noveltool_install_sample_game' ) . "'
            }, function(response) {
                if (response.success) {
                    // 成功時はページをリロード
                    location.reload();
                } else {
                    // 失敗時はエラーメッセージを表示
                    alert(response.data.message || '" . esc_js( __( 'Installation failed.', 'novel-game-plugin' ) ) . "');
                    button.prop('disabled', false).text('" . esc_js( __( 'Install Sample Game', 'novel-game-plugin' ) ) . "');
                }
            }).fail(function() {
                // ネットワークエラー時
                alert('" . esc_js( __( 'Installation failed.', 'novel-game-plugin' ) ) . "');
                button.prop('disabled', false).text('" . esc_js( __( 'Install Sample Game', 'novel-game-plugin' ) ) . "');
            });
        });
    });
    ";
    
    wp_add_inline_script( 'jquery', $inline_script );
}
add_action( 'admin_enqueue_scripts', 'noveltool_my_games_admin_scripts' );

/**
 * サンプル画像プロンプトを非表示にする AJAX ハンドラー
 *
 * @since 1.3.0
 */
function noveltool_dismiss_sample_images_prompt_ajax() {
    // Nonce チェック
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'noveltool_sample_images_prompt' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed', 'novel-game-plugin' ) ) );
    }
    
    // 権限チェック（REST API と同じ権限を使用）
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'novel-game-plugin' ) ) );
    }
    
    // ユーザーメタに保存してプロンプトを非表示に設定
    update_user_meta( get_current_user_id(), 'noveltool_sample_images_prompt_dismissed', true );
    
    wp_send_json_success();
}
add_action( 'wp_ajax_noveltool_dismiss_sample_images_prompt', 'noveltool_dismiss_sample_images_prompt_ajax' );

/**
 * サンプル画像ダウンロードステータスを取得する Ajax ハンドラー
 *
 * 注: このエンドポイントはGETメソッドを使用します。これは読み取り専用の操作であり、
 * 状態を変更しないためです。nonceはGETパラメータで送信されますが、
 * これは管理画面内部でのみ使用され、外部に露出されません。
 *
 * 将来的な検討事項: より堅牢なセキュリティのため、POST + X-WP-Nonce ヘッダー方式への統一を検討。
 * ただし、読み取り専用操作のため現在のGET方式でもセキュリティ上の問題はない。
 *
 * 返却仕様:
 * - 成功時: { success: true, data: { exists: bool, status: string, job_id: string, progress: int, current_step: string, use_background: bool, error: object } }
 * - 失敗時: { success: false, data: { message: string } }
 * - エラー情報は非機密情報のみ含む（code, message, stage, timestamp, meta）
 *
 * @since 1.5.0
 */
function noveltool_check_download_status_ajax() {
    // Nonce チェック
    if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'noveltool_sample_images_prompt' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed', 'novel-game-plugin' ) ) );
    }
    
    // 権限チェック（REST API と同じ権限を使用）
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'novel-game-plugin' ) ) );
    }

    // 停滞ジョブがあれば failed へ遷移（REST API と同等の振る舞い）
    $recovery_result = array();
    $latest_status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    if ( is_array( $latest_status_data ) && isset( $latest_status_data['job_id'] ) && function_exists( 'noveltool_fail_if_job_stalled' ) ) {
        noveltool_fail_if_job_stalled( sanitize_text_field( $latest_status_data['job_id'] ) );
        if ( function_exists( 'noveltool_try_recover_stuck_download_job' ) ) {
            $recovery_result = noveltool_try_recover_stuck_download_job( $latest_status_data );
        }
    }
    
    // サンプル画像の存在チェック
    $exists = noveltool_sample_images_exists();
    $status = get_option( 'noveltool_sample_images_download_status', 'not_started' );
    $status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    $error_data = get_option( 'noveltool_sample_images_download_error', null );
    
    $response = array(
        'exists' => $exists,
        'status' => $status,
    );
    
    // ジョブ情報を追加（バックグラウンド処理の場合）
    if ( isset( $status_data['job_id'] ) ) {
        $response['job_id'] = sanitize_text_field( $status_data['job_id'] );
    }
    if ( isset( $status_data['progress'] ) ) {
        $response['progress'] = intval( $status_data['progress'] );
    }
    if ( isset( $status_data['current_step'] ) ) {
        $response['current_step'] = sanitize_text_field( $status_data['current_step'] );
    }
    if ( isset( $status_data['use_background'] ) ) {
        $response['use_background'] = (bool) $status_data['use_background'];
    }
    if ( isset( $status_data['timestamp'] ) ) {
        $response['status_timestamp'] = intval( $status_data['timestamp'] );
    }
    if ( isset( $status_data['total_assets'] ) ) {
        $response['total_assets'] = intval( $status_data['total_assets'] );
    }
    if ( isset( $status_data['successful_jobs'] ) ) {
        $response['successful_jobs'] = intval( $status_data['successful_jobs'] );
    }
    if ( isset( $status_data['failed_jobs'] ) ) {
        $response['failed_jobs'] = intval( $status_data['failed_jobs'] );
    }
    if ( isset( $status_data['destination_dir'] ) ) {
        $response['destination_dir'] = sanitize_text_field( $status_data['destination_dir'] );
    }
    if ( isset( $status_data['total_files'] ) ) {
        $response['total_files'] = intval( $status_data['total_files'] );
    }
    if ( isset( $status_data['downloaded_files'] ) ) {
        $response['downloaded_files'] = intval( $status_data['downloaded_files'] );
    }
    if ( isset( $status_data['total_bytes'] ) ) {
        $response['total_bytes'] = intval( $status_data['total_bytes'] );
    }
    if ( isset( $status_data['downloaded_bytes'] ) ) {
        $response['downloaded_bytes'] = intval( $status_data['downloaded_bytes'] );
    }
    if ( isset( $status_data['failed_assets'] ) && is_array( $status_data['failed_assets'] ) ) {
        $response['failed_assets'] = array();
        foreach ( $status_data['failed_assets'] as $asset ) {
            if ( ! is_array( $asset ) ) {
                continue;
            }

            $response['failed_assets'][] = array(
                'name'    => isset( $asset['name'] ) ? sanitize_text_field( $asset['name'] ) : 'unknown',
                'message' => isset( $asset['message'] ) ? sanitize_text_field( $asset['message'] ) : '',
                'reason'  => isset( $asset['reason'] ) ? sanitize_text_field( $asset['reason'] ) : '',
            );
        }
    }

    if ( function_exists( 'noveltool_get_download_runtime_metrics' ) ) {
        $runtime_metrics = noveltool_get_download_runtime_metrics( $status_data );
        if ( ! empty( $runtime_metrics ) && is_array( $runtime_metrics ) ) {
            $response = array_merge( $response, $runtime_metrics );
        }
    }
    if ( ! empty( $recovery_result ) && is_array( $recovery_result ) ) {
        $response['auto_recovery'] = array(
            'attempted' => ! empty( $recovery_result['attempted'] ),
            'scheduled' => ! empty( $recovery_result['scheduled'] ),
            'reason'    => isset( $recovery_result['reason'] ) ? sanitize_text_field( $recovery_result['reason'] ) : '',
        );
    }
    
    // エラー情報があれば構造化して追加（非機密情報のみ）
    if ( 'failed' === $status && ! empty( $error_data ) && is_array( $error_data ) ) {
        $response['error'] = array(
            'code'      => isset( $error_data['code'] ) ? sanitize_text_field( $error_data['code'] ) : 'ERR-UNKNOWN',
            'message'   => isset( $error_data['message'] ) ? sanitize_text_field( $error_data['message'] ) : '',
            'stage'     => isset( $error_data['stage'] ) ? sanitize_text_field( $error_data['stage'] ) : 'other',
            'timestamp' => isset( $error_data['timestamp'] ) ? intval( $error_data['timestamp'] ) : 0,
        );
        
        // メタ情報があれば追加（非機密のみ、サニタイズ済み）
        if ( isset( $error_data['meta'] ) && is_array( $error_data['meta'] ) ) {
            $safe_meta = array();
            $allowed_meta_keys = array( 'http_code', 'stage_detail', 'retry_count' );
            foreach ( $allowed_meta_keys as $key ) {
                if ( isset( $error_data['meta'][ $key ] ) ) {
                    $safe_meta[ $key ] = sanitize_text_field( $error_data['meta'][ $key ] );
                }
            }
            if ( ! empty( $safe_meta ) ) {
                $response['error']['meta'] = $safe_meta;
            }
        }
    }
    
    wp_send_json_success( $response );
}
add_action( 'wp_ajax_noveltool_check_download_status', 'noveltool_check_download_status_ajax' );

/**
 * 管理者向け: 診断パッケージを生成してダウンロードさせるハンドラー
 *
 * WP 管理画面からワンクリックで取得できる診断 ZIP を生成します。
 * 含める内容は最小限にし、管理者権限が必要です。
 *
 * @since 1.3.0
 */
function noveltool_handle_download_diagnostic() {
    // 権限チェック
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Permission denied.', 'novel-game-plugin' ) );
    }

    // ノンス検証
    check_admin_referer( 'noveltool_download_diagnostic' );

    // ZipArchive 必須
    if ( ! class_exists( 'ZipArchive' ) ) {
        wp_die( __( 'Zip extension is not available on the server.', 'novel-game-plugin' ) );
    }

    $tmp = tempnam( sys_get_temp_dir(), 'noveltool_diag_' );
    $zip = new ZipArchive();
    if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
        wp_die( __( 'Failed to create diagnostic package.', 'novel-game-plugin' ) );
    }

    // 診断情報を生成
    $diagnostics = array();
    $diagnostics[] = 'Generated: ' . date_i18n( 'c' );
    $diagnostics[] = 'WP Version: ' . get_bloginfo( 'version' );
    $diagnostics[] = 'PHP Version: ' . phpversion();
    $diagnostics[] = 'Memory limit: ' . ini_get( 'memory_limit' );
    $diagnostics[] = 'Max execution time: ' . ini_get( 'max_execution_time' );
    if ( defined( 'NOVEL_GAME_PLUGIN_VERSION' ) ) {
        $diagnostics[] = 'Plugin version: ' . NOVEL_GAME_PLUGIN_VERSION;
    }

    // もしプラグインが保持している直近のエラー情報があれば収集（任意）
    $last_error = get_option( 'noveltool_last_error', '' );
    if ( $last_error ) {
        $diagnostics[] = '\nLast stored plugin error:';
        $diagnostics[] = wp_json_encode( $last_error );
    }

    $zip->addFromString( 'diagnostics.txt', implode( PHP_EOL, $diagnostics ) );

    // wp-content debug.log を含める（存在する場合のみ）
    if ( defined( 'WP_CONTENT_DIR' ) ) {
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $debug_log ) ) {
            $zip->addFile( $debug_log, 'wp-content-debug.log' );
        }
    }

    // プラグインのメタ情報のみを抽出（PHPファイルは含めない）
    $main_plugin = NOVEL_GAME_PLUGIN_PATH . 'novel-game-plugin.php';
    if ( file_exists( $main_plugin ) ) {
        $plugin_headers = get_file_data(
            $main_plugin,
            array(
                'Name'        => 'Plugin Name',
                'PluginURI'   => 'Plugin URI',
                'Version'     => 'Version',
                'Description' => 'Description',
                'Author'      => 'Author',
                'AuthorURI'   => 'Author URI',
                'TextDomain'  => 'Text Domain',
                'DomainPath'  => 'Domain Path',
                'Network'     => 'Network',
                'RequiresWP'  => 'Requires at least',
                'RequiresPHP' => 'Requires PHP',
            )
        );
        
        $plugin_info = array();
        foreach ( $plugin_headers as $key => $value ) {
            if ( ! empty( $value ) ) {
                $plugin_info[] = $key . ': ' . $value;
            }
        }
        
        $zip->addFromString( 'plugin-info.txt', implode( PHP_EOL, $plugin_info ) );
    }

    // noveltool_background_jobs と noveltool_job_log オプションを含める
    $background_jobs = get_option( 'noveltool_background_jobs', array() );
    if ( ! empty( $background_jobs ) ) {
        $zip->addFromString( 'background-jobs.json', wp_json_encode( $background_jobs, JSON_PRETTY_PRINT ) );
    }

    $job_log = get_option( 'noveltool_job_log', array() );
    if ( ! empty( $job_log ) ) {
        $zip->addFromString( 'job-log.json', wp_json_encode( $job_log, JSON_PRETTY_PRINT ) );
    }

    // サンプル画像ダウンロードステータスを含める
    $download_status = get_option( 'noveltool_sample_images_download_status_data', array() );
    if ( ! empty( $download_status ) ) {
        $zip->addFromString( 'download-status.json', wp_json_encode( $download_status, JSON_PRETTY_PRINT ) );
    }

    $download_error = get_option( 'noveltool_sample_images_download_error', '' );
    if ( ! empty( $download_error ) ) {
        if ( is_array( $download_error ) || is_object( $download_error ) ) {
            $serialized_error = wp_json_encode( $download_error, JSON_PRETTY_PRINT );
            if ( false === $serialized_error || null === $serialized_error ) {
                $serialized_error = __( 'Download error data could not be serialized.', 'novel-game-plugin' );
            }
            $zip->addFromString( 'download-error.json', $serialized_error );
        } else {
            $zip->addFromString( 'download-error.txt', sanitize_textarea_field( (string) $download_error ) );
        }
    }

    $zip->close();

    // 出力して削除
    if ( file_exists( $tmp ) ) {
        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="noveltool-diagnostic-' . gmdate( 'Ymd-His' ) . '.zip"' );
        header( 'Content-Length: ' . filesize( $tmp ) );
        readfile( $tmp );
        unlink( $tmp );
        exit;
    }

    wp_die( __( 'Failed to generate diagnostic package.', 'novel-game-plugin' ) );
}
add_action( 'admin_post_noveltool_download_diagnostic', 'noveltool_handle_download_diagnostic' );
