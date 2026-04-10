/**
 * Whereabouts — Editor Script
 * Tag-selector toolbar + server-side preview via REST.
 */
(function(blocks, blockEditor, components, element, apiFetch) {
    'use strict';

    var registerBlockType   = blocks.registerBlockType;
    var useBlockProps       = blockEditor.useBlockProps;
    var BlockControls       = blockEditor.BlockControls;
    var ToolbarGroup        = components.ToolbarGroup;
    var ToolbarDropdownMenu = components.ToolbarDropdownMenu;
    var useEffect           = element.useEffect;
    var useState            = element.useState;
    var createElement       = element.createElement;
    var Fragment            = element.Fragment;

    var TAG_OPTIONS = [
        { label: 'Paragraph (p)',   value: 'p'    },
        { label: 'Heading 1 (h1)', value: 'h1'   },
        { label: 'Heading 2 (h2)', value: 'h2'   },
        { label: 'Heading 3 (h3)', value: 'h3'   },
        { label: 'Heading 4 (h4)', value: 'h4'   },
        { label: 'Inline (span)',  value: 'span'  },
    ];

    registerBlockType('whereabouts/sentence', {
        attributes: {
            tagName: { type: 'string', default: 'p' },
        },

        edit: function(props) {
            var tagName       = props.attributes.tagName || 'p';
            var setAttributes = props.setAttributes;
            var blockProps    = useBlockProps();
            var htmlState     = useState('<em style="opacity:.5">Loading…</em>');
            var html          = htmlState[0];
            var setHtml       = htmlState[1];

            useEffect(function() {
                apiFetch({ path: '/whereabouts/v1/preview?tag=' + tagName })
                    .then(function(data) { setHtml(data.html); })
                    .catch(function() {
                        setHtml('<em style="color:#c00">Preview unavailable — check Whereabouts settings.</em>');
                    });
            }, [tagName]);

            var tagLabel = (TAG_OPTIONS.find(function(o) { return o.value === tagName; }) || {}).label || tagName;
            var controls = TAG_OPTIONS.map(function(option) {
                return {
                    title:    option.label,
                    isActive: option.value === tagName,
                    onClick:  function() { setAttributes({ tagName: option.value }); }
                };
            });

            return createElement(Fragment, null,
                createElement(BlockControls, null,
                    createElement(ToolbarGroup, null,
                        createElement(ToolbarDropdownMenu, {
                            icon: 'editor-paragraph',
                            label: 'Tag: ' + tagLabel,
                            controls: controls
                        })
                    )
                ),
                createElement('div', Object.assign({}, blockProps, {
                    dangerouslySetInnerHTML: { __html: html }
                }))
            );
        },

        save: function() { return null; }
    });

}(
    window.wp.blocks,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.element,
    window.wp.apiFetch
));
