<?php
/**
 * Gutenbergブロック機能
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ノベルゲームブロックの初期化
 *
 * Block API v3 を前提とするため、最低対応 WordPress バージョンは 6.3 です。
 *
 * @since 1.2.0
 */
function noveltool_init_blocks() {
    // ブロックエディタの環境でのみ実行
    if ( ! function_exists( 'register_block_type' ) ) {
        return;
    }

    // ブロック用スクリプトの登録
    wp_register_script(
        'noveltool-blocks',
        NOVEL_GAME_PLUGIN_URL . 'js/blocks.js',
        noveltool_get_block_editor_script_dependencies(),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );

    // ブロック用スタイルの登録
    wp_register_style(
        'noveltool-blocks-style',
        NOVEL_GAME_PLUGIN_URL . 'css/blocks.css',
        array(),
        NOVEL_GAME_PLUGIN_VERSION
    );

    // ブロックタイプの登録
    register_block_type( 'noveltool/game-list', array(
        'api_version'    => 3,
        'editor_script' => 'noveltool-blocks',
        'editor_style'  => 'noveltool-blocks-style',
        'render_callback' => 'noveltool_render_game_list_block',
        'attributes'    => noveltool_get_game_list_block_attributes(),
    ) );
}
add_action( 'init', 'noveltool_init_blocks' );

/**
 * ブロック用スクリプトの依存関係を取得
 *
 * WordPress 6.3 以降の Block API v3 前提で wp-block-editor を優先しつつ、
 * 既存環境では wp-editor にフォールバックする。
 *
 * @return array
 * @since 1.5.0
 */
function noveltool_get_block_editor_script_dependencies() {
    $dependencies = array(
        'wp-blocks',
        'wp-element',
        'wp-components',
        'wp-i18n',
        'wp-data',
        'wp-api-fetch',
    );

    if ( wp_script_is( 'wp-block-editor', 'registered' ) ) {
        $dependencies[] = 'wp-block-editor';
    } elseif ( wp_script_is( 'wp-editor', 'registered' ) ) {
        $dependencies[] = 'wp-editor';
    } else {
        $dependencies[] = 'wp-block-editor';
    }

    return $dependencies;
}

/**
 * ゲーム一覧ブロック属性を取得
 *
 * @return array
 * @since 1.5.0
 */
function noveltool_get_game_list_block_attributes() {
    return array(
        'gameType'        => array(
            'type'    => 'string',
            'default' => 'all',
        ),
        'gameTitle'       => array(
            'type'    => 'string',
            'default' => '',
        ),
        'showCount'       => array(
            'type'    => 'boolean',
            'default' => true,
        ),
        'showDescription' => array(
            'type'    => 'boolean',
            'default' => false,
        ),
        'columns'         => array(
            'type'    => 'number',
            'default' => 3,
        ),
        'orderby'         => array(
            'type'    => 'string',
            'default' => 'title',
        ),
        'order'           => array(
            'type'    => 'string',
            'default' => 'ASC',
        ),
    );
}

/**
 * ゲーム一覧ブロックのレンダリング
 *
 * @param array $attributes ブロックの属性
 * @return string レンダリング結果
 * @since 1.2.0
 */
function noveltool_render_game_list_block( $attributes ) {
    // ショートコードの属性配列を作成
    $shortcode_atts = array();
    
    if ( $attributes['gameType'] === 'single' && ! empty( $attributes['gameTitle'] ) ) {
        // 個別ゲーム用のショートコードは従来の [novel_game_posts] を使用
        $shortcode_atts['game_title'] = $attributes['gameTitle'];
        return noveltool_game_posts_shortcode( $shortcode_atts );
    } else {
        // ゲーム一覧用は [novel_game_list] を使用
        if ( isset( $attributes['showCount'] ) ) {
            $shortcode_atts['show_count'] = $attributes['showCount'] ? 'true' : 'false';
        }
        if ( isset( $attributes['showDescription'] ) ) {
            $shortcode_atts['show_description'] = $attributes['showDescription'] ? 'true' : 'false';
        }
        if ( isset( $attributes['columns'] ) ) {
            $shortcode_atts['columns'] = $attributes['columns'];
        }
        if ( isset( $attributes['orderby'] ) ) {
            $shortcode_atts['orderby'] = $attributes['orderby'];
        }
        if ( isset( $attributes['order'] ) ) {
            $shortcode_atts['order'] = $attributes['order'];
        }
        
        return noveltool_game_list_shortcode( $shortcode_atts );
    }
}

/**
 * ブロック用のREST APIエンドポイントを登録
 *
 * @since 1.2.0
 */
function noveltool_register_block_rest_routes() {
    register_rest_route( 'noveltool/v1', '/games', array(
        'methods' => 'GET',
        'callback' => 'noveltool_get_games_for_block',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        }
    ) );
}
add_action( 'rest_api_init', 'noveltool_register_block_rest_routes' );

/**
 * ブロックエディタ用のゲーム一覧を取得
 *
 * @param WP_REST_Request $request REST APIリクエスト
 * @return WP_REST_Response REST APIレスポンス
 * @since 1.2.0
 */
function noveltool_get_games_for_block( $request ) {
    $games = array();
    
    // 全ゲーム一覧オプション
    $games[] = array(
        'value' => '',
        'label' => __( 'All Games List', 'novel-game-plugin' )
    );
    
    // 個別ゲームタイトル一覧を取得
    $game_titles = noveltool_get_all_game_titles();
    
    if ( ! empty( $game_titles ) ) {
        foreach ( $game_titles as $title ) {
            $games[] = array(
                'value' => $title,
                'label' => $title
            );
        }
    }
    
    return rest_ensure_response( $games );
}

/**
 * ブロックエディタ用のスクリプトに翻訳を提供
 *
 * @since 1.2.0
 */
function noveltool_enqueue_block_assets() {
    if ( is_admin() ) {
        wp_set_script_translations( 'noveltool-blocks', 'novel-game-plugin' );
    }
}
add_action( 'enqueue_block_assets', 'noveltool_enqueue_block_assets' );
