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
    $sample_images_dir = noveltool_get_sample_images_directory();
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

    $part_candidates = array();
    $all_candidates = array();
    $single_candidates = array();

    foreach ( $candidates as $candidate ) {
        $candidate_name = isset( $candidate['name'] ) ? sanitize_file_name( $candidate['name'] ) : '';
        if ( '' === $candidate_name ) {
            continue;
        }

        if ( preg_match( '/-part\d+\.zip$/i', $candidate_name ) ) {
            $part_candidates[] = $candidate;
            continue;
        }

        if ( preg_match( '/-all\.zip$/i', $candidate_name ) ) {
            $all_candidates[] = $candidate;
            continue;
        }

        $single_candidates[] = $candidate;
    }

    // all.zip と part*.zip が同時にある場合は、part セット（2件以上）を優先して重複展開を防ぐ
    if ( count( $part_candidates ) >= 2 ) {
        return array_map(
            function ( $candidate ) {
                return $candidate['asset'];
            },
            $part_candidates
        );
    }

    // part が単独で all が存在する場合は all を優先（不完全な part セットを避ける）
    if ( 1 === count( $part_candidates ) && ! empty( $all_candidates ) ) {
        return array( $all_candidates[0]['asset'] );
    }

    if ( ! empty( $all_candidates ) ) {
        return array( $all_candidates[0]['asset'] );
    }

    if ( ! empty( $single_candidates ) ) {
        return array_map(
            function ( $candidate ) {
                return $candidate['asset'];
            },
            $single_candidates
        );
    }

    if ( ! empty( $part_candidates ) ) {
        return array_map(
            function ( $candidate ) {
                return $candidate['asset'];
            },
            $part_candidates
        );
    }
    
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
 * @param string $temp_file 一時ファイルパス（省略時は自動生成）
 * @param int    $expected_size 期待ファイルサイズ（バイト）
 * @return string|WP_Error 一時ファイルのパスまたはエラー
 * @since 1.3.0
 */
