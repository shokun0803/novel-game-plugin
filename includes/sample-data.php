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
 * サンプル画像ディレクトリ（uploads）を取得
 *
 * @return string サンプル画像ディレクトリの絶対パス
 * @since 1.5.0
 */
function noveltool_get_sample_images_directory() {
    $upload_dir = wp_upload_dir();

    if ( ! empty( $upload_dir['basedir'] ) ) {
        return trailingslashit( $upload_dir['basedir'] ) . 'noveltool/sample-images';
    }

    return WP_CONTENT_DIR . '/uploads/noveltool/sample-images';
}

/**
 * サンプル画像URLのベース（uploads）を取得
 *
 * @return string サンプル画像ベースURL
 * @since 1.5.0
 */
function noveltool_get_sample_images_base_url() {
    $upload_dir = wp_upload_dir();

    if ( ! empty( $upload_dir['baseurl'] ) ) {
        return trailingslashit( $upload_dir['baseurl'] ) . 'noveltool/sample-images/';
    }

    return trailingslashit( content_url( 'uploads/noveltool/sample-images' ) );
}

/**
 * サンプル画像のURLをuploads基準へ変換
 *
 * @param string $url 元の画像URL
 * @return string 変換後URL
 * @since 1.5.0
 */
function noveltool_convert_sample_image_url_to_uploads( $url ) {
    $url = (string) $url;
    if ( '' === $url ) {
        return '';
    }

    $uploads_prefix = trailingslashit( noveltool_get_sample_images_base_url() );
    if ( strpos( $url, $uploads_prefix ) === 0 ) {
        return $url;
    }

    $legacy_prefix = trailingslashit( NOVEL_GAME_PLUGIN_URL ) . 'assets/sample-images/';
    if ( strpos( $url, $legacy_prefix ) === 0 ) {
        $relative = ltrim( substr( $url, strlen( $legacy_prefix ) ), '/' );
        return $uploads_prefix . $relative;
    }

    return $url;
}

/**
 * 既存の Shadow Detective サンプルゲーム画像参照先をuploadsへ移行
 *
 * @param array $game ゲームデータ
 * @return bool 更新が発生した場合true
 * @since 1.5.0
 */
function noveltool_migrate_shadow_detective_image_references_to_uploads( $game ) {
    if ( empty( $game ) || empty( $game['title'] ) ) {
        return false;
    }

    $updated = false;

    if ( ! empty( $game['title_image'] ) ) {
        $new_title_image = noveltool_convert_sample_image_url_to_uploads( $game['title_image'] );
        if ( $new_title_image !== $game['title_image'] ) {
            $game['title_image'] = $new_title_image;
            $updated = true;
        }
    }

    if ( $updated ) {
        noveltool_save_game( $game );
    }

    $scenes = noveltool_get_posts_by_game_title( $game['title'] );
    if ( empty( $scenes ) || ! is_array( $scenes ) ) {
        return $updated;
    }

    foreach ( $scenes as $scene ) {
        $scene_updated = false;

        $single_image_meta_keys = array(
            '_background_image',
            '_character_left',
            '_character_center',
            '_character_right',
        );

        foreach ( $single_image_meta_keys as $meta_key ) {
            $old_value = get_post_meta( $scene->ID, $meta_key, true );
            $new_value = noveltool_convert_sample_image_url_to_uploads( $old_value );
            if ( $new_value !== $old_value ) {
                update_post_meta( $scene->ID, $meta_key, esc_url_raw( $new_value ) );
                $scene_updated = true;
            }
        }

        $dialogue_backgrounds_raw = get_post_meta( $scene->ID, '_dialogue_backgrounds', true );
        $dialogue_backgrounds = json_decode( $dialogue_backgrounds_raw, true );
        if ( is_array( $dialogue_backgrounds ) ) {
            $backgrounds_changed = false;
            foreach ( $dialogue_backgrounds as $index => $background_url ) {
                $new_background_url = noveltool_convert_sample_image_url_to_uploads( $background_url );
                if ( $new_background_url !== $background_url ) {
                    $dialogue_backgrounds[ $index ] = $new_background_url;
                    $backgrounds_changed = true;
                }
            }

            if ( $backgrounds_changed ) {
                update_post_meta( $scene->ID, '_dialogue_backgrounds', wp_json_encode( $dialogue_backgrounds, JSON_UNESCAPED_UNICODE ) );
                $scene_updated = true;
            }
        }

        $dialogue_characters_raw = get_post_meta( $scene->ID, '_dialogue_characters', true );
        $dialogue_characters = json_decode( $dialogue_characters_raw, true );
        if ( is_array( $dialogue_characters ) ) {
            $characters_changed = false;
            foreach ( $dialogue_characters as $dialogue_index => $character_set ) {
                if ( ! is_array( $character_set ) ) {
                    continue;
                }

                foreach ( array( 'left', 'center', 'right' ) as $position ) {
                    $old_character_url = isset( $character_set[ $position ] ) ? $character_set[ $position ] : '';
                    $new_character_url = noveltool_convert_sample_image_url_to_uploads( $old_character_url );
                    if ( $new_character_url !== $old_character_url ) {
                        $dialogue_characters[ $dialogue_index ][ $position ] = $new_character_url;
                        $characters_changed = true;
                    }
                }
            }

            if ( $characters_changed ) {
                update_post_meta( $scene->ID, '_dialogue_characters', wp_json_encode( $dialogue_characters, JSON_UNESCAPED_UNICODE ) );
                $scene_updated = true;
            }
        }

        if ( $scene_updated ) {
            $updated = true;
        }
    }

    return $updated;
}


/**
 * 「影の探偵」本格推理ゲームのデータを取得
 *
 * プレイヤー＝探偵として、証拠と証言を集め、3段階の推理（黒幕・真相・監禁場所）で
 * 事件の真実に迫るハードボイルド・ミステリー。
 *
 * シナリオ構成（25シーン）:
 * - 導入(1-2) → 調査ハブ(4)を中心とした自由調査(3,5-15) → 推理フェーズ(16-18)
 *   → 対決(19-21) → エンディング4種(22-25)
 * - 機能網羅: シーン到達フラグ / 選択肢フラグ条件(true/false・AND) / 選択肢setFlags /
 *   セリフのalternative・hidden表示 / セリフ内背景切替(回想) / 表情差分 / 複数エンディング
 *
 * @return array Shadow Detectiveゲームのデータ構造
 * @since 1.3.0
 */
