# バナー広告機能実装完了報告

## 概要
ゲーム画面にバナー広告を表示する機能の第2段階として、**タイトル画面では広告を表示せず、プレイ開始後のゲーム画面上部にのみ広告を表示**する制御ロジックを実装しました。

## 実装完了日
2025年11月5日

## 対応Issue
- **Issue #**: shokun0803/novel-game-plugin#144
- **Issue タイトル**: 2/4: ゲーム画面へのバナー広告機能 - タイトル非表示・プレイ画面のみ表示＋2媒体フロント実装

## 実装内容

### 1. PHP側の実装（novel-game-plugin.php）

#### 広告コンテナの追加
```php
<!-- 広告コンテナ（プレイ開始後に表示） -->
<div id="novel-ad-container" class="novel-ad-container" style="display: none;">
    <!-- 広告がここに動的に挿入されます -->
</div>
```
- ゲームコンテナ内の最上部に配置
- デフォルトで非表示（`display: none`）
- プレイ開始後にJavaScriptで表示制御

#### 広告設定データの提供
```php
<script id="novel-ad-config" type="application/json">
    <?php 
    $ad_config = array(
        'provider' => 'none',
        'publisherId' => '',
    );
    
    if ( $game_title ) {
        $game_data = noveltool_get_game_by_title( $game_title );
        if ( $game_data && isset( $game_data['id'] ) ) {
            $ad_provider = get_post_meta( $game_data['id'], 'noveltool_ad_provider', true );
            if ( empty( $ad_provider ) ) {
                $ad_provider = 'none';
            }
            $ad_config['provider'] = $ad_provider;
            
            if ( $ad_provider === 'adsense' ) {
                $ad_config['publisherId'] = noveltool_get_google_adsense_id();
            } elseif ( $ad_provider === 'adsterra' ) {
                $ad_config['publisherId'] = noveltool_get_adsterra_id();
            }
        }
    }
    
    echo wp_json_encode( $ad_config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); 
    ?>
</script>
```
- ゲームごとの広告プロバイダー設定を取得
- グローバル広告IDを取得
- JSON形式でJavaScriptに提供

### 2. JavaScript側の実装（js/frontend.js）

#### 広告関連の変数
```javascript
var adConfig = null;
var adInitialized = false;
var $adContainer = $( '#novel-ad-container' );
```

#### 広告設定読み込み
```javascript
function loadAdConfig() {
    try {
        var adConfigData = $( '#novel-ad-config' ).text();
        if ( adConfigData ) {
            adConfig = JSON.parse( adConfigData );
            debugLog( '広告設定を読み込みました:', adConfig );
        }
    } catch ( error ) {
        console.error( '広告設定の解析に失敗しました:', error );
    }
}
```

#### セキュリティ: Publisher IDサニタイゼーション
```javascript
function sanitizePublisherId( publisherId ) {
    if ( ! publisherId || typeof publisherId !== 'string' ) {
        return '';
    }
    // 英数字、ハイフン、アンダースコアのみを許可
    return publisherId.replace( /[^a-zA-Z0-9\-_]/g, '' );
}
```

#### Google AdSense実装
```javascript
function initializeAdSense() {
    if ( ! adConfig || adConfig.provider !== 'adsense' || ! adConfig.publisherId ) {
        return;
    }
    
    debugLog( 'Google AdSense 広告を初期化します' );
    
    if ( document.querySelector( 'script[src*="adsbygoogle.js"]' ) ) {
        debugLog( 'AdSense スクリプトは既に読み込まれています' );
        displayAdSenseAd();
        return;
    }
    
    var sanitizedPublisherId = sanitizePublisherId( adConfig.publisherId );
    if ( ! sanitizedPublisherId ) {
        console.error( 'AdSense Publisher ID が無効です' );
        return;
    }
    
    var script = document.createElement( 'script' );
    script.async = true;
    script.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + 
                encodeURIComponent( sanitizedPublisherId );
    script.crossOrigin = 'anonymous';
    script.onerror = function() {
        console.warn( 'AdSense スクリプトの読み込みに失敗しました' );
    };
    script.onload = function() {
        debugLog( 'AdSense スクリプトの読み込みが完了しました' );
        displayAdSenseAd();
    };
    document.head.appendChild( script );
}

function displayAdSenseAd() {
    if ( ! isAdContainerAvailable() ) {
        return;
    }
    
    var $adUnit = $( '<ins>' )
        .addClass( 'adsbygoogle' )
        .css( {
            'display': 'block',
            'width': '100%',
            'height': '60px'
        } )
        .attr( {
            'data-ad-client': adConfig.publisherId,
            'data-ad-slot': 'auto',
            'data-ad-format': 'horizontal',
            'data-full-width-responsive': 'false'
        } );
    
    $adContainer.append( $adUnit );
    
    try {
        if ( window.adsbygoogle ) {
            ( window.adsbygoogle = window.adsbygoogle || [] ).push( {} );
            debugLog( 'AdSense 広告を初期化しました' );
        }
    } catch ( error ) {
        console.error( 'AdSense 広告の初期化に失敗しました:', error );
    }
}
```

