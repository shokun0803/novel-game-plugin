/**
 * フロントエンドのノベルゲーム表示用JavaScript
 *
 * @package NovelGamePlugin
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	// DOMの読み込み完了を待つ
	$( document ).ready( function() {

		// 変数の初期化
		var dialogueIndex = 0;
		var dialogues = [];
		var dialogueData = [];
		var choices = [];
		var baseBackground = '';
		var currentBackground = '';
		var charactersData = {};
		var $gameContainer = $( '#novel-game-container' );
		var $dialogueText = $( '#novel-dialogue-text' );
		var $dialogueBox = $( '#novel-dialogue-box' );
		var $speakerName = $( '#novel-speaker-name' );
		var $dialogueContinue = $( '#novel-dialogue-continue' );
		var $choicesContainer = $( '#novel-choices' );
		var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
		
		// セリフ表示用の新しい変数
		var currentDialogueIndex = 0;
		var currentPageIndex = 0;
		var currentDialoguePages = [];
		var allDialoguePages = [];
		
		// 表示設定
		var displaySettings = {
			maxCharsPerLine: 20,
			maxLines: 3,
			get maxCharsPerPage() {
				return this.maxCharsPerLine * this.maxLines;
			},
			
			// 画面サイズに応じた設定調整
			adjustForScreenSize: function() {
				const screenWidth = window.innerWidth;
				
				if ( screenWidth < 480 ) {
					// 小画面：文字数を調整
					this.maxCharsPerLine = 18;
				} else if ( screenWidth < 768 ) {
					// モバイル：標準設定
					this.maxCharsPerLine = 20;
				} else {
					// タブレット・デスクトップ：標準設定
					this.maxCharsPerLine = 20;
				}
			}
		};

		// データの取得
		try {
			var dialogueDataRaw = $( '#novel-dialogue-data' ).text();
			var choicesData = $( '#novel-choices-data' ).text();
			var baseBackgroundData = $( '#novel-base-background' ).text();
			var charactersDataRaw = $( '#novel-characters-data' ).text();

			if ( dialogueDataRaw ) {
				dialogueData = JSON.parse( dialogueDataRaw );
				
				// 後方互換性のため、文字列配列の場合は変換
				if ( dialogueData.length > 0 && typeof dialogueData[0] === 'string' ) {
					dialogueData = dialogueData.map( function( text ) {
						return { text: text, background: '', speaker: '' };
					} );
				}
				
				// 旧形式のために dialogues 配列も維持
				dialogues = dialogueData.map( function( item ) {
					return item.text;
				} );
			}

			if ( choicesData ) {
				choices = JSON.parse( choicesData );
			}
			
			if ( baseBackgroundData ) {
				baseBackground = JSON.parse( baseBackgroundData );
				currentBackground = baseBackground;
			}
			
			if ( charactersDataRaw ) {
				charactersData = JSON.parse( charactersDataRaw );
			}
		} catch ( error ) {
			console.error( 'ノベルゲームデータの解析に失敗しました:', error );
			return;
		}

		/**
		 * テキストを20文字×3行のページに分割する
		 *
		 * @param {string} text 分割するテキスト
		 * @return {array} ページ配列
		 */
		function splitTextIntoPages( text ) {
			if ( ! text ) {
				return [];
			}
			
			const pages = [];
			let currentIndex = 0;
			
			while ( currentIndex < text.length ) {
				const pageText = text.substring( currentIndex, currentIndex + displaySettings.maxCharsPerPage );
				pages.push( pageText );
				currentIndex += displaySettings.maxCharsPerPage;
			}
			
			return pages;
		}
		
		/**
		 * ページテキストを表示用にフォーマット（20文字で改行）
		 *
		 * @param {string} pageText ページのテキスト
		 * @return {string} フォーマットされたテキスト
		 */
		function formatTextForDisplay( pageText ) {
			const lines = [];
			let currentIndex = 0;
			
			while ( currentIndex < pageText.length && lines.length < displaySettings.maxLines ) {
				const lineText = pageText.substring( currentIndex, currentIndex + displaySettings.maxCharsPerLine );
				lines.push( lineText );
				currentIndex += displaySettings.maxCharsPerLine;
			}
			
			return lines.join( '\n' );
		}
		
		/**
		 * すべてのセリフをページに分割して準備する
		 */
		function prepareDialoguePages() {
			allDialoguePages = [];
			
			dialogueData.forEach( function( dialogue, dialogueIndex ) {
				const pages = splitTextIntoPages( dialogue.text );
				
				pages.forEach( function( pageText, pageIndex ) {
					allDialoguePages.push( {
						text: pageText,
						background: dialogue.background,
						speaker: dialogue.speaker,
						isFirstPageOfDialogue: pageIndex === 0,
						dialogueIndex: dialogueIndex
					} );
				} );
			} );
			
			currentDialogueIndex = 0;
			currentPageIndex = 0;
		}
		
		/**
		 * キャラクターの状態を更新する
		 *
		 * @param {string} activeSpeaker 現在話しているキャラクター
		 */
		function updateCharacterStates( activeSpeaker ) {
			// すべてのキャラクターをリセット
			$( '.novel-character' ).removeClass( 'speaking not-speaking' );
			
			if ( activeSpeaker && activeSpeaker !== 'narrator' ) {
				// 話しているキャラクターを強調
				$( '.novel-character-' + activeSpeaker ).addClass( 'speaking' );
				
				// 他のキャラクターを薄く表示
				$( '.novel-character' ).not( '.novel-character-' + activeSpeaker ).addClass( 'not-speaking' );
			}
		}
		
		/**
		 * 話者名を表示する
		 *
		 * @param {string} speaker 話者の種類
		 */
		function displaySpeakerName( speaker ) {
			var speakerName = '';
			
			switch ( speaker ) {
				case 'left':
					speakerName = '左キャラクター';
					break;
				case 'center':
					speakerName = '中央キャラクター';
					break;
				case 'right':
					speakerName = '右キャラクター';
					break;
				case 'narrator':
					speakerName = 'ナレーター';
					break;
				default:
					speakerName = '';
			}
			
			if ( speakerName ) {
				$speakerName.text( speakerName ).show();
			} else {
				$speakerName.hide();
			}
		}
		
		/**
		 * 背景画像を変更する（フェードアニメーション付き）
		 */
		function changeBackground( newBackground ) {
			if ( newBackground === currentBackground ) {
				return Promise.resolve();
			}
			
			return new Promise( function( resolve ) {
				// 新しい背景が空の場合は何もしない
				if ( ! newBackground && ! currentBackground ) {
					resolve();
					return;
				}
				
				// 現在の背景が空で、新しい背景が設定されている場合
				if ( ! currentBackground && newBackground ) {
					$gameContainer.css( 'background-image', 'url("' + newBackground + '")' );
					currentBackground = newBackground;
					resolve();
					return;
				}
				
				// 現在の背景があり、新しい背景が空の場合は何もしない
				if ( currentBackground && ! newBackground ) {
					resolve();
					return;
				}
				
				// フェードアニメーション
				$gameContainer.fadeOut( 300, function() {
					$gameContainer.css( 'background-image', 'url("' + newBackground + '")' );
					currentBackground = newBackground;
					$gameContainer.fadeIn( 300, function() {
						resolve();
					} );
				} );
			} );
		}
		
		/**
		 * 現在のページを表示する
		 */
		function displayCurrentPage() {
			if ( currentPageIndex < allDialoguePages.length ) {
				const currentPage = allDialoguePages[ currentPageIndex ];
				const formattedText = formatTextForDisplay( currentPage.text );
				
				// 話者名を表示
				displaySpeakerName( currentPage.speaker );
				
				// キャラクターの状態を更新
				updateCharacterStates( currentPage.speaker );
				
				// 継続インジケーターの表示/非表示
				if ( currentPageIndex < allDialoguePages.length - 1 ) {
					$dialogueContinue.show();
				} else {
					$dialogueContinue.hide();
				}
				
				// 新しいセリフの最初のページの場合は背景を変更
				if ( currentPage.isFirstPageOfDialogue && currentPage.background ) {
					changeBackground( currentPage.background ).then( function() {
						$dialogueText.text( formattedText );
					} );
				} else {
					$dialogueText.text( formattedText );
				}
				
				return true;
			}
			return false;
		}
		
		/**
		 * 次のページまたは選択肢を表示
		 */
		function showNextDialogue() {
			// キーボードイベントリスナーをクリーンアップ
			$( document ).off( 'keydown.novel-choices' );
			
			// 次のページがある場合
			if ( currentPageIndex < allDialoguePages.length - 1 ) {
				currentPageIndex++;
				displayCurrentPage();
			} else {
				// すべてのセリフが終わったら選択肢を表示
				$dialogueBox.hide();
				
				// すべてのキャラクターを通常状態に戻す
				updateCharacterStates( '' );
				
				showChoices();
			}
		}

		/**
		 * 選択肢を表示
		 */
		function showChoices() {
			if ( choices.length === 0 ) {
				return;
			}

			// 最大4つの選択肢に制限
			const displayChoices = choices.slice( 0, 4 );
			
			$choicesContainer.empty();
			
			// 選択肢リストコンテナの作成
			var $choicesList = $( '<div>' ).addClass( 'choice-list' );
			
			// 選択肢状態の初期化
			var selectedChoiceIndex = 0;
			var choiceElements = [];
			
			// 各選択肢を作成
			displayChoices.forEach( function( choice, index ) {
				var $choiceItem = $( '<div>' )
					.addClass( 'choice-item' )
					.attr( 'data-index', index )
					.attr( 'data-next-scene', choice.nextScene )
					.text( choice.text );
				
				// 最初の選択肢を選択状態にする
				if ( index === 0 ) {
					$choiceItem.addClass( 'selected' );
				}
				
				choiceElements.push( $choiceItem );
				$choicesList.append( $choiceItem );
			} );
			
			$choicesContainer.append( $choicesList );
			
			// 選択肢の更新関数
			function updateChoiceSelection( newIndex ) {
				// 現在の選択を解除
				choiceElements[ selectedChoiceIndex ].removeClass( 'selected' );
				
				// 新しい選択肢を選択状態にする
				selectedChoiceIndex = newIndex;
				choiceElements[ selectedChoiceIndex ].addClass( 'selected' );
			}
			
			// 選択肢の実行関数
			function executeChoice( index ) {
				var nextScene = displayChoices[ index ].nextScene;
				if ( nextScene ) {
					window.location.href = nextScene;
				}
			}
			
			// タッチデバイスの場合：タップで即座に選択・実行
			if ( isTouch ) {
				choiceElements.forEach( function( $element, index ) {
					$element.on( 'touchstart', function( e ) {
						e.preventDefault();
						e.stopPropagation();
						executeChoice( index );
					} );
					
					$element.on( 'click', function( e ) {
						e.preventDefault();
						e.stopPropagation();
						executeChoice( index );
					} );
				} );
			} else {
				// デスクトップの場合：クリックで選択状態変更、エンターで実行
				choiceElements.forEach( function( $element, index ) {
					$element.on( 'click', function( e ) {
						e.preventDefault();
						e.stopPropagation();
						updateChoiceSelection( index );
					} );
				} );
			}
			
			// キーボードナビゲーション
			$( document ).on( 'keydown.novel-choices', function( e ) {
				if ( ! $choicesContainer.is( ':visible' ) ) {
					return;
				}
				
				switch( e.which ) {
					case 38: // 上矢印
						e.preventDefault();
						var newIndex = selectedChoiceIndex > 0 ? selectedChoiceIndex - 1 : displayChoices.length - 1;
						updateChoiceSelection( newIndex );
						break;
						
					case 40: // 下矢印
						e.preventDefault();
						var newIndex = selectedChoiceIndex < displayChoices.length - 1 ? selectedChoiceIndex + 1 : 0;
						updateChoiceSelection( newIndex );
						break;
						
					case 13: // エンター
						e.preventDefault();
						executeChoice( selectedChoiceIndex );
						break;
				}
			} );
			
			$choicesContainer.show();
		}

		/**
		 * ゲームコンテナのクリック/タッチイベント
		 */
		function setupGameInteraction() {
			var eventType = isTouch ? 'touchend' : 'click';
			
			$gameContainer.on( eventType, function( e ) {
				// 選択肢が表示されている場合はクリックを無視
				if ( $choicesContainer.is( ':visible' ) ) {
					return;
				}

				// 選択肢要素がクリックされた場合も無視
				if ( $( e.target ).hasClass( 'choice-item' ) || $( e.target ).closest( '.choice-list' ).length > 0 ) {
					return;
				}

				// 次のセリフを表示
				showNextDialogue();
			} );

			// タッチデバイス用の追加イベント
			if ( isTouch ) {
				$gameContainer.on( 'touchstart', function( e ) {
					// 選択肢が表示されている場合はタッチを無視
					if ( $choicesContainer.is( ':visible' ) ) {
						return;
					}
					
					// 選択肢要素がタッチされた場合も無視
					if ( $( e.target ).hasClass( 'choice-item' ) || $( e.target ).closest( '.choice-list' ).length > 0 ) {
						return;
					}
					
					// タッチフィードバック
					$( this ).addClass( 'touch-active' );
				} ).on( 'touchcancel', function() {
					$( this ).removeClass( 'touch-active' );
				} );
			}
		}

		/**
		 * レスポンシブ対応の調整
		 */
		function adjustForResponsive() {
			// ビューポートの高さを取得
			var viewportHeight = window.innerHeight;
			
			// モバイルブラウザのアドレスバーを考慮してコンテナの高さを調整
			if ( isTouch && viewportHeight < 500 ) {
				$gameContainer.css( 'height', viewportHeight + 'px' );
			}
			
			// 画面サイズに応じた表示設定の調整
			displaySettings.adjustForScreenSize();
			
			// 既にセリフが表示されている場合は再分割
			if ( dialogues.length > 0 && allDialoguePages.length > 0 ) {
				const currentPageContent = allDialoguePages[ currentPageIndex ];
				prepareDialoguePages();
				
				// 現在の位置を可能な限り保持
				if ( currentPageContent ) {
					const newPageIndex = allDialoguePages.findIndex( function( page ) {
						return page.includes( currentPageContent.substring( 0, 10 ) );
					} );
					
					if ( newPageIndex !== -1 ) {
						currentPageIndex = newPageIndex;
					}
				}
				
				displayCurrentPage();
			}
		}

		/**
		 * リサイズイベントの処理
		 */
		function handleResize() {
			adjustForResponsive();
		}

		/**
		 * 初期化処理
		 */
		function initializeGame() {
			// ゲームコンテナが存在しない場合は処理を中断
			if ( $gameContainer.length === 0 ) {
				return;
			}

			// イベントリスナーの設定
			setupGameInteraction();

			// リサイズイベントの設定
			$( window ).on( 'resize orientationchange', handleResize );

			// 初期調整
			adjustForResponsive();

			// セリフデータがある場合は分割処理を実行
			if ( dialogues.length > 0 ) {
				prepareDialoguePages();
				displayCurrentPage();
			} else {
				// デバッグ用：セリフデータがない場合のメッセージ
				console.log( 'No dialogue data found' );
			}
		}

		// ゲームの初期化
		initializeGame();
	} );

} )( jQuery );
