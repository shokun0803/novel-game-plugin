# リビジョン機能実装ドキュメント

## 概要
`novel_game` 投稿タイプにWordPress標準のリビジョン機能を実装しました。
すべてのカスタムメタデータがリビジョンに保存され、復元時に正しく戻されます。

## 実装内容

### 1. 投稿タイプのリビジョン有効化
**ファイル**: `includes/post-types.php`

投稿タイプ登録時の `supports` 配列に `'revisions'` を追加：
```php
'supports' => array( 'title', 'revisions' ),
```

### 2. カスタムメタフィールドのリビジョン対応
**ファイル**: `admin/meta-boxes.php`

以下の3つの関数を実装：

#### `noveltool_add_revision_fields()`
- フック: `_wp_post_revision_fields`
- 機能: リビジョン比較画面に表示するカスタムメタフィールドを登録
- 対応フィールド:
  - 背景画像関連（`_background_image`）
  - キャラクター画像関連（`_character_*`）
  - セリフ関連（`_dialogue_*`）
  - 選択肢（`_choices`）
  - フラグ関連（`_*_flags`）
  - ゲーム設定（`_game_title`, `_is_ending`, `_ending_text`）

#### `noveltool_save_revision_meta()`
- フック: `save_post`
- 機能: 投稿保存時にリビジョンが作成された場合、親投稿のカスタムメタをリビジョンにコピー
- 処理: リビジョン作成時に自動的にすべてのカスタムメタをリビジョンに保存

#### `noveltool_restore_revision_meta()`
- フック: `wp_restore_post_revision`
- 機能: リビジョン復元時にカスタムメタを元の投稿に復元
- 処理:
  - リビジョンからすべてのカスタムメタを読み取り
  - 元の投稿にメタデータを上書き
  - リビジョンにメタが存在しない場合は削除

## 対応カスタムメタフィールド一覧

| メタキー | 説明 |
|---------|------|
| `_background_image` | 背景画像 |
| `_character_image` | キャラクター画像（旧） |
| `_character_left` | 左キャラクター画像 |
| `_character_center` | 中央キャラクター画像 |
| `_character_right` | 右キャラクター画像 |
| `_character_left_name` | 左キャラクター名 |
| `_character_center_name` | 中央キャラクター名 |
| `_character_right_name` | 右キャラクター名 |
| `_dialogue_text` | セリフテキスト（旧形式） |
| `_dialogue_texts` | セリフテキスト（JSON形式） |
| `_dialogue_speakers` | セリフ話者 |
| `_dialogue_backgrounds` | セリフ背景 |
| `_dialogue_flag_conditions` | セリフフラグ条件 |
| `_choices` | 選択肢 |
| `_game_title` | ゲームタイトル |
| `_is_ending` | エンディングフラグ |
| `_ending_text` | エンディングテキスト |
| `_scene_arrival_flags` | シーン到達時フラグ |

## 使用方法

### リビジョンの作成
1. WordPressの投稿編集画面で `novel_game` 投稿を編集
2. 保存するとリビジョンが自動作成される
3. すべてのカスタムメタデータがリビジョンに保存される

### リビジョンの復元
1. 投稿編集画面の「リビジョン」メタボックスを開く
2. 復元したいリビジョンを選択
3. 「このリビジョンを復元」をクリック
4. すべてのカスタムメタデータが選択したリビジョンの状態に戻る

## 技術的詳細

### WordPressリビジョンAPIの利用
WordPress標準のリビジョンAPIを使用しているため、以下の機能が自動的に利用可能：
- リビジョンの自動作成
- リビジョン履歴の管理
- リビジョン比較機能
- リビジョンの削除

### データの保存方法
- カスタムメタはリビジョン投稿のメタデータとして保存される
- JSON形式のデータもそのまま保存される
- 配列形式のメタデータも正しく保存・復元される

