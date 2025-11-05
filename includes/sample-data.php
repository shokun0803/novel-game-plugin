<?php
/**
 * サンプルゲームデータの定義
 *
 * プラグイン有効化時に登録されるサンプルゲームのデータを管理
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * サンプルゲームのデータを取得
 *
 * @return array サンプルゲームのデータ構造
 * @since 1.2.0
 */
function noveltool_get_sample_game_data() {
    // プレースホルダー画像のSVG定義（定数として管理）
    $svg_bg_width = 800;
    $svg_bg_height = 600;
    $svg_bg_color = '#87CEEB';
    $svg_char_width = 300;
    $svg_char_height = 400;
    
    // プレースホルダー背景画像のURL
    $placeholder_bg = 'data:image/svg+xml;base64,' . base64_encode(
        sprintf(
            '<svg width="%d" height="%d" xmlns="http://www.w3.org/2000/svg">
                <rect width="%d" height="%d" fill="%s"/>
                <text x="%d" y="%d" font-size="24" text-anchor="middle" fill="#FFFFFF">%s</text>
            </svg>',
            $svg_bg_width,
            $svg_bg_height,
            $svg_bg_width,
            $svg_bg_height,
            $svg_bg_color,
            $svg_bg_width / 2,
            $svg_bg_height / 2,
            esc_html( __( 'Sample Background', 'novel-game-plugin' ) )
        )
    );
    
    // プレースホルダーキャラクター画像（左側 - Alice）
    $placeholder_char_left = 'data:image/svg+xml;base64,' . base64_encode(
        sprintf(
            '<svg width="%d" height="%d" xmlns="http://www.w3.org/2000/svg">
                <rect width="%d" height="%d" fill="#FFB6C1"/>
                <circle cx="%d" cy="100" r="50" fill="#FFFFFF"/>
                <rect x="75" y="160" width="150" height="200" fill="#FF69B4" rx="20"/>
                <text x="%d" y="380" font-size="14" text-anchor="middle" fill="#FFFFFF">%s</text>
            </svg>',
            $svg_char_width,
            $svg_char_height,
            $svg_char_width,
            $svg_char_height,
            $svg_char_width / 2,
            $svg_char_width / 2,
            esc_html( __( 'Character A', 'novel-game-plugin' ) )
        )
    );
    
    // プレースホルダーキャラクター画像（中央 - Bob）
    $placeholder_char_center = 'data:image/svg+xml;base64,' . base64_encode(
        sprintf(
            '<svg width="%d" height="%d" xmlns="http://www.w3.org/2000/svg">
                <rect width="%d" height="%d" fill="#87CEEB"/>
                <circle cx="%d" cy="100" r="50" fill="#FFFFFF"/>
                <rect x="75" y="160" width="150" height="200" fill="#4169E1" rx="20"/>
                <text x="%d" y="380" font-size="14" text-anchor="middle" fill="#FFFFFF">%s</text>
            </svg>',
            $svg_char_width,
            $svg_char_height,
            $svg_char_width,
            $svg_char_height,
            $svg_char_width / 2,
            $svg_char_width / 2,
            esc_html( __( 'Character B', 'novel-game-plugin' ) )
        )
    );
    
    // サンプルゲームの基本情報
    $game_data = array(
        'title'          => __( 'Sample Novel Game', 'novel-game-plugin' ),
        'description'    => __( 'This is a sample visual novel game to help you understand how to use this plugin. You can edit or delete this game at any time.', 'novel-game-plugin' ),
        'title_image'    => '',
        'game_over_text' => __( 'Game Over', 'novel-game-plugin' ),
    );
    
    // サンプルゲームのシーンデータ
    $scenes = array(
        // シーン1: オープニング
        array(
            'title'           => __( 'Sample Novel Game - Opening', 'novel-game-plugin' ),
            'background'      => $placeholder_bg,
            'character_left'  => $placeholder_char_left,
            'character_center' => '',
            'character_right' => '',
            'character_left_name' => __( 'Alice', 'novel-game-plugin' ),
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Welcome to this sample novel game!', 'novel-game-plugin' ),
                __( 'I am Alice. Nice to meet you.', 'novel-game-plugin' ),
                __( 'This game will show you how to create branching stories.', 'novel-game-plugin' ),
                __( 'Now, let me ask you a question...', 'novel-game-plugin' ),
            ),
            'dialogue_speakers' => array(
                '',
                'left',
                'left',
                'left',
            ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'I want to hear more about the story', 'novel-game-plugin' ),
                    'next' => 'scene_2',
                ),
                array(
                    'text' => __( 'I want to learn about choices', 'novel-game-plugin' ),
                    'next' => 'scene_3',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
        ),
        
        // シーン2: ストーリー説明
        array(
            'title'           => __( 'Sample Novel Game - About Story', 'novel-game-plugin' ),
            'background'      => $placeholder_bg,
            'character_left'  => $placeholder_char_left,
            'character_center' => $placeholder_char_center,
            'character_right' => '',
            'character_left_name' => __( 'Alice', 'novel-game-plugin' ),
            'character_center_name' => __( 'Bob', 'novel-game-plugin' ),
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Great choice! Let me introduce my friend Bob.', 'novel-game-plugin' ),
                __( 'Hi there! Nice to meet you.', 'novel-game-plugin' ),
                __( 'You can create rich stories with multiple characters.', 'novel-game-plugin' ),
                __( 'Each character can have different positions and expressions.', 'novel-game-plugin' ),
                __( 'This is the end of this branch. Thank you for playing!', 'novel-game-plugin' ),
            ),
            'dialogue_speakers' => array(
                'left',
                'center',
                'left',
                'center',
                'left',
            ),
            'dialogue_backgrounds' => array( '', '', '', '', '' ),
            'choices'         => array(),
            'is_ending'       => true,
            'ending_text'     => __( 'Story Path - End', 'novel-game-plugin' ),
        ),
        
        // シーン3: 選択肢説明
        array(
            'title'           => __( 'Sample Novel Game - About Choices', 'novel-game-plugin' ),
            'background'      => $placeholder_bg,
            'character_left'  => '',
            'character_center' => $placeholder_char_left,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => __( 'Alice', 'novel-game-plugin' ),
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Excellent! You are interested in branching stories.', 'novel-game-plugin' ),
                __( 'In visual novels, choices are very important.', 'novel-game-plugin' ),
                __( 'Your decisions will affect the story outcome.', 'novel-game-plugin' ),
                __( 'You can create multiple endings based on player choices.', 'novel-game-plugin' ),
                __( 'This is the end of this branch. Thank you for playing!', 'novel-game-plugin' ),
            ),
            'dialogue_speakers' => array(
                'center',
                'center',
                'center',
                'center',
                'center',
            ),
            'dialogue_backgrounds' => array( '', '', '', '', '' ),
            'choices'         => array(),
            'is_ending'       => true,
            'ending_text'     => __( 'Choice Path - End', 'novel-game-plugin' ),
        ),
    );
    
    return array(
        'game'   => $game_data,
        'scenes' => $scenes,
    );
}

