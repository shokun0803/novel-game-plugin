<?php
// Ajaxで新規ノベルゲーム投稿を作成（管理画面等に表示されないようPHPタグ内に記述）
add_action('wp_ajax_novel_game_create_scene', function() {
    check_ajax_referer('novel_game_meta_box_nonce');
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    if (!$title) wp_send_json_error();
    $new_id = wp_insert_post([
        'post_type' => 'novel_game',
        'post_title' => $title,
        'post_status' => 'publish',
    ]);
    if ($new_id && !is_wp_error($new_id)) {
        wp_send_json_success(['ID'=>$new_id, 'title'=>$title]);
    } else {
        wp_send_json_error();
    }
});

// メタボックス用nonceを出力
add_action('admin_enqueue_scripts', function($hook){
    global $post;
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_localize_script('jquery', 'novelGameMeta', [
            'nonce' => wp_create_nonce('novel_game_meta_box_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }
});
function novel_game_add_meta_boxes() {
    add_meta_box('novel_scene_data', 'シーンデータ', 'novel_game_meta_box_callback', 'novel_game', 'normal', 'high');
}
add_action('add_meta_boxes', 'novel_game_add_meta_boxes');

function novel_game_meta_box_callback($post) {
    $background = get_post_meta($post->ID, '_background_image', true);
    $character = get_post_meta($post->ID, '_character_image', true);
    $dialogue = get_post_meta($post->ID, '_dialogue_text', true);
    $choices = get_post_meta($post->ID, '_choices', true);
    $game_title = get_post_meta($post->ID, '_game_title', true);

    // WordPressメディアアップローダ用スクリプト
    wp_enqueue_media();
    ?>
    <p>
        <label>ゲームタイトル:
            <input type="text" name="game_title" value="<?php echo esc_attr($game_title); ?>" style="width: 100%;" placeholder="このシーンが属するゲームのタイトルを入力してください">
        </label>
    </p>
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
}
add_action('save_post', 'novel_game_save_meta_box_data');

