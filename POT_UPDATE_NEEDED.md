# POT ファイル更新が必要

## 概要
PR #[番号] の進捗UI（プログレスバー）と詳細エラー表示実装により、新しい翻訳文字列を追加しました。
POT ファイルの更新が必要です。

## 変更内容
1. プログレスバー関連の文字列を追加：
   - `statusConnecting` - 接続中の状態表示
   - `statusDownloading` - ダウンロード中の状態表示
   - `statusVerifying` - 検証中の状態表示
   - `statusExtracting` - 展開中の状態表示
   - `statusCompleted` - 完了状態の表示
2. 詳細エラー表示関連の文字列を追加：
   - `showErrorDetails` - 詳細エラー表示ボタン
   - `hideErrorDetails` - 詳細非表示ボタン
   - `errorTimestamp` - エラー発生時刻のラベル
3. その他の文字列：
   - `downloadSuccess` - ダウンロード成功メッセージ
   - `downloadTimeout` - タイムアウトメッセージ

## 更新コマンド
WP-CLI が利用可能な環境で以下のコマンドを実行してください：

```bash
wp i18n make-pot . languages/novel-game-plugin.pot
```

## 実施者
- リポジトリメンテナーまたは WP-CLI が利用可能な開発環境を持つ担当者

## 関連ファイル
- `admin/my-games.php`: 新規翻訳文字列の追加
- `js/admin-sample-images-prompt.js`: プログレスバーと詳細エラー表示機能の実装
- `css/admin-sample-images-prompt.css`: プログレスバーと詳細エラー表示のスタイル

## 注意事項
- POT ファイル更新後、このファイル（POT_UPDATE_NEEDED.md）は削除してください
- POT 更新は別コミットとして実施することを推奨
