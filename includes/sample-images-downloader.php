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
 * バックグラウンドジョブのステータス
 * 
 * @since 1.4.0
 */
define( 'NOVELTOOL_JOB_STATUS_PENDING', 'pending' );
define( 'NOVELTOOL_JOB_STATUS_IN_PROGRESS', 'in_progress' );
define( 'NOVELTOOL_JOB_STATUS_COMPLETED', 'completed' );
define( 'NOVELTOOL_JOB_STATUS_FAILED', 'failed' );

/**
 * バックグラウンドジョブのタイプ
 * 
 * @since 1.4.0
 */
define( 'NOVELTOOL_JOB_TYPE_DOWNLOAD', 'download' );
define( 'NOVELTOOL_JOB_TYPE_VERIFY', 'verify' );
define( 'NOVELTOOL_JOB_TYPE_EXTRACT', 'extract' );

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
 * リリースアセットからサンプル画像 ZIP を探す（後方互換性のため、最初の1つを返す）
 *
 * @param array $release_data リリースデータ
 * @return array|null アセット情報またはnull
 * @since 1.3.0
 */
function noveltool_find_sample_images_asset( $release_data ) {
    $assets = noveltool_find_all_sample_images_assets( $release_data );
    return ! empty( $assets ) ? $assets[0] : null;
}

/**
 * リリースアセットからすべてのサンプル画像 ZIP を取得（複数アセット対応）
 *
 * @param array $release_data リリースデータ
 * @return array アセット情報の配列（空配列の場合もあり）
 * @since 1.4.0
 */
function noveltool_find_all_sample_images_assets( $release_data ) {
    if ( ! isset( $release_data['assets'] ) || ! is_array( $release_data['assets'] ) ) {
        return array();
    }
    
    // サンプル画像アセット名のパターン
    // 例: novel-game-plugin-sample-images-1.3.0.zip, novel-game-plugin-sample-images-part1.zip, novel-game-plugin-sample-images.zip
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
        
        // SHA256チェックサムファイルはスキップ
        if ( substr( $name, -7 ) === '.sha256' ) {
            continue;
        }
        
        // 命名規約に基づいて候補を収集
        foreach ( $preferred_names as $index => $pattern ) {
            if ( strpos( $name, $pattern ) === 0 && substr( $name, -4 ) === '.zip' ) {
                $candidates[] = array(
                    'priority' => $index,
                    'asset'    => $asset,
                    'name'     => $name,
                );
                break;
            }
        }
    }
    
    if ( empty( $candidates ) ) {
        return array();
    }
    
    // 優先順位でソート、同じ優先順位の場合はアルファベット順
    usort(
        $candidates,
        function ( $a, $b ) {
            if ( $a['priority'] !== $b['priority'] ) {
                return $a['priority'] - $b['priority'];
            }
            return strcmp( $a['name'], $b['name'] );
        }
    );
    
    // アセット情報のみを返す
    return array_map(
        function ( $candidate ) {
            return $candidate['asset'];
        },
        $candidates
    );
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
 * unzip コマンドの利用可否を堅牢に検出
 *
 * @return bool unzip コマンドが利用可能な場合 true
 * @since 1.4.0
 */
function noveltool_detect_unzip_command() {
    // 方法1: which コマンド（Unix/Linux）
    $output = array();
    $return_var = 0;
    @exec( 'which unzip 2>/dev/null', $output, $return_var );
    
    if ( $return_var === 0 && ! empty( $output ) ) {
        return true;
    }
    
    // 方法2: command -v（より汎用的、bashなど）
    $output = array();
    $return_var = 0;
    @exec( 'command -v unzip 2>/dev/null', $output, $return_var );
    
    if ( $return_var === 0 && ! empty( $output ) ) {
        return true;
    }
    
    // 方法3: unzip を直接実行してバージョン確認（Windows含む）
    $output = array();
    $return_var = 0;
    @exec( 'unzip -v 2>&1', $output, $return_var );
    
    // unzip -v は通常 0 を返すか、エラーでも出力がある
    if ( ! empty( $output ) ) {
        $output_str = implode( ' ', $output );
        // 長さ制限とサニタイズ（ログ用）
        $output_str = substr( $output_str, 0, 500 );
        
        // unzip のバージョン情報が含まれているか確認
        if ( stripos( $output_str, 'unzip' ) !== false || stripos( $output_str, 'info-zip' ) !== false ) {
            return true;
        }
    }
    
    return false;
}

/**
 * 実行環境の抽出能力を検出
 *
 * @return array 検出結果の配列
 * @since 1.4.0
 */
