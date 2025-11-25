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
- **サンプルゲーム** - プラグイン有効化時に自動インストール（学習用）

## インストール

### 1. 手動インストール
```bash
# GitHubからクローン
git clone https://github.com/shokun0803/novel-game-plugin.git

# WordPressプラグインディレクトリに配置
mv novel-game-plugin /path/to/wordpress/wp-content/plugins/

# WordPress管理画面で有効化
```

**📝 プラグイン有効化時の自動処理**
- プラグインを初めて有効化すると、**2つのサンプルゲーム**が自動的にインストールされます
  1. **Sample Novel Game**: 基本的なノベルゲームのデモ（3シーン構成）
  2. **Shadow Detective（影の探偵）**: 本格推理ゲームのデモ（23シーン構成）
- サンプルゲームはプラグインの使い方を学ぶための参考として活用できます
- サンプルゲームは通常のゲームと同様に編集・削除が可能です
- Shadow Detectiveは複数エンディング、証拠収集、フラグシステムの実例として参照できます

### 2. 必要な環境
- WordPress 4.7 以上
- PHP 7.0 以上
- MySQL 5.6 以上

## 使い方

### 管理画面の使い方（v1.2.0以降）

本プラグインは、ゲーム中心型の直感的なメニュー構造を採用しています。

#### 🏠 ダッシュボード
プラグインのホーム画面です。
- **ゲーム・シーン数の統計情報** - 作成済みゲーム数とシーン数を一目で確認
- **クイックアクション** - 新規ゲーム作成、マイゲームへのアクセス
- **3ステップの使い方ガイド** - 初心者向けの簡潔な使い方説明
- **プラグイン概要** - 主要機能の紹介

#### 🎮 マイゲーム
作成したゲームの一覧・選択・管理を行います。
- **ゲーム一覧** - カード形式で見やすく表示
- **ゲーム選択** - ゲームを選択すると個別管理画面に遷移
- **サムネイル表示** - タイトル画像または最初のシーンの背景を表示
- **シーン数表示** - 各ゲームのシーン数を確認

#### ➕ 新規ゲーム作成
新しいゲームを作成します。
- **ゲームタイトル** - ゲームの名称（必須）
- **ゲーム概要** - ゲームの説明文（任意）
- **タイトル画像** - ゲームのメイン画像（任意）
- **Game Over画面テキスト** - デフォルトは"Game Over"

#### ⚙️ 設定
プラグイン全体の設定とショートコード一覧を確認できます。
- **ショートコード一覧** - 使用可能なショートコードとオプション
- **ワンクリックコピー** - ショートコードを簡単にコピー
- **プラグイン情報** - バージョン情報など

### ゲーム個別管理画面

マイゲームでゲームを選択すると、そのゲーム専用の管理画面が表示されます。

#### 📝 シーン一覧タブ
- **シーン一覧** - 選択したゲームのシーンのみを表示
- **編集リンク** - 各シーンの編集画面へ直接アクセス
- **プレビューリンク** - 実際のゲーム画面を確認
- **新規シーン作成** - このタブから直接シーンを追加可能

#### ➕ 新規シーン作成タブ
- **ゲームタイトル自動設定** - 選択中のゲームに自動的に紐付け
- **シーン作成** - WordPressの投稿画面で詳細を設定

#### ⚙️ ゲーム設定タブ
- **ゲーム基本情報の編集** - タイトル、概要、画像などを更新
- **フラグマスタの管理** - ゲーム用のフラグを追加・削除
- **フラグの説明** - 各フラグの用途を記録

### 基本的なワークフロー

#### 1. 初回利用時
```
ダッシュボード → プラグイン概要確認 → 新規ゲーム作成
                                    ↓
                        ゲーム個別管理画面（自動遷移）
                                    ↓
                            新規シーン作成タブ
                                    ↓
                            最初のシーンを作成
```

#### 2. 既存ゲーム管理時
```
マイゲーム → ゲーム選択 → ゲーム個別管理画面
                              ↓
                    ┌─────────┼─────────┐
                    ↓         ↓         ↓
              シーン一覧  新規シーン  ゲーム設定
```

#### 3. シーン編集時
```
シーン一覧 → 編集リンク → シーン編集画面
                              ↓
              背景・キャラ・セリフ・選択肢を設定
```

### 詳細な使用手順

1. **プラグインの有効化**
   - WordPress管理画面「プラグイン」→「インストール済みプラグイン」から有効化

2. **新規ゲーム作成**
   - 管理画面「ノベルゲーム管理」→「➕ 新規ゲーム作成」
   - ゲームタイトル、概要、タイトル画像を入力
   - 「ゲームを作成」をクリック

3. **最初のシーンの作成**
   - ゲーム作成後、自動的にゲーム個別管理画面へ遷移
   - 「➕ 新規シーン作成」タブで「Create New Scene」をクリック
   - 背景画像・キャラクター画像・セリフを設定

4. **分岐シーンの作成**
   - シーン編集画面で選択肢を追加
   - 選択肢に次のシーンを指定
   - 新規シーン作成や既存シーン選択が可能

