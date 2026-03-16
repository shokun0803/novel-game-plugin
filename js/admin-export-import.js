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

            // 分割ZIPダウンロードリストをリセット
            $('#noveltool-split-zip-download-list').hide().empty();
            $('#noveltool-export-size-info').hide().empty();

            // ZIPが選択されている場合はサイズ情報を取得
            if (select.val() && $('#noveltool-export-as-zip').is(':checked')) {
                fetchExportSizeInfo(select.val());
            }
        });

        // ZIPチェックボックスの変更でボタン文言を切り替え
        $('#noveltool-export-as-zip').on('change', function() {
            var exportButton = $('.noveltool-export-button');
            var label = $(this).is(':checked')
                ? '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButtonZip
                : '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButton;
            exportButton.html(label);

            var gameId = $('#noveltool-export-game-select').val();
            if ($(this).is(':checked') && gameId) {
                fetchExportSizeInfo(gameId);
            } else {
                $('#noveltool-export-size-info').hide().empty();
            }
        });

        /**
         * エクスポートサイズ情報を取得してサイズインジケーターを更新する
         *
         * @param {string} gameId ゲームID
         */
        function fetchExportSizeInfo(gameId) {
            var infoDiv = $('#noveltool-export-size-info');
            infoDiv.show().html('<span class="spinner is-active" style="float:none;"></span> ' + noveltoolExportImport.checkingExportSize);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'noveltool_get_export_size_info',
                    game_id: gameId,
                    nonce: noveltoolExportImport.exportNonce
                },
                success: function(response) {
                    if (response.success) {
                        var d = response.data;
                        var msg;
                        if (d.will_split) {
                            msg = noveltoolExportImport.splitZipInfo
                                .replace('%1$d', d.estimated_parts)
                                .replace('%2$d', d.part_size_mb);
                            infoDiv.html('<span class="dashicons dashicons-info" aria-hidden="true"></span> <strong>' + msg + '</strong>');
                        } else {
                            msg = noveltoolExportImport.singleZipInfo;
                            infoDiv.html('<span class="dashicons dashicons-yes" aria-hidden="true"></span> ' + msg);
                        }
                    } else {
                        infoDiv.hide().empty();
                    }
                },
                error: function() {
                    infoDiv.hide().empty();
                }
            });
        }

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

            // 分割ZIPダウンロードリストをリセット（再エクスポート時のイベントリスナー積み重ね防止）
            $('#noveltool-split-zip-download-list').off('click.noveltool').hide().empty();

            if (exportAsZip) {
                // ZIP形式: XHRを使ってblobとして直接受信（メモリ安全、base64不使用）
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl);
                xhr.responseType = 'blob';

                xhr.onload = function() {
                    var contentType = xhr.getResponseHeader('Content-Type') || '';
                    if (xhr.status === 200 && contentType.indexOf('application/zip') !== -1) {
                        // ZIPをそのままダウンロード（単一ZIP）
                        var contentDisposition = xhr.getResponseHeader('Content-Disposition') || '';
                        var zipFilename = 'export.zip';
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
                        var lbl = '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButtonZip;
                        button.prop('disabled', false).html(lbl);
                    } else {
                        // JSONレスポンス（分割ZIP or フォールバック）を解析
                        var reader = new FileReader();
                        reader.onload = function() {
                            try {
                                var data = JSON.parse(reader.result);
                                if (data.success && data.data) {
                                    var d = data.data;
                                    if (d.format === 'split_zip' && d.parts && d.parts.length > 0) {
                                        // 分割ZIPダウンロードリストを表示
                                        showSplitZipDownloadList(d.export_id, d.total_parts, d.parts);
                                        showNotice('success', noveltoolExportImport.exportSuccess);
                                    } else if (d.format === 'json' && d.data) {
                                        // JSONフォールバック
                                        var dataStr  = JSON.stringify(d.data, null, 2);
                                        var dataBlob = new Blob([dataStr], {type: 'application/json'});
                                        var url  = URL.createObjectURL(dataBlob);
                                        var link2 = document.createElement('a');
                                        link2.href     = url;
                                        link2.download = d.filename;
                                        document.body.appendChild(link2);
                                        link2.click();
                                        document.body.removeChild(link2);
                                        URL.revokeObjectURL(url);
                                        showNotice('error', noveltoolExportImport.zipFallbackWarning);
                                    } else {
                                        var msg = (d.message) || noveltoolExportImport.exportError;
                                        showNotice('error', msg);
                                    }
                                } else {
                                    var errMsg = (data.data && data.data.message) || noveltoolExportImport.exportError;
                                    showNotice('error', errMsg);
                                }
                            } catch (e) {
                                showNotice('error', noveltoolExportImport.exportError);
                            }
                            var lbl2 = '<span class="dashicons dashicons-download"></span> ' + noveltoolExportImport.exportButtonZip;
                            button.prop('disabled', false).html(lbl2);
                        };
                        reader.readAsText(xhr.response);
                        return; // ボタン復元は reader.onload 内で実施
                    }
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

        /**
         * 分割ZIPダウンロードリストを表示する
         *
         * 再エクスポート時にイベントリスナーが積み重ならないよう、
         * listDiv.off('click.noveltool') で既存リスナーをクリアしてから登録する。
         *
         * @param {string} exportId  エクスポートID
         * @param {number} totalParts 総パート数
         * @param {Array}  parts      パート情報の配列
         */
        function showSplitZipDownloadList(exportId, totalParts, parts) {
            var listDiv = $('#noveltool-split-zip-download-list');
            // 再エクスポート時にイベントリスナーが積み重ならないよう先にオフにしてから再構築
            listDiv.off('click.noveltool').empty();

            var title = $('<p><strong>' + noveltoolExportImport.splitZipDownloadAll + ' (' + totalParts + ')</strong></p>');
            listDiv.append(title);

            var ul = $('<ul class="noveltool-split-zip-parts"></ul>');
            $.each(parts, function(i, part) {
                var sizeLabel = part.size ? ' (' + Math.round(part.size / 1024) + 'KB)' : '';
                var label = noveltoolExportImport.splitZipPartLabel
                    .replace('%1$d', part.part)
                    .replace('%2$d', totalParts);
                var btn = $('<button type="button" class="button noveltool-split-zip-part-btn"></button>')
                    .text(label + sizeLabel)
                    .attr('data-export-id', exportId)
                    .attr('data-part', part.part)
                    .attr('data-filename', part.filename);
                var li = $('<li></li>').append(btn);
                ul.append(li);
            });
            listDiv.append(ul);

            // 一括ダウンロードボタン
            var dlAllBtn = $('<button type="button" class="button button-primary noveltool-split-zip-download-all"></button>')
                .html('<span class="dashicons dashicons-download" aria-hidden="true"></span> ' + noveltoolExportImport.splitZipDownloadAll);
            listDiv.append($('<p></p>').append(dlAllBtn));

            listDiv.show();

            // 個別ダウンロードボタン（委譲。.off('click.noveltool') 済みなので多重登録なし）
            listDiv.on('click.noveltool', '.noveltool-split-zip-part-btn', function() {
                downloadSplitZipPart($(this));
            });

            // 一括ダウンロードボタン: dlAllBtn は新しい要素なので直接 .on で問題なし
            dlAllBtn.on('click', function() {
                var allBtns = listDiv.find('.noveltool-split-zip-part-btn');
                downloadPartsSequentially(allBtns, 0);
            });
        }

        /**
         * 分割ZIPの1パートをダウンロードする
         *
         * @param {jQuery} btn ダウンロードボタン要素
         */
        function downloadSplitZipPart(btn) {
            var exportId = btn.attr('data-export-id');
            var part     = btn.attr('data-part');
            var filename = btn.attr('data-filename');
            var origHtml = btn.html();

            btn.prop('disabled', true).text(noveltoolExportImport.splitZipDownloading);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.responseType = 'blob';

            xhr.onload = function() {
                var contentType = xhr.getResponseHeader('Content-Type') || '';
                if (xhr.status === 200 && contentType.indexOf('application/zip') !== -1) {
                    var blobUrl = URL.createObjectURL(xhr.response);
                    var link = document.createElement('a');
                    link.href     = blobUrl;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(blobUrl);
                    btn.prop('disabled', true).addClass('noveltool-part-done').html('<span class="dashicons dashicons-yes" aria-hidden="true"></span> ' + btn.text());
                } else {
                    var errMsg = noveltoolExportImport.splitZipDownloadError.replace('%d', part);
                    btn.prop('disabled', false).html(origHtml);
                    showNotice('error', errMsg);
                }
            };

            xhr.onerror = function() {
                btn.prop('disabled', false).html(origHtml);
                showNotice('error', noveltoolExportImport.splitZipDownloadError.replace('%d', part));
            };

            var form = new FormData();
            form.append('action', 'noveltool_download_split_zip_part');
            form.append('export_id', exportId);
            form.append('part', part);
            form.append('nonce', noveltoolExportImport.exportNonce);
            xhr.send(form);
        }

        /**
         * 複数の分割ZIPパートを順番にダウンロードする
         *
         * @param {jQuery} buttons ダウンロードボタンのjQueryオブジェクト
         * @param {number} index   現在のインデックス
         */
        function downloadPartsSequentially(buttons, index) {
            if (index >= buttons.length) {
                showNotice('success', noveltoolExportImport.splitZipAllDone);
                return;
            }
            var btn = $(buttons[index]);
            if (btn.hasClass('noveltool-part-done')) {
                downloadPartsSequentially(buttons, index + 1);
                return;
            }
            var exportId = btn.attr('data-export-id');
            var part     = btn.attr('data-part');
            var filename = btn.attr('data-filename');

            btn.prop('disabled', true).text(noveltoolExportImport.splitZipDownloading);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.responseType = 'blob';

            xhr.onload = function() {
                var contentType = xhr.getResponseHeader('Content-Type') || '';
                if (xhr.status === 200 && contentType.indexOf('application/zip') !== -1) {
                    var blobUrl = URL.createObjectURL(xhr.response);
                    var link = document.createElement('a');
                    link.href     = blobUrl;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(blobUrl);
                    btn.prop('disabled', true).addClass('noveltool-part-done').html('<span class="dashicons dashicons-yes" aria-hidden="true"></span> ' + btn.text());
                    // 次のパートを少し待ってからダウンロード（ブラウザのダウンロード制限対策）
                    setTimeout(function() {
                        downloadPartsSequentially(buttons, index + 1);
                    }, 1000);
                } else {
                    btn.prop('disabled', false);
                    showNotice('error', noveltoolExportImport.splitZipDownloadError.replace('%d', part));
                }
            };

            xhr.onerror = function() {
                btn.prop('disabled', false);
                showNotice('error', noveltoolExportImport.splitZipDownloadError.replace('%d', part));
            };

            var form = new FormData();
            form.append('action', 'noveltool_download_split_zip_part');
            form.append('export_id', exportId);
            form.append('part', part);
            form.append('nonce', noveltoolExportImport.exportNonce);
            xhr.send(form);
        }

        // ファイル選択時の処理（単一ファイル、逐次アップロード方式）
        $('#noveltool-import-file').on('change', function() {
            var fileInput = $(this);
            var importButton = $('.noveltool-import-button');
            var files = fileInput[0].files;

            if (files.length === 0) {
                importButton.prop('disabled', true);
                return;
            }

            // サイズチェック（1ファイル）
            var isZip = files[0].name.toLowerCase().endsWith('.zip');
            var limit = isZip ? noveltoolExportImport.zipMaxSizeBytes : noveltoolExportImport.jsonMaxSizeBytes;
            if (files[0].size > limit) {
                var msg = isZip ? noveltoolExportImport.fileTooLargeZip : noveltoolExportImport.fileTooLargeJson;
                showNotice('error', msg);
                fileInput.val('');
                importButton.prop('disabled', true);
                return;
            }

            importButton.prop('disabled', false);
        });

        // インポートボタンのクリック処理（単一ファイル逐次アップロード）
        $('.noveltool-import-button').on('click', function() {
            var button = $(this);
            var fileInput = $('#noveltool-import-file')[0];
            var downloadImages = $('#noveltool-download-images').is(':checked');
            var progressDiv = $('.noveltool-import-progress');

            if (fileInput.files.length === 0) {
                showNotice('error', noveltoolExportImport.noFileSelected);
                return;
            }

            var formData = new FormData();
            formData.append('action', 'noveltool_import_game');
            formData.append('download_images', downloadImages ? 'true' : 'false');
            formData.append('nonce', noveltoolExportImport.importNonce);
            formData.append('import_file', fileInput.files[0]);

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
                        var d = response.data;

                        if (d.format === 'split_zip_staging') {
                            // 分割ZIPステージング進行中: 進捗パネルを更新して次パートを促す
                            showStagingProgress(d);
                            fileInput.value = '';
                            button.prop('disabled', true); // 次ファイル選択まで無効
                        } else {
                            // インポート完了（通常ZIP/JSON または分割ZIPファイナライズ完了）
                            hideStagingProgress();
                            var message = d.message;
                            if (d.image_download_failures && d.image_download_failures > 0) {
                                message += ' ' + noveltoolExportImport.imageDownloadFailures.replace('%d', d.image_download_failures);
                            }
                            showNotice('success', message);
                            fileInput.value = '';
                            button.prop('disabled', true);
                        }
                    } else {
                        showNotice('error', response.data.message || noveltoolExportImport.importError);
                    }
                },
                error: function() {
                    showNotice('error', noveltoolExportImport.importError);
                },
                complete: function() {
                    progressDiv.hide();
                    // ファイルが選択されている場合はボタンを再有効化
                    if (fileInput.files.length > 0) {
                        button.prop('disabled', false);
                    }
                }
            });
        });

        /**
         * 分割ZIPステージングの進捗パネルを表示する
         *
         * @param {Object} data サーバーレスポンスの data オブジェクト（split_zip_staging）
         */
        function showStagingProgress(data) {
            var panel = $('#noveltool-split-zip-staging');
            panel.empty();

            var title = $('<p><strong>' + noveltoolExportImport.splitZipStagingTitle + '</strong></p>');
            panel.append(title);

            var ul = $('<ul class="noveltool-staging-parts"></ul>');
            for (var i = 1; i <= data.total_parts; i++) {
                var isDone = data.staged_parts.indexOf(i) >= 0;
                var iconCls = isDone ? 'dashicons-yes' : 'dashicons-clock';
                var statusTxt = isDone
                    ? noveltoolExportImport.splitZipPartUploaded
                    : noveltoolExportImport.splitZipPartWaiting;
                var partLabel = noveltoolExportImport.splitZipPartNOfM
                    .replace('%1$d', i)
                    .replace('%2$d', data.total_parts);
                ul.append(
                    '<li><span class="dashicons ' + iconCls + '" aria-hidden="true"></span> ' +
                    partLabel + ': <em>' + statusTxt + '</em></li>'
                );
            }
            panel.append(ul);

            if (data.missing_parts && data.missing_parts.length > 0) {
                var nextPart = data.missing_parts[0];
                var hint = noveltoolExportImport.splitZipUploadNext
                    .replace('%1$d', nextPart)
                    .replace('%2$d', data.total_parts);
                panel.append('<p class="description">' + hint + '</p>');
            }

            panel.show();
        }

        /**
         * ステージング進捗パネルを非表示にする
         */
        function hideStagingProgress() {
            $('#noveltool-split-zip-staging').hide().empty();
        }

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