function noveltool_detect_extraction_capabilities() {
    $capabilities = array(
        'has_ziparchive'   => class_exists( 'ZipArchive' ),
        'has_exec'         => false,
        'has_unzip'        => false,
        'memory_limit'     => ini_get( 'memory_limit' ),
        'memory_limit_mb'  => 0,
        'recommended'      => 'standard',
    );
    
    // メモリ制限を MB 単位に変換
    $memory_str = $capabilities['memory_limit'];
    
    // -1 は無制限
    if ( $memory_str === '-1' ) {
        $capabilities['memory_limit_mb'] = -1;
    } elseif ( preg_match( '/^(\d+)(.)$/', $memory_str, $matches ) ) {
        $value = intval( $matches[1] );
        $unit = strtoupper( $matches[2] );
        
        switch ( $unit ) {
            case 'G':
                $capabilities['memory_limit_mb'] = $value * 1024;
                break;
            case 'M':
                $capabilities['memory_limit_mb'] = $value;
                break;
            case 'K':
                $capabilities['memory_limit_mb'] = $value / 1024;
                break;
        }
    } elseif ( is_numeric( $memory_str ) ) {
        // 単位なしの場合はバイト
        $capabilities['memory_limit_mb'] = intval( $memory_str ) / 1024 / 1024;
    }
    
    // exec の可否を安全にチェック
    if ( function_exists( 'exec' ) ) {
        $disabled = explode( ',', ini_get( 'disable_functions' ) );
        $disabled = array_map( 'trim', $disabled );
        
        if ( ! in_array( 'exec', $disabled, true ) ) {
            $capabilities['has_exec'] = true;
            
            // unzip コマンドの有無を堅牢にチェック
            $capabilities['has_unzip'] = noveltool_detect_unzip_command();
        }
    }
    
    // 推奨方式を決定
    if ( $capabilities['has_ziparchive'] && ( $capabilities['memory_limit_mb'] === -1 || $capabilities['memory_limit_mb'] >= 128 ) ) {
        $capabilities['recommended'] = 'streaming';
    } elseif ( $capabilities['has_unzip'] ) {
        $capabilities['recommended'] = 'unzip_command';
    } elseif ( $capabilities['has_ziparchive'] ) {
        $capabilities['recommended'] = 'standard';
    } else {
        $capabilities['recommended'] = 'none';
    }
    
    return $capabilities;
}

/**
 * ストリームから直接ファイルへチャンク書き込み（メモリ効率重視）
 *
 * @param resource $stream 読み込みストリーム
 * @param string   $target_path 書き込み先ファイルパス
 * @param int      $chunk_size チャンクサイズ（バイト）
 * @param WP_Filesystem_Base $wp_filesystem WordPress Filesystem オブジェクト
 * @return bool|WP_Error 成功した場合true、失敗した場合WP_Error
 * @since 1.4.0
 */
function noveltool_stream_write_file( $stream, $target_path, $chunk_size, $wp_filesystem ) {
    // WP_Filesystem が direct method の場合は PHP のファイルハンドルで直接書き込み
    if ( $wp_filesystem->method === 'direct' ) {
        $fp = @fopen( $target_path, 'wb' );
        if ( false === $fp ) {
            return new WP_Error(
                'file_open_error',
                sprintf(
                    /* translators: %s: file path */
                    __( 'Failed to open file for writing: %s', 'novel-game-plugin' ),
                    $target_path
                )
            );
        }
        
        while ( ! feof( $stream ) ) {
            $chunk = fread( $stream, $chunk_size );
            if ( false === $chunk ) {
                fclose( $fp );
                @unlink( $target_path );
                return new WP_Error(
                    'read_error',
                    __( 'Failed to read from stream.', 'novel-game-plugin' )
                );
            }
            
            $written = fwrite( $fp, $chunk );
            if ( false === $written || $written !== strlen( $chunk ) ) {
                fclose( $fp );
                @unlink( $target_path );
                return new WP_Error(
                    'write_error',
                    __( 'Failed to write chunk to file.', 'novel-game-plugin' )
                );
            }
        }
        
        fclose( $fp );
        @chmod( $target_path, FS_CHMOD_FILE );
        return true;
    }
    
    // WP_Filesystem が FTP/SSH 等のリモートメソッドの場合は一時ファイル経由
    $temp_file = wp_tempnam( basename( $target_path ) );
    if ( ! $temp_file ) {
        return new WP_Error(
            'temp_file_error',
            __( 'Failed to create temporary file.', 'novel-game-plugin' )
        );
    }
    
    $fp = @fopen( $temp_file, 'wb' );
    if ( false === $fp ) {
        @unlink( $temp_file );
        return new WP_Error(
            'temp_file_open_error',
            __( 'Failed to open temporary file for writing.', 'novel-game-plugin' )
        );
    }
    
    while ( ! feof( $stream ) ) {
        $chunk = fread( $stream, $chunk_size );
        if ( false === $chunk ) {
            fclose( $fp );
            @unlink( $temp_file );
            return new WP_Error(
                'read_error',
                __( 'Failed to read from stream.', 'novel-game-plugin' )
            );
        }
        
        $written = fwrite( $fp, $chunk );
        if ( false === $written || $written !== strlen( $chunk ) ) {
            fclose( $fp );
            @unlink( $temp_file );
            return new WP_Error(
                'write_error',
                __( 'Failed to write chunk to temporary file.', 'novel-game-plugin' )
            );
        }
    }
    
    fclose( $fp );
    
    // 一時ファイルを最終的な場所に移動（複数の方法を試行）
    $move_success = false;
    
    // 方法1: WP_Filesystem の move() メソッド
    if ( method_exists( $wp_filesystem, 'move' ) ) {
        $move_result = $wp_filesystem->move( $temp_file, $target_path, true );
        if ( $move_result ) {
            $move_success = true;
        }
    }
    
    // 方法2: copy() + unlink() フォールバック
    if ( ! $move_success && method_exists( $wp_filesystem, 'copy' ) ) {
        if ( $wp_filesystem->copy( $temp_file, $target_path, true, FS_CHMOD_FILE ) ) {
            @unlink( $temp_file );
            $move_success = true;
        }
    }
    
    // 方法3: PHP の rename() フォールバック（direct method 限定）
    if ( ! $move_success && isset( $wp_filesystem->method ) && $wp_filesystem->method === 'direct' ) {
        if ( @rename( $temp_file, $target_path ) ) {
            @chmod( $target_path, FS_CHMOD_FILE );
            $move_success = true;
        }
    }
    
    // すべての方法が失敗した場合
    if ( ! $move_success ) {
        @unlink( $temp_file );
        return new WP_Error(
            'move_error',
            sprintf(
                /* translators: %s: file path */
                __( 'Failed to move temporary file to destination: %s', 'novel-game-plugin' ),
                $target_path
            )
        );
    }
    
    return true;
}

