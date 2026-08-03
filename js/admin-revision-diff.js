/**
 * リビジョン比較画面の表示制御オプション
 *
 * 「変更行のみ表示」「同一行の折りたたみ表示」「差分データのJSON出力」を提供する。
 * リビジョンの差分テーブルはコア（wp.revisions）がAjaxで取得するたびに
 * 動的に再描画されるため、MutationObserverで再描画を検知して状態を再適用する。
 *
 * @package NovelGamePlugin
 * @since 1.8.0
 */

(function ($) {
    'use strict';

    var COLLAPSE_THRESHOLD = 3;
    var state = {
        changesOnly: false,
        collapseUnchanged: false
    };

    /**
     * table.diff の行が「変更なし」行かどうかを判定する
     *
     * @param {Element} row tr要素
     * @return {boolean}
     */
    function isContextRow(row) {
        return row.querySelector('.diff-context') !== null;
    }

    /**
     * 折りたたみ用に挿入したサマリー行を全て取り除き、元の行を復元する
     *
     * @param {Element} frame .revisions-diff-frame要素
     */
    function clearCollapsedGroups(frame) {
        var summaries = frame.querySelectorAll('.noveltool-diff-collapsed-summary');
        summaries.forEach(function (summary) {
            var rows = JSON.parse(summary.getAttribute('data-rows') || '[]');
            rows.forEach(function (index) {
                var row = frame.querySelector('[data-noveltool-row="' + index + '"]');
                if (row) {
                    row.style.display = '';
                }
            });
            summary.parentNode.removeChild(summary);
        });
    }

    /**
     * 差分テーブルの各行に一意なインデックスを付与する（折りたたみ復元用）
     *
     * @param {Element} frame .revisions-diff-frame要素
     */
    function tagRows(frame) {
        var rows = frame.querySelectorAll('table.diff tbody tr');
        rows.forEach(function (row, index) {
            row.setAttribute('data-noveltool-row', String(index));
        });
    }

    /**
     * 表示制御オプションを差分テーブルに適用する
     *
     * @param {Element} frame .revisions-diff-frame要素
     */
    function applyState(frame) {
        var tables = frame.querySelectorAll('table.diff');

        tables.forEach(function (table) {
            clearCollapsedGroups(table.parentNode === frame ? frame : table);

            var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
            var runStart = -1;

            for (var i = 0; i <= rows.length; i++) {
                var row = rows[i];
                var context = row ? isContextRow(row) : false;

                if (context) {
                    row.style.display = state.changesOnly ? 'none' : '';
                    if (runStart === -1) {
                        runStart = i;
                    }
                } else {
                    if (runStart !== -1) {
                        collapseRunIfNeeded(table, rows, runStart, i - 1);
                        runStart = -1;
                    }
                }
            }
        });
    }

    /**
     * 「変更なし」行が連続する区間を、折りたたみ表示が有効な場合にまとめる
     *
     * @param {Element} table  table.diff要素
     * @param {Element[]} rows tr要素の配列
     * @param {number} start   区間の開始インデックス
     * @param {number} end     区間の終了インデックス
     */
    function collapseRunIfNeeded(table, rows, start, end) {
        if (state.changesOnly || !state.collapseUnchanged) {
            return;
        }

        var length = end - start + 1;
        if (length < COLLAPSE_THRESHOLD) {
            return;
        }

        var indexes = [];
        for (var i = start; i <= end; i++) {
            rows[i].style.display = 'none';
            indexes.push(parseInt(rows[i].getAttribute('data-noveltool-row'), 10));
        }

        var summaryRow = document.createElement('tr');
        summaryRow.className = 'noveltool-diff-collapsed-summary';
        summaryRow.setAttribute('data-rows', JSON.stringify(indexes));

        var cell = document.createElement('td');
        cell.colSpan = 2;

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'button-link noveltool-diff-collapsed-toggle';
        button.textContent = (window.noveltoolRevisionDiff && window.noveltoolRevisionDiff.unchangedLinesLabel || '%d unchanged lines').replace('%d', String(length));
        button.addEventListener('click', function () {
            indexes.forEach(function (index) {
                var target = table.querySelector('[data-noveltool-row="' + index + '"]');
                if (target) {
                    target.style.display = '';
                }
            });
            summaryRow.parentNode.removeChild(summaryRow);
        });

        cell.appendChild(button);
        summaryRow.appendChild(cell);

        rows[start].parentNode.insertBefore(summaryRow, rows[start]);
    }

    /**
     * 現在比較中のリビジョンIDを取得する
     *
     * @return {{postId: number, fromId: number, toId: number}|null}
     */
    function getCurrentComparison() {
        if (!window.wp || !wp.revisions || !wp.revisions.view || !wp.revisions.view.frame) {
            return null;
        }

        var frameModel = wp.revisions.view.frame.model;
        var toModel = frameModel.get('to');
        var fromModel = frameModel.get('from');

        if (!toModel) {
            return null;
        }

        return {
            postId: parseInt(frameModel.get('postId'), 10) || 0,
            fromId: fromModel ? parseInt(fromModel.id, 10) : 0,
            toId: parseInt(toModel.id, 10) || 0
        };
    }

    /**
     * 現在の差分データをJSONファイルとしてダウンロードする
     */
    function exportCurrentDiffAsJson() {
        var comparison = getCurrentComparison();
        if (!comparison || !comparison.postId || !comparison.toId) {
            window.alert(window.noveltoolRevisionDiff && window.noveltoolRevisionDiff.exportErrorLabel);
            return;
        }

        $.post(ajaxurl, {
            action: 'noveltool_get_revision_diff_json',
            nonce: window.noveltoolRevisionDiff.ajaxNonce,
            post_id: comparison.postId,
            from_id: comparison.fromId,
            to_id: comparison.toId
        }).done(function (response) {
            if (!response || !response.success) {
                window.alert(window.noveltoolRevisionDiff && window.noveltoolRevisionDiff.exportErrorLabel);
                return;
            }

            var payload = JSON.stringify(response.data, null, 2);
            var blob = new Blob([payload], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');

            link.href = url;
            link.download = 'novel-game-revision-diff-' + comparison.fromId + '-' + comparison.toId + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }).fail(function () {
            window.alert(window.noveltoolRevisionDiff && window.noveltoolRevisionDiff.exportErrorLabel);
        });
    }

    /**
     * 表示制御ツールバーを構築し、差分フレームの直前に挿入する
     *
     * @param {Element} frame .revisions-diff-frame要素
     * @return {Element} ツールバー要素
     */
    function buildToolbar(frame) {
        var settings = window.noveltoolRevisionDiff || {};
        var toolbar = document.createElement('div');
        toolbar.className = 'noveltool-revision-diff-controls';

        var changesOnlyLabel = document.createElement('label');
        var changesOnlyCheckbox = document.createElement('input');
        changesOnlyCheckbox.type = 'checkbox';
        changesOnlyCheckbox.addEventListener('change', function () {
            state.changesOnly = changesOnlyCheckbox.checked;
            collapseCheckbox.disabled = state.changesOnly;
            applyState(frame);
        });
        changesOnlyLabel.appendChild(changesOnlyCheckbox);
        changesOnlyLabel.appendChild(document.createTextNode(' ' + (settings.showChangesOnlyLabel || 'Show changed lines only')));

        var collapseLabel = document.createElement('label');
        var collapseCheckbox = document.createElement('input');
        collapseCheckbox.type = 'checkbox';
        collapseCheckbox.addEventListener('change', function () {
            state.collapseUnchanged = collapseCheckbox.checked;
            applyState(frame);
        });
        collapseLabel.appendChild(collapseCheckbox);
        collapseLabel.appendChild(document.createTextNode(' ' + (settings.collapseUnchangedLabel || 'Collapse unchanged lines')));

        var exportButton = document.createElement('button');
        exportButton.type = 'button';
        exportButton.className = 'button noveltool-diff-export-json';
        exportButton.textContent = settings.exportJsonLabel || 'Export as JSON';
        exportButton.addEventListener('click', exportCurrentDiffAsJson);

        toolbar.appendChild(changesOnlyLabel);
        toolbar.appendChild(collapseLabel);
        toolbar.appendChild(exportButton);

        frame.parentNode.insertBefore(toolbar, frame);

        return toolbar;
    }

    /**
     * 差分フレームの動的な再描画を監視し、表示制御オプションを再適用する
     *
     * @param {Element} frame .revisions-diff-frame要素
     */
    function observeFrame(frame) {
        var observer = new MutationObserver(function () {
            tagRows(frame);
            applyState(frame);
        });
        observer.observe(frame, { childList: true, subtree: true });

        tagRows(frame);
        applyState(frame);
    }

    $(function () {
        var frame = document.querySelector('.revisions-diff-frame');
        if (!frame) {
            return;
        }

        buildToolbar(frame);
        observeFrame(frame);
    });
})(jQuery);
