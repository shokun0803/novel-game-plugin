#!/bin/bash
#
# リリース用 ZIP ビルドスクリプト
# WordPress に直接インストール可能な配布用 ZIP を生成します
#
# Usage: ./scripts/build-release.sh [version]
# Example: ./scripts/build-release.sh v1.3.0
#
# Output:
#   - novel-game-plugin-{version}.zip
#   - novel-game-plugin-{version}.zip.sha256
#

set -euo pipefail

# 必須コマンドチェック
for cmd in rsync zip; do
    command -v "$cmd" >/dev/null 2>&1 || {
        echo "Error: $cmd is required but not installed." >&2
        echo "Please install $cmd on the system." >&2
        exit 1
    }
done

# SHA256 生成コマンドの確認
if command -v sha256sum >/dev/null 2>&1; then
    SHA256_CMD="sha256sum"
elif command -v shasum >/dev/null 2>&1; then
    SHA256_CMD="shasum -a 256"
else
    echo "Error: Neither sha256sum nor shasum is available." >&2
    echo "Please install coreutils or perl on the system." >&2
    exit 1
fi

# バージョン引数の取得（オプション）
VERSION="${1:-}"
if [ -z "$VERSION" ]; then
    # バージョンが指定されていない場合は plugin ファイルから取得（POSIX 互換）
    if [ -f "novel-game-plugin.php" ]; then
        VERSION=$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' novel-game-plugin.php | sed -E 's/.*Version:[[:space:]]*([0-9.]+).*/\1/' || echo "unknown")
        if [ -z "$VERSION" ] || [ "$VERSION" = "unknown" ]; then
            # フォールバック: コメントなしの Version: 行を探す
            VERSION=$(grep -m1 'Version:' novel-game-plugin.php | sed 's/.*Version:[[:space:]]*\([0-9.]*\).*/\1/' || echo "unknown")
        fi
    else
        VERSION="unknown"
    fi
fi

echo "=========================================="
echo "Building release ZIP for version: $VERSION"
echo "=========================================="
echo ""

# 作業ディレクトリの準備
BUILD_DIR="build"
TEMP_DIR="$BUILD_DIR/temp"
PLUGIN_DIR="$TEMP_DIR/novel-game-plugin"
OUTPUT_DIR="$BUILD_DIR"
ZIP_NAME="novel-game-plugin-${VERSION}.zip"
ZIP_PATH="$OUTPUT_DIR/$ZIP_NAME"

# クリーンアップ
echo "Cleaning up previous build artifacts..."
rm -rf "$BUILD_DIR"
mkdir -p "$OUTPUT_DIR"
mkdir -p "$TEMP_DIR"

# プラグインディレクトリの作成
echo "Creating plugin directory structure..."
mkdir -p "$PLUGIN_DIR"

# ファイルのコピー（rsync を使用して除外パターンを適用）
echo "Copying plugin files..."
# 注: *.min.js と *.min.css はビルド成果物として除外しています。
# 本プラグインは圧縮されていないソースファイルを配布し、
# WordPress 環境で必要に応じて圧縮処理が行われることを想定しています。
rsync -av --progress \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='node_modules/' \
    --exclude='build/' \
    --exclude='dist/' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='.vscode/' \
    --exclude='.idea/' \
    --exclude='*.swp' \
    --exclude='*.swo' \
    --exclude='*.tmp' \
    --exclude='*.temp' \
    --exclude='test-*.html' \
    --exclude='*.test.js' \
    --exclude='*.spec.js' \
    --exclude='validate-*.php' \
    --exclude='*.min.js' \
    --exclude='*.min.css' \
    --exclude='npm-debug.log*' \
    --exclude='yarn-debug.log*' \
    --exclude='yarn-error.log*' \
    --exclude='messages.mo*.bak' \
    . "$PLUGIN_DIR/"

echo ""
echo "Files copied successfully."
echo ""

# ZIP の作成
echo "Creating ZIP archive..."
cd "$TEMP_DIR"
zip -r "../../$ZIP_PATH" "novel-game-plugin" -q
cd ../..

echo "ZIP created: $ZIP_PATH"
echo ""

# SHA256 チェックサムの生成
echo "Generating SHA256 checksum..."
cd "$OUTPUT_DIR"
$SHA256_CMD "$ZIP_NAME" > "${ZIP_NAME}.sha256"
cd ..

echo "Checksum created: ${ZIP_PATH}.sha256"
echo ""

# 結果の表示
echo "=========================================="
echo "Build completed successfully!"
echo "=========================================="
echo ""
echo "Output files:"
echo "  - $ZIP_PATH"
echo "  - ${ZIP_PATH}.sha256"
echo ""

# ファイルサイズの表示
if [ -f "$ZIP_PATH" ]; then
    SIZE=$(du -h "$ZIP_PATH" | cut -f1)
    echo "ZIP size: $SIZE"
fi
echo ""

# チェックサムの表示
if [ -f "${ZIP_PATH}.sha256" ]; then
    echo "SHA256 checksum:"
    cat "${ZIP_PATH}.sha256"
fi
echo ""

# 一時ディレクトリのクリーンアップ
echo "Cleaning up temporary files..."
rm -rf "$TEMP_DIR"

echo "Done!"
