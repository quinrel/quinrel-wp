import { registerBlockType } from '@wordpress/blocks';
import { TextControl, TextareaControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('quinrel/faq', {
    title: __('Quinrel FAQ Accordion', 'quinrel'),
    icon: 'editor-help',
    category: 'text',
    description: __('Accessible FAQ accordion with automatic Google FAQPage schema.', 'quinrel'),
    attributes: {
        title: {
            type: 'string',
            default: '',
        },
        items: {
            type: 'array',
            default: [{ question: '', answer: '' }],
        },
    },
    edit: ({ attributes, setAttributes }) => {
        const { title, items } = attributes;

        const updateItem = (index, field, value) => {
            const next = items.map((item, i) => (i === index ? { ...item, [field]: value } : item));
            setAttributes({ items: next });
        };

        const addItem = () => {
            setAttributes({ items: [...items, { question: '', answer: '' }] });
        };

        const removeItem = (index) => {
            if (items.length <= 1) return;
            setAttributes({ items: items.filter((_, i) => i !== index) });
        };

        return (
            <div className="quinrel-editor-block-faq" style={{ border: '1px solid #cbd5e1', borderRadius: '8px', padding: '16px', background: '#f8fafc' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                    <span className="dashicons dashicons-editor-help" style={{ color: '#2563eb' }} />
                    <strong style={{ fontSize: '15px' }}>{__('FAQ Accordion & Schema', 'quinrel')}</strong>
                    <span style={{ fontSize: '11px', background: '#dbeafe', color: '#1e40af', padding: '2px 8px', borderRadius: '9999px', marginLeft: 'auto' }}>
                        {__('Auto FAQPage Schema', 'quinrel')}
                    </span>
                </div>

                <TextControl
                    label={__('Accordion Section Title (Optional)', 'quinrel')}
                    value={title}
                    onChange={(val) => setAttributes({ title: val })}
                    placeholder={__('e.g., Frequently Asked Questions', 'quinrel')}
                />

                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', marginTop: '12px' }}>
                    {items.map((item, idx) => (
                        <div key={idx} style={{ background: '#ffffff', border: '1px solid #e2e8f0', borderRadius: '6px', padding: '12px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                <span style={{ fontWeight: 600, fontSize: '12px', color: '#64748b' }}>
                                    {sprintf(__('Question #%d', 'quinrel'), idx + 1)}
                                </span>
                                {items.length > 1 && (
                                    <Button
                                        isDestructive
                                        isSmall
                                        variant="link"
                                        onClick={() => removeItem(idx)}
                                    >
                                        {__('Remove', 'quinrel')}
                                    </Button>
                                )}
                            </div>
                            <TextControl
                                label={__('Question', 'quinrel')}
                                value={item.question}
                                onChange={(val) => updateItem(idx, 'question', val)}
                                placeholder={__('Enter question text...', 'quinrel')}
                            />
                            <TextareaControl
                                label={__('Answer', 'quinrel')}
                                value={item.answer}
                                onChange={(val) => updateItem(idx, 'answer', val)}
                                placeholder={__('Enter answer explanation...', 'quinrel')}
                                rows={3}
                            />
                        </div>
                    ))}
                </div>

                <div style={{ marginTop: '12px' }}>
                    <Button variant="secondary" onClick={addItem}>
                        + {__('Add Question', 'quinrel')}
                    </Button>
                </div>
            </div>
        );
    },
    save: () => null,
});

