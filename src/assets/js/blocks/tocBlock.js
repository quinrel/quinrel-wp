import { registerBlockType } from '@wordpress/blocks';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('quinrel/toc', {
    title: __('Quinrel Table of Contents', 'quinrel'),
    icon: 'editor-ol',
    category: 'text',
    description: __('Dynamic heading navigation with automatic anchor links and ItemList schema.', 'quinrel'),
    attributes: {
        title: {
            type: 'string',
            default: 'Table of Contents',
        },
    },
    edit: ({ attributes, setAttributes }) => {
        const { title } = attributes;

        return (
            <div className="quinrel-editor-block-toc" style={{ border: '1px solid #cbd5e1', borderRadius: '8px', padding: '16px', background: '#f8fafc' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                    <span className="dashicons dashicons-editor-ol" style={{ color: '#0060F9' }} />
                    <strong style={{ fontSize: '15px' }}>{__('Table of Contents', 'quinrel')}</strong>
                    <span style={{ fontSize: '11px', background: '#E0ECFE', color: '#0140AC', padding: '2px 8px', borderRadius: '9999px', marginLeft: 'auto' }}>
                        {__('Auto ItemList Schema', 'quinrel')}
                    </span>
                </div>

                <TextControl
                    label={__('TOC Heading', 'quinrel')}
                    value={title}
                    onChange={(val) => setAttributes({ title: val })}
                />

                <div style={{ padding: '12px', background: '#ffffff', border: '1px dashed #94a3b8', borderRadius: '6px', color: '#64748b', fontSize: '13px' }}>
                    <p style={{ margin: 0 }}>
                        {__('This block automatically parses all H2, H3, and H4 headings in your article on the frontend and generates jump links and Schema.org ItemList markup.', 'quinrel')}
                    </p>
                </div>
            </div>
        );
    },
    save: () => null,
});

