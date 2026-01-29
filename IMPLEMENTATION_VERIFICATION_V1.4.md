# サンプル画像ダウンロード機能強化 - 実装検証レポート (v1.4.0)

## 実装概要

本実装では、サンプル画像ダウンロード機能に以下の3つの主要な改善を追加しました：

1. **環境検出機能**: サーバー環境の能力を事前にチェック
2. **ストリーミング抽出**: メモリ使用量を最小限に抑えた ZIP 展開
3. **バックグラウンド処理**: ジョブを分割して段階的に実行

## 実装済み機能

### 1. 環境検出 (`noveltool_detect_extraction_capabilities`)

**実装場所**: `includes/sample-images-downloader.php:269`

**機能**:
- ZipArchive 拡張の有無を検出
- exec 関数の利用可否を安全にチェック
- unzip コマンドの存在を確認
- memory_limit を MB 単位に変換
- 推奨抽出方式を自動判定

**検出結果の種類**:
- `streaming`: ZipArchive + 十分なメモリ
- `unzip_command`: unzip コマンド利用可能
- `standard`: ZipArchive のみ（従来方式）
- `none`: 抽出不可能

**早期失敗**:
環境が `none` の場合、ERR-NO-EXT エラーで即座に失敗し、具体的な対処法を表示します。

### 2. ストリーミング抽出 (`noveltool_extract_zip_streaming`)

**実装場所**: `includes/sample-images-downloader.php:339`

**機能**:
- `ZipArchive::getStream()` でファイル単位の展開
- ディレクトリトラバーサル対策
- unzip コマンドへの安全なフォールバック
- エスケープ処理によるコマンドインジェクション対策

**メモリ効率**:
- ZIP 全体をメモリに展開しない
- ファイル単位でストリーミング読み込み・書き込み
- 低メモリ環境でも動作可能

### 3. バックグラウンドジョブシステム

**実装場所**: `includes/sample-images-downloader.php:568-1148`

**主要関数**:
- `noveltool_create_background_job`: ジョブ作成
- `noveltool_schedule_background_job`: ジョブスケジュール
- `noveltool_process_background_job`: ジョブ実行
- `noveltool_check_background_job_chain`: ジョブチェーン管理

**ジョブタイプ**:
1. **ダウンロードジョブ** (`NOVELTOOL_JOB_TYPE_DOWNLOAD`)
   - サンプル画像 ZIP をダウンロード
   - 一時ファイルのパスを返す

2. **検証ジョブ** (`NOVELTOOL_JOB_TYPE_VERIFY`)
   - SHA256 チェックサムを検証
   - チェックサムファイルがある場合のみ実行

3. **抽出ジョブ** (`NOVELTOOL_JOB_TYPE_EXTRACT`)
   - ストリーミング抽出で ZIP を展開
   - 完了後、一時ファイルを削除

**ジョブフロー**:
```
ダウンロードジョブ
    ↓ (10秒後にチェック)
検証ジョブ（チェックサムがある場合）
    ↓ (10秒後にチェック)
抽出ジョブ
    ↓
完了
```

### 4. 進捗情報の拡張

**実装場所**: `includes/sample-images-downloader.php:858-920`

`noveltool_sample_images_download_status_data` に以下のフィールドを追加：
- `job_id`: 現在実行中のジョブID
- `progress`: 進捗パーセンテージ (0-100)
- `current_step`: 現在のステップ (`download`, `verify`, `extract`)
- `use_background`: バックグラウンド処理を使用しているか

### 5. REST API の拡張

**実装場所**: `includes/sample-images-downloader.php:1595-1647`

**ステータス API** (`/wp-json/novel-game-plugin/v1/sample-images/status`):
- ジョブ進捗情報を追加返却
- バックグラウンド処理の状態を可視化

### 6. UI の更新

**実装場所**:
- `admin/my-games.php:313-370`
- `js/admin-sample-images-prompt.js:205-261`

