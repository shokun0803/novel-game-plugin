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
        } catch ( e ) {
            console.error( 'ノベルゲームデータの解析に失敗しました:', e );
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
                    .on( 'click', function() {
                        if ( choice.nextScene ) {
                            window.location.href = choice.nextScene;
                        }
                    } );
                    
                $choicesContainer.append( $button );
            } );
            
            $choicesContainer.show();
        }
        
        /**
         * ゲームコンテナのクリックイベント
         */
        function setupGameInteraction() {
            $gameContainer.on( 'click', function( e ) {
                // 選択肢が表示されている場合はクリックを無視
                if ( $choicesContainer.is( ':visible' ) ) {
                    return;
                }
                
                // 次のセリフを表示
                showNextDialogue();
            } );
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
            
            // 最初のセリフを表示
            showNextDialogue();
        }
        
        // ゲームの初期化
        initializeGame();
    } );
    
} )( jQuery );
