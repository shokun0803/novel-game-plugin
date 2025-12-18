# リリースプロセス

本ドキュメントでは、Novel Game Plugin のリリース手順について説明します。

## 目次

- [概要](#概要)
- [自動ビルド手順](#自動ビルド手順)
- [手動ビルド手順](#手動ビルド手順)
- [検証手順](#検証手順)
- [トラブルシューティング](#トラブルシューティング)

## 概要

Novel Game Plugin では、Git タグをプッシュすることで自動的にリリース用 ZIP ファイルを生成し、GitHub Release に公開する仕組みを採用しています。

### リリース成果物

各リリースには以下のファイルが含まれます：

- `novel-game-plugin-vX.Y.Z.zip` - WordPress に直接インストール可能なプラグイン ZIP
- `novel-game-plugin-vX.Y.Z.zip.sha256` - SHA256 チェックサムファイル

### ZIP の構成

生成される ZIP ファイルは以下の構造を持ちます：

```
novel-game-plugin/
├── admin/
├── assets/
├── css/
├── docs/
├── includes/
├── js/
├── languages/
├── scripts/
├── templates/
├── novel-game-plugin.php
├── uninstall.php
├── README.md
├── CHANGELOG.md
└── .gitignore
```

**含まれないファイル**:
- `.git/`, `.github/` - バージョン管理ファイル
- `node_modules/` - 開発依存関係
- `build/`, `dist/` - ビルドアーティファクト
- `.DS_Store`, `Thumbs.db` - OS 固有ファイル
- `.vscode/`, `.idea/` - IDE 設定ファイル
- `*.test.js`, `*.spec.js` - テストファイル
- `test-*.html`, `validate-*.php` - 検証用ファイル

## 自動ビルド手順

### 前提条件

- `master` ブランチが最新の状態であること
- `CHANGELOG.md` が更新されていること
- `novel-game-plugin.php` のバージョン番号が更新されていること

### 手順

1. **ローカルでバージョン番号を更新**

   ```bash
   # novel-game-plugin.php のバージョンを更新
   # Version: X.Y.Z
   # define( 'NOVEL_GAME_PLUGIN_VERSION', 'X.Y.Z' );
   ```

2. **CHANGELOG.md を更新**

   ```bash
   # リリース内容を記載
   vim CHANGELOG.md
   ```

3. **変更をコミット**

   ```bash
   git add novel-game-plugin.php CHANGELOG.md
   git commit -m "Bump version to X.Y.Z"
   git push origin master
   ```

4. **タグを作成してプッシュ**

   ```bash
   # タグの作成（例: v1.3.0）
   git tag -a v1.3.0 -m "Release version 1.3.0"
   
   # タグをプッシュ
   git push origin v1.3.0
   ```

5. **GitHub Actions の実行確認**

   - GitHub リポジトリの「Actions」タブで `Release Build` ワークフローの実行状態を確認
   - ワークフローが成功すると、自動的に GitHub Release が作成されます

6. **Release の確認**

   - GitHub リポジトリの「Releases」ページで新しいリリースを確認
   - ZIP ファイルと SHA256 チェックサムがアセットとして添付されていることを確認

### 手動トリガー

タグをプッシュせずに手動でリリースビルドを実行することもできます：

1. GitHub リポジトリの「Actions」タブを開く
2. 「Release Build」ワークフローを選択
3. 「Run workflow」ボタンをクリック
4. タグ名（例: `v1.3.0`）を入力
5. 「Run workflow」を実行

## 手動ビルド手順

ローカル環境でリリース ZIP を生成する場合：

### 前提条件

- `rsync` がインストールされていること
- `zip` コマンドが利用可能であること

### 手順

1. **リポジトリのルートディレクトリに移動**

   ```bash
   cd /path/to/novel-game-plugin
   ```

2. **ビルドスクリプトを実行**

   ```bash
   # バージョン指定あり
   bash scripts/build-release.sh v1.3.0
   
   # バージョン指定なし（novel-game-plugin.php から自動取得）
   bash scripts/build-release.sh
   ```

3. **生成されたファイルを確認**

   ```bash
   ls -lh build/
   # novel-game-plugin-v1.3.0.zip
   # novel-game-plugin-v1.3.0.zip.sha256
   ```

4. **チェックサムを確認**

   ```bash
   cat build/novel-game-plugin-v1.3.0.zip.sha256
   ```

## 検証手順

### 基本動作テスト

リリースされた ZIP ファイルが正常にインストール・動作することを確認します。

#### 1. ファイル整合性の検証

```bash
# SHA256 チェックサムの検証
cd build/
sha256sum -c novel-game-plugin-v1.3.0.zip.sha256
# 出力: novel-game-plugin-v1.3.0.zip: OK
```

#### 2. ZIP 構造の確認

```bash
# ZIP の内容を確認
unzip -l novel-game-plugin-v1.3.0.zip | head -30

# トップレベルが novel-game-plugin/ であることを確認
unzip -l novel-game-plugin-v1.3.0.zip | grep "novel-game-plugin/$"
```

#### 3. WordPress でのインストールテスト

1. **テスト環境の準備**
   - ローカルまたはステージング環境の WordPress を用意
   - PHP 7.0+ と WordPress 4.7+ が動作していることを確認

2. **プラグインのインストール**
   - WordPress 管理画面にログイン
   - 「プラグイン」→「新規追加」→「プラグインのアップロード」
   - 生成した ZIP ファイルを選択
   - 「今すぐインストール」をクリック

3. **有効化の確認**
   - インストール完了後、「プラグインを有効化」をクリック
   - エラーが発生しないことを確認

4. **基本機能の動作確認**
   - 管理画面に「ノベルゲーム管理」メニューが表示されることを確認
   - ダッシュボードが正常に表示されることを確認
   - サンプルゲームが自動インストールされることを確認（初回有効化時）
   - 新規ゲーム作成が正常に動作することを確認

5. **フロントエンドの確認**
   - 作成したゲームを公開
   - フロントエンドで正常に表示・動作することを確認
   - レスポンシブデザインが正しく適用されることを確認

### 境界ケーステスト

#### 不要ファイルの混入チェック

```bash
# ZIP 内に含まれてはいけないファイルがないか確認
unzip -l build/novel-game-plugin-v1.3.0.zip | grep -E '(\.git/|\.github/|node_modules/|\.DS_Store|\.vscode/|\.idea/|\.test\.js|\.spec\.js)'

# 何も出力されなければ OK
```

#### プラグインメタ情報の確認

```bash
# ZIP を展開
unzip -q build/novel-game-plugin-v1.3.0.zip -d /tmp/test-plugin/

# メインプラグインファイルの確認
head -20 /tmp/test-plugin/novel-game-plugin/novel-game-plugin.php

# バージョン番号が正しいことを確認
grep "Version:" /tmp/test-plugin/novel-game-plugin/novel-game-plugin.php
grep "NOVEL_GAME_PLUGIN_VERSION" /tmp/test-plugin/novel-game-plugin/novel-game-plugin.php

# クリーンアップ
rm -rf /tmp/test-plugin/
```

### セキュリティチェック

#### ファイルパーミッションの確認

```bash
# ZIP 内のファイルパーミッションを確認
unzip -l build/novel-game-plugin-v1.3.0.zip | grep -E '(\.sh|\.php)$'

# 実行権限が不要なファイルに付与されていないことを確認
```

#### 機密情報の漏洩チェック

```bash
# ZIP を展開して内容を検査
unzip -q build/novel-game-plugin-v1.3.0.zip -d /tmp/security-check/

# 機密情報を含む可能性のあるパターンを検索
grep -r -i -E '(password|secret|api_key|token|credential)' /tmp/security-check/novel-game-plugin/ \
  --exclude-dir=.git \
  | grep -v -E '(\.md:|sanitize|esc_|_nonce)'

# クリーンアップ
rm -rf /tmp/security-check/
```

## トラブルシューティング

### ビルドが失敗する場合

#### rsync が見つからない

**エラー**: `rsync: command not found`

**解決方法**:
```bash
# Ubuntu/Debian
sudo apt-get install rsync

# macOS
brew install rsync

# Windows (WSL)
sudo apt-get install rsync
```

#### zip コマンドが見つからない

**エラー**: `zip: command not found`

**解決方法**:
```bash
# Ubuntu/Debian
sudo apt-get install zip

# macOS
# 通常は標準でインストールされています

# Windows (WSL)
sudo apt-get install zip
```

#### build/ ディレクトリへの書き込み権限がない

**エラー**: `Permission denied`

**解決方法**:
```bash
# ディレクトリの権限を確認
ls -ld build/

# 必要に応じて権限を変更
chmod 755 build/
```

### GitHub Actions のワークフローが失敗する場合

#### タグ形式が正しくない

**原因**: タグ名が `v*.*.*` 形式に一致しない

**解決方法**:
```bash
# 正しいタグ形式で再作成
git tag -d incorrect-tag  # ローカルから削除
git push origin :incorrect-tag  # リモートから削除
git tag -a v1.3.0 -m "Release version 1.3.0"
git push origin v1.3.0
```

#### Release の作成権限がない

**原因**: `GITHUB_TOKEN` の権限不足

**解決方法**:
- ワークフローファイルの `permissions` セクションを確認
- `contents: write` が設定されていることを確認

#### アーティファクトのアップロードが失敗する

**原因**: ビルド成果物が見つからない

**確認方法**:
1. GitHub Actions のログで「リリース用 ZIP のビルド」ステップを確認
2. `build/` ディレクトリにファイルが生成されているか確認
3. ファイル名が期待通りか確認

### WordPress でのインストールが失敗する場合

#### 「このパッケージをインストールできませんでした」エラー

**原因**: ZIP の構造が正しくない

**確認方法**:
```bash
# ZIP のトップレベル構造を確認
unzip -l build/novel-game-plugin-v1.3.0.zip | head -10

# 最初のエントリが "novel-game-plugin/" であることを確認
```

**解決方法**:
- ビルドスクリプトの rsync コマンドを確認
- ZIP 作成時のディレクトリ構造を確認

#### 「プラグインに有効なヘッダー情報がありません」エラー

**原因**: メインプラグインファイルのヘッダー情報が壊れている

**確認方法**:
```bash
unzip -p build/novel-game-plugin-v1.3.0.zip novel-game-plugin/novel-game-plugin.php | head -20
```

**解決方法**:
- `novel-game-plugin.php` のヘッダー情報を確認
- 必須フィールド（Plugin Name, Version など）が揃っているか確認

## テスト用タグを用いた検証手順

本番リリース前に、テスト用タグを使って動作確認を行うことを推奨します。

### 手順

1. **テストブランチの作成**

   ```bash
   git checkout -b test-release-build
   ```

2. **テスト用タグの作成**

   ```bash
   # テスト用タグは "test-" プレフィックスを付ける
   git tag -a test-v1.3.0-rc1 -m "Test release candidate 1.3.0"
   ```

3. **テストタグをプッシュ**

   ```bash
   # 注: test- で始まるタグは自動ビルドのトリガーにならないため、
   # 手動トリガーを使用
   ```

4. **GitHub Actions で手動実行**

   - GitHub の Actions タブから「Release Build」を選択
   - 「Run workflow」で `test-v1.3.0-rc1` を入力して実行

5. **生成された Release を確認**

   - Pre-release として Draft 状態で作成されることを確認
   - ZIP ファイルをダウンロードして検証手順を実施

6. **問題がなければテストタグを削除**

   ```bash
   git tag -d test-v1.3.0-rc1
   git push origin :test-v1.3.0-rc1
   ```

7. **本番タグを作成**

   ```bash
   git checkout master
   git tag -a v1.3.0 -m "Release version 1.3.0"
   git push origin v1.3.0
   ```

## 参考情報

- [WordPress Plugin Handbook - Plugin Headers](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/)
- [GitHub Actions - Using workflows](https://docs.github.com/en/actions/using-workflows)
- [CHANGELOG.md](../CHANGELOG.md) - 変更履歴
- [README.md](../README.md) - プラグイン概要と使用方法

## 関連ドキュメント

- `.github/workflows/release-build.yml` - 自動ビルドワークフロー
- `scripts/build-release.sh` - ビルドスクリプト
- `.github/copilot-instructions.md` - リポジトリ運用規約
