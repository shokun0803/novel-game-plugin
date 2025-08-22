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

		// 統一ゲーム状態オブジェクト（フラグ管理の一元化）
		var gameState = {
			// ゲーム進行状態
			currentDialogueIndex: 0,
			currentPageIndex: 0,
			currentGameTitle: '',
			currentSceneUrl: '',
			
			// ゲームフラグ
			isEndingScene: false,
			autoSaveEnabled: true,
			
			// ゲームデータ
			dialogueData: [],
			dialogues: [],
			choices: [],
			currentDialoguePages: [],
			allDialoguePages: [],
			
			// 表示データ
			baseBackground: '',
			currentBackground: '',
			charactersData: {},
			
			/**
			 * ゲーム状態を初期化する
			 */
			reset: function() {
				this.currentDialogueIndex = 0;
				this.currentPageIndex = 0;
				this.currentGameTitle = '';
				this.currentSceneUrl = '';
				this.isEndingScene = false;
				this.autoSaveEnabled = true;
				this.dialogueData = [];
				this.dialogues = [];
				this.choices = [];
				this.currentDialoguePages = [];
				this.allDialoguePages = [];
				this.baseBackground = '';
				this.currentBackground = '';
				this.charactersData = {};
			},
			
			/**
			 * 新ゲーム開始用の状態設定
			 */
			setNewGame: function(gameTitle, sceneUrl) {
				this.currentDialogueIndex = 0;
				this.currentPageIndex = 0;
				this.currentGameTitle = gameTitle || '';
				this.currentSceneUrl = sceneUrl || window.location.href;
				this.isEndingScene = false;
			},
			
			/**
			 * localStorage保存用のデータを取得
			 */
			getProgressData: function() {
				return {
					gameTitle: this.currentGameTitle,
					sceneUrl: this.currentSceneUrl,
					currentPageIndex: this.currentPageIndex,
					currentDialogueIndex: this.currentDialogueIndex,
					timestamp: Date.now(),
					version: '1.3.0'
				};
			},
			
			/**
			 * localStorage保存用の統一フラグ文字列を取得
			 */
			getFlagsAsString: function() {
				return [
					'isEndingScene:' + this.isEndingScene,
					'autoSaveEnabled:' + this.autoSaveEnabled
				].join(',');
			},
			
			/**
			 * 統一フラグ文字列から状態を復元
			 */
			setFlagsFromString: function(flagsString) {
				if (!flagsString) return;
				
				var flags = flagsString.split(',');
				for (var i = 0; i < flags.length; i++) {
					var pair = flags[i].split(':');
					if (pair.length === 2) {
						var key = pair[0];
						var value = pair[1] === 'true';
						if (key === 'isEndingScene') {
							this.isEndingScene = value;
						} else if (key === 'autoSaveEnabled') {
							this.autoSaveEnabled = value;
						}
					}
				}
			}
		};
		
		// 後方互換性のための変数（段階的移行） - gameStateへの参照に変更
		var dialogueIndex = 0; // 廃止予定：gameState.currentDialogueIndexを参照
		var dialogues = []; // 廃止予定：gameState.dialoguesを参照
		var dialogueData = []; // 廃止予定：gameState.dialogueDataを参照
		var choices = []; // 廃止予定：gameState.choicesを参照
		var baseBackground = ''; // 廃止予定：gameState.baseBackgroundを参照
		var currentBackground = ''; // 廃止予定：gameState.currentBackgroundを参照
		var charactersData = {}; // 廃止予定：gameState.charactersDataを参照
		var $gameContainer = $( '#novel-game-container' );
		var $dialogueText = $( '#novel-dialogue-text' );
		var $dialogueBox = $( '#novel-dialogue-box' );
		var $speakerName = $( '#novel-speaker-name' );
		var $dialogueContinue = $( '#novel-dialogue-continue' );
		var $choicesContainer = $( '#novel-choices' );
		var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
		
		// 自動保存機能の変数（gameStateへの参照）
		var currentGameTitle = ''; // 廃止予定：gameState.currentGameTitleを参照
		var currentSceneUrl = ''; // 廃止予定：gameState.currentSceneUrlを参照
		var autoSaveEnabled = true; // 廃止予定：gameState.autoSaveEnabledを参照
		
		// セリフ表示用の新しい変数（gameStateへの参照）
		// var currentDialogueIndex = 0; // 廃止：gameState.currentDialogueIndexを使用
		// var currentPageIndex = 0; // 廃止：gameState.currentPageIndexを使用
		// var currentDialoguePages = []; // 廃止：gameState.currentDialoguePagesを使用
		// var allDialoguePages = []; // 廃止：gameState.allDialoguePagesを使用
		
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

		// 初期データの読み込み（統一ゲーム状態オブジェクトを使用）
		try {
			var dialogueDataRaw = $( '#novel-dialogue-data' ).text();
			var choicesData = $( '#novel-choices-data' ).text();
			var baseBackgroundData = $( '#novel-base-background' ).text();
			var charactersDataRaw = $( '#novel-characters-data' ).text();
			var endingSceneFlagData = $( '#novel-ending-scene-flag' ).text();

			if ( dialogueDataRaw ) {
				gameState.dialogueData = JSON.parse( dialogueDataRaw );
				
				// 後方互換性のため、文字列配列の場合は変換
				if ( gameState.dialogueData.length > 0 && typeof gameState.dialogueData[0] === 'string' ) {
					gameState.dialogueData = gameState.dialogueData.map( function( text ) {
						return { text: text, background: '', speaker: '' };
					} );
				}
				
				// 旧形式のために dialogues 配列も維持
				gameState.dialogues = gameState.dialogueData.map( function( item ) {
					return item.text;
				} );
				
				// 後方互換変数を更新
				dialogueData = gameState.dialogueData;
				dialogues = gameState.dialogues;
			}

			if ( choicesData ) {
				gameState.choices = JSON.parse( choicesData );
				choices = gameState.choices; // 後方互換変数を更新
			}
			
			if ( baseBackgroundData ) {
				gameState.baseBackground = JSON.parse( baseBackgroundData );
				gameState.currentBackground = gameState.baseBackground;
				// 後方互換変数を更新
				baseBackground = gameState.baseBackground;
				currentBackground = gameState.currentBackground;
			}
			
			if ( charactersDataRaw ) {
				gameState.charactersData = JSON.parse( charactersDataRaw );
				charactersData = gameState.charactersData; // 後方互換変数を更新
			}
			
			// エンディングフラグの読み込み（初期化時）
			console.log( '初期化時エンディングフラグ読み込み開始' );
			console.log( 'endingSceneFlagData (raw):', endingSceneFlagData );
			
			if ( endingSceneFlagData && endingSceneFlagData.trim() !== '' ) {
				try {
					var parsedEndingFlag = JSON.parse( endingSceneFlagData );
					gameState.isEndingScene = parsedEndingFlag;
					console.log( '初期化時エンディングシーンフラグを正常に読み込みました:', {
						raw: endingSceneFlagData,
						parsed: parsedEndingFlag,
						gameState: gameState.isEndingScene
					} );
				} catch ( parseError ) {
					console.error( '初期化時エンディングフラグのパースに失敗:', parseError );
					gameState.isEndingScene = false;
				}
			} else {
				console.log( '初期化時エンディングフラグデータが空または存在しません - falseに設定' );
				gameState.isEndingScene = false;
			}
			
			console.log( 'ゲームデータを統一状態オブジェクトに読み込み完了' );
		} catch ( error ) {
			console.error( 'ノベルゲームデータの解析に失敗しました:', error );
			return;
		}

		/**
		 * ゲーム進捗をlocalStorageに自動保存する（統一ゲーム状態使用）
		 *
		 * @since 1.3.0
		 */
		function autoSaveGameProgress() {
			if ( ! gameState.autoSaveEnabled || ! gameState.currentGameTitle || ! gameState.currentSceneUrl ) {
				return;
			}
			
			try {
				var progressData = gameState.getProgressData();
				// 統一フラグも保存
				progressData.flags = gameState.getFlagsAsString();
				
				var storageKey = generateStorageKey( gameState.currentGameTitle );
				localStorage.setItem( storageKey, JSON.stringify( progressData ) );
				
				console.log( 'ゲーム進捗を統一状態で自動保存しました:', progressData );
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
				// フォールバック：encodeURIComponent を使用した安全な方式
				return 'noveltool_progress_' + encodeURIComponent( gameTitle );
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
		 * 「最初から開始」時の全データ完全削除処理
		 * localStorage内の全ての進捗・フラグ情報を完全に削除し、gameStateを初期化
		 *
		 * @param {string} gameTitle ゲームタイトル
		 * @since 1.2.0
		 */
		function clearGameProgress( gameTitle ) {
			if ( ! gameTitle ) {
				console.warn( 'clearGameProgress: ゲームタイトルが指定されていません' );
				return;
			}
			
			console.log( '「最初から開始」：全データ完全削除処理を開始します', gameTitle );
			
			try {
				// 1. メインのストレージキーを削除
				var storageKey = generateStorageKey( gameTitle );
				localStorage.removeItem( storageKey );
				console.log( 'メインの進捗データを削除:', storageKey );
				
				// 2. 旧形式・類似キーを網羅的に削除（フラグ・状態データも含む）
				var oldKeys = [
					'noveltool_progress_' + encodeURIComponent( gameTitle ),
					'novel_progress_' + gameTitle,
					'game_progress_' + gameTitle,
					gameTitle + '_progress',
					gameTitle + '_save',
					gameTitle + '_flags',
					gameTitle + '_state',
					gameTitle + '_ending',
					'noveltool_' + gameTitle,
					'game_' + gameTitle
				];
				
				for ( var i = 0; i < oldKeys.length; i++ ) {
					if ( localStorage.getItem( oldKeys[i] ) ) {
						localStorage.removeItem( oldKeys[i] );
						console.log( '旧形式のデータを削除:', oldKeys[i] );
					}
				}
				
				// 3. 全localStorageを走査し、関連するキーを完全削除
				var allKeys = [];
				for ( var j = 0; j < localStorage.length; j++ ) {
					allKeys.push( localStorage.key( j ) );
				}
				
				for ( var k = 0; k < allKeys.length; k++ ) {
					var key = allKeys[k];
					if ( key && ( key.includes( gameTitle ) || key.includes( 'noveltool' ) || key.includes( 'novel' ) ) ) {
						localStorage.removeItem( key );
						console.log( '関連データを完全削除:', key );
					}
				}
				
				console.log( '「最初から開始」：localStorage完全クリア完了' );
			} catch ( error ) {
				console.warn( 'localStorage削除処理でエラーが発生:', error );
			}
			
			// 4. gameState強制初期化（エラーの有無に関係なく実行）
			try {
				if ( gameState ) {
					gameState.reset();
					gameState.isEndingScene = false;
					gameState.currentGameTitle = '';
					gameState.currentSceneUrl = '';
					console.log( '「最初から開始」：gameState完全初期化完了' );
				}
			} catch ( stateError ) {
				console.warn( 'gameState初期化でエラーが発生:', stateError );
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
			
			// タイトル画面のボタンを有効化（UI/UX一貫性のため）
			$( '#novel-title-start-new, #novel-title-continue' ).prop( 'disabled', false ).css( 'pointer-events', 'auto' );
			
			// タイトル画面を表示
			$titleScreen.css( 'display', 'flex' ).hide().fadeIn( 300 );
			
			// ゲームデータを一時保存（ボタン押下時に使用）
			window.currentGameSelectionData = gameData;
			console.log( 'showTitleScreen: currentGameSelectionData set to:', window.currentGameSelectionData );
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
		 * タイトル画面のボタンを再有効化する（エラー時の復旧用）
		 */
		function reEnableTitleButtons() {
			console.log( 'Re-enabling title screen buttons' );
			$( '#novel-title-start-new, #novel-title-continue' ).prop( 'disabled', false ).css( 'pointer-events', 'auto' );
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
			
			// タイトル画面のボタンを無効化（UI/UX一貫性のため）
			$( '#novel-title-start-new, #novel-title-continue' ).prop( 'disabled', true ).css( 'pointer-events', 'none' );
			
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
		 * タイトル画面に戻る（エンディングシーン用）
		 */
		function returnToTitleScreen() {
			console.log( 'returnToTitleScreen called' );
			
			// 現在のゲームタイトルを取得
			var gameTitle = currentGameTitle || extractGameTitleFromPage();
			if ( ! gameTitle ) {
				console.warn( 'ゲームタイトルが見つからないため、ゲーム一覧に戻ります' );
				returnToGameList();
				return;
			}
			
			// ゲーム完了時に進捗をクリア
			if ( gameTitle ) {
				clearGameProgress( gameTitle );
				console.log( 'ゲーム完了により進捗をクリアしました:', gameTitle );
			}
			
			// 統一ゲーム状態でエンディングフラグを設定（HTML依存廃止）
			gameState.isEndingScene = false;
			console.log( '統一ゲーム状態でエンディングフラグをリセットしました（タイトル画面復帰時）' );
			
			// 統一ゲーム状態をクリア
			gameState.reset();
			
			// 後方互換変数を更新
			choices = gameState.choices;
			dialogueData = gameState.dialogueData;
			dialogues = gameState.dialogues;
			baseBackground = gameState.baseBackground;
			currentBackground = gameState.currentBackground;
			charactersData = gameState.charactersData;
			// レガシー変数isEndingScene廃止 - gameState.isEndingSceneのみ使用
			console.log( 'Data arrays cleared for title screen return' );
			
			// 統一された状態初期化を使用
			resetAllGameState();
			
			// タイトル画面用のゲームデータを構築
			var gameData = {
				title: gameTitle,
				description: '',
				subtitle: '',
				image: '',
				url: window.location.href
			};
			
			// ゲーム説明文を取得する優先順序
			// 1. DOM内のメタデータから取得
			var gameDescriptionMeta = $( 'meta[name="novel-game-description"]' ).attr( 'content' );
			if ( gameDescriptionMeta ) {
				gameData.description = gameDescriptionMeta.trim();
				console.log( 'Game description from meta tag:', gameData.description );
			}
			
			// 2. DOM内のdata属性から取得
			if ( ! gameData.description ) {
				var gameDataElement = $( '[data-game-description]' ).first();
				if ( gameDataElement.length > 0 ) {
					gameData.description = gameDataElement.attr( 'data-game-description' );
					console.log( 'Game description from data attribute:', gameData.description );
				}
			}
			
			// 3. ページコンテンツから説明文を抽出
			if ( ! gameData.description ) {
				var gameDescriptionElement = $( '.novel-game-description, .game-description, #novel-game-description' ).first();
				if ( gameDescriptionElement.length > 0 ) {
					gameData.description = gameDescriptionElement.text().trim();
					console.log( 'Game description from content element:', gameData.description );
				}
			}
			
			// 4. ページの要約情報から取得
			if ( ! gameData.description ) {
				var excerptMeta = $( 'meta[name="description"]' ).attr( 'content' );
				if ( excerptMeta && excerptMeta.length < 200 ) { // 長すぎる場合は除外
					gameData.description = excerptMeta.trim();
					console.log( 'Game description from page meta description:', gameData.description );
				}
			}
			
			// サブタイトルの取得
			var gameSubtitleMeta = $( 'meta[name="novel-game-subtitle"]' ).attr( 'content' );
			if ( gameSubtitleMeta ) {
				gameData.subtitle = gameSubtitleMeta.trim();
			} else {
				var gameSubtitleElement = $( '[data-game-subtitle]' ).first();
				if ( gameSubtitleElement.length > 0 ) {
					gameData.subtitle = gameSubtitleElement.attr( 'data-game-subtitle' );
				}
			}
			
			// 背景画像の設定
			if ( baseBackground ) {
				gameData.image = baseBackground;
			} else if ( currentBackground ) {
				gameData.image = currentBackground;
			} else {
				// DOM内から背景画像を探す
				var gameImageMeta = $( 'meta[name="novel-game-image"]' ).attr( 'content' );
				if ( gameImageMeta ) {
					gameData.image = gameImageMeta;
				} else {
					var gameImageElement = $( '[data-game-image]' ).first();
					if ( gameImageElement.length > 0 ) {
						gameData.image = gameImageElement.attr( 'data-game-image' );
					}
				}
			}
			
			console.log( 'タイトル画面用ゲームデータを構築しました:', gameData );
			
			// タイトル画面を表示し、確実にcurrentGameSelectionDataを再生成する
			setTimeout( function() {
				showTitleScreen( gameData );
				
				// showTitleScreen後に確実にcurrentGameSelectionDataが設定されているかチェック
				setTimeout( function() {
					if ( ! window.currentGameSelectionData ) {
						console.warn( 'currentGameSelectionData was not set by showTitleScreen, regenerating...' );
						window.currentGameSelectionData = gameData;
						console.log( 'currentGameSelectionData regenerated:', window.currentGameSelectionData );
					} else {
						console.log( 'currentGameSelectionData successfully set:', window.currentGameSelectionData );
					}
				}, 100 );
			}, 500 );
			
			console.log( 'エンディング完了、タイトル画面に戻ります' );
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
							
							// エンディングフラグは統一ゲーム状態で管理（AJAX取得廃止）
							// gameState.isEndingScene の値を変更せず、現在の状態を保持
							// レガシー変数isEndingScene廃止 - gameState.isEndingSceneのみ使用
							console.log( 'Ending flags managed by unified state (no AJAX dependency)', { gameStateIsEndingScene: gameState.isEndingScene } );
							
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
					// エラー時はタイトルボタンを再有効化
					reEnableTitleButtons();
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
							try {
								initializeGameContent();
							} catch ( error ) {
								console.error( 'ゲーム初期化中にエラーが発生:', error );
								// エラー時はタイトルボタンを再有効化
								reEnableTitleButtons();
							}
						}, 100 );
					} ).catch( function( error ) {
						console.error( '進捗チェック中にエラーが発生:', error );
						// エラー時はタイトルボタンを再有効化
						reEnableTitleButtons();
					} );
				} ).catch( function( error ) {
					console.error( 'ゲームの読み込みに失敗しました:', error );
					// エラー時はタイトルボタンを再有効化
					reEnableTitleButtons();
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
						try {
							initializeGameContent();
						} catch ( error ) {
							console.error( 'ゲーム初期化中にエラーが発生:', error );
							// エラー時はタイトルボタンを再有効化
							reEnableTitleButtons();
						}
					}, 100 );
				} ).catch( function( error ) {
					console.error( '進捗チェック中にエラーが発生:', error );
					// エラー時はタイトルボタンを再有効化
					reEnableTitleButtons();
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
				
				console.log( '保存されたゲーム進捗が見つかりました:', savedProgress );
				
				// 進捗確認ダイアログを表示
				showResumeDialog( savedProgress ).then( function( shouldResume ) {
					if ( shouldResume ) {
						console.log( '進捗から再開します' );
						resumeFromSavedProgress( savedProgress ).then( function() {
							resolve();
						} ).catch( function() {
							// 復元に失敗した場合は最初から開始
							console.log( '進捗復元に失敗したため、最初から開始します' );
							// 復元失敗時も最初から開始時と同様に初期化
							// 統一ゲーム状態で進行状況とフラグを初期化（HTML依存廃止）
							gameState.currentPageIndex = 0;
							gameState.currentDialogueIndex = 0;
							gameState.isEndingScene = false;
							
							console.log( '進捗復元失敗のため、統一ゲーム状態で進行状況とフラグを初期化しました' );
							
							// 統一された初期化処理を使用
							initializeNewGame( currentGameTitle, currentSceneUrl );
							resolve();
						} );
					} else {
						console.log( '最初から開始します' );
						// 保存された進捗をクリアして新ゲーム開始
						clearGameProgress( currentGameTitle );
						// 統一ゲーム状態で最初から開始時の進行状況・フラグを初期化
						gameState.currentPageIndex = 0;
						gameState.currentDialogueIndex = 0;
						gameState.isEndingScene = false;
						
						console.log( '「最初から開始」選択のため、統一ゲーム状態で進行状況とフラグを初期化しました' );
						
						// 統一された初期化処理を使用
						initializeNewGame( currentGameTitle, currentSceneUrl );
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
		 * 進捗状態を復元する（「続きから再開」専用）
		 *
		 * @param {object} savedProgress 保存された進捗データ
		 * @since 1.2.0
		 */
		function restoreProgressState( savedProgress ) {
			console.log( '統一ゲーム状態で進捗状態を復元:', savedProgress );
			
			// 統一ゲーム状態に進捗インデックスを復元
			if ( typeof savedProgress.currentPageIndex === 'number' && savedProgress.currentPageIndex >= 0 ) {
				gameState.currentPageIndex = savedProgress.currentPageIndex;
			}
			
			if ( typeof savedProgress.currentDialogueIndex === 'number' && savedProgress.currentDialogueIndex >= 0 ) {
				gameState.currentDialogueIndex = savedProgress.currentDialogueIndex;
			}
			
			// 統一フラグを復元（「続きから再開」時のみ実行）
			if ( savedProgress.flags ) {
				gameState.setFlagsFromString( savedProgress.flags );
				console.log( '統一フラグを復元しました:', savedProgress.flags );
			}
			
			console.log( '復元された統一ゲーム状態:', {
				currentPageIndex: gameState.currentPageIndex,
				currentDialogueIndex: gameState.currentDialogueIndex,
				isEndingScene: gameState.isEndingScene
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
			
			// ゲーム状態を完全にリセット
			resetAllGameData();
		}

		/**
		 * 全ゲームデータを完全にリセット（データと表示状態の両方）
		 * 
		 * @since 1.2.0
		 */
		function resetAllGameData() {
			console.log( 'Resetting all game data and state...' );
			
			// ゲームデータ本体を完全にリセット
			dialogueData = [];
			dialogues = [];
			choices = [];
			baseBackground = '';
			currentBackground = '';
			charactersData = {};
			// レガシー変数isEndingScene廃止 - gameState.isEndingSceneのみ使用
			
			// ゲーム進行状態をリセット
			currentGameTitle = '';
			currentSceneUrl = '';
			
			// ページング状態をリセット
			currentDialogueIndex = 0;
			currentPageIndex = 0;
			currentDialoguePages = [];
			allDialoguePages = [];
			dialogueIndex = 0;
			
			// 一時保存されたゲームデータをクリア
			if ( window.currentGameSelectionData ) {
				window.currentGameSelectionData = null;
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
			
			// イベントハンドラーをクリーンアップ
			$( document ).off( 'keydown.novel-dialogue keydown.novel-end keydown.novel-end-ending click.novel-end-ending touchend.novel-end-ending' );
			
			console.log( 'All game data and state reset completed' );
		}

		/**
		 * ゲーム状態をリセット（表示状態のみ、データはクリアしない）
		 * 
		 * @deprecated 1.2.0 Use resetAllGameData() for complete reset instead
		 */
		/**
		 * ゲーム表示状態のリセット（UI表示のみ）
		 * 
		 * @since 1.0.0
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
		 * HTMLからゲームデータを再読み込みする関数
		 * resetAllGameData()でデータ配列をクリアした後に、HTMLから再度データを取得する
		 * 
		 * @param {boolean} isNewGame 新ゲーム開始時はtrueを渡してエンディングフラグの復旧を防ぐ
		 * @since 1.0.0
		 */
		function reloadGameDataFromHTML( isNewGame ) {
			console.log( 'Reloading game data from HTML (using unified game state)...', { isNewGame: isNewGame } );
			
			try {
				// 新ゲーム開始時は一切のHTML/localStorage参照を排除し、完全初期化のみ実行
				if ( isNewGame ) {
					console.log( '新ゲーム開始モード：HTML/localStorage一切参照せず、強制初期化を実行' );
					gameState.isEndingScene = false;
					gameState.dialogueData = [];
					gameState.dialogues = [];
					gameState.choices = [];
					gameState.currentPageIndex = 0;
					gameState.currentDialogueIndex = 0;
					
					// HTML側のエンディングフラグも必ず'false'にリセット（新ゲーム開始フラグも設定）
					$( '#novel-ending-scene-flag' ).text( 'false' ).attr( 'data-new-game', 'true' );
					console.log( '新ゲーム開始のため、全フラグ・データを強制初期化しました（HTML側エンディングフラグも\'false\'にリセット、新ゲームフラグ設定）' );
					return true;
				}
				
				var dialogueDataRaw = $( '#novel-dialogue-data' ).text();
				var choicesData = $( '#novel-choices-data' ).text();
				var baseBackgroundData = $( '#novel-base-background' ).text();
				var charactersDataRaw = $( '#novel-characters-data' ).text();
				var endingSceneFlagData = $( '#novel-ending-scene-flag' ).text();

				if ( dialogueDataRaw ) {
					gameState.dialogueData = JSON.parse( dialogueDataRaw );
					
					// 後方互換性のため、文字列配列の場合は変換
					if ( gameState.dialogueData.length > 0 && typeof gameState.dialogueData[0] === 'string' ) {
						gameState.dialogueData = gameState.dialogueData.map( function( text ) {
							return { text: text, background: '', speaker: '' };
						} );
					}
					
					// 旧形式のために dialogues 配列も維持
					gameState.dialogues = gameState.dialogueData.map( function( item ) {
						return item.text;
					} );
					
					console.log( 'Reloaded dialogue data to unified state, length:', gameState.dialogueData.length );
				}

				if ( choicesData ) {
					gameState.choices = JSON.parse( choicesData );
					console.log( 'Reloaded choices data to unified state, length:', gameState.choices.length );
				}
				
				if ( baseBackgroundData ) {
					gameState.baseBackground = JSON.parse( baseBackgroundData );
					gameState.currentBackground = gameState.baseBackground;
					console.log( 'Reloaded background data to unified state' );
				}
				
				if ( charactersDataRaw ) {
					gameState.charactersData = JSON.parse( charactersDataRaw );
					console.log( 'Reloaded characters data to unified state' );
				}
				
				// エンディングフラグの読み込み（通常ゲーム時のみ、新ゲーム開始時は読み込み禁止）
				console.log( 'エンディングフラグ読み込み開始' );
				console.log( 'endingSceneFlagData (raw):', endingSceneFlagData );
				
				// 新ゲーム開始フラグをチェック
				var isNewGameFlag = $( '#novel-ending-scene-flag' ).attr( 'data-new-game' ) === 'true';
				console.log( 'isNewGameFlag:', isNewGameFlag );
				
				if ( endingSceneFlagData && endingSceneFlagData.trim() !== '' && !isNewGameFlag ) {
					try {
						var parsedEndingFlag = JSON.parse( endingSceneFlagData );
						gameState.isEndingScene = parsedEndingFlag;
						console.log( 'エンディングシーンフラグを正常に読み込みました:', {
							raw: endingSceneFlagData,
							parsed: parsedEndingFlag,
							gameState: gameState.isEndingScene
						} );
					} catch ( parseError ) {
						console.error( 'エンディングフラグのパースに失敗:', parseError );
						gameState.isEndingScene = false;
					}
				} else {
					console.log( 'エンディングフラグデータが空または存在しません - falseに設定' );
					gameState.isEndingScene = false;
				}
				
				console.log( 'Game data reloaded successfully to unified state from HTML' );
				return true;
			} catch ( error ) {
				console.error( 'HTMLからの統一ゲーム状態への再読み込みに失敗しました:', error );
				return false;
			}
		}

		/**
		 * ゲーム状態・フラグのみを初期化する関数（責務分離）
		 * データ配列の操作は行わず、状態管理のみに専念
		 * 
		 * @since 1.0.0
		 */
		function resetAllGameState() {
			console.log( 'Resetting all game state and flags...' );
			
			// セリフ・対話進行状況の初期化
			currentDialogueIndex = 0;
			currentPageIndex = 0;
			currentDialoguePages = [];
			allDialoguePages = [];
			dialogueIndex = 0;
			
			// ゲーム状態フラグのリセット（エンディングフラグを完全初期化）
			// レガシー変数isEndingScene廃止 - gameState.isEndingSceneのみ使用
			
			// ゲーム情報の初期化
			currentGameTitle = '';
			currentSceneUrl = '';
			
			// DOM要素の表示状態をリセット
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
			
			// キャラクターの状態を完全リセット
			$( '.novel-character' ).removeClass( 'speaking not-speaking' );
			
			// 全てのゲーム関連イベントハンドラーをクリーンアップ
			$( document ).off( 'keydown.novel-dialogue keydown.novel-end keydown.novel-end-ending' );
			$gameContainer.off( 'click.novel-end-ending touchend.novel-end-ending' );
			$( window ).off( 'resize.game orientationchange.game' );
			$gameContainer.off( '.novel-game' );
			
			// 一時データのクリア（デバッグログ付き）
			if ( window.currentGameSelectionData ) {
				console.log( 'Clearing currentGameSelectionData:', window.currentGameSelectionData );
				window.currentGameSelectionData = null;
			} else {
				console.log( 'currentGameSelectionData was already null' );
			}
			
			console.log( 'All game state and flags reset completed' );
		}

		/**
		 * 新ゲーム開始用の初期化処理（一元化）
		 * データ再取得→状態初期化→ゲーム情報設定の流れを統一
		 * 
		 * @param {string} gameTitle ゲームタイトル
		 * @param {string} sceneUrl シーンURL
		 * @since 1.0.0
		 */
		function initializeNewGame( gameTitle, sceneUrl ) {
			console.log( 'Initializing new game:', { gameTitle: gameTitle, sceneUrl: sceneUrl } );
			
			// 1. HTMLからデータを再取得（新ゲーム開始時はエンディングフラグを初期化）
			var reloadSuccess = reloadGameDataFromHTML( true );
			if ( ! reloadSuccess ) {
				console.error( 'Failed to reload game data from HTML' );
				return false;
			}
			
			// 2. 全ゲーム状態を初期化（データ再取得後に必ず実行）
			resetAllGameState();
			
			// 3. 新ゲーム開始時はエンディングフラグを強制的にfalseに設定
			// reloadGameDataFromHTMLでHTMLから再読み込みされた場合も確実に初期化
			// レガシー変数isEndingScene廃止 - gameState.isEndingSceneのみ使用
			console.log( '新ゲーム開始のため、エンディングフラグを統一ゲーム状態で初期化しました' );
			
			// 4. 新ゲーム開始時は進行インデックスも確実に0に設定
			currentPageIndex = 0;
			currentDialogueIndex = 0;
			console.log( '新ゲーム開始のため、進行インデックスを強制初期化しました' );
			
			// 5. ゲーム情報を再設定
			setCurrentGameInfo( gameTitle || '', sceneUrl || window.location.href );
			
			console.log( 'New game initialization completed successfully' );
			return true;
		}

		/**
		 * 「最初から開始」ボタンからの新ゲーム開始処理（完全簡素化版）
		 * 過去の保存情報を完全に削除し、gameStateのみで新ゲーム状態を管理
		 * 
		 * @param {string} gameTitle ゲームタイトル
		 * @param {string} sceneUrl シーンURL
		 * @since 1.0.0
		 */
		function startNewGameFromTitle( gameTitle, sceneUrl ) {
			console.log( '「最初から開始」：新ゲーム開始処理を開始します', { gameTitle: gameTitle, sceneUrl: sceneUrl } );
			
			try {
				// 1. 全データ完全削除：localStorage・進捗・フラグ情報をすべて削除
				clearGameProgress( gameTitle );
				console.log( '「最初から開始」：全データ削除完了' );
				
				// 2. gameState完全初期化：一切の古いデータを参照しない
				gameState.reset();
				gameState.setNewGame( gameTitle, sceneUrl );
				gameState.isEndingScene = false; // 必ずfalseで開始
				console.log( '「最初から開始」：gameState完全初期化完了' );
				
				// 3. HTML要素の新ゲーム用初期化
				$( '#novel-ending-scene-flag' ).text( 'false' ).attr( 'data-new-game', 'true' );
				console.log( '「最初から開始」：HTML要素初期化完了' );
				
				// 4. 新ゲーム用データ読み込み：古いデータを一切参照しない
				gameState.dialogueData = [];
				gameState.dialogues = [];
				gameState.choices = [];
				gameState.currentPageIndex = 0;
				gameState.currentDialogueIndex = 0;
				
				// HTMLから最初のシーンデータを読み込み（新ゲームフラグ付き）
				if ( reloadGameDataFromHTML( true ) ) {
					console.log( '「最初から開始」：シーンデータ読み込み完了' );
				} else {
					console.error( '「最初から開始」：シーンデータ読み込みに失敗' );
					return false;
				}
				
				// 5. タイトル画面を非表示にしてゲーム開始
				hideTitleScreen();
				setTimeout( function() {
					try {
						initializeGameContent( true ); // 新ゲーム強制フラグ
						console.log( '「最初から開始」：新ゲーム開始完了' );
					} catch ( error ) {
						console.error( '「最初から開始」：ゲーム初期化エラー:', error );
					}
				}, 300 );
				
				return true;
			} catch ( error ) {
				console.error( '「最初から開始」：処理中にエラーが発生:', error );
				return false;
			}
		}

		/**
		 * タイトル画面からのゲーム再開処理（一元化）
		 * 進捗復元→フォールバック→画面遷移の流れを統一
		 * 
		 * @param {string} gameTitle ゲームタイトル
		 * @param {string} sceneUrl シーンURL
		 * @since 1.0.0
		 */
		function resumeGameFromTitle( gameTitle, sceneUrl ) {
			console.log( 'Resuming game from title screen:', { gameTitle: gameTitle, sceneUrl: sceneUrl } );
			
			try {
				// 保存された進捗を取得
				var savedProgress = getSavedGameProgress( gameTitle );
				if ( savedProgress ) {
					console.log( '保存された進捗から再開します' );
					
					// タイトル画面を非表示にして進捗復元
					hideTitleScreen();
					setTimeout( function() {
						// 保存された進捗データから状態を復元
						resumeFromSavedProgress( savedProgress ).catch( function( error ) {
							console.error( '進捗復元に失敗しました:', error );
							// フォールバック：最初から開始
							console.log( 'フォールバック: 最初から開始します' );
							
							// 進捗復元失敗時はgameStateで統一管理し、グローバル変数依存を排除
							gameState.currentPageIndex = 0;
							gameState.currentDialogueIndex = 0;
							gameState.isEndingScene = false;
							gameState.currentGameTitle = gameTitle || '';
							
							console.log( '進捗復元失敗のため、gameStateで進行状況とフラグを初期化しました' );
							
							if ( ! initializeNewGame( gameTitle, sceneUrl ) ) {
								console.error( 'Failed to initialize fallback new game' );
								return;
							}
							
							try {
								initializeGameContent( true ); // 新ゲーム強制フラグを渡す
							} catch ( error ) {
								console.error( 'Error during fallback game initialization:', error );
							}
						} );
					}, 300 );
				} else {
					console.log( '保存された進捗が見つかりません。最初から開始します。' );
					// 進捗がない場合は最初から開始
					return startNewGameFromTitle( gameTitle, sceneUrl );
				}
				
				return true;
			} catch ( error ) {
				console.error( 'ゲーム再開処理中にエラーが発生:', error );
				return false;
			}
		}

		/**
		 * 全ゲームデータと状態の包括的リセット（後方互換性維持）
		 * 
		 * @deprecated 新しいコードでは initializeNewGame() を使用してください
		 * @param {boolean} clearDataArrays - セリフ・選択肢データ配列をクリアするかどうか（デフォルト: true）
		 * @param {boolean} reloadData - データ配列クリア後にHTMLから再読み込みするかどうか（デフォルト: false）
		 * @since 1.0.0
		 */
		function resetAllGameData( clearDataArrays, reloadData ) {
			// clearDataArraysが未指定の場合はtrueをデフォルト値とする
			if ( typeof clearDataArrays === 'undefined' ) {
				clearDataArrays = true;
			}
			// reloadDataが未指定の場合はfalseをデフォルト値とする
			if ( typeof reloadData === 'undefined' ) {
				reloadData = false;
			}
			
			console.log( 'Resetting all game data and state... clearDataArrays:', clearDataArrays, 'reloadData:', reloadData );
			
			// 選択肢とセリフデータのリセット（オプション）
			// シーン遷移時は新データがロード後に上書きされるため、クリア不要
			if ( clearDataArrays ) {
				choices = [];
				dialogueData = [];
				dialogues = [];
				baseBackground = '';
				currentBackground = '';
				charactersData = {};
				console.log( 'Data arrays cleared (complete reset)' );
			} else {
				console.log( 'Data arrays preserved (partial reset for scene transition)' );
			}
			
			// 統一された状態初期化を使用
			resetAllGameState();
			
			// データ配列をクリアした後、HTMLから再読み込みする場合
			if ( clearDataArrays && reloadData ) {
				console.log( 'Reloading data from HTML after reset...' );
				reloadGameDataFromHTML( false );
			}
			
			console.log( 'All game data and state reset completed. Game state summary:', {
				currentGameTitle: currentGameTitle,
				currentSceneUrl: currentSceneUrl,
				gameStateIsEndingScene: gameState.isEndingScene, // レガシー変数isEndingScene廃止
				currentPageIndex: currentPageIndex,
				currentDialogueIndex: currentDialogueIndex,
				dialogueDataLength: dialogueData.length,
				choicesLength: choices.length,
				currentGameSelectionData: window.currentGameSelectionData
			} );
		}

		/**
		 * モーダルイベントハンドラーの設定
		 * 
		 * イベント委譲を使用しているため、対象のDOM要素が存在しない状態で呼び出しても安全です。
		 * DOM要素が後から動的に生成された場合でも、正しくイベントが機能します。
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
				console.log( 'Start new game button clicked' );
				
				var $button = $( '#novel-title-start-new' );
				
				// ボタンを無効化（重複クリック防止）
				$button.prop( 'disabled', true ).css( 'pointer-events', 'none' );
				
				try {
					// ゲームタイトルを取得
					var gameTitle = extractGameTitleFromPage();
					if ( ! gameTitle ) {
						console.error( 'Game title not found' );
						$button.prop( 'disabled', false ).css( 'pointer-events', 'auto' );
						return;
					}
					
					console.log( 'Starting new game:', gameTitle );
					
					// startNewGameFromTitle関数を呼び出してロジックを一元化
					var currentSceneUrl = window.location.href;
					if ( startNewGameFromTitle( gameTitle, currentSceneUrl ) ) {
						console.log( 'New game started successfully from title screen' );
					} else {
						console.error( 'Failed to start new game from title screen' );
						$button.prop( 'disabled', false ).css( 'pointer-events', 'auto' );
					}
					
				} catch ( error ) {
					console.error( 'Error starting new game:', error );
					$button.prop( 'disabled', false ).css( 'pointer-events', 'auto' );
				}
			} );
			
			// タイトル画面：続きから始めるボタン（委譲イベント）
			$( document ).on( 'click', '#novel-title-continue', function( e ) {
				e.preventDefault();
				console.log( 'Continue game button clicked' );
				
				var $button = $( '#novel-title-continue' );
				
				// ボタンを無効化（重複クリック防止）
				$button.prop( 'disabled', true ).css( 'pointer-events', 'none' );
				
				try {
					// ゲームタイトルを取得
					var gameTitle = extractGameTitleFromPage();
					if ( ! gameTitle ) {
						console.error( 'Game title not found' );
						$button.prop( 'disabled', false ).css( 'pointer-events', 'auto' );
						return;
					}
					
					console.log( 'Continuing game:', gameTitle );
					
					// resumeGameFromTitle関数を呼び出してロジックを一元化
					var currentSceneUrl = window.location.href;
					if ( resumeGameFromTitle( gameTitle, currentSceneUrl ) ) {
						console.log( 'Game resumed successfully from title screen' );
					} else {
						console.error( 'Failed to resume game from title screen' );
						$button.prop( 'disabled', false ).css( 'pointer-events', 'auto' );
					}
					
				} catch ( error ) {
					console.error( 'Error continuing game:', error );
					$button.prop( 'disabled', false ).css( 'pointer-events', 'auto' );
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
			console.log( 'showChoices called, choices.length:', choices.length );
			console.log( 'Current ending scene flag (gameState.isEndingScene):', gameState.isEndingScene );
			
			if ( choices.length === 0 ) {
				console.warn( 'No choices available - showing game end. This may indicate a data loading issue.' );
				console.log( 'Current game state:', {
					currentGameTitle: currentGameTitle,
					currentSceneUrl: currentSceneUrl,
					dialogueData_length: dialogueData.length,
					dialogues_length: dialogues.length,
					gameStateIsEndingScene: gameState.isEndingScene
				} );
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
					
					// 1. 表示状態をリセット（データクリアは新データロード後に実行）
					resetGameState();
					
					// 2. 新しいシーンのデータを読み込み
					loadGameData( nextScene ).then( function() {
						// 3. 新データロード成功後に古いデータを完全にクリア
						// （この時点で新しいデータがすでにロードされている）
						console.log( 'Scene transition successful, new data loaded' );
						
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
			console.log( 'showGameEnd called' );
			console.log( 'gameState.isEndingScene:', gameState.isEndingScene );
			
			// HTMLからエンディングフラグを再確認（最新状態の保証）
			var htmlEndingFlag = $( '#novel-ending-scene-flag' ).text();
			console.log( 'HTML ending flag text:', htmlEndingFlag );
			
			var htmlEndingValue = false;
			if ( htmlEndingFlag ) {
				try {
					htmlEndingValue = JSON.parse( htmlEndingFlag );
					console.log( 'Parsed HTML ending flag:', htmlEndingValue );
				} catch ( e ) {
					console.warn( 'Failed to parse HTML ending flag:', e );
				}
			}
			
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
			
			// エンディングシーンかどうかを複数の方法で確実に判定（レガシー変数isEndingScene廃止）
			var currentIsEndingScene = htmlEndingValue || gameState.isEndingScene;
			console.log( 'Final ending scene determination:', {
				htmlEndingValue: htmlEndingValue,
				gameStateIsEndingScene: gameState.isEndingScene,
				finalDecision: currentIsEndingScene
			} );
			
			// エンディングシーンの場合とそうでない場合で処理を分ける
			if ( currentIsEndingScene ) {
				// エンディングシーンの場合：クリックでタイトル画面に戻る
				console.log( 'エンディングシーンです。クリックでタイトル画面に戻るUIを生成します。' );
				
				// クリック指示メッセージを追加
				var $clickMessage = $( '<div>' )
					.addClass( 'ending-click-instruction' )
					.text( 'クリックしてタイトル画面に戻る' )
					.css( {
						'text-align': 'center',
						'margin': '20px 0',
						'font-size': '16px',
						'color': '#666'
					} );
				
				$choicesContainer.append( $clickMessage );
				console.log( 'クリック指示メッセージを追加しました' );
				
				// 明示的な「タイトルに戻る」ボタンを追加（確実なUI/UX）
				var $returnButton = $( '<button>' )
					.addClass( 'game-nav-button ending-return-button' )
					.text( 'タイトル画面に戻る' )
					.css( {
						'margin-top': '20px',
						'padding': '15px 25px',
						'font-size': '18px',
						'background-color': '#0073aa',
						'color': 'white',
						'border': 'none',
						'border-radius': '8px',
						'cursor': 'pointer',
						'font-weight': 'bold',
						'min-width': '200px',
						'box-shadow': '0 4px 8px rgba(0, 115, 170, 0.3)',
						'transition': 'all 0.3s ease',
						'display': 'block',
						'margin-left': 'auto',
						'margin-right': 'auto'
					} );
				
				$choicesContainer.append( $returnButton );
				console.log( 'タイトル画面に戻るボタンを追加しました' );
				
				// ボタンが実際に存在するかチェック
				setTimeout( function() {
					var buttonExists = $( '.ending-return-button' ).length > 0;
					console.log( 'エンディングボタン存在確認:', buttonExists );
					if ( buttonExists ) {
						console.log( 'ボタンのCSS情報:', $( '.ending-return-button' ).css( ['display', 'visibility', 'opacity'] ) );
					}
				}, 100 );
				
				// エンディング用のクリックハンドラー（gameState.reset()とタイトル画面表示を確実に実行）
				var endingClickHandler = function( e ) {
					e.preventDefault();
					e.stopPropagation();
					
					console.log( 'エンディング完了 - ゲーム状態をリセットしてタイトル画面に戻ります' );
					
					try {
						// イベントハンドラーを削除（重複実行防止）
						$gameContainer.off( 'click.novel-end-ending touchend.novel-end-ending' );
						$( document ).off( 'keydown.novel-end-ending' );
						$returnButton.off( 'click' );
						
						// 統一ゲーム状態を確実にリセット
						gameState.reset();
						console.log( 'gameState.reset() を実行しました' );
						
						// 後方互換変数も更新（レガシー変数isEndingScene廃止）
						currentPageIndex = gameState.currentPageIndex;
						currentDialogueIndex = gameState.currentDialogueIndex;
						currentGameTitle = gameState.currentGameTitle;
						currentSceneUrl = gameState.currentSceneUrl;
						
						console.log( 'ゲーム状態変数を更新しました' );
						
						// タイトル画面表示（showTitleScreen関数を確実に呼び出し）
						if ( typeof returnToTitleScreen === 'function' ) {
							returnToTitleScreen();
							console.log( 'タイトル画面復帰処理を実行しました' );
						} else {
							console.warn( 'returnToTitleScreen関数が見つかりません' );
							// フォールバック：手動でタイトル画面を表示
							if ( $choicesContainer.length > 0 ) {
								$choicesContainer.html( '<p style="color: green; font-weight: bold; text-align: center; padding: 20px;">ゲーム終了！<br>ページを再読み込みしてもう一度プレイできます。</p>' );
							}
						}
						
					} catch ( error ) {
						console.error( 'エンディング処理中にエラーが発生しました:', error );
						// エラー時のフォールバック
						if ( $choicesContainer.length > 0 ) {
							$choicesContainer.html( '<p style="color: red; font-weight: bold; text-align: center; padding: 20px;">エラーが発生しました。<br>ページを再読み込みしてください。</p>' );
						}
					}
				};
				
				// ボタンクリックイベント
				$returnButton.on( 'click', endingClickHandler );
				
				// 画面全体のクリックイベント（エンディング用）
				$gameContainer.on( 'click.novel-end-ending touchend.novel-end-ending', endingClickHandler );
				
				// キーボードイベントでもタイトル画面に戻る
				$( document ).on( 'keydown.novel-end-ending', function( e ) {
					if ( e.which === 13 || e.which === 32 ) { // Enter or Space
						endingClickHandler( e );
					}
				} );
				
			} else {
				// 通常のエンディング処理（既存のコード）
				console.log( '通常のエンディングです。ナビゲーションボタンを表示します。' );
				
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
				
				// キーボードイベントでもナビゲーション（通常のエンディング用）
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
		 * エンディング機能の完全性をテストする（デバッグ用）
		 */
		function validateEndingImplementation() {
			console.log( '=== エンディング機能実装検証 ===' );
			
			var validationResults = {
				phpOutput: false,
				jsLoading: false,
				uiGeneration: false,
				eventBinding: false,
				stateManagement: false
			};
			
			try {
				// 1. PHP側のエンディングフラグ出力をチェック
				var $endingFlagElement = $( '#novel-ending-scene-flag' );
				if ( $endingFlagElement.length > 0 ) {
					validationResults.phpOutput = true;
					console.log( '✓ PHP側エンディングフラグ出力: 正常' );
					console.log( '  - エレメント存在:', $endingFlagElement.length );
					console.log( '  - 現在の値:', $endingFlagElement.text() );
				} else {
					console.log( '✗ PHP側エンディングフラグ出力: 失敗 - #novel-ending-scene-flag が見つかりません' );
				}
				
				// 2. JavaScript側でのエンディングフラグ読み込みをチェック
				if ( typeof gameState !== 'undefined' ) {
					validationResults.jsLoading = true;
					console.log( '✓ JavaScript側フラグ読み込み: 正常' );
					console.log( '  - gameState.isEndingScene:', gameState.isEndingScene );
					// レガシー変数isEndingScene廃止
				} else {
					console.log( '✗ JavaScript側フラグ読み込み: 失敗 - gameState変数が定義されていません' );
				}
				
				// 3. UI生成機能をチェック
				if ( typeof showGameEnd === 'function' ) {
					validationResults.uiGeneration = true;
					console.log( '✓ エンディングUI生成機能: 正常' );
				} else {
					console.log( '✗ エンディングUI生成機能: 失敗 - showGameEnd関数が見つかりません' );
				}
				
				// 4. イベントバインディング機能をチェック（選択肢コンテナが存在する場合）
				var $choicesContainer = $( '#novel-choices' );
				if ( $choicesContainer.length > 0 ) {
					validationResults.eventBinding = true;
					console.log( '✓ イベントバインディング環境: 正常' );
					console.log( '  - 選択肢コンテナ存在:', $choicesContainer.length );
				} else {
					console.log( '✗ イベントバインディング環境: 警告 - #novel-choices が見つかりません' );
				}
				
				// 5. ゲーム状態管理をチェック
				if ( typeof gameState !== 'undefined' && typeof gameState.reset === 'function' ) {
					validationResults.stateManagement = true;
					console.log( '✓ ゲーム状態管理: 正常' );
				} else {
					console.log( '✗ ゲーム状態管理: 失敗 - gameState.reset が利用できません' );
				}
				
				// 結果サマリー
				var passCount = Object.values( validationResults ).filter( Boolean ).length;
				var totalCount = Object.keys( validationResults ).length;
				
				console.log( '=== 検証結果サマリー ===' );
				console.log( '合格:', passCount + '/' + totalCount );
				
				if ( passCount === totalCount ) {
					console.log( '🎉 エンディング機能は正常に実装されています！' );
					return true;
				} else {
					console.log( '⚠️ エンディング機能に問題があります。上記のエラーを確認してください。' );
					return false;
				}
				
			} catch ( error ) {
				console.error( 'エンディング機能検証中にエラーが発生:', error );
				return false;
			}
		}
		
		/**
		 * エンディング状態を強制的に設定する（デバッグ・テスト用）
		 */
		function forceEndingMode() {
			console.log( 'Force ending mode activated' );
			gameState.isEndingScene = true;
			// レガシー変数isEndingScene廃止 - gameState.isEndingSceneのみ使用
			
			// HTMLエレメントも更新
			var $endingFlag = $( '#novel-ending-scene-flag' );
			if ( $endingFlag.length > 0 ) {
				$endingFlag.text( 'true' );
			}
			
			console.log( 'Ending mode forced. gameState.isEndingScene:', gameState.isEndingScene );
		}
		
		/**
		 * 現在のエンディング状態を確認する（デバッグ用）
		 */
		function checkEndingStatus() {
			var htmlFlag = $( '#novel-ending-scene-flag' ).text();
			console.log( 'Ending status check:' );
			console.log( '- HTML flag:', htmlFlag );
			console.log( '- gameState.isEndingScene:', gameState.isEndingScene );
			// レガシー変数isEndingScene廃止
			
			return {
				html: htmlFlag,
				gameState: gameState.isEndingScene
				// レガシー変数isEndingScene廃止
			};
		}
		
		/**
		 * エンディング機能をテストする（デバッグ用）
		 */
		function testEndingFunctionality() {
			console.log( '=== エンディング機能テスト開始 ===' );
			
			// 現在の状態をチェック
			var status = checkEndingStatus();
			console.log( '現在の状態:', status );
			
			// エンディングモードを強制的に有効化
			forceEndingMode();
			
			// showGameEnd()を直接呼び出してテスト
			console.log( 'showGameEnd()を直接テスト実行...' );
			showGameEnd();
			
			// 生成されたUIをチェック
			setTimeout( function() {
				var $endMessage = $( '.game-end-message' );
				var $clickMessage = $( '.ending-click-instruction' );
				var $returnButton = $( '.ending-return-button' );
				
				console.log( 'UI要素の生成確認:' );
				console.log( '- おわりメッセージ:', $endMessage.length > 0 );
				console.log( '- クリック指示:', $clickMessage.length > 0 );
				console.log( '- 戻るボタン:', $returnButton.length > 0 );
				
				if ( $returnButton.length > 0 ) {
					console.log( '- ボタンが表示されています:', $returnButton.is( ':visible' ) );
					console.log( '- ボタンのテキスト:', $returnButton.text() );
				}
				
				console.log( '=== エンディング機能テスト完了 ===' );
			}, 200 );
		}
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
		 * 「最初から開始」時はgameStateのみを参照し、古いデータを一切使用しない
		 */
		function initializeGameContent( forceNewGame ) {
			console.log( 'initializeGameContent called with forceNewGame:', forceNewGame );
			
			// 新ゲーム開始時はgameStateで完全初期化
			if ( forceNewGame === true ) {
				gameState.currentPageIndex = 0;
				gameState.currentDialogueIndex = 0;
				gameState.isEndingScene = false;
				console.log( '「最初から開始」：gameStateで強制初期化完了' );
			}
			
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
			// データが空の場合はHTMLから再読み込みを試行
			if ( dialogues.length === 0 && dialogueData.length === 0 ) {
				console.log( 'Dialogue data is empty, attempting to reload from HTML...' );
				if ( reloadGameDataFromHTML( forceNewGame === true ) ) {
					console.log( 'Successfully reloaded data from HTML' );
					
					// 新ゲーム強制開始時はHTMLからの再読み込み後もエンディングフラグを確実に初期化
					if ( forceNewGame === true ) {
						gameState.isEndingScene = false;
						// HTML側のエンディングフラグも必ず'false'にリセット（新ゲーム開始フラグも設定）
						$( '#novel-ending-scene-flag' ).text( 'false' ).attr( 'data-new-game', 'true' );
						// レガシー変数isEndingScene廃止 - gameState.isEndingSceneのみ使用
						console.log( 'Force new game: エンディングフラグを再初期化しました（HTML側も\'false\'にリセット、新ゲームフラグ設定）' );
					}
				} else {
					console.error( 'Failed to reload data from HTML' );
				}
			}
			
			// エンディングフラグの状態を確認・同期
			console.log( 'Ending flag sync check:' );
			console.log( '- gameState.isEndingScene:', gameState.isEndingScene );
			// レガシー変数isEndingScene廃止
			var htmlEndingFlag = $( '#novel-ending-scene-flag' ).text();
			var isNewGameFlag = $( '#novel-ending-scene-flag' ).attr( 'data-new-game' ) === 'true';
			console.log( '- HTML ending flag:', htmlEndingFlag );
			console.log( '- isNewGameFlag:', isNewGameFlag );
			
			// HTMLフラグがある場合は同期（新ゲーム強制時以外、且つ新ゲームフラグが設定されていない場合のみ）
			if ( htmlEndingFlag && forceNewGame !== true && !isNewGameFlag ) {
				try {
					var htmlFlagValue = JSON.parse( htmlEndingFlag );
					if ( gameState.isEndingScene !== htmlFlagValue ) {
						console.log( 'Syncing ending flag from HTML:', htmlFlagValue );
						gameState.isEndingScene = htmlFlagValue;
						// レガシー変数isEndingScene廃止 - gameState.isEndingSceneのみ使用
					}
				} catch ( e ) {
					console.warn( 'Failed to parse HTML ending flag:', e );
				}
			}
			
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
				
				// 新ゲーム開始時は必ず最初から開始
				if ( forceNewGame === true ) {
					console.log( 'Starting new game from first scene' );
					currentPageIndex = 0;
					currentDialogueIndex = 0;
					displayCurrentPage();
				} else if ( currentPageIndex > 0 && currentPageIndex < allDialoguePages.length ) {
					console.log( 'Resuming from saved position:', currentPageIndex );
					displayCurrentPage();
				} else {
					console.log( 'Starting from beginning' );
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
				
				// 新ゲーム初期化完了後、新ゲームフラグをクリア
				if ( forceNewGame === true ) {
					$( '#novel-ending-scene-flag' ).removeAttr( 'data-new-game' );
					console.log( 'New game initialization completed, cleared new game flag' );
				}
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
			
			// モーダルイベントを常に設定（DOM要素の存在有無に関わらず委譲イベントを設定）
			setupModalEvents();
			
			// モーダル要素が存在する場合のみ表示制御を実行
			if ( $modalOverlay.length > 0 ) {
				// 初期状態はモーダルとタイトル画面を非表示
				$modalOverlay.hide();
				$titleScreen.hide();
				
				console.log( 'Modal overlay found and hidden' );
			} else {
				console.log( 'No modal overlay found, but events are still set up for dynamic elements' );
			}
			
			console.log( 'Modal events set up successfully (always executed)' );
			
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
		
		// デバッグ用関数をグローバルに公開
		window.novelGameDebug = {
			forceEndingMode: forceEndingMode,
			checkEndingStatus: checkEndingStatus,
			validateEndingImplementation: validateEndingImplementation,
			testEndingFunctionality: testEndingFunctionality,
			showGameEnd: showGameEnd,
			showChoices: showChoices,
			gameState: gameState
		};
		
		// デバッグ情報を出力
		console.log( 'Novel Game Modal initialized. Modal overlay found:', $modalOverlay.length > 0 );
	} );

} )( jQuery );
