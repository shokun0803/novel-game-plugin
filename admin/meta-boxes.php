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
    
    // 既存のセリフテキストを行に分割
    $dialogue_lines = array();
    if ( $dialogue ) {
        $dialogue_lines = array_filter( array_map( 'trim', explode( "\n", $dialogue ) ) );
    }

    // WordPressメディアアップローダー用スクリプトの読み込み
    wp_enqueue_media();

    // 管理画面専用スクリプトの読み込み
    wp_enqueue_script(
        'novel-game-admin-meta-boxes',
        NOVEL_GAME_PLUGIN_URL . 'js/admin-meta-boxes.js',
        array( 'jquery', 'media-upload', 'media-views' ),
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
    
    @media (max-width: 768px) {
        .character-positions-container {
            flex-direction: column;
        }
        
        .character-position-item {
            min-width: auto;
        }
    }
    </style>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="novel_game_title"><?php esc_html_e( 'ゲームタイトル', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <input type="text"
                       id="novel_game_title"
                       name="game_title"
                       value="<?php echo esc_attr( $game_title ); ?>"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'このシーンが属するゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>" />
                <p class="description"><?php esc_html_e( 'ゲーム全体のタイトルを設定します。', 'novel-game-plugin' ); ?></p>
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
                                <th><?php esc_html_e( 'テキスト', 'novel-game-plugin' ); ?></th>
                                <th><?php esc_html_e( '次のシーン', 'novel-game-plugin' ); ?></th>
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
                    <p class="description"><?php esc_html_e( 'プレイヤーが選択できる選択肢を設定します。', 'novel-game-plugin' ); ?></p>
                </div>
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

    foreach ( $fields as $field => $meta_key ) {
        if ( isset( $_POST[ $field ] ) ) {
            $value = wp_unslash( $_POST[ $field ] );

            // サニタイズ処理
            if ( in_array( $field, array( 'dialogue_text', 'choices' ), true ) ) {
                $value = sanitize_textarea_field( $value );
            } else {
                $value = sanitize_text_field( $value );
            }

            update_post_meta( $post_id, $meta_key, $value );
        }
    }
}
add_action( 'save_post', 'noveltool_save_meta_box_data' );

