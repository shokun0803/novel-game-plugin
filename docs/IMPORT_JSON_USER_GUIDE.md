# JSON インポートガイド（ユーザー向け）

このドキュメントでは、Novel Game Plugin の JSON インポート機能について説明します。
管理画面 UI からエクスポートした JSON ファイルや、手動で作成した JSON ファイルをインポートしてゲームを登録できます。

## 目次

1. [概要](#概要)
2. [インポート手順](#インポート手順)
3. [JSON フォーマット仕様](#json-フォーマット仕様)
4. [最小構成サンプル](#最小構成サンプル)
5. [画像の扱い](#画像の扱い)
6. [制限事項と注意点](#制限事項と注意点)
7. [エラーと対処法](#エラーと対処法)
8. [トラブルシューティング](#トラブルシューティング)

---

## 概要

### エクスポート/インポートの仕組み

Novel Game Plugin では、ゲームデータを JSON 形式でエクスポート・インポートできます。

- **エクスポート**: 管理画面「マイゲーム」→ゲーム選択→「ゲーム設定」タブ→「エクスポート」ボタン
- **インポート**: 管理画面「マイゲーム」→「インポート」タブ→ファイル選択→「インポート」ボタン

エクスポートされた JSON は、そのまま別の WordPress サイトにインポートできます。
また、仕様に沿って手動で JSON を作成し、インポートすることも可能です。

### 用途

- ゲームデータのバックアップと復元
- 別サイトへのゲーム移行
- 手動作成したゲームデータの一括インポート
- 開発環境から本番環境へのデータ移行

---

## インポート手順

### 1. インポート画面を開く

1. WordPress 管理画面にログイン
2. 「ノベルゲーム管理」→「マイゲーム」を選択
3. 画面上部の「インポート」タブをクリック

### 2. ファイルを選択

1. 「ファイルを選択」ボタンをクリック
2. インポートする JSON ファイルを選択

### 3. オプション設定

- **画像をダウンロード**: 有効にすると、JSON 内の画像 URL からメディアライブラリに画像をダウンロードします

### 4. インポート実行

1. 「インポート」ボタンをクリック
2. 成功メッセージが表示されたら完了

### 5. 確認

インポートされたゲームは「マイゲーム」一覧に表示されます。

---

## JSON フォーマット仕様

### トップレベル構造

```json
{
  "version": "1.0",
  "plugin_version": "1.3.0",
  "export_date": "2024-01-01 00:00:00",
  "game": { ... },
  "flags": [ ... ],
  "scenes": [ ... ]
}
```

| フィールド | 型 | 必須 | 説明 |
|------------|------|------|------|
| `version` | string | 任意 | インポートフォーマットのバージョン（現在 "1.0"） |
| `plugin_version` | string | 任意 | エクスポート元プラグインのバージョン |
| `export_date` | string | 任意 | エクスポート日時（MySQL 形式） |
| `game` | object | **必須** | ゲーム基本情報 |
| `flags` | array | 任意 | フラグマスタ（ゲーム内で使用するフラグの定義） |
| `scenes` | array | **必須** | シーンデータの配列 |

### game オブジェクト

```json
{
  "game": {
    "title": "サンプルゲーム",
    "description": "これはサンプルゲームです。",
    "title_image": "https://example.com/images/title.jpg",
    "game_over_text": "Game Over"
  }
}
```

| フィールド | 型 | 必須 | 説明 | 制限 |
|------------|------|------|------|------|
| `title` | string | **必須** | ゲームタイトル | 最大200文字 |
| `description` | string | 任意 | ゲーム概要 | 最大5000文字 |
| `title_image` | string | 任意 | タイトル画像 URL | HTTP/HTTPS URL |
| `game_over_text` | string | 任意 | Game Over 表示テキスト | デフォルト: "Game Over" |

### flags 配列

ゲーム内で使用するフラグを定義します。

```json
{
  "flags": [
    {
      "id": 1,
      "name": "アイテム取得",
      "description": "鍵を入手したフラグ"
    },
    {
      "id": 2,
      "name": "ボス撃破",
      "description": "ボスを倒したフラグ"
    }
  ]
}
```

| フィールド | 型 | 必須 | 説明 |
|------------|------|------|------|
| `id` | number | 任意 | フラグ ID（自動採番される） |
| `name` | string | **必須** | フラグ名 |
| `description` | string | 任意 | フラグの説明 |

### scenes 配列

シーン（場面）データの配列です。各シーンは1つの場面を表します。

```json
{
  "scenes": [
    {
      "title": "シーン1: オープニング",
      "background_image": "https://example.com/images/bg1.jpg",
      "character_left": "",
      "character_center": "https://example.com/images/char1.png",
      "character_right": "",
      "character_left_name": "",
      "character_center_name": "主人公",
      "character_right_name": "",
      "dialogue_texts": ["こんにちは。", "私は主人公です。"],
      "dialogue_speakers": ["center", "center"],
      "dialogue_backgrounds": ["", ""],
      "choices": [
        {
          "text": "次へ進む",
          "next": 123
        }
      ],
      "is_ending": false,
      "ending_text": ""
    }
  ]
}
```

#### シーンフィールド一覧

| フィールド | 型 | 必須 | 説明 |
|------------|------|------|------|
| `title` | string | **必須** | シーンタイトル |
| `original_post_id` | number | 任意 | 元の投稿 ID（エクスポート時に自動付与、選択肢リンク用） |
| `background_image` | string | 任意 | 背景画像 URL |
| `character_left` | string | 任意 | 左側キャラクター画像 URL |
| `character_center` | string | 任意 | 中央キャラクター画像 URL |
| `character_right` | string | 任意 | 右側キャラクター画像 URL |
| `character_left_name` | string | 任意 | 左側キャラクター名 |
| `character_center_name` | string | 任意 | 中央キャラクター名 |
| `character_right_name` | string | 任意 | 右側キャラクター名 |
| `dialogue_texts` | array | 任意 | セリフテキストの配列 |
| `dialogue_speakers` | array | 任意 | 話者の配列（"left", "center", "right", "" のいずれか） |
| `dialogue_backgrounds` | array | 任意 | セリフごとの背景画像 URL 配列 |
| `dialogue_flag_conditions` | object/array | 任意 | セリフのフラグ条件 |
| `choices` | array | 任意 | 選択肢の配列 |
| `is_ending` | boolean | 任意 | エンディングシーンかどうか |
| `ending_text` | string | 任意 | エンディングテキスト |
| `scene_arrival_flags` | array | 任意 | シーン到達時に設定するフラグ |

#### choices 配列（選択肢）

```json
{
  "choices": [
    {
      "text": "はい",
      "next": 123,
      "flagConditions": [
        { "name": "アイテム取得", "state": true }
      ],
      "flagConditionLogic": "AND",
      "setFlags": [
        { "name": "選択フラグ", "state": true }
      ]
    }
  ]
}
```

| フィールド | 型 | 必須 | 説明 |
|------------|------|------|------|
| `text` | string | **必須** | 選択肢のテキスト |
| `next` | number | **必須** | 遷移先シーンの投稿 ID（`original_post_id` を参照） |
| `flagConditions` | array | 任意 | 選択肢表示条件となるフラグ |
| `flagConditionLogic` | string | 任意 | フラグ条件の論理演算（"AND" または "OR"） |
| `setFlags` | array | 任意 | 選択時に設定するフラグ |

---

## 最小構成サンプル

最小構成の JSON サンプルは [`docs/sample-import.json`](./sample-import.json) を参照してください。

### 最小要件

インポートに必要な最小要件は以下の通りです：

```json
{
  "game": {
    "title": "マイゲーム"
  },
  "scenes": [
    {
      "title": "シーン1"
    }
  ]
}
```

このように、`game.title` と `scenes[].title` のみが必須フィールドです。

---

## 画像の扱い

### 画像 URL の指定

JSON 内で画像を指定する場合、以下の形式の URL を使用できます：

- **絶対 URL**: `https://example.com/images/background.jpg`
- **WordPress サイト内 URL**: `https://yoursite.com/wp-content/uploads/2024/01/image.jpg`

### 画像ダウンロードオプション

インポート時に「画像をダウンロード」オプションを有効にすると：

1. JSON 内の画像 URL から画像をダウンロード
2. WordPress のメディアライブラリに保存
3. シーンデータ内の URL をローカル URL に置き換え

**注意事項：**

- 外部サイトの画像の場合、ダウンロードに時間がかかることがあります
- ダウンロードに失敗した場合、元の URL がそのまま使用されます
- HTTP/HTTPS URL のみがサポートされます
- 画像として有効なファイル（JPEG, PNG, GIF, WebP）のみダウンロードされます

### 推奨する画像の扱い

1. **同一サイト間での移行**: 画像ダウンロードを有効にする
2. **同一サイト内でのインポート**: 画像ダウンロードは不要（URL がそのまま使用可能）
3. **外部画像を参照**: ダウンロードなしでも可（ただし外部サイトの可用性に依存）

---

## 制限事項と注意点

### ファイルサイズ制限

- **最大ファイルサイズ**: 10MB

10MB を超える JSON ファイルはインポートできません。大規模なゲームデータの場合は、複数のゲームに分割することを検討してください。

### サポート形式

- **ファイル形式**: JSON (.json)
- **エンコーディング**: UTF-8

### 重複タイトルの扱い

同じタイトルのゲームが既に存在する場合、インポート時に自動的にリネームされます。

- 例: 「マイゲーム」→「マイゲーム-2」→「マイゲーム-3」

### 選択肢リンクの再マッピング

エクスポートデータに含まれる `original_post_id` を使用して、選択肢の遷移先が自動的に新しいシーン ID にマッピングされます。

手動で JSON を作成する場合：
1. 各シーンに一意の `original_post_id` を設定
2. 選択肢の `next` にリンク先シーンの `original_post_id` を指定

### 既存データへの影響

- インポートは**新規ゲーム作成**として動作します
- 既存ゲームへの上書きや更新は行われません
- 同じ JSON を複数回インポートすると、複数のゲームが作成されます（タイトルは自動リネーム）

---

## エラーと対処法

### よくあるエラー

| エラーメッセージ | 原因 | 対処法 |
|------------------|------|--------|
| Invalid import data format | JSON 構造が不正 | `game` と `scenes` キーが存在することを確認 |
| Game title is required | ゲームタイトルが未設定 | `game.title` を設定 |
| Scene at index X must have a valid title | シーンタイトルが未設定 | 各シーンに `title` を設定 |
| File size exceeds 10MB limit | ファイルサイズ超過 | ファイルを10MB以下に分割 |
| Invalid JSON encoding | JSON 形式が不正 | JSON 構文を確認（カンマ、括弧など） |
| Only JSON files are allowed | ファイル形式が不正 | .json 拡張子のファイルを使用 |

### JSON バリデーション

インポート前に JSON の構文を確認することをお勧めします：

1. [JSONLint](https://jsonlint.com/) などのオンラインツールを使用
2. テキストエディタの JSON 検証機能を使用

---

## トラブルシューティング

### Q: インポート後、シーン間の遷移がおかしい

**A**: 選択肢の `next` 値が正しく設定されているか確認してください。

- エクスポートした JSON をインポートする場合は自動でリマップされます
- 手動作成の場合は `original_post_id` と `next` の対応を確認してください

### Q: 画像が表示されない

**A**: 以下を確認してください：

1. 画像 URL が有効な HTTP/HTTPS URL か
2. 画像ファイルが存在するか
3. 画像ダウンロードオプションを使用した場合、ダウンロードが成功しているか

### Q: 日本語が文字化けする

**A**: JSON ファイルが UTF-8 エンコーディングで保存されていることを確認してください。

### Q: インポートが途中で止まる

**A**: ファイルサイズが大きい場合、サーバーのタイムアウト設定に達している可能性があります。ゲームを分割してインポートすることを検討してください。

---

## 関連ドキュメント

- [README.md](../README.md) - プラグイン全体の説明
- [sample-import.json](./sample-import.json) - 最小構成サンプル

---

*このドキュメントは Novel Game Plugin v1.3.0 以降に対応しています。*
