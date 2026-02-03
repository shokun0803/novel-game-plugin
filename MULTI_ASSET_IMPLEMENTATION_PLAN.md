# 複数アセット対応実装計画（Multiple Asset Support Implementation Plan）

## 概要
PR#224により分割された複数のサンプル画像ZIPファイルに対応し、低スペックサーバーでの耐障害性を向上させるための実装計画。

## 背景
- 20MBを超える大きなZIPファイルを分割することで、低スペックサーバーでのダウンロード負荷を軽減
- 複数の小さなZIPファイルを個別にダウンロード・抽出することで、メモリ制限や実行時間制限の影響を最小化
- これは新機能ではなく、耐障害性強化の一環

## 実装状況

### ✅ 完了
1. **複数アセット検出機能** (コミット: 5e589c7)
   - `noveltool_find_all_sample_images_assets()` 関数を追加
   - リリースから複数のサンプル画像ZIPを自動検出
   - SHA256チェックサムファイルを除外
   - アルファベット順でソート

### 🚧 実装予定

#### 1. 複数アセット用のジョブ化（高優先度）
**対象ファイル**: `includes/sample-images-downloader.php`
**対象関数**: `noveltool_perform_sample_images_download()` (lines 1522-1700)

**変更内容**:
```php
// 現在: 単一アセットのみ処理
$asset = noveltool_find_sample_images_asset( $release_data );
if ( ! $asset ) {
    return array( 'success' => false, ... );
}

// 変更後: すべてのアセットを検出してジョブ化
$assets = noveltool_find_all_sample_images_assets( $release_data );
if ( empty( $assets ) ) {
    $error_msg = __( 'Sample images asset not found in the latest release.', 'novel-game-plugin' );
    noveltool_update_download_status( 'failed', $error_msg, 'ERR-ASSET-NOTFOUND', 'fetch_release' );
    delete_option( 'noveltool_sample_images_download_lock' );
    return array( 'success' => false, 'message' => $error_msg, 'code' => 'ERR-ASSET-NOTFOUND' );
}

// 各アセットごとにチェックサムを取得
$asset_jobs = array();
foreach ( $assets as $asset ) {
    $asset_name = $asset['name'];
    $download_url = $asset['browser_download_url'];
    $size = isset( $asset['size'] ) ? $asset['size'] : 0;
    
    // チェックサムを取得
    $expected_checksum = '';
    $checksum_asset_name = $asset_name . '.sha256';
    foreach ( $release_data['assets'] as $a ) {
        if ( isset( $a['name'] ) && $a['name'] === $checksum_asset_name ) {
            // チェックサムをダウンロード
            $checksum_response = wp_remote_get( $a['browser_download_url'], array( 'timeout' => 30 ) );
            if ( ! is_wp_error( $checksum_response ) && 200 === wp_remote_retrieve_response_code( $checksum_response ) ) {
                $checksum_body = wp_remote_retrieve_body( $checksum_response );
                if ( preg_match( '/\b([a-f0-9]{64})\b/i', $checksum_body, $matches ) ) {
                    $expected_checksum = $matches[1];
                }
            }
            break;
        }
    }
    
    // ジョブを作成
    $job_data = array(
        'download_url' => $download_url,
        'asset_name' => $asset_name,
        'size' => $size,
        'checksum' => $expected_checksum,
    );
    
    $job_id = noveltool_create_background_job( NOVELTOOL_JOB_TYPE_DOWNLOAD, $job_data );
    if ( $job_id ) {
        $asset_jobs[] = array(
            'job_id' => $job_id,
            'asset_name' => $asset_name,
            'size' => $size,
        );
    }
}

// ステータスデータに assets 配列を追加
noveltool_update_download_status( 
    'in_progress', 
    '', 
    '', 
    'background_processing',
    array(
        'use_background' => true,
        'assets' => array_map( function( $job ) {
            return array(
                'name' => $job['asset_name'],
                'status' => 'pending',
                'progress' => 0,
                'total_bytes' => $job['size'],
                'downloaded_bytes' => 0,
                'job_id' => $job['job_id'],
            );
        }, $asset_jobs ),
    )
);
```

