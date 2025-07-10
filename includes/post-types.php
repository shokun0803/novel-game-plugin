<?php
function novel_game_register_post_type() {
    register_post_type('novel_game', array(
        'labels' => array(
            'name'          => 'ノベルゲーム',
            'singular_name' => 'ノベルゲーム',
        ),
        'public'       => true,
        'has_archive'  => true,
        'menu_icon'    => 'dashicons-book',
        'supports'     => array('title'),
    ));
}
add_action('init', 'novel_game_register_post_type');

// 管理画面のpost一覧にゲームタイトル列を追加
function novel_game_add_custom_columns($columns) {
    $columns['game_title'] = 'ゲームタイトル';
    return $columns;
}
add_filter('manage_novel_game_posts_columns', 'novel_game_add_custom_columns');

// ゲームタイトル列の内容を表示
function novel_game_custom_column_content($column, $post_id) {
    switch ($column) {
        case 'game_title':
            $game_title = get_post_meta($post_id, '_game_title', true);
            echo $game_title ? esc_html($game_title) : '—';
            break;
    }
}
add_action('manage_novel_game_posts_custom_column', 'novel_game_custom_column_content', 10, 2);

// ゲームタイトル列をソート可能にする
function novel_game_sortable_columns($columns) {
    $columns['game_title'] = 'game_title';
    return $columns;
}
add_filter('manage_edit-novel_game_sortable_columns', 'novel_game_sortable_columns');

// ゲームタイトルでのソート処理
function novel_game_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ('game_title' === $query->get('orderby')) {
        $query->set('meta_key', '_game_title');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'novel_game_orderby');
?>