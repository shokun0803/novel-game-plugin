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
        'name'                  => _x( 'Novel Game', 'Post Type General Name', 'novel-game-plugin' ),
        'singular_name'         => _x( 'Novel Game', 'Post Type Singular Name', 'novel-game-plugin' ),
        'menu_name'             => __( 'Novel Game', 'novel-game-plugin' ),
        'name_admin_bar'        => __( 'Novel Game', 'novel-game-plugin' ),
        'archives'              => __( 'Novel Game List', 'novel-game-plugin' ),
        'attributes'            => __( 'Novel Game Attributes', 'novel-game-plugin' ),
        'parent_item_colon'     => __( 'Parent Novel Game:', 'novel-game-plugin' ),
        'all_items'             => __( 'All Novel Games', 'novel-game-plugin' ),
        'add_new_item'          => __( 'Add New Novel Game', 'novel-game-plugin' ),
        'add_new'               => __( 'Add New', 'novel-game-plugin' ),
        'new_item'              => __( 'New Novel Game', 'novel-game-plugin' ),
        'edit_item'             => __( 'Edit Novel Game', 'novel-game-plugin' ),
        'update_item'           => __( 'Update Novel Game', 'novel-game-plugin' ),
        'view_item'             => __( 'View Novel Game', 'novel-game-plugin' ),
        'view_items'            => __( 'View Novel Game', 'novel-game-plugin' ),
        'search_items'          => __( 'Search Novel Games', 'novel-game-plugin' ),
        'not_found'             => __( 'No novel games found', 'novel-game-plugin' ),
        'not_found_in_trash'    => __( 'No novel games found in Trash', 'novel-game-plugin' ),
        'featured_image'        => __( 'Featured Image', 'novel-game-plugin' ),
        'set_featured_image'    => __( 'Set Featured Image', 'novel-game-plugin' ),
        'remove_featured_image' => __( 'Remove Featured Image', 'novel-game-plugin' ),
        'use_featured_image'    => __( 'Use as Featured Image', 'novel-game-plugin' ),
        'insert_into_item'      => __( 'Insert into Novel Game', 'novel-game-plugin' ),
        'uploaded_to_this_item' => __( 'Uploaded to this Novel Game', 'novel-game-plugin' ),
        'items_list'            => __( 'Novel Game List', 'novel-game-plugin' ),
        'items_list_navigation' => __( 'Novel Game List Navigation', 'novel-game-plugin' ),
        'filter_items_list'     => __( 'Filter Novel Game List', 'novel-game-plugin' ),
    );

    $args = array(
        'label'                 => __( 'Novel Game Management', 'novel-game-plugin' ),
        'description'           => __( 'Manage Novel Game Scenes', 'novel-game-plugin' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'revisions', 'custom-fields' ),
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
        'rewrite'               => array(
            'slug'       => 'novel_game',
            'with_front' => false,
        ),
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
    $columns['game_title'] = __( 'Game Title', 'novel-game-plugin' );
    // 必要に応じて他のカラムも追加
    return $columns;
}
add_filter( 'manage_novel_game_posts_columns', 'noveltool_add_custom_columns' );

/**
 * ゲームタイトル列の内容を表示
 *
 * @param string $column  列名
 * @param int    $post_id 投稿ID
 * @since 1.0.0
 */
function noveltool_custom_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'game_title':
            $game_title = get_post_meta( $post_id, '_game_title', true );
            echo $game_title ? esc_html( $game_title ) : '—';
            break;
        case 'game_description':
            $game_description = get_post_meta($post_id, '_game_description', true);
            if ($game_description) {
                $truncated = mb_strlen($game_description) > 50 ? mb_substr($game_description, 0, 50) . '...' : $game_description;
                echo '<span title="' . esc_attr($game_description) . '">' . esc_html($truncated) . '</span>';
            } else {
                echo '—';
            }
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
    // 必要に応じてカスタムソート列を追加
    $columns['game_title'] = 'game_title';
    $columns['game_description'] = 'game_description';
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
    } elseif ( 'game_description' === $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', '_game_description' );
        $query->set( 'orderby', 'meta_value' );
    }
}
add_action( 'pre_get_posts', 'noveltool_orderby' );

/**
 * ゲームタイトルに基づく投稿一覧の取得
 *
 * @param string $game_title ゲームタイトル
 * @param array  $args       追加の引数
 * @return array 投稿の配列
 * @since 1.0.0
 */
function noveltool_get_posts_by_game_title( $game_title, $args = array() ) {
    // デフォルト引数の設定
    $default_args = array(
        'post_type'      => 'novel_game',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            array(
                'key'   => '_game_title',
                'value' => $game_title,
            ),
        ),
        'orderby'        => 'date',
        'order'          => 'ASC',
    );
    
    // 引数のマージ
    $query_args = wp_parse_args( $args, $default_args );
    
    // 投稿の取得
    $posts = get_posts( $query_args );
    
    return $posts;
}

