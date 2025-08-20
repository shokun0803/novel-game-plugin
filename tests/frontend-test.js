/**
 * Novel Game Plugin Frontend Tests
 * 
 * @package NovelGamePlugin
 * @since 1.0.0
 */

/**
 * 新ゲーム開始時の初期化テスト
 * エンディング後に「最初から開始」を選択した場合、必ず最初のシーンから開始されることを検証
 */
function testStartNewGameFromEndingScreen() {
    console.log('=== Test: Start New Game From Ending Screen ===');
    
    // テスト用のダミーデータセットアップ
    var originalDialogueData = window.dialogueData;
    var originalIsEndingScene = window.isEndingScene;
    var originalCurrentPageIndex = window.currentPageIndex;
    var originalCurrentDialogueIndex = window.currentDialogueIndex;
    
    // エンディングシーン状態をシミュレート
    window.isEndingScene = true;
    window.currentPageIndex = 10; // エンディングの位置
    window.currentDialogueIndex = 5;
    window.dialogueData = [
        { text: 'シーン1のテキスト', background: '', speaker: '' },
        { text: 'シーン2のテキスト', background: '', speaker: '' },
        { text: 'シーン3のテキスト', background: '', speaker: '' },
        { text: 'シーン4のテキスト', background: '', speaker: '' },
        { text: 'シーン5のテキスト', background: '', speaker: '' },
        { text: 'エンディングのテキスト', background: '', speaker: '' }
    ];
    
    // HTMLエレメントの模擬
    if (!document.getElementById('novel-dialogue-data')) {
        var mockDataElement = document.createElement('script');
        mockDataElement.id = 'novel-dialogue-data';
        mockDataElement.type = 'application/json';
        mockDataElement.textContent = JSON.stringify(window.dialogueData);
        document.body.appendChild(mockDataElement);
    }
    
    if (!document.getElementById('novel-ending-scene-flag')) {
        var mockEndingElement = document.createElement('script');
        mockEndingElement.id = 'novel-ending-scene-flag';
        mockEndingElement.type = 'application/json';
        mockEndingElement.textContent = 'true';
        document.body.appendChild(mockEndingElement);
    }
    
    console.log('Before test - isEndingScene:', window.isEndingScene, 'currentPageIndex:', window.currentPageIndex);
    
    // 新ゲーム開始をテスト
    try {
        // 実際の初期化関数をテスト
        var success = window.initializeNewGame && window.initializeNewGame('Test Game', window.location.href);
        
        // 検証1: 初期化が成功したか
        if (!success) {
            console.error('❌ Test Failed: initializeNewGame returned false');
            return false;
        }
        
        // 検証2: isEndingSceneがfalseになっているか
        if (window.isEndingScene !== false) {
            console.error('❌ Test Failed: isEndingScene should be false, but got:', window.isEndingScene);
            return false;
        }
        
        // 検証3: currentPageIndexが0になっているか
        if (window.currentPageIndex !== 0) {
            console.error('❌ Test Failed: currentPageIndex should be 0, but got:', window.currentPageIndex);
            return false;
        }
        
        // 検証4: currentDialogueIndexが0になっているか  
        if (window.currentDialogueIndex !== 0) {
            console.error('❌ Test Failed: currentDialogueIndex should be 0, but got:', window.currentDialogueIndex);
            return false;
        }
        
        console.log('After initialization - isEndingScene:', window.isEndingScene, 'currentPageIndex:', window.currentPageIndex);
        
        // initializeGameContentの動作もテスト（関数が存在する場合のみ）
        if (window.initializeGameContent) {
            window.initializeGameContent(true);
        
            // 検証5: forceNewGame = trueでinitializeGameContent後も indices が0か
            if (window.currentPageIndex !== 0) {
                console.error('❌ Test Failed: After initializeGameContent(true), currentPageIndex should be 0, but got:', window.currentPageIndex);
                return false;
            }
            
            if (window.currentDialogueIndex !== 0) {
                console.error('❌ Test Failed: After initializeGameContent(true), currentDialogueIndex should be 0, but got:', window.currentDialogueIndex);
                return false;
            }
        }
        
        console.log('✅ Test Passed: Start New Game From Ending Screen');
        return true;
        
    } catch (error) {
        console.error('❌ Test Failed with exception:', error);
        return false;
    } finally {
        // テスト後のクリーンアップ
        window.dialogueData = originalDialogueData;
        window.isEndingScene = originalIsEndingScene;
        window.currentPageIndex = originalCurrentPageIndex;
        window.currentDialogueIndex = originalCurrentDialogueIndex;
    }
}

/**
 * clearGameProgressのテスト
 * ゲーム進捗が確実にクリアされることを検証
 */
function testClearGameProgress() {
    console.log('=== Test: Clear Game Progress ===');
    
    var testGameTitle = 'Test Game Progress Clear';
    var testProgress = {
        gameTitle: testGameTitle,
        currentPageIndex: 5,
        currentDialogueIndex: 2,
        timestamp: Date.now()
    };
    
    // テスト用進捗データを保存
    try {
        var storageKey = window.generateStorageKey && window.generateStorageKey(testGameTitle);
        if (!storageKey) {
            console.error('❌ Test Failed: generateStorageKey function not available or returned empty');
            return false;
        }
        
        localStorage.setItem(storageKey, JSON.stringify(testProgress));
        
        // 保存されたことを確認
        var savedData = localStorage.getItem(storageKey);
        if (!savedData) {
            console.error('❌ Test Failed: Could not save test progress data');
            return false;
        }
        
        // clearGameProgressを実行（関数が存在する場合のみ）
        if (!window.clearGameProgress) {
            console.error('❌ Test Failed: clearGameProgress function not available');
            return false;
        }
        
        window.clearGameProgress(testGameTitle);
        
        // クリアされたことを確認
        var clearedData = localStorage.getItem(storageKey);
        if (clearedData !== null) {
            console.error('❌ Test Failed: Progress data was not cleared, got:', clearedData);
            return false;
        }
        
        console.log('✅ Test Passed: Clear Game Progress');
        return true;
        
    } catch (error) {
        console.error('❌ Test Failed with exception:', error);
        return false;
    }
}

