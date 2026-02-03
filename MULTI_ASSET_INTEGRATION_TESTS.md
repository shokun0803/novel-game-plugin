# 複数アセット対応 統合テスト仕様書

## 概要

本ドキュメントは、PR #220 で実装された複数アセット対応（分割 ZIP ファイルのダウンロード・抽出・マージ機能）の統合テストを定義します。

## テスト環境要件

### 最小環境（必須テスト）
- PHP 7.4 以上
- memory_limit=128M
- max_execution_time=30
- WordPress 6.0 以上

### 推奨環境
- PHP 8.0 以上
- memory_limit=256M
- max_execution_time=300
- WordPress 6.4 以上

## 必須テストケース

### 1. 複数アセット（大小混在）のダウンロード・抽出・マージ

**目的**: 複数の ZIP ファイルを独立して処理し、最終的に正しくマージできることを確認

**前提条件**:
- memory_limit=128M に設定
- テスト用リリースに複数のサンプル画像 ZIP を用意（例: part1.zip=5MB, part2.zip=10MB, part3.zip=3MB）
- 各 ZIP に `.sha256` チェックサムファイルを含める

**手順**:
1. WordPress 管理画面から「マイゲーム」にアクセス
2. サンプル画像ダウンロードモーダルで「ダウンロード」をクリック
3. 進捗バーが表示され、各アセットのダウンロードが開始されることを確認
4. ダウンロード完了まで待機（5-10分）
5. `assets/sample-images/` ディレクトリにすべてのファイルが正しく展開されていることを確認

**期待結果**:
- すべてのアセットが正常にダウンロードされる
- チェックサムが検証される
- すべてのファイルが正しい場所に配置される
- ステータス API が `assets` 配列と `overall_progress` を返す
- memory_limit=128M の環境でもエラーなく完了する

**確認項目**:
```bash
# ファイル数を確認
find wp-content/plugins/novel-game-plugin/assets/sample-images/ -type f | wc -l

# ステータスを確認
wp option get noveltool_sample_images_download_status_data --format=json

# ジョブログを確認
wp option get noveltool_job_log --format=json
```

---

### 2. ZipArchive 無効 + unzip 有効環境でのフォールバック

**目的**: PHP ZipArchive 拡張が無効な環境でも、unzip コマンドで正常に動作することを確認

**前提条件**:
- PHP ZipArchive 拡張を無効化
- システムに `unzip` コマンドがインストールされている
- exec 関数が有効

**手順**:
1. PHP 設定で ZipArchive を無効化
   ```bash
   # php.ini で extension=zip をコメントアウト
   ```
2. WordPress を再起動
3. サンプル画像ダウンロードを実行
4. unzip コマンドが使用されることをログで確認

**期待結果**:
- `noveltool_detect_extraction_capabilities()` が `unzip` コマンドを検出
- ダウンロード・抽出が正常に完了
- エラーログに「Using unzip command fallback」のメッセージが記録される

**確認項目**:
```bash
# unzip の検出を確認
grep "unzip command" wp-content/debug.log

# 抽出結果を確認
ls -la wp-content/plugins/novel-game-plugin/assets/sample-images/
```

---

### 3. exec 無効環境での明示的エラー

**目的**: exec 関数が無効な環境で、適切なエラーメッセージが表示されることを確認

**前提条件**:
- PHP ZipArchive 拡張を無効化
- php.ini で `disable_functions=exec` を設定

**手順**:
1. PHP 設定で exec を無効化
2. サンプル画像ダウンロードを試行
3. エラーメッセージを確認

**期待結果**:
- 早期失敗で ERR-NO-EXT エラーが返される
- エラーメッセージに「PHP ZipArchive 拡張または unzip コマンドが必要です」と表示される
- 具体的な対処方法が提示される
- ダウンロードが開始されない（無駄な処理をしない）

**確認項目**:
```bash
# エラー情報を確認
wp option get noveltool_sample_images_download_error --format=json
```

---

### 4. 部分的失敗（1つのアセットが失敗しても他は継続）

**目的**: 複数アセットのうち1つが失敗しても、他のアセットの処理が継続されることを確認

**前提条件**:
- 複数のアセットを含むリリース
- 1つのアセットに無効な URL または破損したチェックサムを設定

**手順**:
1. テスト用に1つのアセット URL を無効な URL に変更（テスト環境でのみ）
2. ダウンロードを実行
3. 失敗したアセットと成功したアセットを確認

**期待結果**:
- 無効な URL のアセットは失敗するが、他のアセットは正常に処理される
- `failed_assets` 配列に失敗したアセット情報が記録される
- 少なくとも1つのアセットが成功した場合、全体としては成功とみなす
- ステータス API で各アセットの状態（success/failed）が確認できる

