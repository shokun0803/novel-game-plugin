# Novel Game Plugin

**Version:** 1.1.0  
**License:** GPLv2 or later  
**Author:** shokun0803  
**Requires:** WordPress 4.7+  
**Tested up to:** WordPress 6.0+  

WordPressでサウンドノベル・ビジュアルノベルゲームを作成・公開できる包括的なプラグインです。「弟切草」や「かまいたちの夜」のような分岐型ノベルゲームを簡単に作成できます。

## 主な機能

### 🎮 ゲーム作成機能
- **カスタム投稿タイプ「ノベルゲーム」** - 各シーンを投稿として管理
- **直感的な管理画面** - 背景・キャラクター・セリフ・選択肢を視覚的に編集
- **分岐システム** - 選択肢による複雑なストーリー分岐をサポート
- **メディア統合** - WordPressメディアライブラリから画像を簡単選択
- **ゲーム基本情報設定** - タイトル・説明・タイトル画像の一元管理

### 🖥️ 管理画面機能
- **新規ゲーム作成** - ゲームタイトルから自動的に最初のシーンを生成
- **ゲーム一覧** - 作成したゲームを一覧表示・管理
- **ゲーム基本情報** - ゲーム全体の設定を一元管理
- **シーン管理** - ゲームごとのシーン管理・編集
- **高度なフィルタリング** - ゲームタイトルでの絞り込み検索

### 🌐 フロントエンド機能
- **レスポンシブデザイン** - あらゆるデバイスで快適なプレイ体験
- **インタラクティブUI** - クリック・タップでのセリフ進行
- **アーカイブページ** - ゲーム一覧の美しい表示
- **選択肢システム** - 直感的な選択肢表示と分岐
- **カスタムテンプレート** - テーマとの統合

### 🔧 技術的機能
- **ショートコード対応** - 任意のページにゲームを埋め込み
- **多言語対応** - 国際化（i18n）完全対応
- **セキュリティ** - nonce検証・権限チェック・データサニタイズ
- **Ajax統合** - 管理画面での非同期処理
- **データベース最適化** - 効率的なクエリとインデックス利用

## インストール

### 1. 手動インストール
```bash
# GitHubからクローン
git clone https://github.com/shokun0803/novel-game-plugin.git

# WordPressプラグインディレクトリに配置
mv novel-game-plugin /path/to/wordpress/wp-content/plugins/

# WordPress管理画面で有効化
```

### 2. 必要な環境
- WordPress 4.7 以上
- PHP 7.0 以上
- MySQL 5.6 以上

## 使い方

### 基本的な使用手順

1. **プラグインの有効化**
   - WordPress管理画面「プラグイン」→「インストール済みプラグイン」から有効化

2. **ゲームの基本情報設定**
   - 管理画面「ノベルゲーム」→「ゲーム基本情報」
   - ゲームタイトル・説明・タイトル画像を設定

3. **新規ゲーム作成**
   - 管理画面「ノベルゲーム」→「新規ゲーム作成」
   - ゲームタイトルを入力して「ゲームを作成」

4. **シーンの編集**
   - 自動生成された最初のシーンを編集
   - 背景画像・キャラクター画像・セリフ・選択肢を設定

5. **分岐シーンの作成**
   - 選択肢に次のシーンを指定
   - 新規シーン作成や既存シーン選択が可能

### ショートコード使用例

```php
// 特定のゲームの投稿一覧を表示
[novel_game_posts game_title="マイゲーム"]

// 全ゲーム一覧を表示
[novel_game_posts]

// 表示オプション付き
[novel_game_posts game_title="マイゲーム" limit="5" show_date="false"]
```

### 利用可能なショートコード属性

- `game_title` - 特定のゲームタイトル
- `limit` - 表示する投稿数（デフォルト: -1 = 全て）
- `orderby` - 並び順（date, title等）
- `order` - 昇順/降順（ASC/DESC）
- `show_title` - ゲームタイトル表示（true/false）
- `show_date` - 日付表示（true/false）

## ディレクトリ構成

```
novel-game-plugin/
├── novel-game-plugin.php      # メインプラグインファイル
├── admin/                     # 管理画面関連
│   ├── meta-boxes.php         # メタボックス・Ajax処理
│   ├── new-game.php           # 新規ゲーム作成
│   └── game-settings.php      # ゲーム基本情報設定
├── includes/                  # コア機能
│   └── post-types.php         # カスタム投稿タイプ
├── templates/                 # テンプレート
│   └── archive-novel_game.php # アーカイブページ
├── css/                       # スタイルシート
│   └── style.css              # フロントエンドスタイル
├── js/                        # JavaScript
│   ├── frontend.js            # フロントエンド機能
│   ├── admin.js               # 管理画面基本機能
│   ├── admin-game-settings.js # ゲーム設定画面
│   └── admin-meta-boxes.js    # メタボックス機能
└── languages/                 # 多言語対応
    └── (翻訳ファイル)
```

## 開発者向け情報

### 開発環境セットアップ
```bash
# 開発版の取得
git clone https://github.com/shokun0803/novel-game-plugin.git
cd novel-game-plugin

# 開発ブランチで作業
git checkout -b feature/new-feature
```

