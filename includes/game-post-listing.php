<?php
/**
 * ゲームタイトル別投稿一覧表示機能
 * 
 * @package NovelGamePlugin
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ゲームタイトル別に投稿を取得する関数
 * 
 * @param string $game_title ゲームタイトル
 * @param array $args 追加のクエリパラメータ
 * @return array 投稿の配列
 */
function noveltool_get_posts_by_game_title($game_title = '', $args = array()) {
    $default_args = array(
        'post_type' => 'novel_game',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array()
    );
    
    if (!empty($game_title)) {
        $default_args['meta_query'][] = array(
            'key' => '_game_title',
            'value' => $game_title,
            'compare' => '='
        );
    }
    
    $args = wp_parse_args($args, $default_args);
    return get_posts($args);
}

/**
 * すべてのゲームタイトルを取得する関数
 * 
 * @return array ゲームタイトルの配列
 */
function noveltool_get_all_game_titles() {
    global $wpdb;
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT meta_value as game_title 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = %s 
             AND p.post_type = %s 
             AND p.post_status = %s 
             AND pm.meta_value != '' 
             ORDER BY pm.meta_value ASC",
            '_game_title',
            'novel_game',
            'publish'
        )
    );
    
    return wp_list_pluck($results, 'game_title');
}

/**
 * ゲームタイトル別に投稿をグループ化して取得する関数
 * 
 * @return array ゲームタイトルをキーとした投稿配列
 */
function noveltool_get_posts_grouped_by_game() {
    $game_titles = noveltool_get_all_game_titles();
    $grouped_posts = array();
    
    foreach ($game_titles as $game_title) {
        $posts = noveltool_get_posts_by_game_title($game_title);
        if (!empty($posts)) {
            $grouped_posts[$game_title] = $posts;
        }
    }
    
    // タイトルなしの投稿も取得
    $posts_without_title = noveltool_get_posts_by_game_title('', array(
        'meta_query' => array(
            array(
                'key' => '_game_title',
                'value' => '',
                'compare' => '='
            )
        )
    ));
    
    // タイトルなしの投稿も含む場合
    $posts_without_title_alt = get_posts(array(
        'post_type' => 'novel_game',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_game_title',
                'compare' => 'NOT EXISTS'
            )
        )
    ));
    
    $all_posts_without_title = array_merge($posts_without_title, $posts_without_title_alt);
    
    if (!empty($all_posts_without_title)) {
        $grouped_posts[__('タイトルなし', 'noveltool')] = $all_posts_without_title;
    }
    
    return $grouped_posts;
}

/**
 * フロントエンド用ショートコード [noveltool_game_posts]
 * 
 * @param array $atts ショートコードの属性
 * @return string HTML出力
 */
function noveltool_game_posts_shortcode($atts) {
    $atts = shortcode_atts(array(
        'game_title' => '',
        'show_title' => 'yes',
        'show_excerpt' => 'no',
        'limit' => -1
    ), $atts);
    
    $game_title = sanitize_text_field($atts['game_title']);
    $show_title = $atts['show_title'] === 'yes';
    $show_excerpt = $atts['show_excerpt'] === 'yes';
    $limit = intval($atts['limit']);
    
    $args = array();
    if ($limit > 0) {
        $args['posts_per_page'] = $limit;
    }
    
    if (!empty($game_title)) {
        $posts = noveltool_get_posts_by_game_title($game_title, $args);
        $output = '<div class="noveltool-game-posts">';
        
        if ($show_title) {
            $output .= '<h3 class="noveltool-game-title">' . esc_html($game_title) . '</h3>';
        }
        
        if (!empty($posts)) {
            $output .= '<ul class="noveltool-posts-list">';
            foreach ($posts as $post) {
                $output .= '<li>';
                $output .= '<a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a>';
                if ($show_excerpt && !empty($post->post_excerpt)) {
                    $output .= '<div class="noveltool-post-excerpt">' . esc_html($post->post_excerpt) . '</div>';
                }
                $output .= '</li>';
            }
            $output .= '</ul>';
        } else {
            $output .= '<p>' . __('このゲームに投稿はありません。', 'noveltool') . '</p>';
        }
        
        $output .= '</div>';
        
    } else {
        // すべてのゲームの投稿を表示
        $grouped_posts = noveltool_get_posts_grouped_by_game();
        $output = '<div class="noveltool-all-games">';
        
        foreach ($grouped_posts as $game_title => $posts) {
            $output .= '<div class="noveltool-game-posts">';
            $output .= '<h3 class="noveltool-game-title">' . esc_html($game_title) . '</h3>';
            $output .= '<ul class="noveltool-posts-list">';
            
            $post_count = 0;
            foreach ($posts as $post) {
                if ($limit > 0 && $post_count >= $limit) break;
                
                $output .= '<li>';
                $output .= '<a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a>';
                if ($show_excerpt && !empty($post->post_excerpt)) {
                    $output .= '<div class="noveltool-post-excerpt">' . esc_html($post->post_excerpt) . '</div>';
                }
                $output .= '</li>';
                $post_count++;
            }
            
            $output .= '</ul></div>';
        }
        
        $output .= '</div>';
    }
    
    return $output;
}
add_shortcode('noveltool_game_posts', 'noveltool_game_posts_shortcode');

