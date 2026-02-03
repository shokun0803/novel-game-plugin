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

# 必須コマンドチェック
for cmd in zip find rsync; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "Error: $cmd is required but not installed." >&2
        echo "Please install $cmd on the system." >&2
        exit 1
    fi
done

# SHA256 生成コマンドの確認（配列として保持）
if command -v sha256sum >/dev/null 2>&1; then
    SHA256_CMD=(sha256sum)
elif command -v shasum >/dev/null 2>&1; then
    SHA256_CMD=(shasum -a 256)
else
    echo "Error: Neither sha256sum nor shasum is available." >&2
    echo "Please install coreutils or perl on the system." >&2
    exit 1
fi

# Python3 の確認（ファイルサイズ取得のフォールバックに使用される可能性）
if command -v python3 >/dev/null 2>&1; then
    PYTHON3_AVAILABLE=true
else
    PYTHON3_AVAILABLE=false
fi

# ファイルサイズ取得関数（簡易版、互換性対応）
get_file_size_bytes() {
    local file="$1"
    local size

    if [ ! -f "$file" ]; then
        echo ""  # 空文字を返して失敗を示す
        return 1
    fi

    if size=$(stat -c%s "$file" 2>/dev/null); then
        echo "$size"
        return 0
    fi

    if size=$(stat -f%z "$file" 2>/dev/null); then
        echo "$size"
        return 0
    fi

    if [ "$PYTHON3_AVAILABLE" = true ]; then
        if size=$(python3 -c "import os; print(os.path.getsize('$file'))" 2>/dev/null); then
            echo "$size"
            return 0
        fi
    fi

    if size=$(du -b "$file" 2>/dev/null | cut -f1); then
        echo "$size"
        return 0
    fi

    echo ""
    return 1
}

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

# 生成された ZIP のサイズ検証（数値で取得できることを確認）
echo "Verifying generated ZIP sizes..."
for f in build/novel-game-plugin-sample-images-${VERSION}*.zip; do
    [ -e "$f" ] || continue
    size=$(get_file_size_bytes "$f") || true
    if ! printf '%s' "$size" | grep -Eq '^[0-9]+$'; then
        echo "Error: Failed to determine numeric size for: $f" >&2
        exit 1
    fi
    echo "  $f -> ${size} bytes"
done

echo ""
echo "Done!"
