# 命名規約ガイドライン

本ドキュメントは、Novel Game Plugin の開発における命名規約を定めたものです。
コードの一貫性と保守性を保つため、すべての開発者はこれらの規約に従ってください。

## 目次
1. [PHP 命名規約](#php-命名規約)
2. [JavaScript 命名規約](#javascript-命名規約)
3. [CSS 命名規約](#css-命名規約)
4. [データベース命名規約](#データベース命名規約)

---

## PHP 命名規約

### 関数名

#### プレフィックス
すべての関数には `noveltool_` プレフィックスを付けてください。
これにより、他のプラグインやテーマとの名前衝突を防ぎます。

```php
// ✅ 正しい例
function noveltool_get_game_data() {
    // ...
}

function noveltool_save_scene() {
    // ...
}

// ❌ 間違った例
function get_game_data() {  // プレフィックスなし
    // ...
}

function novelgame_save_scene() {  // 誤ったプレフィックス
    // ...
}
```

#### ケーススタイル
snake_case（スネークケース）を使用してください。

```php
// ✅ 正しい例
function noveltool_register_post_type() { }
function noveltool_get_all_game_titles() { }
function noveltool_is_ending_scene() { }

// ❌ 間違った例
function noveltool_registerPostType() { }  // camelCase
function noveltool_GetAllGameTitles() { }  // PascalCase
function noveltool_is-ending-scene() { }   // kebab-case
```

### クラス名

#### プレフィックス
クラス名にも `Noveltool_` プレフィックスを付けてください（先頭大文字）。

#### ケーススタイル
PascalCase（パスカルケース）を使用し、単語の区切りにはアンダースコアを使用してください。

```php
// ✅ 正しい例
class Noveltool_Game_Manager {
    // ...
}

class Noveltool_Scene_Builder {
    // ...
}

// ❌ 間違った例
class novelToolGameManager { }  // camelCase
class noveltool_game_manager { }  // snake_case
class NovelGameManager { }  // プレフィックスなし
```

### 変数名

#### ローカル変数
snake_case（スネークケース）を使用してください。

```php
// ✅ 正しい例
$game_title = 'My Game';
$scene_data = array();
$is_ending = true;

// ❌ 間違った例
$gameTitle = 'My Game';  // camelCase
$SceneData = array();    // PascalCase
```

#### グローバル変数・定数
すべて大文字の snake_case を使用し、`NOVELTOOL_` プレフィックスを付けてください。

```php
// ✅ 正しい例
define( 'NOVELTOOL_VERSION', '1.0.0' );
define( 'NOVELTOOL_PLUGIN_PATH', __DIR__ );

// ❌ 間違った例
define( 'VERSION', '1.0.0' );  // プレフィックスなし
define( 'noveltool_version', '1.0.0' );  // 小文字
```

### カスタムフィールドキー

アンダースコア始まりの snake_case を使用してください。
プライベートフィールドは先頭にアンダースコアを付けます。

```php
// ✅ 正しい例
'_game_title'
'_background_image'
'_character_left_name'
'_dialogue_texts'
'_is_ending'

// ❌ 間違った例
'gameTitle'         // camelCase
'BackgroundImage'   // PascalCase
'game-title'        // kebab-case
```

---

## JavaScript 命名規約

### 関数名

#### スコープとプレフィックス
- **グローバル関数**: 避けるべきですが、必要な場合は `noveltool_` プレフィックスを付けてください
- **ローカル関数・モジュール内関数**: lowerCamelCase（キャメルケース）を使用し、プレフィックスは不要です

```javascript
// ✅ 正しい例（ローカル関数）
function setupEventListeners() {
    // ...
}

function renderDialogueList() {
    // ...
}

function isPostSaved() {
    // ...
}

// ✅ 正しい例（グローバル関数 - 避けるべき）
function noveltool_init_game() {
    // ...
}

// ❌ 間違った例
function noveltool_setupEventListeners() {  // ローカル関数にプレフィックス
function setup_event_listeners() {  // snake_case
function SetupEventListeners() {  // PascalCase
```

### 変数名

lowerCamelCase（キャメルケース）を使用してください。

```javascript
// ✅ 正しい例
var dialogueData = [];
var currentSceneId = 0;
var isLoading = false;
var backgroundImage = '';

// ❌ 間違った例
var dialogue_data = [];  // snake_case
var CurrentSceneId = 0;  // PascalCase
var IsLoading = false;   // PascalCase
```

### 定数

すべて大文字の snake_case を使用してください。

```javascript
// ✅ 正しい例
const MAX_FILE_SIZE = 5 * 1024 * 1024;
const DEFAULT_GAME_TITLE = 'Untitled Game';
const ALLOWED_EXTENSIONS = ['jpg', 'png', 'gif'];

// ❌ 間違った例
const maxFileSize = 5 * 1024 * 1024;  // camelCase
const DefaultGameTitle = 'Untitled Game';  // PascalCase
```

### オブジェクトプロパティ（文字列キー）

lowerCamelCase（キャメルケース）を使用してください。
特に、PHPから渡される翻訳文字列等のキーは統一してください。

```javascript
// ✅ 正しい例
var strings = {
    confirmDelete: '本当に削除しますか？',
    deleteTarget: '削除対象:',
    useThisImage: 'この画像を使う',
    selectOption: '-- 選択 --',
    createNew: '+ 新規作成...'
};

// ❌ 間違った例
var strings = {
    'confirm_delete': '本当に削除しますか？',  // snake_case
    'DeleteTarget': '削除対象:',  // PascalCase
    'use-this-image': 'この画像を使う'  // kebab-case
};
```

### jQuery オブジェクト

jQuery オブジェクトを格納する変数には、先頭に `$` を付けてください。

```javascript
// ✅ 正しい例
var $form = $( '#post' );
var $button = $( '.submit-button' );
var $dialogueList = $( '#novel-dialogue-list' );

// ❌ 間違った例
var form = $( '#post' );  // $ なし
var button = $( '.submit-button' );  // $ なし
```

---

## CSS 命名規約

### クラス名

kebab-case（ケバブケース）を使用し、プラグイン固有のプレフィックス `noveltool-` を付けてください。

```css
/* ✅ 正しい例 */
.noveltool-game-card { }
.noveltool-dialogue-item { }
.noveltool-character-left { }
.noveltool-button-primary { }

/* ❌ 間違った例 */
.novelToolGameCard { }  /* camelCase */
.noveltool_game_card { }  /* snake_case */
.NovelToolGameCard { }  /* PascalCase */
.game-card { }  /* プレフィックスなし */
```

### ID名

kebab-case（ケバブケース）を使用し、プラグイン固有のプレフィックス `novel-` を付けてください。

```html
<!-- ✅ 正しい例 -->
<div id="novel-game-container"></div>
<input id="novel-dialogue-text">
<button id="novel-create-next-command"></button>

<!-- ❌ 間違った例 -->
<div id="novelGameContainer"></div>  <!-- camelCase -->
<input id="novel_dialogue_text">  <!-- snake_case -->
<div id="game-container"></div>  <!-- プレフィックスなし -->
```

---

## データベース命名規約

### テーブル名

WordPress プレフィックス（`$wpdb->prefix`）の後に `noveltool_` を付け、snake_case を使用してください。

```php
// ✅ 正しい例
global $wpdb;
$table_name = $wpdb->prefix . 'noveltool_games';
$table_name = $wpdb->prefix . 'noveltool_game_flags';
$table_name = $wpdb->prefix . 'noveltool_scene_data';

// ❌ 間違った例
$table_name = $wpdb->prefix . 'games';  // プレフィックスなし
$table_name = $wpdb->prefix . 'noveltoolGames';  // camelCase
```

### カラム名

snake_case（スネークケース）を使用してください。

```sql
-- ✅ 正しい例
CREATE TABLE wp_noveltool_games (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    game_title VARCHAR(255) NOT NULL,
    game_description TEXT,
    title_image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- ❌ 間違った例
CREATE TABLE wp_noveltool_games (
    id BIGINT(20),
    gameTitle VARCHAR(255),  -- camelCase
    GameDescription TEXT,    -- PascalCase
    title-image VARCHAR(255) -- kebab-case
);
```

---

## WordPress 固有の規約

### フック名（アクション・フィルター）

snake_case を使用し、プラグイン固有のプレフィックス `noveltool_` を付けてください。

```php
// ✅ 正しい例
do_action( 'noveltool_before_game_save', $game_data );
apply_filters( 'noveltool_game_title', $title );
add_action( 'noveltool_after_scene_render', 'noveltool_custom_function' );

// ❌ 間違った例
do_action( 'beforeGameSave', $game_data );  // camelCase
apply_filters( 'game_title', $title );  // プレフィックスなし
```

### ショートコード名

snake_case を使用し、プラグイン固有のプレフィックスを付けてください。

```php
// ✅ 正しい例
add_shortcode( 'novel_game_list', 'noveltool_game_list_shortcode' );
add_shortcode( 'novel_game_posts', 'noveltool_game_posts_shortcode' );

// ❌ 間違った例
add_shortcode( 'novelGameList', 'noveltool_game_list_shortcode' );  // camelCase
add_shortcode( 'game-list', 'noveltool_game_list_shortcode' );  // プレフィックスなし
```

### オプション名

snake_case を使用し、プラグイン固有のプレフィックス `noveltool_` を付けてください。

```php
// ✅ 正しい例
update_option( 'noveltool_version', '1.0.0' );
get_option( 'noveltool_default_game_title' );

// ❌ 間違った例
update_option( 'version', '1.0.0' );  // プレフィックスなし
get_option( 'novelToolDefaultGameTitle' );  // camelCase
```

---

## コードレビュー時のチェックポイント

以下の項目を確認してください：

### PHP
- [ ] すべての関数に `noveltool_` プレフィックスが付いているか
- [ ] 関数名・変数名が snake_case になっているか
- [ ] クラス名が `Noveltool_` プレフィックス付きの PascalCase になっているか
- [ ] 定数名がすべて大文字の snake_case になっているか
- [ ] カスタムフィールドキーがアンダースコア始まりの snake_case になっているか

### JavaScript
- [ ] ローカル関数名が lowerCamelCase になっているか（プレフィックスなし）
- [ ] グローバル関数名に `noveltool_` プレフィックスが付いているか
- [ ] 変数名が lowerCamelCase になっているか
- [ ] 文字列キー（オブジェクトプロパティ）が lowerCamelCase になっているか
- [ ] 定数名がすべて大文字の snake_case になっているか
- [ ] jQuery オブジェクト変数に `$` プレフィックスが付いているか

### CSS
- [ ] クラス名が `noveltool-` プレフィックス付きの kebab-case になっているか
- [ ] ID名が `novel-` プレフィックス付きの kebab-case になっているか

### データベース
- [ ] テーブル名に WordPress プレフィックスと `noveltool_` プレフィックスが付いているか
- [ ] カラム名が snake_case になっているか

### WordPress 固有
- [ ] フック名（アクション・フィルター）が `noveltool_` プレフィックス付きの snake_case になっているか
- [ ] ショートコード名が適切なプレフィックス付きの snake_case になっているか
- [ ] オプション名が `noveltool_` プレフィックス付きの snake_case になっているか

---

## 参考資料

- [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- [WordPress HTML Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/)

---

## 更新履歴

- **1.0.0** (2025-10-14): 初版作成 - Phase 3 命名規約統一作業の一環として策定
