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
 * 「影の探偵」本格推理ゲームのデータを取得
 *
 * @return array Shadow Detectiveゲームのデータ構造
 * @since 1.3.0
 */
function noveltool_get_shadow_detective_game_data() {
    // プラグイン内のバンドル画像を使用（PNG形式）
    // SVG placeholders から高品質PNG画像への切り替え
    $plugin_url = NOVEL_GAME_PLUGIN_URL;
    
    // 背景画像（プラグイン同梱PNG）
    $bg_office = $plugin_url . 'assets/sample-images/bg-detective-office.png';
    $bg_warehouse = $plugin_url . 'assets/sample-images/bg-warehouse.png';
    $bg_mansion = $plugin_url . 'assets/sample-images/bg-mansion.png';
    $bg_cafe = $plugin_url . 'assets/sample-images/bg-cafe.png';
    $bg_study = $plugin_url . 'assets/sample-images/bg-study.png';
    $bg_hidden_room = $plugin_url . 'assets/sample-images/bg-hidden-room.png';
    $bg_alley = $plugin_url . 'assets/sample-images/bg-backstreet.png';
    $bg_yakuza_office = $plugin_url . 'assets/sample-images/bg-underground-bar.png';
    $bg_construction = $plugin_url . 'assets/sample-images/bg-abandoned-factory.png';
    $bg_villa = $plugin_url . 'assets/sample-images/bg-confrontation.png';
    
    // キャラクター画像（プラグイン同梱PNG、透過対応）
    
    // 主人公の表情差分
    $char_protagonist_normal = $plugin_url . 'assets/sample-images/char-protagonist-normal.png';
    $char_protagonist_thinking = $plugin_url . 'assets/sample-images/char-protagonist-thinking.png';
    $char_protagonist_serious = $plugin_url . 'assets/sample-images/char-protagonist-serious.png';
    $char_protagonist_determined = $plugin_url . 'assets/sample-images/char-protagonist-determined.png';
    
    // 美咲（Misaki）の表情差分
    $char_misaki_normal = $plugin_url . 'assets/sample-images/char-misaki-normal.png';
    $char_misaki_sad = $plugin_url . 'assets/sample-images/char-misaki-sad.png';
    $char_misaki_worried = $plugin_url . 'assets/sample-images/char-misaki-worried.png';
    $char_misaki_tense = $plugin_url . 'assets/sample-images/char-misaki-tense.png';
    $char_misaki_smile = $plugin_url . 'assets/sample-images/char-misaki-smile.png';
    
    // 誠（Makoto）の表情差分
    $char_makoto_relief = $plugin_url . 'assets/sample-images/char-makoto-relief.png';
    $char_makoto_tired = $plugin_url . 'assets/sample-images/char-makoto-tired.png';
    
    // 高木（Takagi）の表情差分
    $char_takagi_calm = $plugin_url . 'assets/sample-images/char-takagi-calm.png';
    $char_takagi_nervous = $plugin_url . 'assets/sample-images/char-takagi-nervous.png';
    $char_takagi_angry = $plugin_url . 'assets/sample-images/char-takagi-angry.png';
    $char_takagi_regret = $plugin_url . 'assets/sample-images/char-takagi-regret.png';
    
    // 佐藤（Sato）
    $char_sato = $plugin_url . 'assets/sample-images/char-sato.png';
    
    // 情報屋（Informant）
    $char_informant = $plugin_url . 'assets/sample-images/char-informant.png';
    
    // ヤクザ（Yakuza）
    $char_yakuza = $plugin_url . 'assets/sample-images/char-yakuza.png';
    
    // Shadow Detective ゲームの基本情報
    $game_data = array(
        'title'          => __( 'Shadow Detective', 'novel-game-plugin-sample' ),
        'description'    => __( 'A full-fledged mystery game. Investigate the disappearance of businessman Makoto Kurosaki and uncover the truth. Gather evidence and make the right choices to reach the complete solution ending.', 'novel-game-plugin-sample' ),
        'title_image'    => '',
        'game_over_text' => __( 'Investigation Failed', 'novel-game-plugin-sample' ),
        'is_sample'      => true,
        'machine_name'   => 'shadow_detective_v1', // 機械識別子（多言語環境での重複防止）
    );
    
    // シーンデータ（全23シーン）
    $scenes = array(
        // シーン1: 依頼者来訪（探偵事務所）
        // 主人公は左側、美咲は右側に配置（第三者視点の会話表示）
        array(
            'title'           => __( 'Shadow Detective - The Beginning', 'novel-game-plugin-sample' ),
            'background'      => $bg_office,
            'character_left'  => $char_protagonist_normal,
            'character_center' => '',
            'character_right' => $char_misaki_worried,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Misaki', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'On an autumn evening, a woman visited my office.', 'novel-game-plugin-sample' ),
                __( 'Detective, please help me... My husband has been missing for three days.', 'novel-game-plugin-sample' ),
                __( 'The police say there\'s no criminal activity, but my husband would never do this...', 'novel-game-plugin-sample' ),
                __( 'The client\'s name is Misaki Kurosaki. Her husband is businessman Makoto Kurosaki.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', 'right', 'right', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            // 表情差分：セリフごとにキャラクター画像を切り替え
            'dialogue_characters' => array(
                0 => array( 'left' => $char_protagonist_normal, 'center' => '', 'right' => $char_misaki_worried ),
                1 => array( 'left' => $char_protagonist_serious, 'center' => '', 'right' => $char_misaki_sad ),
                2 => array( 'left' => $char_protagonist_serious, 'center' => '', 'right' => $char_misaki_worried ),
                3 => array( 'left' => $char_protagonist_thinking, 'center' => '', 'right' => $char_misaki_worried ),
            ),
            'choices'         => array(
                array(
                    'text' => __( 'Please tell me the details', 'novel-game-plugin-sample' ),
                    'next' => 'scene_2',
                ),
                array(
                    'text' => __( 'When did you last see him?', 'novel-game-plugin-sample' ),
                    'next' => 'scene_2',
                ),
                array(
                    'text' => __( 'What kind of person is your husband?', 'novel-game-plugin-sample' ),
                    'next' => 'scene_2',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン2: 失踪の詳細聴取
        // 主人公は左側、美咲は右側に配置（引き続き会話シーン）
        array(
            'title'           => __( 'Shadow Detective - Details of Disappearance', 'novel-game-plugin-sample' ),
            'background'      => $bg_office,
            'character_left'  => $char_protagonist_serious,
            'character_center' => '',
            'character_right' => $char_misaki_sad,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Misaki', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'Three days ago in the evening, my husband said "I have an important business meeting" and left.', 'novel-game-plugin-sample' ),
                __( 'Since then, his phone hasn\'t connected, and he hasn\'t shown up at the company.', 'novel-game-plugin-sample' ),
                __( 'Recently he seemed strange... as if he was worried about something.', 'novel-game-plugin-sample' ),
                __( 'Strange behavior before disappearing. This might not be a simple runaway case.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'right', 'right', 'right', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            // 表情差分：セリフごとにキャラクター画像を切り替え
            'dialogue_characters' => array(
                0 => array( 'left' => $char_protagonist_serious, 'center' => '', 'right' => $char_misaki_sad ),
                1 => array( 'left' => $char_protagonist_serious, 'center' => '', 'right' => $char_misaki_worried ),
                2 => array( 'left' => $char_protagonist_thinking, 'center' => '', 'right' => $char_misaki_tense ),
                3 => array( 'left' => $char_protagonist_determined, 'center' => '', 'right' => $char_misaki_sad ),
            ),
            'choices'         => array(
                array(
                    'text' => __( 'First, I\'ll investigate the disappearance site', 'novel-game-plugin-sample' ),
                    'next' => 'scene_3',
                ),
                array(
                    'text' => __( 'Let me talk to your family', 'novel-game-plugin-sample' ),
                    'next' => 'scene_5',
                ),
                array(
                    'text' => __( 'I\'d like to hear from friends and acquaintances', 'novel-game-plugin-sample' ),
                    'next' => 'scene_6',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン3: 失踪現場の調査（懐中時計入手）
        // 主人公の独白シーンのため、character_center に配置し dialogue_speakers を空に設定
        array(
            'title'           => __( 'Shadow Detective - Warehouse District', 'novel-game-plugin-sample' ),
            'background'      => $bg_warehouse,
            'character_left'  => '',
            'character_center' => $char_protagonist_serious,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'The warehouse district by the port where he was last seen. A deserted, dimly lit place.', 'novel-game-plugin-sample' ),
                __( 'Something shiny is on the ground... It\'s a pocket watch.', 'novel-game-plugin-sample' ),
                __( 'Opening the back reveals an inscription: "To Makoto, with love, Misaki"', 'novel-game-plugin-sample' ),
                __( 'This must belong to Makoto Kurosaki. Something happened here.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( $bg_warehouse, '', '', $bg_warehouse ),
            'choices'         => array(
                array(
                    'text' => __( 'Continue questioning the area', 'novel-game-plugin-sample' ),
                    'next' => 'scene_5',
                ),
                array(
                    'text' => __( 'Return to the office to organize information', 'novel-game-plugin-sample' ),
                    'next' => 'scene_4',
                ),
                array(
                    'text' => __( 'Search more carefully around the pocket watch', 'novel-game-plugin-sample' ),
                    'next' => 'scene_4',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(
                array( 'id' => 'flag_item_watch', 'value' => 1 ),
            ),
        ),
        
        // シーン4: 情報整理と方針決定
        // 主人公の思案シーンのため、character_center に配置し dialogue_speakers を空に設定
        array(
            'title'           => __( 'Shadow Detective - Time to Deduce', 'novel-game-plugin-sample' ),
            'background'      => $bg_office,
            'character_left'  => '',
            'character_center' => $char_protagonist_thinking,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Time to organize the gathered information.', 'novel-game-plugin-sample' ),
                __( 'The pocket watch... evidence he was at the disappearance site.', 'novel-game-plugin-sample' ),
                __( 'A businessman\'s sudden disappearance. Mention of family noticing strange behavior.', 'novel-game-plugin-sample' ),
                __( 'This case doesn\'t seem superficial.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_flag_conditions' => array(
                2 => array( // 3行目（index 2）: 証拠取得状況による推理の変化
                    'conditions' => array(
                        array( 'name' => 'Pocket Watch', 'state' => 1 ),
                    ),
                    'logic' => 'AND',
                    'displayMode' => 'alternative',
                    'alternativeText' => __( 'The pocket watch found at the scene... and sudden disappearance. This connects to something bigger.', 'novel-game-plugin-sample' ),
                ),
            ),
            'choices'         => array(
                array(
                    'text' => __( 'Prioritize questioning the family', 'novel-game-plugin-sample' ),
                    'next' => 'scene_5',
                ),
                array(
                    'text' => __( 'Investigate friends and associates', 'novel-game-plugin-sample' ),
                    'next' => 'scene_6',
                ),
                array(
                    'text' => __( 'Search for clues in the company records', 'novel-game-plugin-sample' ),
                    'next' => 'scene_6',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン5: 妻への聴取
        // 主人公は右側、美咲は左側に配置（左右を入れ替えることで場面の変化を表現）
        array(
            'title'           => __( 'Shadow Detective - Family Testimony', 'novel-game-plugin-sample' ),
            'background'      => $bg_mansion,
            'character_left'  => $char_misaki_sad,
            'character_center' => '',
            'character_right' => $char_protagonist_serious,
            'character_left_name' => __( 'Misaki', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'Actually... my husband was recently troubled by business management issues.', 'novel-game-plugin-sample' ),
                __( 'He seemed to have problems with business partners... but he wouldn\'t tell me details.', 'novel-game-plugin-sample' ),
                __( 'Also... there were calls from unknown men.', 'novel-game-plugin-sample' ),
                __( 'My husband said "It\'s fine" but his expression was dark...', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'left', 'left', 'left', 'left' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Talk to company associates', 'novel-game-plugin-sample' ),
                    'next' => 'scene_6',
                ),
                array(
                    'text' => __( 'Let me investigate the study', 'novel-game-plugin-sample' ),
                    'next' => 'scene_7',
                    'flagConditions' => array(
                        array( 'name' => 'Talked to Wife', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Ask about the unknown callers in detail', 'novel-game-plugin-sample' ),
                    'next' => 'scene_11',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(
                array( 'id' => 'flag_talked_wife', 'value' => 1 ),
            ),
        ),
        
        // シーン6: 友人の証言と証拠写真入手
        // 主人公は左側、佐藤は右側に配置
        array(
            'title'           => __( 'Shadow Detective - Friend\'s Testimony', 'novel-game-plugin-sample' ),
            'background'      => $bg_cafe,
            'character_left'  => $char_protagonist_normal,
            'character_center' => '',
            'character_right' => $char_sato,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Sato', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'Mr. Makoto has been acting strange recently.', 'novel-game-plugin-sample' ),
                __( 'Last week, I happened to see him... talking with suspicious men.', 'novel-game-plugin-sample' ),
                __( 'I was worried, so I took a photo. Here it is.', 'novel-game-plugin-sample' ),
                __( 'In the photo, Kurosaki is with men who clearly seem to be from the underworld...', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'right', 'right', 'right', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Investigate these men', 'novel-game-plugin-sample' ),
                    'next' => 'scene_11',
                    'flagConditions' => array(
                        array( 'name' => 'Evidence Photo', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'First, investigate the Kurosaki residence more', 'novel-game-plugin-sample' ),
                    'next' => 'scene_7',
                ),
                array(
                    'text' => __( 'Ask Sato for more information', 'novel-game-plugin-sample' ),
                    'next' => 'scene_9',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(
                array( 'id' => 'flag_talked_friend', 'value' => 1 ),
                array( 'id' => 'flag_item_photo', 'value' => 1 ),
            ),
        ),
        
        // シーン7: 黒崎邸の書斎調査
        // 主人公の独白・調査シーンのため、character_center に配置
        array(
            'title'           => __( 'Shadow Detective - Secret of the Study', 'novel-game-plugin-sample' ),
            'background'      => $bg_study,
            'character_left'  => '',
            'character_center' => $char_protagonist_serious,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'With permission, I investigate the study.', 'novel-game-plugin-sample' ),
                __( 'Desk drawers contain receipts and contracts... nothing particularly suspicious.', 'novel-game-plugin-sample' ),
                __( 'However, the bookshelf arrangement seems unnatural...', 'novel-game-plugin-sample' ),
                __( 'Moving the bookshelf reveals... a hidden door!', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( $bg_study, '', '', $bg_hidden_room ),
            'choices'         => array(
                array(
                    'text' => __( 'Try to open the hidden door', 'novel-game-plugin-sample' ),
                    'next' => 'scene_8',
                ),
                array(
                    'text' => __( 'Retreat for now', 'novel-game-plugin-sample' ),
                    'next' => 'scene_9',
                ),
                array(
                    'text' => __( 'Search the study more thoroughly first', 'novel-game-plugin-sample' ),
                    'next' => 'scene_10',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン8: 隠し部屋の発見と手記入手
        // 主人公の独白・調査シーンのため、character_center に配置
        array(
            'title'           => __( 'Shadow Detective - Hidden Truth', 'novel-game-plugin-sample' ),
            'background'      => $bg_hidden_room,
            'character_left'  => '',
            'character_center' => $char_protagonist_determined,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Beyond the hidden door is a small room. There\'s a safe and a desk.', 'novel-game-plugin-sample' ),
                __( 'A diary on the desk... It\'s Makoto Kurosaki\'s handwriting.', 'novel-game-plugin-sample' ),
                __( '"I chose the wrong path..."', 'novel-game-plugin-sample' ),
                __( '"I can\'t repay the debt to them. I don\'t want to trouble my family."', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( $bg_hidden_room, '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Try to open the safe', 'novel-game-plugin-sample' ),
                    'next' => 'scene_10',
                    'flagConditions' => array(
                        array( 'name' => 'Discovered Hidden Room', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Take the diary back for analysis', 'novel-game-plugin-sample' ),
                    'next' => 'scene_9',
                ),
                array(
                    'text' => __( 'Investigate other items in the hidden room', 'novel-game-plugin-sample' ),
                    'next' => 'scene_9',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(
                array( 'id' => 'flag_item_note', 'value' => 1 ),
            ),
        ),
        
        // シーン9: 情報の分析
        // 主人公の思案シーンのため、character_center に配置し dialogue_speakers を空に設定
        array(
            'title'           => __( 'Shadow Detective - To the Core of the Case', 'novel-game-plugin-sample' ),
            'background'      => $bg_office,
            'character_left'  => '',
            'character_center' => $char_protagonist_thinking,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'From the diary, it\'s clear Kurosaki had debt problems.', 'novel-game-plugin-sample' ),
                __( 'The men in the photo from my friend... loan sharks?', 'novel-game-plugin-sample' ),
                __( 'But why a hidden room? There must be something he\'s hiding.', 'novel-game-plugin-sample' ),
                __( 'I need to find the key to the safe.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( $bg_office, '', '', '' ),
            'dialogue_flag_conditions' => array(
                1 => array( // 2行目（index 1）: 証拠収集状況による推理の変化
                    'conditions' => array(
                        array( 'name' => 'Evidence Photo', 'state' => 1 ),
                    ),
                    'logic' => 'AND',
                    'displayMode' => 'alternative',
                    'alternativeText' => __( 'The men in the photo from my friend... clearly loan sharks from Ryu-gumi.', 'novel-game-plugin-sample' ),
                ),
                2 => array( // 3行目（index 2）: 隠し部屋発見後の推理変化
                    'conditions' => array(
                        array( 'name' => 'Discovered Hidden Room', 'state' => 1 ),
                    ),
                    'logic' => 'AND',
                    'displayMode' => 'alternative',
                    'alternativeText' => __( 'The hidden room I found... There\'s definitely something big he was hiding.', 'novel-game-plugin-sample' ),
                ),
            ),
            'choices'         => array(
                array(
                    'text' => __( 'Ask the wife about the safe key', 'novel-game-plugin-sample' ),
                    'next' => 'scene_10',
                ),
                array(
                    'text' => __( 'Pursue the men in the photo', 'novel-game-plugin-sample' ),
                    'next' => 'scene_11',
                    'flagConditions' => array(
                        array( 'name' => 'Evidence Photo', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Carefully review all gathered evidence', 'novel-game-plugin-sample' ),
                    'next' => 'scene_10',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン10: 金庫の鍵入手
        // 主人公は右側、美咲は左側に配置（左右が入れ替わるシーン）
        array(
            'title'           => __( 'Shadow Detective - Finding the Key', 'novel-game-plugin-sample' ),
            'background'      => $bg_mansion,
            'character_left'  => $char_misaki_normal,
            'character_center' => '',
            'character_right' => $char_protagonist_normal,
            'character_left_name' => __( 'Misaki', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'The safe key... Oh, I think there was a spare in my husband\'s study drawer.', 'novel-game-plugin-sample' ),
                __( 'Guided by Misaki, I find a small key deep in the drawer.', 'novel-game-plugin-sample' ),
                __( 'Will this reveal anything...?', 'novel-game-plugin-sample' ),
                __( 'This key might open the safe in the hidden room.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'left', '', 'left', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Open the safe in the hidden room immediately', 'novel-game-plugin-sample' ),
                    'next' => 'scene_13',
                    'flagConditions' => array(
                        array( 'name' => 'Discovered Hidden Room', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Gather other clues first', 'novel-game-plugin-sample' ),
                    'next' => 'scene_11',
                    'flagConditions' => array(
                        array( 'name' => 'Evidence Photo', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Check with the wife about her husband\'s business dealings', 'novel-game-plugin-sample' ),
                    'next' => 'scene_11',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(
                array( 'id' => 'flag_item_key', 'value' => 1 ),
                array( 'id' => 'flag_found_hidden_room', 'value' => 1 ),
            ),
        ),
        
        // シーン11: 裏社会への接触
        // 主人公は左側、情報屋は右側に配置
        array(
            'title'           => __( 'Shadow Detective - Dangerous Investigation', 'novel-game-plugin-sample' ),
            'background'      => $bg_alley,
            'character_left'  => $char_protagonist_serious,
            'character_center' => '',
            'character_right' => $char_informant,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Informant', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'I ask a well-connected informant about the men in the photo.', 'novel-game-plugin-sample' ),
                __( 'Ah, these guys... dangerous bunch.', 'novel-game-plugin-sample' ),
                __( 'They\'re from "Ryu-gumi", a loan shark operation. Once you\'re in, you\'re done.', 'novel-game-plugin-sample' ),
                __( 'Makoto Kurosaki? Yeah, I\'ve heard. He owed them a huge amount.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', 'right', 'right', 'right' ),
            'dialogue_backgrounds' => array( $bg_alley, '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Investigate Ryu-gumi in detail', 'novel-game-plugin-sample' ),
                    'next' => 'scene_12',
                ),
                array(
                    'text' => __( 'Organize information for now', 'novel-game-plugin-sample' ),
                    'next' => 'scene_9',
                ),
                array(
                    'text' => __( 'Search for more connections to Ryu-gumi', 'novel-game-plugin-sample' ),
                    'next' => 'scene_13',
                    'flagConditions' => array(
                        array( 'name' => 'Hidden Room Key', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン12: 龍組の事務所訪問
        // 主人公は左側、ヤクザ幹部は右側に配置（危険な対峙シーン）
        array(
            'title'           => __( 'Shadow Detective - Dragon\'s Den', 'novel-game-plugin-sample' ),
            'background'      => $bg_yakuza_office,
            'character_left'  => $char_protagonist_determined,
            'character_center' => '',
            'character_right' => $char_yakuza,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Ryu-gumi Executive', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'Makoto Kurosaki? Yeah, I know him.', 'novel-game-plugin-sample' ),
                __( 'He ran away after defaulting on a 30 million yen debt.', 'novel-game-plugin-sample' ),
                __( 'But we didn\'t do anything. You got no proof, right?', 'novel-game-plugin-sample' ),
                __( 'They won\'t say anything publicly... but they seem to be hiding something.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'right', 'right', 'right', '' ),
            'dialogue_backgrounds' => array( $bg_yakuza_office, '', '', $bg_alley ),
            'choices'         => array(
                array(
                    'text' => __( 'Press hard', 'novel-game-plugin-sample' ),
                    'next' => 'scene_15',
                ),
                array(
                    'text' => __( 'Retreat for now', 'novel-game-plugin-sample' ),
                    'next' => 'scene_13',
                ),
                array(
                    'text' => __( 'Try a different approach and negotiate', 'novel-game-plugin-sample' ),
                    'next' => 'scene_14',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(
                array( 'id' => 'flag_met_underworld', 'value' => 1 ),
            ),
        ),
        
        // シーン13: 隠し部屋の金庫を開ける
        // 主人公の独白・調査シーンのため、character_center に配置
        array(
            'title'           => __( 'Shadow Detective - Decisive Evidence', 'novel-game-plugin-sample' ),
            'background'      => $bg_hidden_room,
            'character_left'  => '',
            'character_center' => $char_protagonist_determined,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'I use the key to open the safe.', 'novel-game-plugin-sample' ),
                __( 'Inside are numerous documents and... cash!', 'novel-game-plugin-sample' ),
                __( 'Looking at the documents, records of illicit transactions...', 'novel-game-plugin-sample' ),
                __( 'Another name surfaces... "Takagi Construction, President Takagi"?', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'dialogue_flag_conditions' => array(
                3 => array( // 4行目（index 3）: フラグによる条件分岐
                    'conditions' => array(
                        array( 'name' => 'Contacted Underworld', 'state' => 1 ),
                    ),
                    'logic' => 'AND',
                    'displayMode' => 'alternative',
                    'alternativeText' => __( 'And a name other than Ryu-gumi... "Takagi Construction, President Takagi"?', 'novel-game-plugin-sample' ),
                ),
            ),
            'choices'         => array(
                array(
                    'text' => __( 'Investigate Takagi Construction', 'novel-game-plugin-sample' ),
                    'next' => 'scene_14',
                    'flagConditions' => array(
                        array( 'name' => 'Discovered Hidden Room', 'state' => true ),
                        array( 'name' => 'Hidden Room Key', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Contact Ryu-gumi again', 'novel-game-plugin-sample' ),
                    'next' => 'scene_15',
                    'flagConditions' => array(
                        array( 'name' => 'Contacted Underworld', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Carefully examine the documents in the safe', 'novel-game-plugin-sample' ),
                    'next' => 'scene_14',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン14: 高木建設の調査と闇取引メモ入手
        // 主人公の独白・調査シーンのため、character_center に配置
        array(
            'title'           => __( 'Shadow Detective - Shadow of the Mastermind', 'novel-game-plugin-sample' ),
            'background'      => $bg_construction,
            'character_left'  => '',
            'character_center' => $char_protagonist_serious,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Takagi Construction... a major general contractor. Why involved in illicit deals?', 'novel-game-plugin-sample' ),
                __( 'While investigating around the office, I find a memo in the trash...', 'novel-game-plugin-sample' ),
                __( '"Payment from Kurosaki confirmed. Process next through Ryu-gumi"', 'novel-game-plugin-sample' ),
                __( 'This is... President Takagi using Kurosaki and Ryu-gumi for illegal transactions?', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Go directly to meet President Takagi', 'novel-game-plugin-sample' ),
                    'next' => 'scene_16',
                ),
                array(
                    'text' => __( 'Gather more evidence', 'novel-game-plugin-sample' ),
                    'next' => 'scene_15',
                ),
                array(
                    'text' => __( 'Investigate Takagi Construction employees', 'novel-game-plugin-sample' ),
                    'next' => 'scene_15',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(
                array( 'id' => 'flag_item_trade_memo', 'value' => 1 ),
            ),
        ),
        
        // シーン15: 追加調査
        // 主人公の思案シーンのため、character_center に配置
        array(
            'title'           => __( 'Shadow Detective - Deepening Investigation', 'novel-game-plugin-sample' ),
            'background'      => $bg_office,
            'character_left'  => '',
            'character_center' => $char_protagonist_thinking,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Evidence has been accumulating. But I still don\'t have complete certainty.', 'novel-game-plugin-sample' ),
                __( 'President Takagi\'s memo... this could be the decisive evidence.', 'novel-game-plugin-sample' ),
                __( 'Ryu-gumi, Kurosaki, and Takagi Construction... the connections are becoming clear.', 'novel-game-plugin-sample' ),
                __( 'It\'s time to confront them directly.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Interrogate President Takagi', 'novel-game-plugin-sample' ),
                    'next' => 'scene_16',
                ),
                array(
                    'text' => __( 'Consult with the police', 'novel-game-plugin-sample' ),
                    'next' => 'scene_17',
                ),
                array(
                    'text' => __( 'Verify the evidence one more time', 'novel-game-plugin-sample' ),
                    'next' => 'scene_16',
                    'flagConditions' => array(
                        array( 'name' => 'Illicit Transaction Memo', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン16: 高木社長との対峙（第一段階）
        // 主人公は左側、高木社長は右側に配置（対峙シーン）
        array(
            'title'           => __( 'Shadow Detective - Encounter with the Mastermind', 'novel-game-plugin-sample' ),
            'background'      => $bg_construction,
            'character_left'  => $char_protagonist_serious,
            'character_center' => '',
            'character_right' => $char_takagi_calm,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'President Takagi', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'Detective, what brings you here?', 'novel-game-plugin-sample' ),
                __( 'Calm demeanor, but his eyes aren\'t smiling.', 'novel-game-plugin-sample' ),
                __( 'Please tell me about your relationship with Makoto Kurosaki.', 'novel-game-plugin-sample' ),
                __( 'Ah, I had a business relationship with him.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'right', '', 'left', 'right' ),
            'dialogue_backgrounds' => array( $bg_construction, '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Explain this illicit transaction memo', 'novel-game-plugin-sample' ),
                    'next' => 'scene_18',
                    'required_flags' => array( 'flag_item_trade_memo' ),
                ),
                array(
                    'text' => __( 'What about your relationship with Ryu-gumi?', 'novel-game-plugin-sample' ),
                    'next' => 'scene_17',
                ),
                array(
                    'text' => __( 'Ask about the disappearance directly', 'novel-game-plugin-sample' ),
                    'next' => 'scene_17',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン17: 間接的な追及
        // 主人公は左側、高木社長は右側に配置（対峙シーン継続）
        array(
            'title'           => __( 'Shadow Detective - Cautious Approach', 'novel-game-plugin-sample' ),
            'background'      => $bg_construction,
            'character_left'  => $char_protagonist_serious,
            'character_center' => '',
            'character_right' => $char_takagi_nervous,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'President Takagi', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'Ryu-gumi? Ah, I\'ve heard rumors, but I have no connection with them.', 'novel-game-plugin-sample' ),
                __( 'He skillfully evaded... Without decisive evidence, I can\'t press further.', 'novel-game-plugin-sample' ),
                __( 'Detective, baseless defamation is libel, you know.', 'novel-game-plugin-sample' ),
                __( 'At this rate, I can\'t reach the truth...', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'right', '', 'right', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(
                array(
                    'text' => __( 'Wait, please look at this evidence', 'novel-game-plugin-sample' ),
                    'next' => 'scene_18',
                    'flagConditions' => array(
                        array( 'name' => 'Illicit Transaction Memo', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'Sorry, excuse me', 'novel-game-plugin-sample' ),
                    'next' => 'scene_23',
                ),
                array(
                    'text' => __( 'Try bluffing with partial evidence', 'novel-game-plugin-sample' ),
                    'next' => 'scene_22',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン18: 黒幕との対峙（証拠提示）
        // 主人公は左側、高木社長は右側に配置（クライマックス・表情差分あり）
        array(
            'title'           => __( 'Shadow Detective - Revealing the Truth', 'novel-game-plugin-sample' ),
            'background'      => $bg_construction,
            'character_left'  => $char_protagonist_determined,
            'character_center' => '',
            'character_right' => $char_takagi_nervous,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'President Takagi', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'I place the illicit transaction memo on the desk.', 'novel-game-plugin-sample' ),
                __( '...This is...', 'novel-game-plugin-sample' ),
                __( 'His expression changed. Bull\'s eye.', 'novel-game-plugin-sample' ),
                __( 'Tch... Kurosaki left evidence behind.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', 'right', '', 'right' ),
            'dialogue_backgrounds' => array( $bg_construction, '', '', '' ),
            // 表情差分：クライマックスでの表情変化
            'dialogue_characters' => array(
                0 => array( 'left' => $char_protagonist_determined, 'center' => '', 'right' => $char_takagi_nervous ),
                1 => array( 'left' => $char_protagonist_determined, 'center' => '', 'right' => $char_takagi_angry ),
                2 => array( 'left' => $char_protagonist_determined, 'center' => '', 'right' => $char_takagi_angry ),
                3 => array( 'left' => $char_protagonist_determined, 'center' => '', 'right' => $char_takagi_regret ),
            ),
            'choices'         => array(
                array(
                    'text' => __( 'Where is Makoto Kurosaki?', 'novel-game-plugin-sample' ),
                    'next' => 'scene_19',
                ),
                array(
                    'text' => __( 'I\'m calling the police', 'novel-game-plugin-sample' ),
                    'next' => 'scene_19',
                ),
                array(
                    'text' => __( 'Tell me everything you know', 'novel-game-plugin-sample' ),
                    'next' => 'scene_19',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(
                array( 'id' => 'flag_confronted_mastermind', 'value' => 1 ),
            ),
        ),
        
        // シーン19: 真相の告白
        // 主人公は左側、高木社長は右側に配置（告白シーン）
        array(
            'title'           => __( 'Shadow Detective - All the Truth', 'novel-game-plugin-sample' ),
            'background'      => $bg_construction,
            'character_left'  => $char_protagonist_serious,
            'character_center' => '',
            'character_right' => $char_takagi_regret,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'President Takagi', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'Fine... I\'ll tell you everything.', 'novel-game-plugin-sample' ),
                __( 'Kurosaki discovered my fraud. So I tried to silence him...', 'novel-game-plugin-sample' ),
                __( 'But I didn\'t kill him. I asked Ryu-gumi to threaten him.', 'novel-game-plugin-sample' ),
                __( 'Kurosaki is now hiding in my villa. By his own choice.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'right', 'right', 'right', 'right' ),
            'dialogue_backgrounds' => array( $bg_construction, '', '', $bg_villa ),
            'choices'         => array(
                array(
                    'text' => __( 'Protect Kurosaki immediately', 'novel-game-plugin-sample' ),
                    'next' => 'scene_20',
                ),
                array(
                    'text' => __( 'Call the police', 'novel-game-plugin-sample' ),
                    'next' => 'scene_20',
                ),
                array(
                    'text' => __( 'Get the villa location first', 'novel-game-plugin-sample' ),
                    'next' => 'scene_20',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン20: 黒崎誠の保護（分岐点）
        // 主人公は左側、誠は右側に配置（再会シーン）
        array(
            'title'           => __( 'Shadow Detective - Reunion with the Missing', 'novel-game-plugin-sample' ),
            'background'      => $bg_villa,
            'character_left'  => $char_protagonist_normal,
            'character_center' => '',
            'character_right' => $char_makoto_tired,
            'character_left_name' => __( 'Detective', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Makoto Kurosaki', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'You\'re the detective...', 'novel-game-plugin-sample' ),
                __( 'I discovered Takagi\'s fraud and was threatened.', 'novel-game-plugin-sample' ),
                __( 'I was told my family would be harmed, so I had no choice but to hide.', 'novel-game-plugin-sample' ),
                __( 'Please... let me see my wife, Misaki.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( 'right', 'right', 'right', 'right' ),
            'dialogue_backgrounds' => array( $bg_villa, '', '', '' ),
            // 表情差分：再会シーンでの表情変化
            'dialogue_characters' => array(
                0 => array( 'left' => $char_protagonist_normal, 'center' => '', 'right' => $char_makoto_tired ),
                1 => array( 'left' => $char_protagonist_serious, 'center' => '', 'right' => $char_makoto_tired ),
                2 => array( 'left' => $char_protagonist_serious, 'center' => '', 'right' => $char_makoto_tired ),
                3 => array( 'left' => $char_protagonist_normal, 'center' => '', 'right' => $char_makoto_relief ),
            ),
            'choices'         => array(
                array(
                    'text' => __( 'Everything is resolved. Let\'s go back to your family', 'novel-game-plugin-sample' ),
                    'next' => 'scene_21',
                    'flagConditions' => array(
                        array( 'name' => 'Pocket Watch', 'state' => true ),
                        array( 'name' => 'Missing Person\'s Diary', 'state' => true ),
                        array( 'name' => 'Evidence Photo', 'state' => true ),
                        array( 'name' => 'Hidden Room Key', 'state' => true ),
                        array( 'name' => 'Illicit Transaction Memo', 'state' => true ),
                        array( 'name' => 'Confronted Mastermind', 'state' => true ),
                    ),
                    'flagConditionLogic' => 'AND',
                ),
                array(
                    'text' => __( 'There are still unclear points...', 'novel-game-plugin-sample' ),
                    'next' => 'scene_22',
                ),
                array(
                    'text' => __( 'Let me confirm the evidence one more time', 'novel-game-plugin-sample' ),
                    'next' => 'scene_23',
                ),
            ),
            'is_ending'       => false,
            'ending_text'     => '',
            'set_flags'       => array(),
        ),
        
        // シーン21: エンディング - 完全解決
        // 美咲は左側、誠は右側に配置（再会のエンディング）
        array(
            'title'           => __( 'Shadow Detective - Complete Solution', 'novel-game-plugin-sample' ),
            'background'      => $bg_office,
            'character_left'  => $char_misaki_smile,
            'character_center' => '',
            'character_right' => $char_makoto_relief,
            'character_left_name' => __( 'Misaki', 'novel-game-plugin-sample' ),
            'character_center_name' => '',
            'character_right_name' => __( 'Makoto', 'novel-game-plugin-sample' ),
            'dialogue_texts'  => array(
                __( 'All evidence gathered, the truth has been revealed.', 'novel-game-plugin-sample' ),
                __( 'Detective, thank you so much.', 'novel-game-plugin-sample' ),
                __( 'President Takagi was arrested, and I will cooperate with the police.', 'novel-game-plugin-sample' ),
                __( 'Another case solved. This is a detective\'s work.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', 'left', 'right', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(),
            'is_ending'       => true,
            'ending_text'     => __( 'Complete Solution - All truths have been revealed', 'novel-game-plugin-sample' ),
            'set_flags'       => array(),
        ),
        
        // シーン22: エンディング - 部分解決
        // 主人公の独白シーンのため、character_center に配置
        array(
            'title'           => __( 'Shadow Detective - Partial Solution', 'novel-game-plugin-sample' ),
            'background'      => $bg_office,
            'character_left'  => '',
            'character_center' => $char_protagonist_thinking,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Makoto Kurosaki was protected, and the case saw some resolution.', 'novel-game-plugin-sample' ),
                __( 'However, I couldn\'t gather all the evidence.', 'novel-game-plugin-sample' ),
                __( 'President Takagi\'s crimes couldn\'t be fully proven, leaving parts unresolved.', 'novel-game-plugin-sample' ),
                __( 'Frustrating, but this is detective work... perfect case resolution is difficult.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(),
            'is_ending'       => true,
            'ending_text'     => __( 'Partial Solution - Some truths remain in darkness', 'novel-game-plugin-sample' ),
            'set_flags'       => array(),
        ),
        
        // シーン23: エンディング - 証拠不足/誤推理
        // 主人公の独白シーンのため、character_center に配置
        array(
            'title'           => __( 'Shadow Detective - Insufficient Evidence', 'novel-game-plugin-sample' ),
            'background'      => $bg_office,
            'character_left'  => '',
            'character_center' => $char_protagonist_thinking,
            'character_right' => '',
            'character_left_name' => '',
            'character_center_name' => '',
            'character_right_name' => '',
            'dialogue_texts'  => array(
                __( 'Evidence was insufficient, couldn\'t reach the truth.', 'novel-game-plugin-sample' ),
                __( 'Makoto Kurosaki remains missing.', 'novel-game-plugin-sample' ),
                __( 'I should have been more careful and thorough in my investigation.', 'novel-game-plugin-sample' ),
                __( 'Let\'s start the investigation over from the beginning.', 'novel-game-plugin-sample' ),
            ),
            'dialogue_speakers' => array( '', '', '', '' ),
            'dialogue_backgrounds' => array( '', '', '', '' ),
            'choices'         => array(),
            'is_ending'       => true,
            'ending_text'     => __( 'Insufficient Evidence - Detective Failure', 'novel-game-plugin-sample' ),
            'set_flags'       => array(),
        ),
    );
    
    // フラグマスタデータ（証拠アイテムと調査進捗フラグ）
    $flag_master = array(
        array(
            'id'   => 'flag_item_watch',
            'name' => __( 'Pocket Watch', 'novel-game-plugin-sample' ),
            'description' => __( 'A pocket watch belonging to Makoto Kurosaki. Evidence he was at the disappearance site.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_item_note',
            'name' => __( 'Missing Person\'s Diary', 'novel-game-plugin-sample' ),
            'description' => __( 'Kurosaki\'s diary revealing his troubles and evidence of transactions.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_item_photo',
            'name' => __( 'Evidence Photo', 'novel-game-plugin-sample' ),
            'description' => __( 'A photo of the illicit transaction scene.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_item_key',
            'name' => __( 'Hidden Room Key', 'novel-game-plugin-sample' ),
            'description' => __( 'The key to access the hidden room.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_item_trade_memo',
            'name' => __( 'Illicit Transaction Memo', 'novel-game-plugin-sample' ),
            'description' => __( 'Decisive evidence revealing the mastermind\'s identity.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_talked_wife',
            'name' => __( 'Talked to Wife', 'novel-game-plugin-sample' ),
            'description' => __( 'Obtained testimony from Misaki.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_talked_friend',
            'name' => __( 'Talked to Friend', 'novel-game-plugin-sample' ),
            'description' => __( 'Obtained testimony from friend Sato.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_found_hidden_room',
            'name' => __( 'Discovered Hidden Room', 'novel-game-plugin-sample' ),
            'description' => __( 'Found the hidden room in the study.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_met_underworld',
            'name' => __( 'Contacted Underworld', 'novel-game-plugin-sample' ),
            'description' => __( 'Made contact with Ryu-gumi.', 'novel-game-plugin-sample' ),
        ),
        array(
            'id'   => 'flag_confronted_mastermind',
            'name' => __( 'Confronted Mastermind', 'novel-game-plugin-sample' ),
            'description' => __( 'Confronted President Takagi.', 'novel-game-plugin-sample' ),
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
 * @return int 作成されたシーン数
 * @since 1.3.0
 */
function noveltool_generate_scenes_for_game( $game_id, $target_title, $scenes_data ) {
    if ( ! $game_id || ! $target_title || empty( $scenes_data ) ) {
        error_log( 'noveltool_generate_scenes_for_game: Invalid parameters' );
        return 0;
    }
    
    error_log( sprintf( 'noveltool_generate_scenes_for_game: Starting scene generation for game ID %d (%s)', $game_id, $target_title ) );
    
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
        
        // メタデータを保存
        update_post_meta( $post_id, '_game_title', $target_title );
        update_post_meta( $post_id, '_background_image', esc_url_raw( $scene_data['background'] ) );
        update_post_meta( $post_id, '_character_left', esc_url_raw( $scene_data['character_left'] ) );
        update_post_meta( $post_id, '_character_center', esc_url_raw( $scene_data['character_center'] ) );
        update_post_meta( $post_id, '_character_right', esc_url_raw( $scene_data['character_right'] ) );
        update_post_meta( $post_id, '_character_left_name', $scene_data['character_left_name'] );
        update_post_meta( $post_id, '_character_center_name', $scene_data['character_center_name'] );
        update_post_meta( $post_id, '_character_right_name', $scene_data['character_right_name'] );
        
        // セリフデータを保存（新形式）
        update_post_meta( $post_id, '_dialogue_texts', wp_json_encode( $scene_data['dialogue_texts'], JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_dialogue_speakers', wp_json_encode( $scene_data['dialogue_speakers'], JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_dialogue_backgrounds', wp_json_encode( $scene_data['dialogue_backgrounds'], JSON_UNESCAPED_UNICODE ) );
        
        // セリフのフラグ条件データを保存（JSON形式）
        if ( isset( $scene_data['dialogue_flag_conditions'] ) && is_array( $scene_data['dialogue_flag_conditions'] ) ) {
            update_post_meta(
                $post_id,
                '_dialogue_flag_conditions',
                wp_json_encode( $scene_data['dialogue_flag_conditions'], JSON_UNESCAPED_UNICODE )
            );
        }
        
        // セリフごとのキャラクター設定（表情差分）を保存
        if ( isset( $scene_data['dialogue_characters'] ) && is_array( $scene_data['dialogue_characters'] ) ) {
            // dialogue_characters内の画像URLをサニタイズ
            $sanitized_dialogue_characters = array();
            foreach ( $scene_data['dialogue_characters'] as $index => $char_setting ) {
                $sanitized_dialogue_characters[ $index ] = array(
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
                    
                    // flagConditions と flagConditionLogic を保存（required_flags は保存しない）
                    if ( isset( $choice['flagConditions'] ) ) {
                        $choice_data['flagConditions'] = $choice['flagConditions'];
                    }
                    if ( isset( $choice['flagConditionLogic'] ) ) {
                        $choice_data['flagConditionLogic'] = $choice['flagConditionLogic'];
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
        error_log( 'noveltool_generate_scenes_for_game: Scene creation errors:' );
        foreach ( $creation_errors as $error ) {
            error_log( '  - ' . $error );
        }
    }
    
    $created_count = count( $scene_ids );
    error_log( sprintf( 'noveltool_generate_scenes_for_game: Completed. Created %d scenes for game ID %d', $created_count, $game_id ) );
    
    return $created_count;
}

/**
 * Shadow Detectiveゲームをインストール
 *
 * 本格推理ゲーム「影の探偵」をインストールする
 * 機械識別子（machine_name）とタイトルベースで既存チェックを行い、存在しない場合のみインストール
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
    // 1. 機械識別子（machine_name）で検索（優先）
    $existing_game = null;
    if ( isset( $game_data['machine_name'] ) ) {
        $existing_game = noveltool_get_game_by_machine_name( $game_data['machine_name'] );
    }
    
    // 2. machine_name が見つからない場合、タイトルベースでチェック（後方互換）
    if ( ! $existing_game ) {
        $game_title = __( 'Shadow Detective', 'novel-game-plugin-sample' );
        $existing_game = noveltool_get_game_by_title( $game_title );
    }
    
    // ⚠️ 重要: 既存インストール済みのゲームは自動で削除/上書きされません
    if ( $existing_game ) {
        error_log( 'noveltool_install_shadow_detective_game: Existing game detected' );
        
        $target_title = $existing_game['title'] ?? '';
        if ( ! $target_title ) {
            error_log( 'noveltool_install_shadow_detective_game: Existing game has no title' );
            return false;
        }
        
        $existing_scenes = noveltool_get_posts_by_game_title( $target_title );
        if ( is_array( $existing_scenes ) && count( $existing_scenes ) === 0 ) {
            error_log( sprintf( 'noveltool_install_shadow_detective_game: Game exists but has 0 scenes, regenerating for "%s"', $target_title ) );
            
            // 既存ゲームの ID を取得
            $target_game_id = isset( $existing_game['id'] ) ? (int) $existing_game['id'] : null;
            if ( ! $target_game_id ) {
                error_log( 'noveltool_install_shadow_detective_game: Cannot determine target game ID' );
                return false;
            }
            
            // フラグマスタが未保存なら保存
            $current_flag_master = noveltool_get_game_flag_master( $target_title );
            if ( empty( $current_flag_master ) && ! empty( $flag_master ) ) {
                noveltool_save_game_flag_master( $target_title, $flag_master );
                error_log( 'noveltool_install_shadow_detective_game: Flag master saved' );
            }
            
            // シーン再生成
            $created = noveltool_generate_scenes_for_game( $target_game_id, $target_title, $scenes_data );
            $expected_count = count( $scenes_data );
            if ( $created < $expected_count ) {
                error_log( sprintf(
                    'noveltool_install_shadow_detective_game: Incomplete regeneration. Expected %d scenes, created %d',
                    $expected_count,
                    $created
                ) );
                return false;
            }
            return true;
        }
        
        error_log( sprintf( 'noveltool_install_shadow_detective_game: Game exists with %d scenes, skipping', count( $existing_scenes ) ) );
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
    
    // フラグIDの一覧を作成（検証用）
    $valid_flag_ids = array();
    if ( ! empty( $flag_master ) ) {
        foreach ( $flag_master as $flag ) {
            if ( isset( $flag['id'] ) ) {
                $valid_flag_ids[] = $flag['id'];
            }
        }
    }
    
    // required_flags の検証
    $flag_validation_warnings = array();
    foreach ( $scenes_data as $index => $scene_data ) {
        $scene_num = $index + 1;
        
        // 選択肢のrequired_flagsをチェック
        if ( ! empty( $scene_data['choices'] ) ) {
            foreach ( $scene_data['choices'] as $choice_index => $choice ) {
                if ( isset( $choice['required_flags'] ) && is_array( $choice['required_flags'] ) ) {
                    foreach ( $choice['required_flags'] as $required_flag ) {
                        if ( ! in_array( $required_flag, $valid_flag_ids, true ) ) {
                            $flag_validation_warnings[] = sprintf(
                                'Scene %d, choice %d references undefined flag: %s',
                                $scene_num,
                                $choice_index + 1,
                                $required_flag
                            );
                        }
                    }
                }
            }
        }
        
        // set_flagsをチェック
        if ( ! empty( $scene_data['set_flags'] ) ) {
            foreach ( $scene_data['set_flags'] as $set_flag ) {
                if ( isset( $set_flag['id'] ) && ! in_array( $set_flag['id'], $valid_flag_ids, true ) ) {
                    $flag_validation_warnings[] = sprintf(
                        'Scene %d sets undefined flag: %s',
                        $scene_num,
                        $set_flag['id']
                    );
                }
            }
        }
    }
    
    // フラグ検証警告をログに記録
    if ( ! empty( $flag_validation_warnings ) && defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        error_log( 'Novel Game Plugin - Shadow Detective flag validation warnings:' );
        foreach ( $flag_validation_warnings as $warning ) {
            error_log( '  - ' . $warning );
        }
    }
    
    // シーン生成を実行
    $created_scenes = noveltool_generate_scenes_for_game( $game_id, $game_data['title'], $scenes_data );
    
    if ( $created_scenes < count( $scenes_data ) ) {
        // 一部のシーンの作成に失敗した場合
        error_log( sprintf( 'noveltool_install_shadow_detective_game: Incomplete installation. Expected %d scenes, created %d', count( $scenes_data ), $created_scenes ) );
        return false;
    }
    
    return true;
}
