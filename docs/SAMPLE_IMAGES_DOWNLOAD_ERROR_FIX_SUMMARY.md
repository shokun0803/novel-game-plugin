# サンプル画像ダウンロードエラーハンドリング改善 - 実装サマリー

## 概要

このドキュメントは、Issue で報告された「サンプル画像ダウンロード失敗時に不明瞭なエラーメッセージとロック残留で再試行不可になる不具合」に対する修正内容をまとめたものです。

## 問題点

### 1. エラーメッセージが不明瞭
- 失敗時に「サンプル画像のダウンロードに失敗しました。後でもう一度お試しください。」という一般的なメッセージのみ表示
- ユーザーが原因を特定できない
- サポート対応が困難

### 2. ステータスのロック残留
- ダウンロード失敗時にステータスが "in_progress" のまま残る
- 再試行すると「ダウンロードは既に進行中です。」エラーが表示される
- ユーザーが再試行できなくなる

### 3. エラー情報の不足
- 詳細なエラー情報がログやオプションに残らない
- サポート担当者が原因を特定できない

## 修正内容

### 1. エラーハンドリングの強化（includes/sample-images-downloader.php）

#### 1-1. 新しいステータス管理関数の追加

```php
/**
 * ダウンロードステータスを更新
 * 
 * @param string $status ステータス（not_started, in_progress, completed, failed）
 * @param string $error_message エラーメッセージ（失敗時のみ）
 */
function noveltool_update_download_status( $status, $error_message = '' ) {
    // ステータスとタイムスタンプを保存
    $status_data = array(
        'status'    => $status,
        'timestamp' => time(),
    );
    
    // 後方互換性のため、単純なステータス文字列も保存
    update_option( 'noveltool_sample_images_download_status', $status, false );
    update_option( 'noveltool_sample_images_download_status_data', $status_data, false );
    
    // エラーメッセージがある場合は保存
    if ( ! empty( $error_message ) ) {
        update_option(
            'noveltool_sample_images_download_error',
            array(
                'message'   => $error_message,
                'timestamp' => time(),
            ),
            false
        );
    } else {
        // 成功時はエラー情報をクリア
        delete_option( 'noveltool_sample_images_download_error' );
    }
}
```

#### 1-2. TTL（Time To Live）による自動復旧

```php
/**
 * ダウンロードステータスに TTL をチェック
 * 長時間 in_progress のまま残っている場合は自動的に failed に変更
 */
function noveltool_check_download_status_ttl() {
    $status_data = get_option( 'noveltool_sample_images_download_status_data', array() );
    
    // in_progress で 30分以上経過している場合は failed に変更
    if ( 'in_progress' === $status_data['status'] && isset( $status_data['timestamp'] ) ) {
        $elapsed = time() - $status_data['timestamp'];
        if ( $elapsed > 1800 ) { // 30分 = 1800秒
            noveltool_update_download_status(
                'failed',
                __( 'Download timeout: The download process took too long and was automatically cancelled.', 'novel-game-plugin' )
            );
        }
    }
}
```

#### 1-3. すべてのエラーパスでステータスを確実に更新

**変更前**:
```php
if ( is_wp_error( $release_data ) ) {
    update_option( $option_name, 'failed', false );
    return array(
        'success' => false,
        'message' => $release_data->get_error_message(),
    );
}
```

**変更後**:
```php
if ( is_wp_error( $release_data ) ) {
    $error_msg = sprintf(
        /* translators: %s: error message */
        __( 'Failed to fetch release information: %s', 'novel-game-plugin' ),
        $release_data->get_error_message()
    );
    noveltool_update_download_status( 'failed', $error_msg );
    return array(
        'success' => false,
        'message' => $error_msg,
    );
}
```

この変更をすべてのエラーパス（API接続失敗、権限エラー、チェックサム失敗、展開失敗）に適用しました。

#### 1-4. 新しい REST API エンドポイント

```php
// ステータスリセット用エンドポイント
register_rest_route(
    'novel-game-plugin/v1',
    '/sample-images/reset-status',
    array(
        'methods'             => 'POST',
        'callback'            => 'noveltool_api_reset_download_status',
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        },
    )
);

function noveltool_api_reset_download_status( $request ) {
    // ステータスを not_started にリセット
    noveltool_update_download_status( 'not_started' );
    
    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => __( 'Download status has been reset.', 'novel-game-plugin' ),
        ),
        200
    );
}
```

#### 1-5. ステータス取得 API の拡張

エラー情報を含むレスポンスを返すように変更：

