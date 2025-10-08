# novel_game投稿タイプのリビジョン機能実装ドキュメント

## 概要

novel_game投稿タイプにカスタムメタフィールドの変更履歴を管理するリビジョン機能を実装しました。WordPress標準のリビジョン機能を活用した安全で効率的な実装です。

## 実装方式：統合カスタムフィールド方式

すべてのカスタムフィールドデータを **単一の統合フィールド** (`_noveltool_unified_meta`) にJSON文字列として保存し、WordPress標準のリビジョン機能を安全に活用する方式です。

### 技術的利点

1. **WordPress準拠**: 標準的なカスタムフィールド文字列保存を基本とする
2. **安全性確保**: 配列データをJSON化することでWordPressコアの`trim()`エラーを回避
3. **シンプル設計**: 管理対象を統合フィールド1つに集約
4. **拡張性**: 将来的なカスタムフィールド追加に柔軟対応

## 対象カスタムフィールド（全18個）

| フィールドキー | 説明 | データ型 |
|---|---|---|
| `_background_image` | 背景画像 | 文字列 |
| `_character_image` | キャラクター画像（後方互換） | 文字列 |
| `_character_left` | 左キャラクター画像 | 文字列 |
| `_character_center` | 中央キャラクター画像 | 文字列 |
| `_character_right` | 右キャラクター画像 | 文字列 |
| `_character_left_name` | 左キャラクター名 | 文字列 |
| `_character_center_name` | 中央キャラクター名 | 文字列 |
| `_character_right_name` | 右キャラクター名 | 文字列 |
| `_dialogue_text` | セリフテキスト（後方互換） | 文字列 |
| `_dialogue_texts` | セリフテキスト配列 | 配列 |
| `_dialogue_backgrounds` | セリフ背景配列 | 配列 |
| `_dialogue_speakers` | セリフ話者配列 | 配列 |
| `_dialogue_flag_conditions` | セリフフラグ条件 | 配列 |
| `_choices` | 選択肢データ | 文字列（JSON） |
| `_game_title` | ゲームタイトル | 文字列 |
| `_is_ending` | エンディングフラグ | 真偽値 |
| `_ending_text` | エンディングテキスト | 文字列 |
| `_scene_arrival_flags` | シーン到達時フラグ | 配列 |

## 実装内容

### 1. 新規ファイル

#### `includes/revisions.php`

リビジョン機能の中核を担うファイルです。以下の機能を提供します：

- カスタムメタの統合・復元処理
- WordPressリビジョンAPIとの統合
- リビジョン比較画面でのデータ表示

### 2. 主要関数

#### `noveltool_get_revision_meta_keys()`
リビジョン管理対象のカスタムフィールドキー配列を返します。

#### `noveltool_get_unified_custom_meta( $post_id )`
投稿IDから全カスタムメタデータを取得し、JSON文字列として統合します。

**返り値**: JSON文字列（Unicode対応）

#### `noveltool_restore_unified_custom_meta( $post_id, $unified_json )`
統合JSON文字列から個別カスタムメタデータに復元します。

**パラメータ**:
- `$post_id`: 投稿ID
- `$unified_json`: 統合JSON文字列

**返り値**: 成功時true、失敗時false

#### `noveltool_has_custom_meta_changed( $post_id )`
カスタムメタデータの変更を検出します。

**返り値**: 変更があればtrue

#### `noveltool_save_unified_custom_meta( $post_id )`
投稿保存時に統合カスタムフィールドを更新します。

**フック**: `save_post_novel_game` アクション（優先度20）

#### `noveltool_add_revision_fields( $fields )`
WordPressリビジョン機能に統合カスタムフィールドを登録します。

**フック**: `wp_post_revision_fields` フィルター

#### `noveltool_save_revision_meta( $revision_id )`
リビジョン作成時に統合カスタムフィールドをコピーします。

**フック**: `wp_insert_post` アクション

#### `noveltool_restore_revision_meta( $post_id, $revision_id )`
リビジョン復元時に統合カスタムフィールドを復元します。

**フック**: `wp_restore_post_revision` アクション

#### `noveltool_revision_field_display( $value, $field, $compare_from, $context )`
リビジョン比較画面でのカスタムフィールドデータ表示を制御します。

**フック**: `wp_post_revision_field__noveltool_unified_meta` フィルター

### 3. 無限ループ防止機構

以下の条件でリビジョン処理をスキップし、無限ループを防止しています：

1. 自動保存時: `DOING_AUTOSAVE`定数チェック
2. リビジョン保存時: `wp_is_post_revision()`でリビジョンIDを検出
3. 投稿タイプチェック: `novel_game`以外の投稿タイプは処理しない
4. 権限チェック: `current_user_can('edit_post')`で権限確認

