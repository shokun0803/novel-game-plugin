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

    return implode( "\n", noveltool_build_unified_meta_lines( $data ) );
}
add_filter( '_wp_post_revision_field__noveltool_unified_meta', 'noveltool_revision_field_display', 10, 4 );

/**
 * 統合メタデータの連想配列を、コアの行差分に渡すためのプレーンテキスト行の配列に変換
 *
 * フィールド1件・配列の要素1件（連想配列の場合はさらにキー1件）をそれぞれ1行として
 * 出力することで、コア標準の行単位差分がそのまま項目・配列要素・ネストしたキー単位の
 * 差分として機能する（段階2・段階3スコープ）。
 *
 * @param array $data             統合メタデータ（デコード済み連想配列）
 * @param array $move_annotations 配列フィールドキー => [ 要素インデックス => 移動注記文字列 ]（省略可）
 * @return array 行の配列
 * @since 1.7.0
 */
function noveltool_build_unified_meta_lines( $data, $move_annotations = array() ) {
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
            $lines[]   = $label . ':';
            $rows      = array_values( $field_value );
            $key_moves = isset( $move_annotations[ $key ] ) ? $move_annotations[ $key ] : array();

            if ( count( $rows ) > $max_rows ) {
                // 大規模配列では行差分の計算負荷が高くなるため、安全側にフォールバックする。
                /* translators: %d: 配列の要素数 */
                $lines[] = '  ' . sprintf( __( 'Too many items to list individually (%d items).', 'novel-game-plugin' ), count( $rows ) );
            } else {
                foreach ( $rows as $index => $row ) {
                    $suffix = isset( $key_moves[ $index ] ) ? ' ' . $key_moves[ $index ] : '';

                    if ( is_array( $row ) ) {
                        // 連想配列（例: _choices）はキーごとに行を分け、キー単位の変更検出を
                        // 可能にする（段階3: ネスト構造差分）。未変更キーはコアの行差分側で
                        // 「変更なし」として区別される。
                        $lines[] = '  ' . ( $index + 1 ) . '.' . $suffix;
                        foreach ( $row as $row_key => $row_value ) {
                            $lines[] = '    ' . $row_key . ': ' . noveltool_format_scalar_line( $row_value );
                        }
                    } else {
                        $lines[] = '  ' . ( $index + 1 ) . '. ' . noveltool_format_scalar_line( $row ) . $suffix;
                    }
                }
            }
        } else {
            $lines[] = $label . ': ' . noveltool_format_scalar_line( $field_value );
        }
    }

    return $lines;
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
 * 値の差異を判定（配列・スカラーを包括）
 *
 * @param mixed $a 旧値
 * @param mixed $b 新値
 * @return bool 異なれば true
 * @since 1.2.1
 */
function noveltool_revision_values_differ( $a, $b ) {
    if ( is_array( $a ) || is_object( $a ) ) {
        $a = wp_json_encode( $a );
    }
    if ( is_array( $b ) || is_object( $b ) ) {
        $b = wp_json_encode( $b );
    }
    return (string) $a !== (string) $b;
}

/**
 * 配列の要素間で完全一致するものを検出し、移動（並び替え）として対応付ける
 *
 * 同じ位置にある完全一致要素はまず除外し、残った要素同士で完全一致するものを
 * 「移動」として対応付ける。内容が変わった要素（追加/削除/変更）はここでは
 * 対応付けの対象にせず、通常の行差分に委ねる（段階3: 並び替え検出）。
 *
 * @param array $prev_rows 比較元の配列
 * @param array $now_rows  比較先の配列
 * @return array {
 *     @type array $prev 比較元インデックス => 移動先インデックスのマップ
 *     @type array $now  比較先インデックス => 移動元インデックスのマップ
 * }
 * @since 1.8.0
 */
function noveltool_detect_array_moves( $prev_rows, $now_rows ) {
    $prev_rows = array_values( $prev_rows );
    $now_rows  = array_values( $now_rows );

    $prev_matched = array_fill( 0, count( $prev_rows ), false );
    $now_matched  = array_fill( 0, count( $now_rows ), false );

    $min_len = min( count( $prev_rows ), count( $now_rows ) );
    for ( $i = 0; $i < $min_len; $i++ ) {
        if ( ! noveltool_revision_values_differ( $prev_rows[ $i ], $now_rows[ $i ] ) ) {
            $prev_matched[ $i ] = true;
            $now_matched[ $i ]  = true;
        }
    }

    $prev_to_now = array();
    $now_to_prev = array();

    foreach ( $now_rows as $now_index => $now_row ) {
        if ( $now_matched[ $now_index ] ) {
            continue;
        }
        foreach ( $prev_rows as $prev_index => $prev_row ) {
            if ( $prev_matched[ $prev_index ] ) {
                continue;
            }
            if ( ! noveltool_revision_values_differ( $prev_row, $now_row ) ) {
                $prev_matched[ $prev_index ] = true;
                $now_matched[ $now_index ]   = true;
                $prev_to_now[ $prev_index ]  = $now_index;
                $now_to_prev[ $now_index ]   = $prev_index;
                break;
            }
        }
    }

    return array(
        'prev' => $prev_to_now,
        'now'  => $now_to_prev,
    );
}