#### Adsterra実装
```javascript
var AD_DIMENSIONS = {
    BANNER_HEIGHT: 60,
    BANNER_WIDTH: 468
};

function initializeAdsterra() {
    if ( ! adConfig || adConfig.provider !== 'adsterra' || ! adConfig.publisherId ) {
        return;
    }
    
    debugLog( 'Adsterra 広告を初期化します' );
    displayAdsterraAd();
}

function displayAdsterraAd() {
    if ( ! isAdContainerAvailable() ) {
        return;
    }
    
    var sanitizedPublisherId = sanitizePublisherId( adConfig.publisherId );
    if ( ! sanitizedPublisherId ) {
        console.error( 'Adsterra Publisher ID が無効です' );
        return;
    }
    
    var $adScript = $( '<script>' )
        .attr( {
            'type': 'text/javascript',
            'data-cfasync': 'false'
        } )
        .text( 'atOptions = {' +
               '"key": "' + sanitizedPublisherId + '",' +
               '"format": "iframe",' +
               '"height": ' + AD_DIMENSIONS.BANNER_HEIGHT + ',' +
               '"width": ' + AD_DIMENSIONS.BANNER_WIDTH + ',' +
               '"params": {}' +
               '};' );
    
    var $adLoader = $( '<script>' )
        .attr( {
            'type': 'text/javascript',
            'src': '//www.topcreativeformat.com/' + sanitizedPublisherId + '/invoke.js'
        } );
    
    $adContainer.append( $adScript ).append( $adLoader );
    debugLog( 'Adsterra 広告を表示しました' );
}
```

#### 表示制御
```javascript
function showAdvertisement() {
    if ( ! adConfig || adConfig.provider === 'none' || ! adConfig.publisherId ) {
        debugLog( '広告設定がありません。広告は表示しません。' );
        return;
    }
    
    if ( adInitialized ) {
        $adContainer.show();
        debugLog( '広告コンテナを表示しました' );
        return;
    }
    
    if ( adConfig.provider === 'adsense' ) {
        initializeAdSense();
    } else if ( adConfig.provider === 'adsterra' ) {
        initializeAdsterra();
    }
    
    $adContainer.show();
    adInitialized = true;
    debugLog( '広告を初期化して表示しました' );
}

function hideAdvertisement() {
    $adContainer.hide();
    debugLog( '広告を非表示にしました' );
}
```

#### 統合
```javascript
// プレイ開始時（initializeGameContent関数内）
showAdvertisement();

// タイトル画面表示時（showTitleScreen関数内）
hideAdvertisement();
```

### 3. CSS側の実装（css/style.css）

```css
/* 広告コンテナ */
.novel-ad-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    min-height: 60px;
    max-height: 100px;
    background: rgba(240, 240, 240, 0.95);
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* タイトル画面では広告を非表示 */
.novel-game-modal-content #novel-title-screen.novel-title-screen ~ .novel-game-container #novel-ad-container.novel-ad-container,
.novel-game-modal-content #novel-title-screen[style*="display: flex"] ~ .novel-game-container #novel-ad-container.novel-ad-container {
    display: none;
}

/* モバイル対応 */
@media (max-width: 768px) {
    .novel-ad-container {
        min-height: 50px;
        max-height: 90px;
    }
}
```

