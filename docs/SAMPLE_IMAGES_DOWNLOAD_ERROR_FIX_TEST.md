# サンプル画像ダウンロードエラーハンドリング修正 - テスト計画

## テスト概要

本ドキュメントは、PR で実装したサンプル画像ダウンロードのエラーハンドリング改善に関するテスト計画を記載します。

## 実装内容のまとめ

### 1. エラー時のステータス更新の確実化
- すべてのエラーパスで `noveltool_update_download_status('failed', $error_message)` を呼び出し
- ステータスが確実に "failed" に更新されるようにした

### 2. 詳細なエラーメッセージ
- HTTPステータスコードを含むエラーメッセージ
- 権限エラーの具体的なパス表示
- チェックサム不一致の明確な説明

### 3. TTL（Time To Live）による自動復旧
- 30分以上 "in_progress" のままの場合、自動的に "failed" に変更
- `noveltool_check_download_status_ttl()` 関数を追加

### 4. エラー情報の永続化
- `noveltool_sample_images_download_error` オプションに以下を保存：
  - `message`: エラーメッセージ
  - `timestamp`: エラー発生時刻

### 5. UI の改善
- エラー時にトラブルシューティング手順を表示
- ステータスリセットAPIを呼び出してから再試行
- 新しい REST API エンドポイント: `/sample-images/reset-status`

## テストケース

### テスト1: 正常系（成功パス）

**目的**: ダウンロードが正常に完了することを確認

**手順**:
1. WordPress 管理画面にログイン（管理者権限）
2. 「マイゲーム」画面にアクセス
3. サンプル画像ダウンロードのモーダルが表示される
4. 「ダウンロード」ボタンをクリック
5. ダウンロード完了まで待機

**期待結果**:
- ステータスが "in_progress" → "completed" に遷移
- `assets/sample-images/` ディレクトリにファイルが展開される
- エラー情報（`noveltool_sample_images_download_error`）が存在しない
- 成功メッセージが表示される

### テスト2: HTTP エラー（API接続失敗）

**目的**: GitHub API への接続が失敗した場合の動作を確認

**シミュレーション方法**:
```php
// includes/sample-images-downloader.php の noveltool_get_latest_release_info() を一時的に変更
function noveltool_get_latest_release_info() {
    return new WP_Error(
        'api_error',
        'Failed to fetch release info. HTTP status code: 502'
    );
}
```

**期待結果**:
- ステータスが "failed" に更新される
- エラーメッセージ: "Failed to fetch release information: Failed to fetch release info. HTTP status code: 502"
- `noveltool_sample_images_download_error` オプションにエラー情報が保存される
- UI にエラーメッセージとトラブルシューティング手順が表示される
- 「再試行」ボタンが有効

### テスト3: ファイルシステム権限エラー

**目的**: 書き込み権限がない場合の動作を確認

**シミュレーション方法**:
```bash
chmod 555 wp-content/plugins/novel-game-plugin/assets/
```

**期待結果**:
- ステータスが "failed" に更新される
- エラーメッセージ: "Destination directory is not writable: /path/to/assets. Please check file permissions."
- UI にエラーメッセージと具体的な対処手順が表示される
- 「再試行」ボタンが有効

**クリーンアップ**:
```bash
chmod 755 wp-content/plugins/novel-game-plugin/assets/
```

### テスト4: チェックサム検証失敗

**目的**: ダウンロードしたファイルのチェックサムが不一致の場合の動作を確認

**シミュレーション方法**:
```php
// includes/sample-images-downloader.php の noveltool_verify_checksum() を一時的に変更
function noveltool_verify_checksum( $file_path, $expected_checksum ) {
    return false; // 常に失敗
}
```

**期待結果**:
- ステータスが "failed" に更新される
- エラーメッセージ: "Checksum verification failed. The downloaded file may be corrupted. Please try again."
- 一時ファイルが削除される
- UI にエラーメッセージが表示される
- 「再試行」ボタンが有効

### テスト5: 長時間のin_progress状態（TTL自動復旧）

**目的**: 30分以上 "in_progress" のままの場合に自動復旧することを確認

**シミュレーション方法**:
```php
// WordPress 管理画面またはデータベースで以下を実行
update_option('noveltool_sample_images_download_status', 'in_progress', false);
update_option('noveltool_sample_images_download_status_data', array(
    'status' => 'in_progress',
    'timestamp' => time() - 2000 // 33分前
), false);
```

**期待結果**:
- 「マイゲーム」画面にアクセスすると、ダウンロードモーダルが表示される
- 「ダウンロード」ボタンをクリックすると、TTLチェックが実行される
- ステータスが自動的に "failed" に更新される
- エラーメッセージ: "Download timeout: The download process took too long and was automatically cancelled."
- 新しいダウンロードが開始される