**確認項目**:
```bash
# ステータスデータの assets 配列を確認
wp option get noveltool_sample_images_download_status_data --format=json | jq '.assets'

# ジョブログで失敗したアセットを確認
wp option get noveltool_job_log --format=json | jq '.[] | select(.type=="job_failed")'
```

---

### 5. WP_Error ハンドリング（noveltool_perform_sample_images_download が WP_Error を返す場合）

**目的**: `noveltool_perform_sample_images_download()` が WP_Error を返しても REST API が致命的にならないことを確認

**前提条件**:
- GitHub API がレート制限に達しているか、ネットワークエラーが発生する状況

**手順**:
1. GitHub API の URL を無効な URL に一時的に変更（テスト環境でのみ）
   ```php
   // テスト用フィルター
   add_filter('noveltool_github_api_url', function() {
       return 'https://invalid.example.com/';
   });
   ```
2. ダウンロードを試行
3. REST API レスポンスを確認

**期待結果**:
- WP_Error が返される
- REST API が HTTP 500 エラーにならない
- エラーメッセージがサニタイズされている
- 詳細なエラー情報はサーバーログに記録される
- クライアントには非機密の簡潔なメッセージが返される

**確認項目**:
```bash
# REST API レスポンスを確認
curl -X POST "https://example.com/wp-json/noveltool/v1/sample-images/download" \
  -H "X-WP-Nonce: NONCE_HERE" \
  -H "Content-Type: application/json"

# エラーログを確認
tail -f wp-content/debug.log
```

---

### 6. チェックサムなしでの動作

**目的**: チェックサムファイルが存在しない場合でも、検証をスキップして処理が継続されることを確認

**前提条件**:
- テスト用リリースに ZIP ファイルのみを用意（`.sha256` ファイルなし）

**手順**:
1. チェックサムなしのリリースを作成
2. ダウンロードを実行
3. ログとジョブ状態を確認

**期待結果**:
- チェックサム取得失敗がログに記録される
- 検証ジョブがスキップされ、直接抽出ジョブに進む
- ファイルは正常に展開される
- `noveltool_job_log` に「Checksum not available, skipping verification」が記録される

**確認項目**:
```bash
# ジョブログでスキップを確認
wp option get noveltool_job_log --format=json | jq '.[] | select(.message | contains("Checksum"))'

# 最終的にファイルが展開されていることを確認
ls wp-content/plugins/novel-game-plugin/assets/sample-images/
```

---

### 7. 単一アセットでの後方互換性

**目的**: 従来の単一 ZIP ファイル形式でも正常に動作することを確認

**前提条件**:
- 単一のサンプル画像 ZIP を含むリリース

**手順**:
1. 単一アセットのリリースを使用
2. ダウンロードを実行
3. 従来の処理フローで動作することを確認

**期待結果**:
- マルチアセット分岐ではなく、従来の単一アセット処理が実行される
- `multi_asset` フラグが false になる
- ダウンロード・検証・抽出が正常に完了する
- 既存の動作が維持される

**確認項目**:
```bash
# ステータスデータで multi_asset フラグを確認
wp option get noveltool_sample_images_download_status_data --format=json | jq '.multi_asset'

# 単一 job_id が設定されていることを確認
wp option get noveltool_sample_images_download_status_data --format=json | jq '.job_id'
```

---

## 補足テスト

### A. タイムアウト保護

**目的**: ジョブが30回（5分）以上ポーリングされた場合にタイムアウトすることを確認

**手順**:
1. ジョブ処理を意図的に遅延させる（テストコードで sleep を追加）
2. 5分以上待機
3. タイムアウトエラーを確認

**期待結果**:
- 「ERR-JOB-TIMEOUT」エラーが返される
- 一時ファイルが削除される

---

### B. 一時ファイルのクリーンアップ

**目的**: ジョブ失敗時に一時ファイルが自動削除されることを確認

**手順**:
1. ダウンロード中にネットワークを切断
2. ジョブが失敗することを確認
3. `/tmp/` ディレクトリに一時ファイルが残っていないことを確認

**期待結果**:
- 一時ファイルが自動削除される
- ジョブログに削除情報が記録される

---

### C. 完了ジョブの自動クリーンアップ

**目的**: 完了後1時間経過したジョブが自動削除されることを確認

**手順**:
1. ダウンロードを完了
2. 1時間待機（または時刻を手動で変更）
3. `noveltool_background_jobs` オプションを確認

**期待結果**:
- 1時間以上経過したジョブが削除される
- `noveltool_job_log` には削除ログが残る（最新50件）

---

### D. 二重スケジューリング防止

**目的**: ジョブが二重にスケジュールされないことを確認

**手順**:
1. ダウンロードボタンを連打
2. スケジュール済みイベントを確認

**期待結果**:
- 同じジョブが二重にスケジュールされない
- `wp_next_scheduled()` によるチェックが機能する

