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
- `novel-game-plugin-vX.Y.Z.zip.sha256` - プラグイン ZIP の SHA256 チェックサムファイル
- `novel-game-plugin-sample-images-vX.Y.Z-part01.zip` - サンプル画像パッケージ（パート1）
- `novel-game-plugin-sample-images-vX.Y.Z-part02.zip` - サンプル画像パッケージ（パート2）
- `novel-game-plugin-sample-images-vX.Y.Z-part0N.zip` - サンプル画像パッケージ（パートN）
- 各サンプル画像 ZIP に対応する `.sha256` チェックサムファイル
- `novel-game-plugin-sample-images-vX.Y.Z-all.zip` - サンプル画像まとめパッケージ（オプション）
- サンプル画像まとめ ZIP の `.sha256` チェックサムファイル

#### サンプル画像パッケージについて

サンプル画像は `assets/sample-images/` ディレクトリの内容を小さな複数の ZIP に分割してパッケージ化し、GitHub Release のアセットとして提供されます。これにより、低容量のホスティング環境でも容易にダウンロードできるようになります。

**分割方式**:
- 各パートのサイズは約 10MB を目標に分割（50MB 以下を保証）
- 大きな画像ファイルから順に割り当てられます
- パート番号は `part01`, `part02`, ... の形式で連番

**ダウンロード URL 形式**:
```
# 分割パート
https://github.com/shokun0803/novel-game-plugin/releases/download/{tag}/novel-game-plugin-sample-images-{tag}-part01.zip
https://github.com/shokun0803/novel-game-plugin/releases/download/{tag}/novel-game-plugin-sample-images-{tag}-part02.zip
...

# まとめ ZIP（手動ダウンロード用）
https://github.com/shokun0803/novel-game-plugin/releases/download/{tag}/novel-game-plugin-sample-images-{tag}-all.zip
```

**チェックサム検証例**:
```bash
# 分割パート ZIP をダウンロード
wget https://github.com/shokun0803/novel-game-plugin/releases/download/v1.3.0/novel-game-plugin-sample-images-v1.3.0-part01.zip
wget https://github.com/shokun0803/novel-game-plugin/releases/download/v1.3.0/novel-game-plugin-sample-images-v1.3.0-part01.zip.sha256

# チェックサム検証
sha256sum -c novel-game-plugin-sample-images-v1.3.0-part01.zip.sha256
```

**プラグイン側での利用**:
プラグインは初回インストール時（または管理画面からの手動操作時）に、この Release アセットを参照してサンプル画像を自動ダウンロードします。分割された ZIP は順次ダウンロード・展開され、完全なサンプル画像セットが構築されます。これにより、プラグイン本体の ZIP サイズを削減し、必要な場合のみサンプル画像を取得できます。詳細は Issue #213「サンプル画像の初回ダウンロード実装」および PR #220「管理画面から分割アセットをダウンロード」を参照してください。

**手動ダウンロードの場合**:
まとめ ZIP（`sample-images-{version}-all.zip`）を使用することで、すべてのサンプル画像を一度にダウンロードすることも可能です。

**ファイルサイズの注意**:
- GitHub Release のアセットは 2GB まで添付可能です
- 各分割パートは約 10MB を目標にしており、一般的なホスティング環境の制限（50MB 以下）を満たします

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
- `assets/sample-images/` - サンプル画像は別パッケージとして配布

## 自動ビルド手順

### 前提条件

- `master` ブランチが最新の状態であること
- `CHANGELOG.md` が更新されていること
- `novel-game-plugin.php` のバージョン番号が更新されていること

### 実行環境

本リリースワークフローは **ubuntu-latest** ランナーで実行されることを前提としています。ワークフロー内で以下の必須ツールが自動的にインストールされます：

- `zip` - ZIP アーカイブ作成用
- `rsync` - ファイル同期・除外フィルタリング用

