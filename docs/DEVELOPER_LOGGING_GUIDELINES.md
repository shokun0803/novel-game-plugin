# 開発者向けログメッセージとデバッグ機能ガイドライン

## 概要
このドキュメントでは、Novel Game Plugin における開発者向けログメッセージとユーザー向け翻訳文字列の適切な使い分けについて説明します。

## 基本原則

### 翻訳対象とすべきもの（ユーザー向けメッセージ）
- ユーザーに直接表示されるメッセージ（alert, confirm, エラー通知など）
- UI要素のラベルやボタンのテキスト
- ユーザーが理解すべきエラーメッセージ

### 翻訳対象外とすべきもの（開発者向けメッセージ）
- デバッグ用のログメッセージ
- 開発者向けの技術的な情報
- 内部処理の状態確認用メッセージ
- パフォーマンス測定やトレース情報

## 実装方法

### JavaScript でのデバッグログ

#### debugLog 関数の使用（必須）
開発者向けのデバッグメッセージには **必ず** `debugLog()` 関数を使用してください。この関数は `novelGameDebug` フラグで制御され、本番環境では自動的に無効化されます。

**⚠️ 重要: `console.log()` / `console.warn()` / `console.error()` の直接使用は禁止されています。**

```javascript
// ✅ 推奨: debugLog を使用（通常のログ）
debugLog( 'フラグマスタデータを設定しました:', gameTitle, flagMasterData );
debugLog( 'フラグ条件チェック開始 - 現在のフラグ状態:', currentFlags );
debugLog( 'セリフ', dialogueIndex, 'はフラグ条件を満たさないためスキップします' );

// ✅ 推奨: debugLog を使用（警告ログ）
debugLog( 'warn', 'ストレージキーの生成に失敗しました:', error );
debugLog( 'warn', 'フラグマスタデータが見つかりません:', gameTitle );

// ✅ 推奨: debugLog を使用（エラーログ）
debugLog( 'error', 'ゲームの読み込みに失敗しました:', error );
debugLog( 'error', 'シーンデータの読み込みに失敗:', error );
```

#### console.* の直接使用（禁止）
`console.log` / `console.warn` / `console.error` を直接使用することは禁止されています。以下の例外を除き、すべてのログ出力には `debugLog()` を使用してください。

**例外（コード内にコメントで理由を明記すること）:**
- `js/debug-log.js` の内部実装（共通ユーティリティ）
- 初期化前のシム（try-catch で囲まれた緊急フォールバック）
- `window.novelGameShowFlags()` などのユーザーが明示的に呼び出すデバッグユーティリティ
- `window.novelGameSetDebug()` の結果表示

```javascript
// ❌ 禁止: console.* の直接使用
console.log( 'Game container exists:', $gameContainer.length > 0 );
console.warn( 'Something went wrong' );
console.error( 'Critical error occurred' );

// ✅ 正しい: debugLog を使用
debugLog( 'Game container exists:', $gameContainer.length > 0 );
debugLog( 'warn', 'Something went wrong' );
debugLog( 'error', 'Critical error occurred' );
```

#### ユーザー向けメッセージの翻訳
ユーザーに表示されるメッセージは、必ず翻訳関数を使用してください。

**フロントエンド JavaScript の場合:**
```javascript
// ❌ 悪い例: 翻訳されていない
alert( '保存に失敗しました。' );

// ✅ 良い例: 翻訳関数を使用（wp.i18n が利用可能な場合）
alert( wp.i18n.__( '保存に失敗しました。', 'novel-game-plugin' ) );

// ✅ 良い例: PHP側で翻訳してJavaScriptに渡す
alert( novelGameMeta.strings.saveFailed );
```

**管理画面 JavaScript の場合:**
PHP側で翻訳した文字列を `wp_localize_script` 経由で渡し、JavaScript側で参照してください。

```php
// PHP側（admin/meta-boxes.php など）
$js_strings = array(
    'saveFailed' => esc_html__( '保存に失敗しました。', 'novel-game-plugin' ),
    'saveSuccess' => esc_html__( '保存しました。', 'novel-game-plugin' ),
);

wp_localize_script( 'novel-game-admin-meta-boxes', 'novelGameMeta', array(
    'strings' => $js_strings,
) );
```

```javascript
// JavaScript側
alert( novelGameMeta.strings.saveFailed );
debugLog( novelGameMeta.strings.flagSettingChange, flagName, '→', newValue );
```

### PHP でのデバッグログ

WordPressの標準的なデバッグ機能を使用してください。

```php
// ✅ 開発者向けデバッグログ
if ( WP_DEBUG ) {
    error_log( 'Novel Game: フラグマスタデータを保存しました: ' . $game_title );
}

// ✅ ユーザー向けエラーメッセージ（翻訳必須）
wp_die( esc_html__( 'ゲームの保存に失敗しました。', 'novel-game-plugin' ) );
```

## デバッグモードの制御

### フロントエンドでのデバッグモード有効化
ブラウザのコンソールで以下のコマンドを実行してください：

```javascript
// デバッグモードを有効化
window.novelGameSetDebug( true );

// フラグの状態を表示
window.novelGameShowFlags();

// デバッグモードを無効化
window.novelGameSetDebug( false );
```

