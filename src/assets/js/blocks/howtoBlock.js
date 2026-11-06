import { registerBlockType } from '@wordpress/blocks';
import { TextControl, TextareaControl, Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

registerBlockType('quinrel/howto', {
    title: __('Quinrel HowTo Guide', 'quinrel'),
    icon: 'list-view',
    category: 'text',
    description: __('Step-by-step tutorial with automatic Schema.org HowTo markup.', 'quinrel'),
    attributes: {
        title: {
            type: 'string',
            default: '',
        },
        description: {
            type: 'string',
            default: '',
        },
        totalTime: {
            type: 'string',
            default: '',
        },
        estimatedCost: {
            type: 'string',
            default: '',
        },
        currency: {
            type: 'string',
            default: 'USD',
        },
        steps: {
            type: 'array',
            default: [{ title: '', text: '', imageUrl: '' }],
        },
    },
    edit: ({ attributes, setAttributes }) => {
        const { title, description, totalTime, estimatedCost, currency, steps } = attributes;

        const updateStep = (index, field, value) => {
            const next = steps.map((step, i) => (i === index ? { ...step, [field]: value } : step));
            setAttributes({ steps: next });
        };

        const addStep = () => {
            setAttributes({ steps: [...steps, { title: '', text: '', imageUrl: '' }] });
        };

        const removeStep = (index) => {
            if (steps.length <= 1) return;
            setAttributes({ steps: steps.filter((_, i) => i !== index) });
        };

        return (
            <div className="quinrel-editor-block-howto" style={{ border: '1px solid #cbd5e1', borderRadius: '8px', padding: '16px', background: '#f8fafc' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                    <span className="dashicons dashicons-list-view" style={{ color: '#0060F9' }} />
                    <strong style={{ fontSize: '15px' }}>{__('HowTo Guide & Schema', 'quinrel')}</strong>
                    <span style={{ fontSize: '11px', background: '#E0ECFE', color: '#0140AC', padding: '2px 8px', borderRadius: '9999px', marginLeft: 'auto' }}>
                        {__('Auto HowTo Schema', 'quinrel')}
                    </span>
                </div>

                <TextControl
                    label={__('Tutorial Title', 'quinrel')}
                    value={title}
                    onChange={(val) => setAttributes({ title: val })}
                    placeholder={__('e.g., How to Install WordPress on Docker', 'quinrel')}
                />

                <TextareaControl
                    label={__('Summary / Intro Description', 'quinrel')}
                    value={description}
                    onChange={(val) => setAttributes({ description: val })}
                    placeholder={__('Briefly explain what readers will achieve...', 'quinrel')}
                    rows={2}
                />

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '12px' }}>
                    <TextControl
                        label={__('Total Time (e.g. 30M, 2H)', 'quinrel')}
                        value={totalTime}
                        onChange={(val) => setAttributes({ totalTime: val })}
                    />
                    <TextControl
                        label={__('Estimated Cost', 'quinrel')}
                        value={estimatedCost}
                        onChange={(val) => setAttributes({ estimatedCost: val })}
                        placeholder="0"
                    />
                    <TextControl
                        label={__('Currency Code', 'quinrel')}
                        value={currency}
                        onChange={(val) => setAttributes({ currency: val })}
                    />
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', marginTop: '16px' }}>
                    <strong style={{ fontSize: '13px', color: '#334155' }}>{__('Tutorial Steps', 'quinrel')}</strong>
                    {steps.map((step, idx) => (
                        <div key={idx} style={{ background: '#ffffff', border: '1px solid #e2e8f0', borderRadius: '6px', padding: '12px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                <span style={{ fontWeight: 600, fontSize: '12px', color: '#64748b' }}>
                                    {sprintf(__('Step %d', 'quinrel'), idx + 1)}
                                </span>
                                {steps.length > 1 && (
                                    <Button
                                        isDestructive
                                        isSmall
                                        variant="link"
                                        onClick={() => removeStep(idx)}
                                    >
                                        {__('Remove Step', 'quinrel')}
                                    </Button>
                                )}
                            </div>
                            <TextControl
                                label={__('Step Title', 'quinrel')}
                                value={step.title}
                                onChange={(val) => updateStep(idx, 'title', val)}
                                placeholder={__('e.g., Pull the Docker Image', 'quinrel')}
                            />
                            <TextareaControl
                                label={__('Step Instructions', 'quinrel')}
                                value={step.text}
                                onChange={(val) => updateStep(idx, 'text', val)}
                                rows={3}
                            />
                            <TextControl
                                label={__('Image URL (Optional)', 'quinrel')}
                                value={step.imageUrl}
                                onChange={(val) => updateStep(idx, 'imageUrl', val)}
                                placeholder="https://example.com/step-image.png"
                            />
                        </div>
                    ))}
                </div>

                <div style={{ marginTop: '12px' }}>
                    <Button variant="secondary" onClick={addStep}>
                        + {__('Add Step', 'quinrel')}
                    </Button>
                </div>
            </div>
        );
    },
    save: () => null,
});

