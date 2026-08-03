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
    
    // 現在側(value) と 比較元(compare_from) の両方の JSON を扱い差分を判定
    if ( empty( $value ) ) {
        return __( 'No Data', 'novel-game-plugin' );
    }

    $current  = json_decode( $value, true ); // 表示対象（to もしくは from コンテキスト依存）
    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $current ) ) {
        return __( 'No Data', 'novel-game-plugin' );
    }

    // 比較元リビジョン（$compare_from）があればそちらから同フィールド値を取得
    $previous = array();
    if ( $compare_from && isset( $compare_from->ID ) ) {
        $prev_raw = get_metadata( 'post', $compare_from->ID, '_noveltool_unified_meta', true );
        if ( ! empty( $prev_raw ) ) {
            $prev_decoded = json_decode( $prev_raw, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $prev_decoded ) ) {
                $previous = $prev_decoded;
            }
        }
    }

    // キー集合（追加/削除検出のため和集合をとる）
    $all_keys = array_unique( array_merge( array_keys( $current ), array_keys( $previous ) ) );
    sort( $all_keys );

    $output = '<div class="noveltool-revision-data">';
    foreach ( $all_keys as $key ) {
        $label = noveltool_get_field_label( $key );
        $exists_now   = array_key_exists( $key, $current );
        $exists_prev  = array_key_exists( $key, $previous );
        $now_val      = $exists_now ? $current[ $key ] : null;
        $prev_val     = $exists_prev ? $previous[ $key ] : null;

        // 変更タイプ判定
        $change_type = 'unchanged';
        if ( ! $exists_prev && $exists_now ) {
            $change_type = 'added';
        } elseif ( $exists_prev && ! $exists_now ) {
            $change_type = 'removed';
        } elseif ( $exists_prev && $exists_now ) {
            // 値比較（シリアライズして単純比較: 配列/真偽値含む）
            if ( noveltool_revision_values_differ( $prev_val, $now_val ) ) {
                $change_type = 'changed';
            }
        }

        // 表示用値
        $formatted_now  = $exists_now  ? noveltool_format_field_value( $now_val )  : __( '(None)', 'novel-game-plugin' );
        $formatted_prev = $exists_prev ? noveltool_format_field_value( $prev_val ) : __( '(None)', 'novel-game-plugin' );

        // バッジ文言
        switch ( $change_type ) {
            case 'added':
                $badge = '<span class="noveltool-rev-badge added">' . esc_html__( 'Add', 'novel-game-plugin' ) . '</span>';
                break;
            case 'removed':
                $badge = '<span class="noveltool-rev-badge removed">' . esc_html__( 'Delete', 'novel-game-plugin' ) . '</span>';
                break;
            case 'changed':
                $badge = '<span class="noveltool-rev-badge changed">' . esc_html__( 'Change', 'novel-game-plugin' ) . '</span>';
                break;
            default:
                $badge = '<span class="noveltool-rev-badge unchanged">' . esc_html__( 'Same', 'novel-game-plugin' ) . '</span>';
        }

        $output .= '<div class="noveltool-revision-field noveltool-revision-' . esc_attr( $change_type ) . '">';
        $output .= '<strong>' . esc_html( $label ) . '</strong> ' . $badge . '<br />';

        if ( 'unchanged' === $change_type ) {
            $output .= '<span class="noveltool-rev-value">' . wp_kses_post( $formatted_now ) . '</span>';
        } elseif ( 'added' === $change_type ) {
            $output .= '<span class="noveltool-rev-value new">' . wp_kses_post( $formatted_now ) . '</span>';
        } elseif ( 'removed' === $change_type ) {
            $output .= '<span class="noveltool-rev-value old">' . wp_kses_post( $formatted_prev ) . '</span>';
        } else { // changed
            $output .= '<span class="noveltool-rev-value old">' . wp_kses_post( $formatted_prev ) . '</span> → <span class="noveltool-rev-value new">' . wp_kses_post( $formatted_now ) . '</span>';
        }

        // 配列フィールドは、変更がある場合のみ行単位の詳細差分を追加情報として表示する。
        // 変更がない場合は既存のフィールド単位表示のみを維持する（段階2スコープ）。
        if ( 'unchanged' !== $change_type && in_array( $key, noveltool_get_array_diff_meta_keys(), true ) ) {
            $now_arr  = noveltool_maybe_decode_array_value( $now_val );
            $prev_arr = noveltool_maybe_decode_array_value( $prev_val );

            if ( null !== $now_arr || null !== $prev_arr ) {
                $output .= noveltool_render_array_row_diff( $prev_arr, $now_arr );
            }
        }

        $output .= '</div>';
    }
    $output .= '</div>';
    return $output;
}
add_filter( 'wp_post_revision_field__noveltool_unified_meta', 'noveltool_revision_field_display', 10, 4 );