/**
 * すべてのゲームタイトルを取得
 *
 * @return array ゲームタイトルの配列
 * @since 1.0.0
 */
function noveltool_get_all_game_titles() {
    global $wpdb;
    
    // 重複を除いてゲームタイトルを取得
    $game_titles = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = %s 
             AND pm.meta_value != ''
             AND p.post_type = %s
             AND p.post_status = %s
             ORDER BY pm.meta_value ASC",
            '_game_title',
            'novel_game',
            'publish'
        )
    );
    
    return $game_titles;
}

/**
 * 管理画面でゲームタイトルによるフィルタリング機能を追加
 *
 * @since 1.0.0
 */
function noveltool_add_admin_filters() {
    global $typenow;
    
    if ( 'novel_game' === $typenow ) {
        $game_titles = noveltool_get_all_game_titles();
        
        if ( ! empty( $game_titles ) ) {
            $current_filter = isset( $_GET['game_title_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['game_title_filter'] ) ) : '';
            
            echo '<select name="game_title_filter">';
            echo '<option value="">' . esc_html__( 'All Games', 'novel-game-plugin' ) . '</option>';
            
            foreach ( $game_titles as $game_title ) {
                $selected = selected( $current_filter, $game_title, false );
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr( $game_title ),
                    selected( $current_filter, $game_title, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() の出力はエスケープ済み
                    esc_html( $game_title )
                );
            }
            
            echo '</select>';
        }
    }
}
add_action( 'restrict_manage_posts', 'noveltool_add_admin_filters' );

/**
 * 管理画面でのフィルタリング処理
 *
 * @param WP_Query $query WPクエリオブジェクト
 * @since 1.0.0
 */
function noveltool_admin_filter_posts( $query ) {
    global $pagenow;
    
    if ( is_admin() && $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'novel_game' ) {
        if ( isset( $_GET['game_title_filter'] ) && ! empty( $_GET['game_title_filter'] ) ) {
            $game_title = sanitize_text_field( wp_unslash( $_GET['game_title_filter'] ) );
            $query->set( 'meta_key', '_game_title' );
            $query->set( 'meta_value', $game_title );
        }
    }
}
add_action( 'pre_get_posts', 'noveltool_admin_filter_posts' );

/**
 * メニュー構造のカスタマイズ
 *
 * @since 1.2.0
 */
function noveltool_customize_admin_menu() {
    global $submenu;
    
    // 「すべてのノベルゲーム」メニューを削除
    if ( isset( $submenu['edit.php?post_type=novel_game'] ) ) {
        foreach ( $submenu['edit.php?post_type=novel_game'] as $key => $menu_item ) {
            // "All Novel Games"メニューを削除
            if ( $menu_item[2] === 'edit.php?post_type=novel_game' ) {
                unset( $submenu['edit.php?post_type=novel_game'][ $key ] );
            }
            // "Add New"メニューを削除
            if ( $menu_item[2] === 'post-new.php?post_type=novel_game' ) {
                unset( $submenu['edit.php?post_type=novel_game'][ $key ] );
            }
        }
    }
}
add_action( 'admin_menu', 'noveltool_customize_admin_menu', 999 );

/**
 * ダッシュボードページをメニューに追加
 *
 * @since 1.2.0
 */
function noveltool_add_dashboard_menu() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __( 'Dashboard', 'novel-game-plugin' ),
        '🏠 ' . __( 'Dashboard', 'novel-game-plugin' ),
        'edit_posts',
        'novel-game-dashboard',
        'noveltool_dashboard_page',
        0
    );
}
add_action( 'admin_menu', 'noveltool_add_dashboard_menu' );

/**
 * マイゲームページをメニューに追加
 *
 * @since 1.2.0
 */
function noveltool_add_my_games_menu() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __( 'My Games', 'novel-game-plugin' ),
        '🎮 ' . __( 'My Games', 'novel-game-plugin' ),
        'edit_posts',
        'novel-game-my-games',
        'noveltool_my_games_page',
        1
    );
}
add_action( 'admin_menu', 'noveltool_add_my_games_menu' );

/**
 * エクスポート/インポートページをメニューに追加
 *
 * @since 1.3.0
 */
function noveltool_add_export_import_menu() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __( 'Export/Import', 'novel-game-plugin' ),
        '📥📤 ' . __( 'Export/Import', 'novel-game-plugin' ),
        'edit_posts',
        'novel-game-export-import',
        'noveltool_export_import_page',
        2
    );
}
add_action( 'admin_menu', 'noveltool_add_export_import_menu' );