```php
function noveltool_api_sample_images_status( $request ) {
    $exists = noveltool_sample_images_exists();
    $status = get_option( 'noveltool_sample_images_download_status', 'not_started' );
    $error_data = get_option( 'noveltool_sample_images_download_error', null );
    
    $response = array(
        'exists' => $exists,
        'status' => $status,
    );
    
    // エラー情報があれば追加
    if ( ! empty( $error_data ) && is_array( $error_data ) ) {
        $response['error'] = array(
            'message'   => isset( $error_data['message'] ) ? $error_data['message'] : '',
            'timestamp' => isset( $error_data['timestamp'] ) ? $error_data['timestamp'] : 0,
        );
    }
    
    return new WP_REST_Response( $response, 200 );
}
```

### 2. UI の改善（js/admin-sample-images-prompt.js）

#### 2-1. エラーメッセージの詳細化

```javascript
function showErrorMessage(message) {
    var modal = $('#noveltool-sample-images-modal');
    var content = modal.find('.noveltool-modal-content');

    content.find('h2').text(novelToolSampleImages.strings.error);
    
    // エラーメッセージと対処方法を表示
    var errorHtml = '<span style="color: #dc3232;">' + message + '</span>';
    errorHtml += '<div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #dc3232;">';
    errorHtml += '<strong>' + novelToolSampleImages.strings.troubleshooting + '</strong><br>';
    errorHtml += novelToolSampleImages.strings.troubleshootingSteps;
    errorHtml += '</div>';
    
    content.find('p').html(errorHtml);
    
    // 再試行ボタンと閉じるボタンを表示
    var buttons = $('<div>', { class: 'noveltool-modal-buttons' });
    var retryButton = $('<button>', {
        class: 'button button-primary',
        text: novelToolSampleImages.strings.retryButton,
        click: function () {
            resetStatusAndRetry();
        }
    });
    var closeButton = $('<button>', {
        class: 'button',
        text: novelToolSampleImages.strings.closeButton,
        click: function () {
            closeOnly();
        }
    });
    buttons.append(retryButton).append(closeButton);
    content.find('.noveltool-modal-buttons').html(buttons);
}
```

#### 2-2. 自動ステータスリセット機能

```javascript
function resetStatusAndRetry() {
    var modal = $('#noveltool-sample-images-modal');
    var content = modal.find('.noveltool-modal-content');
    
    // リセット中メッセージ
    content.find('h2').text(novelToolSampleImages.strings.resetting);
    content.find('p').html(novelToolSampleImages.strings.pleaseWait);
    content.find('.noveltool-modal-buttons').html('<div class="noveltool-spinner"></div>');
    
    // ステータスリセット API を呼び出し
    $.ajax({
        url: novelToolSampleImages.apiResetStatus,
        method: 'POST',
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', novelToolSampleImages.restNonce);
        },
        success: function () {
            // リセット成功後、ダウンロードを再開
            startDownload();
        },
        error: function () {
            // リセット失敗時は手動再試行を促す
            content.find('h2').text(novelToolSampleImages.strings.error);
            content.find('p').html('<span style="color: #dc3232;">' + novelToolSampleImages.strings.resetFailed + '</span>');
            // 閉じるボタンのみ表示
        }
    });
}
```

### 3. ローカライズ文字列の追加（admin/my-games.php）

以下の新しい翻訳可能文字列を追加：

- `resetting`: 「Resetting...」
- `resetFailed`: 「Failed to reset download status. Please try again later or contact the administrator.」
- `troubleshooting`: 「Troubleshooting:」
- `troubleshootingSteps`: トラブルシューティング手順のリスト
  1. Check your internet connection
  2. Verify that the assets directory has write permissions
  3. Check server error logs for detailed information
  4. If the problem persists, try manual installation (see documentation)

### 4. ドキュメントの更新（docs/SAMPLE_IMAGES_DOWNLOAD.md）

#### 4-1. トラブルシューティングセクションの大幅拡充

以下の情報を追加：

1. **一般的なエラーと対処法**
   - インターネット接続の問題
   - ファイル書き込み権限の問題
   - チェックサム検証の失敗
   - タイムアウトエラー
   - ダウンロードが「進行中」のまま動かない問題
   - GitHub API レート制限

2. **エラーログの確認方法**
   - WordPress デバッグログの場所
   - WordPress オプションの確認方法

3. **再試行の方法**
   - UI からの再試行手順
   - 手動でのステータスリセット方法

#### 4-2. REST API ドキュメントの更新

- `/sample-images/status` エンドポイントのレスポンスに `error` フィールドを追加
- `/sample-images/reset-status` エンドポイントの説明を追加

#### 4-3. オプション一覧の更新

新しい WordPress オプションを追加：

- `noveltool_sample_images_download_status_data`: タイムスタンプ付きステータス
- `noveltool_sample_images_download_error`: エラー詳細情報

#### 4-4. 関数リファレンスの追加

- `noveltool_update_download_status()`
- `noveltool_check_download_status_ttl()`

## データフロー

