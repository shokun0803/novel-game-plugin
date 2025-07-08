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
?>