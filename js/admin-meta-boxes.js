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

	// WordPress ajaxurlの設定
	var ajaxurl = novelGameMeta.ajaxurl;

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
			} );

			customUploader.open();
		} );
	}

	/**
	 * 初期化処理
	 */
	function initializeMetaBox() {
		// 初期描画
		renderChoicesTable();

		// メディアアップローダーの設定
		setupMediaUploader( '#novel_background_image_button', '#novel_background_image', '#novel_background_image_preview' );
		setupMediaUploader( '#novel_character_image_button', '#novel_character_image', '#novel_character_image_preview' );
	}

	/**
	 * イベントリスナーの設定
	 */
	function setupEventListeners() {
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
	}

	// 初期化の実行
	if ( typeof novelGameScenes !== 'undefined' ) {
		scenes = novelGameScenes;
	}

	initializeMetaBox();
	setupEventListeners();
} );