/**
 * 管理画面での投稿一覧にゲームタイトルでフィルタリング機能を追加
 */
function noveltool_add_game_title_filter() {
    global $typenow;
    
    if ($typenow === 'novel_game') {
        $game_titles = noveltool_get_all_game_titles();
        $selected = isset($_GET['game_title_filter']) ? $_GET['game_title_filter'] : '';
        
        echo '<select name="game_title_filter">';
        echo '<option value="">' . __('すべてのゲーム', 'noveltool') . '</option>';
        
        foreach ($game_titles as $game_title) {
            $selected_attr = selected($selected, $game_title, false);
            echo '<option value="' . esc_attr($game_title) . '"' . $selected_attr . '>' . esc_html($game_title) . '</option>';
        }
        
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'noveltool_add_game_title_filter');

/**
 * 管理画面でのゲームタイトルフィルタリング処理
 */
function noveltool_filter_posts_by_game_title($query) {
    global $pagenow;
    
    if (is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'novel_game') {
        if (isset($_GET['game_title_filter']) && !empty($_GET['game_title_filter'])) {
            $game_title = sanitize_text_field($_GET['game_title_filter']);
            
            $query->set('meta_query', array(
                array(
                    'key' => '_game_title',
                    'value' => $game_title,
                    'compare' => '='
                )
            ));
        }
    }
}
add_action('pre_get_posts', 'noveltool_filter_posts_by_game_title');

/**
 * 管理画面用のゲーム別投稿一覧ページを追加
 */
function noveltool_add_game_posts_admin_page() {
    add_submenu_page(
        'edit.php?post_type=novel_game',
        __('ゲーム別投稿一覧', 'noveltool'),
        __('ゲーム別一覧', 'noveltool'),
        'manage_options',
        'noveltool-game-posts',
        'noveltool_game_posts_admin_page'
    );
}
add_action('admin_menu', 'noveltool_add_game_posts_admin_page');

/**
 * 管理画面でのゲーム別投稿一覧ページの内容
 */
function noveltool_game_posts_admin_page() {
    $grouped_posts = noveltool_get_posts_grouped_by_game();
    
    echo '<div class="wrap">';
    echo '<h1>' . __('ゲーム別投稿一覧', 'noveltool') . '</h1>';
    
    if (empty($grouped_posts)) {
        echo '<p>' . __('投稿がありません。', 'noveltool') . '</p>';
        echo '</div>';
        return;
    }
    
    foreach ($grouped_posts as $game_title => $posts) {
        echo '<div class="noveltool-admin-game-section" style="margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<h2 style="margin-top: 0;">' . esc_html($game_title) . ' (' . count($posts) . '件)</h2>';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('タイトル', 'noveltool') . '</th>';
        echo '<th>' . __('作成日', 'noveltool') . '</th>';
        echo '<th>' . __('更新日', 'noveltool') . '</th>';
        echo '<th>' . __('操作', 'noveltool') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($posts as $post) {
            echo '<tr>';
            echo '<td><strong><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></strong></td>';
            echo '<td>' . get_the_date('Y/m/d H:i', $post->ID) . '</td>';
            echo '<td>' . get_the_modified_date('Y/m/d H:i', $post->ID) . '</td>';
            echo '<td>';
            echo '<a href="' . get_edit_post_link($post->ID) . '">' . __('編集', 'noveltool') . '</a> | ';
            echo '<a href="' . get_permalink($post->ID) . '" target="_blank">' . __('表示', 'noveltool') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    echo '</div>';
}
?>