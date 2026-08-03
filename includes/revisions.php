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
            // '_choices' はJSON文字列としてメタ保存されるため、そのまま統合JSONに
            // ネストすると二重エンコードとなり、update_post_meta/update_metadata内部の
            // wp_unslash() でエスケープ済みの引用符が失われて壊れる。配列に復元してから
            // 格納することで、統合JSON上は通常のネスト配列として扱う。
            if ( is_string( $value ) ) {
                $decoded_value = json_decode( $value, true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_value ) ) {
                    $value = $decoded_value;
                }
            }
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
            $restore_value = $unified_data[ $meta_key ];

            // '_choices' は元々JSON文字列としてメタ保存される仕様のため、
            // 統合JSON側で配列化されている場合はJSON文字列に戻してから復元する。
            if ( '_choices' === $meta_key && is_array( $restore_value ) ) {
                $restore_value = wp_json_encode( $restore_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            }

            update_post_meta( $post_id, $meta_key, $restore_value );
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
    
    // 旧統合データ（保存前）を取得
    $old_unified = get_post_meta( $post_id, '_noveltool_unified_meta', true );

    // 新しい統合データを生成
    $unified_json = noveltool_get_unified_custom_meta( $post_id );

    // まず保存（常に最新状態を保持）
    update_post_meta( $post_id, '_noveltool_unified_meta', $unified_json );

    // 初回保存（旧値なし）はリビジョン生成をスキップ（要件）
    if ( $old_unified === '' || $old_unified === null ) {
        return;
    }

    // 変更が無ければ終了
    if ( $old_unified === $unified_json ) {
        return;
    }

    // リビジョンを強制作成（メタ変更のみの場合にも履歴を確保）
    if ( function_exists( '_wp_put_post_revision' ) ) {
        $revision_id = _wp_put_post_revision( $post_id );
        if ( $revision_id && ! is_wp_error( $revision_id ) ) {
            // 念のためリビジョン側へ統合データをコピー（wp_insert_post フックより確実性を担保）
            update_metadata( 'post', $revision_id, '_noveltool_unified_meta', $unified_json );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                noveltool_log( sprintf( 'NovelTool: 強制リビジョン作成 post_id=%d revision_id=%d', $post_id, $revision_id ) );
            }
        }
    }
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
    // 既存フィールド配列に統合フィールドラベルを追加
    $fields['_noveltool_unified_meta'] = __( 'Custom Field Data', 'novel-game-plugin' );
    return $fields;
}
// コアは内部的に _wp_post_revision_fields を利用します。
// 互換のため wp_post_revision_fields にもフックしています（テーマ/プラグインがこちらを参照するケースへの配慮）。
// 将来的にポリシーを厳密化する場合は _wp_post_revision_fields のみでも動作します。
add_filter( '_wp_post_revision_fields', 'noveltool_add_revision_fields' );
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
 * WordPressコアの `_wp_post_revision_field_{$field}` フィルタは、比較対象の
 * from側・to側それぞれに対して1回ずつ個別に呼び出され、コア側の wp_text_diff() が
 * 2回分の戻り値を行単位で突き合わせて追加/削除/変更をハイライトする仕組みになっている。
 * そのため、ここでは「このリビジョン単体の状態」を読みやすいプレーンテキストとして
 * 返すだけにとどめ、実際の差分ハイライトはコアの行差分に委ねる。
 * フィールド1件・配列の要素1件をそれぞれ1行として出力することで、
 * コアの行単位差分がそのまま項目単位・配列要素単位の差分表示として機能する
 * （HTMLをここで組み立てると、コアが差分結果同士をさらに文字レベルで
 * 突き合わせてしまい、二重に差分化された壊れた表示になるため避けている）。
 *
 * @param string  $value   フィールドの値（このリビジョン側の統合JSON文字列）
 * @param string  $field   フィールド名
 * @param WP_Post $post    このリビジョン自体の投稿オブジェクト
 * @param string  $context コンテキスト（'to' または 'from'）
 * @return string 表示用のプレーンテキスト
 * @since 1.2.0
 */
