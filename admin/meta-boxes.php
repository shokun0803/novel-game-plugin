<?php
/**
 * 管理画面のメタボックスとAjax処理
 *
 * @package NovelGamePlugin
 * @since 1.0.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ajax経由で新規ノベルゲーム投稿を作成
 *
 * @since 1.0.0
 */
function noveltool_ajax_create_scene() {
    // セキュリティチェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => __( '権限がありません。', 'novel-game-plugin' ) ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'novel_game_meta_box_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'セキュリティチェックに失敗しました。', 'novel-game-plugin' ) ) );
    }

    // タイトルの取得とサニタイズ
    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    
    // 自動遷移フラグの取得
    $auto_redirect = isset( $_POST['auto_redirect'] ) ? filter_var( wp_unslash( $_POST['auto_redirect'] ), FILTER_VALIDATE_BOOLEAN ) : false;
    
    // 現在の投稿IDの取得（ゲームタイトルを継承するため）
    $current_post_id = isset( $_POST['current_post_id'] ) ? intval( wp_unslash( $_POST['current_post_id'] ) ) : 0;

    if ( empty( $title ) ) {
        wp_send_json_error( array( 'message' => __( 'タイトルが入力されていません。', 'novel-game-plugin' ) ) );
    }

    // 新規投稿の作成
    $post_data = array(
        'post_type'   => 'novel_game',
        'post_title'  => $title,
        'post_status' => 'publish',
    );

    $new_id = wp_insert_post( $post_data );

    if ( $new_id && ! is_wp_error( $new_id ) ) {
        // 現在の投稿からゲームタイトルを継承
        if ( $current_post_id ) {
            $game_title = get_post_meta( $current_post_id, '_game_title', true );
            if ( $game_title ) {
                update_post_meta( $new_id, '_game_title', $game_title );
            }
        }
        
        $response_data = array(
            'ID'    => $new_id,
            'title' => $title,
        );
        
        // 自動遷移が要求されている場合、編集URLを追加
        if ( $auto_redirect ) {
            $response_data['edit_url'] = admin_url( 'post.php?post=' . $new_id . '&action=edit' );
        }
        
        wp_send_json_success( $response_data );
    } else {
        wp_send_json_error( array( 'message' => __( '投稿の作成に失敗しました。', 'novel-game-plugin' ) ) );
    }
}
add_action( 'wp_ajax_novel_game_create_scene', 'noveltool_ajax_create_scene' );

/**
 * 管理画面でのスクリプト読み込み時にnonceとajaxurlを設定
 *
 * @param string $hook 現在のページフック
 * @since 1.0.0
 */