```
[ユーザーアクション: ダウンロード開始]
        ↓
[noveltool_perform_sample_images_download()]
        ↓
[noveltool_check_download_status_ttl()] ← TTLチェック（30分）
        ↓
[noveltool_update_download_status('in_progress')]
        ↓
[各処理: API取得、ダウンロード、チェックサム、展開]
        ↓
    [成功]                          [失敗]
        ↓                               ↓
[noveltool_update_download_status    [noveltool_update_download_status
 ('completed')]                       ('failed', $error_message)]
        ↓                               ↓
[エラー情報をクリア]                [エラー情報を保存]
                                        ↓
                            [UI: エラー表示 + トラブルシューティング]
                                        ↓
                            [ユーザー: 再試行ボタンクリック]
                                        ↓
                            [noveltool_api_reset_download_status()]
                                        ↓
                            [noveltool_update_download_status('not_started')]
                                        ↓
                            [再度ダウンロード開始]
```

## 影響範囲

### 変更されたファイル

1. `includes/sample-images-downloader.php` (164行の変更)
   - 新関数の追加: 2つ
   - 既存関数の修正: 1つ
   - REST API エンドポイントの追加: 1つ
   - エラーハンドリングの改善: すべてのエラーパス

2. `js/admin-sample-images-prompt.js` (67行の変更)
   - 新関数の追加: 1つ
   - 既存関数の修正: 1つ

3. `admin/my-games.php` (37行の変更)
   - ローカライズ文字列の追加: 6つ

4. `docs/SAMPLE_IMAGES_DOWNLOAD.md` (115行の追加)
   - トラブルシューティングセクションの大幅拡充
   - REST API ドキュメントの更新
   - オプション一覧の更新

### 破壊的変更

なし（後方互換性を維持）

- 既存の `noveltool_sample_images_download_status` オプションはそのまま使用
- 新しいオプション（`_data` と `_error`）を追加
- 既存の REST API は変更なし（レスポンスに `error` フィールドを追加するのみ）

## テストの推奨事項

詳細なテスト計画は `docs/SAMPLE_IMAGES_DOWNLOAD_ERROR_FIX_TEST.md` を参照してください。

主要なテストケース：

1. 正常系（成功パス）
2. HTTP エラー（API接続失敗）
3. ファイルシステム権限エラー
4. チェックサム検証失敗
5. 長時間の in_progress 状態（TTL自動復旧）
6. 再試行機能
7. REST API エンドポイント
8. 同時実行ガード

## セキュリティ考慮事項

- すべての REST API エンドポイントは `manage_options` 権限を要求（管理者のみ）
- WP Nonce による CSRF 保護
- エラーメッセージには機密情報を含めない
- ファイルパスは `sanitize_file_name()` でサニタイズ済み

## パフォーマンス影響

- TTL チェックは O(1) の単純な比較処理
- 新しいオプションは `autoload = false` で保存（ページ読み込みに影響なし）
- REST API のレスポンスサイズはわずかに増加（エラー情報の追加）

## 国際化（i18n）

すべての新しいメッセージは翻訳関数（`__()`）でラップされています。

新しい翻訳可能文字列：

- `Download timeout: The download process took too long and was automatically cancelled.`
- `Failed to fetch release information: %s`
- `Sample images asset not found in the latest release. Please contact the plugin developer.`
- `Failed to download sample images: %s`
- `Checksum verification failed. The downloaded file may be corrupted. Please try again.`
- `Failed to extract sample images: %s`
- `Download status has been reset.`
- `Resetting...`
- `Failed to reset download status. Please try again later or contact the administrator.`
- `Troubleshooting:`
- `1. Check your internet connection`
- `2. Verify that the assets directory has write permissions`
- `3. Check server error logs for detailed information`
- `4. If the problem persists, try manual installation (see documentation)`

POT ファイルの更新が必要です：

```bash
wp i18n make-pot . languages/novel-game-plugin.pot
```

## 今後の改善案

1. **ダウンロード進捗バーの追加**
   - 大きなファイルのダウンロード時に進捗を表示

2. **バックグラウンドダウンロード**
   - WP Cron または Action Scheduler を使用してバックグラウンドで処理

3. **自動リトライ機能**
   - 一時的なネットワークエラーの場合に自動的に再試行

4. **ミラーサーバー対応**
   - 複数のダウンロード先を設定可能に

5. **詳細なログ記録**
   - WordPress Site Health に統合
   - ダウンロード履歴の保存

## まとめ

この修正により、以下の問題が解決されました：

✅ エラーメッセージが明確になり、ユーザーが原因を特定できるようになった
✅ ステータスのロック残留がなくなり、常に再試行可能になった
✅ エラー情報が永続化され、サポート対応が容易になった
✅ TTL による自動復旧機能が追加され、長時間のロック状態を回避できるようになった
✅ UI にトラブルシューティング手順が表示され、ユーザーが自己解決できるようになった
✅ ドキュメントが充実し、開発者とユーザーの両方にとって有用な情報が提供されるようになった

すべての変更は後方互換性を維持しており、既存の機能に影響を与えません。
