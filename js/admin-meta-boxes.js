/**
 * 管理画面のメタボックス用JavaScript
 *
 * @package NovelGamePlugin
 * @since 1.0.0
 */

jQuery( function( $ ) {
	'use strict';

	// 投稿一覧を保持する変数
	var scenes = [];
	
	// セリフデータを保持する変数
	var dialogueData = [];

	// WordPress ajaxurlの設定
	var ajaxurl = novelGameMeta.ajaxurl;
	
	/**
	 * 現在のフォームデータを dialogueData に同期
	 */
	function syncCurrentFormData() {
		$( '#novel-dialogue-list .novel-dialogue-item' ).each( function( index ) {
			var $item = $( this );
			var text = $item.find( '.dialogue-text' ).val();
			var speaker = $item.find( '.dialogue-speaker-select' ).val();
			var background = $item.find( '.dialogue-background-input' ).val();
			var alternativeText = $item.find( '.dialogue-alternative-text' ).val();
			
			// dialogueData が存在し、該当インデックスがある場合のみ更新
			if ( dialogueData && dialogueData[index] ) {
				dialogueData[index].text = text || '';
				dialogueData[index].speaker = speaker || '';
				dialogueData[index].background = background || '';
				dialogueData[index].alternativeText = alternativeText || '';
			}
		} );
		
		// 選択肢データも同期（フラグ設定含む）
		updateChoicesHidden();
	}
	
	/**
	 * セリフデータの初期化
	 */
	function initializeDialogueData() {
		// 既存のセリフデータを読み込み
		var existingLines = novelGameMeta.dialogue_lines || [];
		var existingBackgrounds = novelGameMeta.dialogue_backgrounds || [];
		var existingSpeakers = novelGameMeta.dialogue_speakers || [];
		var existingFlagConditions = novelGameMeta.dialogue_flag_conditions || [];
		var existingCharacters = novelGameMeta.dialogue_characters || [];
		
		// 常に新しいデータでリセット（初期化時）
		dialogueData = [];
		
		// 既存のセリフ行をデータ配列に変換
		existingLines.forEach( function( line, index ) {
			var flagConditionData = existingFlagConditions[index] || {};
			var characterData = existingCharacters[index] || {};
			dialogueData.push( {
				text: line,
				background: existingBackgrounds[index] || '',
				speaker: existingSpeakers[index] || '',
				flagConditions: flagConditionData.conditions || [],
				flagConditionLogic: flagConditionData.logic || 'AND',
				displayMode: flagConditionData.displayMode || 'normal',
				alternativeText: flagConditionData.alternativeText || '',
				characters: {
					left: characterData.left || '',
					center: characterData.center || '',
					right: characterData.right || ''
				}
			} );
		} );
		
		// セリフが1つもない場合は空のセリフを1つ追加
		if ( dialogueData.length === 0 ) {
			dialogueData.push( {
				text: '',
				background: '',
				speaker: '',
				flagConditions: [],
				flagConditionLogic: 'AND',
				displayMode: 'normal',
				alternativeText: '',
				characters: {
					left: '',
					center: '',
					right: ''
				}
			} );
		}
		
		// 初期化後に隠しフィールドを更新
		updateDialogueTextarea();
	}
	
	/**
	 * セリフリストを描画
	 */
	function renderDialogueList() {
		var $container = $( '#novel-dialogue-list' );
		$container.empty();
		
		dialogueData.forEach( function( dialogue, index ) {
			var $item = $( '<div class="novel-dialogue-item">' );
			$item.attr( 'data-index', index );
			
			// セリフテキスト入力
			var $textArea = $( '<textarea class="dialogue-text large-text" rows="2" placeholder="' + novelGameMeta.strings.dialoguePlaceholder + '"></textarea>' );
			$textArea.val( dialogue.text );
			$textArea.on( 'input change blur', function() {
				dialogueData[index].text = $( this ).val();
				updateDialogueTextarea();
			} );
			
			// 話者選択
			var $speakerContainer = $( '<div class="dialogue-speaker-container">' );
			var $speakerLabel = $( '<label>' + novelGameMeta.strings.speaker + '</label>' );
			var $speakerSelect = $( '<select class="dialogue-speaker-select">' );
			$speakerSelect.append( '<option value="">' + novelGameMeta.strings.selectSpeaker + '</option>' );
			$speakerSelect.append( '<option value="left"' + ( dialogue.speaker === 'left' ? ' selected' : '' ) + '>' + novelGameMeta.strings.leftCharacter + '</option>' );
			$speakerSelect.append( '<option value="center"' + ( dialogue.speaker === 'center' ? ' selected' : '' ) + '>' + novelGameMeta.strings.centerCharacter + '</option>' );
			$speakerSelect.append( '<option value="right"' + ( dialogue.speaker === 'right' ? ' selected' : '' ) + '>' + novelGameMeta.strings.rightCharacter + '</option>' );
			$speakerSelect.append( '<option value="narrator"' + ( dialogue.speaker === 'narrator' ? ' selected' : '' ) + '>' + novelGameMeta.strings.narrator + '</option>' );
			
			$speakerSelect.on( 'change blur', function() {
				dialogueData[index].speaker = $( this ).val();
				updateDialogueTextarea();
			} );
			
			$speakerContainer.append( $speakerLabel, $speakerSelect );
			
			// 背景画像選択
			var $imageContainer = $( '<div class="dialogue-background-container">' );
			var $imageInput = $( '<input type="hidden" class="dialogue-background-input">' );
			$imageInput.val( dialogue.background );
			var $imagePreview = $( '<img class="dialogue-background-preview" style="max-width: 100px; height: auto; display: none;">' );
			if ( dialogue.background ) {
				$imagePreview.attr( 'src', dialogue.background ).show();
			}
			var $imageButton = $( '<button type="button" class="button dialogue-background-button">' + novelGameMeta.strings.selectBackgroundImage + '</button>' );
			var $imageClearButton = $( '<button type="button" class="button dialogue-background-clear" style="display: ' + ( dialogue.background ? 'inline-block' : 'none' ) + ';">' + novelGameMeta.strings.remove + '</button>' );
			
			$imageButton.on( 'click', function() {
				selectDialogueBackground( index );
			} );
			
			$imageClearButton.on( 'click', function() {
				dialogueData[index].background = '';
				renderDialogueList();
				updateDialogueTextarea();
			} );
			
			$imageContainer.append( $imageInput, $imagePreview, '<br>', $imageButton, $imageClearButton );
			
			// フラグ制御UI
			var $flagContainer = createDialogueFlagUI( dialogue, index );
			
			// セリフごとのキャラクター設定UI
			var $characterContainer = createDialogueCharacterUI( dialogue, index );
			
			// 削除ボタン
			var $deleteButton = $( '<button type="button" class="button dialogue-delete-button">' + novelGameMeta.strings.remove + '</button>' );
			$deleteButton.on( 'click', function() {
				deleteDialogue( index );
			} );
			
			// 上下移動ボタン
			var $moveUpButton = $( '<button type="button" class="button dialogue-move-up">↑</button>' );
			var $moveDownButton = $( '<button type="button" class="button dialogue-move-down">↓</button>' );
			
			$moveUpButton.on( 'click', function() {
				moveDialogue( index, -1 );
			} );
			
			$moveDownButton.on( 'click', function() {
				moveDialogue( index, 1 );
			} );
			
			// 要素の組み立て
			var $controls = $( '<div class="dialogue-controls">' );
			$controls.append( $moveUpButton, $moveDownButton, $deleteButton );
			
			$item.append( '<p><strong>' + novelGameMeta.strings.dialogue + ' ' + ( index + 1 ) + '</strong></p>' );
			$item.append( $textArea );
			$item.append( $speakerContainer );
			$item.append( '<p><strong>' + novelGameMeta.strings.backgroundImage + '</strong></p>' );
			$item.append( $imageContainer );
			$item.append( '<p><strong>' + novelGameMeta.strings.flagControl + '</strong></p>' );
			$item.append( $flagContainer );
			$item.append( $characterContainer );
			$item.append( $controls );
			$item.append( '<hr>' );
			
			$container.append( $item );
		} );
	}
	
	/**
	 * セリフごとのキャラクター設定UIを作成
	 *
	 * @param {Object} dialogue セリフデータ
	 * @param {number} index セリフインデックス
	 * @return {jQuery} キャラクター設定UIコンテナ
	 */
	function createDialogueCharacterUI( dialogue, index ) {
		var $container = $( '<div class="dialogue-character-container">' );
		
		// 折りたたみトグルボタン
		var hasCharacterSettings = dialogue.characters && 
			( dialogue.characters.left || dialogue.characters.center || dialogue.characters.right );
		var toggleButtonText = hasCharacterSettings ? 
			novelGameMeta.strings.hideCharacterSettings : novelGameMeta.strings.showCharacterSettings;
		
		var $toggleButton = $( '<button type="button" class="button dialogue-character-toggle">' + toggleButtonText + '</button>' );
		
		// 設定コンテンツ（折りたたみ可能）
		var $content = $( '<div class="dialogue-character-content" style="display: ' + ( hasCharacterSettings ? 'block' : 'none' ) + ';">' );
		
		// ヘルプテキスト
		$content.append( '<p class="description dialogue-character-help">' + novelGameMeta.strings.dialogueCharacterHelp + '</p>' );
		
		// キャラクター設定グリッド
		var $grid = $( '<div class="dialogue-character-grid">' );
		
		// 左キャラクター
		$grid.append( createDialogueCharacterPositionUI( dialogue, index, 'left', novelGameMeta.strings.leftCharacter ) );
		
		// 中央キャラクター
		$grid.append( createDialogueCharacterPositionUI( dialogue, index, 'center', novelGameMeta.strings.centerCharacter ) );
		
		// 右キャラクター
		$grid.append( createDialogueCharacterPositionUI( dialogue, index, 'right', novelGameMeta.strings.rightCharacter ) );
		
		$content.append( $grid );
		
		// トグルボタンのイベント
		$toggleButton.on( 'click', function( e ) {
			e.preventDefault();
			var isVisible = $content.is( ':visible' );
			$content.slideToggle( 200 );
			$( this ).text( isVisible ? novelGameMeta.strings.showCharacterSettings : novelGameMeta.strings.hideCharacterSettings );
		} );
		
		$container.append( $toggleButton );
		$container.append( $content );
		
		return $container;
	}
	
	/**
	 * キャラクター位置ごとの設定UIを作成
	 *
	 * @param {Object} dialogue セリフデータ
	 * @param {number} index セリフインデックス
	 * @param {string} position 位置（left, center, right）
	 * @param {string} label ラベル
	 * @return {jQuery} 位置設定UI
	 */
	function createDialogueCharacterPositionUI( dialogue, index, position, label ) {
		var $positionContainer = $( '<div class="dialogue-character-position">' );
		var currentImage = ( dialogue.characters && dialogue.characters[position] ) ? dialogue.characters[position] : '';
		
		// ラベル
		$positionContainer.append( '<label class="dialogue-character-label">' + label + '</label>' );
		
		// 隠しフィールド
		var $input = $( '<input type="hidden" class="dialogue-character-input" data-position="' + position + '">' );
		$input.val( currentImage );
		
		// プレビュー画像
		var $preview = $( '<img class="dialogue-character-preview" style="max-width: 80px; height: auto; display: ' + ( currentImage ? 'block' : 'none' ) + ';">' );
		if ( currentImage ) {
			$preview.attr( 'src', currentImage );
		}
		
		// プレースホルダー（シーン設定を使用時）
		var $placeholder = $( '<span class="dialogue-character-placeholder" style="display: ' + ( currentImage ? 'none' : 'block' ) + ';">' + novelGameMeta.strings.useSceneDefault + '</span>' );
		
		// ボタンコンテナ
		var $buttons = $( '<div class="dialogue-character-buttons">' );
		
		// 画像選択ボタン
		var $selectButton = $( '<button type="button" class="button button-small dialogue-character-select">' + novelGameMeta.strings.selectCharacterImage + '</button>' );
		$selectButton.on( 'click', function( e ) {
			e.preventDefault();
			selectDialogueCharacterImage( index, position );
		} );
		
		// クリアボタン
		var $clearButton = $( '<button type="button" class="button button-small dialogue-character-clear" style="display: ' + ( currentImage ? 'inline-block' : 'none' ) + ';">' + novelGameMeta.strings.clearImage + '</button>' );
		$clearButton.on( 'click', function( e ) {
			e.preventDefault();
			if ( ! dialogueData[index].characters ) {
				dialogueData[index].characters = { left: '', center: '', right: '' };
			}
			dialogueData[index].characters[position] = '';
			$input.val( '' );
			$preview.attr( 'src', '' ).hide();
			$placeholder.show();
			$( this ).hide();
			updateDialogueTextarea();
		} );
		
		$buttons.append( $selectButton, $clearButton );
		$positionContainer.append( $input, $preview, $placeholder, $buttons );
		
		return $positionContainer;
	}
	
	/**
	 * セリフごとのキャラクター画像を選択
	 *
	 * @param {number} dialogueIndex セリフインデックス
	 * @param {string} position 位置（left, center, right）
	 */
	function selectDialogueCharacterImage( dialogueIndex, position ) {
		if ( typeof wp.media === 'undefined' ) {
			alert( novelGameMeta.strings.mediaLibraryUnavailable );
			return;
		}
		
		var frame = wp.media( {
			title: novelGameMeta.strings.selectCharacterImage,
			multiple: false,
			library: {
				type: 'image'
			},
			button: {
				text: novelGameMeta.strings.useThisImage
			}
		} );
		
		frame.on( 'select', function() {
			var selection = frame.state().get( 'selection' );
			var attachment = selection.first().toJSON();
			
			// ファイルバリデーション
			var validation = validateImageFile( attachment );
			if ( ! validation.valid ) {
				alert( validation.message );
				return;
			}
			
			// データ更新
			if ( ! dialogueData[dialogueIndex].characters ) {
				dialogueData[dialogueIndex].characters = { left: '', center: '', right: '' };
			}
			dialogueData[dialogueIndex].characters[position] = attachment.url;
			
			// UI更新
			var $item = $( '.novel-dialogue-item[data-index="' + dialogueIndex + '"]' );
			var $positionContainer = $item.find( '.dialogue-character-position' ).filter( function() {
				return $( this ).find( '.dialogue-character-input[data-position="' + position + '"]' ).length > 0;
			} );
			
			$positionContainer.find( '.dialogue-character-input' ).val( attachment.url );
			$positionContainer.find( '.dialogue-character-preview' ).attr( 'src', attachment.url ).show();
			$positionContainer.find( '.dialogue-character-placeholder' ).hide();
			$positionContainer.find( '.dialogue-character-clear' ).show();
			
			updateDialogueTextarea();
		} );
		
		frame.open();
	}
	
	/**
	 * セリフの削除
	 */
	function deleteDialogue( index ) {
		if ( dialogueData.length <= 1 ) {
			alert( novelGameMeta.strings.minDialogueRequired );
			return;
		}
		
		if ( confirm( novelGameMeta.strings.confirmDeleteDialogue ) ) {
			dialogueData.splice( index, 1 );
			renderDialogueList();
			updateDialogueTextarea();
		}
	}
	
	/**
	 * セリフの移動
	 */
	function moveDialogue( index, direction ) {
		var newIndex = index + direction;
		
		if ( newIndex < 0 || newIndex >= dialogueData.length ) {
			return;
		}
		
		// 配列の要素を交換
		var temp = dialogueData[index];
		dialogueData[index] = dialogueData[newIndex];
		dialogueData[newIndex] = temp;
		
		renderDialogueList();
		updateDialogueTextarea();
	}
	
	/**
	 * セリフの追加
	 */
	function addDialogue() {
		dialogueData.push( {
			text: '',
			background: '',
			speaker: '',
			flagConditions: [],
			flagConditionLogic: 'AND',
			displayMode: 'normal', // normal, hidden, alternative
			alternativeText: '',
			characters: {
				left: '',
				center: '',
				right: ''
			}
		} );
		renderDialogueList();
		updateDialogueTextarea();
	}
	
	/**
	 * 背景画像の選択
	 */
	function selectDialogueBackground( index ) {
		if ( typeof wp.media === 'undefined' ) {
			alert( novelGameMeta.strings.mediaLibraryUnavailable );
			return;
		}
		
		var frame = wp.media( {
			title: novelGameMeta.strings.selectBackgroundImage,
			multiple: false,
			library: {
				type: 'image'
			},
			button: {
				text: novelGameMeta.strings.useThisImage
			}
		} );
		
		frame.on( 'select', function() {
			var selection = frame.state().get( 'selection' );
			var attachment = selection.first().toJSON();
			
			// ファイルバリデーション
			var validation = validateImageFile( attachment );
			if ( ! validation.valid ) {
				alert( validation.message );
				return;
			}
			
			dialogueData[index].background = attachment.url;
			renderDialogueList();
			updateDialogueTextarea();
		} );
		
		frame.open();
	}
	
	/**
	 * セリフのフラグ制御UIを作成
	 *
	 * @param {Object} dialogue セリフデータ
	 * @param {number} index セリフインデックス
	 * @return {jQuery} フラグUIコンテナ
	 */
	function createDialogueFlagUI( dialogue, index ) {
		var $container = $( '<div class="dialogue-flag-container">' );
		
		// 表示モード選択
		var $displayModeContainer = $( '<div class="flag-display-mode">' );
		var $displayModeLabel = $( '<label>' + novelGameMeta.strings.displayControl + '</label>' );
		var $displayModeSelect = $( '<select class="dialogue-display-mode-select">' );
		$displayModeSelect.append( '<option value="normal"' + ( dialogue.displayMode === 'normal' ? ' selected' : '' ) + '>' + novelGameMeta.strings.normalDisplay + '</option>' );
		$displayModeSelect.append( '<option value="hidden"' + ( dialogue.displayMode === 'hidden' ? ' selected' : '' ) + '>' + novelGameMeta.strings.hiddenByCondition + '</option>' );
		$displayModeSelect.append( '<option value="alternative"' + ( dialogue.displayMode === 'alternative' ? ' selected' : '' ) + '>' + novelGameMeta.strings.alternativeByCondition + '</option>' );
		
		$displayModeSelect.on( 'change', function() {
			dialogueData[index].displayMode = $( this ).val();
			updateDialogueTextarea();
			renderDialogueList(); // フラグ条件UIの表示切り替え
		} );
		
		$displayModeContainer.append( $displayModeLabel, $displayModeSelect );
		$container.append( $displayModeContainer );
		
		// フラグ条件UI（通常表示以外の場合のみ表示）
		if ( dialogue.displayMode !== 'normal' ) {
			var $flagConditionsContainer = $( '<div class="flag-conditions-container">' );
			
			// フラグ条件（最大3つ）
			for ( var i = 0; i < 3; i++ ) {
				var $conditionRow = $( '<div class="flag-condition-row">' );
				
				// フラグ選択
				var $flagSelect = $( '<select class="dialogue-flag-condition-select">' );
				$flagSelect.append( '<option value="">' + novelGameMeta.strings.selectFlag + '</option>' );
				
				// 現在のゲームのフラグマスタから選択肢を生成
				if ( novelGameFlagData && novelGameFlagData.flagMaster && Array.isArray( novelGameFlagData.flagMaster ) ) {
					novelGameFlagData.flagMaster.forEach( function( flag ) {
						var selected = ( dialogue.flagConditions[i] && dialogue.flagConditions[i].name === flag.name ) ? ' selected' : '';
						$flagSelect.append( '<option value="' + flag.name + '"' + selected + '>' + flag.name + '</option>' );
					} );
				}
				
				// ON/OFF選択
				var $stateSelect = $( '<select class="dialogue-flag-state-select">' );
				var currentState = ( dialogue.flagConditions[i] && dialogue.flagConditions[i].state !== undefined ) ? dialogue.flagConditions[i].state : true;
				$stateSelect.append( '<option value="true"' + ( currentState ? ' selected' : '' ) + '>ON</option>' );
				$stateSelect.append( '<option value="false"' + ( ! currentState ? ' selected' : '' ) + '>OFF</option>' );
				
				// イベントハンドラ
				( function( conditionIndex ) {
					$flagSelect.on( 'change', function() {
						updateDialogueFlagCondition( index, conditionIndex );
					} );
					
					$stateSelect.on( 'change', function() {
						updateDialogueFlagCondition( index, conditionIndex );
					} );
				} )( i );
				
				$conditionRow.append( 
					'<label>' + novelGameMeta.strings.flagLabel + ( i + 1 ) + ':</label> ',
					$flagSelect, ' ',
					$stateSelect
				);
				$flagConditionsContainer.append( $conditionRow );
			}
			
			// AND/OR選択
			var $logicContainer = $( '<div class="flag-logic-container">' );
			var $logicLabel = $( '<label>' + novelGameMeta.strings.condition + '</label>' );
			var $logicSelect = $( '<select class="dialogue-flag-logic-select">' );
			$logicSelect.append( '<option value="AND"' + ( dialogue.flagConditionLogic === 'AND' ? ' selected' : '' ) + '>' + novelGameMeta.strings.conditionAnd + '</option>' );
			$logicSelect.append( '<option value="OR"' + ( dialogue.flagConditionLogic === 'OR' ? ' selected' : '' ) + '>' + novelGameMeta.strings.conditionOr + '</option>' );
			
			$logicSelect.on( 'change', function() {
				dialogueData[index].flagConditionLogic = $( this ).val();
				updateDialogueTextarea();
			} );
			
			$logicContainer.append( $logicLabel, $logicSelect );
			$flagConditionsContainer.append( $logicContainer );
			
			$container.append( $flagConditionsContainer );
			
			// 代替テキスト入力欄（alternativeモードの場合のみ表示）
			if ( dialogue.displayMode === 'alternative' ) {
				var $alternativeTextContainer = $( '<div class="alternative-text-container" style="margin-top: 10px;">' );
				var alternativeTextId = 'dialogue-alternative-text-' + index;
				var $alternativeTextLabel = $( '<label for="' + alternativeTextId + '">' + novelGameMeta.strings.alternativeTextLabel + '</label>' );
				var $alternativeTextArea = $( '<textarea id="' + alternativeTextId + '" class="dialogue-alternative-text large-text" rows="2" placeholder="' + novelGameMeta.strings.alternativeTextPlaceholder + '"></textarea>' );
				$alternativeTextArea.val( dialogue.alternativeText || '' );
				
				$alternativeTextArea.on( 'input change blur', function() {
					dialogueData[index].alternativeText = $( this ).val();
					updateDialogueTextarea();
				} );
				
				$alternativeTextContainer.append( $alternativeTextLabel, '<br>', $alternativeTextArea );
				$container.append( $alternativeTextContainer );
			}
		}
		
		return $container;
	}
	
	/**
	 * セリフのフラグ条件を更新
	 *
	 * @param {number} dialogueIndex セリフインデックス
	 * @param {number} conditionIndex 条件インデックス
	 */
	function updateDialogueFlagCondition( dialogueIndex, conditionIndex ) {
		var $item = $( '.novel-dialogue-item[data-index="' + dialogueIndex + '"]' );
		var $conditionRow = $item.find( '.flag-condition-row' ).eq( conditionIndex );
		
		var flagName = $conditionRow.find( '.dialogue-flag-condition-select' ).val();
		var flagState = $conditionRow.find( '.dialogue-flag-state-select' ).val() === 'true';
		
		// フラグ条件配列を初期化
		if ( ! dialogueData[dialogueIndex].flagConditions ) {
			dialogueData[dialogueIndex].flagConditions = [];
		}
		
		if ( flagName ) {
			dialogueData[dialogueIndex].flagConditions[conditionIndex] = {
				name: flagName,
				state: flagState
			};
		} else {
			// フラグが選択されていない場合は削除
			if ( dialogueData[dialogueIndex].flagConditions[conditionIndex] ) {
				delete dialogueData[dialogueIndex].flagConditions[conditionIndex];
			}
		}
		
		updateDialogueTextarea();
	}
	
	/**
	 * 隠しテキストエリアの更新（後方互換性のため）
	 */
	function updateDialogueTextarea() {
		// 改行を含むセリフテキストを適切に処理するため、
		// 各セリフを改行で区切って結合するのではなく、
		// JSONベースのデータのみを使用
		var textLines = dialogueData.map( function( dialogue ) {
			return dialogue.text;
		} );
		$( '#novel_dialogue_text' ).val( textLines.join( '\n' ) );
		
		// 背景データの更新
		var backgrounds = dialogueData.map( function( dialogue ) {
			return dialogue.background;
		} );
		
		// 話者データの更新
		var speakers = dialogueData.map( function( dialogue ) {
			return dialogue.speaker;
		} );
		
		// セリフテキストデータの更新（新しいJSON形式）
		var texts = dialogueData.map( function( dialogue ) {
			return dialogue.text;
		} );
		
		// フラグ条件データの更新
		var flagConditions = dialogueData.map( function( dialogue ) {
			return {
				conditions: dialogue.flagConditions || [],
				logic: dialogue.flagConditionLogic || 'AND',
				displayMode: dialogue.displayMode || 'normal',
				alternativeText: dialogue.alternativeText || ''
			};
		} );
		
		// セリフごとのキャラクター設定データの更新
		var characters = dialogueData.map( function( dialogue ) {
			return dialogue.characters || { left: '', center: '', right: '' };
		} );
		
		// 隠しフィールドに背景データを設定
		var $existingBackgroundInput = $( 'input[name="dialogue_backgrounds"]' );
		if ( $existingBackgroundInput.length === 0 ) {
			$( '<input type="hidden" name="dialogue_backgrounds">' ).appendTo( '#novel-dialogue-container' );
		}
		$( 'input[name="dialogue_backgrounds"]' ).val( JSON.stringify( backgrounds ) );
		
		// 隠しフィールドに話者データを設定
		var $existingSpeakerInput = $( 'input[name="dialogue_speakers"]' );
		if ( $existingSpeakerInput.length === 0 ) {
			$( '<input type="hidden" name="dialogue_speakers">' ).appendTo( '#novel-dialogue-container' );
		}
		$( 'input[name="dialogue_speakers"]' ).val( JSON.stringify( speakers ) );
		
		// 隠しフィールドにテキストデータを設定（新しいJSON形式）
		var $existingTextInput = $( 'input[name="dialogue_texts"]' );
		if ( $existingTextInput.length === 0 ) {
			$( '<input type="hidden" name="dialogue_texts">' ).appendTo( '#novel-dialogue-container' );
		}
		$( 'input[name="dialogue_texts"]' ).val( JSON.stringify( texts ) );
		
		// 隠しフィールドにフラグ条件データを設定
		var $existingFlagConditionsInput = $( 'input[name="dialogue_flag_conditions"]' );
		if ( $existingFlagConditionsInput.length === 0 ) {
			$( '<input type="hidden" name="dialogue_flag_conditions">' ).appendTo( '#novel-dialogue-container' );
		}
		$( 'input[name="dialogue_flag_conditions"]' ).val( JSON.stringify( flagConditions ) );
		
		// 隠しフィールドにセリフごとのキャラクター設定データを設定
		var $existingCharactersInput = $( 'input[name="dialogue_characters"]' );
		if ( $existingCharactersInput.length === 0 ) {
			$( '<input type="hidden" name="dialogue_characters">' ).appendTo( '#novel-dialogue-container' );
		}
		$( 'input[name="dialogue_characters"]' ).val( JSON.stringify( characters ) );
	}

	/**
	 * 選択肢文字列をパースしてオブジェクト配列に変換
	 *
	 * @param {string} str 選択肢文字列
	 * @return {Array} 選択肢オブジェクト配列
	 */
	/**
	 * 選択肢データをパースする（JSON形式とレガシー形式の両方をサポート）
	 */
	function parseChoices( str ) {
		var arr = [];
		if ( ! str ) {
			return arr;
		}

		// JSON形式を試行
		try {
			var jsonData = JSON.parse( str );
			if ( Array.isArray( jsonData ) ) {
				return jsonData.map( function( item ) {
					// setFlagsのクリーンアップ（空文字列や無効データを除去）
					var cleanSetFlags = [];
					if ( item.setFlags && Array.isArray( item.setFlags ) ) {
						item.setFlags.forEach( function( flagData ) {
							if ( typeof flagData === 'string' ) {
								// 旧形式：空文字列を除外
								var trimmedFlag = flagData.trim();
								if ( trimmedFlag !== '' ) {
									cleanSetFlags.push( trimmedFlag );
								}
							} else if ( typeof flagData === 'object' && flagData.name ) {
								// 新形式：nameが空でない場合のみ保持
								var trimmedName = flagData.name.trim();
								if ( trimmedName !== '' ) {
									cleanSetFlags.push( {
										name: trimmedName,
										state: flagData.state || false
									} );
								}
							}
						} );
					}
					
					return {
						text: item.text || '',
						next: item.next || '',
						flagConditions: item.flagConditions || [],
						flagConditionLogic: item.flagConditionLogic || 'AND',
						setFlags: cleanSetFlags
					};
				} );
			}
		} catch ( e ) {
			// JSON解析失敗時はレガシー形式として処理
		}

		// レガシー形式（"テキスト | 投稿ID" の行形式）
		str.split( '\n' ).forEach( function( line ) {
			var parts = line.split( '|' );
			if ( parts.length === 2 ) {
				arr.push( {
					text: parts[0].trim(),
					next: parts[1].trim(),
					flagConditions: [],
					flagConditionLogic: 'AND',
					setFlags: []
				} );
			}
		} );
		return arr;
	}

	/**
	 * 選択肢テーブルを描画
	 */
	function renderChoicesTable() {
		var choices = parseChoices( $( '#novel_choices_hidden' ).val() );
		var $tbody = $( '#novel-choices-table tbody' );

		$tbody.empty();

		choices.forEach( function( choice, index ) {
			var $row = $( '<tr>' );
			$row.attr( 'data-choice-index', index );

			// ドラッグハンドル
			$row.append( '<td class="sort-handle" style="cursor: move; text-align: center; width: 30px;">⋮⋮</td>' );

			// テキスト入力欄
			$row.append( '<td><input type="text" class="choice-text" value="' + choice.text.replace( /"/g, '&quot;' ) + '" style="width:98%"></td>' );

			// 次のシーン選択
			var $select = $( '<select class="choice-next" style="width:98%"></select>' );
			$select.append( '<option value="">' + novelGameMeta.strings.selectOption + '</option>' );

			scenes.forEach( function( scene ) {
				var selected = ( scene.ID == choice.next ) ? 'selected' : '';
				$select.append( '<option value="' + scene.ID + '" ' + selected + '>' + scene.title + ' (ID:' + scene.ID + ')</option>' );
			} );

			$select.append( '<option value="__new__">' + novelGameMeta.strings.createNew + '</option>' );
			$row.append( $( '<td>' ).append( $select ) );

			// フラグ条件UI
			var $flagConditionsCell = $( '<td style="min-width: 200px;"></td>' );
			var $flagConditionsContainer = $( '<div class="flag-conditions-container"></div>' );
			
			// フラグ条件選択（最大3つ）
			for ( var i = 0; i < 3; i++ ) {
				var $conditionRow = $( '<div class="flag-condition-row" style="margin-bottom: 5px;"></div>' );
				
				// フラグ選択
				var $flagSelect = $( '<select class="flag-condition-select" style="width: 120px; margin-right: 5px;"></select>' );
				$flagSelect.append( '<option value="">' + novelGameMeta.strings.selectFlag + '</option>' );
				
				// 現在のゲームのフラグマスタから選択肢を生成
				if ( novelGameFlagData && novelGameFlagData.flagMaster && Array.isArray( novelGameFlagData.flagMaster ) ) {
					novelGameFlagData.flagMaster.forEach( function( flag ) {
						var selected = ( choice.flagConditions[i] && choice.flagConditions[i].name === flag.name ) ? 'selected' : '';
						$flagSelect.append( '<option value="' + flag.name + '" ' + selected + '>' + flag.name + '</option>' );
					} );
				}
				
				// ON/OFF選択
				var $stateSelect = $( '<select class="flag-state-select" style="width: 60px;"></select>' );
				var currentState = ( choice.flagConditions[i] && choice.flagConditions[i].state !== undefined ) ? choice.flagConditions[i].state : true;
				$stateSelect.append( '<option value="true"' + ( currentState ? ' selected' : '' ) + '>ON</option>' );
				$stateSelect.append( '<option value="false"' + ( ! currentState ? ' selected' : '' ) + '>OFF</option>' );
				
				$conditionRow.append( $flagSelect ).append( $stateSelect );
				$flagConditionsContainer.append( $conditionRow );
			}
			
			// AND/OR選択
			var $logicSelect = $( '<select class="flag-logic-select" style="width: 60px; margin-top: 5px;"></select>' );
			$logicSelect.append( '<option value="AND"' + ( choice.flagConditionLogic === 'AND' ? ' selected' : '' ) + '>AND</option>' );
			$logicSelect.append( '<option value="OR"' + ( choice.flagConditionLogic === 'OR' ? ' selected' : '' ) + '>OR</option>' );
			$flagConditionsContainer.append( $logicSelect );
			
			$flagConditionsCell.append( $flagConditionsContainer );
			$row.append( $flagConditionsCell );

			// フラグ設定UI
			var $flagSetCell = $( '<td style="min-width: 180px;"></td>' );
			var $flagSetContainer = $( '<div class="flag-set-container"></div>' );
			
			// フラグ設定選択（ON/OFF/設定しない）
			if ( novelGameFlagData && novelGameFlagData.flagMaster && Array.isArray( novelGameFlagData.flagMaster ) ) {
				novelGameFlagData.flagMaster.forEach( function( flag ) {
					// 現在の設定値を取得（新旧両形式対応）
					var currentSetting = 'none'; // novelGameMeta.strings.defaultDoNotSet
					if ( choice.setFlags && Array.isArray( choice.setFlags ) ) {
						choice.setFlags.forEach( function( flagData ) {
							if ( typeof flagData === 'object' && flagData.name === flag.name ) {
								// 新形式: { name: "flag1", state: true/false }
								currentSetting = flagData.state ? 'on' : 'off';
							} else if ( typeof flagData === 'string' && flagData === flag.name ) {
								// 旧形式: "flag1" (常にON扱い)
								currentSetting = 'on';
							}
						} );
					}
					
					var $flagRow = $( '<div style="display: flex; align-items: center; margin-bottom: 4px; font-size: 11px;"></div>' );
					var $flagLabel = $( '<span style="min-width: 60px; margin-right: 8px;">' + flag.name + ':</span>' );
					var $flagSelect = $( '<select class="flag-set-select" data-flag-name="' + flag.name + '" style="font-size: 11px; padding: 1px 2px;">' +
						'<option value="none"' + (currentSetting === 'none' ? ' selected' : '') + '>' + novelGameMeta.strings.doNotSet + '</option>' +
						'<option value="on"' + (currentSetting === 'on' ? ' selected' : '') + '>ON</option>' +
						'<option value="off"' + (currentSetting === 'off' ? ' selected' : '') + '>OFF</option>' +
						'</select>' );
					
					// フラグ設定変更時のデバッグログ
					$flagSelect.on( 'change', function() {
						var flagName = $( this ).data( 'flag-name' );
						var newValue = $( this ).val();
						console.log( novelGameMeta.strings.flagSettingChange, flagName, '→', newValue );
						// 選択肢データの自動更新をトリガー
						setTimeout( updateChoicesHidden, 10 );
					} );
					
					$flagRow.append( $flagLabel ).append( $flagSelect );
					$flagSetContainer.append( $flagRow );
				} );
			}
			
			if ( $flagSetContainer.children().length === 0 ) {
				$flagSetContainer.append( '<small style="color: #666;">' + novelGameMeta.strings.noFlags + '</small>' );
			}
			
			$flagSetCell.append( $flagSetContainer );
			$row.append( $flagSetCell );

			// 操作エリア（削除ボタン + 編集ボタン）
			var $actionCell = $( '<td></td>' );
			var $removeButton = $( '<button type="button" class="button choice-remove">' + novelGameMeta.strings.remove + '</button>' );
			$actionCell.append( $removeButton );
			
			// 次のシーンが選択されている場合は編集ボタンを表示
			if ( choice.next && choice.next !== '__new__' && choice.next !== '' ) {
				var $editButton = $( '<a href="' + novelGameMeta.admin_url + 'post.php?post=' + choice.next + '&action=edit" target="_blank" class="button button-small edit-scene-link" style="margin-left: 5px;">' + novelGameMeta.strings.edit + '</a>' );
				$actionCell.append( $editButton );
				
				// 編集ボタンにクリックイベントを追加（投稿保存確認）
				$editButton.on( 'click', function( e ) {
					if ( ! isPostSaved() ) {
						var confirmMessage = novelGameMeta.strings.unsavedChanges;
						if ( ! confirm( confirmMessage ) ) {
							e.preventDefault();
							return false;
						}
					}
				} );
			}
			
			$row.append( $actionCell );

			$tbody.append( $row );
		} );

		// 並び替え機能を初期化
		initChoicesSortable();
	}

	/**
	 * 選択肢の並び替え機能を初期化
	 */
	function initChoicesSortable() {
		var $tbody = $( '#novel-choices-table tbody' );
		
		// 既存のsortableを削除
		if ( $tbody.hasClass( 'ui-sortable' ) ) {
			$tbody.sortable( 'destroy' );
		}
		
		// sortableを初期化
		$tbody.sortable( {
			handle: '.sort-handle',
			axis: 'y',
			helper: 'clone',
			placeholder: 'ui-state-highlight',
			update: function( event, ui ) {
				updateChoicesHidden();
			},
			start: function( event, ui ) {
				ui.placeholder.html( '<td colspan="4" style="height: 40px; background: #f0f0f0; border: 2px dashed #ccc;"></td>' );
			}
		} );
	}

	/**
	 * 選択肢の hidden フィールドを更新（JSON形式で保存）
	 */
	function updateChoicesHidden() {
		var arr = [];

		$( '#novel-choices-table tbody tr' ).each( function() {
			var $row = $( this );
			var text = $row.find( '.choice-text' ).val();
			var next = $row.find( '.choice-next' ).val();

			if ( text && next && next !== '__new__' ) {
				// フラグ条件を収集
				var flagConditions = [];
				$row.find( '.flag-condition-row' ).each( function() {
					var $conditionRow = $( this );
					var flagName = $conditionRow.find( '.flag-condition-select' ).val();
					var flagState = $conditionRow.find( '.flag-state-select' ).val() === 'true';
					
					if ( flagName ) {
						flagConditions.push( {
							name: flagName,
							state: flagState
						} );
					}
				} );
				
				// フラグ設定を収集（新形式: ON/OFF/設定しない）
				var setFlags = [];
				$row.find( '.flag-set-select' ).each( function() {
					var $select = $( this );
					var flagName = $select.data( 'flag-name' );
					var flagSetting = $select.val();
					
					if ( flagName && flagSetting !== 'none' ) {
						// 新形式: { name: "flag1", state: true/false }
						setFlags.push( {
							name: flagName,
							state: flagSetting === 'on'
						} );
					}
				} );
				
				// フラグ条件ロジック
				var flagConditionLogic = $row.find( '.flag-logic-select' ).val() || 'AND';
				
				// 選択肢オブジェクトを作成
				var choiceObject = {
					text: text,
					next: next,
					flagConditions: flagConditions,
					flagConditionLogic: flagConditionLogic,
					setFlags: setFlags
				};
				
				arr.push( choiceObject );
			}
		} );

		// JSON形式で保存
		$( '#novel_choices_hidden' ).val( JSON.stringify( arr ) );
	}

	/**
	 * 画像ファイルのバリデーション
	 *
	 * @param {Object} attachment 添付ファイルオブジェクト
	 * @return {Object} 検証結果 {valid: boolean, message: string}
	 */
	function validateImageFile( attachment ) {
		// 許可する拡張子
		var allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		
		// 最大ファイルサイズ（5MB）
		var maxSize = 5 * 1024 * 1024;
		
		// MIMEタイプチェック
		var allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
		
		// 拡張子チェック
		var extension = attachment.filename ? attachment.filename.split('.').pop().toLowerCase() : '';
		if ( allowedExtensions.indexOf( extension ) === -1 ) {
			return {
				valid: false,
				message: novelGameMeta.strings.unsupportedFileExtension
			};
		}
		
		// MIMEタイプチェック
		if ( allowedMimeTypes.indexOf( attachment.mime ) === -1 ) {
			return {
				valid: false,
				message: novelGameMeta.strings.unsupportedMimeType
			};
		}
		
		// ファイルサイズチェック
		if ( attachment.filesizeInBytes > maxSize ) {
			return {
				valid: false,
				message: novelGameMeta.strings.fileSizeExceeded
			};
		}
		
		return { valid: true, message: '' };
	}

	/**
	 * WordPress メディアアップローダーを設定
	 *
	 * @param {string} buttonId  ボタンのID
	 * @param {string} inputId   入力フィールドのID
	 * @param {string} previewId プレビュー画像のID
	 */
	function setupMediaUploader( buttonId, inputId, previewId ) {
		$( buttonId ).on( 'click', function( e ) {
			e.preventDefault();

			var customUploader = wp.media( {
				title: novelGameMeta.strings.selectImage,
				button: {
					text: novelGameMeta.strings.useThisImage
				},
				multiple: false,
				library: {
					type: 'image'
				}
			} );

			customUploader.on( 'select', function() {
				var attachment = customUploader.state().get( 'selection' ).first().toJSON();
				
				// ファイルバリデーション
				var validation = validateImageFile( attachment );
				if ( ! validation.valid ) {
					alert( validation.message );
					return;
				}
				
				$( inputId ).val( attachment.url );
				$( previewId ).attr( 'src', attachment.url ).show();
				
				// 削除ボタンを表示
				var position = $( e.target ).data( 'position' );
				if ( position ) {
					$( '.character-image-clear[data-position="' + position + '"]' ).show();
				}
			} );

			customUploader.open();
		} );
	}
	
	/**
	 * キャラクター画像の選択用メディアアップローダーを設定
	 *
	 * @param {string} position キャラクターの位置 (left, center, right)
	 */
	function setupCharacterMediaUploader( position ) {
		$( '.character-image-button[data-position="' + position + '"]' ).on( 'click', function( e ) {
			e.preventDefault();

			var customUploader = wp.media( {
				title: novelGameMeta.strings.selectCharacterImage,
				button: {
					text: novelGameMeta.strings.useThisImage
				},
				multiple: false,
				library: {
					type: 'image'
				}
			} );

			customUploader.on( 'select', function() {
				var attachment = customUploader.state().get( 'selection' ).first().toJSON();
				
				// ファイルバリデーション
				var validation = validateImageFile( attachment );
				if ( ! validation.valid ) {
					alert( validation.message );
					return;
				}
				
				$( '#novel_character_' + position ).val( attachment.url );
				$( '#novel_character_' + position + '_preview' ).attr( 'src', attachment.url ).show();
				$( '.character-image-clear[data-position="' + position + '"]' ).show();
				
				// 後方互換性のため、センターキャラクターは古いフィールドにも設定
				if ( position === 'center' ) {
					$( '#novel_character_image' ).val( attachment.url );
				}
			} );

			customUploader.open();
		} );
		
		// 削除ボタンの設定
		$( '.character-image-clear[data-position="' + position + '"]' ).on( 'click', function( e ) {
			e.preventDefault();
			$( '#novel_character_' + position ).val( '' );
			$( '#novel_character_' + position + '_preview' ).attr( 'src', '' ).hide();
			$( this ).hide();
			
			// 後方互換性のため、センターキャラクターは古いフィールドもクリア
			if ( position === 'center' ) {
				$( '#novel_character_image' ).val( '' );
			}
		} );
	}

	/**
	 * 初期化処理
	 */
	function initializeMetaBox() {
		// セリフデータの初期化
		initializeDialogueData();
		
		// 初期描画
		renderChoicesTable();
		renderDialogueList();

		// メディアアップローダーの設定
		setupMediaUploader( '#novel_background_image_button', '#novel_background_image', '#novel_background_image_preview' );
		
		// キャラクター画像用メディアアップローダーの設定
		setupCharacterMediaUploader( 'left' );
		setupCharacterMediaUploader( 'center' );
		setupCharacterMediaUploader( 'right' );
		
		// 旧キャラクター画像用（後方互換性のため）
		setupMediaUploader( '#novel_character_image_button', '#novel_character_image', '#novel_character_image_preview' );
	}

	/**
	 * イベントリスナーの設定
	 */
	function setupEventListeners() {
		// セリフを追加
		$( '#novel-dialogue-add' ).on( 'click', function() {
			addDialogue();
		} );
		
		// フォーム送信時に最新のデータを保存
		$( '#post' ).on( 'submit', function() {
			// 現在のフォームデータを最新の状態に更新
			syncCurrentFormData();
			updateDialogueTextarea();
			// 選択肢データの明示的同期
			updateChoicesHidden();
		} );
		
		// 公開・更新ボタンクリック時の明示的同期
		$( '#publish, #save-post' ).on( 'click', function() {
			// 投稿保存前に最新のフラグ設定を確実に隠しフィールドに反映
			syncCurrentFormData();
			updateDialogueTextarea();
			updateChoicesHidden();
		} );
		
		// ページ離脱時にもデータを保存
		$( window ).on( 'beforeunload', function() {
			syncCurrentFormData();
			updateDialogueTextarea();
		} );

		// 選択肢を追加
		$( '#novel-choice-add' ).on( 'click', function() {
			var $tbody = $( '#novel-choices-table tbody' );
			var $row = $( '<tr>' );

			// ドラッグハンドル列を追加
			$row.append( '<td class="sort-handle" style="cursor: move; text-align: center; width: 30px;">⋮⋮</td>' );

			// テキスト入力欄
			$row.append( '<td><input type="text" class="choice-text" value="" style="width:98%"></td>' );

			// 次のシーン選択
			var $select = $( '<select class="choice-next" style="width:98%"></select>' );
			$select.append( '<option value="">' + novelGameMeta.strings.selectOption + '</option>' );

			scenes.forEach( function( scene ) {
				$select.append( '<option value="' + scene.ID + '">' + scene.title + ' (ID:' + scene.ID + ')</option>' );
			} );

			$select.append( '<option value="__new__">' + novelGameMeta.strings.createNew + '</option>' );
			$row.append( $( '<td>' ).append( $select ) );

			// 操作エリア（削除ボタンのみ、編集ボタンは選択後に動的追加）
			$row.append( '<td><button type="button" class="button choice-remove">' + novelGameMeta.strings.remove + '</button></td>' );

			$tbody.append( $row );

			// 新しく追加した行にもsortable機能を適用
			initChoicesSortable();
		} );

		// 選択肢を削除（データ整合性チェック付き）
		$( '#novel-choices-table' ).on( 'click', '.choice-remove', function() {
			var $row = $( this ).closest( 'tr' );
			var currentChoiceText = $row.find( '.choice-text' ).val();
			var currentChoiceNext = $row.find( '.choice-next' ).val();
			
			// 削除確認メッセージにより詳細な情報を追加
			var confirmMessage = novelGameMeta.strings.confirmDelete;
			if ( currentChoiceText ) {
				confirmMessage += '\n\n' + novelGameMeta.strings.deleteTarget + ' 「' + currentChoiceText + '」';
			}
			
			// 他の選択肢への影響をチェック
			var remainingChoices = $( '#novel-choices-table tbody tr' ).not( $row ).length;
			if ( remainingChoices === 0 ) {
				confirmMessage += '\n\n' + novelGameMeta.strings.lastChoiceWarning;
			}
			
			if ( confirm( confirmMessage ) ) {
				$row.remove();
				updateChoicesHidden();
				
				// 削除後のテーブルが空になった場合の処理
				if ( $( '#novel-choices-table tbody tr' ).length === 0 ) {
					// 空のメッセージを表示（オプション）
					console.log( novelGameMeta.strings.allChoicesDeleted );
				}
			}
		} );

		// 入力変更時の処理
		$( '#novel-choices-table' ).on( 'change', '.choice-text, .choice-next, .flag-condition-select, .flag-state-select, .flag-logic-select, .flag-set-checkbox', function() {
			updateChoicesHidden();
			
			// 次のシーンの選択変更時に編集ボタンを更新
			if ( $( this ).hasClass( 'choice-next' ) ) {
				var $row = $( this ).closest( 'tr' );
				var $actionCell = $row.find( 'td:last' );
				var selectedSceneId = $( this ).val();
				
				// 既存の編集ボタンを削除
				$actionCell.find( '.edit-scene-link' ).remove();
				
				// 次のシーンが選択されている場合は編集ボタンを追加
				if ( selectedSceneId && selectedSceneId !== '__new__' && selectedSceneId !== '' ) {
					var $editButton = $( '<a href="' + novelGameMeta.admin_url + 'post.php?post=' + selectedSceneId + '&action=edit" target="_blank" class="button button-small edit-scene-link" style="margin-left: 5px;">' + novelGameMeta.strings.edit + '</a>' );
					$actionCell.append( $editButton );
					
					// 編集ボタンにクリックイベントを追加（投稿保存確認）
					$editButton.on( 'click', function( e ) {
						if ( ! isPostSaved() ) {
							var confirmMessage = novelGameMeta.strings.unsavedChanges;
							if ( ! confirm( confirmMessage ) ) {
								e.preventDefault();
								return false;
							}
						}
					} );
				}
			}
		} );

		// 新規シーン作成
		$( '#novel-choices-table' ).on( 'change', '.choice-next', function() {
			var $select = $( this );

			if ( $select.val() === '__new__' ) {
				var title = prompt( novelGameMeta.strings.selectTitle );

				if ( title ) {
					$select.prop( 'disabled', true );

					$.post( ajaxurl, {
						action: 'novel_game_create_scene',
						title: title,
						_ajax_nonce: novelGameMeta.nonce
					}, function( response ) {
						if ( response.success ) {
							scenes.push( {
								ID: response.data.ID,
								title: response.data.title
							} );

							$select.append( '<option value="' + response.data.ID + '" selected>' + response.data.title + ' (ID:' + response.data.ID + ')</option>' );
							$select.val( response.data.ID );
						} else {
							alert( novelGameMeta.strings.createFailed );
						}

						$select.prop( 'disabled', false );
						updateChoicesHidden();
					} );
				} else {
					$select.val( '' );
				}
			}
		} );

		// 次のコマンドを新規作成（自動遷移）
		$( '#novel-create-next-command' ).on( 'click', function() {
			// 投稿が保存されているかチェック
			if ( ! isPostSaved() ) {
				if ( confirm( novelGameMeta.strings.postNotSaved ) ) {
					// 投稿を保存
					$( '#publish' ).click();
					
					// 保存完了を待ってから再実行
					setTimeout( function() {
						$( '#novel-create-next-command' ).trigger( 'click' );
					}, 2000 );
				}
				return;
			}
			
			var title = prompt( novelGameMeta.strings.selectNextTitle );

			if ( title ) {
				var $button = $( this );
				var originalText = $button.text();
				
				$button.prop( 'disabled', true ).text( novelGameMeta.strings.redirectingMessage );

				// 新しい選択肢を自動追加
				var $tbody = $( '#novel-choices-table tbody' );
				var $row = $( '<tr>' );

				// ドラッグハンドル列を追加
				$row.append( '<td class="sort-handle" style="cursor: move; text-align: center; width: 30px;">⋮⋮</td>' );

				// テキスト入力欄
				$row.append( '<td><input type="text" class="choice-text" value="' + title + '" style="width:98%"></td>' );

				var $select = $( '<select class="choice-next" style="width:98%"></select>' );
				$select.append( '<option value="">' + novelGameMeta.strings.selectOption + '</option>' );

				scenes.forEach( function( scene ) {
					$select.append( '<option value="' + scene.ID + '">' + scene.title + ' (ID:' + scene.ID + ')</option>' );
				} );

				$select.append( '<option value="__new__">' + novelGameMeta.strings.createNew + '</option>' );
				$row.append( $( '<td>' ).append( $select ) );

				// 削除ボタンを追加
				$row.append( '<td><button type="button" class="button choice-remove">' + novelGameMeta.strings.remove + '</button></td>' );

				$tbody.append( $row );

				// 新しいシーンを作成
				$.post( ajaxurl, {
					action: 'novel_game_create_scene',
					title: title,
					auto_redirect: false, // 自動遷移しない
					current_post_id: novelGameMeta.current_post_id,
					_ajax_nonce: novelGameMeta.nonce
				}, function( response ) {
					if ( response.success ) {
						// 新しいシーンを選択肢に追加
						scenes.push( {
							ID: response.data.ID,
							title: response.data.title
						} );

						// 選択肢の選択欄を更新
						$select.append( '<option value="' + response.data.ID + '" selected>' + response.data.title + ' (ID:' + response.data.ID + ')</option>' );
						$select.val( response.data.ID );
						
						// 編集リンクを削除ボタンと同じセルに追加
						var $actionCell = $row.find( 'td:last' );
						var $editLink = $( '<a href="' + novelGameMeta.admin_url + 'post.php?post=' + response.data.ID + '&action=edit" target="_blank" class="button button-small edit-scene-link" style="margin-left: 5px;">' + novelGameMeta.strings.edit + '</a>' );
						$actionCell.append( $editLink );
						
						// 編集ボタンにクリックイベントを追加（投稿保存確認）
						$editLink.on( 'click', function( e ) {
							if ( ! isPostSaved() ) {
								var confirmMessage = novelGameMeta.strings.unsavedChanges;
								if ( ! confirm( confirmMessage ) ) {
									e.preventDefault();
									return false;
								}
							}
						} );
						
						// 隠しフィールドを更新
						updateChoicesHidden();
						
						alert( novelGameMeta.strings.newSceneCreated.replace( '%s', response.data.title ) );
					} else {
						alert( novelGameMeta.strings.createFailed );
						$row.remove(); // 失敗時は行を削除
					}

					$button.prop( 'disabled', false ).text( originalText );
				} ).fail( function() {
					alert( novelGameMeta.strings.createFailed );
					$button.prop( 'disabled', false ).text( originalText );
					$row.remove(); // 失敗時は行を削除
				} );
			}
		} );
		
	/**
	 * 投稿が保存されているかチェック
	 */
	function isPostSaved() {
		// 新規投稿の場合
		if ( ! novelGameMeta.current_post_id || novelGameMeta.current_post_id === 0 ) {
			return false;
		}
		
		// 投稿ステータスをチェック
		var postStatus = $( '#post_status' ).val();
		if ( postStatus === 'auto-draft' ) {
			return false;
		}
		
		// フォームの変更をチェック
		var $form = $( '#post' );
		if ( $form.length && $form.data( 'changed' ) ) {
			return false;
		}
		
		// WordPressの標準的な変更追跡をチェック
		if ( typeof wp !== 'undefined' && wp.heartbeat && wp.heartbeat.hasConnectionError && wp.heartbeat.hasConnectionError() ) {
			return false;
		}
		
		// ページタイトルに * がある場合（未保存の変更）
		if ( document.title.indexOf( '*' ) !== -1 ) {
			return false;
		}
		
		return true;
	}
		
		// フォームの変更を追跡
		$( '#post' ).on( 'input change keyup', 'input, textarea, select', function() {
			$( '#post' ).data( 'changed', true );
		} );
		
		// 特別にメタボックス内の変更も追跡
		$( '#novel_dialogue_text, #novel_choices_hidden' ).on( 'change', function() {
			$( '#post' ).data( 'changed', true );
		} );
		
		// セリフや選択肢の変更を追跡
		$( document ).on( 'input change', '.dialogue-text, .choice-text, .choice-next', function() {
			$( '#post' ).data( 'changed', true );
		} );
		
		// 投稿保存後はフラグをリセット
		$( '#post' ).on( 'submit', function() {
			$( this ).data( 'changed', false );
		} );
		
		// 公開/更新ボタンクリック時もフラグをリセット
		$( '#publish, #save-post' ).on( 'click', function() {
			setTimeout( function() {
				$( '#post' ).data( 'changed', false );
			}, 1000 );
		} );
	}

	// 初期化の実行
	if ( typeof novelGameScenes !== 'undefined' ) {
		scenes = novelGameScenes;
	}

	initializeMetaBox();
	setupEventListeners();

	// エンディング設定のチェックボックス切り替え機能
	$( '#novel_is_ending' ).on( 'change', function() {
		var $endingTextSetting = $( '#ending_text_setting' );
		if ( $( this ).is( ':checked' ) ) {
			$endingTextSetting.show();
		} else {
			$endingTextSetting.hide();
		}
	} );
} );