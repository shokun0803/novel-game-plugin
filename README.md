# Novel Game Plugin

WordPressでノベルゲームを作成・公開できるプラグインです。

## 主な機能
- カスタム投稿タイプ「ノベルゲーム」
- 管理画面でシーン（背景・キャラ・セリフ・選択肢）を直感的に編集
- フロントエンドでノベルゲームとして表示・分岐
- メディアライブラリから画像選択
- 選択肢から新規シーン作成や既存シーン選択

## 使い方
1. プラグインを `wp-content/plugins/novel-game-plugin` に設置し有効化
2. 管理画面「ノベルゲーム」から新規作成
3. 背景画像・キャラクター画像・セリフ・選択肢を入力
4. 投稿を公開
5. サイト上でノベルゲームとして表示されます（ショートコード不要）

## 開発・バージョン管理
- Gitでバージョン管理
- `dev`ブランチで開発、安定後`master`へマージ推奨

## ディレクトリ構成
```
novel-game-plugin.php
admin/
  meta-boxes.php
css/
  style.css
includes/
  post-types.php
js/
  admin.js
  frontend.js
```

## ライセンス
MIT License

---
ご要望・不具合はGitHubのIssueまで。