function noveltool_get_shadow_detective_game_data() {
    // uploads 内に展開されたサンプル画像を参照
    $plugin_url = noveltool_get_sample_images_base_url();

    // 背景画像（プラグイン同梱PNG）
    $bg_office      = $plugin_url . 'bg-detective-office.png';   // 探偵事務所
    $bg_warehouse   = $plugin_url . 'bg-warehouse.png';          // 港の倉庫街
    $bg_mansion     = $plugin_url . 'bg-mansion.png';            // 黒崎邸
    $bg_cafe        = $plugin_url . 'bg-cafe.png';               // カフェ
    $bg_study       = $plugin_url . 'bg-study.png';              // 書斎
    $bg_hidden_room = $plugin_url . 'bg-hidden-room.png';        // 隠し部屋
    $bg_alley       = $plugin_url . 'bg-backstreet.png';         // 裏路地
    $bg_bar         = $plugin_url . 'bg-underground-bar.png';    // 龍組のバー
    $bg_takagi      = $plugin_url . 'bg-abandoned-factory.png';  // 高木建設の資材ヤード
    $bg_villa       = $plugin_url . 'bg-confrontation.png';      // 海岸道路の別荘

    // 主人公（探偵）の表情差分
    $det_normal     = $plugin_url . 'char-protagonist-normal.png';
    $det_thinking   = $plugin_url . 'char-protagonist-thinking.png';
    $det_serious    = $plugin_url . 'char-protagonist-serious.png';
    $det_determined = $plugin_url . 'char-protagonist-determined.png';

    // 美咲（依頼人・黒崎の妻）の表情差分
    $misaki_normal  = $plugin_url . 'char-misaki-normal.png';
    $misaki_sad     = $plugin_url . 'char-misaki-sad.png';
    $misaki_worried = $plugin_url . 'char-misaki-worried.png';
    $misaki_tense   = $plugin_url . 'char-misaki-tense.png';
    $misaki_smile   = $plugin_url . 'char-misaki-smile.png';

    // 誠（失踪した実業家）の表情差分
    $makoto_relief  = $plugin_url . 'char-makoto-relief.png';
    $makoto_tired   = $plugin_url . 'char-makoto-tired.png';

    // 高木（高木建設社長）の表情差分
    $takagi_calm    = $plugin_url . 'char-takagi-calm.png';
    $takagi_nervous = $plugin_url . 'char-takagi-nervous.png';
    $takagi_angry   = $plugin_url . 'char-takagi-angry.png';
    $takagi_regret  = $plugin_url . 'char-takagi-regret.png';

    // 佐藤（黒崎の旧友）
    $char_sato = $plugin_url . 'char-sato.png';

    // 情報屋
    $char_informant = $plugin_url . 'char-informant.png';

    // 龍組幹部
    $char_yakuza = $plugin_url . 'char-yakuza.png';

    // タイトル画面用画像
    $title_image_file = $plugin_url . 'title-shadow-detective.png';

    // 話者名（共通）
    $name_detective = __( 'Detective', 'novel-game-plugin' );
    $name_misaki    = __( 'Misaki', 'novel-game-plugin' );
    $name_sato      = __( 'Sato', 'novel-game-plugin' );
    $name_informant = __( 'Informant', 'novel-game-plugin' );
    $name_yakuza    = __( 'Ryu-gumi Executive', 'novel-game-plugin' );
    $name_takagi    = __( 'President Takagi', 'novel-game-plugin' );
    $name_makoto    = __( 'Makoto Kurosaki', 'novel-game-plugin' );

    // フラグ名（フラグ条件・setFlags はこの「名前」で照合されるため、必ず同一文字列を使う）
    $f_watch           = __( 'Pocket Watch', 'novel-game-plugin' );
    $f_photo           = __( 'Sato\'s Photo', 'novel-game-plugin' );
    $f_memo            = __( 'Kurosaki\'s Memo', 'novel-game-plugin' );
    $f_key             = __( 'Safe Key', 'novel-game-plugin' );
    $f_ledger          = __( 'Hidden Ledger', 'novel-game-plugin' );
    $f_wife_talk       = __( 'Misaki\'s Testimony', 'novel-game-plugin' );
    $f_wife_confess    = __( 'Misaki\'s Confession', 'novel-game-plugin' );
    $f_sato_talk       = __( 'Sato\'s Testimony', 'novel-game-plugin' );
    $f_sato_debt       = __( 'Sato\'s Debt', 'novel-game-plugin' );
    $f_sato_confess    = __( 'Sato\'s Confession', 'novel-game-plugin' );
    $f_hidden_room     = __( 'Hidden Room', 'novel-game-plugin' );
    $f_informant_intel = __( 'Informant\'s Intel', 'novel-game-plugin' );
    $f_yakuza_slip     = __( 'Executive\'s Slip', 'novel-game-plugin' );
    $f_met_takagi      = __( 'Met Takagi', 'novel-game-plugin' );
    $f_deduce_culprit  = __( 'Deduction: The Mastermind', 'novel-game-plugin' );
    $f_deduce_alive    = __( 'Deduction: Still Alive', 'novel-game-plugin' );
    $f_deduce_place    = __( 'Deduction: The Villa', 'novel-game-plugin' );
    $f_confronted      = __( 'Cornered the Mastermind', 'novel-game-plugin' );

    // Shadow Detective ゲームの基本情報
    $game_data = array(
        'title'          => __( 'Shadow Detective', 'novel-game-plugin' ),
        'description'    => __( 'You are the detective. Businessman Makoto Kurosaki vanished from the harbor three nights ago. Question the people around him, gather evidence, expose the contradictions — and when the pieces align, name the mastermind, the truth, and the place. Wrong deductions have consequences.', 'novel-game-plugin' ),
        'title_image'    => $title_image_file,
        'game_over_text' => __( 'The trail has gone cold', 'novel-game-plugin' ),
        'is_sample'      => true,
        'machine_name'   => 'shadow_detective_v1', // 機械識別子（多言語環境での重複防止）
    );

    // シーンデータ（全25シーン）
    $scenes = array(

        // ---------------------------------------------------------------
        // シーン1: 依頼人（導入）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Client', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => $det_normal,
            'character_center'     => '',
            'character_right'      => $misaki_worried,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_misaki,
            'dialogue_texts'       => array(
                __( 'Cold rain had been falling since noon. Just past seven, a woman appeared at my office door, holding a photograph like a prayer.', 'novel-game-plugin' ),
                __( 'Please, detective. My husband, Makoto, has been missing for three days.', 'novel-game-plugin' ),
                __( 'The police say he simply ran off. But that evening he called me — he said he would be home by ten.', 'novel-game-plugin' ),
                __( 'A man who calls home before a meeting doesn\'t vanish by choice. I\'ll take the case, Mrs. Kurosaki.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'right', 'right', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_normal, 'center' => '', 'right' => $misaki_worried ),
                1 => array( 'left' => $det_normal, 'center' => '', 'right' => $misaki_sad ),
                2 => array( 'left' => $det_serious, 'center' => '', 'right' => $misaki_worried ),
                3 => array( 'left' => $det_serious, 'center' => '', 'right' => $misaki_worried ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Ask her to walk through that last evening', 'novel-game-plugin' ),
                    'next' => 'scene_2',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン2: 最後の夜（3つの糸口を提示）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Last Evening', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => $det_serious,
            'character_center'     => '',
            'character_right'      => $misaki_sad,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_misaki,
            'dialogue_texts'       => array(
                __( 'He left at eight, saying a meeting had come up. He took only his coat and his briefcase.', 'novel-game-plugin' ),
                __( 'A taxi driver remembered him. He was dropped near the harbor warehouses at nine.', 'novel-game-plugin' ),
                __( 'And for weeks now, the phone rings late at night. When I answer... no one speaks.', 'novel-game-plugin' ),
                __( 'A meeting no one knew of, the warehouse district, and silent calls. Three threads. Time to start pulling.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'right', 'right', 'right', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_serious, 'center' => '', 'right' => $misaki_sad ),
                1 => array( 'left' => $det_serious, 'center' => '', 'right' => $misaki_normal ),
                2 => array( 'left' => $det_serious, 'center' => '', 'right' => $misaki_tense ),
                3 => array( 'left' => $det_thinking, 'center' => '', 'right' => $misaki_sad ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Walk the warehouse district where he was last seen', 'novel-game-plugin' ),
                    'next' => 'scene_3',
                ),
                array(
                    'text' => __( 'Visit the residence and hear more from Misaki', 'novel-game-plugin' ),
                    'next' => 'scene_5',
                ),
                array(
                    'text' => __( 'Meet Sato, his oldest friend', 'novel-game-plugin' ),
                    'next' => 'scene_7',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン3: 埠頭の痕跡（懐中時計入手）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — Traces on the Wharf', 'novel-game-plugin' ),
            'background'           => $bg_warehouse,
            'character_left'       => '',
            'character_center'     => $det_serious,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'The harbor at dusk. Rusted cranes, walls of containers — and not a single security camera in sight.', 'novel-game-plugin' ),
                __( 'Fresh tire marks by the loading bay. A heavy car stopped here, then swung around in a hurry.', 'novel-game-plugin' ),
                __( 'Something glints between the pallets — a pocket watch. The lid is engraved: "To Makoto. Misaki."', 'novel-game-plugin' ),
                __( 'The hands stopped at 9:47. A man doesn\'t drop his wife\'s gift and stroll away. He left it here — for someone to find.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'center', '', 'center' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'              => array(
                array(
                    'text' => __( 'Return to the office and lay out the facts', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
                array(
                    'text' => __( 'Call on Misaki at the residence', 'novel-game-plugin' ),
                    'next' => 'scene_5',
                    'flagConditions' => array(
                        array( 'name' => $f_wife_talk, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Meet Sato at the cafe', 'novel-game-plugin' ),
                    'next' => 'scene_7',
                    'flagConditions' => array(
                        array( 'name' => $f_sato_talk, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_item_watch', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン4: 事件簿（調査ハブ / 進捗で独白と行き先が変化）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Case Board', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => '',
            'character_center'     => $det_thinking,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'Back at the office, I pin everything to the corkboard and let the pieces stare back at me.', 'novel-game-plugin' ),
                __( 'A vanished businessman. Midnight calls with no voice. A meeting that never existed.', 'novel-game-plugin' ),
                __( 'Money is the thread. Frightened men run from debts; careful men hide the proof.', 'novel-game-plugin' ),
                __( 'The board is still full of holes. Time to knock on the next door.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'center', 'center', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_flag_conditions' => array(
                1 => array(
                    'conditions' => array(
                        array( 'name' => $f_watch, 'state' => 1 ),
                    ),
                    'logic'           => 'AND',
                    'displayMode'     => 'alternative',
                    'alternativeText' => __( 'The watch stopped at 9:47 on the wharf. If he dropped it on purpose, he was alive when he left — and he did not leave alone.', 'novel-game-plugin' ),
                ),
                2 => array(
                    'conditions' => array(
                        array( 'name' => $f_memo, 'state' => 1 ),
                    ),
                    'logic'           => 'AND',
                    'displayMode'     => 'alternative',
                    'alternativeText' => __( '"The September transfer is the last one. I\'m out." Men who quit a dirty game rarely keep their freedom — or their lives.', 'novel-game-plugin' ),
                ),
                3 => array(
                    'conditions' => array(
                        array( 'name' => $f_ledger, 'state' => 1 ),
                    ),
                    'logic'           => 'AND',
                    'displayMode'     => 'alternative',
                    'alternativeText' => __( 'The ledger bears his initials on every page. The board is nearly complete — now one wrong thread could unravel the whole case.', 'novel-game-plugin' ),
                ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Hear Misaki\'s account at the residence', 'novel-game-plugin' ),
                    'next' => 'scene_5',
                    'flagConditions' => array(
                        array( 'name' => $f_wife_talk, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Meet Sato, the old friend', 'novel-game-plugin' ),
                    'next' => 'scene_7',
                    'flagConditions' => array(
                        array( 'name' => $f_sato_talk, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Ask Misaki about the pocket watch', 'novel-game-plugin' ),
                    'next' => 'scene_6',
                    'flagConditions' => array(
                        array( 'name' => $f_watch, 'state' => true ),
                        array( 'name' => $f_wife_talk, 'state' => true ),
                        array( 'name' => $f_wife_confess, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Search Makoto\'s study', 'novel-game-plugin' ),
                    'next' => 'scene_8',
                    'flagConditions' => array(
                        array( 'name' => $f_wife_talk, 'state' => true ),
                        array( 'name' => $f_hidden_room, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Go back down to the hidden room', 'novel-game-plugin' ),
                    'next' => 'scene_9',
                    'flagConditions' => array(
                        array( 'name' => $f_hidden_room, 'state' => true ),
                        array( 'name' => $f_memo, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Ask Misaki about the safe key', 'novel-game-plugin' ),
                    'next' => 'scene_10',
                    'flagConditions' => array(
                        array( 'name' => $f_memo, 'state' => true ),
                        array( 'name' => $f_key, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Open the safe in the hidden room', 'novel-game-plugin' ),
                    'next' => 'scene_11',
                    'flagConditions' => array(
                        array( 'name' => $f_key, 'state' => true ),
                        array( 'name' => $f_ledger, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Take Sato\'s photo to an informant', 'novel-game-plugin' ),
                    'next' => 'scene_12',
                    'flagConditions' => array(
                        array( 'name' => $f_photo, 'state' => true ),
                        array( 'name' => $f_informant_intel, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Walk into the Ryu-gumi\'s bar', 'novel-game-plugin' ),
                    'next' => 'scene_13',
                    'flagConditions' => array(
                        array( 'name' => $f_informant_intel, 'state' => true ),
                        array( 'name' => $f_yakuza_slip, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Press Sato about his debt', 'novel-game-plugin' ),
                    'next' => 'scene_14',
                    'flagConditions' => array(
                        array( 'name' => $f_sato_debt, 'state' => true ),
                        array( 'name' => $f_sato_confess, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Pay a visit to Takagi Construction', 'novel-game-plugin' ),
                    'next' => 'scene_15',
                    'flagConditions' => array(
                        array( 'name' => $f_ledger, 'state' => true ),
                        array( 'name' => $f_met_takagi, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Assemble the deduction', 'novel-game-plugin' ),
                    'next' => 'scene_16',
                    'flagConditions' => array(
                        array( 'name' => $f_memo, 'state' => true ),
                        array( 'name' => $f_ledger, 'state' => true ),
                        array( 'name' => $f_met_takagi, 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン5: 丘の上の屋敷（美咲の証言）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The House on the Hill', 'novel-game-plugin' ),
            'background'           => $bg_mansion,
            'character_left'       => $misaki_sad,
            'character_center'     => '',
            'character_right'      => $det_serious,
            'character_left_name'  => $name_misaki,
            'character_center_name' => '',
            'character_right_name' => $name_detective,
            'dialogue_texts'       => array(
                __( 'He was proud of the company he built from nothing. But this past year, something was eating him alive.', 'novel-game-plugin' ),
                __( 'He would shut himself in the study until dawn. Once, I heard him on the phone. He sounded like he was pleading.', 'novel-game-plugin' ),
                __( 'Twice, a man came to the door. Broad shoulders, a scar on his jaw. My husband sent me upstairs both times.', 'novel-game-plugin' ),
                __( 'A visitor the wife was never allowed to see. That\'s not business. That\'s a collector.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'left', 'left', 'left', 'right' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $misaki_sad, 'center' => '', 'right' => $det_serious ),
                1 => array( 'left' => $misaki_worried, 'center' => '', 'right' => $det_serious ),
                2 => array( 'left' => $misaki_tense, 'center' => '', 'right' => $det_serious ),
                3 => array( 'left' => $misaki_sad, 'center' => '', 'right' => $det_thinking ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Show her the pocket watch', 'novel-game-plugin' ),
                    'next' => 'scene_6',
                    'flagConditions' => array(
                        array( 'name' => $f_watch, 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Ask to see the study', 'novel-game-plugin' ),
                    'next' => 'scene_8',
                ),
                array(
                    'text' => __( 'Return to the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_talked_wife', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン6: 美咲の告白（懐中時計がこじ開ける証言）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — What Misaki Hid', 'novel-game-plugin' ),
            'background'           => $bg_mansion,
            'character_left'       => $misaki_tense,
            'character_center'     => '',
            'character_right'      => $det_serious,
            'character_left_name'  => $name_misaki,
            'character_center_name' => '',
            'character_right_name' => $name_detective,
            'dialogue_texts'       => array(
                __( 'This was found where your husband was last seen. I need the whole truth now, Mrs. Kurosaki.', 'novel-game-plugin' ),
                __( '...It\'s the watch I gave him. He never took it off. Not even to sleep.', 'novel-game-plugin' ),
                __( 'Forgive me — I did hear that call. He said: "The September payment is the last one. After that, I\'m out."', 'novel-game-plugin' ),
                __( 'I was afraid, detective. I thought if I repeated those words, they would come true.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'right', 'left', 'left', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $misaki_tense, 'center' => '', 'right' => $det_serious ),
                1 => array( 'left' => $misaki_sad, 'center' => '', 'right' => $det_serious ),
                2 => array( 'left' => $misaki_sad, 'center' => '', 'right' => $det_serious ),
                3 => array( 'left' => $misaki_worried, 'center' => '', 'right' => $det_normal ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Ask to see the study', 'novel-game-plugin' ),
                    'next' => 'scene_8',
                    'flagConditions' => array(
                        array( 'name' => $f_hidden_room, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Return to the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_wife_confession', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン7: 旧友（佐藤の証言と写真 / 不自然さの種まき）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — An Old Friend', 'novel-game-plugin' ),
            'background'           => $bg_cafe,
            'character_left'       => $det_normal,
            'character_center'     => '',
            'character_right'      => $char_sato,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_sato,
            'dialogue_texts'       => array(
                __( 'Makoto? We\'ve known each other thirty years. If he were in real trouble, I\'d know— I mean, I\'d want to know.', 'novel-game-plugin' ),
                __( 'Actually, last week I saw him near the harbor with some rough-looking men. I took a photo. Just in case.', 'novel-game-plugin' ),
                __( 'Near the harbor. And you just happened to be there, camera ready?', 'novel-game-plugin' ),
                __( 'I— I was passing by, that\'s all! Here, take the photo. Just find him. Please.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'right', 'right', 'left', 'right' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_normal, 'center' => '', 'right' => $char_sato ),
                1 => array( 'left' => $det_normal, 'center' => '', 'right' => $char_sato ),
                2 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_sato ),
                3 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_sato ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Have an informant identify the men in the photo', 'novel-game-plugin' ),
                    'next' => 'scene_12',
                    'flagConditions' => array(
                        array( 'name' => $f_informant_intel, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Visit the Kurosaki residence', 'novel-game-plugin' ),
                    'next' => 'scene_5',
                    'flagConditions' => array(
                        array( 'name' => $f_wife_talk, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Return to the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_talked_friend', 'value' => 1 ),
                array( 'id' => 'flag_item_photo', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン8: 書斎（二重帳簿の気配と隠し扉）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Study', 'novel-game-plugin' ),
            'background'           => $bg_study,
            'character_left'       => '',
            'character_center'     => $det_serious,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'Ledgers, contracts, tax files — all immaculate. Too immaculate for a company said to be gasping for cash.', 'novel-game-plugin' ),
                __( 'The books balance perfectly, yet the company was starving. Money flowed through these pages, not into them.', 'novel-game-plugin' ),
                __( 'One bookshelf stands a finger\'s width off the wall. Behind it — a low door with a dial lock, left half-open.', 'novel-game-plugin' ),
                __( 'Whoever came for the secret left in a hurry, and empty-handed. Let\'s see what they couldn\'t find.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'center', '', 'center' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'              => array(
                array(
                    'text' => __( 'Step through the hidden door', 'novel-game-plugin' ),
                    'next' => 'scene_9',
                ),
                array(
                    'text' => __( 'Withdraw and rethink at the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_found_hidden_room', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン9: 隠し部屋（手記の入手）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Hidden Room', 'novel-game-plugin' ),
            'background'           => $bg_hidden_room,
            'character_left'       => '',
            'character_center'     => $det_determined,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'A cramped room. A steel safe, a bare desk, and the stale smell of cigarettes and sleepless nights.', 'novel-game-plugin' ),
                __( 'On the desk, a memo in Kurosaki\'s hand: "Sept. transfer to T. The last one. I\'m out."', 'novel-game-plugin' ),
                __( 'T. He was paying someone, and September was meant to end it. Men like "T" rarely agree to endings.', 'novel-game-plugin' ),
                __( 'The safe won\'t give. Somewhere there\'s a key — and I\'d wager the lady of the house knows where.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', '', 'center', 'center' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'              => array(
                array(
                    'text' => __( 'Ask Misaki about the safe key', 'novel-game-plugin' ),
                    'next' => 'scene_10',
                ),
                array(
                    'text' => __( 'Return to the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_item_note', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン10: 二つ目の鍵（hidden表示のデモ: 告白済みなら4行目が消える）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Second Key', 'novel-game-plugin' ),
            'background'           => $bg_mansion,
            'character_left'       => $misaki_normal,
            'character_center'     => '',
            'character_right'      => $det_normal,
            'character_left_name'  => $name_misaki,
            'character_center_name' => '',
            'character_right_name' => $name_detective,
            'dialogue_texts'       => array(
                __( 'There\'s a safe behind his bookshelf. I need whatever opens it.', 'novel-game-plugin' ),
                __( 'A safe? I never knew... wait. His father\'s desk has a drawer with a false bottom. He kept a small key there.', 'novel-game-plugin' ),
                __( 'Beneath the false bottom lies a small brass key, its surface polished by worried fingers.', 'novel-game-plugin' ),
                __( 'She is still holding something back. I can see it in the way she watches the door.', 'novel-game-plugin' ),
                __( 'This should fit the dial door. Time to hear what the safe has been keeping quiet.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'right', 'left', '', 'right', 'right' ),
            'dialogue_backgrounds' => array( '', '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $misaki_normal, 'center' => '', 'right' => $det_normal ),
                1 => array( 'left' => $misaki_worried, 'center' => '', 'right' => $det_normal ),
                2 => array( 'left' => $misaki_worried, 'center' => '', 'right' => $det_normal ),
                3 => array( 'left' => $misaki_worried, 'center' => '', 'right' => $det_thinking ),
                4 => array( 'left' => $misaki_normal, 'center' => '', 'right' => $det_determined ),
            ),
            'dialogue_flag_conditions' => array(
                3 => array( // 既に告白を引き出していれば、この含みのある独白は表示しない
                    'conditions' => array(
                        array( 'name' => $f_wife_confess, 'state' => 1 ),
                    ),
                    'logic'       => 'AND',
                    'displayMode' => 'hidden',
                ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Open the safe', 'novel-game-plugin' ),
                    'next' => 'scene_11',
                ),
                array(
                    'text' => __( 'Return to the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_item_key', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン11: 裏帳簿（決定的証拠 / 告白と噛み合うalternative）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Hidden Ledger', 'novel-game-plugin' ),
            'background'           => $bg_hidden_room,
            'character_left'       => '',
            'character_center'     => $det_determined,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'The key turns with a click that echoes like a gunshot in the tiny room.', 'novel-game-plugin' ),
                __( 'Inside: bundles of cash, and a second ledger — money moving every month from a city contractor through Kurosaki\'s firm.', 'novel-game-plugin' ),
                __( 'Public money, washed clean and returned as "consulting fees". And every page is initialed by the same hand: "T.T."', 'novel-game-plugin' ),
                __( 'Takagi Construction. The biggest name in the harbor redevelopment. A dangerous name to put in a ledger.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', '', 'center', 'center' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_flag_conditions' => array(
                2 => array(
                    'conditions' => array(
                        array( 'name' => $f_wife_confess, 'state' => 1 ),
                    ),
                    'logic'           => 'AND',
                    'displayMode'     => 'alternative',
                    'alternativeText' => __( '"The September payment is the last one." The entries stop in September — exactly as Misaki heard him promise. And every page bears the same initials: "T.T."', 'novel-game-plugin' ),
                ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Pay a visit to Takagi Construction', 'novel-game-plugin' ),
                    'next' => 'scene_15',
                    'flagConditions' => array(
                        array( 'name' => $f_met_takagi, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Have the photo identified first', 'novel-game-plugin' ),
                    'next' => 'scene_12',
                    'flagConditions' => array(
                        array( 'name' => $f_photo, 'state' => true ),
                        array( 'name' => $f_informant_intel, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Return to the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_item_ledger', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン12: 情報屋（権田の特定と佐藤の借金）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Informant', 'novel-game-plugin' ),
            'background'           => $bg_alley,
            'character_left'       => $det_serious,
            'character_center'     => '',
            'character_right'      => $char_informant,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_informant,
            'dialogue_texts'       => array(
                __( 'Two minutes of your time. Who are the men in this photo?', 'novel-game-plugin' ),
                __( 'Heh. The tall one is Gonda — the Ryu-gumi\'s collections man. Loans, threats, disposal. Full service.', 'novel-game-plugin' ),
                __( 'And one more thing, since you pay well. Your client\'s friend — Sato, the cafe gentleman — owes the Ryu-gumi three million.', 'novel-game-plugin' ),
                __( 'Sato owes the same people who took Makoto. And it was Sato\'s photo that led me here. How very convenient.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'left', 'right', 'right', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_informant ),
                1 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_informant ),
                2 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_informant ),
                3 => array( 'left' => $det_determined, 'center' => '', 'right' => $char_informant ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Walk into the Ryu-gumi\'s bar', 'novel-game-plugin' ),
                    'next' => 'scene_13',
                    'flagConditions' => array(
                        array( 'name' => $f_yakuza_slip, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Press Sato about his debt', 'novel-game-plugin' ),
                    'next' => 'scene_14',
                    'flagConditions' => array(
                        array( 'name' => $f_sato_confess, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Return to the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_met_underworld', 'value' => 1 ),
                array( 'id' => 'flag_sato_debt', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン13: 龍の巣（幹部の失言 = 生存の根拠 / 危険な選択肢あり）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Dragon\'s Den', 'novel-game-plugin' ),
            'background'           => $bg_bar,
            'character_left'       => $det_determined,
            'character_center'     => '',
            'character_right'      => $char_yakuza,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_yakuza,
            'dialogue_texts'       => array(
                __( 'A detective, in my bar. You\'ve got nerve — or nothing left to lose.', 'novel-game-plugin' ),
                __( 'Makoto Kurosaki. Your man Gonda met him on the wharf the night he vanished. I can put them both there at 9:47.', 'novel-game-plugin' ),
                __( 'Careful now. We lent Kurosaki money, that\'s all. Think, detective — a dead man repays nothing. Thirty million says we keep him breathing.', 'novel-game-plugin' ),
                __( '"We keep him breathing." Not "we don\'t have him." The dragon just showed me one of his cards.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'right', 'left', 'right', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_determined, 'center' => '', 'right' => $char_yakuza ),
                1 => array( 'left' => $det_determined, 'center' => '', 'right' => $char_yakuza ),
                2 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_yakuza ),
                3 => array( 'left' => $det_thinking, 'center' => '', 'right' => $char_yakuza ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Press Sato about his debt', 'novel-game-plugin' ),
                    'next' => 'scene_14',
                    'flagConditions' => array(
                        array( 'name' => $f_sato_debt, 'state' => true ),
                        array( 'name' => $f_sato_confess, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Force your way into the back rooms', 'novel-game-plugin' ),
                    'next' => 'scene_25',
                ),
                array(
                    'text' => __( 'Return to the office', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_yakuza_hint', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン14: 友の告白（海岸道路の目撃証言）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — A Friend\'s Confession', 'novel-game-plugin' ),
            'background'           => $bg_cafe,
            'character_left'       => $det_serious,
            'character_center'     => '',
            'character_right'      => $char_sato,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_sato,
            'dialogue_texts'       => array(
                __( 'Three million to the Ryu-gumi, Sato. And a perfect photo of Makoto, taken the week he disappears. Start talking.', 'novel-game-plugin' ),
                __( '...They said they only wanted his schedule! A debt wiped clean, for a few dates and places. I never thought they would—', 'novel-game-plugin' ),
                __( 'The night it happened, Gonda\'s car passed me by the harbor gate. It didn\'t turn toward their office. It took the coast road — toward President Takagi\'s villa.', 'novel-game-plugin' ),
                __( 'The coast road. Sato — that guilt of yours may have just saved your friend\'s life.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'left', 'right', 'right', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_sato ),
                1 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_sato ),
                2 => array( 'left' => $det_serious, 'center' => '', 'right' => $char_sato ),
                3 => array( 'left' => $det_determined, 'center' => '', 'right' => $char_sato ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Walk into the Ryu-gumi\'s bar', 'novel-game-plugin' ),
                    'next' => 'scene_13',
                    'flagConditions' => array(
                        array( 'name' => $f_informant_intel, 'state' => true ),
                        array( 'name' => $f_yakuza_slip, 'state' => false ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Return to the office and build the case', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_sato_confession', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン15: 港を牛耳る男（高木との初対面 / 不用意な一言）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Man Behind the Harbor', 'novel-game-plugin' ),
            'background'           => $bg_takagi,
            'character_left'       => $det_serious,
            'character_center'     => '',
            'character_right'      => $takagi_calm,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_takagi,
            'dialogue_texts'       => array(
                __( 'A private detective. How quaint. I gave Kurosaki-kun his first contract, you know. Terrible business, his disappearance.', 'novel-game-plugin' ),
                __( 'His firm moved your money for years. Consulting fees. Harbor redevelopment. The paperwork is remarkably tidy.', 'novel-game-plugin' ),
                __( 'Rumors. Show me one document with my name on it, and I will show you my lawyers.', 'novel-game-plugin' ),
                __( 'He didn\'t ask which money. From a careful man, a careless word is worth more than a confession.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'right', 'left', 'right', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_serious, 'center' => '', 'right' => $takagi_calm ),
                1 => array( 'left' => $det_serious, 'center' => '', 'right' => $takagi_calm ),
                2 => array( 'left' => $det_serious, 'center' => '', 'right' => $takagi_nervous ),
                3 => array( 'left' => $det_thinking, 'center' => '', 'right' => $takagi_nervous ),
            ),
            'dialogue_flag_conditions' => array(
                3 => array(
                    'conditions' => array(
                        array( 'name' => $f_ledger, 'state' => 1 ),
                    ),
                    'logic'           => 'AND',
                    'displayMode'     => 'alternative',
                    'alternativeText' => __( 'He wants a document with his name. I have a ledger with his initials on every page — but not here. Not until the whole picture is airtight.', 'novel-game-plugin' ),
                ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Assemble the deduction at the office', 'novel-game-plugin' ),
                    'next' => 'scene_16',
                    'flagConditions' => array(
                        array( 'name' => $f_memo, 'state' => true ),
                        array( 'name' => $f_ledger, 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Dig for more before the confrontation', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_met_takagi', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン16: 推理Ⅰ・盤面を動かす手（黒幕の特定 / setFlagsで回答記録）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — Deduction I: The Hand That Moves', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => '',
            'character_center'     => $det_thinking,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'Night. The desk lamp draws a small circle of light around everything I know.', 'novel-game-plugin' ),
                __( 'The ledger, the memo, the debts, the lies. Behind them all — whose hand has been moving the pieces?', 'novel-game-plugin' ),
                __( 'Name the wrong player, and the real one sweeps the board while I kick down the wrong door. Think.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'center', 'center' ),
            'dialogue_backgrounds' => array( '', '', '' ),
            'choices'              => array(
                array(
                    'text' => __( 'President Takagi. The initials, the money, the motive.', 'novel-game-plugin' ),
                    'next' => 'scene_17',
                    'flagConditions' => array(
                        array( 'name' => $f_memo, 'state' => true ),
                        array( 'name' => $f_ledger, 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                    'setFlags' => array(
                        array( 'name' => $f_deduce_culprit, 'state' => true ),
                    ),
                ),
                array(
                    'text' => __( 'The Ryu-gumi. Collectors turned kidnappers.', 'novel-game-plugin' ),
                    'next' => 'scene_24',
                ),
                array(
                    'text' => __( 'Sato. The friend who knew his every move.', 'novel-game-plugin' ),
                    'next' => 'scene_24',
                ),
                array(
                    'text' => __( 'Not yet. I will not accuse without proof.', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン17: 推理Ⅱ・生か死か（幹部の失言が鍵）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — Deduction II: Alive or Dead', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => '',
            'character_center'     => $det_thinking,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'Takagi gave the order; the Ryu-gumi carried it out. But carried out — what, exactly?', 'novel-game-plugin' ),
                __( 'The watch was dropped, not smashed. No blood on the wharf, no body in the bay, no ransom note on the door.', 'novel-game-plugin' ),
                __( 'So answer it straight: is Makoto Kurosaki alive?', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'center', 'center', 'center' ),
            'dialogue_backgrounds' => array( '', '', '' ),
            'dialogue_flag_conditions' => array(
                1 => array(
                    'conditions' => array(
                        array( 'name' => $f_yakuza_slip, 'state' => 1 ),
                    ),
                    'logic'           => 'AND',
                    'displayMode'     => 'alternative',
                    'alternativeText' => __( '"A dead man repays nothing. Thirty million says we keep him breathing." The executive\'s slip fits the clean wharf: no blood, no body, no note.', 'novel-game-plugin' ),
                ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Alive. He\'s worth nothing to them dead.', 'novel-game-plugin' ),
                    'next' => 'scene_18',
                    'flagConditions' => array(
                        array( 'name' => $f_yakuza_slip, 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                    'setFlags' => array(
                        array( 'name' => $f_deduce_alive, 'state' => true ),
                    ),
                ),
                array(
                    'text' => __( 'Dead. They silenced him that night.', 'novel-game-plugin' ),
                    'next' => 'scene_24',
                ),
                array(
                    'text' => __( 'He staged everything and fled abroad.', 'novel-game-plugin' ),
                    'next' => 'scene_24',
                ),
                array(
                    'text' => __( 'I can\'t call it yet. Back to the streets.', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン18: 推理Ⅲ・海岸道路の果て（佐藤の告白が鍵）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — Deduction III: The Coast Road', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => '',
            'character_center'     => $det_determined,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'Alive, hidden, and useful. Where does Takagi cage a man who knows where every yen is buried?', 'novel-game-plugin' ),
                __( 'Not the Ryu-gumi\'s bar — too many eyes already on it. Not a hotel — too many witnesses he doesn\'t own.', 'novel-game-plugin' ),
                __( 'Say it out loud, and stake the case on it.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'center', 'center', 'center' ),
            'dialogue_backgrounds' => array( '', '', '' ),
            'dialogue_flag_conditions' => array(
                1 => array(
                    'conditions' => array(
                        array( 'name' => $f_sato_confess, 'state' => 1 ),
                    ),
                    'logic'           => 'AND',
                    'displayMode'     => 'alternative',
                    'alternativeText' => __( 'Sato watched Gonda\'s car take the coast road that night. And the coast road ends at exactly one property worth hiding a man in.', 'novel-game-plugin' ),
                ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Takagi\'s villa, at the end of the coast road.', 'novel-game-plugin' ),
                    'next' => 'scene_19',
                    'flagConditions' => array(
                        array( 'name' => $f_sato_confess, 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                    'setFlags' => array(
                        array( 'name' => $f_deduce_place, 'state' => true ),
                    ),
                ),
                array(
                    'text' => __( 'The back rooms of the Ryu-gumi\'s bar.', 'novel-game-plugin' ),
                    'next' => 'scene_24',
                ),
                array(
                    'text' => __( 'A freighter already out of the harbor.', 'novel-game-plugin' ),
                    'next' => 'scene_24',
                ),
                array(
                    'text' => __( 'Not sure enough. One more round of legwork.', 'novel-game-plugin' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン19: 別荘（対決の開幕 / 表情差分の見せ場）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Villa', 'novel-game-plugin' ),
            'background'           => $bg_villa,
            'character_left'       => $det_determined,
            'character_center'     => '',
            'character_right'      => $takagi_calm,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_takagi,
            'dialogue_texts'       => array(
                __( 'The villa at the end of the coast road. Beyond the iron gate, a single window burns on the second floor.', 'novel-game-plugin' ),
                __( 'Detective. This is private property. One phone call and you are finished in this city.', 'novel-game-plugin' ),
                __( 'Make the call. The police will want to see the second floor too — and this ledger, initialed by you on every page.', 'novel-game-plugin' ),
                __( '...What do you want? Money? A seat on a board? Name your figure and disappear.', 'novel-game-plugin' ),
                __( 'The only figure I want is the man upstairs — alive. And then the truth. All of it, in your own words.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'right', 'left', 'right', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_determined, 'center' => '', 'right' => $takagi_calm ),
                1 => array( 'left' => $det_determined, 'center' => '', 'right' => $takagi_calm ),
                2 => array( 'left' => $det_determined, 'center' => '', 'right' => $takagi_nervous ),
                3 => array( 'left' => $det_determined, 'center' => '', 'right' => $takagi_angry ),
                4 => array( 'left' => $det_determined, 'center' => '', 'right' => $takagi_angry ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Lay every piece of evidence on the table', 'novel-game-plugin' ),
                    'next' => 'scene_20',
                ),
                array(
                    'text' => __( 'Demand to see Kurosaki first', 'novel-game-plugin' ),
                    'next' => 'scene_20',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン20: すべての真実（自白 / 回想の背景切替デモ）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Whole Truth', 'novel-game-plugin' ),
            'background'           => $bg_villa,
            'character_left'       => $det_serious,
            'character_center'     => '',
            'character_right'      => $takagi_regret,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_takagi,
            'dialogue_texts'       => array(
                __( 'Kurosaki wanted out. Out! With everything he knew about the harbor money still in that stubborn head of his.', 'novel-game-plugin' ),
                __( 'So Gonda collected him from the wharf that night. No one was to be hurt. He simply had to be persuaded — to stay quiet.', 'novel-game-plugin' ),
                __( 'The midnight calls? Gonda\'s idea. A telephone that rings and says nothing — so the house never forgets what silence sounds like.', 'novel-game-plugin' ),
                __( 'Then the ledger vanished from his study. Three days I asked him, politely, where it went. Three days, he said nothing at all.', 'novel-game-plugin' ),
                __( 'Upstairs, behind a locked door, a hollow-cheeked man lifts his head at the sound of new footsteps.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'right', 'right', 'right', 'right', '' ),
            // 2行目〜3行目は失踪の夜の回想として倉庫街を映し、4行目で別荘に戻す
            'dialogue_backgrounds' => array( '', $bg_warehouse, '', $bg_villa, '' ),
            'choices'              => array(
                array(
                    'text' => __( 'Open the door', 'novel-game-plugin' ),
                    'next' => 'scene_21',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(
                array( 'id' => 'flag_confronted_mastermind', 'value' => 1 ),
            ),
        ),

        // ---------------------------------------------------------------
        // シーン21: 二階の男（誠の保護 / エンディング分岐点）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — The Man Upstairs', 'novel-game-plugin' ),
            'background'           => $bg_villa,
            'character_left'       => $det_normal,
            'character_center'     => '',
            'character_right'      => $makoto_tired,
            'character_left_name'  => $name_detective,
            'character_center_name' => '',
            'character_right_name' => $name_makoto,
            'dialogue_texts'       => array(
                __( 'You\'re... not one of Takagi\'s men. Then who—', 'novel-game-plugin' ),
                __( 'A detective. Your wife hired me. She never once believed you ran.', 'novel-game-plugin' ),
                __( 'Misaki... I dropped the watch on the wharf, praying someone would read it right. Three days, I\'ve been holding on to that prayer.', 'novel-game-plugin' ),
                __( 'The police are on the coast road now. It\'s over. All that\'s left is to decide how much of the truth we hand them.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( 'right', 'left', 'right', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_characters'  => array(
                0 => array( 'left' => $det_normal, 'center' => '', 'right' => $makoto_tired ),
                1 => array( 'left' => $det_normal, 'center' => '', 'right' => $makoto_tired ),
                2 => array( 'left' => $det_normal, 'center' => '', 'right' => $makoto_relief ),
                3 => array( 'left' => $det_determined, 'center' => '', 'right' => $makoto_relief ),
            ),
            'choices'              => array(
                array(
                    'text' => __( 'Hand over everything — ledger, memo, watch, and every testimony', 'novel-game-plugin' ),
                    'next' => 'scene_22',
                    'flagConditions' => array(
                        array( 'name' => $f_watch, 'state' => true ),
                        array( 'name' => $f_photo, 'state' => true ),
                        array( 'name' => $f_wife_confess, 'state' => true ),
                        array( 'name' => $f_yakuza_slip, 'state' => true ),
                        array( 'name' => $f_sato_confess, 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Bring him home first. The rest can wait.', 'novel-game-plugin' ),
                    'next' => 'scene_23',
                ),
            ),
            'is_ending'            => false,
            'ending_text'          => '',
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン22: 結末・光の中へ（完全解決エンド）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — Ending: Into the Light', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => $misaki_smile,
            'character_center'     => '',
            'character_right'      => $makoto_relief,
            'character_left_name'  => $name_misaki,
            'character_center_name' => '',
            'character_right_name' => $name_makoto,
            'dialogue_texts'       => array(
                __( 'One week later, the harbor scandal owns every front page. Takagi arrested. The Ryu-gumi raided. The ledger in an evidence vault.', 'novel-game-plugin' ),
                __( 'Detective. You gave me back my husband — and the truth with him. I don\'t have words enough.', 'novel-game-plugin' ),
                __( 'The watch is back where it belongs. And this time, the September payment really was the last.', 'novel-game-plugin' ),
                __( 'Rain again tonight. Some cases end with a cell door closing. The good ones end with a lit window in a warm house.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'left', 'right', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'              => array(),
            'is_ending'            => true,
            'ending_text'          => __( 'Complete Solution — Every truth brought into the light', 'novel-game-plugin' ),
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン23: 結末・半分の真実（部分解決エンド）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — Ending: A Half Truth', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => '',
            'character_center'     => $det_thinking,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'Makoto Kurosaki came home. That much of the job, I did.', 'novel-game-plugin' ),
                __( 'But the chain of proof had gaps, and Takagi\'s lawyers cut the ledger loose. "Insufficient corroboration," the court called it.', 'novel-game-plugin' ),
                __( 'He sold the villa, paid a fine, and smiled for the cameras. The dragon lost a claw. Nothing more.', 'novel-game-plugin' ),
                __( 'A half truth keeps a man awake worse than any lie. Someday, Takagi. Someday.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'center', 'center', 'center' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'              => array(),
            'is_ending'            => true,
            'ending_text'          => __( 'Partial Solution — The mastermind slips the net', 'novel-game-plugin' ),
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン24: 結末・誤った扉（誤推理エンド）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — Ending: The Wrong Door', 'novel-game-plugin' ),
            'background'           => $bg_office,
            'character_left'       => '',
            'character_center'     => $det_thinking,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'I was so sure. I named the wrong name, kicked the wrong door — and watched the case fall apart in my hands.', 'novel-game-plugin' ),
                __( 'While the papers laughed at the detective who cried wolf, a quieter hand swept the board clean.', 'novel-game-plugin' ),
                __( 'The ledger is ash now. The witnesses have forgotten my face. And Makoto Kurosaki is still out there — somewhere.', 'novel-game-plugin' ),
                __( 'A detective\'s oath: when you are wrong, you start over — from the very first thread.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', 'center', 'center', 'center' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'              => array(),
            'is_ending'            => true,
            'ending_text'          => __( 'False Deduction — The truth slipped through your fingers', 'novel-game-plugin' ),
            'set_flags'            => array(),
        ),

        // ---------------------------------------------------------------
        // シーン25: 結末・袋小路（強行突入の代償）
        // ---------------------------------------------------------------
        array(
            'title'                => __( 'Shadow Detective — Ending: Dead End', 'novel-game-plugin' ),
            'background'           => $bg_alley,
            'character_left'       => '',
            'character_center'     => $det_serious,
            'character_right'      => '',
            'character_left_name'  => '',
            'character_center_name' => $name_detective,
            'character_right_name' => '',
            'dialogue_texts'       => array(
                __( 'Force, against a house built on force. It was over in half a minute.', 'novel-game-plugin' ),
                __( 'They left me in the back alley with a receipt written in bruises: the next visit ends in the harbor.', 'novel-game-plugin' ),
                __( 'By morning, every door in the case had quietly shut. Witnesses moved away. The client received a generous "settlement".', 'novel-game-plugin' ),
                __( 'A detective who runs out of patience runs out of everything. This case is a dead end — and the dead end is mine.', 'novel-game-plugin' ),
            ),
            'dialogue_speakers'    => array( '', '', 'center', 'center' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'              => array(),
            'is_ending'            => true,
            'ending_text'          => __( 'Dead End — The investigation is closed', 'novel-game-plugin' ),
            'set_flags'            => array(),
        ),
    );

    // フラグマスタデータ（証拠・証言・推理の記録）
    $flag_master = array(
        array(
            'id'          => 'flag_item_watch',
            'name'        => $f_watch,
            'description' => __( 'Makoto\'s pocket watch, found on the wharf. Stopped at 9:47 — dropped on purpose.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_item_photo',
            'name'        => $f_photo,
            'description' => __( 'A photo of Makoto with rough-looking men near the harbor, taken by Sato.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_item_note',
            'name'        => $f_memo,
            'description' => __( 'A memo in Makoto\'s hand: "Sept. transfer to T. The last one. I\'m out."', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_item_key',
            'name'        => $f_key,
            'description' => __( 'A brass key from the false-bottomed drawer. Opens the safe in the hidden room.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_item_ledger',
            'name'        => $f_ledger,
            'description' => __( 'A second ledger tracking laundered public money. Every page initialed "T.T."', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_talked_wife',
            'name'        => $f_wife_talk,
            'description' => __( 'Misaki spoke of sleepless nights, a pleading phone call, and a scarred visitor.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_wife_confession',
            'name'        => $f_wife_confess,
            'description' => __( 'Misaki admitted overhearing him: "The September payment is the last one. I\'m out."', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_talked_friend',
            'name'        => $f_sato_talk,
            'description' => __( 'Sato provided the photo — but his story has holes.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_sato_debt',
            'name'        => $f_sato_debt,
            'description' => __( 'The informant says Sato owes the Ryu-gumi three million.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_sato_confession',
            'name'        => $f_sato_confess,
            'description' => __( 'Sato sold Makoto\'s schedule for his debt — and saw Gonda\'s car take the coast road.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_found_hidden_room',
            'name'        => $f_hidden_room,
            'description' => __( 'Behind the study bookshelf: a dial-locked door someone else searched in a hurry.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_met_underworld',
            'name'        => $f_informant_intel,
            'description' => __( 'The tall man in the photo is Gonda, the Ryu-gumi\'s collections man.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_yakuza_hint',
            'name'        => $f_yakuza_slip,
            'description' => __( '"A dead man repays nothing. Thirty million says we keep him breathing."', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_met_takagi',
            'name'        => $f_met_takagi,
            'description' => __( 'President Takagi never asked which money. A careless word from a careful man.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_deduce_culprit',
            'name'        => $f_deduce_culprit,
            'description' => __( 'Concluded that President Takagi is the hand moving the pieces.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_deduce_alive',
            'name'        => $f_deduce_alive,
            'description' => __( 'Concluded that Makoto is alive — worth nothing to them dead.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_deduce_place',
            'name'        => $f_deduce_place,
            'description' => __( 'Concluded that Makoto is held at the villa on the coast road.', 'novel-game-plugin' ),
        ),
        array(
            'id'          => 'flag_confronted_mastermind',
            'name'        => $f_confronted,
            'description' => __( 'Confronted Takagi at the villa with the ledger.', 'novel-game-plugin' ),
        ),
    );

    return array(
        'game'        => $game_data,
        'scenes'      => $scenes,
        'flag_master' => $flag_master,
    );
}

/**
 * ゲームのシーンを生成する
 *
 * 指定されたゲームIDとタイトルに対して、シーンデータからシーンを生成する
 * 
 * @param int    $game_id     ゲームID
 * @param string $target_title ゲームタイトル（メタデータ保存用）
 * @param array  $scenes_data シーンデータの配列
 * @param array  $flag_master フラグマスタ配列（id->name 変換用）
 * @return int 作成されたシーン数
 * @since 1.3.0
 */
function noveltool_generate_scenes_for_game( $game_id, $target_title, $scenes_data, $flag_master = array() ) {
    if ( ! $game_id || ! $target_title || empty( $scenes_data ) ) {
        noveltool_log( 'noveltool_generate_scenes_for_game: Invalid parameters' );
        return 0;
    }
    
    noveltool_log( sprintf( 'noveltool_generate_scenes_for_game: Starting scene generation for game ID %d (%s)', $game_id, $target_title ) );
    
    // シーンを作成し、IDを記録
    $scene_ids = array();
    $creation_errors = array();
    $first_scene_id = null;

    // フラグIDからフラグ名への逆引きマップを作成
    $flag_id_to_name = array();
    if ( is_array( $flag_master ) ) {
        foreach ( $flag_master as $flag ) {
            if ( isset( $flag['id'], $flag['name'] ) ) {
                $flag_id_to_name[ (string) $flag['id'] ] = (string) $flag['name'];
            }
        }
    }
    
    foreach ( $scenes_data as $index => $scene_data ) {
        // 投稿を作成
        $post_data = array(
            'post_type'    => 'novel_game',
            'post_title'   => $scene_data['title'],
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        );
        
        $post_id = wp_insert_post( $post_data );
        
        if ( is_wp_error( $post_id ) || ! $post_id ) {
            // エラーをログに記録
            $creation_errors[] = sprintf(
                'Failed to create scene %d (%s): %s',
                $index + 1,
                $scene_data['title'],
                is_wp_error( $post_id ) ? $post_id->get_error_message() : 'wp_insert_post returned 0'
            );
            continue;
        }
        
        // シーンIDを記録
        $scene_ids[ 'scene_' . ( $index + 1 ) ] = $post_id;
        
        // 最初のシーンIDを保存
        if ( 0 === $index ) {
            $first_scene_id = $post_id;
        }
        
        // メタデータを保存
        update_post_meta( $post_id, '_game_title', $target_title );
        
        // 最初のシーンを開始シーンとして設定
        if ( 0 === $index ) {
            update_post_meta( $post_id, '_is_start_scene', '1' );
        }
        
        $bg = isset( $scene_data['background'] ) ? esc_url_raw( $scene_data['background'] ) : '';
        update_post_meta( $post_id, '_background_image', $bg );
        
        $left = isset( $scene_data['character_left'] ) ? esc_url_raw( $scene_data['character_left'] ) : '';
        $center = isset( $scene_data['character_center'] ) ? esc_url_raw( $scene_data['character_center'] ) : '';
        $right = isset( $scene_data['character_right'] ) ? esc_url_raw( $scene_data['character_right'] ) : '';
        update_post_meta( $post_id, '_character_left', $left );
        update_post_meta( $post_id, '_character_center', $center );
        update_post_meta( $post_id, '_character_right', $right );
        
        update_post_meta( $post_id, '_character_left_name', $scene_data['character_left_name'] );
        update_post_meta( $post_id, '_character_center_name', $scene_data['character_center_name'] );
        update_post_meta( $post_id, '_character_right_name', $scene_data['character_right_name'] );
        
        // セリフデータを保存（新形式）
        update_post_meta( $post_id, '_dialogue_texts', wp_json_encode( $scene_data['dialogue_texts'], JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_dialogue_speakers', wp_json_encode( $scene_data['dialogue_speakers'], JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_dialogue_backgrounds', wp_json_encode( $scene_data['dialogue_backgrounds'], JSON_UNESCAPED_UNICODE ) );
        
        // セリフのフラグ条件データを保存
        // 注意: フロントエンド出力(noveltool_filter_novel_game_content)と管理画面は
        // 「セリフindexをキーとした配列」を期待するため、JSON文字列ではなく配列のまま保存する
        if ( isset( $scene_data['dialogue_flag_conditions'] ) && is_array( $scene_data['dialogue_flag_conditions'] ) ) {
            update_post_meta(
                $post_id,
                '_dialogue_flag_conditions',
                $scene_data['dialogue_flag_conditions']
            );
        }
        
        // セリフごとのキャラクター設定（表情差分）を保存
        if ( isset( $scene_data['dialogue_characters'] ) && is_array( $scene_data['dialogue_characters'] ) ) {
            // dialogue_characters内の画像URLをサニタイズ
            $sanitized_dialogue_characters = array();
            foreach ( $scene_data['dialogue_characters'] as $idx => $char_setting ) {
                $sanitized_dialogue_characters[ $idx ] = array(
                    'left'   => isset( $char_setting['left'] ) ? esc_url_raw( $char_setting['left'] ) : '',
                    'center' => isset( $char_setting['center'] ) ? esc_url_raw( $char_setting['center'] ) : '',
                    'right'  => isset( $char_setting['right'] ) ? esc_url_raw( $char_setting['right'] ) : '',
                );
            }
            update_post_meta(
                $post_id,
                '_dialogue_characters',
                wp_json_encode( $sanitized_dialogue_characters, JSON_UNESCAPED_UNICODE )
            );
        }
        
        // エンディング設定
        update_post_meta( $post_id, '_is_ending', $scene_data['is_ending'] );
        update_post_meta( $post_id, '_ending_text', $scene_data['ending_text'] );
        
        // set_flags を保存（フラグ設定用）
        if ( ! empty( $scene_data['set_flags'] ) ) {
            update_post_meta( $post_id, '_set_flags', wp_json_encode( $scene_data['set_flags'], JSON_UNESCAPED_UNICODE ) );

            // 互換: set_flags からシーン到達時フラグ（_scene_arrival_flags）を生成
            // フロントエンドは _scene_arrival_flags を参照して到達時フラグを適用する。
            $scene_arrival_flags = array();
            foreach ( $scene_data['set_flags'] as $flag_item ) {
                if ( ! is_array( $flag_item ) || empty( $flag_item['id'] ) || empty( $flag_item['value'] ) ) {
                    continue;
                }

                $flag_id = (string) $flag_item['id'];
                if ( isset( $flag_id_to_name[ $flag_id ] ) ) {
                    $scene_arrival_flags[] = $flag_id_to_name[ $flag_id ];
                }
            }

            if ( ! empty( $scene_arrival_flags ) ) {
                update_post_meta( $post_id, '_scene_arrival_flags', array_values( array_unique( $scene_arrival_flags ) ) );
            }
        }
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
                    $choice_data = array(
                        'text' => $choice['text'],
                        'next' => $scene_ids[ $choice['next'] ],
                    );
                    
                    // flagConditions と flagConditionLogic を保存
                    if ( isset( $choice['flagConditions'] ) && is_array( $choice['flagConditions'] ) ) {
                        $choice_data['flagConditions'] = $choice['flagConditions'];
                    }

                    if ( isset( $choice['flagConditionLogic'] ) ) {
                        $choice_data['flagConditionLogic'] = $choice['flagConditionLogic'];
                    }

                    // 選択肢で設定するフラグ（推理の回答記録などに使用）
                    if ( isset( $choice['setFlags'] ) && is_array( $choice['setFlags'] ) ) {
                        $choice_data['setFlags'] = $choice['setFlags'];
                    }

                    // 後方互換: required_flags（flag_id配列）を flagConditions（name配列）へ変換
                    if ( empty( $choice_data['flagConditions'] ) && isset( $choice['required_flags'] ) && is_array( $choice['required_flags'] ) ) {
                        $required_conditions = array();
                        foreach ( $choice['required_flags'] as $required_flag_id ) {
                            $required_flag_id = (string) $required_flag_id;
                            if ( isset( $flag_id_to_name[ $required_flag_id ] ) ) {
                                $required_conditions[] = array(
                                    'name'  => $flag_id_to_name[ $required_flag_id ],
                                    'state' => true,
                                );
                            }
                        }

                        if ( ! empty( $required_conditions ) ) {
                            $choice_data['flagConditions'] = $required_conditions;
                            if ( empty( $choice_data['flagConditionLogic'] ) ) {
                                $choice_data['flagConditionLogic'] = 'AND';
                            }
                        }
                    }
                    
                    $choices[] = $choice_data;
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
        noveltool_log( 'noveltool_generate_scenes_for_game: Scene creation errors:' );
        foreach ( $creation_errors as $error ) {
            noveltool_log( '  - ' . $error );
        }
    }
    
    $created_count = count( $scene_ids );
    noveltool_log( sprintf( 'noveltool_generate_scenes_for_game: Completed. Created %d scenes for game ID %d', $created_count, $game_id ) );
    
    // 最初のシーンが作成された場合、ゲームの start_scene_id を更新
    if ( $first_scene_id ) {
        noveltool_update_game_start_scene( $target_title, $first_scene_id );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            noveltool_log( sprintf( 'noveltool_generate_scenes_for_game: Set start_scene_id to %d for game "%s"', $first_scene_id, $target_title ) );
        }
    }
    
    return $created_count;
}

/**
 * Shadow Detectiveゲームをインストール
 *
 * 本格推理ゲーム「影の探偵」をインストールする
 * 機械識別子（machine_name）で既存チェックを行い、存在しない場合のみインストール
 * 
 * ⚠️ 重要: 既存インストール済みのゲームは自動で削除/上書きされません
 * 既存ゲームが見つかった場合は何も変更せずに false を返します
 * 
 * ⚠️ 注意: この関数は init アクション以降に呼び出される必要があります
 * WordPress 6.7以降では、翻訳ファイルは init アクション以降でのみ完全に利用可能です
 *
 * @return bool 成功した場合true、失敗または既に存在する場合false
 * @since 1.3.0
 */
function noveltool_install_shadow_detective_game() {
    // Shadow Detectiveデータを取得
    $detective_data = noveltool_get_shadow_detective_game_data();
    $game_data = $detective_data['game'];
    $scenes_data = $detective_data['scenes'];
    $flag_master = $detective_data['flag_master'];
    
    // Shadow Detectiveが既に存在するかチェック
    $existing_game = null;
    if ( isset( $game_data['machine_name'] ) ) {
        $existing_game = noveltool_get_game_by_machine_name( $game_data['machine_name'] );
    }
    
    // ⚠️ 重要: 既存インストール済みのゲームは自動で削除/上書きされません
    if ( $existing_game ) {
        noveltool_log( 'noveltool_install_shadow_detective_game: Existing game detected' );
        
        $target_title = $existing_game['title'] ?? '';
        if ( ! $target_title ) {
            noveltool_log( 'noveltool_install_shadow_detective_game: Existing game has no title' );
            return false;
        }
        
        $existing_scenes = noveltool_get_posts_by_game_title( $target_title );
        if ( is_array( $existing_scenes ) && count( $existing_scenes ) === 0 ) {
            noveltool_log( sprintf( 'noveltool_install_shadow_detective_game: Game exists but has 0 scenes, regenerating for "%s"', $target_title ) );
            
            // 既存ゲームの ID を取得
            $target_game_id = isset( $existing_game['id'] ) ? (int) $existing_game['id'] : null;
            if ( ! $target_game_id ) {
                noveltool_log( 'noveltool_install_shadow_detective_game: Cannot determine target game ID' );
                return false;
            }
            
            // フラグマスタが未保存なら保存
            $current_flag_master = noveltool_get_game_flag_master( $target_title );
            if ( empty( $current_flag_master ) && ! empty( $flag_master ) ) {
                noveltool_save_game_flag_master( $target_title, $flag_master );
                noveltool_log( 'noveltool_install_shadow_detective_game: Flag master saved' );
            }
            
            // シーン再生成（最初のシーンが開始シーンとして設定される）
            $created = noveltool_generate_scenes_for_game( $target_game_id, $target_title, $scenes_data, $flag_master );
            $expected_count = count( $scenes_data );
            if ( $created < $expected_count ) {
                noveltool_log( sprintf(
                    'noveltool_install_shadow_detective_game: Incomplete regeneration. Expected %d scenes, created %d',
                    $expected_count,
                    $created
                ) );
                return false;
            }

            // 既存ゲーム再生成後に画像参照先をuploadsへ統一
            noveltool_migrate_shadow_detective_image_references_to_uploads( $existing_game );
            return true;
        }

        // 既存ゲームに対しても画像参照先をuploadsへ移行
        $migrated = noveltool_migrate_shadow_detective_image_references_to_uploads( $existing_game );
        if ( $migrated ) {
            noveltool_log( 'noveltool_install_shadow_detective_game: Existing game image references migrated to uploads' );
            return true;
        }
        
        noveltool_log( sprintf( 'noveltool_install_shadow_detective_game: Game exists with %d scenes, skipping', count( $existing_scenes ) ) );
        return false; // 既に存在する場合はスキップ（何も変更しない）
    }
    
    // ゲームを作成
    $game_id = noveltool_save_game( $game_data );
    
    if ( ! $game_id ) {
        return false; // ゲーム作成に失敗
    }
    
    // フラグマスタを保存
    if ( ! empty( $flag_master ) ) {
        noveltool_save_game_flag_master( $game_data['title'], $flag_master );
    }
    
    // シーン生成を実行（最初のシーンが開始シーンとして設定される）
    $created_scenes = noveltool_generate_scenes_for_game( $game_id, $game_data['title'], $scenes_data, $flag_master );
    
    if ( $created_scenes < count( $scenes_data ) ) {
        // 一部のシーンの作成に失敗した場合
        noveltool_log( sprintf( 'noveltool_install_shadow_detective_game: Incomplete installation. Expected %d scenes, created %d', count( $scenes_data ), $created_scenes ) );
        return false;
    }

    // 新規作成時も画像参照先をuploadsへ統一
    $created_game = noveltool_get_game_by_machine_name( 'shadow_detective_v1' );
    if ( $created_game ) {
        noveltool_migrate_shadow_detective_image_references_to_uploads( $created_game );
    }
    
    return true;
}

/**
 * サンプル画像ディレクトリ（uploads/noveltool/sample-images）を削除する
 *
 * プラグインが管理するサンプル画像保存領域のみを削除対象とし、
 * ユーザーの uploads 配下のファイルには一切触れない。
 * パストラバーサル対策として、削除前に厳密なパス検証を実施する。
 *
 * @return array {
 *     @type bool     $success       削除処理全体の成否
 *     @type int      $deleted_count 削除したファイル数
 *     @type string[] $errors        エラーメッセージの配列
 * }
 * @since 1.6.0
 */
function noveltool_delete_sample_images() {
    $result = array(
        'success'       => false,
        'deleted_count' => 0,
        'errors'        => array(),
    );

    $sample_dir = noveltool_get_sample_images_directory();

    // ディレクトリが存在しない場合は既に削除済みとみなし成功を返す
    if ( ! is_dir( $sample_dir ) ) {
        $result['success'] = true;
        return $result;
    }

    // uploads ベースディレクトリの取得
    $upload_dir   = wp_upload_dir();
    $uploads_base = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';

    if ( empty( $uploads_base ) ) {
        $result['errors'][] = __( 'Failed to resolve uploads directory.', 'novel-game-plugin' );
        return $result;
    }

    // realpath でシンボリックリンクとパストラバーサルを解決
    $real_sample_dir = realpath( $sample_dir );
    $real_uploads    = realpath( $uploads_base );

    if ( false === $real_sample_dir || false === $real_uploads ) {
        $result['errors'][] = __( 'Failed to resolve directory path.', 'novel-game-plugin' );
        return $result;
    }

    // 厳密パス検証: uploads/noveltool/sample-images と完全一致することを確認
    $expected_dir = rtrim( $real_uploads, DIRECTORY_SEPARATOR )
        . DIRECTORY_SEPARATOR . 'noveltool'
        . DIRECTORY_SEPARATOR . 'sample-images';

    if ( $real_sample_dir !== $expected_dir ) {
        $result['errors'][] = __( 'Unexpected sample images directory path. Deletion aborted for safety.', 'novel-game-plugin' );
        return $result;
    }

    // 再帰的にディレクトリを削除
    $all_ok            = noveltool_delete_sample_images_recursive( $real_sample_dir, $real_sample_dir, $result );
    $result['success'] = $all_ok && empty( $result['errors'] );

    // 削除結果を監査ログとして記録
    if ( $result['success'] ) {
        noveltool_log( sprintf( '[NovelGamePlugin] Sample images deletion completed: %d file(s) deleted.', $result['deleted_count'] ) );
    } else {
        noveltool_log( sprintf(
            '[NovelGamePlugin] Sample images deletion partially failed: %d file(s) deleted. Errors: %s',
            $result['deleted_count'],
            implode( '; ', $result['errors'] )
        ) );
    }

    return $result;
}

/**
 * ディレクトリを再帰的に削除する内部ヘルパー
 *
 * 各エントリごとに is_link() / realpath() を検証し、$root_dir 配下に収まるものだけを処理する。
 * シンボリックリンクはリンク先を辿らず、リンク自体のみを削除対象とする。
 * 境界外と判定されたエントリは削除せず error_log に記録して安全側フォールバックする。
 *
 * @param string $dir      削除対象ディレクトリの絶対パス
 * @param string $root_dir 削除を許可する最上位ディレクトリの realpath（境界）
 * @param array  $result   結果配列（参照渡し）
 * @return bool 全削除に成功した場合 true
 * @since 1.6.0
 */
function noveltool_delete_sample_images_recursive( $dir, $root_dir, &$result ) {
    if ( ! is_dir( $dir ) ) {
        return true;
    }

    $items = scandir( $dir );
    if ( false === $items ) {
        $result['errors'][] = sprintf(
            /* translators: %s: directory name */
            __( 'Failed to read directory: %s', 'novel-game-plugin' ),
            basename( $dir )
        );
        return false;
    }

    $all_deleted = true;
    foreach ( $items as $item ) {
        if ( '.' === $item || '..' === $item ) {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        // シンボリックリンクはリンク先を辿らずリンク自体のみを削除する
        if ( is_link( $path ) ) {
            wp_delete_file( $path );
            if ( ! file_exists( $path ) ) {
                $result['deleted_count']++;
            } else {
                $msg = sprintf(
                    '[NovelGamePlugin] Failed to delete symlink: %s',
                    $path
                );
                noveltool_log( $msg );
                $result['errors'][] = sprintf(
                    /* translators: %s: file name */
                    __( 'Failed to delete file: %s', 'novel-game-plugin' ),
                    $item
                );
                $all_deleted = false;
            }
            continue;
        }

        // 通常エントリ: realpath で正規化し、root_dir 配下に留まることを確認
        $real_path = realpath( $path );
        if ( false === $real_path ) {
            // realpath が失敗した場合は安全側フォールバック（スキップ）
            noveltool_log( sprintf( '[NovelGamePlugin] Could not resolve path, skipping for safety: %s', $path ) );
            $result['errors'][] = sprintf(
                /* translators: %s: file or directory name */
                __( 'Failed to resolve path, skipped for safety: %s', 'novel-game-plugin' ),
                $item
            );
            $all_deleted = false;
            continue;
        }

        // root_dir 配下に収まっているか確認（境界チェック）
        $root_prefix = rtrim( $root_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
        if ( 0 !== strpos( $real_path, $root_prefix ) && $real_path !== rtrim( $root_dir, DIRECTORY_SEPARATOR ) ) {
            noveltool_log( sprintf( '[NovelGamePlugin] Path escaped root boundary, skipping for safety: %s', $real_path ) );
            $result['errors'][] = sprintf(
                /* translators: %s: file or directory name */
                __( 'Path is outside the allowed directory, skipped for safety: %s', 'novel-game-plugin' ),
                $item
            );
            $all_deleted = false;
            continue;
        }

        if ( is_dir( $real_path ) ) {
            $sub_ok = noveltool_delete_sample_images_recursive( $real_path, $root_dir, $result );
            if ( $sub_ok ) {
                if ( ! rmdir( $real_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- 境界検証済みサンプル画像ディレクトリの再帰削除
                    $result['errors'][] = sprintf(
                        /* translators: %s: directory name */
                        __( 'Failed to delete directory: %s', 'novel-game-plugin' ),
                        $item
                    );
                    $all_deleted = false;
                }
            } else {
                $all_deleted = false;
            }
        } else {
            wp_delete_file( $real_path );
            if ( ! file_exists( $real_path ) ) {
                $result['deleted_count']++;
            } else {
                $result['errors'][] = sprintf(
                    /* translators: %s: file name */
                    __( 'Failed to delete file: %s', 'novel-game-plugin' ),
                    $item
                );
                $all_deleted = false;
            }
        }
    }

    return $all_deleted;
}
