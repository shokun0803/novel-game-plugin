/**
 * 管理画面用のJavaScript
 * 
 * @package NovelGamePlugin
 * @since 1.0.0
 */

( function( $ ) {
    'use strict';
    
    $( document ).ready( function() {
        
        /**
         * 選択肢フィールドのJSON検証
         */
        function validateChoicesJSON() {
            $( '#choices' ).on( 'change', function() {
                var $this = $( this );
                var value = $this.val();
                
                try {
                    if ( value.trim() !== '' ) {
                        JSON.parse( value );
                    }
                    $this.css( 'border', '2px solid #00a32a' );
                } catch ( e ) {
                    $this.css( 'border', '2px solid #d63638' );
                }
            } );
        }
        
        /**
         * 画像URLの検証
         */
        function validateImageURLs() {
            $( '#background_image, #character_image' ).on( 'blur', function() {
                var $this = $( this );
                var url = $this.val();
                
                if ( url && ! url.match( /^https?:\/\// ) ) {
                    alert( 'URLは http:// または https:// で始まる必要があります。' );
                    $this.val( '' );
                }
            } );
        }
        
        /**
         * 初期化処理
         */
        function initialize() {
            validateChoicesJSON();
            validateImageURLs();
        }
        
        // 初期化の実行
        initialize();
    } );
    
} )( jQuery );
