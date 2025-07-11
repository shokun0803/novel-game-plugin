<?php
/**
 * カスタム投稿タイプの登録と管理
 * 
 * @package NovelGamePlugin
 * @since 1.0.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ノベルゲーム用のカスタム投稿タイプを登録
 * 
 * @since 1.0.0
 */
function noveltool_register_post_type() {
    $labels = array(
        'name'                  => _x( 'ノベルゲーム', 'Post Type General Name', 'novel-game-plugin' ),
        'singular_name'         => _x( 'ノベルゲーム', 'Post Type Singular Name', 'novel-game-plugin' ),
        'menu_name'             => __( 'ノベルゲーム', 'novel-game-plugin' ),
        'name_admin_bar'        => __( 'ノベルゲーム', 'novel-game-plugin' ),
        'archives'              => __( 'ノベルゲーム一覧', 'novel-game-plugin' ),
        'attributes'            => __( 'ノベルゲーム属性', 'novel-game-plugin' ),
        'parent_item_colon'     => __( '親ノベルゲーム:', 'novel-game-plugin' ),
        'all_items'             => __( 'すべてのノベルゲーム', 'novel-game-plugin' ),
        'add_new_item'          => __( '新しいノベルゲームを追加', 'novel-game-plugin' ),
        'add_new'               => __( '新規追加', 'novel-game-plugin' ),
        'new_item'              => __( '新しいノベルゲーム', 'novel-game-plugin' ),
        'edit_item'             => __( 'ノベルゲームを編集', 'novel-game-plugin' ),
        'update_item'           => __( 'ノベルゲームを更新', 'novel-game-plugin' ),
        'view_item'             => __( 'ノベルゲームを表示', 'novel-game-plugin' ),
        'view_items'            => __( 'ノベルゲームを表示', 'novel-game-plugin' ),
        'search_items'          => __( 'ノベルゲームを検索', 'novel-game-plugin' ),
        'not_found'             => __( 'ノベルゲームが見つかりません', 'novel-game-plugin' ),
        'not_found_in_trash'    => __( 'ゴミ箱にノベルゲームが見つかりません', 'novel-game-plugin' ),
        'featured_image'        => __( 'アイキャッチ画像', 'novel-game-plugin' ),
        'set_featured_image'    => __( 'アイキャッチ画像を設定', 'novel-game-plugin' ),
        'remove_featured_image' => __( 'アイキャッチ画像を削除', 'novel-game-plugin' ),
        'use_featured_image'    => __( 'アイキャッチ画像として使用', 'novel-game-plugin' ),
        'insert_into_item'      => __( 'ノベルゲームに挿入', 'novel-game-plugin' ),
        'uploaded_to_this_item' => __( 'このノベルゲームにアップロード', 'novel-game-plugin' ),
        'items_list'            => __( 'ノベルゲーム一覧', 'novel-game-plugin' ),
        'items_list_navigation' => __( 'ノベルゲーム一覧ナビゲーション', 'novel-game-plugin' ),
        'filter_items_list'     => __( 'ノベルゲーム一覧をフィルター', 'novel-game-plugin' ),
    );
    
    $args = array(
        'label'                 => __( 'ノベルゲーム', 'novel-game-plugin' ),
        'description'           => __( 'ノベルゲームのシーンを管理', 'novel-game-plugin' ),
        'labels'                => $labels,
        'supports'              => array( 'title' ),
        'taxonomies'            => array(),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 25,
        'menu_icon'             => 'dashicons-book',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    );
    
    register_post_type( 'novel_game', $args );
}
add_action( 'init', 'noveltool_register_post_type' );

/**
 * 管理画面の投稿一覧にゲームタイトル列を追加
 * 
 * @param array $columns 既存の列
 * @return array 修正された列
 * @since 1.0.0
 */
function noveltool_add_custom_columns( $columns ) {
    $columns['game_title'] = __( 'ゲームタイトル', 'novel-game-plugin' );
    return $columns;
}
add_filter( 'manage_novel_game_posts_columns', 'noveltool_add_custom_columns' );

/**
 * ゲームタイトル列の内容を表示
 * 
 * @param string $column 列名
 * @param int    $post_id 投稿ID
 * @since 1.0.0
 */
function noveltool_custom_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'game_title':
            $game_title = get_post_meta( $post_id, '_game_title', true );
            echo $game_title ? esc_html( $game_title ) : '—';
            break;
    }
}
add_action( 'manage_novel_game_posts_custom_column', 'noveltool_custom_column_content', 10, 2 );

/**
 * ゲームタイトル列をソート可能にする
 * 
 * @param array $columns ソート可能な列
 * @return array 修正された列
 * @since 1.0.0
 */
function noveltool_sortable_columns( $columns ) {
    $columns['game_title'] = 'game_title';
    return $columns;
}
add_filter( 'manage_edit-novel_game_sortable_columns', 'noveltool_sortable_columns' );

/**
 * ゲームタイトルでのソート処理
 * 
 * @param WP_Query $query WPクエリオブジェクト
 * @since 1.0.0
 */
function noveltool_orderby( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    
    if ( 'game_title' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', '_game_title' );
        $query->set( 'orderby', 'meta_value' );
    }
}
add_action( 'pre_get_posts', 'noveltool_orderby' );
?>