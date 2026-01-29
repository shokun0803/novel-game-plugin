<?php
/**
 * サンプル画像ダウンローダー
 * 
 * GitHub Release からサンプル画像をダウンロードする機能を提供
 *
 * @package NovelGamePlugin
 * @since 1.3.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * サンプル画像ディレクトリが存在するかチェック
 *
 * @return bool サンプル画像ディレクトリが存在する場合true
 * @since 1.3.0
 */
function noveltool_sample_images_exists() {
    $sample_images_dir = NOVEL_GAME_PLUGIN_PATH . 'assets/sample-images';
    return is_dir( $sample_images_dir ) && ! empty( glob( $sample_images_dir . '/*' ) );
}

/**
 * GitHub Releases API から最新リリース情報を取得
 *
 * @return array|WP_Error リリース情報の配列またはエラー
 * @since 1.3.0
 */
function noveltool_get_latest_release_info() {
    $api_url = 'https://api.github.com/repos/shokun0803/novel-game-plugin/releases/latest';
    
    $response = wp_remote_get(
        $api_url,
        array(
            'timeout' => 15,
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'NovelGamePlugin/' . NOVEL_GAME_PLUGIN_VERSION . ' (+https://github.com/shokun0803/novel-game-plugin)',
            ),
        )
    );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
        // 404 の場合は /releases/latest が存在しない（公開リリースが無い）可能性があるため
        // プレリリースを含む一覧を取得してフォールバックする
        if ( $status_code === 404 ) {
            $list_url = 'https://api.github.com/repos/shokun0803/novel-game-plugin/releases?per_page=10';
            $list_resp = wp_remote_get(
                $list_url,
                array(
                    'timeout' => 15,
                    'headers' => array(
                        'Accept'     => 'application/vnd.github.v3+json',
                        'User-Agent' => 'NovelGamePlugin/' . NOVEL_GAME_PLUGIN_VERSION . ' (+https://github.com/shokun0803/novel-game-plugin)',
                    ),
                )
            );

            if ( is_wp_error( $list_resp ) ) {
                return $list_resp;
            }

            $list_code = wp_remote_retrieve_response_code( $list_resp );
            if ( $list_code !== 200 ) {
                return new WP_Error(
                    'api_error',
                    sprintf(
                        /* translators: %d: HTTP status code */
                        __( 'Failed to fetch release info. HTTP status code: %d', 'novel-game-plugin' ),
                        $status_code
                    )
                );
            }

            $list_body = wp_remote_retrieve_body( $list_resp );
            $list_data = json_decode( $list_body, true );
            if ( json_last_error() !== JSON_ERROR_NONE || empty( $list_data ) || ! is_array( $list_data ) ) {
                return new WP_Error(
                    'api_error',
                    __( 'Failed to fetch release info: no releases found.', 'novel-game-plugin' )
                );
            }

            // 一覧の先頭を最新（作成順）とみなして返す
            return $list_data[0];
        }

        return new WP_Error(
            'api_error',
            sprintf(
                /* translators: %d: HTTP status code */
                __( 'Failed to fetch release info. HTTP status code: %d', 'novel-game-plugin' ),
                $status_code
            )
        );
    }
    
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_Error(
            'json_error',
            __( 'Failed to parse release information.', 'novel-game-plugin' )
        );
    }
    
    return $data;
}

/**
 * リリースアセットからサンプル画像 ZIP を探す
 *
 * @param array $release_data リリースデータ
 * @return array|null アセット情報またはnull
 * @since 1.3.0
 */