## 動作フロー

### 投稿保存時

```
1. ユーザーがnovel_game投稿を保存
   ↓
2. 既存のカスタムメタ保存処理（admin/meta-boxes.php）
   ↓
3. noveltool_save_unified_custom_meta()実行
   - 全カスタムフィールドを取得
   - JSON文字列に統合
   - _noveltool_unified_metaとして保存
   ↓
4. WordPressがリビジョンを自動作成
   ↓
5. noveltool_save_revision_meta()実行
   - 親投稿の_noveltool_unified_metaを取得
   - リビジョンにコピー
```

### リビジョン復元時

```
1. ユーザーがリビジョンを選択して復元
   ↓
2. WordPressが標準フィールド（タイトル等）を復元
   ↓
3. noveltool_restore_revision_meta()実行
   - リビジョンから_noveltool_unified_metaを取得
   - JSONデコード
   - 各カスタムフィールドに復元
   - _noveltool_unified_metaも更新（整合性確保）
```

### リビジョン比較画面

```
1. ユーザーがリビジョン比較画面を表示
   ↓
2. WordPressが各フィールドの差分を表示
   ↓
3. noveltool_revision_field_display()実行
   - _noveltool_unified_metaのJSON文字列を整形
   - 各フィールドを日本語ラベル付きで表示
   - 配列フィールドは要素数を表示
```

## テスト方法

### 1. 基本動作確認

1. **投稿の作成と編集**
   ```
   - WordPress管理画面にログイン
   - ノベルゲーム > 新規追加
   - カスタムフィールド（背景画像、セリフ、選択肢等）を入力
   - 「公開」をクリック
   - 投稿を再度編集してカスタムフィールドを変更
   - 「更新」をクリック
   ```

2. **リビジョンの確認**
   ```
   - 投稿編集画面で「リビジョン」メタボックスを確認
   - 「リビジョンを表示」リンクをクリック
   - リビジョン比較画面で「カスタムフィールドデータ」欄を確認
   - 変更内容が表示されていることを確認
   ```

3. **リビジョンの復元**
   ```
   - リビジョン比較画面で古いリビジョンを選択
   - 「このリビジョンを復元」をクリック
   - 投稿編集画面で各カスタムフィールドが復元されていることを確認
   ```

### 2. 詳細テストケース

#### テストケース1: 基本フィールドの復元
```
目的: 文字列型フィールドの復元を確認

手順:
1. 新規投稿を作成
   - ゲームタイトル: "テストゲーム"
   - 背景画像: "bg1.jpg"
2. 保存
3. フィールドを変更
   - ゲームタイトル: "変更後ゲーム"
   - 背景画像: "bg2.jpg"
4. 更新
5. 最初のリビジョンに復元

期待結果:
- ゲームタイトル: "テストゲーム"に復元
- 背景画像: "bg1.jpg"に復元
```

#### テストケース2: 配列フィールドの復元
```
目的: 配列型フィールド（セリフ、選択肢等）の復元を確認

手順:
1. 新規投稿を作成
   - セリフ1: "こんにちは"
   - セリフ2: "さようなら"
   - 選択肢1: "はい"
2. 保存
3. フィールドを変更
   - セリフ1: "おはよう"（変更）
   - セリフ2: "さようなら"（変更なし）
   - セリフ3: "ありがとう"（追加）
   - 選択肢1を削除
4. 更新
5. 最初のリビジョンに復元

期待結果:
- セリフ1: "こんにちは"に復元
- セリフ2: "さようなら"（変更なし）
- セリフ3が削除される
- 選択肢1: "はい"が復元される
```

#### テストケース3: 複雑なデータ構造の復元
```
目的: 選択肢のフラグ設定等、複雑なデータ構造の復元を確認

手順:
1. 新規投稿を作成
   - 選択肢1: "選択A"、フラグ設定: "flag1=true"
   - 選択肢2: "選択B"、フラグ設定: "flag2=false"
2. 保存
3. フィールドを変更
   - 選択肢1のフラグ設定: "flag1=false"
   - 選択肢2のフラグ設定: "flag2=true, flag3=true"
4. 更新
5. 最初のリビジョンに復元

期待結果:
- 選択肢1のフラグ設定: "flag1=true"に復元
- 選択肢2のフラグ設定: "flag2=false"に復元（flag3は削除）
```

#### テストケース4: 無限ループ防止の確認
```
目的: リビジョン保存時の無限ループが発生しないことを確認

手順:
1. 新規投稿を作成
2. カスタムフィールドを入力
3. 保存
4. wp_posts テーブルでpost_type='revision'のレコード数を確認
5. 再度同じ内容で保存
6. リビジョンレコード数を再確認

期待結果:
- リビジョンが正常に1つずつ増加
- リビジョンが無制限に増加しない
- PHPエラーやタイムアウトが発生しない
```

