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
     * ダウンロードを開始
     */
    function startDownload() {
        var modal = $('#noveltool-sample-images-modal');
        var content = modal.find('.noveltool-modal-content');

        // ボタンを無効化
        content.find('button').prop('disabled', true);

        // プログレス表示に変更
        content.find('h2').text(novelToolSampleImages.strings.downloading);
        content.find('p').html(novelToolSampleImages.strings.pleaseWait);
        content.find('.noveltool-modal-buttons').html('<div class="noveltool-spinner"></div>');

        // REST API でダウンロード開始
        $.ajax({
            url: novelToolSampleImages.apiDownload,
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', novelToolSampleImages.restNonce);
            },
            success: function (response) {
                if (response.success) {
                    showSuccessMessage(response.message);
                } else {
                    showErrorMessage(response.message);
                }
            },
            error: function (xhr) {
                var message = novelToolSampleImages.strings.downloadFailed;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showErrorMessage(message);
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
    function showErrorMessage(message) {
        var modal = $('#noveltool-sample-images-modal');
        var content = modal.find('.noveltool-modal-content');

        content.find('h2').text(novelToolSampleImages.strings.error);
        
        // エラーメッセージと対処方法を表示
        var errorMessage = $('<span>', {
            css: { color: '#dc3232' },
            text: message
        });
        
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
        
        content.find('p').empty().append(errorMessage).append(troubleshootingBox);

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