function noveltool_find_sample_images_asset( $release_data ) {
    if ( ! isset( $release_data['assets'] ) || ! is_array( $release_data['assets'] ) ) {
        return null;
    }
    
    // サンプル画像アセット名のパターン
    // 例: novel-game-plugin-sample-images-1.3.0.zip, novel-game-plugin-sample-images-v1.3.0.zip, novel-game-plugin-sample-images.zip
    $preferred_names = array(
        'novel-game-plugin-sample-images',
        'novel-game-plugin-sample-images-v',
        'novel-game-plugin-sample-images.zip',
    );
    
    $candidates = array();
    
    foreach ( $release_data['assets'] as $asset ) {
        if ( ! isset( $asset['name'] ) || ! isset( $asset['browser_download_url'] ) ) {
            continue;
        }
        
        $name = $asset['name'];
        
        // 命名規約に基づいて候補を収集
        foreach ( $preferred_names as $index => $pattern ) {
            if ( strpos( $name, $pattern ) === 0 && substr( $name, -4 ) === '.zip' ) {
                $candidates[] = array(
                    'priority' => $index,
                    'asset'    => $asset,
                );
                break;
            }
        }
    }
    
    if ( empty( $candidates ) ) {
        return null;
    }
    
    // 優先順位でソート
    usort(
        $candidates,
        function ( $a, $b ) {
            return $a['priority'] - $b['priority'];
        }
    );
    
    return $candidates[0]['asset'];
}

/**
 * サンプル画像 ZIP をダウンロード
 *
 * @param string $download_url ダウンロードURL
 * @return string|WP_Error 一時ファイルのパスまたはエラー
 * @since 1.3.0
 */
function noveltool_download_sample_images_zip( $download_url ) {
    $temp_file = wp_tempnam( 'noveltool-sample-images.zip' );
    
    if ( ! $temp_file ) {
        return new WP_Error(
            'tempfile_error',
            __( 'Failed to create temporary file.', 'novel-game-plugin' )
        );
    }
    
    $response = wp_remote_get(
        $download_url,
        array(
            'timeout'  => 300, // 5分
            'stream'   => true,
            'filename' => $temp_file,
            'headers'  => array(
                'User-Agent' => 'NovelGamePlugin/' . NOVEL_GAME_PLUGIN_VERSION . ' (+https://github.com/shokun0803/novel-game-plugin)',
            ),
        )
    );
    
    if ( is_wp_error( $response ) ) {
        @unlink( $temp_file );
        return $response;
    }
    
    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
        @unlink( $temp_file );
        return new WP_Error(
            'download_error',
            sprintf(
                /* translators: %d: HTTP status code */
                __( 'Failed to download file. HTTP status code: %d', 'novel-game-plugin' ),
                $status_code
            )
        );
    }
    
    return $temp_file;
}

/**
 * SHA256 チェックサムを検証
 *
 * @param string $file_path ファイルパス
 * @param string $expected_checksum 期待されるチェックサム
 * @return bool 検証が成功した場合true
 * @since 1.3.0
 */
function noveltool_verify_checksum( $file_path, $expected_checksum ) {
    if ( ! file_exists( $file_path ) ) {
        return false;
    }
    
    $actual_checksum = hash_file( 'sha256', $file_path );
    
    return hash_equals( strtolower( $expected_checksum ), strtolower( $actual_checksum ) );
}

/**
 * ZIP ファイルを展開
 *
 * @param string $zip_file ZIPファイルのパス
 * @param string $destination 展開先ディレクトリ
 * @return bool|WP_Error 成功した場合true、失敗した場合WP_Error
 * @since 1.3.0
 */
function noveltool_extract_zip( $zip_file, $destination ) {
    global $wp_filesystem;
    
    // WordPress Filesystem API を初期化
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    
    WP_Filesystem();
    
    if ( ! $wp_filesystem ) {
        return new WP_Error(
            'filesystem_error',
            __( 'Could not initialize filesystem.', 'novel-game-plugin' )
        );
    }
    
    // 親ディレクトリの書き込み権限をチェック
    $parent_dir = dirname( $destination );
    if ( ! $wp_filesystem->is_writable( $parent_dir ) ) {
        return new WP_Error(
            'permission_error',
            sprintf(
                /* translators: %s: directory path */
                __( 'Destination directory is not writable: %s', 'novel-game-plugin' ),
                $parent_dir
            )
        );
    }
    
    // 展開先ディレクトリを作成
    if ( ! $wp_filesystem->is_dir( $destination ) ) {
        if ( ! $wp_filesystem->mkdir( $destination, FS_CHMOD_DIR ) ) {
            return new WP_Error(
                'mkdir_error',
                __( 'Could not create destination directory.', 'novel-game-plugin' )
            );
        }
    }
    
    // ZIP を展開
    $result = unzip_file( $zip_file, $destination );
    
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    
    return true;
}

