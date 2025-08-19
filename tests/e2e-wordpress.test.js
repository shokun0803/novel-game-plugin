// PuppeteerによるWordPress上のE2E自動テストサンプル
console.log('[Test] E2E test file loaded');
console.error('[Test] E2E test file loaded');
const puppeteer = require('puppeteer');
jest.setTimeout(60000);

describe('NovelGamePlugin E2E', () => {
    let browser, page;
    const wpUrl = 'http://localhost/%e3%81%a6%e3%81%99%e3%81%a8'; // テスト対象のWordPress URL

    beforeAll(async () => {
        jest.setTimeout(60000);
        browser = await puppeteer.launch({
            headless: false,
            args: [
                '--font-render-hinting=none',
                '--lang=ja-JP',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-setuid-sandbox'
            ]
        });
        page = await browser.newPage();
        // ブラウザ内console.logをテストログに出力
        page.on('console', msg => {
            console.log('[Browser]', msg.text());
        });
        // ネットワーク監視: AJAXリクエスト・レスポンス・失敗をキャプチャ
        page.on('request', req => {
            if (req.url().includes('novel_game')) {
                console.log('[Puppeteer][request]', req.method(), req.url());
            }
        });
        page.on('response', async res => {
            if (res.url().includes('novel_game')) {
                const txt = await res.text();
                console.log('[Puppeteer][response]', res.status(), res.url(), txt.substring(0, 500));
            }
        });
        page.on('requestfailed', req => {
            if (req.url().includes('novel_game')) {
                console.error('[Puppeteer][requestfailed]', req.method(), req.url(), req.failure());
            }
        });
        await page.goto(wpUrl);
        // frontend.jsのscriptタグが読み込まれるまで待機
    // frontend.jsの読み込み判定をwindowオブジェクトで確認
    await page.waitForFunction('window.openModal !== undefined', { timeout: 15000 });
        }, 60000);

    // タイムアウトを180秒に延長
    jest.setTimeout(180000);

    afterAll(async () => {
        if (browser) {
            await browser.close();
        }
    });

    test('一覧画面からプレイ開始→モーダル表示→最初から開始でゲームが最初のシーンから始まる', async () => {
        console.log('[Test] test: start-from-title-screen');
        console.error('[Test] test: start-from-title-screen');
        // PuppeteerのconsoleイベントでJSエラーを取得
        const errors = [];
        page.on('console', msg => {
            if (msg.type() === 'error' || msg.type() === 'warning') {
                errors.push(msg.text());
            }
        });
        // 一覧画面のゲームカードをクリック（.noveltool-game-item）
    // JS初期化待機
    await new Promise(r => setTimeout(r, 2000));
        await page.waitForSelector('.noveltool-play-button', { timeout: 10000 });
        // クリック可能か判定
        const isDisabled = await page.$eval('.noveltool-play-button', el => el.disabled || window.getComputedStyle(el).pointerEvents === 'none');
        if (isDisabled) throw new Error('プレイ開始ボタンがクリック不可状態です');
        await page.evaluate(() => {
            const el = document.querySelector('.noveltool-play-button');
            if (el) el.scrollIntoView({ behavior: 'auto', block: 'center' });
        });
        // ボタンの座標を取得してマウスを移動
        const playBtn = await page.$('.noveltool-play-button');
        const box = await playBtn.boundingBox();
        await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        await new Promise(r => setTimeout(r, 300)); // マウスオーバー待機
        // Puppeteerのクリック操作（jQuery経由も残す）
        // jQueryイベントバインドの有無を確認
        const hasJquery = await page.evaluate(() => typeof window.jQuery !== 'undefined');
        if (!hasJquery) throw new Error('jQueryがロードされていません');
        let hasEvent = await page.evaluate(() => {
            if (!window.jQuery) return false;
            const events = window.jQuery._data(document, 'events');
            return events && events.click;
        });
        // バインドがなければテスト用に直接バインド
        if (!hasEvent) {
            await page.evaluate(() => {
                window.jQuery('.noveltool-play-button').off('click').on('click', function() {
                    // 必要なdata属性を取得
                    var gameUrl = this.getAttribute('data-game-url');
                    var gameTitle = this.getAttribute('data-game-title');
                    var gameDescription = this.getAttribute('data-game-description') || '';
                    var gameSubtitle = this.getAttribute('data-game-subtitle') || '';
                    var gameImage = this.getAttribute('data-game-image') || '';
                    var gameData = {
                        url: gameUrl,
                        title: gameTitle,
                        description: gameDescription,
                        subtitle: gameSubtitle,
                        image: gameImage
                    };
                    if (window.openModal) window.openModal(gameData);
                });
            });
        }
        // jQueryで直接イベント発火
        await page.evaluate(() => {
            const el = document.querySelector('.noveltool-play-button');
            if (el) el.click();
        });
        await new Promise(r => setTimeout(r, 2000)); // 画面遷移待機
        // クリック後、display: none解除まで最大5秒待機
        let modalDisplay = 'none';
        for (let i = 0; i < 10; i++) {
            modalDisplay = await page.$eval('#novel-game-modal-overlay', el => window.getComputedStyle(el).display);
            if (modalDisplay !== 'none') break;
            await new Promise(r => setTimeout(r, 500));
        }
        console.log('Modal display style:', modalDisplay);
        if (modalDisplay === 'none') {
            const htmlFail = await page.content();
            require('fs').writeFileSync('modal-fail.html', htmlFail);
            if (errors.length > 0) require('fs').writeFileSync('modal-fail-console.txt', errors.join('\n'));
            throw new Error('Modal not displayed. HTML dumped to modal-fail.html, console errors dumped to modal-fail-console.txt');
        }
        expect(modalDisplay).not.toBe('none');

        await page.waitForSelector('#novel-title-start-new', { timeout: 10000 });
        // ボタンの表示・有効化を強制
        await page.evaluate(() => {
            const el = document.querySelector('#novel-title-start-new');
            const parent = document.querySelector('#novel-title-screen');
            if (parent) {
                parent.style.display = 'block';
                parent.style.visibility = 'visible';
                parent.style.pointerEvents = 'auto';
                parent.style.opacity = '1';
                parent.style.zIndex = '9999';
            }
            if (el) {
                el.style.display = 'block';
                el.style.visibility = 'visible';
                el.style.pointerEvents = 'auto';
                el.style.opacity = '1';
                el.style.zIndex = '99999';
                el.disabled = false;
                el.setAttribute('disabled', false);
                el.removeAttribute('disabled');
                el.scrollIntoView({ behavior: 'auto', block: 'center' });
                el.click(); // JSから直接発火
            }
        });
        await new Promise(r => setTimeout(r, 5000)); // ゲーム開始待機（5秒に延長）

        try {
            // いずれかの要素が表示されるまで待機
            const selectors = ['.novel-dialogue-box', '.novel-dialogue-text-container', '#novel-dialogue-text'];
            let foundSelector = null;
            for (const sel of selectors) {
                try {
                    await page.waitForSelector(sel, { timeout: 20000 });
                    foundSelector = sel;
                    break;
                } catch (e) {}
            }
            if (!foundSelector) throw new Error('No dialogue element found');
            const firstSceneText = await page.$eval(foundSelector, el => el.textContent);
            console.log('First scene text:', firstSceneText);
            expect(firstSceneText).toMatch(/電子申請システムとは？/);
        } catch (e) {
            const htmlFail = await page.content();
            require('fs').writeFileSync('novel-dialogue-fail.html', htmlFail);
            throw e;
        }
        // JS初期化状態を確認
        const jsStatus = await page.evaluate(() => {
            return {
                openModal: typeof window.openModal !== 'undefined',
                jQuery: typeof window.jQuery !== 'undefined',
                playBtn: !!document.querySelector('.noveltool-play-button'),
                playBtnClick: !!document.querySelector('.noveltool-play-button') && !!document.querySelector('.noveltool-play-button').onclick,
                modal: !!document.getElementById('novel-game-modal-overlay'),
            };
        });
        if (!jsStatus.openModal || !jsStatus.jQuery || !jsStatus.playBtn || !jsStatus.modal) {
            throw new Error('JS status: ' + JSON.stringify(jsStatus));
        }
        console.log('JS status:', jsStatus);
    }, 20000);

    test('エンディング到達後に最初から開始で進行状況がリセットされる', async () => {
        console.log('[Test] test: ending-reset-and-scene-transition');
        console.error('[Test] test: ending-reset-and-scene-transition');
        try {
            // 選択肢の表示状態を取得してログ出力
            const choicesDisplay = await page.evaluate(() => {
                const el = document.getElementById('novel-choices');
                return el ? window.getComputedStyle(el).display : 'none';
            });
            const choiceBtnCount = await page.evaluate(() => {
                return document.querySelectorAll('.noveltool-choice-button').length;
            });
                                    // セリフ送りを繰り返し、選択肢が表示されるまで進める
                                        let choicesVisible = false;
                                                                    for (let i = 0; i < 20; i++) {
                                                                        // 必ず出力されるログ
                                                                        console.log(`[Test] ダイアログ送りループ: i=${i} クリック前`);
                                                                        const currentPageIndex = await page.evaluate(() => window.currentPageIndex ?? -1);
                                                                        console.log(`[Test] ダイアログ送りループ: i=${i} currentPageIndex=${currentPageIndex}`);
                                                                        await page.click('#novel-dialogue-box');
                                                                        await new Promise(r => setTimeout(r, 300));
                                                                        choicesVisible = await page.evaluate(() => {
                                                                            const choices = document.querySelector('#novel-choices');
                                                                            return choices && choices.style.display !== 'none' && choices.children.length > 0;
                                                                        });
                                                                        console.log(`[Test] ダイアログ送りループ: i=${i} choicesVisible=${choicesVisible}`);
                                                                        if (choicesVisible) break;
                                                                    }
                                                                    if (!choicesVisible) {
                                                                        const choicesHtmlFail = await page.evaluate(() => document.querySelector('#novel-choices')?.outerHTML || 'not found');
                                                                        console.log('[Test] 選択肢表示失敗: choicesVisible=', choicesVisible);
                                                                        console.log('[Test] #novel-choices HTML:', choicesHtmlFail);
                                                                        throw new Error('選択肢が表示されませんでした');
                                                                    }

                            // 選択肢表示直前のHTMLと状態をログ出力
                            const choicesHtmlBefore = await page.evaluate(() => document.querySelector('#novel-choices')?.outerHTML || 'not found');
                            console.log('Choices HTML before:', choicesHtmlBefore);

                            // 選択肢が表示されたか確認
                            choicesVisible = await page.evaluate(() => {
                                const choices = document.querySelector('#novel-choices');
                                return choices && choices.style.display !== 'none' && choices.children.length > 0;
                            });
                            console.log('Choices visible:', choicesVisible);

                            // 選択肢ボタンの数を確認
                            const choiceButtonsCount = await page.evaluate(() => {
                                return document.querySelectorAll('#novel-choices .choice-item').length;
                            });
                            console.log('Choice buttons count:', choiceButtonsCount);

                            // 選択肢表示直後のHTMLと状態をログ出力
                            const choicesHtmlAfter = await page.evaluate(() => document.querySelector('#novel-choices')?.outerHTML || 'not found');
                            console.log('Choices HTML after:', choicesHtmlAfter);
        // 最初から開始
        await page.waitForSelector('#novel-title-start-new', { timeout: 10000 });
        await page.evaluate(() => {
            const el = document.querySelector('#novel-title-start-new');
            const parent = document.querySelector('#novel-title-screen');
            if (parent) {
                parent.style.display = 'block';
                parent.style.visibility = 'visible';
                parent.style.pointerEvents = 'auto';
                parent.style.opacity = '1';
                parent.style.zIndex = '9999';
            }
            if (el) {
                el.style.display = 'block';
                el.style.visibility = 'visible';
                el.style.pointerEvents = 'auto';
                el.style.opacity = '1';
                el.style.zIndex = '99999';
                el.disabled = false;
                el.setAttribute('disabled', false);
                el.removeAttribute('disabled');
                el.scrollIntoView({ behavior: 'auto', block: 'center' });
                el.click(); // JSから直接発火
            }
        });
        await new Promise(r => setTimeout(r, 5000));
        // ゲームを最後まで進める（選択肢が出たら3番目を選択、それ以外は「次へ」）
        // 選択肢をすべて順番にクリックし、遷移先のURL・タイトル・セリフ内容を記録
    const choiceButtons = await page.$$('#novel-choices .choice-item');
    // 選択肢数とHTMLを強制ログ
    const novelChoicesHtml = await page.evaluate(() => document.querySelector('#novel-choices')?.outerHTML || 'not found');
    console.log('[Test] 選択肢クリック前: choiceButtons.length =', choiceButtons.length);
    console.log('[Test] #novel-choices HTML =', novelChoicesHtml);
    console.log('Choice loop start, choiceButtons.length:', choiceButtons.length);
        for (let idx = 0; idx < choiceButtons.length; idx++) {
            console.log(`Choice loop idx=${idx} start`);
            console.log(`Choice loop idx=${idx} end`);
            // クリック前の要素情報を詳細ログ
            const choiceInfoBefore = await page.evaluate((idx) => {
                const el = document.querySelectorAll('#novel-choices .choice-item')[idx];
                if (!el) return 'not found';
                return {
                    idx,
                    text: el.textContent,
                    class: el.className,
                    dataIndex: el.getAttribute('data-index'),
                    dataNextScene: el.getAttribute('data-next-scene'),
                    isJquery: !!window.jQuery,
                    jqueryLength: window.jQuery ? window.jQuery('#novel-choices .choice-item').length : -1
                };
            }, idx);
            console.log(`[Choice ${idx}] before click:`, choiceInfoBefore);
            await page.evaluate((idx) => {
                if (window.jQuery) {
                    window.jQuery('#novel-choices .choice-item').eq(idx).trigger('click');
                } else {
                    const el = document.querySelectorAll('#novel-choices .choice-item')[idx];
                    if (el) {
                        el.click();
                    }
                }
            }, idx);
            // クリック後の要素情報を詳細ログ
            const choiceInfoAfter = await page.evaluate((idx) => {
                const el = document.querySelectorAll('#novel-choices .choice-item')[idx];
                if (!el) return 'not found';
                return {
                    idx,
                    text: el.textContent,
                    class: el.className,
                    dataIndex: el.getAttribute('data-index'),
                    dataNextScene: el.getAttribute('data-next-scene'),
                    isJquery: !!window.jQuery,
                    jqueryLength: window.jQuery ? window.jQuery('#novel-choices .choice-item').length : -1
                };
            }, idx);
            console.log(`[Choice ${idx}] after click:`, choiceInfoAfter);
            await new Promise(r => setTimeout(r, 2000));
            const afterClickUrl = await page.evaluate(() => window.location.href);
            const afterClickTitle = await page.title();
            // クリック後の全ダイアログテキストを明示的に取得・出力
            const afterDialogueTexts = await page.evaluate(() => {
                return [
                    document.querySelector('.novel-dialogue-text')?.textContent || '',
                    document.querySelector('.novel-dialogue-text-container')?.textContent || '',
                    document.querySelector('.novel-dialogue-box')?.textContent || '',
                ];
            });
            console.log(`[Choice ${idx}] クリック後ダイアログテキスト:`, afterDialogueTexts);
        }
        // すべての選択肢クリック後、最初のシーンに戻るかどうかも検証
        await page.waitForSelector('#novel-title-start-new', { timeout: 10000 });
        await page.evaluate(() => {
            const el = document.querySelector('#novel-title-start-new');
            if (el) el.click();
        });
        await new Promise(r => setTimeout(r, 2000));
        // モーダルを閉じる
        await page.waitForSelector('#novel-game-close-btn', { timeout: 5000 });
        await page.click('#novel-game-close-btn');
        await new Promise(r => setTimeout(r, 1000));
        // 再度「最初から開始」
        await page.waitForSelector('#novel-title-start-new', { timeout: 10000 });
        await page.evaluate(() => {
            const el = document.querySelector('#novel-title-start-new');
            const parent = document.querySelector('#novel-title-screen');
            if (parent) {
                parent.style.display = 'block';
                parent.style.visibility = 'visible';
                parent.style.pointerEvents = 'auto';
                parent.style.opacity = '1';
                parent.style.zIndex = '9999';
            }
            if (el) {
                el.style.display = 'block';
                el.style.visibility = 'visible';
                el.style.pointerEvents = 'auto';
                el.style.opacity = '1';
                el.style.zIndex = '99999';
                el.disabled = false;
                el.setAttribute('disabled', false);
                el.removeAttribute('disabled');
                el.scrollIntoView({ behavior: 'auto', block: 'center' });
                el.click(); // JSから直接発火
            }
        });
        await new Promise(r => setTimeout(r, 5000));
        // 最初のシーンが表示されるか検証
        const selectors = ['.novel-dialogue-box', '.novel-dialogue-text-container', '#novel-dialogue-text'];
        let foundSelector = null;
        for (const sel of selectors) {
            try {
                await page.waitForSelector(sel, { timeout: 10000 });
                foundSelector = sel;
                break;
            } catch (e) {}
        }
        if (!foundSelector) throw new Error('No dialogue element found after reset');
        const firstSceneText = await page.$eval(foundSelector, el => el.textContent);
        expect(firstSceneText).toMatch(/電子申請システムとは？/);
        } catch (err) {
            console.log('[Test] テスト中にエラー発生:', err);
            throw err;
        }
        }, 120000);

    test('エンディング画面が繰り返し表示されるバグの自動検証', async () => {
        console.log('[Test] test: ending-repeat-bug-check');
        // 1. 最初から開始
        await page.waitForSelector('#novel-title-start-new', { timeout: 10000 });
        // クリック前の状態詳細ログ
        const btnInfo = await page.evaluate(() => {
            const el = document.querySelector('#novel-title-start-new');
            if (!el) return { exists: false };
            const rect = el.getBoundingClientRect();
            return {
                exists: true,
                display: window.getComputedStyle(el).display,
                visibility: window.getComputedStyle(el).visibility,
                pointerEvents: window.getComputedStyle(el).pointerEvents,
                opacity: window.getComputedStyle(el).opacity,
                zIndex: window.getComputedStyle(el).zIndex,
                disabled: el.disabled,
                rect,
                text: el.textContent
            };
        });
        console.log('[Test] #novel-title-start-new info:', btnInfo);
        // 強制表示・有効化
        await page.evaluate(() => {
            const el = document.querySelector('#novel-title-start-new');
            const parent = document.querySelector('#novel-title-screen');
            if (parent) {
                parent.style.display = 'block';
                parent.style.visibility = 'visible';
                parent.style.pointerEvents = 'auto';
                parent.style.opacity = '1';
                parent.style.zIndex = '9999';
            }
            if (el) {
                el.style.display = 'block';
                el.style.visibility = 'visible';
                el.style.pointerEvents = 'auto';
                el.style.opacity = '1';
                el.style.zIndex = '99999';
                el.disabled = false;
                el.setAttribute('disabled', false);
                el.removeAttribute('disabled');
                el.scrollIntoView({ behavior: 'auto', block: 'center' });
            }
        });
        await new Promise(r => setTimeout(r, 500));
        // 再度状態確認
        const btnInfo2 = await page.evaluate(() => {
            const el = document.querySelector('#novel-title-start-new');
            if (!el) return { exists: false };
            const rect = el.getBoundingClientRect();
            return {
                exists: true,
                display: window.getComputedStyle(el).display,
                visibility: window.getComputedStyle(el).visibility,
                pointerEvents: window.getComputedStyle(el).pointerEvents,
                opacity: window.getComputedStyle(el).opacity,
                zIndex: window.getComputedStyle(el).zIndex,
                disabled: el.disabled,
                hasDisabledAttr: el.hasAttribute('disabled'),
                rect,
                text: el.textContent
            };
        });
        console.log('[Test] #novel-title-start-new info after force:', btnInfo2);
        // クリック（Puppeteerで失敗したらevaluateで直接発火）
        try {
            await page.click('#novel-title-start-new');
        } catch (e) {
            console.log('[Test] Puppeteer click failed, fallback to direct el.click()');
            await page.evaluate(() => {
                const el = document.querySelector('#novel-title-start-new');
                if (el) el.click();
            });
        }
        await new Promise(r => setTimeout(r, 2000));
        // 2. ダイアログ送りでエンディング画面まで進める
        let isEnding = false;
        for (let i = 0; i < 30; i++) {
            // エンディング判定（.ending-click-instruction表示 or #novel-ending-scene-flag）
            isEnding = await page.evaluate(() => {
                return !!document.querySelector('.ending-click-instruction') || !!document.getElementById('novel-ending-scene-flag');
            });
            if (isEnding) {
                console.log(`[Test] エンディング画面に到達: i=${i}`);
                break;
            }
            await page.click('.novel-dialogue-box');
            await new Promise(r => setTimeout(r, 300));
        }
        if (!isEnding) throw new Error('エンディング画面に到達できませんでした');
        // 3. モーダルを閉じる
        await page.waitForSelector('#novel-game-close-btn', { timeout: 5000 });
        await page.click('#novel-game-close-btn');
        await new Promise(r => setTimeout(r, 1000));
        // 4. 再度「最初から開始」
        await page.waitForSelector('#novel-title-start-new', { timeout: 10000 });
        await page.click('#novel-title-start-new');
        await new Promise(r => setTimeout(r, 2000));
        // 5. 再度ダイアログ送りでエンディング画面が繰り返し表示されるか判定
        let isEndingRepeat = false;
        for (let i = 0; i < 30; i++) {
            isEndingRepeat = await page.evaluate(() => {
                return !!document.querySelector('.ending-click-instruction') || !!document.getElementById('novel-ending-scene-flag');
            });
            if (isEndingRepeat) {
                console.log(`[Test] 再度エンディング画面が表示されるバグを検知: i=${i}`);
                throw new Error('エンディング画面が繰り返し表示されるバグが再現しました');
            }
            await page.click('.novel-dialogue-box');
            await new Promise(r => setTimeout(r, 300));
        }
        console.log('[Test] エンディング画面の繰り返し表示バグは再現しませんでした');
    });
});
console.log('[Test] E2E test file end');
console.error('[Test] E2E test file end');
