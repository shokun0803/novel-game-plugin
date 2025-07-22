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

			// 削除ボタン
			$row.append( '<td><button type="button" class="button choice-remove">' + novelGameMeta.strings.remove + '</button></td>' );

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
				message: 'サポートされていないファイル形式です。jpg, jpeg, png, gif, webp のみアップロード可能です。'
			};
		}
		
		// MIMEタイプチェック
		if ( allowedMimeTypes.indexOf( attachment.mime ) === -1 ) {
			return {
				valid: false,
				message: 'サポートされていないファイル形式です。画像ファイルのみアップロード可能です。'
			};
		}
		
		// ファイルサイズチェック
		if ( attachment.filesizeInBytes > maxSize ) {
			return {
				valid: false,
				message: 'ファイルサイズが大きすぎます。5MB以下のファイルをアップロードしてください。'
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

			// 削除ボタン
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
				confirmMessage += '\n\n削除対象: 「' + currentChoiceText + '」';
			}
			
			// 他の選択肢への影響をチェック
			var remainingChoices = $( '#novel-choices-table tbody tr' ).not( $row ).length;
			if ( remainingChoices === 0 ) {
				confirmMessage += '\n\n注意: これは最後の選択肢です。削除すると選択肢が無くなります。';
			}
			
			if ( confirm( confirmMessage ) ) {
				$row.remove();
				updateChoicesHidden();
				
				// 削除後のテーブルが空になった場合の処理
				if ( $( '#novel-choices-table tbody tr' ).length === 0 ) {
					// 空のメッセージを表示（オプション）
					console.log( '選択肢が全て削除されました。' );
				}
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
			// 投稿が保存されているかチェック
			if ( ! noveltool_is_post_saved() ) {
				if ( confirm( '投稿が保存されていません。保存してから新しいコマンドを作成しますか？' ) ) {
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
						
						// 編集リンクを追加
						var $editLink = $( '<a href="' + novelGameMeta.admin_url + 'post.php?post=' + response.data.ID + '&action=edit" target="_blank" class="button button-small edit-scene-link">編集</a>' );
						$row.find( 'td:last' ).append( $editLink );
						
						// 隠しフィールドを更新
						updateChoicesHidden();
						
						alert( '新しいシーン「' + response.data.title + '」が作成されました。編集リンクから編集できます。' );
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
		function noveltool_is_post_saved() {
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
			
			return true;
		}
		
		// フォームの変更を追跡
		$( '#post' ).on( 'change keyup', 'input, textarea, select', function() {
			$( '#post' ).data( 'changed', true );
		} );
		
		// 投稿保存後はフラグをリセット
		$( '#post' ).on( 'submit', function() {
			$( this ).data( 'changed', false );
		} );
	}

	// 初期化の実行
	if ( typeof novelGameScenes !== 'undefined' ) {
		scenes = novelGameScenes;
	}

	initializeMetaBox();
	setupEventListeners();
} );