#### 2. アセット単位のジョブ処理（高優先度）
**対象関数**: 
- `noveltool_job_download_sample_images()` (lines 1314-1390)
- `noveltool_job_verify_sample_images()` (lines 1392-1455)
- `noveltool_job_extract_sample_images()` (lines 1457-1520)

**変更内容**:
- 各ジョブでメタデータから `asset_name` を取得
- 一時ディレクトリを `wp_upload_dir()['basedir'] . '/noveltool-temp/job-' . $job_id` に作成
- 抽出は一時ディレクトリに対して実行
- 抽出完了後、`realpath()` 検証を行ってから最終ディレクトリに移動
- 移動失敗時はロールバック（移動済みファイルを削除）
- マージポリシー: デフォルトで上書き許可

```php
// noveltool_job_extract_sample_images の変更例
function noveltool_job_extract_sample_images( $job ) {
    $job_id = $job['id'];
    $meta = $job['meta'];
    $asset_name = isset( $meta['asset_name'] ) ? $meta['asset_name'] : 'unknown';
    $temp_file = isset( $meta['temp_file'] ) ? $meta['temp_file'] : '';
    
    if ( ! file_exists( $temp_file ) ) {
        return new WP_Error( 'temp_file_not_found', __( 'Temporary ZIP file not found.', 'novel-game-plugin' ) );
    }
    
    // 一時抽出ディレクトリを作成
    $upload_dir = wp_upload_dir();
    $temp_extract_dir = $upload_dir['basedir'] . '/noveltool-temp/extract-' . $job_id;
    wp_mkdir_p( $temp_extract_dir );
    
    // ストリーミング抽出（一時ディレクトリに）
    $extract_result = noveltool_extract_zip_streaming( $temp_file, $temp_extract_dir );
    
    if ( is_wp_error( $extract_result ) ) {
        // 一時ディレクトリをクリーンアップ
        noveltool_recursive_delete( $temp_extract_dir );
        @unlink( $temp_file );
        return $extract_result;
    }
    
    // 最終配置先
    $final_destination = NOVEL_GAME_PLUGIN_PATH . 'assets/sample-images';
    wp_mkdir_p( $final_destination );
    
    // 一時ディレクトリから最終ディレクトリにファイルを移動（マージ）
    $moved_files = array();
    $merge_result = noveltool_merge_extracted_files( $temp_extract_dir, $final_destination, $moved_files );
    
    if ( is_wp_error( $merge_result ) ) {
        // ロールバック: 移動済みファイルを削除
        foreach ( $moved_files as $file ) {
            @unlink( $file );
        }
        noveltool_recursive_delete( $temp_extract_dir );
        @unlink( $temp_file );
        return $merge_result;
    }
    
    // クリーンアップ
    noveltool_recursive_delete( $temp_extract_dir );
    @unlink( $temp_file );
    
    // 成功を記録
    noveltool_append_job_log( $job_id, 'extract', 'completed', sprintf(
        __( 'Extracted %d files from %s', 'novel-game-plugin' ),
        count( $moved_files ),
        $asset_name
    ) );
    
    return true;
}

// 新規ヘルパー関数
function noveltool_merge_extracted_files( $source_dir, $destination_dir, &$moved_files ) {
    global $wp_filesystem;
    
    if ( ! $wp_filesystem ) {
        WP_Filesystem();
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ( $files as $file ) {
        $source_path = $file->getRealPath();
        $relative_path = substr( $source_path, strlen( $source_dir ) + 1 );
        $target_path = $destination_dir . '/' . $relative_path;
        
        // realpath() 検証
        $target_dir = dirname( $target_path );
        wp_mkdir_p( $target_dir );
        
        $real_target_dir = realpath( $target_dir );
        $real_destination = realpath( $destination_dir );
        
        if ( $real_target_dir === false || strpos( $real_target_dir, $real_destination ) !== 0 ) {
            return new WP_Error( 'traversal_detected', sprintf(
                __( 'Directory traversal detected: %s', 'novel-game-plugin' ),
                $relative_path
            ) );
        }
        
        // ファイルを移動（または上書き）
        if ( ! $wp_filesystem->move( $source_path, $target_path, true ) ) {
            // フォールバック: copy + unlink
            if ( $wp_filesystem->copy( $source_path, $target_path, true ) ) {
                @unlink( $source_path );
            } else {
                return new WP_Error( 'move_failed', sprintf(
                    __( 'Failed to move file: %s', 'novel-game-plugin' ),
                    $relative_path
                ) );
            }
        }
        
        $moved_files[] = $target_path;
    }
    
    return true;
}
```