/**
 * ZIP ファイルをストリーミング展開（メモリ効率重視）
 *
 * @param string $zip_file ZIPファイルのパス
 * @param string $destination 展開先ディレクトリ
 * @return bool|WP_Error 成功した場合true、失敗した場合WP_Error
 * @since 1.4.0
 */
function noveltool_extract_zip_streaming( $zip_file, $destination ) {
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
    
    // 展開先ディレクトリを作成
    if ( ! $wp_filesystem->is_dir( $destination ) ) {
        if ( ! $wp_filesystem->mkdir( $destination, FS_CHMOD_DIR ) ) {
            return new WP_Error(
                'mkdir_error',
                __( 'Could not create destination directory.', 'novel-game-plugin' )
            );
        }
    }
    
    $capabilities = noveltool_detect_extraction_capabilities();
    
    // ZipArchive を使用したストリーミング抽出
    if ( $capabilities['has_ziparchive'] ) {
        $zip = new ZipArchive();
        $open_result = $zip->open( $zip_file );
        
        if ( true !== $open_result ) {
            return new WP_Error(
                'zip_open_error',
                __( 'Failed to open ZIP file.', 'novel-game-plugin' )
            );
        }
        
        // ファイルを1つずつストリーミング展開
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $stat = $zip->statIndex( $i );
            if ( false === $stat ) {
                continue;
            }
            
            $filename = $stat['name'];
            
            // ディレクトリトラバーサル対策
            $clean_path = str_replace( array( '\\', "\0" ), '', $filename );
            if ( strpos( $clean_path, '..' ) !== false ) {
                continue;
            }
            
            $target_path = $destination . '/' . ltrim( $clean_path, '/' );
            
            // ディレクトリの場合
            if ( substr( $filename, -1 ) === '/' ) {
                if ( ! $wp_filesystem->is_dir( $target_path ) ) {
                    $wp_filesystem->mkdir( $target_path, FS_CHMOD_DIR );
                }
                continue;
            }
            
            // ファイルの親ディレクトリを作成
            $target_dir = dirname( $target_path );
            if ( ! $wp_filesystem->is_dir( $target_dir ) ) {
                $wp_filesystem->mkdir( $target_dir, FS_CHMOD_DIR, true );
            }
            
            // 正規化後のパスが destination 内にあることを確認（親ディレクトリ作成後）
            $real_target = realpath( $target_dir );
            $real_destination = realpath( $destination );
            if ( $real_target === false || $real_destination === false || strpos( $real_target, $real_destination ) !== 0 ) {
                error_log( "NovelGamePlugin: Skipping file outside destination: {$filename}" );
                continue;
            }
            
            // ストリーミング展開（チャンク読み込みでメモリ効率化）
            $stream = $zip->getStream( $filename );
            if ( false === $stream ) {
                $zip->close();
                return new WP_Error(
                    'stream_error',
                    sprintf(
                        /* translators: %s: file name */
                        __( 'Failed to extract file: %s', 'novel-game-plugin' ),
                        $filename
                    )
                );
            }
            
            // 真のストリーミング書き込み（チャンクごとに直接書き込み）
            $chunk_size = 8192; // 8KB
            $write_result = noveltool_stream_write_file( $stream, $target_path, $chunk_size, $wp_filesystem );
            
            fclose( $stream );
            
            if ( is_wp_error( $write_result ) ) {
                $zip->close();
                return $write_result;
            }
        }
        
        $zip->close();
        return true;
    }
    
    // フォールバック: unzip コマンド
    if ( $capabilities['has_unzip'] ) {
        $zip_file_escaped = escapeshellarg( $zip_file );
        $destination_escaped = escapeshellarg( $destination );
        
        $output = array();
        $return_var = 0;
        
        // unzip を実行（-o: 上書き, -q: サイレント）
        @exec( "unzip -o -q {$zip_file_escaped} -d {$destination_escaped} 2>&1", $output, $return_var );
        
        if ( $return_var !== 0 ) {
            // 詳細なエラー情報をログに記録（管理者向け）
            $output_str = implode( "\n", $output );
            error_log( sprintf(
                'NovelGamePlugin: unzip command failed with exit code %d. Output: %s',
                $return_var,
                substr( $output_str, 0, 1000 ) // 最大1000文字まで
            ) );
            
            // ユーザー向けには簡潔で非機密のメッセージを返す
            return new WP_Error(
                'unzip_error',
                __( 'Failed to extract ZIP using unzip command. Please check server logs or install PHP ZipArchive extension.', 'novel-game-plugin' )
            );
        }
        
        return true;
    }
    
    // どの方法も利用できない場合
    return new WP_Error(
        'no_extraction_method',
        __( 'No extraction method available. Please install PHP ZipArchive extension or unzip command.', 'novel-game-plugin' )
    );
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
    
    // ストリーミング抽出を試みる（オプションで制御可能）
    $use_streaming = get_option( 'noveltool_use_streaming_extraction', true );
    
    if ( $use_streaming ) {
        $streaming_result = noveltool_extract_zip_streaming( $zip_file, $destination );
        if ( ! is_wp_error( $streaming_result ) ) {
            return true;
        }
        
        // ストリーミング失敗時は標準方式にフォールバック
        error_log( 'NovelGamePlugin: Streaming extraction failed, falling back to standard method: ' . $streaming_result->get_error_message() );
    }
    
    // ZIP を展開（標準方式）
    $result = unzip_file( $zip_file, $destination );
    
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    
    return true;
}

