<?php
/**
 * アーカイブテンプレート - ノベルゲーム一覧
 * 
 * 作成済みのノベルゲームを一覧表示し、選択可能にする
 * 
 * @package NovelGamePlugin
 * @since 1.1.0
 */

get_header(); ?>

<div id="novel-game-archive" class="novel-game-archive-container">
    <header class="archive-header">
        <h1 class="archive-title"><?php _e('ノベルゲーム一覧', 'novel-game-plugin'); ?></h1>
        <p class="archive-description"><?php _e('プレイしたいゲームを選択してください', 'novel-game-plugin'); ?></p>
    </header>

    <div class="noveltool-game-list-grid noveltool-columns-3">
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
                <div class="noveltool-game-list-item">
                    <?php if ($game_image) : ?>
                        <div class="noveltool-game-thumbnail">
                            <img src="<?php echo esc_url($game_image); ?>" alt="<?php echo esc_attr($game_title); ?>" />
                        </div>
                    <?php endif; ?>
                    
                    <div class="noveltool-game-content">
                        <h3 class="noveltool-game-title">
                            <?php echo esc_html($game_title); ?>
                        </h3>
                        
                        <?php if ( $game_description ) : ?>
                            <p class="noveltool-game-description"><?php echo esc_html(mb_substr($game_description, 0, 120)); 
                            if ( mb_strlen($game_description) > 120 ) {
                                echo '...';
                            } ?></p>
                        <?php endif; ?>
                        
                        <p class="noveltool-game-count"><?php printf(__('%d シーン', 'novel-game-plugin'), $scene_count); ?></p>
                        
                        <div class="noveltool-game-actions">
                            <button class="noveltool-game-select-button" 
                                data-game-id="<?php echo esc_attr($game->first_scene_id); ?>" 
                                data-game-url="<?php echo esc_url(get_permalink($game->first_scene_id)); ?>" 
                                data-game-title="<?php echo esc_attr($game_title); ?>" 
                                data-game-subtitle="<?php echo esc_attr($game_description ? wp_trim_words($game_description, 5, '') : ''); ?>" 
                                data-game-description="<?php echo esc_attr($game_description); ?>" 
                                data-game-image="<?php echo esc_url($game_image); ?>">
                                <?php _e('選択', 'novel-game-plugin'); ?>
                            </button>
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

