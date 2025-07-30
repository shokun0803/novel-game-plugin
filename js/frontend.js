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

		// モーダル関連の変数
		var $modalOverlay = $( '#novel-game-modal-overlay' );
		var $startButton = $( '#novel-game-start-btn' );
		var $closeButton = $( '#novel-game-close-btn' );
		
		// モーダル表示フラグ
		var isModalOpen = false;

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
		
		// ゲーム設定
		var gameSettings = {};
		
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
			var gameSettingsData = $( '#novel-game-settings' ).text();

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
			
			if ( gameSettingsData ) {
				gameSettings = JSON.parse( gameSettingsData );
			} else {
				// デフォルト設定
				gameSettings = {
					title: '',
					description: '',
					ending_message: 'おわり'
				};
			}
		} catch ( error ) {
			console.error( 'ノベルゲームデータの解析に失敗しました:', error );
			return;
		}

		/**
		 * ゲームデータを動的に読み込む
		 *
		 * @param {string} gameUrl ゲームのURL
		 */
		function loadGameData( gameUrl ) {
			console.log( 'loadGameData called with URL:', gameUrl );
			return new Promise( function( resolve, reject ) {
				$.ajax( {
					url: gameUrl,
					type: 'GET',
					success: function( response ) {
						console.log( 'AJAX response received, response length:', response.length );
						try {
							// レスポンスからゲームデータを抽出
							var $response = $( response );
							console.log( 'Response parsed as jQuery object, elements count:', $response.length );
							
							// セリフデータを取得
							var dialogueDataScript = $response.filter( 'script#novel-dialogue-data' );
							if ( dialogueDataScript.length === 0 ) {
								dialogueDataScript = $response.find( '#novel-dialogue-data' );
							}
							console.log( 'Dialogue data script found:', dialogueDataScript.length );
							
							if ( dialogueDataScript.length > 0 ) {
								var dialogueDataText = dialogueDataScript.text() || dialogueDataScript.html();
								console.log( 'Dialogue data text length:', dialogueDataText ? dialogueDataText.length : 0 );
								if ( dialogueDataText ) {
									dialogueData = JSON.parse( dialogueDataText );
									console.log( 'Parsed dialogue data:', dialogueData.length );
									
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
									console.log( 'Dialogues array updated, length:', dialogues.length );
								}
							}
							
							// 選択肢データを取得
							var choicesDataScript = $response.filter( 'script#novel-choices-data' );
							if ( choicesDataScript.length === 0 ) {
								choicesDataScript = $response.find( '#novel-choices-data' );
							}
							console.log( 'Choices data script found:', choicesDataScript.length );
							
							if ( choicesDataScript.length > 0 ) {
								var choicesDataText = choicesDataScript.text() || choicesDataScript.html();
								if ( choicesDataText ) {
									choices = JSON.parse( choicesDataText );
									console.log( 'Parsed choices data:', choices.length );
								}
							}
							
							// 背景データを取得
							var backgroundDataScript = $response.filter( 'script#novel-base-background' );
							if ( backgroundDataScript.length === 0 ) {
								backgroundDataScript = $response.find( '#novel-base-background' );
							}
							console.log( 'Background data script found:', backgroundDataScript.length );
							
							if ( backgroundDataScript.length > 0 ) {
								var backgroundDataText = backgroundDataScript.text() || backgroundDataScript.html();
								if ( backgroundDataText ) {
									baseBackground = JSON.parse( backgroundDataText );
									currentBackground = baseBackground;
									console.log( 'Parsed background data:', baseBackground );
								}
							}
							
							// キャラクターデータを取得
							var charactersDataScript = $response.filter( 'script#novel-characters-data' );
							if ( charactersDataScript.length === 0 ) {
								charactersDataScript = $response.find( '#novel-characters-data' );
							}
							console.log( 'Characters data script found:', charactersDataScript.length );
							
							if ( charactersDataScript.length > 0 ) {
								var charactersDataText = charactersDataScript.text() || charactersDataScript.html();
								if ( charactersDataText ) {
									charactersData = JSON.parse( charactersDataText );
									console.log( 'Parsed characters data:', charactersData );
								}
							}
							
							// ゲーム設定データを取得
							var gameSettingsScript = $response.filter( 'script#novel-game-settings' );
							if ( gameSettingsScript.length === 0 ) {
								gameSettingsScript = $response.find( '#novel-game-settings' );
							}
							console.log( 'Game settings script found:', gameSettingsScript.length );
							
							if ( gameSettingsScript.length > 0 ) {
								var gameSettingsText = gameSettingsScript.text() || gameSettingsScript.html();
								if ( gameSettingsText ) {
									gameSettings = JSON.parse( gameSettingsText );
									console.log( 'Parsed game settings:', gameSettings );
								}
							} else {
								// デフォルト設定
								gameSettings = {
									title: '',
									description: '',
									ending_message: 'おわり'
								};
							}
							
							// ゲームコンテナの内容を更新
							// モーダル内のゲームコンテナではなく、実際のゲームページのコンテナを探す
							var $gameContentElement = null;
							
							// まず、モーダル以外のゲームコンテナを探す
							var $allGameContainers = $response.find( '#novel-game-container' );
							console.log( 'Found game containers:', $allGameContainers.length );
							$allGameContainers.each( function( index ) {
								var $container = $( this );
								var containerHtml = $container.html();
								console.log( 'Container ' + index + ' HTML length:', containerHtml ? containerHtml.length : 0 );
								console.log( 'Container ' + index + ' is in modal:', $container.closest( '.novel-game-modal-overlay' ).length > 0 );
								console.log( 'Container ' + index + ' content preview:', containerHtml ? containerHtml.substring( 0, 100 ) : 'empty' );
								
								// モーダル内のコンテナ（空の場合）は除外
								if ( $container.closest( '.novel-game-modal-overlay' ).length === 0 && 
									 containerHtml && containerHtml.trim() !== '' && 
									 containerHtml.indexOf( 'ゲーム内容は動的に読み込まれます' ) === -1 ) {
									$gameContentElement = $container;
									console.log( 'Selected container ' + index + ' as game content' );
									return false; // break
								}
							} );
							
							// 見つからない場合は、フィルタで直接探す
							if ( ! $gameContentElement || $gameContentElement.length === 0 ) {
								console.log( 'No valid container found in find, trying filter' );
								$gameContentElement = $response.filter( '#novel-game-container' ).first();
								// それでも見つからない場合は、最初に見つかったものを使用
								if ( $gameContentElement.length === 0 ) {
									console.log( 'No container found in filter, using first found' );
									$gameContentElement = $response.find( '#novel-game-container' ).first();
								}
							}
							
							if ( $gameContentElement && $gameContentElement.length > 0 ) {
								console.log( 'Found game content element, updating container' );
								var newContent = $gameContentElement.html();
								console.log( 'New content length:', newContent ? newContent.length : 0 );
								$gameContainer.html( newContent );
								
								// 背景画像を設定
								if ( baseBackground ) {
									$gameContainer.css( 'background-image', 'url("' + baseBackground + '")' );
									console.log( 'Background image set:', baseBackground );
								}
								
								// 必要なDOM要素を再取得
								$dialogueText = $( '#novel-dialogue-text' );
								$dialogueBox = $( '#novel-dialogue-box' );
								$speakerName = $( '#novel-speaker-name' );
								$dialogueContinue = $( '#novel-dialogue-continue' );
								$choicesContainer = $( '#novel-choices' );
								
								console.log( 'Game content loaded successfully' );
							} else {
								console.error( 'No valid game content found in response' );
							}
							
							resolve();
						} catch ( error ) {
							console.error( 'ゲームデータの解析に失敗しました:', error );
							reject( error );
						}
					},
					error: function( xhr, status, error ) {
						console.error( 'ゲームデータの読み込みに失敗しました:', error );
						reject( error );
					}
				} );
			} );
		}
		/**
		 * モーダルを開く
		 *
		 * @param {string} gameUrl （オプション）ゲームのURL
		 */
		function openModal( gameUrl ) {
			console.log( 'openModal called with URL:', gameUrl );
			console.log( 'Modal overlay exists:', $modalOverlay.length > 0 );
			console.log( 'isModalOpen:', isModalOpen );
			
			if ( isModalOpen ) {
				console.log( 'Modal already open, ignoring' );
				return;
			}
			
			// モーダル要素が存在しない場合はページ遷移
			if ( $modalOverlay.length === 0 ) {
				console.log( 'Modal overlay not found, redirecting to:', gameUrl );
				if ( gameUrl ) {
					window.location.href = gameUrl;
				}
				return;
			}
			
			isModalOpen = true;
			
			// ボディとHTMLのスクロールを無効化
			$( 'html, body' ).addClass( 'modal-open' ).css( 'overflow', 'hidden' );
			
			// オーバーレイの表示を確実にする（WordPress レイアウト制約を回避）
			$modalOverlay.removeClass( 'show' ).css( {
				'display': 'flex',
				'opacity': '0',
				'position': 'fixed',
				'top': '0',
				'left': '0',
				'width': '100vw',
				'height': '100vh',
				'z-index': '2147483647',
				// WordPress レイアウト制約を強制的に回避
				'max-width': 'none',
				'margin': '0',
				'margin-left': '0',
				'margin-right': '0',
				'padding': '0',
				'right': '0',
				'bottom': '0',
				'inset': '0',
				// 追加の配置制御
				'transform': 'none',
				'box-sizing': 'border-box'
			} ).animate( { opacity: 1 }, 300, function() {
				$modalOverlay.addClass( 'show' );
			} );
			
			console.log( 'Modal overlay display set to flex' );
			
			// ゲームデータの読み込み（URLが指定されている場合）
			if ( gameUrl ) {
				console.log( 'Loading game data from URL:', gameUrl );
				loadGameData( gameUrl ).then( function() {
					console.log( 'Game data loaded successfully' );
					// モーダル表示後にゲームを初期化
					setTimeout( function() {
						initializeGameContent();
					}, 100 );
				} ).catch( function( error ) {
					console.error( 'ゲームの読み込みに失敗しました:', error );
					closeModal();
				} );
			} else {
				console.log( 'No game URL provided, initializing existing content' );
				// モーダル表示後にゲームを初期化
				setTimeout( function() {
					initializeGameContent();
				}, 100 );
			}
			
			// ESCキーでモーダルを閉じる
			$( document ).on( 'keydown.modal', function( e ) {
				if ( e.which === 27 ) { // ESC key
					console.log( 'ESC key pressed, closing modal' );
					closeModal();
				}
			} );
		}

		/**
		 * モーダルを閉じる
		 */
		function closeModal() {
			console.log( 'closeModal called, isModalOpen:', isModalOpen );
			
			if ( ! isModalOpen ) {
				console.log( 'Modal already closed, ignoring' );
				return;
			}
			
			isModalOpen = false;
			
			// ボディとHTMLのスクロールを復元
			$( 'html, body' ).removeClass( 'modal-open' ).css( 'overflow', '' );
			
			// オーバーレイを非表示
			$modalOverlay.removeClass( 'show' ).animate( { opacity: 0 }, 300, function() {
				$modalOverlay.css( 'display', 'none' );
			} );
			
			console.log( 'Modal overlay hidden' );
			
			// イベントリスナーをクリーンアップ
			$( document ).off( 'keydown.modal' );
			$( document ).off( 'keydown.novel-dialogue' );
			$( window ).off( 'resize.game orientationchange.game' );
			
			// ゲーム状態をリセット
			resetGameState();
		}

		/**
		 * ゲーム状態をリセット
		 */
		function resetGameState() {
			currentDialogueIndex = 0;
			currentPageIndex = 0;
			currentDialoguePages = [];
			allDialoguePages = [];
			
			// 表示をリセット
			$dialogueText.text( '' );
			$speakerName.text( '' );
			$choicesContainer.empty().hide();
			$dialogueContinue.hide();
		}

		/**
		 * モーダルイベントハンドラーの設定
		 */
		function setupModalEvents() {
			console.log( 'Setting up modal events' );
			console.log( 'Start button exists:', $startButton.length > 0 );
			console.log( 'Close button exists:', $closeButton.length > 0 );
			
			// 開始ボタンクリックイベント
			$startButton.on( 'click', function( e ) {
				e.preventDefault();
				console.log( 'Start button clicked' );
				openModal();
			} );

			// 閉じるボタンクリックイベント
			$closeButton.on( 'click', function( e ) {
				e.preventDefault();
				console.log( 'Close button clicked' );
				closeModal();
			} );
			
			// モーダル内のクローズボタンイベントも設定（動的生成対応）
			$( document ).on( 'click', '.novel-game-close-btn', function( e ) {
				e.preventDefault();
				console.log( 'Dynamic close button clicked' );
				closeModal();
			} );

			// オーバーレイクリックでは閉じない（意図しない終了を防止）
			// コメントアウト：オーバーレイクリックによる終了を無効化
		}

		/**
		 * ショートコードからのアクセスかどうかを判定
		 */
		function isShortcodeContext() {
			return window.location.search.indexOf( 'shortcode=1' ) !== -1;
		}

		/**
		 * テキストを20文字×3行のページに分割する
		 * 改行文字を考慮して分割する
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
				let pageText = '';
				let currentLines = 0;
				let currentLineLength = 0;
				
				while ( currentIndex < text.length && currentLines < displaySettings.maxLines ) {
					const char = text.charAt( currentIndex );
					
					if ( char === '\n' ) {
						// 改行文字の場合
						pageText += char;
						currentLines++;
						currentLineLength = 0;
						currentIndex++;
					} else if ( currentLineLength >= displaySettings.maxCharsPerLine ) {
						// 行の文字数が上限に達した場合
						pageText += '\n';
						currentLines++;
						currentLineLength = 0;
						
						// 次の行に移れない場合はページを終了
						if ( currentLines >= displaySettings.maxLines ) {
							break;
						}
						
						// 現在の文字を追加
						pageText += char;
						currentLineLength++;
						currentIndex++;
					} else {
						// 通常の文字
						pageText += char;
						currentLineLength++;
						currentIndex++;
					}
				}
				
				pages.push( pageText );
			}
			
			return pages;
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
			
			// 話者がいない場合（ナレーター）は全キャラクターを通常状態に戻す
			if ( ! activeSpeaker || activeSpeaker === 'narrator' || activeSpeaker === '' ) {
				// 全キャラクターを通常状態で表示
				return;
			}
			
			// 話しているキャラクターを強調
			$( '.novel-character-' + activeSpeaker ).addClass( 'speaking' );
			
			// 他のキャラクターを薄く表示
			$( '.novel-character' ).not( '.novel-character-' + activeSpeaker ).addClass( 'not-speaking' );
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
					speakerName = charactersData.left_name || '左キャラクター';
					break;
				case 'center':
					speakerName = charactersData.center_name || '中央キャラクター';
					break;
				case 'right':
					speakerName = charactersData.right_name || '右キャラクター';
					break;
				case 'narrator':
				case '':
				default:
					speakerName = '';
					break;
			}
			
			$speakerName.text( speakerName );
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
				
				// currentDialogueIndexを現在のページの対応するdialogueIndexに更新
				currentDialogueIndex = currentPage.dialogueIndex;
				
				// 話者名を表示
				displaySpeakerName( currentPage.speaker );
				
				// キャラクターの状態を更新
				updateCharacterStates( currentPage.speaker );
				
				// 継続インジケーターの表示/非表示
				// 次のページがある場合は常に表示
				if ( currentPageIndex < allDialoguePages.length - 1 ) {
					$dialogueContinue.show();
				} else {
					// 最後のページでも継続マーカーを表示（選択肢がある場合もない場合も）
					$dialogueContinue.show();
				}
				
				// 新しいセリフの最初のページの場合は背景を変更
				if ( currentPage.isFirstPageOfDialogue && currentPage.background ) {
					changeBackground( currentPage.background ).then( function() {
						$dialogueText.text( currentPage.text );
					} );
				} else {
					$dialogueText.text( currentPage.text );
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
				
				// currentDialogueIndexを現在のページの対応するdialogueIndexに更新
				if ( allDialoguePages[ currentPageIndex ] ) {
					currentDialogueIndex = allDialoguePages[ currentPageIndex ].dialogueIndex;
				}
				
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
		 * 選択肢を表示、選択肢がない場合は「おわり」を表示
		 */
		function showChoices() {
			if ( choices.length === 0 ) {
				// 選択肢がない場合は「おわり」を表示
				showGameEnd();
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
					// ページ遷移ではなく、モーダル内でゲームを継続
					loadGameData( nextScene ).then( function() {
						// ゲーム状態をリセット
						resetGameState();
						
						// ゲームコンテンツを初期化
						initializeGameContent();
					} ).catch( function( error ) {
						console.error( '次のシーンの読み込みに失敗しました:', error );
						// フォールバック：ページ遷移
						window.location.href = nextScene;
					} );
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
						executeChoice( index );
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
		 * ゲーム終了時のエンディング画面を表示
		 */
		function showGameEnd() {
			$choicesContainer.empty();
			
			// カスタムエンディングメッセージを使用（デフォルトは「おわり」）
			var endingMessage = gameSettings && gameSettings.ending_message ? gameSettings.ending_message : 'おわり';
			
			// エンディングメッセージを表示（クリック可能にする）
			var $endMessage = $( '<div>' )
				.addClass( 'game-end-message clickable-ending' )
				.text( endingMessage )
				.attr( 'title', 'クリックでタイトル画面に戻る' );
			
			// エンディング画面クリックイベントを追加
			$endMessage.on( 'click', function( e ) {
				e.preventDefault();
				e.stopPropagation();
				returnToTitleScreen();
			} );
			
			// タッチデバイス対応
			if ( isTouch ) {
				$endMessage.on( 'touchend', function( e ) {
					e.preventDefault();
					e.stopPropagation();
					returnToTitleScreen();
				} );
			}
			
			$choicesContainer.append( $endMessage );
			
			// ナビゲーションボタンを追加（従来の機能も残す）
			var $navigationContainer = $( '<div>' ).addClass( 'game-navigation' );
			
			// ショートコード使用の検出
			var isShortcodeUsed = noveltool_is_shortcode_context();
			
			if ( isShortcodeUsed ) {
				// ショートコードの場合は「閉じる」ボタン
				var $closeButton = $( '<button>' )
					.addClass( 'game-nav-button close-button' )
					.text( '閉じる' )
					.on( 'click', function() {
						// ショートコードコンテナを非表示にする
						$gameContainer.closest( '.noveltool-shortcode-container' ).hide();
						// または親ウィンドウを閉じる
						if ( window.parent !== window ) {
							window.parent.postMessage( 'close-game', '*' );
						}
					});
				
				$navigationContainer.append( $closeButton );
			} else {
				// 通常の場合は「ゲーム一覧に戻る」ボタン
				var $gameListButton = $( '<button>' )
					.addClass( 'game-nav-button game-list-button' )
					.text( 'ゲーム一覧に戻る' )
					.on( 'click', function() {
						returnToGameList();
					});
				
				$navigationContainer.append( $gameListButton );
			}
			
			$choicesContainer.append( $navigationContainer );
			$choicesContainer.show();
			
			// 継続マーカーを非表示
			$dialogueContinue.hide();
			
			// キーボードイベントでエンディング画面の操作
			$( document ).on( 'keydown.novel-end', function( e ) {
				if ( e.which === 13 || e.which === 32 ) { // Enter or Space
					e.preventDefault();
					// メインアクションはタイトル画面に戻る
					returnToTitleScreen();
				} else if ( e.which === 27 ) { // ESC
					e.preventDefault();
					// ESCキーでは従来の動作（閉じる/ゲーム一覧）
					if ( isShortcodeUsed ) {
						$closeButton.trigger( 'click' );
					} else {
						$gameListButton.trigger( 'click' );
					}
				}
			} );
		}
		
		/**
		 * ショートコードコンテキストかどうかを判定
		 */
		function noveltool_is_shortcode_context() {
			// URLパラメーターでショートコードかどうかを判定
			var urlParams = new URLSearchParams( window.location.search );
			if ( urlParams.get( 'shortcode' ) === '1' ) {
				return true;
			}
			
			// 親要素にショートコードクラスがあるかチェック
			if ( $gameContainer.closest( '.noveltool-shortcode-container' ).length > 0 ) {
				return true;
			}
			
			// リファラーをチェック（同じドメインでない場合はショートコード使用の可能性）
			var referrer = document.referrer;
			var currentDomain = window.location.hostname;
			
			if ( referrer ) {
				var referrerDomain = new URL( referrer ).hostname;
				if ( referrerDomain !== currentDomain ) {
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * タイトル画面に戻る（モーダルを閉じてゲーム開始前の状態に戻す）
		 */
		function returnToTitleScreen() {
			console.log( 'Returning to title screen' );
			
			// モーダルを閉じる
			closeModal();
			
			// 必要に応じてページのタイトル部分にスクロール
			var $titleContainer = $( '#novel-game-title, .novel-game-title' );
			if ( $titleContainer.length > 0 ) {
				$( 'html, body' ).animate( {
					scrollTop: $titleContainer.offset().top - 100
				}, 500 );
			}
		}
		
		/**
		 * ゲーム一覧に戻る
		 */
		function returnToGameList() {
			// アーカイブページのURLを取得
			var archiveUrl = window.location.origin + window.location.pathname.replace( /\/[^\/]+\/?$/, '' );
			// novel_gameアーカイブページのURLを構築
			var gameArchiveUrl = archiveUrl.replace( /\/$/, '' ) + '/novel_game/';
			window.location.href = gameArchiveUrl;
		}

		/**
		 * ゲームコンテナのクリック/タッチイベント
		 */
		function setupGameInteraction() {
			var eventType = isTouch ? 'touchend' : 'click';
			
			$gameContainer.on( eventType, function( e ) {
				// 選択肢が表示されている場合はクリックを無視
				if ( $choicesContainer.is( ':visible' ) ) {
					// ただし、クリック可能なエンディングメッセージの場合は許可
					if ( $( e.target ).hasClass( 'clickable-ending' ) ) {
						return; // エンディングメッセージのクリックは処理される
					}
					return;
				}

				// 選択肢要素がクリックされた場合も無視
				if ( $( e.target ).hasClass( 'choice-item' ) || $( e.target ).closest( '.choice-list' ).length > 0 ) {
					return;
				}
				
				// クリック可能な「おわり」メッセージの場合は個別に処理されるため無視
				if ( $( e.target ).hasClass( 'clickable-ending' ) ) {
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
						// ただし、クリック可能なエンディングメッセージの場合は許可
						if ( $( e.target ).hasClass( 'clickable-ending' ) ) {
							return; // エンディングメッセージのタッチは処理される
						}
						return;
					}
					
					// 選択肢要素がタッチされた場合も無視
					if ( $( e.target ).hasClass( 'choice-item' ) || $( e.target ).closest( '.choice-list' ).length > 0 ) {
						return;
					}
					
					// クリック可能な「おわり」メッセージの場合は個別に処理されるため無視
					if ( $( e.target ).hasClass( 'clickable-ending' ) ) {
						return;
					}
					
					// タッチフィードバック
					$( this ).addClass( 'touch-active' );
				} ).on( 'touchcancel', function() {
					$( this ).removeClass( 'touch-active' );
				} );
			}
			
			// キーボードイベント処理（Enter、Space）
			$( document ).on( 'keydown.novel-dialogue', function( e ) {
				// 選択肢が表示されている場合はキーボード操作を無視
				if ( $choicesContainer.is( ':visible' ) ) {
					return;
				}
				
				// EnterキーまたはSpaceキーでセリフを進める
				if ( e.which === 13 || e.which === 32 ) { // Enter or Space
					e.preventDefault();
					showNextDialogue();
				}
			} );
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
						return page.text.includes( currentPageContent.text.substring( 0, 10 ) );
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
			// モーダルが開いている時のみ処理
			if ( isModalOpen ) {
				adjustForResponsive();
			}
		}

		/**
		 * ゲームコンテンツの初期化処理（モーダル内で実行）
		 */
		function initializeGameContent() {
			console.log( 'initializeGameContent called' );
			console.log( 'Game container exists:', $gameContainer.length > 0 );
			console.log( 'Dialogue data length:', dialogueData.length );
			console.log( 'Dialogues length:', dialogues.length );
			
			// ゲームコンテナが存在しない場合は処理を中断
			if ( $gameContainer.length === 0 ) {
				console.error( 'Game container not found' );
				return;
			}
			
			// DOM要素を再取得（動的読み込み後に必要）
			$dialogueText = $( '#novel-dialogue-text' );
			$dialogueBox = $( '#novel-dialogue-box' );
			$speakerName = $( '#novel-speaker-name' );
			$dialogueContinue = $( '#novel-dialogue-continue' );
			$choicesContainer = $( '#novel-choices' );
			
			console.log( 'Dialogue elements found:', {
				text: $dialogueText.length,
				box: $dialogueBox.length,
				speaker: $speakerName.length,
				continue: $dialogueContinue.length,
				choices: $choicesContainer.length
			} );

			// イベントリスナーの設定
			setupGameInteraction();

			// リサイズイベントの設定
			$( window ).on( 'resize.game orientationchange.game', handleResize );

			// 初期調整
			adjustForResponsive();

			// セリフデータがある場合は分割処理を実行
			if ( dialogues.length > 0 || dialogueData.length > 0 ) {
				console.log( 'Preparing dialogue pages' );
				prepareDialoguePages();
				displayCurrentPage();
			} else {
				// デバッグ用：セリフデータがない場合のメッセージ
				console.log( 'No dialogue data found' );
				
				// ゲームコンテナに何かコンテンツがあるかチェック
				var containerContent = $gameContainer.html();
				console.log( 'Game container content length:', containerContent ? containerContent.length : 0 );
				console.log( 'Game container content (first 200 chars):', containerContent ? containerContent.substring( 0, 200 ) : 'empty' );
			}
		}

		/**
		 * 初期化処理
		 */
		function initializeGame() {
			console.log( 'Initializing game...' );
			console.log( 'Modal overlay found:', $modalOverlay.length > 0 );
			console.log( 'Start button found:', $startButton.length > 0 );
			console.log( 'Close button found:', $closeButton.length > 0 );
			
			// モーダル要素が存在する場合のみモーダルイベントを設定
			if ( $modalOverlay.length > 0 ) {
				// モーダルイベントの設定
				setupModalEvents();
				
				// 初期状態はモーダルを非表示
				$modalOverlay.hide();
				
				console.log( 'Modal events set up successfully' );
			} else {
				console.log( 'No modal overlay found, skipping modal setup' );
			}
		}

		// ゲームの初期化
		initializeGame();
		
		// モーダル機能をグローバルに常に公開
		// archive-novel_game.phpからの呼び出しに対応するため
		window.novelGameModal = {
			open: function( gameUrl ) {
				console.log( 'novelGameModal.open called with URL:', gameUrl );
				console.log( 'Modal overlay exists:', $modalOverlay.length > 0 );
				
				// モーダル要素が存在しない場合はページ遷移
				if ( $modalOverlay.length === 0 ) {
					console.log( 'Modal overlay not found, redirecting to:', gameUrl );
					if ( gameUrl ) {
						window.location.href = gameUrl;
					}
					return;
				}
				openModal( gameUrl );
			},
			close: function() {
				console.log( 'novelGameModal.close called' );
				// モーダル要素が存在する場合のみ閉じる処理
				if ( $modalOverlay.length > 0 ) {
					closeModal();
				}
			},
			isAvailable: function() {
				return $modalOverlay.length > 0;
			}
		};
		
		// デバッグ情報を出力
		console.log( 'Novel Game Modal initialized. Modal overlay found:', $modalOverlay.length > 0 );
	} );

} )( jQuery );
