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
        title: __( 'Novel Game List', 'novel-game-plugin' ),
        description: __( 'Display a list of novel games or an individual game', 'novel-game-plugin' ),
        icon: 'games',
        category: 'embed',
        keywords: [
            __( 'Novel', 'novel-game-plugin' ),
            __( 'Game', 'novel-game-plugin' ),
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
                { label: __( 'All Games List', 'novel-game-plugin' ), value: 'all' },
                { label: __( 'Individual Game', 'novel-game-plugin' ), value: 'single' }
            ];

            // 並び順オプション
            var orderByOptions = [
                { label: __( 'Title', 'novel-game-plugin' ), value: 'title' },
                { label: __( 'Date Created', 'novel-game-plugin' ), value: 'date' },
                { label: __( 'Date Modified', 'novel-game-plugin' ), value: 'modified' }
            ];

            var orderOptions = [
                { label: __( 'Ascending', 'novel-game-plugin' ), value: 'ASC' },
                { label: __( 'Descending', 'novel-game-plugin' ), value: 'DESC' }
            ];

            // プレビュー用の説明文
            var previewText = '';
            if ( attributes.gameType === 'all' ) {
                previewText = __( 'All games list will be displayed', 'novel-game-plugin' );
            } else if ( attributes.gameTitle ) {
                previewText = __( 'Game "{title}" will be displayed', 'novel-game-plugin' ).replace( '{title}', attributes.gameTitle );
            } else {
                previewText = __( 'Please select a game', 'novel-game-plugin' );
            }

            return [
                // サイドバーの設定パネル
                el( InspectorControls, {},
                    el( PanelBody, {
                        title: __( 'Game Settings', 'novel-game-plugin' ),
                        initialOpen: true
                    },
                        // ゲームタイプ選択
                        el( SelectControl, {
                            label: __( 'Display Type', 'novel-game-plugin' ),
                            value: attributes.gameType,
                            options: gameTypeOptions,
                            onChange: onGameTypeChange
                        } ),

                        // 個別ゲーム選択（個別ゲームタイプの場合のみ表示）
                        attributes.gameType === 'single' && ! gamesList.isLoading && el( SelectControl, {
                            label: __( 'Game Selection', 'novel-game-plugin' ),
                            value: attributes.gameTitle,
                            options: gamesList.games,
                            onChange: onGameTitleChange
                        } ),

                        // ゲーム一覧の場合の詳細設定
                        attributes.gameType === 'all' && [
                            el( ToggleControl, {
                                key: 'showCount',
                                label: __( 'Show Scene Count', 'novel-game-plugin' ),
                                checked: attributes.showCount,
                                onChange: onShowCountChange
                            } ),

                            el( ToggleControl, {
                                key: 'showDescription',
                                label: __( 'Show Game Description', 'novel-game-plugin' ),
                                checked: attributes.showDescription,
                                onChange: onShowDescriptionChange
                            } ),

                            el( RangeControl, {
                                key: 'columns',
                                label: __( 'Number of Columns', 'novel-game-plugin' ),
                                value: attributes.columns,
                                onChange: onColumnsChange,
                                min: 1,
                                max: 6
                            } ),

                            el( SelectControl, {
                                key: 'orderby',
                                label: __( 'Sort Order', 'novel-game-plugin' ),
                                value: attributes.orderby,
                                options: orderByOptions,
                                onChange: onOrderByChange
                            } ),

                            el( SelectControl, {
                                key: 'order',
                                label: __( 'Order', 'novel-game-plugin' ),
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
                            label: __( 'Novel Game List', 'novel-game-plugin' ),
                            instructions: __( 'Loading game data...', 'novel-game-plugin' )
                        },
                            el( Spinner )
                        ) :
                        el( Placeholder, {
                            icon: 'games',
                            label: __( 'Novel Game List', 'novel-game-plugin' ),
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