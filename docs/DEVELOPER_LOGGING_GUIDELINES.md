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

#### debugLog 関数の使用（推奨）
開発者向けのデバッグメッセージには `debugLog()` 関数を使用してください。この関数は `novelGameDebug` フラグで制御され、本番環境では自動的に無効化されます。

```javascript
// ✅ 推奨: debugLog を使用
debugLog( 'フラグマスタデータを設定しました:', gameTitle, flagMasterData );
debugLog( 'フラグ条件チェック開始 - 現在のフラグ状態:', currentFlags );
debugLog( 'セリフ', dialogueIndex, 'はフラグ条件を満たさないためスキップします' );
```

#### console.log の直接使用
`console.log` を直接使用する場合は、必ず開発者向けの技術的なメッセージに限定してください。ユーザー向けメッセージには使用しないでください。

```javascript
// ⚠️ 許容されるが debugLog 推奨
console.log( 'Game container exists:', $gameContainer.length > 0 );
console.log( 'Modal initialization:', { overlay: found, buttons: initialized } );
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
console.log( novelGameMeta.strings.flagSettingChange, flagName, '→', newValue );
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
frontend.js には既に以下の実装があります：

```javascript
// デバッグフラグ（本番環境でのログ出力制御）
var novelGameDebug = typeof window.novelGameDebug !== 'undefined' ? window.novelGameDebug : false;

/**
 * デバッグログ出力（本番環境では無効化）
 *
 * @param {string} message ログメッセージ
 * @param {...*} args 追加引数
 * @since 1.2.0
 */
function debugLog( message ) {
    if ( novelGameDebug ) {
        var args = Array.prototype.slice.call( arguments );
        console.log.apply( console, args );
    }
}
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

## チェックリスト

コードレビュー時には以下を確認してください：

- [ ] ユーザー向けメッセージは翻訳関数を使用しているか
- [ ] 開発者向けデバッグログは `debugLog()` または `console.log()` を使用しているか
- [ ] 翻訳関数には必ず `'novel-game-plugin'` textdomain を指定しているか
- [ ] alert/confirm などのユーザー向けダイアログは翻訳されているか
- [ ] PHP側で翻訳した文字列は適切にエスケープされているか（`esc_html__`, `esc_attr__` など）

## 参考リンク

- [WordPress コーディング規約](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WordPress 国際化ハンドブック](https://developer.wordpress.org/apis/internationalization/)
- [xgettext マニュアル](https://www.gnu.org/software/gettext/manual/html_node/xgettext-Invocation.html)