## セキュリティ対策

### XSS脆弱性の修正
- **問題**: `adConfig.publisherId` を未検証で使用していた（DOM-based XSS）
- **対策**: `sanitizePublisherId()` 関数でPublisher IDをサニタイズ
- **実装**: 英数字、ハイフン、アンダースコアのみを許可し、その他の文字を削除
- **検証**: CodeQL静的解析で脆弱性が検出されなくなったことを確認

### 入力検証
- **Publisher IDの検証**: 空文字列や無効な型の場合はエラーを返す
- **エラーハンドリング**: 無効なIDの場合は処理を中断し、エラーメッセージを表示
- **一貫性**: AdSenseとAdsterra両方に同じサニタイゼーション処理を適用

## コード品質の改善

### コードレビュー対応
1. **重複コードの削除**: 広告コンテナの検証ロジックを `isAdContainerAvailable()` ヘルパー関数に抽出
2. **ハードコード値の定数化**: Adsterra広告の寸法を `AD_DIMENSIONS` 定数に抽出
3. **多言語対応**: AdSenseのdata-ad-slot属性値を「自動」から「auto」に修正
4. **CSS改善**: `!important` を削除し、セレクタの特異性を上げて制御

### 保守性の向上
- コードの重複を削除
- 定数を使用して設定値を管理
- 関数を適切に分割して責任を明確化

## テスト結果

### CodeQL静的解析
- **JavaScript**: ✅ 0件のアラート（XSS脆弱性修正後）
- **セキュリティ**: ✅ 脆弱性なし

### 構文チェック
- **JavaScript**: ✅ 構文エラーなし

## ドキュメント

### テストドキュメント
- **ファイル**: `BANNER_AD_TESTING.md`
- **内容**:
  - グローバル広告設定の確認手順
  - ゲームごと広告設定の確認手順
  - タイトル画面での広告非表示確認
  - プレイ開始後の広告表示確認
  - 広告プロバイダー別の動作確認
  - 広告非表示条件の確認
  - レスポンシブ対応の確認
  - ブラウザ互換性の確認
  - デバッグ方法とトラブルシューティング

## 技術的特徴

### 非同期読み込み
- 広告スクリプトは非同期で読み込み、ゲーム本体の動作をブロックしない
- `script.async = true` を使用

### エラーハンドリング
- 広告スクリプト読み込み失敗時もゲームは正常動作
- `script.onerror` でエラーをキャッチ
- コンソールに警告メッセージを表示

### 条件厳格制御
- プロバイダーとグローバルID両方が設定されている場合のみ表示
- 以下の条件すべてを満たす必要がある:
  1. `adConfig.provider` が `'adsense'` または `'adsterra'`
  2. `adConfig.publisherId` が空でない
  3. Publisher IDが有効な形式

### 後方互換性
- 既存ゲーム（`noveltool_ad_provider` 未設定）では広告を表示しない
- デフォルト値は `'none'`
- 既存のゲームプレイに影響なし

### デバッグサポート
- コンソールログで動作確認可能
- `debugLog()` 関数でデバッグモード切り替え
- `window.novelGameSetDebug(true)` でデバッグモードを有効化

## 今後の課題・改善点

### 広告表示の最適化
- 広告の遅延読み込み（Lazy Loading）の検討
- 広告表示タイミングの最適化
- 広告サイズの自動調整

### 対応媒体の拡大
- 他の広告プロバイダーのサポート追加
- カスタム広告タグのサポート

### パフォーマンス改善
- 広告スクリプトのプリロード
- 広告表示のスムーズなアニメーション
- メモリ使用量の最適化

### アクセシビリティ
- スクリーンリーダー対応の改善
- キーボードナビゲーションのサポート

## まとめ
バナー広告機能の第2段階として、タイトル画面での非表示とプレイ画面での表示制御を実装しました。Google AdSenseとAdsterraの2媒体に対応し、セキュリティとコード品質に配慮した実装となっています。包括的なテストドキュメントも作成し、今後の保守と拡張が容易になっています。