function noveltool_download_sample_images_zip( $download_url, $temp_file = '', $expected_size = 0 ) {
    $temp_file = is_string( $temp_file ) ? $temp_file : '';
    if ( '' === $temp_file ) {
        $temp_file = wp_tempnam( 'noveltool-sample-images.zip' );
    }
    
    if ( ! $temp_file ) {
        return new WP_Error(
            'tempfile_error',
            __( 'Failed to create temporary file.', 'novel-game-plugin' )
        );
    }

    $expected_size = max( 0, intval( $expected_size ) );
    if ( $expected_size > 0 ) {
        $chunk_result = noveltool_download_sample_images_zip_chunked( $download_url, $temp_file, $expected_size );
        if ( ! is_wp_error( $chunk_result ) ) {
            return $temp_file;
        }

        error_log( 'NovelGamePlugin: Chunked download failed, fallback to stream mode: ' . $chunk_result->get_error_message() );
        if ( file_exists( $temp_file ) ) {
            @unlink( $temp_file );
        }
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
 * サンプル画像 ZIP を Range チャンクでダウンロード
 *
 * @param string $download_url ダウンロードURL
 * @param string $temp_file 一時ファイルパス
 * @param int    $expected_size 期待ファイルサイズ（バイト）
 * @return true|WP_Error 成功時 true / 失敗時 WP_Error
 * @since 1.5.0
 */
function noveltool_download_sample_images_zip_chunked( $download_url, $temp_file, $expected_size ) {
    $expected_size = max( 1, intval( $expected_size ) );
    $chunk_size = intval( apply_filters( 'noveltool_sample_images_chunk_size', 2 * 1024 * 1024 ) );
    $chunk_size = max( 256 * 1024, min( 8 * 1024 * 1024, $chunk_size ) );

    $init_handle = @fopen( $temp_file, 'wb' );
    if ( ! $init_handle ) {
        return new WP_Error(
            'tempfile_open_failed',
            __( 'Failed to initialize temporary file for chunked download.', 'novel-game-plugin' )
        );
    }
    fclose( $init_handle );

    $offset = 0;
    $max_loops = intval( ceil( $expected_size / $chunk_size ) ) + 5;
    $loop_count = 0;

    while ( $offset < $expected_size ) {
        $loop_count++;
        if ( $loop_count > $max_loops ) {
            return new WP_Error(
                'chunk_loop_guard',
                __( 'Chunked download loop exceeded safety limit.', 'novel-game-plugin' )
            );
        }

        $range_end = min( $offset + $chunk_size - 1, $expected_size - 1 );
        $response = wp_remote_get(
            $download_url,
            array(
                'timeout'     => 120,
                'redirection' => 5,
                'decompress'  => false,
                'headers'     => array(
                    'User-Agent' => 'NovelGamePlugin/' . NOVEL_GAME_PLUGIN_VERSION . ' (+https://github.com/shokun0803/novel-game-plugin)',
                    'Range'      => 'bytes=' . $offset . '-' . $range_end,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 206 !== $status_code && ! ( 0 === $offset && 200 === $status_code ) ) {
            return new WP_Error(
                'chunk_http_error',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'Chunked download failed. HTTP status code: %d', 'novel-game-plugin' ),
                    intval( $status_code )
                )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! is_string( $body ) || '' === $body ) {
            return new WP_Error(
                'chunk_empty_body',
                __( 'Chunked download returned empty response body.', 'novel-game-plugin' )
            );
        }

        $written = @file_put_contents( $temp_file, $body, FILE_APPEND );
        if ( false === $written ) {
            return new WP_Error(
                'chunk_write_failed',
                __( 'Failed to append chunk to temporary file.', 'novel-game-plugin' )
            );
        }

        $written = intval( $written );
        if ( $written <= 0 ) {
            return new WP_Error(
                'chunk_zero_write',
                __( 'Chunk write returned zero bytes.', 'novel-game-plugin' )
            );
        }

        $offset += $written;

        // サーバーが Range 未対応で初回 200 を返した場合は全体取得済みとして終了
        if ( 200 === $status_code ) {
            break;
        }
    }

    $final_size = @filesize( $temp_file );
    if ( false === $final_size || intval( $final_size ) <= 0 ) {
        return new WP_Error(
            'chunk_invalid_size',
            __( 'Chunked download file size is invalid.', 'novel-game-plugin' )
        );
    }

    if ( intval( $final_size ) < intval( $expected_size ) ) {
        return new WP_Error(
            'chunk_incomplete',
            __( 'Chunked download completed but file is smaller than expected.', 'novel-game-plugin' )
        );
    }

    return true;
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
 * unzip コマンドで ZIP を展開
 *
 * @param string $zip_file ZIPファイルのパス
 * @param string $destination 展開先ディレクトリ
 * @return true|WP_Error 成功時 true / 失敗時 WP_Error
 * @since 1.5.0
 */
function noveltool_extract_zip_with_unzip_command( $zip_file, $destination ) {
    $zip_file_escaped = escapeshellarg( $zip_file );
    $destination_escaped = escapeshellarg( $destination );

    $output = array();
    $return_var = 0;

    @exec( "unzip -o -q {$zip_file_escaped} -d {$destination_escaped} 2>&1", $output, $return_var );

    if ( 0 !== $return_var ) {
        $output_str = implode( "\n", $output );
        error_log(
            sprintf(
                'NovelGamePlugin: unzip command failed with exit code %d. Output: %s',
                $return_var,
                substr( $output_str, 0, 1000 )
            )
        );

        return new WP_Error(
            'unzip_error',
            __( 'Failed to extract ZIP using unzip command. Please check server logs or install PHP ZipArchive extension.', 'novel-game-plugin' ),
            array( 'unzip_exit_code' => $return_var )
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
        if ( ! wp_mkdir_p( $destination ) ) {
            return new WP_Error(
                'mkdir_error',
                __( 'Could not create destination directory.', 'novel-game-plugin' )
            );
        }
    }
    
    $capabilities = noveltool_detect_extraction_capabilities();

    // 低メモリ環境では unzip コマンドを優先してメモリ圧迫を回避
    if ( $capabilities['has_unzip'] && isset( $capabilities['recommended'] ) && 'unzip_command' === $capabilities['recommended'] ) {
        return noveltool_extract_zip_with_unzip_command( $zip_file, $destination );
    }
    
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
        return noveltool_extract_zip_with_unzip_command( $zip_file, $destination );
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
    
    // 親ディレクトリの書き込み権限をチェック（未作成ディレクトリにも対応）
    $parent_dir = dirname( $destination );
    $check_dir = $parent_dir;
    while ( ! $wp_filesystem->is_dir( $check_dir ) && dirname( $check_dir ) !== $check_dir ) {
        $check_dir = dirname( $check_dir );
    }

    if ( ! $wp_filesystem->is_writable( $check_dir ) ) {
        return new WP_Error(
            'permission_error',
            sprintf(
                /* translators: %s: directory path */
                __( 'Destination directory is not writable: %s', 'novel-game-plugin' ),
                $check_dir
            )
        );
    }
    
    // 展開先ディレクトリを作成
    if ( ! $wp_filesystem->is_dir( $destination ) ) {
        if ( ! wp_mkdir_p( $destination ) ) {
            return new WP_Error(
                'mkdir_error',
                __( 'Could not create destination directory.', 'novel-game-plugin' )
            );
        }
    }
    
    $capabilities = noveltool_detect_extraction_capabilities();

    // 互換性重視: 既定では WordPress 標準 unzip_file を優先し、必要時のみストリーミングを有効化する
    $use_streaming = get_option( 'noveltool_use_streaming_extraction', false );

    // 低メモリ環境では unzip コマンドを優先して展開
    if ( ! $use_streaming && $capabilities['has_unzip'] && 'unzip_command' === $capabilities['recommended'] ) {
        return noveltool_extract_zip_with_unzip_command( $zip_file, $destination );
    }
    
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
function noveltool_schedule_background_job( $job_id, $delay = 0 ) {
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
         * @param int $delay 遅延秒数
         * @since 1.4.0
         */
        do_action( 'noveltool_schedule_custom_job', $job_id, $delay );
        return true;
    }
    
    // デフォルトは WP Cron を使用
    $schedule_time = time() + absint( $delay );
    return wp_schedule_single_event( $schedule_time, 'noveltool_process_background_job', array( $job_id ) );
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

    $running_key = 'noveltool_job_running_' . md5( sanitize_text_field( $job_id ) );
    $running_ttl = ( isset( $job['type'] ) && NOVELTOOL_JOB_TYPE_DOWNLOAD === $job['type'] ) ? 1800 : 600;
    set_transient( $running_key, time(), $running_ttl );
    register_shutdown_function( 'noveltool_handle_background_job_shutdown', $job_id, $running_key );
    
    // ジョブをin_progressに更新
    noveltool_update_background_job(
        $job_id,
        array(
            'status'   => NOVELTOOL_JOB_STATUS_IN_PROGRESS,
            'attempts' => $job['attempts'] + 1,
        )
    );
    
    $result = null;
    
    try {
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
    } catch ( Throwable $throwable ) {
        $result = new WP_Error(
            'background_exception',
            __( 'Exception occurred during background job processing.', 'novel-game-plugin' ),
            array(
                'exception_class' => get_class( $throwable ),
                'exception_file'  => wp_normalize_path( $throwable->getFile() ),
                'exception_line'  => intval( $throwable->getLine() ),
                'message'         => wp_strip_all_tags( (string) $throwable->getMessage() ),
            )
        );

        error_log(
            sprintf(
                'NovelGamePlugin: Background exception job_id=%s type=%s class=%s message=%s file=%s line=%d',
                sanitize_text_field( $job_id ),
                isset( $job['type'] ) ? sanitize_text_field( $job['type'] ) : 'unknown',
                get_class( $throwable ),
                wp_strip_all_tags( (string) $throwable->getMessage() ),
                wp_normalize_path( $throwable->getFile() ),
                intval( $throwable->getLine() )
            )
        );
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
                    'detail'  => $result->get_error_data(),
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

    delete_transient( $running_key );
}
add_action( 'noveltool_process_background_job', 'noveltool_process_background_job' );

/**
 * バックグラウンドジョブの異常終了を検知して失敗へ遷移
 *
 * @param string $job_id ジョブID
 * @param string $running_key 実行中フラグのtransientキー
 * @since 1.5.0
 */
function noveltool_handle_background_job_shutdown( $job_id, $running_key ) {
    $fatal = error_get_last();
    if ( ! $fatal || ! in_array( $fatal['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
        return;
    }

    $job = noveltool_get_background_job( $job_id );
    if ( ! is_array( $job ) ) {
        delete_transient( $running_key );
        return;
    }

    if ( isset( $job['status'] ) && NOVELTOOL_JOB_STATUS_IN_PROGRESS === $job['status'] ) {
        $fatal_message = isset( $fatal['message'] ) ? wp_strip_all_tags( (string) $fatal['message'] ) : '';
        $fatal_file = isset( $fatal['file'] ) ? wp_strip_all_tags( (string) $fatal['file'] ) : '';
        $fatal_line = isset( $fatal['line'] ) ? intval( $fatal['line'] ) : 0;

        if ( ! empty( $fatal_file ) ) {
            $wp_content_dir = wp_normalize_path( WP_CONTENT_DIR );
            $normalized_fatal_file = wp_normalize_path( $fatal_file );
            if ( 0 === strpos( $normalized_fatal_file, $wp_content_dir ) ) {
                $fatal_file = ltrim( substr( $normalized_fatal_file, strlen( $wp_content_dir ) ), '/' );
            } else {
                $fatal_file = basename( $fatal_file );
            }
        }

        error_log(
            sprintf(
                'NovelGamePlugin: Background fatal job_id=%s message=%s file=%s line=%d',
                sanitize_text_field( $job_id ),
                $fatal_message,
                $fatal_file,
                $fatal_line
            )
        );

        noveltool_update_background_job(
            $job_id,
            array(
                'status' => NOVELTOOL_JOB_STATUS_FAILED,
                'error'  => array(
                    'code'    => 'background_fatal',
                    'message' => __( 'Background job terminated by fatal error.', 'novel-game-plugin' ),
                    'detail'  => array(
                        'fatal_message' => $fatal_message,
                        'fatal_file'    => $fatal_file,
                        'fatal_line'    => $fatal_line,
                    ),
                ),
            )
        );
    }

    delete_transient( $running_key );
}

/**
 * ダウンロードジョブを処理
 *
 * @param array $job ジョブ情報
 * @return array|WP_Error 結果またはエラー
 * @since 1.4.0
 */
function noveltool_job_download_sample_images( $job ) {
    $download_url = isset( $job['data']['download_url'] ) ? $job['data']['download_url'] : '';
    $expected_size = isset( $job['data']['size'] ) ? absint( $job['data']['size'] ) : 0;

    if ( ! function_exists( 'wp_tempnam' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    
    if ( empty( $download_url ) ) {
        return new WP_Error( 'missing_url', 'Download URL is missing' );
    }
    
    $temp_file = isset( $job['data']['temp_file'] ) ? $job['data']['temp_file'] : '';
    if ( empty( $temp_file ) ) {
        $temp_file = wp_tempnam( 'noveltool-sample-images.zip' );
        if ( ! $temp_file ) {
            return new WP_Error( 'tempfile_error', 'Failed to create temporary file.' );
        }

        $job_data = isset( $job['data'] ) && is_array( $job['data'] ) ? $job['data'] : array();
        $job_data['temp_file'] = $temp_file;
        if ( isset( $job['id'] ) ) {
            noveltool_update_background_job(
                $job['id'],
                array(
                    'data' => $job_data,
                )
            );
        }
    }

    $temp_zip = noveltool_download_sample_images_zip( $download_url, $temp_file, $expected_size );
    
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
    
    $destination = noveltool_get_sample_images_directory();
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
    
    $previous_status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    $status_data = is_array( $previous_status_data ) ? $previous_status_data : array();
    $status_data['status'] = sanitize_text_field( $status );
    $status_data['timestamp'] = intval( $timestamp );
    
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
        if ( isset( $job_info['job_ids'] ) && is_array( $job_info['job_ids'] ) ) {
            $status_data['job_ids'] = array_values(
                array_filter(
                    array_map( 'sanitize_text_field', $job_info['job_ids'] )
                )
            );
        }
        if ( isset( $job_info['total_assets'] ) ) {
            $status_data['total_assets'] = max( 0, intval( $job_info['total_assets'] ) );
        }
        if ( isset( $job_info['successful_jobs'] ) ) {
            $status_data['successful_jobs'] = max( 0, intval( $job_info['successful_jobs'] ) );
        }
        if ( isset( $job_info['failed_jobs'] ) ) {
            $status_data['failed_jobs'] = max( 0, intval( $job_info['failed_jobs'] ) );
        }
        if ( isset( $job_info['failed_assets'] ) && is_array( $job_info['failed_assets'] ) ) {
            $status_data['failed_assets'] = array();
            foreach ( $job_info['failed_assets'] as $failed_asset ) {
                if ( ! is_array( $failed_asset ) ) {
                    continue;
                }

                $status_data['failed_assets'][] = array(
                    'name'    => isset( $failed_asset['name'] ) ? sanitize_text_field( $failed_asset['name'] ) : 'unknown',
                    'message' => isset( $failed_asset['message'] ) ? sanitize_text_field( $failed_asset['message'] ) : '',
                    'reason'  => isset( $failed_asset['reason'] ) ? sanitize_text_field( $failed_asset['reason'] ) : '',
                );
            }
        }
        if ( isset( $job_info['total_files'] ) ) {
            $status_data['total_files'] = max( 0, intval( $job_info['total_files'] ) );
        }
        if ( isset( $job_info['downloaded_files'] ) ) {
            $status_data['downloaded_files'] = max( 0, intval( $job_info['downloaded_files'] ) );
        }
        if ( isset( $job_info['total_bytes'] ) ) {
            $status_data['total_bytes'] = max( 0, intval( $job_info['total_bytes'] ) );
        }
        if ( isset( $job_info['downloaded_bytes'] ) ) {
            $status_data['downloaded_bytes'] = max( 0, intval( $job_info['downloaded_bytes'] ) );
        }
        if ( isset( $job_info['destination_dir'] ) ) {
            $status_data['destination_dir'] = sanitize_text_field( $job_info['destination_dir'] );
        }
        if ( isset( $job_info['current_queue_index'] ) ) {
            $status_data['current_queue_index'] = max( 0, intval( $job_info['current_queue_index'] ) );
        }
        if ( isset( $job_info['queue_assets'] ) && is_array( $job_info['queue_assets'] ) ) {
            $status_data['queue_assets'] = array();
            foreach ( $job_info['queue_assets'] as $queue_asset ) {
                if ( ! is_array( $queue_asset ) ) {
                    continue;
                }

                $status_data['queue_assets'][] = array(
                    'name' => isset( $queue_asset['name'] ) ? sanitize_text_field( $queue_asset['name'] ) : '',
                    'url'  => isset( $queue_asset['url'] ) ? esc_url_raw( $queue_asset['url'] ) : '',
                    'size' => isset( $queue_asset['size'] ) ? max( 0, intval( $queue_asset['size'] ) ) : 0,
                    'checksum' => isset( $queue_asset['checksum'] ) ? sanitize_text_field( $queue_asset['checksum'] ) : '',
                );
            }
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
            $allowed_keys = array( 'http_code', 'stage_detail', 'retry_count', 'stuck_seconds', 'fatal_message', 'fatal_file', 'fatal_line', 'exception_class', 'exception_file', 'exception_line', 'unzip_exit_code' );
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
 * 順次キューから次のアセットダウンロードを開始
 *
 * @param array $status_data 現在のステータス配列
 * @param int   $user_id ユーザーID
 * @return bool 次ジョブを開始した場合 true
 * @since 1.5.0
 */
function noveltool_start_next_queued_asset_download( $status_data, $user_id = 0 ) {
    if ( ! is_array( $status_data ) || empty( $status_data['queue_assets'] ) || ! is_array( $status_data['queue_assets'] ) ) {
        return false;
    }

    $queue_assets = $status_data['queue_assets'];
    $current_index = isset( $status_data['current_queue_index'] ) ? intval( $status_data['current_queue_index'] ) : 0;
    $next_index = $current_index + 1;

    if ( ! isset( $queue_assets[ $next_index ] ) || ! is_array( $queue_assets[ $next_index ] ) ) {
        return false;
    }

    $next_asset = $queue_assets[ $next_index ];
    $download_url = isset( $next_asset['url'] ) ? esc_url_raw( $next_asset['url'] ) : '';
    if ( '' === $download_url ) {
        noveltool_update_download_status(
            'failed',
            __( '次のダウンロードURLが不正です。再度ダウンロードを実行してください。', 'novel-game-plugin' ),
            'ERR-ASSET-NEXT-INVALID',
            'background'
        );
        delete_option( 'noveltool_sample_images_download_lock' );
        return false;
    }

    $download_job_id = noveltool_create_background_job(
        NOVELTOOL_JOB_TYPE_DOWNLOAD,
        array(
            'download_url' => $download_url,
            'asset_name'   => isset( $next_asset['name'] ) ? sanitize_text_field( $next_asset['name'] ) : '',
            'asset_index'  => $next_index,
            'total_assets' => count( $queue_assets ),
            'size'         => isset( $next_asset['size'] ) ? max( 0, intval( $next_asset['size'] ) ) : 0,
            'checksum'     => isset( $next_asset['checksum'] ) ? sanitize_text_field( $next_asset['checksum'] ) : '',
            'user_id'      => intval( $user_id ),
        )
    );

    if ( ! is_string( $download_job_id ) || '' === $download_job_id ) {
        noveltool_update_download_status(
            'failed',
            __( '次のダウンロードジョブ作成に失敗しました。再度ダウンロードを実行してください。', 'novel-game-plugin' ),
            'ERR-NEXT-JOB-CREATE',
            'background'
        );
        delete_option( 'noveltool_sample_images_download_lock' );
        return false;
    }

    $total_files = isset( $status_data['total_files'] ) ? max( 1, intval( $status_data['total_files'] ) ) : count( $queue_assets );
    $downloaded_files = $next_index;
    $progress_base = 5 + intval( floor( ( $downloaded_files / $total_files ) * 40 ) );
    $progress_base = max( 5, min( 45, $progress_base ) );

    $total_bytes = isset( $status_data['total_bytes'] ) ? max( 0, intval( $status_data['total_bytes'] ) ) : 0;
    $downloaded_bytes_confirmed = 0;
    for ( $i = 0; $i < $next_index; $i++ ) {
        if ( isset( $queue_assets[ $i ]['size'] ) ) {
            $downloaded_bytes_confirmed += max( 0, intval( $queue_assets[ $i ]['size'] ) );
        }
    }

    noveltool_update_download_status(
        'in_progress',
        '',
        '',
        '',
        array(),
        array(
            'job_id'           => $download_job_id,
            'job_ids'          => array( $download_job_id ),
            'progress'         => $progress_base,
            'current_step'     => 'download',
            'use_background'   => true,
            'multi_asset'      => true,
            'queue_assets'     => $queue_assets,
            'current_queue_index' => $next_index,
            'total_files'      => $total_files,
            'downloaded_files' => $downloaded_files,
            'total_bytes'      => $total_bytes,
            'downloaded_bytes' => $downloaded_bytes_confirmed,
            'destination_dir'  => isset( $status_data['destination_dir'] ) ? $status_data['destination_dir'] : noveltool_get_sample_images_directory(),
            'total_assets'     => isset( $status_data['total_assets'] ) ? intval( $status_data['total_assets'] ) : $total_files,
            'successful_jobs'  => isset( $status_data['successful_jobs'] ) ? intval( $status_data['successful_jobs'] ) : $total_files,
            'failed_jobs'      => isset( $status_data['failed_jobs'] ) ? intval( $status_data['failed_jobs'] ) : 0,
            'failed_assets'    => isset( $status_data['failed_assets'] ) && is_array( $status_data['failed_assets'] ) ? $status_data['failed_assets'] : array(),
        )
    );

    noveltool_schedule_background_job( $download_job_id );

    $checksum = isset( $next_asset['checksum'] ) ? sanitize_text_field( $next_asset['checksum'] ) : '';
    wp_schedule_single_event(
        time() + 10,
        'noveltool_check_background_job_chain',
        array( $download_job_id, $checksum )
    );

    return true;
}

/**
 * ダウンロード進捗の実行時メトリクスを集計
 *
 * @param array $status_data ステータス配列
 * @return array 集計済みメトリクス
 * @since 1.5.0
 */
function noveltool_get_download_runtime_metrics( $status_data ) {
    if ( ! is_array( $status_data ) ) {
        return array();
    }

    $metrics = array();

    if ( isset( $status_data['destination_dir'] ) ) {
        $metrics['destination_dir'] = sanitize_text_field( $status_data['destination_dir'] );
    }

    $job_ids = array();
    if ( isset( $status_data['job_ids'] ) && is_array( $status_data['job_ids'] ) ) {
        $job_ids = array_values(
            array_filter(
                array_map( 'sanitize_text_field', $status_data['job_ids'] )
            )
        );
    } elseif ( isset( $status_data['job_id'] ) && '' !== $status_data['job_id'] ) {
        $job_ids = array( sanitize_text_field( $status_data['job_id'] ) );
    }

    $jobs = get_option( 'noveltool_background_jobs', array() );
    $total_files = ! empty( $job_ids ) ? count( $job_ids ) : 0;
    $downloaded_files = 0;
    $missing_jobs = 0;
    $total_bytes = 0;
    $downloaded_bytes_confirmed = 0;
    $job_last_updated = 0;
    $active_jobs = 0;
    $next_process_event = 0;

    foreach ( $job_ids as $job_id ) {
        if ( ! isset( $jobs[ $job_id ] ) || ! is_array( $jobs[ $job_id ] ) ) {
            $missing_jobs++;
            continue;
        }

        $job = $jobs[ $job_id ];
        $job_updated_at = isset( $job['updated_at'] ) ? intval( $job['updated_at'] ) : 0;
        if ( $job_updated_at > $job_last_updated ) {
            $job_last_updated = $job_updated_at;
        }

        $next_event = wp_next_scheduled( 'noveltool_process_background_job', array( $job_id ) );
        if ( $next_event && intval( $next_event ) > $next_process_event ) {
            $next_process_event = intval( $next_event );
        }

        $next_related_event = noveltool_get_next_job_related_event_timestamp( $job_id );
        if ( $next_related_event && ( ! isset( $metrics['next_related_event'] ) || intval( $next_related_event ) > intval( $metrics['next_related_event'] ) ) ) {
            $metrics['next_related_event'] = intval( $next_related_event );
        }

        if ( isset( $job['status'] ) && in_array( $job['status'], array( NOVELTOOL_JOB_STATUS_PENDING, NOVELTOOL_JOB_STATUS_IN_PROGRESS ), true ) ) {
            $active_jobs++;
        }

        $job_size = isset( $job['data']['size'] ) ? max( 0, intval( $job['data']['size'] ) ) : 0;
        $total_bytes += $job_size;

        if ( isset( $job['status'] ) && NOVELTOOL_JOB_STATUS_COMPLETED === $job['status'] ) {
            $downloaded_files++;

            if ( isset( $job['result']['temp_file'] ) && ! empty( $job['result']['temp_file'] ) && file_exists( $job['result']['temp_file'] ) ) {
                $file_size = filesize( $job['result']['temp_file'] );
                if ( false !== $file_size ) {
                    $downloaded_bytes_confirmed += max( 0, intval( $file_size ) );
                    continue;
                }
            }

            $downloaded_bytes_confirmed += $job_size;
        } elseif ( isset( $job['status'] ) && NOVELTOOL_JOB_STATUS_IN_PROGRESS === $job['status'] && isset( $job['type'] ) && NOVELTOOL_JOB_TYPE_DOWNLOAD === $job['type'] ) {
            if ( isset( $job['data']['temp_file'] ) && ! empty( $job['data']['temp_file'] ) && file_exists( $job['data']['temp_file'] ) ) {
                $partial_size = filesize( $job['data']['temp_file'] );
                if ( false !== $partial_size ) {
                    $downloaded_bytes_confirmed += max( 0, intval( $partial_size ) );
                    $metrics['current_download_file'] = isset( $job['data']['asset_name'] ) ? sanitize_text_field( $job['data']['asset_name'] ) : '';
                    $metrics['current_download_bytes'] = max( 0, intval( $partial_size ) );
                    $metrics['current_download_total'] = $job_size;
                }
            }
        }
    }

    if ( isset( $status_data['total_files'] ) ) {
        $total_files = max( $total_files, intval( $status_data['total_files'] ) );
    }
    if ( isset( $status_data['total_bytes'] ) ) {
        $total_bytes = max( $total_bytes, intval( $status_data['total_bytes'] ) );
    }

    $metrics['total_files'] = max( 0, $total_files );
    $metrics['downloaded_files'] = max( 0, $downloaded_files );
    $metrics['missing_jobs'] = max( 0, $missing_jobs );
    $metrics['total_bytes'] = max( 0, $total_bytes );
    $metrics['downloaded_bytes_confirmed'] = max( 0, $downloaded_bytes_confirmed );

    $progress = isset( $status_data['progress'] ) ? max( 0, min( 100, intval( $status_data['progress'] ) ) ) : 0;
    $metrics['downloaded_bytes_estimated'] = $total_bytes > 0
        ? max( $downloaded_bytes_confirmed, intval( floor( ( $total_bytes * $progress ) / 100 ) ) )
        : $downloaded_bytes_confirmed;
    $metrics['active_jobs'] = max( 0, $active_jobs );
    $metrics['job_last_updated'] = max( 0, $job_last_updated );
    $metrics['job_update_lag_seconds'] = $job_last_updated > 0 ? max( 0, time() - $job_last_updated ) : 0;
    $metrics['next_process_event'] = max( 0, $next_process_event );

    return $metrics;
}

/**
 * 指定ジョブに関連する次回イベント時刻を取得
 *
 * @param string $job_id ジョブID
 * @return int UNIX時刻（見つからない場合は 0）
 * @since 1.5.0
 */
function noveltool_get_next_job_related_event_timestamp( $job_id ) {
    $job_id = sanitize_text_field( $job_id );
    if ( '' === $job_id ) {
        return 0;
    }

    $timestamps = array();

    $process_next = wp_next_scheduled( 'noveltool_process_background_job', array( $job_id ) );
    if ( $process_next ) {
        $timestamps[] = intval( $process_next );
    }

    $cron_array = _get_cron_array();
    if ( ! is_array( $cron_array ) ) {
        return ! empty( $timestamps ) ? min( $timestamps ) : 0;
    }

    $related_hooks = array(
        'noveltool_check_background_job_chain',
        'noveltool_check_background_job_verify',
        'noveltool_check_background_job_extract',
    );

    foreach ( $cron_array as $timestamp => $cronhooks ) {
        if ( ! is_array( $cronhooks ) ) {
            continue;
        }

        foreach ( $related_hooks as $hook_name ) {
            if ( empty( $cronhooks[ $hook_name ] ) || ! is_array( $cronhooks[ $hook_name ] ) ) {
                continue;
            }

            foreach ( $cronhooks[ $hook_name ] as $event ) {
                if ( ! isset( $event['args'] ) || ! is_array( $event['args'] ) || empty( $event['args'] ) ) {
                    continue;
                }

                $first_arg = sanitize_text_field( strval( $event['args'][0] ) );
                if ( $first_arg === $job_id ) {
                    $timestamps[] = intval( $timestamp );
                    break;
                }
            }
        }
    }

    return ! empty( $timestamps ) ? min( $timestamps ) : 0;
}

/**
 * 停滞兆候のある in_progress ジョブを自動復旧する
 *
 * @param array $status_data ステータス配列
 * @return array 復旧結果
 * @since 1.5.0
 */
function noveltool_try_recover_stuck_download_job( $status_data ) {
    $result = array(
        'attempted' => false,
        'scheduled' => false,
        'reason'    => '',
    );

    if ( ! is_array( $status_data ) || ! isset( $status_data['status'] ) || 'in_progress' !== $status_data['status'] ) {
        $result['reason'] = 'not_in_progress';
        return $result;
    }

    if ( empty( $status_data['job_id'] ) ) {
        $result['reason'] = 'missing_job_id';
        return $result;
    }

    $job_id = sanitize_text_field( $status_data['job_id'] );
    $job = noveltool_get_background_job( $job_id );
    if ( ! is_array( $job ) ) {
        $result['reason'] = 'job_not_found';
        return $result;
    }

    $updated_at = isset( $job['updated_at'] ) ? intval( $job['updated_at'] ) : 0;
    if ( $updated_at <= 0 ) {
        $updated_at = isset( $job['created_at'] ) ? intval( $job['created_at'] ) : 0;
    }

    $lag_seconds = $updated_at > 0 ? max( 0, time() - $updated_at ) : 0;
    $recovery_threshold = 180;
    $orphan_fail_threshold = 420;

    $next_process_event = wp_next_scheduled( 'noveltool_process_background_job', array( $job_id ) );
    if ( $next_process_event ) {
        $result['reason'] = 'process_event_exists';
        return $result;
    }

    $job_running_key = 'noveltool_job_running_' . md5( $job_id );
    $heartbeat_value = get_transient( $job_running_key );
    if ( false !== $heartbeat_value ) {
        $heartbeat_timestamp = intval( $heartbeat_value );
        // 旧実装（value=1）との互換: 数値1は時刻として扱えないため stale 扱い
        if ( $heartbeat_timestamp > 1000000000 ) {
            $heartbeat_age = max( 0, time() - $heartbeat_timestamp );
            if ( $heartbeat_age < 600 ) {
                $result['reason'] = 'job_running_heartbeat';
                return $result;
            }
        }
    }

    // 一時ファイル更新時刻が新しい場合は実行中とみなす
    if ( isset( $job['data']['temp_file'] ) && ! empty( $job['data']['temp_file'] ) && file_exists( $job['data']['temp_file'] ) ) {
        $temp_mtime = filemtime( $job['data']['temp_file'] );
        if ( false !== $temp_mtime ) {
            $temp_age = max( 0, time() - intval( $temp_mtime ) );
            if ( $temp_age < 180 ) {
                $result['reason'] = 'job_running_tempfile_active';
                return $result;
            }
        }
    }

    if ( $lag_seconds < $recovery_threshold ) {
        $result['reason'] = 'lag_too_short';
        return $result;
    }

    $recovery_key = 'noveltool_job_recovery_' . md5( $job_id );
    $recovery_attempts = intval( get_transient( $recovery_key ) );
    if ( $recovery_attempts >= 1 ) {
        if ( $lag_seconds >= $orphan_fail_threshold ) {
            noveltool_update_download_status(
                'failed',
                __( 'バックグラウンドジョブの再開に失敗しました。再試行してください。', 'novel-game-plugin' ),
                'ERR-JOB-ORPHANED',
                'background',
                array( 'stage_detail' => 'orphaned_without_schedule', 'retry_count' => $recovery_attempts )
            );
            delete_option( 'noveltool_sample_images_download_lock' );
            $result['reason'] = 'orphaned_marked_failed';
            return $result;
        }

        $result['reason'] = 'already_recovered';
        return $result;
    }

    $result['attempted'] = true;

    noveltool_update_background_job(
        $job_id,
        array(
            'status' => NOVELTOOL_JOB_STATUS_PENDING,
        )
    );

    $scheduled = noveltool_schedule_background_job( $job_id, 1 );
    if ( $scheduled ) {
        set_transient( $recovery_key, 1, 30 * MINUTE_IN_SECONDS );
        $result['scheduled'] = true;
        $result['reason'] = 'rescheduled_process_job';

        error_log(
            sprintf(
                'NovelGamePlugin: Auto-recovered stuck job %s (lag=%ds)',
                $job_id,
                $lag_seconds
            )
        );
    } else {
        $result['reason'] = 'schedule_failed';
    }

    return $result;
}

/**
 * ダウンロード進捗を指定範囲内で段階的に進める
 *
 * @param int $min_progress 最小進捗
 * @param int $max_progress 最大進捗
 * @since 1.5.0
 */
function noveltool_bump_download_progress( $min_progress, $max_progress ) {
    $status = get_option( 'noveltool_sample_images_download_status', 'not_started' );
    if ( 'in_progress' !== $status ) {
        return;
    }

    $status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    if ( empty( $status_data ) || ! is_array( $status_data ) ) {
        return;
    }

    $current = isset( $status_data['progress'] ) ? intval( $status_data['progress'] ) : 0;
    $current_step = isset( $status_data['current_step'] ) ? sanitize_text_field( $status_data['current_step'] ) : 'download';
    $job_id = isset( $status_data['job_id'] ) ? sanitize_text_field( $status_data['job_id'] ) : '';
    $use_background = isset( $status_data['use_background'] ) ? (bool) $status_data['use_background'] : true;

    $next = max( intval( $min_progress ), min( intval( $max_progress ), $current + 1 ) );
    if ( $next <= $current ) {
        return;
    }

    noveltool_update_download_status(
        'in_progress',
        '',
        '',
        '',
        array(),
        array(
            'job_id'         => $job_id,
            'progress'       => $next,
            'current_step'   => $current_step,
            'use_background' => $use_background,
        )
    );
}

/**
 * 進行中ジョブの停滞を検知して失敗へ遷移する
 *
 * @param string $job_id ジョブID
 * @param int    $timeout_seconds 停滞判定秒数
 * @return bool 停滞を検知して失敗にした場合 true
 * @since 1.5.0
 */
function noveltool_fail_if_job_stalled( $job_id, $timeout_seconds = 600 ) {
    $job = noveltool_get_background_job( $job_id );
    if ( ! $job || ! isset( $job['status'] ) || NOVELTOOL_JOB_STATUS_IN_PROGRESS !== $job['status'] ) {
        return false;
    }

    $timeout_seconds = intval( $timeout_seconds );
    if ( isset( $job['type'] ) && NOVELTOOL_JOB_TYPE_DOWNLOAD === $job['type'] ) {
        // 大容量ダウンロードでの誤検知を避けるため、download ジョブは判定時間を長めに取る
        $timeout_seconds = max( $timeout_seconds, 1800 );
    }

    $updated_at = isset( $job['updated_at'] ) ? intval( $job['updated_at'] ) : 0;
    if ( $updated_at <= 0 ) {
        $updated_at = isset( $job['created_at'] ) ? intval( $job['created_at'] ) : 0;
    }

    if ( $updated_at > 0 && ( time() - $updated_at ) > $timeout_seconds ) {
        $error_message = __( 'バックグラウンドのダウンロードジョブが停止した可能性があります。再度ダウンロードを実行してください。', 'novel-game-plugin' );
        noveltool_update_download_status(
            'failed',
            $error_message,
            'ERR-JOB-STALLED',
            'background',
            array( 'stage_detail' => 'stalled_timeout' )
        );
        delete_option( 'noveltool_sample_images_download_lock' );
        noveltool_delete_background_job( $job_id );
        return true;
    }

    return false;
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
 * ダウンロードジョブIDをuser_metaからクリアするヘルパー関数
 *
 * @param int $user_id ユーザーID（0の場合はスキップ）
 * @since 1.5.0
 */
function noveltool_clear_download_job_id( $user_id ) {
    if ( $user_id > 0 ) {
        delete_user_meta( $user_id, 'noveltool_download_job_id' );
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
    $asset_size = isset( $asset['size'] ) ? absint( $asset['size'] ) : 0;
    $destination_dir = noveltool_get_sample_images_directory();
    
    // ダウンロードジョブを作成
    $download_job_id = noveltool_create_background_job(
        NOVELTOOL_JOB_TYPE_DOWNLOAD,
        array(
            'download_url' => $download_url,
            'user_id'      => get_current_user_id(), // ユーザーIDを保存（バックグラウンド処理での削除用）
        )
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
            'job_ids'        => array( $download_job_id ),
            'progress'       => 10,
            'current_step'   => 'download',
            'use_background' => true,
            'total_files'    => 1,
            'downloaded_files' => 0,
            'total_bytes'    => $asset_size,
            'downloaded_bytes' => 0,
            'destination_dir' => $destination_dir,
        )
    );
    
    // ジョブIDをuser_metaに保存（UI追跡用）
    // 注: この関数は通常ユーザーリクエスト時に呼ばれるため get_current_user_id() を使用
    // ジョブデータにもuser_idを保存済みなので、バックグラウンド処理時はそちらを参照
    $user_id = get_current_user_id();
    if ( $user_id ) {
        update_user_meta( $user_id, 'noveltool_download_job_id', $download_job_id );
    }
    
    // ダウンロードジョブをスケジュール
    noveltool_schedule_background_job( $download_job_id );
    
    // チェーンジョブを登録（ダウンロード完了後に実行）
    // 二重スケジューリング防止チェック
    $chain_scheduled = wp_next_scheduled( 'noveltool_check_background_job_chain', array( $download_job_id, $checksum ) );
    if ( ! $chain_scheduled ) {
        wp_schedule_single_event(
            time() + 10,
            'noveltool_check_background_job_chain',
            array( $download_job_id, $checksum )
        );
    }
    
    return array(
        'success' => true,
        'message' => __( 'Download started in background. Please wait...', 'novel-game-plugin' ),
        'job_id'  => $download_job_id,
    );
}

/**
 * 複数アセットのバックグラウンドダウンロードを実行
 *
 * @param array $release_data リリース情報
 * @param array $assets_with_checksum アセットとチェックサムの配列
 * @return array|WP_Error 実行結果またはエラー
 * @since 1.4.1
 */
function noveltool_perform_multi_asset_download_background( $release_data, $assets_with_checksum ) {
    // 必須修正1: assets 空チェック
    if ( empty( $assets_with_checksum ) || ! is_array( $assets_with_checksum ) ) {
        error_log( 'NovelGamePlugin: noveltool_perform_multi_asset_download_background() called with empty assets array' );
        return new WP_Error(
            'empty_assets',
            __( 'No assets available for download.', 'novel-game-plugin' ),
            array( 'stage' => 'multi_asset_setup' )
        );
    }
    
    // 順次実行用のキューを作成
    $queue_assets = array();
    $successful_jobs = 0;
    $failed_assets = array();
    $total_bytes = 0;
    $destination_dir = noveltool_get_sample_images_directory();
    
    foreach ( $assets_with_checksum as $index => $item ) {
        $asset = $item['asset'];
        $checksum = $item['checksum'];
        $asset_name = isset( $asset['name'] ) ? sanitize_text_field( $asset['name'] ) : '';
        $download_url = isset( $asset['browser_download_url'] ) ? esc_url_raw( $asset['browser_download_url'] ) : '';
        $size = isset( $asset['size'] ) ? absint( $asset['size'] ) : 0;
        $total_bytes += $size;
        
        // 必須修正6: 型検査とサニタイズ
        if ( empty( $asset_name ) || empty( $download_url ) ) {
            error_log( sprintf( 'NovelGamePlugin: Invalid asset at index %d: missing name or URL', $index ) );
            $failed_assets[] = array(
                'index'   => $index,
                'name'    => $asset_name ? $asset_name : 'unknown',
                'reason'  => 'invalid_asset_data',
                'message' => __( 'Invalid asset data', 'novel-game-plugin' ),
            );
            continue;
        }
        
        // 必須修正4: チェックサム取得ポリシーの明確化
        // チェックサムがない場合はログに記録し、検証スキップとして処理
        if ( empty( $checksum ) ) {
            error_log( sprintf(
                'NovelGamePlugin: Checksum not available for %s. Verification will be skipped.',
                $asset_name
            ) );
        }
        
        $queue_assets[] = array(
            'name'     => $asset_name,
            'url'      => $download_url,
            'size'     => $size,
            'checksum' => $checksum,
        );
        $successful_jobs++;
    }
    
    // すべてのジョブ作成に失敗した場合はエラーを返す
    if ( empty( $queue_assets ) ) {
        error_log( 'NovelGamePlugin: All asset jobs failed to create' );
        return new WP_Error(
            'all_jobs_failed',
            __( 'Failed to create background jobs for all assets. Please check server logs.', 'novel-game-plugin' ),
            array(
                'stage'         => 'multi_asset_setup',
                'failed_assets' => $failed_assets,
            )
        );
    }
    
    $first_asset = $queue_assets[0];
    $first_job_id = noveltool_create_background_job(
        NOVELTOOL_JOB_TYPE_DOWNLOAD,
        array(
            'download_url' => $first_asset['url'],
            'asset_name'   => $first_asset['name'],
            'asset_index'  => 0,
            'total_assets' => count( $queue_assets ),
            'size'         => isset( $first_asset['size'] ) ? intval( $first_asset['size'] ) : 0,
            'checksum'     => isset( $first_asset['checksum'] ) ? $first_asset['checksum'] : '',
            'user_id'      => get_current_user_id(),
        )
    );

    if ( ! is_string( $first_job_id ) || '' === $first_job_id ) {
        return new WP_Error(
            'first_job_failed',
            __( 'Failed to create initial background job.', 'novel-game-plugin' ),
            array( 'stage' => 'multi_asset_setup' )
        );
    }
    
    // 集約ステータスを更新
    noveltool_update_download_status(
        'in_progress',
        '',
        '',
        '',
        array(),
        array(
            'job_id'           => $first_job_id,
            'job_ids'          => array( $first_job_id ),
            'progress'         => 5,
            'current_step'     => 'download',
            'use_background'   => true,
            'multi_asset'      => true,
            'queue_assets'     => $queue_assets,
            'current_queue_index' => 0,
            'total_assets'     => count( $assets_with_checksum ),
            'successful_jobs'  => $successful_jobs,
            'failed_jobs'      => count( $failed_assets ),
            'failed_assets'    => $failed_assets,
            'total_files'      => count( $queue_assets ),
            'downloaded_files' => 0,
            'total_bytes'      => $total_bytes,
            'downloaded_bytes' => 0,
            'destination_dir'  => $destination_dir,
        )
    );
    
    // ジョブIDをuser_metaに保存（UI追跡用）
    // 注: この関数は通常ユーザーリクエスト時に呼ばれるため get_current_user_id() を使用
    // ジョブデータにもuser_idを保存済みなので、バックグラウンド処理時はそちらを参照
    $user_id = get_current_user_id();
    if ( $user_id && $first_job_id ) {
        update_user_meta( $user_id, 'noveltool_download_job_id', $first_job_id );
    }

    noveltool_schedule_background_job( $first_job_id );
    wp_schedule_single_event(
        time() + 10,
        'noveltool_check_background_job_chain',
        array( $first_job_id, isset( $first_asset['checksum'] ) ? $first_asset['checksum'] : '' )
    );
    
    // 部分的な失敗がある場合は警告を含める
    $message = sprintf(
        /* translators: %d: number of asset files */
        __( 'Download started for %d asset files in background. Please wait...', 'novel-game-plugin' ),
        $successful_jobs
    );
    
    if ( ! empty( $failed_assets ) ) {
        $message .= ' ' . sprintf(
            /* translators: %d: number of failed assets */
            __( 'Warning: %d assets could not be queued.', 'novel-game-plugin' ),
            count( $failed_assets )
        );
    }
    
    return array(
        'success'        => true,
        'message'        => sanitize_text_field( $message ),
        'job_ids'        => array( $first_job_id ),
        'total_assets'   => count( $assets_with_checksum ),
        'successful'     => $successful_jobs,
        'failed'         => count( $failed_assets ),
        'failed_assets'  => $failed_assets,
    );
}

/**
 * 監視イベントが現行ダウンロード状態に対して古いかどうかを判定する
 *
 * @param string $job_id 監視対象ジョブID
 * @param string $expected_step 想定ステップ（download/verify/extract）
 * @return bool 古いイベントの場合 true
 * @since 1.5.0
 */
function noveltool_is_stale_watchdog_event( $job_id, $expected_step = '' ) {
    $job_id = sanitize_text_field( $job_id );
    if ( '' === $job_id ) {
        return false;
    }

    $status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    if ( ! is_array( $status_data ) ) {
        return false;
    }

    $status = isset( $status_data['status'] ) ? sanitize_text_field( $status_data['status'] ) : '';
    if ( 'in_progress' !== $status ) {
        return true;
    }

    $active_job_id = isset( $status_data['job_id'] ) ? sanitize_text_field( $status_data['job_id'] ) : '';
    if ( '' !== $active_job_id && $active_job_id !== $job_id ) {
        return true;
    }

    if ( '' !== $expected_step ) {
        $current_step = isset( $status_data['current_step'] ) ? sanitize_text_field( $status_data['current_step'] ) : '';
        if ( '' !== $current_step && $current_step !== sanitize_text_field( $expected_step ) ) {
            return true;
        }
    }

    return false;
}

/**
 * バックグラウンドジョブチェーンをチェック
 *
 * @param string $previous_job_id 前のジョブID
 * @param string $checksum チェックサム（オプション）
 * @since 1.4.0
 */
function noveltool_check_background_job_chain( $previous_job_id, $checksum = '' ) {
    if ( noveltool_fail_if_job_stalled( $previous_job_id ) ) {
        return;
    }

    $job = noveltool_get_background_job( $previous_job_id );
    
    if ( ! $job ) {
        if ( noveltool_is_stale_watchdog_event( $previous_job_id, 'download' ) ) {
            error_log( sprintf( 'NovelGamePlugin: Ignore stale chain watcher for missing job: %s', sanitize_text_field( $previous_job_id ) ) );
            return;
        }

        $retry_key = 'noveltool_missing_job_retry_' . md5( sanitize_text_field( $previous_job_id ) );
        $retry_count = intval( get_transient( $retry_key ) );
        if ( $retry_count < 3 ) {
            set_transient( $retry_key, $retry_count + 1, 60 );
            wp_schedule_single_event(
                time() + 10,
                'noveltool_check_background_job_chain',
                array( $previous_job_id, $checksum )
            );
            return;
        }

        delete_transient( $retry_key );
        error_log( "NovelGamePlugin: Previous job not found after retries: {$previous_job_id}" );
        noveltool_update_download_status(
            'failed',
            __( 'ダウンロードジョブが見つかりません。再度ダウンロードを実行してください。', 'novel-game-plugin' ),
            'ERR-JOB-NOTFOUND',
            'background',
            array( 'retry_count' => $retry_count )
        );
        delete_option( 'noveltool_sample_images_download_lock' );
        return;
    }
    
    // ジョブが完了していない場合は再スケジュール
    if ( $job['status'] !== NOVELTOOL_JOB_STATUS_COMPLETED ) {
        if ( $job['status'] === NOVELTOOL_JOB_STATUS_FAILED ) {
            // 失敗した場合、一時ファイルをクリーンアップ
            $result = isset( $job['result'] ) ? $job['result'] : array();
            $temp_file = isset( $result['temp_file'] ) ? $result['temp_file'] : '';
            if ( empty( $temp_file ) && isset( $job['data']['temp_file'] ) ) {
                $temp_file = $job['data']['temp_file'];
            }
            if ( ! empty( $temp_file ) && file_exists( $temp_file ) ) {
                @unlink( $temp_file );
            }
            
            $error = isset( $job['error'] ) ? $job['error'] : array( 'message' => 'Unknown error' );
            $error_context = array();
            if ( isset( $error['detail'] ) && is_array( $error['detail'] ) ) {
                if ( isset( $error['detail']['fatal_message'] ) ) {
                    $error_context['fatal_message'] = sanitize_text_field( $error['detail']['fatal_message'] );
                }
                if ( isset( $error['detail']['fatal_file'] ) ) {
                    $error_context['fatal_file'] = sanitize_text_field( $error['detail']['fatal_file'] );
                }
                if ( isset( $error['detail']['fatal_line'] ) ) {
                    $error_context['fatal_line'] = intval( $error['detail']['fatal_line'] );
                }
                if ( isset( $error['detail']['exception_class'] ) ) {
                    $error_context['exception_class'] = sanitize_text_field( $error['detail']['exception_class'] );
                }
                if ( isset( $error['detail']['exception_file'] ) ) {
                    $error_context['exception_file'] = sanitize_text_field( $error['detail']['exception_file'] );
                }
                if ( isset( $error['detail']['exception_line'] ) ) {
                    $error_context['exception_line'] = intval( $error['detail']['exception_line'] );
                }
                if ( isset( $error['detail']['unzip_exit_code'] ) ) {
                    $error_context['unzip_exit_code'] = intval( $error['detail']['unzip_exit_code'] );
                }
            }
            noveltool_update_download_status(
                'failed',
                isset( $error['message'] ) ? $error['message'] : 'Job failed',
                isset( $error['code'] ) ? $error['code'] : 'ERR-JOB-FAILED',
                'background',
                $error_context
            );
            delete_option( 'noveltool_sample_images_download_lock' );
            noveltool_delete_background_job( $previous_job_id );
            return;
        }

        $job_running_key = 'noveltool_job_running_' . md5( sanitize_text_field( $previous_job_id ) );
        $is_job_running = (bool) get_transient( $job_running_key );
        $next_process_event = wp_next_scheduled( 'noveltool_process_background_job', array( $previous_job_id ) );
        $updated_at = isset( $job['updated_at'] ) ? intval( $job['updated_at'] ) : 0;
        if ( $updated_at <= 0 ) {
            $updated_at = isset( $job['created_at'] ) ? intval( $job['created_at'] ) : 0;
        }
        $stuck_seconds = $updated_at > 0 ? max( 0, time() - $updated_at ) : 0;

        if ( NOVELTOOL_JOB_STATUS_IN_PROGRESS === $job['status'] && ! $is_job_running && ! $next_process_event && $stuck_seconds >= 60 ) {
            $recover_key = 'noveltool_process_recover_retry_' . md5( sanitize_text_field( $previous_job_id ) );
            $recover_count = intval( get_transient( $recover_key ) );
            if ( $recover_count < 2 ) {
                set_transient( $recover_key, $recover_count + 1, 300 );
                noveltool_schedule_background_job( $previous_job_id, 5 );
                noveltool_update_download_status(
                    'in_progress',
                    __( 'バックグラウンド処理を再開しています…', 'novel-game-plugin' ),
                    '',
                    'background',
                    array(
                        'stage_detail'    => 'recover_process_rescheduled',
                        'recovered'       => true,
                        'recover_attempt' => $recover_count + 1,
                    )
                );
            }
        }

        if ( NOVELTOOL_JOB_STATUS_IN_PROGRESS === $job['status'] && ! $is_job_running && ! $next_process_event && $stuck_seconds >= 180 ) {
            noveltool_update_download_status(
                'failed',
                __( 'ダウンロード処理が停止しました。再度ダウンロードを実行してください。', 'novel-game-plugin' ),
                'ERR-JOB-STUCK',
                'background',
                array( 'stage_detail' => 'download_job_orphaned', 'retry_count' => intval( $job['attempts'] ), 'stuck_seconds' => $stuck_seconds )
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
        $temp_file = isset( $job['data']['temp_file'] ) ? $job['data']['temp_file'] : '';
        if ( ! empty( $temp_file ) && file_exists( $temp_file ) && filesize( $temp_file ) > 0 ) {
            noveltool_bump_download_progress( 5, 45 );
        }
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
    $user_id = isset( $job['data']['user_id'] ) ? intval( $job['data']['user_id'] ) : 0;
    
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
                'user_id'   => $user_id,
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
        noveltool_schedule_extract_job( $temp_file, $user_id );
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
    if ( noveltool_fail_if_job_stalled( $verify_job_id ) ) {
        return;
    }

    $job = noveltool_get_background_job( $verify_job_id );
    
    if ( ! $job ) {
        if ( noveltool_is_stale_watchdog_event( $verify_job_id, 'verify' ) ) {
            error_log( sprintf( 'NovelGamePlugin: Ignore stale verify watcher for missing job: %s', sanitize_text_field( $verify_job_id ) ) );
            return;
        }

        $retry_key = 'noveltool_missing_job_retry_' . md5( sanitize_text_field( $verify_job_id ) );
        $retry_count = intval( get_transient( $retry_key ) );
        if ( $retry_count < 3 ) {
            set_transient( $retry_key, $retry_count + 1, 60 );
            wp_schedule_single_event(
                time() + 10,
                'noveltool_check_background_job_verify',
                array( $verify_job_id, $temp_file )
            );
            return;
        }

        delete_transient( $retry_key );
        noveltool_update_download_status(
            'failed',
            __( '検証ジョブが見つかりません。再度ダウンロードを実行してください。', 'novel-game-plugin' ),
            'ERR-JOB-NOTFOUND',
            'background',
            array( 'retry_count' => $retry_count )
        );
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
        
        noveltool_bump_download_progress( 50, 75 );
        wp_schedule_single_event(
            time() + 10,
            'noveltool_check_background_job_verify',
            array( $verify_job_id, $temp_file )
        );
        return;
    }
    
    // 検証完了 - 抽出ジョブへ
    $user_id = isset( $job['data']['user_id'] ) ? intval( $job['data']['user_id'] ) : 0;
    noveltool_schedule_extract_job( $temp_file, $user_id );
    noveltool_delete_background_job( $verify_job_id );
}
add_action( 'noveltool_check_background_job_verify', 'noveltool_check_background_job_verify', 10, 2 );

/**
 * 抽出ジョブをスケジュール
 *
 * @param string $temp_file 一時ファイル
 * @param int    $user_id ユーザーID
 * @since 1.4.0
 */
function noveltool_schedule_extract_job( $temp_file, $user_id = 0 ) {
    $user_id = intval( $user_id );
    $extract_job_id = noveltool_create_background_job(
        NOVELTOOL_JOB_TYPE_EXTRACT,
        array(
            'temp_file' => $temp_file,
            'user_id'   => $user_id,
        )
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
    if ( noveltool_fail_if_job_stalled( $extract_job_id ) ) {
        return;
    }

    $job = noveltool_get_background_job( $extract_job_id );
    
    if ( ! $job ) {
        if ( noveltool_is_stale_watchdog_event( $extract_job_id, 'extract' ) ) {
            error_log( sprintf( 'NovelGamePlugin: Ignore stale extract watcher for missing job: %s', sanitize_text_field( $extract_job_id ) ) );
            return;
        }

        $retry_key = 'noveltool_missing_job_retry_' . md5( sanitize_text_field( $extract_job_id ) );
        $retry_count = intval( get_transient( $retry_key ) );
        if ( $retry_count < 3 ) {
            set_transient( $retry_key, $retry_count + 1, 60 );
            wp_schedule_single_event(
                time() + 10,
                'noveltool_check_background_job_extract',
                array( $extract_job_id )
            );
            return;
        }

        delete_transient( $retry_key );
        noveltool_update_download_status(
            'failed',
            __( '展開ジョブが見つかりません。再度ダウンロードを実行してください。', 'novel-game-plugin' ),
            'ERR-JOB-NOTFOUND',
            'background',
            array( 'retry_count' => $retry_count )
        );
        delete_option( 'noveltool_sample_images_download_lock' );
        return;
    }
    
    if ( $job['status'] !== NOVELTOOL_JOB_STATUS_COMPLETED ) {
        if ( $job['status'] === NOVELTOOL_JOB_STATUS_FAILED ) {
            $error = isset( $job['error'] ) ? $job['error'] : array( 'message' => 'Unknown error' );
            $error_context = array();
            if ( isset( $error['detail'] ) && is_array( $error['detail'] ) ) {
                if ( isset( $error['detail']['fatal_message'] ) ) {
                    $error_context['fatal_message'] = sanitize_text_field( $error['detail']['fatal_message'] );
                }
                if ( isset( $error['detail']['fatal_file'] ) ) {
                    $error_context['fatal_file'] = sanitize_text_field( $error['detail']['fatal_file'] );
                }
                if ( isset( $error['detail']['fatal_line'] ) ) {
                    $error_context['fatal_line'] = intval( $error['detail']['fatal_line'] );
                }
                if ( isset( $error['detail']['exception_class'] ) ) {
                    $error_context['exception_class'] = sanitize_text_field( $error['detail']['exception_class'] );
                }
                if ( isset( $error['detail']['exception_file'] ) ) {
                    $error_context['exception_file'] = sanitize_text_field( $error['detail']['exception_file'] );
                }
                if ( isset( $error['detail']['exception_line'] ) ) {
                    $error_context['exception_line'] = intval( $error['detail']['exception_line'] );
                }
                if ( isset( $error['detail']['unzip_exit_code'] ) ) {
                    $error_context['unzip_exit_code'] = intval( $error['detail']['unzip_exit_code'] );
                }
            }
            noveltool_update_download_status(
                'failed',
                isset( $error['message'] ) ? $error['message'] : 'Extraction failed',
                isset( $error['code'] ) ? $error['code'] : 'ERR-EXTRACT-FAILED',
                'extract',
                $error_context
            );
            delete_option( 'noveltool_sample_images_download_lock' );
            noveltool_delete_background_job( $extract_job_id );
            return;
        }
        
        noveltool_bump_download_progress( 80, 95 );
        wp_schedule_single_event(
            time() + 10,
            'noveltool_check_background_job_extract',
            array( $extract_job_id )
        );
        return;
    }
    
    // multi_asset キューの場合は次のアセットを開始
    $status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    if ( is_array( $status_data ) && ! empty( $status_data['queue_assets'] ) && is_array( $status_data['queue_assets'] ) ) {
        $user_id = isset( $job['data']['user_id'] ) ? intval( $job['data']['user_id'] ) : 0;

        // 現在ジョブを削除してから次ジョブを開始
        noveltool_delete_background_job( $extract_job_id );

        $started = noveltool_start_next_queued_asset_download( $status_data, $user_id );
        if ( $started ) {
            return;
        }

        // 次ジョブが無ければ全件完了として扱う
        $queue_assets = $status_data['queue_assets'];
        $total_files = count( $queue_assets );
        $total_bytes = isset( $status_data['total_bytes'] ) ? max( 0, intval( $status_data['total_bytes'] ) ) : 0;

        noveltool_update_download_status(
            'completed',
            '',
            '',
            '',
            array(),
            array(
                'downloaded_files' => $total_files,
                'total_files'      => $total_files,
                'downloaded_bytes' => $total_bytes,
                'total_bytes'      => $total_bytes,
            )
        );
    } else {
        // 単一アセットは従来どおり完了
        noveltool_update_download_status( 'completed' );
    }

    update_option( 'noveltool_sample_images_downloaded', true, false );
    delete_option( 'noveltool_sample_images_download_lock' );
    
    // ジョブIDをuser_metaからクリア
    $completed_user_id = isset( $job['data']['user_id'] ) ? intval( $job['data']['user_id'] ) : 0;
    if ( $completed_user_id > 0 ) {
        noveltool_clear_download_job_id( $completed_user_id );
    }
    
    // 完了したジョブのログを保存（デバッグ・監査用）
    $log_entry = array(
        'type'         => 'job_completed',
        'job_id'       => $extract_job_id,
        'completed_at' => time(),
        'status'       => 'success',
    );
    noveltool_append_job_log( $log_entry );
    
    // 現在のジョブを削除（未削除の場合のみ）
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
    
    $upload_dir = wp_upload_dir();
    $destination_parent = ! empty( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : WP_CONTENT_DIR . '/uploads';
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
    
    // 複数のサンプル画像アセットを探す（分割ZIPサポート）
    $all_assets = noveltool_find_all_sample_images_assets( $release_data );
    if ( empty( $all_assets ) ) {
        // 後方互換: 単一アセット検索へフォールバック
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
        $all_assets = array( $asset );
    }
    
    // 各アセットのチェックサムを取得
    $assets_with_checksum = array();
    foreach ( $all_assets as $asset ) {
        $asset_name = $asset['name'];
        $checksum = '';
        
        // 対応するチェックサムファイルを探す
        $checksum_asset = null;
        foreach ( $release_data['assets'] as $a ) {
            if ( isset( $a['name'] ) && $a['name'] === $asset_name . '.sha256' ) {
                $checksum_asset = $a;
                break;
            }
        }
        
        // チェックサムを取得
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
                    $checksum = $matches[1];
                } else {
                    error_log( sprintf( 'NovelGamePlugin: Invalid checksum format for %s', $asset_name ) );
                }
            } else {
                error_log( sprintf( 'NovelGamePlugin: Failed to fetch checksum for %s', $asset_name ) );
            }
        }
        
        $assets_with_checksum[] = array(
            'asset'    => $asset,
            'checksum' => $checksum,
        );
    }
    
    // 後方互換: 単一アセットの場合は従来の変数も設定
    $asset = $all_assets[0];
    $asset_name = $asset['name'];
    $download_url = $asset['browser_download_url'];
    $expected_checksum = $assets_with_checksum[0]['checksum'];
    
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
        if ( count( $all_assets ) > 1 ) {
            // 複数アセット: 各アセットに対してジョブを作成
            $result = noveltool_perform_multi_asset_download_background( $release_data, $assets_with_checksum );
            
            // WP_Error チェック（必須修正2の対応）
            if ( is_wp_error( $result ) ) {
                $error_msg = $result->get_error_message();
                $error_data = $result->get_error_data();
                $error_code = $result->get_error_code();
                
                // 詳細をログに記録
                error_log( sprintf(
                    'NovelGamePlugin: Multi-asset download failed - Code: %s, Message: %s',
                    $error_code,
                    $error_msg
                ) );
                
                // ステータスを更新
                noveltool_update_download_status(
                    'failed',
                    sanitize_text_field( $error_msg ),
                    $error_code,
                    isset( $error_data['stage'] ) ? $error_data['stage'] : 'multi_asset_setup'
                );
                
                delete_option( 'noveltool_sample_images_download_lock' );
                
                // ユーザー向けには簡潔なメッセージを返す
                return array(
                    'success' => false,
                    'message' => sanitize_text_field( $error_msg ),
                    'code'    => $error_code,
                    'stage'   => isset( $error_data['stage'] ) ? $error_data['stage'] : 'multi_asset_setup',
                );
            }
            
            // 配列型チェック（必須修正6の対応）
            if ( ! is_array( $result ) ) {
                error_log( 'NovelGamePlugin: Multi-asset download returned non-array result' );
                delete_option( 'noveltool_sample_images_download_lock' );
                return array(
                    'success' => false,
                    'message' => sanitize_text_field( __( 'Unexpected error during multi-asset download setup', 'novel-game-plugin' ) ),
                    'code'    => 'ERR-INVALID-RESULT',
                    'stage'   => 'multi_asset_setup',
                );
            }
        } else {
            // 単一アセット: 従来の処理
            $result = noveltool_perform_sample_images_download_background( $release_data, $asset, $expected_checksum );
        }
        delete_option( 'noveltool_sample_images_download_lock' );
        return $result;
    }
    
    // 従来の同期処理
    // ZIP をダウンロード
    $temp_zip = noveltool_download_sample_images_zip( $download_url, '', isset( $asset['size'] ) ? absint( $asset['size'] ) : 0 );
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
    $destination = noveltool_get_sample_images_directory();
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
    
    // ジョブIDをuser_metaからクリア（同期処理の場合）
    // 注: 同期実行時はユーザーセッション内なので get_current_user_id() を使用
    noveltool_clear_download_job_id( get_current_user_id() );
    
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
    if ( $result instanceof WP_Error ) {
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
    $recovery_result = array();
    $latest_status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    if ( is_array( $latest_status_data ) && isset( $latest_status_data['job_id'] ) ) {
        noveltool_fail_if_job_stalled( sanitize_text_field( $latest_status_data['job_id'] ) );
        $recovery_result = noveltool_try_recover_stuck_download_job( $latest_status_data );
    }

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

    $runtime_metrics = noveltool_get_download_runtime_metrics( $status_data );
    if ( ! empty( $runtime_metrics ) ) {
        $response = array_merge( $response, $runtime_metrics );
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
