#!/bin/bash
#
# サンプル画像 ZIP ビルドスクリプト
# assets/sample-images/ の内容を配布用 ZIP にパッケージ化します
#
# Usage: ./scripts/build-sample-images.sh [version]
# Example: ./scripts/build-sample-images.sh v1.3.0
#
# Output:
#   - novel-game-plugin-sample-images-{version}.zip
#   - novel-game-plugin-sample-images-{version}.zip.sha256
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

# バージョン引数の取得（必須）
VERSION="${1:-}"
if [ -z "$VERSION" ]; then
    echo "Error: Version argument is required." >&2
    echo "Usage: $0 <version>" >&2
    exit 1
fi

echo "=========================================="
echo "Building sample images ZIP for version: $VERSION"
echo "=========================================="
echo ""

# sample-images ディレクトリの存在確認
if [ ! -d "assets/sample-images" ] || [ -z "$(ls -A assets/sample-images 2>/dev/null)" ]; then
    echo "No sample images found in assets/sample-images/; skipping."
    exit 0
fi

# 作業ディレクトリの準備
BUILD_DIR="build"
TEMP_DIR="$BUILD_DIR/temp-sample-images"
OUTPUT_DIR="$BUILD_DIR"
ZIP_NAME="novel-game-plugin-sample-images-${VERSION}.zip"
ZIP_PATH="$OUTPUT_DIR/$ZIP_NAME"

# クリーンアップ（sample-images 用の一時ディレクトリのみ）
echo "Cleaning up previous sample images build artifacts..."
rm -rf "$TEMP_DIR"
mkdir -p "$OUTPUT_DIR"
mkdir -p "$TEMP_DIR"

# ファイルのコピー（rsync を使用して除外パターンを適用）
echo "Copying sample image files..."
rsync -av --progress \
    --exclude='.git/' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='*.tmp' \
    --exclude='*.temp' \
    assets/sample-images/ "$TEMP_DIR/"

echo ""
echo "Files copied successfully."
echo ""

# ZIP の作成
echo "Creating ZIP archive..."
cd "$TEMP_DIR"
zip -r "../../$ZIP_PATH" . -q
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