/**
 * バックグラウンドジョブを作成
 *
 * @param string $job_type ジョブタイプ
 * @param array  $job_data ジョブデータ
 * @return string ジョブID
 * @since 1.4.0
 */
function noveltool_create_background_job( $job_type, $job_data = array() ) {
    $job_id = uniqid( 'job_', true );
    
    $job = array(
        'id'         => $job_id,
        'type'       => $job_type,
        'status'     => NOVELTOOL_JOB_STATUS_PENDING,
        'data'       => $job_data,
        'created_at' => time(),
        'updated_at' => time(),
        'attempts'   => 0,
        'error'      => null,
    );
    
    $jobs = get_option( 'noveltool_background_jobs', array() );
    $jobs[ $job_id ] = $job;
    update_option( 'noveltool_background_jobs', $jobs, false );
    
    return $job_id;
}

/**
 * バックグラウンドジョブを取得
 *
 * @param string $job_id ジョブID
 * @return array|null ジョブ情報またはnull
 * @since 1.4.0
 */
function noveltool_get_background_job( $job_id ) {
    $jobs = get_option( 'noveltool_background_jobs', array() );
    return isset( $jobs[ $job_id ] ) ? $jobs[ $job_id ] : null;
}

/**
 * バックグラウンドジョブを更新
 *
 * @param string $job_id ジョブID
 * @param array  $updates 更新データ
 * @return bool 成功した場合true
 * @since 1.4.0
 */
function noveltool_update_background_job( $job_id, $updates ) {
    $jobs = get_option( 'noveltool_background_jobs', array() );
    
    if ( ! isset( $jobs[ $job_id ] ) ) {
        return false;
    }
    
    $jobs[ $job_id ] = array_merge( $jobs[ $job_id ], $updates );
    $jobs[ $job_id ]['updated_at'] = time();
    
    update_option( 'noveltool_background_jobs', $jobs, false );
    
    return true;
}

/**
 * バックグラウンドジョブを削除
 *
 * @param string $job_id ジョブID
 * @return bool 成功した場合true
 * @since 1.4.0
 */
function noveltool_delete_background_job( $job_id ) {
    $jobs = get_option( 'noveltool_background_jobs', array() );
    
    if ( ! isset( $jobs[ $job_id ] ) ) {
        return false;
    }
    
    unset( $jobs[ $job_id ] );
    update_option( 'noveltool_background_jobs', $jobs, false );
    
    return true;
}

/**
 * バックグラウンドジョブをスケジュール
 *
 * @param string $job_id ジョブID
 * @return bool 成功した場合true
 * @since 1.4.0
 */
function noveltool_schedule_background_job( $job_id ) {
    // 既にスケジュール済みかチェック
    $timestamp = wp_next_scheduled( 'noveltool_process_background_job', array( $job_id ) );
    
    if ( $timestamp ) {
        return true;
    }
    
    // フィルターで外部スケジューラ（Action Scheduler等）への切替を可能に
    $use_custom_scheduler = apply_filters( 'noveltool_use_custom_job_scheduler', false, $job_id );
    
    if ( $use_custom_scheduler ) {
        /**
         * カスタムジョブスケジューラのフック
         * 
         * Action Scheduler 等を使う場合はここでスケジュール
         * 
         * @param string $job_id ジョブID
         * @since 1.4.0
         */
        do_action( 'noveltool_schedule_custom_job', $job_id );
        return true;
    }
    
    // デフォルトは WP Cron を使用
    return wp_schedule_single_event( time(), 'noveltool_process_background_job', array( $job_id ) );
}

/**
 * ジョブログにエントリを追加（デバッグ・監査用）
 *
 * @param array $log_entry ログエントリ
 * @since 1.4.0
 */
function noveltool_append_job_log( $log_entry ) {
    $log = get_option( 'noveltool_job_log', array() );
    
    // ログの最大サイズを制限（最新50件まで）
    if ( count( $log ) >= 50 ) {
        array_shift( $log );
    }
    
    $log[] = $log_entry;
    update_option( 'noveltool_job_log', $log, false );
}

/**
 * 完了したジョブをクリーンアップ
 *
 * @since 1.4.0
 */
function noveltool_cleanup_completed_jobs() {
    $jobs = get_option( 'noveltool_background_jobs', array() );
    $current_time = time();
    $retention_period = 3600; // 1時間
    
    foreach ( $jobs as $job_id => $job ) {
        // 完了または失敗したジョブで、1時間以上経過しているものを削除
        if ( in_array( $job['status'], array( NOVELTOOL_JOB_STATUS_COMPLETED, NOVELTOOL_JOB_STATUS_FAILED ), true ) ) {
            $updated_at = isset( $job['updated_at'] ) ? $job['updated_at'] : 0;
            if ( $current_time - $updated_at > $retention_period ) {
                // ログに記録してから削除
                $log_entry = array(
                    'type'       => 'job_cleaned',
                    'job_id'     => $job_id,
                    'job_status' => $job['status'],
                    'cleaned_at' => $current_time,
                );
                noveltool_append_job_log( $log_entry );
                
                unset( $jobs[ $job_id ] );
            }
        }
    }
    
    update_option( 'noveltool_background_jobs', $jobs, false );
}

/**
 * バックグラウンドジョブを処理
 *
 * @param string $job_id ジョブID
 * @since 1.4.0
 */