function noveltool_admin_enqueue_scripts( $hook ) {
    global $post;

    // 投稿編集画面でのみ実行
    if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }

    // novel_game投稿タイプでのみ実行
    if ( isset( $post->post_type ) && 'novel_game' !== $post->post_type ) {
        return;
    }

    // 管理画面用スクリプトの読み込み
    wp_enqueue_script(
        'novel-game-admin',
        NOVEL_GAME_PLUGIN_URL . 'js/admin.js',
        array( 'jquery' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );
    
    // メタボックス用スクリプトの読み込み
    wp_enqueue_script(
        'novel-game-admin-meta-boxes',
        NOVEL_GAME_PLUGIN_URL . 'js/admin-meta-boxes.js',
        array( 'jquery', 'jquery-ui-sortable' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );
    
    // 現在の投稿のゲームタイトルからフラグマスタを取得
    $current_game_title = get_post_meta( $post->ID, '_game_title', true );
    $flag_master = array();
    if ( $current_game_title ) {
        $flag_master = noveltool_get_game_flag_master( $current_game_title );
    }
    
    // JavaScriptに渡すデータ
    $js_data = array(
        'flagMaster' => $flag_master,
        'gameTitle' => $current_game_title,
    );
    
    wp_localize_script( 'novel-game-admin-meta-boxes', 'novelGameFlagData', $js_data );
}
add_action( 'admin_enqueue_scripts', 'noveltool_admin_enqueue_scripts' );
/**
 * メタボックスを追加
 *
 * @since 1.0.0
 */
function noveltool_add_meta_boxes() {
    add_meta_box(
        'novel_scene_data',
        __( 'シーンデータ', 'novel-game-plugin' ),
        'noveltool_meta_box_callback',
        'novel_game',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'noveltool_add_meta_boxes' );

/**
 * メタボックスのコールバック関数
 *
 * @param WP_Post $post 投稿オブジェクト
 * @since 1.0.0
 */
function noveltool_meta_box_callback( $post ) {
    // nonceフィールドの追加
    wp_nonce_field( 'novel_game_meta_box', 'novel_game_meta_box_nonce' );

    // 現在の値を取得
    $background  = get_post_meta( $post->ID, '_background_image', true );
    $character   = get_post_meta( $post->ID, '_character_image', true );
    $dialogue    = get_post_meta( $post->ID, '_dialogue_text', true );
    $choices     = get_post_meta( $post->ID, '_choices', true );
    $game_title  = get_post_meta( $post->ID, '_game_title', true );
    $dialogue_backgrounds = get_post_meta( $post->ID, '_dialogue_backgrounds', true );
    
    // 3体キャラクター対応の値を取得
    $character_left   = get_post_meta( $post->ID, '_character_left', true );
    $character_center = get_post_meta( $post->ID, '_character_center', true );
    $character_right  = get_post_meta( $post->ID, '_character_right', true );
    $dialogue_speakers = get_post_meta( $post->ID, '_dialogue_speakers', true );
    
    // キャラクター名前の取得
    $character_left_name   = get_post_meta( $post->ID, '_character_left_name', true );
    $character_center_name = get_post_meta( $post->ID, '_character_center_name', true );
    $character_right_name  = get_post_meta( $post->ID, '_character_right_name', true );
    
    // エンディング設定の取得
    $is_ending = get_post_meta( $post->ID, '_is_ending', true );
    $ending_text = get_post_meta( $post->ID, '_ending_text', true );
    
    // セリフフラグ条件データの取得
    $dialogue_flag_conditions = get_post_meta( $post->ID, '_dialogue_flag_conditions', true );
    if ( ! is_array( $dialogue_flag_conditions ) ) {
        $dialogue_flag_conditions = array();
    }
    
    // 後方互換性：既存の単一キャラクター画像をセンター位置に移行
    if ( $character && ! $character_center ) {
        $character_center = $character;
    }
    
    // 対話背景データの処理
    if ( is_string( $dialogue_backgrounds ) ) {
        $dialogue_backgrounds = json_decode( $dialogue_backgrounds, true );
    }
    if ( ! is_array( $dialogue_backgrounds ) ) {
        $dialogue_backgrounds = array();
    }
    
    // 対話話者データの処理
    if ( is_string( $dialogue_speakers ) ) {
        $dialogue_speakers = json_decode( $dialogue_speakers, true );
    }
    if ( ! is_array( $dialogue_speakers ) ) {
        $dialogue_speakers = array();
    }
    
    // 既存のセリフデータの処理
    $dialogue_lines = array();
    
    // JSONベースのデータが存在する場合は、それを使用
    if ( is_string( $dialogue_speakers ) ) {
        $dialogue_speakers_array = json_decode( $dialogue_speakers, true );
    } elseif ( is_array( $dialogue_speakers ) ) {
        $dialogue_speakers_array = $dialogue_speakers;
    } else {
        $dialogue_speakers_array = array();
    }
    
    // セリフテキストデータの取得（新しいJSON形式）
    $dialogue_texts = get_post_meta( $post->ID, '_dialogue_texts', true );
    if ( is_string( $dialogue_texts ) ) {
        $dialogue_texts_array = json_decode( $dialogue_texts, true );
    } elseif ( is_array( $dialogue_texts ) ) {
        $dialogue_texts_array = $dialogue_texts;
    } else {
        $dialogue_texts_array = array();
    }
    
    // JSONベースのデータが存在する場合はそれを優先
    if ( ! empty( $dialogue_speakers_array ) || ! empty( $dialogue_backgrounds ) || ! empty( $dialogue_texts_array ) ) {
        // 新しいJSONベースのシステムを使用
        if ( is_string( $dialogue_backgrounds ) ) {
            $dialogue_backgrounds_array = json_decode( $dialogue_backgrounds, true );
        } elseif ( is_array( $dialogue_backgrounds ) ) {
            $dialogue_backgrounds_array = $dialogue_backgrounds;
        } else {
            $dialogue_backgrounds_array = array();
        }
        
        // セリフテキストは新しいJSONデータを優先し、改行で分割しない
        $max_count = max(
            count( (array) $dialogue_speakers_array ),
            count( (array) $dialogue_backgrounds_array ),
            count( (array) $dialogue_texts_array )
        );
        if ( $max_count > 0 ) {
            // 新しいJSONベースのテキストデータが存在する場合は、それを使用
            if ( ! empty( $dialogue_texts_array ) ) {
                for ( $i = 0; $i < $max_count; $i++ ) {
                    $dialogue_lines[] = isset( $dialogue_texts_array[ $i ] ) ? $dialogue_texts_array[ $i ] : '';
                }
            } else {
                // 古いテキストデータから改行で分割して取得（後方互換性のため）
                $old_dialogue_lines = array();
                if ( $dialogue ) {
                    $old_dialogue_lines = array_filter( array_map( 'trim', explode( "\n", $dialogue ) ) );
                }
                
                // JSON データの数に合わせてセリフを構築
                for ( $i = 0; $i < $max_count; $i++ ) {
                    $dialogue_lines[] = isset( $old_dialogue_lines[ $i ] ) ? $old_dialogue_lines[ $i ] : '';
                }
            }
        }
    } else {
        // 古いシステムの場合、改行で分割（後方互換性のため）
        if ( $dialogue ) {
            $dialogue_lines = array_filter( array_map( 'trim', explode( "\n", $dialogue ) ) );
        }
    }

    // WordPressメディアアップローダー用スクリプトの読み込み
    wp_enqueue_media();

    // 管理画面専用スクリプトの読み込み
    wp_enqueue_script(
        'novel-game-admin-meta-boxes',
        NOVEL_GAME_PLUGIN_URL . 'js/admin-meta-boxes.js',
        array( 'jquery', 'jquery-ui-sortable', 'media-upload', 'media-views' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );

    // 投稿一覧を取得してJavaScriptに渡す
    $scene_posts = get_posts(
        array(
            'post_type'      => 'novel_game',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        )
    );

    $scenes_data = array();
    foreach ( $scene_posts as $scene_post ) {
        $scenes_data[] = array(
            'ID'    => $scene_post->ID,
            'title' => $scene_post->post_title,
        );
    }

    // JavaScript用の翻訳文字列
    $js_strings = array(
        'selectOption'       => __( '-- 選択 --', 'novel-game-plugin' ),
        'createNew'          => __( '+ 新規作成...', 'novel-game-plugin' ),
        'remove'             => __( '削除', 'novel-game-plugin' ),
        'selectImage'        => __( '画像を選択', 'novel-game-plugin' ),
        'useThisImage'       => __( 'この画像を使う', 'novel-game-plugin' ),
        'confirmDelete'      => __( '本当に削除しますか？', 'novel-game-plugin' ),
        'createFailed'       => __( '作成に失敗しました。', 'novel-game-plugin' ),
        'selectTitle'        => __( '新しいシーンのタイトルを入力してください', 'novel-game-plugin' ),
        'selectNextTitle'    => __( '次のコマンドのタイトルを入力してください', 'novel-game-plugin' ),
        'redirectingMessage' => __( '作成中です...', 'novel-game-plugin' ),
    );

    // データをJavaScriptに渡す
    wp_localize_script( 'novel-game-admin-meta-boxes', 'novelGameScenes', $scenes_data );
    wp_localize_script(
        'novel-game-admin-meta-boxes',
        'novelGameMeta',
        array(
            'nonce'         => wp_create_nonce( 'novel_game_meta_box_nonce' ),
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'admin_url'     => admin_url(),
            'current_post_id' => $post->ID,
            'strings'       => $js_strings,
            'dialogue_lines' => $dialogue_lines,
            'dialogue_backgrounds' => $dialogue_backgrounds,
            'dialogue_speakers' => $dialogue_speakers,
            'character_left' => $character_left,
            'character_center' => $character_center,
            'character_right' => $character_right,
            'character_left_name' => $character_left_name,
            'character_center_name' => $character_center_name,
            'character_right_name' => $character_right_name,
            'is_ending' => (bool) $is_ending,
            'dialogue_flag_conditions' => $dialogue_flag_conditions,
        )
    );

    // HTMLテンプレートの出力
    ?>
    <style>
    .character-positions-container {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    
    .character-position-item {
        border: 1px solid #ccd0d4;
        padding: 15px;
        border-radius: 4px;
        background: #f9f9f9;
        min-width: 200px;
        flex: 1;
    }
    
    .character-position-item h4 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #333;
    }
    
    .character-name-input {
        margin-bottom: 15px;
    }
    
    .character-name-input label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        font-size: 13px;
        color: #555;
    }
    
    .character-name-input input {
        width: 100%;
        max-width: 200px;
        padding: 6px 8px;
        border: 1px solid #ccd0d4;
        border-radius: 3px;
        font-size: 13px;
    }
    
    .character-position-item img {
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .character-position-item .button {
        margin-right: 5px;
    }
    
    .novel-dialogue-item {
        border: 1px solid #ccd0d4;
        margin-bottom: 10px;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 4px;
    }
    
    .novel-dialogue-item .dialogue-text {
        margin-bottom: 10px;
    }
    
    .dialogue-speaker-container {
        margin-bottom: 10px;
    }
    
    .dialogue-speaker-container label {
        font-weight: bold;
        margin-bottom: 5px;
        display: block;
    }
    
    .dialogue-speaker-container select {
        width: 100%;
        max-width: 200px;
    }
    
    .dialogue-background-container {
        margin-bottom: 10px;
    }
    
    .dialogue-background-preview {
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .dialogue-controls {
        margin-top: 10px;
    }
    
    .dialogue-controls .button {
        margin-right: 5px;
    }
    
    .dialogue-move-up,
    .dialogue-move-down {
        font-size: 12px;
        padding: 2px 8px;
        height: auto;
        line-height: 1.2;
    }
    
    .dialogue-delete-button {
        background: #dc3232;
        color: white;
        border-color: #dc3232;
    }
    
    .dialogue-delete-button:hover {
        background: #c62d2d;
        border-color: #c62d2d;
    }
    
    /* 選択肢テーブルのスタイル */
    #novel-choices-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    #novel-choices-table th,
    #novel-choices-table td {
        padding: 8px;
        border: 1px solid #ddd;
        text-align: left;
    }
    
    .sort-handle {
        color: #666;
        font-weight: bold;
        user-select: none;
        background: #f9f9f9;
    }
    
    .sort-handle:hover {
        background: #e9e9e9;
        color: #333;
    }
    
    .ui-state-highlight {
        background: #ffffcc;
        border: 2px dashed #cccccc;
    }
    
    /* レスポンシブデザイン - 768px以下 */
    @media (max-width: 768px) {
        .character-positions-container {
            flex-direction: column;
        }
        
        .character-position-item {
            min-width: auto;
        }
        
        /* 選択肢テーブルのモバイル対応 */
        #novel-choices-table {
            font-size: 14px;
        }
        
        #novel-choices-table th,
        #novel-choices-table td {
            padding: 6px 4px;
        }
        
        .choice-text,
        .choice-next {
            font-size: 14px !important;
            width: 100% !important;
        }
        
        .sort-handle {
            font-size: 16px;
            padding: 8px 4px;
        }
        
        .choice-remove {
            font-size: 12px;
            padding: 4px 8px;
        }
        
        /* フォームテーブルのモバイル対応 */
        .form-table th,
        .form-table td {
            display: block;
            width: 100%;
            padding: 10px 0;
        }
        
        .form-table th {
            background: #f9f9f9;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        
        .regular-text {
            width: 100% !important;
            max-width: 100% !important;
        }
        
        /* メタボックス内の画像プレビュー */
        .dialogue-background-preview,
        #novel_background_image_preview,
        #novel_character_left_preview,
        #novel_character_center_preview,
        #novel_character_right_preview {
            max-width: 100%;
            height: auto;
        }
        
        /* ボタンのモバイル対応 */
        .button {
            margin: 2px 0;
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .dialogue-controls .button {
            margin: 2px 4px 2px 0;
            padding: 4px 8px;
            font-size: 12px;
        }
    }
    
    /* さらに小さい画面（480px以下）への追加対応 */
    @media (max-width: 480px) {
        #novel-choices-table {
            font-size: 12px;
        }
        
        .choice-text,
        .choice-next {
            font-size: 12px !important;
        }
        
        .choice-remove {
            font-size: 11px;
            padding: 3px 6px;
        }
        
        .sort-handle {
            font-size: 14px;
        }
        
        .dialogue-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .dialogue-controls .button {
            flex: 1;
            min-width: 60px;
            margin: 2px 0;
            text-align: center;
        }
    }
    </style>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="novel_game_title"><?php esc_html_e( 'ゲームタイトル', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <?php
                $available_games = noveltool_get_all_games();
                if ( ! empty( $available_games ) ) :
                ?>
                    <select id="novel_game_title" name="game_title" class="regular-text">
                        <option value=""><?php esc_html_e( '-- ゲームを選択してください --', 'novel-game-plugin' ); ?></option>
                        <?php foreach ( $available_games as $game ) : ?>
                            <option value="<?php echo esc_attr( $game['title'] ); ?>" <?php selected( $game_title, $game['title'] ); ?>>
                                <?php echo esc_html( $game['title'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'このシーンが属するゲームを選択してください。', 'novel-game-plugin' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) ); ?>" target="_blank">
                            <?php esc_html_e( 'ゲームを管理', 'novel-game-plugin' ); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <input type="text"
                           id="novel_game_title"
                           name="game_title"
                           value="<?php echo esc_attr( $game_title ); ?>"
                           class="regular-text"
                           placeholder="<?php esc_attr_e( 'このシーンが属するゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>" />
                    <p class="description">
                        <?php esc_html_e( 'ゲーム全体のタイトルを設定します。', 'novel-game-plugin' ); ?>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) ); ?>" target="_blank">
                            <?php esc_html_e( 'ゲームを管理', 'novel-game-plugin' ); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="novel_background_image"><?php esc_html_e( '背景画像', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <input type="hidden"
                       id="novel_background_image"
                       name="background_image"
                       value="<?php echo esc_attr( $background ); ?>" />
                <img id="novel_background_image_preview"
                     src="<?php echo esc_url( $background ); ?>"
                     alt="<?php esc_attr_e( '背景画像プレビュー', 'novel-game-plugin' ); ?>"
                     style="max-width: 300px; height: auto; display: <?php echo $background ? 'block' : 'none'; ?>;" />
                <p>
                    <button type="button"
                            class="button"
                            id="novel_background_image_button">
                        <?php esc_html_e( 'メディアから選択', 'novel-game-plugin' ); ?>
                    </button>
                </p>
                <p class="description"><?php esc_html_e( 'シーンの背景画像を設定します。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php esc_html_e( 'キャラクター画像', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <div class="character-positions-container">
                    <p class="description"><?php esc_html_e( '最大3体のキャラクターを左・中央・右の位置に配置できます。', 'novel-game-plugin' ); ?></p>
                    
                    <!-- 左キャラクター -->
                    <div class="character-position-item">
                        <h4><?php esc_html_e( '左キャラクター', 'novel-game-plugin' ); ?></h4>
                        <input type="hidden"
                               id="novel_character_left"
                               name="character_left"
                               value="<?php echo esc_attr( $character_left ); ?>" />
                        <div class="character-name-input">
                            <label for="novel_character_left_name"><?php esc_html_e( 'キャラクター名', 'novel-game-plugin' ); ?></label>
                            <input type="text"
                                   id="novel_character_left_name"
                                   name="character_left_name"
                                   value="<?php echo esc_attr( $character_left_name ); ?>"
                                   placeholder="<?php esc_attr_e( 'キャラクター名を入力', 'novel-game-plugin' ); ?>"
                                   class="regular-text" />
                        </div>
                        <img id="novel_character_left_preview"
                             src="<?php echo esc_url( $character_left ); ?>"
                             alt="<?php esc_attr_e( '左キャラクター画像プレビュー', 'novel-game-plugin' ); ?>"
                             style="max-width: 150px; height: auto; display: <?php echo $character_left ? 'block' : 'none'; ?>;" />
                        <p>
                            <button type="button"
                                    class="button character-image-button"
                                    data-position="left">
                                <?php esc_html_e( '画像を選択', 'novel-game-plugin' ); ?>
                            </button>
                            <button type="button"
                                    class="button character-image-clear"
                                    data-position="left"
                                    style="display: <?php echo $character_left ? 'inline-block' : 'none'; ?>;">
                                <?php esc_html_e( '削除', 'novel-game-plugin' ); ?>
                            </button>
                        </p>
                    </div>
                    
                    <!-- 中央キャラクター -->
                    <div class="character-position-item">
                        <h4><?php esc_html_e( '中央キャラクター', 'novel-game-plugin' ); ?></h4>
                        <input type="hidden"
                               id="novel_character_center"
                               name="character_center"
                               value="<?php echo esc_attr( $character_center ); ?>" />
                        <div class="character-name-input">
                            <label for="novel_character_center_name"><?php esc_html_e( 'キャラクター名', 'novel-game-plugin' ); ?></label>
                            <input type="text"
                                   id="novel_character_center_name"
                                   name="character_center_name"
                                   value="<?php echo esc_attr( $character_center_name ); ?>"
                                   placeholder="<?php esc_attr_e( 'キャラクター名を入力', 'novel-game-plugin' ); ?>"
                                   class="regular-text" />
                        </div>
                        <img id="novel_character_center_preview"
                             src="<?php echo esc_url( $character_center ); ?>"
                             alt="<?php esc_attr_e( '中央キャラクター画像プレビュー', 'novel-game-plugin' ); ?>"
                             style="max-width: 150px; height: auto; display: <?php echo $character_center ? 'block' : 'none'; ?>;" />
                        <p>
                            <button type="button"
                                    class="button character-image-button"
                                    data-position="center">
                                <?php esc_html_e( '画像を選択', 'novel-game-plugin' ); ?>
                            </button>
                            <button type="button"
                                    class="button character-image-clear"
                                    data-position="center"
                                    style="display: <?php echo $character_center ? 'inline-block' : 'none'; ?>;">
                                <?php esc_html_e( '削除', 'novel-game-plugin' ); ?>
                            </button>
                        </p>
                    </div>
                    
                    <!-- 右キャラクター -->
                    <div class="character-position-item">
                        <h4><?php esc_html_e( '右キャラクター', 'novel-game-plugin' ); ?></h4>
                        <input type="hidden"
                               id="novel_character_right"
                               name="character_right"
                               value="<?php echo esc_attr( $character_right ); ?>" />
                        <div class="character-name-input">
                            <label for="novel_character_right_name"><?php esc_html_e( 'キャラクター名', 'novel-game-plugin' ); ?></label>
                            <input type="text"
                                   id="novel_character_right_name"
                                   name="character_right_name"
                                   value="<?php echo esc_attr( $character_right_name ); ?>"
                                   placeholder="<?php esc_attr_e( 'キャラクター名を入力', 'novel-game-plugin' ); ?>"
                                   class="regular-text" />
                        </div>
                        <img id="novel_character_right_preview"
                             src="<?php echo esc_url( $character_right ); ?>"
                             alt="<?php esc_attr_e( '右キャラクター画像プレビュー', 'novel-game-plugin' ); ?>"
                             style="max-width: 150px; height: auto; display: <?php echo $character_right ? 'block' : 'none'; ?>;" />
                        <p>
                            <button type="button"
                                    class="button character-image-button"
                                    data-position="right">
                                <?php esc_html_e( '画像を選択', 'novel-game-plugin' ); ?>
                            </button>
                            <button type="button"
                                    class="button character-image-clear"
                                    data-position="right"
                                    style="display: <?php echo $character_right ? 'inline-block' : 'none'; ?>;">
                                <?php esc_html_e( '削除', 'novel-game-plugin' ); ?>
                            </button>
                        </p>
                    </div>
                </div>
                
                <!-- 後方互換性のために古いフィールドを残す -->
                <input type="hidden"
                       id="novel_character_image"
                       name="character_image"
                       value="<?php echo esc_attr( $character ); ?>" />
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php esc_html_e( 'セリフ', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <div id="novel-dialogue-container">
                    <div id="novel-dialogue-list">
                        <!-- セリフ一覧が動的に生成されます -->
                    </div>
                    <p>
                        <button type="button" class="button" id="novel-dialogue-add">
                            <?php esc_html_e( '+ セリフを追加', 'novel-game-plugin' ); ?>
                        </button>
                    </p>
                </div>
                <p class="description"><?php esc_html_e( '各セリフに対して背景画像を設定できます。背景画像が指定されていない場合は、前のシーンの背景が継続されます。', 'novel-game-plugin' ); ?></p>
                
                <!-- 後方互換性のために隠しフィールドを維持 -->
                <textarea id="novel_dialogue_text"
                          name="dialogue_text"
                          style="display: none;"><?php echo esc_textarea( $dialogue ); ?></textarea>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php esc_html_e( '選択肢', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <div id="novel-choices-box">
                    <table id="novel-choices-table" class="widefat">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><?php esc_html_e( '順序', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'テキスト', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( '次のシーン', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'フラグ条件', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'フラグ設定', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( '操作', 'novel-game-plugin' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- JavaScriptで動的に生成 -->
                        </tbody>
                    </table>
                    <p>
                        <button type="button"
                                class="button"
                                id="novel-choice-add">
                            <?php esc_html_e( '選択肢を追加', 'novel-game-plugin' ); ?>
                        </button>
                        <button type="button"
                                class="button button-secondary"
                                id="novel-create-next-command">
                            <?php esc_html_e( '次のコマンドを新規作成', 'novel-game-plugin' ); ?>
                        </button>
                    </p>
                    <input type="hidden"
                           id="novel_choices_hidden"
                           name="choices"
                           value="<?php echo esc_attr( $choices ); ?>" />
                    <p class="description">
                        <?php esc_html_e( 'プレイヤーが選択できる選択肢を設定します。', 'novel-game-plugin' ); ?><br>
                        <strong><?php esc_html_e( 'フラグ条件：', 'novel-game-plugin' ); ?></strong><?php esc_html_e( '表示に必要なフラグ条件（最大3つ、AND/OR指定可能）', 'novel-game-plugin' ); ?><br>
                        <strong><?php esc_html_e( 'フラグ設定：', 'novel-game-plugin' ); ?></strong><?php esc_html_e( 'この選択肢を選んだ時に設定されるフラグ', 'novel-game-plugin' ); ?>
                    </p>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="novel_is_ending"><?php esc_html_e( 'エンディング設定', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <label for="novel_is_ending">
                    <input type="checkbox"
                           id="novel_is_ending"
                           name="is_ending"
                           value="1"
                           <?php checked( $is_ending ); ?> />
                    <?php esc_html_e( 'このシーンをエンディング（ゲームの終了）として設定する', 'novel-game-plugin' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'チェックを入れると、このシーンでゲームが終了します。選択肢が設定されていてもエンディングが優先されます。', 'novel-game-plugin' ); ?></p>
                
                <div id="ending_text_setting" style="margin-top: 15px; <?php echo $is_ending ? '' : 'display: none;'; ?>">
                    <label for="novel_ending_text"><?php esc_html_e( 'エンディング画面テキスト', 'novel-game-plugin' ); ?></label><br>
                    <input type="text" 
                           id="novel_ending_text" 
                           name="ending_text" 
                           value="<?php echo esc_attr( $ending_text ); ?>" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e( 'エンディング画面に表示するテキスト（デフォルト: おわり）', 'novel-game-plugin' ); ?>" />
                    <p class="description"><?php esc_html_e( 'エンディング画面に表示するテキストを設定します。空欄の場合は「おわり」が表示されます。', 'novel-game-plugin' ); ?></p>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php esc_html_e( 'フラグ設定', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <?php
                // 現在のゲームタイトルからフラグマスタを取得
                $current_flag_master = array();
                if ( $game_title ) {
                    $current_flag_master = noveltool_get_game_flag_master( $game_title );
                }
                
                // シーン到達時フラグの取得
                $scene_arrival_flags = get_post_meta( $post->ID, '_scene_arrival_flags', true );
                if ( ! is_array( $scene_arrival_flags ) ) {
                    $scene_arrival_flags = array();
                }
                ?>
                
                <div class="noveltool-flags-container">
                    <h4><?php esc_html_e( 'シーン到達時に設定するフラグ', 'novel-game-plugin' ); ?></h4>
                    <p class="description"><?php esc_html_e( 'このシーンに到達した時に自動的に設定されるフラグを選択してください。', 'novel-game-plugin' ); ?></p>
                    
                    <?php if ( ! empty( $current_flag_master ) ) : ?>
                        <div class="noveltool-scene-flags">
                            <?php foreach ( $current_flag_master as $flag ) : ?>
                                <label class="noveltool-flag-checkbox">
                                    <input type="checkbox" 
                                           name="scene_arrival_flags[]" 
                                           value="<?php echo esc_attr( $flag['name'] ); ?>"
                                           <?php checked( in_array( $flag['name'], $scene_arrival_flags, true ) ); ?> />
                                    <code><?php echo esc_html( $flag['name'] ); ?></code>
                                    <?php if ( $flag['description'] ) : ?>
                                        <span class="flag-description">（<?php echo esc_html( $flag['description'] ); ?>）</span>
                                    <?php endif; ?>
                                </label><br>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="no-flags-message">
                            <p><?php esc_html_e( 'このゲームにはまだフラグが設定されていません。', 'novel-game-plugin' ); ?>
                               <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) ); ?>" target="_blank">
                                   <?php esc_html_e( 'ゲーム基本情報でフラグを管理', 'novel-game-plugin' ); ?>
                               </a>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <h4 style="margin-top: 20px;"><?php esc_html_e( 'セリフレベルのフラグ条件', 'novel-game-plugin' ); ?></h4>
                    <p class="description"><?php esc_html_e( '各セリフに個別のフラグ条件を設定する場合は、セリフ編集時に設定してください。フラグ条件を満たさない場合、そのセリフは表示されません。', 'novel-game-plugin' ); ?></p>
                    
                    <div id="noveltool-dialogue-flags-info">
                        <p><em><?php esc_html_e( 'セリフのフラグ条件は、上記の「セリフ」セクションで各セリフを編集する際に設定できます。', 'novel-game-plugin' ); ?></em></p>
                    </div>
                </div>
                
                <style>
                .noveltool-flags-container {
                    border: 1px solid #ddd;
                    padding: 15px;
                    border-radius: 4px;
                    background: #f9f9f9;
                }
                
                .noveltool-scene-flags {
                    margin: 10px 0;
                }
                
                .noveltool-flag-checkbox {
                    display: block;
                    margin-bottom: 8px;
                    padding: 5px 0;
                }
                
                .noveltool-flag-checkbox input[type="checkbox"] {
                    margin-right: 8px;
                }
                
                .noveltool-flag-checkbox code {
                    font-weight: bold;
                    background: #e8e8e8;
                    padding: 2px 6px;
                    border-radius: 3px;
                }
                
                .flag-description {
                    color: #666;
                    margin-left: 8px;
                }
                
                .no-flags-message {
                    padding: 10px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                }
                </style>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * メタボックスのデータを保存
 *
 * @param int $post_id 投稿ID
 * @since 1.0.0
 */
function noveltool_save_meta_box_data( $post_id ) {
    // 自動保存の場合は処理しない
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // 権限チェック
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // nonceチェック
    if ( ! isset( $_POST['novel_game_meta_box_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['novel_game_meta_box_nonce'] ) ), 'novel_game_meta_box' ) ) {
        return;
    }

    // データの保存
    $fields = array(
        'background_image' => '_background_image',
        'character_image'  => '_character_image',
        'character_left'   => '_character_left',
        'character_center' => '_character_center',
        'character_right'  => '_character_right',
        'character_left_name'   => '_character_left_name',
        'character_center_name' => '_character_center_name',
        'character_right_name'  => '_character_right_name',
        'dialogue_text'    => '_dialogue_text',
        'choices'          => '_choices',
        'game_title'       => '_game_title',
    );
    
    // セリフ背景データの保存
    if ( isset( $_POST['dialogue_backgrounds'] ) ) {
        $dialogue_backgrounds = wp_unslash( $_POST['dialogue_backgrounds'] );
        
        // JSON文字列の場合は妥当性をチェックして保存
        if ( is_string( $dialogue_backgrounds ) ) {
            $decoded = json_decode( $dialogue_backgrounds, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                // 配列の各要素をサニタイズ
                $sanitized_backgrounds = array_map( 'sanitize_text_field', $decoded );
                update_post_meta( $post_id, '_dialogue_backgrounds', $sanitized_backgrounds );
            }
        } elseif ( is_array( $dialogue_backgrounds ) ) {
            // 配列の場合は各要素をサニタイズ
            $sanitized_backgrounds = array_map( 'sanitize_text_field', $dialogue_backgrounds );
            update_post_meta( $post_id, '_dialogue_backgrounds', $sanitized_backgrounds );
        }
    }
    
    // セリフ話者データの保存
    if ( isset( $_POST['dialogue_speakers'] ) ) {
        $dialogue_speakers = wp_unslash( $_POST['dialogue_speakers'] );
        
        // JSON文字列の場合は妥当性をチェックして保存
        if ( is_string( $dialogue_speakers ) ) {
            $decoded = json_decode( $dialogue_speakers, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                // 配列の各要素をサニタイズ
                $sanitized_speakers = array_map( 'sanitize_text_field', $decoded );
                update_post_meta( $post_id, '_dialogue_speakers', $sanitized_speakers );
            }
        } elseif ( is_array( $dialogue_speakers ) ) {
            // 配列の場合は各要素をサニタイズ
            $sanitized_speakers = array_map( 'sanitize_text_field', $dialogue_speakers );
            update_post_meta( $post_id, '_dialogue_speakers', $sanitized_speakers );
        }
    }
    
    // セリフテキストデータの保存（新しいJSON形式）
    if ( isset( $_POST['dialogue_texts'] ) ) {
        $dialogue_texts = wp_unslash( $_POST['dialogue_texts'] );
        
        // JSON文字列の場合は妥当性をチェックして保存
        if ( is_string( $dialogue_texts ) ) {
            $decoded = json_decode( $dialogue_texts, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                // 配列の各要素をサニタイズ（改行を保持するためsanitize_textarea_fieldを使用）
                $sanitized_texts = array_map( 'sanitize_textarea_field', $decoded );
                update_post_meta( $post_id, '_dialogue_texts', $sanitized_texts );
            }
        } elseif ( is_array( $dialogue_texts ) ) {
            // 配列の場合は各要素をサニタイズ（改行を保持するためsanitize_textarea_fieldを使用）
            $sanitized_texts = array_map( 'sanitize_textarea_field', $dialogue_texts );
            update_post_meta( $post_id, '_dialogue_texts', $sanitized_texts );
        }
    }

    // エンディング設定の保存処理
    if ( isset( $_POST['is_ending'] ) && '1' === $_POST['is_ending'] ) {
        // チェックされている場合はtrueで保存
        update_post_meta( $post_id, '_is_ending', true );
    } else {
        // チェックされていない場合はメタデータを削除
        delete_post_meta( $post_id, '_is_ending' );
    }

    // エンディングテキストの保存処理
    if ( isset( $_POST['ending_text'] ) ) {
        $ending_text = sanitize_text_field( wp_unslash( $_POST['ending_text'] ) );
        if ( ! empty( $ending_text ) ) {
            update_post_meta( $post_id, '_ending_text', $ending_text );
        } else {
            delete_post_meta( $post_id, '_ending_text' );
        }
    }

    // シーン到達時フラグの保存処理
    if ( isset( $_POST['scene_arrival_flags'] ) && is_array( $_POST['scene_arrival_flags'] ) ) {
        $scene_arrival_flags = array_map( 'sanitize_text_field', wp_unslash( $_POST['scene_arrival_flags'] ) );
        update_post_meta( $post_id, '_scene_arrival_flags', $scene_arrival_flags );
    } else {
        delete_post_meta( $post_id, '_scene_arrival_flags' );
    }

    // セリフフラグ条件データの保存処理
    if ( isset( $_POST['dialogue_flag_conditions'] ) ) {
        $dialogue_flag_conditions = wp_unslash( $_POST['dialogue_flag_conditions'] );
        
        // JSON文字列の場合は妥当性をチェックして保存
        if ( is_string( $dialogue_flag_conditions ) ) {
            $decoded = json_decode( $dialogue_flag_conditions, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                // 配列の各要素をサニタイズ
                $sanitized_flag_conditions = array();
                foreach ( $decoded as $flag_condition ) {
                    if ( is_array( $flag_condition ) ) {
                        $sanitized_item = array();
                        
                        // conditionsの処理
                        if ( isset( $flag_condition['conditions'] ) && is_array( $flag_condition['conditions'] ) ) {
                            $sanitized_conditions = array();
                            foreach ( $flag_condition['conditions'] as $condition ) {
                                if ( is_array( $condition ) && isset( $condition['name'] ) ) {
                                    $sanitized_conditions[] = array(
                                        'name' => sanitize_text_field( $condition['name'] ),
                                        'state' => isset( $condition['state'] ) ? (bool) $condition['state'] : true
                                    );
                                }
                            }
                            $sanitized_item['conditions'] = $sanitized_conditions;
                        }
                        
                        // logicの処理
                        if ( isset( $flag_condition['logic'] ) ) {
                            $sanitized_item['logic'] = sanitize_text_field( $flag_condition['logic'] );
                        }
                        
                        // displayModeの処理
                        if ( isset( $flag_condition['displayMode'] ) ) {
                            $sanitized_item['displayMode'] = sanitize_text_field( $flag_condition['displayMode'] );
                        }
                        
                        $sanitized_flag_conditions[] = $sanitized_item;
                    }
                }
                update_post_meta( $post_id, '_dialogue_flag_conditions', $sanitized_flag_conditions );
            }
        } elseif ( is_array( $dialogue_flag_conditions ) ) {
            // 配列の場合は直接サニタイズ
            $sanitized_flag_conditions = array();
            foreach ( $dialogue_flag_conditions as $flag_condition ) {
                if ( is_array( $flag_condition ) ) {
                    $sanitized_item = array();
                    
                    if ( isset( $flag_condition['conditions'] ) && is_array( $flag_condition['conditions'] ) ) {
                        $sanitized_conditions = array();
                        foreach ( $flag_condition['conditions'] as $condition ) {
                            if ( is_array( $condition ) && isset( $condition['name'] ) ) {
                                $sanitized_conditions[] = array(
                                    'name' => sanitize_text_field( $condition['name'] ),
                                    'state' => isset( $condition['state'] ) ? (bool) $condition['state'] : true
                                );
                            }
                        }
                        $sanitized_item['conditions'] = $sanitized_conditions;
                    }
                    
                    if ( isset( $flag_condition['logic'] ) ) {
                        $sanitized_item['logic'] = sanitize_text_field( $flag_condition['logic'] );
                    }
                    
                    if ( isset( $flag_condition['displayMode'] ) ) {
                        $sanitized_item['displayMode'] = sanitize_text_field( $flag_condition['displayMode'] );
                    }
                    
                    $sanitized_flag_conditions[] = $sanitized_item;
                }
            }
            update_post_meta( $post_id, '_dialogue_flag_conditions', $sanitized_flag_conditions );
        }
    }

    // 選択肢フラグデータの保存処理
    if ( isset( $_POST['choices'] ) ) {
        $choices_data = wp_unslash( $_POST['choices'] );
        
        // JSON文字列の場合は妥当性をチェックして保存
        if ( is_string( $choices_data ) ) {
            $decoded_choices = json_decode( $choices_data, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_choices ) ) {
                // JSON形式の場合：フラグ関連データを抽出してサニタイズ
                $sanitized_choices = array();
                foreach ( $decoded_choices as $choice_data ) {
                    if ( is_array( $choice_data ) ) {
                        $sanitized_choice = array();
                        
                        // 基本データのサニタイズ
                        if ( isset( $choice_data['text'] ) ) {
                            $sanitized_choice['text'] = sanitize_text_field( $choice_data['text'] );
                        }
                        if ( isset( $choice_data['next'] ) ) {
                            $sanitized_choice['next'] = intval( $choice_data['next'] );
                        }
                        
                        // フラグ条件データのサニタイズ
                        if ( isset( $choice_data['flagConditions'] ) && is_array( $choice_data['flagConditions'] ) ) {
                            $sanitized_conditions = array();
                            foreach ( $choice_data['flagConditions'] as $condition ) {
                                if ( is_array( $condition ) && isset( $condition['name'] ) ) {
                                    $sanitized_conditions[] = array(
                                        'name' => sanitize_text_field( $condition['name'] ),
                                        'state' => isset( $condition['state'] ) ? (bool) $condition['state'] : true
                                    );
                                }
                            }
                            $sanitized_choice['flagConditions'] = $sanitized_conditions;
                        }
                        
                        // フラグ条件ロジックのサニタイズ
                        if ( isset( $choice_data['flagConditionLogic'] ) ) {
                            $sanitized_choice['flagConditionLogic'] = sanitize_text_field( $choice_data['flagConditionLogic'] );
                        }
                        
                        // 設定フラグデータのサニタイズ（新旧両形式対応）
                        if ( isset( $choice_data['setFlags'] ) && is_array( $choice_data['setFlags'] ) ) {
                            $sanitized_set_flags = array();
                            foreach ( $choice_data['setFlags'] as $flag_data ) {
                                if ( is_string( $flag_data ) ) {
                                    // 旧形式（文字列）: 空文字列を除外
                                    $trimmed_flag = trim( sanitize_text_field( $flag_data ) );
                                    if ( $trimmed_flag !== '' ) {
                                        $sanitized_set_flags[] = $trimmed_flag;
                                    }
                                } elseif ( is_array( $flag_data ) && isset( $flag_data['name'] ) ) {
                                    // 新形式（オブジェクト）: nameが空でない場合のみ保存
                                    $trimmed_name = trim( sanitize_text_field( $flag_data['name'] ) );
                                    if ( $trimmed_name !== '' ) {
                                        $sanitized_flag_obj = array(
                                            'name' => $trimmed_name,
                                            'state' => isset( $flag_data['state'] ) ? (bool) $flag_data['state'] : true
                                        );
                                        $sanitized_set_flags[] = $sanitized_flag_obj;
                                    }
                                }
                            }
                            $sanitized_choice['setFlags'] = $sanitized_set_flags;
                        }
                        
                        $sanitized_choices[] = $sanitized_choice;
                    }
                }
                
                // サニタイズ済みデータをJSON形式で保存
                update_post_meta( $post_id, '_choices', wp_json_encode( $sanitized_choices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
            } else {
                // JSON形式でない場合は従来通りの処理
                $value = sanitize_textarea_field( $choices_data );
                update_post_meta( $post_id, '_choices', $value );
            }
        } elseif ( is_array( $choices_data ) ) {
            // 配列の場合は直接サニタイズ（通常は発生しない）
            $value = sanitize_textarea_field( wp_json_encode( $choices_data ) );
            update_post_meta( $post_id, '_choices', $value );
        }
    }

    foreach ( $fields as $field => $meta_key ) {
        if ( isset( $_POST[ $field ] ) ) {
            $value = wp_unslash( $_POST[ $field ] );

            // choicesフィールドは上で専用処理済みなのでスキップ
            if ( $field === 'choices' ) {
                continue;
            }

            // サニタイズ処理
            if ( in_array( $field, array( 'dialogue_text' ), true ) ) {
                $value = sanitize_textarea_field( $value );
            } else {
                $value = sanitize_text_field( $value );
            }

            update_post_meta( $post_id, $meta_key, $value );
        }
    }
}
add_action( 'save_post', 'noveltool_save_meta_box_data' );

/**
 * カスタムメタフィールドをリビジョンに追加
 *
 * @param array $fields リビジョンフィールド
 * @return array 修正されたリビジョンフィールド
 * @since 1.2.0
 */
function noveltool_add_revision_fields( $fields ) {
    // リビジョン対応するカスタムメタフィールドを追加
    $fields['_background_image']         = __( '背景画像', 'novel-game-plugin' );
    $fields['_character_image']          = __( 'キャラクター画像', 'novel-game-plugin' );
    $fields['_character_left']           = __( '左キャラクター画像', 'novel-game-plugin' );
    $fields['_character_center']         = __( '中央キャラクター画像', 'novel-game-plugin' );
    $fields['_character_right']          = __( '右キャラクター画像', 'novel-game-plugin' );
    $fields['_character_left_name']      = __( '左キャラクター名', 'novel-game-plugin' );
    $fields['_character_center_name']    = __( '中央キャラクター名', 'novel-game-plugin' );
    $fields['_character_right_name']     = __( '右キャラクター名', 'novel-game-plugin' );
    $fields['_dialogue_text']            = __( 'セリフテキスト', 'novel-game-plugin' );
    $fields['_dialogue_texts']           = __( 'セリフテキスト（JSON）', 'novel-game-plugin' );
    $fields['_dialogue_speakers']        = __( 'セリフ話者', 'novel-game-plugin' );
    $fields['_dialogue_backgrounds']     = __( 'セリフ背景', 'novel-game-plugin' );
    $fields['_dialogue_flag_conditions'] = __( 'セリフフラグ条件', 'novel-game-plugin' );
    $fields['_choices']                  = __( '選択肢', 'novel-game-plugin' );
    $fields['_game_title']               = __( 'ゲームタイトル', 'novel-game-plugin' );
    $fields['_is_ending']                = __( 'エンディング', 'novel-game-plugin' );
    $fields['_ending_text']              = __( 'エンディングテキスト', 'novel-game-plugin' );
    $fields['_scene_arrival_flags']      = __( 'シーン到達時フラグ', 'novel-game-plugin' );
    
    return $fields;
}
add_filter( '_wp_post_revision_fields', 'noveltool_add_revision_fields' );

/**
 * 配列形式カスタムメタのリビジョン表示処理（セリフテキスト）
 *
 * @param mixed   $value 表示する値
 * @param string  $field フィールド名
 * @param WP_Post $post  投稿オブジェクト
 * @return string 表示用文字列
 * @since 1.2.1
 */
function noveltool_display_revision_field_dialogue_texts( $value, $field, $post ) {
    if ( is_array( $value ) || is_object( $value ) ) {
        return wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
    }
    return $value;
}

/**
 * 配列形式カスタムメタのリビジョン表示処理（選択肢）
 *
 * @param mixed   $value 表示する値
 * @param string  $field フィールド名
 * @param WP_Post $post  投稿オブジェクト
 * @return string 表示用文字列
 * @since 1.2.1
 */
function noveltool_display_revision_field_choices( $value, $field, $post ) {
    if ( is_array( $value ) ) {
        $formatted = array();
        foreach ( $value as $choice ) {
            if ( is_array( $choice ) && isset( $choice['text'] ) ) {
                $formatted[] = $choice['text'];
            }
        }
        return implode( ', ', $formatted );
    }
    return $value;
}

/**
 * 配列形式カスタムメタのリビジョン表示処理（汎用配列）
 *
 * @param mixed   $value 表示する値
 * @param string  $field フィールド名
 * @param WP_Post $post  投稿オブジェクト
 * @return string 表示用文字列
 * @since 1.2.1
 */
function noveltool_display_revision_field_array( $value, $field, $post ) {
    if ( is_array( $value ) ) {
        return implode( ', ', $value );
    }
    return $value;
}

// 配列フィールド用フィルター登録
add_filter( '_wp_post_revision_field__dialogue_texts', 'noveltool_display_revision_field_dialogue_texts', 10, 3 );
add_filter( '_wp_post_revision_field__choices', 'noveltool_display_revision_field_choices', 10, 3 );
add_filter( '_wp_post_revision_field__dialogue_speakers', 'noveltool_display_revision_field_array', 10, 3 );
add_filter( '_wp_post_revision_field__dialogue_backgrounds', 'noveltool_display_revision_field_array', 10, 3 );
add_filter( '_wp_post_revision_field__dialogue_flag_conditions', 'noveltool_display_revision_field_array', 10, 3 );
add_filter( '_wp_post_revision_field__scene_arrival_flags', 'noveltool_display_revision_field_array', 10, 3 );

/**
 * 文字列フィールド用の安全な表示処理
 *
 * @param mixed   $value 表示する値
 * @param string  $field フィールド名
 * @param WP_Post $post  投稿オブジェクト
 * @return string 表示用文字列
 * @since 1.2.1
 */
function noveltool_display_revision_field_safe_string( $value, $field, $post ) {
    if ( is_array( $value ) || is_object( $value ) ) {
        return wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
    }
    return (string) $value;
}

/**
 * 真偽値フィールド用の表示処理
 *
 * @param mixed   $value 表示する値
 * @param string  $field フィールド名
 * @param WP_Post $post  投稿オブジェクト
 * @return string 表示用文字列
 * @since 1.2.1
 */
function noveltool_display_revision_field_boolean( $value, $field, $post ) {
    if ( is_array( $value ) || is_object( $value ) ) {
        return wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
    }
    return $value ? __( 'はい', 'novel-game-plugin' ) : __( 'いいえ', 'novel-game-plugin' );
}

// 文字列フィールド用フィルター登録
add_filter( '_wp_post_revision_field__background_image', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__character_image', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__character_left', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__character_center', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__character_right', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__character_left_name', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__character_center_name', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__character_right_name', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__dialogue_text', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__game_title', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__ending_text', 'noveltool_display_revision_field_safe_string', 10, 3 );
add_filter( '_wp_post_revision_field__is_ending', 'noveltool_display_revision_field_boolean', 10, 3 );

/**
 * カスタムメタフィールドをリビジョンに保存
 *
 * @param int $revision_id リビジョンID
 * @since 1.2.0
 */
function noveltool_save_revision_meta( $revision_id ) {
    $parent_id = wp_is_post_revision( $revision_id );
    
    if ( ! $parent_id ) {
        return;
    }
    
    // 投稿タイプをチェック
    $parent = get_post( $parent_id );
    if ( ! $parent || 'novel_game' !== $parent->post_type ) {
        return;
    }
    
    // リビジョンに保存するカスタムメタフィールドのリスト
    $meta_keys = array(
        '_background_image',
        '_character_image',
        '_character_left',
        '_character_center',
        '_character_right',
        '_character_left_name',
        '_character_center_name',
        '_character_right_name',
        '_dialogue_text',
        '_dialogue_texts',
        '_dialogue_speakers',
        '_dialogue_backgrounds',
        '_dialogue_flag_conditions',
        '_choices',
        '_game_title',
        '_is_ending',
        '_ending_text',
        '_scene_arrival_flags',
    );
    
    // 各メタフィールドをリビジョンにコピー
    foreach ( $meta_keys as $meta_key ) {
        $meta_value = get_post_meta( $parent_id, $meta_key, true );
        
        if ( false !== $meta_value ) {
            // 配列・オブジェクトは必ず文字列化してからメタデータに保存
            if ( is_array( $meta_value ) || is_object( $meta_value ) ) {
                $meta_value = serialize( $meta_value );
            } else {
                // 文字列データも安全性のため文字列型にキャスト
                $meta_value = (string) $meta_value;
            }
            update_metadata( 'post', $revision_id, $meta_key, $meta_value );
        }
    }
}
add_action( 'save_post', 'noveltool_save_revision_meta' );

/**
 * リビジョン復元時にカスタムメタフィールドを復元
 *
 * @param int $post_id     投稿ID
 * @param int $revision_id リビジョンID
 * @since 1.2.0
 */
function noveltool_restore_revision_meta( $post_id, $revision_id ) {
    // 投稿タイプの確認
    if ( get_post_type( $post_id ) !== 'novel_game' ) {
        return;
    }
    
    // 権限チェック
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    // リビジョンの存在確認
    $revision = get_post( $revision_id );
    if ( ! $revision || 'revision' !== $revision->post_type ) {
        return;
    }
    
    // WordPressの自動リビジョン作成を一時無効化（復元処理中の再帰防止）
    add_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );
    
    // デバッグログ出力
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log(
            sprintf(
                'Novel Game: リビジョン復元実行 - 投稿ID: %d, リビジョンID: %d',
                $post_id,
                $revision_id
            )
        );
    }
    
    // リビジョンから復元するカスタムメタフィールドのリスト
    $meta_keys = array(
        '_background_image',
        '_character_image',
        '_character_left',
        '_character_center',
        '_character_right',
        '_character_left_name',
        '_character_center_name',
        '_character_right_name',
        '_dialogue_text',
        '_dialogue_texts',
        '_dialogue_speakers',
        '_dialogue_backgrounds',
        '_dialogue_flag_conditions',
        '_choices',
        '_game_title',
        '_is_ending',
        '_ending_text',
        '_scene_arrival_flags',
    );
    
    // 各メタフィールドをリビジョンから復元
    foreach ( $meta_keys as $meta_key ) {
        $meta_value = get_metadata( 'post', $revision_id, $meta_key, true );
        
        // メタデータが存在し、空文字列でない場合は更新
        if ( false !== $meta_value && '' !== $meta_value ) {
            // シリアライズされたデータを適切に復元
            $meta_value = maybe_unserialize( $meta_value );
            
            // データ型の最終検証
            if ( is_string( $meta_value ) && $meta_value === '' ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                update_post_meta( $post_id, $meta_key, $meta_value );
            }
        } else {
            // リビジョンにメタが存在しない、または空の場合は削除
            delete_post_meta( $post_id, $meta_key );
        }
    }
    
    // リビジョンチェックフィルターを削除
    remove_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );
}
add_action( 'wp_restore_post_revision', 'noveltool_restore_revision_meta', 10, 2 );

/**
 * カスタムメタ変更時にpost_excerptを更新してリビジョン作成を強制
 *
 * WordPressは post_title、post_content、post_excerpt の変更時のみリビジョンを作成するため、
 * カスタムメタフィールドのみの変更時にもリビジョンが作成されるよう、
 * post_excerpt を自動更新する。
 *
 * @param int $post_id 投稿ID
 * @since 1.2.1
 */
function noveltool_create_revision_on_meta_change( $post_id ) {
    // 基本チェック
    if ( get_post_type( $post_id ) !== 'novel_game' ) {
        return;
    }
    
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    // nonceチェック
    if ( ! isset( $_POST['novel_game_meta_box_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['novel_game_meta_box_nonce'] ) ), 'novel_game_meta_box' ) ) {
        return;
    }
    
    // 無限ループ防止
    static $processing_post_ids = array();
    if ( isset( $processing_post_ids[ $post_id ] ) ) {
        return;
    }
    
    $processing_post_ids[ $post_id ] = true;
    
    // メタ変更検出用ハッシュ生成
    // 定義済みフィールドのみを対象にしてスコープを限定
    $tracked_meta_keys = array(
        '_background_image',
        '_character_image',
        '_character_left',
        '_character_center',
        '_character_right',
        '_character_left_name',
        '_character_center_name',
        '_character_right_name',
        '_dialogue_text',
        '_dialogue_texts',
        '_dialogue_speakers',
        '_dialogue_backgrounds',
        '_dialogue_flag_conditions',
        '_choices',
        '_game_title',
        '_is_ending',
        '_ending_text',
        '_scene_arrival_flags',
    );
    
    $meta_data = array();
    foreach ( $tracked_meta_keys as $meta_key ) {
        $value = get_post_meta( $post_id, $meta_key, true );
        if ( $value !== '' && $value !== false ) {
            $meta_data[ $meta_key ] = $value;
        }
    }
    
    $timestamp = current_time( 'Y-m-d H:i:s' );
    $meta_hash = substr( md5( serialize( $meta_data ) ), 0, 12 );
    
    $new_excerpt = sprintf(
        'Novel meta updated: %s [%s]',
        $timestamp,
        $meta_hash
    );
    
    // save_post フックを一時削除して wp_update_post 実行
    remove_action( 'save_post', 'noveltool_create_revision_on_meta_change', 20 );
    remove_action( 'save_post', 'noveltool_save_meta_box_data' );
    remove_action( 'save_post', 'noveltool_save_revision_meta' );
    
    // WordPressの自動リビジョン作成を一時無効化
    add_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );
    
    $result = wp_update_post( array(
        'ID' => $post_id,
        'post_excerpt' => $new_excerpt
    ) );
    
    // リビジョンチェックフィルターを削除
    remove_filter( 'wp_save_post_revision_check_for_changes', '__return_false' );
    
    if ( is_wp_error( $result ) || $result === 0 ) {
        error_log( 'Novel Game Plugin: Failed to update post excerpt for revision creation. Post ID: ' . $post_id );
        
        // フック復元
        add_action( 'save_post', 'noveltool_save_meta_box_data' );
        add_action( 'save_post', 'noveltool_save_revision_meta' );
        add_action( 'save_post', 'noveltool_create_revision_on_meta_change', 20 );
        
        unset( $processing_post_ids[ $post_id ] );
        return;
    }
    
    // フック復元
    add_action( 'save_post', 'noveltool_save_meta_box_data' );
    add_action( 'save_post', 'noveltool_save_revision_meta' );
    add_action( 'save_post', 'noveltool_create_revision_on_meta_change', 20 );
    
    unset( $processing_post_ids[ $post_id ] );
}
add_action( 'save_post', 'noveltool_create_revision_on_meta_change', 20 );

/**
 * RSSフィードからpost_excerptを除外
 *
 * @param string $excerpt 抜粋テキスト
 * @return string フィルタ後の抜粋テキスト
 * @since 1.2.1
 */
function noveltool_filter_excerpt_rss( $excerpt ) {
    if ( get_post_type() === 'novel_game' ) {
        return '';
    }
    return $excerpt;
}
add_filter( 'the_excerpt_rss', 'noveltool_filter_excerpt_rss' );

/**
 * フロントエンドでのpost_excerpt表示を制御
 *
 * @param string  $excerpt 抜粋テキスト
 * @param WP_Post $post    投稿オブジェクト
 * @return string フィルタ後の抜粋テキスト
 * @since 1.2.1
 */
function noveltool_filter_excerpt_display( $excerpt, $post ) {
    if ( $post && $post->post_type === 'novel_game' ) {
        return '';
    }
    return $excerpt;
}
add_filter( 'get_the_excerpt', 'noveltool_filter_excerpt_display', 10, 2 );

