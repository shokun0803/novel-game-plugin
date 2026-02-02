#!/bin/bash
#
# サンプル画像 ZIP ビルドスクリプト
# assets/sample-images/ の内容を配布用 ZIP にパッケージ化します
#
# このスクリプトは create-sample-images-asset.sh のラッパーとして機能します
#
# Usage: ./scripts/build-sample-images.sh [version] [--split|--no-split|--all]
# Example: ./scripts/build-sample-images.sh v1.3.0
#
# Output (デフォルト --split):
#   - novel-game-plugin-sample-images-{version}-part01.zip
#   - novel-game-plugin-sample-images-{version}-part02.zip
#   - ...
#   各 ZIP に対応する .sha256 チェックサムファイル
#
# Output (--all):
#   - 分割 ZIP + まとめ ZIP (sample-images-{version}-all.zip)
#

set -euo pipefail

# バージョン引数の取得（必須）
VERSION="${1:-}"
if [ -z "$VERSION" ]; then
    echo "Error: Version argument is required." >&2
    echo "Usage: $0 <version> [--split|--no-split|--all]" >&2
    exit 1
fi

# オプションの取得（オプショナル）
OPTION="${2:---split}"

echo "=========================================="
echo "Building sample images ZIP for version: $VERSION"
echo "=========================================="
echo ""

# sample-images ディレクトリの存在確認
if [ ! -d "assets/sample-images" ] || [ -z "$(ls -A assets/sample-images 2>/dev/null)" ]; then
    echo "No sample images found in assets/sample-images/; skipping."
    exit 0
fi

# create-sample-images-asset.sh を呼び出す
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"${SCRIPT_DIR}/create-sample-images-asset.sh" "$VERSION" "$OPTION"

echo ""
echo "Done!"