/**
 * ダウンロードステータスのタイムアウト時間（秒）
 * 
 * @since 1.3.0
 */
if ( ! defined( 'NOVELTOOL_DOWNLOAD_TTL' ) ) {
    define( 'NOVELTOOL_DOWNLOAD_TTL', 1800 ); // 30分
}

/**
 * ダウンロードステータスに TTL（Time To Live）をチェック
 * 長時間 in_progress のまま残っている場合は自動的に failed に変更
 *
 * @since 1.3.0
 */
function noveltool_check_download_status_ttl() {
    $status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    
    if ( empty( $status_data ) || ! isset( $status_data['status'] ) ) {
        return;
    }
    
    // in_progress で TTL 以上経過している場合は failed に変更
    if ( 'in_progress' === $status_data['status'] && isset( $status_data['timestamp'] ) ) {
        $elapsed = time() - $status_data['timestamp'];
        if ( $elapsed > NOVELTOOL_DOWNLOAD_TTL ) {
            noveltool_update_download_status( 'failed', __( 'Download timeout: The download process took too long and was automatically cancelled.', 'novel-game-plugin' ), 'ERR-TIMEOUT', 'other', array( 'stage_detail' => 'ttl_exceeded' ) );
            // 古いロックを削除
            delete_option( 'noveltool_sample_images_download_lock' );
        }
    }
}

/**
 * ダウンロードステータスを更新
 *
 * @param string $status ステータス（not_started, in_progress, completed, failed）
 * @param string $error_message エラーメッセージ（失敗時のみ）
 * @param string $error_code エラーコード（失敗時のみ）
 * @param string $error_stage エラー発生段階（失敗時のみ）
 * @param array  $error_meta エラーメタ情報（失敗時のみ）
 * @since 1.3.0
 */
function noveltool_update_download_status( $status, $error_message = '', $error_code = '', $error_stage = '', $error_meta = array() ) {
    $timestamp = time();
    
    $status_data = array(
        'status'    => $status,
        'timestamp' => $timestamp,
    );
    
    // 後方互換性のため、単純なステータス文字列も保存
    update_option( 'noveltool_sample_images_download_status', $status, false );
    update_option( 'noveltool_sample_images_download_status_data', $status_data, false );
    
    // エラー情報を構造化して保存
    if ( $status === 'failed' && ! empty( $error_message ) ) {
        $error_data = array(
            'code'      => ! empty( $error_code ) ? sanitize_text_field( $error_code ) : 'ERR-UNKNOWN',
            'message'   => sanitize_text_field( $error_message ),
            'stage'     => ! empty( $error_stage ) ? sanitize_text_field( $error_stage ) : 'other',
            'timestamp' => $timestamp,
        );
        
        // 非機密メタ情報のみ保存
        if ( ! empty( $error_meta ) && is_array( $error_meta ) ) {
            $safe_meta = array();
            // 許可されたメタキーのみ保存（機密情報を除外）
            $allowed_keys = array( 'http_code', 'stage_detail', 'retry_count' );
            foreach ( $allowed_keys as $key ) {
                if ( isset( $error_meta[ $key ] ) ) {
                    $safe_meta[ $key ] = sanitize_text_field( $error_meta[ $key ] );
                }
            }
            if ( ! empty( $safe_meta ) ) {
                $error_data['meta'] = $safe_meta;
            }
        }
        
        update_option( 'noveltool_sample_images_download_error', $error_data, false );
        
        // 内部ログに詳細を記録（デバッグ用）
        error_log( sprintf(
            'NovelGamePlugin: Download failed - Code: %s, Stage: %s, Message: %s',
            $error_data['code'],
            $error_data['stage'],
            $error_message
        ) );
    } elseif ( $status === 'completed' || $status === 'not_started' ) {
        // 完了または再開時はエラー情報をクリア
        delete_option( 'noveltool_sample_images_download_error' );
    }
    // in_progress 時は過去のエラー情報を保持（デバッグ用）
}

