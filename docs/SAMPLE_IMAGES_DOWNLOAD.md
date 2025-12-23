# サンプル画像ダウンロード機能ガイド

## 概要

本プラグインでは、配布パッケージの軽量化のため、サンプルゲームで使用する画像ファイルを初回アクセス時にGitHub Releasesからダウンロードする仕組みを採用しています。

## ユーザー向け情報

### 初回アクセス時の動作

1. プラグインを有効化後、初めて「マイゲーム」画面にアクセスすると、サンプル画像のダウンロードを促すモーダルが表示されます
2. 「ダウンロード」ボタンをクリックすると、GitHubから自動的にサンプル画像（約15MB）がダウンロードされます
3. ダウンロードには数分かかる場合があります（通信速度に依存）
4. ダウンロード完了後、サンプルゲームが正常に表示されるようになります

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

1. **WordPressデバッグログ**: `wp-content/debug.log`（WP_DEBUG が有効な場合）
2. **WordPressオプション**: 管理画面 > ツール > サイトヘルス > 情報 から確認
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
- `noveltool_sample_images_download_status_data`: ダウンロード状況の詳細情報（タイムスタンプ付き）
  ```php
  array(
      'status'    => 'failed',
      'timestamp' => 1703299200
  )
  ```
- `noveltool_sample_images_download_error`: 最後のエラー情報
  ```php
  array(
      'message'   => 'Failed to download: HTTP 502',
      'timestamp' => 1703299200
  )
  ```

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

ZIP ファイルを展開します。

**パラメータ**:
- `$zip_file` (string): ZIP ファイルのパス
- `$destination` (string): 展開先ディレクトリ

**戻り値**: `bool|WP_Error`

#### `noveltool_update_download_status( $status, $error_message = '' )`

ダウンロードステータスを更新します。

**パラメータ**:
- `$status` (string): ステータス（`not_started`, `in_progress`, `completed`, `failed`）
- `$error_message` (string): エラーメッセージ（オプション）

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

1. **サーバー環境依存**: 大きなファイルのダウンロード・展開が可能なサーバー環境が必要です
2. **タイムアウト**: ダウンロードに時間がかかる場合、PHPのタイムアウト制限に注意が必要です
3. **ファイル権限**: WordPress がファイルを書き込める権限が必要です

## 今後の改善案

- [ ] バックグラウンドダウンロード（WP Cron / Action Scheduler の利用）
- [ ] ダウンロード進捗バーの表示
- [ ] 複数のミラーサーバーからのダウンロード対応
- [ ] オフライン環境でのインストール方法の追加
- [ ] ダウンロード失敗時の詳細なエラーレポート
