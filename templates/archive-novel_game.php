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

    <div class="novel-games-grid">
        <?php
        // ゲームタイトルごとにグループ化するためのクエリ
        global $wpdb;
        
        // 重複を除いてゲームタイトルを取得し、各ゲームの最初のシーンIDも取得
        $games_query = "
            SELECT 
                pm.meta_value as game_title,
                MIN(p.ID) as first_scene_id,
                COUNT(p.ID) as scene_count
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

        if ($games) :
            foreach ($games as $game) :
                // 最初のシーンの情報を取得
                $first_scene = get_post($game->first_scene_id);
                if (!$first_scene) continue;
                
                $game_title = esc_html($game->game_title);
                $background_image = get_post_meta($game->first_scene_id, '_background_image', true);
                $scene_count = intval($game->scene_count);
                ?>
                <div class="novel-game-card" data-game-url="<?php echo esc_url(get_permalink($game->first_scene_id)); ?>">
                    <div class="game-thumbnail">
                        <?php if ($background_image) : ?>
                            <img src="<?php echo esc_url($background_image); ?>" alt="<?php echo esc_attr($game_title); ?>" class="game-bg-image">
                        <?php else : ?>
                            <div class="game-placeholder">
                                <span class="placeholder-text"><?php _e('No Image', 'novel-game-plugin'); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="game-overlay">
                            <div class="game-info">
                                <h3 class="game-title"><?php echo $game_title; ?></h3>
                                <p class="scene-count"><?php printf(__('%d シーン', 'novel-game-plugin'), $scene_count); ?></p>
                            </div>
                            <div class="play-button">
                                <span><?php _e('スタート', 'novel-game-plugin'); ?></span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ゲームカードのクリックイベント
    const gameCards = document.querySelectorAll('.novel-game-card');
    
    gameCards.forEach(function(card) {
        card.addEventListener('click', function() {
            const gameUrl = this.getAttribute('data-game-url');
            if (gameUrl) {
                // 最初のシーンのパーマリンクに遷移
                window.location.href = gameUrl;
            }
        });
        
        // ホバー効果
        card.addEventListener('mouseenter', function() {
            this.classList.add('hovered');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('hovered');
        });
    });
});
</script>

<?php get_footer(); ?>