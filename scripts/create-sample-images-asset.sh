#!/bin/bash
#
# サンプル画像アセット作成スクリプト
# 
# GitHub Release 用のサンプル画像 ZIP とチェックサムを生成します。
# サンプル画像を複数の小さな ZIP に分割し、低容量ホスティング環境での
# ダウンロードを容易にします。
# 
# 使用方法:
#   ./scripts/create-sample-images-asset.sh v1.3.0 [--split]
#
# オプション:
#   --split     画像を複数の ZIP に分割（デフォルト: 有効）
#   --no-split  単一の ZIP のみ生成
#   --all       分割 ZIP と単一まとめ ZIP の両方を生成
#
# @package NovelGamePlugin
# @since 1.3.0

set -e

# 必須コマンドチェック
for cmd in zip find; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "Error: $cmd is required but not installed." >&2
        echo "Please install $cmd on the system." >&2
        exit 1
    fi
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

# ファイルサイズ取得関数（互換性対応）
get_file_size_bytes() {
    local file="$1"
    if stat -c%s "$file" >/dev/null 2>&1; then
        # GNU stat
        stat -c%s "$file"
    elif stat -f%z "$file" >/dev/null 2>&1; then
        # BSD stat
        stat -f%z "$file"
    elif python3 -c "import os; print(os.path.getsize('$file'))" 2>/dev/null; then
        # Python fallback
        python3 -c "import os; print(os.path.getsize('$file'))"
    else
        # 最後の手段: du -b（一部環境で動作しない可能性あり）
        du -b "$file" 2>/dev/null | cut -f1 || echo "0"
    fi
}

# チェックサム生成関数
generate_checksum() {
    local filename="$1"
    $SHA256_CMD "$filename" > "${filename}.sha256"
}

# バージョン番号をパラメータから取得
VERSION=${1:-v1.0.0}
SPLIT_MODE="split"  # デフォルトは分割モード

# オプション解析
shift || true
while [ $# -gt 0 ]; do
    case "$1" in
        --split)
            SPLIT_MODE="split"
            ;;
        --no-split)
            SPLIT_MODE="single"
            ;;
        --all)
            SPLIT_MODE="all"
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
    shift
done

# カラー出力用
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # 色なし

echo -e "${GREEN}サンプル画像アセット作成スクリプト${NC}"
echo "バージョン: ${VERSION}"
echo "モード: ${SPLIT_MODE}"
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

echo "作業ディレクトリ: ${BUILD_DIR}"
echo "サンプル画像ディレクトリ: ${SAMPLE_IMAGES_DIR}"
echo ""

# 既存の ZIP ファイルを削除
echo -e "${YELLOW}既存のサンプル画像 ZIP ファイルを削除します...${NC}"
rm -f "${BUILD_DIR}/novel-game-plugin-sample-images-${VERSION}"*.zip*
echo ""

