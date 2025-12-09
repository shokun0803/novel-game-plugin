# 開発者向けガイド

本ドキュメントは、Novel Game Plugin の開発に参加する開発者向けの情報をまとめたものです。

## 目次

1. [開発環境セットアップ](#開発環境セットアップ)
2. [コーディング規約](#コーディング規約)
3. [JavaScript コードチェック](#javascript-コードチェック)
4. [翻訳ファイルの更新手順](#翻訳ファイルの更新手順)
5. [フック・フィルター](#フックフィルター)
6. [コードレビュー](#コードレビュー)
7. [開発者向けログメッセージとデバッグ機能](#開発者向けログメッセージとデバッグ機能)
8. [貢献方法](#貢献方法)

---

## 開発環境セットアップ

### リポジトリの取得

```bash
# 開発版の取得
git clone https://github.com/shokun0803/novel-game-plugin.git
cd novel-game-plugin

# 開発ブランチで作業
git checkout -b feature/new-feature
```

### 開発に必要なツール

- WordPress 4.7 以上
- PHP 7.0 以上
- MySQL 5.6 以上
- gettext ツール（翻訳ファイルの更新時に必要）
  - `xgettext`, `msgmerge`, `msgfmt` コマンド

---

## コーディング規約

### WordPress 公式コーディング規約の準拠

- [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) に準拠
- [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/) に準拠
- [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/) に準拠

### 基本的なルール

- **PHP**: インデントはスペース4つ、関数・変数・定数の命名にはスネークケースを使用
- **JavaScript**: インデントはタブ、変数名は lowerCamelCase を使用
- **CSS**: インデントはタブ、クラス名は kebab-case を使用
- **プレフィックス**: すべての関数・クラスに `noveltool_` プレフィックスを付ける
- **セキュリティ**: 入力値の検証とサニタイズ、出力時のエスケープ処理を必ず実施
- **国際化**: すべての表示テキストに翻訳関数（`__()`, `_e()` など）を使用

### 詳細な命名規約

詳細な命名規約については、[命名規約ガイドライン](NAMING_CONVENTIONS.md) を参照してください。

---

## JavaScript コードチェック

JavaScript コードの品質チェックは、CI（GitHub Actions）で自動的に実行される grep ベースのチェックによって行われます。

### エラーとなるパターン

- **禁止された console.* の使用**: `debugLog()` 関数を使用してください
- **eval() の使用**: セキュリティリスクのため使用禁止
- **new Function() の使用**: セキュリティリスクのため使用禁止
- **setTimeout/setInterval での文字列評価**: セキュリティリスクのため使用禁止

### 警告のみのパターン（ビルドは失敗しません）

- **innerHTML の使用**: XSS 脆弱性のリスクがあるため警告表示（適切なエスケープ処理を確認してください）
- **insertAdjacentHTML の使用**: XSS 脆弱性のリスクがあるため警告表示（適切なエスケープ処理を確認してください）

### ローカルでのチェック方法（任意）

CI と同じチェックをローカルで実行できます。専用スクリプトを使用するか、個別に grep で確認できます：

```bash
# 専用スクリプトで全パターンをチェック（推奨）
bash scripts/check-js-patterns.sh

# または個別にチェック（PCRE パターンを使用するため -P オプションが必要）
# console.* の使用をチェック（debug-log.js 以外）
find js -name "*.js" -type f ! -name "debug-log.js" -print0 | \
  xargs -0 grep -nP 'console\.(log|warn|error|info|debug)\b'

# eval() の使用をチェック
find js -name "*.js" -type f -print0 | xargs -0 grep -nP '\beval\s*\('

# new Function() の使用をチェック
find js -name "*.js" -type f -print0 | xargs -0 grep -nP '\bnew\s+Function\s*\('

# innerHTML の使用をチェック（警告）
find js -name "*.js" -type f -print0 | xargs -0 grep -nP -E '\.innerHTML\s*(\+?=)'
```

**注意**: `-P` オプションは PCRE (Perl互換正規表現) を使用します。`\b`（単語境界）や `\s`（空白文字）などのパターンに必要です。

詳細は [開発者向けログメッセージガイドライン](DEVELOPER_LOGGING_GUIDELINES.md) を参照してください。

---

## 翻訳ファイルの更新手順

このプラグインは国際化（i18n）に対応しており、textdomain `novel-game-plugin` を使用しています。

### 翻訳可能文字列の追加

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

### .pot ファイルの更新

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

### .po / .mo ファイルの更新

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

### 新しい言語の追加

```bash
# 新しい言語の .po ファイルを作成（例: 英語）
msginit --input=languages/novel-game-plugin.pot \
  --locale=en_US \
  --output=languages/novel-game-plugin-en_US.po

# 翻訳後、.mo ファイルにコンパイル
msgfmt languages/novel-game-plugin-en_US.po -o languages/novel-game-plugin-en_US.mo
```

### サンプルデータの翻訳ファイル

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

---

## フック・フィルター

プラグインでは以下のWordPressフックを利用：

- `init` - 投稿タイプ登録
- `admin_menu` - 管理画面メニュー
- `the_content` - コンテンツフィルター
- `template_include` - テンプレート読み込み

カスタムフックを追加する場合は、必ず `noveltool_` プレフィックスを付けてください。

---

## コードレビュー

プルリクエストのレビュー時には、[コードレビューチェックリスト](CODE_REVIEW_CHECKLIST.md) を活用してください。

レビューで確認すべき主なポイント：

- [ ] WordPress コーディング規約に準拠しているか
- [ ] 命名規約が守られているか
- [ ] セキュリティ対策が適切に実装されているか
- [ ] 入力値のサニタイゼーションと出力のエスケープが実施されているか
- [ ] 翻訳関数が適切に使用されているか
- [ ] PHPDoc コメントが日本語で記述されているか
- [ ] 既存機能を損なっていないか

### コメント主導レビューポリシーの遵守
- すべてのレビューでは、リモートの最新コメント・コミットを取得し、レビュー対象を確定してください。特に『ユーザー（オーナー）による最新のコメント (anchor) → それに対する reply → reply に紐づくコミット』の流れに従って、**該当コミットのみをレビュー対象**にしてください。これにより、重複レビューを避け、効率的に差分確認を行えます。

例: anchor の createdAt と reply の createdAt の範囲にコミットが追加されているかを確認し、該当コミットのみ `git show` で確認する。

---

## 開発者向けログメッセージとデバッグ機能

開発者向けログメッセージとユーザー向け翻訳文字列の適切な使い分けについては、[開発者向けログメッセージガイドライン](DEVELOPER_LOGGING_GUIDELINES.md) を参照してください。

**重要なポイント:**
- ユーザー向けメッセージは必ず翻訳関数（`__()`, `_e()` など）を使用
- 開発者向けデバッグログは `debugLog()` 関数を使用（翻訳不要）
- フロントエンドでは `debugLog()` 関数を使用することで、本番環境でのログ出力を制御可能
- `console.log()` / `console.warn()` / `console.error()` の直接使用は禁止（`debugLog()` を使用）

---

## 貢献方法

### 開発への貢献手順

1. リポジトリをフォーク
2. 機能ブランチを作成（`feature/new-feature`, `fix/bug-fix` など）
3. 変更をコミット
4. プルリクエストを作成（base: `dev` ブランチ）

### プルリクエストの作成

- **base ブランチ**: 必ず `dev` を指定してください（`master` への直接マージは禁止）
- **タイトル・説明**: 日本語で記述してください
- **変更内容**: 何を変更したか、なぜ変更したかを明確に記載してください
- **関連 Issue**: 関連する Issue があれば参照してください

### バグレポート・機能要望

- GitHubのIssueでご報告ください
- 再現手順を詳しく記載してください
- 期待する動作と実際の動作を明記してください

---

## 関連ドキュメント

- [命名規約ガイドライン](NAMING_CONVENTIONS.md) - 詳細な命名規約
- [コードレビューチェックリスト](CODE_REVIEW_CHECKLIST.md) - レビュー時のチェック項目
- [開発者向けログメッセージガイドライン](DEVELOPER_LOGGING_GUIDELINES.md) - ログとデバッグ機能の使い方

---

## ライセンス

このプラグインはGPLv2またはそれ以降のバージョンでライセンスされています。  
詳細は [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) をご確認ください。

すべてのコードは GPLv2 互換ライセンスに準拠してください。サードパーティのライブラリやコードを使用する場合は、ライセンスの互換性を確認してください。