function noveltool_process_background_job( $job_id ) {
    $job = noveltool_get_background_job( $job_id );
    
    if ( ! $job ) {
        error_log( "NovelGamePlugin: Job not found: {$job_id}" );
        return;
    }
    
    // ジョブをin_progressに更新
    noveltool_update_background_job(
        $job_id,
        array(
            'status'   => NOVELTOOL_JOB_STATUS_IN_PROGRESS,
            'attempts' => $job['attempts'] + 1,
        )
    );
    
    $result = null;
    
    // ジョブタイプに応じて処理
    switch ( $job['type'] ) {
        case NOVELTOOL_JOB_TYPE_DOWNLOAD:
            $result = noveltool_job_download_sample_images( $job );
            break;
            
        case NOVELTOOL_JOB_TYPE_VERIFY:
            $result = noveltool_job_verify_sample_images( $job );
            break;
            
        case NOVELTOOL_JOB_TYPE_EXTRACT:
            $result = noveltool_job_extract_sample_images( $job );
            break;
            
        default:
            $result = new WP_Error( 'invalid_job_type', 'Invalid job type' );
    }
    
    // 結果に応じてジョブを更新
    if ( is_wp_error( $result ) ) {
        noveltool_update_background_job(
            $job_id,
            array(
                'status' => NOVELTOOL_JOB_STATUS_FAILED,
                'error'  => array(
                    'code'    => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ),
            )
        );
        
        error_log( "NovelGamePlugin: Job failed ({$job_id}): " . $result->get_error_message() );
    } else {
        noveltool_update_background_job(
            $job_id,
            array(
                'status' => NOVELTOOL_JOB_STATUS_COMPLETED,
                'result' => $result,
            )
        );
    }
}
add_action( 'noveltool_process_background_job', 'noveltool_process_background_job' );

/**
 * ダウンロードジョブを処理
 *
 * @param array $job ジョブ情報
 * @return array|WP_Error 結果またはエラー
 * @since 1.4.0
 */
function noveltool_job_download_sample_images( $job ) {
    $download_url = isset( $job['data']['download_url'] ) ? $job['data']['download_url'] : '';
    
    if ( empty( $download_url ) ) {
        return new WP_Error( 'missing_url', 'Download URL is missing' );
    }
    
    $temp_zip = noveltool_download_sample_images_zip( $download_url );
    
    if ( is_wp_error( $temp_zip ) ) {
        return $temp_zip;
    }
    
    return array( 'temp_file' => $temp_zip );
}

/**
 * 検証ジョブを処理
 *
 * @param array $job ジョブ情報
 * @return array|WP_Error 結果またはエラー
 * @since 1.4.0
 */
function noveltool_job_verify_sample_images( $job ) {
    $temp_file = isset( $job['data']['temp_file'] ) ? $job['data']['temp_file'] : '';
    $checksum = isset( $job['data']['checksum'] ) ? $job['data']['checksum'] : '';
    
    if ( empty( $temp_file ) ) {
        return new WP_Error( 'missing_file', 'Temporary file path is missing' );
    }
    
    if ( ! file_exists( $temp_file ) ) {
        return new WP_Error( 'file_not_found', 'Temporary file not found' );
    }
    
    // チェックサムがある場合は検証
    if ( ! empty( $checksum ) ) {
        if ( ! noveltool_verify_checksum( $temp_file, $checksum ) ) {
            return new WP_Error( 'checksum_failed', 'Checksum verification failed' );
        }
    }
    
    return array( 'verified' => true );
}

/**
 * 抽出ジョブを処理
 *
 * @param array $job ジョブ情報
 * @return array|WP_Error 結果またはエラー
 * @since 1.4.0
 */
