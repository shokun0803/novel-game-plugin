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
    
    $preferred_names = array(
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
    
    // 同時実行ガード: 既にダウンロード中の場合はエラーを返す
    $status = get_option( 'noveltool_sample_images_download_status', 'not_started' );
    if ( 'in_progress' === $status ) {
        return array(
            'success' => false,
            'message' => __( 'Download already in progress.', 'novel-game-plugin' ),
        );
    }
    
    // ダウンロード状況を記録
    update_option( 'noveltool_sample_images_download_status', 'in_progress' );
    
    // Filesystem の初期化と書き込み権限の事前チェック
    global $wp_filesystem;
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();
    
    if ( ! $wp_filesystem ) {
        update_option( 'noveltool_sample_images_download_status', 'failed' );
        return array(
            'success' => false,
            'message' => __( 'Could not initialize filesystem.', 'novel-game-plugin' ),
        );
    }
    
    $destination_parent = NOVEL_GAME_PLUGIN_PATH . 'assets';
    if ( ! $wp_filesystem->is_writable( $destination_parent ) ) {
        update_option( 'noveltool_sample_images_download_status', 'failed' );
        return array(
            'success' => false,
            'message' => sprintf(
                /* translators: %s: directory path */
                __( 'Destination directory is not writable: %s', 'novel-game-plugin' ),
                $destination_parent
            ),
        );
    }
    
    // 最新リリース情報を取得
    $release_data = noveltool_get_latest_release_info();
    if ( is_wp_error( $release_data ) ) {
        update_option( 'noveltool_sample_images_download_status', 'failed' );
        return array(
            'success' => false,
            'message' => $release_data->get_error_message(),
        );
    }
    
    // サンプル画像アセットを探す
    $asset = noveltool_find_sample_images_asset( $release_data );
    if ( ! $asset ) {
        update_option( 'noveltool_sample_images_download_status', 'failed' );
        return array(
            'success' => false,
            'message' => __( 'Sample images asset not found in the latest release.', 'novel-game-plugin' ),
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
        update_option( 'noveltool_sample_images_download_status', 'failed' );
        return array(
            'success' => false,
            'message' => $temp_zip->get_error_message(),
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
                error_log( 'NovelGamePlugin: Checksum file fetch returned HTTP ' . $checksum_status );
            } else {
                $checksum_body = wp_remote_retrieve_body( $checksum_response );
                
                // SHA256 チェックサム（64文字の16進数）を抽出
                if ( preg_match( '/\b([a-f0-9]{64})\b/i', $checksum_body, $matches ) ) {
                    $expected_checksum = $matches[1];
                    
                    if ( ! noveltool_verify_checksum( $temp_zip, $expected_checksum ) ) {
                        @unlink( $temp_zip );
                        update_option( 'noveltool_sample_images_download_status', 'failed' );
                        return array(
                            'success' => false,
                            'message' => __( 'Checksum verification failed. The downloaded file may be corrupted.', 'novel-game-plugin' ),
                        );
                    }
                } else {
                    error_log( 'NovelGamePlugin: Invalid checksum format in .sha256 file' );
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
        update_option( 'noveltool_sample_images_download_status', 'failed' );
        return array(
            'success' => false,
            'message' => $extract_result->get_error_message(),
        );
    }
    
    // 完了状態を記録
    update_option( 'noveltool_sample_images_download_status', 'completed' );
    update_option( 'noveltool_sample_images_downloaded', true );
    
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
        return new WP_REST_Response( $result, 400 );
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
    
    return new WP_REST_Response(
        array(
            'exists' => $exists,
            'status' => $status,
        ),
        200
    );
}
