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
    mapfile -t FILES < <(find . -type f ! -name "*.DS_Store" ! -name "._*" -exec du -b {} + | sort -rn | cut -f2-)
    
    # 分割設定（バイト単位）
    MAX_PART_SIZE=$((10 * 1024 * 1024))  # 10MB目標
    PART_NUM=1
    CURRENT_SIZE=0
    declare -a PART_FILES
    
    # ファイルを分割
    for FILE in "${FILES[@]}"; do
        FILE_SIZE=$(du -b "$SAMPLE_IMAGES_DIR/$FILE" | cut -f1)
        
        # 現在のパートが上限に達したら新しいパートを開始
        if [ $CURRENT_SIZE -gt 0 ] && [ $(($CURRENT_SIZE + $FILE_SIZE)) -gt $MAX_PART_SIZE ]; then
            # 現在のパートを ZIP 化
            create_part_zip $PART_NUM "${PART_FILES[@]}"
            
            # 次のパート準備
            PART_NUM=$((PART_NUM + 1))
            CURRENT_SIZE=0
            PART_FILES=()
        fi
        
        # ファイルを現在のパートに追加
        PART_FILES+=("$FILE")
        CURRENT_SIZE=$(($CURRENT_SIZE + $FILE_SIZE))
    done
    
    # 最後のパートを ZIP 化
    if [ ${#PART_FILES[@]} -gt 0 ]; then
        create_part_zip $PART_NUM "${PART_FILES[@]}"
    fi
    
    # 一時ディレクトリを削除
    rm -rf "$TEMP_DIR"
    
    echo -e "${GREEN}✓ 分割 ZIP ファイルの作成が完了しました（${PART_NUM} パート）${NC}"
    echo ""
}

# パート ZIP 作成関数
create_part_zip() {
    local part_num=$1
    shift
    local files=("$@")
    
    local part_name=$(printf "part%02d" $part_num)
    local zip_filename="novel-game-plugin-sample-images-${VERSION}-${part_name}.zip"
    local zip_path="${BUILD_DIR}/${zip_filename}"
    
    echo "  パート ${part_num} を作成中..."
    
    # 一時ディレクトリにファイルをコピー
    local part_temp_dir="${TEMP_DIR}/${part_name}"
    mkdir -p "$part_temp_dir"
    
    for file in "${files[@]}"; do
        cp "$SAMPLE_IMAGES_DIR/$file" "$part_temp_dir/"
    done
    
    # ZIP 作成
    cd "$part_temp_dir"
    zip -q -r "$zip_path" .
    
    # チェックサム生成
    cd "$BUILD_DIR"
    sha256sum "$zip_filename" > "${zip_filename}.sha256"
    
    # サイズ表示
    local size=$(du -h "$zip_path" | cut -f1)
    echo "    ${zip_filename} (${size})"
}

# 単一まとめ ZIP 生成関数
generate_single_zip() {
    echo -e "${GREEN}単一まとめ ZIP ファイルを作成しています...${NC}"
    
    local zip_filename="novel-game-plugin-sample-images-${VERSION}-all.zip"
    local zip_path="${BUILD_DIR}/${zip_filename}"
    
    # サンプル画像を ZIP 化
    cd "$ASSETS_DIR"
    zip -q -r "$zip_path" sample-images/ -x "*.DS_Store" "*/._*"
    
    if [ $? -ne 0 ]; then
        echo -e "${RED}エラー: ZIP ファイルの作成に失敗しました${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✓ まとめ ZIP ファイルを作成しました: ${zip_filename}${NC}"
    
    # ファイルサイズを表示
    local zip_size=$(du -h "$zip_path" | cut -f1)
    echo "  ファイルサイズ: ${zip_size}"
    
    # チェックサムを生成
    cd "$BUILD_DIR"
    sha256sum "$zip_filename" > "${zip_filename}.sha256"
    
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
