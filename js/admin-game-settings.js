/**
 * ゲーム設定ページ用JavaScript
 *
 * @package NovelGamePlugin
 * @since 1.1.0
 */

jQuery( function( $ ) {
    'use strict';

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
                
                // ファイルバリデーション
                var validation = validateImageFile( attachment );
                if ( ! validation.valid ) {
                    alert( validation.message );
                    return;
                }
                
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
     * ショートコードコピー機能
     */
    function setupShortcodeCopy() {
        $( '.copy-shortcode' ).on( 'click', function( e ) {
            e.preventDefault();
            
            var button = $( this );
            var shortcode = button.data( 'shortcode' );
            
            // クリップボードにコピー
            if ( navigator.clipboard && window.isSecureContext ) {
                // モダンなClipboard API
                navigator.clipboard.writeText( shortcode ).then( function() {
                    showCopySuccess( button );
                } ).catch( function( err ) {
                    console.error( 'クリップボードへのコピーに失敗しました:', err );
                    fallbackCopyText( shortcode, button );
                } );
            } else {
                // フォールバック方式
                fallbackCopyText( shortcode, button );
            }
        } );
    }

    /**
     * コピー成功時の視覚フィードバック
     *
     * @param {jQuery} button クリックされたボタン
     */
    function showCopySuccess( button ) {
        var originalText = button.text();
        button.addClass( 'copy-success' );
        button.text( 'コピーしました！' );
        
        setTimeout( function() {
            button.removeClass( 'copy-success' );
            button.text( originalText );
        }, 2000 );
    }

    /**
     * フォールバック方式でのテキストコピー
     *
     * @param {string} text コピーするテキスト
     * @param {jQuery} button クリックされたボタン
     */
    function fallbackCopyText( text, button ) {
        // 一時的なテキストエリアを作成
        var textArea = document.createElement( 'textarea' );
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild( textArea );
        textArea.focus();
        textArea.select();
        
        try {
            var success = document.execCommand( 'copy' );
            if ( success ) {
                showCopySuccess( button );
            } else {
                alert( 'ショートコードのコピーに失敗しました。手動でコピーしてください。' );
            }
        } catch ( err ) {
            console.error( 'フォールバックコピーに失敗しました:', err );
            alert( 'ショートコードのコピーに失敗しました。手動でコピーしてください。' );
        } finally {
            document.body.removeChild( textArea );
        }
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

        // ショートコードコピー機能を設定
        setupShortcodeCopy();

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