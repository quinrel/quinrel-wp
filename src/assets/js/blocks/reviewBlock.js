import { registerBlockType } from '@wordpress/blocks';
import { TextControl, TextareaControl, RangeControl, SelectControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('quinrel/review', {
    title: __('Quinrel Review Card', 'quinrel'),
    icon: 'star-filled',
    category: 'text',
    description: __('Product/critique review card with star ratings and Review schema.', 'quinrel'),
    attributes: {
        itemName: {
            type: 'string',
            default: '',
        },
        ratingValue: {
            type: 'number',
            default: 5.0,
        },
        itemType: {
            type: 'string',
            default: 'Product',
        },
        summary: {
            type: 'string',
            default: '',
        },
        pros: {
            type: 'array',
            default: [''],
        },
        cons: {
            type: 'array',
            default: [''],
        },
        affiliateUrl: {
            type: 'string',
            default: '',
        },
        affiliateText: {
            type: 'string',
            default: 'Check Price',
        },
    },
    edit: ({ attributes, setAttributes }) => {
        const { itemName, ratingValue, itemType, summary, pros, cons, affiliateUrl, affiliateText } = attributes;

        const updatePro = (index, value) => {
            const next = [...pros];
            next[index] = value;
            setAttributes({ pros: next });
        };

        const updateCon = (index, value) => {
            const next = [...cons];
            next[index] = value;
            setAttributes({ cons: next });
        };

        return (
            <div className="quinrel-editor-block-review" style={{ border: '1px solid #cbd5e1', borderRadius: '8px', padding: '16px', background: '#ffffff' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                    <span className="dashicons dashicons-star-filled" style={{ color: '#f59e0b' }} />
                    <strong style={{ fontSize: '15px' }}>{__('Review Card & Schema', 'quinrel')}</strong>
                    <span style={{ fontSize: '11px', background: '#fef3c7', color: '#92400e', padding: '2px 8px', borderRadius: '9999px', marginLeft: 'auto' }}>
                        {__('Auto Review Schema', 'quinrel')}
                    </span>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: '12px' }}>
                    <TextControl
                        label={__('Item / Product Name', 'quinrel')}
                        value={itemName}
                        onChange={(val) => setAttributes({ itemName: val })}
                        placeholder={__('e.g., Sony WH-1000XM5 Headphones', 'quinrel')}
                    />
                    <SelectControl
                        label={__('Item Type', 'quinrel')}
                        value={itemType}
                        options={[
                            { label: 'Product', value: 'Product' },
                            { label: 'SoftwareApplication', value: 'SoftwareApplication' },
                            { label: 'Book', value: 'Book' },
                            { label: 'Movie', value: 'Movie' },
                            { label: 'LocalBusiness / Restaurant', value: 'LocalBusiness' },
                        ]}
                        onChange={(val) => setAttributes({ itemType: val })}
                    />
                </div>

                <RangeControl
                    label={__('Rating Value (1.0 - 5.0)', 'quinrel')}
                    value={ratingValue}
                    onChange={(val) => setAttributes({ ratingValue: val })}
                    min={1}
                    max={5}
                    step={0.1}
                />

                <TextareaControl
                    label={__('Verdict / Executive Summary', 'quinrel')}
                    value={summary}
                    onChange={(val) => setAttributes({ summary: val })}
                    rows={2}
                />

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '12px' }}>
                    <div style={{ background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '6px', padding: '12px' }}>
                        <strong style={{ color: '#166534', fontSize: '13px' }}>✓ {__('Pros', 'quinrel')}</strong>
                        {pros.map((p, idx) => (
                            <div key={idx} style={{ marginTop: '8px' }}>
                                <TextControl
                                    value={p}
                                    onChange={(val) => updatePro(idx, val)}
                                    placeholder={__('Add a pro...', 'quinrel')}
                                />
                            </div>
                        ))}
                        <Button isSmall variant="secondary" onClick={() => setAttributes({ pros: [...pros, ''] })}>
                            + {__('Add Pro', 'quinrel')}
                        </Button>
                    </div>

                    <div style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '6px', padding: '12px' }}>
                        <strong style={{ color: '#991b1b', fontSize: '13px' }}>✗ {__('Cons', 'quinrel')}</strong>
                        {cons.map((c, idx) => (
                            <div key={idx} style={{ marginTop: '8px' }}>
                                <TextControl
                                    value={c}
                                    onChange={(val) => updateCon(idx, val)}
                                    placeholder={__('Add a con...', 'quinrel')}
                                />
                            </div>
                        ))}
                        <Button isSmall variant="secondary" onClick={() => setAttributes({ cons: [...cons, ''] })}>
                            + {__('Add Con', 'quinrel')}
                        </Button>
                    </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '12px' }}>
                    <TextControl
                        label={__('Call To Action URL (Optional)', 'quinrel')}
                        value={affiliateUrl}
                        onChange={(val) => setAttributes({ affiliateUrl: val })}
                        placeholder="https://retailer.example.com/item"
                    />
                    <TextControl
                        label={__('Button Label', 'quinrel')}
                        value={affiliateText}
                        onChange={(val) => setAttributes({ affiliateText: val })}
                    />
                </div>
            </div>
        );
    },
    save: () => null,
});