### コーディング規約
- WordPress公式コーディング規約に準拠
- 全ての関数・クラスに `noveltool_` プレフィックス
- PHPDocコメントの記述必須
- セキュリティ対策の実装必須

詳細な命名規約については、[命名規約ガイドライン](docs/NAMING_CONVENTIONS.md) を参照してください。

### コードレビュー

プルリクエストのレビュー時には、[コードレビューチェックリスト](docs/CODE_REVIEW_CHECKLIST.md) を活用してください。

### 開発者向けログメッセージとデバッグ機能

開発者向けログメッセージとユーザー向け翻訳文字列の適切な使い分けについては、[開発者向けログメッセージガイドライン](docs/DEVELOPER_LOGGING_GUIDELINES.md) を参照してください。

**重要なポイント:**
- ユーザー向けメッセージは必ず翻訳関数（`__()`, `_e()` など）を使用
- 開発者向けデバッグログは `debugLog()` または `console.log()` を使用（翻訳不要）
- フロントエンドでは `debugLog()` 関数を使用することで、本番環境でのログ出力を制御可能

### 翻訳ファイルの更新手順

このプラグインは国際化（i18n）に対応しており、textdomain `novel-game-plugin` を使用しています。

#### 翻訳可能文字列の追加
新しい翻訳可能文字列を追加する際は、必ず `novel-game-plugin` を textdomain として指定してください：

```php
// PHP での例
__( '翻訳する文字列', 'novel-game-plugin' )
_e( '翻訳する文字列', 'novel-game-plugin' )
esc_html__( '翻訳する文字列', 'novel-game-plugin' )
esc_attr__( '翻訳する文字列', 'novel-game-plugin' )
```

```javascript
// JavaScript (wp.i18n) での例
__( '翻訳する文字列', 'novel-game-plugin' )
```

#### .pot ファイルの更新
翻訳可能文字列を追加・変更したら、以下のコマンドで .pot ファイルを更新してください：

```bash
# xgettext を使用した POT ファイル生成（PHP）
xgettext \
  --language=PHP \
  --from-code=UTF-8 \
  --keyword=__ \
  --keyword=_e \
  --keyword=_x:1,2c \
  --keyword=esc_html__ \
  --keyword=esc_html_e \
  --keyword=esc_attr__ \
  --keyword=esc_attr_e \
  --package-name="Novel Game Plugin" \
  --package-version="1.1.2" \
  --msgid-bugs-address="https://github.com/shokun0803/novel-game-plugin/issues" \
  --output=languages/novel-game-plugin-php.pot \
  $(find . -name "*.php" -not -path "./node_modules/*" -not -path "./.git/*")

# JavaScript ファイルからの抽出
xgettext \
  --language=JavaScript \
  --from-code=UTF-8 \
  --keyword=__ \
  --output=languages/novel-game-plugin-js.pot \
  $(find . -name "*.js" -not -path "./node_modules/*" -not -path "./.git/*")

# 統合
msgcat --use-first --sort-output \
  languages/novel-game-plugin-php.pot \
  languages/novel-game-plugin-js.pot \
  -o languages/novel-game-plugin.pot
```

#### .po / .mo ファイルの更新
```bash
# 既存の .po ファイルを .pot から更新
msgmerge --update languages/novel-game-plugin-ja.po languages/novel-game-plugin.pot

# .mo ファイルのコンパイル
msgfmt languages/novel-game-plugin-ja.po -o languages/novel-game-plugin-ja_JP.mo
```

#### 新しい言語の追加
```bash
# 新しい言語の .po ファイルを作成（例: 英語）
msginit --input=languages/novel-game-plugin.pot \
  --locale=en_US \
  --output=languages/novel-game-plugin-en_US.po

# 翻訳後、.mo ファイルにコンパイル
msgfmt languages/novel-game-plugin-en_US.po -o languages/novel-game-plugin-en_US.mo
```

### フック・フィルター
プラグインでは以下のWordPressフックを利用：
- `init` - 投稿タイプ登録
- `admin_menu` - 管理画面メニュー
- `the_content` - コンテンツフィルター
- `template_include` - テンプレート読み込み

## 貢献・サポート

### バグレポート・機能要望
- GitHubのIssueでご報告ください
- 再現手順を詳しく記載してください

### 開発への貢献
1. リポジトリをフォーク
2. 機能ブランチを作成
3. 変更をコミット
4. プルリクエストを作成

### ライセンス
このプラグインはGPLv2またはそれ以降のバージョンでライセンスされています。  
詳細は [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) をご確認ください。

## 更新履歴

### Version 1.1.0
- ゲーム基本情報設定機能追加
- 新規ゲーム作成ページ追加
- アーカイブテンプレート追加
- ショートコード機能拡張
- 管理画面UI改善

### Version 1.0.0
- 初回リリース
- 基本的なノベルゲーム機能
- カスタム投稿タイプ
- フロントエンド表示機能

---

**Author:** [shokun0803](https://github.com/shokun0803)  
**Repository:** [novel-game-plugin](https://github.com/shokun0803/novel-game-plugin)  
**Support:** [GitHub Issues](https://github.com/shokun0803/novel-game-plugin/issues)
