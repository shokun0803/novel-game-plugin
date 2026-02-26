/**
 * ゲームデータのエクスポート/インポート処理
 *
 * @package NovelGamePlugin
 * @since 1.3.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // ゲーム選択ドロップダウンの変更処理
        $('#noveltool-export-game-select').on('change', function() {
            var select = $(this);
            var exportButton = $('.noveltool-export-button');
            
            if (select.val()) {
                exportButton.prop('disabled', false);
            } else {
                exportButton.prop('disabled', true);
            }
        });

        // ZIPチェックボックスの変更でボタン文言を切り替え
        $('#noveltool-export-as-zip').on('change', function() {
            var exportButton = $('.noveltool-export-button');
            var label = $(this).is(':checked')
                ? '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButtonZip
                : '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButton;
            if (!exportButton.prop('disabled')) {
                exportButton.html(label);
            } else {
                // ボタンが無効でもラベルを更新
                exportButton.html(label);
            }
        });

        // エクスポートボタンのクリック処理
        $('.noveltool-export-button').on('click', function() {
            var button = $(this);
            var gameId = button.data('game-id');
            var exportAsZip = $('#noveltool-export-as-zip').is(':checked');
            
            // ドロップダウンからゲームIDを取得（専用画面の場合）
            var gameSelect = $('#noveltool-export-game-select');
            if (gameSelect.length && gameSelect.val()) {
                gameId = gameSelect.val();
            }
            
            // ゲームIDが未選択の場合
            if (!gameId) {
                if (noveltoolExportImport.noGameSelected) {
                    showNotice('error', noveltoolExportImport.noGameSelected);
                }
                return;
            }

            // ボタンを無効化
            var exportingLabel = exportAsZip
                ? '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.collectingImages
                : '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exporting;
            button.prop('disabled', true).html(exportingLabel);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'noveltool_export_game',
                    game_id: gameId,
                    export_as_zip: exportAsZip ? 'true' : 'false',
                    nonce: noveltoolExportImport.exportNonce
                },
                success: function(response) {
                    if (response.success) {
                        var filename = response.data.filename;
                        var format   = response.data.format || 'json';

                        if (format === 'zip' && response.data.zip_base64) {
                            // base64デコードしてZIPをダウンロード
                            var byteChars   = atob(response.data.zip_base64);
                            var byteNumbers = new Array(byteChars.length);
                            for (var i = 0; i < byteChars.length; i++) {
                                byteNumbers[i] = byteChars.charCodeAt(i);
                            }
                            var byteArray = new Uint8Array(byteNumbers);
                            var dataBlob  = new Blob([byteArray], {type: 'application/zip'});
                            var url  = URL.createObjectURL(dataBlob);
                            var link = document.createElement('a');
                            link.href     = url;
                            link.download = filename;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            URL.revokeObjectURL(url);
                        } else {
                            // JSON形式でダウンロード
                            var dataStr  = JSON.stringify(response.data.data, null, 2);
                            var dataBlob = new Blob([dataStr], {type: 'application/json'});
                            var url  = URL.createObjectURL(dataBlob);
                            var link = document.createElement('a');
                            link.href     = url;
                            link.download = filename;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            URL.revokeObjectURL(url);
                        }

                        // ZIP作成失敗フォールバック警告
                        if (response.data.warning && noveltoolExportImport.zipFallbackWarning) {
                            showNotice('error', noveltoolExportImport.zipFallbackWarning);
                        } else {
                            showNotice('success', noveltoolExportImport.exportSuccess);
                        }
                    } else {
                        showNotice('error', response.data.message || noveltoolExportImport.exportError);
                    }
                },
                error: function() {
                    showNotice('error', noveltoolExportImport.exportError);
                },
                complete: function() {
                    // ボタンを有効化し文言を元に戻す
                    var isZip = $('#noveltool-export-as-zip').is(':checked');
                    var label = isZip
                        ? '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButtonZip
                        : '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButton;
                    button.prop('disabled', false).html(label);
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

            // ZIPの場合はサイズ上限を100MBに拡張
            var isZipFile = file.name.toLowerCase().endsWith('.zip');
            var maxSize   = isZipFile ? noveltoolExportImport.zipMaxSizeBytes : noveltoolExportImport.jsonMaxSizeBytes;
            if (file.size > maxSize) {
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
                        var message = response.data.message;
                        
                        // 画像ダウンロード失敗件数を追加
                        if (response.data.image_download_failures && response.data.image_download_failures > 0) {
                            message += ' ' + noveltoolExportImport.imageDownloadFailures.replace('%d', response.data.image_download_failures);
                        }
                        
                        showNotice('success', message);
                        
                        // ファイル入力をクリア
                        fileInput.value = '';
                        button.prop('disabled', true);
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
            
            // 既存の通知を削除（専用画面とセクション両方対応）
            $('.noveltool-export-import-section .notice, .noveltool-export-import-container .notice').remove();
            
            // 新しい通知を追加
            var container = $('.noveltool-export-import-container');
            if (container.length) {
                // 専用画面
                container.prepend(notice);
            } else {
                // 旧セクション（互換性のため残す）
                $('.noveltool-export-import-section').prepend(notice);
            }

            // アクセシビリティ向上: 通知にフォーカスを移動
            notice.attr('tabindex', -1).focus();
        }
    });

})(jQuery);