#### 3. 集約進捗と状態管理（高優先度）
**対象関数**: 
- `noveltool_update_download_status()` (lines 397-451)
- `noveltool_api_sample_images_status()` (lines 646-714)

**変更内容**:
```php
// noveltool_update_download_status の変更
function noveltool_update_download_status( $status, $message = '', $code = '', $stage = '', $meta = array() ) {
    $data = get_option( 'noveltool_sample_images_download_status_data', array() );
    
    // 既存フィールドを更新
    $data['status'] = in_array( $status, array( 'idle', 'in_progress', 'completed', 'failed' ), true ) ? $status : 'idle';
    $data['message'] = sanitize_text_field( $message );
    $data['code'] = sanitize_text_field( $code );
    $data['stage'] = sanitize_text_field( $stage );
    $data['timestamp'] = time();
    
    // メタ情報を許可リストでサニタイズ
    $allowed_meta_keys = array( 'http_code', 'stage_detail', 'retry_count', 'use_background', 'job_id', 'progress', 'current_step', 'assets' );
    foreach ( $allowed_meta_keys as $key ) {
        if ( isset( $meta[ $key ] ) ) {
            if ( $key === 'assets' && is_array( $meta[ $key ] ) ) {
                // assets 配列を検証してサニタイズ
                $data['assets'] = array_map( function( $asset ) {
                    return array(
                        'name' => sanitize_text_field( $asset['name'] ?? '' ),
                        'status' => sanitize_text_field( $asset['status'] ?? 'pending' ),
                        'progress' => max( 0, min( 100, intval( $asset['progress'] ?? 0 ) ) ),
                        'downloaded_bytes' => isset( $asset['downloaded_bytes'] ) ? absint( $asset['downloaded_bytes'] ) : null,
                        'total_bytes' => isset( $asset['total_bytes'] ) ? absint( $asset['total_bytes'] ) : null,
                        'message' => isset( $asset['message'] ) ? sanitize_text_field( $asset['message'] ) : '',
                        'job_id' => isset( $asset['job_id'] ) ? absint( $asset['job_id'] ) : null,
                    );
                }, $meta[ $key ] );
            } elseif ( $key === 'progress' ) {
                $data[ $key ] = max( 0, min( 100, intval( $meta[ $key ] ) ) );
            } elseif ( in_array( $key, array( 'http_code', 'retry_count' ), true ) ) {
                $data[ $key ] = absint( $meta[ $key ] );
            } else {
                $data[ $key ] = sanitize_text_field( $meta[ $key ] );
            }
        }
    }
    
    // overall_progress を計算
    if ( isset( $data['assets'] ) && is_array( $data['assets'] ) && ! empty( $data['assets'] ) ) {
        $total_weight = 0;
        $weighted_progress = 0;
        
        foreach ( $data['assets'] as $asset ) {
            $weight = isset( $asset['total_bytes'] ) && $asset['total_bytes'] > 0 ? $asset['total_bytes'] : 1;
            $total_weight += $weight;
            $weighted_progress += ( $asset['progress'] / 100 ) * $weight;
        }
        
        $data['overall_progress'] = $total_weight > 0 ? round( ( $weighted_progress / $total_weight ) * 100 ) : 0;
    } else {
        $data['overall_progress'] = isset( $data['progress'] ) ? $data['progress'] : 0;
    }
    
    update_option( 'noveltool_sample_images_download_status_data', $data, false );
}

// noveltool_api_sample_images_status の変更
function noveltool_api_sample_images_status() {
    // ... 権限チェック ...
    
    $status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    
    $response = array(
        'status' => isset( $status_data['status'] ) ? $status_data['status'] : 'idle',
        'message' => isset( $status_data['message'] ) ? $status_data['message'] : '',
        'code' => isset( $status_data['code'] ) ? $status_data['code'] : '',
        'stage' => isset( $status_data['stage'] ) ? $status_data['stage'] : '',
        'timestamp' => isset( $status_data['timestamp'] ) ? $status_data['timestamp'] : 0,
        'progress' => isset( $status_data['progress'] ) ? intval( $status_data['progress'] ) : 0,
        'overall_progress' => isset( $status_data['overall_progress'] ) ? intval( $status_data['overall_progress'] ) : 0,
    );
    
    // バックグラウンド処理情報を追加
    if ( isset( $status_data['use_background'] ) && $status_data['use_background'] ) {
        $response['use_background'] = true;
        $response['job_id'] = isset( $status_data['job_id'] ) ? absint( $status_data['job_id'] ) : null;
        $response['current_step'] = isset( $status_data['current_step'] ) ? $status_data['current_step'] : '';
        
        // 個別アセット情報を追加
        if ( isset( $status_data['assets'] ) && is_array( $status_data['assets'] ) ) {
            $response['assets'] = array_map( function( $asset ) {
                return array(
                    'name' => $asset['name'],
                    'status' => $asset['status'],
                    'progress' => intval( $asset['progress'] ),
                    'downloaded_bytes' => $asset['downloaded_bytes'],
                    'total_bytes' => $asset['total_bytes'],
                    'message' => isset( $asset['message'] ) ? $asset['message'] : '',
                );
            }, $status_data['assets'] );
        }
    }
    
    // エラー情報は非機密情報のみ
    $error_data = get_option( 'noveltool_sample_images_download_error', array() );
    if ( ! empty( $error_data ) && is_array( $error_data ) ) {
        $response['error'] = array(
            'message' => isset( $error_data['message'] ) ? sanitize_text_field( $error_data['message'] ) : '',
            'code' => isset( $error_data['code'] ) ? sanitize_text_field( $error_data['code'] ) : '',
            'stage' => isset( $error_data['stage'] ) ? sanitize_text_field( $error_data['stage'] ) : '',
        );
        
        // 許可リストのメタ情報のみ追加
        if ( isset( $error_data['meta'] ) && is_array( $error_data['meta'] ) ) {
            $allowed_keys = array( 'http_code', 'stage_detail', 'retry_count' );
            $response['error']['meta'] = array();
            foreach ( $allowed_keys as $key ) {
                if ( isset( $error_data['meta'][ $key ] ) ) {
                    $response['error']['meta'][ $key ] = sanitize_text_field( $error_data['meta'][ $key ] );
                }
            }
        }
    }
    
    return rest_ensure_response( $response );
}
```