/**
 * 致命的エラー発生時にエラー情報を保存するシャットダウンフック
 * 
 * @since 1.3.0
 */
function noveltool_save_error_on_shutdown() {
    $error = error_get_last();
    if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
        // 致命的エラーの場合、ステータスを failed に更新
        $status = get_option( 'noveltool_sample_images_download_status', '' );
        if ( $status === 'in_progress' ) {
            noveltool_update_download_status(
                'failed',
                __( 'A fatal error occurred during download.', 'novel-game-plugin' ),
                'ERR-FATAL',
                'other',
                array( 'stage_detail' => 'php_shutdown' )
            );
            delete_option( 'noveltool_sample_images_download_lock' );
        }
    }
}

/**
 * サンプル画像ダウンロードのメイン処理
 *
 * @return array 結果配列 array('success' => bool, 'message' => string)
 * @since 1.3.0
 */
function noveltool_perform_sample_images_download() {
    // 既に存在する場合はスキップ
    if ( noveltool_sample_images_exists() ) {
        return array(
            'success' => false,
            'message' => __( 'Sample images already exist.', 'novel-game-plugin' ),
        );
    }
    
    // TTL チェック: 長時間 in_progress のままの場合は自動復旧
    noveltool_check_download_status_ttl();
    
    // 原子的なロック取得: add_option は既存の場合失敗するため競合回避可能
    $lock_acquired = add_option( 'noveltool_sample_images_download_lock', time(), '', 'no' );
    
    if ( ! $lock_acquired ) {
        // ロック取得失敗 - 他のプロセスが実行中
        $error_msg = __( 'Download already in progress.', 'novel-game-plugin' );
        error_log( 'NovelGamePlugin: Failed to acquire download lock. Another process may have started the download.' );
        return array(
            'success' => false,
            'message' => $error_msg,
        );
    }
    
    // ステータスを in_progress に更新
    noveltool_update_download_status( 'in_progress' );
    
    // シャットダウンフック登録（致命的エラー対応）
    register_shutdown_function( 'noveltool_save_error_on_shutdown' );
    
    // Filesystem の初期化と書き込み権限の事前チェック
    global $wp_filesystem;
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    
    if ( ! $wp_filesystem ) {
        $error_msg = __( 'Could not initialize filesystem.', 'novel-game-plugin' );
        noveltool_update_download_status( 'failed', $error_msg, 'ERR-FS-INIT', 'filesystem' );
        delete_option( 'noveltool_sample_images_download_lock' );
        return array(
            'success' => false,
            'message' => $error_msg,
            'code'    => 'ERR-FS-INIT',
            'stage'   => 'filesystem',
        );
    }
    
    $destination_parent = NOVEL_GAME_PLUGIN_PATH . 'assets';
    if ( ! $wp_filesystem->is_writable( $destination_parent ) ) {
        $error_msg = sprintf(
            /* translators: %s: directory path */
            __( 'Destination directory is not writable: %s. Please check file permissions.', 'novel-game-plugin' ),
            $destination_parent
        );
        noveltool_update_download_status( 'failed', $error_msg, 'ERR-PERM', 'filesystem', array( 'stage_detail' => 'parent_dir_not_writable' ) );
        delete_option( 'noveltool_sample_images_download_lock' );
        return array(
            'success' => false,
            'message' => $error_msg,
            'code'    => 'ERR-PERM',
            'stage'   => 'filesystem',
        );
    }
    
    // 最新リリース情報を取得
    $release_data = noveltool_get_latest_release_info();
    if ( is_wp_error( $release_data ) ) {
        $error_msg = sprintf(
            /* translators: %s: error message */
            __( 'Failed to fetch release information: %s', 'novel-game-plugin' ),
            $release_data->get_error_message()
        );
        noveltool_update_download_status( 'failed', $error_msg, 'ERR-RELEASE-FETCH', 'fetch_release', array( 'http_code' => $release_data->get_error_code() ) );
        delete_option( 'noveltool_sample_images_download_lock' );
        return array(
            'success' => false,
            'message' => $error_msg,
            'code'    => 'ERR-RELEASE-FETCH',
            'stage'   => 'fetch_release',
        );
    }
    
    // サンプル画像アセットを探す
    $asset = noveltool_find_sample_images_asset( $release_data );
    if ( ! $asset ) {
        $error_msg = __( 'Sample images asset not found in the latest release. Please contact the plugin developer.', 'novel-game-plugin' );
        noveltool_update_download_status( 'failed', $error_msg, 'ERR-ASSET-NOTFOUND', 'fetch_release' );
        delete_option( 'noveltool_sample_images_download_lock' );
        return array(
            'success' => false,
            'message' => $error_msg,
            'code'    => 'ERR-ASSET-NOTFOUND',
            'stage'   => 'fetch_release',
        );
    }
    
    $download_url = $asset['browser_download_url'];
    $asset_name   = $asset['name'];
    
    // チェックサムファイルを探す
    $checksum_asset = null;
    foreach ( $release_data['assets'] as $a ) {
        if ( isset( $a['name'] ) && $a['name'] === $asset_name . '.sha256' ) {
            $checksum_asset = $a;
            break;
        }
    }
    
    // ZIP をダウンロード
    $temp_zip = noveltool_download_sample_images_zip( $download_url );
    if ( is_wp_error( $temp_zip ) ) {
        $error_msg = sprintf(
            /* translators: %s: error message */
            __( 'Failed to download sample images: %s', 'novel-game-plugin' ),
            $temp_zip->get_error_message()
        );
        noveltool_update_download_status( 'failed', $error_msg, 'ERR-DOWNLOAD', 'download', array( 'http_code' => $temp_zip->get_error_code() ) );
        delete_option( 'noveltool_sample_images_download_lock' );
        return array(
            'success' => false,
            'message' => $error_msg,
            'code'    => 'ERR-DOWNLOAD',
            'stage'   => 'download',
        );
    }
    
    // チェックサム検証（チェックサムファイルが存在する場合）
    if ( $checksum_asset ) {
        $headers = array(
            'User-Agent' => 'NovelGamePlugin/' . NOVEL_GAME_PLUGIN_VERSION . ' (+https://github.com/shokun0803/novel-game-plugin)',
        );
        
        $checksum_response = wp_remote_get(
            $checksum_asset['browser_download_url'],
            array(
                'timeout' => 30,
                'headers' => $headers,
            )
        );
        
        if ( is_wp_error( $checksum_response ) ) {
            // チェックサムファイルの取得失敗は警告のみ（処理は続行）
            error_log( 'NovelGamePlugin: Failed to fetch checksum file: ' . $checksum_response->get_error_message() );
        } else {
            $checksum_status = wp_remote_retrieve_response_code( $checksum_response );
            if ( $checksum_status !== 200 ) {
                // HTTP ステータスエラーをログに記録（処理は続行）
                error_log( sprintf( 'NovelGamePlugin: Checksum file fetch returned HTTP %d. URL: %s', $checksum_status, $checksum_asset['browser_download_url'] ) );
            } else {
                $checksum_body = wp_remote_retrieve_body( $checksum_response );
                
                // SHA256 チェックサム（64文字の16進数）を抽出
                if ( preg_match( '/\b([a-f0-9]{64})\b/i', $checksum_body, $matches ) ) {
                    $expected_checksum = $matches[1];
                    
                    if ( ! noveltool_verify_checksum( $temp_zip, $expected_checksum ) ) {
                        @unlink( $temp_zip );
                        $error_msg = __( 'Checksum verification failed. The downloaded file may be corrupted. Please try again.', 'novel-game-plugin' );
                        noveltool_update_download_status( 'failed', $error_msg, 'ERR-CHECKSUM', 'verify_checksum' );
                        delete_option( 'noveltool_sample_images_download_lock' );
                        return array(
                            'success' => false,
                            'message' => $error_msg,
                            'code'    => 'ERR-CHECKSUM',
                            'stage'   => 'verify_checksum',
                        );
                    }
                } else {
                    // チェックサムフォーマットが不正（64文字の16進数ではない）
                    error_log( sprintf( 'NovelGamePlugin: Invalid checksum format in .sha256 file. Expected 64-character hex string. Content: %s', substr( $checksum_body, 0, 200 ) ) );
                    // フォーマット不正の場合も処理は続行（チェックサムなしでインストール）
                }
            }
        }
    }
    
    // ZIP を展開
    $destination = NOVEL_GAME_PLUGIN_PATH . 'assets/sample-images';
    $extract_result = noveltool_extract_zip( $temp_zip, $destination );
    
    // 一時ファイルを削除
    @unlink( $temp_zip );
    
    if ( is_wp_error( $extract_result ) ) {
        $error_msg = sprintf(
            /* translators: %s: error message */
            __( 'Failed to extract sample images: %s', 'novel-game-plugin' ),
            $extract_result->get_error_message()
        );
        noveltool_update_download_status( 'failed', $error_msg, 'ERR-EXTRACT', 'extract' );
        delete_option( 'noveltool_sample_images_download_lock' );
        return array(
            'success' => false,
            'message' => $error_msg,
            'code'    => 'ERR-EXTRACT',
            'stage'   => 'extract',
        );
    }
    
    // 完了状態を記録
    noveltool_update_download_status( 'completed' );
    update_option( 'noveltool_sample_images_downloaded', true, false );
    
    // ロックを解放
    delete_option( 'noveltool_sample_images_download_lock' );
    
    return array(
        'success' => true,
        'message' => __( 'Sample images downloaded and installed successfully.', 'novel-game-plugin' ),
    );
}

