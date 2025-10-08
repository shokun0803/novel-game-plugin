<?php
/**
 * novel_game投稿タイプのリビジョン機能実装（統合カスタムフィールド方式）
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * リビジョン管理対象のカスタムフィールドキーを取得
 *
 * @return array カスタムフィールドキーの配列
 * @since 1.2.0
 */
function noveltool_get_revision_meta_keys() {
    return array(
        '_background_image',
        '_character_image',
        '_character_left',
        '_character_center',
        '_character_right',
        '_character_left_name',
        '_character_center_name',
        '_character_right_name',
        '_dialogue_text',
        '_dialogue_texts',
        '_dialogue_backgrounds',
        '_dialogue_speakers',
        '_dialogue_flag_conditions',
        '_choices',
        '_game_title',
        '_is_ending',
        '_ending_text',
        '_scene_arrival_flags',
    );
}

/**
 * 投稿のカスタムメタデータを統合JSON文字列として取得
 *
 * @param int $post_id 投稿ID
 * @return string JSON文字列
 * @since 1.2.0
 */
function noveltool_get_unified_custom_meta( $post_id ) {
    $meta_keys = noveltool_get_revision_meta_keys();
    $unified_data = array();
    
    foreach ( $meta_keys as $meta_key ) {
        $value = get_post_meta( $post_id, $meta_key, true );
        
        // 値が存在する場合のみ保存（空文字列や0はスキップしない）
        if ( $value !== '' && $value !== false && $value !== null ) {
            $unified_data[ $meta_key ] = $value;
        }
    }
    
    // JSON文字列に変換（Unicode文字を正しく扱う）
    return wp_json_encode( $unified_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/**
 * 統合JSON文字列から個別カスタムメタデータに復元
 *
 * @param int    $post_id      投稿ID
 * @param string $unified_json 統合JSON文字列
 * @return bool 成功した場合true
 * @since 1.2.0
 */
function noveltool_restore_unified_custom_meta( $post_id, $unified_json ) {
    if ( empty( $unified_json ) || ! is_string( $unified_json ) ) {
        return false;
    }
    
    // JSONデコード
    $unified_data = json_decode( $unified_json, true );
    
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $unified_data ) ) {
        return false;
    }
    
    // 各カスタムフィールドに復元
    $meta_keys = noveltool_get_revision_meta_keys();
    
    foreach ( $meta_keys as $meta_key ) {
        if ( isset( $unified_data[ $meta_key ] ) ) {
            update_post_meta( $post_id, $meta_key, $unified_data[ $meta_key ] );
        } else {
            // 統合データに存在しないフィールドは削除
            delete_post_meta( $post_id, $meta_key );
        }
    }
    
    return true;
}

/**
 * カスタムメタデータの変更を検出
 *
 * @param int $post_id 投稿ID
 * @return bool 変更があればtrue
 * @since 1.2.0
 */
function noveltool_has_custom_meta_changed( $post_id ) {
    // 現在の統合データを取得
    $current_unified = noveltool_get_unified_custom_meta( $post_id );
    
    // 保存済みの統合データを取得
    $saved_unified = get_post_meta( $post_id, '_noveltool_unified_meta', true );
    
    // 初回保存の場合は変更ありとみなす
    if ( empty( $saved_unified ) ) {
        return true;
    }
    
    // データが異なる場合は変更あり
    return $current_unified !== $saved_unified;
}

/**
 * 投稿保存時に統合カスタムフィールドを更新し、リビジョンとの同期を確保
 *
 * @param int $post_id 投稿ID
 * @since 1.2.0
 */
function noveltool_save_unified_custom_meta( $post_id ) {
    // 自動保存の場合は処理しない
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    // リビジョン保存時はスキップ（無限ループ防止）
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    // novel_game投稿タイプでない場合はスキップ
    if ( get_post_type( $post_id ) !== 'novel_game' ) {
        return;
    }
    
    // 権限チェック
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    // 統合データを生成
    $unified_json = noveltool_get_unified_custom_meta( $post_id );
    
    // 統合データをカスタムフィールドとして保存
    update_post_meta( $post_id, '_noveltool_unified_meta', $unified_json );
}
add_action( 'save_post_novel_game', 'noveltool_save_unified_custom_meta', 20 );

/**
 * WordPressリビジョン機能に統合カスタムフィールドを登録
 *
 * @param array $fields リビジョンフィールド
 * @return array 更新されたリビジョンフィールド
 * @since 1.2.0
 */
function noveltool_add_revision_fields( $fields ) {
    $fields['_noveltool_unified_meta'] = __( 'カスタムフィールドデータ', 'novel-game-plugin' );
    return $fields;
}
add_filter( 'wp_post_revision_fields', 'noveltool_add_revision_fields' );

/**
 * リビジョンに統合カスタムフィールドをコピー
 *
 * @param int $revision_id リビジョンID
 * @since 1.2.0
 */
function noveltool_save_revision_meta( $revision_id ) {
    $parent_id = wp_is_post_revision( $revision_id );
    
    if ( ! $parent_id ) {
        return;
    }
    
    // 親投稿の統合データを取得
    $unified_meta = get_post_meta( $parent_id, '_noveltool_unified_meta', true );
    
    if ( ! empty( $unified_meta ) ) {
        // リビジョンに統合データをコピー
        update_metadata( 'post', $revision_id, '_noveltool_unified_meta', $unified_meta );
    }
}
add_action( 'wp_insert_post', 'noveltool_save_revision_meta' );

/**
 * リビジョン復元時に統合カスタムフィールドを復元
 *
 * @param int $post_id     復元先の投稿ID
 * @param int $revision_id リビジョンID
 * @since 1.2.0
 */