function noveltool_job_extract_sample_images( $job ) {
    $temp_file = isset( $job['data']['temp_file'] ) ? $job['data']['temp_file'] : '';
    
    if ( empty( $temp_file ) ) {
        return new WP_Error( 'missing_file', 'Temporary file path is missing' );
    }
    
    if ( ! file_exists( $temp_file ) ) {
        return new WP_Error( 'file_not_found', 'Temporary file not found' );
    }
    
    $destination = NOVEL_GAME_PLUGIN_PATH . 'assets/sample-images';
    $result = noveltool_extract_zip( $temp_file, $destination );
    
    // 一時ファイルを削除
    @unlink( $temp_file );
    
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    
    return array( 'extracted' => true );
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
 * @param array  $job_info ジョブ情報（オプション）
 * @since 1.3.0
 */
function noveltool_update_download_status( $status, $error_message = '', $error_code = '', $error_stage = '', $error_meta = array(), $job_info = array() ) {
    $timestamp = time();
    
    // ステータス値のバリデーション
    $valid_statuses = array( 'not_started', 'in_progress', 'completed', 'failed' );
    if ( ! in_array( $status, $valid_statuses, true ) ) {
        error_log( "NovelGamePlugin: Invalid status value: {$status}" );
        $status = 'failed';
    }
    
    $status_data = array(
        'status'    => sanitize_text_field( $status ),
        'timestamp' => intval( $timestamp ),
    );
    
    // ジョブ情報を追加（バックグラウンド処理の場合）
    if ( ! empty( $job_info ) && is_array( $job_info ) ) {
        if ( isset( $job_info['job_id'] ) ) {
            $status_data['job_id'] = sanitize_text_field( $job_info['job_id'] );
        }
        if ( isset( $job_info['progress'] ) ) {
            $progress = intval( $job_info['progress'] );
            // 進捗値を 0-100 の範囲に制限
            $status_data['progress'] = max( 0, min( 100, $progress ) );
        }
        if ( isset( $job_info['current_step'] ) ) {
            $valid_steps = array( 'download', 'verify', 'extract' );
            $step = sanitize_text_field( $job_info['current_step'] );
            $status_data['current_step'] = in_array( $step, $valid_steps, true ) ? $step : '';
        }
        if ( isset( $job_info['use_background'] ) ) {
            $status_data['use_background'] = (bool) $job_info['use_background'];
        }
    }
    
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
 * サンプル画像ダウンロードをバックグラウンドで実行
 *
 * @param array $release_data リリースデータ
 * @param array $asset サンプル画像アセット
 * @param string $checksum チェックサム（オプション）
 * @return array 結果配列
 * @since 1.4.0
 */
function noveltool_perform_sample_images_download_background( $release_data, $asset, $checksum = '' ) {
    $download_url = $asset['browser_download_url'];
    
    // ダウンロードジョブを作成
    $download_job_id = noveltool_create_background_job(
        NOVELTOOL_JOB_TYPE_DOWNLOAD,
        array( 'download_url' => $download_url )
    );
    
    // ステータスを更新
    noveltool_update_download_status(
        'in_progress',
        '',
        '',
        '',
        array(),
        array(
            'job_id'         => $download_job_id,
            'progress'       => 10,
            'current_step'   => 'download',
            'use_background' => true,
        )
    );
    
    // ダウンロードジョブをスケジュール
    noveltool_schedule_background_job( $download_job_id );
    
    // チェーンジョブを登録（ダウンロード完了後に実行）
    wp_schedule_single_event(
        time() + 10,
        'noveltool_check_background_job_chain',
        array( $download_job_id, $checksum )
    );
    
    return array(
        'success' => true,
        'message' => __( 'Download started in background. Please wait...', 'novel-game-plugin' ),
        'job_id'  => $download_job_id,
    );
}

/**
 * バックグラウンドジョブチェーンをチェック
 *
 * @param string $previous_job_id 前のジョブID
 * @param string $checksum チェックサム（オプション）
 * @since 1.4.0
 */
function noveltool_check_background_job_chain( $previous_job_id, $checksum = '' ) {
    $job = noveltool_get_background_job( $previous_job_id );
    
    if ( ! $job ) {
        error_log( "NovelGamePlugin: Previous job not found: {$previous_job_id}" );
        noveltool_update_download_status( 'failed', 'Previous job not found', 'ERR-JOB-NOTFOUND', 'background' );
        delete_option( 'noveltool_sample_images_download_lock' );
        return;
    }
    
    // ジョブが完了していない場合は再スケジュール
    if ( $job['status'] !== NOVELTOOL_JOB_STATUS_COMPLETED ) {
        if ( $job['status'] === NOVELTOOL_JOB_STATUS_FAILED ) {
            // 失敗した場合、一時ファイルをクリーンアップ
            $result = isset( $job['result'] ) ? $job['result'] : array();
            $temp_file = isset( $result['temp_file'] ) ? $result['temp_file'] : '';
            if ( ! empty( $temp_file ) && file_exists( $temp_file ) ) {
                @unlink( $temp_file );
            }
            
            $error = isset( $job['error'] ) ? $job['error'] : array( 'message' => 'Unknown error' );
            noveltool_update_download_status(
                'failed',
                isset( $error['message'] ) ? $error['message'] : 'Job failed',
                isset( $error['code'] ) ? $error['code'] : 'ERR-JOB-FAILED',
                'background'
            );
            delete_option( 'noveltool_sample_images_download_lock' );
            noveltool_delete_background_job( $previous_job_id );
            return;
        }
        
        // まだ完了していない場合は10秒後に再チェック（最大30回 = 5分）
        $attempts = isset( $job['attempts'] ) ? intval( $job['attempts'] ) : 0;
        if ( $attempts >= 30 ) {
            // タイムアウト
            noveltool_update_download_status(
                'failed',
                __( 'Job timeout: The job took too long to complete.', 'novel-game-plugin' ),
                'ERR-JOB-TIMEOUT',
                'background'
            );
            delete_option( 'noveltool_sample_images_download_lock' );
            noveltool_delete_background_job( $previous_job_id );
            return;
        }
        
        // 再チェックをスケジュール
        wp_schedule_single_event(
            time() + 10,
            'noveltool_check_background_job_chain',
            array( $previous_job_id, $checksum )
        );
        return;
    }
    
    // ダウンロードジョブ完了 - 次は検証ジョブ
    $result = isset( $job['result'] ) ? $job['result'] : array();
    $temp_file = isset( $result['temp_file'] ) ? $result['temp_file'] : '';
    
    if ( empty( $temp_file ) ) {
        noveltool_update_download_status( 'failed', 'Temporary file not found', 'ERR-TEMPFILE', 'background' );
        delete_option( 'noveltool_sample_images_download_lock' );
        noveltool_delete_background_job( $previous_job_id );
        return;
    }
    
    // 検証ジョブを作成
    if ( ! empty( $checksum ) ) {
        $verify_job_id = noveltool_create_background_job(
            NOVELTOOL_JOB_TYPE_VERIFY,
            array(
                'temp_file' => $temp_file,
                'checksum'  => $checksum,
            )
        );
        
        noveltool_update_download_status(
            'in_progress',
            '',
            '',
            '',
            array(),
            array(
                'job_id'       => $verify_job_id,
                'progress'     => 50,
                'current_step' => 'verify',
            )
        );
        
        noveltool_schedule_background_job( $verify_job_id );
        
        // チェーンジョブを登録（検証完了後に抽出）
        wp_schedule_single_event(
            time() + 10,
            'noveltool_check_background_job_verify',
            array( $verify_job_id, $temp_file )
        );
        
        noveltool_delete_background_job( $previous_job_id );
    } else {
        // チェックサムがない場合は直接抽出へ
        noveltool_schedule_extract_job( $temp_file );
        noveltool_delete_background_job( $previous_job_id );
    }
}
add_action( 'noveltool_check_background_job_chain', 'noveltool_check_background_job_chain', 10, 2 );

/**
 * 検証ジョブチェック
 *
 * @param string $verify_job_id 検証ジョブID
 * @param string $temp_file 一時ファイル
 * @since 1.4.0
 */
function noveltool_check_background_job_verify( $verify_job_id, $temp_file ) {
    $job = noveltool_get_background_job( $verify_job_id );
    
    if ( ! $job ) {
        noveltool_update_download_status( 'failed', 'Verify job not found', 'ERR-JOB-NOTFOUND', 'background' );
        delete_option( 'noveltool_sample_images_download_lock' );
        return;
    }
    
    if ( $job['status'] !== NOVELTOOL_JOB_STATUS_COMPLETED ) {
        if ( $job['status'] === NOVELTOOL_JOB_STATUS_FAILED ) {
            $error = isset( $job['error'] ) ? $job['error'] : array( 'message' => 'Unknown error' );
            noveltool_update_download_status(
                'failed',
                isset( $error['message'] ) ? $error['message'] : 'Verification failed',
                isset( $error['code'] ) ? $error['code'] : 'ERR-VERIFY-FAILED',
                'verify_checksum'
            );
            delete_option( 'noveltool_sample_images_download_lock' );
            noveltool_delete_background_job( $verify_job_id );
            @unlink( $temp_file );
            return;
        }
        
        wp_schedule_single_event(
            time() + 10,
            'noveltool_check_background_job_verify',
            array( $verify_job_id, $temp_file )
        );
        return;
    }
    
    // 検証完了 - 抽出ジョブへ
    noveltool_schedule_extract_job( $temp_file );
    noveltool_delete_background_job( $verify_job_id );
}
add_action( 'noveltool_check_background_job_verify', 'noveltool_check_background_job_verify', 10, 2 );

/**
 * 抽出ジョブをスケジュール
 *
 * @param string $temp_file 一時ファイル
 * @since 1.4.0
 */
function noveltool_schedule_extract_job( $temp_file ) {
    $extract_job_id = noveltool_create_background_job(
        NOVELTOOL_JOB_TYPE_EXTRACT,
        array( 'temp_file' => $temp_file )
    );
    
    noveltool_update_download_status(
        'in_progress',
        '',
        '',
        '',
        array(),
        array(
            'job_id'       => $extract_job_id,
            'progress'     => 80,
            'current_step' => 'extract',
        )
    );
    
    noveltool_schedule_background_job( $extract_job_id );
    
    wp_schedule_single_event(
        time() + 10,
        'noveltool_check_background_job_extract',
        array( $extract_job_id )
    );
}

/**
 * 抽出ジョブチェック
 *
 * @param string $extract_job_id 抽出ジョブID
 * @since 1.4.0
 */
function noveltool_check_background_job_extract( $extract_job_id ) {
    $job = noveltool_get_background_job( $extract_job_id );
    
    if ( ! $job ) {
        noveltool_update_download_status( 'failed', 'Extract job not found', 'ERR-JOB-NOTFOUND', 'background' );
        delete_option( 'noveltool_sample_images_download_lock' );
        return;
    }
    
    if ( $job['status'] !== NOVELTOOL_JOB_STATUS_COMPLETED ) {
        if ( $job['status'] === NOVELTOOL_JOB_STATUS_FAILED ) {
            $error = isset( $job['error'] ) ? $job['error'] : array( 'message' => 'Unknown error' );
            noveltool_update_download_status(
                'failed',
                isset( $error['message'] ) ? $error['message'] : 'Extraction failed',
                isset( $error['code'] ) ? $error['code'] : 'ERR-EXTRACT-FAILED',
                'extract'
            );
            delete_option( 'noveltool_sample_images_download_lock' );
            noveltool_delete_background_job( $extract_job_id );
            return;
        }
        
        wp_schedule_single_event(
            time() + 10,
            'noveltool_check_background_job_extract',
            array( $extract_job_id )
        );
        return;
    }
    
    // すべて完了
    noveltool_update_download_status( 'completed' );
    update_option( 'noveltool_sample_images_downloaded', true, false );
    delete_option( 'noveltool_sample_images_download_lock' );
    
    // 完了したジョブのログを保存（デバッグ・監査用）
    $job = noveltool_get_background_job( $extract_job_id );
    if ( $job ) {
        $log_entry = array(
            'type'         => 'job_completed',
            'job_id'       => $extract_job_id,
            'completed_at' => time(),
            'status'       => 'success',
        );
        noveltool_append_job_log( $log_entry );
    }
    
    // 現在のジョブを削除
    noveltool_delete_background_job( $extract_job_id );
    
    // 完了したジョブのクリーンアップ（自動削除がオプションで有効な場合）
    $auto_cleanup = get_option( 'noveltool_auto_cleanup_jobs', true );
    if ( $auto_cleanup ) {
        noveltool_cleanup_completed_jobs();
    }
}
add_action( 'noveltool_check_background_job_extract', 'noveltool_check_background_job_extract' );

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
    $expected_checksum = '';
    foreach ( $release_data['assets'] as $a ) {
        if ( isset( $a['name'] ) && $a['name'] === $asset_name . '.sha256' ) {
            $checksum_asset = $a;
            break;
        }
    }
    
    // チェックサムを取得（バックグラウンド処理用）
    if ( $checksum_asset ) {
        $checksum_response = wp_remote_get(
            $checksum_asset['browser_download_url'],
            array(
                'timeout' => 30,
                'headers' => array(
                    'User-Agent' => 'NovelGamePlugin/' . NOVEL_GAME_PLUGIN_VERSION . ' (+https://github.com/shokun0803/novel-game-plugin)',
                ),
            )
        );
        
        if ( ! is_wp_error( $checksum_response ) && 200 === wp_remote_retrieve_response_code( $checksum_response ) ) {
            $checksum_body = wp_remote_retrieve_body( $checksum_response );
            if ( preg_match( '/\b([a-f0-9]{64})\b/i', $checksum_body, $matches ) ) {
                $expected_checksum = $matches[1];
            }
        }
    }
    
    // 環境検出
    $capabilities = noveltool_detect_extraction_capabilities();
    
    // 環境が不十分な場合は早期失敗
    if ( 'none' === $capabilities['recommended'] ) {
        $error_msg = __( 'Server environment does not support ZIP extraction. Please install PHP ZipArchive extension or unzip command.', 'novel-game-plugin' );
        noveltool_update_download_status( 'failed', $error_msg, 'ERR-NO-EXT', 'environment_check' );
        delete_option( 'noveltool_sample_images_download_lock' );
        return array(
            'success' => false,
            'message' => $error_msg,
            'code'    => 'ERR-NO-EXT',
            'stage'   => 'environment_check',
        );
    }
    
    // メモリ不足の警告（ただし処理は続行）
    if ( $capabilities['memory_limit_mb'] > 0 && $capabilities['memory_limit_mb'] < 128 ) {
        error_log( sprintf(
            'NovelGamePlugin: Low memory limit detected: %s MB (recommended: 256 MB or higher)',
            $capabilities['memory_limit_mb']
        ) );
    }
    
    // バックグラウンド処理を使用するかどうかを判定
    $use_background = get_option( 'noveltool_use_background_processing', true );
    
    if ( $use_background ) {
        // バックグラウンド処理で実行
        $result = noveltool_perform_sample_images_download_background( $release_data, $asset, $expected_checksum );
        delete_option( 'noveltool_sample_images_download_lock' );
        return $result;
    }
    
    // 従来の同期処理
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
    
    // WP_Error が返された場合の処理
    if ( is_wp_error( $result ) ) {
        $error_code = $result->get_error_code();
        $error_message = $result->get_error_message();
        
        // 詳細なエラー情報をログに記録
        error_log( sprintf(
            'NovelGamePlugin: Sample images download failed with WP_Error - Code: %s, Message: %s',
            $error_code,
            $error_message
        ) );
        
        // ユーザー向けには簡潔で非機密のメッセージを返す
        $response = array(
            'success' => false,
            'message' => __( 'Sample images download failed. Please check server logs for details.', 'novel-game-plugin' ),
            'error'   => array(
                'code'      => sanitize_text_field( $error_code ),
                'message'   => __( 'An error occurred during download. Please try again.', 'novel-game-plugin' ),
                'stage'     => 'download',
                'timestamp' => time(),
            ),
        );
        
        return new WP_REST_Response( $response, 500 );
    }
    
    // 配列でない場合の保護
    if ( ! is_array( $result ) ) {
        error_log( 'NovelGamePlugin: noveltool_perform_sample_images_download() returned non-array result' );
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Invalid response format from download function.', 'novel-game-plugin' ),
            ),
            500
        );
    }
    
    // success キーの存在チェック
    if ( ! isset( $result['success'] ) ) {
        error_log( 'NovelGamePlugin: noveltool_perform_sample_images_download() returned array without success key' );
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'Invalid response format from download function.', 'novel-game-plugin' ),
            ),
            500
        );
    }
    
    if ( $result['success'] ) {
        return new WP_REST_Response( $result, 200 );
    } else {
        // 失敗時は詳細なエラー情報を含めて返す
        $error_data = get_option( 'noveltool_sample_images_download_error', null );
        
        // エラーコードとステージから適切なHTTPステータスを決定
        $http_status = 400; // デフォルト
        $error_code = isset( $result['code'] ) ? sanitize_text_field( $result['code'] ) : 'download_failed';
        $error_stage = isset( $result['stage'] ) ? sanitize_text_field( $result['stage'] ) : 'other';
        $error_message = isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : __( 'Download failed.', 'novel-game-plugin' );
        
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
            'message' => $error_message,
            'error'   => array(
                'code'      => $error_code,
                'message'   => $error_message,
                'stage'     => $error_stage,
                'timestamp' => is_array( $error_data ) && isset( $error_data['timestamp'] ) ? intval( $error_data['timestamp'] ) : time(),
            ),
        );
        
        // メタ情報があれば追加（非機密のみ）
        if ( is_array( $error_data ) && isset( $error_data['meta'] ) && is_array( $error_data['meta'] ) ) {
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
    
    // エラー情報があれば構造化して追加（非機密情報のみ）
    if ( ! empty( $error_data ) && is_array( $error_data ) ) {
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
    
    // バックグラウンドジョブをクリーンアップ
    delete_option( 'noveltool_background_jobs' );
    
    // スケジュール済みのすべてのイベントをキャンセル
    $events_to_cancel = array(
        'noveltool_process_background_job',
        'noveltool_check_background_job_chain',
        'noveltool_check_background_job_verify',
        'noveltool_check_background_job_extract',
    );
    
    foreach ( $events_to_cancel as $event ) {
        while ( $timestamp = wp_next_scheduled( $event ) ) {
            wp_unschedule_event( $timestamp, $event );
        }
    }
    
    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => __( 'Download status has been reset.', 'novel-game-plugin' ),
        ),
        200
    );
}