#### 4. UI の更新（中優先度）
**対象ファイル**: `js/admin-sample-images-prompt.js`

**変更内容**:
- フロント側に「個別アセットの進捗バー」と「全体進捗バー」を表示
- ステータスポーリングで `assets` 配列と `overall_progress` を取得
- 各アセットの進捗を個別に表示（名前、サイズ、進捗バー）
- エスケープと長さ制限を適用

```javascript
// updateProgress の変更例
function updateProgress(data) {
    var progressContainer = $('#noveltool-download-progress');
    
    if (!progressContainer.length) {
        return;
    }
    
    // 全体進捗バー
    var overallProgress = data.overall_progress || data.progress || 0;
    progressContainer.find('.noveltool-progress-bar').css('width', overallProgress + '%');
    progressContainer.find('.noveltool-progress-text').text(overallProgress + '%');
    
    // 個別アセット進捗（assets 配列がある場合）
    if (data.assets && Array.isArray(data.assets) && data.assets.length > 0) {
        var assetsContainer = progressContainer.find('.noveltool-assets-progress');
        
        if (!assetsContainer.length) {
            assetsContainer = $('<div>', {
                class: 'noveltool-assets-progress',
                css: { marginTop: '15px' }
            });
            progressContainer.append(assetsContainer);
        }
        
        assetsContainer.empty();
        
        // 各アセットの進捗を表示
        data.assets.forEach(function(asset) {
            var assetName = $('<div>').text(asset.name).html(); // エスケープ
            var assetProgress = Math.max(0, Math.min(100, parseInt(asset.progress) || 0));
            var assetStatus = $('<div>').text(asset.status).html(); // エスケープ
            
            var sizeText = '';
            if (asset.downloaded_bytes !== null && asset.total_bytes !== null && asset.total_bytes > 0) {
                var downloadedMB = (asset.downloaded_bytes / (1024 * 1024)).toFixed(1);
                var totalMB = (asset.total_bytes / (1024 * 1024)).toFixed(1);
                sizeText = ' (' + downloadedMB + ' / ' + totalMB + ' MB)';
            }
            
            var assetItem = $('<div>', {
                class: 'noveltool-asset-item',
                css: { marginBottom: '10px', padding: '8px', background: '#f5f5f5', borderRadius: '3px' }
            });
            
            var assetHeader = $('<div>', {
                html: '<strong>' + assetName + '</strong> - ' + assetStatus + sizeText,
                css: { marginBottom: '5px', fontSize: '12px' }
            });
            
            var assetProgressBar = $('<div>', {
                class: 'noveltool-progress-bar-container',
                css: { height: '8px', background: '#ddd', borderRadius: '4px', overflow: 'hidden' }
            });
            
            var assetProgressFill = $('<div>', {
                class: 'noveltool-progress-bar',
                css: { width: assetProgress + '%', height: '100%', background: '#0073aa', transition: 'width 0.3s' }
            });
            
            assetProgressBar.append(assetProgressFill);
            assetItem.append(assetHeader).append(assetProgressBar);
            
            if (asset.message) {
                var assetMessage = $('<div>', {
                    text: asset.message.substring(0, 100), // 長さ制限
                    css: { marginTop: '3px', fontSize: '11px', color: '#666' }
                });
                assetItem.append(assetMessage);
            }
            
            assetsContainer.append(assetItem);
        });
    }
    
    // ステータスメッセージの更新
    var statusMessage = data.message || '';
    if (data.current_step) {
        var stepLabels = {
            'download': novelToolSampleImages.strings.stageDownload || 'Downloading',
            'verify': novelToolSampleImages.strings.stageVerify || 'Verifying',
            'extract': novelToolSampleImages.strings.stageExtract || 'Extracting'
        };
        statusMessage = stepLabels[data.current_step] || data.current_step;
    }
    
    progressContainer.find('.noveltool-status-message').text(statusMessage.substring(0, 200)); // 長さ制限
}
```

