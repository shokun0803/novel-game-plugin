# コメント対応による修正完了レポート - v1.4.0

## コミット情報
- **コミットハッシュ**: 5136ba1
- **日付**: 2026-01-29

## 実装した修正

### 1. ストリーミング抽出の真のストリーミング化 ✅

**問題点**: チャンクを `$content` 変数に連結してから一括書き込みしていたため、大きな個別ファイルでメモリを消費

**修正内容**:
- 新関数 `noveltool_stream_write_file()` を追加（Line 338-447）
- チャンクごとに `fwrite()` で直接ファイルへ書き込み
- WP_Filesystem の `direct` メソッドではPHPのファイルハンドルで直接書き込み
- FTP/SSH 等のリモートメソッドでは一時ファイル経由で処理

```php
// 真のストリーミング書き込み
$fp = @fopen( $target_path, 'wb' );
while ( ! feof( $stream ) ) {
    $chunk = fread( $stream, $chunk_size );
    fwrite( $fp, $chunk );
}
fclose( $fp );
```

**ファイル**: `includes/sample-images-downloader.php`
**関数**: `noveltool_stream_write_file()` (新規)、`noveltool_extract_zip_streaming()` (修正)

---

### 2. realpath チェックと新規ディレクトリ作成の順序修正 ✅

**問題点**: 親ディレクトリが存在しないと `realpath()` が false になりスキップされる

**修正内容**:
- 親ディレクトリ作成を `realpath()` チェックの前に移動
- `ltrim()` でパスの先頭スラッシュを正規化

```php
// 1. target_path を構築
$target_path = $destination . '/' . ltrim( $clean_path, '/' );

// 2. 親ディレクトリを作成
$target_dir = dirname( $target_path );
if ( ! $wp_filesystem->is_dir( $target_dir ) ) {
    $wp_filesystem->mkdir( $target_dir, FS_CHMOD_DIR, true );
}

// 3. realpath() で検証
$real_target = realpath( $target_dir );
$real_destination = realpath( $destination );
if ( $real_target === false || strpos( $real_target, $real_destination ) !== 0 ) {
    continue;
}
```

**ファイル**: `includes/sample-images-downloader.php`
**関数**: `noveltool_extract_zip_streaming()` (修正)

---

### 3. バックグラウンドジョブの履歴削除を安全化 ✅

**問題点**: `delete_option('noveltool_background_jobs')` で全履歴を即削除、デバッグ・監査が困難

**修正内容**:
- 新関数 `noveltool_append_job_log()` を追加（最新50件のログを保持）
- 新関数 `noveltool_cleanup_completed_jobs()` を追加（1時間後に自動削除）
- 新オプション `noveltool_auto_cleanup_jobs`（デフォルト: true）で自動削除を制御
- 完了時にジョブ情報をログに保存

```php
// ジョブログに保存
$log_entry = array(
    'type'         => 'job_completed',
    'job_id'       => $extract_job_id,
    'completed_at' => time(),
    'status'       => 'success',
);
noveltool_append_job_log( $log_entry );

// 自動クリーンアップ（オプションで制御）
$auto_cleanup = get_option( 'noveltool_auto_cleanup_jobs', true );
if ( $auto_cleanup ) {
    noveltool_cleanup_completed_jobs();
}
```

**ファイル**: `includes/sample-images-downloader.php`
**関数**: `noveltool_append_job_log()` (新規)、`noveltool_cleanup_completed_jobs()` (新規)、`noveltool_check_background_job_extract()` (修正)

---

### 4. WP Cron 依存のドキュメントと抽象化フック ✅

**問題点**: WP Cron の遅延について説明がなく、将来的な Action Scheduler 導入の道筋が不明確

**修正内容**:

#### ドキュメント追加
- WP Cron の制限事項を明記
  - アクセス少ないサイトでの遅延
  - サーバー cron 設定が無効な場合の影響
- 対処方法を4つ記載
  1. サーバー cron の設定（推奨）
  2. WP Crontrol プラグインの利用
  3. 処理遅延時の対処（リセット）
  4. 従来の同期処理への切替
- Action Scheduler への移行方法を記載

#### 抽象化フック追加
```php
// カスタムスケジューラのフィルター
$use_custom_scheduler = apply_filters( 'noveltool_use_custom_job_scheduler', false, $job_id );

if ( $use_custom_scheduler ) {
    // Action Scheduler 等を使う場合
    do_action( 'noveltool_schedule_custom_job', $job_id );
    return true;
}
```

