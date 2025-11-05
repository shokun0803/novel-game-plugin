# サンプルゲーム機能のテスト手順

このドキュメントでは、プラグイン有効化時に自動インストールされるサンプルゲーム機能のテスト手順を説明します。

## テスト環境

- WordPress 4.7以上
- PHP 7.0以上
- MySQL 5.6以上

## テストシナリオ

### 1. 初回有効化時のサンプルゲーム作成

#### 手順
1. WordPressにプラグインをインストール
2. 管理画面の「プラグイン」→「インストール済みプラグイン」へ移動
3. 「Novel Game Plugin」を有効化
4. 「ノベルゲーム管理」→「🎮 マイゲーム」へ移動

#### 期待される結果
- ✅ 「Sample Novel Game」という名前のゲームが存在する
- ✅ ゲームのサムネイル画像（SVGプレースホルダー）が表示される
- ✅ シーン数が「3個」と表示される

### 2. サンプルゲームのシーン確認

#### 手順
1. 「マイゲーム」から「Sample Novel Game」を選択
2. 「📝 シーン一覧」タブを確認

#### 期待される結果
- ✅ 以下の3つのシーンが存在する：
  - Sample Novel Game - Opening
  - Sample Novel Game - About Story
  - Sample Novel Game - About Choices
- ✅ 各シーンに編集リンクとプレビューリンクが表示される

### 3. シーン1（オープニング）の内容確認

#### 手順
1. 「Sample Novel Game - Opening」の「編集」リンクをクリック
2. メタボックスの内容を確認

#### 期待される結果
- ✅ 背景画像：SVGプレースホルダーが設定されている
- ✅ キャラクター：左側にAliceが配置されている
- ✅ セリフ：4行のセリフが登録されている
- ✅ 選択肢：2つの選択肢が設定されている
  - 「I want to hear more about the story」
  - 「I want to learn about choices」
- ✅ エンディング設定：エンディングではない（チェックなし）

### 4. シーン2（ストーリー説明）の内容確認

#### 手順
1. 「Sample Novel Game - About Story」の「編集」リンクをクリック
2. メタボックスの内容を確認

#### 期待される結果
- ✅ 背景画像：SVGプレースホルダーが設定されている
- ✅ キャラクター：左側にAlice、中央にBobが配置されている
- ✅ セリフ：5行のセリフが登録されている
- ✅ 選択肢：選択肢なし
- ✅ エンディング設定：エンディングである（チェックあり）
- ✅ エンディングテキスト：「Story Path - End」

### 5. シーン3（選択肢説明）の内容確認

#### 手順
1. 「Sample Novel Game - About Choices」の「編集」リンクをクリック
2. メタボックスの内容を確認

#### 期待される結果
- ✅ 背景画像：SVGプレースホルダーが設定されている
- ✅ キャラクター：中央にAliceが配置されている
- ✅ セリフ：5行のセリフが登録されている
- ✅ 選択肢：選択肢なし
- ✅ エンディング設定：エンディングである（チェックあり）
- ✅ エンディングテキスト：「Choice Path - End」

### 6. フロントエンドでのゲームプレイ

#### 手順
1. シーン一覧から「Sample Novel Game - Opening」のプレビューをクリック
2. 「Start Game」ボタンをクリック
3. セリフを進める
4. 選択肢「I want to hear more about the story」を選択
5. エンディングまでプレイ

#### 期待される結果
- ✅ ゲームが正常に起動する
- ✅ セリフが順番に表示される
- ✅ キャラクターが正しい位置に表示される
- ✅ 選択肢が表示される
- ✅ 選択後、シーン2に遷移する
- ✅ エンディング画面が表示される

### 7. もう一方の分岐の確認

#### 手順
1. ブラウザを更新してゲームをリセット
2. 今度は「I want to learn about choices」を選択
3. エンディングまでプレイ

#### 期待される結果
- ✅ シーン3に遷移する
- ✅ 異なるエンディングテキストが表示される

### 8. 重複インストール防止の確認

#### 手順
1. WordPress管理画面の「プラグイン」へ移動
2. 「Novel Game Plugin」を無効化
3. 再度有効化
4. 「マイゲーム」を確認

#### 期待される結果
- ✅ サンプルゲームが重複していない
- ✅ 「Sample Novel Game」が1つだけ存在する

### 9. サンプルゲームの編集可能性確認

#### 手順
1. サンプルゲームのシーン1を編集
2. セリフを変更して保存
3. フロントエンドでプレイして確認

#### 期待される結果
- ✅ 通常のゲームと同様に編集できる
- ✅ 変更内容が反映される

### 10. サンプルゲームの削除可能性確認

#### 手順
1. 「ゲーム設定」タブへ移動
2. 「Delete Game」ボタンをクリック（存在する場合）
3. またはシーンを個別に削除

#### 期待される結果
- ✅ サンプルゲームを削除できる
- ✅ エラーが発生しない

## データベース確認（開発者向け）

### インストールフラグの確認

```sql
SELECT * FROM wp_options WHERE option_name = 'noveltool_sample_game_installed';
```

期待される結果：`option_value` が `1` または `true`

### ゲームデータの確認

```sql
SELECT * FROM wp_options WHERE option_name = 'noveltool_games';
```

期待される結果：JSON形式で「Sample Novel Game」のデータが含まれる

### 投稿データの確認

```sql
SELECT ID, post_title, post_type 
FROM wp_posts 
WHERE post_type = 'novel_game' 
  AND post_title LIKE '%Sample Novel Game%';
```

期待される結果：3件の投稿が存在する

## トラブルシューティング

### サンプルゲームがインストールされない場合

1. **PHPエラーログを確認**
   ```
   tail -f /var/log/php_errors.log
   ```

2. **WordPress デバッグモードを有効化**
   `wp-config.php` に以下を追加：
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

3. **手動でインストール関数を実行**
   WordPress管理画面のテーマエディタまたはPHPファイルで：
   ```php
   noveltool_install_sample_game();
   ```

### サンプルゲームが重複する場合

1. **インストールフラグを確認**
   ```sql
   SELECT * FROM wp_options WHERE option_name = 'noveltool_sample_game_installed';
   ```

2. **フラグをリセット**
   ```sql
   DELETE FROM wp_options WHERE option_name = 'noveltool_sample_game_installed';
   ```

## テスト結果の記録

テスト実施日：___________

| テストケース | 結果 | 備考 |
|------------|------|------|
| 1. 初回有効化 | ☐ Pass ☐ Fail | |
| 2. シーン確認 | ☐ Pass ☐ Fail | |
| 3. シーン1内容 | ☐ Pass ☐ Fail | |
| 4. シーン2内容 | ☐ Pass ☐ Fail | |
| 5. シーン3内容 | ☐ Pass ☐ Fail | |
| 6. ゲームプレイ | ☐ Pass ☐ Fail | |
| 7. 分岐確認 | ☐ Pass ☐ Fail | |
| 8. 重複防止 | ☐ Pass ☐ Fail | |
| 9. 編集可能性 | ☐ Pass ☐ Fail | |
| 10. 削除可能性 | ☐ Pass ☐ Fail | |

総合評価：☐ 合格 ☐ 不合格

テスト担当者：___________