/**
 * 値の差異を判定（配列・スカラーを包括）
 *
 * @param mixed $a 旧値
 * @param mixed $b 新値
 * @return bool 異なれば true
 * @since 1.2.1
 */
function noveltool_revision_values_differ( $a, $b ) {
    // 型と値を統一的に比較（配列は JSON、オブジェクトは強制配列化）
    if ( is_array( $a ) || is_object( $a ) ) {
        $a = wp_json_encode( $a );
    }
    if ( is_array( $b ) || is_object( $b ) ) {
        $b = wp_json_encode( $b );
    }
    return (string) $a !== (string) $b;
}

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
 * 値を配列として解釈できる場合に配列として返す
 *
 * `_choices` のように JSON 文字列としてメタ保存されるフィールドがあるため、
 * 配列そのものだけでなく JSON デコード可能な文字列も配列として扱う。
 *
 * @param mixed $value 判定対象の値
 * @return array|null 配列として解釈できた場合は配列、できない場合は null
 * @since 1.7.0
 */
function noveltool_maybe_decode_array_value( $value ) {
    if ( is_array( $value ) ) {
        return $value;
    }

    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            return $decoded;
        }
    }

    return null;
}

/**
 * 配列内の1行分の値を表示用文字列に整形
 *
 * ネストしたキー単位の差分表示は対象外（段階3で対応）とし、
 * 連想配列は「キー: 値」の並びとして表示する。
 *
 * @param mixed $row 行データ
 * @return string 整形済みHTML（エスケープ済み）
 * @since 1.7.0
 */
function noveltool_format_array_row_value( $row ) {
    if ( is_array( $row ) ) {
        $parts = array();
        foreach ( $row as $row_key => $row_value ) {
            if ( is_array( $row_value ) || is_object( $row_value ) ) {
                $row_value = wp_json_encode( $row_value, JSON_UNESCAPED_UNICODE );
            } elseif ( is_bool( $row_value ) ) {
                $row_value = $row_value ? __( 'Yes', 'novel-game-plugin' ) : __( 'No', 'novel-game-plugin' );
            }

            $row_value = (string) $row_value;
            if ( mb_strlen( $row_value ) > 50 ) {
                $row_value = mb_substr( $row_value, 0, 50 ) . '...';
            }

            $parts[] = esc_html( $row_key ) . ': ' . esc_html( $row_value );
        }
        return implode( ' / ', $parts );
    }

    if ( is_bool( $row ) ) {
        return $row ? esc_html__( 'Yes', 'novel-game-plugin' ) : esc_html__( 'No', 'novel-game-plugin' );
    }

    $row = (string) $row;
    if ( mb_strlen( $row ) > 80 ) {
        return esc_html( mb_substr( $row, 0, 80 ) ) . '...';
    }
    return esc_html( $row );
}

/**
 * 配列フィールドの行単位差分をHTMLとして生成
 *
 * 各行を「同一 / 変更 / 追加 / 削除」に判定して表示する（段階2スコープ）。
 * 要素数が多すぎる場合は詳細表示をスキップし、負荷を抑えたフォールバック表示にする。
 *
 * @param array $prev_arr 比較元（変更前）の配列
 * @param array $now_arr  比較対象（変更後）の配列
 * @return string 生成されたHTML
 * @since 1.7.0
 */
