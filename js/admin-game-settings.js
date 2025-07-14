/**
 * ゲーム設定ページ用JavaScript
 *
 * @package NovelGamePlugin
 * @since 1.1.0
 */

jQuery( function( $ ) {
    'use strict';

    /**
     * WordPress メディアアップローダーを設定
     *
     * @param {string} buttonId  ボタンのID
     * @param {string} inputId   入力フィールドのID
     * @param {string} previewId プレビュー画像のID
     * @param {string} removeId  削除ボタンのID
     */
    function setupMediaUploader( buttonId, inputId, previewId, removeId ) {
        // 画像選択ボタンのクリック処理
        $( buttonId ).on( 'click', function( e ) {
            e.preventDefault();

            var customUploader = wp.media( {
                title: novelGameSettings.strings.selectImage,
                button: {
                    text: novelGameSettings.strings.useThisImage
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            } );

            customUploader.on( 'select', function() {
                var attachment = customUploader.state().get( 'selection' ).first().toJSON();
                $( inputId ).val( attachment.url );
                $( previewId ).attr( 'src', attachment.url ).show();
                $( removeId ).show();
            } );

            customUploader.open();
        } );

        // 画像削除ボタンのクリック処理
        $( removeId ).on( 'click', function( e ) {
            e.preventDefault();

            if ( confirm( novelGameSettings.strings.confirmRemove ) ) {
                $( inputId ).val( '' );
                $( previewId ).hide();
                $( this ).hide();
            }
        } );
    }

    /**
     * フォームのバリデーション
     */
    function setupFormValidation() {
        $( 'form' ).on( 'submit', function( e ) {
            var gameTitle = $( '#game_title' ).val().trim();
            
            if ( ! gameTitle ) {
                alert( novelGameSettings.strings.titleRequired );
                e.preventDefault();
                return false;
            }

            return true;
        } );
    }

    /**
     * 初期化処理
     */
    function initialize() {
        // タイトル画面画像用のメディアアップローダーを設定
        setupMediaUploader( 
            '#game_title_image_button', 
            '#game_title_image', 
            '#game_title_image_preview',
            '#game_title_image_remove'
        );

        // フォームバリデーションを設定
        setupFormValidation();

        // 文字数カウンター（必要に応じて）
        $( '#game_description' ).on( 'input', function() {
            var currentLength = $( this ).val().length;
            var maxLength = 500; // 最大文字数（例）
            
            if ( currentLength > maxLength ) {
                $( this ).val( $( this ).val().substring( 0, maxLength ) );
            }
        } );
    }

    // 初期化の実行
    initialize();
} );