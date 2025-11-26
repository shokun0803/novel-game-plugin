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
        wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'novel-game-plugin' ) ) );
    }

    // nonceチェック
    if ( ! isset( $_POST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'novel_game_meta_box_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'novel-game-plugin' ) ) );
    }

    // タイトルの取得とサニタイズ
    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    
    // 自動遷移フラグの取得
    $auto_redirect = isset( $_POST['auto_redirect'] ) ? filter_var( wp_unslash( $_POST['auto_redirect'] ), FILTER_VALIDATE_BOOLEAN ) : false;
    
    // 現在の投稿IDの取得（ゲームタイトルを継承するため）
    $current_post_id = isset( $_POST['current_post_id'] ) ? intval( wp_unslash( $_POST['current_post_id'] ) ) : 0;

    if ( empty( $title ) ) {
        wp_send_json_error( array( 'message' => __( 'Title is not entered.', 'novel-game-plugin' ) ) );
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
        wp_send_json_error( array( 'message' => __( 'Failed to create post.', 'novel-game-plugin' ) ) );
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

    // get_current_screen() の null チェック
    $current_screen = get_current_screen();
    if ( ! $current_screen ) {
        return;
    }

    // 投稿オブジェクトの存在チェック
    if ( ! $post || ! isset( $post->ID ) || ! isset( $post->post_type ) ) {
        return;
    }

    // novel_game投稿タイプでのみ実行
    if ( 'novel_game' !== $post->post_type ) {
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
        __( 'Scene Data', 'novel-game-plugin' ),
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
    
    // URLパラメータからゲームタイトルを取得（新規作成時）
    if ( empty( $game_title ) && isset( $_GET['game_title'] ) ) {
        $game_title = sanitize_text_field( wp_unslash( $_GET['game_title'] ) );
    }
    
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
    
    // セリフごとのキャラクター設定データの取得
    $dialogue_characters = get_post_meta( $post->ID, '_dialogue_characters', true );
    if ( is_string( $dialogue_characters ) ) {
        $dialogue_characters = json_decode( $dialogue_characters, true );
    }
    if ( ! is_array( $dialogue_characters ) ) {
        $dialogue_characters = array();
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
    
    // JSONベースのデータが存在する場合に使用
    if ( ! empty( $dialogue_speakers_array ) || ! empty( $dialogue_backgrounds ) || ! empty( $dialogue_texts_array ) ) {
        if ( is_string( $dialogue_backgrounds ) ) {
            $dialogue_backgrounds_array = json_decode( $dialogue_backgrounds, true );
        } elseif ( is_array( $dialogue_backgrounds ) ) {
            $dialogue_backgrounds_array = $dialogue_backgrounds;
        } else {
            $dialogue_backgrounds_array = array();
        }
        
        $max_count = max(
            count( (array) $dialogue_speakers_array ),
            count( (array) $dialogue_backgrounds_array ),
            count( (array) $dialogue_texts_array )
        );
        if ( $max_count > 0 ) {
            for ( $i = 0; $i < $max_count; $i++ ) {
                $dialogue_lines[] = isset( $dialogue_texts_array[ $i ] ) ? $dialogue_texts_array[ $i ] : '';
            }
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

    // JavaScript用の翻訳文字列（HTML挿入用にエスケープ済み）
    $js_strings = array(
        'selectOption'             => esc_html__( '-- Select --', 'novel-game-plugin' ),
        'createNew'                => esc_html__( '+ Create New...', 'novel-game-plugin' ),
        'remove'                   => esc_html__( 'Remove', 'novel-game-plugin' ),
        'selectImage'              => esc_html__( 'Select Image', 'novel-game-plugin' ),
        'useThisImage'             => esc_html__( 'Use This Image', 'novel-game-plugin' ),
        'confirmDelete'            => esc_html__( 'Are you sure you want to delete?', 'novel-game-plugin' ),
        'createFailed'             => esc_html__( 'Creation failed.', 'novel-game-plugin' ),
        'selectTitle'              => esc_html__( 'Please enter a title for the new scene', 'novel-game-plugin' ),
        'selectNextTitle'          => esc_html__( 'Please enter a title for the next command', 'novel-game-plugin' ),
        'redirectingMessage'       => esc_html__( 'Creating...', 'novel-game-plugin' ),
        'dialoguePlaceholder'      => esc_attr__( 'Please enter dialogue', 'novel-game-plugin' ),
        'speaker'                  => esc_html__( 'Speaker:', 'novel-game-plugin' ),
        'selectSpeaker'            => esc_html__( '-- Select Speaker --', 'novel-game-plugin' ),
        'leftCharacter'            => esc_html__( 'Left Character', 'novel-game-plugin' ),
        'centerCharacter'          => esc_html__( 'Center Character', 'novel-game-plugin' ),
        'rightCharacter'           => esc_html__( 'Right Character', 'novel-game-plugin' ),
        'narrator'                 => esc_html__( 'Narrator', 'novel-game-plugin' ),
        'backgroundImage'          => esc_html__( 'Background Image:', 'novel-game-plugin' ),
        'selectBackgroundImage'    => esc_html__( 'Select Background Image', 'novel-game-plugin' ),
        'flagControl'              => esc_html__( 'Flag Control:', 'novel-game-plugin' ),
        'displayControl'           => esc_html__( 'Display Control:', 'novel-game-plugin' ),
        'normalDisplay'            => esc_html__( 'Always Display', 'novel-game-plugin' ),
        'hiddenByCondition'        => esc_html__( 'Hide When Condition Met', 'novel-game-plugin' ),
        'alternativeByCondition'   => esc_html__( 'Show Alternative Text When Condition Met', 'novel-game-plugin' ),
        'selectFlag'               => esc_html__( '-- Select Flag --', 'novel-game-plugin' ),
        'flagLabel'                => esc_html__( 'Flag', 'novel-game-plugin' ),
        'condition'                => esc_html__( 'Condition:', 'novel-game-plugin' ),
        'conditionAnd'             => esc_html__( 'AND (All)', 'novel-game-plugin' ),
        'conditionOr'              => esc_html__( 'OR (Any)', 'novel-game-plugin' ),
        'alternativeTextLabel'     => esc_html__( 'Alternative Text (displayed when condition is met):', 'novel-game-plugin' ),
        'alternativeTextPlaceholder' => esc_attr__( 'Enter the dialogue to display when the flag condition is met', 'novel-game-plugin' ),
        'dialogue'                 => esc_html__( 'Dialogue', 'novel-game-plugin' ),
        'minDialogueRequired'      => esc_html__( 'At least one dialogue is required.', 'novel-game-plugin' ),
        'confirmDeleteDialogue'    => esc_html__( 'Are you sure you want to delete this dialogue?', 'novel-game-plugin' ),
        'mediaLibraryUnavailable'  => esc_html__( 'Media library is not available.', 'novel-game-plugin' ),
        'selectCharacterImage'     => esc_html__( 'Select Character Image', 'novel-game-plugin' ),
        'noFlags'                  => esc_html__( 'No Flags', 'novel-game-plugin' ),
        'flagSettingChange'        => esc_html__( 'Flag Setting Change:', 'novel-game-plugin' ),
        'doNotSet'                 => esc_html__( 'Do Not Set', 'novel-game-plugin' ),
        'defaultDoNotSet'          => esc_html__( 'Default is "Do Not Set"', 'novel-game-plugin' ),
        'deleteTarget'             => esc_html__( 'Delete Target:', 'novel-game-plugin' ),
        'lastChoiceWarning'        => esc_html__( 'Warning: This is the last choice. Deleting it will remove all choices.', 'novel-game-plugin' ),
        'allChoicesDeleted'        => esc_html__( 'All choices have been deleted.', 'novel-game-plugin' ),
        'unsavedChanges'           => esc_html__( 'There are unsaved changes in the current post.\n\nDo you want to open the edit screen without saving?\n\nSelect "Cancel" to save the current post first.', 'novel-game-plugin' ),
        'postNotSaved'             => esc_html__( 'The post has not been saved. Do you want to save it before creating a new command?', 'novel-game-plugin' ),
        'newSceneCreated'          => esc_html__( 'A new scene "%s" has been created. You can edit it from the edit link.', 'novel-game-plugin' ),
        'edit'                     => esc_html__( 'Edit', 'novel-game-plugin' ),
        'unsupportedFileExtension' => esc_html__( 'Unsupported file format. Only jpg, jpeg, png, gif, and webp files can be uploaded.', 'novel-game-plugin' ),
        'unsupportedMimeType'      => esc_html__( 'Unsupported file format. Only image files can be uploaded.', 'novel-game-plugin' ),
        'fileSizeExceeded'         => esc_html__( 'File size is too large. Please upload a file smaller than 5MB.', 'novel-game-plugin' ),
        // セリフごとのキャラクター設定用文言
        'dialogueCharacterSettings' => esc_html__( 'Character Settings for This Dialogue', 'novel-game-plugin' ),
        'dialogueCharacterHelp'     => esc_html__( 'You can set individual character images for each dialogue. If not set, the scene\'s character settings will be used.', 'novel-game-plugin' ),
        'showCharacterSettings'     => esc_html__( '▼ Show Character Settings', 'novel-game-plugin' ),
        'hideCharacterSettings'     => esc_html__( '▲ Hide Character Settings', 'novel-game-plugin' ),
        'useSceneDefault'           => esc_html__( '(Use Scene Default)', 'novel-game-plugin' ),
        'clearImage'                => esc_html__( 'Clear', 'novel-game-plugin' ),
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
            'dialogue_characters' => $dialogue_characters,
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
    
    /* セリフごとのキャラクター設定 */
    .dialogue-character-container {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px dashed #ccd0d4;
    }
    
    .dialogue-character-toggle {
        font-size: 12px;
        color: #0073aa;
    }
    
    .dialogue-character-content {
        margin-top: 10px;
        padding: 15px;
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .dialogue-character-help {
        margin: 0 0 10px 0;
        font-size: 12px;
    }
    
    .dialogue-character-grid {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .dialogue-character-position {
        flex: 1;
        min-width: 150px;
        background: #fff;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-align: center;
    }
    
    .dialogue-character-label {
        display: block;
        font-weight: bold;
        margin-bottom: 8px;
        font-size: 12px;
        color: #333;
    }
    
    .dialogue-character-preview {
        margin: 5px auto;
        border: 1px solid #ddd;
        border-radius: 3px;
        max-height: 80px;
    }
    
    .dialogue-character-placeholder {
        display: block;
        font-size: 11px;
        color: #888;
        padding: 10px 0;
        font-style: italic;
    }
    
    .dialogue-character-buttons {
        margin-top: 8px;
        display: flex;
        gap: 5px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .dialogue-character-buttons .button {
        font-size: 11px;
        padding: 3px 8px;
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
                <label for="novel_game_title"><?php esc_html_e( 'Game Title', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <?php
                $available_games = noveltool_get_all_games();
                if ( ! empty( $available_games ) ) :
                ?>
                    <select id="novel_game_title" name="game_title" class="regular-text">
                        <option value=""><?php esc_html_e( '-- Please Select a Game --', 'novel-game-plugin' ); ?></option>
                        <?php foreach ( $available_games as $game ) : ?>
                            <option value="<?php echo esc_attr( $game['title'] ); ?>" <?php selected( $game_title, $game['title'] ); ?>>
                                <?php echo esc_html( $game['title'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Please select the game this scene belongs to.', 'novel-game-plugin' ); ?>
                        <a href="<?php echo esc_url( noveltool_get_my_games_url() ); ?>" target="_blank">
                            <?php esc_html_e( 'Manage Games', 'novel-game-plugin' ); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <input type="text"
                           id="novel_game_title"
                           name="game_title"
                           value="<?php echo esc_attr( $game_title ); ?>"
                           class="regular-text"
                           placeholder="<?php esc_attr_e( 'Enter the title of the game this scene belongs to', 'novel-game-plugin' ); ?>" />
                    <p class="description">
                        <?php esc_html_e( 'Set the title for the entire game.', 'novel-game-plugin' ); ?>
                        <a href="<?php echo esc_url( noveltool_get_my_games_url() ); ?>" target="_blank">
                            <?php esc_html_e( 'Manage Games', 'novel-game-plugin' ); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="novel_background_image"><?php esc_html_e( 'Background Image', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <input type="hidden"
                       id="novel_background_image"
                       name="background_image"
                       value="<?php echo esc_attr( $background ); ?>" />
                <img id="novel_background_image_preview"
                     src="<?php echo esc_url( $background ); ?>"
                     alt="<?php esc_attr_e( 'Background Image Preview', 'novel-game-plugin' ); ?>"
                     style="max-width: 300px; height: auto; display: <?php echo $background ? 'block' : 'none'; ?>;" />
                <p>
                    <button type="button"
                            class="button"
                            id="novel_background_image_button">
                        <?php esc_html_e( 'Select from Media', 'novel-game-plugin' ); ?>
                    </button>
                </p>
                <p class="description"><?php esc_html_e( 'Set the background image for this scene.', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php esc_html_e( 'Character Images', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <div class="character-positions-container">
                    <p class="description"><?php esc_html_e( 'You can place up to 3 characters in left, center, and right positions.', 'novel-game-plugin' ); ?></p>
                    
                    <!-- 左キャラクター -->
                    <div class="character-position-item">
                        <h4><?php esc_html_e( 'Left Character', 'novel-game-plugin' ); ?></h4>
                        <input type="hidden"
                               id="novel_character_left"
                               name="character_left"
                               value="<?php echo esc_attr( $character_left ); ?>" />
                        <div class="character-name-input">
                            <label for="novel_character_left_name"><?php esc_html_e( 'Character Name', 'novel-game-plugin' ); ?></label>
                            <input type="text"
                                   id="novel_character_left_name"
                                   name="character_left_name"
                                   value="<?php echo esc_attr( $character_left_name ); ?>"
                                   placeholder="<?php esc_attr_e( 'Enter Character Name', 'novel-game-plugin' ); ?>"
                                   class="regular-text" />
                        </div>
                        <img id="novel_character_left_preview"
                             src="<?php echo esc_url( $character_left ); ?>"
                             alt="<?php esc_attr_e( 'Left Character Image Preview', 'novel-game-plugin' ); ?>"
                             style="max-width: 150px; height: auto; display: <?php echo $character_left ? 'block' : 'none'; ?>;" />
                        <p>
                            <button type="button"
                                    class="button character-image-button"
                                    data-position="left">
                                <?php esc_html_e( 'Select Image', 'novel-game-plugin' ); ?>
                            </button>
                            <button type="button"
                                    class="button character-image-clear"
                                    data-position="left"
                                    style="display: <?php echo $character_left ? 'inline-block' : 'none'; ?>;">
                                <?php esc_html_e( 'Remove', 'novel-game-plugin' ); ?>
                            </button>
                        </p>
                    </div>
                    
                    <!-- 中央キャラクター -->
                    <div class="character-position-item">
                        <h4><?php esc_html_e( 'Center Character', 'novel-game-plugin' ); ?></h4>
                        <input type="hidden"
                               id="novel_character_center"
                               name="character_center"
                               value="<?php echo esc_attr( $character_center ); ?>" />
                        <div class="character-name-input">
                            <label for="novel_character_center_name"><?php esc_html_e( 'Character Name', 'novel-game-plugin' ); ?></label>
                            <input type="text"
                                   id="novel_character_center_name"
                                   name="character_center_name"
                                   value="<?php echo esc_attr( $character_center_name ); ?>"
                                   placeholder="<?php esc_attr_e( 'Enter Character Name', 'novel-game-plugin' ); ?>"
                                   class="regular-text" />
                        </div>
                        <img id="novel_character_center_preview"
                             src="<?php echo esc_url( $character_center ); ?>"
                             alt="<?php esc_attr_e( 'Center Character Image Preview', 'novel-game-plugin' ); ?>"
                             style="max-width: 150px; height: auto; display: <?php echo $character_center ? 'block' : 'none'; ?>;" />
                        <p>
                            <button type="button"
                                    class="button character-image-button"
                                    data-position="center">
                                <?php esc_html_e( 'Select Image', 'novel-game-plugin' ); ?>
                            </button>
                            <button type="button"
                                    class="button character-image-clear"
                                    data-position="center"
                                    style="display: <?php echo $character_center ? 'inline-block' : 'none'; ?>;">
                                <?php esc_html_e( 'Remove', 'novel-game-plugin' ); ?>
                            </button>
                        </p>
                    </div>
                    
                    <!-- 右キャラクター -->
                    <div class="character-position-item">
                        <h4><?php esc_html_e( 'Right Character', 'novel-game-plugin' ); ?></h4>
                        <input type="hidden"
                               id="novel_character_right"
                               name="character_right"
                               value="<?php echo esc_attr( $character_right ); ?>" />
                        <div class="character-name-input">
                            <label for="novel_character_right_name"><?php esc_html_e( 'Character Name', 'novel-game-plugin' ); ?></label>
                            <input type="text"
                                   id="novel_character_right_name"
                                   name="character_right_name"
                                   value="<?php echo esc_attr( $character_right_name ); ?>"
                                   placeholder="<?php esc_attr_e( 'Enter Character Name', 'novel-game-plugin' ); ?>"
                                   class="regular-text" />
                        </div>
                        <img id="novel_character_right_preview"
                             src="<?php echo esc_url( $character_right ); ?>"
                             alt="<?php esc_attr_e( 'Right Character Image Preview', 'novel-game-plugin' ); ?>"
                             style="max-width: 150px; height: auto; display: <?php echo $character_right ? 'block' : 'none'; ?>;" />
                        <p>
                            <button type="button"
                                    class="button character-image-button"
                                    data-position="right">
                                <?php esc_html_e( 'Select Image', 'novel-game-plugin' ); ?>
                            </button>
                            <button type="button"
                                    class="button character-image-clear"
                                    data-position="right"
                                    style="display: <?php echo $character_right ? 'inline-block' : 'none'; ?>;">
                                <?php esc_html_e( 'Remove', 'novel-game-plugin' ); ?>
                            </button>
                        </p>
                    </div>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php esc_html_e( 'Dialogue', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <div id="novel-dialogue-container">
                    <div id="novel-dialogue-list">
                        <!-- セリフ一覧が動的に生成されます -->
                    </div>
                    <p>
                        <button type="button" class="button" id="novel-dialogue-add">
                            <?php esc_html_e( '+ Add Dialogue', 'novel-game-plugin' ); ?>
                        </button>
                    </p>
                </div>
                <p class="description"><?php esc_html_e( 'You can set a background image for each dialogue. If no background image is specified, the background from the previous scene will continue.', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php esc_html_e( 'Choices', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <div id="novel-choices-box">
                    <table id="novel-choices-table" class="widefat">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><?php esc_html_e( 'Order', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Text', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Next Scene', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Flag Conditions', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Flag Settings', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'novel-game-plugin' ); ?></th>
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
                            <?php esc_html_e( 'Add Choice', 'novel-game-plugin' ); ?>
                        </button>
                        <button type="button"
                                class="button button-secondary"
                                id="novel-create-next-command">
                            <?php esc_html_e( 'Create New Next Command', 'novel-game-plugin' ); ?>
                        </button>
                    </p>
                    <input type="hidden"
                           id="novel_choices_hidden"
                           name="choices"
                           value="<?php echo esc_attr( $choices ); ?>" />
                    <p class="description">
                        <?php esc_html_e( 'Set choices that players can select.', 'novel-game-plugin' ); ?><br>
                        <strong><?php esc_html_e( 'Flag Conditions:', 'novel-game-plugin' ); ?></strong><?php esc_html_e( ' Flag conditions required for display (up to 3, AND/OR supported)', 'novel-game-plugin' ); ?><br>
                        <strong><?php esc_html_e( 'Flag Settings:', 'novel-game-plugin' ); ?></strong><?php esc_html_e( ' The flag to set when this choice is selected', 'novel-game-plugin' ); ?>
                    </p>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="novel_is_ending"><?php esc_html_e( 'Ending Settings', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <label for="novel_is_ending">
                    <input type="checkbox"
                           id="novel_is_ending"
                           name="is_ending"
                           value="1"
                           <?php checked( $is_ending ); ?> />
                    <?php esc_html_e( 'Set this scene as an ending (end of game)', 'novel-game-plugin' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'When checked, the game will end at this scene. The ending takes priority even if choices are set.', 'novel-game-plugin' ); ?></p>
                
                <div id="ending_text_setting" style="margin-top: 15px; <?php echo $is_ending ? '' : 'display: none;'; ?>">
                    <label for="novel_ending_text"><?php esc_html_e( 'Ending Screen Text', 'novel-game-plugin' ); ?></label><br>
                    <input type="text" 
                           id="novel_ending_text" 
                           name="ending_text" 
                           value="<?php echo esc_attr( $ending_text ); ?>" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e( 'Text to display on ending screen (Default: END)', 'novel-game-plugin' ); ?>" />
                    <p class="description"><?php esc_html_e( 'Set the text to display on the ending screen. If blank, "END" will be displayed.', 'novel-game-plugin' ); ?></p>
                </div>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label><?php esc_html_e( 'Flag Settings', 'novel-game-plugin' ); ?></label>
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
                    <h4><?php esc_html_e( 'Flags to Set on Scene Arrival', 'novel-game-plugin' ); ?></h4>
                    <p class="description"><?php esc_html_e( 'Select flags that will be automatically set when this scene is reached.', 'novel-game-plugin' ); ?></p>
                    
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
                            <p><?php esc_html_e( 'No flags have been set for this game yet.', 'novel-game-plugin' ); ?>
                               <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-settings' ) ); ?>" target="_blank">
                                   <?php esc_html_e( 'Manage Flags in Game Settings', 'novel-game-plugin' ); ?>
                               </a>
                            </p>
                        </div>
                    <?php endif; ?>
                    
                    <h4 style="margin-top: 20px;"><?php esc_html_e( 'Dialogue-Level Flag Conditions', 'novel-game-plugin' ); ?></h4>
                    <p class="description"><?php esc_html_e( 'To set individual flag conditions for each dialogue, configure them when editing dialogues. Dialogues will not be displayed if flag conditions are not met.', 'novel-game-plugin' ); ?></p>
                    
                    <div id="noveltool-dialogue-flags-info">
                        <p><em><?php esc_html_e( 'Dialogue flag conditions can be set when editing each dialogue in the "Dialogue" section above.', 'novel-game-plugin' ); ?></em></p>
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

    // セリフごとのキャラクター設定データの保存
    if ( isset( $_POST['dialogue_characters'] ) ) {
        $dialogue_characters = wp_unslash( $_POST['dialogue_characters'] );
        
        // JSON文字列の場合は妥当性をチェックして保存
        if ( is_string( $dialogue_characters ) ) {
            $decoded = json_decode( $dialogue_characters, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                // 配列の各要素をサニタイズ
                $sanitized_characters = noveltool_sanitize_dialogue_characters( $decoded );
                update_post_meta( $post_id, '_dialogue_characters', $sanitized_characters );
            }
        } elseif ( is_array( $dialogue_characters ) ) {
            // 配列の場合は直接サニタイズ
            $sanitized_characters = noveltool_sanitize_dialogue_characters( $dialogue_characters );
            update_post_meta( $post_id, '_dialogue_characters', $sanitized_characters );
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
                        
                        // alternativeTextの処理（displayMode が 'alternative' の場合のみ保存）
                        if ( isset( $flag_condition['displayMode'] ) && $flag_condition['displayMode'] === 'alternative' ) {
                            if ( isset( $flag_condition['alternativeText'] ) ) {
                                $sanitized_item['alternativeText'] = sanitize_textarea_field( $flag_condition['alternativeText'] );
                            }
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
                    
                    // alternativeTextの処理（displayMode が 'alternative' の場合のみ保存）
                    if ( isset( $flag_condition['displayMode'] ) && $flag_condition['displayMode'] === 'alternative' ) {
                        if ( isset( $flag_condition['alternativeText'] ) ) {
                            $sanitized_item['alternativeText'] = sanitize_textarea_field( $flag_condition['alternativeText'] );
                        }
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
                        
                        // 設定フラグデータのサニタイズ
                        if ( isset( $choice_data['setFlags'] ) && is_array( $choice_data['setFlags'] ) ) {
                            $sanitized_set_flags = array();
                            foreach ( $choice_data['setFlags'] as $flag_data ) {
                                if ( is_array( $flag_data ) && isset( $flag_data['name'] ) ) {
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
 * セリフごとのキャラクター設定をサニタイズ
 *
 * @param array $dialogue_characters キャラクター設定配列
 * @return array サニタイズ済み配列
 * @since 1.4.0
 */
function noveltool_sanitize_dialogue_characters( $dialogue_characters ) {
    if ( ! is_array( $dialogue_characters ) ) {
        return array();
    }
    
    $sanitized = array();
    
    foreach ( $dialogue_characters as $item ) {
        if ( ! is_array( $item ) ) {
            // 配列でない場合は空の配列を追加
            $sanitized[] = array(
                'left'   => '',
                'center' => '',
                'right'  => '',
            );
            continue;
        }
        
        $sanitized_item = array(
            'left'   => '',
            'center' => '',
            'right'  => '',
        );
        
        // 許可されたキーのみ処理
        foreach ( array( 'left', 'center', 'right' ) as $position ) {
            if ( isset( $item[ $position ] ) && is_string( $item[ $position ] ) ) {
                // URLをサニタイズ（空文字は許可）
                $sanitized_item[ $position ] = esc_url_raw( $item[ $position ] );
            }
        }
        
        $sanitized[] = $sanitized_item;
    }
    
    return $sanitized;
}