### 条件付きセリフ表示機能

#### 表示制御モード
1. **常に表示** - フラグ条件に関わらず常に表示
2. **条件成立時に非表示** - 指定したフラグ条件を満たすと非表示
3. **条件成立時に内容変更** - 指定したフラグ条件を満たすと代替テキストを表示

#### 動作仕様
- **条件成立時**: 代替テキストを表示（空の場合は通常テキストをフォールバック）
- **条件不成立時**: 通常テキストを表示

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

**重要: WordPress.org 標準準拠のため、ソースコードの文字列は英語で記述してください。**

```php
// PHP での例（英語で記述）
__( 'Translatable string', 'novel-game-plugin' )
_e( 'Translatable string', 'novel-game-plugin' )
esc_html__( 'Translatable string', 'novel-game-plugin' )
esc_attr__( 'Translatable string', 'novel-game-plugin' )
```

```javascript
// JavaScript (wp.i18n) での例（英語で記述）
__( 'Translatable string', 'novel-game-plugin' )
```

#### .pot ファイルの更新
翻訳可能文字列を追加・変更したら、以下のコマンドで .pot ファイルを更新してください：

**重要**: メイン POT の生成時は `includes/sample-data.php` を除外してください（サンプルデータは別ドメイン）。

```bash
# メインプラグイン用 POT ファイル生成（sample-data.php を除外）
find . -name "*.php" \
  -not -path "./languages/*" \
  -not -path "./node_modules/*" \
  -not -path "./.git/*" \
  -not -path "./includes/sample-data.php" \
  -print0 | xargs -0 xgettext \
  --default-domain=novel-game-plugin \
  --from-code=UTF-8 \
  --language=PHP \
  --keyword=__ \
  --keyword=_e \
  --keyword=_x:1,2c \
  --keyword=_n:1,2 \
  --keyword=_nx:1,2,4c \
  --keyword=esc_html__ \
  --keyword=esc_html_e \
  --keyword=esc_attr__ \
  --keyword=esc_attr_e \
  --add-comments=translators \
  --package-name="Novel Game Plugin" \
  --package-version="1.3.0" \
  --msgid-bugs-address="https://github.com/shokun0803/novel-game-plugin/issues" \
  --output=languages/novel-game-plugin.pot
```

#### .po / .mo ファイルの更新

**重要**: 翻訳ファイルを更新する前に、必ずバックアップを作成してください。

```bash
# バックアップの作成
cp languages/novel-game-plugin-ja.po languages/novel-game-plugin-ja.po.bak

# 既存の .po ファイルを .pot から更新（既存翻訳を保持しながらマージ）
msgmerge --update --backup=none languages/novel-game-plugin-ja.po languages/novel-game-plugin.pot

# .mo ファイルのコンパイル
# WordPress環境の互換性のため、ja.mo と ja_JP.mo の両方を生成します
msgfmt languages/novel-game-plugin-ja.po -o languages/novel-game-plugin-ja.mo
msgfmt languages/novel-game-plugin-ja.po -o languages/novel-game-plugin-ja_JP.mo
```

**注意**: 日本語翻訳ファイルについて
- WordPress環境によっては `ja.mo` または `ja_JP.mo` のいずれかのみが読み込まれる場合があります
- 互換性を確保するため、両方のファイルを生成・同梱することを推奨します
- これにより、異なるWordPress環境での翻訳表示が確実になります

#### 新しい言語の追加
```bash
# 新しい言語の .po ファイルを作成（例: 英語）
msginit --input=languages/novel-game-plugin.pot \
  --locale=en_US \
  --output=languages/novel-game-plugin-en_US.po

# 翻訳後、.mo ファイルにコンパイル
msgfmt languages/novel-game-plugin-en_US.po -o languages/novel-game-plugin-en_US.mo
```

#### サンプルデータの翻訳ファイル
サンプルゲーム（Shadow Detective）の翻訳は、UI翻訳とは別のテキストドメイン `novel-game-plugin-sample` に分離されています。

**サンプルデータ用POTファイルの生成:**
```bash
# includes/sample-data.php から POT ファイルを生成
xgettext \
  --default-domain=novel-game-plugin-sample \
  --from-code=UTF-8 \
  --language=PHP \
  --keyword=__ \
  --keyword=_e \
  --keyword=_x:1,2c \
  --keyword=_n:1,2 \
  --keyword=_nx:1,2,4c \
  --keyword=esc_html__ \
  --keyword=esc_html_e \
  --keyword=esc_attr__ \
  --keyword=esc_attr_e \
  --add-comments=translators \
  --package-name="Novel Game Plugin - Sample Data" \
  --package-version="1.3.0" \
  --msgid-bugs-address="https://github.com/shokun0803/novel-game-plugin/issues" \
  --output=languages/novel-game-plugin-sample.pot \
  includes/sample-data.php
```

**サンプルデータ用翻訳ファイルの更新:**

**重要**: 翻訳ファイルを更新する前に、必ずバックアップを作成してください。