function noveltool_restore_revision_meta( $post_id, $revision_id ) {
    // リビジョンから統合データを取得
    $unified_meta = get_metadata( 'post', $revision_id, '_noveltool_unified_meta', true );
    
    if ( ! empty( $unified_meta ) ) {
        // 統合データから個別カスタムフィールドに復元
        noveltool_restore_unified_custom_meta( $post_id, $unified_meta );
        
        // 復元後の統合データを保存（整合性確保）
        update_post_meta( $post_id, '_noveltool_unified_meta', $unified_meta );
    }
}
add_action( 'wp_restore_post_revision', 'noveltool_restore_revision_meta', 10, 2 );

/**
 * リビジョン比較画面でのカスタムフィールドデータ表示
 *
 * @param string $value       フィールドの値
 * @param string $field       フィールド名
 * @param object $compare_from 比較元のリビジョン
 * @param string $context     コンテキスト（'to' または 'from'）
 * @return string 表示用の値
 * @since 1.2.0
 */
function noveltool_revision_field_display( $value, $field, $compare_from, $context ) {
    if ( '_noveltool_unified_meta' !== $field ) {
        return $value;
    }
    
    // JSON文字列を整形して表示
    if ( ! empty( $value ) ) {
        $decoded = json_decode( $value, true );
        
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            $output = '<div class="noveltool-revision-data">';
            
            foreach ( $decoded as $key => $val ) {
                // フィールド名を日本語に変換
                $label = noveltool_get_field_label( $key );
                
                // 値の表示形式を整形
                $formatted_value = noveltool_format_field_value( $val );
                
                $output .= '<div class="noveltool-revision-field">';
                $output .= '<strong>' . esc_html( $label ) . ':</strong> ';
                $output .= '<span>' . wp_kses_post( $formatted_value ) . '</span>';
                $output .= '</div>';
            }
            
            $output .= '</div>';
            return $output;
        }
    }
    
    return __( 'データなし', 'novel-game-plugin' );
}
add_filter( 'wp_post_revision_field__noveltool_unified_meta', 'noveltool_revision_field_display', 10, 4 );

/**
 * カスタムフィールドキーを日本語ラベルに変換
 *
 * @param string $key カスタムフィールドキー
 * @return string 日本語ラベル
 * @since 1.2.0
 */
function noveltool_get_field_label( $key ) {
    $labels = array(
        '_background_image'         => __( '背景画像', 'novel-game-plugin' ),
        '_character_image'          => __( 'キャラクター画像（旧）', 'novel-game-plugin' ),
        '_character_left'           => __( '左キャラクター画像', 'novel-game-plugin' ),
        '_character_center'         => __( '中央キャラクター画像', 'novel-game-plugin' ),
        '_character_right'          => __( '右キャラクター画像', 'novel-game-plugin' ),
        '_character_left_name'      => __( '左キャラクター名', 'novel-game-plugin' ),
        '_character_center_name'    => __( '中央キャラクター名', 'novel-game-plugin' ),
        '_character_right_name'     => __( '右キャラクター名', 'novel-game-plugin' ),
        '_dialogue_text'            => __( 'セリフテキスト（旧）', 'novel-game-plugin' ),
        '_dialogue_texts'           => __( 'セリフテキスト', 'novel-game-plugin' ),
        '_dialogue_backgrounds'     => __( 'セリフ背景', 'novel-game-plugin' ),
        '_dialogue_speakers'        => __( 'セリフ話者', 'novel-game-plugin' ),
        '_dialogue_flag_conditions' => __( 'セリフフラグ条件', 'novel-game-plugin' ),
        '_choices'                  => __( '選択肢', 'novel-game-plugin' ),
        '_game_title'               => __( 'ゲームタイトル', 'novel-game-plugin' ),
        '_is_ending'                => __( 'エンディング', 'novel-game-plugin' ),
        '_ending_text'              => __( 'エンディングテキスト', 'novel-game-plugin' ),
        '_scene_arrival_flags'      => __( 'シーン到達時フラグ', 'novel-game-plugin' ),
    );
    
    return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
}

/**
 * カスタムフィールド値を表示用に整形
 *
 * @param mixed $value カスタムフィールド値
 * @return string 整形された値
 * @since 1.2.0
 */
function noveltool_format_field_value( $value ) {
    // 配列の場合
    if ( is_array( $value ) ) {
        $count = count( $value );
        return sprintf( __( '配列（%d要素）', 'novel-game-plugin' ), $count );
    }
    
    // 真偽値の場合
    if ( is_bool( $value ) ) {
        return $value ? __( 'はい', 'novel-game-plugin' ) : __( 'いいえ', 'novel-game-plugin' );
    }
    
    // 文字列の場合（長い場合は省略）
    if ( is_string( $value ) ) {
        if ( mb_strlen( $value ) > 50 ) {
            return esc_html( mb_substr( $value, 0, 50 ) ) . '...';
        }
        return esc_html( $value );
    }
    
    // その他
    return esc_html( (string) $value );
}

/**
 * リビジョン比較画面用のスタイルを追加
 *
 * @since 1.2.0
 */
function noveltool_revision_styles() {
    global $pagenow;
    
    if ( 'revision.php' === $pagenow ) {
        ?>
        <style>
        .noveltool-revision-data {
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .noveltool-revision-field {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .noveltool-revision-field:last-child {
            border-bottom: none;
        }
        .noveltool-revision-field strong {
            color: #333;
            min-width: 150px;
            display: inline-block;
        }
        .noveltool-revision-field span {
            color: #666;
        }
        </style>
        <?php
    }
}
add_action( 'admin_head', 'noveltool_revision_styles' );
