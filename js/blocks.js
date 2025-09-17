/**
 * ノベルゲーム専用Gutenbergブロック
 *
 * @package NovelGamePlugin
 * @since 1.2.0
 */

( function( blocks, element, editor, components, i18n, data ) {
    'use strict';

    var el = element.createElement;
    var __ = i18n.__;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var RangeControl = components.RangeControl;
    var PanelBody = components.PanelBody;
    var InspectorControls = editor.InspectorControls;
    var Placeholder = components.Placeholder;
    var Spinner = components.Spinner;
    var useSelect = data.useSelect;
    var useState = element.useState;
    var useEffect = element.useEffect;

    /**
     * ゲーム一覧を取得するフック
     */
    function useGamesList() {
        var games = useState( [] );
        var setGames = games[1];
        var isLoading = useState( true );
        var setIsLoading = isLoading[1];

        useEffect( function() {
            wp.apiFetch( {
                path: '/noveltool/v1/games'
            } ).then( function( gamesList ) {
                setGames( gamesList );
                setIsLoading( false );
            } ).catch( function() {
                setIsLoading( false );
            } );
        }, [] );

        return {
            games: games[0],
            isLoading: isLoading[0]
        };
    }

    /**
     * ノベルゲーム一覧ブロック
     */
    blocks.registerBlockType( 'noveltool/game-list', {
        title: __( 'ノベルゲーム一覧', 'novel-game-plugin' ),
        description: __( 'ノベルゲームの一覧または個別ゲームを表示します', 'novel-game-plugin' ),
        icon: 'games',
        category: 'embed',
        keywords: [
            __( 'ノベル', 'novel-game-plugin' ),
            __( 'ゲーム', 'novel-game-plugin' ),
            __( 'novel', 'novel-game-plugin' ),
            __( 'game', 'novel-game-plugin' )
        ],
        supports: {
            html: false,
            align: [ 'wide', 'full' ]
        },
        attributes: {
            gameType: {
                type: 'string',
                default: 'all'
            },
            gameTitle: {
                type: 'string',
                default: ''
            },
            showCount: {
                type: 'boolean',
                default: true
            },
            showDescription: {
                type: 'boolean',
                default: false
            },
            columns: {
                type: 'number',
                default: 3
            },
            orderby: {
                type: 'string',
                default: 'title'
            },
            order: {
                type: 'string',
                default: 'ASC'
            }
        },

        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var gamesList = useGamesList();

            function onGameTypeChange( newGameType ) {
                setAttributes( { 
                    gameType: newGameType,
                    gameTitle: newGameType === 'all' ? '' : attributes.gameTitle
                } );
            }

            function onGameTitleChange( newGameTitle ) {
                setAttributes( { gameTitle: newGameTitle } );
            }

            function onShowCountChange( newShowCount ) {
                setAttributes( { showCount: newShowCount } );
            }

            function onShowDescriptionChange( newShowDescription ) {
                setAttributes( { showDescription: newShowDescription } );
            }

            function onColumnsChange( newColumns ) {
                setAttributes( { columns: newColumns } );
            }

            function onOrderByChange( newOrderBy ) {
                setAttributes( { orderby: newOrderBy } );
            }

            function onOrderChange( newOrder ) {
                setAttributes( { order: newOrder } );
            }

            // ゲーム選択オプション
            var gameTypeOptions = [
                { label: __( '全ゲーム一覧', 'novel-game-plugin' ), value: 'all' },
                { label: __( '個別ゲーム', 'novel-game-plugin' ), value: 'single' }
            ];

            // 並び順オプション
            var orderByOptions = [
                { label: __( 'タイトル', 'novel-game-plugin' ), value: 'title' },
                { label: __( '作成日', 'novel-game-plugin' ), value: 'date' },
                { label: __( '更新日', 'novel-game-plugin' ), value: 'modified' }
            ];

            var orderOptions = [
                { label: __( '昇順', 'novel-game-plugin' ), value: 'ASC' },
                { label: __( '降順', 'novel-game-plugin' ), value: 'DESC' }
            ];

            // プレビュー用の説明文
            var previewText = '';
            if ( attributes.gameType === 'all' ) {
                previewText = __( '全ゲーム一覧が表示されます', 'novel-game-plugin' );
            } else if ( attributes.gameTitle ) {
                previewText = __( 'ゲーム「{title}」が表示されます', 'novel-game-plugin' ).replace( '{title}', attributes.gameTitle );
            } else {
                previewText = __( 'ゲームを選択してください', 'novel-game-plugin' );
            }

            return [
                // サイドバーの設定パネル
                el( InspectorControls, {},
                    el( PanelBody, {
                        title: __( 'ゲーム設定', 'novel-game-plugin' ),
                        initialOpen: true
                    },
                        // ゲームタイプ選択
                        el( SelectControl, {
                            label: __( '表示タイプ', 'novel-game-plugin' ),
                            value: attributes.gameType,
                            options: gameTypeOptions,
                            onChange: onGameTypeChange
                        } ),

                        // 個別ゲーム選択（個別ゲームタイプの場合のみ表示）
                        attributes.gameType === 'single' && ! gamesList.isLoading && el( SelectControl, {
                            label: __( 'ゲーム選択', 'novel-game-plugin' ),
                            value: attributes.gameTitle,
                            options: gamesList.games,
                            onChange: onGameTitleChange
                        } ),

                        // ゲーム一覧の場合の詳細設定
                        attributes.gameType === 'all' && [
                            el( ToggleControl, {
                                key: 'showCount',
                                label: __( 'シーン数を表示', 'novel-game-plugin' ),
                                checked: attributes.showCount,
                                onChange: onShowCountChange
                            } ),

                            el( ToggleControl, {
                                key: 'showDescription',
                                label: __( 'ゲーム説明を表示', 'novel-game-plugin' ),
                                checked: attributes.showDescription,
                                onChange: onShowDescriptionChange
                            } ),

                            el( RangeControl, {
                                key: 'columns',
                                label: __( '表示列数', 'novel-game-plugin' ),
                                value: attributes.columns,
                                onChange: onColumnsChange,
                                min: 1,
                                max: 6
                            } ),

                            el( SelectControl, {
                                key: 'orderby',
                                label: __( '並び順', 'novel-game-plugin' ),
                                value: attributes.orderby,
                                options: orderByOptions,
                                onChange: onOrderByChange
                            } ),

                            el( SelectControl, {
                                key: 'order',
                                label: __( '順序', 'novel-game-plugin' ),
                                value: attributes.order,
                                options: orderOptions,
                                onChange: onOrderChange
                            } )
                        ]
                    )
                ),

                // メインのブロックコンテンツ
                el( 'div', { className: 'noveltool-block-container' },
                    gamesList.isLoading ? 
                        el( Placeholder, {
                            icon: 'games',
                            label: __( 'ノベルゲーム一覧', 'novel-game-plugin' ),
                            instructions: __( 'ゲームデータを読み込み中...', 'novel-game-plugin' )
                        },
                            el( Spinner )
                        ) :
                        el( Placeholder, {
                            icon: 'games',
                            label: __( 'ノベルゲーム一覧', 'novel-game-plugin' ),
                            instructions: previewText
                        } )
                )
            ];
        },

        save: function() {
            // サーバーサイドでレンダリングするため、save関数では何も返さない
            return null;
        }
    } );

} )( 
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor || window.wp.editor,
    window.wp.components,
    window.wp.i18n,
    window.wp.data
);