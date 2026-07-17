# 国際化（i18n）更新メモ

## 新規追加された翻訳可能文字列

以下の文字列が追加されました。POT ファイルの更新が必要です。

### admin/my-games.php

1. `'Sample Images Download in Progress'` - ダウンロード進捗バナーのタイトル
2. `'Checking status...'` - ステータス確認中のメッセージ
3. `'View Details'` - 詳細表示ボタン
4. `'Hide Details'` - 詳細非表示ボタン
5. `'JavaScript is disabled. Sample image download progress cannot be displayed in real-time.'` - JavaScript無効時の警告
6. `'Download Sample Images (requires JavaScript)'` - JavaScript無効時のダウンロードボタン
7. `'Security check failed'` - Ajax ハンドラーのセキュリティエラー
8. `'Insufficient permissions'` - Ajax ハンドラーの権限エラー

### includes/sample-images-downloader.php

ジョブID追跡機能追加に伴うコメント更新（翻訳不要）

### js/admin-sample-images-prompt.js

JavaScriptファイル内の文字列は、すべて `novelToolSampleImages.strings` オブジェクトから取得されており、
PHPファイルで `__()` 関数を通じて翻訳されています。新規のJavaScript側での翻訳文字列追加はありません。

## POT ファイル更新方法

WordPress 環境で WP-CLI が利用可能な場合：

```bash
wp i18n make-pot . languages/novel-game-plugin.pot --domain=novel-game-plugin
```

または、gettext ツールを使用：

```bash
find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" | xargs xgettext \
  --language=PHP \
  --keyword=__ \
  --keyword=_e \
  --keyword=_x:1,2c \
  --keyword=_ex:1,2c \
  --keyword=_n:1,2 \
  --keyword=_nx:1,2,4c \
  --keyword=esc_html__ \
  --keyword=esc_html_e \
  --keyword=esc_html_x:1,2c \
  --keyword=esc_attr__ \
  --keyword=esc_attr_e \
  --keyword=esc_attr_x:1,2c \
  --from-code=UTF-8 \
  --output=languages/novel-game-plugin.pot
```

## 日本語翻訳の更新

POT ファイル更新後、既存の .po ファイルをマージ：

```bash
msgmerge --update languages/novel-game-plugin-ja.po languages/novel-game-plugin.pot
```

翻訳を追加後、.mo ファイルをコンパイル：

```bash
msgfmt languages/novel-game-plugin-ja.po -o languages/novel-game-plugin-ja.mo
```