#### 5. ジョブ進捗の更新（高優先度）
**対象関数**: 各ジョブ処理関数内

**変更内容**:
- ダウンロード中にバイト数を記録
- 定期的に `noveltool_update_asset_progress()` を呼び出してステータスデータを更新

```php
function noveltool_update_asset_progress( $job_id, $asset_name, $status, $progress, $downloaded_bytes = null, $message = '' ) {
    $status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    
    if ( ! isset( $status_data['assets'] ) || ! is_array( $status_data['assets'] ) ) {
        return;
    }
    
    // 該当アセットを更新
    foreach ( $status_data['assets'] as &$asset ) {
        if ( $asset['name'] === $asset_name && $asset['job_id'] === $job_id ) {
            $asset['status'] = sanitize_text_field( $status );
            $asset['progress'] = max( 0, min( 100, intval( $progress ) ) );
            if ( $downloaded_bytes !== null ) {
                $asset['downloaded_bytes'] = absint( $downloaded_bytes );
            }
            if ( $message ) {
                $asset['message'] = sanitize_text_field( $message );
            }
            break;
        }
    }
    
    // overall_progress を再計算
    $total_weight = 0;
    $weighted_progress = 0;
    
    foreach ( $status_data['assets'] as $asset ) {
        $weight = isset( $asset['total_bytes'] ) && $asset['total_bytes'] > 0 ? $asset['total_bytes'] : 1;
        $total_weight += $weight;
        $weighted_progress += ( $asset['progress'] / 100 ) * $weight;
    }
    
    $status_data['overall_progress'] = $total_weight > 0 ? round( ( $weighted_progress / $total_weight ) * 100 ) : 0;
    
    update_option( 'noveltool_sample_images_download_status_data', $status_data, false );
}
```

