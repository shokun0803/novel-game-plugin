/**
 * 共通デバッグログユーティリティ
 *
 * フロントエンドおよび管理画面で使用される debugLog 関数の共通実装。
 * 各ファイルでの重複定義を避け、一貫したログ出力機能を提供します。
 *
 * @package NovelGamePlugin
 * @since 1.5.0
 */

// 安全な debugLog 実装（global）
(function(global) {
    'use strict';

    // debug ロード時に localized オブジェクトの debug 値を優先して読み込む
    // wp_localize_script で渡された値を window.novelGameDebug / window.novelGameAdminDebug に反映
    if ( typeof global.novelGameDebug === 'undefined' && typeof global.novelGameFront !== 'undefined' && typeof global.novelGameFront.debug === 'boolean' ) {
        global.novelGameDebug = !!global.novelGameFront.debug;
    }
    if ( typeof global.novelGameAdminDebug === 'undefined' && typeof global.novelGameMeta !== 'undefined' && typeof global.novelGameMeta.debug === 'boolean' ) {
        global.novelGameAdminDebug = !!global.novelGameMeta.debug;
    }
    if ( typeof global.novelGameAdminDebug === 'undefined' && typeof global.noveltoolExportImport !== 'undefined' && typeof global.noveltoolExportImport.debug === 'boolean' ) {
        global.novelGameAdminDebug = !!global.noveltoolExportImport.debug;
    }

    // フロント/管理でそれぞれ localize したフラグを読み込む（存在する場合）
    var enabled = false;
    if ( typeof global.novelGameDebug !== 'undefined' ) {
        enabled = !!global.novelGameDebug;
    } else if ( typeof global.novelGameAdminDebug !== 'undefined' ) {
        enabled = !!global.novelGameAdminDebug;
    } else if ( typeof global.NOVEL_GAME_DEBUG !== 'undefined' ) {
        enabled = !!global.NOVEL_GAME_DEBUG;
    }

    /**
     * console メソッドを安全に呼び出す
     *
     * @param {string} level ログレベル ('log', 'warn', 'error')
     * @param {Array} args ログ引数
     */
    function safeApplyConsole(level, args) {
        try {
            if ( typeof console !== 'undefined' && console && typeof console[level] === 'function' ) {
                console[level].apply(console, args);
            } else if ( typeof console !== 'undefined' && console && typeof console.log === 'function' ) {
                console.log.apply(console, args);
            }
        } catch (e) {
            // ログ呼び出しに失敗しても処理を中断させない
        }
    }

    /**
     * デバッグログ出力（本番環境では無効化）
     *
     * 第1引数が 'log', 'warn', 'error' のいずれかの場合はログレベルとして扱い、
     * それ以外の場合は従来通り 'log' レベルで全引数を出力します。
     *
     * 注意: debugLog('warn') のように単一引数でレベル名を渡した場合、
     * それはメッセージとして扱われます（'log' レベルで 'warn' という文字列を出力）。
     * レベル指定として認識されるには、2つ以上の引数が必要です。
     *
     * @param {string} levelOrMessage ログレベル ('log', 'warn', 'error') またはログメッセージ
     * @param {...*} args 追加引数
     */
    function debugLog() {
        if (!enabled) {
            return;
        }
        var args = Array.prototype.slice.call(arguments);
        var levels = [ 'log', 'warn', 'error' ];
        var level = 'log';

        if (args.length > 1 && levels.indexOf(args[0]) !== -1) {
            level = args.shift();
        }

        safeApplyConsole(level, args);
    }

    /**
     * デバッグモードを動的に切り替える
     *
     * @param {boolean} value 有効にするかどうか
     */
    debugLog.setEnabled = function(value) {
        enabled = !!value;
    };

    /**
     * 現在のデバッグモード状態を取得する
     *
     * @return {boolean} デバッグモードが有効かどうか
     */
    debugLog.isEnabled = function() {
        return enabled;
    };

    // グローバルに提供する
    global.debugLog = debugLog;
})(window);
