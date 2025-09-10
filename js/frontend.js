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
		var isEnding = false;
		
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
			var endingDataRaw = $( '#novel-ending-data' ).text();

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
			
			if ( endingDataRaw ) {
				isEnding = JSON.parse( endingDataRaw );
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
				localStorage.removeItem( storageKey );
				console.log( 'ゲーム進捗をクリアしました:', gameTitle );
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
			
			// モーダル再生成後のDOM参照漏れを防ぐため、必ず最新のDOM要素を取得
			$titleScreen = $( '#novel-title-screen' );
			$titleMain = $( '#novel-title-main' );
			$titleSubtitle = $( '#novel-title-subtitle' );
			$titleDescription = $( '#novel-title-description' );
			$titleStartBtn = $( '#novel-title-start-new' );
			$titleContinueBtn = $( '#novel-title-continue' );
			
			// モーダル再生成後の表示確保のため、display: flex と .show クラスを明示的に設定
			if ( $titleScreen.length > 0 ) {
				$titleScreen.css( 'display', 'flex' ).addClass( 'show' );
			}
			
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
				
				if ( savedProgress ) {
					$titleContinueBtn.show();
					console.log( '保存された進捗が見つかりました。「続きから始める」ボタンを表示します。' );
				} else {
					$titleContinueBtn.hide();
					console.log( '保存された進捗がありません。「続きから始める」ボタンを非表示にします。' );
				}
			} else {
				$titleContinueBtn.hide();
			}
			
			// タイトル画面を表示（モーダル再生成後の表示確保のため、アニメーション付きで表示）
			$titleScreen.hide().fadeIn( 300 );
			
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
			
			// モーダル再生成後のDOM参照漏れを防ぐため、必ず最新のDOM要素を取得
			$titleScreen = $( '#novel-title-screen' );
			
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
							
							// エンディングデータを取得
							var endingDataScript = $response.filter( 'script#novel-ending-data' );
							if ( endingDataScript.length === 0 ) {
								endingDataScript = $response.find( '#novel-ending-data' );
							}
							console.log( 'Ending data script found:', endingDataScript.length );
							
							if ( endingDataScript.length > 0 ) {
								var endingDataText = endingDataScript.text() || endingDataScript.html();
								if ( endingDataText ) {
									isEnding = JSON.parse( endingDataText );
									console.log( 'Parsed ending data:', isEnding );
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
		 * モーダルを開く（タイトル画面表示モードまたは直接ゲーム開始モード）
		 *
		 * @param {string|object} gameUrlOrData ゲームのURLまたはゲームデータオブジェクト
		 */
		function openModal( gameUrlOrData ) {
			console.log( 'openModal called with:', gameUrlOrData );
			
			// モーダル再生成後のDOM参照漏れを防ぐため、必ず最新のDOM要素を取得
			$modalOverlay = $( '#novel-game-modal-overlay' );
			
			// モーダル再生成後の表示確保のため、display: flex を明示的に設定
			if ( $modalOverlay.length > 0 ) {
				$modalOverlay.css( 'display', 'flex' );
			}
			
			console.log( 'Modal overlay exists:', $modalOverlay.length > 0 );
			console.log( 'isModalOpen:', isModalOpen );
			
			if ( isModalOpen ) {
				console.log( 'Modal already open, ignoring' );
				return;
			}
			
			// モーダル要素が存在しない場合はページ遷移
			if ( $modalOverlay.length === 0 ) {
				console.log( 'Modal overlay not found, redirecting to:', gameUrlOrData );
				if ( typeof gameUrlOrData === 'string' ) {
					window.location.href = gameUrlOrData;
				} else if ( gameUrlOrData && gameUrlOrData.url ) {
					window.location.href = gameUrlOrData.url;
				}
				return;
			}
			
			isModalOpen = true;
			
			// ボディとHTMLのスクロールを無効化
			$( 'html, body' ).addClass( 'modal-open' ).css( 'overflow', 'hidden' );
			
			// オーバーレイの表示を確実にする（WordPress レイアウト制約を回避）
			// モーダル再生成後の表示確保のため、display: flex と .show クラスを明示的に設定
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
			
			// モーダル再生成後のDOM参照漏れを防ぐため、必ず最新のDOM要素を取得
			$modalOverlay = $( '#novel-game-modal-overlay' );
			
			if ( ! isModalOpen ) {
				console.log( 'Modal already closed, ignoring' );
				return;
			}
			
			isModalOpen = false;
			
			// ボディとHTMLのスクロールを復元
			$( 'html, body' ).removeClass( 'modal-open' ).css( 'overflow', '' );
			
			// オーバーレイを非表示
			if ( $modalOverlay.length > 0 ) {
				$modalOverlay.removeClass( 'show' ).animate( { opacity: 0 }, 300, function() {
					$modalOverlay.css( 'display', 'none' );
				} );
			}
			
			console.log( 'Modal overlay hidden' );
			
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
						console.log( '保存された進捗が見つかりません。最初から開始します。' );
						// 進捗がない場合は最初から開始（タイトル画面経由のため進捗チェックはスキップ）
						hideTitleScreen();
						setTimeout( function() {
							initializeGameContent();
						}, 300 );
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
		 * 選択肢を表示、選択肢がない場合は「おわり」を表示
		 */
		function showChoices() {
			// エンディング設定がある場合は選択肢に関係なくゲーム終了
			if ( isEnding ) {
				showGameEnd();
				return;
			}
			
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
					// 既存のイベントハンドラーをクリーンアップ
					$( document ).off( 'keydown.novel-choices' );
					
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
			
			// エンディング判定による処理分岐
			if ( isEnding ) {
				// エンディング画面：話者名枠を非表示にする
				$speakerName.hide();
				
				// エンディングメッセージを表示
				var $endMessage = $( '<div>' )
					.addClass( 'game-end-message' )
					.text( 'おわり' );
				
				$choicesContainer.append( $endMessage );
				
				// 「タイトルに戻る」ボタンを追加
				var $navigationContainer = $( '<div>' ).addClass( 'game-navigation' );
				var $titleReturnButton = $( '<button>' )
					.addClass( 'game-nav-button title-return-button' )
					.text( 'タイトルに戻る' )
					.on( 'click', function() {
						console.log( 'タイトルに戻るボタンがクリックされました' );
						
						// 既存のモーダル再生成 API を使用してモーダルを削除・再生成
						window.novelGameModalUtil.recreate().then( function() {
							console.log( 'モーダル再生成完了、タイトル画面を表示します' );
							
							// 再生成後にタイトル画面を表示（状態変数はリセットしない）
							openModal();
							
						} ).catch( function( error ) {
							console.error( 'モーダル再生成に失敗しました:', error );
							// フォールバック：直接タイトル画面を表示
							openModal();
						} );
					});
				
				$navigationContainer.append( $titleReturnButton );
				$choicesContainer.append( $navigationContainer );
				
				// キーボードイベントでもタイトルに戻る
				$( document ).on( 'keydown.novel-end', function( e ) {
					if ( e.which === 13 || e.which === 32 ) { // Enter or Space
						e.preventDefault();
						$titleReturnButton.trigger( 'click' );
					}
				} );
				
			} else {
				// 通常のゲーム完了：進捗をクリア
				if ( currentGameTitle ) {
					clearGameProgress( currentGameTitle );
					console.log( 'ゲーム完了により進捗をクリアしました:', currentGameTitle );
				}
				
				// 「おわり」メッセージを表示
				var $endMessage = $( '<div>' )
					.addClass( 'game-end-message' )
					.text( 'おわり' );
				
				$choicesContainer.append( $endMessage );
				
				// ナビゲーションボタンを追加
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
				
				// キーボードイベントでもナビゲーション
				$( document ).on( 'keydown.novel-end', function( e ) {
					if ( e.which === 13 || e.which === 32 ) { // Enter or Space
						e.preventDefault();
						if ( isShortcodeUsed ) {
							$closeButton.trigger( 'click' );
						} else {
							$gameListButton.trigger( 'click' );
						}
					}
				} );
			}
			
			$choicesContainer.show();
			
			// 継続マーカーを非表示
			$dialogueContinue.hide();
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
				if ( currentPageContent && typeof currentPageContent === 'string' ) {
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

		/**
		 * モーダルDOM再生成ユーティリティ
		 * ゲーム進行中にモーダルの削除・再生成が必要な場合に使用
		 * 
		 * @since 1.3.0
		 */
		var modalUtil = {
			/**
			 * モーダルDOM構造のテンプレート
			 * 既存構造と同一構造を保証する
			 */
			template: {
				overlay: '<div id="novel-game-modal-overlay" class="novel-game-modal-overlay" style="display: none;"></div>',
				content: '<div id="novel-game-modal-content" class="novel-game-modal-content"></div>',
				closeButton: '<button id="novel-game-close-btn" class="novel-game-close-btn" aria-label="ゲームを閉じる" title="ゲームを閉じる"><span class="close-icon">×</span></button>',
				titleScreen: '<div id="novel-title-screen" class="novel-title-screen" style="display: none;"><div class="novel-title-content"><h2 id="novel-title-main" class="novel-title-main"></h2><p id="novel-title-subtitle" class="novel-title-subtitle"></p><p id="novel-title-description" class="novel-title-description"></p><div class="novel-title-buttons"><button id="novel-title-start-new" class="novel-title-btn novel-title-start-btn">最初から開始</button><button id="novel-title-continue" class="novel-title-btn novel-title-continue-btn" style="display: none;">続きから始める</button></div></div></div>',
				gameContainer: '<div id="novel-game-container" class="novel-game-container"><!-- ゲーム内容は動的に読み込まれます --></div>'
			},

			/**
			 * 現在の実行中アニメーション管理
			 */
			pendingAnimations: [],

			/**
			 * モーダル再生成の実行状態
			 */
			isRecreating: false,

			/**
			 * モーダルを安全に再生成する
			 * フェードアウトなど非同期アニメーション完了後に実行
			 * 
			 * @param {object} options 再生成オプション
			 * @param {boolean} options.preserveState 現在の状態を保持するかどうか（デフォルト: true）
			 * @param {boolean} options.waitForAnimations アニメーション完了を待つかどうか（デフォルト: true）
			 * @return {Promise} 再生成完了のPromise
			 */
			recreate: function( options ) {
				var self = this;
				options = options || {};
				var preserveState = options.preserveState !== false;
				var waitForAnimations = options.waitForAnimations !== false;

				return new Promise( function( resolve, reject ) {
					console.log( 'modalUtil.recreate開始:', { preserveState: preserveState, waitForAnimations: waitForAnimations } );

					// 既に再生成中の場合は待機
					if ( self.isRecreating ) {
						console.log( 'モーダル再生成が既に実行中です' );
						setTimeout( function() {
							self.recreate( options ).then( resolve ).catch( reject );
						}, 100 );
						return;
					}

					self.isRecreating = true;

					try {
						// 1. 現在の状態を保存
						var savedState = preserveState ? self._saveCurrentState() : null;

						// 2. アニメーション完了を待機
						var animationPromise = waitForAnimations ? self._waitForAnimations() : Promise.resolve();

						animationPromise.then( function() {
							// 3. イベントハンドラーをクリーンアップ
							self._cleanupEventHandlers();

							// 4. 古いモーダルを削除
							self._removeOldModal();

							// 5. 新しいモーダルを生成
							self._createNewModal();

							// 6. DOM参照を再取得
							self._refreshDOMReferences();

							// 7. イベントハンドラーを再設定
							self._rebindEventHandlers();

							// 8. 状態を復元
							if ( savedState ) {
								self._restoreState( savedState );
							}

							self.isRecreating = false;
							console.log( 'modalUtil.recreate完了' );
							resolve();

						} ).catch( function( error ) {
							self.isRecreating = false;
							console.error( 'modalUtil.recreate失敗:', error );
							reject( error );
						} );

					} catch ( error ) {
						self.isRecreating = false;
						console.error( 'modalUtil.recreate例外:', error );
						reject( error );
					}
				} );
			},

			/**
			 * 現在のアニメーション完了を待機
			 * 
			 * @return {Promise} アニメーション完了のPromise
			 */
			_waitForAnimations: function() {
				var self = this;
				return new Promise( function( resolve ) {
					// モーダルのフェードアニメーション検知
					if ( $modalOverlay.length > 0 && $modalOverlay.is( ':animated' ) ) {
						console.log( 'モーダルアニメーション完了を待機中...' );
						$modalOverlay.queue( function() {
							$( this ).dequeue();
							resolve();
						} );
					} else {
						// 短時間待機してから完了とする（CSS animationなどのために）
						setTimeout( resolve, 50 );
					}
				} );
			},

			/**
			 * 現在の状態を保存
			 * 
			 * @return {object} 保存された状態
			 */
			_saveCurrentState: function() {
				var state = {};

				try {
					// モーダルの表示状態
					state.isModalOpen = isModalOpen;
					state.isTitleScreenVisible = isTitleScreenVisible;

					// モーダルの位置・スタイル
					if ( $modalOverlay.length > 0 ) {
						state.modalDisplay = $modalOverlay.css( 'display' );
						state.modalOpacity = $modalOverlay.css( 'opacity' );
						state.modalClasses = $modalOverlay.attr( 'class' );
					}

					// タイトル画面の表示状態
					if ( $titleScreen.length > 0 ) {
						state.titleScreenDisplay = $titleScreen.css( 'display' );
						state.titleScreenClasses = $titleScreen.attr( 'class' );
					}

					// ゲームコンテナの状態
					if ( $gameContainer.length > 0 ) {
						state.gameContainerHTML = $gameContainer.html();
						state.gameContainerClasses = $gameContainer.attr( 'class' );
						state.gameContainerStyle = $gameContainer.attr( 'style' );
					}

					console.log( '状態保存完了:', state );
					return state;

				} catch ( error ) {
					console.warn( '状態保存中にエラーが発生:', error );
					return {};
				}
			},

			/**
			 * イベントハンドラーのクリーンアップ
			 */
			_cleanupEventHandlers: function() {
				console.log( 'イベントハンドラーのクリーンアップ開始' );

				try {
					// モーダル関連のイベントをクリーンアップ
					$( document ).off( 'keydown.modal' );
					$( document ).off( 'keydown.novel-dialogue' );
					$( document ).off( 'keydown.resume-dialog' );
					$( window ).off( 'resize.game orientationchange.game' );

					// モーダル要素のイベントをクリーンアップ
					if ( $modalOverlay.length > 0 ) {
						$modalOverlay.off( '.novel-game' );
					}
					if ( $gameContainer.length > 0 ) {
						$gameContainer.off( '.novel-game' );
					}

					// 動的に追加されたイベントもクリーンアップ
					$( document ).off( 'click.novel-game-dynamic' );

					console.log( 'イベントハンドラーのクリーンアップ完了' );

				} catch ( error ) {
					console.warn( 'イベントハンドラークリーンアップ中にエラーが発生:', error );
				}
			},

			/**
			 * 古いモーダルを削除
			 */
			_removeOldModal: function() {
				console.log( '古いモーダルの削除開始' );

				try {
					if ( $modalOverlay.length > 0 ) {
						$modalOverlay.remove();
					}
					console.log( '古いモーダルの削除完了' );

				} catch ( error ) {
					console.warn( '古いモーダル削除中にエラーが発生:', error );
				}
			},

			/**
			 * 新しいモーダルを生成
			 */
			_createNewModal: function() {
				console.log( '新しいモーダルの生成開始' );

				try {
					// モーダル構造を構築
					var $newOverlay = $( this.template.overlay );
					var $newContent = $( this.template.content );
					var $newCloseButton = $( this.template.closeButton );
					var $newTitleScreen = $( this.template.titleScreen );
					var $newGameContainer = $( this.template.gameContainer );

					// 構造を組み立て
					$newContent.append( $newCloseButton );
					$newContent.append( $newTitleScreen );
					$newContent.append( $newGameContainer );
					$newOverlay.append( $newContent );

					// DOMに追加
					$( 'body' ).append( $newOverlay );

					console.log( '新しいモーダルの生成完了' );

				} catch ( error ) {
					console.error( '新しいモーダル生成中にエラーが発生:', error );
					throw error;
				}
			},

			/**
			 * DOM参照を再取得
			 */
			_refreshDOMReferences: function() {
				console.log( 'DOM参照の再取得開始' );

				try {
					// グローバル変数の再取得
					$modalOverlay = $( '#novel-game-modal-overlay' );
					$closeButton = $( '#novel-game-close-btn' );
					$titleScreen = $( '#novel-title-screen' );
					$titleMain = $( '#novel-title-main' );
					$titleSubtitle = $( '#novel-title-subtitle' );
					$titleDescription = $( '#novel-title-description' );
					$titleStartBtn = $( '#novel-title-start-new' );
					$titleContinueBtn = $( '#novel-title-continue' );
					$gameContainer = $( '#novel-game-container' );

					// ゲーム内の要素も再取得
					$dialogueText = $( '#novel-dialogue-text' );
					$dialogueBox = $( '#novel-dialogue-box' );
					$speakerName = $( '#novel-speaker-name' );
					$dialogueContinue = $( '#novel-dialogue-continue' );
					$choicesContainer = $( '#novel-choices' );

					console.log( 'DOM参照の再取得完了:', {
						modalOverlay: $modalOverlay.length,
						closeButton: $closeButton.length,
						titleScreen: $titleScreen.length,
						gameContainer: $gameContainer.length
					} );

				} catch ( error ) {
					console.error( 'DOM参照再取得中にエラーが発生:', error );
					throw error;
				}
			},

			/**
			 * イベントハンドラーを再設定
			 */
			_rebindEventHandlers: function() {
				console.log( 'イベントハンドラーの再設定開始' );

				try {
					// モーダルイベントを再設定
					if ( typeof setupModalEvents === 'function' ) {
						setupModalEvents();
					}

					console.log( 'イベントハンドラーの再設定完了' );

				} catch ( error ) {
					console.error( 'イベントハンドラー再設定中にエラーが発生:', error );
					throw error;
				}
			},

			/**
			 * 状態を復元
			 * 
			 * @param {object} savedState 保存された状態
			 */
			_restoreState: function( savedState ) {
				console.log( '状態の復元開始:', savedState );

				try {
					if ( ! savedState ) {
						return;
					}

					// モーダルの表示状態復元
					if ( savedState.isModalOpen !== undefined ) {
						isModalOpen = savedState.isModalOpen;
					}
					if ( savedState.isTitleScreenVisible !== undefined ) {
						isTitleScreenVisible = savedState.isTitleScreenVisible;
					}

					// モーダルのスタイル復元
					if ( $modalOverlay.length > 0 ) {
						if ( savedState.modalDisplay ) {
							$modalOverlay.css( 'display', savedState.modalDisplay );
						}
						if ( savedState.modalOpacity ) {
							$modalOverlay.css( 'opacity', savedState.modalOpacity );
						}
						if ( savedState.modalClasses ) {
							$modalOverlay.attr( 'class', savedState.modalClasses );
						}
					}

					// タイトル画面の表示状態復元
					if ( $titleScreen.length > 0 ) {
						if ( savedState.titleScreenDisplay ) {
							$titleScreen.css( 'display', savedState.titleScreenDisplay );
						}
						if ( savedState.titleScreenClasses ) {
							$titleScreen.attr( 'class', savedState.titleScreenClasses );
						}
					}

					// ゲームコンテナの状態復元
					if ( $gameContainer.length > 0 ) {
						if ( savedState.gameContainerHTML ) {
							$gameContainer.html( savedState.gameContainerHTML );
						}
						if ( savedState.gameContainerClasses ) {
							$gameContainer.attr( 'class', savedState.gameContainerClasses );
						}
						if ( savedState.gameContainerStyle ) {
							$gameContainer.attr( 'style', savedState.gameContainerStyle );
						}
					}

					console.log( '状態の復元完了' );

				} catch ( error ) {
					console.warn( '状態復元中にエラーが発生:', error );
				}
			},

			/**
			 * 短時間で連続操作された場合の安全性チェック
			 * 
			 * @return {boolean} 安全に実行可能かどうか
			 */
			isSafeToRecreate: function() {
				// 再生成中でないこと
				if ( this.isRecreating ) {
					return false;
				}

				// モーダル要素が存在すること
				if ( $modalOverlay.length === 0 ) {
					return true; // 存在しない場合は再生成可能
				}

				// アニメーション中でないこと
				if ( $modalOverlay.is( ':animated' ) ) {
					return false;
				}

				return true;
			}
		};

		// modalUtilをグローバルにエクスポート（他の機能から利用可能にする）
		window.novelGameModalUtil = modalUtil;

		/**
		 * モーダル再生成ユーティリティの使用例:
		 * 
		 * // 基本的な再生成（状態保持あり）
		 * window.novelGameModalUtil.recreate().then(function() {
		 *     console.log('モーダル再生成完了');
		 * });
		 * 
		 * // 状態を保持せずに再生成
		 * window.novelGameModalUtil.recreate({ preserveState: false }).then(function() {
		 *     console.log('モーダル再生成完了（状態リセット）');
		 * });
		 * 
		 * // アニメーション完了を待たずに即座に再生成
		 * window.novelGameModalUtil.recreate({ waitForAnimations: false }).then(function() {
		 *     console.log('モーダル再生成完了（アニメーション待機なし）');
		 * });
		 * 
		 * // 安全性チェック
		 * if (window.novelGameModalUtil.isSafeToRecreate()) {
		 *     // 再生成実行
		 * }
		 */

		console.log( 'モーダルDOM再生成ユーティリティが初期化されました' );
	} );

} )( jQuery );
