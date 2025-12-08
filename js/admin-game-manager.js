/**
 * ゲーム管理画面用スクリプト
 *
 * @package NovelGamePlugin
 * @since 1.3.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // カラーピッカーの初期化
        if ($.fn.wpColorPicker) {
            $('.noveltool-color-picker').wpColorPicker();
        }
    });

})(jQuery);