function noveltool_revision_field_display( $value, $field, $post, $context ) {
    if ( '_noveltool_unified_meta' !== $field ) {
        return $value;
    }

    if ( empty( $value ) ) {
        return '';
    }

    $data = json_decode( $value, true );
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
        return '';
    }

    $array_diff_keys = noveltool_get_array_diff_meta_keys();
    $max_rows         = (int) apply_filters( 'noveltool_revision_array_diff_max_rows', 200 );
    $lines            = array();

    foreach ( noveltool_get_revision_meta_keys() as $key ) {
        if ( ! array_key_exists( $key, $data ) ) {
            continue;
        }

        $label       = noveltool_get_field_label( $key );
        $field_value = $data[ $key ];

        if ( is_array( $field_value ) && in_array( $key, $array_diff_keys, true ) ) {
            // 配列フィールドは要素ごとに1行とし、要素単位の追加/削除/変更を
            // コアの行差分でそのまま表現できるようにする（段階2スコープ）。
            $lines[] = $label . ':';
            $rows    = array_values( $field_value );

            if ( count( $rows ) > $max_rows ) {
                // 大規模配列では行差分の計算負荷が高くなるため、安全側にフォールバックする。
                /* translators: %d: 配列の要素数 */
                $lines[] = '  ' . sprintf( __( 'Too many items to list individually (%d items).', 'novel-game-plugin' ), count( $rows ) );
            } else {
                foreach ( $rows as $index => $row ) {
                    $lines[] = '  ' . ( $index + 1 ) . '. ' . noveltool_format_array_row_line( $row );
                }
            }
        } else {
            $lines[] = $label . ': ' . noveltool_format_scalar_line( $field_value );
        }
    }

    return implode( "\n", $lines );
}
add_filter( '_wp_post_revision_field__noveltool_unified_meta', 'noveltool_revision_field_display', 10, 4 );

/**
 * 配列単位の行差分表示対象となるカスタムフィールドキーを取得
 *
 * @return array 対象キーの配列
 * @since 1.7.0
 */
function noveltool_get_array_diff_meta_keys() {
    return array(
        '_dialogue_texts',
        '_dialogue_backgrounds',
        '_dialogue_speakers',
        '_dialogue_flag_conditions',
        '_choices',
        '_scene_arrival_flags',
    );
}

/**
 * 配列内の1要素を、コアの行差分に渡すための1行テキストに整形
 *
 * ネストしたキー単位の差分表示は対象外（段階3で対応）とし、
 * 連想配列は「キー: 値」の並びとして表示する。
 *
 * @param mixed $row 行データ
 * @return string 1行分のプレーンテキスト
 * @since 1.7.0
 */
function noveltool_format_array_row_line( $row ) {
    if ( is_array( $row ) ) {
        $parts = array();
        foreach ( $row as $row_key => $row_value ) {
            $parts[] = $row_key . ': ' . noveltool_format_scalar_line( $row_value );
        }
        return implode( ' / ', $parts );
    }

    return noveltool_format_scalar_line( $row );
}

/**
 * スカラー値を1行のプレーンテキストに整形
 *
 * 改行を含む値をそのまま返すと、コアの行差分での1要素=1行という
 * 対応関係が崩れるため、区切り記号に置換する。
 *
 * @param mixed $value 値
 * @return string 1行分のプレーンテキスト
 * @since 1.7.0
 */
function noveltool_format_scalar_line( $value ) {
    if ( is_bool( $value ) ) {
        return $value ? __( 'Yes', 'novel-game-plugin' ) : __( 'No', 'novel-game-plugin' );
    }

    if ( is_array( $value ) || is_object( $value ) ) {
        $value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
    }

    return str_replace( array( "\r\n", "\r", "\n" ), ' ⏎ ', (string) $value );
}

/**
 * カスタムフィールドキーを日本語ラベルに変換
 *
 * @param string $key カスタムフィールドキー
 * @return string 日本語ラベル
 * @since 1.2.0
 */
function noveltool_get_field_label( $key ) {
    $labels = array(
        '_background_image'         => __( 'Background Image', 'novel-game-plugin' ),
        '_character_image'          => __( 'Character Image (Old)', 'novel-game-plugin' ),
        '_character_left'           => __( 'Left Character Image', 'novel-game-plugin' ),
        '_character_center'         => __( 'Center Character Image', 'novel-game-plugin' ),
        '_character_right'          => __( 'Right Character Image', 'novel-game-plugin' ),
        '_character_left_name'      => __( 'Left Character Name', 'novel-game-plugin' ),
        '_character_center_name'    => __( 'Center Character Name', 'novel-game-plugin' ),
        '_character_right_name'     => __( 'Right Character Name', 'novel-game-plugin' ),
        '_dialogue_text'            => __( 'Dialogue Text (Old)', 'novel-game-plugin' ),
        '_dialogue_texts'           => __( 'Dialogue Texts', 'novel-game-plugin' ),
        '_dialogue_backgrounds'     => __( 'Dialogue Backgrounds', 'novel-game-plugin' ),
        '_dialogue_speakers'        => __( 'Dialogue Speakers', 'novel-game-plugin' ),
        '_dialogue_flag_conditions' => __( 'Dialogue Flag Conditions', 'novel-game-plugin' ),
        '_choices'                  => __( 'Choices', 'novel-game-plugin' ),
        '_game_title'               => __( 'Game Title', 'novel-game-plugin' ),
        '_is_ending'                => __( 'Ending', 'novel-game-plugin' ),
        '_ending_text'              => __( 'Ending Text', 'novel-game-plugin' ),
        '_scene_arrival_flags'      => __( 'Scene Arrival Flags', 'novel-game-plugin' ),
    );
    
    return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
}
