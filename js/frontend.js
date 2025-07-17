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
		var choices = [];
		var $gameContainer = $( '#novel-game-container' );
		var $dialogueText = $( '#novel-dialogue-text' );
		var $dialogueBox = $( '#novel-dialogue-box' );
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
			var dialogueData = $( '#novel-dialogue-data' ).text();
			var choicesData = $( '#novel-choices-data' ).text();

			if ( dialogueData ) {
				dialogues = JSON.parse( dialogueData );
			}

			if ( choicesData ) {
				choices = JSON.parse( choicesData );
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
			
			dialogues.forEach( function( dialogue ) {
				const pages = splitTextIntoPages( dialogue );
				allDialoguePages = allDialoguePages.concat( pages );
			} );
			
			currentDialogueIndex = 0;
			currentPageIndex = 0;
		}
		
		/**
		 * 現在のページを表示する
		 */
		function displayCurrentPage() {
			if ( currentPageIndex < allDialoguePages.length ) {
				const currentPageText = allDialoguePages[ currentPageIndex ];
				const formattedText = formatTextForDisplay( currentPageText );
				$dialogueText.text( formattedText );
				return true;
			}
			return false;
		}
		
		/**
		 * 次のページまたは選択肢を表示
		 */
		function showNextDialogue() {
			// 次のページがある場合
			if ( currentPageIndex < allDialoguePages.length - 1 ) {
				currentPageIndex++;
				displayCurrentPage();
			} else {
				// すべてのセリフが終わったら選択肢を表示
				$dialogueBox.hide();
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

			$choicesContainer.empty();

			choices.forEach( function( choice ) {
				var $button = $( '<button>' )
					.addClass( 'novel-choice-button' )
					.text( choice.text )
					.on( 'click', function( e ) {
						e.preventDefault();
						e.stopPropagation();
						if ( choice.nextScene ) {
							window.location.href = choice.nextScene;
						}
					} );

				// タッチデバイス対応
				if ( isTouch ) {
					$button.on( 'touchstart', function() {
						$( this ).addClass( 'touch-active' );
					} ).on( 'touchend touchcancel', function() {
						$( this ).removeClass( 'touch-active' );
					} );
				}

				$choicesContainer.append( $button );
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

				// 選択肢ボタンがクリックされた場合も無視
				if ( $( e.target ).hasClass( 'novel-choice-button' ) ) {
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
