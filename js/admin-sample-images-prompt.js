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
    function updateProgressBar(percentage, statusText) {
        var modal = $('#noveltool-sample-images-modal');
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
    
    /**
     * ダウンロード状態をポーリング
     */
    function pollDownloadStatus(pollInterval, startTime) {
        var maxPollTime = 300000; // 5分
        
        if (Date.now() - startTime > maxPollTime) {
            // タイムアウト
            fetchDetailedError(function(detail) {
                showErrorMessage(novelToolSampleImages.strings.downloadTimeout || 'ダウンロードがタイムアウトしました。', detail);
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
                    if (response.error && response.error.message) {
                        errorMsg = response.error.message;
                    }
                    fetchDetailedError(function(detailedError) {
                        showErrorMessage(errorMsg, detailedError || response.error);
                    });
                } else if (response.status === 'in_progress') {
                    // ダウンロード中
                    var elapsed = Date.now() - startTime;
                    
                    // バイト単位の進捗情報があれば使用
                    if (
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
                        // バイト情報がない場合はindeterminateモード + 段階的ステータス
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

        // REST API でダウンロード開始
        $.ajax({
            url: novelToolSampleImages.apiDownload,
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', novelToolSampleImages.restNonce);
            },
            success: function (response) {
                if (response.success) {
                    // 成功レスポンスが即座に返る場合
                    updateProgressBar(100, novelToolSampleImages.strings.statusCompleted || '完了');
                    setTimeout(function() {
                        showSuccessMessage(response.message);
                    }, 500);
                } else {
                    // 失敗レスポンス - 必ず詳細エラーを取得
                    fetchDetailedError(function(detailedError) {
                        showErrorMessage(response.message, detailedError);
                    });
                }
            },
            error: function (xhr) {
                // ダウンロードが開始されているがレスポンスが遅い、または失敗
                if (xhr.status === 0 || xhr.status === 504 || xhr.status === 502) {
                    // タイムアウトまたはゲートウェイエラー - ポーリングで状態確認
                    updateProgressBar(null, novelToolSampleImages.strings.statusDownloading || 'ダウンロード中...');
                    setTimeout(function() {
                        pollDownloadStatus(3000, downloadStartTime);
                    }, 2000);
                } else {
                    // その他のエラー - 必ず詳細エラーを取得
                    var message = novelToolSampleImages.strings.downloadFailed;
                    var errorDetail = null;
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                        errorDetail = xhr.responseJSON.error || null;
                    }
                    
                    // サーバーから詳細エラーを取得して表示
                    fetchDetailedError(function(detailedError) {
                        showErrorMessage(message, detailedError || errorDetail);
                    });
                }
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
     */
    function showErrorMessage(message, errorDetail) {
        var modal = $('#noveltool-sample-images-modal');
        var content = modal.find('.noveltool-modal-content');

        content.find('h2').text(novelToolSampleImages.strings.error);
        
        // エラーメッセージと対処方法を表示
        var errorMessage = $('<span>', {
            css: { color: '#dc3232' },
            text: message
        });
        
        // 詳細エラー表示エリア
        var errorDetailsSection = $('<div>');
        
        if (errorDetail && errorDetail.message) {
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
            
            var errorDetailsContent = $('<div>', {
                class: 'noveltool-error-details-content',
                text: errorDetail.message
            });
            
            errorDetailsDiv.append(errorDetailsContent);
            
            if (errorDetail.timestamp) {
                var date = new Date(errorDetail.timestamp * 1000);
                var timestampText = novelToolSampleImages.strings.errorTimestamp || 'エラー発生時刻: ';
                var errorTimestamp = $('<div>', {
                    class: 'noveltool-error-timestamp',
                    text: timestampText + date.toLocaleString()
                });
                errorDetailsDiv.append(errorTimestamp);
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
        
        content.find('p').empty().append(errorMessage).append(errorDetailsSection).append(troubleshootingBox);

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
    });

})(jQuery);