/**
 * サンプル画像ダウンロード用 REST API エンドポイントを登録
 *
 * @since 1.3.0
 */
function noveltool_register_sample_images_api() {
    register_rest_route(
        'novel-game-plugin/v1',
        '/sample-images/download',
        array(
            'methods'             => 'POST',
            'callback'            => 'noveltool_api_download_sample_images',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        )
    );
    
    register_rest_route(
        'novel-game-plugin/v1',
        '/sample-images/status',
        array(
            'methods'             => 'GET',
            'callback'            => 'noveltool_api_sample_images_status',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        )
    );
    
    register_rest_route(
        'novel-game-plugin/v1',
        '/sample-images/reset-status',
        array(
            'methods'             => 'POST',
            'callback'            => 'noveltool_api_reset_download_status',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        )
    );
}
add_action( 'rest_api_init', 'noveltool_register_sample_images_api' );

/**
 * サンプル画像ダウンロード API コールバック
 *
 * @param WP_REST_Request $request リクエストオブジェクト
 * @return WP_REST_Response レスポンス
 * @since 1.3.0
 */
function noveltool_api_download_sample_images( $request ) {
    $result = noveltool_perform_sample_images_download();
    
    if ( $result['success'] ) {
        return new WP_REST_Response( $result, 200 );
    } else {
        // 失敗時は詳細なエラー情報を含めて返す
        $error_data = get_option( 'noveltool_sample_images_download_error', null );
        
        // エラーコードとステージから適切なHTTPステータスを決定
        $http_status = 400; // デフォルト
        $error_code = isset( $result['code'] ) ? $result['code'] : 'download_failed';
        $error_stage = isset( $result['stage'] ) ? $result['stage'] : 'other';
        
        // ステージ/コード別のHTTPステータス
        if ( strpos( $error_code, 'ERR-PERM' ) === 0 || strpos( $error_code, 'ERR-FS-INIT' ) === 0 ) {
            $http_status = 403; // 権限エラー
        } elseif ( strpos( $error_code, 'ERR-ASSET-NOTFOUND' ) === 0 ) {
            $http_status = 404; // リソース未検出
        } elseif ( strpos( $error_code, 'ERR-CHECKSUM' ) === 0 ) {
            $http_status = 422; // 検証失敗
        } elseif ( strpos( $error_code, 'ERR-RELEASE-FETCH' ) === 0 || strpos( $error_code, 'ERR-DOWNLOAD' ) === 0 ) {
            $http_status = 502; // 外部サービスエラー
        } elseif ( strpos( $error_code, 'ERR-EXTRACT' ) === 0 || strpos( $error_code, 'ERR-FATAL' ) === 0 ) {
            $http_status = 500; // サーバー内部エラー
        }
        
        $response = array(
            'success' => false,
            'message' => sanitize_text_field( $result['message'] ),
            'error'   => array(
                'code'      => sanitize_text_field( $error_code ),
                'message'   => sanitize_text_field( $result['message'] ),
                'stage'     => sanitize_text_field( $error_stage ),
                'timestamp' => is_array( $error_data ) && isset( $error_data['timestamp'] ) ? intval( $error_data['timestamp'] ) : time(),
            ),
        );
        
        // メタ情報があれば追加（非機密のみ）
        if ( is_array( $error_data ) && isset( $error_data['meta'] ) && is_array( $error_data['meta'] ) ) {
            $response['error']['meta'] = $error_data['meta'];
        }
        
        return new WP_REST_Response( $response, $http_status );
    }
}