**変更内容**:
- バックグラウンド処理の説明をモーダルに追加
- 進捗表示で `progress` と `current_step` を使用
- 各ステップに応じたステータステキスト表示
- 環境検出エラー用のメッセージ追加

## 設定オプション

以下のオプションで動作を制御可能（WordPress オプション API を使用）:

```php
// ストリーミング抽出を使用するか（デフォルト: true）
update_option( 'noveltool_use_streaming_extraction', true );

// バックグラウンド処理を使用するか（デフォルト: true）
update_option( 'noveltool_use_background_processing', true );
```

## エラーハンドリング

### 環境検出エラー
- **ERR-NO-EXT**: ZIP 拡張機能未対応
  - ZipArchive も unzip も利用不可
  - 対処法: PHP ZipArchive 拡張または unzip コマンドをインストール

### メモリ不足
- memory_limit < 128MB の場合は警告をログに記録
- 処理は続行（ストリーミング抽出でメモリ効率化）

### ジョブ失敗
- 各ジョブで失敗が発生した場合、詳細なエラー情報を記録
- エラーコード、メッセージ、ステージを構造化して保存

## セキュリティ対策

1. **権限チェック**: すべての API は `manage_options` 権限を要求
2. **引数エスケープ**: unzip コマンドの引数は `escapeshellarg()` でエスケープ
3. **ディレクトリトラバーサル対策**: ファイル名に `..` が含まれる場合はスキップ
4. **機密情報のマスク**: エラー情報に機密情報を含めない

## 互換性

- WordPress 5.0 以降
- PHP 7.0 以降
- ZipArchive 拡張（推奨）または unzip コマンド
- memory_limit: 128MB 以上推奨（256MB 以上が理想）

## デフォルト動作

v1.4.0 では以下がデフォルトで有効：
- ストリーミング抽出: 有効
- バックグラウンド処理: 有効

従来の同期処理に戻す場合は、上記のオプションを `false` に設定してください。

## テスト推奨事項

### 1. 環境検出のテスト
```php
$capabilities = noveltool_detect_extraction_capabilities();
var_dump( $capabilities );
// 期待される結果: 配列に has_ziparchive, has_exec, has_unzip, memory_limit_mb, recommended が含まれる
```

### 2. ストリーミング抽出のテスト
低メモリ環境（memory_limit=128M）でサンプル画像をダウンロードし、エラーなく完了することを確認。

### 3. バックグラウンド処理のテスト
- ダウンロード開始後、WP Cron が正常にジョブを実行するか確認
- 進捗情報が正しく更新されるか確認
- ジョブチェーンが正常に動作するか確認

### 4. エラーハンドリングのテスト
- ZipArchive と unzip が両方利用不可の環境で ERR-NO-EXT が返されるか確認
- メモリ不足の環境で警告がログに記録されるか確認

## 既知の制約

1. **WP Cron の依存**: バックグラウンド処理は WP Cron に依存するため、アクセスが少ないサイトでは処理が遅延する可能性があります
2. **一時ファイル**: ダウンロードした ZIP ファイルは一時ファイルとして保存されるため、ディスク容量が必要です
3. **ジョブクリーンアップ**: 現在、完了したジョブは手動でクリーンアップされません（将来の改善予定）

## 今後の改善案

- [ ] ジョブの自動クリーンアップ（古いジョブの削除）
- [ ] リトライロジックの追加（失敗したジョブの再試行）
- [ ] 進捗のバイト単位での表示（現在はパーセンテージのみ）
- [ ] 複数のミラーサーバーからのダウンロード対応

## まとめ

v1.4.0 の実装により、サンプル画像ダウンロード機能は以下の点で大幅に改善されました：

- **耐障害性の向上**: 環境検出により早期失敗、ストリーミング抽出により低メモリ環境対応
- **タイムアウト耐性**: バックグラウンド処理により PHP の実行時間制限に依存しにくい
- **ユーザー体験の向上**: 詳細な進捗情報、明確なエラーメッセージ

これにより、幅広いホスティング環境で安定してサンプル画像を導入できるようになりました。