### debugLog 関数の実装

debugLog は **`js/debug-log.js`** に共通実装として定義されています。各 JS ファイルに重複定義する必要はありません。

**グローバル実装の利点:**
- 各ファイルでの重複定義を避け、一貫したログ出力機能を提供
- `console[level]` がない環境でも安全に動作
- デバッグフラグの管理を一元化

**読み込み順とフラグの優先ルール:**
1. `wp_add_inline_script` で `window.novelGameDebug` / `window.novelGameAdminDebug` を設定
2. `debug-log.js` が localized オブジェクト（`novelGameFront.debug`, `novelGameMeta.debug`）を検出した場合、それを優先して読み込む
3. 上記の値が undefined の場合は、`window.NOVEL_GAME_DEBUG` をフォールバックとして使用

**wp_add_inline_script を使用する理由:** `debug-log.js` が読み込まれる前にデバッグフラグを設定することで、スクリプト実行時に正しいフラグ値が参照されます。

#### 共通ファイル（js/debug-log.js）

```javascript
// 安全な debugLog 実装（global）
(function(global) {
    'use strict';

    // debug ロード時に localized オブジェクトの debug 値を優先して読み込む
    if ( typeof global.novelGameDebug === 'undefined' && typeof global.novelGameFront !== 'undefined' ) {
        global.novelGameDebug = !!global.novelGameFront.debug;
    }
    if ( typeof global.novelGameAdminDebug === 'undefined' && typeof global.novelGameMeta !== 'undefined' ) {
        global.novelGameAdminDebug = !!global.novelGameMeta.debug;
    }

    // フロント/管理でそれぞれ localize したフラグを読み込む
    var enabled = false;
    if ( typeof global.novelGameDebug !== 'undefined' ) {
        enabled = !!global.novelGameDebug;
    } else if ( typeof global.novelGameAdminDebug !== 'undefined' ) {
        enabled = !!global.novelGameAdminDebug;
    } else if ( typeof global.NOVEL_GAME_DEBUG !== 'undefined' ) {
        enabled = !!global.NOVEL_GAME_DEBUG;
    }

    function safeApplyConsole(level, args) {
        try {
            if ( typeof console !== 'undefined' && console && typeof console[level] === 'function' ) {
                console[level].apply(console, args);
            } else if ( typeof console !== 'undefined' && console && typeof console.log === 'function' ) {
                console.log.apply(console, args);
            }
        } catch (e) {
            // ログ呼び出しに失敗しても処理を中断させない
        }
    }

    function debugLog() {
        if (!enabled) {
            return;
        }
        var args = Array.prototype.slice.call(arguments);
        var levels = ['log','warn','error'];
        var level = 'log';

        if (args.length > 1 && levels.indexOf(args[0]) !== -1) {
            level = args.shift();
        }

        safeApplyConsole(level, args);
    }

    // グローバルに提供する
    global.debugLog = debugLog;
})(window);
```

#### 引数仕様

| 呼び出し方法 | ログレベル | 説明 |
|-------------|-----------|------|
| `debugLog('message')` | log | 単一引数はメッセージとして扱う |
| `debugLog('message', data)` | log | 複数引数は全てログに出力 |
| `debugLog('warn', 'message')` | warn | 第1引数が 'warn' でレベル指定 |
| `debugLog('error', 'message', err)` | error | 第1引数が 'error' でレベル指定 |

**注意:** `debugLog('warn')` のように単一引数でレベル名を渡した場合、それはメッセージとして扱われます（'log' レベルで 'warn' という文字列を出力）。レベル指定として認識されるには、2つ以上の引数が必要です。

#### 管理画面 JavaScript での使用

管理画面では `novelGameAdminDebug` フラグで制御されます。

```javascript
// ブラウザコンソールで管理画面のデバッグモードを有効化
window.novelGameAdminDebug = true;
// または
debugLog.setEnabled(true);
```

## デバッグフラグの設定

デバッグフラグは PHP 側で `wp_localize_script` を使用して設定されます。デフォルトは `WP_DEBUG` の値に従います。

```php
// フロントエンド用
wp_add_inline_script(
    'novel-game-debug-log',
    'window.novelGameDebug = ' . ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'true' : 'false' ) . ';',
    'before'
);

// 管理画面用
wp_add_inline_script(
    'novel-game-debug-log',
    'window.novelGameAdminDebug = ' . ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'true' : 'false' ) . ';',
    'before'
);
```

## .pot ファイルからのデバッグメッセージ除外

### xgettext の設定
JavaScript ファイルから翻訳文字列を抽出する際は、`__` 関数のみをキーワードとして指定します。`console.log` や `debugLog` 内の文字列は抽出対象外です。

```bash
# JavaScript ファイルからの抽出（README.md 参照）
xgettext \
  --language=JavaScript \
  --from-code=UTF-8 \
  --keyword=__ \
  --output=languages/novel-game-plugin-js.pot \
  $(find . -name "*.js" -not -path "./node_modules/*" -not -path "./.git/*")
```

