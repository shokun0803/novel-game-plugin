# POT ファイル更新が必要

## 概要
PR の進捗UI（プログレスバー）と詳細エラー表示実装、およびアクセシビリティ改善、エラー処理改善、構造化エラー対応により、新しい翻訳文字列を追加しました。
POT ファイルの更新が必要です。

**注意**: POTファイルの実ファイル更新は別環境でのWP-CLI実行が必要なため、別タスクとして実施してください。

## 変更内容
1. プログレスバー関連の文字列を追加：
   - `statusConnecting` - 接続中の状態表示
   - `statusDownloading` - ダウンロード中の状態表示
   - `statusDownloadingBytes` - バイト数表示付きダウンロード中の状態
   - `statusVerifying` - 検証中の状態表示
   - `statusExtracting` - 展開中の状態表示
   - `statusCompleted` - 完了状態の表示
2. 詳細エラー表示関連の文字列を追加：
   - `showErrorDetails` - 詳細エラー表示ボタン
   - `hideErrorDetails` - 詳細非表示ボタン
   - `errorTimestamp` - エラー発生時刻のラベル
   - `errorDetailFetchFailed` - 詳細エラー取得失敗時のメッセージ
   - `errorDetailNotAvailable` - 詳細エラーが記録されていない場合のメッセージ
3. 診断コードとHTTPエラー関連の文字列を追加：
   - `diagnosticCode` - 診断コードのラベル
   - `errorBadRequest` - HTTP 400エラーメッセージ
   - `errorForbidden` - HTTP 403エラーメッセージ
   - `errorNotFound` - HTTP 404エラーメッセージ
   - `errorServerError` - HTTP 500エラーメッセージ
   - `errorServiceUnavailable` - HTTP 503エラーメッセージ
   - `errorUnknown` - 不明なエラーメッセージ
4. 構造化エラー表示関連の文字列を追加：
   - `errorStage` - エラー発生段階のラベル
   - `errorCode` - エラーコードのラベル
   - `stageFetchRelease` - リリース情報取得段階の表示
   - `stageDownload` - ダウンロード段階の表示
   - `stageVerifyChecksum` - チェックサム検証段階の表示
   - `stageExtract` - 展開段階の表示
   - `stageFilesystem` - ファイルシステム操作段階の表示
   - `stageOther` - その他の段階の表示
5. その他の文字列：
   - `downloadSuccess` - ダウンロード成功メッセージ
   - `downloadTimeout` - タイムアウトメッセージ

**合計**: 26個の新規翻訳可能文字列

## 更新コマンド
WP-CLI が利用可能な環境で以下のコマンドを実行してください：

```bash
wp i18n make-pot . languages/novel-game-plugin.pot
```

## 実施者
- リポジトリメンテナーまたは WP-CLI が利用可能な開発環境を持つ担当者

## 関連ファイル
- `admin/my-games.php`: 新規翻訳文字列の追加（合計26個）
- `js/admin-sample-images-prompt.js`: プログレスバーと詳細エラー表示機能の実装、アクセシビリティ改善、エラー処理改善、構造化エラー対応
- `css/admin-sample-images-prompt.css`: プログレスバーと詳細エラー表示のスタイル、indeterminateモード追加
- `includes/sample-images-downloader.php`: 構造化エラーレスポンス、適切なHTTPステータスコード、シャットダウンフック、ステージ別エラーコード

## 注意事項
- POT ファイル更新後、このファイル（POT_UPDATE_NEEDED.md）は削除してください
- POT 更新は別コミットとして実施することを推奨