/**
 * コアが生成したカスタムフィールド差分に、配列要素の移動（並び替え）注記を追加する
 *
 * `_wp_post_revision_field_{$field}` フィルタは比較対象の片側ずつしか呼び出されず、
 * 単体の呼び出しだけでは要素の「移動」を判定できない（両側のデータが同時に手に
 * 入らない）。このフィルタは両リビジョンの比較が完了した後段で呼ばれ、比較元・
 * 比較先の投稿オブジェクトが揃った状態で渡されるため、ここで両側のデータを
 * 取得し直し、移動注記を加えたテキストで wp_text_diff() を再実行して該当フィールド
 * の差分だけを差し替える。移動が1件も無い場合はコアが生成した差分をそのまま使う
 * （無用な再計算を避ける）。
 *
 * @param array[]       $fields       wp_get_revision_ui_diff() が生成した差分配列
 * @param WP_Post|false $compare_from 比較元のリビジョン（初回リビジョンとの比較時はfalse）
 * @param WP_Post       $compare_to   比較先のリビジョン
 * @return array[] 更新後の差分配列
 * @since 1.8.0
 */
function noveltool_annotate_revision_array_moves( $fields, $compare_from, $compare_to ) {
    if ( ! $compare_from || ! is_array( $fields ) ) {
        return $fields;
    }

    foreach ( $fields as $index => $field_data ) {
        if ( ! isset( $field_data['id'] ) || '_noveltool_unified_meta' !== $field_data['id'] ) {
            continue;
        }

        $from_raw = get_metadata( 'post', $compare_from->ID, '_noveltool_unified_meta', true );
        $to_raw   = get_metadata( 'post', $compare_to->ID, '_noveltool_unified_meta', true );

        $from_data = ! empty( $from_raw ) ? json_decode( $from_raw, true ) : array();
        $to_data   = ! empty( $to_raw ) ? json_decode( $to_raw, true ) : array();

        if ( ! is_array( $from_data ) ) {
            $from_data = array();
        }
        if ( ! is_array( $to_data ) ) {
            $to_data = array();
        }

        $from_moves = array();
        $to_moves   = array();
        $has_moves  = false;

        foreach ( noveltool_get_array_diff_meta_keys() as $key ) {
            if ( empty( $from_data[ $key ] ) || empty( $to_data[ $key ] )
                || ! is_array( $from_data[ $key ] ) || ! is_array( $to_data[ $key ] ) ) {
                continue;
            }

            $moves = noveltool_detect_array_moves( $from_data[ $key ], $to_data[ $key ] );

            if ( empty( $moves['prev'] ) ) {
                continue;
            }
            $has_moves = true;

            $from_moves[ $key ] = array();
            foreach ( $moves['prev'] as $prev_index => $now_index ) {
                /* translators: %d: 移動先の位置番号（1始まり） */
                $from_moves[ $key ][ $prev_index ] = sprintf( __( '(moved to #%d)', 'novel-game-plugin' ), $now_index + 1 );
            }

            $to_moves[ $key ] = array();
            foreach ( $moves['now'] as $now_index => $prev_index ) {
                /* translators: %d: 移動元の位置番号（1始まり） */
                $to_moves[ $key ][ $now_index ] = sprintf( __( '(moved from #%d)', 'novel-game-plugin' ), $prev_index + 1 );
            }
        }

        if ( ! $has_moves ) {
            continue;
        }

        $from_text = implode( "\n", noveltool_build_unified_meta_lines( $from_data, $from_moves ) );
        $to_text   = implode( "\n", noveltool_build_unified_meta_lines( $to_data, $to_moves ) );

        $diff_args = array(
            'show_split_view' => true,
            'title_left'      => __( 'Removed' ),
            'title_right'     => __( 'Added' ),
        );
        /** This filter is documented in wp-admin/includes/revision.php */
        $diff_args = apply_filters( 'revision_text_diff_options', $diff_args, '_noveltool_unified_meta', $compare_from, $compare_to );

        $diff = wp_text_diff( $from_text, $to_text, $diff_args );
        if ( $diff ) {
            $fields[ $index ]['diff'] = $diff;
        }
    }

    return $fields;
}
add_filter( 'wp_get_revision_ui_diff', 'noveltool_annotate_revision_array_moves', 10, 3 );

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