### テスト6: 再試行機能

**目的**: 失敗後の再試行が正常に動作することを確認

**手順**:
1. テスト2またはテスト3で失敗状態を作る
2. エラーメッセージが表示されることを確認
3. 「再試行」ボタンをクリック
4. リセット処理が実行される
5. ダウンロードが再開される

**期待結果**:
- ステータスが "failed" → "not_started" → "in_progress" に遷移
- `/sample-images/reset-status` API が呼び出される
- リセット成功後、ダウンロードが自動的に再開される
- エラー情報（`noveltool_sample_images_download_error`）がクリアされる

### テスト7: REST API エンドポイント

**目的**: 新しい REST API エンドポイントが正常に動作することを確認

#### 7-1: ステータス取得（エラー情報あり）

**リクエスト**:
```bash
curl -X GET "http://your-site.local/wp-json/novel-game-plugin/v1/sample-images/status" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**期待レスポンス**（失敗時）:
```json
{
  "exists": false,
  "status": "failed",
  "error": {
    "message": "Failed to download sample images: HTTP status code: 502",
    "timestamp": 1703299200
  }
}
```

#### 7-2: ステータスリセット

**リクエスト**:
```bash
curl -X POST "http://your-site.local/wp-json/novel-game-plugin/v1/sample-images/reset-status" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

**期待レスポンス**:
```json
{
  "success": true,
  "message": "Download status has been reset."
}
```

### テスト8: 同時実行ガード

**目的**: 複数のダウンロード要求が同時に実行されないことを確認

**手順**:
1. ブラウザで「マイゲーム」画面を2つのタブで開く
2. 両方のタブで「ダウンロード」ボタンをほぼ同時にクリック

**期待結果**:
- 1つ目のダウンロードが "in_progress" になる
- 2つ目のリクエストは "Download already in progress." エラーを返す
- 重複ダウンロードが発生しない

## 回帰テスト

### 既存機能への影響確認

**テスト項目**:
1. サンプルゲームのインストール機能が正常に動作すること
2. 「後で」ボタンで非表示にした場合、バナーが表示されること
3. ダウンロード完了後、サンプル画像が正常に表示されること
4. 国際化（翻訳）が正常に動作すること

## 手動テスト手順

### 準備

1. テスト環境のセットアップ
   ```bash
   # プラグインをインストール
   cd wp-content/plugins/
   git clone https://github.com/shokun0803/novel-game-plugin.git
   cd novel-game-plugin
   git checkout copilot/fix-sample-image-download-error
   ```

2. サンプル画像ディレクトリを削除（初期状態に戻す）
   ```bash
   rm -rf assets/sample-images/
   ```

3. WordPress オプションをクリア
   ```bash
   wp option delete noveltool_sample_images_downloaded
   wp option delete noveltool_sample_images_download_status
   wp option delete noveltool_sample_images_download_status_data
   wp option delete noveltool_sample_images_download_error
   ```

### テスト実行

1. 正常系テスト（テスト1）を実行
2. 異常系テストを順番に実行（テスト2〜5）
3. 再試行機能テスト（テスト6）を実行
4. REST API テスト（テスト7）を実行
5. 回帰テストを実行

## 自動テスト（将来の拡張）

現在は手動テストのみですが、将来的には以下のテストを自動化することを推奨します：

1. PHPUnit テスト
   - `noveltool_update_download_status()` のユニットテスト
   - `noveltool_check_download_status_ttl()` のユニットテスト
   - REST API エンドポイントの統合テスト

2. JavaScript テスト
   - `resetStatusAndRetry()` のユニットテスト
   - エラーメッセージ表示のテスト

## テスト完了の定義

以下のすべてを満たした場合、テスト完了とみなします：

- [ ] すべてのテストケースが期待結果を満たす
- [ ] 既存機能に影響がない（回帰テストパス）
- [ ] エラーメッセージが明確で、ユーザーが対処方法を理解できる
- [ ] ステータスが常に正しく更新される（ロック残留なし）
- [ ] ドキュメントが最新の状態に更新されている

## トラブルシューティング

テスト中に問題が発生した場合：

1. WordPress デバッグログを確認
   ```php
   // wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. ブラウザのコンソールを確認（JavaScript エラー）

3. データベースのオプションテーブルを確認
   ```sql
   SELECT * FROM wp_options WHERE option_name LIKE 'noveltool_sample%';
   ```

4. REST API レスポンスを直接確認
   ```bash
   wp rest sample-images status --user=admin
   ```
