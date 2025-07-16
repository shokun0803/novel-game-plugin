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
		 * 次のセリフを表示
		 */
		function showNextDialogue() {
			if ( dialogueIndex < dialogues.length ) {
				$dialogueText.text( dialogues[ dialogueIndex ] );
				dialogueIndex++;
			} else {
				// セリフが終わったら選択肢を表示
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

			// 最初のセリフを表示
			showNextDialogue();
		}

		// ゲームの初期化
		initializeGame();
	} );

} )( jQuery );
