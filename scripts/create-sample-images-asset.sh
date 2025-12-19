#!/bin/bash
#
# サンプル画像アセット作成スクリプト
# 
# GitHub Release 用のサンプル画像 ZIP とチェックサムを生成します。
# 
# 使用方法:
#   ./scripts/create-sample-images-asset.sh v1.3.0
#
# @package NovelGamePlugin
# @since 1.3.0

set -e

# バージョン番号をパラメータから取得
VERSION=${1:-v1.0.0}

# カラー出力用
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}サンプル画像アセット作成スクリプト${NC}"
echo "バージョン: ${VERSION}"
echo ""

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
ASSETS_DIR="${PLUGIN_DIR}/assets"
SAMPLE_IMAGES_DIR="${ASSETS_DIR}/sample-images"
BUILD_DIR="${PLUGIN_DIR}/build"

# サンプル画像ディレクトリの存在確認
if [ ! -d "$SAMPLE_IMAGES_DIR" ]; then
    echo -e "${RED}エラー: サンプル画像ディレクトリが見つかりません: ${SAMPLE_IMAGES_DIR}${NC}"
    exit 1
fi

# ビルドディレクトリを作成
mkdir -p "$BUILD_DIR"

# ZIP ファイル名
ZIP_FILENAME="novel-game-plugin-sample-images-${VERSION}.zip"
ZIP_PATH="${BUILD_DIR}/${ZIP_FILENAME}"
CHECKSUM_PATH="${ZIP_PATH}.sha256"

echo "作業ディレクトリ: ${BUILD_DIR}"
echo "サンプル画像ディレクトリ: ${SAMPLE_IMAGES_DIR}"
echo ""

# 既存の ZIP ファイルを削除
if [ -f "$ZIP_PATH" ]; then
    echo -e "${YELLOW}既存の ZIP ファイルを削除します: ${ZIP_PATH}${NC}"
    rm "$ZIP_PATH"
fi

if [ -f "$CHECKSUM_PATH" ]; then
    echo -e "${YELLOW}既存のチェックサムファイルを削除します: ${CHECKSUM_PATH}${NC}"
    rm "$CHECKSUM_PATH"
fi

echo ""
echo -e "${GREEN}ZIP ファイルを作成しています...${NC}"

# サンプル画像を ZIP 化
cd "$ASSETS_DIR"
zip -r "$ZIP_PATH" sample-images/ -x "*.DS_Store" "*/._*"

if [ $? -ne 0 ]; then
    echo -e "${RED}エラー: ZIP ファイルの作成に失敗しました${NC}"
    exit 1
fi

echo -e "${GREEN}✓ ZIP ファイルを作成しました: ${ZIP_PATH}${NC}"

# ファイルサイズを表示
ZIP_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
echo "  ファイルサイズ: ${ZIP_SIZE}"

# チェックサムを生成
echo ""
echo -e "${GREEN}SHA256 チェックサムを生成しています...${NC}"

cd "$BUILD_DIR"
sha256sum "$ZIP_FILENAME" > "$CHECKSUM_PATH"

if [ $? -ne 0 ]; then
    echo -e "${RED}エラー: チェックサムの生成に失敗しました${NC}"
    exit 1
fi

echo -e "${GREEN}✓ チェックサムを生成しました: ${CHECKSUM_PATH}${NC}"

# チェックサムを表示
CHECKSUM=$(cat "$CHECKSUM_PATH")
echo "  ${CHECKSUM}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}完了！${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "作成されたファイル:"
echo "  1. ${ZIP_PATH}"
echo "  2. ${CHECKSUM_PATH}"
echo ""
echo "次のステップ:"
echo "  1. GitHub Release ページを開く"
echo "  2. 新しいリリース (${VERSION}) を作成"
echo "  3. 上記の 2 つのファイルをアセットとしてアップロード"
echo ""
echo -e "${YELLOW}注意: リリース作成後、プラグインのダウンロード機能が正常に動作するか確認してください${NC}"