**確認項目**:
```bash
# スケジュール済みイベントを確認
wp cron event list --format=table | grep noveltool
```

---

## REST API 出力仕様の確認

### ステータス API レスポンス構造

**エンドポイント**: `GET /wp-json/noveltool/v1/sample-images/status`

**期待されるレスポンス構造（複数アセット）**:
```json
{
  "status": "in_progress",
  "timestamp": 1703299200,
  "job_id": "job_abc123",
  "progress": 50,
  "current_step": "verify",
  "use_background": true,
  "multi_asset": true,
  "assets": [
    {
      "name": "novel-game-plugin-sample-images-part1.zip",
      "status": "completed",
      "progress": 100,
      "total_bytes": 5242880,
      "downloaded_bytes": 5242880,
      "job_id": "job_abc123",
      "message": ""
    },
    {
      "name": "novel-game-plugin-sample-images-part2.zip",
      "status": "in_progress",
      "progress": 50,
      "total_bytes": 10485760,
      "downloaded_bytes": 5242880,
      "job_id": "job_def456",
      "message": ""
    }
  ],
  "overall_progress": 75,
  "job_ids": ["job_abc123", "job_def456", "job_ghi789"]
}
```

**期待されるレスポンス構造（単一アセット/後方互換）**:
```json
{
  "status": "completed",
  "timestamp": 1703299200,
  "job_id": "job_abc123",
  "progress": 100,
  "current_step": "extract",
  "use_background": true,
  "multi_asset": false
}
```

### フィールドの型とサニタイゼーション確認

すべてのフィールドが適切にサニタイズされていることを確認:
- `status`: ホワイトリスト検証（'not_started', 'in_progress', 'completed', 'failed'）
- `progress`: 整数、0-100の範囲
- `current_step`: 許可リスト検証（'download', 'verify', 'extract'）
- `job_id`, `name`, `message`: `sanitize_text_field()` 適用
- `total_bytes`, `downloaded_bytes`: `absint()` 適用

---

## テスト実行チェックリスト

実装完了後、以下のすべてをチェックしてください:

- [ ] 1. 複数アセット（大小混在）のダウンロード・抽出・マージ（memory_limit=128M）
- [ ] 2. ZipArchive 無効 + unzip 有効環境でのフォールバック
- [ ] 3. exec 無効環境での明示的エラー（ERR-NO-EXT）
- [ ] 4. 部分的失敗（1つのアセットが失敗しても他は継続）
- [ ] 5. WP_Error ハンドリング（REST API が致命的にならない）
- [ ] 6. チェックサムなしでの動作（検証スキップ）
- [ ] 7. 単一アセットでの後方互換性
- [ ] A. タイムアウト保護（30回リトライ = 5分）
- [ ] B. 一時ファイルのクリーンアップ
- [ ] C. 完了ジョブの自動クリーンアップ（1時間保持）
- [ ] D. 二重スケジューリング防止
- [ ] REST API 出力仕様の確認（型・サニタイゼーション）

---

## テスト環境のセットアップ

### Docker を使用した複数環境テスト

```dockerfile
# Dockerfile.test-low-memory
FROM wordpress:php7.4-apache
RUN echo "memory_limit = 128M" > /usr/local/etc/php/conf.d/memory.ini
RUN echo "max_execution_time = 30" >> /usr/local/etc/php/conf.d/memory.ini
```

```dockerfile
# Dockerfile.test-no-ziparchive
FROM wordpress:php8.0-apache
RUN apt-get update && apt-get install -y unzip
RUN sed -i 's/^extension=zip/;extension=zip/' /usr/local/etc/php/php.ini
```

```bash
# テスト実行
docker-compose -f docker-compose.test.yml up -d
docker-compose -f docker-compose.test.yml exec wordpress bash
# 各テストケースを実行
```

---

## 報告フォーマット

テスト完了後、以下の情報を報告してください:

1. **実行環境**: PHP バージョン、WordPress バージョン、memory_limit、max_execution_time
2. **実行日時**: YYYY-MM-DD HH:MM:SS
3. **テスト結果**: 各テストケースの Pass/Fail
4. **失敗時の詳細**: エラーメッセージ、ログ、スクリーンショット
5. **修正したファイル名・関数名・コミットハッシュ**: 問題修正を行った場合

---

## 関連ドキュメント

- `MULTI_ASSET_IMPLEMENTATION_PLAN.md`: 複数アセット対応の実装計画
- `MULTI_ASSET_CRITICAL_FIXES.md`: 必須修正の詳細
- `docs/SAMPLE_IMAGES_DOWNLOAD.md`: サンプル画像ダウンロード機能ガイド
- `ERROR_HANDLING_FIXES_V1.4.1.md`: エラーハンドリング強化の詳細
