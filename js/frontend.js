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
		var $clearProgressButton = $( '#novel-game-clear-progress-btn' );
		var $closeButton = $( '#novel-game-close-btn' );
		
		// タイトル画面関連の変数
		var $titleScreen = $( '#novel-title-screen' );
		var $titleMain = $( '#novel-title-main' );
		var $titleSubtitle = $( '#novel-title-subtitle' );
		var $titleDescription = $( '#novel-title-description' );
		var $titleStartBtn = $( '#novel-title-start-new' );
		var $titleContinueBtn = $( '#novel-title-continue' );
		
		// モーダル表示フラグ
		var isModalOpen = false;
		var isTitleScreenVisible = false;

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
		
		// 自動保存機能の変数
		var currentGameTitle = '';
		var currentSceneUrl = '';
		var autoSaveEnabled = true;
		
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
		 * ゲーム進捗をlocalStorageに自動保存する
		 *
		 * @since 1.2.0
		 */
		function autoSaveGameProgress() {
			if ( ! autoSaveEnabled || ! currentGameTitle || ! currentSceneUrl ) {
				return;
			}
			
			try {
				var progressData = {
					gameTitle: currentGameTitle,
					sceneUrl: currentSceneUrl,
					currentPageIndex: currentPageIndex,
					currentDialogueIndex: currentDialogueIndex,
					timestamp: Date.now(),
					version: '1.2.0'
				};
				
				var storageKey = generateStorageKey( currentGameTitle );
				localStorage.setItem( storageKey, JSON.stringify( progressData ) );
				
				console.log( 'ゲーム進捗を自動保存しました:', progressData );
			} catch ( error ) {
				console.warn( 'ゲーム進捗の保存に失敗しました:', error );
			}
		}
		
		/**
		 * ユニークなストレージキーを生成する
		 * サイトのホスト名とパスを含めて他サイトとの競合を防ぐ
		 *
		 * @param {string} gameTitle ゲームタイトル
		 * @return {string} ストレージキー
		 * @since 1.2.0
		 */
		function generateStorageKey( gameTitle ) {
			if ( ! gameTitle ) {
				return '';
			}
			
			try {
				// サイトのホスト名とパスを取得
				var hostname = window.location.hostname || 'localhost';
				var pathname = window.location.pathname || '/';
				
				// ホスト名とパスからディレクトリ部分を抽出（ファイル名は除外）
				var pathDir = pathname.substring( 0, pathname.lastIndexOf( '/' ) + 1 );
				
				// サイト識別子を作成
				var siteId = hostname + pathDir;
				
				// ゲームタイトルをBase64エンコードして安全な文字列に変換
				var encodedTitle = btoa( unescape( encodeURIComponent( gameTitle ) ) ).replace( /[^a-zA-Z0-9]/g, '' );
				
				// サイトIDもBase64エンコードして安全な文字列に変換
				var encodedSiteId = btoa( unescape( encodeURIComponent( siteId ) ) ).replace( /[^a-zA-Z0-9]/g, '' );
				
				// ユニークなストレージキーを生成
				var storageKey = 'noveltool_progress_' + encodedSiteId + '_' + encodedTitle;
				
				return storageKey;
			} catch ( error ) {
				console.warn( 'ストレージキーの生成に失敗しました:', error );
				// フォールバック：従来の方式
				return 'noveltool_progress_' + btoa( gameTitle ).replace( /[^a-zA-Z0-9]/g, '' );
			}
		}
		
		/**
		 * 前の選択肢シーンの進捗を取得する
		 *
		 * @param {string} gameTitle ゲームタイトル
		 * @return {object|null} 保存されたデータまたはnull
		 */
		function getPreviousChoiceProgress( gameTitle ) {
			if ( ! gameTitle ) {
				return null;
			}
			
			try {
				var storageKey = generateStorageKey( gameTitle ) + '_prev_choice';
				var savedData = localStorage.getItem( storageKey );
				
				if ( savedData ) {
					var progressData = JSON.parse( savedData );
					
					// 1週間以上古いデータは削除
					var maxAge = 7 * 24 * 60 * 60 * 1000; // 1週間（ミリ秒）
					var currentTime = Date.now();
					var savedTime = progressData.timestamp || 0;
					
					if ( currentTime - savedTime > maxAge ) {
						console.log( '保存された前選択肢進捗が古いため削除します' );
						localStorage.removeItem( storageKey );
						return null;
					}
					
					console.log( '保存された前選択肢進捗を取得しました:', progressData );
					return progressData;
				}
			} catch ( error ) {
				console.warn( '前選択肢進捗の取得に失敗しました:', error );
			}
			
			return null;
		}
		
		/**
		 * 保存されたゲーム進捗を取得する
		 *
		 * @param {string} gameTitle ゲームタイトル
		 * @return {object|null} 保存されたデータまたはnull
		 * @since 1.2.0
		 */
		function getSavedGameProgress( gameTitle ) {
			if ( ! gameTitle ) {
				return null;
			}
			
			try {
				var storageKey = generateStorageKey( gameTitle );
				var savedData = localStorage.getItem( storageKey );
				
				if ( savedData ) {
					var progressData = JSON.parse( savedData );
					
					// データの有効性をチェック（30日以内のデータのみ有効）
					var currentTime = Date.now();
					var savedTime = progressData.timestamp || 0;
					var maxAge = 30 * 24 * 60 * 60 * 1000; // 30日（ミリ秒）
					
					if ( currentTime - savedTime > maxAge ) {
						console.log( '保存されたゲーム進捗が古いため削除します' );
						localStorage.removeItem( storageKey );
						return null;
					}
					
					console.log( '保存されたゲーム進捗を取得しました:', progressData );
					return progressData;
				}
			} catch ( error ) {
				console.warn( 'ゲーム進捗の取得に失敗しました:', error );
			}
			
			return null;
		}
		
		/**
		 * 特定のゲームの進捗を削除する
		 *
		 * @param {string} gameTitle ゲームタイトル
		 * @since 1.2.0
		 */
		function clearGameProgress( gameTitle ) {
			if ( ! gameTitle ) {
				return;
			}
			
			try {
				var storageKey = generateStorageKey( gameTitle );
				var prevChoiceKey = storageKey + '_prev_choice';
				
				localStorage.removeItem( storageKey );
				localStorage.removeItem( prevChoiceKey );
				console.log( 'ゲーム進捗と前選択肢進捗をクリアしました:', gameTitle );
			} catch ( error ) {
				console.warn( 'ゲーム進捗のクリアに失敗しました:', error );
			}
		}
		
		/**
		 * 現在のゲーム情報を設定する
		 *
		 * @param {string} gameTitle ゲームタイトル
		 * @param {string} sceneUrl 現在のシーンURL
		 * @since 1.2.0
		 */
		function setCurrentGameInfo( gameTitle, sceneUrl ) {
			currentGameTitle = gameTitle || '';
			currentSceneUrl = sceneUrl || window.location.href;
			
			console.log( '現在のゲーム情報を設定:', { gameTitle: currentGameTitle, sceneUrl: currentSceneUrl } );
		}
		
		/**
		 * ページからゲームタイトルを抽出する
		 *
		 * @param {string} gameUrl ゲームページのURL（省略時は現在のページ）
		 * @return {string} ゲームタイトル
		 * @since 1.2.0
		 */
		function extractGameTitleFromPage( gameUrl ) {
			var gameTitle = '';
			
			try {
				// ページタイトルからゲームタイトルを取得
				var titleElement = $( '#novel-game-title' );
				if ( titleElement.length > 0 ) {
					gameTitle = titleElement.text().trim();
				}
				
				// メタデータからゲームタイトルを取得
				if ( ! gameTitle ) {
					var metaGameTitle = $( 'meta[name="novel-game-title"]' ).attr( 'content' );
					if ( metaGameTitle ) {
						gameTitle = metaGameTitle.trim();
					}
				}
				
				// DOM内のゲームデータから取得
				if ( ! gameTitle ) {
					var gameDataElements = document.querySelectorAll( '[data-game-title]' );
					if ( gameDataElements.length > 0 ) {
						gameTitle = gameDataElements[0].getAttribute( 'data-game-title' );
					}
				}
				
				// ページタイトルから推測
				if ( ! gameTitle ) {
					var pageTitle = document.title;
					// WordPress のページタイトル形式から抽出を試みる
					var titleParts = pageTitle.split( ' | ' );
					if ( titleParts.length > 0 ) {
						gameTitle = titleParts[0].trim();
					}
				}
				
				console.log( 'ページからゲームタイトルを抽出:', gameTitle );
			} catch ( error ) {
				console.warn( 'ゲームタイトルの抽出に失敗しました:', error );
			}
			
			return gameTitle;
		}

		/**
		 * タイトル画面を表示する
		 *
		 * @param {object} gameData ゲームデータ（title, description, subtitle, url等）
		 */
		function showTitleScreen( gameData ) {
			console.log( 'showTitleScreen called with:', gameData );
			
			if ( isTitleScreenVisible ) {
				console.log( 'Title screen already visible, ignoring' );
				return;
			}
			
			isTitleScreenVisible = true;
			
			// タイトル画面の内容を設定
			$titleMain.text( gameData.title || '' );
			$titleSubtitle.text( gameData.subtitle || '' ).toggle( !!gameData.subtitle );
			$titleDescription.text( gameData.description || '' ).toggle( !!gameData.description );
			
			// 背景画像を設定
			setTitleScreenBackground( gameData );
			
			// 保存された進捗をチェックして「続きから始める」ボタンの表示を制御
			if ( gameData.title ) {
				var savedProgress = getSavedGameProgress( gameData.title );
				var previousChoiceProgress = getPreviousChoiceProgress( gameData.title );
				
				if ( savedProgress || previousChoiceProgress ) {
					$titleContinueBtn.show();
					if ( savedProgress ) {
						console.log( '保存された進捗が見つかりました。「続きから始める」ボタンを表示します。' );
					} else {
						console.log( '前の選択肢進捗が見つかりました。「続きから始める」ボタンを表示します。' );
					}
				} else {
					$titleContinueBtn.hide();
					console.log( '保存された進捗がありません。「続きから始める」ボタンを非表示にします。' );
				}
			} else {
				$titleContinueBtn.hide();
			}
			
			// タイトル画面を表示
			$titleScreen.css( 'display', 'flex' ).hide().fadeIn( 300 );
			
			// ゲームデータを一時保存（ボタン押下時に使用）
			window.currentGameSelectionData = gameData;
		}

		/**
		 * タイトル画面の背景画像を設定する
		 *
		 * @param {object} gameData ゲームデータ
		 */
		function setTitleScreenBackground( gameData ) {
			if ( ! gameData || ! gameData.url ) {
				console.log( 'No game data or URL provided for background image' );
				return;
			}
			
			// 背景画像を取得する優先順序
			var backgroundImage = '';
			
			// 1. ゲーム固有のタイトル用画像（最優先）
			if ( gameData.image && gameData.image.trim() ) {
				backgroundImage = gameData.image;
				console.log( 'Using game-specific title image:', backgroundImage );
			}
			// 2. 現在読み込まれたベース背景画像
			else if ( baseBackground ) {
				backgroundImage = baseBackground;
				console.log( 'Using base background from loaded data:', backgroundImage );
			}
			// 3. 現在の背景画像
			else if ( currentBackground ) {
				backgroundImage = currentBackground;
				console.log( 'Using current background:', backgroundImage );
			}
			// 4. セリフデータの最初の背景画像
			else if ( dialogueData && dialogueData.length > 0 && dialogueData[0].background_image ) {
				backgroundImage = dialogueData[0].background_image;
				console.log( 'Using background from first dialogue:', backgroundImage );
			}
			// 5. セリフデータの最初のbackgroundプロパティ
			else if ( dialogueData && dialogueData.length > 0 && dialogueData[0].background ) {
				backgroundImage = dialogueData[0].background;
				console.log( 'Using background from first dialogue (legacy):', backgroundImage );
			}
			
			// 背景画像を設定
			if ( backgroundImage ) {
				console.log( 'Setting title screen background image:', backgroundImage );
				$titleScreen.css( {
					'background-image': 'linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url(' + backgroundImage + ')',
					'background-size': 'cover',
					'background-position': 'center',
					'background-repeat': 'no-repeat'
				} );
			} else {
				console.log( 'No background image found, using default gradient' );
				$titleScreen.css( {
					'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
					'background-image': 'none'
				} );
			}
		}

		/**
		 * タイトル画面を非表示にする
		 */
		function hideTitleScreen() {
			console.log( 'hideTitleScreen called' );
			
			if ( ! isTitleScreenVisible ) {
				console.log( 'Title screen already hidden, ignoring' );
				return;
			}
			
			isTitleScreenVisible = false;
			
			// タイトル画面を非表示
			$titleScreen.fadeOut( 300, function() {
				$titleScreen.css( 'display', 'none' );
				// 背景画像をリセット
				$titleScreen.css( {
					'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
					'background-image': 'none'
				} );
			} );
			
			// 一時保存されたゲームデータをクリア
			window.currentGameSelectionData = null;
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
							
							// ゲームタイトルを抽出
							var extractedGameTitle = '';
							var titleElement = $response.find( '#novel-game-title' );
							if ( titleElement.length > 0 ) {
								extractedGameTitle = titleElement.text().trim();
							}
							
							// 現在のゲーム情報を設定
							if ( extractedGameTitle ) {
								setCurrentGameInfo( extractedGameTitle, gameUrl );
							}
							
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
		 * モーダル要素を動的に生成してbodyに追加する
		 */
		function createModalElements() {
			console.log( 'Creating modal elements dynamically' );
			
			// モーダルオーバーレイの作成
			var $newModalOverlay = $( '<div>' )
				.attr( 'id', 'novel-game-modal-overlay' )
				.addClass( 'novel-game-modal-overlay' )
				.css( {
					'display': 'none',
					'position': 'fixed',
					'top': '0',
					'left': '0',
					'width': '100vw',
					'height': '100vh',
					'background': 'rgba(0, 0, 0, 0.9)',
					'z-index': '2147483647',
					'flex-direction': 'column',
					'justify-content': 'center',
					'align-items': 'center',
					'max-width': 'none',
					'margin': '0',
					'padding': '0',
					'box-sizing': 'border-box'
				} );
			
			// タイトル画面の作成
			var $newTitleScreen = $( '<div>' )
				.attr( 'id', 'novel-title-screen' )
				.addClass( 'novel-title-screen' )
				.css( {
					'position': 'absolute',
					'top': '0',
					'left': '0',
					'width': '100%',
					'height': '100%',
					'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
					'color': 'white',
					'display': 'flex',
					'align-items': 'center',
					'justify-content': 'center',
					'z-index': '10'
				} );
			
			// タイトル画面コンテンツの作成
			var $titleContent = $( '<div>' )
				.addClass( 'novel-title-content' )
				.css( {
					'text-align': 'center',
					'max-width': '600px',
					'padding': '40px'
				} );
			
			var $titleMain = $( '<h1>' )
				.attr( 'id', 'novel-title-main' )
				.addClass( 'novel-title-main' )
				.css( {
					'font-size': 'clamp(28px, 6vw, 48px)',
					'margin': '0 0 20px 0',
					'font-weight': 'bold',
					'text-shadow': '2px 2px 4px rgba(0,0,0,0.5)'
				} );
			
			var $titleSubtitle = $( '<h2>' )
				.attr( 'id', 'novel-title-subtitle' )
				.addClass( 'novel-title-subtitle' )
				.css( {
					'font-size': 'clamp(16px, 3vw, 24px)',
					'margin': '0 0 15px 0',
					'font-weight': 'normal',
					'opacity': '0.9'
				} );
			
			var $titleDescription = $( '<p>' )
				.attr( 'id', 'novel-title-description' )
				.addClass( 'novel-title-description' )
				.css( {
					'font-size': 'clamp(14px, 2.5vw, 18px)',
					'margin': '0 0 30px 0',
					'line-height': '1.6',
					'opacity': '0.8'
				} );
			
			var $titleButtons = $( '<div>' )
				.addClass( 'novel-title-buttons' )
				.css( {
					'display': 'flex',
					'flex-direction': 'column',
					'gap': '15px',
					'align-items': 'center'
				} );
			
			var $titleStartBtn = $( '<button>' )
				.attr( 'id', 'novel-title-start-new' )
				.addClass( 'novel-title-button novel-title-start' )
				.text( '最初から開始' )
				.css( {
					'background': '#ff6b6b',
					'color': 'white',
					'border': 'none',
					'padding': '15px 40px',
					'border-radius': '25px',
					'font-size': 'clamp(16px, 3vw, 20px)',
					'font-weight': 'bold',
					'cursor': 'pointer',
					'transition': 'all 0.3s ease',
					'min-width': '200px'
				} );
			
			var $titleContinueBtn = $( '<button>' )
				.attr( 'id', 'novel-title-continue' )
				.addClass( 'novel-title-button novel-title-continue' )
				.text( '続きから始める' )
				.css( {
					'background': '#4CAF50',
					'color': 'white',
					'border': 'none',
					'padding': '15px 40px',
					'border-radius': '25px',
					'font-size': 'clamp(16px, 3vw, 20px)',
					'font-weight': 'bold',
					'cursor': 'pointer',
					'transition': 'all 0.3s ease',
					'min-width': '200px',
					'display': 'none'
				} );
			
			// ゲームコンテナの作成
			var $gameContainer = $( '<div>' )
				.attr( 'id', 'novel-game-container' )
				.addClass( 'novel-game-container' )
				.css( {
					'width': '100%',
					'height': '100%',
					'position': 'relative',
					'background-size': 'cover',
					'background-position': 'center',
					'background-repeat': 'no-repeat',
					'overflow': 'hidden'
				} )
				.html( '<div style="text-align: center; color: white; padding: 50px;">ゲーム内容は動的に読み込まれます</div>' );
			
			// DOM構造を組み立て
			$titleButtons.append( $titleStartBtn, $titleContinueBtn );
			$titleContent.append( $titleMain, $titleSubtitle, $titleDescription, $titleButtons );
			$newTitleScreen.append( $titleContent );
			$newModalOverlay.append( $newTitleScreen, $gameContainer );
			
			// bodyに追加
			$( 'body' ).append( $newModalOverlay );
			
			console.log( 'Modal elements created and added to body' );
		}

		/**
		 * モーダルを開く（タイトル画面表示モードまたは直接ゲーム開始モード）
		 *
		 * @param {string|object} gameUrlOrData ゲームのURLまたはゲームデータオブジェクト
		 */
		function openModal( gameUrlOrData ) {
			console.log( 'openModal called with:', gameUrlOrData );
			console.log( 'Modal overlay exists:', $modalOverlay.length > 0 );
			console.log( 'isModalOpen:', isModalOpen );
			
			if ( isModalOpen ) {
				console.log( 'Modal already open, ignoring' );
				return;
			}
			
			// モーダル要素が存在しない場合は新規生成
			if ( $modalOverlay.length === 0 ) {
				console.log( 'Modal overlay not found, creating new modal elements' );
				createModalElements();
				// 新規生成後にjQuery要素を再取得
				$modalOverlay = $( '#novel-game-modal-overlay' );
				$titleScreen = $( '#novel-title-screen' );
				$titleMain = $( '#novel-title-main' );
				$titleSubtitle = $( '#novel-title-subtitle' );
				$titleDescription = $( '#novel-title-description' );
				$titleStartBtn = $( '#novel-title-start-new' );
				$titleContinueBtn = $( '#novel-title-continue' );
				
				// ゲームコンテナも再取得
				$gameContainer = $( '#novel-game-container' );
				
				console.log( 'Modal elements recreated, overlay length:', $modalOverlay.length );
				
				// モーダル要素の生成に失敗した場合のみページ遷移（通常は発生しないはず）
				if ( $modalOverlay.length === 0 ) {
					console.error( 'Critical error: Failed to create modal elements after createModalElements()' );
					// 最後の手段としてページ遷移（ただし、createModalElements が正常に動作すれば実行されない）
					if ( typeof gameUrlOrData === 'string' ) {
						window.location.href = gameUrlOrData;
					} else if ( gameUrlOrData && gameUrlOrData.url ) {
						window.location.href = gameUrlOrData.url;
					}
					return;
				}
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
			
			// ゲームデータがオブジェクトとして渡された場合（ゲームリストから）
			if ( typeof gameUrlOrData === 'object' && gameUrlOrData !== null ) {
				console.log( 'Opening with game data (from game list), showing title screen' );
				
				// ゲームデータを読み込んでからタイトル画面を表示
				loadGameData( gameUrlOrData.url ).then( function() {
					console.log( 'Game data loaded successfully, showing title screen' );
					
					// タイトル画面を表示
					setTimeout( function() {
						showTitleScreen( gameUrlOrData );
					}, 300 );
				} ).catch( function( error ) {
					console.error( 'ゲームの読み込みに失敗しました:', error );
					closeModal();
				} );
			}
			// URLが文字列として指定された場合（URLから直接または従来の方法）
			else if ( typeof gameUrlOrData === 'string' ) {
				console.log( 'Loading game data from URL:', gameUrlOrData );
				loadGameData( gameUrlOrData ).then( function() {
					console.log( 'Game data loaded successfully' );
					
					// 保存された進捗をチェック
					checkAndOfferResumeOption().then( function() {
						// モーダル表示後にゲームを初期化
						setTimeout( function() {
							initializeGameContent();
						}, 100 );
					} );
				} ).catch( function( error ) {
					console.error( 'ゲームの読み込みに失敗しました:', error );
					closeModal();
				} );
			} else {
				console.log( 'No game URL provided, initializing existing content' );
				
				// 現在のゲーム情報を設定
				var gameTitle = extractGameTitleFromPage();
				if ( gameTitle ) {
					setCurrentGameInfo( gameTitle, window.location.href );
				}
				
				// 保存された進捗をチェック
				checkAndOfferResumeOption().then( function() {
					// モーダル表示後にゲームを初期化
					setTimeout( function() {
						initializeGameContent();
					}, 100 );
				} );
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
		 * 保存された進捗をチェックして再開オプションを表示する
		 *
		 * @return {Promise} 進捗チェック完了のPromise
		 * @since 1.2.0
		 */
		function checkAndOfferResumeOption() {
			return new Promise( function( resolve ) {
				if ( ! currentGameTitle ) {
					console.log( 'ゲームタイトルが設定されていないため、進捗チェックをスキップします' );
					resolve();
					return;
				}
				
				var savedProgress = getSavedGameProgress( currentGameTitle );
				if ( ! savedProgress ) {
					console.log( '保存された進捗が見つかりません' );
					resolve();
					return;
				}
				
				console.log( '保存された進捗が見つかりました:', savedProgress );
				
				// 進捗確認ダイアログを表示
				showResumeDialog( savedProgress ).then( function( shouldResume ) {
					if ( shouldResume ) {
						console.log( '進捗から再開します' );
						resumeFromSavedProgress( savedProgress ).then( function() {
							resolve();
						} ).catch( function() {
							// 復元に失敗した場合は最初から開始
							console.log( '進捗復元に失敗したため、最初から開始します' );
							resolve();
						} );
					} else {
						console.log( '最初から開始します' );
						// 保存された進捗をクリア
						clearGameProgress( currentGameTitle );
						resolve();
					}
				} );
			} );
		}
		
		/**
		 * 再開確認ダイアログを表示する
		 *
		 * @param {object} savedProgress 保存された進捗データ
		 * @return {Promise} ユーザーの選択結果のPromise（true: 再開, false: 最初から）
		 * @since 1.2.0
		 */
		function showResumeDialog( savedProgress ) {
			return new Promise( function( resolve ) {
				// ダイアログHTML作成
				var $dialogOverlay = $( '<div>' ).addClass( 'novel-resume-dialog-overlay' ).css( {
					'position': 'fixed',
					'top': '0',
					'left': '0',
					'width': '100vw',
					'height': '100vh',
					'background': 'rgba(0, 0, 0, 0.8)',
					'z-index': '2147483648',
					'display': 'flex',
					'align-items': 'center',
					'justify-content': 'center'
				} );
				
				var $dialog = $( '<div>' ).addClass( 'novel-resume-dialog' ).css( {
					'background': 'white',
					'border-radius': '10px',
					'padding': '30px',
					'max-width': '400px',
					'width': '90%',
					'text-align': 'center',
					'box-shadow': '0 10px 30px rgba(0, 0, 0, 0.3)'
				} );
				
				var $title = $( '<h3>' ).text( 'ゲーム再開' ).css( {
					'margin': '0 0 20px 0',
					'color': '#333'
				} );
				
				var savedDate = new Date( savedProgress.timestamp );
				var formattedDate = savedDate.toLocaleString( 'ja-JP' );
				
				var $message = $( '<p>' ).html( 
					'前回のプレイ途中のデータが見つかりました。<br>' +
					'<small>保存日時: ' + formattedDate + '</small><br><br>' +
					'続きから再開しますか？'
				).css( {
					'margin': '0 0 25px 0',
					'color': '#666',
					'line-height': '1.6'
				} );
				
				var $buttonContainer = $( '<div>' ).css( {
					'display': 'flex',
					'gap': '10px',
					'justify-content': 'center'
				} );
				
				var $resumeButton = $( '<button>' ).text( '続きから再開' ).css( {
					'background': '#4CAF50',
					'color': 'white',
					'border': 'none',
					'padding': '12px 20px',
					'border-radius': '5px',
					'cursor': 'pointer',
					'font-size': '14px',
					'min-width': '120px'
				} );
				
				var $restartButton = $( '<button>' ).text( '最初から開始' ).css( {
					'background': '#f44336',
					'color': 'white',
					'border': 'none',
					'padding': '12px 20px',
					'border-radius': '5px',
					'cursor': 'pointer',
					'font-size': '14px',
					'min-width': '120px'
				} );
				
				// ホバー効果
				$resumeButton.hover( function() {
					$( this ).css( 'background', '#45a049' );
				}, function() {
					$( this ).css( 'background', '#4CAF50' );
				} );
				
				$restartButton.hover( function() {
					$( this ).css( 'background', '#da190b' );
				}, function() {
					$( this ).css( 'background', '#f44336' );
				} );
				
				// イベント設定
				$resumeButton.on( 'click', function() {
					$dialogOverlay.remove();
					resolve( true );
				} );
				
				$restartButton.on( 'click', function() {
					$dialogOverlay.remove();
					resolve( false );
				} );
				
				// ESCキーで閉じる（最初から開始として扱う）
				$( document ).on( 'keydown.resume-dialog', function( e ) {
					if ( e.which === 27 ) {
						$( document ).off( 'keydown.resume-dialog' );
						$dialogOverlay.remove();
						resolve( false );
					}
				} );
				
				// ダイアログ構築
				$buttonContainer.append( $resumeButton, $restartButton );
				$dialog.append( $title, $message, $buttonContainer );
				$dialogOverlay.append( $dialog );
				
				// 画面に追加
				$( 'body' ).append( $dialogOverlay );
				
				// フェードイン
				$dialogOverlay.hide().fadeIn( 300 );
			} );
		}
		
		/**
		 * 保存された進捗から再開する
		 *
		 * @param {object} savedProgress 保存された進捗データ
		 * @return {Promise} 復元完了のPromise
		 * @since 1.2.0
		 */
		function resumeFromSavedProgress( savedProgress ) {
			return new Promise( function( resolve, reject ) {
				console.log( '保存された進捗から復元中:', savedProgress );
				
				try {
					// 異なるシーンから再開する場合は、そのシーンを読み込む
					if ( savedProgress.sceneUrl && savedProgress.sceneUrl !== currentSceneUrl ) {
						console.log( '別のシーンから再開:', savedProgress.sceneUrl );
						
						loadGameData( savedProgress.sceneUrl ).then( function() {
							// データ読み込み後に進捗を復元
							restoreProgressState( savedProgress );
							// ゲームコンテンツを初期化
							setTimeout( function() {
								initializeGameContent();
								resolve();
							}, 100 );
						} ).catch( function( error ) {
							console.error( 'シーンデータの読み込みに失敗:', error );
							reject( error );
						} );
					} else {
						// 同じシーンの場合は現在のデータで進捗を復元
						restoreProgressState( savedProgress );
						// ゲームコンテンツを初期化
						setTimeout( function() {
							initializeGameContent();
							resolve();
						}, 100 );
					}
				} catch ( error ) {
					console.error( '進捗復元中にエラーが発生:', error );
					reject( error );
				}
			} );
		}
		
		/**
		 * 進捗状態を復元する
		 *
		 * @param {object} savedProgress 保存された進捗データ
		 * @since 1.2.0
		 */
		function restoreProgressState( savedProgress ) {
			console.log( '進捗状態を復元:', savedProgress );
			
			// 進捗インデックスを復元
			if ( typeof savedProgress.currentPageIndex === 'number' && savedProgress.currentPageIndex >= 0 ) {
				currentPageIndex = savedProgress.currentPageIndex;
			}
			
			if ( typeof savedProgress.currentDialogueIndex === 'number' && savedProgress.currentDialogueIndex >= 0 ) {
				currentDialogueIndex = savedProgress.currentDialogueIndex;
				dialogueIndex = savedProgress.currentDialogueIndex; // 後方互換性
			}
			
			console.log( '復元された進捗位置:', {
				currentPageIndex: currentPageIndex,
				currentDialogueIndex: currentDialogueIndex
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
				
				// タイトル画面復帰用にモーダルをDOM から削除
				// （returnToTitleScreen での再生成を可能にするため）
				if ( $modalOverlay.length > 0 ) {
					console.log( 'Removing modal overlay from DOM for regeneration' );
					$modalOverlay.remove();
					
					// jQuery要素をリセット
					$modalOverlay = $( '#novel-game-modal-overlay' );
					$titleScreen = $( '#novel-title-screen' );
					$titleMain = $( '#novel-title-main' );
					$titleSubtitle = $( '#novel-title-subtitle' );
					$titleDescription = $( '#novel-title-description' );
					$titleStartBtn = $( '#novel-title-start-new' );
					$titleContinueBtn = $( '#novel-title-continue' );
				}
			} );
			
			console.log( 'Modal overlay hidden and will be removed' );
			
			// イベントリスナーをクリーンアップ
			$( document ).off( 'keydown.modal' );
			$( document ).off( 'keydown.novel-dialogue' );
			$( window ).off( 'resize.game orientationchange.game' );
			
			// ゲーム状態をリセット
			resetGameState();
		}

		/**
		 * ゲーム状態をリセット（表示状態のみ、データはクリアしない）
		 */
		function resetGameState() {
			console.log( 'Resetting game display state...' );
			
			// セリフ関連の表示状態をリセット
			currentDialogueIndex = 0;
			currentPageIndex = 0;
			currentDialoguePages = [];
			allDialoguePages = [];
			
			// セリフ表示インデックスをリセット
			dialogueIndex = 0;
			
			// 背景表示の状態をリセット（データは保持）
			if ( baseBackground ) {
				currentBackground = baseBackground;
			}
			
			// DOM要素の表示をリセット
			if ( $dialogueText && $dialogueText.length > 0 ) {
				$dialogueText.text( '' );
			}
			if ( $speakerName && $speakerName.length > 0 ) {
				$speakerName.text( '' );
			}
			if ( $choicesContainer && $choicesContainer.length > 0 ) {
				$choicesContainer.empty().hide();
			}
			if ( $dialogueContinue && $dialogueContinue.length > 0 ) {
				$dialogueContinue.hide();
			}
			if ( $dialogueBox && $dialogueBox.length > 0 ) {
				$dialogueBox.show();
			}
			
			// キャラクターの状態をリセット
			$( '.novel-character' ).removeClass( 'speaking not-speaking' );
			
			// イベントハンドラーをクリーンアップ（選択肢以外）
			$( document ).off( 'keydown.novel-dialogue keydown.novel-end' );
			
			console.log( 'Game display state reset completed' );
		}

		/**
		 * モーダルイベントハンドラーの設定
		 */
		function setupModalEvents() {
			console.log( 'Setting up modal events' );
			console.log( 'Start button exists:', $startButton.length > 0 );
			console.log( 'Clear progress button exists:', $clearProgressButton.length > 0 );
			console.log( 'Close button exists:', $closeButton.length > 0 );
			console.log( 'Title screen exists:', $titleScreen.length > 0 );
			
			// 開始ボタンクリックイベント
			$startButton.on( 'click', function( e ) {
				e.preventDefault();
				console.log( 'Start button clicked' );
				openModal();
			} );
			
			// 進捗クリアボタンクリックイベント
			$clearProgressButton.on( 'click', function( e ) {
				e.preventDefault();
				console.log( 'Clear progress button clicked' );
				
				var gameTitle = extractGameTitleFromPage();
				if ( gameTitle ) {
					if ( confirm( '保存されたゲーム進捗をクリアしますか？\nこの操作は取り消せません。' ) ) {
						clearGameProgress( gameTitle );
						updateClearProgressButtonVisibility( gameTitle );
						alert( 'ゲーム進捗をクリアしました。' );
					}
				}
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
			
			// タイトル画面：最初から開始ボタン（委譲イベント）
			$( document ).on( 'click', '#novel-title-start-new', function( e ) {
				e.preventDefault();
				console.log( 'Title screen start button clicked' );
				
				if ( window.currentGameSelectionData && window.currentGameSelectionData.url ) {
					var gameTitle = window.currentGameSelectionData.title;
					
					// 保存された進捗があれば削除（最初から開始のため）
					if ( gameTitle ) {
						clearGameProgress( gameTitle );
						console.log( '「最初から開始」のため、保存済み進捗を削除しました' );
					}
					
					// タイトル画面を非表示にしてゲーム開始
					hideTitleScreen();
					setTimeout( function() {
						// タイトル画面経由での開始のため、進捗チェックをスキップして直接初期化
						initializeGameContent();
					}, 300 );
				}
			} );
			
			// タイトル画面：続きから始めるボタン（委譲イベント）
			$( document ).on( 'click', '#novel-title-continue', function( e ) {
				e.preventDefault();
				console.log( 'Title screen continue button clicked' );
				
				if ( window.currentGameSelectionData && window.currentGameSelectionData.url ) {
					var gameTitle = window.currentGameSelectionData.title;
					var savedProgress = getSavedGameProgress( gameTitle );
					
					if ( savedProgress ) {
						console.log( '保存された進捗から再開します' );
						// タイトル画面を非表示にして保存地点から再開
						hideTitleScreen();
						setTimeout( function() {
							// 保存された進捗データから状態を復元（タイトル画面経由のため進捗チェックはスキップ）
							resumeFromSavedProgress( savedProgress ).catch( function( error ) {
								console.error( '進捗復元に失敗しました:', error );
								// フォールバック：最初から開始
								initializeGameContent();
							} );
						}, 300 );
					} else {
						// 通常の進捗がない場合、前の選択肢シーンをチェック
						var previousChoiceProgress = getPreviousChoiceProgress( gameTitle );
						if ( previousChoiceProgress ) {
							console.log( '前の選択肢シーンから再開します' );
							hideTitleScreen();
							setTimeout( function() {
								resumeFromSavedProgress( previousChoiceProgress ).catch( function( error ) {
									console.error( '前選択肢シーンの復元に失敗しました:', error );
									// フォールバック：最初から開始
									initializeGameContent();
								} );
							}, 300 );
						} else {
							console.log( '保存された進捗が見つかりません。最初から開始します。' );
							// 進捗がない場合は最初から開始（タイトル画面経由のため進捗チェックはスキップ）
							hideTitleScreen();
							setTimeout( function() {
								initializeGameContent();
							}, 300 );
						}
					}
				}
			} );

			// ゲームカード・プレイボタンクリックイベント（委譲イベント）
			$( document ).on( 'click', '.noveltool-game-item, .noveltool-play-button', function( e ) {
				e.preventDefault();
				e.stopPropagation();
				
				var $target = $( this );
				var gameUrl = $target.attr( 'data-game-url' ) || $target.closest( '[data-game-url]' ).attr( 'data-game-url' );
				var gameTitle = $target.attr( 'data-game-title' ) || $target.closest( '[data-game-title]' ).attr( 'data-game-title' );
				var gameDescription = $target.attr( 'data-game-description' ) || $target.closest( '[data-game-description]' ).attr( 'data-game-description' ) || '';
				var gameSubtitle = $target.attr( 'data-game-subtitle' ) || $target.closest( '[data-game-subtitle]' ).attr( 'data-game-subtitle' ) || '';
				var gameImage = $target.attr( 'data-game-image' ) || $target.closest( '[data-game-image]' ).attr( 'data-game-image' ) || '';
				
				console.log( 'Game item clicked:', { gameUrl: gameUrl, gameTitle: gameTitle, gameDescription: gameDescription, gameImage: gameImage } );
				
				if ( gameUrl && gameTitle ) {
					// ゲームデータオブジェクトを作成し、タイトル画面表示モードでモーダルを開く
					var gameData = {
						url: gameUrl,
						title: gameTitle,
						description: gameDescription,
						subtitle: gameSubtitle,
						image: gameImage
					};
					
					openModal( gameData );
				} else {
					console.error( 'ゲームデータが不足しています:', { gameUrl: gameUrl, gameTitle: gameTitle } );
				}
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
				displayCurrentPage();
				
				// 進捗を自動保存
				autoSaveGameProgress();
			} else {
				// すべてのセリフが終わったら選択肢を表示
				$dialogueBox.hide();
				
				// すべてのキャラクターを通常状態に戻す
				updateCharacterStates( '' );
				
				// 最終セリフ完了時の進捗を保存
				autoSaveGameProgress();
				
				showChoices();
			}
		}

		/**
		 * 現在のシーンがエンディングかどうかを判定
		 *
		 * @return {boolean} エンディングの場合true、そうでなければfalse
		 * @since 1.2.0
		 */
		function checkIfCurrentSceneIsEnding() {
			// novel-scene-dataスクリプトからエンディング情報を取得
			var $sceneDataScript = $( '#novel-scene-data' );
			if ( $sceneDataScript.length > 0 ) {
				try {
					var sceneData = JSON.parse( $sceneDataScript.text() );
					if ( sceneData && sceneData.isEnding ) {
						return true;
					}
				} catch ( e ) {
					console.warn( 'シーンデータの解析に失敗しました:', e );
				}
			}
			
			// HTMLページからエンディングフラグを取得する（代替手段）
			var $gameContainer = $( '#novel-game-container' );
			if ( $gameContainer.length > 0 && $gameContainer.data( 'is-ending' ) ) {
				return true;
			}
			
			// グローバル変数からチェック（代替手段）
			if ( window.novelGameSceneData && window.novelGameSceneData.isEnding ) {
				return true;
			}
			
			return false;
		}

		/**
		 * 選択肢を表示、選択肢がない場合はエンディングまたはゲームオーバーを表示
		 */
		function showChoices() {
			if ( choices.length === 0 ) {
				// 選択肢がない場合はエンディングかゲームオーバーかを判定
				var isEnding = checkIfCurrentSceneIsEnding();
				if ( isEnding ) {
					showGameEnd();
				} else {
					showGameOver();
				}
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
					// 既存のイベントハンドラーをクリーンアップ
					$( document ).off( 'keydown.novel-choices' );
					
					// 0. 現在のシーンを前の選択肢シーンとして記録（Game Over時の復帰用）
					savePreviousChoiceSceneForCurrentGame();
					
					// 1. まず古いデータを完全にクリア
					dialogueData = [];
					dialogues = [];
					choices = [];
					baseBackground = '';
					currentBackground = '';
					charactersData = {};
					
					// 2. 表示状態をリセット
					resetGameState();
					
					// 3. 新しいシーンのデータを読み込み
					loadGameData( nextScene ).then( function() {
						// 4. シーン遷移後の進捗を保存
						autoSaveGameProgress();
						
						// 5. ゲームコンテンツを初期化
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
		 * ゲーム終了時の「おわり」画面を表示
		 */
		function showGameEnd() {
			$choicesContainer.empty();
			
			// ゲーム完了時に進捗をクリア
			if ( currentGameTitle ) {
				clearGameProgress( currentGameTitle );
				console.log( 'ゲーム完了により進捗をクリアしました:', currentGameTitle );
			}
			
			// 「おわり」メッセージを表示
			var $endMessage = $( '<div>' )
				.addClass( 'game-end-message' )
				.text( 'おわり' );
			
			$choicesContainer.append( $endMessage );
			
			// 「タイトルに戻る」ボタンを追加
			var $navigationContainer = $( '<div>' ).addClass( 'game-navigation' );
			var $returnToTitleButton = $( '<button>' )
				.addClass( 'game-nav-button return-title-button' )
				.text( 'タイトルに戻る' )
				.css( {
					'display': 'inline-block !important',
					'visibility': 'visible !important',
					'opacity': '1 !important',
					'position': 'relative !important',
					'z-index': '9999 !important',
					'background': '#4CAF50 !important',
					'color': 'white !important',
					'border': 'none !important',
					'padding': '15px 30px !important',
					'border-radius': '5px !important',
					'font-size': '16px !important',
					'cursor': 'pointer !important',
					'margin': '10px auto !important',
					'text-align': 'center !important'
				} )
				.on( 'click', function() {
					returnToTitleScreen();
				});
			
			$navigationContainer.append( $returnToTitleButton );
			$choicesContainer.append( $navigationContainer );
			
			// 確実に表示（!important で CSS 競合を回避）
			$choicesContainer.css( {
				'display': 'block !important',
				'visibility': 'visible !important',
				'opacity': '1 !important',
				'position': 'relative !important',
				'z-index': '1000 !important'
			} ).show();
			
			// ナビゲーションコンテナも確実に表示
			$navigationContainer.css( {
				'display': 'block !important',
				'visibility': 'visible !important',
				'text-align': 'center !important',
				'margin': '20px 0 !important'
			} );
			
			// 継続マーカーを非表示
			$dialogueContinue.hide();
			
			// 話者名を非表示
			if ( $speakerName && $speakerName.length > 0 ) {
				$speakerName.hide();
			}
			
			// デバッグ：ボタンの表示状態を確認
			console.log( 'showGameEnd: ボタン要素が作成されました' );
			console.log( 'Button element:', $returnToTitleButton[0] );
			console.log( 'Button visible:', $returnToTitleButton.is( ':visible' ) );
			console.log( 'Choices container visible:', $choicesContainer.is( ':visible' ) );
			console.log( 'Navigation container visible:', $navigationContainer.is( ':visible' ) );
			
			// キーボードイベントでもナビゲーション
			$( document ).on( 'keydown.novel-end', function( e ) {
				if ( e.which === 13 || e.which === 32 ) { // Enter or Space
					e.preventDefault();
					$returnToTitleButton.trigger( 'click' );
				}
			} );
		}
		
		/**
		 * タイトル画面に戻る（モーダルを再生成）
		 */
		function returnToTitleScreen() {
			console.log( 'タイトル画面に戻ります' );
			
			if ( window.currentGameSelectionData ) {
				console.log( 'ゲームデータが保存されています、モーダルを再生成します' );
				
				// 現在のモーダルを閉じる
				closeModal();
				
				// モーダルが完全に閉じられるまで待ってから新しいモーダルを開く
				setTimeout( function() {
					console.log( 'モーダル再生成開始' );
					// タイトル画面付きで再度開く
					openModal( window.currentGameSelectionData );
				}, 600 ); // アニメーション完了を待つため少し長めに設定
			} else {
				console.log( 'ゲームデータが保存されていません、ページリロードにフォールバック' );
				// フォールバック：ページリロード
				window.location.reload();
			}
		}
		
		/**
		 * 現在のゲームの前選択肢シーンを記録する（選択肢実行時）
		 */
		function savePreviousChoiceSceneForCurrentGame() {
			if ( ! currentGameTitle || ! currentSceneUrl ) {
				return;
			}
			
			try {
				var storageKey = generateStorageKey( currentGameTitle ) + '_prev_choice';
				var previousChoiceData = {
					gameTitle: currentGameTitle,
					sceneUrl: currentSceneUrl,
					currentPageIndex: currentPageIndex,
					currentDialogueIndex: currentDialogueIndex,
					timestamp: Date.now()
				};
				
				localStorage.setItem( storageKey, JSON.stringify( previousChoiceData ) );
				console.log( '前選択肢シーンを記録しました:', previousChoiceData );
			} catch ( error ) {
				console.warn( '前選択肢シーンの記録に失敗しました:', error );
			}
		}
		
		/**
		 * Game Over時に前の選択肢シーンを記録する
		 */
		function savePreviousChoiceScene() {
			if ( ! currentGameTitle ) {
				return;
			}
			
			try {
				// 現在のシーンがGame Overなので、ひとつ前のシーンを記録
				var storageKey = generateStorageKey( currentGameTitle ) + '_prev_choice';
				var previousChoiceData = {
					gameTitle: currentGameTitle,
					sceneUrl: currentSceneUrl,
					timestamp: Date.now()
				};
				
				localStorage.setItem( storageKey, JSON.stringify( previousChoiceData ) );
				console.log( 'Game Over時の前選択肢シーンを記録しました:', previousChoiceData );
			} catch ( error ) {
				console.warn( '前選択肢シーンの記録に失敗しました:', error );
			}
		}
		
		/**
		 * ゲームオーバー時の画面を表示
		 */
		function showGameOver() {
			$choicesContainer.empty();
			
			// ゲームオーバー時は進捗をクリアしない（前の選択肢からの復帰を可能にするため）
			// 代わりに、前の選択肢があったシーンを記録する
			if ( currentGameTitle ) {
				savePreviousChoiceScene();
			}
			
			// 「Game Over」メッセージを表示
			var $gameOverMessage = $( '<div>' )
				.addClass( 'game-end-message game-over-message' )
				.text( 'Game Over' );
			
			$choicesContainer.append( $gameOverMessage );
			
			// 「タイトルに戻る」ボタンを追加
			var $navigationContainer = $( '<div>' ).addClass( 'game-navigation' );
			var $returnToTitleButton = $( '<button>' )
				.addClass( 'game-nav-button return-title-button' )
				.text( 'タイトルに戻る' )
				.css( {
					'display': 'inline-block !important',
					'visibility': 'visible !important',
					'opacity': '1 !important',
					'position': 'relative !important',
					'z-index': '9999 !important',
					'background': '#f44336 !important',
					'color': 'white !important',
					'border': 'none !important',
					'padding': '15px 30px !important',
					'border-radius': '5px !important',
					'font-size': '16px !important',
					'cursor': 'pointer !important',
					'margin': '10px auto !important',
					'text-align': 'center !important'
				} )
				.on( 'click', function() {
					returnToTitleScreen();
				});
			
			$navigationContainer.append( $returnToTitleButton );
			$choicesContainer.append( $navigationContainer );
			
			// 確実に表示（!important で CSS 競合を回避）
			$choicesContainer.css( {
				'display': 'block !important',
				'visibility': 'visible !important',
				'opacity': '1 !important',
				'position': 'relative !important',
				'z-index': '1000 !important'
			} ).show();
			
			// ナビゲーションコンテナも確実に表示
			$navigationContainer.css( {
				'display': 'block !important',
				'visibility': 'visible !important',
				'text-align': 'center !important',
				'margin': '20px 0 !important'
			} );
			
			// 継続マーカーを非表示
			$dialogueContinue.hide();
			
			// 話者名を非表示
			if ( $speakerName && $speakerName.length > 0 ) {
				$speakerName.hide();
			}
			
			// デバッグ：ボタンの表示状態を確認
			console.log( 'showGameOver: ボタン要素が作成されました' );
			console.log( 'Button element:', $returnToTitleButton[0] );
			console.log( 'Button visible:', $returnToTitleButton.is( ':visible' ) );
			console.log( 'Choices container visible:', $choicesContainer.is( ':visible' ) );
			console.log( 'Navigation container visible:', $navigationContainer.is( ':visible' ) );
			
			// キーボードイベントでもナビゲーション
			$( document ).on( 'keydown.novel-gameover', function( e ) {
				if ( e.which === 13 || e.which === 32 ) { // Enter or Space
					e.preventDefault();
					$returnToTitleButton.trigger( 'click' );
				}
			} );
		}
		
		/**
		 * 進捗クリアボタンの表示・非表示を更新する
		 *
		 * @param {string} gameTitle ゲームタイトル
		 * @since 1.2.0
		 */
		function updateClearProgressButtonVisibility( gameTitle ) {
			if ( ! gameTitle || $clearProgressButton.length === 0 ) {
				return;
			}
			
			var hasSavedProgress = getSavedGameProgress( gameTitle ) !== null;
			
			if ( hasSavedProgress ) {
				$clearProgressButton.show();
			} else {
				$clearProgressButton.hide();
			}
			
			console.log( 'Clear progress button visibility updated:', hasSavedProgress ? 'visible' : 'hidden' );
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
			
			// 既存のイベントハンドラーをクリーンアップしてから設定
			$gameContainer.off( 'click.novel-game touchend.novel-game touchstart.novel-game touchcancel.novel-game' );
			
			$gameContainer.on( eventType + '.novel-game', function( e ) {
				// 選択肢が表示されている場合はクリックを無視
				if ( $choicesContainer.is( ':visible' ) ) {
					return;
				}

				// 選択肢要素がクリックされた場合も無視
				if ( $( e.target ).hasClass( 'choice-item' ) || $( e.target ).closest( '.choice-list' ).length > 0 ) {
					return;
				}
				
				// 「おわり」メッセージがクリックされた場合も無視（別途処理）
				if ( $( e.target ).hasClass( 'game-end-message' ) ) {
					return;
				}

				// 次のセリフを表示
				showNextDialogue();
			} );

			// タッチデバイス用の追加イベント
			if ( isTouch ) {
				$gameContainer.on( 'touchstart.novel-game', function( e ) {
					// 選択肢が表示されている場合はタッチを無視
					if ( $choicesContainer.is( ':visible' ) ) {
						return;
					}
					
					// 選択肢要素がタッチされた場合も無視
					if ( $( e.target ).hasClass( 'choice-item' ) || $( e.target ).closest( '.choice-list' ).length > 0 ) {
						return;
					}
					
					// 「おわり」メッセージがタッチされた場合も無視（別途処理）
					if ( $( e.target ).hasClass( 'game-end-message' ) ) {
						return;
					}
					
					// タッチフィードバック
					$( this ).addClass( 'touch-active' );
				} ).on( 'touchcancel.novel-game', function() {
					$( this ).removeClass( 'touch-active' );
				} );
			}
			
			// キーボードイベント処理（Enter、Space）
			// 既存のキーボードイベントをクリーンアップしてから設定
			$( document ).off( 'keydown.novel-dialogue' );
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
			
			// 以前のイベントハンドラーをクリーンアップ
			$( window ).off( 'resize.game orientationchange.game' );
			$gameContainer.off( '.novel-game' );

			// イベントリスナーの設定
			setupGameInteraction();

			// リサイズイベントの設定
			$( window ).on( 'resize.game orientationchange.game', handleResize );

			// 初期調整
			adjustForResponsive();

			// セリフデータがある場合は分割処理を実行
			if ( dialogues.length > 0 || dialogueData.length > 0 ) {
				console.log( 'Preparing dialogue pages' );
				
				// データの検証とクリーンアップ
				if ( dialogueData.length === 0 && dialogues.length > 0 ) {
					// 古い形式のdialogues配列からdialogueDataを再構築
					dialogueData = dialogues.map( function( text ) {
						return { text: text, background: '', speaker: '' };
					} );
					console.log( 'Rebuilt dialogueData from legacy dialogues array' );
				}
				
				// 空のセリフをフィルタリング
				dialogueData = dialogueData.filter( function( item ) {
					return item && item.text && item.text.trim() !== '';
				} );
				
				// dialogues配列も更新
				dialogues = dialogueData.map( function( item ) {
					return item.text;
				} );
				
				console.log( 'Cleaned dialogue data, final length:', dialogueData.length );
				
				prepareDialoguePages();
				
				// 保存された進捗がある場合はその位置から開始、なければ最初から
				if ( currentPageIndex > 0 && currentPageIndex < allDialoguePages.length ) {
					console.log( '保存された位置から再開:', currentPageIndex );
					displayCurrentPage();
				} else {
					console.log( '最初から開始' );
					currentPageIndex = 0;
					currentDialogueIndex = 0;
					displayCurrentPage();
				}
				
				// 現在のゲーム情報を設定（まだ設定されていない場合）
				if ( ! currentGameTitle ) {
					var gameTitle = extractGameTitleFromPage();
					if ( gameTitle ) {
						setCurrentGameInfo( gameTitle, currentSceneUrl || window.location.href );
					}
				}
				
				// 初期位置を自動保存
				autoSaveGameProgress();
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
			console.log( 'Title screen found:', $titleScreen.length > 0 );
			console.log( 'Start button found:', $startButton.length > 0 );
			console.log( 'Clear progress button found:', $clearProgressButton.length > 0 );
			console.log( 'Close button found:', $closeButton.length > 0 );
			
			// モーダル要素が存在する場合のみモーダルイベントを設定
			if ( $modalOverlay.length > 0 ) {
				// モーダルイベントの設定
				setupModalEvents();
				
				// 初期状態はモーダルとタイトル画面を非表示
				$modalOverlay.hide();
				$titleScreen.hide();
				
				console.log( 'Modal events set up successfully' );
			} else {
				console.log( 'No modal overlay found, skipping modal setup' );
			}
			
			// 進捗クリアボタンの表示状態を初期化
			var gameTitle = extractGameTitleFromPage();
			if ( gameTitle ) {
				updateClearProgressButtonVisibility( gameTitle );
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
			openSelection: function( gameData ) {
				console.log( 'novelGameModal.openSelection called with data:', gameData );
				console.log( 'Modal overlay exists:', $modalOverlay.length > 0 );
				
				// モーダル要素が存在しない場合は直接ゲームを開始
				if ( $modalOverlay.length === 0 ) {
					console.log( 'Modal overlay not found, opening game directly' );
					if ( gameData && gameData.url ) {
						window.location.href = gameData.url;
					}
					return;
				}
				openModal( gameData );
			},
			close: function() {
				console.log( 'novelGameModal.close called' );
				// タイトル画面が表示されている場合は非表示にする
				if ( isTitleScreenVisible ) {
					hideTitleScreen();
				}
				// ゲームモーダルが開いている場合はゲームモーダルを閉じる
				if ( isModalOpen && $modalOverlay.length > 0 ) {
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
