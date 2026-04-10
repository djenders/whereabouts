/**
 * Whereabouts — Editor Script (source)
 *
 * Build with: npm install && npm run build
 * This produces build/index.js which WordPress loads in the block editor.
 *
 * The pre-built build/index.js works without this build step — this file
 * is here for development and future modifications.
 */
import { registerBlockType }                    from '@wordpress/blocks';
import { useBlockProps, BlockControls }          from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarDropdownMenu }     from '@wordpress/components';
import { useEffect, useState }                   from '@wordpress/element';
import apiFetch                                  from '@wordpress/api-fetch';

const TAG_OPTIONS = [
    { label: 'Paragraph (p)',  value: 'p'    },
    { label: 'Heading 1 (h1)', value: 'h1'   },
    { label: 'Heading 2 (h2)', value: 'h2'   },
    { label: 'Heading 3 (h3)', value: 'h3'   },
    { label: 'Heading 4 (h4)', value: 'h4'   },
    { label: 'Inline (span)',  value: 'span'  },
];

registerBlockType( 'whereabouts/sentence', {

    attributes: {
        tagName: { type: 'string', default: 'p' },
    },

    edit: function Edit( { attributes, setAttributes } ) {
        const { tagName }           = attributes;
        const blockProps            = useBlockProps();
        const [ html, setHtml ]     = useState( '<em style="opacity:.5">Loading…</em>' );

        useEffect( () => {
            apiFetch( { path: `/whereabouts/v1/preview?tag=${tagName}` } )
                .then( ( data ) => setHtml( data.html ) )
                .catch( () =>
                    setHtml( '<em style="color:#c00">Preview unavailable — check Whereabouts settings.</em>' )
                );
        }, [ tagName ] );

        const tagLabel = TAG_OPTIONS.find( o => o.value === tagName )?.label ?? tagName;

        return (
            <>
                <BlockControls>
                    <ToolbarGroup>
                        <ToolbarDropdownMenu
                            icon="location"
                            label={ `Tag: ${ tagLabel }` }
                            controls={ TAG_OPTIONS.map( option => ( {
                                title:    option.label,
                                isActive: option.value === tagName,
                                onClick:  () => setAttributes( { tagName: option.value } ),
                            } ) ) }
                        />
                    </ToolbarGroup>
                </BlockControls>
                <div { ...blockProps }
                     dangerouslySetInnerHTML={ { __html: html } }
                />
            </>
        );
    },

    save: () => null,
} );