**ファイル**: `docs/SAMPLE_IMAGES_DOWNLOAD.md`、`includes/sample-images-downloader.php`
**関数**: `noveltool_schedule_background_job()` (修正)

---

### 5. unzip 検出の堅牢化 ✅

**問題点**: `which` コマンドが使えない環境（Windows 等）で unzip 検出が失敗

**修正内容**:
- 新関数 `noveltool_detect_unzip_command()` を追加
- 3つの検出方式を順次試行
  1. `which unzip` （Unix/Linux）
  2. `command -v unzip` （より汎用的）
  3. `unzip -v` （直接実行、Windows 対応）

```php
function noveltool_detect_unzip_command() {
    // 方法1: which
    @exec( 'which unzip 2>/dev/null', $output, $return_var );
    if ( $return_var === 0 && ! empty( $output ) ) {
        return true;
    }
    
    // 方法2: command -v
    @exec( 'command -v unzip 2>/dev/null', $output, $return_var );
    if ( $return_var === 0 && ! empty( $output ) ) {
        return true;
    }
    
    // 方法3: unzip -v
    @exec( 'unzip -v 2>&1', $output, $return_var );
    if ( ! empty( $output ) ) {
        $output_str = implode( ' ', $output );
        if ( stripos( $output_str, 'unzip' ) !== false ) {
            return true;
        }
    }
    
    return false;
}
```

**ファイル**: `includes/sample-images-downloader.php`
**関数**: `noveltool_detect_unzip_command()` (新規)、`noveltool_detect_extraction_capabilities()` (修正)

---

### 6. REST API とステータスデータの整合性確認 ✅

**問題点**: ステータス値やジョブ情報のバリデーションが不十分

**修正内容**:
- ステータス値のホワイトリスト検証
- progress の範囲制限（0-100）
- current_step の許可リスト検証
- すべてのフィールドに適切なサニタイゼーション

```php
// ステータス値のバリデーション
$valid_statuses = array( 'not_started', 'in_progress', 'completed', 'failed' );
if ( ! in_array( $status, $valid_statuses, true ) ) {
    error_log( "NovelGamePlugin: Invalid status value: {$status}" );
    $status = 'failed';
}

// progress の範囲制限
$progress = intval( $job_info['progress'] );
$status_data['progress'] = max( 0, min( 100, $progress ) );

// current_step のバリデーション
$valid_steps = array( 'download', 'verify', 'extract' );
$step = sanitize_text_field( $job_info['current_step'] );
$status_data['current_step'] = in_array( $step, $valid_steps, true ) ? $step : '';
```

**ファイル**: `includes/sample-images-downloader.php`
**関数**: `noveltool_update_download_status()` (修正)

---

## 変更サマリー

- **追加関数**: 4個
  - `noveltool_stream_write_file()`
  - `noveltool_append_job_log()`
  - `noveltool_cleanup_completed_jobs()`
  - `noveltool_detect_unzip_command()`
- **修正関数**: 5個
  - `noveltool_extract_zip_streaming()`
  - `noveltool_schedule_background_job()`
  - `noveltool_check_background_job_extract()`
  - `noveltool_detect_extraction_capabilities()`
  - `noveltool_update_download_status()`
- **追加オプション**: 2個
  - `noveltool_job_log`: ジョブ実行ログ（最新50件）
  - `noveltool_auto_cleanup_jobs`: 自動クリーンアップの有効/無効
- **追加フィルター/アクション**: 2個
  - `noveltool_use_custom_job_scheduler`: カスタムスケジューラ使用フラグ
  - `noveltool_schedule_custom_job`: カスタムスケジューラのフック

## 互換性

- すべての修正で既存挙動の破壊はありません
- 新オプションはデフォルトで適切な値が設定されています
- 追加のフィルター/アクションはオプトイン方式です

## テスト推奨事項

1. **真のストリーミング書き込み**: 大きなファイル（10MB以上）を含むZIPで memory_limit=128M 環境でテスト
2. **realpath 順序**: 深い階層のディレクトリ構造を含むZIPでテスト
3. **ジョブログ**: ダウンロード完了後に `noveltool_job_log` オプションを確認
4. **unzip 検出**: Windows 環境で unzip がインストールされている場合にテスト
5. **API バリデーション**: 不正なステータス値でAPI呼び出しを試行

## 関連ドキュメント

- `docs/SAMPLE_IMAGES_DOWNLOAD.md`: WP Cron の制限事項と対処方法を追記
- `IMPLEMENTATION_COMPLETE_V1.4.md`: 実装完了レポート

---

**実装者**: GitHub Copilot  
**レビュアー**: @shokun0803  
**日付**: 2026-01-29
