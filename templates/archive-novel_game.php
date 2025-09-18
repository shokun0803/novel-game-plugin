<?php
/**
 * アーカイブテンプレート - ノベルゲーム一覧
 * 
 * 作成済みのノベルゲームを一覧表示し、選択可能にする
 * 
 * @package NovelGamePlugin
 * @since 1.1.0
 */

// テーマに依存しない自己完結型のHTMLヘッダー
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title( '|', true, 'right' ); ?><?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="novel-game-archive" class="novel-game-archive-container">
    <header class="archive-header">
        <h1 class="archive-title"><?php _e('ノベルゲーム一覧', 'novel-game-plugin'); ?></h1>
        <p class="archive-description"><?php _e('プレイしたいゲームを選択してください', 'novel-game-plugin'); ?></p>
    </header>

    <div class="novel-games-grid">
        <?php
        // ゲームタイトルごとにグループ化して最初のシーンを取得
        global $wpdb;
        
        // 新しいマルチゲーム対応のゲーム一覧を取得
        $games_from_option = noveltool_get_all_games();
        
        if ( ! empty( $games_from_option ) ) {
            // 新しい形式：オプションからゲーム一覧を取得
            $games = array();
            foreach ( $games_from_option as $game_data ) {
                // 各ゲームの最初のシーンを取得（ゲームの開始点）
                $first_scene_query = $wpdb->prepare("
                    SELECT p.ID, p.post_title
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'novel_game'
                    AND p.post_status = 'publish'
                    AND pm.meta_key = '_game_title'
                    AND pm.meta_value = %s
                    ORDER BY p.post_date ASC
                    LIMIT 1
                ", $game_data['title']);
                
                $first_scene = $wpdb->get_row($first_scene_query);
                
                if ( $first_scene ) {
                    // ゲーム内の総シーン数を取得
                    $total_scenes_query = $wpdb->prepare("
                        SELECT COUNT(p.ID) as scene_count
                        FROM {$wpdb->posts} p
                        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                        WHERE p.post_type = 'novel_game'
                        AND p.post_status = 'publish'
                        AND pm.meta_key = '_game_title'
                        AND pm.meta_value = %s
                    ", $game_data['title']);
                    
                    $scene_count_result = $wpdb->get_row($total_scenes_query);
                    $scene_count = $scene_count_result ? intval($scene_count_result->scene_count) : 0;
                    
                    $games[] = (object) array(
                        'game_title' => $game_data['title'],
                        'game_description' => isset($game_data['description']) ? $game_data['description'] : '',
                        'game_title_image' => isset($game_data['title_image']) ? $game_data['title_image'] : '',
                        'first_scene_id' => $first_scene->ID,
                        'first_scene_title' => $first_scene->post_title,
                        'scene_count' => $scene_count
                    );
                }
            }
        } else {
            // 後方互換性：メタデータからゲームタイトルを取得
            $games_query = "
                SELECT 
                    pm.meta_value as game_title,
                    MIN(p.ID) as first_scene_id,
                    COUNT(p.ID) as scene_count,
                    MIN(p.post_title) as first_scene_title
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_game_title' 
                AND pm.meta_value != ''
                AND p.post_type = 'novel_game'
                AND p.post_status = 'publish'
                GROUP BY pm.meta_value
                ORDER BY pm.meta_value ASC
            ";
            $games = $wpdb->get_results($games_query);
            
            // 後方互換性のために必要なプロパティを追加
            foreach ( $games as &$game ) {
                $game->game_description = '';
                $game->game_title_image = '';
            }
        }
        
        if ($games) :
            foreach ($games as $game) :
                // 最初のシーンの情報を取得
                $first_scene = get_post($game->first_scene_id);
                if (!$first_scene) continue;
                
                $game_title = esc_html($game->game_title);
                $game_description = isset($game->game_description) ? esc_html($game->game_description) : '';
                $scene_count = intval($game->scene_count);
                
                // ゲーム専用のタイトル画像またはデフォルトの背景画像を使用
                $game_image = '';
                if ( isset($game->game_title_image) && !empty($game->game_title_image) ) {
                    $game_image = $game->game_title_image;
                } else {
                    // タイトル画像がない場合は最初のシーンの背景画像を使用
                    $game_image = get_post_meta($game->first_scene_id, '_background_image', true);
                }
                ?>
                <div class="novel-game-card noveltool-game-item" 
                     data-game-url="<?php echo esc_url(add_query_arg('shortcode', '1', get_permalink($game->first_scene_id))); ?>" 
                     data-game-title="<?php echo esc_attr($game_title); ?>"
                     data-game-description="<?php echo esc_attr($game_description); ?>"
                     data-game-image="<?php echo esc_attr($game_image); ?>"
                     data-game-subtitle=""
                     data-scene-count="<?php echo esc_attr($scene_count); ?>">
                    <div class="game-thumbnail">
                        <?php if ($game_image) : ?>
                            <img src="<?php echo esc_url($game_image); ?>" alt="<?php echo esc_attr($game_title); ?>" class="game-bg-image">
                        <?php else : ?>
                            <div class="game-placeholder">
                                <span class="placeholder-text"><?php _e('No Image', 'novel-game-plugin'); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="game-overlay">
                            <div class="game-info">
                                <h3 class="game-title"><?php echo $game_title; ?></h3>
                                <?php if ( $game_description ) : ?>
                                    <p class="game-description"><?php echo wp_trim_words($game_description, 20, '...'); ?></p>
                                <?php endif; ?>
                                <p class="scene-count"><?php printf(__('%d シーン', 'novel-game-plugin'), $scene_count); ?></p>
                                <p class="first-scene-info"><?php printf(__('開始: %s', 'novel-game-plugin'), esc_html($game->first_scene_title)); ?></p>
                            </div>
                            <div class="play-button">
                                <span><?php _e('ゲーム開始', 'novel-game-plugin'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            endforeach;
        else :
            ?>
            <div class="no-games-message">
                <p><?php _e('まだゲームが作成されていません。', 'novel-game-plugin'); ?></p>
                <?php if (current_user_can('edit_posts')) : ?>
                    <p><a href="<?php echo admin_url('post-new.php?post_type=novel_game'); ?>" class="button"><?php _e('新しいゲームを作成', 'novel-game-plugin'); ?></a></p>
                <?php endif; ?>
            </div>
            <?php
        endif;
        ?>
    </div>
</div>

<!-- モーダルオーバーレイ（ゲーム表示用・タイトル画面統合版） -->
<div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;">
    <!-- モーダルコンテンツ -->
    <div id="novel-game-modal-content" class="novel-game-modal-content">
        <!-- ゲーム閉じるボタン -->
        <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="<?php echo esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ); ?>" title="<?php echo esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ); ?>">
            <span class="close-icon">×</span>
        </button>
        
        <!-- タイトル画面 -->
        <div id="novel-title-screen" class="novel-title-screen" style="display: none;">
            <div class="novel-title-content">
                <h2 id="novel-title-main" class="novel-title-main"></h2>
                <p id="novel-title-subtitle" class="novel-title-subtitle"></p>
                <p id="novel-title-description" class="novel-title-description"></p>
                <div class="novel-title-buttons">
                    <button id="novel-title-start-new" class="novel-title-btn novel-title-start-btn">
                        <?php echo esc_html__( '最初から開始', 'novel-game-plugin' ); ?>
                    </button>
                    <button id="novel-title-continue" class="novel-title-btn novel-title-continue-btn" style="display: none;">
                        <?php echo esc_html__( '続きから始める', 'novel-game-plugin' ); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- ゲームコンテナ -->
        <div id="novel-game-container" class="novel-game-container">
            <!-- ゲーム内容は動的に読み込まれます -->
        </div>
    </div>
</div>

<style>
.novel-game-archive-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.archive-header {
    text-align: center;
    margin-bottom: 40px;
}

.archive-title {
    font-size: 2.5em;
    margin-bottom: 10px;
    color: #333;
}

.archive-description {
    font-size: 1.2em;
    color: #666;
    margin-bottom: 0;
}

.novel-games-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 30px;
}

.novel-game-card {
    cursor: pointer;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    background: #fff;
}

.novel-game-card:hover,
.novel-game-card.hovered {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.game-thumbnail {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
}

.game-bg-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.novel-game-card:hover .game-bg-image,
.novel-game-card.hovered .game-bg-image {
    transform: scale(1.05);
}

.game-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.placeholder-text {
    color: white;
    font-size: 1.1em;
    font-weight: bold;
}

.game-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 70%, transparent 100%);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.game-info {
    flex: 1;
}

.game-title {
    font-size: 1.4em;
    margin: 0 0 8px 0;
    font-weight: bold;
    line-height: 1.2;
}

.game-description {
    font-size: 0.9em;
    margin: 0 0 8px 0;
    opacity: 0.9;
    line-height: 1.3;
}

.scene-count,
.first-scene-info {
    font-size: 0.85em;
    margin: 2px 0;
    opacity: 0.8;
}

.play-button {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid white;
    border-radius: 25px;
    padding: 8px 16px;
    font-weight: bold;
    font-size: 0.9em;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.novel-game-card:hover .play-button,
.novel-game-card.hovered .play-button {
    background: white;
    color: #333;
    transform: scale(1.05);
}

.no-games-message {
    text-align: center;
    padding: 60px 20px;
    background: #f9f9f9;
    border-radius: 12px;
    margin-top: 40px;
}

.no-games-message p {
    font-size: 1.1em;
    color: #666;
    margin-bottom: 20px;
}

.no-games-message .button {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    transition: background 0.3s ease;
}

.no-games-message .button:hover {
    background: #5a67d8;
}

/* レスポンシブデザイン */
@media (max-width: 768px) {
    .novel-games-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .archive-title {
        font-size: 2em;
    }
    
    .archive-description {
        font-size: 1em;
    }
    
    .game-thumbnail {
        height: 180px;
    }
    
    .game-overlay {
        padding: 15px;
    }
    
    .game-title {
        font-size: 1.2em;
    }
}

@media (max-width: 480px) {
    .novel-game-archive-container {
        padding: 15px;
    }
    
    .game-thumbnail {
        height: 160px;
    }
    
    .game-overlay {
        padding: 12px;
    }
    
    .play-button {
        padding: 6px 12px;
        font-size: 0.8em;
    }
}
</style>

<?php wp_footer(); ?>
</body>
</html>