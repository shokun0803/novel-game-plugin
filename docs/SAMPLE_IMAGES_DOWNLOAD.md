# サンプル画像ダウンロード機能ガイド

## 概要

本プラグインでは、配布パッケージの軽量化のため、サンプルゲームで使用する画像ファイルを初回アクセス時にGitHub Releasesからダウンロードする仕組みを採用しています。

## ユーザー向け情報

### 初回アクセス時の動作

1. プラグインを有効化後、初めて「マイゲーム」画面にアクセスすると、サンプル画像のダウンロードを促すモーダルが表示されます
2. 「ダウンロード」ボタンをクリックすると、GitHubから自動的にサンプル画像（約15MB）がダウンロードされます
3. ダウンロード中は進捗バー（プログレスバー）が表示され、現在の状態（接続中/ダウンロード中/検証中/展開中）がリアルタイムで確認できます
4. ダウンロードには数分かかる場合があります（通信速度に依存）
5. ダウンロード完了後、サンプルゲームが正常に表示されるようになります

### 進捗表示について

ダウンロード中は以下の段階的なステータスが表示されます：

- **接続中...**: サーバーへの接続を確立しています
- **ダウンロード中...**: サンプル画像をダウンロードしています
- **検証中...**: ダウンロードしたファイルの整合性を確認しています
- **展開中...**: ファイルを展開し、適切な場所に配置しています
- **完了**: すべての処理が正常に完了しました

### ダウンロードをスキップする場合

- 「後で」または「キャンセル」ボタンをクリックすることで、ダウンロードを延期できます
- サンプルゲームを使用しない場合は、ダウンロードする必要はありません

### トラブルシューティング

#### ダウンロードに失敗した場合

サンプル画像のダウンロードに失敗する主な原因と対処法：

**1. インターネット接続の問題**
- 症状: 「Failed to download sample images: HTTP status code: 502」のようなエラーメッセージ
- 対処法: インターネット接続を確認し、時間をおいて再試行してください

**2. ファイル書き込み権限の問題**
- 症状: 「Destination directory is not writable」エラー
- 対処法: 以下のコマンドで書き込み権限を設定してください
  ```bash
  chmod -R 755 wp-content/plugins/novel-game-plugin/assets/
  ```

**3. チェックサム検証の失敗**
- 症状: 「Checksum verification failed」エラー
- 対処法: ダウンロード中に通信が切断された可能性があります。再試行してください

**4. タイムアウトエラー**
- 症状: 「Download timeout」エラーまたはダウンロードが進行中のまま30分以上経過
- 対処法: 
  - サーバーの php.ini で max_execution_time と memory_limit を確認
  - 推奨設定: max_execution_time=300, memory_limit=256M
  - WordPressの管理画面から再試行ボタンをクリック（自動的にステータスがリセットされます）

**5. ダウンロードが「進行中」のまま動かない**
- 症状: 「Download already in progress」エラーが表示され、再試行できない
- 対処法: 
  - 30分以上経過すると自動的にステータスがリセットされます
  - 手動でリセットする場合は、以下のコマンドをWordPress管理画面またはデータベースで実行:
    ```php
    delete_option('noveltool_sample_images_download_status');
    delete_option('noveltool_sample_images_download_status_data');
    delete_option('noveltool_sample_images_download_error');
    ```

**6. GitHub APIレート制限**
- 症状: 「Failed to fetch release info: HTTP status code: 403」
- 対処法: GitHub APIのレート制限に達した可能性があります。1時間待ってから再試行してください

#### エラーログの確認方法

詳細なエラー情報は以下の場所で確認できます：

1. **モーダル内の詳細エラー表示**: エラー発生時にモーダル内の「詳しいエラーを確認」ボタンをクリックすると、サーバー側で記録された詳細なエラーメッセージとタイムスタンプが表示されます
2. **WordPressデバッグログ**: `wp-content/debug.log`（WP_DEBUG が有効な場合）
3. **WordPressオプション**: 管理画面 > ツール > サイトヘルス > 情報 から確認
   - `noveltool_sample_images_download_status`: 現在のダウンロード状態
   - `noveltool_sample_images_download_error`: 最後のエラーメッセージとタイムスタンプ

