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
 * 全テストを実行
 */
function runAllTests() {
    console.log('Starting Novel Game Plugin Frontend Tests...');
    
    var tests = [
        testStartNewGameFromEndingScreen,
        testClearGameProgress
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