```bash
# バックアップの作成
cp languages/novel-game-plugin-sample-ja.po languages/novel-game-plugin-sample-ja.po.bak

# 既存の .po ファイルを .pot から更新（既存翻訳を保持しながらマージ）
msgmerge --update --backup=none languages/novel-game-plugin-sample-ja.po languages/novel-game-plugin-sample.pot

# .mo ファイルのコンパイル（ja.mo と ja_JP.mo の両方を生成）
msgfmt languages/novel-game-plugin-sample-ja.po -o languages/novel-game-plugin-sample-ja.mo
msgfmt languages/novel-game-plugin-sample-ja.po -o languages/novel-game-plugin-sample-ja_JP.mo
```

**注意**: 
- サンプルデータの翻訳は `includes/sample-data.php` のみに含まれます
- UI翻訳（`novel-game-plugin`）とサンプルデータ翻訳（`novel-game-plugin-sample`）は独立して管理されます
- これによりサンプルデータの翻訳更新がUI翻訳に影響を与えることを防ぎます
- `msgmerge` を使用することで、既存の翻訳を失わずに新しい文字列を追加できます

### フック・フィルター
プラグインでは以下のWordPressフックを利用：
- `init` - 投稿タイプ登録
- `admin_menu` - 管理画面メニュー
- `the_content` - コンテンツフィルター
- `template_include` - テンプレート読み込み

### エクスポート/インポート

ゲームデータをJSON形式でエクスポート・インポートできます。

#### エクスポート
1. 管理画面「マイゲーム」からゲームを選択
2. 「ゲーム設定」タブを開く
3. 「エクスポート」ボタンをクリック
4. JSONファイルがダウンロードされます

#### インポート
1. 管理画面「マイゲーム」を開く
2. 「インポート」タブをクリック
3. JSONファイルを選択してインポート

**詳細な仕様やトラブルシューティングについては、[JSON インポートガイド](docs/IMPORT_JSON_USER_GUIDE.md) を参照してください。**

最小構成のサンプルファイルは [docs/sample-import.json](docs/sample-import.json) にあります。

#### 主な制限事項
- ファイルサイズ: 最大10MB
- ファイル形式: JSONのみ (.json)
- 重複タイトル: 自動でリネームされます

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

## サンプルゲーム: Shadow Detective（影の探偵）

### 概要
Shadow Detective（影の探偵）は、本格推理ゲームのサンプルとして実装されています。
実業家・黒崎誠の失踪事件を追う探偵となり、証拠を集めながら真相を解明するストーリーです。

### ゲーム仕様
- **シーン数**: 23シーン
- **エンディング**: 3種類（完全解決・部分解決・証拠不足）
- **証拠アイテム**: 5種類（懐中時計・手記・証拠写真・隠し部屋の鍵・闇取引メモ）
- **調査進捗フラグ**: 5種類（妻との会話・友人との会話・隠し部屋発見・裏社会接触・黒幕対峙）

### 特徴
- **複数エンディング**: 証拠収集の度合いによってエンディングが変化
- **フラグシステム**: 10個のフラグによる進行管理と分岐制御
- **条件付き選択肢**: required_flags による選択肢の有効化制御
- **国際化対応**: すべてのテキストが翻訳可能

### 詳細ドキュメント
- [シナリオ詳細設計](docs/shadow-detective-scenario.md) - 全23シーンの詳細なストーリーライン
- [テスト手順書](docs/shadow-detective-testing.md) - 品質保証のためのテストケース

### プレイ方法
1. プラグインを有効化すると自動的にインストールされます
2. 「ノベルゲーム管理」→「マイゲーム」から「Shadow Detective」を選択
3. シーン一覧から最初のシーンを開いてプレイ開始

## 更新履歴

### Version 1.3.0 (予定)
- **Shadow Detective（影の探偵）ゲーム追加**
  - 23シーン構成の本格推理ゲーム
  - 3種類のエンディング（完全解決・部分解決・証拠不足）
  - 10個のフラグによる証拠収集・進捗管理システム
  - required_flags による選択肢条件分岐
  - SVG形式のプレースホルダー画像（背景10種・キャラクター6種）
- プラグイン有効化時に Shadow Detective を自動インストール
- AJAX経由での Shadow Detective 手動インストール機能追加
- 詳細なシナリオ設計ドキュメント・テスト手順書の追加

### Version 1.2.0 (予定)
- サンプルゲーム自動インストール機能追加
  - プラグイン有効化時に学習用サンプルゲームを自動作成
  - 3シーン構成の分岐デモンストレーション
  - SVG形式のプレースホルダー画像を使用
- 「条件で内容変更」モードの仕様変更
  - 条件成立時に代替テキストを表示、条件不成立時に通常テキストを表示
  - 代替テキストが空の場合は通常テキストをフォールバック（空表示を回避）
- 管理画面のUI文言を明確化
  - 「通常表示」→「常に表示」
  - 「条件で非表示」→「条件成立時に非表示」
  - 「条件で内容変更」→「条件成立時に内容変更（代替テキスト表示）」

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
