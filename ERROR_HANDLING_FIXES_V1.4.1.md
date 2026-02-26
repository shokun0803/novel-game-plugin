# エラーハンドリングとセキュリティ強化 - 修正レポート

## コミット情報
- **コミットハッシュ**: 4d2af85
- **日付**: 2026-02-03
- **対応コメント**: #3839243802

## 実装した修正

### 1. WP_Error チェックの追加 ✅

**ファイル**: `includes/sample-images-downloader.php`  
**関数**: `noveltool_api_download_sample_images()` (lines 1810-1950)

**問題点**:
- `noveltool_perform_sample_images_download()` の戻り値を配列前提で直接参照
- WP_Error が返された場合に致命的エラーが発生する可能性

**実装内容**:
```php
// WP_Error チェック
if ( is_wp_error( $result ) ) {
    // 詳細をログに記録
    error_log( sprintf(
        'NovelGamePlugin: Sample images download failed with WP_Error - Code: %s, Message: %s',
        $error_code, $error_message
    ) );
    
    // ユーザーには簡潔なメッセージ
    return new WP_REST_Response( array(
        'success' => false,
        'message' => __( 'Sample images download failed. Please check server logs for details.', 'novel-game-plugin' ),
        'error' => array( /* 非機密情報のみ */ )
    ), 500 );
}

// 配列型チェック
if ( ! is_array( $result ) ) { /* エラー処理 */ }

// success キー存在チェック
if ( ! isset( $result['success'] ) ) { /* エラー処理 */ }
```

**効果**:
- WP_Error が返された場合でもクラッシュしない
- 詳細エラーはサーバーログに記録、ユーザーには安全なメッセージ
- 型安全性の向上

---

### 2. ファイル移動操作の堅牢化 ✅

**ファイル**: `includes/sample-images-downloader.php`  
**関数**: `noveltool_stream_write_file()` (lines 466-519)

**問題点**:
- `$wp_filesystem->move()` が存在しないか失敗する環境がある
- 失敗時に一時ファイルが残る可能性

**実装内容**:
```php
// 3段階フォールバック
$move_success = false;

// 方法1: WP_Filesystem の move()
if ( method_exists( $wp_filesystem, 'move' ) ) {
    if ( $wp_filesystem->move( $temp_file, $target_path, true ) ) {
        $move_success = true;
    }
}

// 方法2: copy() + unlink()
if ( ! $move_success && method_exists( $wp_filesystem, 'copy' ) ) {
    if ( $wp_filesystem->copy( $temp_file, $target_path, true, FS_CHMOD_FILE ) ) {
        @unlink( $temp_file );
        $move_success = true;
    }
}

// 方法3: rename() (direct method 限定)
if ( ! $move_success && $wp_filesystem->method === 'direct' ) {
    if ( @rename( $temp_file, $target_path ) ) {
        @chmod( $target_path, FS_CHMOD_FILE );
        $move_success = true;
    }
}

// すべて失敗したら一時ファイル削除
if ( ! $move_success ) {
    @unlink( $temp_file );
    return new WP_Error( 'move_error', /* メッセージ */ );
}
```

**効果**:
- FTP/SSH/direct など複数の WP_Filesystem メソッドに対応
- 一時ファイルの孤立を防止
- 環境依存を最小化

---

### 3. exec() 出力の安全な取り扱い ✅

**ファイル**: `includes/sample-images-downloader.php`  
**関数**: `noveltool_detect_unzip_command()` (lines 269-306)、unzip フォールバック (lines 640-665)

**問題点**:
- exec() の出力をユーザー向けレスポンスに含めると機密情報が露出
- 長大な出力やパス情報がユーザーに表示される

**実装内容**:

#### noveltool_detect_unzip_command()
```php
// 出力を500文字に制限
$output_str = substr( $output_str, 0, 500 );
```

#### unzip フォールバック
```php
if ( $return_var !== 0 ) {
    // 詳細はログに記録（管理者向け）
    $output_str = implode( "\n", $output );
    error_log( sprintf(
        'NovelGamePlugin: unzip command failed with exit code %d. Output: %s',
        $return_var,
        substr( $output_str, 0, 1000 )
    ) );
    
    // ユーザーには簡潔なメッセージ
    return new WP_Error(
        'unzip_error',
        __( 'Failed to extract ZIP using unzip command. Please check server logs or install PHP ZipArchive extension.', 'novel-game-plugin' )
    );
}
```

**効果**:
- コマンド出力をユーザーに露出しない
- 詳細情報は管理者ログで確認可能
- セキュリティリスクの低減

---

### 4. REST API レスポンスの非機密化 ✅

**ファイル**: `includes/sample-images-downloader.php`  
**関数**: `noveltool_api_sample_images_status()` (lines 1984-2007)、`noveltool_api_download_sample_images()` (lines 1934-1946)

**問題点**:
- エラーメタ情報を無制限に返却していた
- 内部ログや機密情報が含まれる可能性

**実装内容**:
```php
// 許可リストでメタ情報をフィルタリング
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
```

**効果**:
- 非機密情報のみを返却（http_code, stage_detail, retry_count）
- すべての値を `sanitize_text_field()` でサニタイズ
- 機密情報の露出を防止

---

## 変更サマリー

### セキュリティ強化
- ✅ exec() 出力をユーザーに露出しない（ログに分離）
- ✅ REST API レスポンスを非機密情報のみに制限
- ✅ すべてのエラーメッセージをサニタイズ

### エラーハンドリング改善
- ✅ WP_Error チェックを追加
- ✅ 型チェックとキー存在確認を強化
- ✅ ファイル移動操作の3段階フォールバック

### ログ管理
- ✅ 詳細情報は `error_log()` でサーバーログに記録
- ✅ ユーザーには簡潔で安全なメッセージのみ返却
- ✅ 出力長を制限（500-1000文字）

### 互換性
- ✅ 既存挙動を維持
- ✅ 複数の WP_Filesystem メソッドに対応
- ✅ 一時ファイルの孤立を防止

## テスト推奨事項

1. **WP_Error テスト**:
   - `noveltool_perform_sample_images_download()` が WP_Error を返すケースを想定
   - API が適切にエラーレスポンスを返すことを確認

2. **ファイル移動テスト**:
   - FTP/SSH/direct の各 WP_Filesystem メソッドで動作確認
   - move() が存在しない環境でのフォールバックテスト

3. **exec() 出力テスト**:
   - unzip コマンドが失敗した場合にユーザーに機密情報が露出しないことを確認
   - サーバーログに詳細が記録されることを確認

4. **REST API テスト**:
   - エラーレスポンスに許可リスト外のメタ情報が含まれないことを確認
   - すべてのフィールドがサニタイズされていることを確認

---

**実装者**: GitHub Copilot  
**レビュアー**: @shokun0803  
**日付**: 2026-02-03
