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
    $game_description = get_post_meta( $post->ID, '_game_description', true );
    $game_title_image = get_post_meta( $post->ID, '_game_title_image', true );

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
// ...existing code...
    ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="novel_game_title"><?php esc_html_e( 'ゲームタイトル', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
<<<<<<< HEAD
                <input type="text" id="novel_game_title" name="game_title" value="<?php echo esc_attr( $game_title ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'このシーンが属するゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>" />
                <p class="description"><?php _e( 'ゲーム全体のタイトルを設定します。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="novel_game_description"><?php _e( 'ゲーム概要', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <textarea id="novel_game_description" name="game_description" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'ゲームの概要・説明を入力してください', 'novel-game-plugin' ); ?>"><?php echo esc_textarea( $game_description ); ?></textarea>
                <p class="description"><?php _e( 'ゲーム全体の説明や概要を設定します。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="novel_game_title_image"><?php _e( 'タイトル画面画像', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
                <input type="hidden" name="game_title_image" id="novel_game_title_image" value="<?php echo esc_attr( $game_title_image ); ?>">
                <img id="novel_game_title_image_preview" src="<?php echo esc_url( $game_title_image ); ?>" style="max-width:200px; max-height:150px; display:<?php echo $game_title_image ? 'block' : 'none'; ?>; margin-bottom: 10px;" />
                <br>
                <button type="button" class="button" id="novel_game_title_image_button"><?php _e( 'タイトル画像を選択', 'novel-game-plugin' ); ?></button>
                <button type="button" class="button" id="novel_game_title_image_remove" style="<?php echo $game_title_image ? '' : 'display:none;'; ?>"><?php _e( '画像を削除', 'novel-game-plugin' ); ?></button>
            </td>
        </tr>
=======
                <input type="text"
                       id="novel_game_title"
                       name="game_title"
                       value="<?php echo esc_attr( $game_title ); ?>"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'このシーンが属するゲームのタイトルを入力してください', 'novel-game-plugin' ); ?>" />
                <p class="description"><?php esc_html_e( 'ゲーム全体のタイトルを設定します。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>

>>>>>>> 46e8888 (Fix PHP coding standards compliance - spacing, formatting, and security)
        <tr>
            <th scope="row">
                <label for="novel_background_image"><?php esc_html_e( '背景画像', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
<<<<<<< HEAD
                <input type="hidden" id="novel_background_image" name="background_image" value="<?php echo esc_attr( $background ); ?>" />
                <img id="novel_background_image_preview" src="<?php echo esc_url( $background ); ?>" alt="<?php esc_attr_e( '背景画像プレビュー', 'novel-game-plugin' ); ?>" style="max-width: 300px; height: auto; display: <?php echo $background ? 'block' : 'none'; ?>;" />
                <p><button type="button" class="button" id="novel_background_image_button"><?php _e( 'メディアから選択', 'novel-game-plugin' ); ?></button></p>
                <p class="description"><?php _e( 'シーンの背景画像を設定します。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>
=======
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

>>>>>>> 46e8888 (Fix PHP coding standards compliance - spacing, formatting, and security)
        <tr>
            <th scope="row">
                <label for="novel_character_image"><?php esc_html_e( 'キャラクター画像', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
<<<<<<< HEAD
                <input type="hidden" id="novel_character_image" name="character_image" value="<?php echo esc_attr( $character ); ?>" />
                <img id="novel_character_image_preview" src="<?php echo esc_url( $character ); ?>" alt="<?php esc_attr_e( 'キャラクター画像プレビュー', 'novel-game-plugin' ); ?>" style="max-width: 300px; height: auto; display: <?php echo $character ? 'block' : 'none'; ?>;" />
                <p><button type="button" class="button" id="novel_character_image_button"><?php _e( 'メディアから選択', 'novel-game-plugin' ); ?></button></p>
                <p class="description"><?php _e( 'シーンに表示するキャラクター画像を設定します。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>
=======
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

>>>>>>> 46e8888 (Fix PHP coding standards compliance - spacing, formatting, and security)
        <tr>
            <th scope="row">
                <label for="novel_dialogue_text"><?php esc_html_e( 'セリフ', 'novel-game-plugin' ); ?></label>
            </th>
            <td>
<<<<<<< HEAD
                <textarea id="novel_dialogue_text" name="dialogue_text" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $dialogue ); ?></textarea>
                <p class="description"><?php _e( 'セリフを入力してください。改行で分割されます。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>
=======
                <textarea id="novel_dialogue_text"
                          name="dialogue_text"
                          rows="5"
                          cols="50"
                          class="large-text"><?php echo esc_textarea( $dialogue ); ?></textarea>
                <p class="description"><?php esc_html_e( 'セリフを入力してください。改行で分割されます。', 'novel-game-plugin' ); ?></p>
            </td>
        </tr>

>>>>>>> 46e8888 (Fix PHP coding standards compliance - spacing, formatting, and security)
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
<<<<<<< HEAD
                    <p><button type="button" class="button" id="novel-choice-add"><?php _e( '選択肢を追加', 'novel-game-plugin' ); ?></button></p>
                    <input type="hidden" id="novel_choices_hidden" name="choices" value="<?php echo esc_attr( $choices ); ?>" />
                    <p class="description"><?php _e( 'プレイヤーが選択できる選択肢を設定します。', 'novel-game-plugin' ); ?></p>
=======
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
>>>>>>> 46e8888 (Fix PHP coding standards compliance - spacing, formatting, and security)
                </div>
            </td>
        </tr>
    </table>
<?php
}
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
        'game_description' => '_game_description',
        'game_title_image' => '_game_title_image',
    );
<<<<<<< HEAD
    foreach ( $fields as $field => $meta_key ) {
        if ( isset( $_POST[ $field ] ) ) {
            $value = $_POST[ $field ];
            if ( in_array( $field, array( 'dialogue_text', 'choices', 'game_description' ) ) ) {
=======

    foreach ( $fields as $field => $meta_key ) {
        if ( isset( $_POST[ $field ] ) ) {
            $value = wp_unslash( $_POST[ $field ] );

            // サニタイズ処理
            if ( in_array( $field, array( 'dialogue_text', 'choices' ), true ) ) {
>>>>>>> 46e8888 (Fix PHP coding standards compliance - spacing, formatting, and security)
                $value = sanitize_textarea_field( $value );
            } else {
                $value = sanitize_text_field( $value );
            }
<<<<<<< HEAD
=======

>>>>>>> 46e8888 (Fix PHP coding standards compliance - spacing, formatting, and security)
            update_post_meta( $post_id, $meta_key, $value );
        }
    }
}
add_action( 'save_post', 'noveltool_save_meta_box_data' );
    <div style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; background-color: #f9f9f9;">
        <h3 style="margin-top: 0; color: #333;">ゲーム基本情報</h3>
        <p>
            <label><strong>ゲームタイトル:</strong><br>
                <input type="text" name="game_title" value="<?php echo esc_attr($game_title); ?>" style="width: 100%;" placeholder="ゲームのタイトルを入力してください">
            </label>
        </p>
        <p>
            <label><strong>ゲーム概要:</strong><br>
                <textarea name="game_description" rows="3" style="width: 100%;" placeholder="ゲームの概要・説明を入力してください"><?php echo esc_textarea($game_description); ?></textarea>
            </label>
        </p>
        <p>
            <label><strong>タイトル画面画像:</strong><br>
                <input type="hidden" name="game_title_image" id="novel_game_title_image" value="<?php echo esc_attr($game_title_image); ?>">
                <img id="novel_game_title_image_preview" src="<?php echo esc_url($game_title_image); ?>" style="max-width:200px; max-height:150px; display:<?php echo $game_title_image ? 'block' : 'none'; ?>; margin-bottom: 10px;" />
                <br>
                <button type="button" class="button" id="novel_game_title_image_button">タイトル画像を選択</button>
                <button type="button" class="button" id="novel_game_title_image_remove" style="<?php echo $game_title_image ? '' : 'display:none;'; ?>">画像を削除</button>
            </label>
        </p>
    </div>
    
    <div style="border: 1px solid #ddd; padding: 15px; background-color: #fff;">
        <h3 style="margin-top: 0; color: #333;">シーンデータ</h3>
    <p>
        <label>背景画像:
            <input type="hidden" name="background_image" id="novel_background_image" value="<?php echo esc_attr($background); ?>">
            <img id="novel_background_image_preview" src="<?php echo esc_url($background); ?>" style="max-width:200px; display:<?php echo $background ? 'block' : 'none'; ?>;" />
            <button type="button" class="button" id="novel_background_image_button">メディアから選択</button>
        </label>
    </p>
    <p>
        <label>キャラクター画像:
            <input type="hidden" name="character_image" id="novel_character_image" value="<?php echo esc_attr($character); ?>">
            <img id="novel_character_image_preview" src="<?php echo esc_url($character); ?>" style="max-width:200px; display:<?php echo $character ? 'block' : 'none'; ?>;" />
            <button type="button" class="button" id="novel_character_image_button">メディアから選択</button>
        </label>
    </p>
    <p><label>セリフ（改行で分割）:<br>
        <textarea name="dialogue_text" rows="5" cols="50"><?php echo esc_textarea($dialogue); ?></textarea></label></p>
    <div id="novel-choices-box">
        <label>選択肢（テキストと次のシーンを指定）:</label>
        <table id="novel-choices-table" style="width:100%;max-width:600px;">
            <thead><tr><th>テキスト</th><th>次のシーン</th><th>操作</th></tr></thead>
            <tbody></tbody>
        </table>
        <button type="button" class="button" id="novel-choice-add">選択肢を追加</button>
        <input type="hidden" name="choices" id="novel_choices_hidden" value="<?php echo esc_textarea($choices); ?>">
    </div>
    </div>
    <script>
    jQuery(function($){
        // 投稿一覧取得
        var scenes = [];
        <?php
        $args = array('post_type'=>'novel_game','posts_per_page'=>-1,'post_status'=>'publish');
        $scene_posts = get_posts($args);
        echo 'scenes = '.json_encode(array_map(function($p){return array('ID'=>$p->ID,'title'=>$p->post_title);}, $scene_posts), JSON_UNESCAPED_UNICODE).';';
        ?>

        // choicesの初期値をパース
        function parseChoices(str) {
            var arr = [];
            if (!str) return arr;
            str.split('\n').forEach(function(line){
                var parts = line.split('|');
                if(parts.length===2) arr.push({text:parts[0].trim(), next:parts[1].trim()});
            });
            return arr;
        }
        function renderChoicesTable() {
            var choices = parseChoices($('#novel_choices_hidden').val());
            var $tbody = $('#novel-choices-table tbody');
            $tbody.empty();
            choices.forEach(function(choice, idx){
                var row = $('<tr>');
                row.append('<td><input type="text" class="choice-text" value="'+choice.text.replace(/"/g,'&quot;')+'" style="width:98%"></td>');
                var select = $('<select class="choice-next" style="width:98%"></select>');
                select.append('<option value="">--選択--</option>');
                scenes.forEach(function(scene){
                    var sel = (scene.ID==choice.next)?'selected':'';
                    select.append('<option value="'+scene.ID+'" '+sel+'>'+scene.title+' (ID:'+scene.ID+')</option>');
                });
                select.append('<option value="__new__">+ 新規作成...</option>');
                row.append($('<td>').append(select));
                row.append('<td><button type="button" class="button choice-remove">削除</button></td>');
                $tbody.append(row);
            });
        }
        function updateChoicesHidden() {
            var arr = [];
            $('#novel-choices-table tbody tr').each(function(){
                var text = $(this).find('.choice-text').val();
                var next = $(this).find('.choice-next').val();
                if(text && next && next!=="__new__") arr.push(text+' | '+next);
            });
            $('#novel_choices_hidden').val(arr.join('\n'));
        }
        // 初期描画
        renderChoicesTable();

        // 追加
        $('#novel-choice-add').on('click', function(){
            var $tbody = $('#novel-choices-table tbody');
            var row = $('<tr>');
            row.append('<td><input type="text" class="choice-text" value="" style="width:98%"></td>');
            var select = $('<select class="choice-next" style="width:98%"></select>');
            select.append('<option value="">--選択--</option>');
            scenes.forEach(function(scene){
                select.append('<option value="'+scene.ID+'">'+scene.title+' (ID:'+scene.ID+')</option>');
            });
            select.append('<option value="__new__">+ 新規作成...</option>');
            row.append($('<td>').append(select));
            row.append('<td><button type="button" class="button choice-remove">削除</button></td>');
            $tbody.append(row);
        });
        // 削除
        $('#novel-choices-table').on('click', '.choice-remove', function(){
            $(this).closest('tr').remove();
            updateChoicesHidden();
        });
        // 入力変更
        $('#novel-choices-table').on('change', '.choice-text, .choice-next', function(){
            updateChoicesHidden();
        });
        // 新規作成
        $('#novel-choices-table').on('change', '.choice-next', function(){
            if($(this).val()==='__new__'){
                var title = prompt('新しいシーンのタイトルを入力してください');
                if(title){
                    // Ajaxで新規投稿作成
                    var select = $(this);
                    select.prop('disabled', true);
                    $.post(ajaxurl, {
                        action: 'novel_game_create_scene',
                        title: title,
                        _ajax_nonce: novelGameMeta.nonce
                    }, function(res){
                        if(res.success){
                            scenes.push({ID:res.data.ID, title:res.data.title});
                            select.append('<option value="'+res.data.ID+'" selected>'+res.data.title+' (ID:'+res.data.ID+')</option>');
                            select.val(res.data.ID);
                        }else{
                            alert('作成失敗');
                        }
                        select.prop('disabled', false);
                        updateChoicesHidden();
                    });
                }else{
                    $(this).val('');
                }
            }
        });
    });
    </script>
    <script>
    jQuery(function($){
        function mediaUploader(buttonId, inputId, previewId) {
            $(buttonId).on('click', function(e) {
                e.preventDefault();
                var custom_uploader = wp.media({
                    title: '画像を選択',
                    button: { text: 'この画像を使う' },
                    multiple: false
                })
                .on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    $(inputId).val(attachment.url);
                    $(previewId).attr('src', attachment.url).show();
                })
                .open();
            });
        }
        mediaUploader('#novel_background_image_button', '#novel_background_image', '#novel_background_image_preview');
        mediaUploader('#novel_character_image_button', '#novel_character_image', '#novel_character_image_preview');
        mediaUploader('#novel_game_title_image_button', '#novel_game_title_image', '#novel_game_title_image_preview');
        
        // タイトル画像削除ボタン
        $('#novel_game_title_image_remove').on('click', function(e) {
            e.preventDefault();
            $('#novel_game_title_image').val('');
            $('#novel_game_title_image_preview').hide();
            $(this).hide();
        });
        
        // タイトル画像選択時に削除ボタンを表示
        $('#novel_game_title_image_button').on('click', function(e) {
            e.preventDefault();
            var custom_uploader = wp.media({
                title: 'タイトル画像を選択',
                button: { text: 'この画像を使う' },
                multiple: false
            })
            .on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $('#novel_game_title_image').val(attachment.url);
                $('#novel_game_title_image_preview').attr('src', attachment.url).show();
                $('#novel_game_title_image_remove').show();
            })
            .open();
        });
    });
    </script>
<?php
}

function novel_game_save_meta_box_data($post_id) {
    if (array_key_exists('background_image', $_POST)) {
        update_post_meta($post_id, '_background_image', sanitize_text_field($_POST['background_image']));
    }
    if (array_key_exists('character_image', $_POST)) {
        update_post_meta($post_id, '_character_image', sanitize_text_field($_POST['character_image']));
    }
    if (array_key_exists('dialogue_text', $_POST)) {
        update_post_meta($post_id, '_dialogue_text', sanitize_textarea_field($_POST['dialogue_text']));
    }
    if (array_key_exists('choices', $_POST)) {
        update_post_meta($post_id, '_choices', sanitize_textarea_field($_POST['choices']));
    }
    if (array_key_exists('game_title', $_POST)) {
        update_post_meta($post_id, '_game_title', sanitize_text_field($_POST['game_title']));
    }
    if (array_key_exists('game_description', $_POST)) {
        update_post_meta($post_id, '_game_description', sanitize_textarea_field($_POST['game_description']));
    }
    if (array_key_exists('game_title_image', $_POST)) {
        update_post_meta($post_id, '_game_title_image', sanitize_text_field($_POST['game_title_image']));
    }
}
add_action('save_post', 'novel_game_save_meta_box_data');