現在の設定では、以下のメッセージは自動的に除外されます：
- `console.log()` 内の文字列
- `debugLog()` 内の文字列
- `console.warn()` 内の文字列
- `console.error()` 内の文字列

## JavaScript の静的チェック（CI）

JavaScript の静的チェックは CI 上で grep ベースの検査を使用しています。以下のパターンを検出します：

### エラーとなるパターン

1. **禁止された console.* の使用**: `debugLog()` 関数を使用してください
   - 検出パターン: `console\.\(log\|warn\|error\|info\|debug\)`
   - 例外: `js/debug-log.js`、try-catch ブロック内、`novelGameShowFlags`/`novelGameSetDebug` 関数内

2. **eval() の使用**: セキュリティリスクのため使用禁止
   - 検出パターン: `\beval\s*\(`

3. **new Function() の使用**: セキュリティリスクのため使用禁止
   - 検出パターン: `\bnew\s+Function\s*\(`

4. **setTimeout/setInterval での文字列評価**: セキュリティリスクのため使用禁止
   - 検出パターン: `setTimeout\s*\(\s*["'\`]`

### 警告のみのパターン（ビルドは失敗しない）

1. **innerHTML の使用**: XSS 脆弱性のリスクがあるため警告表示
   - 検出パターン: `\.innerHTML\s*[+=]`
   - 推奨: `textContent` を使用するか、適切なエスケープ処理を確認

2. **insertAdjacentHTML の使用**: XSS 脆弱性のリスクがあるため警告表示
   - 検出パターン: `insertAdjacentHTML\s*\(`
   - 推奨: 適切なエスケープ処理を確認

### ローカルでのチェック方法

CI と同じチェックをローカルで実行する例。専用スクリプトを使用するか、個別に grep で確認できます：

```bash
# 専用スクリプトで全パターンをチェック（推奨）
bash scripts/check-js-patterns.sh

# または個別にチェック（PCRE パターンを使用するため -P オプションが必要）
# console.* の使用をチェック（debug-log.js 以外）
find js -name "*.js" -type f ! -name "debug-log.js" -print0 | \
  xargs -0 grep -nP 'console\.(log|warn|error|info|debug)\b'

# eval() の使用をチェック
find js -name "*.js" -type f -print0 | \
  xargs -0 grep -nP '\beval\s*\('

# new Function() の使用をチェック
find js -name "*.js" -type f -print0 | \
  xargs -0 grep -nP '\bnew\s+Function\s*\('

# setTimeout/setInterval での文字列評価をチェック
find js -name "*.js" -type f -print0 | \
  xargs -0 grep -nP -E 'set(Timeout|Interval)\s*\(\s*["'"'"'`]'

# innerHTML の使用をチェック（警告）
find js -name "*.js" -type f -print0 | \
  xargs -0 grep -nP -E '\.innerHTML\s*(\+?=)'
```

**注意**: `-P` オプションは PCRE (Perl互換正規表現) を使用します。`\b`（単語境界）や `\s`（空白文字）などのパターンに必要です。

### console.* の許可される使用例

以下のコンテキストでは `console.*` の使用が許可されます：

1. **`js/debug-log.js`**: debugLog 実装のため
2. **明示的な console チェック**: 初期化前シムなど
   ```javascript
   // 許可: 初期化前シムのため debugLog がまだ利用不可
   if ( typeof console !== 'undefined' && typeof console.log === 'function' ) {
     console.log( 'メッセージ' );
   }
   ```
3. **デバッグユーティリティ関数内**: `novelGameShowFlags`、`novelGameSetDebug`
   ```javascript
   // 許可: ユーザーが明示的に呼び出すデバッグユーティリティ
   window.novelGameShowFlags = function() {
     console.log( 'デバッグ情報' );
   };
   ```

## チェックリスト

コードレビュー時には以下を確認してください：

- [ ] ユーザー向けメッセージは翻訳関数を使用しているか
- [ ] 開発者向けデバッグログは `debugLog()` を使用しているか（`console.*` の直接使用は禁止）
- [ ] `debugLog()` で警告・エラーを出力する場合は適切なレベル（`'warn'`, `'error'`）を指定しているか
- [ ] 翻訳関数には必ず `'novel-game-plugin'` textdomain を指定しているか
- [ ] alert/confirm などのユーザー向けダイアログは翻訳されているか
- [ ] PHP側で翻訳した文字列は適切にエスケープされているか（`esc_html__`, `esc_attr__` など）
- [ ] ログメッセージに機密情報（パスワード、トークン等）が含まれていないか
- [ ] 各 JS ファイルに `debugLog` のローカル定義が重複していないか（`js/debug-log.js` を使用）
- [ ] eval()、new Function()、文字列評価の setTimeout/setInterval を使用していないか
- [ ] innerHTML/insertAdjacentHTML を使用する場合は適切なエスケープ処理を実施しているか

## 参考リンク

- [WordPress コーディング規約](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WordPress 国際化ハンドブック](https://developer.wordpress.org/apis/internationalization/)
- [xgettext マニュアル](https://www.gnu.org/software/gettext/manual/html_node/xgettext-Invocation.html)
