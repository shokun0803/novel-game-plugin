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
                dismissModal();
            }
        });

        var cancelButton = $('<button>', {
            class: 'button',
            text: novelToolSampleImages.strings.cancelButton,
            click: function () {
                dismissModal();
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
     * モーダルを閉じる
     */
    function dismissModal() {
        var modal = $('#noveltool-sample-images-modal');
        modal.removeClass('show');
        setTimeout(function () {
            modal.remove();
        }, 300);

        // 後で表示しないフラグを設定
        $.post(ajaxurl, {
            action: 'noveltool_dismiss_sample_images_prompt',
            nonce: novelToolSampleImages.nonce
        });
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
            url: novelToolSampleImages.apiUrl + '/sample-images/download',
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
        content.find('p').html('<span style="color: #dc3232;">' + message + '</span>');

        var buttons = $('<div>', {
            class: 'noveltool-modal-buttons'
        });

        var retryButton = $('<button>', {
            class: 'button button-primary',
            text: novelToolSampleImages.strings.retryButton,
            click: function () {
                // モーダルをリセットして再試行
                content.find('h2').text(novelToolSampleImages.strings.modalTitle);
                content.find('p').html(novelToolSampleImages.strings.modalMessage);
                content.find('.noveltool-modal-buttons').html(
                    $('<button>', {
                        class: 'button button-primary',
                        text: novelToolSampleImages.strings.downloadButton,
                        click: function () {
                            startDownload();
                        }
                    })
                );
            }
        });

        var closeButton = $('<button>', {
            class: 'button',
            text: novelToolSampleImages.strings.closeButton,
            click: function () {
                dismissModal();
            }
        });

        buttons.append(retryButton).append(closeButton);
        content.find('.noveltool-modal-buttons').html(buttons);
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
    });

})(jQuery);
