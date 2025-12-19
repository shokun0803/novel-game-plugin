# 翻訳ファイル（POT）の更新手順

サンプル画像ダウンロード機能で追加された新しい翻訳可能な文字列を POT ファイルに反映する必要があります。

## 必要なツール

- WP-CLI（WordPress Command Line Interface）

## 更新手順

### 1. WP-CLI のインストール（未インストールの場合）

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp
```

### 2. POT ファイルの生成

プラグインのルートディレクトリで以下のコマンドを実行：

```bash
wp i18n make-pot . languages/novel-game-plugin.pot
```

### 3. 生成された POT ファイルの確認

以下の新しい文字列が含まれていることを確認：

```
# サンプル画像ダウンロード関連
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

# エラーメッセージ
msgid "Failed to fetch release info. HTTP status code: %d"
msgid "Failed to parse release information."
msgid "Sample images asset not found in the latest release."
msgid "Failed to create temporary file."
msgid "Failed to download file. HTTP status code: %d"
msgid "Checksum verification failed. The downloaded file may be corrupted."
msgid "Could not initialize filesystem."
msgid "Could not create destination directory."
msgid "Sample images downloaded and installed successfully."
msgid "Sample images already exist."

# 権限エラー
msgid "Security check failed"
msgid "Insufficient permissions"
```

### 4. PO ファイルの更新

既存の翻訳ファイル（`languages/novel-game-plugin-ja.po`）を更新：

```bash
wp i18n update-po languages/novel-game-plugin.pot languages/novel-game-plugin-ja.po
```

### 5. 日本語翻訳の追加

`languages/novel-game-plugin-ja.po` ファイルを開き、新しい文字列に対応する日本語翻訳を追加します。

例：
```po
#: includes/sample-images-downloader.php:XXX
msgid "Download Sample Images"
msgstr "サンプル画像のダウンロード"

#: js/admin-sample-images-prompt.js:XXX
msgid "Download"
msgstr "ダウンロード"

# ... 他の翻訳も追加
```

### 6. MO ファイルの生成

翻訳が完了したら、MO ファイルを生成：

```bash
wp i18n make-mo languages/
```

### 7. 動作確認

WordPress の管理画面で言語を日本語に設定し、以下を確認：

1. マイゲーム画面でサンプル画像ダウンロードのモーダルが日本語で表示される
2. すべてのボタンとメッセージが日本語で表示される
3. エラーメッセージも日本語で表示される

## 手動での翻訳（WP-CLI がない場合）

### Poedit などの翻訳ツールを使用

1. [Poedit](https://poedit.net/) をダウンロード・インストール
2. `languages/novel-game-plugin.pot` を開く
3. 「POTファイルから翻訳を更新」を実行
4. 新しい文字列を翻訳
5. `languages/novel-game-plugin-ja.po` として保存
6. MO ファイルが自動生成される

## 翻訳ファイルの配置

最終的に以下のファイルが必要：

```
languages/
├── novel-game-plugin.pot           # POTファイル（テンプレート）
├── novel-game-plugin-ja.po         # 日本語 POファイル
├── novel-game-plugin-ja.mo         # 日本語 MOファイル（コンパイル済み）
└── novel-game-plugin-ja_JP.mo      # 日本語 MOファイル（ロケール指定）
```

## 注意事項

- POT ファイルは開発環境でのみ更新してください
- MO ファイルは Git にコミットしないでください（.gitignore に含まれています）
- 翻訳の品質を確認してからコミットしてください
- WordPress 公式ディレクトリに登録する場合、translate.wordpress.org で翻訳が管理されます

## 参考リンク

- [WP-CLI i18n コマンド](https://developer.wordpress.org/cli/commands/i18n/)
- [WordPress 国際化ガイド](https://developer.wordpress.org/plugins/internationalization/)
- [Poedit](https://poedit.net/)
