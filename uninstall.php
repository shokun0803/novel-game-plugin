<?php
/**
 * プラグインアンインストール処理
 *
 * このファイルはWordPressがプラグインをアンインストール（削除）する際に自動的に実行されます。
 * ユーザーの設定に応じて、プラグインデータを削除または保持します。
 *
 * @package NovelGamePlugin
 * @since 1.3.0
 */

// WP_UNINSTALL_PLUGIN が定義されていない場合は直接アクセスとみなし終了
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// WordPress が定義されていない場合も終了
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// グローバル $wpdb オブジェクトを使用
global $wpdb;

// ユーザーの設定を確認
$delete_all_data = get_option( 'noveltool_delete_data_on_uninstall', false );

if ( $delete_all_data ) {
    // オプション有効時：すべてのデータを完全削除
    noveltool_uninstall_delete_all_data( $wpdb );
} else {
    // デフォルト動作：内部フラグのみ削除（ユーザーデータは保持）
    noveltool_uninstall_delete_internal_flags();
}

/**
 * 内部フラグのみを削除する関数（デフォルト動作）
 *
 * ユーザーが作成したゲームデータ、投稿、設定は保持します。
 * プラグインの内部状態フラグのみを削除します。
 *
 * @since 1.3.0
 */
function noveltool_uninstall_delete_internal_flags() {
    // サンプルゲームインストール関連のフラグを削除
    delete_option( 'noveltool_sample_games_installed' );
    delete_option( 'noveltool_pending_sample_install' );
}

/**
 * すべてのプラグインデータを完全削除する関数
 *
 * ユーザーが「アンインストール時にすべてのデータを削除」オプションを有効にした場合のみ実行されます。
 * この処理は元に戻せません。
 *
 * @param object $wpdb WordPress データベースオブジェクト
 * @since 1.3.0
 */
function noveltool_uninstall_delete_all_data( $wpdb ) {
    // エラーハンドリング用のログ記録開始
    $errors = array();

    try {
        // 1. すべての novel_game 投稿を完全削除
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
                'novel_game'
            )
        );

        if ( ! empty( $post_ids ) ) {
            foreach ( $post_ids as $post_id ) {
                // wp_delete_post で完全削除（第2引数 true でゴミ箱を経由しない）
                $result = wp_delete_post( $post_id, true );
                if ( ! $result ) {
                    $errors[] = sprintf( 'Failed to delete post ID: %d', $post_id );
                }
            }
        }

        // 2. noveltool_games オプションを削除
        delete_option( 'noveltool_games' );

        // 3. すべてのフラグマスタを削除（noveltool_game_flags_* パターン）
        $flag_options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( 'noveltool_game_flags_' ) . '%'
            )
        );

        if ( ! empty( $flag_options ) ) {
            foreach ( $flag_options as $option_name ) {
                delete_option( $option_name );
            }
        }

        // 4. すべての noveltool_* オプションを削除
        $all_noveltool_options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( 'noveltool_' ) . '%'
            )
        );

        if ( ! empty( $all_noveltool_options ) ) {
            foreach ( $all_noveltool_options as $option_name ) {
                delete_option( $option_name );
            }
        }

        // 5. 内部フラグも削除
        delete_option( 'noveltool_sample_games_installed' );
        delete_option( 'noveltool_pending_sample_install' );

    } catch ( Exception $e ) {
        // エラーが発生した場合はログに記録
        $errors[] = $e->getMessage();
        error_log( 'Novel Game Plugin Uninstall Error: ' . $e->getMessage() );
    }

    // エラーがあればログに記録（ベストエフォート）
    if ( ! empty( $errors ) ) {
        error_log( 'Novel Game Plugin Uninstall completed with errors: ' . implode( ', ', $errors ) );
    }
}