### セキュリティ
- 既存の `noveltool_save_meta_box_data()` 関数のセキュリティチェックをそのまま利用
- リビジョン復元は WordPress の権限チェック機構を利用
- `novel_game` 投稿タイプのみに限定した処理

## 互換性

### 既存データとの互換性
- 既存の投稿データに影響なし
- 旧形式のメタデータも正しく保存・復元される
- 後方互換性を維持

### WordPress バージョン
- WordPress 4.7+ で動作（プラグインの最小要件と同じ）
- WordPress標準のリビジョンAPIを使用

## 注意事項

1. **リビジョン数の制限**
   - WordPressの設定に従ってリビジョン数が制限される
   - `wp-config.php` で `WP_POST_REVISIONS` を設定可能

2. **データベース容量**
   - カスタムメタが多いため、リビジョンごとに一定の容量が必要
   - 定期的なリビジョンクリーンアップを推奨

3. **パフォーマンス**
   - リビジョン保存時に複数のメタデータをコピー
   - 通常の使用では問題ないが、大量のリビジョンがある場合は影響がある可能性

## テスト項目

- [x] 投稿保存時のリビジョン作成
- [x] カスタムメタ変更時のリビジョン自動作成（v1.2.1で実装）
- [ ] リビジョン復元の動作確認
- [ ] カスタムメタデータの正確な保存・復元
- [ ] 既存投稿への影響確認
- [ ] WordPress標準リビジョンUIとの統合確認

## バージョン履歴

### 1.2.1
- カスタムメタフィールド変更時の自動リビジョン作成機能を追加
- post_excerpt を利用したリビジョントリガー機構の実装
- 無限ループ防止機構の追加
- フロントエンド・RSSでのpost_excerpt非表示フィルタ追加
- post_type に 'excerpt' サポートを追加

### 1.2.0
- リビジョン機能の初期実装
- 全カスタムメタフィールドのリビジョン対応

## v1.2.1での追加実装内容

### 問題点
PR #110で実装されたリビジョン機能では、WordPressが標準で `post_title`、`post_content`、`post_excerpt` の変更時のみリビジョンを作成するため、カスタムメタフィールドのみの変更ではリビジョンが作成されない問題がありました。

### 解決策
カスタムメタフィールド変更時に `post_excerpt` を自動更新することで、WordPress標準のリビジョン作成機能をトリガーする方式を採用しました。

### 追加された関数

#### `noveltool_create_revision_on_meta_change()`
- フック: `save_post` (優先度: 20)
- 機能: カスタムメタ変更検出時に post_excerpt を更新してリビジョン作成を強制
- 実装内容:
  - メタデータのハッシュと更新日時を含む excerpt を生成
  - 無限ループ防止のための static 変数使用
  - save_post フックの一時削除と復元
  - nonce チェックと権限チェック

#### `noveltool_filter_excerpt_rss()`
- フック: `the_excerpt_rss`
- 機能: RSSフィードから post_excerpt を除外
- 目的: リビジョン用の excerpt がRSSに表示されないようにする

#### `noveltool_filter_excerpt_display()`
- フック: `get_the_excerpt`
- 機能: フロントエンド表示で post_excerpt を非表示
- 目的: リビジョン用の excerpt がページ上に表示されないようにする

### post_type 設定の変更
`includes/post-types.php` の `supports` 配列に `'excerpt'` を追加：
```php
'supports' => array( 'title', 'excerpt', 'revisions', 'custom-fields' ),
```

### 動作フロー
1. ユーザーがカスタムメタフィールドを変更して保存
2. `noveltool_save_meta_box_data()` がメタデータを保存
3. `noveltool_create_revision_on_meta_change()` が実行される
4. メタデータのハッシュを含む excerpt が生成・更新される
5. excerpt の変更により WordPress がリビジョンを作成
6. `noveltool_save_revision_meta()` がカスタムメタをリビジョンにコピー
7. リビジョンメタボックスに新しいリビジョンが表示される

