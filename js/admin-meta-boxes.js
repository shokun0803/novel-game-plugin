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
			
			// dialogueData が存在し、該当インデックスがある場合のみ更新
			if ( dialogueData && dialogueData[index] ) {
				dialogueData[index].text = text || '';
				dialogueData[index].speaker = speaker || '';
				dialogueData[index].background = background || '';
			}
		} );
	}
	
	/**
	 * セリフデータの初期化
	 */
	function initializeDialogueData() {
		// 既存のセリフデータを読み込み
		var existingLines = novelGameMeta.dialogue_lines || [];
		var existingBackgrounds = novelGameMeta.dialogue_backgrounds || [];
		var existingSpeakers = novelGameMeta.dialogue_speakers || [];
		
		// 常に新しいデータでリセット（初期化時）
		dialogueData = [];
		
		// 既存のセリフ行をデータ配列に変換
		existingLines.forEach( function( line, index ) {
			dialogueData.push( {
				text: line,
				background: existingBackgrounds[index] || '',
				speaker: existingSpeakers[index] || ''
			} );
		} );
		
		// セリフが1つもない場合は空のセリフを1つ追加
		if ( dialogueData.length === 0 ) {
			dialogueData.push( {
				text: '',
				background: '',
				speaker: ''
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
			var $textArea = $( '<textarea class="dialogue-text large-text" rows="2" placeholder="セリフを入力してください"></textarea>' );
			$textArea.val( dialogue.text );
			$textArea.on( 'input change blur', function() {
				dialogueData[index].text = $( this ).val();
				updateDialogueTextarea();
			} );
			
			// 話者選択
			var $speakerContainer = $( '<div class="dialogue-speaker-container">' );
			var $speakerLabel = $( '<label>話者:</label>' );
			var $speakerSelect = $( '<select class="dialogue-speaker-select">' );
			$speakerSelect.append( '<option value="">-- 話者を選択 --</option>' );
			$speakerSelect.append( '<option value="left"' + ( dialogue.speaker === 'left' ? ' selected' : '' ) + '>左キャラクター</option>' );
			$speakerSelect.append( '<option value="center"' + ( dialogue.speaker === 'center' ? ' selected' : '' ) + '>中央キャラクター</option>' );
			$speakerSelect.append( '<option value="right"' + ( dialogue.speaker === 'right' ? ' selected' : '' ) + '>右キャラクター</option>' );
			$speakerSelect.append( '<option value="narrator"' + ( dialogue.speaker === 'narrator' ? ' selected' : '' ) + '>ナレーター</option>' );
			
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
			var $imageButton = $( '<button type="button" class="button dialogue-background-button">背景画像を選択</button>' );
			var $imageClearButton = $( '<button type="button" class="button dialogue-background-clear" style="display: ' + ( dialogue.background ? 'inline-block' : 'none' ) + ';">削除</button>' );
			
			$imageButton.on( 'click', function() {
				selectDialogueBackground( index );
			} );
			
			$imageClearButton.on( 'click', function() {
				dialogueData[index].background = '';
				renderDialogueList();
				updateDialogueTextarea();
			} );
			
			$imageContainer.append( $imageInput, $imagePreview, '<br>', $imageButton, $imageClearButton );
			
			// 削除ボタン
			var $deleteButton = $( '<button type="button" class="button dialogue-delete-button">削除</button>' );
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
			
			$item.append( '<p><strong>セリフ ' + ( index + 1 ) + '</strong></p>' );
			$item.append( $textArea );
			$item.append( $speakerContainer );
			$item.append( '<p><strong>背景画像:</strong></p>' );
			$item.append( $imageContainer );
			$item.append( $controls );
			$item.append( '<hr>' );
			
			$container.append( $item );
		} );
	}
	
	/**
	 * セリフの削除
	 */
	function deleteDialogue( index ) {
		if ( dialogueData.length <= 1 ) {
			alert( '最低1つのセリフは必要です。' );
			return;
		}
		
		if ( confirm( '本当にこのセリフを削除しますか？' ) ) {
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
			speaker: ''
		} );
		renderDialogueList();
		updateDialogueTextarea();
	}
	
	/**
	 * 背景画像の選択
	 */
	function selectDialogueBackground( index ) {
		if ( typeof wp.media === 'undefined' ) {
			alert( 'メディアライブラリが利用できません。' );
			return;
		}
		
		var frame = wp.media( {
			title: '背景画像を選択',
			multiple: false,
			library: {
				type: 'image'
			},
			button: {
				text: 'この画像を使用'
			}
		} );
		
		frame.on( 'select', function() {
			var selection = frame.state().get( 'selection' );
			var attachment = selection.first().toJSON();
			
			dialogueData[index].background = attachment.url;
			renderDialogueList();
			updateDialogueTextarea();
		} );
		
		frame.open();
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
	}

	/**
	 * 選択肢文字列をパースしてオブジェクト配列に変換
	 *
	 * @param {string} str 選択肢文字列
	 * @return {Array} 選択肢オブジェクト配列
	 */
	function parseChoices( str ) {
		var arr = [];
		if ( ! str ) {
			return arr;
		}

		str.split( '\n' ).forEach( function( line ) {
			var parts = line.split( '|' );
			if ( parts.length === 2 ) {
				arr.push( {
					text: parts[0].trim(),
					next: parts[1].trim()
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

		choices.forEach( function( choice ) {
			var $row = $( '<tr>' );

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

			// 削除ボタン
			$row.append( '<td><button type="button" class="button choice-remove">' + novelGameMeta.strings.remove + '</button></td>' );

			$tbody.append( $row );
		} );
	}

	/**
	 * 選択肢の hidden フィールドを更新
	 */
	function updateChoicesHidden() {
		var arr = [];

		$( '#novel-choices-table tbody tr' ).each( function() {
			var text = $( this ).find( '.choice-text' ).val();
			var next = $( this ).find( '.choice-next' ).val();

			if ( text && next && next !== '__new__' ) {
				arr.push( text + ' | ' + next );
			}
		} );

		$( '#novel_choices_hidden' ).val( arr.join( '\n' ) );
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
				multiple: false
			} );

			customUploader.on( 'select', function() {
				var attachment = customUploader.state().get( 'selection' ).first().toJSON();
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
				title: 'キャラクター画像を選択',
				button: {
					text: 'この画像を使用'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			} );

			customUploader.on( 'select', function() {
				var attachment = customUploader.state().get( 'selection' ).first().toJSON();
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

			$row.append( '<td><input type="text" class="choice-text" value="" style="width:98%"></td>' );

			var $select = $( '<select class="choice-next" style="width:98%"></select>' );
			$select.append( '<option value="">' + novelGameMeta.strings.selectOption + '</option>' );

			scenes.forEach( function( scene ) {
				$select.append( '<option value="' + scene.ID + '">' + scene.title + ' (ID:' + scene.ID + ')</option>' );
			} );

			$select.append( '<option value="__new__">' + novelGameMeta.strings.createNew + '</option>' );
			$row.append( $( '<td>' ).append( $select ) );

			$row.append( '<td><button type="button" class="button choice-remove">' + novelGameMeta.strings.remove + '</button></td>' );

			$tbody.append( $row );
		} );

		// 選択肢を削除
		$( '#novel-choices-table' ).on( 'click', '.choice-remove', function() {
			if ( confirm( novelGameMeta.strings.confirmDelete ) ) {
				$( this ).closest( 'tr' ).remove();
				updateChoicesHidden();
			}
		} );

		// 入力変更時の処理
		$( '#novel-choices-table' ).on( 'change', '.choice-text, .choice-next', function() {
			updateChoicesHidden();
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
			var title = prompt( novelGameMeta.strings.selectNextTitle );

			if ( title ) {
				var $button = $( this );
				var originalText = $button.text();
				
				$button.prop( 'disabled', true ).text( novelGameMeta.strings.redirectingMessage );

				$.post( ajaxurl, {
					action: 'novel_game_create_scene',
					title: title,
					auto_redirect: true,
					current_post_id: novelGameMeta.current_post_id,
					_ajax_nonce: novelGameMeta.nonce
				}, function( response ) {
					if ( response.success && response.data.edit_url ) {
						// 編集画面に遷移
						window.location.href = response.data.edit_url;
					} else {
						alert( novelGameMeta.strings.createFailed );
						$button.prop( 'disabled', false ).text( originalText );
					}
				} ).fail( function() {
					alert( novelGameMeta.strings.createFailed );
					$button.prop( 'disabled', false ).text( originalText );
				} );
			}
		} );
	}

	// 初期化の実行
	if ( typeof novelGameScenes !== 'undefined' ) {
		scenes = novelGameScenes;
	}

	initializeMetaBox();
	setupEventListeners();
} );