/**
 * サンプルゲームをインストール
 *
 * プラグイン有効化時に実行され、サンプルゲームとシーンを作成
 *
 * @return bool 成功した場合true、失敗または既に存在する場合false
 * @since 1.2.0
 */
function noveltool_install_sample_game() {
    // サンプルゲームが既に存在するかチェック
    $sample_game_title = __( 'Sample Novel Game', 'novel-game-plugin' );
    
    // 既存のサンプルゲームをチェック（オプションで管理）
    $sample_installed = get_option( 'noveltool_sample_game_installed', false );
    
    if ( $sample_installed ) {
        return false; // 既にインストール済み
    }
    
    // サンプルデータを取得
    $sample_data = noveltool_get_sample_game_data();
    $game_data = $sample_data['game'];
    $scenes_data = $sample_data['scenes'];
    
    // ゲームを作成
    $game_id = noveltool_save_game( $game_data );
    
    if ( ! $game_id ) {
        return false; // ゲーム作成に失敗
    }
    
    // シーンを作成し、IDを記録
    $scene_ids = array();
    $creation_errors = array();
    
    foreach ( $scenes_data as $index => $scene_data ) {
        // 投稿を作成
        $post_data = array(
            'post_type'    => 'novel_game',
            'post_title'   => $scene_data['title'],
            'post_content' => '',
            'post_status'  => 'publish',
        );
        
        $post_id = wp_insert_post( $post_data );
        
        if ( is_wp_error( $post_id ) ) {
            // エラーをログに記録
            $creation_errors[] = sprintf(
                'Failed to create scene %d (%s): %s',
                $index + 1,
                $scene_data['title'],
                $post_id->get_error_message()
            );
            continue; // エラーの場合はスキップ
        }
        
        // シーンIDを記録
        $scene_ids[ 'scene_' . ( $index + 1 ) ] = $post_id;
        
        // メタデータを保存
        update_post_meta( $post_id, '_game_title', $game_data['title'] );
        update_post_meta( $post_id, '_background_image', $scene_data['background'] );
        update_post_meta( $post_id, '_character_left', $scene_data['character_left'] );
        update_post_meta( $post_id, '_character_center', $scene_data['character_center'] );
        update_post_meta( $post_id, '_character_right', $scene_data['character_right'] );
        update_post_meta( $post_id, '_character_left_name', $scene_data['character_left_name'] );
        update_post_meta( $post_id, '_character_center_name', $scene_data['character_center_name'] );
        update_post_meta( $post_id, '_character_right_name', $scene_data['character_right_name'] );
        
        // セリフデータを保存（新形式）
        update_post_meta( $post_id, '_dialogue_texts', wp_json_encode( $scene_data['dialogue_texts'], JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_dialogue_speakers', wp_json_encode( $scene_data['dialogue_speakers'], JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_dialogue_backgrounds', wp_json_encode( $scene_data['dialogue_backgrounds'], JSON_UNESCAPED_UNICODE ) );
        
        // エンディング設定
        update_post_meta( $post_id, '_is_ending', $scene_data['is_ending'] );
        update_post_meta( $post_id, '_ending_text', $scene_data['ending_text'] );
    }
    
    // 選択肢のリンクを更新（2回目のループで実際のIDに置き換え）
    foreach ( $scenes_data as $index => $scene_data ) {
        // シーンIDが存在しない場合はスキップ
        if ( ! isset( $scene_ids[ 'scene_' . ( $index + 1 ) ] ) ) {
            continue;
        }
        
        $post_id = $scene_ids[ 'scene_' . ( $index + 1 ) ];
        
        if ( ! empty( $scene_data['choices'] ) ) {
            $choices = array();
            
            foreach ( $scene_data['choices'] as $choice ) {
                if ( isset( $scene_ids[ $choice['next'] ] ) ) {
                    $choices[] = array(
                        'text' => $choice['text'],
                        'next' => $scene_ids[ $choice['next'] ],
                    );
                }
            }
            
            // JSON形式で選択肢を保存
            if ( ! empty( $choices ) ) {
                update_post_meta( $post_id, '_choices', wp_json_encode( $choices, JSON_UNESCAPED_UNICODE ) );
            }
        }
    }
    
    // エラーが発生していた場合はログに記録
    if ( ! empty( $creation_errors ) ) {
        // WordPressのデバッグログに記録（WP_DEBUGが有効な場合）
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( 'Novel Game Plugin - Sample game installation errors:' );
            foreach ( $creation_errors as $error ) {
                error_log( '  - ' . $error );
            }
        }
    }
    
    // すべてのシーンが正常に作成されたかチェック
    $expected_scenes = count( $scenes_data );
    $created_scenes = count( $scene_ids );
    
    if ( $created_scenes < $expected_scenes ) {
        // 一部のシーンの作成に失敗した場合でもインストール済みとしてマーク
        // （再試行を防ぐため）
        update_option( 'noveltool_sample_game_installed', true );
        
        // 不完全なインストールであることを記録
        update_option( 'noveltool_sample_game_install_incomplete', true );
        
        return false; // 不完全なインストール
    }
    
    // インストール済みフラグを設定
    update_option( 'noveltool_sample_game_installed', true );
    
    return true;
}