#### 再試行の方法

1. モーダルの「再試行」ボタンをクリック（自動的にステータスがリセットされ、ダウンロードが再開されます）
2. それでも解決しない場合は、サーバーのエラーログを確認してください
3. モーダルが表示されない場合は、「マイゲーム」画面のバナーから「サンプル画像をダウンロード」ボタンをクリック

#### 手動インストール

ダウンロードが何度も失敗する場合は、手動でインストールすることもできます：

1. [GitHubリリースページ](https://github.com/shokun0803/novel-game-plugin/releases/latest)から `novel-game-plugin-sample-images-vX.X.X.zip` をダウンロード
2. ZIPファイルを展開
3. 展開したファイルを `wp-content/plugins/novel-game-plugin/assets/sample-images/` ディレクトリに配置

## 開発者向け情報

### 実装の概要

サンプル画像ダウンロード機能は以下のコンポーネントで構成されています：

- **PHP サーバーサイド処理**: `includes/sample-images-downloader.php`
- **JavaScript UI**: `js/admin-sample-images-prompt.js`
- **CSS スタイル**: `css/admin-sample-images-prompt.css`

### REST API エンドポイント

#### ダウンロード開始

```
POST /wp-json/novel-game-plugin/v1/sample-images/download
```

**権限**: `manage_options`（管理者のみ）

**レスポンス**:
```json
{
  "success": true,
  "message": "Sample images downloaded and installed successfully."
}
```

#### ダウンロード状況確認

```
GET /wp-json/novel-game-plugin/v1/sample-images/status
```

**権限**: `manage_options`（管理者のみ）

**レスポンス**:
```json
{
  "exists": false,
  "status": "not_started",
  "error": {
    "message": "Failed to download sample images: HTTP status code: 502",
    "timestamp": 1703299200
  }
}
```

**status の値**:
- `not_started`: ダウンロード未開始
- `in_progress`: ダウンロード中
- `completed`: ダウンロード完了
- `failed`: ダウンロード失敗

**error フィールド**（失敗時のみ）:
- `message`: エラーメッセージ
- `timestamp`: エラー発生時刻（UNIXタイムスタンプ）

#### ダウンロードステータスのリセット

```
POST /wp-json/novel-game-plugin/v1/sample-images/reset-status
```

**権限**: `manage_options`（管理者のみ）

**レスポンス**:
```json
{
  "success": true,
  "message": "Download status has been reset."
}
```

**用途**: ダウンロードが「進行中」のまま動かなくなった場合などにステータスをリセットします

### GitHub Releases との連携

#### アセットの命名規約

リリース時には以下の命名規約でアセットを作成してください：

1. **ZIPファイル**: `novel-game-plugin-sample-images-vX.X.X.zip`
2. **チェックサムファイル（オプション）**: `novel-game-plugin-sample-images-vX.X.X.zip.sha256`

#### チェックサムファイルの生成

```bash
sha256sum novel-game-plugin-sample-images-v1.3.0.zip > novel-game-plugin-sample-images-v1.3.0.zip.sha256
```

### セキュリティ

- すべてのダウンロード処理は管理者権限（`manage_options`）を持つユーザーのみ実行可能
- REST API エンドポイントはWP Nonce による認証を実装
- ダウンロードしたファイルは SHA256 チェックサムで検証（チェックサムファイルが存在する場合）
- ファイル展開は WordPress Filesystem API を使用

### オプション

以下の WordPress オプションがプラグインで使用されます：

- `noveltool_sample_images_downloaded`: サンプル画像がダウンロード済みかどうか（boolean）
- `noveltool_sample_images_download_status`: ダウンロード状況（`not_started`, `in_progress`, `completed`, `failed`）
- `noveltool_sample_images_download_status_data`: ダウンロード状況の詳細情報（v1.4.0 でジョブ情報を追加）
  ```php
  array(
      'status'       => 'in_progress',
      'timestamp'    => 1703299200,
      // v1.4.0 で追加されたフィールド（バックグラウンド処理時のみ）
      'job_id'       => 'job_abc123',
      'progress'     => 50,          // 進捗パーセンテージ（0-100）
      'current_step' => 'verify',    // 'download', 'verify', 'extract'
      'use_background' => true       // バックグラウンド処理を使用しているか
  )
  ```
- `noveltool_sample_images_download_error`: 最後のエラー情報
  ```php
  array(
      'code'      => 'ERR-NO-EXT',
      'message'   => 'Server does not support ZIP extraction',
      'stage'     => 'environment_check',
      'timestamp' => 1703299200,
      'meta'      => array(           // 非機密情報のみ
          'http_code'    => 502,
          'stage_detail' => 'extraction_check',
          'retry_count'  => 1
      )
  )
  ```
- `noveltool_background_jobs`: バックグラウンドジョブの情報（v1.4.0 で追加）
  ```php
  array(
      'job_abc123' => array(
          'id'         => 'job_abc123',
          'type'       => 'download',   // 'download', 'verify', 'extract'
          'status'     => 'completed',  // 'pending', 'in_progress', 'completed', 'failed'
          'data'       => array(),      // ジョブ固有のデータ
          'created_at' => 1703299200,
          'updated_at' => 1703299300,
          'attempts'   => 1,
          'error'      => null
      )
  )
  ```
- `noveltool_use_streaming_extraction`: ストリーミング抽出を使用するか（boolean、デフォルト: true、v1.4.0 で追加）
- `noveltool_use_background_processing`: バックグラウンド処理を使用するか（boolean、デフォルト: true、v1.4.0 で追加）

### ユーザーメタ

- `noveltool_sample_images_prompt_dismissed`: ユーザーがプロンプトを非表示にしたかどうか（boolean）

### 関数リファレンス

#### `noveltool_sample_images_exists()`

サンプル画像ディレクトリが存在し、空でないかチェックします。

**戻り値**: `bool`

#### `noveltool_get_latest_release_info()`

GitHub Releases API から最新のリリース情報を取得します。

**戻り値**: `array|WP_Error`

#### `noveltool_find_sample_images_asset( $release_data )`

リリースデータからサンプル画像 ZIP アセットを探します。

**パラメータ**:
- `$release_data` (array): リリースデータ

**戻り値**: `array|null`

#### `noveltool_download_sample_images_zip( $download_url )`

サンプル画像 ZIP をダウンロードします。

**パラメータ**:
- `$download_url` (string): ダウンロード URL

**戻り値**: `string|WP_Error` - 一時ファイルのパスまたはエラー

#### `noveltool_verify_checksum( $file_path, $expected_checksum )`

SHA256 チェックサムを検証します。

**パラメータ**:
- `$file_path` (string): ファイルパス
- `$expected_checksum` (string): 期待されるチェックサム

**戻り値**: `bool`

#### `noveltool_extract_zip( $zip_file, $destination )`

ZIP ファイルを展開します。v1.4.0 以降はデフォルトでストリーミング抽出を使用します。

**パラメータ**:
- `$zip_file` (string): ZIP ファイルのパス
- `$destination` (string): 展開先ディレクトリ

**戻り値**: `bool|WP_Error`

#### `noveltool_detect_extraction_capabilities()`

サーバー環境の抽出機能を検出します。v1.4.0 で追加。

**戻り値**: `array` - 以下のキーを含む配列
- `has_ziparchive` (bool): ZipArchive 拡張の有無
- `has_exec` (bool): exec 関数の利用可否
- `has_unzip` (bool): unzip コマンドの利用可否
- `memory_limit` (string): メモリ制限の値
- `memory_limit_mb` (int): メモリ制限の MB 値
- `recommended` (string): 推奨抽出方式（`streaming`, `unzip_command`, `standard`, `none`）

#### `noveltool_extract_zip_streaming( $zip_file, $destination )`

ZIP ファイルをストリーミング展開します。v1.4.0 で追加。

メモリ使用量を最小限に抑えるため、ファイルを1つずつ展開します。ZipArchive が利用できない場合は unzip コマンドにフォールバックします。

**パラメータ**:
- `$zip_file` (string): ZIP ファイルのパス
- `$destination` (string): 展開先ディレクトリ

**戻り値**: `bool|WP_Error`

#### `noveltool_create_background_job( $job_type, $job_data )`

バックグラウンドジョブを作成します。v1.4.0 で追加。

**パラメータ**:
- `$job_type` (string): ジョブタイプ（`NOVELTOOL_JOB_TYPE_DOWNLOAD`, `NOVELTOOL_JOB_TYPE_VERIFY`, `NOVELTOOL_JOB_TYPE_EXTRACT`）
- `$job_data` (array): ジョブデータ

**戻り値**: `string` - ジョブID

#### `noveltool_schedule_background_job( $job_id )`

バックグラウンドジョブをスケジュールします。v1.4.0 で追加。

**パラメータ**:
- `$job_id` (string): ジョブID

**戻り値**: `bool` - 成功した場合 true

#### `noveltool_perform_sample_images_download_background( $release_data, $asset, $checksum )`

サンプル画像ダウンロードをバックグラウンドで実行します。v1.4.0 で追加。

ダウンロード・検証・抽出を個別のジョブに分割し、WP Cron で順次実行します。

**パラメータ**:
- `$release_data` (array): リリースデータ
- `$asset` (array): サンプル画像アセット
- `$checksum` (string): チェックサム（オプション）

**戻り値**: `array` - `array('success' => bool, 'message' => string, 'job_id' => string)`

#### `noveltool_update_download_status( $status, $error_message = '', $error_code = '', $error_stage = '', $error_meta = array(), $job_info = array() )`

ダウンロードステータスを更新します。v1.4.0 でジョブ情報のサポートを追加。

**パラメータ**:
- `$status` (string): ステータス（`not_started`, `in_progress`, `completed`, `failed`）
- `$error_message` (string): エラーメッセージ（オプション）
- `$error_code` (string): エラーコード（オプション）
- `$error_stage` (string): エラー発生段階（オプション）
- `$error_meta` (array): エラーメタ情報（オプション）
- `$job_info` (array): ジョブ情報（v1.4.0で追加、オプション）
  - `job_id` (string): ジョブID
  - `progress` (int): 進捗パーセンテージ（0-100）
  - `current_step` (string): 現在のステップ（`download`, `verify`, `extract`）
  - `use_background` (bool): バックグラウンド処理を使用しているか

**戻り値**: なし

#### `noveltool_check_download_status_ttl()`

ダウンロードステータスの TTL（有効期限）をチェックします。30分以上 `in_progress` のままの場合は自動的に `failed` に変更します。

**戻り値**: なし

#### `noveltool_perform_sample_images_download()`

サンプル画像ダウンロードのメイン処理を実行します。

**戻り値**: `array` - `array('success' => bool, 'message' => string)`

## リリースプロセス

### サンプル画像アセットの準備

1. サンプル画像を `assets/sample-images/` ディレクトリに配置
2. ZIP ファイルを作成:
   ```bash
   cd assets
   zip -r novel-game-plugin-sample-images-v1.3.0.zip sample-images/
   ```
3. チェックサムを生成:
   ```bash
   sha256sum novel-game-plugin-sample-images-v1.3.0.zip > novel-game-plugin-sample-images-v1.3.0.zip.sha256
   ```
4. GitHub Release を作成し、両方のファイルをアセットとしてアップロード

### プラグイン配布パッケージの作成

配布パッケージからサンプル画像を除外する場合：

1. `.gitignore` に `assets/sample-images/` を追加（必要に応じて）
2. ZIP 作成時に除外:
   ```bash
   zip -r novel-game-plugin-v1.3.0.zip novel-game-plugin/ -x "*/assets/sample-images/*"
   ```

## 国際化

すべての UI 文言は翻訳可能です。翻訳ファイルは `languages/` ディレクトリに配置されます。

### 翻訳が必要な文字列

- モーダルのタイトルとメッセージ
- ボタンラベル
- エラーメッセージ
- 成功メッセージ

### POT ファイルの生成

```bash
wp i18n make-pot . languages/novel-game-plugin.pot
```

## 既知の制限事項

~~1. **サーバー環境依存**: 大きなファイルのダウンロード・展開が可能なサーバー環境が必要です~~
~~2. **タイムアウト**: ダウンロードに時間がかかる場合、PHPのタイムアウト制限に注意が必要です~~
3. **ファイル権限**: WordPress がファイルを書き込める権限が必要です

**v1.4.0 で大幅に改善**: ストリーミング抽出とバックグラウンド処理により、サーバー環境への依存を最小限に抑えました。

## 今後の改善案

- [x] バックグラウンドダウンロード（WP Cron の利用）（v1.4.0で実装済み）
- [x] ストリーミング抽出によるメモリ効率の改善（v1.4.0で実装済み）
- [x] 環境検出と早期失敗（v1.4.0で実装済み）
- [x] ダウンロード進捗バーの表示（v1.3.0で実装済み）
- [x] ダウンロード失敗時の詳細なエラーレポート（v1.3.0で実装済み）
- [ ] 複数のミラーサーバーからのダウンロード対応
- [ ] オフライン環境でのインストール方法の追加

## v1.4.0 の改善点

### ストリーミング抽出

従来は ZIP ファイル全体をメモリに展開していましたが、v1.4.0 ではファイルを1つずつストリーミング展開することでメモリ使用量を大幅に削減しました。

- `ZipArchive::getStream()` を使用したファイル単位の展開
- メモリに全データを保持しないため、低メモリ環境でも動作
- PHP ZipArchive が利用できない場合は `unzip` コマンドにフォールバック

### バックグラウンド処理

ダウンロード・検証・抽出を小さなジョブに分割し、WP Cron で順次実行することで、PHP の実行時間制限に影響されにくくなりました。

- ダウンロードジョブ: サンプル画像 ZIP をダウンロード
- 検証ジョブ: SHA256 チェックサムを検証（チェックサムファイルがある場合）
- 抽出ジョブ: ストリーミング抽出で画像を展開

各ジョブは短時間で完了し、タイムアウトやメモリ制限に依存しにくい設計です。

#### WP Cron の依存と制限

バックグラウンド処理は WordPress の WP Cron に依存しています。WP Cron はサイトへのアクセスがトリガーとなるため、以下の制限があります：

**制限事項**:
- アクセスが少ないサイトでは、ジョブの実行が遅延する可能性があります
- サーバーの cron 設定が無効な場合、ジョブが実行されません

**対処方法**:

1. **サーバー cron の設定（推奨）**:
   ```bash
   # crontab に追加
   */5 * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```
   wp-config.php に以下を追加して WP Cron を無効化:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

2. **WP Crontrol プラグインの利用**:
   - WP Cron の実行状況を監視・管理できるプラグイン
   - スケジュール済みイベントを手動実行可能

3. **処理遅延時の対処**:
   - ダウンロード開始後、5分以上進捗がない場合は「リセット」ボタンをクリック
   - リセット後、再度ダウンロードを開始してください

4. **従来の同期処理への切替**:
   ```php
   update_option( 'noveltool_use_background_processing', false );
   ```

#### 将来的な改善: Action Scheduler への移行

現在の実装では、将来的に Action Scheduler 等のより堅牢なジョブキューシステムへ移行するための抽象化ポイント（フック）を用意しています：

- `noveltool_schedule_job`: ジョブのスケジュール（`apply_filters` で置換可能）
- `noveltool_process_job`: ジョブの実行（`do_action` でフック可能）

Action Scheduler を導入する場合は、これらのフックポイントでスケジューリング方式を切り替えることができます。

### 環境検出

実行前にサーバー環境をチェックし、必要な機能が不足している場合は早期にエラーを返します。

検出項目:
- PHP ZipArchive 拡張の有無
- exec 関数と unzip コマンドの利用可否
- memory_limit の値

推奨環境:
- PHP ZipArchive 拡張またはUnzipコマンド
- memory_limit: 128MB以上（推奨: 256MB以上）

環境が不十分な場合は、具体的な対処法を含むエラーメッセージが表示されます。

### 設定オプション

管理者は以下のオプションで動作を制御できます（WordPress オプション API を使用）:

- `noveltool_use_streaming_extraction`: ストリーミング抽出を使用するか（デフォルト: true）
- `noveltool_use_background_processing`: バックグラウンド処理を使用するか（デフォルト: true）

従来の同期処理に戻す場合は、これらのオプションを false に設定してください。
