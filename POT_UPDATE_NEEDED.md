# POT ファイル更新が必要

## 概要
PR #[番号] のコメントフィードバック対応により、翻訳文字列の構造を変更しました。
POT ファイルの更新が必要です。

## 変更内容
1. `troubleshootingSteps` を HTML 混入形式から配列形式に変更
2. 各トラブルシューティング手順を個別の翻訳可能文字列として分離

## 更新コマンド
WP-CLI が利用可能な環境で以下のコマンドを実行してください：

```bash
wp i18n make-pot . languages/novel-game-plugin.pot
```

## 実施者
- リポジトリメンテナーまたは WP-CLI が利用可能な開発環境を持つ担当者

## 関連ファイル
- `admin/my-games.php`: 翻訳文字列の構造変更
- `js/admin-sample-images-prompt.js`: 配列形式のトラブルシューティング手順を処理

## 注意事項
- POT ファイル更新後、このファイル（POT_UPDATE_NEEDED.md）は削除してください
- POT 更新は別コミットとして実施することを推奨
