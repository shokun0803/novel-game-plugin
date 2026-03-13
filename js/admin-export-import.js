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

            if (exportAsZip) {
                // ZIP形式: XHRを使ってblobとして直接受信（メモリ安全、base64不使用）
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl);
                xhr.responseType = 'blob';

                xhr.onload = function() {
                    var contentType = xhr.getResponseHeader('Content-Type') || '';
                    if (xhr.status === 200 && contentType.indexOf('application/zip') !== -1) {
                        // ZIPをそのままダウンロード
                        var contentDisposition = xhr.getResponseHeader('Content-Disposition') || '';
                        var zipFilename = 'export.zip';
                        // RFC5987: filename*=UTF-8''<encoded> を優先し、なければ filename= を使用
                        var fnStarMatch = contentDisposition.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
                        if (fnStarMatch) {
                            try { zipFilename = decodeURIComponent(fnStarMatch[1].trim()); } catch (e) { /* fallback below */ }
                        }
                        if (zipFilename === 'export.zip') {
                            var fnMatch = contentDisposition.match(/filename="([^"]+)"/);
                            if (fnMatch) { zipFilename = fnMatch[1]; }
                        }
                        var blobUrl = URL.createObjectURL(xhr.response);
                        var link = document.createElement('a');
                        link.href     = blobUrl;
                        link.download = zipFilename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(blobUrl);
                        showNotice('success', noveltoolExportImport.exportSuccess);
                    } else {
                        // JSON フォールバック: blob → text → parse
                        var reader = new FileReader();
                        reader.onload = function() {
                            try {
                                var data = JSON.parse(reader.result);
                                if (data.success && data.data && data.data.data) {
                                    var dataStr  = JSON.stringify(data.data.data, null, 2);
                                    var dataBlob = new Blob([dataStr], {type: 'application/json'});
                                    var url  = URL.createObjectURL(dataBlob);
                                    var link = document.createElement('a');
                                    link.href     = url;
                                    link.download = data.data.filename;
                                    document.body.appendChild(link);
                                    link.click();
                                    document.body.removeChild(link);
                                    URL.revokeObjectURL(url);
                                    showNotice('error', noveltoolExportImport.zipFallbackWarning);
                                } else {
                                    var msg = (data.data && data.data.message) || noveltoolExportImport.exportError;
                                    showNotice('error', msg);
                                }
                            } catch (e) {
                                showNotice('error', noveltoolExportImport.exportError);
                            }
                        };
                        reader.readAsText(xhr.response);
                    }
                    // ボタンを元に戻す
                    var lbl = '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButtonZip;
                    button.prop('disabled', false).html(lbl);
                };

                xhr.onerror = function() {
                    showNotice('error', noveltoolExportImport.exportError);
                    var lbl = '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButtonZip;
                    button.prop('disabled', false).html(lbl);
                };

                var xhrForm = new FormData();
                xhrForm.append('action', 'noveltool_export_game');
                xhrForm.append('game_id', gameId);
                xhrForm.append('export_as_zip', 'true');
                xhrForm.append('nonce', noveltoolExportImport.exportNonce);
                xhr.send(xhrForm);
                return; // $.ajax は使用しない
            }

            // JSON形式: 既存の $.ajax() を使用
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'noveltool_export_game',
                    game_id: gameId,
                    export_as_zip: 'false',
                    nonce: noveltoolExportImport.exportNonce
                },
                success: function(response) {
                    if (response.success) {
                        // JSON形式でダウンロード
                        var dataStr  = JSON.stringify(response.data.data, null, 2);
                        var dataBlob = new Blob([dataStr], {type: 'application/json'});
                        var url  = URL.createObjectURL(dataBlob);
                        var link = document.createElement('a');
                        link.href     = url;
                        link.download = response.data.filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(url);
                        showNotice('success', noveltoolExportImport.exportSuccess);
                    } else {
                        showNotice('error', response.data.message || noveltoolExportImport.exportError);
                    }
                },
                error: function() {
                    showNotice('error', noveltoolExportImport.exportError);
                },
                complete: function() {
                    // ボタンを有効化し文言を元に戻す
                    var label = '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButton;
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

            // ZIPの場合はサイズ上限を切り替え
            var isZipFile = file.name.toLowerCase().endsWith('.zip');
            var maxSize   = isZipFile ? noveltoolExportImport.zipMaxSizeBytes : noveltoolExportImport.jsonMaxSizeBytes;
            if (file.size > maxSize) {
                var tooLargeMsg = isZipFile ? noveltoolExportImport.fileTooLargeZip : noveltoolExportImport.fileTooLargeJson;
                showNotice('error', tooLargeMsg);
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