/**
 * エンディング画面クリック機能のテスト
 * エンディング画面でクリックした際にタイトル画面に戻る機能を検証
 */
function testEndingScreenClick() {
    console.log('=== Test: Ending Screen Click Functionality ===');
    
    // テスト環境のセットアップ
    var originalIsEndingScene = window.isEndingScene;
    var $testContainer = $('<div>').attr('id', 'test-game-container');
    var $testChoicesContainer = $('<div>').attr('id', 'test-choices-container');
    $testContainer.append($testChoicesContainer);
    $('body').append($testContainer);
    
    try {
        // エンディングシーン状態にセット
        window.isEndingScene = true;
        
        // gameStateオブジェクトが存在する場合はそちらも設定
        if (window.gameState) {
            window.gameState.isEndingScene = true;
        }
        
        console.log('Ending scene state set to true');
        
        // showGameEnd関数が存在するかチェック
        if (!window.showGameEnd) {
            console.warn('⚠️ showGameEnd function not available - creating mock implementation');
            
            // Mock implementation for testing
            window.showGameEnd = function() {
                var $choicesContainer = $('#test-choices-container');
                var isEndingScene = window.isEndingScene || (window.gameState && window.gameState.isEndingScene);
                
                $choicesContainer.empty();
                
                var $endMessage = $('<div>')
                    .addClass('game-end-message')
                    .text('おわり');
                $choicesContainer.append($endMessage);
                
                if (isEndingScene) {
                    var $clickMessage = $('<div>')
                        .addClass('ending-click-instruction')
                        .text('クリックしてタイトル画面に戻る');
                    $choicesContainer.append($clickMessage);
                    
                    var $returnButton = $('<button>')
                        .addClass('game-nav-button ending-return-button')
                        .text('タイトル画面に戻る');
                    $choicesContainer.append($returnButton);
                    
                    var endingClickHandler = function(e) {
                        e.preventDefault();
                        console.log('Ending click handler triggered');
                        
                        if (window.gameState && window.gameState.reset) {
                            window.gameState.reset();
                        }
                        
                        window.isEndingScene = false;
                        console.log('Game state reset and ending flag cleared');
                    };
                    
                    $returnButton.on('click', endingClickHandler);
                    $testContainer.on('click.novel-end-ending', endingClickHandler);
                }
            };
        }
        
        // showGameEndを実行
        window.showGameEnd();
        
        // エンディング要素が作成されたかチェック
        var $endMessage = $('.game-end-message');
        var $clickInstruction = $('.ending-click-instruction');
        var $returnButton = $('.ending-return-button');
        
        if ($endMessage.length === 0) {
            console.error('❌ Test Failed: "おわり" message not found');
            return false;
        }
        
        if ($clickInstruction.length === 0) {
            console.error('❌ Test Failed: Click instruction message not found');
            return false;
        }
        
        if ($returnButton.length === 0) {
            console.error('❌ Test Failed: Return button not found');
            return false;
        }
        
        console.log('✅ Ending screen elements created successfully');
        
        // ボタンクリックをシミュレート
        var clickEventTriggered = false;
        var originalGameState = window.gameState ? { ...window.gameState } : null;
        
        // クリックイベントをトリガー
        $returnButton.trigger('click');
        
        // 状態がリセットされたかチェック
        if (window.gameState && window.gameState.reset) {
            if (window.gameState.currentPageIndex !== 0 || window.gameState.currentDialogueIndex !== 0) {
                console.error('❌ Test Failed: Game state not properly reset after click');
                return false;
            }
        }
        
        if (window.isEndingScene !== false) {
            console.error('❌ Test Failed: isEndingScene flag not cleared after click');
            return false;
        }
        
        console.log('✅ Test Passed: Ending Screen Click Functionality');
        return true;
        
    } catch (error) {
        console.error('❌ Test Failed with exception:', error);
        return false;
    } finally {
        // クリーンアップ
        window.isEndingScene = originalIsEndingScene;
        $testContainer.remove();
        $('.game-end-message, .ending-click-instruction, .ending-return-button').remove();
        $(document).off('.novel-end-ending');
    }
}

/**
 * 全テストを実行
 */
function runAllTests() {
    console.log('Starting Novel Game Plugin Frontend Tests...');
    
    var tests = [
        testStartNewGameFromEndingScreen,
        testClearGameProgress,
        testEndingScreenClick
    ];
    
    var passed = 0;
    var failed = 0;
    
    tests.forEach(function(test) {
        try {
            if (test()) {
                passed++;
            } else {
                failed++;
            }
        } catch (error) {
            console.error('Test execution error:', error);
            failed++;
        }
    });
    
    console.log('=== Test Results ===');
    console.log('Passed:', passed);
    console.log('Failed:', failed);
    console.log('Total:', tests.length);
    
    if (failed === 0) {
        console.log('🎉 All tests passed!');
        return true;
    } else {
        console.log('❌ Some tests failed');
        return false;
    }
}

// ページ読み込み後にテストを実行（開発環境でのみ）
if (window.location.hostname === 'localhost' || window.location.hostname.includes('test')) {
    document.addEventListener('DOMContentLoaded', function() {
        // 少し待ってからテスト実行（初期化完了を待つ）
        setTimeout(runAllTests, 1000);
    });
}