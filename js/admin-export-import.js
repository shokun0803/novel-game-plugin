/**
 * ゲームデータのエクスポート/インポート処理
 *
 * @package NovelGamePlugin
 * @since 1.3.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // エクスポートボタンのクリック処理
        $('.noveltool-export-button').on('click', function() {
            var button = $(this);
            var gameId = button.data('game-id');

            // ボタンを無効化
            button.prop('disabled', true).text(noveltoolExportImport.exporting);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'noveltool_export_game',
                    game_id: gameId,
                    nonce: noveltoolExportImport.exportNonce
                },
                success: function(response) {
                    if (response.success) {
                        // JSONデータをダウンロード
                        var dataStr = JSON.stringify(response.data.data, null, 2);
                        var dataBlob = new Blob([dataStr], {type: 'application/json'});
                        var url = URL.createObjectURL(dataBlob);
                        var link = document.createElement('a');
                        link.href = url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(url);

                        // 成功メッセージを表示
                        showNotice('success', noveltoolExportImport.exportSuccess);
                    } else {
                        showNotice('error', response.data.message || noveltoolExportImport.exportError);
                    }
                },
                error: function() {
                    showNotice('error', noveltoolExportImport.exportError);
                },
                complete: function() {
                    // ボタンを有効化
                    button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButton);
                }
            });
        });

        // ファイル選択時の処理
        $('#noveltool-import-file').on('change', function() {
            var fileInput = $(this);
            var importButton = $('.noveltool-import-button');

            if (fileInput[0].files.length > 0) {
                importButton.prop('disabled', false);
            } else {
                importButton.prop('disabled', true);
            }
        });

        // インポートボタンのクリック処理
        $('.noveltool-import-button').on('click', function() {
            var button = $(this);
            var fileInput = $('#noveltool-import-file')[0];
            var downloadImages = $('#noveltool-download-images').is(':checked');
            var progressDiv = $('.noveltool-import-progress');

            if (fileInput.files.length === 0) {
                showNotice('error', noveltoolExportImport.noFileSelected);
                return;
            }

            var file = fileInput.files[0];

            // ファイルサイズチェック（10MBまで）
            if (file.size > 10 * 1024 * 1024) {
                showNotice('error', noveltoolExportImport.fileTooLarge);
                return;
            }

            // フォームデータの作成
            var formData = new FormData();
            formData.append('action', 'noveltool_import_game');
            formData.append('import_file', file);
            formData.append('download_images', downloadImages ? 'true' : 'false');
            formData.append('nonce', noveltoolExportImport.importNonce);

            // ボタンを無効化し、進捗表示
            button.prop('disabled', true);
            progressDiv.show();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        
                        // ファイル入力をクリア
                        fileInput.value = '';
                        button.prop('disabled', true);
                        
                        // 数秒後にマイゲームページにリダイレクト
                        setTimeout(function() {
                            window.location.href = noveltoolExportImport.myGamesUrl;
                        }, 2000);
                    } else {
                        showNotice('error', response.data.message || noveltoolExportImport.importError);
                    }
                },
                error: function() {
                    showNotice('error', noveltoolExportImport.importError);
                },
                complete: function() {
                    // 進捗非表示
                    progressDiv.hide();
                    button.prop('disabled', false);
                }
            });
        });

        /**
         * 通知メッセージを表示
         *
         * @param {string} type 'success' または 'error'
         * @param {string} message メッセージテキスト
         */
        function showNotice(type, message) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // 既存の通知を削除
            $('.noveltool-export-import-section .notice').remove();
            
            // 新しい通知を追加
            $('.noveltool-export-import-section').prepend(notice);
            
            // 数秒後に自動的に削除
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    });

})(jQuery);
