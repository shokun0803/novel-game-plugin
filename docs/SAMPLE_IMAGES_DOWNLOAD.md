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

1. インターネット接続を確認してください
2. WordPressのファイル書き込み権限を確認してください（`wp-content/plugins/novel-game-plugin/assets/` に書き込み権限が必要）
3. モーダルの「再試行」ボタンをクリックして再度ダウンロードを試みてください
4. それでも解決しない場合は、サーバーのエラーログを確認してください

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
  "status": "not_started"
}
```

**status の値**:
- `not_started`: ダウンロード未開始
- `in_progress`: ダウンロード中
- `completed`: ダウンロード完了
- `failed`: ダウンロード失敗

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
