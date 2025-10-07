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
        'supports'              => array( 'title', 'excerpt', 'custom-fields' ),
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
    $columns['game_title'] = __( 'ゲームタイトル', 'novel-game-plugin' );
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
            echo '<option value="">' . esc_html__( 'すべてのゲーム', 'novel-game-plugin' ) . '</option>';
            
            foreach ( $game_titles as $game_title ) {
                $selected = selected( $current_filter, $game_title, false );
                echo '<option value="' . esc_attr( $game_title ) . '" ' . $selected . '>' . esc_html( $game_title ) . '</option>';
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
 * ゲーム一覧ページをメニューに追加
 *
 * @since 1.0.0
 */
function noveltool_add_game_list_menu() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __( 'ゲーム一覧', 'novel-game-plugin' ),
        __( 'ゲーム一覧', 'novel-game-plugin' ),
        'edit_posts',
        'novel-game-list',
        'noveltool_game_list_page'
    );
}
add_action( 'admin_menu', 'noveltool_add_game_list_menu' );

/**
 * ゲーム一覧ページの内容
 *
 * @since 1.0.0
 */
function noveltool_game_list_page() {
    // 権限チェック
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'このページにアクセスする権限がありません。', 'novel-game-plugin' ) );
    }
    
    // ゲームタイトルの取得
    $game_titles = noveltool_get_all_game_titles();
    
    // 選択されたゲームタイトル
    $selected_game = isset( $_GET['game_title'] ) ? sanitize_text_field( wp_unslash( $_GET['game_title'] ) ) : '';
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        
        <div class="noveltool-game-list-container">
            <?php if ( empty( $game_titles ) ) : ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e( 'まだゲームが作成されていません。', 'novel-game-plugin' ); ?></p>
                    <p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=novel_game&page=novel-game-new' ) ); ?>" class="button button-primary"><?php esc_html_e( '新規ゲームを作成', 'novel-game-plugin' ); ?></a></p>
                </div>
            <?php else : ?>
                <div class="noveltool-game-selector">
                    <form method="get" action="">
                        <input type="hidden" name="post_type" value="novel_game" />
                        <input type="hidden" name="page" value="novel-game-list" />
                        <label for="game_title_select"><?php esc_html_e( 'ゲームを選択:', 'novel-game-plugin' ); ?></label>
                        <select name="game_title" id="game_title_select">
                            <option value=""><?php esc_html_e( '-- ゲームを選択 --', 'novel-game-plugin' ); ?></option>
                            <?php foreach ( $game_titles as $game_title ) : ?>
                                <option value="<?php echo esc_attr( $game_title ); ?>" <?php selected( $selected_game, $game_title ); ?>>
                                    <?php echo esc_html( $game_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" class="button" value="<?php esc_attr_e( '表示', 'novel-game-plugin' ); ?>" />
                    </form>
                </div>
                
                <?php if ( ! empty( $selected_game ) ) : ?>
                    <div class="noveltool-game-posts">
                        <h2><?php printf( esc_html__( 'ゲーム: %s', 'novel-game-plugin' ), esc_html( $selected_game ) ); ?></h2>
                        
                        <?php
                        $posts = noveltool_get_posts_by_game_title( $selected_game );
                        
                        if ( empty( $posts ) ) :
                            ?>
                            <div class="notice notice-warning">
                                <p><?php esc_html_e( 'このゲームにはまだ投稿がありません。', 'novel-game-plugin' ); ?></p>
                            </div>
                        <?php else : ?>
                            <div class="noveltool-posts-actions">
                                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=novel_game' ) ); ?>" class="button button-primary">
                                    <?php esc_html_e( '新しいシーンを追加', 'novel-game-plugin' ); ?>
                                </a>
                            </div>
                            
                            <table class="wp-list-table widefat fixed striped posts">
                                <thead>
                                    <tr>
                                        <th scope="col" class="manage-column column-title">
                                            <?php esc_html_e( 'タイトル', 'novel-game-plugin' ); ?>
                                        </th>
                                        <th scope="col" class="manage-column column-date">
                                            <?php esc_html_e( '作成日', 'novel-game-plugin' ); ?>
                                        </th>
                                        <th scope="col" class="manage-column column-status">
                                            <?php esc_html_e( 'ステータス', 'novel-game-plugin' ); ?>
                                        </th>
                                        <th scope="col" class="manage-column column-actions">
                                            <?php esc_html_e( '操作', 'novel-game-plugin' ); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $posts as $post ) : ?>
                                        <tr>
                                            <td class="title column-title">
                                                <strong>
                                                    <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
                                                        <?php echo esc_html( $post->post_title ); ?>
                                                    </a>
                                                </strong>
                                                <div class="row-actions">
                                                    <span class="edit">
                                                        <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">
                                                            <?php esc_html_e( '編集', 'novel-game-plugin' ); ?>
                                                        </a>
                                                    </span>
                                                    |
                                                    <span class="view">
                                                        <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank">
                                                            <?php esc_html_e( '表示', 'novel-game-plugin' ); ?>
                                                        </a>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="date column-date">
                                                <?php echo esc_html( get_the_date( 'Y/m/d H:i', $post->ID ) ); ?>
                                            </td>
                                            <td class="status column-status">
                                                <?php echo esc_html( get_post_status_object( $post->post_status )->label ); ?>
                                            </td>
                                            <td class="actions column-actions">
                                                <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="button button-small">
                                                    <?php esc_html_e( '編集', 'novel-game-plugin' ); ?>
                                                </a>
                                                <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="button button-small" target="_blank">
                                                    <?php esc_html_e( '表示', 'novel-game-plugin' ); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="noveltool-game-overview">
                    <h3><?php esc_html_e( 'ゲーム概要', 'novel-game-plugin' ); ?></h3>
                    <div class="noveltool-games-summary">
                        <?php foreach ( $game_titles as $game_title ) : ?>
                            <?php
                            $game_posts = noveltool_get_posts_by_game_title( $game_title );
                            $post_count = count( $game_posts );
                            ?>
                            <div class="noveltool-game-summary-item">
                                <strong><?php echo esc_html( $game_title ); ?></strong>
                                <span class="post-count"><?php printf( esc_html__( '%d 投稿', 'novel-game-plugin' ), $post_count ); ?></span>
                                <a href="<?php echo esc_url( add_query_arg( array( 'game_title' => $game_title ), admin_url( 'edit.php?post_type=novel_game&page=novel-game-list' ) ) ); ?>" class="button button-small">
                                    <?php esc_html_e( '表示', 'novel-game-plugin' ); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .noveltool-game-list-container {
        max-width: 1200px;
    }
    .noveltool-game-selector {
        background: #f9f9f9;
        padding: 15px;
        border: 1px solid #ddd;
        margin-bottom: 20px;
    }
    .noveltool-game-selector select {
        margin: 0 10px;
    }
    .noveltool-posts-actions {
        margin-bottom: 15px;
    }
    .noveltool-game-overview {
        margin-top: 30px;
        padding: 20px;
        background: #f9f9f9;
        border: 1px solid #ddd;
    }
    .noveltool-games-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }
    .noveltool-game-summary-item {
        background: white;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 3px;
        min-width: 200px;
    }
    .noveltool-game-summary-item strong {
        display: block;
        margin-bottom: 5px;
    }
    .noveltool-game-summary-item .post-count {
        color: #666;
        font-size: 0.9em;
        margin-right: 10px;
    }
    </style>
    <?php
}