これらのツールがインストールされていない環境で手動ビルドを行う場合は、事前にインストールしてください（詳細は[手動ビルド手順](#手動ビルド手順)を参照）。

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
- `sha256sum` または `shasum` が利用可能であること

### プラグイン本体のビルド

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

### サンプル画像のビルド

サンプル画像は3つのモードでビルドできます：

#### 1. 分割モード（デフォルト）

複数の小さな ZIP に分割して生成します（推奨）：

```bash
# 分割 ZIP のみ生成
bash scripts/build-sample-images.sh v1.3.0

# または明示的に --split を指定
bash scripts/build-sample-images.sh v1.3.0 --split
```

生成されるファイル:
```
build/novel-game-plugin-sample-images-v1.3.0-part01.zip
build/novel-game-plugin-sample-images-v1.3.0-part01.zip.sha256
build/novel-game-plugin-sample-images-v1.3.0-part02.zip
build/novel-game-plugin-sample-images-v1.3.0-part02.zip.sha256
...
```

#### 2. まとめ ZIP のみモード

単一の大きな ZIP のみを生成します：

```bash
bash scripts/build-sample-images.sh v1.3.0 --no-split
```

生成されるファイル:
```
build/novel-game-plugin-sample-images-v1.3.0-all.zip
build/novel-game-plugin-sample-images-v1.3.0-all.zip.sha256
```

#### 3. 両方モード

分割 ZIP とまとめ ZIP の両方を生成します（GitHub Release で使用）：

```bash
bash scripts/build-sample-images.sh v1.3.0 --all
```

生成されるファイル:
```
build/novel-game-plugin-sample-images-v1.3.0-part01.zip
build/novel-game-plugin-sample-images-v1.3.0-part01.zip.sha256
build/novel-game-plugin-sample-images-v1.3.0-part02.zip
build/novel-game-plugin-sample-images-v1.3.0-part02.zip.sha256
...
build/novel-game-plugin-sample-images-v1.3.0-all.zip
build/novel-game-plugin-sample-images-v1.3.0-all.zip.sha256
```

### ファイルサイズとパート分割について

- 各分割パートは約 10MB を目標にサイズ調整されます
- 大きな画像ファイルから優先的に各パートに割り当てられます
- パート数は画像の総サイズに応じて自動的に決定されます

### 生成されたファイルの確認

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
   - 以下のアセットが添付されていることを確認：
     - `novel-game-plugin-${TAG}.zip`
     - `novel-game-plugin-${TAG}.zip.sha256`
     - `novel-game-plugin-sample-images-${TAG}-part01.zip`
     - `novel-game-plugin-sample-images-${TAG}-part01.zip.sha256`
     - `novel-game-plugin-sample-images-${TAG}-part02.zip`
     - `novel-game-plugin-sample-images-${TAG}-part02.zip.sha256`
     - ... (その他のパート)
     - `novel-game-plugin-sample-images-${TAG}-all.zip`
     - `novel-game-plugin-sample-images-${TAG}-all.zip.sha256`
   - ZIP ファイルをダウンロードして検証手順を実施

6. **チェックサム検証**

   ```bash
   # プラグイン ZIP の検証
   sha256sum -c novel-game-plugin-test-v1.3.0-rc1.zip.sha256
   # → novel-game-plugin-test-v1.3.0-rc1.zip: OK

   # サンプル画像分割 ZIP の検証（各パート）
   sha256sum -c novel-game-plugin-sample-images-test-v1.3.0-rc1-part01.zip.sha256
   # → novel-game-plugin-sample-images-test-v1.3.0-rc1-part01.zip: OK
   sha256sum -c novel-game-plugin-sample-images-test-v1.3.0-rc1-part02.zip.sha256
   # → novel-game-plugin-sample-images-test-v1.3.0-rc1-part02.zip: OK
   
   # まとめ ZIP の検証
   sha256sum -c novel-game-plugin-sample-images-test-v1.3.0-rc1-all.zip.sha256
   # → novel-game-plugin-sample-images-test-v1.3.0-rc1-all.zip: OK
   ```

7. **分割アセットの整合性確認**

   ```bash
   # 各パートを展開して結合
   mkdir temp-extract
   cd temp-extract
   unzip ../novel-game-plugin-sample-images-test-v1.3.0-rc1-part01.zip
   unzip ../novel-game-plugin-sample-images-test-v1.3.0-rc1-part02.zip
   # ... 他のパートも同様に展開
   
   # ファイル数を確認
   find . -type f | wc -l
   
   # まとめ ZIP と比較
   cd ..
   mkdir temp-all
   unzip novel-game-plugin-sample-images-test-v1.3.0-rc1-all.zip -d temp-all/
   diff -r temp-extract/ temp-all/sample-images/
   # → 差分がないことを確認
   
   # クリーンアップ
   rm -rf temp-extract temp-all
   ```

8. **問題がなければテストタグを削除**

   ```bash
   git tag -d test-v1.3.0-rc1
   git push origin :test-v1.3.0-rc1
   ```

8. **本番タグを作成**

   ```bash
   git checkout master
   git tag -a v1.3.0 -m "Release version 1.3.0"
   git push origin v1.3.0
   ```

## 参考情報

**注**: 外部ドキュメントへのリンクは定期的に確認し、URL が変更されている場合は更新してください。

- [WordPress Plugin Handbook - Plugin Headers](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/) - プラグインヘッダーの要件
- [GitHub Actions - Using workflows](https://docs.github.com/en/actions/using-workflows) - ワークフローの使用方法
- [CHANGELOG.md](../CHANGELOG.md) - 変更履歴
- [README.md](../README.md) - プラグイン概要と使用方法

## 関連ドキュメント

- `.github/workflows/release-build.yml` - 自動ビルドワークフロー
- `scripts/build-release.sh` - ビルドスクリプト
- `.github/copilot-instructions.md` - リポジトリ運用規約