/**
 * リビジョン比較画面専用のスクリプトを読み込み
 *
 * 差分の表示制御オプション（変更行のみ表示・同一行の折りたたみ・JSON出力）を
 * 提供するためのスクリプトを、リビジョン比較画面でのみ読み込む。
 *
 * @param string $hook 現在のページフック
 * @since 1.8.0
 */
function noveltool_revision_diff_enqueue_scripts( $hook ) {
    if ( 'revision.php' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'noveltool-admin-revision-diff',
        NOVEL_GAME_PLUGIN_URL . 'js/admin-revision-diff.js',
        array( 'jquery', 'revisions' ),
        NOVEL_GAME_PLUGIN_VERSION,
        true
    );

    wp_localize_script(
        'noveltool-admin-revision-diff',
        'noveltoolRevisionDiff',
        array(
            'ajaxNonce'              => wp_create_nonce( 'noveltool_revision_diff_json' ),
            'showChangesOnlyLabel'   => __( 'Show changed lines only', 'novel-game-plugin' ),
            'collapseUnchangedLabel' => __( 'Collapse unchanged lines', 'novel-game-plugin' ),
            'exportJsonLabel'        => __( 'Export as JSON', 'novel-game-plugin' ),
            /* translators: %d: 折りたたまれた変更なし行の件数 */
            'unchangedLinesLabel'    => __( '%d unchanged lines', 'novel-game-plugin' ),
            'exportErrorLabel'       => __( 'Failed to export the diff data. Please try again.', 'novel-game-plugin' ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'noveltool_revision_diff_enqueue_scripts' );

/**
 * リビジョン比較画面のスタイルを追加
 *
 * @since 1.8.0
 */
function noveltool_revision_diff_styles() {
    global $pagenow;

    if ( 'revision.php' !== $pagenow ) {
        return;
    }
    ?>
    <style>
    .noveltool-revision-diff-controls {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px;
        margin: 8px 0;
        padding: 8px 12px;
        background: #f6f7f7;
        border: 1px solid #dcdcde;
        border-radius: 3px;
    }
    .noveltool-revision-diff-controls label { display: inline-flex; align-items: center; gap: 4px; }
    .noveltool-diff-collapsed-toggle { color: #2271b1; }
    </style>
    <?php
}
add_action( 'admin_head', 'noveltool_revision_diff_styles' );

/**
 * リビジョン比較のカスタムフィールドデータをJSON形式で取得するAjaxハンドラ
 *
 * 表示中のリビジョン比較データをJSON形式でエクスポートするための導線として提供する。
 *
 * @since 1.8.0
 */
function noveltool_ajax_get_revision_diff_json() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'noveltool_revision_diff_json' ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'novel-game-plugin' ) ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
    $from_id = isset( $_POST['from_id'] ) ? absint( wp_unslash( $_POST['from_id'] ) ) : 0;
    $to_id   = isset( $_POST['to_id'] ) ? absint( wp_unslash( $_POST['to_id'] ) ) : 0;

    if ( ! $post_id || ! $to_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid request.', 'novel-game-plugin' ) ) );
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'novel-game-plugin' ) ) );
    }

    $to_post = get_post( $to_id );
    if ( ! $to_post || (int) $to_post->post_parent !== $post_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid request.', 'novel-game-plugin' ) ) );
    }

    $from_data = array();
    if ( $from_id ) {
        $from_post = get_post( $from_id );
        if ( ! $from_post || (int) $from_post->post_parent !== $post_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'novel-game-plugin' ) ) );
        }
        $from_raw  = get_metadata( 'post', $from_id, '_noveltool_unified_meta', true );
        $from_data = ! empty( $from_raw ) ? json_decode( $from_raw, true ) : array();
    }

    $to_raw  = get_metadata( 'post', $to_id, '_noveltool_unified_meta', true );
    $to_data = ! empty( $to_raw ) ? json_decode( $to_raw, true ) : array();

    wp_send_json_success(
        array(
            'from' => is_array( $from_data ) ? $from_data : array(),
            'to'   => is_array( $to_data ) ? $to_data : array(),
        )
    );
}
add_action( 'wp_ajax_noveltool_get_revision_diff_json', 'noveltool_ajax_get_revision_diff_json' );
