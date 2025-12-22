# 新しい翻訳文字列（POT ファイル更新時に追加する必要があります）

## サンプル画像ダウンロード機能で追加された翻訳可能な文字列

以下の文字列は `includes/sample-images-downloader.php` および関連ファイルで追加されました。
POT ファイル更新時に以下の文字列が含まれていることを確認してください。

### includes/sample-images-downloader.php

```
msgid "Download already in progress."
msgstr ""

msgid "Destination directory is not writable: %s"
msgstr ""
```

### 既存の文字列（確認用）

以下の文字列は既に実装されており、POT ファイルに含まれているはずです：

```
msgid "Sample images already exist."
msgid "Failed to fetch release info. HTTP status code: %d"
msgid "Failed to parse release information."
msgid "Sample images asset not found in the latest release."
msgid "Failed to create temporary file."
msgid "Failed to download file. HTTP status code: %d"
msgid "Checksum verification failed. The downloaded file may be corrupted."
msgid "Could not initialize filesystem."
msgid "Could not create destination directory."
msgid "Sample images downloaded and installed successfully."
msgid "Download Sample Images"
msgid "Sample game images are not installed. Would you like to download them now? Download size: approximately %s."
msgid "Download"
msgid "Later"
msgid "Cancel"
msgid "Downloading..."
msgid "Please wait while the sample images are being downloaded. This may take a few minutes."
msgid "Success"
msgid "Error"
msgid "Failed to download sample images. Please try again later."
msgid "Retry"
msgid "Close"
msgid "Security check failed"
msgid "Insufficient permissions"
```

## POT ファイル更新コマンド

WP-CLI がインストールされている環境で以下のコマンドを実行してください：

```bash
cd /path/to/novel-game-plugin
wp i18n make-pot . languages/novel-game-plugin.pot
```

## 手動での POT ファイル更新

WP-CLI が利用できない場合は、Poedit などのツールを使用して：

1. `languages/novel-game-plugin.pot` を開く
2. ソースコードから文字列を抽出
3. 上記の新しい文字列が含まれていることを確認
4. 保存

## 日本語翻訳の追加

POT ファイル更新後、`languages/novel-game-plugin-ja.po` に以下の日本語訳を追加してください：

```po
#: includes/sample-images-downloader.php:XXX
msgid "Download already in progress."
msgstr "ダウンロードは既に進行中です。"

#: includes/sample-images-downloader.php:XXX
msgid "Destination directory is not writable: %s"
msgstr "展開先ディレクトリに書き込み権限がありません: %s"
```

その後、MO ファイルを生成：

```bash
wp i18n make-mo languages/
```

または Poedit で PO ファイルを保存すると自動的に MO ファイルが生成されます。
