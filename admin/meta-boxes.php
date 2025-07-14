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
        wp_send_json_success(
            array(
                'ID'    => $new_id,
                'title' => $title,
            )
        );
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
        'selectOption'  => __( '-- 選択 --', 'novel-game-plugin' ),
        'createNew'     => __( '+ 新規作成...', 'novel-game-plugin' ),
        'remove'        => __( '削除', 'novel-game-plugin' ),
        'selectImage'   => __( '画像を選択', 'novel-game-plugin' ),
        'useThisImage'  => __( 'この画像を使う', 'novel-game-plugin' ),
        'confirmDelete' => __( '本当に削除しますか？', 'novel-game-plugin' ),
        'createFailed'  => __( '作成に失敗しました。', 'novel-game-plugin' ),
        'selectTitle'   => __( '新しいシーンのタイトルを入力してください', 'novel-game-plugin' ),
    );

    // データをJavaScriptに渡す
    wp_localize_script( 'novel-game-admin-meta-boxes', 'novelGameScenes', $scenes_data );
    wp_localize_script(
        'novel-game-admin-meta-boxes',
        'novelGameMeta',
        array(
            'nonce'   => wp_create_nonce( 'novel_game_meta_box_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'strings' => $js_strings,
        )
    );

    // HTMLテンプレートの出力
    ?>
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
                <label for="novel_character_image"><?php esc_html_e( 'キャラクター画像', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <input type="hidden"
                       id="novel_character_image"
                       name="character_image"
                       value="<?php echo esc_attr( $character ); ?>" />
                <img id="novel_character_image_preview"
                     src="<?php echo esc_url( $character ); ?>"
                     alt="<?php esc_attr_e( 'キャラクター画像プレビュー', 'novel-game-plugin' ); ?>"
                     style="max-width: 300px; height: auto; display: <?php echo $character ? 'block' : 'none'; ?>;" />
                <p>
                    <button type="button"
                            class="button"
                            id="novel_character_image_button">
                        <?php esc_html_e( 'メディアから選択', 'novel-game-plugin' ); ?>
                    </button>
                </p>
                <p class="description"><?php esc_html_e( 'シーンに表示するキャラクター画像を設定します。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="novel_dialogue_text"><?php esc_html_e( 'セリフ', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <textarea id="novel_dialogue_text"
                          name="dialogue_text"
                          rows="5"
                          cols="50"
                          class="large-text"><?php echo esc_textarea( $dialogue ); ?></textarea>
                <p class="description"><?php esc_html_e( 'セリフを入力してください。改行で分割されます。', 'novel-game-plugin' ); ?></p>
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
        'dialogue_text'    => '_dialogue_text',
        'choices'          => '_choices',
        'game_title'       => '_game_title',
    );

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