## セキュリティとバリデーション

### 実装済み
- ✅ エスケープ処理（`sanitize_text_field()`, `esc_html()`）
- ✅ `realpath()` による正規化パス検証
- ✅ 許可リストによるメタ情報フィルタリング
- ✅ 範囲制限（progress: 0-100）

### 追加予定
- 一時ディレクトリのパーミッション設定（0755）
- ファイルサイズ上限チェック
- マージ時の上書き確認オプション

## テスト観点

### 単一アセット（後方互換性）
- [ ] 既存の単一ZIPダウンロードが動作すること
- [ ] エラーハンドリングが正常に機能すること

### 複数アセット
- [ ] 複数ZIPの自動検出が動作すること
- [ ] 各アセットが独立してダウンロード・検証・抽出されること
- [ ] 一時ディレクトリが正しく作成・削除されること
- [ ] ファイルのマージが正しく動作すること（上書き含む）
- [ ] 進捗が正しく集約されること（バイト重み付け）

### エラーケース
- [ ] アセット検出失敗時の挙動
- [ ] ダウンロード失敗時のロールバック
- [ ] 抽出失敗時のロールバック
- [ ] マージ失敗時のロールバック（移動済みファイル削除）

### UI
- [ ] 個別アセット進捗が表示されること
- [ ] 全体進捗が正しく計算されること
- [ ] エラーメッセージが適切に表示されること
- [ ] 長い文字列が制限されること

## 実装の優先順位

1. **高優先度** (即座に実装)
   - 複数アセット用のジョブ化
   - アセット単位のジョブ処理
   - 集約進捗と状態管理

2. **中優先度** (次のコミット)
   - UI の更新
   - ジョブ進捗の更新

3. **低優先度** (後で実装可)
   - 上書き確認オプション
   - 詳細なエラーメッセージ

## 次のステップ

1. `noveltool_perform_sample_images_download()` を更新して複数アセットをジョブ化
2. ジョブ処理関数を更新して一時ディレクトリに抽出
3. マージ処理を実装（`noveltool_merge_extracted_files()`）
4. ステータス API を更新して `assets` 配列を返す
5. UI を更新して個別進捗を表示
6. テストと検証

## 参考
- Issue #220: サンプル画像ダウンロードの耐障害性強化
- PR #224: リリースビルドの分割（プラグイン本体とサンプル画像）
- コメント #3839296992: 複数アセット対応の必要性について