### 3. データベース確認

```sql
-- 統合メタフィールドの確認
SELECT post_id, meta_key, meta_value 
FROM wp_postmeta 
WHERE meta_key = '_noveltool_unified_meta' 
ORDER BY post_id DESC 
LIMIT 5;

-- リビジョンの統合メタフィールド確認
SELECT p.post_type, pm.post_id, pm.meta_key, LEFT(pm.meta_value, 100) as meta_value_preview
FROM wp_postmeta pm
JOIN wp_posts p ON pm.post_id = p.ID
WHERE pm.meta_key = '_noveltool_unified_meta'
AND p.post_type = 'revision'
ORDER BY pm.post_id DESC
LIMIT 5;

-- 特定投稿のリビジョン一覧
SELECT ID, post_parent, post_modified, post_status
FROM wp_posts
WHERE post_parent = [投稿ID]
AND post_type = 'revision'
ORDER BY post_modified DESC;
```

## トラブルシューティング

### 問題1: リビジョンが作成されない

**原因**: WordPressのリビジョン機能が無効化されている

**解決策**:
```php
// wp-config.phpで以下を確認
define('WP_POST_REVISIONS', true); // または数値（保存するリビジョン数）
```

### 問題2: カスタムフィールドが復元されない

**原因**: 統合メタフィールドが保存されていない

**確認方法**:
1. 投稿編集画面で「カスタムフィールド」メタボックスを表示
2. `_noveltool_unified_meta`フィールドが存在するか確認
3. 値がJSON形式であることを確認

**解決策**:
- 投稿を再保存してデータを再生成
- PHPエラーログを確認

### 問題3: リビジョン比較画面でエラー

**原因**: JSON文字列が不正

**解決策**:
```php
// includes/revisions.phpの以下の部分にデバッグコードを追加
function noveltool_revision_field_display( $value, $field, $compare_from, $context ) {
    error_log( 'Revision field value: ' . print_r( $value, true ) );
    // ... 既存のコード
}
```

### 問題4: 配列データで trim() エラー

**状況**: `trim(): Argument #1 ($string) must be of type string, array given`

**原因**: WordPressコアがリビジョン処理で配列データに`trim()`を実行

**解決策**: 本実装では配列データをJSON文字列化することで回避済み

## パフォーマンス考慮事項

### メモリ使用量

- 統合JSON文字列は通常数KB〜数十KB程度
- 大量のセリフや選択肢がある場合でも100KB以下
- メモリへの影響は最小限

### データベースクエリ

- 投稿保存時: 追加クエリ1回（統合メタの更新）
- リビジョン作成時: 追加クエリ1回（リビジョンへのコピー）
- リビジョン復元時: 追加クエリ18回（各フィールドの復元）

### 最適化のヒント

1. **リビジョン数の制限**
   ```php
   // wp-config.phpで設定
   define('WP_POST_REVISIONS', 10); // 最新10件のみ保存
   ```

2. **古いリビジョンの定期削除**
   ```php
   // 6ヶ月以上前のリビジョンを削除
   wp_delete_old_revisions( strtotime('-6 months') );
   ```

## 今後の拡張可能性

### 1. 差分表示の改善

現在は変更されたフィールドとその値を表示していますが、以下の改善が可能：

- フィールドごとの詳細な差分表示（配列要素の追加・削除・変更）
- ビジュアル差分表示（画像の変更前後を並べて表示）

### 2. 自動バックアップ

重要な変更時に自動的にバックアップを作成：

- ゲームタイトル変更時
- 大量のセリフや選択肢を削除した時

### 3. リビジョンのエクスポート/インポート

リビジョンデータをJSON形式でエクスポート/インポート可能に：

- バックアップ・復元の簡便化
- 別環境への移行の容易化

### 4. カスタムフィールド選択機能

ユーザーがリビジョン管理対象のフィールドを選択可能に：

- 設定画面でフィールドのON/OFF切り替え
- プロジェクトごとのカスタマイズ

## 参考資料

- [WordPress リビジョン API](https://developer.wordpress.org/apis/revisions/)
- [WordPress カスタムメタデータ API](https://developer.wordpress.org/plugins/metadata/)
- [WordPress プラグインレビューガイドライン](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

## 変更履歴

### 1.2.0 (2024-10-08)
- novel_game投稿タイプのリビジョン機能を実装
- 統合カスタムフィールド方式を採用
- 全18個のカスタムフィールドをリビジョン管理対象に
- WordPressコアとの競合（trim()エラー）を回避
- リビジョン比較画面でのカスタムデータ表示に対応