/**
 * サンプル画像ダウンロード状況取得 API コールバック
 *
 * @param WP_REST_Request $request リクエストオブジェクト
 * @return WP_REST_Response レスポンス
 * @since 1.3.0
 */
function noveltool_api_sample_images_status( $request ) {
    $exists = noveltool_sample_images_exists();
    $status = get_option( 'noveltool_sample_images_download_status', 'not_started' );
    $error_data = get_option( 'noveltool_sample_images_download_error', null );
    
    $response = array(
        'exists' => $exists,
        'status' => $status,
    );
    
    // エラー情報があれば構造化して追加
    if ( ! empty( $error_data ) && is_array( $error_data ) ) {
        $response['error'] = array(
            'code'      => isset( $error_data['code'] ) ? sanitize_text_field( $error_data['code'] ) : 'ERR-UNKNOWN',
            'message'   => isset( $error_data['message'] ) ? sanitize_text_field( $error_data['message'] ) : '',
            'stage'     => isset( $error_data['stage'] ) ? sanitize_text_field( $error_data['stage'] ) : 'other',
            'timestamp' => isset( $error_data['timestamp'] ) ? intval( $error_data['timestamp'] ) : 0,
        );
        
        // メタ情報があれば追加（非機密のみ）
        if ( isset( $error_data['meta'] ) && is_array( $error_data['meta'] ) ) {
            $response['error']['meta'] = $error_data['meta'];
        }
    }
    
    return new WP_REST_Response( $response, 200 );
}

/**
 * ダウンロードステータスをリセットする API コールバック
 *
 * @param WP_REST_Request $request リクエストオブジェクト
 * @return WP_REST_Response レスポンス
 * @since 1.3.0
 */
function noveltool_api_reset_download_status( $request ) {
    // ステータスを not_started にリセット
    noveltool_update_download_status( 'not_started' );
    
    // ロックも解放
    delete_option( 'noveltool_sample_images_download_lock' );
    
    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => __( 'Download status has been reset.', 'novel-game-plugin' ),
        ),
        200
    );
}