<!-- ゲーム選択モーダル（Issue #50対応） -->
<div id="game-selection-modal-overlay" class="game-selection-modal-overlay" style="display: none;">
    <div id="game-selection-modal-content" class="game-selection-modal-content">
        <button id="game-selection-close-btn" class="game-selection-close-btn" aria-label="<?php echo esc_attr__( '閉じる', 'novel-game-plugin' ); ?>" title="<?php echo esc_attr__( '閉じる', 'novel-game-plugin' ); ?>">
            <span class="close-icon">×</span>
        </button>
        <div class="game-selection-modal-body">
            <div class="game-selection-image-container">
                <img id="game-selection-image" class="game-selection-image" src="" alt="" />
            </div>
            <div class="game-selection-info">
                <h2 id="game-selection-title" class="game-selection-title"></h2>
                <h3 id="game-selection-subtitle" class="game-selection-subtitle"></h3>
                <p id="game-selection-description" class="game-selection-description"></p>
                <div class="game-selection-actions">
                    <button id="start-new-game-btn" class="game-action-button start-new-game-btn">
                        <?php echo esc_html__( 'ゲーム開始', 'novel-game-plugin' ); ?>
                    </button>
                    <button id="resume-game-btn" class="game-action-button resume-game-btn" style="display: none;">
                        <?php echo esc_html__( '途中から始める', 'novel-game-plugin' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- モーダルオーバーレイ（ゲーム表示用） -->
<div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;">
    <!-- モーダルコンテンツ -->
    <div id="novel-game-modal-content" class="novel-game-modal-content">
        <!-- ゲーム閉じるボタン -->
        <button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="<?php echo esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ); ?>" title="<?php echo esc_attr__( 'ゲームを閉じる', 'novel-game-plugin' ); ?>">
            <span class="close-icon">×</span>
        </button>
        
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

/* 統一されたゲーム一覧スタイル */
.noveltool-game-list-grid {
    display: grid;
    gap: 30px;
    margin-top: 30px;
}

.noveltool-columns-3 {
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
}

.noveltool-game-list-item {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e1e8ed;
}

.noveltool-game-list-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.noveltool-game-thumbnail {
    width: 100%;
    height: 200px;
    overflow: hidden;
    position: relative;
}

.noveltool-game-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.noveltool-game-list-item:hover .noveltool-game-thumbnail img {
    transform: scale(1.05);
}

.noveltool-game-content {
    padding: 20px;
}

.noveltool-game-title {
    margin: 0 0 12px 0;
    font-size: 1.3em;
    font-weight: bold;
    color: #1a1a1a;
    line-height: 1.3;
}

.noveltool-game-description {
    color: #666;
    font-size: 0.9em;
    line-height: 1.5;
    margin: 8px 0;
}

.noveltool-game-count {
    color: #888;
    font-size: 0.85em;
    margin: 8px 0;
}

.noveltool-game-actions {
    margin-top: 15px;
    text-align: center;
}

.noveltool-game-select-button {
    background: #0073aa;
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    min-width: 100px;
}

.noveltool-game-select-button:hover {
    background: #005a87;
    color: white;
    transform: translateY(-1px);
}

/* ゲーム選択モーダルのスタイル */
.game-selection-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.8);
    z-index: 2147483600;
    display: flex;
    align-items: center;
    justify-content: center;
}

.game-selection-modal-content {
    background: white;
    border-radius: 16px;
    max-width: 90vw;
    max-height: 90vh;
    width: 800px;
    overflow: hidden;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.game-selection-close-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: background 0.3s ease;
}

.game-selection-close-btn:hover {
    background: rgba(0, 0, 0, 0.9);
}

.game-selection-modal-body {
    display: flex;
    min-height: 400px;
}

.game-selection-image-container {
    width: 50%;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.game-selection-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.game-selection-info {
    width: 50%;
    padding: 32px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.game-selection-title {
    font-size: 1.8em;
    font-weight: bold;
    margin: 0 0 8px 0;
    color: #1a1a1a;
}

.game-selection-subtitle {
    font-size: 1.1em;
    color: #666;
    margin: 0 0 16px 0;
    font-weight: normal;
}

.game-selection-description {
    color: #444;
    line-height: 1.6;
    margin: 0 0 24px 0;
    flex-grow: 1;
}

.game-selection-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.game-action-button {
    padding: 14px 28px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: none;
}

.start-new-game-btn {
    background: #0073aa;
    color: white;
}

.start-new-game-btn:hover {
    background: #005a87;
    transform: translateY(-1px);
}

.resume-game-btn {
    background: #46b450;
    color: white;
}

.resume-game-btn:hover {
    background: #3d9c46;
    transform: translateY(-1px);
}

.no-games-message {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-games-message .button {
    background: #0073aa;
    color: white;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-block;
    margin-top: 16px;
}

.no-games-message .button:hover {
    background: #005a87;
    color: white;
}

/* レスポンシブデザイン */
@media (max-width: 768px) {
    .noveltool-columns-3 {
        grid-template-columns: 1fr;
    }
    
    .game-selection-modal-content {
        width: 95vw;
        margin: 20px;
    }
    
    .game-selection-modal-body {
        flex-direction: column;
        min-height: auto;
    }
    
    .game-selection-image-container,
    .game-selection-info {
        width: 100%;
    }
    
    .game-selection-image-container {
        height: 200px;
    }
}
</style>

<?php get_footer(); ?>