function noveltool_render_array_row_diff( $prev_arr, $now_arr ) {
    $prev_arr = array_values( (array) $prev_arr );
    $now_arr  = array_values( (array) $now_arr );

    $row_count = max( count( $prev_arr ), count( $now_arr ) );

    if ( 0 === $row_count ) {
        return '';
    }

    // 大規模配列では詳細差分の生成負荷が高くなるため、安全側にフォールバックする。
    $max_rows = (int) apply_filters( 'noveltool_revision_array_diff_max_rows', 200 );
    if ( $row_count > $max_rows ) {
        return '<p class="noveltool-rev-array-fallback">' . esc_html__( 'Too many items to show a detailed diff. Showing summary only.', 'novel-game-plugin' ) . '</p>';
    }

    $output = '<div class="noveltool-revision-array-diff">';

    for ( $i = 0; $i < $row_count; $i++ ) {
        $exists_prev = array_key_exists( $i, $prev_arr );
        $exists_now  = array_key_exists( $i, $now_arr );
        $prev_row    = $exists_prev ? $prev_arr[ $i ] : null;
        $now_row     = $exists_now ? $now_arr[ $i ] : null;

        if ( ! $exists_prev && $exists_now ) {
            $row_change = 'added';
        } elseif ( $exists_prev && ! $exists_now ) {
            $row_change = 'removed';
        } elseif ( noveltool_revision_values_differ( $prev_row, $now_row ) ) {
            $row_change = 'changed';
        } else {
            $row_change = 'unchanged';
        }

        switch ( $row_change ) {
            case 'added':
                $badge = '<span class="noveltool-rev-badge added">' . esc_html__( 'Add', 'novel-game-plugin' ) . '</span>';
                break;
            case 'removed':
                $badge = '<span class="noveltool-rev-badge removed">' . esc_html__( 'Delete', 'novel-game-plugin' ) . '</span>';
                break;
            case 'changed':
                $badge = '<span class="noveltool-rev-badge changed">' . esc_html__( 'Change', 'novel-game-plugin' ) . '</span>';
                break;
            default:
                $badge = '<span class="noveltool-rev-badge unchanged">' . esc_html__( 'Same', 'novel-game-plugin' ) . '</span>';
        }

        /* translators: %d: 配列内の行番号（1始まり） */
        $row_label = sprintf( __( 'Row %d', 'novel-game-plugin' ), $i + 1 );

        $output .= '<div class="noveltool-revision-array-row noveltool-revision-' . esc_attr( $row_change ) . '">';
        $output .= '<span class="noveltool-rev-row-label">' . esc_html( $row_label ) . '</span> ' . $badge;

        if ( 'unchanged' === $row_change ) {
            $output .= '<div class="noveltool-rev-value">' . noveltool_format_array_row_value( $now_row ) . '</div>';
        } elseif ( 'added' === $row_change ) {
            $output .= '<div class="noveltool-rev-value new">' . noveltool_format_array_row_value( $now_row ) . '</div>';
        } elseif ( 'removed' === $row_change ) {
            $output .= '<div class="noveltool-rev-value old">' . noveltool_format_array_row_value( $prev_row ) . '</div>';
        } else { // changed
            $output .= '<div class="noveltool-rev-value old">' . noveltool_format_array_row_value( $prev_row ) . '</div>';
            $output .= '<div class="noveltool-rev-value new">' . noveltool_format_array_row_value( $now_row ) . '</div>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
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
        /* translators: %d: number of array items */
        return sprintf( __( 'Array (%d items)', 'novel-game-plugin' ), $count );
    }
    
    // 真偽値の場合
    if ( is_bool( $value ) ) {
        return $value ? __( 'Yes', 'novel-game-plugin' ) : __( 'No', 'novel-game-plugin' );
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
        .noveltool-revision-field span { color: #555; }
        .noveltool-revision-field .noveltool-rev-badge { display:inline-block; margin-left:4px; padding:2px 6px; font-size:11px; border-radius:10px; background:#ccc; color:#222; line-height:1.2; }
        .noveltool-revision-field.changed .noveltool-rev-badge.changed { background:#f0ad4e; color:#222; }
        .noveltool-revision-field.added .noveltool-rev-badge.added { background:#5cb85c; color:#fff; }
        .noveltool-revision-field.removed .noveltool-rev-badge.removed { background:#d9534f; color:#fff; }
        .noveltool-revision-field.unchanged .noveltool-rev-badge.unchanged { background:#e0e0e0; color:#555; }
        .noveltool-rev-value.old { text-decoration:line-through; opacity:0.7; }
        .noveltool-rev-value.new { font-weight:bold; }
        .noveltool-revision-array-diff { margin-top:6px; padding-left:10px; border-left:2px solid #ddd; }
        .noveltool-revision-array-row { padding:4px 0; border-bottom:1px dotted #e5e5e5; }
        .noveltool-revision-array-row:last-child { border-bottom:none; }
        .noveltool-rev-row-label { font-size:11px; color:#777; min-width:60px; display:inline-block; }
        .noveltool-revision-array-row .noveltool-rev-value { margin-left:4px; font-size:13px; }
        .noveltool-rev-array-fallback { font-style:italic; color:#777; margin-top:6px; }
        </style>
        <?php
    }
}
add_action( 'admin_head', 'noveltool_revision_styles' );
