/**
 * サンプル画像ダウンロードプロンプト
 * 
 * マイゲーム画面でサンプル画像が存在しない場合にモーダルを表示
 *
 * @package NovelGamePlugin
 * @since 1.3.0
 */

(function ($) {
    'use strict';
    
    // 定数定義（サーバー側から調整可能）
    var FALLBACK_TIMEOUT_MS = novelToolSampleImages.fallbackTimeoutMs || 5000;
    var POLL_INTERVAL_MS = novelToolSampleImages.pollIntervalMs || 3000;
    var MAX_POLL_TIME_MS = novelToolSampleImages.maxPollTimeMs || 300000;
    var XHR_TIMEOUT_MS = novelToolSampleImages.xhrTimeoutMs || 120000;
    var latestBannerStatusData = null;

    /**
     * モーダルを表示
     */
    function showSampleImagesPrompt() {
        var modal = $('<div>', {
            id: 'noveltool-sample-images-modal',
            class: 'noveltool-modal-overlay'
        });

        var modalContent = $('<div>', {
            class: 'noveltool-modal-content'
        });

        var modalTitle = $('<h2>', {
            text: novelToolSampleImages.strings.modalTitle
        });

        var modalMessage = $('<p>', {
            html: novelToolSampleImages.strings.modalMessage
        });

        var modalButtons = $('<div>', {
            class: 'noveltool-modal-buttons'
        });

        var downloadButton = $('<button>', {
            class: 'button button-primary',
            text: novelToolSampleImages.strings.downloadButton,
            click: function () {
                startDownload();
            }
        });

        var laterButton = $('<button>', {
            class: 'button',
            text: novelToolSampleImages.strings.laterButton,
            click: function () {
                dismissPermanently();
            }
        });

        var cancelButton = $('<button>', {
            class: 'button',
            text: novelToolSampleImages.strings.cancelButton,
            click: function () {
                closeOnly();
            }
        });

        modalButtons.append(downloadButton).append(laterButton).append(cancelButton);
        modalContent.append(modalTitle).append(modalMessage).append(modalButtons);
        modal.append(modalContent);

        modal.on('click', function (e) {
            if (e.target === this) {
                closeOnly();
            }
        });

        $('body').append(modal);

        // アニメーション表示
        setTimeout(function () {
            modal.addClass('show');
        }, 10);
    }

    /**
     * モーダルを永続的に非表示にする（後でボタン用）
     */
    function dismissPermanently() {
        // サーバーに恒久的な非表示フラグを保存
        $.post(ajaxurl, {
            action: 'noveltool_dismiss_sample_images_prompt',
            nonce: novelToolSampleImages.nonce
        });
        
        // モーダルを閉じる
        closeOnly();
    }

    /**
     * モーダルを閉じるだけ（フラグは保存しない）
     */
    function closeOnly() {
        var modal = $('#noveltool-sample-images-modal');
        modal.removeClass('show');
        setTimeout(function () {
            modal.remove();
        }, 300);
    }

    /**
     * プログレスバーを表示
     */
    function showProgressBar() {
        var progressContainer = $('<div>', {
            class: 'noveltool-progress-container'
        });
        
        // プログレスバーを生成し、ARIA属性を明示的に設定（確実性を保証）
        var progressBar = $('<div>', {
            class: 'noveltool-progress-bar'
        });
        progressBar.attr('role', 'progressbar')
                   .attr('aria-valuemin', 0)
                   .attr('aria-valuemax', 100)
                   .attr('aria-valuenow', 0);
        
        var progressFill = $('<div>', {
            class: 'noveltool-progress-fill',
            css: { width: '0%' }
        });
        
        progressBar.append(progressFill);
        
        // ステータステキスト（aria-live属性で確実にスクリーンリーダー通知）
        var progressStatus = $('<div>', {
            class: 'noveltool-progress-status',
            text: novelToolSampleImages.strings.statusConnecting || '接続中...'
        });
        progressStatus.attr('aria-live', 'polite').attr('aria-atomic', 'true');
        
        progressContainer.append(progressBar).append(progressStatus);
        
        return progressContainer;
    }
    
    /**
     * プログレスバーを更新
     * 
     * @param {number|null} percentage - 進捗パーセンテージ（0-100）。nullの場合はindeterminateモード
     * @param {string} statusText - 表示するステータステキスト
     */
    function updateProgressBar(percentage, statusText, modal) {
        modal = modal && modal.length ? modal : $('#noveltool-sample-images-modal');
        var progressBar = modal.find('.noveltool-progress-bar');
        var progressFill = modal.find('.noveltool-progress-fill');
        var progressStatus = modal.find('.noveltool-progress-status');
        
        if (progressBar.length > 0) {
            if (percentage === null) {
                // indeterminateモード：進捗不明
                progressBar.addClass('indeterminate');
                progressBar.removeAttr('aria-valuenow');
                progressFill.css('width', '100%').text('');
            } else {
                // 確定的な進捗
                progressBar.removeClass('indeterminate');
                progressBar.attr('aria-valuenow', percentage); // 必ず.attr()で更新
                progressFill.css('width', percentage + '%').text(percentage + '%');
            }
            
            if (statusText) {
                // aria-live属性を確実に設定してスクリーンリーダー通知を保証
                progressStatus.attr('aria-live', 'polite').attr('aria-atomic', 'true').text(statusText);
            }
        }
    }

    function getByteProgress(data) {
        if (!data) {
            return null;
        }

        if (typeof data.total_bytes === 'number' && data.total_bytes > 0 && typeof data.downloaded_bytes_confirmed === 'number' && data.downloaded_bytes_confirmed >= 0) {
            var confirmed = Math.max(0, Math.min(data.downloaded_bytes_confirmed, data.total_bytes));
            return {
                percentage: Math.floor((confirmed / data.total_bytes) * 100),
                current: confirmed,
                total: data.total_bytes
            };
        }

        return null;
    }
    
    /**
     * ダウンロード状態をポーリング
     */
    function pollDownloadStatus(pollInterval, startTime) {
        if (Date.now() - startTime > MAX_POLL_TIME_MS) {
            // タイムアウト
            fetchDetailedError(function(detail) {
                showErrorMessage(novelToolSampleImages.strings.downloadTimeout || 'ダウンロードがタイムアウトしました。', detail, 'ERR-TIMEOUT');
            });
            return;
        }
        
        $.ajax({
            url: novelToolSampleImages.apiStatus,
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', novelToolSampleImages.restNonce);
            },
            success: function (response) {
                if (response.status === 'completed' && response.exists) {
                    // ダウンロード完了
                    updateProgressBar(100, novelToolSampleImages.strings.statusCompleted || '完了');
                    setTimeout(function() {
                        showSuccessMessage(novelToolSampleImages.strings.downloadSuccess || 'サンプル画像のダウンロードが完了しました。');
                    }, 500);
                } else if (response.status === 'failed') {
                    // ダウンロード失敗 - 必ず詳細エラーを取得
                    var errorMsg = novelToolSampleImages.strings.downloadFailed;
                    var errorCode = 'ERR-DOWNLOAD-FAILED';
                    if (response.error && response.error.message) {
                        errorMsg = response.error.message;
                        errorCode = response.error.code || errorCode;
                    }
                    fetchDetailedError(function(detailedError) {
                        showErrorMessage(errorMsg, detailedError || response.error, errorCode);
                    });
                } else if (response.status === 'in_progress') {
                    // ダウンロード中
                    var elapsed = Date.now() - startTime;
                    var byteProgress = getByteProgress(response);
                    
                    // バックグラウンド処理の進捗情報を優先
                    if (byteProgress) {
                        var byteStatusText = (novelToolSampleImages.strings.statusDownloadingBytes || 'ダウンロード中: ') + formatBytes(byteProgress.current) + ' / ' + formatBytes(byteProgress.total);
                        updateProgressBar(byteProgress.percentage, byteStatusText);
                    } else if (typeof response.progress === 'number') {
                        var statusText = novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...';
                        
                        // current_step に基づいてステータステキストを変更
                        if (response.current_step) {
                            switch (response.current_step) {
                                case 'download':
                                    statusText = novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...';
                                    break;
                                case 'verify':
                                    statusText = novelToolSampleImages.strings.statusVerifying || '検証中...';
                                    break;
                                case 'extract':
                                    statusText = novelToolSampleImages.strings.statusExtracting || '展開中...';
                                    break;
                            }
                        }
                        
                        // バックグラウンド処理の場合は注記を追加
                        if (response.use_background) {
                            statusText += ' ' + (novelToolSampleImages.strings.backgroundNote || '(バックグラウンドで処理中)');
                        }
                        
                        updateProgressBar(response.progress, statusText);
                    }
                    // バイト単位の進捗情報があれば使用（従来の方式）
                    else if (
                        response.progress &&
                        typeof response.progress.current === 'number' &&
                        typeof response.progress.total === 'number' &&
                        isFinite(response.progress.current) &&
                        isFinite(response.progress.total) &&
                        response.progress.total > 0
                    ) {
                        var percentage = Math.floor((response.progress.current / response.progress.total) * 100);
                        var statusText = novelToolSampleImages.strings.statusDownloadingBytes || 'ダウンロード中: ' + 
                            formatBytes(response.progress.current) + ' / ' + formatBytes(response.progress.total);
                        updateProgressBar(percentage, statusText);
                    } else {
                        // 進捗情報がない場合はindeterminateモード + 段階的ステータス
                        var statusText = novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...';
                        if (elapsed > 30000) {
                            statusText = novelToolSampleImages.strings.statusVerifying || '検証中...';
                        }
                        if (elapsed > 60000) {
                            statusText = novelToolSampleImages.strings.statusExtracting || '展開中...';
                        }
                        
                        updateProgressBar(null, statusText); // null = indeterminate
                    }
                    
                    // 次のポーリング
                    setTimeout(function() {
                        pollDownloadStatus(pollInterval, startTime);
                    }, pollInterval);
                } else {
                    // not_started または不明な状態 - 継続してポーリング
                    setTimeout(function() {
                        pollDownloadStatus(pollInterval, startTime);
                    }, pollInterval);
                }
            },
            error: function () {
                // ポーリングエラーは継続
                setTimeout(function() {
                    pollDownloadStatus(pollInterval, startTime);
                }, pollInterval);
            }
        });
    }
    
    /**
     * バイト数をフォーマット
     */
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * HTTPステータスコードに基づくユーザー向けメッセージを取得
     */
    function getHttpStatusMessage(status) {
        var messages = {
            400: novelToolSampleImages.strings.errorBadRequest || 'リクエストが不正です。',
            403: novelToolSampleImages.strings.errorForbidden || '権限がありません。管理者に確認してください。',
            404: novelToolSampleImages.strings.errorNotFound || 'リソースが見つかりません。',
            500: novelToolSampleImages.strings.errorServerError || 'サーバーエラーが発生しました。',
            503: novelToolSampleImages.strings.errorServiceUnavailable || 'サービスが一時的に利用できません。'
        };
        return messages[status] || (novelToolSampleImages.strings.errorUnknown || 'エラーが発生しました（ステータス: ' + status + '）');
    }

    /**
     * ダウンロードを開始
     */
    function startDownload() {
        var modal = $('#noveltool-sample-images-modal');
        var content = modal.find('.noveltool-modal-content');

        // ボタンを無効化
        content.find('button').prop('disabled', true);

        // プログレス表示に変更
        content.find('h2').text(novelToolSampleImages.strings.downloading);
        content.find('p').empty();
        
        // プログレスバーを追加
        var progressBar = showProgressBar();
        content.find('p').append(progressBar);
        
        content.find('.noveltool-modal-buttons').html('<div class="noveltool-spinner"></div>');

        // ダウンロード開始時刻を記録
        var downloadStartTime = Date.now();
        
        // 初期進捗を表示（indeterminate）
        updateProgressBar(null, novelToolSampleImages.strings.statusConnecting || '接続中...');

        // まずXHRでリアルタイム進捗を試みる
        var xhr = new XMLHttpRequest();
        var fallbackTimeout = null;
        var xhrCompleted = false;
        
        // 注: XHRの'progress'イベントはサーバーがチャンク転送エンコーディング（chunked transfer encoding）で
        // 段階的な応答を送信する場合のみ機能します。通常のダウンロード開始エンドポイントでは
        // このイベントは発生しないため、自動的にポーリングモードへフォールバックします。
        
        // 完了ハンドラ
        xhr.addEventListener('load', function() {
            xhrCompleted = true;
            clearTimeout(fallbackTimeout);
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // 成功レスポンスが即座に返る場合
                        updateProgressBar(100, novelToolSampleImages.strings.statusCompleted || '完了');
                        setTimeout(function() {
                            showSuccessMessage(response.message);
                        }, 500);
                    } else {
                        // 失敗レスポンス
                        var errorCode = 'ERR-API-FAILED';
                        if (response.error && response.error.code) {
                            errorCode = response.error.code;
                        }
                        fetchDetailedError(function(detailedError) {
                            showErrorMessage(response.message, detailedError, errorCode);
                        });
                    }
                } catch (e) {
                    // JSON パースエラー
                    showErrorMessage(
                        novelToolSampleImages.strings.downloadFailed || 'ダウンロードに失敗しました。',
                        null,
                        'ERR-PARSE-FAILED'
                    );
                }
            } else if (xhr.status === 0 || xhr.status === 504 || xhr.status === 502) {
                // タイムアウトまたはゲートウェイエラー - ポーリングで状態確認
                updateProgressBar(null, novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...');
                setTimeout(function() {
                    pollDownloadStatus(3000, downloadStartTime);
                }, 2000);
            } else {
                // その他のエラー
                handleXhrError(xhr);
            }
        });
        
        // エラーハンドラ
        xhr.addEventListener('error', function() {
            xhrCompleted = true;
            clearTimeout(fallbackTimeout);
            // ネットワークエラー - ポーリングで状態確認
            updateProgressBar(null, novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...');
            setTimeout(function() {
                pollDownloadStatus(3000, downloadStartTime);
            }, 2000);
        });
        
        // タイムアウトハンドラ
        xhr.addEventListener('timeout', function() {
            xhrCompleted = true;
            clearTimeout(fallbackTimeout);
            // タイムアウト - ポーリングで状態確認
            updateProgressBar(null, novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...');
            setTimeout(function() {
                pollDownloadStatus(3000, downloadStartTime);
            }, 2000);
        });
        
        // XHRを開始
        xhr.open('POST', novelToolSampleImages.apiDownload, true);
        xhr.setRequestHeader('X-WP-Nonce', novelToolSampleImages.restNonce);
        xhr.setRequestHeader('Content-Type', 'application/json');
        // 注: このタイムアウトはバックグラウンド処理をトリガーする初回API呼び出し用です。
        // 実際のダウンロードはバックグラウンドで継続し、ポーリングで監視します。
        xhr.timeout = XHR_TIMEOUT_MS;
        
        // プログレスイベントが発生しない場合のフォールバック（通常のケース）
        fallbackTimeout = setTimeout(function() {
            if (!xhrCompleted) {
                // XHRが完了していない場合のみポーリング開始
                updateProgressBar(null, novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...');
                pollDownloadStatus(POLL_INTERVAL_MS, downloadStartTime);
            }
        }, FALLBACK_TIMEOUT_MS);
        
        xhr.send(JSON.stringify({}));
    }
    
    /**
     * XHRエラーを処理
     */
    function handleXhrError(xhr) {
        var message = novelToolSampleImages.strings.downloadFailed || 'ダウンロードに失敗しました。';
        var errorDetail = null;
        var errorCode = 'ERR-HTTP-' + xhr.status;
        
        try {
            var response = JSON.parse(xhr.responseText);
            if (response.error) {
                errorDetail = response.error;
                message = errorDetail.message || response.message || message;
                errorCode = errorDetail.code || errorCode;
            } else if (response.message) {
                message = response.message;
            }
            showErrorMessage(message, errorDetail, errorCode);
        } catch (e) {
            // JSONパース失敗 - HTTPステータスに基づくメッセージ
            message = getHttpStatusMessage(xhr.status);
            showErrorMessage(message, null, errorCode);
        }
        
        // フォールバック: サーバー保存のエラー情報も取得
        fetchDetailedError(function(detailedError) {
            if (detailedError && !errorDetail) {
                showErrorMessage(message, detailedError, errorCode);
            }
        });
    }
    
    /**
     * サーバーから詳細エラー情報を取得
     * 
     * @param {function} callback - コールバック関数。詳細エラー情報またはフォールバックメッセージを受け取る
     */
    function fetchDetailedError(callback) {
        $.ajax({
            url: novelToolSampleImages.apiStatus,
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', novelToolSampleImages.restNonce);
            },
            success: function (response) {
                if (response.error && response.error.message) {
                    // サーバー側に詳細エラーがある場合
                    callback(response.error);
                } else {
                    // 詳細エラーがない場合は簡潔な代替メッセージ
                    callback({
                        message: novelToolSampleImages.strings.errorDetailNotAvailable || 'エラーの詳細情報は記録されていません。サーバーログを確認してください。',
                        timestamp: null
                    });
                }
            },
            error: function () {
                // 詳細エラー取得に失敗した場合のフォールバック
                callback({
                    message: novelToolSampleImages.strings.errorDetailFetchFailed || '詳細の取得に失敗しました。サーバーログを確認してください。',
                    timestamp: null
                });
            }
        });
    }

    /**
     * 成功メッセージを表示
     */
    function showSuccessMessage(message) {
        var modal = $('#noveltool-sample-images-modal');
        var content = modal.find('.noveltool-modal-content');

        content.find('h2').text(novelToolSampleImages.strings.success);
        content.find('p').html('<span style="color: #46b450;">' + message + '</span>');
        content.find('.noveltool-modal-buttons').html(
            $('<button>', {
                class: 'button button-primary',
                text: novelToolSampleImages.strings.closeButton,
                click: function () {
                    // モーダルを閉じてページをリロード（サンプル画像が表示されるように）
                    modal.removeClass('show');
                    setTimeout(function () {
                        modal.remove();
                        location.reload();
                    }, 300);
                }
            })
        );
    }

    /**
     * エラーメッセージを表示
     * 
     * @param {string} message - ユーザー向けエラーメッセージ
     * @param {object|null} errorDetail - 詳細エラー情報オブジェクト
     * @param {string} errorCode - エラーコード（診断用）
     */
    function showErrorMessage(message, errorDetail, errorCode) {
        var modal = $('#noveltool-sample-images-modal');
        var content = modal.find('.noveltool-modal-content');

        content.find('h2').text(novelToolSampleImages.strings.error);
        
        // エラーメッセージと診断コードを表示
        var errorMessage = $('<span>', {
            css: { color: '#dc3232' },
            text: message
        });
        
        // 診断コード（あれば表示）
        var diagnosticCode = $('<div>', {
            class: 'noveltool-diagnostic-code',
            css: { 'font-size': '12px', 'color': '#666', 'margin-top': '8px' }
        });
        if (errorCode) {
            diagnosticCode.text((novelToolSampleImages.strings.diagnosticCode || '診断コード') + ': ' + errorCode);
        }
        
        // ステージ情報を表示（あれば）
        var stageInfo = $('<div>', {
            class: 'noveltool-error-stage',
            css: { 'font-size': '12px', 'color': '#666', 'margin-top': '4px' }
        });
        if (errorDetail && errorDetail.stage) {
            var stageLabels = {
                'fetch_release': novelToolSampleImages.strings.stageFetchRelease || 'リリース情報取得',
                'download': novelToolSampleImages.strings.stageDownload || 'ダウンロード',
                'verify_checksum': novelToolSampleImages.strings.stageVerifyChecksum || 'チェックサム検証',
                'extract': novelToolSampleImages.strings.stageExtract || '展開',
                'filesystem': novelToolSampleImages.strings.stageFilesystem || 'ファイルシステム',
                'other': novelToolSampleImages.strings.stageOther || 'その他'
            };
            var stageLabel = stageLabels[errorDetail.stage] || errorDetail.stage;
            stageInfo.text((novelToolSampleImages.strings.errorStage || 'エラー発生段階') + ': ' + stageLabel);
        }
        
        // 詳細エラー表示エリア
        var errorDetailsSection = $('<div>');
        
        if (errorDetail && (errorDetail.message || errorDetail.code)) {
            var detailsToggle = $('<button>', {
                class: 'noveltool-error-details-toggle',
                text: novelToolSampleImages.strings.showErrorDetails || '詳しいエラーを確認',
                click: function(e) {
                    e.preventDefault();
                    var detailsContent = $(this).next('.noveltool-error-details');
                    if (detailsContent.is(':visible')) {
                        detailsContent.slideUp();
                        $(this).text(novelToolSampleImages.strings.showErrorDetails || '詳しいエラーを確認');
                    } else {
                        detailsContent.slideDown();
                        $(this).text(novelToolSampleImages.strings.hideErrorDetails || '詳細を非表示');
                    }
                }
            });
            
            var errorDetailsDiv = $('<div>', {
                class: 'noveltool-error-details',
                css: { display: 'none' }
            });
            
            // メッセージ
            if (errorDetail.message) {
                var errorDetailsContent = $('<div>', {
                    class: 'noveltool-error-details-content',
                    text: errorDetail.message
                });
                errorDetailsDiv.append(errorDetailsContent);
            }
            
            // コード
            if (errorDetail.code) {
                var codeDiv = $('<div>', {
                    class: 'noveltool-error-code',
                    text: (novelToolSampleImages.strings.errorCode || 'エラーコード') + ': ' + errorDetail.code
                });
                errorDetailsDiv.append(codeDiv);
            }
            
            // タイムスタンプ
            if (errorDetail.timestamp) {
                var date = new Date(errorDetail.timestamp * 1000);
                var timestampText = novelToolSampleImages.strings.errorTimestamp || 'エラー発生時刻: ';
                var errorTimestamp = $('<div>', {
                    class: 'noveltool-error-timestamp',
                    text: timestampText + date.toLocaleString()
                });
                errorDetailsDiv.append(errorTimestamp);
            }
            
            // メタ情報（あれば）
            if (errorDetail.meta && typeof errorDetail.meta === 'object') {
                var metaDiv = $('<div>', {
                    class: 'noveltool-error-meta',
                    css: { 'font-size': '11px', 'color': '#999', 'margin-top': '5px' }
                });
                var metaItems = [];
                for (var key in errorDetail.meta) {
                    if (errorDetail.meta.hasOwnProperty(key)) {
                        metaItems.push(key + ': ' + errorDetail.meta[key]);
                    }
                }
                if (metaItems.length > 0) {
                    metaDiv.text(metaItems.join(', '));
                    errorDetailsDiv.append(metaDiv);
                }
            }
            
            errorDetailsSection.append(detailsToggle).append(errorDetailsDiv);
        }
        
        var troubleshootingBox = $('<div>', {
            css: {
                'margin-top': '15px',
                'padding': '10px',
                'background': '#f9f9f9',
                'border-left': '4px solid #dc3232'
            }
        });
        
        troubleshootingBox.append(
            $('<strong>').text(novelToolSampleImages.strings.troubleshooting)
        );
        
        // トラブルシューティング手順をリストとして追加
        var stepsList = $('<ol>', {
            css: {
                'margin': '10px 0 0 0',
                'padding-left': '20px'
            }
        });
        
        $.each(novelToolSampleImages.strings.troubleshootingSteps, function(index, step) {
            stepsList.append($('<li>').text(step));
        });
        
        troubleshootingBox.append(stepsList);
        
        content.find('p').empty().append(errorMessage).append(diagnosticCode).append(stageInfo).append(errorDetailsSection).append(troubleshootingBox);

        var buttons = $('<div>', {
            class: 'noveltool-modal-buttons'
        });

        var retryButton = $('<button>', {
            class: 'button button-primary',
            text: novelToolSampleImages.strings.retryButton,
            click: function () {
                // ステータスをリセットしてから再試行
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

    /**
     * ステータスをリセットして再試行
     */
    function resetStatusAndRetry() {
        var modal = $('#noveltool-sample-images-modal');
        var content = modal.find('.noveltool-modal-content');
        
        // ボタンを無効化
        content.find('button').prop('disabled', true);
        
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
                content.find('.noveltool-modal-buttons').html(
                    $('<button>', {
                        class: 'button',
                        text: novelToolSampleImages.strings.closeButton,
                        click: function () {
                            closeOnly();
                        }
                    })
                );
            }
        });
    }

    /**
     * ステータスをリセットしてページを再読み込み
     */
    function resetStatusAndReload() {
        $.ajax({
            url: novelToolSampleImages.apiResetStatus,
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', novelToolSampleImages.restNonce);
            },
            complete: function () {
                location.reload();
            }
        });
    }

    /**
     * ダウンロードを中断して状態を初期化
     */
    function abortDownloadAndReload(button) {
        if (!confirm(novelToolSampleImages.strings.abortDownloadConfirm || '現在のダウンロードを中断して初期化します。よろしいですか？')) {
            return;
        }

        if (button && button.length) {
            button.prop('disabled', true).text(novelToolSampleImages.strings.aborting || '中断しています...');
        }

        $.ajax({
            url: novelToolSampleImages.apiResetStatus,
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', novelToolSampleImages.restNonce);
            },
            success: function () {
                location.reload();
            },
            error: function () {
                if (button && button.length) {
                    button.prop('disabled', false).text(novelToolSampleImages.strings.abortDownloadButton || 'ダウンロードを中断');
                }
                alert(novelToolSampleImages.strings.abortFailed || '中断処理に失敗しました。ページを再読み込みして再試行してください。');
            }
        });
    }

    /**
     * 初期化
     */
    $(document).ready(function () {
        // サンプル画像の存在をチェック
        if (novelToolSampleImages.shouldPrompt) {
            // 少し遅延させて表示（ページ読み込み完了後）
            setTimeout(function () {
                showSampleImagesPrompt();
            }, 500);
        }
        
        // バナーのダウンロードボタン処理
        $('#noveltool-download-sample-images-banner').on('click', function (e) {
            e.preventDefault();
            // モーダルを表示してダウンロードを開始
            showSampleImagesPrompt();
        });

        // 「今回はダウンロードしない」ボタン処理
        $('#noveltool-skip-sample-images-download').on('click', function (e) {
            e.preventDefault();

            $.post(ajaxurl, {
                action: 'noveltool_dismiss_sample_images_prompt',
                nonce: novelToolSampleImages.nonce
            }).always(function() {
                $('#noveltool-skip-sample-images-download').closest('.notice').fadeOut();
            });
        });
        
        // ダウンロード進捗バナーの初期化
        if (novelToolSampleImages.hasActiveDownload) {
            initializeProgressBanner();
        }
        
        // 詳細表示ボタンのハンドラ
        $('#noveltool-show-download-details').on('click', function(e) {
            e.preventDefault();
            showDownloadDetailsModal();
        });
    });
    
    /**
     * 進捗バナーを初期化してポーリング開始
     */
    function initializeProgressBanner() {
        var pollInterval = POLL_INTERVAL_MS;
        var startTime = Date.now();
        var pollTimeoutId = null;
        var ajaxErrorCount = 0;
        
        // Page Visibility API でページ非表示時のポーリングを停止
        var isPageVisible = true;
        
        if (typeof document.hidden !== 'undefined') {
            // ページ表示状態の変更を監視
            document.addEventListener('visibilitychange', function() {
                isPageVisible = !document.hidden;
                
                if (isPageVisible && pollTimeoutId === null) {
                    // ページが再表示されたら即座にポーリング再開
                    pollBannerStatus();
                }
            });
        }
        
        function pollBannerStatus() {
            // ページが非表示の場合はスキップ
            if (!isPageVisible) {
                pollTimeoutId = setTimeout(pollBannerStatus, pollInterval);
                return;
            }
            
            $.ajax({
                url: novelToolSampleImages.ajaxUrl,
                method: 'GET',
                data: {
                    action: 'noveltool_check_download_status',
                    nonce: novelToolSampleImages.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        ajaxErrorCount = 0;
                        latestBannerStatusData = response.data;
                        updateBannerProgress(response.data);
                        
                        // 完了または失敗の場合はポーリング停止
                        if (response.data.status === 'completed' || response.data.status === 'failed') {
                            pollTimeoutId = null;
                            if (response.data.status === 'completed') {
                                showBannerComplete();
                            } else {
                                showBannerError(response.data.error || response.data);
                            }
                        } else {
                            // 継続してポーリング
                            pollTimeoutId = setTimeout(pollBannerStatus, pollInterval);
                        }
                    } else {
                        ajaxErrorCount += 1;

                        // サーバーが失敗メッセージを返している場合は即時表示
                        if (response && response.data && response.data.message) {
                            pollTimeoutId = null;
                            showBannerError({ message: response.data.message });
                            return;
                        }

                        // 連続エラー時は固定表示を避けてエラー表示へ遷移
                        if (ajaxErrorCount >= 3) {
                            pollTimeoutId = null;
                            showBannerError({
                                message: novelToolSampleImages.strings.downloadFailed || 'ダウンロード状態の取得に失敗しました。ページを再読み込みして再試行してください。'
                            });
                            return;
                        }

                        // タイムアウトまでは継続
                        if (Date.now() - startTime < MAX_POLL_TIME_MS) {
                            pollTimeoutId = setTimeout(pollBannerStatus, pollInterval);
                        } else {
                            pollTimeoutId = null;
                            showBannerError({
                                message: novelToolSampleImages.strings.downloadTimeout || '状態確認がタイムアウトしました。再試行してください。'
                            });
                        }
                    }
                },
                error: function() {
                    ajaxErrorCount += 1;

                    if (ajaxErrorCount >= 3) {
                        pollTimeoutId = null;
                        showBannerError({
                            message: novelToolSampleImages.strings.downloadFailed || 'ダウンロード状態の取得に失敗しました。ページを再読み込みして再試行してください。'
                        });
                        return;
                    }

                    // エラー時も継続（タイムアウトまで）
                    if (Date.now() - startTime < MAX_POLL_TIME_MS) {
                        pollTimeoutId = setTimeout(pollBannerStatus, pollInterval);
                    } else {
                        pollTimeoutId = null;
                        showBannerError({
                            message: novelToolSampleImages.strings.downloadTimeout || '状態確認がタイムアウトしました。再試行してください。'
                        });
                    }
                }
            });
        }
        
        // 初回ポーリング開始
        pollBannerStatus();
    }
    
    /**
     * バナーの進捗を更新
     */
    function updateBannerProgress(data) {
        var banner = $('#noveltool-download-progress-banner');
        if (banner.length === 0) return;
        
        var progressBar = banner.find('.noveltool-progress-bar');
        var progressFill = banner.find('.noveltool-progress-fill');
        var progressStatus = banner.find('.noveltool-progress-status');
        
        var byteProgress = getByteProgress(data);

        if (byteProgress) {
            progressBar.removeClass('indeterminate');
            progressBar.attr('aria-valuenow', byteProgress.percentage);
            progressFill.css('width', byteProgress.percentage + '%').text(byteProgress.percentage + '%');

            var byteStatus = (novelToolSampleImages.strings.statusDownloadingBytes || 'ダウンロード中: ') + formatBytes(byteProgress.current) + ' / ' + formatBytes(byteProgress.total);
            progressStatus.text(byteStatus);
            ensureRecoveryActionForInProgress(banner, data);
        } else if (typeof data.progress === 'number') {
            // 確定的な進捗
            progressBar.removeClass('indeterminate');
            progressBar.attr('aria-valuenow', data.progress);
            progressFill.css('width', data.progress + '%').text(data.progress + '%');
            
            var statusText = novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...';
            if (data.current_step) {
                switch (data.current_step) {
                    case 'download':
                        statusText = novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...';
                        break;
                    case 'verify':
                        statusText = novelToolSampleImages.strings.statusVerifying || '検証中...';
                        break;
                    case 'extract':
                        statusText = novelToolSampleImages.strings.statusExtracting || '展開中...';
                        break;
                }
            }
            
            if (data.use_background) {
                statusText += ' ' + (novelToolSampleImages.strings.backgroundNote || '(バックグラウンドで処理中)');
            }
            
            progressStatus.text(statusText);
            ensureRecoveryActionForInProgress(banner, data);
        } else {
            // indeterminateモード
            progressBar.addClass('indeterminate');
            progressBar.removeAttr('aria-valuenow');
            progressFill.css('width', '100%').text('');
            progressStatus.text(novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...');
            ensureRecoveryActionForInProgress(banner, data);
        }
    }

    function ensureRecoveryActionForInProgress(banner, data) {
        if (!banner || banner.length === 0 || !data) {
            return;
        }

        var lagSeconds = typeof data.job_update_lag_seconds === 'number' ? data.job_update_lag_seconds : 0;
        var shouldShowRetry = data.status === 'in_progress' && lagSeconds >= 180;

        if (!shouldShowRetry) {
            return;
        }

        if (banner.find('#noveltool-retry-download').length === 0) {
            banner.append('<button type="button" id="noveltool-retry-download" class="button button-secondary" style="margin-top: 10px; margin-left: 8px;">' + (novelToolSampleImages.strings.retryButton || '再試行') + '</button>');
        }

        banner.find('#noveltool-retry-download').off('click').on('click', function() {
            resetStatusAndReload();
        });
    }

    /**
     * ステータス値を表示用ラベルへ変換
     */
    function getReadableStatus(data) {
        if (!data || !data.status) {
            return novelToolSampleImages.strings.checkingStatus || '状態確認中...';
        }

        if (data.status === 'failed') {
            return novelToolSampleImages.strings.error || 'エラー';
        }
        if (data.status === 'completed') {
            return novelToolSampleImages.strings.statusCompleted || '完了';
        }

        if (data.current_step === 'verify') {
            return novelToolSampleImages.strings.statusVerifying || '検証中...';
        }
        if (data.current_step === 'extract') {
            return novelToolSampleImages.strings.statusExtracting || '展開中...';
        }

        return novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...';
    }

    function getReadableStep(step) {
        if (!step) {
            return '-';
        }

        switch (step) {
            case 'download':
                return novelToolSampleImages.strings.stageDownload || 'ダウンロード';
            case 'verify':
                return novelToolSampleImages.strings.statusVerifying || '検証中...';
            case 'extract':
                return novelToolSampleImages.strings.stageExtract || '展開';
            case 'background':
                return novelToolSampleImages.strings.stageBackground || 'バックグラウンド処理';
            default:
                return step;
        }
    }

    /**
     * UNIX時刻を表示文字列へ変換
     */
    function formatStatusTimestamp(timestamp) {
        if (!timestamp || typeof timestamp !== 'number') {
            return '-';
        }
        try {
            return new Date(timestamp * 1000).toLocaleString();
        } catch (e) {
            return '-';
        }
    }

    function formatDuration(seconds) {
        if (typeof seconds !== 'number' || seconds < 0) {
            return '-';
        }
        var total = Math.floor(seconds);
        var mins = Math.floor(total / 60);
        var secs = total % 60;
        if (mins <= 0) {
            return secs + '秒';
        }
        return mins + '分' + secs + '秒';
    }

    /**
     * 詳細情報のHTMLを生成
     */
    function buildDownloadDetailsHtml(data) {
        if (!data) {
            return '<p style="margin: 8px 0;">' + (novelToolSampleImages.strings.checkingStatus || '状態確認中...') + '</p>';
        }

        var html = '';
        html += '<div style="margin-top: 12px; padding: 10px; background: #f6f7f7; border: 1px solid #dcdcde;">';
        html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailStatusLabel || 'ステータス') + ':</strong> ' + getReadableStatus(data) + '</p>';
        html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailProgressLabel || '進捗') + ':</strong> ' + (typeof data.progress === 'number' ? data.progress + '%' : '-') + ' <span style="color:#646970;">(フェーズ目安)</span></p>';
        html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailStepLabel || 'ステップ') + ':</strong> ' + getReadableStep(data.current_step) + '</p>';
        html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailJobIdLabel || 'ジョブID') + ':</strong> ' + (data.job_id || '-') + '</p>';

        var ts = data.status_timestamp || (data.error && data.error.timestamp ? data.error.timestamp : 0);
        html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailUpdatedLabel || '更新時刻') + ':</strong> ' + formatStatusTimestamp(ts) + '</p>';

        if (typeof data.total_assets === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailAssetsLabel || 'アセット数') + ':</strong> ' + data.total_assets + '</p>';
        }
        if (typeof data.total_files === 'number' || typeof data.downloaded_files === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailTotalFilesLabel || '対象ファイル数') + ':</strong> '
                + (typeof data.total_files === 'number' ? data.total_files : '-')
                + ' / <strong>' + (novelToolSampleImages.strings.detailDownloadedFilesLabel || '完了ファイル数') + ':</strong> '
                + (typeof data.downloaded_files === 'number' ? data.downloaded_files : '-')
                + '</p>';
        }
        if (typeof data.total_bytes === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailTotalBytesLabel || '総容量') + ':</strong> ' + formatBytes(data.total_bytes) + '</p>';
        }
        if (typeof data.downloaded_bytes_estimated === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailDownloadedBytesLabel || 'ダウンロード済み容量（推定）') + ':</strong> ' + formatBytes(data.downloaded_bytes_estimated) + '</p>';
        }
        if (typeof data.downloaded_bytes_confirmed === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailConfirmedBytesLabel || 'ダウンロード済み容量（確定）') + ':</strong> ' + formatBytes(data.downloaded_bytes_confirmed) + '</p>';
        }
        if (data.current_download_file) {
            html += '<p style="margin: 4px 0;"><strong>現在のダウンロード:</strong> ' + data.current_download_file + '</p>';
        }
        if (typeof data.missing_jobs === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailMissingJobsLabel || '未検出ジョブ数') + ':</strong> ' + data.missing_jobs + '</p>';
        }
        if (typeof data.active_jobs === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailActiveJobsLabel || '処理中ジョブ数') + ':</strong> ' + data.active_jobs + '</p>';
        }
        if (typeof data.job_last_updated === 'number' && data.job_last_updated > 0) {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailJobLastUpdatedLabel || '最終ジョブ更新時刻') + ':</strong> ' + formatStatusTimestamp(data.job_last_updated) + '</p>';
        }
        if (typeof data.job_update_lag_seconds === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailJobLagLabel || '最終更新からの経過') + ':</strong> ' + formatDuration(data.job_update_lag_seconds) + '</p>';
        }
        if (typeof data.next_process_event === 'number' && data.next_process_event > 0) {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailNextProcessLabel || '次回ジョブ実行予定') + ':</strong> ' + formatStatusTimestamp(data.next_process_event) + '</p>';
        }
        if (typeof data.next_related_event === 'number' && data.next_related_event > 0) {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailNextWatchLabel || '次回監視実行予定') + ':</strong> ' + formatStatusTimestamp(data.next_related_event) + '</p>';
        }
        if (data.auto_recovery && typeof data.auto_recovery === 'object') {
            var recoveryText = (data.auto_recovery.scheduled ? '実行済み' : '未実行') + (data.auto_recovery.reason ? ' (' + data.auto_recovery.reason + ')' : '');
            html += '<p style="margin: 4px 0;"><strong>自動復旧:</strong> ' + recoveryText + '</p>';
        }
        if (data.destination_dir) {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailDestinationLabel || '保存先ディレクトリ') + ':</strong> ' + data.destination_dir + '</p>';
        }

        html += '<p style="margin: 8px 0 4px; color:#646970;"><strong>' + (novelToolSampleImages.strings.detailProgressHintLabel || '進捗表示について') + ':</strong> ' + (novelToolSampleImages.strings.detailProgressHintText || 'この進捗%はフェーズの目安です。') + '</p>';
        if (typeof data.successful_jobs === 'number' || typeof data.failed_jobs === 'number') {
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailQueuedLabel || '成功ジョブ') + ':</strong> '
                + (typeof data.successful_jobs === 'number' ? data.successful_jobs : '-')
                + ' / <strong>' + (novelToolSampleImages.strings.detailFailedQueuedLabel || '失敗ジョブ') + ':</strong> '
                + (typeof data.failed_jobs === 'number' ? data.failed_jobs : '-')
                + '</p>';
        }

        if (data.error) {
            html += '<hr style="margin: 10px 0;" />';
            html += '<p style="margin: 4px 0; color: #b32d2e;"><strong>' + (novelToolSampleImages.strings.error || 'エラー') + ':</strong> ' + (data.error.message || '-') + '</p>';
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.errorCode || 'エラーコード') + ':</strong> ' + (data.error.code || '-') + '</p>';
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.errorStage || 'エラー段階') + ':</strong> ' + (data.error.stage || '-') + '</p>';
        }

        if (Array.isArray(data.failed_assets) && data.failed_assets.length > 0) {
            html += '<hr style="margin: 10px 0;" />';
            html += '<p style="margin: 4px 0;"><strong>' + (novelToolSampleImages.strings.detailFailedAssetsLabel || '失敗したアセット') + ':</strong></p>';
            html += '<ul style="margin: 4px 0 0 20px;">';
            data.failed_assets.forEach(function(asset) {
                var name = asset && asset.name ? asset.name : (novelToolSampleImages.strings.detailUnknownLabel || '不明');
                var message = asset && asset.message ? asset.message : '';
                html += '<li>' + name + (message ? ' - ' + message : '') + '</li>';
            });
            html += '</ul>';
        }

        html += '</div>';
        return html;
    }
    
    /**
     * バナーを完了状態に更新
     */
    function showBannerComplete() {
        var banner = $('#noveltool-download-progress-banner');
        if (banner.length === 0) return;
        
        banner.removeClass('notice-info').addClass('notice-success');
        banner.find('p strong').text(novelToolSampleImages.strings.downloadSuccess || 'サンプル画像のダウンロードが完了しました。');
        banner.find('.noveltool-banner-progress').html(
            '<p style="margin: 0;">' + (novelToolSampleImages.strings.downloadSuccess || 'サンプル画像のダウンロードが完了しました。') + '</p>'
        );
        banner.find('#noveltool-show-download-details').text(novelToolSampleImages.strings.closeButton || '閉じる').off('click').on('click', function() {
            banner.fadeOut(function() {
                banner.remove();
                // ページをリロードしてサンプル画像を反映
                location.reload();
            });
        });
        banner.find('#noveltool-retry-download').remove();
    }
    
    /**
     * バナーをエラー状態に更新
     */
    function showBannerError(error) {
        var banner = $('#noveltool-download-progress-banner');
        if (banner.length === 0) return;
        
        banner.removeClass('notice-info').addClass('notice-error');
        var errorMsg = error && error.message ? error.message : (novelToolSampleImages.strings.downloadFailed || 'ダウンロードに失敗しました。');
        banner.find('p strong').text(novelToolSampleImages.strings.error || 'エラー');
        banner.find('.noveltool-banner-progress').html(
            '<p style="margin: 0; color: #dc3232;">' + errorMsg + '</p>'
        );

        banner.find('#noveltool-show-download-details').text(novelToolSampleImages.strings.viewDetails || 'View Details').off('click').on('click', function(e) {
            e.preventDefault();
            showDownloadDetailsModal();
        });

        if (banner.find('#noveltool-retry-download').length === 0) {
            banner.append('<button type="button" id="noveltool-retry-download" class="button button-secondary" style="margin-top: 10px; margin-left: 8px;">' + (novelToolSampleImages.strings.retryButton || '再試行') + '</button>');
        }

        banner.find('#noveltool-retry-download').off('click').on('click', function() {
            resetStatusAndReload();
        });
    }
    
    /**
     * ダウンロード詳細モーダルを表示
     */
    function showDownloadDetailsModal() {
        // 既存のモーダルがあれば削除
        $('#noveltool-download-details-modal').remove();
        
        var modal = $('<div>', {
            id: 'noveltool-download-details-modal',
            class: 'noveltool-modal-overlay show'
        });
        
        var modalContent = $('<div>', {
            class: 'noveltool-modal-content'
        });
        
        var modalTitle = $('<h2>', {
            text: novelToolSampleImages.strings.downloading || 'ダウンロード中'
        });
        
        var modalMessage = $('<p>', {
            html: novelToolSampleImages.strings.pleaseWait || 'サンプル画像をダウンロード中です。しばらくお待ちください。'
        });

        var detailsContainer = $('<div>', {
            class: 'noveltool-download-details-container',
            html: buildDownloadDetailsHtml(latestBannerStatusData)
        });
        
        var progressBar = showProgressBar();
        
        var modalButtons = $('<div>', {
            class: 'noveltool-modal-buttons'
        });
        
        var closeButton = $('<button>', {
            class: 'button',
            text: novelToolSampleImages.strings.closeButton || '閉じる',
            click: function() {
                modal.removeClass('show');
                setTimeout(function() {
                    modal.remove();
                }, 300);
            }
        });

        var retryButton = $('<button>', {
            class: 'button button-secondary',
            text: novelToolSampleImages.strings.retryButton || '再試行',
            click: function() {
                resetStatusAndReload();
            }
        });

        var abortButton = $('<button>', {
            class: 'button button-link-delete',
            text: novelToolSampleImages.strings.abortDownloadButton || 'ダウンロードを中断',
            click: function() {
                abortDownloadAndReload(abortButton);
            }
        });
        
        modalButtons.append(abortButton).append(retryButton).append(closeButton);
        modalContent.append(modalTitle).append(modalMessage).append(progressBar).append(detailsContainer).append(modalButtons);
        modal.append(modalContent);

        modal.on('click', function (e) {
            if (e.target === this) {
                modal.removeClass('show');
                setTimeout(function() {
                    modal.remove();
                }, 300);
            }
        });

        $('body').append(modal);
        
        // 初期進捗を取得して表示
        $.ajax({
            url: novelToolSampleImages.ajaxUrl,
            method: 'GET',
            data: {
                action: 'noveltool_check_download_status',
                nonce: novelToolSampleImages.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    latestBannerStatusData = response.data;
                    var initialByteProgress = getByteProgress(response.data);
                    if (initialByteProgress) {
                        var initialByteStatus = (novelToolSampleImages.strings.statusDownloadingBytes || 'ダウンロード中: ') + formatBytes(initialByteProgress.current) + ' / ' + formatBytes(initialByteProgress.total);
                        updateProgressBar(initialByteProgress.percentage, initialByteStatus, modal);
                    } else if (typeof response.data.progress === 'number') {
                        updateProgressBar(response.data.progress, novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...', modal);
                    } else {
                        updateProgressBar(null, novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...', modal);
                    }

                    detailsContainer.html(buildDownloadDetailsHtml(response.data));
                }
            }
        });
    }

})(jQuery);
