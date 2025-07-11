/**
 * Content Archive Clipboard - Gutenberg Block
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, ToggleControl, SelectControl, RangeControl } = wp.components;
    const { __ } = wp.i18n;
    const { Fragment } = wp.element;

    registerBlockType('content-archive-clipboard/archive', {
        title: __('Content Archive', 'content-archive-clipboard'),
        description: __('Display a filterable archive of all blog posts with clipboard copy functionality.', 'content-archive-clipboard'),
        category: 'widgets',
        icon: 'archive',
        keywords: [
            __('archive', 'content-archive-clipboard'),
            __('posts', 'content-archive-clipboard'),
            __('clipboard', 'content-archive-clipboard'),
            __('copy', 'content-archive-clipboard')
        ],
        attributes: {
            postsPerPage: {
                type: 'number',
                default: -1
            },
            postType: {
                type: 'string',
                default: 'post'
            },
            showFilters: {
                type: 'boolean',
                default: true
            },
            showExport: {
                type: 'boolean',
                default: true
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { postsPerPage, postType, showFilters, showExport } = attributes;
            
            return (
                Fragment(null,
                    // Inspector Controls (Sidebar)
                    InspectorControls(null,
                        PanelBody({
                            title: __('Archive Settings', 'content-archive-clipboard'),
                            initialOpen: true
                        },
                            SelectControl({
                                label: __('Post Type', 'content-archive-clipboard'),
                                value: postType,
                                options: [
                                    { label: __('Posts', 'content-archive-clipboard'), value: 'post' },
                                    { label: __('Pages', 'content-archive-clipboard'), value: 'page' },
                                    { label: __('All Post Types', 'content-archive-clipboard'), value: 'any' }
                                ],
                                onChange: function(value) {
                                    setAttributes({ postType: value });
                                }
                            }),
                            
                            RangeControl({
                                label: __('Posts Per Page', 'content-archive-clipboard'),
                                value: postsPerPage === -1 ? 100 : postsPerPage,
                                onChange: function(value) {
                                    setAttributes({ postsPerPage: value === 100 ? -1 : value });
                                },
                                min: 1,
                                max: 100,
                                help: __('Set to 100 to show all posts', 'content-archive-clipboard')
                            }),
                            
                            ToggleControl({
                                label: __('Show Filters', 'content-archive-clipboard'),
                                checked: showFilters,
                                onChange: function(value) {
                                    setAttributes({ showFilters: value });
                                },
                                help: __('Display date and category filters', 'content-archive-clipboard')
                            }),
                            
                            ToggleControl({
                                label: __('Show Export Options', 'content-archive-clipboard'),
                                checked: showExport,
                                onChange: function(value) {
                                    setAttributes({ showExport: value });
                                },
                                help: __('Allow users to export the archive as CSV or Markdown', 'content-archive-clipboard')
                            })
                        )
                    ),
                    
                    // Block Preview in Editor
                    wp.element.createElement('div', {
                        className: 'content-archive-clipboard-preview'
                    },
                        wp.element.createElement('div', {
                            style: {
                                border: '2px dashed #ccc',
                                padding: '20px',
                                textAlign: 'center',
                                backgroundColor: '#f8f9fa',
                                borderRadius: '8px'
                            }
                        },
                            wp.element.createElement('div', {
                                style: {
                                    fontSize: '18px',
                                    fontWeight: 'bold',
                                    marginBottom: '10px',
                                    color: '#333'
                                }
                            }, __('📋 Content Archive', 'content-archive-clipboard')),
                            
                            wp.element.createElement('div', {
                                style: {
                                    fontSize: '14px',
                                    color: '#666',
                                    marginBottom: '15px'
                                }
                            }, __('Archive of all blog posts with clipboard functionality', 'content-archive-clipboard')),
                            
                            wp.element.createElement('div', {
                                style: {
                                    fontSize: '12px',
                                    color: '#888'
                                }
                            },
                                wp.element.createElement('div', null, 
                                    __('Post Type:', 'content-archive-clipboard') + ' ' + 
                                    (postType === 'post' ? __('Posts', 'content-archive-clipboard') : 
                                     postType === 'page' ? __('Pages', 'content-archive-clipboard') : 
                                     __('All Post Types', 'content-archive-clipboard'))
                                ),
                                wp.element.createElement('div', null, 
                                    __('Posts Per Page:', 'content-archive-clipboard') + ' ' + 
                                    (postsPerPage === -1 ? __('All', 'content-archive-clipboard') : postsPerPage)
                                ),
                                wp.element.createElement('div', null, 
                                    __('Filters:', 'content-archive-clipboard') + ' ' + 
                                    (showFilters ? __('Enabled', 'content-archive-clipboard') : __('Disabled', 'content-archive-clipboard'))
                                ),
                                wp.element.createElement('div', null, 
                                    __('Export:', 'content-archive-clipboard') + ' ' + 
                                    (showExport ? __('Enabled', 'content-archive-clipboard') : __('Disabled', 'content-archive-clipboard'))
                                )
                            )
                        )
                    )
                )
            );
        },
        
        save: function() {
            // Return null to use PHP render callback
            return null;
        }
    });
    
})(window.wp);