# 分割 ZIP 生成関数
generate_split_zips() {
    echo -e "${GREEN}分割 ZIP ファイルを作成しています...${NC}"
    
    # 一時作業ディレクトリ
    TEMP_DIR="${BUILD_DIR}/temp-split-images"
    rm -rf "$TEMP_DIR"
    mkdir -p "$TEMP_DIR"
    
    # ファイルをサイズでソートして取得（大きいファイルから先に処理）
    cd "$SAMPLE_IMAGES_DIR"
    
    # IFS を改行のみに設定して、ファイル名に空白があっても安全に扱う
    local OLD_IFS="$IFS"
    IFS=$'\n'
    
    # ファイルリストを取得（サイズ降順）
    local file_list=()
    while IFS= read -r line; do
        file_list+=("$line")
    done < <(find . -type f ! -name "*.DS_Store" ! -name "._*" -print0 | while IFS= read -r -d '' file; do
        size=$(get_file_size_bytes "$file")
        printf "%s\t%s\n" "$size" "$file"
    done | sort -rn | cut -f2-)
    
    IFS="$OLD_IFS"
    
    # 分割設定（バイト単位）
    MAX_PART_SIZE=$((10 * 1024 * 1024))  # 10MB目標
    PART_NUM=1
    CURRENT_SIZE=0
    declare -a PART_FILES
    
    echo "  総ファイル数: ${#file_list[@]}"
    
    # ファイルを分割
    for FILE in "${file_list[@]}"; do
        FILE_SIZE=$(get_file_size_bytes "$SAMPLE_IMAGES_DIR/$FILE")
        
        # 単一ファイルが上限を超える場合の警告
        if [ "$FILE_SIZE" -gt "$MAX_PART_SIZE" ]; then
            echo -e "  ${YELLOW}警告: ファイル $FILE のサイズ ($FILE_SIZE bytes) が MAX_PART_SIZE を超えています${NC}"
            echo -e "  ${YELLOW}      このファイルは単独で1パートに含めます${NC}"
            
            # 現在のパートに他のファイルがあれば先に出力
            if [ ${#PART_FILES[@]} -gt 0 ]; then
                create_part_zip "$PART_NUM" "${PART_FILES[@]}"
                PART_NUM=$((PART_NUM + 1))
                PART_FILES=()
                CURRENT_SIZE=0
            fi
            
            # 大きなファイルを単独でパートに追加
            create_part_zip "$PART_NUM" "$FILE"
            PART_NUM=$((PART_NUM + 1))
            CURRENT_SIZE=0
            continue
        fi
        
        # 現在のパートが上限に達したら新しいパートを開始
        if [ "$CURRENT_SIZE" -gt 0 ] && [ $((CURRENT_SIZE + FILE_SIZE)) -gt "$MAX_PART_SIZE" ]; then
            # 現在のパートを ZIP 化
            create_part_zip "$PART_NUM" "${PART_FILES[@]}"
            
            # 次のパート準備
            PART_NUM=$((PART_NUM + 1))
            CURRENT_SIZE=0
            PART_FILES=()
        fi
        
        # ファイルを現在のパートに追加
        PART_FILES+=("$FILE")
        CURRENT_SIZE=$((CURRENT_SIZE + FILE_SIZE))
    done
    
    # 最後のパートを ZIP 化
    if [ ${#PART_FILES[@]} -gt 0 ]; then
        create_part_zip "$PART_NUM" "${PART_FILES[@]}"
    fi
    
    # 一時ディレクトリを削除
    rm -rf "$TEMP_DIR"
    
    echo -e "${GREEN}✓ 分割 ZIP ファイルの作成が完了しました（${PART_NUM} パート）${NC}"
    echo ""
}

# パート ZIP 作成関数
create_part_zip() {
    local part_num="$1"
    shift
    local files=("$@")
    
    local part_name
    part_name=$(printf "part%02d" "$part_num")
    local zip_filename="novel-game-plugin-sample-images-${VERSION}-${part_name}.zip"
    local zip_path="${BUILD_DIR}/${zip_filename}"
    
    echo "  パート ${part_num} を作成中 (${#files[@]} ファイル)..."
    
    # 一時ディレクトリにファイルをコピー（パス構造を保持）
    local part_temp_dir="${TEMP_DIR}/${part_name}"
    mkdir -p "$part_temp_dir"
    
    # ファイルのパス構造を保持してコピー
    for file in "${files[@]}"; do
        # ファイル名に空白や特殊文字が含まれても安全に処理
        local target_dir
        target_dir=$(dirname "$file")
        mkdir -p "$part_temp_dir/$target_dir"
        cp "$SAMPLE_IMAGES_DIR/$file" "$part_temp_dir/$file"
    done
    
    # ZIP 作成（相対パスを保持）
    cd "$part_temp_dir"
    if ! zip -q -r "$zip_path" .; then
        echo -e "${RED}エラー: ZIP ファイルの作成に失敗しました: $zip_filename${NC}" >&2
        exit 1
    fi
    
    # チェックサム生成
    cd "$BUILD_DIR"
    generate_checksum "$zip_filename"
    
    # サイズ表示
    local size
    size=$(du -h "$zip_path" | cut -f1)
    local file_count=${#files[@]}
    echo "    ${zip_filename} (${size}, ${file_count} ファイル)"
}

# 単一まとめ ZIP 生成関数
generate_single_zip() {
    echo -e "${GREEN}単一まとめ ZIP ファイルを作成しています...${NC}"
    
    local zip_filename="novel-game-plugin-sample-images-${VERSION}-all.zip"
    local zip_path="${BUILD_DIR}/${zip_filename}"
    
    # サンプル画像を ZIP 化
    cd "$ASSETS_DIR"
    if ! zip -q -r "$zip_path" sample-images/ -x "*.DS_Store" "*/._*"; then
        echo -e "${RED}エラー: ZIP ファイルの作成に失敗しました${NC}" >&2
        exit 1
    fi
    
    echo -e "${GREEN}✓ まとめ ZIP ファイルを作成しました: ${zip_filename}${NC}"
    
    # ファイルサイズを表示
    local zip_size
    zip_size=$(du -h "$zip_path" | cut -f1)
    echo "  ファイルサイズ: ${zip_size}"
    
    # チェックサムを生成
    cd "$BUILD_DIR"
    generate_checksum "$zip_filename"
    
    echo ""
}

# モードに応じて ZIP を生成
case "$SPLIT_MODE" in
    split)
        generate_split_zips
        ;;
    single)
        generate_single_zip
        ;;
    all)
        generate_split_zips
        generate_single_zip
        ;;
esac

# 完了メッセージ
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}完了！${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "作成されたファイル:"
ls -lh "${BUILD_DIR}"/novel-game-plugin-sample-images-${VERSION}*.zip 2>/dev/null | awk '{print "  - " $9 " (" $5 ")"}'
echo ""
echo "チェックサムファイル:"
ls -1 "${BUILD_DIR}"/novel-game-plugin-sample-images-${VERSION}*.sha256 2>/dev/null | awk '{print "  - " $1}'
echo ""
echo "次のステップ:"
echo "  1. GitHub Release ページを開く"
echo "  2. 新しいリリース (${VERSION}) を作成"
echo "  3. 上記のファイルをアセットとしてアップロード"
echo ""
if [ "$SPLIT_MODE" = "split" ] || [ "$SPLIT_MODE" = "all" ]; then
    echo -e "${YELLOW}注意: 分割された ZIP ファイルはプラグインの管理画面から順次ダウンロードされます${NC}"
    echo -e "${YELLOW}      アセット名の命名規則（sample-images-{version}-partNN.zip）を維持してください${NC}"
fi
echo ""
